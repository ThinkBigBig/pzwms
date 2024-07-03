<?php

namespace App\Logics;

use App\Models\Product;
use App\Models\ProductSkuStock;
use Exception;
use Psy\Util\Json;
use App\Models\SkuStock;
use App\Models\Stock;

class ShenDu extends BaseLogic
{
    function productSync($data): bool
    {
        $data = json_decode($data, true);
        foreach ($data['items'] as $v) {
            try {
                $goods = [
                    'product_sn' => $v['goodsCode'], //货号
                    'name' => $v['itemName'], //商品名称
                    'description' => !empty($v['shortName']) ? $v['shortName'] : '', //商品简称
                    'englishName' => !empty($v['englishName']) ? $v['englishName'] : '', //英文商品名称
                    'brand_name' => !empty($v['brandName']) ? $v['brandName'] : '', //英文商品名称
                    'seasonName' => !empty($v['seasonName']) ? $v['seasonName'] : '', //英文商品名称
                    'product_category_name' => !empty($v['categoryName']) ? $v['categoryName'] : '', //英文商品名称
                ];
                $Product = (new Product())->where('product_sn', '=', $v['goodsCode'])->first();
                if (!empty($Product) && $Product != NULL) {
                    (new Product)->where('product_sn', '=', $v['goodsCode'])->update($goods);
                    $Product_id =  $Product->id;
                } else {
                    $Product_id =  (new Product)->insertGetId($goods);
                }
                $sku = [
                    'product_id' =>  $Product_id,
                    'itemCode' => $v['itemCode'], //
                    'bar_code'  =>  $v['barCode'], //条形码(可多个;用分号;隔开)
                    'skuProperty' => !empty($v['skuProperty']) ? $v['skuProperty'] : '', //商品属性(如红色;XXL)
                    'stockUnit' => !empty($v['stockUnit']) ? $v['stockUnit'] : '', //商品计量单位
                    'length' => !empty($v['length']) ? $v['length'] : '', //长(单位：厘米)
                    'width' => !empty($v['width']) ? $v['width'] : '', //宽(单位：厘米)
                    'height' => !empty($v['height']) ? $v['height'] : '', //高(单位：厘米)
                    'volume' => !empty($v['volume']) ? $v['volume'] : '', //长(单位：升)
                    'grossWeight' => !empty($v['grossWeight']) ? $v['grossWeight'] : '', //毛重(单位：千克)
                    'netWeight' => !empty($v['netWeight']) ? $v['netWeight'] : '', //净重(单位：千克)

                    'color' => !empty($v['color']) ? $v['color'] : '', //颜色
                    'size' => !empty($v['size']) ? $v['size'] : '', //尺寸
                    // 'title' => !empty($v['title']) ? $v['title']: '',//title
                    'tagPrice' => !empty($v['tagPrice']) ? $v['tagPrice'] : 0, //吊牌价
                    'retailPrice' => !empty($v['retailPrice']) ? $v['retailPrice'] : 0, //零售价
                    'costPrice' => !empty($v['costPrice']) ? $v['costPrice'] : 0, //成本价
                    'purchasePrice' => !empty($v['purchasePrice']) ? $v['purchasePrice'] : 0, //采购价
                ];
                $SkuStock = (new SkuStock)->where('itemCode', '=', $v['itemCode'])->first();
                if (!empty($SkuStock) && $SkuStock != NULL) {
                    (new SkuStock)->where('itemCode', '=', $v['itemCode'])->update($sku);
                    // $Product_id =  $Product->id;
                } else {
                    $sku['sku_code'] =  time();
                    $Product_id =  (new SkuStock)->insertGetId($sku);
                }
            } catch (\Exception $e) {
                $error_arr[] = [
                    // 'lineNo'    => $v['lineNo'],
                    'itemCode'  => $v['itemCode'],
                    'message'   => $e->getMessage()
                ];
                Robot::sendException('商品信息同步异常' . $e->__toString());
            }
        }
        return true;
    }

