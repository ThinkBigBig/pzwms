<?php

namespace App\Logics;

use App\Handlers\CarryMeApi;
use App\Models\CarryMeBidding;
use App\Models\CarrymeCallbackLog;
use App\Models\ChannelBidding;
use App\Models\ChannelOrder;

class CarrymeCallbackLogic extends BaseLogic
{

    public static $is_retry = false; //是否重试
    public static $log = null; //重试的记录

    //出价成功，消息通知
    static function bidSuccess($data = null)
    {
        $retry_times = 0;
        if (self::$is_retry) {
            $log = self::$log;
            $p = $log->request;
            $retry_times = $log->retry_times + 1;
        } else {
            $p = $data;
            unset($p['carryme_bidding_item_id']);
            unset($p['carryme_bidding_id']);

            $where = [
                'type' => CarrymeCallbackLog::TYPE_BID_ADD,
                'carryme_bidding_item_id' => $data['carryme_bidding_item_id'],
                'carryme_bidding_id' => $data['carryme_bidding_id'],
            ];
            $log = CarrymeCallbackLog::firstOrCreate($where, ['status' => CarrymeCallbackLog::STATUS_DEFAULT, 'request' => $p]);
        }

        if (!in_array($log->status, [CarrymeCallbackLog::STATUS_DEFAULT, CarrymeCallbackLog::STATUS_FAIL])) {
            return;
        }

        $api = new CarryMeApi();
        $ret = $api->bidCallback($p);
        $update = ['response' => $ret, 'retry' => 0, 'retry_times' => $retry_times];
        if (($ret['code'] ?? '') == 200) {
            $update['status'] = CarrymeCallbackLog::STATUS_SUCCESS;
        } else {
            $update['status'] = CarrymeCallbackLog::STATUS_FAIL;
            if (($ret['code'] ?? '') == 500) $update['retry'] = 1;
        }
        $log->update($update);
    }

    //出价取消，消息通知
    static function bidCancel($bidding = null, $is_single = false, $params = [])
    {
        $retry_times = 0;
        if (self::$is_retry) {
            $log = self::$log;
            $data = $log->request;
            $retry_times = $log->retry_times + 1;
        } else {
            
            if($bidding){
                $callback_id = CarryMeBidding::where('id', $bidding->carryme_bidding_id)->value('callback_id');
                $data = [
                    'messageId' => $bidding->carryme_bidding_id,
                    'isSuccess' => $bidding->status == ChannelBidding::BID_CANCEL ? true : false,
                    "erpBidItemId" => $bidding->carryme_bidding_item_id,
                    "thirdPartyProductRecordId" => $callback_id
                ];
                $where = [
                    'type' => CarrymeCallbackLog::TYPE_BID_CANCEL,
                    'carryme_bidding_id' => $bidding->carryme_bidding_id,
                    'carryme_bidding_item_id' => $bidding->carryme_bidding_item_id,
                ];
            }else{
                $data = [
                    'messageId' => $params['carryme_bidding_id'],
                    'isSuccess' => $params['result'],
                    "erpBidItemId" => $params['carryme_bidding_item_id'],
                    "thirdPartyProductRecordId" => $params['callback_id']
                ];
                $where = [
                    'type' => CarrymeCallbackLog::TYPE_BID_CANCEL,
                    'carryme_bidding_id' => $params['carryme_bidding_id'],
                    'carryme_bidding_item_id' => $params['carryme_bidding_item_id'],
                ];
            }
            
            $log = CarrymeCallbackLog::firstOrCreate($where, ['status' => CarrymeCallbackLog::STATUS_DEFAULT, 'request' => $data]);
        }


        if (!in_array($log->status, [CarrymeCallbackLog::STATUS_DEFAULT, CarrymeCallbackLog::STATUS_FAIL])) {
            return;
        }

        $api = new CarryMeApi();
        $ret = $api->bidCancelCallback($data);

        $update = ['response' => $ret, 'retry' => 0, 'retry_times' => $retry_times];
        if (($ret['code'] ?? '') == 200) {
            $update['status'] = CarrymeCallbackLog::STATUS_SUCCESS;
        } else {
            $update['status'] = CarrymeCallbackLog::STATUS_FAIL;
            if (($ret['code'] ?? '') == 500) $update['retry'] = 1;
        }
        $log->update($update);
    }

