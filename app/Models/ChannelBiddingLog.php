<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelBiddingLog extends Model
{
    use HasFactory;
    protected $guarded = [];

    const UPDATED_AT = null;

    const TYPE_ADD = 1;//新增出价
    const TYPE_UPDATE = 2;//修改出价
    const TYPE_CANCEL = 3;//取消出价

    static function addLog($type,$channel_bidding,$data)
    {
        $channel_code = $channel_bidding->channel_code;
        $new_price = $data['new_price']??0;
        $new_qty = $data['new_qty']??1;
        $old_price = $data['old_price']??0;
        $old_qty = $data['old_qty']??1;
        $product_id = $data['product_id']??0;
        switch($type){
            case self::TYPE_ADD:
                $msg = sprintf('新增出价，价格%s，数量%d',$new_price,$new_qty);
                break;
            case self::TYPE_UPDATE:
                $msg = sprintf('更新出价，价格%s，数量%d，原价格%s，原数量%s',$new_price,$new_qty,$old_price,$old_qty);
                break;
            case self::TYPE_CANCEL:
                $msg = sprintf('取消出价，原价格%s，原数量%s',$new_price,$new_qty);
                break;
        }

        self::create([
            'channel_code' => $channel_code,
            'bidding_no' => $channel_bidding->bidding_no,
            'product_id'=> $product_id,
            'spu_id' => $channel_bidding->spu_id,
            'sku_id' => $channel_bidding->sku_id,
            'remark' => $msg,
            'type' => $type
        ]);
    }
}
