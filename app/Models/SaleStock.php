<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;

class SaleStock extends BaseModel
{
    public $table = 'pms_sale_stock';

    const CREATEDTIME = 'createtime';
    const UPDATEDTIME = 'updatetime';
}
