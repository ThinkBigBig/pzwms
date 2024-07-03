<?php

namespace App\Logics;

use App\Handlers\CarryMeApi;
use App\Logics\bid\BidCancel;
use App\Logics\bid\BidQueue;
use App\Logics\channel\DW;
use App\Models\CarrymeNoticeLog;
use App\Models\ChannelBidding;
use App\Models\ChannelBiddingItem;
use App\Models\ChannelOrder;
use App\Models\ChannelRefundLog;
use App\Models\StockProduct;
use App\Models\OperationLog;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Psy\Util\Json;

class OrderLogic extends BaseLogic
{
    // 商家确认发货
    public function businessConfirm($params): array
    {
        $this->success = false;
        $where = ['id' => $params['order_id']];
        $order = ChannelOrder::where($where)->first();

        if (!$order) {
            $this->err_msg = '订单不存在';
            return [];
        }

        if (in_array($order->status, [ChannelOrder::STATUS_CONFIRM, ChannelOrder::STATUS_DELIVER])) {
            $this->success = true;
            return [
                'err_code' => ErrorCode::OK,
                'dispatch_num' => $order->dispatch_num_print,
                'order_no' => $order->order_no,
            ];
        }

        if ($order->status != ChannelOrder::STATUS_CREATED) {
            $this->err_msg = sprintf('非待商家确认发货状态，当前状态：%s', $order->status_txt);
            return [];
        }

        $api = new ChannelLogic($order->channel_code);

        //发货前检查下订单状态
        $detail = $api->getOrderDetail($order->order_no);
        if ($detail && $detail['order_status'] != ChannelOrder::STATUS_CREATED) {

            // 已取消或关闭，同步订单状态
            if (in_array($detail['order_status'], ChannelOrder::$end_status)) {
                self::orderSync(['order' => $order], $detail);
                $this->err_msg = '订单已取消或关闭';
                return [];
            }

            $update = [];
            switch ($detail['order_status']) {
                case ChannelOrder::STATUS_DELIVER: //已发货
                    $update['status'] = ChannelOrder::STATUS_DELIVER;
                    $update['dispatch_num'] = $detail['deliver']['express_no'];
                    $update['business_confirm_time'] = strtotime($detail['deliver']['delivery_time']);
                    $update['platform_confirm_time'] = strtotime($detail['modify_time']);
                    break;
                case ChannelOrder::STATUS_CONFIRM: //已确认
                    $update['status'] = ChannelOrder::STATUS_CONFIRM;
                    $update['dispatch_num'] = $detail['deliver']['express_no'];
                    $update['business_confirm_time'] = strtotime($detail['deliver']['delivery_time']);
                    break;
            }
            if ($update) {
                $this->success = true;
                $order->update($update);
                Robot::sendNotice(sprintf('通知：订单%s 发货状态不一致，已同步至当前状态：%d。', $order->order_no, $order->status));
                return [
                    'err_code' => ErrorCode::ORDER_STATUS_EXCEPTION,
                    'dispatch_num' => $order->dispatch_num_print,
                    'order_no' => $order->order_no,
                ];
            }
        }

        try {
            $api->businessConfirm($order);
            $this->success = true;
            OperationLog::add(OperationLog::ORDER_BUSING, 0, $order->order_no, ($params['remark'] ?? '') . '操作成功', $params['admin_user_id'] ?? 0);
            return [
                'err_code' => ErrorCode::OK,
                'dispatch_num' => $order->dispatch_num_print,
                'order_no' => $order->order_no,
            ];
        } catch (\Exception $e) {
            OperationLog::add(OperationLog::ORDER_BUSING, 0, $order->order_no, ($params['remark'] ?? '') . '操作失败', $params['admin_user_id'] ?? 0);
            $msg = sprintf('商家确认发货失败。订单：%s ，原因：%s', $order->order_no, $e->getMessage());
            Robot::sendException(Robot::FAIL_MSG, $msg);
            $this->err_msg = '操作失败，' . $e->getMessage();
            return [];
        }
    }

