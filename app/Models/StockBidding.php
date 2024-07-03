<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockBidding extends Model
{
    use HasFactory;
    protected $guarded = [];



    protected $casts = [
        'properties' => 'array',
    ];

    public function setPropertiesAttribute($properties)
    {
        $this->attributes['properties'] = json_encode($properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function stockProduct()
    {
        return $this->hasOne(StockProduct::class, 'id', 'stock_product_id');
    }

    public function channelBidding()
    {
        return $this->hasOne(ChannelBidding::class, 'stock_bidding_id', 'id')->where(['channel_bidding.status' => ChannelBidding::BID_SUCCESS]);
    }

    public function stockBiddingItems()
    {
        return $this->hasMany(StockBiddingItem::class, 'stock_bidding_id', 'id');
    }

    public function activeStockBiddingItems()
    {
        return $this->hasMany(StockBiddingItem::class, 'stock_bidding_id', 'id')->where(function ($query) {
            $query->where(['status' => StockBiddingItem::STATUS_DEFAULT])->orWhere(function ($query) {
                $query->where(['status' => StockBiddingItem::STATUS_SUCCESS, 'qty_sold' => 0]);
            });
        });
    }
}
