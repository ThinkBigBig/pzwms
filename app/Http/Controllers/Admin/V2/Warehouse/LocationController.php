<?php

namespace App\Http\Controllers\Admin\V2\Warehouse;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;


class LocationController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\WarehouseLocation';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = []; //获取全部Where条件
    protected $BA  = ['*']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $BLWhere  = [
        'location_code' => ['=', ''],
        'WHERE' => ['WHERE', ''],
    ]; //获取全部分页Where条件
    // protected $BL  = ['id','warehouse_code','warehouse_name','type','status','tag'];//获取全部分页选取字段 *是全部
    protected $BL  = ['*']; //获取全部分页选取字段 *是全部
    protected $BLOrder  = [['wms_area_location.created_at', 'desc'], ['wms_area_location.id', 'desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序

    protected $BaseCreateVat = [
        'location_code' => 'required|regex:/^[0-9A-Z-]*$/', //位置码
        'area_code' => 'required', //库区编码
        'warehouse_code' => 'required', //仓库编码

    ]; //新增验证

    protected $BaseCreateVatMsg = ['regex' => '位置码支持大写字母、数字和中划线'];
    protected $BaseCreate = [
        'location_code' => '',
        'area_code' => '',
        'warehouse_code' => '',
        'pick_number' => '',
        'type' => '',
        'tag' => '',
        'volume' => '',
        'status' => '',
        'notes' => '',
        'remark' => '',
        'created_at' => ['type', 'date'],
        'created_user' => ['type', 'user_id'],
    ]; //新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
    ]; //新增验证
    protected $BaseUpdate = [
        'area_code' => '',
        'warehouse_code' => '',
        'pick_number' => '',
        'type' => '',
        'tag' => '',
        'volume' => '',
        'status' => '',
        'notes' => '',
        'remark' => '',
        'updated_at' => ['type', 'date'],
        'admin_user_id' => ['type', 'user_id'],
    ]; //新增数据
    protected $BUWhere = ['id' => ['=', '']]; //新增数据
    protected $NAME;
    protected $exportField;

    protected $importFields;

    // protected $relationMap = ['area_code'=>['ware_area.warehouse.warehouse_name','ware_area.area_name']];

  
    public function setExcelField()
    {
        $this->NAME = __('excel.location.location_code');
        $this->exportField = [
            'location_code' => __('excel.location.location_code'),
            // 'area_code' => __('excel.location.area_code'),
            'wms_area.area_name' => __('excel.location.area_name'),
            // 'warehouse_code' =>  __('excel.warehouse.warehouse_code'),
            'warehouse_code' =>  __('excel.warehouse.title'),
            'pick_number' => __('excel.location.pick_number'),
            'status_txt' => __('excel.status_txt'),
            'is_able' => __('excel.location.is_able'),
            'type' => __('excel.location.type'),
            'tag' => __('excel.tag')
        ];
        $this->importFields = [
            'ware_area.warehouse.warehouse_code' => __('excel.warehouse.warehouse_code'),
            'ware_area.warehouse.warehouse_name' =>  __('excel.warehouse.title') . '|required',
            'ware_area.area_code' => __('excel.location.area_code'),
            'ware_area.area_name' =>  __('excel.location.area_name') . '|required',
            'location_code' => __('excel.location.location_code') . '|required',
            'pick_number' => __('excel.location.pick_number'),
            'status_txt' => __('excel.status_txt'),
            'is_able' => __('excel.location.is_able'),
            'type' => __('excel.location.type') . '|required',
            'tag' => __('excel.tag'),
        ];
    }

    public function _createFrom($create_data)
    {
        $create_data['admin_user_id'] = request()->header('user_id');
        if(!isset($create_data['status']))$create_data['status']=1;
        $create_data['is_able']=1;
        if (empty($create_data['pick_number'])) {
            $code = $create_data['location_code'];
            $pick_number = $this->model->getPickNumber($code);
            $create_data['pick_number'] = $pick_number;
        }
        if ($this->model->checkRepeat($create_data['warehouse_code'], $create_data['location_code'])) {
            return $create_data;
        } else {
            return $this->vdtError('编码重复');
        }
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
                    $res = $ndel_ids . '位置上有商品,删除失败!' . $ids . '删除成功！';
                } else {
                    return $this->error($ndel_ids . '位置上有商品,删除失败!');
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


    public function exportFormat()
    {
        $count = 2;
        $field = [
            'location_code' => __('excel.location.location_code'),
            'warehouse_code' => __('excel.warehouse.warehouse_code'),
            'warehouse.warehouse_name' => __('excel.warehouse_name'),
            'area_code' => __('excel.location.area_code'),
            'ware_area.area_name' => __('excel.location.area_name'),
            'pick_number' => __('excel.location.pick_number'),
            'status_txt' => __('excel.status_txt'),
            'is_able_txt' => __('excel.location.is_able'),
            'type_txt' => __('excel.location.type'),
            'remark' => __('excel.remark')
        ];
        $rule = [
            'required' => [
                'location_code', 'area_code'
            ],
            'auto' => [
                'created_user' => request()->header('user_id'),
            ],
            'uniq' => 'location_code',

        ];
        $this->exportField = $field;
        return [
            'name' => __('excel.location.location_code'),
            'count' => $count,
            'rule' => $rule,
            'field' => $field,
        ];
    }

    public function importFormat()
    {
        $count = 2;
        $field =  [
            'warehouse_code' => __('excel.warehouse.warehouse_code'),
            'warehouse_name' => __('excel.warehouse_name'),
            'location_code' => __('excel.location.location_code'),
            'area_code' => __('excel.location.area_code'),
            'area_name' => __('excel.location.area_name'),
            'pick_number' => __('excel.location.pick_number'),
            'status_txt' => __('excel.status_txt'),
            'is_able' => __('excel.location.is_able'),
            'type' => __('excel.location.type'),
            'tag' => __('excel.tag')
        ];
        $rule = [
            'required' => 'location_code',
            'auto' => [
                'created_user' => request()->header('user_id'),
                'status' => 1,
                'is_able' => 1,
                'pick_number' =>['methodField',"getPickNumber",['location_code']],
                'warehouse_code' =>['methodField',"getWarehouseCode",['warehouse_name']],
                'area_code' =>['methodField',"getAreaCode",['area_name','warehouse_code']],
            ],
            'uniq_columns' => 'location_code|checkRepeat?warehouse_code,location_code',
            'delete'=>['area_name','warehouse_name'],

        ];
        return [
            'count' => $count,
            'rule' => $rule,
            'field' => $field,
        ];
    }
}
