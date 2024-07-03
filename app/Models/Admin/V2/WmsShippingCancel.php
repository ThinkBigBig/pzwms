<?php

namespace App\Models\Admin\V2;


use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Logics\wms\AllocationTask;
class WmsShippingCancel extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'wms_shipping_cancel';

    const METHOD_CANCEL = 1; //取消出库
    const METHOD_STOCK = 2; //释放库存
    const METHOD_PUTAWAY = 3; //发货拦截

    const COMPLETE = 1; //已完成
    const PUTAWAY = 2; //已上架
    const WAIT_PUTAWAY = 3; //待上架
    const PUTAWAY_ING = 4; //上架中

    const CONFIRMED = 1; //已确认


    static function maps($attr, $option = false)
    {
        $maps = [
            'status' => [
                self::CONFIRMED => __('admin.wms.status.confirm'), //'已确认',
                '0' => __('admin.wms.status.wait_confirm'), //'待确认'
            ],
            'cancel_status' => [
                '4' => __('admin.wms.status.part_list'), //'部分上架',
                '3' => __('admin.wms.status.wait_list'), //'待上架',
                '2' => __('admin.wms.status.listed'), //'已上架',
                '1' => __('admin.wms.status.done'), //'已完成',
            ],
            'method' => [
                '3' => __('admin.wms.method.out_intercept'), //'发货拦截',
                '2' =>  __('admin.wms.method.release_inventory'), //'释放库存',
                '1' =>  __('admin.wms.method.cancel_inventory'), //'取消库存',
            ],
            'type' => [
                '3' => __('admin.wms.type.out_other'), //'其他出库',
                '2' => __('admin.wms.type.out_transfer'), //'调拨出库',
                '1' => __('admin.wms.type.out_sale'), //'销售出库',
            ],
        ];
        if (!$option) return $maps[$attr];
        return self::cloumnOptions($maps[$attr]);
    }


    protected $appends = ['type_txt', 'method_txt', 'cancel_status_txt', 'status_txt'];

    public function getStatusTxtAttribute(): string
    {
        return self::maps('status')[$this->status] ?? '';
    }

    public function getTypeTxtAttribute(): string
    {
        return self::maps('type')[$this->type] ?? '';
    }

    public function getMethodTxtAttribute(): string
    {
        return self::maps('method')[$this->method] ?? '';
    }

    public function getCancelStatusTxtAttribute(): string
    {
        return self::maps('cancel_status')[$this->cancel_status] ?? '';
    }

    function adminUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'admin_user_id');
    }

    function request()
    {
        return $this->hasOne(WmsShippingRequest::class, 'request_code', 'request_code');
    }

    function columnOptions()
    {
        return [
            'type' => WmsAllocationTask::maps('type', true),
            'status' => WmsAllocationTask::maps('status', true),
            'cancel_status' => WmsAllocationTask::maps('cancel_status', true),
            'method' => WmsAllocationTask::maps('method', true),
        ];
    }

    public $requiredColumns = [];

    function columns()
    {
        // export=true 导出时展示 ， search=true 搜索查询时展示
        return [
            ['value' => 'type', 'label' => '单据类型', 'export' => false],
            ['value' => 'type_txt', 'label' => '单据类型', 'search' => false],
            ['value' => 'code', 'label' => '单据编码'],
            ['value' => 'status', 'label' => '单据状态', 'export' => false],
            ['value' => 'status_txt', 'label' => '单据状态', 'search' => false],
            ['value' => 'cancel_status', 'label' => '取消状态', 'export' => false],
            ['value' => 'cancel_status_txt', 'label' => '取消状态', 'search' => false],
            ['value' => 'request_code', 'label' => '出库单编码'],
            ['value' => 'method', 'label' => '处理方式', 'export' => false],
            ['value' => 'method_txt', 'label' => '处理方式', 'search' => false],
            ['value' => 'third_no', 'label' => '第三方单据编码'],
            ['value' => 'created_at', 'label' => '下单时间'],
            ['value' => 'wms_shipping_request.created_at', 'label' => '来源单下单时间', 'export' => false],
            ['value' => 'request_order_at', 'label' => '来源单下单时间', 'search' => false],
            ['value' => 'cancel_num', 'label' => '应取消总数'],
            ['value' => 'canceled_num', 'label' => '已取消总数'],
            ['value' => 'wait_putaway_num', 'label' => '待上架总数'],
            ['value' => 'putaway_num', 'label' => '已上架总数'],
            ['value' => 'remark', 'label' => '备注'],
        ];
    }

    static function add($request, $method)
    {
        $cancel_code = AllocationTask::cancelCode();
        $cancel = WmsShippingCancel::create([
            'type' => $request->type,
            'code' => $cancel_code,
            'request_code' => $request->request_code,
            'warehouse_code' => $request->warehouse_code,
            'status' => WmsShippingCancel::CONFIRMED,
            'cancel_status' => WmsShippingCancel::COMPLETE,
            'method' => $method,
            'third_no' => $request->third_no,
            'cancel_num' => $request->payable_num,
            'canceled_num' => $request->payable_num,
            'create_user_id' => ADMIN_INFO['user_id'],
            'admin_user_id' => ADMIN_INFO['user_id'],
            'tenant_id' => ADMIN_INFO['tenant_id'],
        ]);
        return $cancel;
    }
}
