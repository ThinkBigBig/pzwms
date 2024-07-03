<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class TotalInv extends wmsBaseModel
{
    use HasFactory;
    // protected $table = 'wms_totalinv';
    protected $table = 'wms_total_inv';

    // public function supplier(){
    //     return $this->belongsTo(Supplier::class,'sup_id')->where('status',1)->withDefault();
    // }

    public function product(){
        return $this->hasOne(ProductSpecAndBar::class, 'bar_code', 'bar_code')->withDefault([
            'sku'=>'',
            'spec_one'=>'',
            'spec_two'=>'',
            'spec_three'=>'',
            'product'=>[
                'name'=>'',
                'product_sn'=>'',
                'img'=>'',
            ]
        ]);
    }

 
    public function warehouse(){
        return $this->hasOne(Warehouse::class,'warehouse_code','warehouse_code')->where('status',1)->withDefault();
    }
}
