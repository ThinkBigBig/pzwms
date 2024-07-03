<?php

namespace App\Logics;

use App\Handlers\CarryMeApi;
use App\Handlers\GoatApi;
use App\Jobs\stockProduct as JobsStockProduct;
use App\Logics\bid\BidCancel;
use App\Logics\bid\BidRule;
use App\Logics\bid\Excute;
use App\Logics\channel\DW;
use App\Logics\channel\GOAT;
use App\Models\CarryMeBidding;
use App\Models\CarryMeBiddingItem;
use App\Models\Channel;
use App\Models\ChannelBidding;
use App\Models\ChannelBiddingItem;
use App\Models\ChannelBiddingLog;
use App\Models\ChannelProduct;
use App\Models\StockBidding;
use App\Models\StockBiddingItem;
use App\Models\StockProduct;
use App\Models\StockProductChannel;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Psy\Util\Json;

class BiddingAsyncLogic extends BaseLogic
{
    /**
     * 出价v3版本
     * 按渠道 + 业务维度 异步出价处理
     *
     * @param array $params
     */
    public function bidV3($params)
    {
        foreach ($params['config'] as $k => $item) {
            $channel = new ChannelLogic($item['channel_code']);
            $item['bid_price'] = $channel->appBidPrice($item);
            $params['config'][$k] = $item;
        }

        try {
            DB::beginTransaction();
            //新增出价记录
            $bidding = CarryMeBidding::create([
                'good_name' => $params['good_name'],
                'product_sn' => $params['product_sn'],
                'properties' => $params['properties'],
                'sku_id' => $params['sku_id'] ?? 0,
                'spu_id' => $params['spu_id'] ?? 0,
                'config' => $params['config'],
                'qty' => $params['qty'],
                'callback_id' => $params['callback_id'],
            ]);

            $carryme_item_ids = [];
            $check = [];
            foreach ($params['config'] as $item) {

                //防止相同的渠道和业务有多个配置
                $key = sprintf('%s_%d', $item['channel_code'], $item['business_type']);
                if ($check[$key] ?? '') continue;

                $this->setChannelCode($item['channel_code']);
                //闪电直发出价
                $item = CarryMeBiddingItem::create([
                    'channel_code' => $this->channel_code,
                    'price' => $item['bid_price'],
                    'original_price' => $item['price'],
                    'qty' => $params['qty'],
                    'qty_left' => $params['qty'],
                    'business_type' => $item['business_type'],
                    'carryme_bidding_id' => $bidding->id
                ]);

                $carryme_item_ids[] = $item->id;
            }
            // 相同规格是否有库存出价，有的话先取消
            BidCancel::appAddBid($bidding, $carryme_item_ids, $params['product_sn'], $params['properties'][0]['valuecn'], $params['sku_id'] ?? 0, $item['business_type']);
            DB::commit();
            $res['info'] = [];
            $res['carryme_bid_id'] = $bidding->id;
            return $res;
        } catch (Exception $e) {
            DB::rollBack();
            $this->success = false;
            $this->err_msg = '操作失败，请稍后再试';
            return [];
        }
    }

    //获取最新出价
    private function _getLastBid($item, $product)
    {
        $where = [
            'channel_code' => $item->channel_code,
            'sku_id' => $product['sku_id'],
            'spu_id' => $product['spu_id'],
        ];
        $where[] = [function ($query) {
            $query->where('status', ChannelBidding::BID_DEFAULT)
                ->orWhere(function ($query) {
                    $query->where('status', ChannelBidding::BID_SUCCESS)->where('qty_sold', '=', 0);
                });
        }];
        //相同的货号、规格的最新有效出价
        $last_bid = ChannelBidding::where($where)->whereIn('source', ['', 'app', 'stock'])->orderBy('id', 'desc')->first();
        return $last_bid;
    }

    private function _noNeedUpdate($last_bid, $item, $bid_data)
    {
        $last_item = $last_bid->stockBiddingItem;
        $stock_product = $item->stockProduct;
        $old_stock_product = $last_item->stockProduct;

        try {

            DB::beginTransaction();
            $last_item->update([
                'status' => StockBiddingItem::STATUS_CANCEL,
                'remark' => '已出价信息满足新规则,关联关系被替换',
            ]);

            $last_bid->update([
                'stock_bidding_item_id' => $item->id,
                'stock_bidding_id' => $item->stock_bidding_id,
            ]);
            $item->update([
                'threshold_price' => $bid_data['threshold_price'],
                'price' => $last_bid->price,
                'qty_bid' => 1,
                'status' => StockBiddingItem::STATUS_SUCCESS,
                'qty_left' => 0
            ]);
            DB::commit();

            // 刷新商品库存
            self::bidStockRefresh($item);
            // 商品信息有变化，刷新老商品库存
            if ($stock_product->id != $old_stock_product->id) {
                self::bidStockRefresh($last_item);
            }
            Log::channel('daily2')->info('库存出价无需更新，只变更关联关系', ['last_id' => $last_item->id, 'new_id' => $item->id]);
            BidCancel::cancelCompleted(null, $last_item, true);
        } catch (Exception $e) {
            $msg = sprintf('_noNeedUpdate 操作失败 %s', $e->__toString());
            Robot::sendException($msg);
            DB::rollBack();
        }
    }

    //库存出价
    public function bidSingleStock(StockBiddingItem $item)
    {
        // Log::channel('daily2')->info('bid', ['库存开始出价', 'stock_bidding_id' => $item->stock_bidding_id, 'stock_bidding_item_id' => $item->id]);
        $lock = RedisKey::stockItemBidLock($item);
        if (!Redis::setnx($lock, date('Y-m-d H:i:s'))) {
            return false;
        }

        $stock_product = $item->stockProduct;
        if ($stock_product->is_deleted) {
            $this->setErrorMsg('商品已清空');
            goto FAIL;
        }

        if ($stock_product->status !== StockProduct::STATUS_SHELF) {
            $this->err_msg = '商品已下架';
            $this->success = false;
            goto FAIL;
        }

        if ($item->cancel_progress) {
            $this->err_msg = '未出价已取消';
            $this->success = false;
            goto FAIL;
        }

        $this->setChannelCode($item->channel_code);
        $params = [
            'product_sn' => $stock_product->product_sn,
            'properties' => [["valuecn" => $stock_product->properties]],
            'qty' => $item->qty,
        ];

        $find = self::channelConfig($item->channel_code);
        if (!$find || $find['stock_bid'] != Channel::ON) {
            $this->setErrorMsg('出价渠道已关闭');
            goto FAIL;
        }

        //商品信息
        $product = $this->_getProductInfo($params['product_sn'], $params['properties'], true);
        if (!$product || !$product['sku_id']) {
            $this->err_msg = '未找到对应的商品尺码信息';
            $this->success = false;
            goto FAIL;
        }

        //最低价
        $lowest = ProductLogic::lowestPrice($product);
        //有效出价
        $last_bid = $this->_getLastBid($item, $product);
        $params['last_bid'] = $last_bid;

        //出价金额
        $api = new ChannelLogic($item->channel_code);
        $bid_data = $api->stockBidPriceHandle($params, $lowest, $stock_product);
        if ($bid_data['price'] === null) {
            $this->err_msg = sprintf('不满足出价规则 最低价%s 门槛价%s', $lowest['lowest_price_jpy'], $bid_data['threshold_price']);
            $this->success = false;

            if ($last_bid && $last_bid->source_format == ChannelBidding::SOURCE_STOCK) {
                Excute::stockCancel([
                    'stock_bidding_item_id' => $last_bid->stock_bidding_item_id,
                    'remark' => '最低价/门槛价变更，本次出价取消',
                    'refreshStock' => false,
                    'product_sn' => $last_bid->product_sn,
                ]);
            }
            goto FAIL;
        }

        if ($last_bid) {
            $rule = new BidRule();
            if (false === $rule::isHightProfitBid($last_bid, $bid_data, ChannelBidding::SOURCE_STOCK)) {
                $this->err_msg = '不是最优出价 ' . $rule::$detail_msg;
                $this->success = false;
                goto FAIL;
            }

            //之前出价满足新的门槛价和最低价
            if ($last_bid->source_format == ChannelBidding::SOURCE_STOCK) {
                if ($api->stockBiddingPriceCheck($last_bid->price, $stock_product) && $last_bid->price == $bid_data['price']) {
                    $this->_noNeedUpdate($last_bid, $item, $bid_data);
                    Redis::del($lock);
                    return true;
                }

                //之前出价不满足新规则，取消
                Excute::stockCancel([
                    'stock_bidding_item_id' => $last_bid->stock_bidding_item_id,
                    'remark' => '库存价格变更，本次出价取消',
                    'refreshStock' => false,
                    'product_sn' => $last_bid->product_sn,
                ]);
            }
        }

        $price = $bid_data['price'];
        $api = new ChannelLogic($item->channel_code);
        $qty = $params['qty'];
        try {
            //出价
            $bid_res = $api->bidOrUpdate($last_bid, $price, $qty, $product);
            $old_bidding_no = $bid_res['old_bidding_no'];
            $bidding_no = $bid_res['bidding_no'];
            $bid_status = $bid_res['bid_status'];
        } catch (\Exception $e) {
            $this->err_msg = '出价失败，' . $e->getMessage();
            $this->success = false;
            goto FAIL;
        }

        try {
            DB::beginTransaction();
            $item->update([
                'threshold_price' => $bid_data['threshold_price'] ?? 0,
                'price' => $price,
                'qty_bid' => $qty,
                'status' => StockBiddingItem::STATUS_SUCCESS,
                'qty_left' => 0
            ]);
            $where = [
                'channel_code' => $this->channel_code,
                'bidding_no' => $bidding_no,
                'spu_id' => $product['spu_id'],
                'sku_id' => $product['sku_id'],
                'source' => ChannelBidding::SOURCE_STOCK,
            ];
            $bidding = ChannelBidding::updateOrCreate($where, [
                'price' => $price,
                'price_jpy' => $bid_data['price_jpy'],
                'currency' => $bid_data['currency'],
                'price_unit' => $bid_data['price_unit'],
                'lowest_price' => $lowest['lowest_price'],
                'lowest_price_at' => $lowest['lowest_price_at'],
                'qty' => $qty,
                'qty_remain' => $qty,
                'good_name' => $product['name'],
                'product_sn' => $product['product_sn'],
                'properties' => $product['properties'],
                'spu_logo' => $product['pic'],
                'status' => $bid_status,
                'stock_bidding_id' => $item->stock_bidding_id,
                'stock_bidding_item_id' => $item->id,
                'old_bidding_no' => $old_bidding_no,
            ]);
            $item->update(['status' => StockBiddingItem::STATUS_SUCCESS]);
            DB::commit();
            $this->success = true;
            Redis::del($lock);
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->success = false;
            $this->err_msg = $e->getMessage();
            Redis::del($lock);
            return false;
        }

        FAIL:
        $update = [
            'threshold_price' => $bid_data['threshold_price'] ?? 0,
            'status' => StockBiddingItem::STATUS_FAIL,
            'fail_reason' => $this->err_msg
        ];
        if ($item->cancel_progress) {
            $update['cancel_progress'] = CarryMeBiddingItem::CANCEL_COMPLETE;
        }
        $item->update($update);
        Redis::del($lock);
        // Log::channel('daily2')->info('stock-bid-fail', [$this->err_msg, 'stock_bidding_item_id' => $item->id]);
        self::bidStockRefresh($item);
        BidCancel::cancelCompleted(null, $item, true);
        return false;
    }

