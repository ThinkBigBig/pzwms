<?php

namespace App\Logics\bid;

use App\Logics\BaseLogic;
use App\Models\ChannelBidding;

class BidRule extends BaseLogic
{
    public static $detail_msg = '';

    /**
     * app和库存出价进行比较，保留利润较高的出价
     *
     * @param ChannelBidding $last_bid
     * @param array $bid_data
     *          profit 当前出价利润
     */
    static function isHightProfitBid($last_bid, $bid_data, $source)
    {
        if (!$last_bid) return null;
        if ($source == $last_bid->source_format) {
            return null;
        }

        $old_profit = BidData::profit($last_bid);
        $profit = $bid_data['profit'];

        if ($old_profit >= $profit) {
            self::$detail_msg = sprintf('old_profit %s profit %s last_id %d',$old_profit,$profit,$last_bid->id);
            return false;
        }

        // 取消相同渠道和规格下的所有有效库存出价
        $biddings = ChannelBidding::where(['channel_code' => $last_bid->channel_code, 'sku_id' => $last_bid->sku_id, 'qty_sold' => 0])->whereIn('status', [ChannelBidding::BID_SUCCESS, ChannelBidding::BID_DEFAULT])->get();

        // 取消上次出价
        switch ($last_bid->source_format) {
            case ChannelBidding::SOURCE_APP:
                foreach ($biddings as $bidding) {
                    // BidQueue::appSingleChanelCancel([
                    //     'bidding_no' => $bidding->bidding_no,
                    //     'remark' => '库存新出价取消',
                    //     'refreshStock' => false,
                    //     'product_sn' => $bidding->product_sn,
                    // ]);
                    Excute::appCancel([
                        'bidding_no' => $bidding->bidding_no,
                        'remark' => '库存新出价取消',
                        'refreshStock' => false,
                        'product_sn' => $bidding->product_sn,
                    ]);
                }
                break;
            case ChannelBidding::SOURCE_STOCK:

                foreach ($biddings as $bidding) {
                    // BidQueue::stockSingleChannelCancel([
                    //     'stock_bidding_item_id' => $bidding->stockBiddingItem->id,
                    //     'remark' => 'app新出价取消',
                    //     'product_sn' => $bidding->product_sn,
                    // ]);
                    if($bidding->stock_bidding_item_id){
                        Excute::stockCancel([
                            'stock_bidding_item_id' => $bidding->stock_bidding_item_id,
                            'remark' => 'app新出价取消',
                            'product_sn' => $bidding->product_sn,
                        ]);
                    }
                    
                }
                break;
        }
        return true;
    }
}
