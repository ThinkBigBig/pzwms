<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class PurchaseDetails extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_purchase_details'; //采购明细
    protected $guarded = [];
    protected $with = ['product'];
    protected $appends = ['wait_num', 'amount'];

    public function product()
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
    //待收总数
    public function getWaitNumAttribute($key)
    {
        return $this->num - $this->recv_num;
    }
    //采购额
    public function getAmountAttribute($key)
    {
        return round($this->num * $this->buy_price, 2);
    }
}