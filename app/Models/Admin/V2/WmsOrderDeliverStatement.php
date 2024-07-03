<?php

namespace App\Models\Admin\V2;


use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsOrderDeliverStatement extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'wms_order_deliver_statements';

    // product_type 产品类型 0:实物 1：虚拟 2：赠品 3：附属品 4：其他
    // inventory_type 库存类型 1-自营 2-寄卖

    protected $appends = ['product_type_txt', 'inventory_type_txt', 'cost_price'];

    public function getProductTypeTxtAttribute(): string
    {
        $map = [
            '1' => __('admin.wms.product.virtual'), //'虚拟',
            '2' => __('admin.wms.product.gift'), //'赠品',
            '3' => __('admin.wms.product.accessory'), //'附属品',
            '4' => __('admin.wms.product.other'), //'其他',
            '0' => __('admin.wms.product.real'), //'实物',
        ];
        return $map[$this->product_type] ?? '';
    }

    public function getInventoryTypeTxtAttribute(): string
    {
        $map = [
            '1' => __('admin.wms.inventory.self_support'), //自营
            '2' => __('admin.wms.inventory.consign'), //寄卖
        ];
        return $map[$this->inventory_type] ?? '';
    }

    // 成本价
    public function getCostPriceAttribute(): string
    {
        return bcdiv($this->cost_amount, $this->num,2);
    }

    function columns()
    {
        // export=true 导出时展示 ，search=true 搜索查询时展示
        return [
            ['value' => 'origin_code', 'label' => '单据编码'],
            ['value' => 'third_no', 'label' => '电商订单号'],
            ['value' => 'shop_name', 'label' => '店铺'],
            ['value' => 'order_at', 'label' => '下单时间'],
            ['value' => 'payment_at', 'label' => '支付时间'],
            ['value' => 'shipped_at', 'label' => '发货时间'],
            ['value' => 'category_name', 'label' => '产品分类'],
            ['value' => 'brand_name', 'label' => '品牌名称'],
            ['value' => 'sku', 'label' => 'SKU编码'],
            ['value' => 'product_sn', 'label' => '货号'],
            ['value' => 'name', 'label' => '品名'],
            ['value' => 'spec_one', 'label' => '规格'],
            ['value' => 'num', 'label' => '数量'],
            ['value' => 'retails_price', 'label' => '零售价'],
            ['value' => 'amount', 'label' => '成交价'],
            ['value' => 'discount_amount', 'label' => '优惠额'],
            ['value' => 'payment_amount', 'label' => '实际支付额'],
            ['value' => 'cost_price', 'label' => '成本价', 'search' => false],
            ['value' => 'cost_amount', 'label' => '成本额'],
            ['value' => 'gross_profit', 'label' => '毛利'],
            ['value' => 'gross_profit_rate', 'label' => '毛利率'],
            ['value' => 'freight', 'label' => '运费'],
            ['value' => 'product_type_txt', 'label' => '产品类型'],
            ['value' => 'sup_name', 'label' => '供应商'],
            ['value' => 'inventory_type_txt', 'label' => '库存类型'],
            ['value' => 'quality_type', 'label' => '质量类型'],
            ['value' => 'quality_level', 'label' => '质量等级'],
            ['value' => 'batch_no', 'label' => '批次号'],
            ['value' => 'uniq_code', 'label' => '唯一码'],
            ['value' => 'company_name', 'label' => '物流公司'],
            ['value' => 'deliver_no', 'label' => '物流单号'],
            ['value' => 'remark', 'label' => '备注',]
        ];
    }
}
