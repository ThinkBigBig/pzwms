<?php

namespace App\Http\Controllers\Admin\V2\Product;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class BrandsTenantController extends BaseController
{
    protected $BaseModels = 'App\Models\Admin\V2\ProductBrands';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = []; //获取全部Where条件
    protected $BA  = ['*']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $BLWhere  = [
        'name' => ['like', ''],
        'code' => ['=', ''],
        'WHERE' => ['WHERE', ''],
    ]; //获取全部分页Where条件
    protected $BL  = ['id', 'name', 'code', 'status', 'sort', 'note']; //获取全部分页选取字段 *是全部
    protected $BLOrder  = [['sort', 'desc'], ['created_at', 'desc'], ['id', 'desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序

    protected $BaseCreateVat = [
        'name' => 'required', //分类名称
    ]; //新增验证
    protected $BaseCreate = [
        'name' => '',
        'code' => '',
        'note' => '',
        'status' => '',
        'sort' => '',
        'created_at' => ['type', 'date'],
        'created_user' => ['type', 'user_id'],

    ]; //新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
    ]; //新增验证
    protected $BaseUpdate = [
        'name' => '',
        'note' => '',
        'status' => '',
        'sort' => '',
        'updated_at' => ['type', 'date'],
        'updated_user' => ['type', 'user_id'],

    ]; //新增数据
    protected $BUWhere = ['id' => ['=', '']]; //新增数据

    protected $NAME =  '商品品牌';
    protected $exportField;
    protected $importFields;
    protected $repeat = ['name', 'code'];

 
    public function setExcelField()
    {
        $this->exportField = [
            'name' => __('excel.product.brands.name'),
            'code' => __('excel.product.brands.code'),
            'status_txt' => __('excel.product.brands.status_txt'),
            'sort' => __('excel.product.brands.sort'),
            'note' => __('excel.remark')
        ];
        $this->importFields = [
            'name' => __('excel.product.brands.name') . '|required',
            'code' => __('excel.product.brands.code'),
            'status_txt' => __('excel.product.brands.status_txt'),
            'sort' => __('excel.product.brands.sort'),
            'note' => __('excel.remark')
        ];
    }


    public function _createFrom($create_data)
    {
        if (empty($create_data['code'])) {
            $create_data['code'] = $this::getErpCode('PP', 10);
        }
        if (!$this->checkRepeat('name', $create_data['name'])) return $this->vdtError(__('excel.response.name_repeat'));

        return $create_data;
    }

    public function BaseDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required',
        ], ['ids.required' => __('base.vdt')]);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $ids = $request->ids;

        $data = (new $this->BaseModels)->BaseDelete($ids);
        $res = [];
        if ($data) {
            if (is_array($data)) {
                $ids = implode(',', $data['del_ids']);
                $ndel_ids = implode(',', $data['ndel_ids']);
                if ($ids) {
                    $res = $ndel_ids . '品牌下存在商品,删除失败!' . $ids . '删除成功！';
                } else {
                    return $this->error($ndel_ids . '品牌下存在商品,删除失败!');
                }
            }
            $admin_id = Auth::id();
            DB::table('admin_logs')->insert([
                'admin_id' => $admin_id,
                'log_url' => empty($data['s']) ? '' : $data['s'],
                'log_ip' => get_ip(),
                'log_info' => '删除内容',
                'log_time' => date('Y-m-d H:i:s'),
                'log_info_details' => json_encode([$ids], true)
            ]);
            return $this->success($res);
        } else {
            return  $this->error();
        }
    }

    public function importFormat()
    {
        $count = 2;
        $field = $this->exportField;
        $rule = [
            'required' => $this->required,
            'auto' => [
                'created_user' => request()->header('user_id'),
                'code'=>['method',"getErpCode",['PP']],
            ],
            'uniq_columns' => ['name','code'],

        ];
        return [
            'count' => $count,
            'rule' => $rule,
            'field' => $field,
        ];
    }

}
