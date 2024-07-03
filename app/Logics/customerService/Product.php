<?php

namespace App\Logics\customerService;

use App\Logics\BaseLogic;
use App\Models\Product as ModelsProduct;
use App\Models\ProductSkuStock;
use App\Models\Stock;

class Product extends BaseLogic
{
    // 客户和仓库映射
    static $house_name_map = [
        'ihad' => 'XY',
    ];

    // 商品信息
    function products($params)
    {
        $size = $params['size'] ?? 10;
        $cur_page = $params['cur_page'] ?? 1;
        $house_name = self::$house_name_map[$params['source']] ?? '';
        if (!$house_name) {
            $this->setErrorMsg('仓库信息不存在');
            return [];
        }

        $list = ModelsProduct::select(['id', 'product_sn', 'name', 'description', 'englishName', 'brand_name', 'seasonName', 'product_category_name'])
            ->with(['skus:product_id,itemCode,bar_code,sku_code,skuProperty,stockUnit,length,width,height,volume,grossWeight,netWeight,color,size,tagPrice,retailPrice,costPrice,purchasePrice'])
            ->simplePaginate($size, '*', 'page', $cur_page);
        return $list;
    }

    // 商品库存
    function stock($params)
    {
        $size = $params['size'] ?? 10;
        $cur_page = $params['cur_page'] ?? 1;
        $house_name = self::$house_name_map[$params['source']] ?? '';
        $init = $params['init'] ?? 0;
        if (!$house_name) {
            $this->setErrorMsg('仓库信息不存在');
            return [];
        }
        $model = ProductSkuStock::where('storeHouseName', $house_name);
        if (!$init) {
            $model->where('updated_at', '>', date('Y-m-d 00:00:00', strtotime('-3 day')));
        }

        return $model->select(['ownerCode', "itemCode", "goodsCode", "itemName", "barCode", "skuProperty", "weightCostPrice", "uniqueCode", "storeGoodsNum", "lockGoodsNum", "waiteGoodsNum", "freezeGoodsNum", "storeHouseCode", "storeHouseName", "supplierCode", "supplierName", "inventoryType", "qualityType", "qualityLevel", "batchCode", "requestId"])
            ->simplePaginate($size, '*', 'page', $cur_page);
    }

    // 商品出入库明细
    function detail($params)
    {
        $size = $params['size'] ?? 10;
        $cur_page = $params['cur_page'] ?? 1;
        $house_name = self::$house_name_map[$params['source']] ?? '';
        $init = $params['init'] ?? 0;
        if (!$house_name) {
            $this->setErrorMsg('仓库信息不存在');
            return [];
        }
        $model = Stock::where('storeHouseName', $house_name);
        if (!$init) {
            $model->where('changeTime', '>', date('Y-m-d 00:00:00', strtotime('-1 day')));
        }
        return $model->simplePaginate($size, '*', 'page', $cur_page);
    }
}
