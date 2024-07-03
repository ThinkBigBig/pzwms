<?php

namespace App\Logics\bid;

use App\Logics\BaseLogic;
use App\Logics\BidExecute;
use App\Logics\ChannelLogic;
use App\Models\BidExcutionLog;
use App\Models\CarryMeBiddingItem;
use App\Models\ChannelBidding;
use App\Models\ChannelProduct;
use App\Models\StockBiddingItem;

class Excute extends BaseLogic
{
    public static $detail_msg = '';

    static function appBid($params): bool
    {
        $where = ['carryme_bidding_item_id' => $params['carryme_bidding_item_id']];
        $update = ['product_sn' => $params['product_sn'], 'channel_product_sku_id' => 0, 'spu_id' => 0, 'sku_id' => 0];
        if ($params['remark'] ?? '') {
            $update['bid_remark'] = $params['remark'];
        }
        $item = CarryMeBiddingItem::where(['id' => $params['carryme_bidding_item_id']])->first();
        if (!$item) return false;
        $bid = $item->carrymeBidding;
        if (!$bid) return false;

        $update['channel_code'] = $item->channel_code;

        $where['carryme_bidding_id'] = $bid->id;
        $sku = self::getProductInfo($params['product_sn'], $bid->properties, $item->channel_code, false);
        if ($sku) {
            $update['channel_product_sku_id'] = $sku->id;
            $update['spu_id'] = $sku->spu_id;
            $update['sku_id'] = $sku->sku_id;
        }
        $find = BidExcutionLog::where($where)->first();
        if ($find) return true;
        $update['bid_status'] = BidExcutionLog::WAIT;
        BidExcutionLog::addLog(array_merge($where, $update));
        return true;
    }

    static function appCancel($params): bool
    {

        $update = ['refresh_stock' => intval($params['refreshStock'] ?? false)];
        $where = ['product_sn' => $params['product_sn']];
        if ($params['remark'] ?? '') {
            $update['cancel_remark'] = $params['remark'];
        }

        $item = null;
        if ($params['carryme_bidding_item_id'] ?? 0) {
            $item = CarryMeBiddingItem::where(['id' => $params['carryme_bidding_item_id']])->first();
        }
        if ($params['bidding_no'] ?? '') {
            $bidding = ChannelBidding::where(['bidding_no' => $params['bidding_no']])->first();
            if (!$bidding) return false;
            $item = $bidding->carrymeBiddingItem;
        }
        if (!$item) return false;

        $where['carryme_bidding_id'] = $item->carryme_bidding_id;
        $where['carryme_bidding_item_id'] = $item->id;

        $update['channel_code'] = $item->channel_code;
        $update['cancel_status'] = BidExcutionLog::WAIT;
        $find = BidExcutionLog::where($where)->first();
        if (!$find) {
            BidExcutionLog::addLog(array_merge($where, $update));
            return true;
        }
        if ($find->cancel_status == BidExcutionLog::DEFAULT) {
            $find->update($update);
        }
        return true;
    }

    static function stockBid($params): bool
    {
        $where = ['stock_bidding_item_id' => $params['stock_bidding_item_id']];
        $update = ['product_sn' => $params['product_sn'], 'channel_product_sku_id' => 0, 'spu_id' => 0, 'sku_id' => 0];
        if ($params['remark'] ?? '') {
            $update['bid_remark'] = $params['remark'];
        }
        $item = StockBiddingItem::where(['id' => $params['stock_bidding_item_id']])->first();
        if (!$item) return false;
        $product = $item->stockProduct;
        if (!$product) return false;

        $update['channel_code'] = $item->channel_code;
        $where['stock_bidding_id'] = $item->stock_bidding_id;
        $property = [['valuecn' => $product->properties]];
        $sku = self::getProductInfo($params['product_sn'], $property, $item->channel_code, true);
        if ($sku) {
            $update['channel_product_sku_id'] = $sku->id;
            $update['spu_id'] = $sku->spu_id;
            $update['sku_id'] = $sku->sku_id;
        }
        $find = BidExcutionLog::where($where)->first();
        if ($find) return true;
        $update['bid_status'] = BidExcutionLog::WAIT;
        BidExcutionLog::addLog(array_merge($where, $update));
        return true;
    }

    static function stockCancel($params): bool
    {
        $update = ['refresh_stock' => intval($params['refreshStock'] ?? false)];
        $where = ['product_sn' => $params['product_sn']];
        if ($params['remark'] ?? '') {
            $update['cancel_remark'] = $params['remark'];
        }

        $item = null;
        if ($params['stock_bidding_item_id'] ?? 0) {
            $item = StockBiddingItem::where(['id' => $params['stock_bidding_item_id']])->first();
        }
        if (!$item) return false;

        $where['stock_bidding_item_id'] = $item->id;
        $where['stock_bidding_id'] = $item->stock_bidding_id;

        $update['channel_code'] = $item->channel_code;
        $update['cancel_status'] = BidExcutionLog::WAIT;
        $find = BidExcutionLog::where($where)->first();
        if (!$find) {
            BidExcutionLog::addLog(array_merge($where, $update));
            return true;
        }
        if ($find->cancel_status == BidExcutionLog::DEFAULT) {
            $find->update($update);
        }
        return true;
    }

    static function getProductInfo($product_sn, $property, $channel_code, $stock_bid = false)
    {
        //根据货号和规格，从channel_product 和 channel_product_sku中获取sku_id 和 spu_id
        $product = ChannelProduct::where(['product_sn' => $product_sn, 'channel_code' => $channel_code])->with('skus')->first();
        if ($product && $product['skus']) {
            $api = new ChannelLogic($product['channel_code']);
            foreach ($product['skus'] as $sku) {
                if ($stock_bid) {
                    $match = $api->stockMatchSku($sku, $property, $product);
                } else {
                    $match = $api->matchSku($sku, $property, $product);
                }
                if ($match) {
                    $data = $sku;
                    $data['name'] = $product->good_name;
                    $data['pic'] = $product->spu_logo;
                    $data['product_sn'] = $product->product_sn;
                    return $sku;
                }
            }
        }

        return [];
    }
}