    //平台确认发货
    public function platformConfirm($params)
    {
        $this->success = false;
        $where = ['id' => $params['order_id']];
        if ($params['stock_source'] ?? '') {
            $where['stock_source'] = $params['stock_source'];
        }
        $order = ChannelOrder::where($where)->first();
        if (!$order) {
            $this->err_msg = '订单不存在。';
            return [];
        }
        //已经是发货状态，直接同步
        if (in_array($order->status, [ChannelOrder::STATUS_DELIVER, ChannelOrder::STATUS_COMPLETE])) {
            $this->success = true;
            return [];
        }
        // 已取消状态操作失败
        if (in_array($order->status, [ChannelOrder::STATUS_CLOSE, ChannelOrder::STATUS_CANCEL])) {
            $this->err_msg = '订单已取消/已关闭，不能操作发货';
            return [];
        }

        $api = new ChannelLogic($order->channel_code);
        try {
            $api->platformConfirm($order);
            $remark = ($params['remark'] ?? '') . '操作成功';
            $this->success = true;
        } catch (Exception $e) {
            $this->err_msg = $e->getMessage();
            Robot::sendFail(sprintf('（平台确认发货），订单：%s，原因：%s', $order->order_no, $e->getMessage()));
            $remark = ($params['remark'] ?? '') . '操作失败';
        }

        OperationLog::add(OperationLog::ORDER_PLATFORM, 0, $order->order_no, $remark, $params['admin_user_id'] ?? 0);
        return [];
    }

    //平台确认发货
    public function batchPlatformConfirm($ids, $channel_code)
    {
        $this->success = false;
        $channel = new ChannelLogic($channel_code);
        $res = $channel->batchPlatformConfirm($ids);
        if ($res) {
            $remark = implode(',', $ids);
            $orders = ChannelOrder::whereIn('id', $ids)->get();
            foreach ($orders as $order) {
                $detail = $channel->getOrderDetail($order->order_no);
                if ($detail['order_status'] == ChannelOrder::STATUS_DELIVER) {
                    $order->update([
                        'status' => $detail['order_status'],
                        'dispatch_num' => $detail['deliver']['express_no'],
                        'platform_confirm_time' => time(),
                    ]);
                }
                $this->orderSync(['order' => $order], $detail);
            }
            OperationLog::add(OperationLog::ORDER_BATCH_PLATFORM, 0, '', $remark, $params['admin_user_id'] ?? 0);
        }
        return [];
    }

    //订单取消
    public function orderCancel($params)
    {
        $this->success = false;
        $order = ChannelOrder::where(['id' => $params['order_id']])->first();
        if (!$order->cancel_end_time || date('Y-m-d H:i:s') > $order->cancel_end_time) {
            //已过取消截止时间
            $this->err_msg = '超过取消截止时间';
            return false;
        }

        $logic = new ChannelLogic($order->channel_code);
        $res = $logic->orderCancel($order);
        if ($res['is_success']) {
            $order->update([
                'status' => ChannelOrder::STATUS_CANCEL,
                'cancel_time' => time(),
            ]);
            OperationLog::add(OperationLog::ORDER_CANCEL, 0, $order->order_no, '', $params['admin_user_id'] ?? 0);
            // StockProductLogic::stockUpdate($order->channelBidding->stockBiddingItem->stock_product_id, StockProductLogic::STOCK_UPDATE_ORDER_CANCEL);
            $this->_orderProductTakeoff($order);
            self::voidOrderHandle($order);
            $this->success = true;
            return true;
        }

        $this->err_msg = $res['msg'] ?? '';
        return false;
    }

