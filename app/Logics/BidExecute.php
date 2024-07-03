<?php

namespace App\Logics;

use App\Logics\bid\BidCancel;
use App\Logics\bid\BidQueue;
use App\Logics\bid\Excute;
use App\Models\BidExcutionLog;
use App\Models\CarryMeBiddingItem;
use App\Models\ChannelBidding;
use App\Models\StockBiddingItem;
use Exception;
use Psy\Util\Json;

class BidExecute extends BaseLogic
{
    static function orderBiddingCancel($bidding)
    {
        try {
            $remark = '订单成立，全渠道下架';
            switch ($bidding->source_format) {

                case ChannelBidding::SOURCE_STOCK: //库存出价
                    $stock_bidding = $bidding->stockBidding;
                    if (!$stock_bidding) break;

                    // 订单成立后，立即取消所有出价
                    foreach ($stock_bidding->stockBiddingItems as $item) {
                        BiddingAsyncLogic::stockSingleChannelCancel([
                            'stock_bidding_item_id' => $item->id,
                            'remark' => $remark
                        ]);
                    }
                    break;
                case ChannelBidding::SOURCE_APP: //app出价，取消相同出价单出价
                    $biddings = ChannelBidding::where(['carryme_bidding_id' => $bidding->carryme_bidding_id])->whereIn('status', [CarryMeBiddingItem::STATUS_BID, CarryMeBiddingItem::STATUS_DEFAULT])->get();
                    foreach ($biddings as $bidding) {
                        BiddingAsyncLogic::singleChannelCancel([
                            'bidding_no' => $bidding->bidding_no,
                            'remark' => $remark,
                            'callback' => false,
                            'refreshStock' => false,
                        ]);
                    }
                    break;
            }
        } catch (Exception $e) {
            Robot::sendException($e->__toString());
        }
    }

    /**
     * 取消出价
     *
     * @param ChannelBidding $bidding
     * @param string $remark
     * @param boolean $callback 是否通知Carryme回调
     */
    static function cancel($bidding, $remark = '', $callback = false)
    {
        switch ($bidding->source_format) {
            case ChannelBidding::SOURCE_STOCK: //库存出价
                $res =  BiddingAsyncLogic::stockSingleChannelCancel([
                    'stock_bidding_item_id' => $bidding->stock_bidding_item_id,
                    'product_sn' => $bidding->product_sn,
                    'remark' => $remark,
                ]);
                break;
            case ChannelBidding::SOURCE_APP: //app出价，取消相同出价单出价
                $res =  BiddingAsyncLogic::singleChannelCancel([
                    'callback' => $callback,
                    'bidding_no' => $bidding->bidding_no,
                    'remark' => $remark,
                    'refreshStock' => false,
                ]);
                break;
        }

        return $res;
    }

    static function appItemCancel($params)
    {
        $success = false;
        $item = CarryMeBiddingItem::where(['id' => $params['carryme_bidding_item_id']])->first();
        $api = new ChannelLogic($item->channel_code);
        $bidding = ChannelBidding::where(['carryme_bidding_item_id' => $params['carryme_bidding_item_id']])->orderBy('id', 'desc')->first();
        if (!$bidding) {
            $success = true;
            goto RES;
        }

        if ($bidding->status == ChannelBidding::BID_DEFAULT) {
            // 超过2分钟没有出价结果，重入队列前补偿刷新一次
            $msg = sprintf('出价进行中取消，等待出价完成。出价信息：%s', Json::encode($bidding));
            if (strtotime($bidding->created_at) < time() - 120) {
                $re = (new BiddingAsyncLogic())->refreshBidResult(['ext_tag' => $bidding->bidding_no, 'spu_id' => $bidding->spu_id]);
                $msg .= sprintf(' 补偿结果：%s', Json::encode($re));
            }
            Robot::sendNotice($msg);

            $params['product_sn'] = $bidding->product_sn;
            //重新加入队列
            // BidQueue::appSingleChanelCancelNew($params);
            // Excute::appCancel($params);
            return false;
        }

        if (!in_array($bidding->business_type, ChannelBidding::$cross_border_business)) {
            Robot::sendNotice(sprintf('非闪电和现货出价取消。出价信息：%s', Json::encode($bidding)));
            $success = true;
            goto RES;
        }

        $success = $api->biddingCancel($bidding);

        $item = $bidding->carrymeBiddingItem;
        $update = ['status' => CarryMeBiddingItem::STATUS_CANCEL,];
        if ($params['remark'] ?? '') $update['remark'] = $params['remark'];
        if ($item && $item->cancel_progress) $update['cancel_progress'] = CarryMeBiddingItem::CANCEL_COMPLETE;
        $item->update($update);

        RES:
        if ($api->bidCancelIsSync() || ($bidding && in_array($bidding->status, [ChannelBidding::BID_CANCEL, ChannelBidding::BID_FAIL]))) {
            BidCancel::cancelCompleted($item, null, $success);
        }
        return true;
    }

    static function stockItemCancel($params)
    {
        $success = false;
        $item = StockBiddingItem::where(['id' => $params['stock_bidding_item_id']])->first();
        $api = new ChannelLogic($item->channel_code);
        $bidding = ChannelBidding::where(['stock_bidding_item_id' => $params['stock_bidding_item_id']])->orderBy('id', 'desc')->first();
        if (!$bidding) {
            $success = true;
            goto RES;
        }

        if ($bidding->status == ChannelBidding::BID_DEFAULT) {
            // 超过2分钟没有出价结果，重入队列前补偿刷新一次
            $msg = sprintf('出价进行中取消，等待出价完成。出价信息：%s', Json::encode($bidding));
            if (strtotime($bidding->created_at) < time() - 120) {
                $re = (new BiddingAsyncLogic())->refreshBidResult(['ext_tag' => $bidding->bidding_no, 'spu_id' => $bidding->spu_id]);
                $msg .= sprintf(' 补偿结果：%s', Json::encode($re));
            }
            Robot::sendNotice($msg);

            //重新加入队列
            $params['product_sn'] = $bidding->product_sn;
            BidQueue::stockSingleChannelCancel($params);
            return false;
        }

        $success = $api->biddingCancel($bidding);

        $item = $bidding->stockBiddingItem;
        $update = ['status' => StockBiddingItem::STATUS_CANCEL,];
        if ($params['remark'] ?? '') $update['remark'] = $params['remark'];
        if ($item && $item->cancel_progress) $update['cancel_progress'] = CarryMeBiddingItem::CANCEL_COMPLETE;
        $item->update($update);

        RES:
        if ($api->bidCancelIsSync() || ($bidding && in_array($bidding->status, [ChannelBidding::BID_CANCEL, ChannelBidding::BID_FAIL]))) {
            BidCancel::cancelCompleted(null, $item, $success);
        }
        return true;
    }
}
