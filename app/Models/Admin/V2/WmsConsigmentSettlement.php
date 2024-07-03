<?php

namespace App\Models\Admin\V2;

use App\Logics\BaseLogic;
use App\Logics\wms\Warehouse;
use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsConsigmentSettlement extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'wms_consignment_settlement';

    static function maps($attr, $option = false)
    {
        $maps = [
            'status' => [
                "2" => __('admin.wms.status.audit'), //'已审核',
                "1" => __('admin.wms.status.confirm'), //'已确认,
            ],
            'type' => [
                '3' => __('admin.wms.type.refund'), //'仅退款',
                '2' => __('admin.wms.type.return'), //'退货退款',
                '1' => __('admin.wms.type.handwork'), //'手工订单',
                '0' => __('admin.wms.type.eshop'), //'电商订单',
            ],
            'stattlement_status' => [
                '3' => __('admin.wms.status.withdraw'), //'已提现',
                '2' =>  __('admin.wms.status.in_withdraw'), //'提现中',
                '1' =>  __('admin.wms.status.wait_withdraw'), //'待提现',
                '0' =>  __('admin.wms.status.wait_settle'), //'待结算',
            ],
        ];
        if (!$option) return $maps[$attr];
        return self::cloumnOptions($maps[$attr]);
    }

    protected $appends = ['status_txt', 'type_txt', 'stattlement_status_txt'];

    public function getStatusTxtAttribute()
    {
        return self::maps('status')[$this->status] ?? '';
    }

    public function getTypeTxtAttribute()
    {
        return self::maps('type')[$this->type] ?? '';
    }
    public function getStattlementStatusTxtAttribute()
    {
        return self::maps('stattlement_status')[$this->stattlement_status] ?? '';
    }

    function createUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'created_user');
    }

    function updateUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'updated_user');
    }


    function columnOptions()
    {
        return [
            'type'  => self::maps('type', true),
            'status'  => self::maps('status', true),
            'stattlement_status' => self::maps('stattlement_status', true),
        ];
    }

    public $requiredColumns = [];

    function columns()
    {
        // export=true 导出时展示 ， search=true 搜索查询时展示
        return [
            ['value' => 'stattlement_status', 'label' => '结算状态', 'export' => false],
            ['value' => 'stattlement_status_txt', 'label' => '结算状态', 'search' => false],
            ['value' => 'sup_name', 'label' => '供应商'],
            ['value' => 'type', 'label' => '单据类型', 'export' => false],
            ['value' => 'type_txt', 'label' => '单据类型', 'search' => false],
            ['value' => 'origin_code', 'label' => '单据编码'],
            ['value' => 'status', 'label' => '单据状态', 'export' => false],
            ['value' => 'status_txt', 'label' => '单据状态', 'search' => false],
            ['value' => 'confirm_at', 'label' => '确认时间'],
            ['value' => 'third_code', 'label' => '电商订单号'],
            ['value' => 'order_at', 'label' => '下单时间'],
            ['value' => 'sku', 'label' => 'SKU编码'],
            ['value' => 'product_sn', 'label' => '货号'],
            ['value' => 'product_name', 'label' => '品名'],
            ['value' => 'spec_one', 'label' => '规格'],
            ['value' => 'bid_price', 'label' => '出价'],
            ['value' => 'actual_deal_price', 'label' => '实际成交价'],
            ['value' => 'deal_price', 'label' => '成交价'],
            ['value' => 'payment_amount', 'label' => '实际支付金额'],
            ['value' => 'num', 'label' => '数量'],
            ['value' => 'quality_type', 'label' => '质量类型'],
            ['value' => 'quality_level', 'label' => '质量等级'],
            ['value' => 'subsidy_amount', 'label' => '平台补贴'],
            ['value' => 'bid_amount', 'label' => '出价总额'],
            ['value' => 'actual_deal_amount', 'label' => '实际成交总额'],
            ['value' => 'deal_amount', 'label' => '成交总额'],
            ['value' => 'rule_name', 'label' => '应用结算规则'],
            ['value' => 'stattlement_amount', 'label' => '结算总额'],
            ['value' => 'send_warehouse_name', 'label' => '发货仓库'],
            ['value' => 'return_warehouse_name', 'label' => '退货仓库'],
            ['value' => 'shop_name', 'label' => '店铺'],
            ['value' => 'order_channel', 'label' => '销售渠道'],
            ['value' => 'action_at', 'label' => '收发货时间'],
            ['value' => 'settlement_at', 'label' => '结算时间'],
            ['value' => 'apply_code', 'label' => '提现申请单编码'],
            ['value' => 'apply_at', 'label' => '提现申请时间'],
            ['value' => 'remark', 'label' => '备注'],
        ];
    }
}
