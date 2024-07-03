<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppOrder extends Model
{
    use HasFactory;
    protected $guarded = [];

    // 订单状态，0->求购中；1->待付款；2->待发货；3->已发货；4->已完成；5->已关闭；
    const WAIT = 0;
    const NOPAY = 1;
    const WAITDELIVER = 2;
    const DELIVERED = 3;
    const COMPLETED = 4;
    const CLOSED = 5;

    function appBidding() {
        return $this->hasOne(AppBidding::class,'logId','logId');
    }
    
}
