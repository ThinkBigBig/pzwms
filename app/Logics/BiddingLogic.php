<?php

namespace App\Logics;

use App\Jobs\bidAdd;
use App\Models\CarryMeBidding;
use App\Models\CarryMeBiddingItem;
use App\Models\Channel;
use App\Models\ChannelBidding;
use App\Models\ChannelProduct;
use Exception;
use Illuminate\Support\Facades\DB;
use Psy\Util\Json;

class BiddingLogic extends BaseLogic
{

    //出价
    public function bid($params): array
    {
        //新增出价记录
        $bidding = CarryMeBidding::create([
            'good_name' => $params['good_name'],
            'product_sn' => $params['product_sn'],
            'properties' => $params['properties'],
            'sku_id' => $params['sku_id'] ?? 0,
            'spu_id' => $params['spu_id'] ?? 0,
            'config' => $params['config'],
            'qty' => $params['qty'],
        ]);

        $check = [];
        $info = [];
        foreach ($params['config'] as $item) {

            //防止相同的渠道和业务有多个配置
            $key = sprintf('%s_%d', $item['channel_code'], $item['business_type']);
            if ($check[$key] ?? '') continue;

            $this->setChannelCode($item['channel_code']);
            //闪电直发出价
            if ($item['business_type'] == ChannelBidding::BUSINESS_TYPE_BLOT) {
                $params['price'] = $item['price'];
                $check[$key] = true;
                $data = $this->bidBlot($params, $bidding->id, $item['business_type']);
                if ($data) $info[] = $data;
            }
        }
        if (!$info) return [];
        $res['info'] = $info;
        $res['carryme_bid_id'] = $this->old_carryme_bid_id ?: $bidding->id;
        return $res;
    }

    /**
     * 出价v2版本
     *
     * @param array $params
     */
    public function bidV2($params)
    {
        //新增出价记录
        $bidding = CarryMeBidding::create([
            'good_name' => $params['good_name'],
            'product_sn' => $params['product_sn'],
            'properties' => $params['properties'],
            'sku_id' => $params['sku_id'] ?? 0,
            'spu_id' => $params['spu_id'] ?? 0,
            'config' => $params['config'],
            'qty' => $params['qty'],
        ]);

        $check = [];
        $info = [];
        foreach ($params['config'] as $item) {

            //防止相同的渠道和业务有多个配置
            $key = sprintf('%s_%d', $item['channel_code'], $item['business_type']);
            if ($check[$key] ?? '') continue;

            $this->setChannelCode($item['channel_code']);
            //闪电直发出价
            if (in_array($item['business_type'] , [ChannelBidding::BUSINESS_TYPE_BLOT,ChannelBidding::BUSINESS_TYPE_SPOT])) {
                $params['price'] = $item['price'];
                $check[$key] = true;
                $data = $this->bidBlotV2($params, $bidding->id, $item['business_type']);
                if ($data) $info[] = $data;
            }
        }

        $res['success'] = $this->success;
        $res['err_msg'] = $this->err_msg;
        $res['business_type'] = $params['config'][0]['business_type'];
        $res['callback_id'] = $params['callback_id'] ?? 0;
        $res['info'] = $info;

        $res['carryme_bid_id'] = 0;
        if ($info) {
            $res['carryme_bid_id'] = $bidding->id;
        }

        return $res;
    }

