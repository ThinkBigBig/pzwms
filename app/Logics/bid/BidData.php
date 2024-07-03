<?php

namespace App\Logics\bid;

use App\Logics\BaseLogic;
use App\Logics\ChannelLogic;
use App\Models\ChannelBidding;

class BidData extends BaseLogic
{
    // 出价收益
    static function profit(ChannelBidding $bidding)
    {
        $logic = new ChannelLogic($bidding->channel_code);
        $price = $bidding->price;
        $price_jpy = $logic->bidPrice2Jpy($price);
        switch($bidding->source_format){
            case ChannelBidding::SOURCE_APP:
                $origin = $bidding->carrymeBiddingItem->price;
                break;
            case ChannelBidding::SOURCE_STOCK:
                $origin = $bidding->stockBiddingItem->threshold_price;
                break;
        }
        //收益 = 实际出价 - 原始出价
        return $price_jpy - $origin;
    }
}