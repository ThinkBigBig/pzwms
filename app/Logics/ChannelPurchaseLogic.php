<?php

namespace App\Logics;

use App\Logics\channel\CARRYME;
use App\Logics\channel\DW;
use App\Models\ChannelBidding;
use App\Models\ChannelProduct;
use App\Models\ChannelProductSku;
use App\Models\ChannelPurchaseBidding;
use App\Models\PurchasePriceConfig;
use App\Models\PurchaseProduct;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Psy\Util\Json;

/**
 * 从三方渠道获取到最低价，并按照最低价*1.05的价格出价到CARRYME APP
 */
class ChannelPurchaseLogic extends BaseLogic
{
    // 渠道最低价有更新，加入队列，准备出价到CARRYME
    static function pushQueue($params)
    {
        if ($params['channel_code'] != 'DW') return false;
        if (!env('CHANNEL_PURCHASE_CARRYME', false)) return false;
        // if (!$params['lowest_price']) return false;
        if ($params['product_sn'] ?? '') {
            $find = PurchaseProduct::where('status', PurchaseProduct::ACTIVE)->where('product_sn', $params['product_sn'])->first();
            if (!$find) return false;
        }


        Redis::rpush(RedisKey::CHANNEL_PURCHASE_QUEUE, Json::encode($params));
        return true;
    }

    // 添加/删除可空卖商品
    static function product($params)
    {
        $product_sn = $params['product_sn'];
        $status = $params['status'];
        PurchaseProduct::updateOrCreate(['channel_code' => 'DW', 'product_sn' => $params['product_sn']], ['status' => $status,]);
        if ($status == PurchaseProduct::ACTIVE) {
            $data = [
                'product_init' => 1,
                'product_sn' => $product_sn,
            ];
            Redis::rpush(RedisKey::PURCHASE_PRODUCT_QUEUE, json_encode($data));
            return true;
        }
        return true;
    }

    // 新增价格配置规则
    function addPriceConfig($params)
    {
        $min = $params['min'] ?? 0;
        $max = $params['max'];
        $formula = $params['formula'];
        $channel_code = $params['channel_code'] ?? 'DW';
        $product_sn = $params['product_sn'] ?? '';
        if ($max < $min) {
            $this->setErrorMsg('输入值不合法');
            return false;
        }
        $raw = sprintf('((minimum>%d and minimum<%d) or (maximum>%d and maximum<%d) or (minimum<%d and maximum>=%d))', $min, $max, $min, $max, $min, $max);
        $find = PurchasePriceConfig::where('status', PurchasePriceConfig::ACTIVE)->where('channel_code', $channel_code)->where('product_sn', $product_sn)->whereRaw($raw)->first();

        // 查区间是否有重叠
        if ($find) {
            $this->setErrorMsg(sprintf('范围有重叠 (%d - %d]', $find->minimum, $find->maximum));
            return false;
        }

        // 试用计算公式是否有效
        $origin = 1000;
        $num = PurchasePriceConfig::calculate($origin, $formula, $product_sn);
        if ($num <= $origin) {
            $this->setErrorMsg('试算未通过');
            return false;
        }

        PurchasePriceConfig::updateOrCreate([
            'minimum' => $min,
            'maximum' => $max,
            'status' => PurchasePriceConfig::ACTIVE,
            'channel_code' => $channel_code,
            'product_sn' => $product_sn
        ], [
            'formula' => $formula,
            'admin_user_id' => $params['admin_user_id']
        ]);
        return true;
    }

    // 删除价格配置规则
    function delPriceConfig($params)
    {
        $min = $params['min'] ?? 0;
        $max = $params['max'];
        $channel_code = $params['channel_code'] ?? 'DW';
        PurchasePriceConfig::where([
            'minimum' => $min,
            'maximum' => $max,
            'status' => PurchasePriceConfig::ACTIVE,
            'channel_code' => $channel_code,
            'product_sn' => $params['product_sn'] ?? '',
        ])->update([
            'status' => PurchasePriceConfig::INACTIVE,
            'admin_user_id' => $params['admin_user_id']
        ]);
        return true;
    }

