<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;

class Brand extends BaseModel
{
    public $table = 'pms_brand';

    const CREATEDTIME = 'createtime';
    const UPDATEDTIME = 'updatetime';
}