    //出价取消，消息通知
    static function bidCancelNew($params)
    {
        $retry_times = 0;
        if (self::$is_retry) {
            $log = self::$log;
            $data = $log->request;
            $retry_times = $log->retry_times + 1;
        } else {
            $data = [
                'messageId' => $params['carryme_bidding_id'],
                'isSuccess' => $params['result'],
                "erpBidItemId" => $params['carryme_bidding_item_id'],
                "thirdPartyProductRecordId" => $params['callback_id'],
            ];

            $where = [
                'type' => CarrymeCallbackLog::TYPE_BID_CANCEL,
                'carryme_bidding_id' => $params['carryme_bidding_id'],
                'carryme_bidding_item_id' => $params['carryme_bidding_item_id'],
            ];
            $log = CarrymeCallbackLog::firstOrCreate($where, ['status' => CarrymeCallbackLog::STATUS_DEFAULT, 'request' => $data]);
        }


        if (!in_array($log->status, [CarrymeCallbackLog::STATUS_DEFAULT, CarrymeCallbackLog::STATUS_FAIL])) {
            return;
        }

        $api = new CarryMeApi();
        $ret = $api->bidCancelCallback($data);

        $update = ['response' => $ret, 'retry' => 0, 'retry_times' => $retry_times];
        if (($ret['code'] ?? '') == 200) {
            $update['status'] = CarrymeCallbackLog::STATUS_SUCCESS;
        } else {
            $update['status'] = CarrymeCallbackLog::STATUS_FAIL;
            if (($ret['code'] ?? '') == 500) $update['retry'] = 1;
        }
        $log->update($update);
    }

    //订单创建成功，消息通知
    static function orderSuccess($order = null)
    {
        $retry_times = 0;
        if (self::$is_retry) {
            $log = self::$log;
            $params = $log->request;
            $retry_times = $log->retry_times + 1;
        } else {
            //同一笔订单通知一次
            $where = [
                'type' => CarrymeCallbackLog::TYPE_ORDER_SUCCESS,
                'channel_order_id' => $order->id,
                'channel_bidding_id' => $order->channel_bidding_id,
            ];
            $update = ['status' => CarrymeCallbackLog::STATUS_DEFAULT];
            if ($order->channel_code == 'DW') {
                $update['send_time'] = $order->paysuccess_time->timestamp + 1800;
            }
            $log = CarrymeCallbackLog::firstOrCreate($where, $update);
            $biding = $order->channelBidding;
            $carryme_bidding = $biding->carrymeBidding;
            $carryme_bidding_item = $biding->carrymeBiddingItem;

            $api = new ChannelLogic($order->channel_code);
            $rate =  $api->exchange()->reverse_exchange;
            $params = [
                'businessType' => $biding->carryme_business_type,
                'productSn' => $biding->product_sn,
                'skuProperties' => $carryme_bidding->properties,
                'price' => $carryme_bidding_item->original_price,
                'from' => $order->channel_code,
                'orderId' => $order->id,
                'thirdPartyOrderId' => $order->order_no,
                'orderPrice' => $order->price, //订单原始金额
                'orderPriceJpy' => $order->price_jpy, //订单金额转日元
                'exchangeRate' => $rate, //当时转换汇率
            ];

            $msg = sprintf('订单创建，渠道%s， 订单号:%s 订单金额：%s%s  汇率：%s  原始出价:%s JPY 购买价格：%s JPY，差额：%s JPY 库存来源：%s', $order->channel_code, $order->order_no, $order->show_price, $order->currency, $rate, $carryme_bidding_item->price, $order->price_jpy,  $order->price_jpy - $carryme_bidding_item->price, $biding->source);
            Robot::sendNotice2($msg);
        }
        if (!in_array($log->status, [CarrymeCallbackLog::STATUS_DEFAULT, CarrymeCallbackLog::STATUS_FAIL])) {
            return;
        }
        if (!$order) {
            $order = ChannelOrder::where(['id' => $log->channel_order_id])->first();
        }

        if ($log->send_time > 0) {
            // 订单已取消或关闭，不再同步
            if (in_array($order->status, ChannelOrder::$invalid_status)) {
                $log->update(['status' => CarrymeCallbackLog::STATUS_NOT_NEED]);
                return;
            }
            // 未到发送时间，先不同步
            if (time() < $log->send_time) {
                return;
            }
        }

        $update['request'] = $params;
        $api = new CarryMeApi();
        $ret = $api->paySuccess($params);
        $update['response'] = $ret;
        $update['retry'] = 0;
        $update['retry_times'] = $retry_times;

        if (($ret['code'] ?? '') == 200) {
            $update['status'] = CarrymeCallbackLog::STATUS_SUCCESS;
        } else {
            $update['status'] = CarrymeCallbackLog::STATUS_FAIL;
            if (($ret['code'] ?? '') == 500) $update['retry'] = 1;
        }
        $log->update($update);
    }

