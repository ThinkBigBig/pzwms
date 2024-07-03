<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;

class BondedStockNumber extends BaseModel
{
    public $table = 'pms_bonded_stock_number';

    const CREATEDTIME = 'createtime';
    const UPDATEDTIME = 'updatetime';
}
