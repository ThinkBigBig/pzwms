<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockxBidding extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function stockxProduct()
    {
        return $this->hasOne(StockxProduct::class, 'productId', 'productId');
    }

    public function stockxProductVariant()
    {
        return $this->hasOne(StockxProductVariant::class, 'variantId', 'variantId');
    }

    function stockxOrder()
    {
        return $this->hasOne(StockxOrder::class, 'listingId', 'listingId');
    }

    public function channelBidding()
    {
        return $this->hasOne(ChannelBidding::class, 'bidding_no', 'bidding_no');
    }
    
}
