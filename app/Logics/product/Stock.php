<?php

namespace App\Logics\product;

use App\Logics\BaseLogic;
use App\Models\ChannelOrder;
use App\Models\StockProduct;

class Stock extends BaseLogic
{
    static function orderStock($params)
    {
        $info_updated_at = $params['info_updated_at'] ?? '';
        $stock_product_id = $params['stock_product_id'];

        // 统计订单数
        // 计算剩余库存
        // 如果超卖，取消出价
        $product_ids = StockProduct::where(['product_sn' => $params['product_sn'], 'properties' => $params['properties']])->pluck('id');

        $number = ChannelOrder::where(['stock_source' => ChannelOrder::SOURCE_STOCK])
            ->whereIn('status', ChannelOrder::$active_status)
            ->where('created_at', '>', $info_updated_at)
            ->whereHas('stockBddingItem', function ($query) use ($product_ids) {
                $query->whereIn('stock_product_id', $product_ids);
            })->count();
        return $number;
    }
}
