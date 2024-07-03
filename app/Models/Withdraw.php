<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;

class Withdraw extends BaseModel
{
    public $table = 'withdraw';

    const CREATEDTIME = 'createtime';
    const UPDATEDTIME = 'updatetime';
}
