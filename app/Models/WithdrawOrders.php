<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;

class WithdrawOrders extends BaseModel
{
    public $table = 'withdraw_orders';

    const CREATEDTIME = 'createtime';
    const UPDATEDTIME = 'updatetime';
}
