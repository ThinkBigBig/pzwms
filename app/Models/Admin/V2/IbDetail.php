<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use  App\Models\Admin\V2\ArrivalRegist;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;


class IbDetail extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_ib_detail';

    protected $appends =  ['diff_num'];
    // protected $with = ['supplier','product'];
    public function ibOrder(){
        return $this->hasOne(IbOrder::class,'ib_code','ib_code');
    }
    public function supplier(){
        return $this->hasOne(Supplier::class,'id','sup_id')->withDefault(['status'=>1]);
    }

    public function product(){
        return $this->hasOne(ProductSpecAndBar::class, 'bar_code', 'bar_code')->withDefault([
            'sku'=>'',
            'spec_one'=>'',
            'product'=>[
                'name'=>'',
                'product_sn'=>'',
                'img'=>'',
            ]
        ]);
    }
    public function getDiffNumAttribute($key)
    {
        return $this->re_total - $this->rd_total;
    }
}
