<?php

namespace App\Models\Admin\V2;


use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsShippingOrder extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'wms_shipping_orders';
    
    function request(){
        return $this->hasOne(ObOrder::class,'request_code','request_code');
    }
}
