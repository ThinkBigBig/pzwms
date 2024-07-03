<?php

namespace App\Logics\bid;

use App\Jobs\bidAdd;
use App\Logics\BaseLogic;

class BidQueue extends BaseLogic
{

    // 根据货号分配队列
    static function _getQueueName($params)
    {
        $v = substr($params['product_sn'], -1);
        if (is_numeric($v)) {
            if ($v % 2 == 0) {
                return 'bid-add2';
            } else {
                return 'bid-add1';
            }
        }
        return 'bid-add1';
    }

    /**
     * app新增出价
     *
     * @param array $params
     * @return void
     */
    static function appAdd($params)
    {
        bidAdd::dispatch([
            'carryme_bidding_item_id' => $params['carryme_bidding_item_id'],
            'bid_type' => bidAdd::BID_TYPE_SINGLE,
            'option' => bidAdd::OPTION_ADD,
            'product_sn' => $params['product_sn'],
        ])->onQueue(self::_getQueueName($params));
    }

    /**
     * 单渠道app出价取消
     *
     * @param array $params
     *  - bidding_no 出价编号
     *  - callback  是否调通知carryme出价取消，仅在app主动取消出价时为true，其余情况都是false
     *  - remark  取消原因备注
     *  - refreshStock 是否刷新库存出价
     */
    static function appSingleChanelCancel($params)
    {
        $data = [
            'bidding_no' => $params['bidding_no'],
            'cancel_type' => bidAdd::CANCEL_TYPE_SIGNLE, //单个渠道取消出价
            'remark' => $params['remark'] ?? '',
            'callback' => $params['callback'] ?? false,
            'option' => bidAdd::OPTION_CANCEL,
            'refreshStock' => $params['refreshStock'] ?? true,
            'product_sn' => $params['product_sn'],
        ];
        if ($params['delay'] ?? 0) {
            bidAdd::dispatch($data)->onQueue(self::_getQueueName($params))->delay(now()->addMinutes($params['delay']));
        } else {
            bidAdd::dispatch($data)->onQueue(self::_getQueueName($params));
        }
    }

      /**
     * 单渠道app出价取消
     *
     * @param array $params
     *  - carryme_bidding_item_id 出价ID
     *  - remark  取消原因备注
     */
    static function appSingleChanelCancelNew($params)
    {
        $data = [
            'carryme_bidding_item_id' => $params['carryme_bidding_item_id'],
            'cancel_type' => bidAdd::CANCEL_TYPE_SIGNLE, //单个渠道取消出价
            'remark' => $params['remark'] ?? '',
            'option' => bidAdd::OPTION_CANCEL,
            'product_sn' => $params['product_sn'],
        ];
        if ($params['delay'] ?? 0) {
            bidAdd::dispatch($data)->onQueue(self::_getQueueName($params))->delay(now()->addMinutes($params['delay']));
        } else {
            bidAdd::dispatch($data)->onQueue(self::_getQueueName($params));
        }
    }

    /**
     * app还没有出价就取消了
     *
     * @param array $params
     * @return void
     */
    static function appSampleCancel($params)
    {
        $data = [
            'carryme_bidding_item_id' => $params['carryme_bidding_item_id'],
            'cancel_type' => bidAdd::CANCEL_TYPE_SAMPLE, //单个渠道取消出价
            'remark' => $params['remark'] ?? '',
            'callback' => $params['callback'] ?? false,
            'option' => bidAdd::OPTION_CANCEL,
            'refreshStock' => $params['refreshStock'] ?? true,
            'product_sn' => $params['product_sn'],
        ];
        bidAdd::dispatch($data)->onQueue(self::_getQueueName($params))->delay(now()->addMinutes(1));
    }

    /**
     * app刷新出价
     *
     * @param array $params
     */
    static function appRefresh($params)
    {
        bidAdd::dispatch([
            'bid_type' => bidAdd::BID_REFRESH,
            'channel_bidding_id' => $params['channel_bidding_id'],
            'option' => bidAdd::OPTION_ADD,
            'product_sn' => $params['product_sn'],
        ])->onQueue(self::_getQueueName($params));
    }

    /**
     * 单渠道库存出价取消
     *
     * @param array $params
     */
    static function stockSingleChannelCancel($params)
    {
        bidAdd::dispatch([
            'cancel_type' => bidAdd::CANCEL_TYPE_SIGNLE,
            'stock_bidding_item_id' => $params['stock_bidding_item_id'],
            'remark' => $params['remark'] ?? '',
            'option' => bidAdd::OPTION_CANCEL,
            'refreshStock' => $params['refreshStock'] ?? true,
            'after_action' => $params['after_action']??[],
            'product_sn' => $params['product_sn'],
        ])->onQueue(self::_getQueueName($params));
    }

    /**
     * 新增库存出价
     *
     * @param array $params
     */
    static function stockAdd($params)
    {
        bidAdd::dispatch([
            'bid_type' => bidAdd::BID_TYPE_SINGLE,
            'stock_bidding_id' => $params['stock_bidding_id'],
            'stock_bidding_item_id' => $params['stock_bidding_item_id'],
            'option' => bidAdd::OPTION_ADD,
            'product_sn' => $params['product_sn'],
        ])->onQueue(self::_getQueueName($params));
    }
}