    //订单成立
    static function orderCreated($data, $channel_code)
    {
        $where = ['bidding_no' => $data['bidding_no'], 'channel_code' => $channel_code];
        $bidding = ChannelBidding::where($where)->first();

        if (!$bidding) return [];

        $where = ['order_no' => $data['order_no'], 'channel_code' => $channel_code];
        $order = ChannelOrder::where($where)->first();
        if ($order) return $order;

        $key = RedisKey::orderLock($bidding->carryme_bidding_id . '_' . $bidding->stock_bidding_id);
        Redis::set($key, $data['order_no']);

        $item = ChannelBiddingItem::where([
            'channel_bidding_id' => $bidding->id,
            'product_id' => $data['product_id'] ?? 0,
            'sku_id' => $data['sku_id'],
            'spu_id' => $data['spu_id'],
            // 'status' => ChannelBiddingItem::STATUS_SHELF
        ])->first();

        DB::beginTransaction();
        try {
            $update = [
                'channel_bidding_id' => $bidding->id,
                'order_no' => $data['order_no'],
                'price' => $data['price'],
                'currency' => $data['currency'],
                'price_unit' => $data['price_unit'],
                'price_rmb' => $data['price_rmb'],
                'price_jpy' => $data['price_jpy'],
                'qty' => $data['qty'],
                'paysuccess_time' => strtotime($data['paysuccess_time']),
                'channel_code' => $channel_code,
                'status' => ChannelOrder::STATUS_CREATED,
                'channel_bidding_item_id' => $item ? $item->id : 0,
                'tripartite_order_id' => $data['order_id'] ?? 0,
                'stock_source' => $bidding->source,
            ];
            if ($data['cancel_end_time'] ?? '') {
                $update['cancel_end_time'] = $data['cancel_end_time'];
            }
            $order = ChannelOrder::firstOrCreate($where, $update);

            //更新数量
            if ($data['bidding_detail']) {
                $bidding_detail = $data['bidding_detail'];
                $bidding_update = [
                    'qty_sold' => $bidding_detail['qty_sold'],
                    'qty_remain' => $bidding_detail['qty_remain'],
                ];
            } else {
                $bidding_update = [
                    'qty_sold' => $bidding->qty_sold + 1,
                    'qty_remain' => $bidding->qty - $bidding->qty_sold - 1,
                ];
            }

            $bidding->update($bidding_update);
            if ($bidding->source_format == ChannelBidding::SOURCE_APP) {
                self::_orderSuccessApp($bidding, $data);
            } elseif ($bidding->source_format == ChannelBidding::SOURCE_STOCK) {
                self::_orderSuccessStock($bidding, $data, $order);
            } else {
                self::_orderSuccessChannel($bidding, $order);
            }
            DB::commit();

            $api = new ChannelLogic($order->channel_code);
            $api->afterOrderCreate($bidding, $order);

            if ($bidding->source_format == ChannelBidding::SOURCE_APP) {
                CarrymeCallbackLogic::orderSuccess($order);
            } elseif ($bidding->source_format == ChannelBidding::SOURCE_STOCK) {

                $rate =  $api->exchange()->reverse_exchange;
                $stock_product_channel = $bidding->stockBiddingItem->stockProductChannel;

                $msg = sprintf('订单创建，渠道%s，订单号:%s 订单金额：%s%s  汇率：%s  门槛价:%s JPY 购买价格：%s JPY，差额：%s JPY 库存来源：%s', $bidding->channel_code, $order->order_no, $order->show_price, $order->currency, $rate, $stock_product_channel->threshold_price, $order->price_jpy,  $order->price_jpy - $stock_product_channel->threshold_price, $bidding->source);
                Robot::sendNotice2($msg);
            }

            BidCancel::orderCreated($bidding);
            Redis::del($key);
            return $order;
        } catch (\Exception $e) {
            DB::rollBack();
            Redis::del($key);
            Robot::sendException(sprintf('订单%s创建异常%s', $data['order_no'], $e->getMessage()));
            // throw $e;
            return null;
        }
    }

    static function _orderSuccessApp($bidding, $data)
    {
        $bidding_detail = $data['bidding_detail'];
        if ($bidding->source_format == ChannelBidding::SOURCE_APP) {
            $bidding->carrymeBiddingItem->update([
                'qty_sold' => $bidding_detail['qty_sold'] ?? 1,
            ]);
        }
    }

    static function _orderSuccessStock($bidding, $data, $order)
    {
        $stock_bidding_item = $bidding->stockBiddingItem;
        $stock_bidding_item->update(['qty_sold' => 1]);

        StockProductLogic::stockUpdate($stock_bidding_item->stock_product_id, StockProductLogic::STOCK_UPDATE_ORDER);

        $stock_product = $stock_bidding_item->stockProduct;
        if (!$stock_product) {
            return;
        }

        // Log::info('订单成立时商品库存', ['order_no' => $order->order_no, 'product_sn' => $stock_product->product_sn, 'properties' => $stock_product->properties, 'stock' => $stock_product->stock, 'bidding_stock' => $stock_product->bidding_stock, 'order_stock' => $stock_product->order_stock]);

        // 出价占用库存 + 订单占用库存 < 上传库存
        $update = ['purchase_url' => $stock_product->purchase_url,];
        if ($stock_product->stock - $stock_product->order_stock < 0) {
            $update['is_abnormal'] = 1;
        }
        $order->update($update);
    }

