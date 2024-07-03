<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsAfterSaleOrderDetail extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'wms_after_sale_order_details';

    protected $with = ['product'];


    public function product(){
        return $this->belongsTo(ProductSpecAndBar::class,'bar_code','bar_code')->withDefault();
    }

    public function saleDetail(){
        return $this->hasOne(WmsOrderDetail::class,'id','order_detail_id');
    }


}