    function productStockSync($data): bool
    {
        $arr2 = json_decode($data, true);
        if (!empty($arr2)) {

            $arr = $arr2['remark'];
            $arr = json_decode($arr, true);
            $error_arr = [];
            foreach ($arr as $v) {
                try {
                    $stock_v = [];
                    $stock_v['ownerCode']  = $arr2['ownerCode'];
                    if (isset($v["itemCode"])) $stock_v['itemCode'] = $v["itemCode"];
                    if (isset($v["goodsCode"])) $stock_v['goodsCode'] = $v["goodsCode"];
                    if (isset($v["itemName"])) $stock_v['itemName'] = $v["itemName"];
                    if (isset($v["barCode"])) $stock_v['barCode'] = $v["barCode"];

                    if (isset($v["skuProperty"])) $stock_v['skuProperty'] = $v["skuProperty"];
                    if (isset($v["weightCostPrice"])) $stock_v['weightCostPrice'] = $v["weightCostPrice"];
                    if (isset($v["uniqueCode"])) $stock_v['uniqueCode'] = $v["uniqueCode"];
                    if (isset($v["storeGoodsNum"])) $stock_v['storeGoodsNum'] = $v["storeGoodsNum"];
                    if (isset($v["lockGoodsNum"])) $stock_v['lockGoodsNum'] = $v["lockGoodsNum"];
                    if (isset($v["waiteGoodsNum"])) $stock_v['waiteGoodsNum'] = $v["waiteGoodsNum"];
                    if (isset($v["freezeGoodsNum"])) $stock_v['freezeGoodsNum'] = $v["freezeGoodsNum"];

                    if (isset($v["storeHouseCode"])) $stock_v['storeHouseCode'] = $v["storeHouseCode"];
                    if (isset($v["storeHouseName"])) $stock_v['storeHouseName'] = $v["storeHouseName"];
                    if (isset($v["supplierCode"])) $stock_v['supplierCode'] = $v["supplierCode"];
                    if (isset($v["supplierName"])) $stock_v['supplierName'] = $v["supplierName"];
                    if (isset($v["inventoryType"])) $stock_v['inventoryType'] = $v["inventoryType"];
                    if (isset($v["qualityType"])) $stock_v['qualityType'] = $v["qualityType"];
                    if (isset($v["qualityLevel"])) $stock_v['qualityLevel'] = $v["qualityLevel"];
                    if (isset($v["batchCode"])) $stock_v['batchCode'] = $v["batchCode"];
                    if (isset($v["requestId"])) $stock_v['requestId'] = $v["requestId"];

                    ProductSkuStock::updateOrCreate(['requestId' => $v['requestId']], $stock_v);
                } catch (\Exception $e) {
                    $error_arr[] = [
                        'lineNo'    => $v['lineNo'],
                        'itemCode' => $v['itemCode'],
                        'message'  => $e->getMessage()
                    ];
                    Robot::sendException('可用库存同步异常' . $e->__toString());
                }
            }
        }
        return true;
    }

    function productStockDetailSync($data): bool
    {
        $arr2 = json_decode($data, true);
        $arr = $arr2['remark'];
        $arr = json_decode($arr, true);
        if (!empty($arr)) {
            $stock_arr = [];
            $error_arr = [];
            foreach ($arr as $v) {
                try {
                    $stock_v = [];
                    $stock_v['ownerCode']  = $arr2['ownerCode'];
                    if (isset($v["changeTime"])) {
                        $stock_v['changeTime'] = $v["changeTime"] / 1000;
                        $stock_v['changeTime'] = date('Y-m-d H:i:s', $stock_v['changeTime']);
                    }
                    if (isset($v["srcOrderType"])) $stock_v['srcOrderType'] = $v["srcOrderType"];
                    if (isset($v["srcOrderCode"])) $stock_v['srcOrderCode'] = $v["srcOrderCode"];
                    if (isset($v["threeOrderCode"])) $stock_v['threeOrderCode'] = $v["threeOrderCode"];
                    if (isset($v["itemCode"])) $stock_v['itemCode'] = $v["itemCode"];
                    if (isset($v["goodsCode"])) $stock_v['goodsCode'] = $v["goodsCode"];
                    if (isset($v["goodsName"])) $stock_v['goodsName'] = $v["goodsName"];

                    if (isset($v["skuProperty"])) $stock_v['skuProperty'] = $v["skuProperty"];
                    if (isset($v["costPrice"])) $stock_v['costPrice'] = $v["costPrice"];
                    if (isset($v["weightCostPrice"])) $stock_v['weightCostPrice'] = $v["weightCostPrice"];
                    if (isset($v["orderType"])) $stock_v['orderType'] = $v["orderType"];
                    if (isset($v["orderCode"])) $stock_v['orderCode'] = $v["orderCode"];
                    if (isset($v["changeNum"])) $stock_v['changeNum'] = $v["changeNum"];
                    if (isset($v["storeHouseCode"])) $stock_v['storeHouseCode'] = $v["storeHouseCode"];

                    if (isset($v["storeHouseName"])) $stock_v['storeHouseName'] = $v["storeHouseName"];
                    if (isset($v["supplierCode"])) $stock_v['supplierCode'] = $v["supplierCode"];
                    if (isset($v["supplierName"])) $stock_v['supplierName'] = $v["supplierName"];
                    if (isset($v["inventoryType"])) $stock_v['inventoryType'] = $v["inventoryType"];
                    if (isset($v["qualityType"])) $stock_v['qualityType'] = $v["qualityType"];
                    if (isset($v["qualityLevel"])) $stock_v['qualityLevel'] = $v["qualityLevel"];

                    if (isset($v["batchCode"])) $stock_v['batchCode'] = $v["batchCode"];
                    if (isset($v["requestId"])) $stock_v['requestId'] = $v["requestId"];
                    // $stock_arr[] =$stock_v;
                    $Stock = (new Stock)->where('requestId', '=', $v['requestId'])->first();
                    if (isset($Stock) && $Stock != NULL) {
                        (new Stock)
                            ->where('requestId', '=', $v['requestId'])
                            ->update($stock_v);
                    } else {
                        (new Stock)->insert($stock_v);
                    }
                } catch (\Exception $e) {
                    $error_arr[] = [
                        'lineNo'    => $v['lineNo'],
                        'itemCode' => $v['itemCode'],
                        'message'  => $e->getMessage()
                    ];
                    Robot::sendException('出入库明细同步异常' . $e->__toString());
                }
            }
        }
        return true;
    }
}
