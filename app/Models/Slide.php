<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;

class Slide extends BaseModel
{
    public $table = 'slide';

    const CREATEDTIME = 'createtime';
    const UPDATEDTIME = 'updatetime';
}
