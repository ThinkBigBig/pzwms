<?php

namespace App\Models\Admin\V2;


use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsShippingRequest extends wmsBaseModel
{
    use HasFactory;

    public $table = 'wms_shipping_request';
    

}
