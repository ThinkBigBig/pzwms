<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarryMeBidding extends BaseModel
{
    use HasFactory;

    protected $table = 'carryme_bidding';

    protected $guarded = [];

    protected $casts = [
        'properties' => 'array',
        'config' => 'array',
    ];

    const STATUS_WAIT_BID = 10; //待出价
    const STATUS_BID = 20; //已出价
    const STATUS_CLOSE = 30; //已关闭
    const STATUS_ORDER = 40; //已成交

    public function channelBids(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ChannelBidding::class, 'carryme_bidding_id', 'id');
    }

    public function carrymeBiddingItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CarryMeBiddingItem::class, 'carryme_bidding_id', 'id');
    }

    public function setPropertiesAttribute($properties)
    {
        $this->attributes['properties'] = json_encode($properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function setConfigAttribute($config)
    {
        $this->attributes['config'] = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    //获取carryme的出价类型
    static function getCarrymeBusinessType($business_type)
    {
        //carryme对应的出价类型 0现货 1预售 2闪电直发
        $maps = [
            ChannelBidding::BUSINESS_TYPE_BLOT => 2, //闪电直发
            ChannelBidding::BUSINESS_TYPE_SPOT => 0, //现货
            ChannelBidding::BUSINESS_TYPE_PRE_SALE => 1, //预售
        ];
        return $maps[$business_type] ?? '';
    }

    public function activeBiddingItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CarryMeBiddingItem::class, 'carryme_bidding_id', 'id')->where(['status'=>[CarryMeBiddingItem::STATUS_DEFAULT,CarryMeBiddingItem::STATUS_BID]]);
    }
}
