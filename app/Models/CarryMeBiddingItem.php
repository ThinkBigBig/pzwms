<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarryMeBiddingItem extends BaseModel
{
    use HasFactory;

    protected $table = 'carryme_bidding_items';

    protected $guarded = [];

    const STATUS_DEFAULT = 0; //默认
    const STATUS_BID = 1; //已出价
    const STATUS_CANCEL = 2; //已取消
    const STATUS_FAIL = 3; //出价失败

    const CANCEL_PENDING = 1; //取消待执行
    const CANCEL_COMPLETE = 2; //取消已执行

    static $vaild_status = [self::STATUS_DEFAULT, self::STATUS_BID];

    public function channelBids(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ChannelBidding::class, 'carryme_bidding_item_id', 'id');
    }

    public function carrymeBidding(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CarryMeBidding::class, 'id', 'carryme_bidding_id');
    }

    public function callbackBid()
    {
        return $this->hasMany(CarrymeCallbackLog::class, 'carryme_bidding_item_id')->whereIn('type', [CarrymeCallbackLog::TYPE_BID_ADD, CarrymeCallbackLog::TYPE_BID_CANCEL]);
    }
}
