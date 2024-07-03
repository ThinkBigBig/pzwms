<?php

namespace App\Models\Admin\V2;

use App\Logics\BaseLogic;
use App\Logics\traits\WmsAttribute;
use App\Logics\wms\Shop;
use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WmsOrder extends wmsBaseModel
{
    use HasFactory, SoftDeletes, WmsAttribute;
    protected $guarded = [];
    protected $table = 'wms_orders';

    // 0-暂存 1-审核中 2-审核通过 3-审核拒绝 4-已撤回 5-已暂停 6-已取消
    const STASH = 0;
    const WAIT_AUDIT = 1;
    const PASS = 2;
    const REJECT = 3;
    const REVOKE = 4;
    const PAUSE = 5;
    const CANCEL = 6;

    // 发货状态 0-待发货 1-发货中 2-部分发货 3-已发货 4-已取消
    const WAIT = 0; //待发货
    const ON_DELIVERY = 1; //发货中
    const PARTIAL_DELIVERY = 2; //部分发货
    const DELIVERED = 3; //已发货
    const CANCELED = 4; //已取消

    protected $casts = [
        // 'order_at' => 'datetime:Y-m-d H:i:s',
        'deadline' => 'datetime:Y-m-d H:i:s',
        // 'paysuccess_time' => 'datetime:Y-m-d H:i:s',
    ];

    static $active_status = [self::STASH, self::WAIT_AUDIT, self::PASS, self::PAUSE];

    protected $appends = ['type_txt', 'status_txt', 'source_type_txt', 'deliver_status_txt', 'payment_status_txt', 'order_platform_txt', 'tag_txt', 'deliver_url'];

    public function getTypeTxtAttribute(): string
    {
        return self::maps('type')[$this->type] ?? '';
    }

    public function getStatusTxtAttribute(): string
    {
        return self::maps('status')[$this->status] ?? '';
    }


    public function getSourceTypeTxtAttribute(): string
    {
        return self::maps('source')[$this->source] ?? '';
    }

    public function getDeliverStatusTxtAttribute(): string
    {
        // 0-待发货 1-发货中 2-部分发货 3-已发货 4-已取消
        return self::maps('deliver_status')[$this->deliver_status] ?? '';
    }

    static function maps($attr, $option = false)
    {
        $maps =  [
            'type' => [
                '0' => __('columns.wms_orders.table_title'), //'手工订单',
            ],
            'source' => [
                '0' => __('admin.wms.source.order'), //'手工订单',
            ],
            'payment_status' => [
                '1' => __('admin.wms.status.payment'), //'已支付',
                '0' => __('admin.wms.status.no_pay'), //'未支付',
            ],
            'deliver_status' => [
                '4' => __('admin.wms.status.cancel'), //'已取消',
                '3' => __('admin.wms.status.deliver'), //'已发货',
                '2' => __('admin.wms.status.deliver_part'), //'部分发货',
                '1' => __('admin.wms.status.deliver_ing'), //'发货中',
                '0' => __('admin.wms.status.wait_deliver'), //'待发货',
            ],
            'status' => [
                self::CANCEL =>  __('admin.wms.status.cancel'), //'已取消',
                self::PAUSE => __('admin.wms.status.pause'), //'暂停',
                self::REVOKE =>  __('admin.wms.status.revoke'), //'已撤回',
                self::REJECT =>  __('admin.wms.status.reject'), //'审核未通过',
                self::PASS =>  __('admin.wms.status.audit'), //'已审核',
                self::WAIT_AUDIT =>  __('admin.wms.status.audit_ing'), //'已提交',
                self::STASH =>  __('admin.wms.status.stash'), //'暂存',
            ],
        ];
        if (!$option) return $maps[$attr];
        return self::cloumnOptions($maps[$attr]);
    }
    public function getPaymentStatusTxtAttribute(): string
    {
        return self::maps('payment_status')[$this->payment_status] ?? '';
    }

    public function getTagTxtAttribute(): string
    {
        return '';
    }

    static function code()
    {
        $code = 'XSDD' . date('ymdHis') . rand(1000, 9999);
        $find = WmsOrder::where(['code' => $code])->value('code');
        if ($find) return self::code();
        return $code;
    }

    static function generateThirdNo()
    {
        return 'SGDD' . date('ymdHis') . rand(1000, 9999);
    }

    function orderUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'order_user');
    }

    function createUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'create_user_id');
    }

    function adminUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'admin_user_id');
    }

    function shipping()
    {
        return $this->hasMany(ObOrder::class, 'third_no', 'third_no')->where('type', 1)->orderBy('id', 'desc');
    }

    function request()
    {
        return $this->hasOne(ObOrder::class, 'third_no', 'third_no')->where('type', 1)->orderBy('id', 'desc');
    }

    function details()
    {
        return $this->hasMany(WmsOrderDetail::class, 'origin_code', 'code')->where('status', 1);
    }

    function afterSale()
    {
        return $this->hasMany(AfterSaleOrder::class, 'origin_code', 'code');
    }

    function activeAfterSale()
    {
        return $this->hasOne(WmsAfterSaleOrder::class, 'origin_code', 'code')->whereIn('status', [0, 1, 2, 4]);
    }

    function columnOptions()
    {
        return [
            'status' => self::maps('status', true),
            'deliver_status' => self::maps('deliver_status', true),
            'payment_status' => self::maps('payment_status', true),
            'shop_code' => Shop::selectOptions(),
            'warehouse_code' => BaseLogic::warehouseOptions(),
            'source_type' => self::maps('source', true),
            'create_user_id' => BaseLogic::adminUsers(),
        ];
    }

    public function searchSku($v){
        $bar_codes = ProductSpecAndBar::where('sku',$v)->pluck('bar_code')->toArray();
        $codes = WmsOrderDetail::whereIn('bar_code',$bar_codes)->pluck('origin_code')->toArray();
        return ['code',$codes];
    }

    public function searchProductSn($v){
        $product_ids = Product::where('product_sn',$v)->pluck('id')->toArray();
        $bar_codes = ProductSpecAndBar::whereIn('product_id',$product_ids)->pluck('bar_code')->toArray();
        $codes = WmsOrderDetail::whereIn('bar_code',$bar_codes)->pluck('origin_code')->toArray();
        return ['code',$codes];
    }

    function columns()
    {
        // statusOPtion
        // export=true 导出时展示 ，search=true 搜索查询时展示
        return [
            ['value' => 'tag', 'label' => '标记',  'export' => false],
            ['value' => 'seller_message', 'label' => '卖家留言', 'rowspan' => 2],
            ['value' => 'order_at', 'label' => '下单时间', 'rowspan' => 2],
            ['value' => 'third_no', 'label' => '电商单号', 'rowspan' => 2],
            ['value' => 'status', 'label' => '单据状态',  'export' => false],
            ['value' => 'status_txt', 'label' => '单据状态', 'search' => false, 'rowspan' => 2],
            ['value' => 'code', 'label' => '单据编码', 'rowspan' => 2],
            ['value' => 'deliver_status', 'label' => '发货状态',  'export' => false],
            ['value' => 'deliver_status_txt', 'label' => '发货状态', 'search' => false, 'rowspan' => 2],
            ['value' => 'payment_status', 'label' => '付款状态',  'export' => false],
            ['value' => 'payment_status_txt', 'label' => '付款状态', 'search' => false, 'rowspan' => 2],
            ['value' => 'buyer_account', 'label' => '买家账号', 'rowspan' => 2],
            ['value' => 'order_platform_txt', 'label' => '来源平台', 'search' => false, 'rowspan' => 2],
            ['value' => 'order_platform', 'label' => '来源平台', 'export' => false],
            ['value' => 'shop_name', 'label' => '店铺', 'rowspan' => 2, 'search' => false],
            ['value' => 'shop_code', 'label' => '店铺', 'export' => false],
            ['value' => 'warehouse_name', 'label' => '仓库',  'search' => false, 'rowspan' => 2],
            ['value' => 'warehouse_code', 'label' => '仓库',  'export' => false],
            ['value' => 'source_type', 'label' => '单据来源',  'export' => false],
            ['value' => 'source_type_txt', 'label' => '单据来源', 'search' => false, 'rowspan' => 2],
            // ['value' => 'wms_logistics_products.pickup_method_txt', 'label' => '提货方式', 'search' => false],
            ['value' => 'product_code', 'label' => '物流', 'export' => false],
            ['value' => 'product_name', 'label' => '物流', 'search' => false, 'rowspan' => 2],
            ['value' => 'deliver_no', 'label' => '物流单号', 'rowspan' => 2],
            ['value' => 'deliver_fee', 'label' => '物流费用', 'rowspan' => 2],
            ['value' => 'num', 'label' => '总数量', 'rowspan' => 2],
            ['value' => 'total_amount', 'label' => '订单总额', 'rowspan' => 2],
            ['value' => 'payment_amount', 'label' => '实际支付总额', 'rowspan' => 2],
            ['value' => 'discount_amount', 'label' => '优惠总额', 'rowspan' => 2],
            ['value' => 'remark', 'label' => '备注', 'rowspan' => 2,],
            ['value' => 'create_user_id', 'label' => '创建人',  'export' => false],
            ['value' => 'create_user', 'label' => '创建人', 'search' => false, 'rowspan' => 2],
            ['value' => 'created_at', 'label' => '创建时间', 'rowspan' => 2],
            ['value' => 'detail', 'label' => '产品明细', 'search' => false, 'index' => 1, 'colspan' => 19],
            ['value' => 'sku', 'label' => 'SKU编码', 'export' => false,'lang' => 'wms_spec_and_bar.sku', 'index' => 2],
            ['value' => 'product_sn', 'label' => '货号', 'lang' => 'wms_product.product_sn', 'export' => false,'index' => 2],
            ['value' => 'wms_product.name', 'label' => '品名', 'search' => false, 'index' => 2],
            ['value' => 'wms_product.product_sn', 'label' => '货号', 'search' => false, 'index' => 2],
            ['value' => 'wms_spec_and_bar.spec_one', 'label' => '规格', 'search' => false, 'index' => 2],
            ['value' => 'wms_spec_and_bar.sku', 'label' => 'SKU编码', 'search' => false, 'index' => 2],
            ['value' => 'wms_order_details.num', 'label' => '数量', 'search' => false, 'index' => 2],
            ['value' => 'wms_order_details.cost_price', 'label' => '成本额', 'search' => false, 'index' => 2],
            ['value' => 'wms_order_details.retails_price', 'label' => '零售价', 'search' => false, 'index' => 2],
            ['value' => 'wms_order_details.price', 'label' => '成交价', 'search' => false, 'index' => 2],
            ['value' => 'wms_order_details.amount', 'label' => '成交金额', 'search' => false, 'index' => 2],
            ['value' => 'wms_order_details.discount_amount', 'label' => '优惠额', 'search' => false, 'index' => 2],
            ['value' => 'wms_order_details.payment_amount', 'label' => '实际支付金额', 'search' => false, 'index' => 2],
            // ['value' => 'product_name', 'label' => '库存类型', 'search' => false, 'index' => 2],
            ['value' => 'wms_order_details.quality_type', 'label' => '质量类型', 'search' => false, 'index' => 2],
            ['value' => 'wms_order_details.quality_level', 'label' => '质量等级', 'search' => false, 'index' => 2],
            ['value' => 'wms_order_details.batch_no', 'label' => '批次号', 'search' => false, 'index' => 2],
            ['value' => 'wms_order_details.uniq_code', 'label' => '唯一码', 'search' => false, 'index' => 2],
            ['value' => 'wms_order_details.oversold_num', 'label' => '超卖数量', 'search' => false, 'index' => 2],
            ['value' => 'wms_order_details.refund_num', 'label' => '发货前退款数量', 'search' => false, 'index' => 2],
            // ['value' => 'product_name', 'label' => '发货数量', 'search' => false, 'index' => 2],
            ['value' => 'wms_order_details.sendout_refund_num', 'label' => '发货后仅退款数量', 'search' => false, 'index' => 2],
            ['value' => 'wms_order_details.return_num', 'label' => '退货数量', 'search' => false, 'index' => 2],
            // ['value' => 'code', 'label' => '原单号', 'search' => false, 'index' => 2],
            // ['value' => 'third_no', 'label' => '子电商单号', 'search' => false, 'index' => 2],
            ['value' => 'flag', 'lang'=>'wms.flag','label' => '旗帜',  'export' => false],

        ];
    }
}
