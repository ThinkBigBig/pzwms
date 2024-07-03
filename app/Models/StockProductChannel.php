<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockProductChannel extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function channelProductSku()
    {
        return $this->hasOne(ChannelProductSku::class, 'id', 'channel_product_sku_id');
    }

    public function stockProduct()
    {
        return $this->hasOne(StockProduct::class, 'id', 'stock_product_id');
    }

    public function stockProductActive()
    {
        return $this->hasOne(StockProduct::class, 'id', 'stock_product_id')->where(['is_deleted' => 0, 'status' => StockProduct::STATUS_SHELF]);
    }
}
