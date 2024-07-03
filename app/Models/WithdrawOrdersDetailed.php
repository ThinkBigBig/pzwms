<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;

class WithdrawOrdersDetailed extends BaseModel
{
    public $table = 'withdraw_orders_detailed';
    const CREATEDTIME = 'createtime';
    const UPDATEDTIME = 'updatetime';
}
