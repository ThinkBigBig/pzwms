<?php

namespace App\Logics;

use App\Handlers\CarryMeApi;
use App\Jobs\carrymeNotice;
use App\Models\CarryMeBidding;
use App\Models\CarrymeNoticeLog;
use App\Models\ChannelOrder;
use Illuminate\Support\Facades\DB;

class NoticeLogic extends BaseLogic
{
    //订单创建成功，消息通知
    static function orderSuccess(ChannelOrder $order)
    {
        try {
            $biding = $order->channelBidding;
            $carryme_bidding = $biding->carrymeBidding;
            $notice = $order->carrymeNotice;

            //状态检查
            if (!$notice) return;
            if (!in_array($notice->order_success_notice_status, CarrymeNoticeLog::$do_status)) {
                return;
            }

            //同一个出价单超卖
            $sum = DB::select('select sum(co.qty) as num from  channel_order co,channel_bidding cb,carryme_notice_logs cnl
        where co.channel_bidding_id = cb.id and co.id = cnl.channel_order_id
        and co.status IN (1,2,3) and cb.id=:channel_bidding_id and cnl.order_success_notice_status IN (0,1,2)', ['channel_bidding_id' => $biding->id]);
            $num = ($sum[0]->num - $order->qty);
            if ($carryme_bidding->qty <= $num) {
                //超过，不再通知
                $notice->update(['order_success_notice_status' => CarrymeNoticeLog::STATUS_NOT_NEED]);
                return;
            }

            //执行通知
            $params = [
                'businessType' => $biding->carryme_business_type,
                'productSn' => $biding->product_sn,
                'skuProperties' => $carryme_bidding->properties,
                'price' => $order->price_jpy,
                'from' => $order->channel_code,
                'orderId' => $order->id,
                'thirdPartyOrderId' => $order->order_no,
                'business_type'=>$biding->business_type,
            ];
            $api = new CarryMeApi();
            $ret = $api->paySuccess($params);
            $update = ['order_success_notice_status' => CarrymeNoticeLog::STATUS_FAIL];

            if (($ret['code'] ?? '') == 200) {
                $update = ['order_success_notice_status' => CarrymeNoticeLog::STATUS_SUCCESS, 'order_success_notice_at' => date('Y-m-d H:i:s')];
            }
            $update['order_success_notice_info'] = ['params' => $params, 'ret' => $ret];
            $notice->update($update);
            return;
        } catch (\Exception $e) {
            Robot::sendText(Robot::FAIL_MSG, '异常：' . $e->__toString());
            return;
        }
    }

    //订单取消，消息通知
    static function orderCancel(ChannelOrder $order)
    {
        if (!$order) return;
        $notice = $order->carrymeNotice;
        if ($notice && in_array($notice->order_cancel_notice_status, CarrymeNoticeLog::$do_status)) {

            $params = [
                'order_no' => $order->order_no
            ];
            $api = new CarryMeApi();
            $ret = $api->orderCancel($params);
            $update = ['order_cancel_notice_status' => CarrymeNoticeLog::STATUS_FAIL];

            if (($ret['code'] ?? '') == 200) {
                $update = ['order_cancel_notice_status' => CarrymeNoticeLog::STATUS_SUCCESS, 'order_cancel_notice_at' => date('Y-m-d H:i:s')];
            }
            $update['order_cancel_notice_info'] = ['params' => $params, 'ret' => $ret];
            $notice->update($update);
        }
        return;
    }

    //出价成功，消息通知
    static function bidSuccess($data, $retry = false, CarrymeNoticeLog $notice = null)
    {
        if ($retry) {
            //重试
            $data = $notice->bid_sccess_notice_info['params'];
        }

        $p = $data;
        unset($p['carryme_bidding_item_id']);
        $api = new CarryMeApi();
        $ret = $api->bidCallback($p);
        $where = ['carryme_bidding_id' => $data['list'][0]['messageId'], 'carryme_bidding_item_id' => $data['carryme_bidding_item_id']];
        if ($notice) {
            $where['id'] = $notice->id;
        }

        if (($ret['code'] ?? '') == 200) {
            $update = ['bid_sccess_notice_status' => CarrymeNoticeLog::STATUS_SUCCESS, 'bid_sccess_notice_at' => date('Y-m-d H:i:s')];
        } else {
            $update = ['bid_sccess_notice_status' => CarrymeNoticeLog::STATUS_FAIL,];
        }
        $update['bid_sccess_notice_info'] = [
            'params' => $data,
            'ret' => $ret
        ];
        CarrymeNoticeLog::updateOrCreate($where, $update);
    }

    //出价取消，消息通知
    static function bidCancel($data, $retry = false, CarrymeNoticeLog $notice = null)
    {
        if ($retry) {
            $data = $notice->bid_cancel_notice_info['params'];
        }

        $where = ['carryme_bidding_id' => $data['list'][0]['messageId']];
        if ($notice) {
            $where['id'] = $notice->id;
        }

        //已经通知过，不再通知
        $find = CarrymeNoticeLog::where($where)->first();
        if ($find && !in_array($find->bid_cancel_notice_status, CarrymeNoticeLog::$do_status)) {
            return false;
        }

        $p = $data;
        unset($p['carryme_bidding_item_id']);
        $api = new CarryMeApi();
        $ret = $api->bidCancelCallback($p);

        if (($ret['code'] ?? '') == 200) {
            $update = ['bid_cancel_notice_status' => CarrymeNoticeLog::STATUS_SUCCESS, 'bid_cancel_notice_at' => date('Y-m-d H:i:s')];
        } else {
            $update = ['bid_cancel_notice_status' => CarrymeNoticeLog::STATUS_FAIL,];
        }

        $update['bid_cancel_notice_info'] = [
            'params' => $data,
            'ret' => $ret
        ];
        CarrymeNoticeLog::updateOrCreate($where, $update);
    }

    //补发指定消息
    static function sendNotice($id)
    {
        $notice = CarrymeNoticeLog::where(['id' => $id])->first();
        if ($notice) {
            $order = $notice->channelOrder;
            if (in_array($notice->order_success_notice_status, CarrymeNoticeLog::$do_status)) {
                self::orderSuccess($order);
            }

            if (in_array($notice->order_cancel_notice_status, CarrymeNoticeLog::$do_status)) {
                self::orderCancel($order);
            }

            if (in_array($notice->bid_sccess_notice_status, CarrymeNoticeLog::$do_status)) {
                self::bidSuccess([], true, $notice);
            }

            if (in_array($notice->bid_cancel_notice_status, CarrymeNoticeLog::$do_status)) {
                self::bidCancel([], true, $notice);
            }
        }
        return 'ok';
    }
}