    private function bidBlotV2($params, $carryme_id, $business_type): array
    {
        $item = CarryMeBiddingItem::create([
            'channel_code' => $this->channel_code,
            'price' => $params['price'],
            'qty' => $params['qty'],
            'status' => CarryMeBiddingItem::STATUS_BID,
            'qty_left' => $params['qty'],
            'business_type' => $business_type,
            'carryme_bidding_id' => $carryme_id
        ]);

        //查每个渠道对应的商品信息
        $product = $this->getProductInfo($params['product_sn'], $params['properties']);
        if (!$product || !$product['sku_id']) {
            $this->err_msg = '未找到对应的商品尺码信息';
            $this->success = false;
            $item->update(['status' => CarryMeBiddingItem::STATUS_FAIL, 'fail_reason' => $this->err_msg]);
            $msg = sprintf('出价失败，原因：%s ，规格：%s', $this->err_msg, Json::encode($params));
            Robot::sendText(Robot::FAIL_MSG, $msg);
            return [];
        }

        $api = new ChannelLogic($this->channel_code);
        $bid_data = $api->bidPriceHandle($params);

        $where = [
            'channel_code' => $this->channel_code,
            'sku_id' => $product['sku_id'],
            'spu_id' => $product['spu_id'],
        ];
        $where[] = [function ($query) {
            $query->whereIn('status', [ChannelBidding::BID_DEFAULT, ChannelBidding::BID_SUCCESS]);
        }];
        //相同的货号、规格的最新有效出价
        $last_bid = ChannelBidding::where($where)->where('qty_remain', '>', 0)->orderBy('id', 'desc')->first();

        //相同价格和数量已经出过价
        if ($last_bid && $last_bid->qty == $bid_data['qty'] && $last_bid->price == $bid_data['price']) {
            Robot::sendText(Robot::NOTICE_MSG, '通知：相同价格和数量已经出价');
            CarryMeBiddingItem::where(['id' => $last_bid->carryme_bidding_item_id])
                ->update(['status' => CarryMeBiddingItem::STATUS_CANCEL]);
            $last_bid->update([
                'carryme_bidding_item_id' => $item->id,
                'carryme_bidding_id' => $carryme_id,
            ]);
            return ['channel_code' => $this->channel_code, 'business_type' => $business_type, 'lowest_price' => $last_bid->lowest_price];
        }

        //出价时平台最低价
        $lowest = $api->getLowestPrice($product['sku_id']);
        $lowest_price = $lowest['lowest_price'];
        $lowest_price_jpy = $lowest['lowest_price_jpy'];


        //调三方接口进行出价
        $price = $bid_data['price'];
        $qty = $bid_data['qty'];
        try {
            //出价
            $bid_res = $api->bidOrUpdate($last_bid, $price, $qty, $product);
            $old_bidding_no = $bid_res['old_bidding_no'];
            $bidding_no = $bid_res['bidding_no'];
            $bid_status = $bid_res['bid_status'];
        } catch (\Exception $e) {
            $msg = sprintf('出价失败。sku_id：%d，price：%d，qty:%d ，原因：%s', $product['sku_id'], $price, $qty, $e->getMessage());
            Robot::sendText(Robot::FAIL_MSG, $msg);

            $this->err_msg = '出价失败，' . $e->getMessage();
            $this->success = false;
            $item->update(['status' => CarryMeBiddingItem::STATUS_FAIL, 'fail_reason' => $this->err_msg]);
            return [];
        }


        try {
            DB::beginTransaction();
            $item->update([
                'qty_bid' => $qty,
                'status' => CarryMeBiddingItem::STATUS_BID,
                'updated_at' => date('Y-m-d H:i:s'),
                'qty_left' => 0
            ]);
            $where = [
                'channel_code' => $this->channel_code, 'bidding_no' => $bidding_no,
                'business_type' => ChannelBidding::BUSINESS_TYPE_BLOT,
                'spu_id' => $product['spu_id'],
                'sku_id' => $product['sku_id'],

            ];
            ChannelBidding::updateOrCreate($where, [
                'price' => $price,
                'currency' => $bid_data['currency'],
                'price_unit' => $bid_data['price_unit'],
                'price_rmb' => $bid_data['price_rmb'],
                'lowest_price' => $lowest_price,
                'qty' => $qty,
                'qty_remain' => $qty,
                'good_name' => $product['name'],
                'product_sn' => $product['product_sn'],
                'properties' => $product['properties'],
                'spu_logo' => $product['pic'],
                'status' => $bid_status,
                'carryme_bidding_id' => $carryme_id,
                'carryme_bidding_item_id' => $item->id,
                'old_bidding_no' => $old_bidding_no,
            ]);
            DB::commit();
            $this->success = true;
            return ['channel_code' => $this->channel_code, 'business_type' => $business_type, 'lowest_price' => $lowest_price_jpy];
        } catch (\Exception $e) {
            DB::rollBack();
            $this->success = false;
            $this->err_msg = $e->getMessage();
            $msg = sprintf('出价信息保存失败，carryme_id：%d，bidding_no：%s', $carryme_id, $bidding_no);
            Robot::sendText(Robot::FAIL_MSG, $msg);
        }
        return [];
    }

