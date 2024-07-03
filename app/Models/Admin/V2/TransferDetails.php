<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use  App\Models\Admin\V2\ArrivalRegist;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;


class TransferDetails extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_transfer_details'; //调拨申请单
    protected $guarded = [];

    protected $with  =['supplier:id,name','product'];

    protected $appends = ['quality_type_txt','inv_type_txt'];
    protected $map;

    public function __construct($params = [])
    {
        parent::__construct($params);
        $this->map = [
            'inv_type' => [0 => __('status.proprietary'), 1 => __('status.consignment')],
        ];
    }

    public function supplier(){
        return $this->hasOne(Supplier::class,'id','sup_id')->withDefault(['status'=>1]);
    }

    public function getQualityTypeTxtAttribute($key)
    {
        return $this->quality_type;
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
