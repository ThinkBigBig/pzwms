<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;

class SkuStock extends BaseModel
{
    public $table = 'pms_sku_stock';

    const CREATED_AT = 'createtime';
}
