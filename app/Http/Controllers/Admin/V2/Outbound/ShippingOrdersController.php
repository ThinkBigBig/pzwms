<?php

namespace App\Http\Controllers\Admin\V2\Outbound;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class ShippingOrdersController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\ShippingOrders';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = []; //获取全部Where条件
    protected $BA  = ['*']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $code_field = 'ship_code';
    protected $BLWhere  = [
        'WHERE' => ['WHERE', ''],
        'NUMBER' => [['ship_code', 'ob_order.third_no', 'ob_order.deliver_no'], ''],
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
    // protected $exportDefault = [
    //     '发货单','已审核','已发货'
    // ];
    protected $exportField;

    public function setExcelField()
    {
        $this->NAME = __('excel.shipping_order.title');
        $this->exportField = [
            'type_txt' => __('excel.shipping_order.type'),
            'ship_code' => __('excel.shipping_order.ship_code'),
            'status_txt' => __('excel.shipping_order.status'),
            'request_status' => __('excel.shipping_order.request_status'),
            'ob_order.type_txt' => __('excel.shipping_order.ob_order_type_txt'),
            'ob_order.request_code' => __('excel.shipping_order.ob_order_request_code'),
            'ob_order.erp_no' => __('excel.shipping_order.ob_order_erp_no'),
            'ob_order.third_no' => __('excel.shipping_order.ob_order_third_no'),
            'sku_num' => __('excel.shipping_order.sku_num'),
            'actual_num' => __('excel.shipping_order.actual_num'),
            'quality_num' => __('excel.shipping_order.quality_num'),
            'defects_num' => __('excel.shipping_order.defects_num'),
            'users_txt.shipper_user' => __('excel.shipping_order.shipper_user'),
            'shipped_at' => __('excel.shipping_order.shipped_at'),
            'ob_order.warehouse_name' => __('excel.shipping_order.ob_order_warehouse_name'),
            'ob_order.deliver_type' => __('excel.shipping_order.ob_order_deliver_type'),
            'ob_order.deliver_no' => __('excel.shipping_order.ob_order_deliver_no'),
            'ed_print.updated_at' => __('excel.shipping_order.ed_print_updated_at'),
        ];
    }
}
