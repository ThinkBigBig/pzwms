<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;

class Stock extends BaseModel
{
    public $table = 'pms_stock';

    const CREATED_AT = 'createtime';
}
