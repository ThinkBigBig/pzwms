<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelPurchaseBidding extends Model
{
    use HasFactory;
    protected $guarded = [];

    const SUCCESS = 1;
    const CANCEL = 2;
    const FAIL = 3;

    //转换类型
    protected $casts = [
        'properties' => 'array',
    ];
    public function setPropertiesAttribute($properties)
    {
        $this->attributes['properties'] = json_encode($properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    function channelBidding()
    {
        return $this->hasOne(ChannelBidding::class, 'bidding_no', 'bidding_no')->where('channel_code', 'CARRYME')->where('source', $this->source);
    }
}
