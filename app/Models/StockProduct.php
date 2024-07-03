<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockProduct extends Model
{
    use HasFactory;
    protected $guarded = [];

    const STATUS_SHELF = 0; //上架
    const STATUS_TAKE_DOWN = 1; //下架

    static $status_maps = [
        self::STATUS_SHELF => '已上架',
        self::STATUS_TAKE_DOWN => '已下架',
    ];

    static $list_maps = [
        self::STATUS_SHELF => '未出价',
        self::STATUS_TAKE_DOWN => '已下架',
    ];

    protected $appends = ['status_txt'];

    public function getStatusTxtAttribute(): string
    {
        return self::$list_maps[$this->status] ?? '';
    }

    public function product()
    {
        return $this->hasOne(ChannelProduct::class, 'product_sn', 'product_sn');
    }

    public function stockBiddingActive()
    {
        return $this->hasOne(StockBiddingItem::class, 'stock_product_id', 'id')->where(['status' => StockBiddingItem::STATUS_SUCCESS, 'qty_sold' => 0]);
    }

    public function dwChannel()
    {
        $where = ['channel_code' => 'DW'];
        return $this->hasOne(StockProductChannel::class, 'stock_product_id', 'id')->where($where);
    }

    public function goatChannel()
    {
        $where = ['channel_code' => 'GOAT'];
        return $this->hasOne(StockProductChannel::class, 'stock_product_id', 'id')->where($where);
    }

    public function stockxChannel()
    {
        $where = ['channel_code' => 'STOCKX'];
        return $this->hasOne(StockProductChannel::class, 'stock_product_id', 'id')->where($where);
    }

    public function carrymeChannel()
    {
        $where = ['channel_code' => 'CARRYME'];
        return $this->hasOne(StockProductChannel::class, 'stock_product_id', 'id')->where($where);
    }

    public function dw()
    {
        $status = [StockBiddingItem::STATUS_DEFAULT, StockBiddingItem::STATUS_SUCCESS];
        $where = ['channel_code' => 'DW', 'qty_sold' => 0];
        return $this->hasOne(StockBiddingItem::class, 'stock_product_id', 'id')->where($where)->whereIn('status', $status);
    }

    public function goat()
    {
        $status = [StockBiddingItem::STATUS_DEFAULT, StockBiddingItem::STATUS_SUCCESS];
        $where = ['channel_code' => 'GOAT', 'qty_sold' => 0];
        return $this->hasOne(StockBiddingItem::class, 'stock_product_id', 'id')->where($where)->whereIn('status', $status);
    }

    public function stockx()
    {
        $status = [StockBiddingItem::STATUS_DEFAULT, StockBiddingItem::STATUS_SUCCESS];
        $where = ['channel_code' => 'STOCKX', 'qty_sold' => 0];
        return $this->hasOne(StockBiddingItem::class, 'stock_product_id', 'id')->where($where)->whereIn('status', $status);
    }
    public function carryme()
    {
        $status = [StockBiddingItem::STATUS_DEFAULT, StockBiddingItem::STATUS_SUCCESS];
        $where = ['channel_code' => 'CARRYME', 'qty_sold' => 0];
        return $this->hasOne(StockBiddingItem::class, 'stock_product_id', 'id')->where($where)->whereIn('status', $status);
    }

    public function stockBiddings()
    {
        return $this->hasMany(StockBidding::class, 'stock_product_id', 'id')->orderBy('id', 'desc');
    }

    public function lastStockBidding()
    {
        return $this->hasOne(StockBidding::class, 'stock_product_id', 'id')->orderBy('id', 'desc');
    }

    // 商品是否可出价
    static function canBid(StockProduct $product): bool
    {
        if (!$product) return false;
        if ($product->is_deleted == 0 && $product->status == self::STATUS_SHELF) return true;
        return false;
    }

    public function productHistory()
    {
        return $this->hasOne(StockProductHistory::class, 'stock_product_id', 'id');
    }
}
