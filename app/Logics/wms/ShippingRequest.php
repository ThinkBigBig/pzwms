<?php

namespace App\Logics\wms;

use App\Logics\BaseLogic;
use App\Models\Admin\V2\preAllocationDetail;
use App\Models\Admin\V2\Product;

/**
 * 出库请求
 */
class ShippingRequest extends BaseLogic
{
    // 预配信息
    static function preDetails($params)
    {
        $model = preAllocationDetail::where('request_code', $params['request_code']);
        // 商品名
        if ($params['name'] ?? '') {
            $product_ids = Product::where('name', 'like', $params['name'])->pluck('id');
            if (!$product_ids) return [];
            $params['product_ids'] = $product_ids;
        }

        if (($params['sku'] ?? '') || ($params['product_ids'] ?? [])) {
            $model->whereHas('property', function ($query) use ($params) {
                // sku编码
                if ($params['sku'] ?? '') {
                    $query->where('sku', $params['sku']);
                }
                if ($params['product_ids'] ?? []) {
                    $query->whereIn('product_id', $params['product_ids']);
                }
            });
        }

        $data = $model->with(['property', 'supplier', 'warehouse'])->get()->toArray();
        foreach ($data as &$item) {
            $item['spec_bar'] = $item['property'];
            self::sepBarFormat($item);
            unset($item['property']);
            unset($item['product']);
        }
        return $data;
    }
}
