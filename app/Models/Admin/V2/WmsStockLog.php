<?php

namespace App\Models\Admin\V2;


use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WmsStockLog extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = "wms_stock_logs";

    const OP_RECEIVE = 1; //收货
    const OP_QUALITY = 2; //质量类型调整
    const OP_QC = 3; //质检
    const OP_SUPPLIER = 4; //供应商调整单
    const OP_SHELF = 5; //上架
    const OP_DWON = 6; //下架
    const OP_STOCK_LOCK = 7; //出库锁定
    const OP_ALLOCATE = 8; //配货
    const OP_SENDOUT = 9; //发货
    const OP_MOVE_SHEFL = 10; //移位上架
    const OP_MOVE_DWON = 11; //移位下架
    const OP_LEAK_BACK = 12; //少货寻回
    const OP_CANCEL_OUT = 13; //取消出库
    const OP_LEAK_FREEZE = 14; //少货冻结
    const OP_QUICK_MOVE = 15; //快速移位
    const OP_PRODUCT_CHANGE = 16; //产品调整
    const OP_ALLOCATE_RETRY = 17; //重配释放
    const OP_IN_INVALID = 18; //入库作废
    const OP_RELEASE_STOCK = 19; //释放库存

    const ORDER_RECEIVE = 1; //收货单
    const ORDER_QC = 2; //质检单
    const ORDER_QC_CONFIRM = 3; //质检确认单
    const ORDER_PUTAWAY = 4; //入库上架单
    const ORDER_ALLOCATE = 5; //配货订单
    const ORDER_SENDOUT = 6; //发货单
    const ORDER_CKD = 7; //出库单
    const ORDER_SUPPLIER = 8; //供应商调整单
    const ORDER_QC_CAHNGE = 9; //质量类型调整
    const ORDER_SALE = 10; //一般交易出库单
    const ORDER_TRANSFER_RECEIVE = 11; //调拨收货
    const ORDER_MOVE_UP = 12; //移位上架单
    const ORDER_MOVE_ALLOCATE = 13; //移位配货订单
    const ORDER_TRANSFER_OUT = 14; //调拨出库
    const ORDER_MOVE_APPLY = 15; //动盘申请单
    const ORDER_RETURN_RECEIVE = 16; //退货收货
    const ORDER_OTHER_OUT = 17; //其他出库
    const ORDER_CHECK = 18; //动态盘点单
    const ORDER_QUICK_MOVE = 19; //快速移位单
    const ORDER_MOVE = 20; //中转移位单
    const ORDER_BUY_REGISTER = 21; //采购到货登记
    const ORDER_TRANSFER_REGISTER = 22; //调拨到货登记
    const ORDER_RETURN_REGISTER = 23; //退货到货登记
    const ORDER_CANCEL = 24; //一般交易出库取消单
    const ORDER_OTHER_RECEIVE = 25; //其他收货
    const ORDER_CANCEL_PUTAWAY = 26; //取消单上架单
    const ORDER_TRANSFER_CANCEL = 27; //调拨出库取消单
    const ORDER_OTHER_CANCEL = 28; //其他出库取消单
    const ORDER_SALE_CANCEL = 29; //销售出库取消单
    const ORDER_CANCEL_OUT = 30; //出库取消
    const ORDER_RKD = 31; //入库单
    const ORDER_DJD = 32; //登记单
    const ORDER_YWD = 33; //移位单
    const ORDER_PDSQ = 34; //盘点申请
    const ORDER_QXCKD = 35; //出库取消单
    const ORDER_PDD = 36; //盘点单
    const ORDER_SHD = 37; //收货单
    const ORDER_ZJD = 38; //质检单
    const ORDER_SJD = 39; //上架单

    protected $appends = ['operation_txt', 'type_txt', 'quality_type_txt'];

    static function maps($attr, $option = false)
    {
        $maps = [
            'operation' => [
                self::OP_SENDOUT => __('admin.wms.action.sendout'), //'发货',
                self::OP_RECEIVE => __('admin.wms.action.receive'), //'收货',
                self::OP_QUALITY => __('admin.wms.action.qc_change'), //'质量类型调整',
                self::OP_QC => __('admin.wms.action.qc'), //'质检',
                self::OP_SUPPLIER => __('admin.wms.action.suppiler_change'), //'供应商调整单',
                self::OP_SHELF => __('admin.wms.action.shelf'), //'上架',
                self::OP_DWON => __('admin.wms.action.down'), //'下架',
                self::OP_STOCK_LOCK => __('admin.wms.action.out_lock'), //'出库锁定',
                self::OP_ALLOCATE => __('admin.wms.action.allocate'), //'配货',
                self::OP_MOVE_SHEFL => __('admin.wms.action.move_shelf'), //'移位上架',
                self::OP_MOVE_DWON => __('admin.wms.action.move_down'), //'移位下架',
                self::OP_LEAK_BACK => __('admin.wms.action.leak_back'), //'少货寻回',
                self::OP_CANCEL_OUT => __('admin.wms.action.cancel_out'), //'取消出库',
                self::OP_LEAK_FREEZE => __('admin.wms.action.leak_freeze'), //'少货冻结',
                self::OP_QUICK_MOVE => __('admin.wms.action.quick_move'), //'快速移位',
                self::OP_PRODUCT_CHANGE => __('admin.wms.action.product_change'), //'产品调整',
                self::OP_ALLOCATE_RETRY => __('admin.wms.action.allocate_retry'), //'重配释放',
                self::OP_IN_INVALID => __('admin.wms.action.in_invalid'), //'入库作废',
            ],
            'type' => [
                self::ORDER_SENDOUT => __('admin.wms.order.sendout'), //'发货单',
                self::ORDER_RECEIVE => __('admin.wms.order.receive'), //'收货单',
                self::ORDER_QC => __('admin.wms.order.qc'), //'质检单',
                self::ORDER_QC_CONFIRM => __('admin.wms.order.qc_confirm'), //'质检确认单',
                self::ORDER_PUTAWAY => __('admin.wms.order.putaway'), //'入库上架单',
                self::ORDER_ALLOCATE => __('admin.wms.order.allocate'), //'配货订单',
                self::ORDER_SUPPLIER => __('admin.wms.order.suppiler_change'), //'供应商调整单',
                self::ORDER_QC_CAHNGE => __('admin.wms.order.qc_change'), //'质量类型调整',
                self::ORDER_SALE => __('admin.wms.order.sale'), //'一般交易出库单',
                self::ORDER_TRANSFER_RECEIVE => __('admin.wms.order.transfer'), //'调拨收货',
                self::ORDER_MOVE_UP => __('admin.wms.order.move_up'), //'移位上架单',
                self::ORDER_MOVE_ALLOCATE => __('admin.wms.order.move_allocate'), //'移位配货订单',
                self::ORDER_TRANSFER_OUT => __('admin.wms.order.transfer_out'), //'调拨出库',
                self::ORDER_MOVE_APPLY => __('admin.wms.order.move_apply'), //'动盘申请单',
                self::ORDER_RETURN_RECEIVE => __('admin.wms.order.return_receive'), //'退货收货',
                self::ORDER_OTHER_OUT => __('admin.wms.order.other_out'), //'其他出库',
                self::ORDER_CHECK => __('admin.wms.order.check'), //'动态盘点单',
                self::ORDER_QUICK_MOVE => __('admin.wms.order.quick_move'), //'快速移位单',
                self::ORDER_MOVE => __('admin.wms.order.move'), //'中转移位单',
                self::ORDER_BUY_REGISTER => __('admin.wms.order.buy_register'), //'采购到货登记',
                self::ORDER_TRANSFER_REGISTER => __('admin.wms.order.transfer_register'), //'调拨到货登记',
                self::ORDER_RETURN_REGISTER => __('admin.wms.order.return_register'), //'退货到货登记',
                self::ORDER_CANCEL => __('admin.wms.order.order_cancel'), //'一般交易出库取消单',
                self::ORDER_OTHER_RECEIVE => __('admin.wms.order.other_receive'), //'其他收货',
                self::ORDER_CANCEL_PUTAWAY => __('admin.wms.order.cancel_putaway'), //'取消单上架单',
                self::ORDER_TRANSFER_CANCEL => __('admin.wms.order.transfer_cancel'), //'调拨出库取消单',
                self::ORDER_OTHER_CANCEL => __('admin.wms.order.other_cancel'), //'其他出库取消单',
                self::ORDER_SALE_CANCEL => __('admin.wms.order.sale_cancel'), //'销售出库取消单',
                // self::ORDER_CKD => __('admin.wms.order.out'), //'出库单',
            ],
        ];
        if (!$option) return $maps[$attr];
        return self::cloumnOptions($maps[$attr]);
    }

    public function getOperationTxtAttribute(): string
    {
        return self::maps('operation')[$this->operation] ?? '';
    }

    public function getTypeTxtAttribute(): string
    {
        return self::maps('type')[$this->type] ?? '';
    }

    public function getQualityTypeTxtAttribute(): string
    {
        $maps = [
            '1' => __('admin.wms.type.normal'), //'正品',
            '2' => __('admin.wms.type.flaw'), //'瑕疵'
        ];
        return $maps[$this->quality_type] ?? '';
    }


    /**
     * 添加库存流水
     *
     * @param int $type 单据类型
     * @param string $uniq_code 唯一码
     * @param string $code 单据编码
     * @param array $data 源值、备注信息
     * @return bool
     */
    static function add($type, $uniq_code, $code, $data = [])
    {
        // 根据唯一码找商品信息
        $good = Inventory::where('uniq_code', $uniq_code)->orderBy('id', 'desc')->first();
        if (!$good) return false;

        $origin_type = '';
        $origin_code = '';
        $erp_no = '';
        $operation = 0;

        switch ($type) {
            case self::ORDER_RECEIVE: // 收货单
                $order = RecvOrder::where('recv_code', $code)->with('arrItem')->first();
                $origin_type = $order->arrItem->arr_type_txt . '登记';;
                $origin_code = $order->arrItem->arr_code;
                $operation = self::OP_RECEIVE;
                break;
            case self::ORDER_QC: //质检单
                $order = WmsQualityList::where('qc_code', $code)->first();
                $origin_type = $order->type_txt;
                $origin_code = $order->arrOrder->arr_code ?? '';
                $operation = self::OP_QUALITY;
                break;
            case self::ORDER_QC_CONFIRM: //质检确认单
                $order = WmsQualityConfirmList::where('qc_code', $code)->first();
                $origin_type = $order->type_txt;
                $origin_code = $order->arr_code;
                $operation = self::OP_QUALITY;
                break;
            case self::ORDER_PUTAWAY: //入库上架单
                $order = WmsPutawayList::where('putaway_code', $code)->first();
                $origin_type = $order->type_txt;
                if ($order->type == 1 && empty($order->origin_code)) {
                    $origin_code = $good->arrItem->arr_code;
                } else   $origin_code = $order->origin_code;
                $operation = self::OP_SHELF;
                break;
            case self::ORDER_ALLOCATE: //配货订单
                $order = preAllocationLists::where('pre_alloction_code', $code)->with('shippingRequest')->first();
                $origin_type = $order->shippingRequest->type_txt ?: '';
                $origin_code = $order->request_code;
                $erp_no = $order->shippingRequest->erp_no;
                $operation = self::OP_ALLOCATE;
                break;
            case self::ORDER_SENDOUT: //发货单
                $order = WmsShippingOrder::where('ship_code', $code)->with('request')->first();
                $origin_type = $order->request->type_txt;
                $origin_code = $order->request_code;
                $erp_no = $order->request->erp_no;
                $operation = self::OP_SENDOUT;
                break;
            case self::ORDER_CKD: //出库单
                $order = ObOrder::where('request_code', $code)->with('request')->first();
                $origin_type = $order->type_txt;
                $origin_code = $order->request_code;
                $erp_no = $order->erp_no;
                $operation = self::OP_STOCK_LOCK;
                break;
            case self::ORDER_SUPPLIER: //供应商调整单
                $order = ArrivalRegist::where('arr_code', $code)->first();
                $origin_type = $order->arr_type_txt . '登记';
                $origin_code = $order->arr_code;
                $erp_no = $order->third_doc_code;
                $operation = self::OP_SUPPLIER;
                break;
            case self::ORDER_CANCEL_OUT: //出库取消单
                $order = $data['request'];
                $origin_type = $order->type_txt . '取消单';
                $origin_code = $order->request_code;
                $erp_no = $order->erp_no;
                switch ($order->type) {
                    case '1':
                        $type = self::ORDER_SALE_CANCEL;
                        break;
                    case '2':
                        $type = self::ORDER_TRANSFER_CANCEL;
                        break;
                    case '3':
                        $type = self::ORDER_OTHER_CANCEL;
                        break;
                    default:
                        # code...
                        break;
                }
                $operation = self::OP_CANCEL_OUT;
                break;
            case self::ORDER_QUICK_MOVE: //快速移位单
                $order = $data['move'];
                $origin_type = $order->type_txt;
                $origin_code = $order->code;
                $erp_no = '';
                $operation = self::OP_QUICK_MOVE;
                break;
            case self::ORDER_MOVE_ALLOCATE: //移位配货单
                $order = $data['move'];
                $origin_type = $order->type_txt;
                $origin_code = $order->code;
                $erp_no = '';
                $operation = self::OP_MOVE_DWON;
                break;
            case self::ORDER_MOVE_UP: //移位上架单
                $order = $data['move'];
                $origin_type = $order->type_txt;
                $origin_code = $order->code;
                $erp_no = '';
                $operation = self::OP_MOVE_SHEFL;
                break;
            case self::ORDER_CANCEL_PUTAWAY: //取消单上架
                $order = $data['cancel'];
                $origin_type = $order->type_txt . '取消单';
                $origin_code = $order->code;
                $erp_no = $order->request_code;
                $operation = self::OP_SHELF;
                break;
            case self::ORDER_CHECK: //少货寻回
                $order = $data['diff']->check->first();
                $origin_type = $order->type_txt ?? '';
                $origin_code = $order->code ?? '';
                $erp_no = '';
                $operation = self::OP_LEAK_BACK;
                break;
            case self::ORDER_MOVE_APPLY: //少货冻结
                $order = $data['diff']->request->first();
                $origin_type = $order->type_txt ?? '';
                $origin_code = $order->origin_code ?? '';
                $erp_no = '';
                $operation = self::OP_LEAK_FREEZE;
                break;
        }


        self::create([
            'operation' => $operation,
            'origin_value' => $data['origin_value'] ?? '',
            'type' => $type,
            'source_code' => $code,
            'origin_type' => $origin_type,
            'origin_code' => $origin_code,
            'erp_no' => $erp_no,
            'sup_id' => $good->sup_id,
            'bar_code' => $good->bar_code,
            'location_code' => $good->location_code,
            'uniq_code' => $uniq_code,
            'batch_no' => $good->lot_num,
            'num' => $data['num'] ?? 1,
            'warehouse_code' => $good->warehouse_code,
            'quality_type' => $good->quality_type,
            'quality_level' => $good->quality_level,
            'remark' => $data['remark'] ?? '',
            'tenant_id' => request()->header('tenant_id'),
            'create_user_id' => request()->header('user_id'),
        ]);
        return true;
    }

    // 批量添加流水记录
    static function addBtach($type, $uniq_codes, $code, $data = [])
    {
        $uniq_codes = array_unique($uniq_codes);
        if (!$uniq_codes) return;

        $origin_type = '';
        $origin_code = '';
        $erp_no = '';
        $operation = 0;

        switch ($type) {
            case self::ORDER_RECEIVE: // 收货单
                $order = RecvOrder::where('recv_code', $code)->with('arrItem')->first();
                $origin_type = $order->arrItem->arr_type_txt . '登记';
                $origin_code = $order->arrItem->arr_code;
                $operation = self::OP_RECEIVE;
                break;
            case self::ORDER_SENDOUT:
                $request = $data['request'];
                $origin_type = $request->type_txt;
                $origin_code = $request->request_code;
                $erp_no = $request->erp_no;
                $operation = self::OP_SENDOUT;
                break;
        }
        if (!$operation) return;

        $arr = [];
        $invs = Inventory::whereIn('uniq_code', $uniq_codes)->get()->keyBy('uniq_code');
        foreach ($uniq_codes as $uniq_code) {
            $good = $invs[$uniq_code] ?? null;
            if (!$good) continue;

            $arr[] = [
                'operation' => $operation,
                'origin_value' => self::_originValue($type, ['good' => $good]),
                'type' => $type,
                'source_code' => $code,
                'origin_type' => $origin_type,
                'origin_code' => $origin_code,
                'erp_no' => $erp_no,
                'sup_id' => $good->sup_id,
                'bar_code' => $good->bar_code,
                'location_code' => $good->location_code,
                'uniq_code' => $uniq_code,
                'batch_no' => $good->lot_num,
                'num' => $data['num'] ?? 1,
                'warehouse_code' => $good->warehouse_code,
                'quality_type' => $good->quality_type,
                'quality_level' => $good->quality_level,
                'remark' => $data['remark'] ?? '',
                'tenant_id' => request()->header('tenant_id'),
                'create_user_id' => request()->header('user_id'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if (count($arr) >= 500) {
                DB::table('wms_stock_logs')->insert($arr);
                $arr = [];
            }
        }
        if (!$arr) return;
        DB::table('wms_stock_logs')->insert($arr);
    }

    static function _originValue($type, $data)
    {
        switch ($type) {
            case self::ORDER_SENDOUT:
                $good = $data['good'] ?? null;
                if (!$good) return '';
                return $good->location_code;
                break;
        }
        return '';
    }

    function supplier()
    {
        return $this->hasOne(Supplier::class, 'id', 'sup_id');
    }

    function specBar()
    {
        return $this->hasOne(ProductSpecAndBar::class, 'bar_code', 'bar_code');
    }

    function warehouse()
    {
        return $this->hasOne(Warehouse::class, 'warehouse_code', 'warehouse_code');
    }

    function createUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'create_user_id');
    }

    function adminUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'admin_user_id');
    }

    function columnOptions()
    {
        return [
            'operation' => self::maps('operation', true),
            'type' => self::maps('type', true),
        ];
    }

    public $requiredColumns = [];

    function columns()
    {
        // export=true 导出时展示 ， search=true 搜索查询时展示
        return [
            ['value' => 'uniq_code', 'label' => '唯一码', 'export' => false],
            ['value' => 'operation', 'label' => '业务操作', 'export' => false],
            ['value' => 'operation_txt', 'label' => '业务操作', 'search' => false],
            ['value' => 'origin_value', 'label' => '源值'],
            ['value' => 'type', 'label' => '单据类型', 'export' => false],
            ['value' => 'type_txt', 'label' => '单据类型', 'search' => false],
            ['value' => 'source_code', 'label' => '单据编码'],
            ['value' => 'origin_type', 'label' => '来源单据类型'],
            ['value' => 'origin_code', 'label' => '来源单据编码'],
            ['value' => 'erp_no', 'label' => 'ERP单据编码'],
            ['value' => 'supplier', 'label' => '供应商', 'search' => false],
            ['value' => 'sku', 'label' => 'SKU编码', 'search' => false],
            ['value' => 'specBar.sku', 'label' => 'SKU编码', 'search' => true, 'export' => false, 'lang' => 'sku'],
            ['value' => 'product_sn', 'label' => '货号', 'search' => false],
            ['value' => 'specBar.product.product_sn', 'label' => '货号', 'search' => true, 'export' => false, 'lang' => 'product_sn'],
            ['value' => 'name', 'label' => '品名', 'search' => false],
            ['value' => 'specBar.product.name', 'label' => '品名', 'search' => true, 'export' => false, 'lang' => 'name'],
            ['value' => 'spec_one', 'label' => '规格', 'search' => false],
            ['value' => 'specBar.spec_one', 'label' => '规格', 'search' => true, 'export' => false, 'lang' => 'spec_one'],
            ['value' => 'bar_code', 'label' => '条形码'],
            ['value' => 'batch_no', 'label' => '批次号'],
            ['value' => 'uniq_code', 'label' => '唯一码', 'search' => false],
            ['value' => 'quality_type', 'label' => '质量类型', 'export' => false, 'search' => true],
            ['value' => 'quality_type_txt', 'label' => '质量类型', 'search' => false],
            ['value' => 'quality_level', 'label' => '质量等级'],
            ['value' => 'location_code', 'label' => '位置码'],
            ['value' => 'num', 'label' => '库存流动'],
            ['value' => 'warehouse_name', 'label' => '仓库', 'search' => false],
            ['value' => 'remark', 'label' => '备注'],
            ['value' => 'create_user', 'label' => '创建人', 'search' => false],
            ['value' => 'created_at', 'label' => '创建时间'],
            ['value' => 'admin_user', 'label' => '最后更新人', 'search' => false],
            ['value' => 'updated_at', 'label' => '最后更新时间'],
        ];
    }
}
