<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use  App\Models\Admin\V2\ArrivalRegist;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;


class OObDetails extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_other_ob_details'; //其他出库申请详情
    protected $guarded = [];
    protected $appends =['quality_type_txt'];

    protected $with  =['supplier:id,name','product'];
    public function getQualityTypeTxtAttribute($key)
    {
        return $this->quality_type;
    }

    public function supplier(){
        return $this->belongsTo(Supplier::class,'sup_id')->withDefault(['status'=>1]);
    }
    public  function product()
    {
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

}
