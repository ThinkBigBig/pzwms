<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockxProduct extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $casts = [
        'productAttributes' => 'array'
    ];

    public function stockxProductVariants()
    {
        return $this->hasMany(StockxProductVariant::class, 'stockx_product_id', 'id');
    }


}