    static function _orderSuccessChannel($bidding, $order)
    {
        $purchanse = $bidding->channelPurchaseBidding;
        if ($purchanse) {
            $purchanse->update(['qty_sold' => 1,]);
            $order->update([
                'purchase_url' => $purchanse->source,
                'purchase_name' => $purchanse->source,
            ]);
            // 下单并重新获取最低价
            $dw = new DW();
            $dw->syncLowestPrice(['spu_id' => $purchanse->spu_id, 'sku_id' => $purchanse->sku_id, 'cp_id' => $purchanse->cp_id]);
        }
    }

    //订单关闭
    static function orderClose($channel_order, $data)
    {
        if ($channel_order->status != ChannelOrder::STATUS_CLOSE) {
            $update = [
                'status' => ChannelOrder::STATUS_CLOSE,
                'close_reason' => $data['close_remark'] ?? '',
                'old_order_no' => $channel_order->order_no,
                'order_no' => $data['order_no'] ?? '',
                'close_time' => strtotime($data['close_time'] ?? ''),
            ];
            $channel_order->update($update);
            Robot::sendNotice2(sprintf('订单关闭，渠道：%s 订单号：%s 原订单号：%s 关闭原因：%s 库存来源：%s', $channel_order->channel_code, $data['order_no'], $update['old_order_no'] ?? '', $data['close_remark'] ?? '', $channel_order->stock_source));
            OperationLog::add(OperationLog::ORDER_CLOSE, 0, $channel_order->order_no);
            self::voidOrderHandle($channel_order);

            $api = new ChannelLogic($channel_order->channel_code);
            $api->afterOrderClose($channel_order->channelBidding, $channel_order);
        }
    }

    //订单取消
    static function orderRefund($channel_code, $refund)
    {
        ChannelRefundLog::create([
            'channel_code' => $channel_code,
            'order_no' => $refund['order_no'],
            'type' => $refund['type'],
        ]);

        if (isset($refund['order_id']) && $refund['order_id']) {
            $where = ['tripartite_order_id' => $refund['order_id']];
        } else {
            $where = ['order_no' => $refund['order_no']];
        }

        $where[] = [function ($query) {
            $query->whereIn('status', [
                ChannelOrder::STATUS_DEFAULT,
                ChannelOrder::STATUS_COMPLETE,
                ChannelOrder::STATUS_CREATED,
                ChannelOrder::STATUS_CONFIRM,
                ChannelOrder::STATUS_DELIVER
            ]);
        }];
        $order = ChannelOrder::where($where)->first();
        if (!$order) return [];

        $update = ['status' => ChannelOrder::STATUS_CANCEL, 'cancel_time' => strtotime($refund['cancel_time'] ?? '')];
        if ($refund['order_no'] != $order->order_no) {
            $update['order_no'] = $refund['order_no'];
            $update['old_order_no'] = $order->order_no;
        }
        $order->update($update);

        if ($order->source_format == ChannelOrder::SOURCE_APP) {
            CarrymeCallbackLogic::orderCancel($order);
        }
        OperationLog::add(OperationLog::ORDER_CANCEL, 0, $order->order_no);
        self::voidOrderHandle($order);
        Robot::sendNotice2(sprintf('订单取消，渠道：%s 订单号：%s 原订单号：%s  库存来源：%s', $channel_code, $refund['order_no'], $update['old_order_no'] ?? '', $order->stock_source));

        $api = new ChannelLogic($order->channel_code);
        $api->afterOrderRefund($order->channelBidding, $order);
        return $order;
    }

