<?php

namespace App\Http\Controllers\Admin\V2\Product;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Http\Request;
use App\Transformers\AuthorizationTransformer;
use Illuminate\Support\Facades\Hash;
use Dingo\Api\Exception\StoreResourceFailedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\SkuStock;
use App\Handlers\HttpService;
use App\Imports\ProductImport;
use Maatwebsite\Excel\Facades\Excel;

class SpecAndBarController extends BaseController
{
    protected $BaseModels = 'App\Models\Admin\V2\ProductSpecAndBar';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = []; //获取全部Where条件
    protected $BA  = ['*']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $BLWhere  = [
        'code' => ['=', ''],
        'bar_code' => ['=', ''],
        'spec_one' => ['=', ''],
        'sku' => ['=', ''],
        'product_id' => ['=', ''],
        'WHERE' => ['WHERE', ''],

    ]; //获取全部分页Where条件
    protected $BL  = ['id', 'product_id', 'code', 'sku', 'bar_code', 'spec_one', 'remark']; //获取全部分页选取字段 *是全部
    protected $BLOrder  = [['created_at', 'desc'], ['id', 'desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序

    protected $BaseCreateVat = [
        // 'product_id' => 'required',//货号
        'spec_one' => 'required',
        'bar_code' => 'required',
    ]; //新增验证
    protected $BaseCreate = [
        'product_id' => '',
        'code' => '',
        'sku' => '',
        'bar_code' => '',
        'spec_one' => '',
        'spec_two' => '',
        'spec_three' => '',
        'remark' => '',
        'retails_price' => '',
        'tag_price' => '',
        'const_price' => '',
        'created_at' => ['type', 'date'],
        'created_user' => ['type', 'user_id'],
    ]; //新增数据

    protected $BaseUpdateVat = [
        'bar_code' => 'required_without:id',
        'spec_one' => 'required_without:id',
    ]; //新增验证
    protected $BaseUpdate = [
        'id' => '',
        'product_id' => '',
        'sku' => '',
        'bar_code' => '',
        'spec_one' => '',
        'spec_two' => '',
        'spec_three' => '',
        'retails_price' => '',
        'remark' => '',
        'tag_price' => '',
        'const_price' => '',
        'updated_at' => ['type', 'date'],
        'updated_user' => ['type', 'user_id'],

    ]; //新增数据
    protected $BUWhere = ['id' => ['=', '']]; //新增数据
    protected $NAME;
    protected $exportField;
    protected $importFields;
    protected $relationMap = ['product_id' => ['product.product_sn', 'product.name'], 'code' => 'sku'];
    protected $required = ['bar_code', 'product_id', 'spec_one'];

    public function setExcelField()
    {
        $this->NAME = __('excel.product.spec.title');
        $this->exportField = [
            'bar_code' => __('excel.product.spec.bar_code'),
            'product.product_sn' => __('excel.product.product.product_sn'),
            'product.name' => __('excel.product.product.name'),
            'sku' => __('excel.product.spec.sku'),
            'spec_one' => __('excel.product.spec.spec_one'),
        ];
        $this->importFields =   [
            'bar_code' => __('excel.product.spec.bar_code') . '|required',
            'product.product_sn' => __('excel.product.product.product_sn') . '|required',
            'product.name' => __('excel.product.product.name'),
            'sku' => __('excel.product.spec.sku'),
            'spec_one' => __('excel.product.spec.spec_one') . '|required',
        ];
    }
    public function validCreate($datas)
    {
        $spes = [];
        foreach ($datas as $k => $data) {
            $create_data = [];
            $msg = !empty($this->BaseCreateVatMsg) ? $this->BaseCreateVatMsg : [];
            $validator = Validator::make($data, $this->BaseCreateVat, $msg);
            if ($validator->fails()) return ['code' => 500, 'msg' =>  __('base.vdt')];

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
                    $create_data[$k] = request()->header('user_id');
                }
            }
            //增加租户id
            $tenant_id = request()->header('tenant_id');
            if ($tenant_id) $create_data['tenant_id'] = $tenant_id;
            $spes[] = $create_data;
        }
        return ['code' => 200, 'data' => $spes];
    }


