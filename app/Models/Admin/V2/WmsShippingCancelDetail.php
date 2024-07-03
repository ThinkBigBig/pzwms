<?php

namespace App\Models\Admin\V2;


use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsShippingCancelDetail extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'wms_shipping_cancel_detail';

    static function maps($attr, $option = false)
    {
        return [];
    }
}