    // 订单失效处理
    static function voidOrderHandle(ChannelOrder $channel_order)
    {
        $bidding = $channel_order->channelBidding;

        // 库存出价
        if ($channel_order->source_format == ChannelOrder::SOURCE_STOCK) {
            if (!$channel_order->platform_confirm_time || !strtotime($channel_order->platform_confirm_time)) {
                StockProductLogic::stockUpdate($bidding->stockBiddingItem->stock_product_id, StockProductLogic::STOCK_UPDATE_ORDER_CANCEL);

                // 最后的库存订单被取消掉，库存+1，可再触发一次出价
                $stock_product = $bidding->stockBidding->stockProduct;
                if (!$stock_product->lastStockBidding->activeStockBiddingItems->toArray()) {
                    $logic = new BiddingAsyncLogic();
                    $logic->stockBiddingAdd([
                        'stock_product' => $stock_product,
                        'remark' => '库存订单取消，新增出价'
                    ]);
                }
            }

            // 取消订单对应的出价，针对订单创建后商品不能下架的渠道：GOAT
            BiddingAsyncLogic::orderBiddingCancel($bidding, '订单取消，对应出价取消');
        }

        // app出价
        if ($channel_order->source_format == ChannelOrder::SOURCE_APP) {
            CarrymeCallbackLogic::orderCancel($channel_order);
            $api = new CarryMeApi();
            $res = $api->bidVerify(['carryme_bidding_id' => $bidding->carryme_bidding_id]);
            if (!$res) {
                //出价失效，直接取消
                BiddingAsyncLogic::singleChannelCancel([
                    'bidding_no' => $bidding->bidding_no,
                    'remark' => 'app订单取消，出价失效',
                    'callback' => false,
                    'product_sn' => $bidding->product_sn,
                ]);
            }
        }
    }

    /**
     * 三方渠道订单列表
     * @param Request $request
     * @return LengthAwarePaginator
     */
    static function channelOrderList(Request $request): LengthAwarePaginator
    {
        $size = $request->input('size', 10);
        $cur_page = $request->input('cur_page', 1);

        $select = ['*'];
        $where = [];
        $order_no = $request->input('order_no', '');
        $product_sn = $request->input('product_sn', '');
        if ($order_no) {
            $where[] = ['order_no', '=', $order_no];
        }
        if ($request->get('channel_code', '')) {
            $where['channel_code'] = $request->get('channel_code');
        }
        if ($request->get('tripartite_order_id', '')) {
            $where['tripartite_order_id'] = $request->get('tripartite_order_id');
        }

        if ($request->input('source', '')) {
            $where['stock_source'] = strtolower($request->get('source'));
        }

        $model = ChannelOrder::with(['channelBidding', 'channel'])->where($where)->orderBy('id', 'desc');
        if ($product_sn) {
            $model->whereHas('channelBidding', function ($query) use ($product_sn) {
                $query->where('product_sn', $product_sn);
            });
        }

        $properties = $request->input('properties', '');
        if ($properties) {
            $model->whereHas('channelBidding', function ($query) use ($properties) {
                $query->where('properties', 'like', '%"' . $properties . '"%');
            });
        }

        return $model->paginate($size, $select, 'page', $cur_page);
    }

    public function orderSync($params, $detail = null)
    {
        if (isset($params['order'])) {
            $order = $params['order'];
        } else {
            $order = ChannelOrder::where(['id' => $params['order_id']])->first();
        }

        $new_order_no = $params['new_order_no'] ?? '';
        if (!$detail) {
            $api = new ChannelLogic($order->channel_code);
            $detail = $api->getOrderDetail($new_order_no ?: $order->order_no);
        }

        switch ($detail['order_status']) {
            case ChannelOrder::STATUS_CANCEL: // 已取消
                if ($order->status != ChannelOrder::STATUS_CANCEL) {
                    self::orderRefund($order->channel_code, $detail['refund']);
                    $remark = sprintf('状态同步，当前状态：%s sub_status：%s', $order->status, $detail['sub_status'] ?? '');
                    $this->_orderProductTakeoff($order, $detail);
                }
                break;
            case ChannelOrder::STATUS_CLOSE: // 已关闭
                if ($order->status != ChannelOrder::STATUS_CLOSE) {
                    self::orderClose($order, $detail);
                    $remark = sprintf('状态同步，当前状态：%s sub_status：%s', $order->status, $detail['sub_status'] ?? '');
                    $this->_orderProductTakeoff($order, $detail);
                }
                break;
            case ChannelOrder::STATUS_CONFIRM: // 已确认
                if ($order->status != ChannelOrder::STATUS_CONFIRM) {
                    $remark = sprintf('状态同步，当前状态：%s sub_status：%s', $order->status, $detail['sub_status'] ?? '');
                    $this->businessConfirm(['order_id' => $order->id, 'remark' => $remark, 'admin_user_id' => $params['admin_user_id'] ?? 0]);
                }
                break;
            case ChannelOrder::STATUS_DELIVER: // 已发货
                if ($order->status != ChannelOrder::STATUS_DELIVER) {
                    $remark = sprintf('状态同步，当前状态：%s sub_status：%s', $order->status, $detail['sub_status'] ?? '');
                    $this->platformConfirm(['order_id' => $order->id, 'remark' => $remark, 'admin_user_id' => $params['admin_user_id'] ?? 0]);
                }
                break;

            case ChannelOrder::STATUS_COMPLETE: // 已完成
                if ($order->status != ChannelOrder::STATUS_COMPLETE) {
                    $remark = sprintf('状态同步，当前状态：%s sub_status：%s', $order->status, $detail['sub_status'] ?? '');
                    $this->orderComplete($order, $detail);
                }
                break;

            default:
                $order->touch();
        }
        return $order;
    }

