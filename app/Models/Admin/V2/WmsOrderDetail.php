<?php

namespace App\Models\Admin\V2;


use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WmsOrderDetail extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'wms_order_details';

    protected $casts = [
        'lock_ids' => 'array',
    ];

    function specBar()
    {
        return $this->hasOne(ProductSpecAndBar::class, 'bar_code', 'bar_code');
    }
    function product()
    {
        return $this->hasOne(ProductSpecAndBar::class, 'bar_code', 'bar_code');
    }

    function items()
    {
        return $this->hasMany(WmsOrderItem::class,  'detail_id', 'id')->where('origin_code', $this->origin_code)->where('warehouse_code', $this->warehouse_code);
    }

    function activeAfterSale()
    {
        $res = DB::select("SELECT asd.*,sa.id as after_sale_id FROM wms_after_sale_orders sa ,wms_after_sale_order_details asd 
        WHERE sa.`code`=asd.origin_code AND sa.`status` IN(0,1,2,4) AND sa.origin_code='$this->origin_code' AND asd.order_detail_id=$this->id and asd.tenant_id=$this->tenant_id and sa.tenant_id=$this->tenant_id");
        return $res;
    }
}