    //库存出价，仅适用于CARRYME渠道出价
    public function bidSingleStockV2(StockBiddingItem $item)
    {
        // Log::channel('daily2')->info('bid', ['库存开始出价', 'stock_bidding_id' => $item->stock_bidding_id, 'stock_bidding_item_id' => $item->id]);
        $lock = RedisKey::stockItemBidLock($item);
        if (!Redis::setnx($lock, date('Y-m-d H:i:s'))) {
            return false;
        }

        $stock_product = $item->stockProduct;
        if ($stock_product->is_deleted) {
            $this->setErrorMsg('商品已清空');
            goto FAIL;
        }

        if ($item->cancel_progress) {
            $this->setErrorMsg('未出价已取消');
            goto FAIL;
        }

        $this->setChannelCode($item->channel_code);
        $params = [
            'product_sn' => $stock_product->product_sn,
            'properties' => [["valuecn" => $stock_product->properties]],
            'qty' => $item->qty,
        ];

        $find = self::channelConfig($item->channel_code);
        if (!$find || $find['stock_bid'] != Channel::ON) {
            $this->setErrorMsg('出价渠道已关闭');
            goto FAIL;
        }

        //商品信息
        $product = $this->_getProductInfo($params['product_sn'], $params['properties'], true);
        if (!$product || !$product['sku_id']) {
            $this->setErrorMsg('未找到对应的商品尺码信息');
            goto FAIL;
        }

        $api = new ChannelLogic($item->channel_code);
        //最低价
        $lowest = ProductLogic::lowestPrice($product);
        //有效出价
        $last_bid = $this->_getLastBid($item, $product);
        $params['last_bid'] = $last_bid;
        if ($stock_product->status !== StockProduct::STATUS_SHELF) {
            $this->setErrorMsg('商品已下架');
            if ($last_bid) {
                // 取消出价
                $api->biddingCancel($last_bid);
            }
            goto FAIL;
        }
        if ($last_bid) {
            // 同步下出价状态，已经取消的直接更新状态
            $last_bid = $api->syncBiddingInfo($last_bid);
            if ($last_bid->status != ChannelBidding::BID_SUCCESS) {
                $last_bid = null;
            }
        }

        //出价金额
        $bid_data = $api->stockBidPriceHandle($params, $lowest, $stock_product);
        if ($last_bid) {
            //出价金额没有变化，直接更新关联关系
            if ($last_bid->price == $bid_data['price']) {
                $last_item = $last_bid->stockBiddingItem;
                $old = [
                    'stock_bidding_id' => $last_bid->stock_bidding_id,
                    'stock_bidding_item_id' => $last_bid->stock_bidding_item_id,
                ];
                $update = [
                    'stock_bidding_id' => $item->stock_bidding_id,
                    'stock_bidding_item_id' => $item->id,
                ];
                $last_bid->update($update);
                $last_item->update(['status' => StockBiddingItem::STATUS_CANCEL, 'cancel_progress' => CarryMeBiddingItem::CANCEL_COMPLETE, 'remark' => '出价金额没有变化，关联关系被替换',]);
                $this->success = true;
                $item->update([
                    'threshold_price' => $bid_data['threshold_price'] ?? 0,
                    'price' => $bid_data['price'],
                    'qty_bid' => $params['qty'],
                    'status' => StockBiddingItem::STATUS_SUCCESS,
                    'qty_left' => 0
                ]);
                Redis::del($lock);
                // self::log('出价金额没有变化，直接更新关联关系', ['old' => $old, 'new' => $update]);
                $this->success = true;
                self::bidStockRefresh($last_item);
                BidCancel::cancelCompleted(null, $last_item, true);
                return true;
            }
            // 出价金额有变化，原出价取消
            $api->biddingCancel($last_bid);
        }


        if ($bid_data['price'] === null) {
            $this->setErrorMsg(sprintf('不满足出价规则 最低价%s 门槛价%s', $lowest['lowest_price_jpy'], $bid_data['threshold_price']));

            if ($last_bid && $last_bid->source_format == ChannelBidding::SOURCE_STOCK) {
                Excute::stockCancel([
                    'stock_bidding_item_id' => $last_bid->stock_bidding_item_id,
                    'remark' => '最低价/门槛价变更，本次出价取消',
                    'refreshStock' => false,
                    'product_sn' => $last_bid->product_sn,
                ]);
            }

            goto FAIL;
        }

        $price = $bid_data['price'];
        $api = new ChannelLogic($item->channel_code);
        $qty = $params['qty'];
        try {
            //出价
            $bid_res = $api->bidOrUpdate($last_bid, $price, $qty, $product);
            $old_bidding_no = $bid_res['old_bidding_no'];
            $bidding_no = $bid_res['bidding_no'];
            $bid_status = $bid_res['bid_status'];
        } catch (\Exception $e) {
            $this->setErrorMsg('出价失败，' . $e->getMessage());
            goto FAIL;
        }

        try {
            DB::beginTransaction();
            $item->update([
                'threshold_price' => $bid_data['threshold_price'] ?? 0,
                'price' => $price,
                'qty_bid' => $qty,
                'status' => StockBiddingItem::STATUS_SUCCESS,
                'qty_left' => 0
            ]);
            $where = [
                'channel_code' => $this->channel_code,
                'bidding_no' => $bidding_no,
                'spu_id' => $product['spu_id'],
                'sku_id' => $product['sku_id'],
                'source' => ChannelBidding::SOURCE_STOCK,
            ];
            $bidding = ChannelBidding::updateOrCreate($where, [
                'price' => $price,
                'price_jpy' => $bid_data['price_jpy'],
                'currency' => $bid_data['currency'],
                'price_unit' => $bid_data['price_unit'],
                'lowest_price' => $lowest['lowest_price'],
                'lowest_price_at' => $lowest['lowest_price_at'],
                'qty' => $qty,
                'qty_remain' => $qty,
                'good_name' => $product['name'],
                'product_sn' => $product['product_sn'],
                'properties' => $product['properties'],
                'spu_logo' => $product['pic'],
                'status' => $bid_status,
                'stock_bidding_id' => $item->stock_bidding_id,
                'stock_bidding_item_id' => $item->id,
                'old_bidding_no' => $old_bidding_no,
            ]);
            $item->update(['status' => StockBiddingItem::STATUS_SUCCESS]);
            DB::commit();
            $this->success = true;
            Redis::del($lock);

            if ($item->cancel_progress) {
                $item->update(['status' => StockBiddingItem::STATUS_CANCEL]);
                self::bidStockRefresh($item);
            }
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            Redis::del($lock);
            return false;
        }

        FAIL:
        $update = [
            'threshold_price' => $bid_data['threshold_price'] ?? 0,
            'status' => StockBiddingItem::STATUS_FAIL,
            'fail_reason' => $this->err_msg
        ];
        if ($item->cancel_progress) {
            $update['cancel_progress'] = CarryMeBiddingItem::CANCEL_COMPLETE;
        }
        $item->update($update);
        Redis::del($lock);
        // Log::channel('daily2')->info('库存出价失败', [$this->err_msg, 'stock_bidding_item_id' => $item->id]);
        self::bidStockRefresh($item);
        BidCancel::cancelCompleted(null, $item, true);
        return false;
    }

    // 刷新商品库存
    static function bidStockRefresh(StockBiddingItem $item)
    {
        StockProductLogic::stockUpdate($item->stock_product_id, StockProductLogic::STOCK_UPDATE_BID);
    }

