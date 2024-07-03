<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsPreAllocationDetail extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_pre_allocation_detail';

    const WAIT_GROUP = 1; //已预配待分组
    const WAIT_RECEIVER = 2; //已分组待领取
    const WAIT_ALLOCATE = 3; //已领取待配货
    const ALLOCATING = 4; //配货中
    const WAIT_RECEIVE = 5; //已配货待复核
    const WAIT_DELIVER = 6; //已复核待发货
    const DELIVERED = 7; //已发货

    function shippingRequest(){
        return $this->hasOne(WmsShippingRequest::class,'request_code','request_code');
    }

    function list(){
        return $this->hasOne(WmsShippingRequest::class,'request_code','request_code');
    }
}