    //订单取消，消息通知
    static function orderCancel($order = null)
    {
        $retry_times = 0;
        if (self::$is_retry) {
            $log = self::$log;
            $params = $log->request;
            $retry_times = $log->retry_times + 1;
        } else {
            if (!$order) return;
            $params = ['order_no' => $order->old_order_no ?: $order->order_no,];
            $where = [
                'type' => CarrymeCallbackLog::TYPE_ORDER_CANCEL,
                'channel_order_id' => $order->id,
                'channel_bidding_id' => $order->channel_bidding_id,
            ];
            $log = CarrymeCallbackLog::firstOrCreate($where, ['request' => $params, 'status' => CarrymeCallbackLog::STATUS_DEFAULT]);
        }

        if (!in_array($log->status, [CarrymeCallbackLog::STATUS_DEFAULT, CarrymeCallbackLog::STATUS_FAIL])) {
            return;
        }

        $where = [
            'channel_order_id' => $log->channel_order_id,
            'channel_bidding_id' => $log->channel_bidding_id,
            'type' => CarrymeCallbackLog::TYPE_ORDER_SUCCESS
        ];
        $order_success = CarrymeCallbackLog::where($where)->first();
        // 订单成立 未同步 或 无需同步 时，取消订单也无需同步
        if ($order_success && in_array($order_success->status, [CarrymeCallbackLog::STATUS_DEFAULT, CarrymeCallbackLog::STATUS_NOT_NEED])) {
            $log->update(['status' => CarrymeCallbackLog::STATUS_NOT_NEED]);
            return;
        }

        $api = new CarryMeApi();
        $ret = $api->orderCancel($params);
        $update = ['response' => $ret, 'retry' => 0, 'retry_times' => $retry_times];

        if (($ret['code'] ?? '') == 200) {
            $update['status'] = CarrymeCallbackLog::STATUS_SUCCESS;
        } else {
            $update['status'] = CarrymeCallbackLog::STATUS_FAIL;
            if (($ret['code'] ?? '') == 500  || (!$ret)) $update['retry'] = 1;
        }
        $log->update($update);
        return;
    }


    //更新虚拟物流单号
    static function dispatchNum($order = null)
    {
        $retry_times = 0;
        if (self::$is_retry) {
            $log = self::$log;
            $params = $log->request;
            $retry_times = $log->retry_times + 1;
        } else {
            if (!$order) return;
            $params = [
                'order_no' => $order->order_no,
                'dispatch_num' => $order->dispatch_num_print,
            ];
            $where = [
                'type' => CarrymeCallbackLog::TYPE_ORDER_DISPATCH_NUM,
                'channel_order_id' => $order->id,
                'channel_bidding_id' => $order->channel_bidding_id,
            ];
            $log = CarrymeCallbackLog::firstOrCreate($where, ['request' => $params, 'status' => CarrymeCallbackLog::STATUS_DEFAULT]);
        }

        if (!in_array($log->status, [CarrymeCallbackLog::STATUS_DEFAULT, CarrymeCallbackLog::STATUS_FAIL])) {
            return;
        }

        $api = new CarryMeApi();
        $ret = $api->dispatchNum($params);
        $update = ['response' => $ret, 'retry' => 0, 'retry_times' => $retry_times];

        if (($ret['code'] ?? '') == 200) {
            $update['status'] = CarrymeCallbackLog::STATUS_SUCCESS;
        } else {
            $update['status'] = CarrymeCallbackLog::STATUS_FAIL;
            if (($ret['code'] ?? '') == 500) $update['retry'] = 1;
        }
        $log->update($update);
        return;
    }

    //同步最低价
    static function syncLowestPrice($params)
    {
        $p = [
            'productRecordId' => $params['callback_id'],
            'code' => $params['channel_code'],
            'low' => $params['lowest']['lowest_price_jpy'],
        ];

        $log = CarrymeCallbackLog::create([
            'type' => CarrymeCallbackLog::TYPE_LOWEST_PRICE,
            'carryme_bidding_item_id' => $params['carryme_bidding_item_id'],
            'carryme_bidding_id' => $params['carryme_bidding_id'],
            'request' => $p,
            'status' => CarrymeCallbackLog::STATUS_DEFAULT
        ]);

        if (!in_array($log->status, [CarrymeCallbackLog::STATUS_DEFAULT, CarrymeCallbackLog::STATUS_FAIL])) {
            return;
        }

        $api = new CarryMeApi();
        $ret = $api->syncLowestPrice($p);
        $update = ['response' => $ret, 'retry' => 0, 'retry_times' => 0];

        if (($ret['code'] ?? '') == 200) {
            $update['status'] = CarrymeCallbackLog::STATUS_SUCCESS;
        } else {
            $update['status'] = CarrymeCallbackLog::STATUS_FAIL;
        }
        $log->update($update);
        return;
    }


    static function retry($log)
    {
        self::$is_retry = true;
        self::$log = $log;
        switch ($log->type) {
            case CarrymeCallbackLog::TYPE_BID_ADD:
                self::bidSuccess();
                break;
            case CarrymeCallbackLog::TYPE_BID_CANCEL:
                self::bidCancel();
                break;
            case CarrymeCallbackLog::TYPE_ORDER_SUCCESS:
                self::orderSuccess();
                break;
            case CarrymeCallbackLog::TYPE_ORDER_CANCEL:
                self::orderCancel();
                break;
            case CarrymeCallbackLog::TYPE_ORDER_DISPATCH_NUM:
                self::dispatchNum();
                break;
        }
    }
}
