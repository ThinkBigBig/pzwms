<?php

namespace App\Http\Controllers\Admin\V2\Product;

use App\Http\Controllers\Admin\V2\BaseController;
use App\Logics\RedisKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\Product';
    // protected $exportName = '\App\Exports\Test';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = []; //获取全部Where条件
    protected $BA  = ['*']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $BLWhere  = [
        'name' => ['like', ''],
        'WHERE' => ['WHERE', ''],
    ]; //获取全部分页Where条件
    protected $BL  = ['id', 'img', 'product_sn', 'name', 'category_id', 'type', 'brand_id', 'status', 'note', 'created_at']; //获取全部分页选取字段 *是全部
    protected $BLOrder  = [['sort', 'desc'], ['created_at', 'desc'], ['id', 'desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序

    protected $required = ['name', 'product_sn'];
    protected $BaseCreateVat = [
        'product_sn' => 'required', //货号
        'name'  => 'required', //名称
        'category_id' => 'required',
        'type' => 'required',
    ]; //新增验证
    protected $BaseCreate = [
        'brand_id' => '',
        'category_id' => '',
        'serie_id' => '',
        'name' => '',
        'img' => '',
        'product_sn' => '',
        'type' => '',
        'note' => '',
        'status' => '',
        'sort' => '',
        'specs' => '',
        'created_at' => ['type', 'date'],
        'created_user' => ['type', 'user_id'],
    ]; //新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
    ]; //新增验证
    protected $BaseUpdate = [
        'brand_id' => '',
        'category_id' => '',
        'serie_id' => '',
        'name' => '',
        'img' => '',
        // 'product_sn' => '',
        'type' => '',
        'note' => '',
        'status' => '',
        'remark' => '',
        'sort' => '',
        'updated_at' => ['type', 'date'],
        'updated_user' => ['type', 'user_id'],

    ]; //新增数据
    protected $BUWhere = ['id' => ['=', '']]; //新增数据

    protected $NAME ;
    protected $exportField;
    protected $importFields;
    protected $relationMap = ['category_id' => ['category.name'], 'brand_id' => ['brand.name']];
    protected $repeat = ['product_sn'];


    public function setExcelField()
    {
        $this->NAME =  __('excel.product.product.title');
        $this->exportField = [
            'img' => __('excel.product.product.img'),
            'product_sn' => __('excel.product.product.product_sn'),
            'name' => __('excel.product.product.name'),
            'category.name' => __('excel.product.product.category_name'),
            'type' => __('excel.product.product.type'),
            'brand.name' => __('excel.product.product.brand_name'),
            'status_txt' =>  __('excel.status_txt'),
            'note' =>  __('excel.remark'),
        ];
        $this->importFields = [
            'img' => __('excel.product.product.img'),
            'product_sn' => __('excel.product.product.product_sn') . '|required',
            'name' => __('excel.product.product.name') . '|required',
            'category.name' => __('excel.product.product.category_name') . '|required',
            'type' => __('excel.product.product.type'),
            'brand.name' => __('excel.product.product.brand_name'),
            'status_txt' =>  __('excel.status_txt'),
            'note' =>  __('excel.remark'),
        ];
    }

    public function BaseCreate(Request $request)
    {
        $create_data = [];
        $msg = !empty($this->BaseCreateVatMsg) ? $this->BaseCreateVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseCreateVat, $msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        $repeatRes = $this->setRepeat($request);
        if (is_object($repeatRes)) return $repeatRes;

        //根据配置传入参数
        foreach ($this->BaseCreate as $k => $v) {
            if (isset($data[$k]) && $data[$k] != '') {
                $create_data[$k] = $data[$k];
            }
            if (!empty($v[0]) && !empty($v[1]) && $v[0] == 'type' && $v[1] == 'time') {
                $create_data[$k] = time();
            }
            if (!empty($v[0]) && !empty($v[1]) && $v[0] == 'type' && $v[1] == 'date') {
                $create_data[$k] = date('Y-m-d H:i:s');
            }
            if (!empty($v[0]) && !empty($v[1]) && $v[0] == 'type' && $v[1] == 'user_id') {
                $create_data[$k] = $request->header('user_id');
            }
        }
        //修改参数  request要存在或者BUWhere存在
        if (method_exists($this, '_createFrom')) $create_data = $this->_createFrom($create_data);
        if (is_object($create_data)) return $create_data;

        //增加租户id
        $tenant_id = $request->header('tenant_id');
        if ($tenant_id) $create_data['tenant_id'] = $tenant_id;



        //处理规格数据
        $specs = $request->get('specs');
        if ($specs) {
            //添加了规格
            $specs = json_decode($specs, 1);
            $specs = (new SpecAndBarController($request))->validCreate($specs);
            if ($specs['code'] != 200) return $this->vdtError($specs['msg']);
            $create_data['specs'] = $specs['data'];
        }
        list($id, $msg) = (new $this->BaseModels)->BaseCreate($create_data);
        if ($id) {
            $data['id'] = $id;
            $admin_id = Auth::id();
            DB::table('admin_logs')->insert([
                'admin_id' => $admin_id,
                'log_url' => empty($data['s']) ? '' : $data['s'],
                'log_ip' => get_ip(),
                'log_info' => '新增内容',
                'log_time' => date('Y-m-d H:i:s'),
                'log_info_details' => json_encode($create_data, true)
            ]);
            return  $this->success($data, __('base.success'));
        } else {
            return  $this->error($msg);
        }
    }

    public function BaseUpdate(Request $request)
    {
        $where_data = [];
        $update_data = [];
        $msg = !empty($this->BaseUpdateVatMsg) ? $this->BaseUpdateVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseUpdateVat, $msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        $repeatRes = $this->setRepeat($request, 1);
        if (is_object($repeatRes)) return $repeatRes;
        //根据配置传入参数
        foreach ($this->BaseUpdate as $k => $v) {
            if (isset($data[$k]) && $data[$k] !== '') {
                $update_data[$k] = $data[$k];
            }
            if (!empty($v) && empty($v[1])) {
                $update_data[$k] = $v;
            }
            if (!empty($v[0]) && !empty($v[1]) && $v[0] == 'type' && $v[1] == 'time') {
                $update_data[$k] = time();
            }
            if (!empty($v[0]) && !empty($v[1]) && $v[0] == 'type' && $v[1] == 'date') {
                $update_data[$k] = date('Y-m-d H:i:s');
            }
            if (!empty($v[0]) && !empty($v[1]) && $v[0] == 'type' && $v[1] == 'user_id') {
                $update_data[$k] = $request->header('user_id');
            }
        }
        //修改参数  request要存在或者BUWhere存在
        foreach ($this->BUWhere as $B_k => $B_v) {
            if (isset($data[$B_k]) && empty($B_v[1]) && $data[$B_k] != '') {
                $where_data[] = [$B_k, $B_v[0], $data[$B_k]];
            }
            if (!empty($B_v) && !empty($B_v[1])) {
                $where_data[] = [$B_k, $B_v[0], $B_v[1]];
            }
        }


        //处理规格数据
        $specs = $request->get('specs');
        if ($specs) {
            //添加了规格
            $specs = json_decode($specs, 1);
            $specs = (new SpecAndBarController($request))->validUpdate($specs);
            if ($specs['code'] != 200) return $this->vdtError($specs['msg']);
            $update_data['specs'] = $specs['data'];
        }

        if (method_exists($this, '_updateFrom')) $update_data = $this->_updateFrom($update_data);
        list($res,$RData) = (new $this->BaseModels)->BaseUpdate($where_data, $update_data);
        if(!$res)return $this->error($RData);
        if ($RData) {
            $admin_id = Auth::id();
            DB::table('admin_logs')->insert([
                'admin_id' => $admin_id,
                'log_url' => empty($data['s']) ? '' : $data['s'],
                'log_ip' => get_ip(),
                'log_info' => '修改内容',
                'log_time' => date('Y-m-d H:i:s'),
                'log_info_details' => json_encode($update_data, true)
            ]);

            return  $this->success($data, __('base.success'));
        } else {
            return  $this->error();
        }
    }

    //新品维护
    public function addNewBar(Request $request)
    {
        $addNewVat = [
            'product_sn' => 'required', //货号
            'name'  => 'required', //名称
            'category_id' => 'required',
            'bar_code' => 'required',
            'spec_one' => 'required',
        ];
        $data = $request->all();
        $validator = Validator::make($data, $addNewVat);
        if ($validator->fails()) return $this->errorBadRequest($validator);

        //检查货号是否已存在
        $product_info = [
            'product_sn' => $data['product_sn'],
            'name' => $data['name'],
            'category_id' => $data['category_id'],
        ];
        if (!empty($data['img'])) $product_info['img'] = $data['img'];
        if (!empty($data['brand_id'])) $product_info['brand_id'] = $data['brand_id'];
        list($res, $id) = $this->model->checkProd($product_info);
        if (!$res) return $this->error($id);
        $bar_info = [
            'bar_code' => $data['bar_code'],
            'spec_one' => $data['spec_one'],
        ];
        if ($id == null) $id = $this->model->addProduct($product_info);
        if (empty($id)) return $this->error();
        list($row, $msg) = $this->model->addBar($id, $data['product_sn'], $bar_info);
        if (empty($row)) return $this->error($msg);
        return $this->success();
    }

    public function exportFormat()
    {
        $name = $this->NAME;
        $field = [
            'product_sn' => __('excel.product.product.product_sn'),
            'name' => __('excel.product.product.name'),
            'category.code' => __('excel.product.category.code'),
            'type' =>  __('excel.product.product.type'),
            'brand.code' => __('excel.product.brands.code'),
        ];
        $detail = [
            'name' =>__('excel.product.product.spec_detail') ,
            'with_as' => 'specs',
            'field' => [
                'sku' => __('excel.product.spec.sku'),
                'code' =>__('excel.product.spec.code'),
                'spec_one' => __('excel.product.spec.spec_one'),
                'bar_code' => __('excel.product.spec.bar_code'),
                'const_price' => __('excel.product.spec.const_price'),
                'tag_price' =>__('excel.product.spec.tag_price'),
                'retails_price' => __('excel.product.spec.retails_price'),
                'remark' =>__('excel.remark'),
            ]

        ];
        $format = ['name' => ['color' => 'red']];
        return [
            'name' => $name,
            'format' => $format,
            'field' => $field,
            'detail' => $detail,
        ];
    }


    public function importFormat()
    {
        $count = 2;
        $field = [
            'img' => __('excel.product.product.img'),
            'product_sn' => __('excel.product.product.product_sn'),
            'name' => __('excel.product.product.name'),
            'category_id' => __('excel.product.product.category_name'),
            'type' => __('excel.product.product.type'),
            'brand_id' => __('excel.product.product.brand_name'),
            'status' =>  __('excel.status_txt'),
            'note' =>  __('excel.remark'),
        ];

        $rule = [
            'required' => $this->required,
            'auto' => [
                'created_user' => request()->header('user_id'),
            ],
            'relation' => [
                'category_id' => [
                    'model' => ['App\Models\Admin\V2\ProductCategory', 'name', 'id'],
                    'default' => '',
                ],
                'brand_id' => [
                    'model' => ['App\Models\Admin\V2\ProductBrands', 'name', 'id'],
                    'default' => '',
                ],
            ],
            'uniq_columns' => ['product_sn'],
        ];
        return [
            'count' => $count,
            'rule' => $rule,
            'field' => $field,
        ];
    }

    // 导入成功后同步商品图片
    public function afterImport()
    {
        $data = [
            'params' => ['tenant_id' => ADMIN_INFO['tenant_id']],
            'class' => 'App\Logics\wms\Product',
            'method' => 'imgSync',
            'time' => date('Y-m-d H:i:s'),
        ];
        Redis::rpush(RedisKey::QUEUE2_AYSNC_HADNLE, json_encode($data));
    }


    public function BaseDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required',
        ], ['ids.required' => __('base.vdt')]);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $ids = $request->ids;
        list($res,$data) = (new $this->BaseModels)->del($ids);
        if(!$res) return $this->error($data);
        if ($data) {
            $admin_id = Auth::id();
            DB::table('admin_logs')->insert([
                'admin_id' => $admin_id,
                'log_url' => empty($data['s']) ? '' : $data['s'],
                'log_ip' => get_ip(),
                'log_info' => '删除内容',
                'log_time' => date('Y-m-d H:i:s'),
                'log_info_details' => json_encode([$ids], true)
            ]);
            return $this->success($data);
        } else {
            return  $this->error();
        }
    }

    public function Export(Request $request)
    {
        
        $params = $this->_beforeExport($request);
        $res = $this->BaseLimit($request)->getData(1);
        if ($res['code'] != 200) return $this->error($res['msg']);
        $data = $res['data']['data'];
        ob_end_clean();
        ob_start();
        return  Excel::download((new $this->exportName($data, $params)), $this->NAME . date('YmdHis') . mt_rand(100, 999) . '.xlsx');
    }

    public function getProName(Request $request){
       
       $data =  $this->model->getProName($request->get('product_sn',''));
       if(!$data) return $this->error(__('response.product_not_exists'));
       return $this->success($data);
    }
}