    //取消出价
    public function cancel($params)
    {
        $where = [
            'id' => $params['carryme_bidding_id']
        ];
        $carryme_bidding = CarryMeBidding::where($where)->first();
        if (!$carryme_bidding) {
            $this->err_msg = '没有待撤销的出价';
            $this->success = false;
            return [];
        }

        $res = [];
        //carryme_bidding_items列表数据
        $items = CarryMeBiddingItem::where('carryme_bidding_id', $params['carryme_bidding_id'])->get();
        foreach ($items as $item) {
            if (in_array($item->business_type , [ChannelBidding::BUSINESS_TYPE_BLOT,ChannelBidding::BUSINESS_TYPE_SPOT])) {
                $this->setChannelCode($item->channel_code);
                $res = $this->biddingCancelBlot($item);
            }
        }
        return $res;
    }

    //批量取消出价
    public function batchCancel($params): array
    {
        //根据货号查在出价成功的单
        $list = CarryMeBidding::where('product_sn', $params['product_sn'])
            ->whereHas('carrymeBiddingItems', function ($query) {
                $query->where('status', CarryMeBiddingItem::STATUS_BID);
            })->get();

        foreach ($list as $val) {
            bidAdd::dispatch(['carryme_bidding_id' => $val->id, 'option' => bidAdd::OPTION_CANCEL])->onQueue('bid-add');
        }
        return [];
    }

    //渠道列表
    static function getChannels()
    {
        return Channel::where('status', Channel::STATUS_ON)->select(['code', 'name'])->get();
    }

