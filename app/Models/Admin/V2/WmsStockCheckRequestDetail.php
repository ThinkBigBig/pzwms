<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsStockCheckRequestDetail extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = "wms_stock_check_request_details";

    const ACTIVE = 1;

    function specBar()
    {
        return $this->hasOne(ProductSpecAndBar::class, 'bar_code', 'bar_code');
    }
}