    // 执行出价
    function bid($params)
    {
        // self::log('三方渠道空卖到APP出价', $params);
        $channel_product = ChannelProduct::where('id', $params['cp_id'])->first();
        $channel_product_sku = ChannelProductSku::where([
            'spu_id' => $params['spu_id'],
            'sku_id' => $params['sku_id'],
            'cp_id' => $params['cp_id'],
        ])->first();
        if (!$channel_product_sku) return false;
        $qty = 1;

        // 现有出价
        $last_bid = ChannelPurchaseBidding::where([
            'source' => $params['channel_code'],
            'platform' => 'CARRYME',
            'spu_id' => $params['spu_id'],
            'sku_id' => $params['sku_id'],
            'status' => ChannelPurchaseBidding::SUCCESS,
            'qty_sold' => 0,
        ])->orderBy('id', 'desc')->first();

        $api = new CARRYME();
        if ($last_bid) {
            $last_channel_bid = $last_bid->channelBidding;
            if ($last_channel_bid) { // 同步下原出价信息，防止出价单已经被操作取消
                $last_channel_bid = $api->syncBiddingInfo($last_channel_bid);
                if ($last_channel_bid->status != ChannelBidding::BID_SUCCESS) {
                    $last_bid = null;
                }
            }

            // 最低价为0现有出价取消
            if ($last_bid && (!$params['lowest_price'])) {
                $last_bidding = $last_bid->channelBidding;
                $last_bid->update(['status' => ChannelPurchaseBidding::CANCEL, 'cancel_remark' => '最低价为0出价取消']);
                $res = $api->biddingCancel($last_bidding);
                return false;
            }
        }

        $origin = ChannelPurchaseBidding::create([
            'lowest_price' => $params['lowest_price'],
            'lowest_price_at' => $params['lowest_price_at'],
            'source' => $params['channel_code'],
            'platform' => 'CARRYME',
            'product_sn' => $channel_product->product_sn,
            'properties' =>  $channel_product_sku->properties,
            'spu_id' => $params['spu_id'],
            'sku_id' => $params['sku_id'],
            'qty' => $qty,
        ]);
        // 商品信息匹配
        $carryme_sku = $this->_channelSizeMatchCarryme($channel_product, $channel_product_sku);
        if (!$carryme_sku || !($carryme_sku['sku_id'] ?? 0)) {
            $origin->update(['status' => ChannelPurchaseBidding::FAIL, 'fail_reason' => '未匹配到商品信息',]);
            return false;
        }
        $channel = new ChannelLogic($params['channel_code']);
        // 出价金额计算
        $lowest_price = $params['lowest_price'];
        $price_origin = PurchasePriceConfig::calculate($lowest_price, '', $channel_product->product_sn);
        $bid_price = $channel->RMB2Jpy($price_origin);
        $lowest_price_jpy = $channel->RMB2Jpy($lowest_price);

        if ($last_bid) {
            // 和原出价金额相同，新出价失败
            if ($last_bid->price == $bid_price) {
                $origin->update([
                    'status' => ChannelPurchaseBidding::FAIL,
                    'fail_reason' => '相同金额已出价，无需更新',
                    'price' => $bid_price,
                ]);
                $last_bid->lowest_price_at = $params['lowest_price_at'];
                $last_bid->save();
                return false;
            }
            $last_bidding = $last_bid->channelBidding;
            // 和原出价金额不同，取消原出价
            $res = $api->biddingCancel($last_bidding);
            if (!$res) {
                $origin->update([
                    'status' => ChannelPurchaseBidding::FAIL,
                    'fail_reason' => '原出价金额取消失败，id:' . $last_bid->id,
                    'price' => $bid_price,
                ]);
                return false;
            }
            $last_bid->update(['status' => ChannelPurchaseBidding::CANCEL, 'cancel_remark' => '最低价变更出价取消']);
        }


        try {
            // 开始执行新出价
            $res = $api->channelPurchaseBid($bid_price, $qty, $carryme_sku);
        } catch (Exception $e) {
            // 出价失败
            $origin->update([
                'status' => ChannelPurchaseBidding::FAIL,
                'fail_reason' => '出价失败 原因:' . $e->getMessage(),
                'price' => $bid_price,
                'lowest_price_jpy' => $lowest_price_jpy,
            ]);
            return false;
        }

        try {
            DB::beginTransaction();
            $where = [
                'bidding_no' => $res['bidding_no'],
                'channel_code' => 'CARRYME',
                'source' => $params['channel_code'],
                'spu_id' => $carryme_sku['spu_id'],
                'sku_id' => $carryme_sku['sku_id'],
            ];
            ChannelBidding::updateOrCreate($where, [
                'price' => $bid_price,
                'qty' => $qty,
                'lowest_price' => $params['lowest_price'],
                'lowest_price_at' => $params['lowest_price_at'],
                'currency' => 'JPY',
                'price_unit' => 2,
                'price_jpy' => $bid_price,
                'qty_remain' => $qty,
                'good_name' => $carryme_sku['name'],
                'product_sn' => $carryme_sku['product_sn'],
                'properties' => $carryme_sku['properties'],
                'spu_logo' => $carryme_sku['pic'],
                'status' => $res['bid_status'],
            ]);

            // 更新 channel_purchase_biddings
            $origin->update([
                'bidding_no' => $res['bidding_no'],
                'status' => ChannelPurchaseBidding::SUCCESS,
                'price' => $bid_price,
                'lowest_price_jpy' => $lowest_price_jpy,
            ]);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            // 出价信息保存失败
            Robot::sendFail(sprintf('[渠道空卖]出价信息保存失败，origin:%s res:%s', $origin->id, Json::encode($res)));
            // 更新 channel_purchase_biddings
            $origin->update([
                'bidding_no' => $res['bidding_no'],
                'status' => ChannelPurchaseBidding::SUCCESS,
                'price' => $bid_price,
                'lowest_price_jpy' => $lowest_price_jpy,
            ]);
            return false;
        }
    }

    // 渠道尺码匹配CARRYME尺码
    private function _channelSizeMatchCarryme(ChannelProduct $product, ChannelProductSku $sku)
    {
        // {"尺码":"45.5"}  匹配  [{"key":"nike M 22.cm","value":"29.5","valuecn":"45.5"}]
        $size = $sku->properties['尺码'] ?? '';
        if (!$size) return [];

        // 找到对应的属性并进行封装
        $property = [['valuecn' => $sku->properties['尺码'] ?? '']];
        $product = ChannelProduct::where(['product_sn' => $product->product_sn, 'channel_code' => 'CARRYME'])->with('skus')->first();
        if (!$product) return [];

        if ($product && $product['skus']) {
            $api = new ChannelLogic($product['channel_code']);
            foreach ($product['skus'] as $sku) {
                $match = $api->stockMatchSku($sku, $property, $product);
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


    // 出价取消
    function bidCancel($purchase_bidding, $params)
    {
        $bidding = $purchase_bidding->channelBidding;
        if (!$bidding) return true;

        $api = new CARRYME();
        if ($api->biddingCancel($bidding)) {
            $purchase_bidding->update(['status' => ChannelPurchaseBidding::CANCEL, 'cancel_remark' => $params['cancel_remark'] ?? '']);
            return true;
        }
        return false;
    }
}