    //按渠道业务独立出价
    public function bidSingle(CarryMeBiddingItem $item)
    {
        // Log::channel('daily2')->info('bid', ['app开始出价', 'carryme_bidding_id' => $item->carryme_bidding_id, 'carryme_bidding_item_id' => $item->id]);
        $lock = RedisKey::appItemBidLock($item);
        if (!Redis::setnx($lock, date('Y-m-d H:i:s'))) {
            return false;
        }

        $this->setChannelCode($item->channel_code);
        $carryme_bidding = $item->carrymeBidding;
        $params = [
            'product_sn' => $carryme_bidding->product_sn,
            'properties' => $carryme_bidding->properties,
            'price' => $item->price,
            'qty' => $carryme_bidding->qty,
        ];
        $carryme_id = $carryme_bidding->id;
        $business_type = $item->business_type;
        $info = ['carryme_bidding_item' => $item,];

        $find = self::channelConfig($item->channel_code);
        if (!$find || $find['app_bid'] != Channel::ON) {
            $this->setErrorMsg('出价渠道已关闭');
            $item->update(['status' => CarryMeBiddingItem::STATUS_FAIL, 'fail_reason' => $this->err_msg]);
            $this->bidNotice($carryme_bidding, $info);
            Redis::del($lock);
            return;
        }

        // 渠道新增之前未获取过商品信息，重新获取下
        $product = ChannelProduct::where(['product_sn' => $params['product_sn'], 'channel_code' => $item->channel_code])->first();
        if (!$product) {
            (new ProductLogic())->product(['product_sn' => $params['product_sn']]);
        }

        //商品信息
        $product = $this->_getProductInfo($params['product_sn'], $params['properties']);
        if ((!$product) || (!$product['sku_id'] ?? 0)) {
            $this->err_msg = '未找到对应的商品尺码信息';
            $this->success = false;
            $item->update(['status' => CarryMeBiddingItem::STATUS_FAIL, 'fail_reason' => $this->err_msg]);
            $this->bidNotice($carryme_bidding, $info);
            Redis::del($lock);
            return;
        }

        $last_bid = $this->_getLastBid($item, $product);
        $lowest = ProductLogic::lowestPrice($product);

        // 出价前被取消
        if ($item->cancel_progress == CarryMeBiddingItem::CANCEL_PENDING) {
            $this->err_msg = '未出价已取消，不再出价';
            $this->success = false;
            $item->update([
                'cancel_progress' => CarryMeBiddingItem::CANCEL_COMPLETE,
                'status' => CarryMeBiddingItem::STATUS_CANCEL
            ]);

            // 业务类型一致，新出价已生成，老出价就失效了，直接取消掉
            if ($last_bid && $last_bid->business_type == $item->business_type) {
                $item_remark = sprintf('新出价被取消 %d', $item->id);
                CarryMeBiddingItem::where(['id' => $last_bid->carryme_bidding_item_id])
                    ->update([
                        'cancel_progress' => CarryMeBiddingItem::CANCEL_PENDING,
                        'remark' => $item_remark,
                    ]);
                BidExecute::cancel($last_bid, $item_remark, false);
            }

            $info = ['carryme_bidding_item' => $item, 'channel_code' => $this->channel_code, 'business_type' => $business_type, 'lowest_price' => $lowest['lowest_price_jpy'], 'channel_bidding' => $last_bid, 'erp_success' => false];
            $this->bidNotice($carryme_bidding, $info);
            Redis::del($lock);
            return;
        }

        //出价金额处理
        $api = new ChannelLogic($item->channel_code);
        $params['last_bid'] = $last_bid;
        $bid_data = $api->bidPriceHandle($params, $lowest);

        $source_app = $last_bid ? ($last_bid->source_format == ChannelBidding::SOURCE_APP) : false;

        //非相同业务已出价
        if ($last_bid && $source_app && $last_bid->business_type != $item->business_type) {
            $last_item = $last_bid->carrymeBiddingItem;
            if ($last_bid->price < $bid_data['price'] || ($last_bid->price == $bid_data['price'] && $last_item && $item->price > $last_item->price)) {
                $this->success = true;
                $item->update(['status' => CarryMeBiddingItem::STATUS_FAIL, 'fail_reason' => '出价金额高于当前已出价金额']);
                $info = ['carryme_bidding_item' => $item, 'channel_code' => $this->channel_code, 'business_type' => $business_type, 'lowest_price' => $lowest['lowest_price_jpy'], 'channel_bidding' => $last_bid, 'erp_success' => false];
                $this->bidNotice($carryme_bidding, $info);
                Redis::del($lock);
                return;
            }
        }


        $api = new ChannelLogic($item->channel_code);
        //相同价格和数量已经出过价
        if ($last_bid && $source_app && $last_bid->qty == $bid_data['qty'] && $last_bid->price == $bid_data['price']) {
            $last_item = $last_bid->carrymeBiddingItem;
            // 上次出价正在取消的不再更新关联关系，直接新增出价。还是有效状态的，对比新老出价的数量和金额。
            if (!$last_item->cancel_progress) {
                $last_item->update(['status' => CarryMeBiddingItem::STATUS_CANCEL, 'remark' => '相同价格和数量出价新增出价，更新关联关系。']);
                // Log::channel('daily2')->info('bid', ['相同价格和数量出价新增出价，更新关联关系。', 'last_id' => $last_item->id, 'new_id' => $item->id]);
                $old_status = $last_bid->status;
                $last_bid->update([
                    'business_type' => $item->business_type,
                    'carryme_bidding_item_id' => $item->id,
                    'carryme_bidding_id' => $carryme_id,
                ]);
                $info =  ['carryme_bidding_item' => $item, 'channel_code' => $this->channel_code, 'business_type' => $business_type, 'lowest_price' => $lowest['lowest_price_jpy'], 'channel_bidding' => $last_bid, 'erp_success' => true];
                $item->update(['status' => CarryMeBiddingItem::STATUS_BID, 'qty_bid' => $bid_data['qty'], 'qty_left' => 0]);

                // 上一次出价在进行中，关联关系被替换，上次出价直接回调出价失败
                if ($old_status == ChannelBidding::BID_DEFAULT) {
                    $old_carryme = $last_bid->carrymeBidding;
                    $info = [
                        'carryme_bidding_item' => $last_item,
                        'channel_code' => $this->channel_code,
                        'business_type' => $last_item->business_type,
                        'lowest_price' => $lowest['lowest_price_jpy'],
                        'channel_bidding' => $last_bid,
                        'erp_success' => false
                    ];
                    $this->bidNoticeNew($old_carryme, $info, true);
                }

                BidCancel::cancelCompleted($last_item, null, true);
                $this->bidNotice($carryme_bidding, $info);
                Redis::del($lock);
                return;
            }
        }

        //出价时平台最低价
        $lowest_price = $lowest['lowest_price'];
        $lowest_price_jpy = $lowest['lowest_price_jpy'];
        $lowest_price_at = date('Y-m-d H:i:s');

        $price = $bid_data['price'];
        $qty = $bid_data['qty'];
        if (!$price) {
            $this->err_msg = '不符合跟价规则，不出价。';
            $this->success = false;
            // Robot::sendNotice($this->err_msg . Json::encode($item));
            if ($last_bid) { //取消之前的有效出价
                self::singleChannelCancel([
                    'bidding_no' => $last_bid->bidding_no,
                    'remark' => '价格更新取消',
                    'callback' => false,
                ]);
            }
            $info =  ['carryme_bidding_item' => $item, 'channel_code' => $this->channel_code, 'business_type' => $business_type, 'lowest_price' => $lowest_price_jpy, 'channel_bidding' => null, 'erp_success' => false];
            goto RES_FAIL;
        }

        //取消同规格下的库存出价
        if ($last_bid) {
            if (false === BidRule::isHightProfitBid($last_bid, $bid_data, ChannelBidding::SOURCE_APP)) {
                $this->err_msg = '不是最有利出价';
                $this->success = false;
                goto RES_FAIL;
            }
        }


        try {
            //出价
            $bid_res = $api->bidOrUpdate($last_bid, $price, $qty, $product);
            $old_bidding_no = $bid_res['old_bidding_no'];
            $bidding_no = $bid_res['bidding_no'];
            $bid_status = $bid_res['bid_status'];
        } catch (\Exception $e) {

            $this->err_msg = '出价失败，' . $e->getMessage();
            $this->success = false;
            $item->update(['status' => CarryMeBiddingItem::STATUS_FAIL, 'fail_reason' => $this->err_msg]);
            Robot::sendFail(sprintf('（出价）sku_id：%d，price：%d，qty:%d ，原因：%s', $product['sku_id'], $price, $qty, $e->getMessage()));
            $this->bidNotice($carryme_bidding, $info);
            Redis::del($lock);
            return;
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
                'channel_code' => $this->channel_code,
                'bidding_no' => $bidding_no,
                'spu_id' => $product['spu_id'],
                'sku_id' => $product['sku_id'],

            ];
            $bidding = ChannelBidding::updateOrCreate($where, [
                'business_type' => $business_type,
                'price' => $price,
                'currency' => $bid_data['currency'],
                'price_unit' => $bid_data['price_unit'],
                'price_rmb' => $bid_data['price_rmb'],
                'price_jpy' => $bid_data['price_jpy'],
                'lowest_price' => $lowest_price,
                'lowest_price_at' => $lowest_price_at,
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
                'source' => ChannelBidding::SOURCE_APP,
            ]);
            $item = $bidding->carrymeBiddingItem;
            DB::commit();
            $this->success = true;
            $info =  [
                'carryme_bidding_item' => $item,
                'channel_code' => $this->channel_code,
                'business_type' => $business_type,
                'lowest_price' => $lowest_price_jpy,
                'channel_bidding' => $bidding,
                'erp_success' => true
            ];

            // 出价中被取消
            if ($item && $item->cancel_progress == CarryMeBiddingItem::CANCEL_PENDING) {
                $info['erp_success'] = false;
                BidExecute::cancel($bidding, '出价中取消');
            }

            $this->bidNotice($carryme_bidding, $info);
            Redis::del($lock);
            return;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->success = false;
            $this->err_msg = $e->getMessage();
            $item->update([
                'status' => CarryMeBiddingItem::STATUS_FAIL,
                'qty_left' => 0,
                'fail_reason' => $this->err_msg
            ]);
            Robot::sendFail(sprintf('（出价信息保存），carryme_id：%d，bidding_no：%s', $carryme_id, $bidding_no));
        }

        $this->bidNotice($carryme_bidding, $info);
        Redis::del($lock);
        return;

