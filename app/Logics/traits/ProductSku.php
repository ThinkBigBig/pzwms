<?php

namespace App\Logics\traits;

use App\Jobs\stockProduct;
use App\Logics\ChannelPurchaseLogic;
use App\Models\ChannelProduct;
use App\Models\ChannelProductSku;
use App\Models\Product;
use App\Models\SizeStandard;

trait ProductSku
{
    /**
     * 更新sku最低价
     *
     * @param array $params
     */
    public function updateLowestPrice($params)
    {
        $price = $params['price'];
        if (!isset($price['lowest_price'])) return;
        if (!isset($price['lowest_price_jpy'])) return;

        $product = ChannelProduct::where(['channel_code' => $params['channel_code'], 'spu_id' => $params['spu_id'], 'status' => ChannelProduct::STATUS_ACTIVE])->first();
        if(!$product) return;
        $cp_id = $product->id;

        $sku = ChannelProductSku::where(['spu_id' => $params['spu_id'], 'sku_id' => $params['sku_id'], 'cp_id' => $cp_id, 'status' => 1])->first();
        if (!$sku) return;

        $change = $sku->lowest_price != $price['lowest_price'];
        $sku->update([
            'lowest_price' => $price['lowest_price'],
            'lowest_price_jpy' => $price['lowest_price_jpy'],
            'lowest_price_at' => date('Y-m-d H:i:s'),
        ]);

        // GOAT最低价变化，erp刷新出价
        if ($change && $params['channel_code'] == 'GOAT') {
            stockProduct::dispatch(['action' => stockProduct::GOAT_REFRESH, 'spu_id' => $params['spu_id'], 'sku_id' => $params['sku_id'], 'cp_id' => $cp_id])->onQueue('product');
        }

        // 最低价变化，出价到APP
        ChannelPurchaseLogic::pushQueue([
            'channel_code' => $params['channel_code'],
            'spu_id' => $params['spu_id'],
            'sku_id' => $params['sku_id'],
            'cp_id' => $cp_id,
            'lowest_price' => $price['lowest_price'],
            'lowest_price_at' => date('Y-m-d H:i:s'),
            'product_sn' => $product->product_sn,
        ]);
    }

    /**
     * 根据欧码获取对应的法码
     *
     * @param array $eu_arr
     * @param string $product_sn
     */
    public function sizeFr($eu_arr, $product_sn)
    {
        $res = SizeStandard::where(['product_sn' => $product_sn])->whereIn('size_eu', $eu_arr)->pluck('size_fr')->toArray();
        return $res ?: [];
    }

    public function sizeBrand($product_sn)
    {
        $product = Product::where(['product_sn' => $product_sn])->first();
        if (!$product) return '';
        $brand = $product->productBrand;
        if ($brand) {
            return $brand->size_brand;
        }

        return '';
    }
}
