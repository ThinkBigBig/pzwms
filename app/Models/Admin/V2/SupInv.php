<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

class SupInv extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_sup_inv'; //产品库存明细
    protected $appends = ['quality_type_txt', 'inv_type_txt', 'amount'];
    protected $map;

    // public function supplier(){
    //     return $this->belongsTo(Supplier::class,'sup_id')->where('status',1)->withDefault();
    // }

    public function product()
    {
        return $this->hasOne(ProductSpecAndBar::class, 'bar_code', 'bar_code')->withDefault([
            'sku' => '',
            'spec_one' => '',
            'product' => [
                'name' => '',
                'product_sn' => '',
                'img' => '',
            ]
        ]);
    }

    // public function warehouse(){
    //     return $this->hasOne(Warehouse::class,'warehouse_code','warehouse_code')->where('status',1)->withDefault();
    // }

    public function __construct($params = [])
    {
        parent::__construct($params);
        $this->map = [
            'inv_type' => [0 => __('status.proprietary'), 1 => __('status.consignment')],
        ];
    }

    public function getQualityTypeTxtAttribute($key)
    {
        return $this->quality_type;
    }
    public function getAmountAttribute($key)
    {
        return sprintf("%.2f", round($this->wh_inv * $this->buy_price, 2));
    }

    //格式化商品
    function _formatList($item)
    {
        foreach ($item['data'] as &$pro) {
            // $pro['wh_name'] =  empty($pro['warehouse_name']) ? Warehouse::getName($pro['warehouse_code']) : $pro['warehouse_name'];
            // $pro['supplier_name'] = empty($pro['sup_name']) ? Supplier::getName($pro['sup_id']) : $pro['sup_name'];
            $pro_info = ProductSpecAndBar::getInfo($pro['bar_code']);
            $pro['product_name'] =  $pro_info['name'];
            $pro['product_sku'] =  $pro_info['sku'];
            $pro['product_spec'] =  $pro_info['spec'];
            $pro['product_spec_two'] =  $pro_info['spec_two'];
            $pro['product_spec_three'] =  $pro_info['spec_three'];
            $pro['product_sn'] =  $pro_info['product_sn'];
        }
        return $item;
    }

    //供应商库存更新
    public static function supInvUpdate($uniq_codes, $lock = 0, $id = 0)
    {
        //确认供应商 匹配入库单  质检更新  锁定库存/释放库存更新
        if (is_string($uniq_codes)) $uniq_codes = explode(',', $uniq_codes);
        $query = Inventory::query();
        if ($lock && $id) {
            $query = $query->where('id', $id);
            //直接可售数量+-
            $item = $query->first();
            if (!$item) return [false, '库存明细不存在'];
            $uniq_code_1 = $item->quality_level == 'A' ? '' : $item->uniq_code;
            $sup_item = self::where('warehouse_code', $item->warehouse_code)->where('bar_code', $item->bar_code)
                ->where('lot_num', $item->lot_num)->where('uniq_code_1', $uniq_code_1)
                ->where('quality_type', $item->getRawOriginal('quality_type'))->where('quality_level', $item->quality_level)
                ->where('sup_id', $item->sup_id)->where('sup_id', $item->sup_id)
                ->first();
            if (!$sup_item) return [false, '供应商库存不存在'];
            $sup_item->sale_inv -= $lock;
            $sup_item->lock_inv += $lock;
            $row = $sup_item->save();
            return [$row, ''];
        } else {
            if ($id) {
                $query = $query->where('id', $id);
            } else $query = $query->whereIn('uniq_code', $uniq_codes);
        }
        $select = $query->select('warehouse_code', 'bar_code', 'sup_id')->groupBy('warehouse_code', 'bar_code', 'sup_id')->get();
        $msg = '';
        foreach ($select as $item) {
            list($res, $msg) = self::_invUpdate($item->warehouse_code, $item->bar_code, $item->sup_id);
            if (!$res) return [false, $msg];
        }
        return [true, $msg];
    }

    //供应商库存更新
    public static function supInvUpdateByBarcode($warehouse_code, array $bar_codes, $sup_id)
    {
        //确认供应商 匹配入库单  质检更新  锁定库存/释放库存更新
        foreach ($bar_codes as $bar_code) {
            list($res, $msg) = self::_invUpdate($warehouse_code, $bar_code, $sup_id);
            if (!$res) return [false, $msg];
        }
        return [true, $msg];
    }

    //供应商库存更新
    public static function supInvUpdateByBarcodes($warehouse_code, array $map)
    {
        //确认供应商 匹配入库单  质检更新  锁定库存/释放库存更新
        foreach ($map as $sup_id=>$bar_codes) {
            foreach($bar_codes as $bar_code){
                list($res, $msg) = self::_invUpdate($warehouse_code, $bar_code, $sup_id);
                if (!$res) return [false, $msg];
            }
        }
        return [true, $msg];
    }
    private static function _invUpdate($warehouse_code, $bar_code, $sup_id)
    {
        // dd($warehouse_code,$bar_code,$sup_id);
        $inv_data = Inventory::from('wms_inv_goods_detail as inv')->where('inv.warehouse_code', $warehouse_code)->where('inv.bar_code', $bar_code)->where('inv.sup_id', $sup_id)
            ->where('inv.sup_id', '<>', 0)->whereNotIn('in_wh_status', [0, 4, 7])
            // ->leftJoin('wms_warehouse as w', 'w.warehouse_code', '=', 'inv.warehouse_code')
            // ->leftJoin('wms_supplier as s', 's.id', '=', 'inv.sup_id')
            ->select(
                'inv.warehouse_code',
                // 'w.warehouse_name',
                'bar_code',
                'lot_num',
                DB::raw('If(quality_level <> "A", uniq_code, "" ) uniq_code_1'), //唯一码
                'quality_type',
                'quality_level',
                'inv.sup_id',
                // 's.name as sup_name',
                'buy_price',
                DB::raw('count(*) AS wh_inv'), //在仓库存
                DB::raw('count(If(sale_status = 1,TRUE,NULL)) sale_inv'), //可售库存
                DB::raw('count(IF( sale_status = 2, TRUE, NULL ))  lock_inv'), //架上锁定库存
                DB::raw('count(If(inv_status = 7,true,null)) wt_send_inv'), //待发库存
                DB::raw('count(If(in_wh_status = 6,true,null)) freeze_inv'), //冻结库存
                'inv.inv_type',
                'inv.tenant_id',
            )->groupBy('bar_code', 'warehouse_code', 'lot_num', 'quality_type', 'quality_level', 'uniq_code_1', 'sup_id', 'buy_price', 'inv_type', 'tenant_id')->get();

        // dd($inv_data);
        $sup_data = self::where('bar_code', $bar_code)->where('warehouse_code', $warehouse_code)->where('sup_id', $sup_id)->lockForUpdate();
        if($inv_data->isEmpty() && $sup_data->exists()){
            $sup_data->delete();
            return [true, '供应商库存更新成功'];
        }

        $sup_level = $sup_data->pluck('quality_level', 'id')->toArray();
        $warehouse_name = Warehouse::getName($warehouse_code);
        $sup_name = Supplier::getName($sup_id);

        foreach ($inv_data as $item) {
            $upd_data = [
                'warehouse_name' => $warehouse_name,
                'sup_name' => $sup_name,
                'buy_price' => $item->buy_price,
                'wh_inv' => $item->wh_inv,
                'sale_inv' => $item->sale_inv,
                'lock_inv' => $item->lock_inv,
                'wt_send_inv' => $item->wt_send_inv,
                'freeze_inv' => $item->freeze_inv,
                'inv_type' => $item->inv_type,
                'updated_user' => request()->header('user_id', 0),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $cre_date = array_merge($upd_data, [
                'quality_type' => $item->getRawOriginal('quality_type'),
                'quality_level' => $item->quality_level,
                'bar_code' => $item->bar_code,
                'lot_num' => $item->lot_num,
                'uniq_code_1' => $item->uniq_code_1,
                'warehouse_code' => $item->warehouse_code,
                'sup_id' => $item->sup_id,
                'tenant_id' => $item->tenant_id,
                'created_at' => date('Y-m-d H:i:s'),
                'created_user' => request()->header('user_id', 0),
            ]);
            // $select_data = [
            //     'warehouse_code' => $item->warehouse_code,
            //     'bar_code' => $item->bar_code,
            //     'lot_num' => $item->lot_num,
            //     'uniq_code_1' => $item->uniq_code_1,
            //     'quality_type' => $item->getRawOriginal('quality_type'),
            //     'quality_level' => $item->quality_level,
            //     'sup_id' => $item->sup_id,
            //     'sup_id' => $item->buy_price,
            // ];
            $sup_item = self::where('warehouse_code', $item->warehouse_code)->where('bar_code', $item->bar_code)
                ->where('lot_num', $item->lot_num)->where('uniq_code_1', $item->uniq_code_1)
                ->where('quality_type', $item->getRawOriginal('quality_type'))->where('quality_level', $item->quality_level)
                ->where('sup_id', $item->sup_id)->where('sup_id', $item->sup_id)
                ->first();
            if ($sup_item) {
                //更新
                $row = $sup_item->update($upd_data);
                if (!$row) return [false, '供应商库存更新失败'];
                unset($sup_level[$sup_item->id]);
            } else {
                //新增
                $row = self::insert($cre_date);
                if (!$row) return [false, '供应商库存新增失败'];
            }
        }
        if ($sup_level) {
            //删除多余数据
            foreach ($sup_level as $id => $level) {
                $row = self::find($id)->delete();
                if (!$row) return [false, '供应商库存id:' . $id . '删除失败'];
            }
        }
        return [true, '供应商库存更新成功'];
    }

    //库存查询原始单据详情
    public static function invOrderInfo($name, $data)
    {
        $rData  = [];
        $list = $data->select('warehouse_code', 'lock_code', 'lock_type', DB::raw('count(*) as num'))->groupBy('warehouse_code', 'lock_code', 'lock_type')->get();
        foreach ($list as $li) {
            $code = $li->lock_code ?? '';
            $temp = [
                'wh_name' => '',
                'order_type' => '',
                'code' => $code,
                'third_code' => '',
                'count' => $li->num,
                'type' => $li->lock_type,
            ];
            switch ($li->lock_type) {
                case '1':
                    //销售订单
                    $item  = WmsOrder::where('code', $li->lock_code)->first();
                    $temp['third_code'] = $item->third_no ?? '';
                    $temp['wh_name'] = $item->warehouse_name ?? '';
                    $temp['order_type'] = $item->type_txt ?? '';
                    break;
                case '2':
                    //调拨单
                    // $item  = TransferOrder::where('tr_code',$li->lock_code)->where('doc_status',2)->first();
                    $temp['order_type'] = __('status.transfer_ob');
                    $temp['third_code'] = '';
                    $temp['wh_name'] = Warehouse::getName($li->warehouse_code);
                    break;
                case '3':
                    //其他出库单
                    $temp['order_type'] = __('status.other_ob');
                    $temp['third_code'] = '';
                    $temp['wh_name'] = Warehouse::getName($li->warehouse_code);
                    break;

                default:
                    if ($name == 'freeze_inv') {
                        //冻结库存-盘点差异单
                        $item  = WmsStockDifference::where('code', $code)->first();
                        $temp['wh_name'] = Warehouse::getName($li->warehouse_code);
                        $temp['order_type'] = $item->type_txt ?? '';
                        $temp['third_code'] = $item->origin_code ?? '';
                    }
                    # code...
                    break;
            }
            $temp['sub_third_code'] = $temp['third_code'];
            $rData[] = $temp;
        }
        return $rData;
    }
}
