<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;

class ProductSkuStock extends BaseModel
{
    public $table = 'pms_product_stock';

    const CREATED_AT = null;

    protected $guarded = [];
}
