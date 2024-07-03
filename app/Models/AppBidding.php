<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppBidding extends Model
{
    use HasFactory;
    protected $guarded = [];

    const FAIL = -1;//出价失败
    const SUCCESS = 0;//已出价
    const CANCEL = 1;//已取消
    const SOLD = 2;//已售罄

    function channelBidding(){
        return $this->hasOne(ChannelBidding::class,'bidding_no','bidding_no')->where(['channel_code'=>'CARRYME']);
    }

    function appProduct() {
        return $this->hasOne(AppProduct::class,'productId','productId');
    }
    function appProductSku() {
        return $this->hasOne(AppProductSku::class,'standardId','standardId');
    }
}
