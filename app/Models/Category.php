<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;

class Category extends BaseModel
{
    public $table = 'pms_product_category';

    const CREATEDTIME = 'createtime';
    const UPDATEDTIME = 'updatetime';
}
