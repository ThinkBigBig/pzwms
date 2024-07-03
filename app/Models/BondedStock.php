<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;

class BondedStock extends BaseModel
{
    public $table = 'pms_bonded_stock';

    const CREATEDTIME = 'createtime';
    const UPDATEDTIME = 'updatetime';
}
