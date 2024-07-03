<?php

namespace App\Http\Controllers\Admin\V2\Outbound;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Models\Admin\V2\WmsOptionLog;

use App\Models\Admin\V2\preAllocationDetail;

class PreStrategyController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\PreAllocationStrategy';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = []; //获取全部Where条件
    protected $BA  = ['*']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $BLWhere  = [
        'WHERE' => ['WHERE', ''],
        'NUMBER' => [['startegy_code', 'warehouse_name', 'name'], ''],
        'ORDER' => ['ORDER', ''],
    ]; //获取全部分页Where条件
    protected $BL  = ['*']; //获取全部分页选取字段 *是全部
    protected $BLOrder  = [['created_at', 'desc'], ['id', 'desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序
    protected $BaseCreateVat = [
        'type' => 'required|regex:/[1-3]/', //类型
        // 'warehouse_name'=>'required',//名称
        'warehouse_code' => 'required',
        'name' => 'required',
        'sort' => 'required',
        'content' => 'required',

    ]; //新增验证
    protected $BaseCreate = [
        'warehouse_code' => '',
        'warehouse_name' => '',
        'name' => '',
        'type' => '',
        'sort' => '',
        'status' => '',
        'condition' => '',
        'content' => '',
        'remark' => '',
        'created_at' => ['type', 'date'],
        'create_user_id' => ['type', 'user_id'],
    ]; //新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',

    ]; //新增验证
    protected $BaseUpdate = [
        'warehouse_code' => '',
        'warehouse_name' => '',
        'name' => '',
        'type' => '',
        'sort' => '',
        'status' => '',
        'condition' => '',
        'content' => '',
        'remark' => '',
        'updated_at' => ['type', 'date'],
        'admin_user_id' => ['type', 'user_id'],
    ]; //新增数据
    protected $BUWhere = ['id' => ['=', '']]; //新增数据

    protected $NAME;
    protected $exportField;

    public function setExcelField()
    {
        $this->NAME = __('excel.pre_strategy.title');
        $this->exportField = [
            'warehouse_name' => __('excel.warehouse_name'),
            'startegy_code' => __('excel.pre_strategy.startegy_code'),
            'name' => __('excel.pre_strategy.name'),
            'type_txt' => __('excel.pre_strategy.type_txt'),
            'sort' => __('excel.pre_strategy.sort'),
            'status_txt' => __('excel.pre_strategy.status_txt'),
            'remark' => __('excel.remark'),
            'users_txt.create_user' => __('excel.pre_strategy.create_user'),
            'created_at' => __('excel.pre_strategy.created_at'),
            'users_txt.admin_user' => __('excel.pre_strategy.admin_user'),
            'updated_at' => __('excel.pre_strategy.updated_at'),

        ];
    }
    public function  _createFrom($create_data)
    {
        $create_data['startegy_code'] = $this->getErpCode('PHCL');
        $create_data['warehouse_name'] = $this->model->getWarehouseName($create_data['warehouse_code']);
        // $create_data[]='';
        WmsOptionLog::add(WmsOptionLog::PHCL, $create_data['startegy_code'], '创建', '配货策略创建成功', $create_data);

        return $create_data;
    }
}