    // 订单取消，尽快下架商品，避免出现超卖
    private function _orderProductTakeoff($order, $detail = [])
    {
        // GOAT STOCKX 需要下架商品
        if (!in_array($order->channel_code, ['GOAT', 'STOCKX'])) return true;
        $api = new ChannelLogic($order->channel_code);
        if (!$detail) {
            $detail = $api->getOrderDetail($order->order_no);
        }
        $api->productTakedown([
            'product_id' => $detail['product_id'] ?? 0,
            'bidding_no' => $detail['bidding_no']
        ]);
    }

    // 订单完成
    public function orderComplete($order, $data)
    {
        if ($order->status != ChannelOrder::STATUS_COMPLETE || !$order->completion_time) {
            $update = ['status' => ChannelOrder::STATUS_COMPLETE];
            if ($data['completion_time'] ?? '') {
                $update['completion_time'] = strtotime($data['completion_time']);
            }
            $order->update($update);
        }
    }

    /**
     * 库存出价订单列表
     */
    static function stockOrderList($params)
    {
        $size = $params['size'] ?? 10;
        $cur_page = $params['cur_page'] ?? 1;
        $show_all = $params['show_all'] ?? 0;

        $select = ['*'];
        $where = [];
        $where[] = [function ($query) {
            $query->where('stock_source', '<>', ChannelOrder::SOURCE_APP)->orWhere(['unmatch' => 1]);
        }];

        $order_no = $params['order_no'] ?? '';
        $product_sn = $params['product_sn'] ?? '';
        if ($order_no) {
            $where[] = ['order_no', '=', $order_no];
        }

        if ($params['tripartite_order_id'] ?? '') {
            $where['tripartite_order_id'] = $params['tripartite_order_id'];
        }
        if (isset($params['is_abnormal']) && $params['is_abnormal'] !== null) {
            $where['is_abnormal'] = $params['is_abnormal'];
        }
        if ($params['status'] ?? '') {
            if ($params['status'] == ChannelOrder::STATUS_CREATED) {
                $where['confirm_progress'] = ChannelOrder::PROGRESS_DEFAULT;
            }
            $where['status'] = $params['status'];
        }

        if (!$show_all) {
            $where[] = [function ($query) {
                $query->whereIn('channel_code', ['GOAT', 'STOCKX', 'CARRYME'])->orWhere(function ($query) {
                    $query->where('channel_code', 'DW')->where('paysuccess_time', '<', time() - 3600);
                });
            }];
        }


        $model = ChannelOrder::where($where)->orderBy('id', 'desc');
        if ($product_sn) {
            $model->whereHas('channelBidding', function ($query) use ($product_sn) {
                $query->where('product_sn', $product_sn);
            });
        }
        if ($params['pay_time_start'] ?? '') {
            $model->where('paysuccess_time', '>=', strtotime($params['pay_time_start']));
        }
        if ($params['pay_time_end'] ?? '') {
            $model->where('paysuccess_time', '<=', strtotime($params['pay_time_end']));
        }
        if ($params['order_ids'] ?? '') {
            $model->whereIn('id', explode(',', $params['order_ids']));
        }
        if ($params['order_source'] ?? '') {
            $arr = explode('-', trim(strtoupper($params['order_source'])));
            if (count($arr) > 1) {
                $model->where('stock_source', $arr[1]);
                $model->where('channel_code', $arr[0]);
            } else {
                $arr = explode(' ', trim(strtoupper($params['order_source'])));
                $model->where('channel_code', $arr[0]);
                if (count($arr) > 1) {
                    $model->where('unmatch', 1);
                } else {
                    $model->where('unmatch', 0);
                }
            }
        }

        if ($params['need_purchase'] ?? '') {
            // 是空卖
            if ($params['need_purchase'] == 1) {
                $model->whereRaw('purchase_url > ""');
            }

            // 不是空卖
            if ($params['need_purchase'] == 2) {
                $model->where('purchase_url', '');
            }
        }

        if ($params['properties'] ?? '') {
            $model->whereHas('channelBidding', function ($query) use ($params) {
                $query->where('properties', 'like', '%"' . $params['properties'] . '"%');
            });
        }

        // 导出物流单时使用，必须有虚拟物流单号
        if ($params['deliver'] ?? 0) {
            $model->where('dispatch_num', '>', '');
        }
        $model->whereIn('stock_source', ['app', 'stock']);

        $data = $model->paginate($size, $select, 'page', $cur_page);
        $res = [];
        foreach ($data as $item) {
            $bidding = $item->channelBidding;
            $product = $bidding ? $bidding->product : null;
            $stock_bidding = $bidding ? $bidding->stockBidding : null;
            $stock_product = $stock_bidding ? $stock_bidding->stockProduct : null;

            $can_cancel = $item->cancel_end_time && strtotime($item->cancel_end_time) > time() && in_array($item->status, ChannelOrder::$no_sendout_status);

            $ext = $item->unmatch ? ' APP' : '';
            $res[] = [
                'order_id' => $item->id,
                'order_no' => $item->order_no . "\t",
                'channel_code' => $item->channel_code . $ext,
                'product_name' => $product ? $product->good_name : '',
                'spu_logo' => $product ? $product->spu_logo_url : '',
                'properties' => $bidding ? $bidding->properties : '',
                'order_price' => $item->price_jpy,
                'bidding_price' => $bidding ? $bidding->price_jpy : 0,
                'cost_price' => $stock_product ? $stock_product->cost_price : 0,
                'paysuccess_time' => $item->paysuccess_time,
                'business_confirm_time' => $item->business_confirm_time,
                'platform_confirm_time' => $item->platform_confirm_time,
                'close_time' => $item->close_time,
                'cancel_time' => $item->cancel_time,
                'completion_time' => $item->completion_time,
                'can_cancel' => $can_cancel ? 1 : 0,
                'is_abnormal' => $item->is_abnormal,
                'status_txt' => $item->status_txt,
                'status' => $item->status,
                'dispatch_num_print' => $item->dispatch_num_print,
                'product_sn' => $product ? $product->product_sn : '',
                'size' => $stock_product ? $stock_product->properties : '',
                'purchase_url' => $item->purchase_url,
                'purchase_name' => $item->purchase_name,
                'purchase_status' => $item->purchase_status,
                'purchase_status_txt' => $item->purchase_status_txt,
                'purchase_btn' => $item->purchase_url && $item->purchase_status == ChannelOrder::PURCHASE_DEFAULT ? 1 : 0, //是否显示已采购按钮
            ];
        }
        $data = collect($data)->toArray();
        $data['data'] = $res;
        return $data;
    }

    /**
     * 更新订单信息
     *
     * @param array $params
     */
    public function updateInfo($params)
    {
        $order = ChannelOrder::where(['id' => $params['order_id']])->first();
        $update = [];
        if (($params['purchase_status'] ?? '') && $order->purchase_url) {
            $update['purchase_status'] = 1;
        }
        if ($update) {
            $order->update($update);
        }
        return true;
    }

    /**
     * 标记carryme未匹配订单
     *
     * @param array $params
     */
    public function unmatch($params)
    {
        $order = ChannelOrder::where(['id' => $params['order_id']])->first();
        if (!$order) {
            $this->success = false;
            $this->err_msg = '订单不存在';
            return false;
        }
        if (!in_array($order->source, ['', ChannelOrder::SOURCE_APP])) {
            $this->success = false;
            $this->err_msg = '并非app订单';
            return false;
        }
        if ($order->unmatch) {
            return true;
        }
        $order->update(['unmatch' => 1]);
    }
}
