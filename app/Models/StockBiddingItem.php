<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockBiddingItem extends Model
{
    use HasFactory;
    protected $guarded = [];

    const STATUS_DEFAULT = 0;
    const STATUS_SUCCESS = 1;
    const STATUS_CANCEL = 2;
    const STATUS_FAIL = 3;

    static $vaild_status = [self::STATUS_DEFAULT, self::STATUS_SUCCESS];

    public function channelBidding()
    {
        return $this->hasOne(ChannelBidding::class, 'stock_bidding_item_id', 'id')->where(['channel_bidding.status' => ChannelBidding::BID_SUCCESS]);
    }

    public function stockProduct()
    {
        return $this->hasOne(StockProduct::class, 'id', 'stock_product_id');
    }

    public function channelProduct()
    {
        return $this->hasOne(ChannelProduct::class, 'id', 'channel_product_id');
    }

    public function stockProductChannel()
    {
        return $this->hasOne(StockProductChannel::class, 'stock_product_id', 'stock_product_id')->where(['stock_product_channels.channel_code' => $this->channel_code]);
    }
}
