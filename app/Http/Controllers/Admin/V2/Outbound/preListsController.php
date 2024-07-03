<?php

namespace App\Http\Controllers\Admin\V2\Outbound;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Models\Admin\V2\UniqCodePrintLog;


class preListsController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\preAllocationLists';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = []; //获取全部Where条件
    protected $BA  = ['*']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $BLWhere  = [
        'WHERE' => ['WHERE', ''],
        'NUMBER' => [['pre_alloction_code', 'request_code', 'ob_order.deliver_no','ob_order.third_no'], ''],
        'ORDER' => ['ORDER', ''],
    ]; //获取全部分页Where条件
    protected $BL  = ['*']; //获取全部分页选取字段 *是全部
    protected $BLOrder  = [['created_at', 'desc'], ['id', 'desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序
    protected $code_field = 'pre_alloction_code';
    protected $BaseCreateVat = [
        'type' => 'required|regex:/[1-3]/', //类型
        'warehouse_name' => 'required', //名称
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
        $this->NAME = __('excel.pre_lists.title');
        $this->exportField = [
            'type_txt' => __('excel.pre_lists.type_txt'),
            'pre_alloction_code' => __('excel.pre_lists.pre_alloction_code'),
            'origin_type_txt' => __('excel.pre_lists.origin_type_txt'),
            'request_code' => __('excel.pre_lists.request_code'),
            'ob_order.erp_no' => __('excel.pre_lists.ob_order_erp_no'),
            'ob_order.third_no' => __('excel.pre_lists.ob_order_third_no'),
            'ob_order.paysuccess_time' => __('excel.pre_lists.ob_order_paysuccess_time'),
            'status' => __('excel.pre_lists.status'),
            'allocation_status' => __('excel.pre_lists.allocation_status'),
            'created_at' => __('excel.pre_lists.created_at'),
            'warehouse_code' => __('excel.warehouse_name'),
            'sku_num' => __('excel.pre_lists.sku_num'),
            'pre_num' => __('excel.pre_lists.pre_num'),
            'cancel_num' => __('excel.pre_lists.cancel_num'),
            'actual_num' => __('excel.pre_lists.actual_num'),
            'diff_num' => __('excel.pre_lists.diff_num'),
            'ob_order.order_platform' => __('excel.pre_lists.ob_order_order_platform'),
            'ob_order.order_channel' => __('excel.pre_lists.ob_order_order_channel'),
            'ob_order.deliver_type' => __('excel.pre_lists.ob_order_deliver_type'),
            'ob_order.deliver_no' => __('excel.pre_lists.ob_order_deliver_no'),
            'remark' => __('excel.remark'),
        ];
    }

    public function _exportFiledEdit(){
        $update = [
            __('excel.warehouse_name')=>'wh_name',
        ];
        return $update;
    }

    //打印快递单
    public function printEdNo(Request $request)
    {
        $vat = [
            'request_code' => 'required',
        ];
        $data = $this->vatReturn($request, $vat);
        list($res, $msg) = $this->model->printEdNo($data['request_code']);
        if ($res) return $this->success($msg);
        return $this->error($msg);
    }

    //流入配货池
    public function toPool(Request $request)
    {
        $vat = [
            'ids' => 'required',
        ];
        $data = $this->vatReturn($request, $vat);
        return $this->modelReturn('toPool', [$data]);
    }
}
