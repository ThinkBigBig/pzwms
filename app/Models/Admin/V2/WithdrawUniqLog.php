<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class WithdrawUniqLog extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_withdraw_uniq_log'; //退货/调拨 唯一码记录
    protected $guarded = [];

    //获取可能得唯一码的商品信息
    public static function getProData($third_no, $bar_codes, $type)
    {

        // $ib_item = IbDetail::where('ib_code', $ib_code)->orderBy('created_at', 'desc')->select('bar_code', 'quality_type', 'quality_level', 'sup_id', 'buy_price', 'uniq_code', 're_total')->get();
        // if ($ib_item->isEmpty()) return [false, '入库需求单无商品'];
        //获取需求单编号
        $ob_item = ObOrder::where('third_no', $third_no)->orderBy('id', 'desc')->first();
        if (!$ob_item) return [false, '出库需求单不存在'];
        if ($ob_item->request_status != 4) return [false, '未发货出库'];
        $request_code = $ob_item->request_code;
        $dist_detail = preAllocationDetail::where('request_code', $request_code)->whereIn('bar_code', $bar_codes)->get()
            ->makeHidden('product');
        $pro_data = [];
        foreach ($dist_detail as $pro) {
            if (empty($pro->uniq_code)) return [false, '退调单未出库'];
            $where = [
                'type' => $type == 2 ? 1 : 2,
                'inv_type' => Inventory::getInvType($pro['uniq_code']),
                'source_code' => $third_no,
                'sup_id' => $pro['sup_id'],
                'buy_price' => $pro['buy_price'],
                'bar_code' => $pro['bar_code'],
                'quality_type' => $pro->getRawOriginal('quality_type'),
                'quality_level' => $pro['quality_level'],
                'uniq_code' => $pro['uniq_code'],
                'batch_no' => $pro['batch_no'],
            ];
            $temp = [
                'source_details_id' => $pro['id'],
                'tenant_id' => $pro['tenant_id'],
                'admin_user_id' => request()->header('user_id'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            self::updateOrCreate($where,$temp);
            // $pro_data[] = $temp;
        }

        return [true, '更新失败'];
        // $row = self::add($pro_data);
        // return [$row, '更新失败'];
    }

    //添加唯一码记录
    public static function add($products)
    {
        return self::insert($products);
    }
}