        RES_FAIL:
        $item->update(['status' => CarryMeBiddingItem::STATUS_FAIL, 'fail_reason' => $this->err_msg]);
        // Log::channel('daily2')->info('bid', ['app出价失败', $this->err_msg, 'carryme_bidding_item_id' => $item->id]);
        Redis::del($lock);
        $this->bidNotice($carryme_bidding, $info);
        // Robot::sendFail(sprintf('（出价），carryme_bidding_item_id：%s，原因：%s ，规格：%s 渠道：%s', $item->id, $this->err_msg, Json::encode($params), $this->channel_code));
        // 出价失败时也触发下取消完成的事件
        BidCancel::cancelCompleted($item, null, true);
        return;
    }



    //出价结果更新
    public function bidSingleSuccess($params)
    {
        $where = [
            'channel_code' => $params['channel_code'],
            'bidding_no' => $params['bidding_no'],
            'spu_id' => $params['spu_id'],
            'sku_id' => $params['sku_id']
        ];
        $channel_bidding = ChannelBidding::where($where)->orderBy('id', 'desc')->first();
        if (!$channel_bidding) return;

        try {
            DB::beginTransaction();

            if ($channel_bidding->status == ChannelBidding::BID_DEFAULT) {
                $channel_bidding->update(['status' => ChannelBidding::BID_SUCCESS]);
            }

            if ($params['product_id'] ?? 0) {
                //channel_bidding_item表新增数据
                $where = [
                    'channel_bidding_id' => $channel_bidding->id,
                    'spu_id' => $params['spu_id'],
                    'sku_id' => $params['sku_id'],
                    'product_id' => $params['product_id'],
                ];

                $update = [
                    'price' => $params['price'],
                    'properties' => $params['properties'],
                    'qty' => 1,
                    'status' => $params['channel_bidding_item_status'],
                    'shelf_at' => $params['shelf_at'],
                    'takedown_at' => $params['takedown_at'],
                ];

                $log_data = [
                    'new_price' => $params['price'],
                    'product_id' => $params['product_id'],
                ];
                $type = '';
                $item = ChannelBiddingItem::where($where)->first();
                if (!$item) {
                    $type = ChannelBiddingLog::TYPE_ADD;
                }
                if ($item && $item->price != $params['price']) {
                    $type = ChannelBiddingLog::TYPE_UPDATE;
                    $log_data['old_price'] = $item->price;
                }

                //更新状态
                ChannelBiddingItem::updateOrCreate($where, $update);
                if ($type) {
                    //加日志
                    ChannelBiddingLog::addLog($type, $channel_bidding, $log_data);
                }
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Robot::sendFail(sprintf('(出价信息保存)，原因：%s，product：%s', $e->getMessage(), Json::encode($params)));
        }

        $this->success = true;

        // 如果出价单已经被取消，直接取消出价
        if ($channel_bidding->carryme_bidding_item_id) {
            $item = $channel_bidding->carrymeBiddingItem;
        }
        if ($channel_bidding->stock_bidding_item_id) {
            $item = $channel_bidding->stockBiddingItem;
            $product = $item->stockProduct;
            // 出价成功，商品被删除
            if ($product && $product->is_deleted) {
                BidExecute::cancel($channel_bidding, $item->remark, false);
            }
        }

        if ($item && $item->cancel_progress == CarryMeBiddingItem::CANCEL_PENDING) {
            BidExecute::cancel($channel_bidding, $item->remark, false);
        }

        if ($channel_bidding->source_format == ChannelBidding::SOURCE_APP) {
            $api = new ChannelLogic($channel_bidding->channel_code);
            $lowest_price = $channel_bidding->lowest_price;
            if ($channel_bidding->currency != 'JPY') {
                $lowest_price = $api->price2Jpy($lowest_price);
            }
            $this->bidNotice($channel_bidding->carrymeBidding, [
                'channel_code' => $channel_bidding->channel_code,
                'lowest_price' => $lowest_price,
                'business_type' => $channel_bidding->business_type,
                'channel_bidding' => $channel_bidding,
                'carryme_bidding_item' => $channel_bidding->carrymeBiddingItem,
                'erp_success' => true,
            ]);
        }
        return;
    }

    // 出价失败
    public function bidFail($params)
    {
        $where = ['bidding_no' => $params['bidding_no'], 'channel_code' => $params['channel_code']];
        $bidding = ChannelBidding::where($where)->whereIN('status', [ChannelBidding::BID_SUCCESS, ChannelBidding::BID_DEFAULT])->first();
        if (!$bidding) return false;
        $update = [
            'qty_cancel' => 1,
            'qty_remain' => 0,
            'status' => ChannelBidding::BID_CANCEL,
        ];
        //更新出价状态
        $bidding->update($update);

        // 库存出价
        if ($bidding->stock_bidding_item_id) {
            $stock_item = $bidding->stockBiddingItem;
            $stock_item->update(['status' => StockBiddingItem::STATUS_FAIL, 'fail_reason' => $params['fail_reason']]);
            self::bidStockRefresh($stock_item);
        }

        // app出价
        if ($bidding->carryme_bidding_item_id) {
            $carryme_item = $bidding->carrymeBiddingItem;
            $carryme_bidding = $bidding->carrymeBidding;
            $carryme_item->update(['status' => CarryMeBiddingItem::STATUS_FAIL, 'fail_reason' => $params['fail_reason']]);
            $info =  ['carryme_bidding_item' => $carryme_item, 'erp_success' => false,];
            $this->bidNoticeNew($carryme_bidding, $info);
        }
        return true;
    }

    // 取消出价成功调用
    public function bidCancelSuccess($params)
    {
        $where = ['bidding_no' => $params['bidding_no'], 'channel_code' => $params['channel_code']];
        $bidding = ChannelBidding::where($where)->whereIN('status', [ChannelBidding::BID_SUCCESS, ChannelBidding::BID_DEFAULT])->first();
        if (!$bidding) return false;

        $update = [
            'qty_cancel' => 1,
            'qty_remain' => 0,
            'status' => ChannelBidding::BID_CANCEL,
        ];
        //更新出价状态
        $bidding->update($update);


        // app取消回调
        if ($bidding->source_format == ChannelBidding::SOURCE_APP) {
            $item = $bidding->carrymeBiddingItem;
            if (!$item) return false;

            $update = ['status' => CarryMeBiddingItem::STATUS_CANCEL];
            if ($params['remark'] ?? '') $update['remark'] = $params['remark'];
            if ($item->cancel_progress) $update['cancel_progress'] = CarryMeBiddingItem::CANCEL_COMPLETE;
            $item->update($update);

            BidCancel::cancelCompleted($item, null, true);
        } else {
            $item = $bidding->stockBiddingItem;
            $item->update(['status' => StockBiddingItem::STATUS_CANCEL, 'cancel_progress' => CarryMeBiddingItem::CANCEL_COMPLETE]);
            self::bidStockRefresh($item);
            BidCancel::cancelCompleted(null, $item, true);
        }
        return true;
    }

    //出价结果回调
    public function bidNotice($carryme_bidding, $res)
    {
        if (!$carryme_bidding->callback_id) return;

        if ($this->success) {
            $channel_bidding = $res['channel_bidding'];
            if ($channel_bidding->status == ChannelBidding::BID_DEFAULT) {
                //出价提交成功，暂时不回调
                return;
            }
        }
        $carryme_bidding_item = $res['carryme_bidding_item'];

        $data = [
            'businessType' => CarryMeBidding::getCarrymeBusinessType($carryme_bidding_item->business_type), //出价类型 0现货 1预售 2闪电直发
            'code' => $carryme_bidding_item->channel_code, //渠道编码
            'lowestPrice' => $res['lowest_price'] ?? 0, //最低价
            'isSuccess' => $this->success,
            'messageId' => $carryme_bidding->id,
            "erpBidItemId" => $carryme_bidding_item->id,
            'thirdPartyProductRecordId' => $carryme_bidding->callback_id,
            'carryme_bidding_item_id' => $carryme_bidding_item->id,
            'carryme_bidding_id' => $carryme_bidding_item->carryme_bidding_id,
            'erpSuccess' => $res['erp_success'] ?? false,
        ];
        CarrymeCallbackLogic::bidSuccess($data);
    }

    //出价结果回调
    private function bidNoticeNew($carryme_bidding, $res, $end = false)
    {
        if (!$carryme_bidding->callback_id) return;
        $carryme_bidding_item = $res['carryme_bidding_item'];
        $data = [
            'businessType' => CarryMeBidding::getCarrymeBusinessType($carryme_bidding_item->business_type), //出价类型 0现货 1预售 2闪电直发
            'code' => $carryme_bidding_item->channel_code, //渠道编码
            'lowestPrice' => $res['lowest_price'] ?? 0, //最低价
            'isSuccess' => true,
            'messageId' => $carryme_bidding->id,
            "erpBidItemId" => $carryme_bidding_item->id,
            'thirdPartyProductRecordId' => $carryme_bidding->callback_id,
            'carryme_bidding_item_id' => $carryme_bidding_item->id,
            'carryme_bidding_id' => $carryme_bidding_item->carryme_bidding_id,
            'erpSuccess' => $res['erp_success'] ?? false,
        ];
        CarrymeCallbackLogic::bidSuccess($data);
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

        $ret = [];
        //carryme_bidding_items列表数据
        $items = CarryMeBiddingItem::where('carryme_bidding_id', $params['carryme_bidding_id'])->get();
        $success_num = 0;
        $total = 0;
        foreach ($items as $item) {
            $total++;
            if (in_array($item->business_type, [ChannelBidding::BUSINESS_TYPE_BLOT, ChannelBidding::BUSINESS_TYPE_SPOT])) {
                $this->setChannelCode($item->channel_code);
                $res = $this->_biddingCancelBlot($item);
                if ($res['success']) $success_num++;
                $ret['list'][] = $res;
            }
        }
        $ret['total'] = $total;
        $ret['success_num'] = $success_num;
        return $ret;
    }

    //批量取消出价
    public function batchCancel($params): array
    {
        $items = CarryMeBiddingItem::whereHas('carrymeBidding', function ($query) use ($params) {
            $query->where('product_sn', $params['product_sn']);
        })->get();

        foreach ($items as $item) {
            self::singleCancelQueue([
                'carryme_bid_item_id' => $item->id,
                'remark' => '按货号批量取消出价',
                'callback' => $params['callback'] ?? false,
            ]);
        }
        return [];
    }


    static function cancelByBid($params)
    {
        $items = CarryMeBiddingItem::where(['carryme_bidding_id' => $params['carryme_bid_id']])
            ->whereIn('status', [CarryMeBiddingItem::STATUS_BID, CarryMeBiddingItem::STATUS_DEFAULT])
            ->get();
        foreach ($items as $item) {
            self::singleCancelQueue([
                'carryme_bid_item_id' => $item->id,
                'remark' => $params['remark'] ?? '',
                'callback' => $params['callback'] ?? false,
            ]);
        }
    }

    //单渠道取消
    static function singleCancelQueue($params)
    {
        $item = CarryMeBiddingItem::where(['id' => $params['carryme_bid_item_id']])->first();

        $update = ['cancel_progress' => CarryMeBiddingItem::CANCEL_PENDING];
        if ($params['callback'] ?? false) {
            $update['cancel_callback'] = 1;
        }
        CarryMeBiddingItem::where(['id' => $params['carryme_bid_item_id']])->update($update);
        $biddings = ChannelBidding::where([
            'carryme_bidding_item_id' => $params['carryme_bid_item_id']
        ])->whereIn('status', [ChannelBidding::BID_DEFAULT, ChannelBidding::BID_SUCCESS])->get();

        // 可取消的出价单不存在
        if (!count($biddings)) {
            $carryme = $item->carrymeBidding;
            $params['carryme_bidding_item_id'] = $params['carryme_bid_item_id'];
            $params['product_sn'] = $carryme ? $carryme->product_sn : '100001';
            BidQueue::appSampleCancel($params);
            return;
        }

        $params['carryme_bidding_item_id'] = $params['carryme_bid_item_id'];
        foreach ($biddings as $bidding) {
            $params['bidding_no'] = $bidding->bidding_no;
            $params['product_sn'] = $bidding->product_sn;
            // BidQueue::appSingleChanelCancel($params);
            Excute::appCancel($params);
        }
    }

    //渠道列表
    static function getChannels()
    {
        $data = Channel::where('status', Channel::STATUS_ON)->select(['code', 'name'])->get();
        return array_map(function (&$item) {
            $item['code2'] = strtolower($item['code']);
            return $item;
        }, $data);
    }

    //根据货号和规格获取商品信息
    private function _getProductInfo($product_sn, $property, $stock_bid = false)
    {
        //根据货号和规格，从channel_product 和 channel_product_sku中获取sku_id 和 spu_id
        $product = ChannelProduct::where(['product_sn' => $product_sn, 'channel_code' => $this->channel_code, 'status' => ChannelProduct::STATUS_ACTIVE])->with('skus')->first();
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

    //取消出价 - 闪电直发、现货
    private function _biddingCancelBlot(CarryMeBiddingItem $item)
    {
        $this->success = true;
        $cancel_num = 0; //取消出价数量
        $bid_num = 0; //不能撤销的数量
        $re_onqueue = false;

        //出价失败、出价已取消的，也算取消成功
        if (in_array($item->status, [CarryMeBiddingItem::STATUS_CANCEL, CarryMeBiddingItem::STATUS_FAIL])) {
            goto RES;
        }

        //已经出价的调用取消出价接口
        if ($item->status == CarryMeBiddingItem::STATUS_BID) {

            $bids = $item->channelBids;

            foreach ($bids as $bid) {
                //存在出价未回调的状态，加入队列等待出价成功后执行
                if ($bid->status == ChannelBidding::BID_DEFAULT) {
                    $this->success = false;
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
            'success' => $this->success,
            'qty' => $item->qty,
            'qty_bid' => $bid_num,
            'qty_cancel' => $cancel_num,
            're_onqueue' => $re_onqueue, //处理未完成，重新加入队列
        ];
    }

    /**
     * 库存出价单渠道取消
     *
     * @param array $params
     */
    static function stockSingleChannelCancel($params)
    {
        // 查出价信息
        $item = StockBiddingItem::where(['id' => $params['stock_bidding_item_id']])->first();
        if (!$item) return true;

        $api = new ChannelLogic($item->channel_code);
        if ($item->status != StockBiddingItem::STATUS_SUCCESS) goto RET;

        $bidding = ChannelBidding::where(['stock_bidding_item_id' => $item->id])->orderBy('id', 'desc')->first();
        if (!$bidding) goto RET;

        // 出价中等待出价完成再取消
        if ($bidding->status == ChannelBidding::BID_DEFAULT) {
            $params['stock_bidding_item_id'] = $item->id;
            $params['product_sn'] = $bidding->product_sn;
            // BidQueue::stockSingleChannelCancel($params);
            // Excute::stockCancel($params);
            return false;
        }

        // 取消出价
        $res = $api->biddingCancel($bidding);
        if (!$res) {
            // 出价单取消失败
            return false;
        }

        try {
            DB::beginTransaction();
            // 更新渠道出价单、库存出价单状态
            $item->update([
                'status' => StockBiddingItem::STATUS_CANCEL,
                'remark' => $params['remark'] ?? '',
                'cancel_progress' => CarryMeBiddingItem::CANCEL_COMPLETE,
            ]);
            $bidding->update([
                'status' => ChannelBidding::BID_CANCEL
            ]);
            DB::commit();
            self::bidStockRefresh($item);
            goto RET;
        } catch (Exception $e) {
            DB::rollBack();
            Log::info($e->__toString());
            return false;
        }
        RET:

        $bidding = ChannelBidding::where(['stock_bidding_item_id' => $item->id])->orderBy('id', 'desc')->first();
        if ($api->bidCancelIsSync() || (!$bidding) || in_array($bidding->status, [ChannelBidding::BID_CANCEL, ChannelBidding::BID_FAIL])) {
            BidCancel::cancelCompleted(null, $item, true);
        }
        // 执行取消出价后的后续操作
        if ($params['after_action'] ?? []) {
            call_user_func_array($params['after_action'][0], [$params['after_action'][1] ?? []]);
        }
        return true;
    }

    /**
     * 库存出价单渠道取消
     *
     * @param array $params
     */
    static function stockItemCancelV2($params)
    {
        $msg = '';
        // 查出价信息
        $item = StockBiddingItem::where(['id' => $params['stock_bidding_item_id']])->first();
        if (!$item) return true;
        $lock = RedisKey::stockItemBidLock($item);
        if (!Redis::setnx($lock, date('Y-m-d H:i:s'))) {
            return ['result' => false, 'msg' => '出价中，暂不处理'];
        }

        $api = new ChannelLogic($item->channel_code);
        $bidding = ChannelBidding::where(['stock_bidding_item_id' => $item->id])->orderBy('id', 'desc')->first();
        if (!$bidding && $item->status == StockBiddingItem::STATUS_DEFAULT) {
            $item->update(['status' => StockBiddingItem::STATUS_CANCEL, 'cancel_progress' => CarryMeBiddingItem::CANCEL_COMPLETE]);
            $msg = '未出价已取消';
            BidCancel::cancelCompleted(null, $item, true);
            goto RET;
        }

        // 关联关系被更新
        if (!$bidding) {
            $item->update(['status' => StockBiddingItem::STATUS_CANCEL]);
            BidCancel::cancelCompleted(null, $item, true);
            Redis::del($lock);
            self::bidStockRefresh($item);
            return ['result' => true, 'msg' => '关联关系被更新，直接取消'];
        }

        if ($bidding->status == ChannelBidding::BID_DEFAULT) {
            // 超过2分钟没有出价结果，重入队列前补偿刷新一次
            if ($bidding->channel_code == 'GOAT' && strtotime($bidding->created_at) < time() - 120) {
                (new BiddingAsyncLogic())->refreshBidResult(['ext_tag' => $bidding->bidding_no, 'spu_id' => $bidding->spu_id]);
            }
            $msg = '等获取到出价结果再取消';
            goto RET2;
        }

        if (in_array($bidding->status, [ChannelBidding::BID_CANCEL, ChannelBidding::BID_FAIL])) {
            $msg = '出价已取消/失败';
            $item->update(['status' => StockBiddingItem::STATUS_CANCEL]);
            BidCancel::cancelCompleted(null, $item, true);
            goto RET;
        }

        $api->biddingCancel($bidding);

        try {
            DB::beginTransaction();
            // 更新渠道出价单、库存出价单状态
            $item->update([
                'status' => StockBiddingItem::STATUS_CANCEL,
                'remark' => $params['remark'] ?? '',
                'cancel_progress' => CarryMeBiddingItem::CANCEL_COMPLETE,
            ]);
            $bidding->update([
                'status' => ChannelBidding::BID_CANCEL
            ]);
            DB::commit();
            self::bidStockRefresh($item);
            goto RET;
        } catch (Exception $e) {
            DB::rollBack();
            Log::info($e->__toString());
            Redis::del($lock);
            $msg = '出价结果保存失败';
            goto RET2;
        }

        RET:
        $bidding = ChannelBidding::where(['stock_bidding_item_id' => $item->id])->orderBy('id', 'desc')->first();
        if ($api->bidCancelIsSync() || (!$bidding) || in_array($bidding->status, [ChannelBidding::BID_CANCEL, ChannelBidding::BID_FAIL])) {
            BidCancel::cancelCompleted(null, $item, true);
        }
        Redis::del($lock);
        return ['result' => true, 'msg' => $msg];

        RET2:
        Redis::del($lock);
        return ['result' => false, 'msg' => $msg];
    }

    static function appItemCancelV2($params)
    {
        // self::log('bid', $params);
        $msg = '';
        $success = false;
        $item = CarryMeBiddingItem::where(['id' => $params['carryme_bidding_item_id']])->first();
        $api = new ChannelLogic($item->channel_code);

        $lock = RedisKey::appItemBidLock($item);
        if (!Redis::setnx($lock, date('Y-m-d H:i:s'))) {
            return ['result' => false, 'msg' => '出价中，暂不处理'];
        }

        $bidding = ChannelBidding::where(['carryme_bidding_item_id' => $params['carryme_bidding_item_id']])->orderBy('id', 'desc')->first();
        if (!$bidding) {
            $cancel_data = ['status' => CarryMeBiddingItem::STATUS_CANCEL, 'cancel_progress' => CarryMeBiddingItem::CANCEL_COMPLETE];

            if ($item->status == CarryMeBiddingItem::STATUS_DEFAULT) {
                $item->update($cancel_data);
                $msg = '未出价已取消';
                BidCancel::cancelCompleted($item, null, $success);
                goto RES;
            }

            if (in_array($item->status, [CarryMeBiddingItem::STATUS_CANCEL, CarryMeBiddingItem::STATUS_FAIL])) {
                $msg = '未出价已取消/失败';
                $item->update($cancel_data);
                BidCancel::cancelCompleted($item, null, $success);
                goto RES;
            }

            if ($item->status == CarryMeBiddingItem::STATUS_BID) {
                $msg = '关联关系被取代，直接取消';
                $item->update($cancel_data);
                BidCancel::cancelCompleted($item, null, $success);
                goto RES;
            }

            // 超过5天没有出价成功，直接失败
            if ($item->updated_at < date('Y-m-d H:i:s', time() - 5 * 24 * 3600)) {
                $msg = '超时未出价取消';
                BidCancel::cancelCompleted($item, null, $success);
                goto RES;
            }

            $msg = '出价中，暂不处理';
            goto RET2;
        }



        if ($bidding->status == ChannelBidding::BID_DEFAULT) {
            if ($bidding->channel_code == 'GOAT' && strtotime($bidding->created_at) < time() - 120) {
                (new BiddingAsyncLogic())->refreshBidResult(['ext_tag' => $bidding->bidding_no, 'spu_id' => $bidding->spu_id]);
            }
            $msg = '等获取到出价结果再取消';
            goto RET2;
        }

        if (!in_array($bidding->business_type, ChannelBidding::$cross_border_business)) {
            $msg = '数据异常';
            goto RES;
        }

        $success = $api->biddingCancel($bidding);

        $item = $bidding->carrymeBiddingItem;
        $update = ['status' => CarryMeBiddingItem::STATUS_CANCEL,];
        if ($params['remark'] ?? '') $update['remark'] = $params['remark'];
        if ($item && $item->cancel_progress) $update['cancel_progress'] = CarryMeBiddingItem::CANCEL_COMPLETE;
        $item->update($update);

        RES:
        $bidding = ChannelBidding::where(['carryme_bidding_item_id' => $params['carryme_bidding_item_id']])->orderBy('id', 'desc')->first();
        if ($api->bidCancelIsSync() || ($bidding && in_array($bidding->status, [ChannelBidding::BID_CANCEL, ChannelBidding::BID_FAIL]))) {
            BidCancel::cancelCompleted($item, null, $success);
        }
        Redis::del($lock);
        return ['result' => true, 'msg' => $msg];

        RET2:
        Redis::del($lock);
        return ['result' => false, 'msg' => $msg];
    }


    /**
     * app出价-单渠道取消
     *
     * @param array $params
     *  - bidding_no 出价单号
     *  - refreshStock 是否刷新库存出价
     *  - callback 是否回调通知Carryme
     *  - remark 备注
     */
    static function singleChannelCancel($params)
    {
        $re_onqueue = false;
        $success = true;
        $callback = $params['callback'] ?? false;

        $bidding = ChannelBidding::where(['bidding_no' => $params['bidding_no']])->orderBy('id', 'desc')->first();
        if (!$bidding) goto RES;

        if ($bidding->status == ChannelBidding::BID_DEFAULT) {
            $re_onqueue = true;

            // 超过2分钟没有出价结果，重入队列前补偿刷新一次
            $msg = sprintf('出价进行中取消，等待出价完成。出价信息：%s', Json::encode($bidding));
            if (strtotime($bidding->created_at) < time() - 120) {
                $re = (new BiddingAsyncLogic())->refreshBidResult(['ext_tag' => $params['bidding_no'], 'spu_id' => $bidding->spu_id]);
                $msg .= sprintf(' 补偿结果：%s', Json::encode($re));
            }
            Robot::sendNotice($msg);

            //重新加入队列
            // BidQueue::appSingleChanelCancel([
            //     'bidding_no' => $bidding->bidding_no,
            //     'remark' => $params['remark'],
            //     'callback' => $callback,
            //     'delay' => 1,
            //     'product_sn' => $bidding->product_sn,
            // ]);
            goto RES;
        }
        if (!in_array($bidding->business_type, ChannelBidding::$cross_border_business)) {
            Robot::sendNotice(sprintf('非闪电和现货出价取消。出价信息：%s', Json::encode($bidding)));
            goto RES;
        }

        $api = new ChannelLogic($bidding->channel_code);
        $api->biddingCancel($bidding);

        $item = $bidding->carrymeBiddingItem;
        if (!$item) goto RES;

        $update = [];
        if ($params['remark'] ?? '') $update['remark'] = $params['remark'];
        if ($api->bidCancelIsSync()) {
            $update = ['status' => CarryMeBiddingItem::STATUS_CANCEL,];
            if ($item && $item->cancel_progress) $update['cancel_progress'] = CarryMeBiddingItem::CANCEL_COMPLETE;
        }
        if ($update) $item->update($update);

        RES:
        return compact('re_onqueue', 'success');
    }

    /**
     * app未出价就取消
     *
     * @param array $params
     */
    static function singleSampleCancel($params)
    {
        $update = ['cancel_progress' => CarryMeBiddingItem::CANCEL_COMPLETE, 'status' => CarryMeBiddingItem::STATUS_CANCEL,];
        if ($params['remark'] ?? '') {
            $update['remark'] = $params['remark'];
        }

        $item = CarryMeBiddingItem::where(['id' => $params['carryme_bidding_item_id']])->first();
        if (!$item) return false;
        
        try {
            DB::beginTransaction();

            $bidding = ChannelBidding::where(['carryme_bidding_item_id' => $params['carryme_bidding_item_id']])->whereIn('status', [ChannelBidding::BID_DEFAULT, ChannelBidding::BID_SUCCESS])->orderBy('id', 'desc')->first();

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            BidQueue::appSampleCancel($params);
            return false;
        }

        // 有进行中的出价，延时等出价完成再处理
        if ($bidding) {
            BidQueue::appSampleCancel($params);
            return false;
        }

        $item->update($update);
        // 取消成功回调
        if ($params['callback'] ?? false) {
            $carryme = $item->carrymeBidding;
            CarrymeCallbackLogic::bidCancel(null, true, [
                'carryme_bidding_id' => $item->carryme_bidding_id,
                'result' => true,
                'carryme_bidding_item_id' => $item->id,
                'callback_id' => $carryme ? $carryme->callback_id : 0,
            ]);
        }
        return true;
    }

    //刷新goat出价结果
    public function refreshBidResult($params)
    {
        $goat = new GOAT();
        $logic = new BiddingAsyncLogic();

        $ext_tag = $params['ext_tag'];
        $p = [];
        if ($ext_tag) {
            $p['ext_tag'] = $ext_tag;
        } else {
            $p['pt_id'] = $params['spu_id'];
        }

        //获取商品信息
        $res = (new GoatApi())->productSearch($p);
        $array = [];
        foreach ($res['products'] as $product) {

            if (!($product['extTag'] ?? '')) {
                continue;
            }
            if (!in_array($product['saleStatus'], ['active', 'completed'])) {
                continue;
            }

            $bidding = ChannelBidding::where(['bidding_no' => $product['extTag'], 'channel_code' => 'GOAT', 'status' => ChannelBidding::BID_DEFAULT])->first();
            if (!$bidding) continue;

            $params = [
                'channel_code' => $bidding->channel_code,
                'bidding_no' => $product['extTag'],
                'spu_id' => $product['productTemplateId'],
                'sku_id' => $goat->getSkuId($product['productTemplateId'], $product['size']),
                'product_id' => $product['id'],
                'price' => $product['priceCents'],
                'properties' => ['size' => $product['size']],
                'channel_bidding_item_status' => in_array($product['saleStatus'], ['active', 'completed']) ? ChannelBiddingItem::STATUS_SHELF : ChannelBiddingItem::STATUS_TAKEDOWN,
                'shelf_at' => $product['createdAt'],
                'takedown_at' => $product['updatedAt'],
            ];
            $logic->bidSingleSuccess($params);
            $array[] = $product['extTag'];
        }

        return $array;
    }

    /**
     * 库存出价全渠道新增
     *
     * @param array $params
     */
    public function stockBiddingAdd($params)
    {
        // self::log('库存出价', $params);
        $carryme_id = $params['carryme_id'] ?? 0;
        if ($carryme_id) {
            $key = 'erp:stock_bid:' . $carryme_id;
            if (Redis::get($key)) return true;
            Redis::setex($key, 120, 1);
        }

        $product_init = $params['product_init'] ?? false;
        $stock_product = $params['stock_product'] ?? null;
        if ((!$stock_product) && ($params['product_sn'] ?? '') && ($params['properties'] ?? '')) {
            $stock_product = StockProduct::where(['product_sn' => $params['product_sn'], 'properties' => $params['properties'], 'is_deleted' => 0])->first();
        }
        if (!$stock_product) {
            $msg = sprintf('未获取到库存商品信息 %s', Json::encode($params));
            Robot::sendNotice($msg);
            return false;
        }

        $lock = RedisKey::sotckBidLock($stock_product->id);
        if (!Redis::setnx($lock, date('Y-m-d H:i:s'))) {
            Robot::sendNotice('库存出价中 stock_product_id：' . $stock_product->id);
            return false;
        }

        $where = ['product_sn' => $stock_product->product_sn, 'status' => StockProduct::STATUS_SHELF, 'is_deleted' => 0];
        // 同货号同规格库存到手价最低的stock_product
        try {
            DB::beginTransaction();
            $model = StockProduct::where($where)
                ->whereRaw("properties='{$stock_product->properties}'");
            if (!$product_init) {
                $model->whereRaw('stock-bidding_stock-order_stock>0');
            }
            $product_min = $model->groupBy(['product_sn', 'properties'])
                ->select(['product_sn', 'properties', DB::raw('min(finnal_price) as finnal_price')])->get()->toArray();
            $stock_product = null;
            if ($product_min) {
                $where['product_sn'] = $product_min[0]['product_sn'];
                $where['finnal_price'] = $product_min[0]['finnal_price'];
                $stock_product = StockProduct::where($where)
                    ->whereRaw("properties='{$product_min[0]['properties']}'")
                    ->whereRaw('stock-bidding_stock-order_stock>0')
                    ->first();
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Robot::sendException($e->__toString());
        }

        if (!$stock_product) {
            // 没获取到最低价商品信息
            $msg = sprintf('没有可出价的商品。参数 %s', Json::encode($params));
            Robot::sendNotice($msg);
            Redis::del($lock);
            return false;
        }

        if ($stock_product->status == StockProduct::STATUS_TAKE_DOWN) {
            $msg = sprintf('商品已下架，暂不出价。货号 %s 规格%s remark：%s', $stock_product->product_sn, $stock_product->properties, $params['remark'] ?? '');
            Robot::sendNotice($msg);
            Redis::del($lock);
            return false;
        }

        // 剩余可出价库存  = 总库存 - 出价库存 - 订单库存
        if ($stock_product->stock - $stock_product->bidding_stock - $stock_product->order_stock <= 0) {
            // 无剩余可出价库存
            $msg = sprintf('库存告罄。货号 %s 规格%s', $stock_product->product_sn, $stock_product->properties);
            Robot::sendNotice($msg);

            JobsStockProduct::dispatch(['id' => $stock_product->id, 'action' => JobsStockProduct::ITEM_CLEAR])->onQueue('product');
            Redis::del($lock);
            return false;
        }
        // self::log('当前库存信息', $stock_product->toArray());

        $bid = StockBidding::create([
            'stock_product_id' => $stock_product->id,
            'qty' => 1,
            'remark' => $params['remark'] ?? '',
            'stock_batch_no' => $stock_product->batch_no,
        ]);

        $channels = BiddingLogic::getChannels();
        foreach ($channels as $channel) {
            $item = StockBiddingItem::create([
                'stock_bidding_id' => $bid->id,
                'stock_product_id' => $stock_product->id,
                'channel_code' => $channel['code'],
                'business_type' => 0,
                'qty' => 1,
                'remark' => $params['remark'] ?? '',
            ]);

            Excute::stockBid([
                'stock_bidding_id' => $bid->id,
                'stock_bidding_item_id' => $item->id,
                'product_sn' => $stock_product->product_sn,
                'remark' => $params['remark'] ?? '',
            ]);
        }

        StockProductLogic::stockUpdate($stock_product->id, StockProductLogic::STOCK_UPDATE_BID);
        Redis::del($lock);
        return true;
    }

    /**
     * 取消商品下的所有库存出价
     *
     * @param array $params
     */
    public function stockBiddingCancel($params)
    {
        $product_sn = $params['product_sn'] ?? '';
        $properties = $params['properties'] ?? '';

        $where[] = [function ($query) {
            $query->whereIn('status', [StockBiddingItem::STATUS_SUCCESS, StockBiddingItem::STATUS_DEFAULT]);
        }];
        if ($params['stock_product_id'] ?? 0) {
            $where['stock_product_id'] = $params['stock_product_id'];
        }
        if ($params['stock_bidding_id'] ?? 0) {
            $where['stock_bidding_id'] = $params['stock_bidding_id'];
        }

        $model = StockBiddingItem::where($where);
        if (($params['product_sn'] ?? '') && ($params['properties'] ?? '')) {
            $product_ids = StockProduct::where(['product_sn' => $params['product_sn'], 'properties' => $params['properties']])->pluck('id');
            if ($product_ids) {
                $model->whereIn('stock_product_id', $product_ids);
            }
        }

        $stock_bid_id = 0;
        $bids = $model->get();
        $num = 0;
        foreach ($bids as $bid) {
            $bid->update(['cancel_progress' => CarryMeBiddingItem::CANCEL_PENDING]);
            $params['stock_bidding_item_id'] = $bid->id;
            $params['refreshStock'] = false;
            $params['product_sn'] = $bid->stockProduct ? $bid->stockProduct->product_sn : '';
            $stock_bid_id = $bid->stock_bidding_id;
            // BidQueue::stockSingleChannelCancel($params);
            Excute::stockCancel($params);
            if (!(in_array($bid->channel_code, ['GOAT', 'STOCKX']) && $bid->qty_sold > 0)) {
                $num++;
            }
        }

        // 全部取消后，重新出价
        if ($params['rebid'] ?? false) {
            $remark = $params['remark'] ?? '取消商品下所有库存出价';
            if ($stock_bid_id) {
                Redis::hset(RedisKey::BID_AFTER_CANCEL, $stock_bid_id, $remark . '，重新出价');
            } else {
                // 不用取消，直接出价
                if ($params['stock_product_id'] ?? 0) {
                    $product = StockProduct::where(['id' => $params['stock_product_id']])->first();
                    // StockProductLogic::stockUpdate($product->id, StockProductLogic::STOCK_UPDATE_BID);
                    $product_sn = $product->product_sn;
                    $properties = $product->properties;
                }
                if ($product_sn && $properties) {
                    (new BiddingAsyncLogic())->stockBiddingAdd([
                        'product_sn' => $product_sn,
                        'properties' => $properties,
                        'remark' => $remark,
                    ]);
                }
            }
        }

        // 不用取消出价，直接执行后续操作
        if ((!$bids->count()) || (!$num)) {
            if ($params['after_action'] ?? []) {
                call_user_func_array($params['after_action'][0], [$params['after_action'][1] ?? []]);
            }
        }
    }

    /**
     * 取消指定渠道的库存出价
     *
     * @param  StockBiddingItem $bid
     */
    public function stockBiddingCancelSingleChannel($bid, $params = [])
    {
        $stock_product = $bid->stockProduct;
        if (!$stock_product) return;
        Excute::stockCancel([
            'remark' => $params['remark'] ?? '',
            'stock_bidding_item_id' => $bid->id,
            'product_sn' => $stock_product->product_sn,
        ]);
        // BidQueue::stockSingleChannelCancel([
        //     'remark' => $params['remark'] ?? '',
        //     'stock_bidding_item_id' => $bid->id,
        //     'product_sn' => $stock_product->product_sn,
        // ]);
    }

    //全渠道刷新出价
    public function biddingRefresh($bidding)
    {
        switch ($bidding->source_format) {

            case ChannelBidding::SOURCE_STOCK:
                $stock_bidding = $bidding->stockBidding;
                // 商品重新上架
                $this->stockBiddingAdd(['stock_product' => $stock_bidding->stockProduct, 'remark' => '库存订单成立，刷新出价']);
                break;

            case ChannelBidding::SOURCE_APP:
                // app订单成立，刷新库存出价
                $carrymeBidding = $bidding->carrymeBidding;
                if ($carrymeBidding->properties[0]['valuecn'] ?? '') {
                    // 先取消库存当前出价，然后再刷新库存出价
                    $this->stockBiddingCancel([
                        'product_sn' => $carrymeBidding->product_sn,
                        'properties' => $carrymeBidding->properties[0]['valuecn'] ?? '',
                        'remark' => 'app订单成立，下架当前库存出价',
                        'after_action' => [[new BiddingAsyncLogic(), 'stockBiddingAdd'], [
                            'product_sn' => $carrymeBidding->product_sn,
                            'properties' => $carrymeBidding->properties[0]['valuecn'] ?? '',
                            'remark' => 'app订单成立，刷新出价'
                        ]],
                    ]);

                    // $where = [
                    //     'product_sn' => $carrymeBidding->product_sn, 
                    //     'properties' => $carrymeBidding->properties[0]['valuecn'] ?? ''
                    // ];
                    // $stock_product = StockProduct::where($where)->get();

                    // $this->stockBiddingAdd(['product_sn' => $carrymeBidding->product_sn, 'properties' => $carrymeBidding->properties[0]['valuecn'] ?? '', 'remark' => 'app订单成立，刷新出价']);
                }
                break;
        }
    }

    /**
     * 取消订单对应的全渠道出价
     *
     * @param ChannelBidding $bidding
     * @param string $remark
     * @return void
     */
    static function orderBiddingCancel($bidding, $remark)
    {
        $stock_bidding = $bidding->stockBidding;
        if (!$stock_bidding) return;

        // 订单成立后，立即取消所有出价
        foreach ($stock_bidding->stockBiddingItems as $item) {
            BiddingAsyncLogic::stockSingleChannelCancel([
                'stock_bidding_item_id' => $item->id,
                'remark' => $remark
            ]);
        }
    }

    //刷新出价
    public function bidRefresh($bidding, $action_at = '')
    {
        if (!$action_at)  $action_at = date('Y-m-d H:i:s');

        $this->err_msg = '';

        if ($bidding->status != ChannelBidding::BID_SUCCESS) {
            $this->err_msg = '非出价成功状态，不再刷新';
            return false;
        }

        $key = RedisKey::orderLock($bidding->carryme_bidding_id . '_' . $bidding->stock_bidding_id);
        if (Redis::get($key)) {
            $this->err_msg = '订单生成中，暂不刷新';
            return false;
        }

        $carryme = new CarryMeApi();
        // 查当前出价是否还有效
        $res = $carryme->bidVerify(['carryme_bidding_id' => $bidding->carryme_bidding_id]);
        if (!$res) {
            $this->err_msg = '出价失效，不再刷新';
            self::singleChannelCancel([
                'bidding_no' => $bidding->bidding_no,
                'remark' => '出价失效',
                'callback' => false,
            ]);
            return false;
        }


        $carryme_bid = $bidding->carrymeBidding;
        if (!$carryme_bid) return false;

        // 取消出价，不再刷新
        $carryme_item = $bidding->carrymeBiddingItem;
        if ($carryme_item && $carryme_item->cancel_progress) {
            $this->err_msg = '出价取消，不再刷新';
            return false;
        }

        // 原始出价已经取消，不再刷新
        if ($carryme_item->status != CarryMeBiddingItem::STATUS_BID) {
            $this->err_msg = '原始出价已取消，不再刷新';
            return false;
        }

        // 有新增出价，不再刷新
        $new = CarryMeBidding::where([
            'product_sn' => $carryme_bid->product_sn,
            'sku_id' => $carryme_bid->sku_id,
        ])->where('created_at', '>', $action_at)->first();
        if ($new) {
            // Robot::sendNotice('有新出价，不再刷新');
            $this->err_msg = '有新出价，不再刷新';
            return false;
        }

        $bids = ChannelBidding::where(['carryme_bidding_id' => $bidding->carryme_bidding_id])->get();
        $has_order = false;
        foreach ($bids as $bid) {
            if ($bid->channelOrders->toArray()) {
                $has_order = true;
                break;
            }
        }
        if ($has_order) {
            $this->err_msg = '有订单生成，不再刷新';
            return false;
        }

        $carryme_bidding = $bidding->carrymeBidding;
        $item = $bidding->carrymeBiddingItem;

        // 查询出价单状态
        $channel = new DW();
        $detail = $channel->getBiddingDetail($bidding->bidding_no);
        if ($detail['status'] != 1) {
            $this->err_msg = '出价单已取消，不再刷新';
            return false;
        }

        $params = [
            'price' => $item->price,
            'qty' => $item->qty,
            'product_sn' => $carryme_bidding->product_sn,
            'properties' => $carryme_bidding->properties,
            'business_type' => $bidding->business_type,
        ];

        //最低价
        $product = $this->_getProductInfo($params['product_sn'], $params['properties']);
        $lowest = ProductLogic::lowestPrice($product);

        //出价金额处理
        $api = new ChannelLogic($item->channel_code);
        $params['last_bid'] = $bidding;
        $bid_data = $api->bidPriceHandle($params, $lowest);

        $price = $bid_data['price'];
        $qty = $params['qty'];
        if ($price == $bidding->price && $qty == $bidding->qty) {
            $this->err_msg = '价格数量没有变化，无需刷新';
            return false;
        }
        if (!$price) {
            $this->err_msg = '出价金额为空，不刷新';
            return false;
        }

        // 调出价更新接口
        try {
            $bid_res = $api->bidOrUpdate($bidding, $price, $qty, $product);
        } catch (\Exception $e) {
            //价格更新失败
            $this->err_msg = '刷新失败，原因：' . $e->__toString();
            return false;
        }

        $bidding->update([
            'status' => ChannelBidding::BID_CANCEL,
        ]);
        $params['source'] = 'app';
        $this->_saveBiddingInfo($item, $product, $bid_data, $lowest, $bid_res, $params);

        // 向CarryMe同步最低价
        CarrymeCallbackLogic::syncLowestPrice([
            'callback_id' => $carryme_bidding->callback_id,
            'lowest' => $lowest,
            'carryme_bidding_item_id' => $bidding->carryme_bidding_item_id,
            'carryme_bidding_id' => $bidding->carryme_bidding_id,
            'channel_code' => $bidding->channel_code,
        ]);

        $this->err_msg = sprintf('原出价金额：%s 新出价金额：%s', $bidding->price, $bid_data['price']);
        return true;
    }

    //出价成功，保存信息
    private function _saveBiddingInfo($item, $product, $bid_data, $lowest, $bid_res, $params)
    {
        $price = $params['price'];
        $qty = $params['qty'];
        $old_bidding_no = $bid_res['old_bidding_no'];
        $bidding_no = $bid_res['bidding_no'];
        $bid_status = $bid_res['bid_status'];

        try {
            DB::beginTransaction();
            $item->update([
                'qty_bid' => $qty,
                'status' => CarryMeBiddingItem::STATUS_BID,
                'updated_at' => date('Y-m-d H:i:s'),
                'qty_left' => 0
            ]);
            $where = [
                'channel_code' => $this->channel_code,
                'bidding_no' => $bidding_no,
                'spu_id' => $product['spu_id'],
                'sku_id' => $product['sku_id'],

            ];
            ChannelBidding::updateOrCreate($where, [
                'business_type' => $params['business_type'],
                'price' => $bid_data['price'],
                'currency' => $bid_data['currency'],
                'price_unit' => $bid_data['price_unit'],
                'price_rmb' => $bid_data['price_rmb'],
                'price_jpy' => $bid_data['price_jpy'] ?? 0,
                'lowest_price' => $lowest['lowest_price'],
                'lowest_price_at' => $lowest['lowest_price_at'],
                'qty' => $qty,
                'qty_remain' => $qty,
                'good_name' => $product['name'],
                'product_sn' => $product['product_sn'],
                'properties' => $product['properties'],
                'spu_logo' => $product['pic'],
                'status' => $bid_status,
                'carryme_bidding_id' => $item->carryme_bidding_id,
                'carryme_bidding_item_id' => $item->id,
                'old_bidding_no' => $old_bidding_no,
                'source' => $params['source'] ?? '',
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            //出价信息保存失败，触发补偿机制
            $arr = compact('product', 'bid_data', 'lowest', 'bid_res', 'params');
            $arr['item_id'] = $item->id;
            $msg = sprintf('出价成功，出价单数据保存失败，请尽快处理。%s  %s', Json::encode($arr), $e->__toString());
            Robot::sendFail($msg);
            return false;
        }

        return true;
    }


    /**
     * 三方出价信息
     *
     * @param array $params
     * @return void
     */
    static function search($params)
    {
        $size = $params['size'] ?? 10;
        $cur_page = $params['cur_page'] ?? 1;
        $where = [];
        if ($params['product_sn'] ?? '') {
            $where['product_sn'] = $params['product_sn'];
        }
        if ($params['status'] ?? '') {
            $where['status'] = $params['status'];
        }
        if ($params['channel_code'] ?? '') {
            $where['channel_code'] = strtoupper($params['channel_code']);
        }
        if ($params['sku_id'] ?? '') {
            $where['sku_id'] = $params['sku_id'];
        }
        if ($params['spu_id'] ?? '') {
            $where['spu_id'] = $params['spu_id'];
        }
        if ($params['bidding_no'] ?? '') {
            $where['bidding_no'] = $params['bidding_no'];
        }
        if ($params['source'] ?? '') {
            $where['source'] = $params['source'];
        }

        $select = ['*'];
        $model = ChannelBidding::where($where)->orderBy('id', 'desc');
        if ($params['properties'] ?? '') {
            $model->where('properties', 'like', '%"' . $params['properties'] . '"%');
        }
        return $model->paginate($size, $select, 'page', $cur_page);
    }

    function stockProductRefreshBid($product, $remark = '定时刷新'): bool
    {
        $lock = 'erp:stock_product_rebid:' . $product->id;
        if (Redis::get($lock)) {
            return true;
        }
        Redis::setex($lock, 1800, date('Y-m-d H:i:s'));

        if (!$remark) $remark = '定时刷新';

        Robot::sendNotice(sprintf('库存刷新出价 id:%s 货号%s 规格%s remark:%s', $product->id, $product->product_sn, $product->properties, $remark));

        $bidding = StockBidding::where(['stock_product_id' => $product->id])->orderBy('id', 'desc')->first();
        // 之前没有出价
        if (!$product->bidding_stock) {
            $this->stockBiddingAdd(['stock_product' => $product, 'remark' => $remark . '出价']);
            return true;
        }
        if (!$bidding) return false;

        // 原始出价取消
        StockBiddingItem::where(['stock_bidding_id' => $bidding->id, 'qty_sold' => 0])
            ->whereIn('status', [StockBiddingItem::STATUS_SUCCESS, StockBiddingItem::STATUS_DEFAULT])
            ->update(['status' => StockBiddingItem::STATUS_CANCEL, 'cancel_progress' => 1, 'remark' => $remark . '取消']);
        // 更新锁定库存
        StockProductLogic::stockUpdate($product->id, StockProductLogic::STOCK_UPDATE_BID);
        // 新增出价
        $this->stockBiddingAdd(['stock_product' => $product, 'remark' => $remark . '出价']);
        return true;

        // if ($items->count() == 0) {
        //     $this->stockBiddingAdd(['stock_product' => $product, 'remark' => $remark . '出价']);
        //     return true;
        // }

        // Redis::hset(RedisKey::BID_AFTER_CANCEL, $bidding->id, $remark . '出价');
        // foreach ($items as $item) {
        //     Excute::stockCancel(['product_sn' => $product->product_sn, 'stock_bidding_item_id' => $item->id, 'remark' => $remark . '取消']);
        //     // BidExecute::stockItemCancel(['stock_bidding_item_id' => $item->id, 'remark' => $remark . '取消']);
        // }
        // return true;
    }

    //刷新得物库存出价
    public function stockBidRefresh($bidding, $action_at = '')
    {
        // self::log('bid', ['desc' => '库存刷新出价 bidding_no:' . $bidding->bidding_no]);
        if (!$action_at)  $action_at = date('Y-m-d H:i:s');
        $this->err_msg = '';

        if ($bidding->status != ChannelBidding::BID_SUCCESS) {
            $this->err_msg = '非出价成功状态，不再刷新';
            return false;
        }

        $key = RedisKey::orderLock($bidding->carryme_bidding_id . '_' . $bidding->stock_bidding_id);
        if (Redis::get($key)) {
            $this->err_msg = '订单生成中，暂不刷新';
            return false;
        }

        $stock_bid = $bidding->stockBidding;
        if (!$stock_bid) return false;

        // 取消出价，不再刷新
        $stock_item = $bidding->stockBiddingItem;
        if ($stock_item && $stock_item->cancel_progress) {
            $this->err_msg = '出价取消，不再刷新';
            return false;
        }
        $stock_product = $stock_item->stockProduct;
        $lock = 'erp:stock_product_rebid:' . $stock_product->id;
        if (Redis::get($lock)) {
            $this->err_msg = '已重新出价，不再刷新';
            return false;
        }
        if (!StockProduct::canBid($stock_product)) {
            $this->err_msg = '商品已下架或删除，不再刷新';
            return false;
        }
        $dw_channel = $stock_product->dwChannel;
        if ($dw_channel && $dw_channel->threshold_price != $stock_item->threshold_price) {
            $this->err_msg = '门槛价已变更，不再刷新';
            return false;
        }

        // 原始出价已经取消，不再刷新
        if ($stock_item->status != StockBiddingItem::STATUS_SUCCESS) {
            $this->err_msg = '原始出价已取消，不再刷新';
            return false;
        }

        // 有新增出价，不再刷新
        $new = StockBidding::where(['stock_product_id' => $stock_item->stock_product_id,])->where('created_at', '>', $action_at)->first();
        if ($new) {
            $this->err_msg = '有新出价，不再刷新';
            return false;
        }

        $bids = ChannelBidding::where(['stock_bidding_id' => $bidding->stock_bidding_id])->get();
        $has_order = false;
        foreach ($bids as $bid) {
            if ($bid->channelOrders->toArray()) {
                $has_order = true;
                break;
            }
        }
        if ($has_order) {
            $this->err_msg = '有订单生成，不再刷新';
            return false;
        }

        // 查询出价单状态
        $channel = new DW();
        $detail = $channel->getBiddingDetail($bidding->bidding_no);
        if ($detail['status'] != 1) {
            $this->err_msg = '出价单已取消，不再刷新';
            return false;
        }

        $params = [
            'qty' => $stock_item->qty,
            'product_sn' => $stock_product->product_sn,
            'properties' => [["valuecn" => $stock_product->properties]],
            'business_type' => $bidding->business_type,
        ];

        //最低价
        $product = $this->_getProductInfo($params['product_sn'], $params['properties'], true);
        $lowest = ProductLogic::lowestPrice($product, true);

        //出价金额处理
        $api = new ChannelLogic($stock_item->channel_code);
        $params['last_bid'] = $bidding;
        $bid_data = $api->stockBidPriceHandle($params, $lowest, $stock_product);

        $price = $bid_data['price'];
        $qty = $params['qty'];
        if ($price == $bidding->price && $qty == $bidding->qty) {
            $this->err_msg = '价格数量没有变化，无需刷新';
            return false;
        }

        // 调出价更新接口
        try {
            $bid_res = $api->bidOrUpdate($bidding, $price, $qty, $product);
        } catch (\Exception $e) {
            //价格更新失败
            $this->err_msg = '刷新失败，原因：' . $e->__toString();
            return false;
        }

        $bidding->update(['status' => ChannelBidding::BID_CANCEL,]);
        $params['source'] = $bidding->source;

        try {
            DB::beginTransaction();
            $stock_item->update([
                'threshold_price' => $bid_data['threshold_price'] ?? 0,
                'price' => $price,
                'qty_bid' => $qty,
                'status' => StockBiddingItem::STATUS_SUCCESS,
                'qty_left' => 0
            ]);
            $where = [
                'channel_code' => $this->channel_code,
                'bidding_no' => $bid_res['bidding_no'],
                'spu_id' => $product['spu_id'],
                'sku_id' => $product['sku_id'],
                'source' => ChannelBidding::SOURCE_STOCK,
            ];
            ChannelBidding::updateOrCreate($where, [
                'business_type' => $params['business_type'],
                'price' => $price,
                'price_jpy' => $bid_data['price_jpy'],
                'currency' => $bid_data['currency'],
                'price_unit' => $bid_data['price_unit'],
                'lowest_price' => $lowest['lowest_price'],
                'lowest_price_at' => $lowest['lowest_price_at'],
                'qty' => $qty,
                'qty_remain' => $qty,
                'good_name' => $product['name'],
                'product_sn' => $product['product_sn'],
                'properties' => $product['properties'],
                'spu_logo' => $product['pic'],
                'status' => $bid_res['bid_status'],
                'stock_bidding_id' => $stock_item->stock_bidding_id,
                'stock_bidding_item_id' => $stock_item->id,
                'old_bidding_no' => $bid_res['old_bidding_no'],
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            //出价信息保存失败，触发补偿机制
            $arr = compact('product', 'bid_data', 'lowest', 'bid_res', 'params');
            $arr['item_id'] = $stock_item->id;
            $msg = sprintf('出价成功，出价单数据保存失败，请尽快处理。%s  %s', Json::encode($arr), $e->__toString());
            Robot::sendFail($msg);
            return false;
        }

        $this->err_msg = sprintf('原出价金额：%s 新出价金额：%s', $bidding->price, $bid_data['price']);
        return true;
    }

    // 保存渠道配置
    function setChannelConfig($params)
    {
        $find = Channel::where('code', $params['channel_code'])->first();
        if (!$find) {
            $this->setErrorMsg('渠道不存在');
            return [];
        }
        $find->update([
            'status' => $params['status'],
            'stock_bid' => $params['stock_bid'],
            'app_bid' => $params['app_bid'],
        ]);
        Redis::del(RedisKey::CHANNEL_CONFIG);
        return $find;
    }

    // 获取渠道配置
    static function channelConfig($channel_code)
    {
        $arr = Redis::get(RedisKey::CHANNEL_CONFIG);
        $info = [];
        if ($arr) {
            $info = json_decode($arr, true)[$channel_code] ?? [];
            if ($info) return $info;
        }

        $arr = Channel::get()->keyBy('code')->toArray();
        Redis::set(RedisKey::CHANNEL_CONFIG, json_encode($arr));
        return $arr[$channel_code] ?? [];
    }

    // 渠道最低价刷新
    function lowestPirceRefresh($channel_code, $data)
    {
        $this->channel_code = $channel_code;
        // self::log('lowestPirceRefresh', $data);
        // {"id":"89668","productId":"7993","productSn":"DX1419-300","spData":"[{\"key\":\"nike M 22.cm\",\"value\":\"22.5\",\"valuecn\":\"35.5\"}]","price":0.0}
        $api = new ChannelLogic($channel_code);
        // 根据渠道匹配商品信息
        $product = $this->_getProductInfo($data['productSn'], json_decode($data['spData'], true), true);
        if (!$product || !$product->sku_id) {
            // self::log('未获取到商品信息', $data);
            return false;
        }

        // 获取当前出价
        $item = new StockBiddingItem();
        $item->channel_code = $channel_code;
        $last_bid = $this->_getLastBid($item, $product);
        // 根据sku找到库存商品
        $query = StockProductChannel::leftJoin('stock_products', 'stock_product_channels.stock_product_id', '=', 'stock_products.id')
            ->where('channel_product_sku_id', $product->id)
            ->where('channel_code', $channel_code)
            ->where('stock_products.is_deleted', 0)
            ->where('stock_products.status', 0);
        $result = $query->select('stock_product_channels.stock_product_id');
        $stock_product_ids = $result->pluck('stock_product_id')->toArray();
        $stock_product = null;
        if ($stock_product_ids) {
            $stock_product = StockProduct::where('id', $stock_product_ids[0])->first();
        }
        if (!$stock_product) {
            // self::log('未获取到库存商品信息', $data);
            return false;
        }

        //出价金额
        $params = [
            'product_sn' => $stock_product->product_sn,
            'properties' => [["valuecn" => $stock_product->properties]],
            'qty' => $item->qty,
            'last_bid' => $last_bid,
        ];
        $lowest = [
            'lowest_price' => $data['price'],
            'lowest_price_jpy' => $api->bidPrice2Jpy($data['price']),
        ];
        $bid_data = $api->stockBidPriceHandle($params, $lowest, $stock_product);
        if (!$bid_data['price']) {
            // self::log('不满足出价规则', $data);
            return false;
        }
        if ($last_bid && $bid_data['price'] == $last_bid['price']) {
            // self::log('出价金额无变化，无需刷新', $data);
            return false;
        }
        $this->stockProductRefreshBid($stock_product, $channel_code . '最低价变更3');
        return true;
    }
}