    //根据货号和规格获取商品信息
    private function getProductInfo($product_sn, $property)
    {
        //根据货号和规格，从channel_product 和 channel_product_sku中获取sku_id 和 spu_id
        $product = ChannelProduct::where(['product_sn' => $product_sn, 'channel_code' => $this->channel_code])->with('skus')->first();
        if ($product && $product['skus']) {
            $api = new ChannelLogic($product['channel_code']);
            foreach ($product['skus'] as $sku) {
                if ($api->matchSku($sku, $property)) {
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

    protected $old_carryme_bid_id = 0;
    //出价 - 闪电直发
    private function bidBlot($params, $carryme_id, $business_type): array
    {
        $this->old_carryme_bid_id = 0;
        $api = new ChannelLogic($this->channel_code);
        //根据信息获取对应的sku_id spu_id
        $product = $this->getProductInfo($params['product_sn'], $params['properties']);

        //校验价格和数量
        if ($product && ($product['sku_id'])) {
            $bid_data = $api->bidPriceHandle($params);

            $find = ChannelBidding::where([
                'channel_code' => $this->channel_code,
                'sku_id' => $product['sku_id'],
                'spu_id' => $product['spu_id'],
                'status' => ChannelBidding::BID_SUCCESS,
            ])->where('qty_remain', '>', 0)->orderBy('id', 'desc')->first();
            if ($find && $find->qty == $bid_data['qty'] && $find->price == $bid_data['price']) {
                //相同价格和数量已经出过价
                Robot::sendText(Robot::NOTICE_MSG, '通知：相同价格和数量已经出价');
                $this->old_carryme_bid_id = $find->carryme_bidding_id;
                CarryMeBidding::where(['id' => $carryme_id])->delete();
                return ['channel_code' => $this->channel_code, 'business_type' => $business_type, 'lowest_price' => $api->RMB2Jpy($find->lowest_price)];
            }
        }


        $item = CarryMeBiddingItem::create([
            'channel_code' => $this->channel_code,
            'price' => $params['price'],
            'qty' => $params['qty'],
            'status' => CarryMeBiddingItem::STATUS_BID,
            'qty_left' => $params['qty'],
            'business_type' => $business_type,
            'carryme_bidding_id' => $carryme_id
        ]);

        if (!$product || !$product['sku_id']) {
            $this->err_msg = '未找到对应的商品信息';
            $this->success = false;
            $item->update(['status' => CarryMeBiddingItem::STATUS_FAIL, 'fail_reason' => $this->err_msg]);
            return [];
        }

        //出价时平台最低价
        $lowest = $api->getLowestPrice($product['sku_id']);
        $lowest_price = $lowest['lowest_price'];
        $lowest_price_jpy = $lowest['lowest_price_jpy'];


        //调三方接口进行出价
        $price = $bid_data['price'];
        $qty = $bid_data['qty'];
        try {
            //查spu_id 和sku_id 有没有已经存在的出价
            $where = [
                'spu_id' => $product['spu_id'],
                'sku_id' => $product['sku_id'],
                'status' => ChannelBidding::BID_SUCCESS,
                'channel_code' => $this->channel_code,
            ];
            $old = ChannelBidding::where($where)->where('qty_remain', '>', 0)->orderBy('id', 'desc')->first();
            $old_bidding_no = '';
            if ($old) {
                $old_bidding_no = $old->bidding_no;
                //修改出价
                $res = $api->bidUpdate($old_bidding_no, $price, $qty, $old->qty);
                if ($res && $res['code'] == 200) {
                    $bidding_no = $res['data']['bidding_no'] ?? '';
                    $old->qty_remain = max(0, $old->qty_remain - $qty);
                    if ($old->qty_remain == 0 && $old->qty_sold == 0) {
                        $old->status = ChannelBidding::BID_CANCEL;
                        CarryMeBiddingItem::where(['id' => $old->carryme_bidding_item_id])->update(['status' => CarryMeBiddingItem::STATUS_CANCEL]);
                    }
                } else {

                    if (($res['code'] ?? '') == '20900020') {
                        //原出价已被取消，同步出价状态，并新增出价
                        $old->update(['status' => ChannelBidding::BID_CANCEL]);
                        $msg = sprintf('通知：同步出价状态。出价单号: %s 原因： %s', $old_bidding_no, $res['msg'] ?? '');
                        $old_bidding_no = '';
                        $old = null;
                        Robot::sendText(Robot::NOTICE_MSG, $msg);
                    } else {
                        //直接抛出异常
                        throw new Exception($res['msg'] ?? '接口未响应');
                    }
                }
            }
            if (!$old_bidding_no) {
                //新增出价
                $bidding_no =  $api->bid($product, $price, $qty);
            }
        } catch (\Exception $e) {
            $msg = sprintf('出价失败。sku_id：%d，price：%d，qty:%d ，原因：%s', $product['sku_id'], $price, $qty, $e->getMessage());
            Robot::sendText(Robot::FAIL_MSG, $msg);

            $this->err_msg = '出价失败，' . $e->getMessage();
            $this->success = false;
            $item->update(['status' => CarryMeBiddingItem::STATUS_FAIL, 'fail_reason' => $this->err_msg]);
            return [];
        }


        try {
            DB::beginTransaction();

            if ($old) $old->save();

            $item->update([
                'qty_bid' => $qty,
                'status' => CarryMeBiddingItem::STATUS_BID,
                'updated_at' => date('Y-m-d H:i:s'),
                'qty_left' => 0
            ]);

            ChannelBidding::create([
                'business_type' => ChannelBidding::BUSINESS_TYPE_BLOT,
                'channel_code' => $this->channel_code,
                'price' => $price,
                'currency' => $bid_data['currency'],
                'price_unit' => $bid_data['price_unit'],
                'price_rmb' => $bid_data['price_rmb'],
                'lowest_price' => $lowest_price,
                'qty' => $qty,
                'qty_remain' => $qty,
                'spu_id' => $product['spu_id'],
                'sku_id' => $product['sku_id'],
                'good_name' => $product['name'],
                'product_sn' => $product['product_sn'],
                'properties' => $product['properties'],
                'spu_logo' => $product['pic'],
                'status' => ChannelBidding::BID_SUCCESS,
                'bidding_no' => $bidding_no,
                'carryme_bidding_id' => $carryme_id,
                'carryme_bidding_item_id' => $item->id,
                'old_bidding_no' => $old_bidding_no,
            ]);
            DB::commit();
            $this->success = true;
            return ['channel_code' => $this->channel_code, 'business_type' => $business_type, 'lowest_price' => $lowest_price_jpy];
        } catch (\Exception $e) {
            DB::rollBack();
            $this->success = false;
            $this->err_msg = $e->getMessage();
            $msg = sprintf('出价信息保存失败，carryme_id：%d，bidding_no：%s', $carryme_id, $bidding_no);
            Robot::sendText(Robot::FAIL_MSG, $msg);
        }
        return [];
    }

    //取消出价 - 闪电直发
    private function biddingCancelBlot(CarryMeBiddingItem $item)
    {
        $this->success = true;
        $cancel_num = 0; //取消出价数量
        $bid_num = 0; //不能撤销的数量
        $re_onqueue = false;

        //已经出价的调用取消出价接口
        if ($item->status == CarryMeBiddingItem::STATUS_BID) {

            $bids = $item->channelBids;

            foreach ($bids as $bid) {
                //存在出价未回调的状态，加入队列等待出价成功后执行
                if ($bid->status == ChannelBidding::BID_DEFAULT) {
                    $re_onqueue = true;
                    Robot::sendNotice(sprintf('出价进行中取消，等待出价完成。出价信息：%s', Json::encode($bid)));
                    goto RES;
                }
            }

            $api = new ChannelLogic($this->channel_code);
            foreach ($bids as $bid) {

                if ($bid->status != ChannelBidding::BID_SUCCESS) continue;

                //调接口取消出价
                try {
                    $ret =  $api->bidCancel($bid);
                    if (($ret['code'] ?? '') == 200) {
                        $res['qty_cancel'] = $ret['data']['qty_cancel'];
                        $res['qty'] = $ret['data']['qty'];
                    } elseif (($ret['code'] ?? '') == 20900020) {
                        //查出价详情
                        $detail = $api->getBiddingDetail($bid->bidding_no);
                        $res['qty_cancel'] = $detail['qty_remain'];
                        $res['qty'] = $detail['qty'];
                    } else {
                        throw new Exception($ret['msg'] ?? '接口未响应');
                    }

                    $cancel_num += $res['qty_cancel'];
                    $bid_num += ($res['qty'] - $res['qty_cancel']);

                    $update = [
                        'qty_cancel' => $res['qty_cancel'],
                        'qty_remain' => max(0, $res['qty'] - $bid->qty_sold - $res['qty_cancel']),
                        'status' => ChannelBidding::BID_CANCEL,
                    ];

                    //更新出价状态
                    $bid->update($update);
                } catch (\Exception $e) {
                    $msg = sprintf('取消出价失败。bidding_no：%s，原因：%s', $bid->bidding_no, $e->getMessage());
                    Robot::sendText(Robot::FAIL_MSG, $msg);

                    $this->success = false;
                    $this->err_msg = '取消出价失败';
                    return [];
                }
            }

            //更新数量和状态
            $item->update([
                'status' => CarryMeBiddingItem::STATUS_CANCEL,
                'qty_bid' => $bid_num,
                'qty_cancel' => $cancel_num,
            ]);
        }

        RES:
        return [
            'qty' => $item->qty,
            'qty_bid' => $bid_num,
            'qty_cancel' => $cancel_num,
            're_onqueue' => $re_onqueue, //重新加入队列
        ];
    }

    static function singleChannelCancel($params)
    {
        $bidding = ChannelBidding::where(['bidding_no' => $params['bidding_no']])->first();
        $api = new ChannelLogic($bidding->channel_code);
        $api->biddingCancel($bidding);
        $num = ChannelBidding::where(['carryme_bidding_id' => $bidding->carryme_bidding_id])->whereIn('status', [ChannelBidding::BID_DEFAULT, ChannelBidding::BID_CANCEL])->count();
        //全部被取消，通知carryme 
        if (!$num) {
            $data = [
                'list' => [[
                    'messageId' => $bidding->carryme_bidding_id,
                    'isSuccess' => true,
                ]]
            ];
            NoticeLogic::bidCancel($data);
        }
    }
}