    public function validUpdate($datas)
    {
        $update_spes = [];
        foreach ($datas as $k => $data) {
            $where_data = [];
            $update_data = [];
            $msg = !empty($this->BaseUpdateVatMsg) ? $this->BaseUpdateVatMsg : [];
            $validator = Validator::make($data, $this->BaseUpdateVat, $msg);
            if ($validator->fails()) return ['code' => 500, 'msg' => __('base.vdt')];
            if (empty($data['id'])) {
                if (!$this->checkRepeat('bar_code', $data['bar_code'])) return ['code' => 500, 'msg' => 'bar code repeat'];
            }
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
                    $update_data[$k] = request()->header('user_id');
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
            //增加租户id
            // $tenant_id = request()->header('tenant_id');
            // if($tenant_id )$update_data['tenant_id'] = $tenant_id;
            $spes[] = $update_data;
            $update_spes[] = ['where' => $where_data, 'update_data' => $update_data];
        }


        return ['code' => 200, 'data' => $update_spes];
    }

    public function importFormat()
    {
        $count = 2;
        $field = [
            'bar_code' => __('excel.product.spec.bar_code'),
            'product_id' =>  __('excel.product.product.product_sn'),
            'sku' => __('excel.product.spec.sku'),
            'spec_one' => __('excel.product.spec.spec_one'),
        ];
        $rule = [
            'required' => $this->required,
            'auto' => [
                'created_user' => request()->header('user_id'),
                'sku' => ['methodField', "getSku", ['product_id', 'spec_one']],
                'code' => ['field', 'sku'],
            ],
            'relation' => [
                'product_id' => [
                    'model' => ['App\Models\Admin\V2\Product', 'product_sn', 'id'],
                ],
            ],
            'uniq_columns' => ['sku', 'bar_code'],

        ];
        return [
            'count' => $count,
            'rule' => $rule,
            'field' => $field,
        ];
    }


    //sku商品列表
    public function skuList(Request $request)
    {
        $where_data = [];
        $msg = !empty($this->BaseLVatMsg) ? $this->BaseLVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseLVat, $msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);


        $data = $request->all();
        $cur_page   = !empty($data['cur_page']) ? $data['cur_page'] : 1;
        $size       = !empty($data['size']) ? $data['size'] : 10;

        //修改参数  request要存在或者BUWhere存在

        if (!empty($data['WHERE'])) {
            $data['WHERE'] = json_decode($data['WHERE'], true);
        }
        if (!empty($data['ORDER'])) {
            $this->BLOrder = array_merge(json_decode($data['ORDER'], true), $this->BLOrder);
        }
        // if (!empty($data['SHOW'])) {
        //     $this->BL=json_decode($data['SHOW'],true);

        // }

        foreach ($this->BLWhere as $B_k => $B_v) {
            // var_dump($B_k[1]);exit;
            if ($B_k == 'NUMBER') {
                $number = $request->get('number');
                if ($number) {
                    if ($number) {
                        $where_data[] = [$B_k, $B_v[0], $number];
                    }
                }
            }
            if (!empty($B_v) && empty($B_v[1]) && isset($data[$B_k])) {
                if ($B_v[0] == 'allLike') {
                    $where_data[] = ["concat({$B_v[2]}) like ?", $B_v[0], ["%{$data[$B_k]}%"]];
                    continue;
                }
                $where_data[] = [$B_k, $B_v[0], $data[$B_k]];
                continue;
            }
            if (!empty($B_v) && (!empty($B_v[1]) || $B_v[1] === 0)) {
                $where_data[] = [$B_k, $B_v[0], $B_v[1]];
                continue;
            }
        }
        $RData = (new $this->BaseModels)->skuList($where_data, $this->BL, $this->BLOrder, $cur_page, $size);
        return  $this->success($RData, __('base.success'));
    }

    public function BaseDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required',
        ], ['ids.required' => __('base.vdt')]);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $ids = $request->ids;
        list($res, $data) = (new $this->BaseModels)->del($ids);
        if (!$res) return $this->error($data);
        if ($res) {
            $admin_id = $request->header('user_id');
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

    public function BaseUpdate(Request $request)
    {
        $where_data = [];
        $update_data = [];
        $msg = !empty($this->BaseUpdateVatMsg) ? $this->BaseUpdateVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseUpdateVat, $msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        //根据配置传入参数
        $base_update = $this->BaseUpdate;
        $base_update['product_sn'] = '';
        foreach ($base_update as $k => $v) {
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
            if (!empty($v[0]) && !empty($v[1]) && $v[0] == 'type' && $v[1] == 'user_name') {
                $update_data[$k] = $request->header('user_name');
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
        list($res, $RData) = (new $this->BaseModels)->BaseUpdate($where_data, $update_data);
        if (!$res) return $this->error($RData);
        if ($RData) {
            $admin_id = $request->header('user_id');
            $update_data['where'] = $where_data;
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
            return  $this->error('无更新');
        }
    }
}
