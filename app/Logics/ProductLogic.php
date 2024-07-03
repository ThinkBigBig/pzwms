<?php

namespace App\Logics;

use App\Handlers\GoatApi;
use App\Handlers\OSSUtil;
use App\Models\ChannelProduct;
use App\Models\ChannelProductSku;
use App\Models\SizeStandard;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ProductLogic extends BaseLogic
{
    public function product($params)
    {
        $product_sn = $params['product_sn'];
        $update = $params['update'] ?? false;

        //获取在售渠道
        $channels = BiddingLogic::getChannels();
        foreach ($channels as $channel) {

            $where = ['product_sn' => $product_sn, 'channel_code' => $channel['code'], 'status' => ChannelProduct::STATUS_ACTIVE];
            $product = ChannelProduct::where($where)->first();
            //不存在或更新
            if (!$product || $update) {
                $api = new ChannelLogic($channel['code']);
                try {
                    $data = $api->getProductInfo($product_sn);
                    $data = $api->productDetailFormat($data);
                    //保存数据
                    self::saveChannelProduct($data, $channel['code']);
                } catch (\Exception $e) {
                    $msg = sprintf('商品信息获取失败。渠道：%s，货号：%s ，原因：%s', $channel['code'], $product_sn, $e->getMessage());
                    Robot::sendText(Robot::FAIL_MSG, $msg);
                }
            }
        }

        return ChannelProduct::where('product_sn', $product_sn)
            ->select([
                DB::raw('GROUP_CONCAT( distinct(channel_code) ) AS channel_codes'),
                DB::raw('min(id) as id'),
                'good_name', 'good_name_cn', 'spu_logo', 'brand_name', 'category_name', 'product_sn'
            ])
            ->with('skus')
            ->groupBY('product_sn')
            ->get();
    }

    //保存三方渠道商品信息
    private function saveChannelProduct($data, $channel_code): void
    {
        if (!$data) return;

        $info = $data['info'];
        $info['channel_code'] = $channel_code;

        $where = ['channel_code' => $channel_code, 'spu_id' => $info['spu_id']];
        if ($data['spu_logo'] ?? '') {
            $info['spu_logo'] = $data['spu_logo'];
        } else {
            $filename = '';
            if ($info['spu_logo_origin'] ?? '') {
                //上传spu_logo到oss
                $filename = sprintf('spu_logo/%s_%s_%s.%s', $info['product_sn'], $channel_code, genRandomString(), pathinfo($info['spu_logo_origin'])['extension']);
                $oss = new OSSUtil();
                $oss->addFileByUrl($filename, $info['spu_logo_origin']);
            } else {
                // 找其他渠道看能否获取到商品图片
                $filename = ChannelProduct::where(['product_sn' => $info['product_sn']])->where('spu_logo', '>', '')->value('spu_logo');
                if (!$filename) $filename = '';
            }

            $info['spu_logo'] = $filename;
        }


        DB::beginTransaction();
        try {
            $product = ChannelProduct::updateOrCreate($where, $info);
            $cp_id = $product->id;

            foreach ($data['skus'] as $sku) {
                $where = ['spu_id' => $sku['spu_id'], 'sku_id' => $sku['sku_id'], 'cp_id' => $cp_id];
                $sku['cp_id'] = $cp_id;
                $sku['status'] = ChannelProductSku::STAUTS_ON;
                ChannelProductSku::updateOrCreate($where, $sku);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
            Log::info($e->__toString());
            return;
        }
    }

    // 更新渠道商品信息
    static function updateProduct($product_sn, $channel_code)
    {
        $api = new ChannelLogic($channel_code);
        try {

            $product = ChannelProduct::where(['product_sn' => $product_sn, 'channel_code' => $channel_code])->first();
            if ($product) {
                ChannelProductSku::where(['cp_id' => $product->id])->update([
                    'properties' => []
                ]);
                // ChannelProductSku::where(['cp_id' => $product->id])->update(['properties' => []]);
            }

            $data = $api->getProductInfo($product_sn);
            $data =  $api->productDetailFormat($data);
            //保存数据
            self::saveChannelProduct($data, $channel_code);
        } catch (\Exception $e) {
            $msg = sprintf('商品信息获取失败。渠道：%s，货号：%s ，原因：%s', $channel_code, $product_sn, $e->getMessage());
            Robot::sendText(Robot::FAIL_MSG, $msg);
        }
    }

    static function sizeStandard()
    {
        $api = new GoatApi();
        $data = $api->getSizeConversion();
        foreach ($data as $gender => $item) {
            $gen = SizeStandard::getGender($gender);
            foreach ($item as $brand => $vals) {
                foreach ($vals as $val) {
                    $size_eu = $val['unitOnly']['size'];
                    $size_us = $val['original']['size'];
                    $where = [
                        'gender' => $gen,
                        'brand' => $brand,
                        'size_eu' => $size_eu
                    ];
                    $update = [
                        'size_us' => $size_us
                    ];
                    SizeStandard::firstOrCreate($where, $update);
                }
            }
        }
    }

    //同步所有商品最低价
    public function syncLowestPriceAll()
    {
        set_time_limit(0);
        $lock = RedisKey::PRODUCT_LOWEST_PRICE_LOCK;
        if (!Redis::setnx($lock, 1)) {
            Robot::sendNotice('本次刷新最低价不执行');
            return;
        }


        $list = ChannelProductSku::where(['status' => 1])->whereHas('channelProduct', function ($query) {
            $query->where(['channel_code' => 'DW']);
        })->select(['sku_id', 'spu_id'])->distinct()->get()->toArray();
        //获取DW商品sku列表
        $time = time();
        $api = new ChannelLogic('DW');
        foreach ($list as $item) {
            try {
                $api->syncLowestPrice($item);
            } catch (Exception $e) {
                Robot::sendException($e->__toString());
            }
        }
        Robot::sendNotice(sprintf('DW同步最低价完成，sku数量：%d，耗时：%d秒', count($list), time() - $time));


        //获取GOAT商品spu列表
        $list = ChannelProductSku::where(['status' => 1])->whereHas('channelProduct', function ($query) {
            $query->where(['channel_code' => 'GOAT']);
        })->select(['spu_id'])->distinct()->get()->toArray();
        $time = time();
        $api = new ChannelLogic('GOAT');
        foreach ($list as $item) {
            try {
                $api->syncLowestPrice($item);
                sleep(1);
            } catch (Exception $e) {
                Robot::sendException($e->__toString());
            }
        }
        Robot::sendNotice(sprintf('GOAT同步最低价完成，spu数量：%d，耗时：%d秒', count($list), time() - $time));

        Redis::delete($lock);
    }

    static function lowestPrice($channel_product_sku, $use_lock = false)
    {
        $lowest_price = 0;
        $lowest_price_jpy = 0;
        if (!$channel_product_sku) goto RES;
        $channel_code = $channel_product_sku->channelProduct->channel_code;
        $api = new ChannelLogic($channel_code);
        $api->syncLowestPrice([
            'sku_id' => $channel_product_sku->sku_id,
            'spu_id' => $channel_product_sku->spu_id,
            'use_lock' => $use_lock,
        ]);
        $sku = ChannelProductSku::where(['id' => $channel_product_sku->id])->first();
        $lowest_price = $sku->lowest_price;
        $lowest_price_jpy = $sku->lowest_price_jpy;
        $lowest_price_at = date('Y-m-d H:i:s');
        RES:
        return compact('lowest_price', 'lowest_price_jpy', 'lowest_price_at');
    }

    // 指定货号指定属性所有渠道的sku信息
    static function getSku($product_sn, $property, $stock_bid = false)
    {
        $res = [];
        $products = ChannelProduct::where(['product_sn' => $product_sn, 'status' => ChannelProduct::STATUS_ACTIVE])->with('skus')->get();
        foreach ($products as $product) {
            if (!$product['skus']) continue;
            $api = new ChannelLogic($product['channel_code']);
            foreach ($product['skus'] as $sku) {
                if ($stock_bid) {
                    $match = $api->stockMatchSku($sku, $property, $product);
                } else {
                    $match = $api->matchSku($sku, $property, $product);
                }
                if ($match) {
                    $res[$product['channel_code']] = [
                        'channel_code' => $product['channel_code'],
                        'channel_product_sku_id' => $sku->id,
                        'channel_product_id' => $product['id'],
                    ];
                }
            }
        }
        return $res;
    }
}
