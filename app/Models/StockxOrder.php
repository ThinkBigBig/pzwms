<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockxOrder extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'product' => 'array',
        'variant' => 'array',
        'shipment' => 'array',
        'payout' => 'array',
    ];

    //订单进行中状态 
    static $active_status = [
        "CREATED", "CCAUTHORIZATIONFAILED", "SHIPPED", "RECEIVED", "AUTHENTICATING", "AUTHENTICATED", "PAYOUTPENDING", "PAYOUTCOMPLETED", "SYSTEMFULFILLED", "PAYOUTFAILED", "SUSPENDED"
    ];
}
