<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use  App\Models\Admin\V2\ArrivalRegist;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;


class OIbDetails extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_other_ib_details'; //其他入库申请详情
    protected $guarded = [];

    protected $with  =['supplier:id,name','product'];
    protected $appends = ['amount','inv_type_txt'];
    protected $map;

    public function __construct($params = [])
    {
        parent::__construct($params);
        $this->map = [
            'inv_type' => [0 => __('status.proprietary'), 1 => __('status.consignment')],
        ];
    }

    public function supplier(){
        return $this->belongsTo(Supplier::class,'sup_id')->withDefault(['status'=>1]);
    }

    public function getAmountAttribute($key){
        return $this->buy_price * $this->num;
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
