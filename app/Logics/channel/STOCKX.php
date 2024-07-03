<?php

namespace App\Logics\channel;

use App\Handlers\OSSUtil;
use App\Handlers\StockxApi;
use App\Logics\BaseLogic;
use App\Logics\CarrymeCallbackLogic;
use App\Logics\ChannelLogic;
use App\Logics\ExchangeLogic;
use App\Logics\Robot;
use App\Logics\traits\ProductSku;
use App\Models\CarryMeBiddingItem;
use App\Models\ChannelBidding;
use App\Models\ChannelBiddingItem;
use App\Models\ChannelBiddingLog;
use App\Models\ChannelOrder;
use App\Models\ChannelProduct;
use App\Models\ChannelRefundLog;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\SizeStandard;
use App\Models\StockxBidding;
use App\Models\StockxOrder;
use App\Models\StockxProduct;
use App\Models\StockxProductVariant;
use Exception;
use finfo;
use Illuminate\Support\Facades\Log;

class STOCKX implements ThirdInterface
{
    use ProductSku;
    const BUSINESS_TYPE_KJ = 1; //业务类型-跨境
    static $code = 'STOCKX';

    public $price_unit = BaseLogic::PRICE_UNIT_YUNA;
    public $price_currency = BaseLogic::CURRENCY_JP;
    public $from_currency = BaseLogic::CURRENCY_JP;

    static $threshold_limit = 8600; //美分


    //出价金额转换 人民币转美元
    public function RMB2Price($price)
    {
        return $price;
    }

    //订单金额 - rmb 美元转人民币
    public function price2RMB($price)
    {
        return $price;
    }

    public function exchange()
    {
        $exchange = new ExchangeLogic($this->price_currency);
        $exchange->getExchange();
        return $exchange;
    }

    //美分转日元
    public function usd2jpy($price)
    {
        $exchange = new ExchangeLogic(BaseLogic::CURRENCY_US);
        $exchange->getExchange();
        //1美元 = 100美分
        $price =  bcmul(bcdiv($price, 100, 2), $exchange->reverse_exchange, 2);
        return ceil($price);
    }

    // 日元转美分
    public function jpy2usd($price)
    {
        $exchange = new ExchangeLogic($this->price_currency);
        $exchange->getExchange();
        // 日元转美分
        return ceil(bcmul(bcmul($price, $exchange->positive_exchange, 5), 100, 2));
    }

    // stockx的出价也是日元，不用进行汇率转换
    public function bidPrice2Jpy($price)
    {
        return $price;
    }

    //订单金额 - rmb 美元转人民币
    public function price2Jpy($price)
    {
        return $this->usd2jpy($price);
    }

    private function _product($product_sn)
    {
        $product = StockxProduct::where(['styleId' => $product_sn])->first();
        if ($product) return $product;

        $api = new StockxApi();
        $res = $api->searchCatalog(['query' => $product_sn]);
        $list = $res['products'] ?? [];
        foreach ($list as $item) {
            $where = [
                'productId' => $item['productId'],
            ];
            $update = [
                'brand' => $item['brand'] ?? '',
                'productType' => $item['productType'] ?? '',
                'styleId' => $item['styleId'] ?? '',
                'urlKey' => $item['urlKey'] ?? '',
                'title' => $item['title'] ?? '',
                'productAttributes' => $item['productAttributes'] ?? '',
            ];
            StockxProduct::updateOrCreate($where, $update);
        }
        return StockxProduct::where(['styleId' => $product_sn])->first();
    }

    private function _skus($product)
    {
        $skus = StockxProductVariant::where(['productId' => $product->productId])->get();
        if ($skus->count() > 0) return $skus;

        $api = new StockxApi();
        $res = $api->productVariants($product->productId);
        foreach ($res as $item) {
            $where = [
                'productId' => $item['productId'],
                'variantId' => $item['variantId'],
            ];
            $update = [
                'variantName' => $item['variantName'],
                'variantValue' => $item['variantValue'],
                'stockx_product_id' => $product->id,
            ];
            StockxProductVariant::updateOrCreate($where, $update);
        }
        return StockxProductVariant::where(['productId' => $product->productId])->get();
    }

    public function getProductInfo($product_sn)
    {
        $product = $this->_product($product_sn);
        if (!$product) return [];
        $skus = $this->_skus($product);
        return [
            'product' => $product,
            'skus' => $skus,
        ];
    }


    public function productDetailFormat($data): array
    {
        if (!$data) return [];
        $product = $data['product'];

        $status = ChannelProduct::STATUS_ACTIVE;
        $info = [
            'good_name' => $product->title, //商品名
            'good_name_cn' => $product->title, //商品名称-中文
            'spu_logo_origin' => '', //商品图片
            'product_sn' => $product->styleId, //货号
            'brand_id' => 0, //品牌id
            'brand_name' => $product->brand, //品牌名称
            'spu_id' => $product->id, //所在渠道spu_id
            'status' => $status, //1上架 0下架
            'category_id' => 0, //分类id
            'category_name' => $product->productType, //分类名
        ];

        $skus = [];
        foreach ($data['skus'] as $item) {
            $skus[] = [
                'properties' => $this->sizeFormat(strtolower($product->brand), $info['product_sn'], $product->productAttributes['gender'] ?? '', $item->variantValue, $product),
                'barcode' => '',
                'sku_id' => $item->id,
                'spu_id' => $product->id,
                'status' => $status,
                'tags'   => '',
            ];
        }
        return compact('info', 'skus');
    }

    // 格式化尺码属性
    private function sizeFormat($brand, $product_sn, $gender, $value, $product)
    {
        $arr = explode(' ', $value);
        $res = ['original' => $value,];

        // sneakers-运动鞋 collectibles-收藏品 handbags-包 streetwear-衣服 watches-手表
        if ($product->productType == 'streetwear') {
            $res['size_eu'] = strtoupper($value);
            return $res;
        }

        $maps = [
            'W' => 'women',
            'M' => 'men',
            'Y' => 'youth',
            'K' => 'youth',
            'C' => 'infant',
        ];
        // 如果商品信息中有品牌信息，按照商品信息的获取
        $size_brand = ProductBrand::where(['product_sn' => $product_sn])->value('size_brand');
        if ($size_brand) {
            $brand = $size_brand;
        } else {
            $brand = str_replace(' ', '_', $brand);
        }

        foreach ($arr as $item) {
            if (is_numeric($item)) {
                $res['size_us'] = $item;
                $size = SizeStandard::sizeInfo($item, $gender, $brand, $product_sn);
                if ($size) {
                    $res['size_eu'] = $size->size_eu;
                    $res['size_fr'] = $size->size_fr;
                }
                continue;
            }

            if ($item == '2E') {
                $res['version'] = 'D宽';
                continue;
            }
            if ($item == '/') continue;

            $us = substr($item, 0, -1);
            $tag = substr($item, -1, 1);

            $gender = $maps[$tag];
            if ($tag == 'W') {
                $size = SizeStandard::sizeInfo($us, $gender, $brand, $product_sn);
                $res['women']['size_us'] = $us;
                if ($size) {
                    $res['women']['size_eu'] = $size->size_eu;
                    $res['women']['size_fr'] = $size->size_fr;
                }
                continue;
            }
            if ($tag == 'M') {
                $size = SizeStandard::sizeInfo($us, $gender, $brand, $product_sn);
                $res['men']['size_us'] = $us;
                if ($size) {
                    $res['men']['size_eu'] = $size->size_eu;
                    $res['men']['size_fr'] = $size->size_fr;
                }
                continue;
            }
            if ($tag == 'Y' || $tag == 'K') {
                $size = SizeStandard::sizeInfo($us, $gender, $brand, $product_sn);
                $res['size_us'] = $us;
                if ($size) {
                    $res['size_eu'] = $size->size_eu;
                    $res['size_fr'] = $size->size_fr;
                }
                continue;
            }
            if ($tag == 'C') {
                $size = SizeStandard::sizeInfo($us, $gender, $brand, $product_sn);
                $res['size_us'] = $us;
                if ($size) {
                    $res['size_eu'] = $size->size_eu;
                    $res['size_fr'] = $size->size_fr;
                }
                continue;
            }
        }

        return $res;
    }

    //判断sku的属性是否匹配
    public function matchSku($sku, $properties, $product = null)
    {
        $arr = array_column($properties, 'valuecn');
        $properties = $sku->properties;
        // 有版本要求，不能只匹配尺码
        if ($properties['version'] ?? '') {
            return false;
        }

        if ($properties['size_eu'] ?? 0) {
            $arr2 = [$properties['size_eu']];
        } else {
            $women = $sku->properties['women'] ?? [];
            $men = $sku->properties['men'] ?? [];
            $size = [];
            if ($women && (!$men)) $size = $women;
            if ((!$women) && $men) $size = $men;
            if ($women && $men) {
                $tag = Product::where(['product_sn' => $product->product_sn])->value('product_category_name');
                if ($tag == '男款') $size = $men;
                if ($tag == '女款') $size = $women;
            }
            $arr2 = [$size ? $size['size_eu'] ?? 0 : 0];
        }

        if (array_diff($arr, $arr2) || array_diff($arr2, $arr)) return false;
        return true;
    }

    /**
     * 库存出价判断sku的属性是否匹配
     * - 38.5 匹配10
     * - 38.5-D宽 匹配10
     */
    public function stockMatchSku($sku, $properties, $product)
    {
        $attr = $properties[0]['valuecn'];
        $attrs = explode('-', $attr);
        //properties 的可能存在以下数据格式：
        // 没有性别 (老){"size_us": "4","size_eu": "36"}
        // 没有性别（新） {"original": "3","size_us": "3","size_eu": "35.5","size_fr": "35.5"}
        // 有版型要求 {"original": "3 2E","size_us": "3","size_eu": "35.5","size_fr": "35.5","version": "D宽"}
        // 幼童、婴童类型 {"original": "10.5C","size_us": "10.5","size_eu": "27.5","size_fr": "27.5"}
        // 女款或男款 {"original": "5W","women": {"size_us": "5","size_eu": "35.5","size_fr": "35.5"}}
        // 既有女款又有男款 {"original": "5W / 3.5M","women": {"size_us": "5","size_eu": "35.5","size_fr": "35.5"},"men": {"size_us": "3.5","size_eu": "35.5","size_fr": "35.5"}}

        $arr2 = [];
        $arr3 = [];
        $common = [];
        if ($sku->properties['size_eu'] ?? '') $arr2[] = $sku->properties['size_eu'];
        if ($sku->properties['size_fr'] ?? '') $arr3[] = $sku->properties['size_fr'];
        if ($sku->properties['version'] ?? '') $common[] = $sku->properties['version'];
        $women = $sku->properties['women'] ?? [];
        $men = $sku->properties['men'] ?? [];
        if ($women || $men) {
            $size = [];
            // 同时存在男款和女款，就查库存商品是男款还是女款
            if ($women && $men) {
                $tag = Product::where(['product_sn' => $product->product_sn])->value('product_category_name');
                if ($tag == '男款') $size = $men;
                if ($tag == '女款') $size = $women;
                // 如果不是其中的一种，直接匹配失败
                if (!$size) return false;
            }

            if ($women) $size = $women;
            if ($men) $size = $men;

            $arr2[] = $size['size_eu'] ?? '';
            $arr3[] = $size['size_fr'] ?? '';
        }

        $arr_eu = array_merge($common, $arr2);
        $arr_fr = array_merge($common, $arr3);

        $notmatch_eu = array_diff($attrs, $arr_eu) || array_diff($arr_eu, $attrs);
        $notmatch_fr = array_diff($attrs, $arr_fr) || array_diff($arr_fr, $attrs);

        if ($notmatch_eu && $notmatch_fr) return false;
        return true;
    }


    const BID_STATUS_INACTIVE = 'INACTIVE';
    const BID_STATUS_ACTIVE = 'ACTIVE';
    const BID_STATUS_DELETED = 'DELETED';
    const BID_STATUS_CANCELED = 'CANCELED';
    const BID_STATUS_MATCHED = 'MATCHED';
    const BID_STATUS_COMPLETED = 'COMPLETED';

    // 出价单有效状态
    static $bid_active_arr = [
        self::BID_STATUS_ACTIVE, self::BID_STATUS_COMPLETED, self::BID_STATUS_MATCHED
    ];

    // 出价单失效状态
    static $bid_invalid_arr = [
        self::BID_STATUS_DELETED, self::BID_STATUS_CANCELED, self::BID_STATUS_INACTIVE
    ];

    private function _cancel($bidding_no)
    {
        // 同步下出价单状态
        $this->syncListInfo($bidding_no);
        // "INACTIVE", "ACTIVE", "DELETED", "CANCELED", "MATCHED", "COMPLETED"
        $bidding = StockxBidding::where(['bidding_no' => $bidding_no])->first();
        if (!$bidding) return true;
        // 出价已取消或删除
        if (in_array($bidding->status, [self::BID_STATUS_CANCELED, self::BID_STATUS_DELETED])) return true;
        // 删除请求已发出
        if ($bidding->deleteOperationId) return true;
        // 已经匹配/已完成，无法删除
        if (in_array($bidding->status, [self::BID_STATUS_MATCHED, self::BID_STATUS_COMPLETED])) return false;

        $api = new StockxApi();
        $res = $api->deleteList($bidding->listingId);
        if (($res['listingId'] ?? '') == $bidding->listingId) {
            $bidding->update([
                'deleteOperationId' => $res['operationId'],
                'deleteOperationStatus' => $res['operationStatus'],
            ]);
            return true;
        }
        Robot::sendFail(sprintf('stockx 删除出价失败 params：%s res: %s', json_encode($bidding), json_encode($res)));
        return false;
    }


    /**
     * 新增商品
     * @throws Exception
     */
    public function bid($product, $price, $qty, $type = self::BUSINESS_TYPE_KJ, $params = [])
    {
        $bidding = StockxBidding::where(['bidding_no' => $product['ext_tag']])->first();
        if ($bidding) return true;

        // 根据sku 和spu找获取productId 、
        $va = StockxProductVariant::where(['id' => $product['sku_id']])->first();
        if (!$va) {
            throw new Exception('stockx 未找到对应的Variant信息');
        }



        $api = new StockxApi();
        $res = $api->createList([
            'variantId' => $va->variantId,
            'price' => $price,
        ]);
        if ($res['listingId'] ?? '') {
            StockxBidding::create([
                'bidding_no' => $product['ext_tag'],
                'productId' => $va->productId,
                'variantId' => $va->variantId,
                'listingId' => $res['listingId'],
                'createOperationId' => $res['operationId'],
                'createOperationStatus' => $res['operationStatus'],
            ]);
            return true;
        }

        throw new Exception('stockx ' . json_encode($res));
        return false;
    }

    /**
     * 新增或更新出价
     * 查是否有可更新出价，没有再新增出价
     *
     * @param ChannelBidding|null $last_bid
     * @param int $price
     * @param int $qty
     * @param array $product
     */
    public function bidOrUpdate($last_bid, $price, $qty, $product)
    {
        if (!$price) {
            throw new Exception('出价金额不能为空');
        }

        $old_bidding_no = '';
        $bid_status = 0;
        $bidding_no = '';
        if ($last_bid) { //取消出价
            $this->_cancel($bidding_no);
        }

        //新增出价
        $bidding_no = 'S' . genRandomString() . time();
        $product['ext_tag'] = $bidding_no;
        $res = $this->bid($product, $price, $qty);
        return compact('bidding_no', 'old_bidding_no', 'bid_status');
    }

    /**
     * 取消出价
     * @throws Exception
     */
    public function bidCancel($bidding, $type = self::BUSINESS_TYPE_KJ): array
    {
        $this->_cancel($bidding->bidding_no);
        $this->syncBiddingInfo($bidding);

        return [
            'code' => 200,
            'data' => [
                'qty' => 1,
                'qty_cancel' => 0
            ]
        ];
    }

    // 批量删除出价
    function batchCancel($listings)
    {
        if (!$listings) return false;
        $api = new StockxApi();
        $res = $api->batchListDelete($listings);
        if (!$res) return false;

        // 循环同步出价数据
        foreach ($listings as $list) {
            $stock_bidding = StockxBidding::where('listingId', $list)->first();
            if (!$stock_bidding) continue;
            $bidding = $stock_bidding->channelBidding;
            $this->syncBiddingInfo($bidding);
        }
    }

    const OPERATION_STATUS_PENDING = 'PENDING';
    const OPERATION_STATUS_SUCCEEDED = 'SUCCEEDED';
    const OPERATION_STATUS_FAILED = 'FAILED';

    public function syncBiddingRes($list)
    {
        $bidding = StockxBidding::where(['listingId' => $list['listingId']])->first();
        if (!$bidding) {
            // 不是api出价，不处理
            return false;
        }

        $status = $list['status'];
        // $bidding->update(['status' => $status]);

        if (in_array($status, self::$bid_active_arr) || (in_array($status, self::$bid_invalid_arr) && $bidding->createOperationStatus == self::OPERATION_STATUS_PENDING)) {
            $spu = $bidding->stockxProduct;
            $sku = $bidding->stockxProductVariant;
            $spu_id = $spu ? $spu->id : 0;
            $sku_id = $sku ? $sku->id : 0;

            $amount = $list['amount'];
            if ($list['currencyCode'] == 'USD') {
                $amount = $this->usd2jpy($list['amount']);
            }
            if (!in_array($list['currencyCode'], ['JPY', 'USD'])) {
                Robot::sendException('stockx 出价数据异常！！！！，list %s', json_encode($list));
            }

            if ($bidding->status == '' || $bidding->createOperationStatus == self::OPERATION_STATUS_PENDING) {
                $bidding->update([
                    'variantValue' => $sku ? $sku->variantValue : '',
                    'status' => $list['status'],
                    'amount' => $list['amount'],
                    'currencyCode' => $list['currencyCode'],
                    'inventoryType' => $list['inventoryType'],
                    'createdAt' => $list['createdAt'],
                    'updatedAt' => $list['updatedAt'],
                    'askId' => $list['ask']['askId'] ?? '',
                    'createOperationStatus' => self::OPERATION_STATUS_SUCCEEDED,
                ]);
            }

            $add_success = true;
            $error = '';
            if (in_array($status, self::$bid_invalid_arr)) {
                $add_success = false;
                if (($list['lastOperation']['operationId']) == $bidding->createOperationId) {
                    $error = $list['lastOperation']['error'];
                }
            }

            return [
                'add_success' => $add_success,
                'bidding_no' => $bidding->bidding_no,
                'amount' => $amount,
                'currencyCode' => 'JPY',
                'variantValue' => $sku ? $sku->variantValue : '',
                'spu_id' => $spu_id,
                'sku_id' => $sku_id,
                'error' => $error,
            ];
        }

        $delete = $status == 'DELETED' && $status != $bidding->status;
        // 出价删除
        if (
            in_array($status, self::$bid_invalid_arr) &&
            ($bidding->deleteOperationStatus == self::OPERATION_STATUS_PENDING || $delete)
        ) {
            $bidding->update([
                'status' => $list['status'],
                'deleteOperationStatus' => self::OPERATION_STATUS_SUCCEEDED,
            ]);
            return [
                'delete_success' => true,
                'bidding_no' => $bidding->bidding_no,
            ];
        }

        return [];
    }

    // 格式化订单数据
    public function orderDetailFormat($data)
    {
        if (!$data) return [];
        $bidding_no = StockxBidding::where(['listingId' => $data['listingId']])->value('bidding_no');
        if (!$bidding_no) return [];
        $bidding = ChannelBidding::where(['bidding_no' => $bidding_no])->first();
        if (!$bidding) return [];

        $order_no = $data['orderNumber'];
        $paysuccess_time = $data['createdAt']; //购买时间
        $sku_id = $bidding->sku_id;
        $spu_id = $bidding->spu_id;
        $good_name = $data['product']['productName'];
        $properties = $data['variant']['variantValue'];
        $qty = 1; //购买数量

        $deliver = [
            'express_no' => $data['shipment']['trackingNumber'] ?? '',
            'express_file' => $data['shipment']['shippingDocumentUrl'] ?? '',
            'delivery_time' => $data['updatedAt'],
        ];

        $maps = [
            // 激活状态
            'CREATED' => ChannelOrder::STATUS_CREATED,
            'CCAUTHORIZATIONFAILED' => ChannelOrder::STATUS_DEFAULT, //CC验证失败
            'SHIPPED' => ChannelOrder::STATUS_DELIVER, //发货
            'RECEIVED' => ChannelOrder::STATUS_DELIVER, //收货
            'AUTHENTICATING' => ChannelOrder::STATUS_DELIVER, //鉴定中
            'AUTHENTICATED' => ChannelOrder::STATUS_DELIVER, //鉴定通过
            'PAYOUTPENDING' => ChannelOrder::STATUS_DELIVER, //等待支付
            'PAYOUTCOMPLETED' => ChannelOrder::STATUS_DELIVER, //支付完成
            'SYSTEMFULFILLED' => ChannelOrder::STATUS_DELIVER,
            'PAYOUTFAILED' => ChannelOrder::STATUS_DELIVER, //支付失败
            'SUSPENDED' => ChannelOrder::STATUS_DELIVER, //暂停

            // 历史状态
            'AUTHFAILED' => ChannelOrder::STATUS_CANCEL, //身份验证失败
            'DIDNOTSHIP' => ChannelOrder::STATUS_CANCEL, //未发货
            'CANCELED' => ChannelOrder::STATUS_CANCEL, //取消
            'COMPLETED' => ChannelOrder::STATUS_COMPLETE, //完成
            'RETURNED' => ChannelOrder::STATUS_CLOSE, //退回
        ];
        if (!($maps[$data['status']] ?? '')) {
            Robot::sendException('stockx订单状态异常 ' . json_encode($data));
            return [];
        }
        $order_status = $maps[$data['status']] ?? ChannelOrder::STATUS_DELIVER;
        $order_type = '';
        $modify_time = $data['updatedAt']; //订单修改时间
        $close_time = $data['updatedAt']; //订单关闭时间
        $res =  compact('order_no', 'paysuccess_time', 'sku_id', 'spu_id', 'good_name', 'properties', 'qty', 'bidding_no', 'deliver', 'order_status', 'order_type', 'modify_time', 'close_time');


        if (!in_array($data['currencyCode'], ['USD', 'JPY'])) {
            Robot::sendException('stockx订单数据异常，%s', json_encode($data));
        }
        $price_jpy = $data['amount'];
        if ($data['currencyCode'] == 'USD') {
            $price_jpy = $this->usd2jpy(bcmul($data['amount'], 100, 0));
        }

        $res['price'] = $price_jpy;
        $res['currency'] = $this->price_currency;
        $res['price_unit'] = $this->price_unit;
        $res['price_rmb'] = 0;
        $res['price_jpy'] = $price_jpy;
        $res['updated_at'] =  $data['updatedAt'];
        $res['sub_status'] = $data['status'];
        $res['close_remark'] = '';

        //订单完成时间
        $res['completion_time'] = '';
        if ($order_status == ChannelOrder::STATUS_COMPLETE) {
            $res['completion_time'] = $data['updatedAt'];
        }
        $res['sub_status_txt'] = '';
        //根据出价单，统计剩余数量、已售数量
        $res['bidding_detail'] = $this->getBiddingDetail($res['bidding_no']);

        $type_map = [
            'AUTHFAILED' => ChannelRefundLog::TYPE_CANCEL, //订单取消
            'DIDNOTSHIP' => ChannelRefundLog::TYPE_CANCEL, //订单取消
            'CANCELED' => ChannelRefundLog::TYPE_CANCEL, //订单取消
            'RETURNED' => ChannelRefundLog::TYPE_REFUND, //买家客退
        ];
        $refund = [
            'type' => $type_map[$data['status']] ?? '',
            'order_no' => $order_no,
            'cancel_time' => $close_time,
        ];

        $res['refund'] = $refund;
        return $res;
    }

    /**
     * 获取订单详细信息
     * @param $order_no [订单编号]
     * @return array
     */
    public function getOrderDetail($order_no): array
    {
        $order = StockxOrder::where(['orderNumber' => $order_no])->first();
        if (!$order) return [];

        // 获取发货单
        if (!$order->shipment) {
            $api = new StockxApi();
            $res = $api->orderDetail($order_no);
            return self::syncOrder($res);
        }
        return $this->orderDetailFormat($order);
    }

    //统计已售数量和剩余数量
    public function getBiddingDetail($bidding_no)
    {
        return [];
        $qty = 0;
        $qty_sold = 0;
        $qty_remain = 0;

        $bidding = ChannelBidding::where(['bidding_no' => $bidding_no])->first();
        if (!$bidding) goto RES;

        $orders = $bidding->channelOrders;
        $qty = $bidding->qty;
        $qty_sold = count($orders);
        $qty_remain = $qty - $qty_sold;
        RES:
        return compact('qty', 'qty_sold', 'qty_remain');
    }

    // 同步出价状态
    function syncBiddingInfo($bidding)
    {
        $stockx_bidding = StockxBidding::where(['bidding_no' => $bidding->bidding_no])->first();
        $api = new StockxApi();
        $res = $api->getList($stockx_bidding->listingId);
        if (!$res) return true;
        $qty = 1;
        $qty_cancel = 0;
        $qty_sold = 0;
        if (in_array($res['status'], ['INACTIVE', 'DELETED', 'CANCELED'])) {
            $qty_cancel = 1;
        }
        if (in_array($res['status'], ['MATCHED', 'COMPLETED'])) {
            $qty_sold = 1;
        }
        $status = 0;
        if ($qty_cancel) {
            $status = ChannelBidding::BID_CANCEL;
        }
        if ($status && $bidding->status != $status) {
            $bidding->update([
                'status' => $status,
                'qty_sold' => $qty_sold,
                'qty_remain' => max($qty - $qty_cancel - $qty_sold, 0),
            ]);
        }
        $stockx_bidding->update(['status' => $res['status']]);
        return true;
    }

    // 获取最低价 
    function getLowestPrice($sku_id)
    {
        $sku = StockxProductVariant::where(['id' => $sku_id])->first();
        $lowest_price = 0;
        $lowest_price_jpy = 0;

        $api = new StockxApi();
        $res = $api->marketData($sku->productId, $sku->variantId);
        $currency_code = $res['currencyCode'] ?? '';
        if (in_array($currency_code, ['JPY', 'USD'])) {
            $price = $res['sellFasterAmount'] ?? 0;
            if ($price > 0) {
                $lowest_price = $price; //日元
                $lowest_price_jpy = $price;
                if ($currency_code == 'USD') {
                    $lowest_price_jpy = $this->usd2jpy($lowest_price);
                }
            }
        }

        return compact('lowest_price', 'lowest_price_jpy');
    }

    //出价金额处理
    public function bidPriceHandle($params, $lowest)
    {
        $origin = $params['price'];
        $price = $params['price'];

        // 符合跟价规则就用最低价，不符合就不出价
        $bid_price = null;
        if ($lowest['lowest_price_jpy'] ?? 0) {
            $last_bid = $params['last_bid'] ?? null;
            // 如果当前出价就是最低价，不做跟价处理
            if ($last_bid && $lowest['lowest_price_jpy'] == $last_bid->price) {
                $bid_price = $lowest['lowest_price_jpy'];
            } else {
                $bid_price = $lowest['lowest_price_jpy'];
            }
            // 实际出价金额 < 应出价金额
            if ($bid_price <= 0 || $bid_price < $price) {
                $bid_price = null;
            }
        }

        $price = $bid_price ? ceil($bid_price / 100) * 100 : $bid_price;
        $params['price_rmb'] = 0;
        $params['currency'] = $this->price_currency;
        $params['price_unit'] = $this->price_unit;
        $params['price'] = $price; //日元
        $params['price_jpy'] = $bid_price; //日元
        $params['profit'] = $params['price_jpy'] - $origin;
        return $params;
    }

    //erp库存出价金额处理
    public function stockBidPriceHandle($params, $lowest, $stock_product)
    {
        $channel = $stock_product->stockxChannel;
        $this->threshold_price = $channel ? $channel->threshold_price : 0;
        $price = null;
        $price_jpy = 0;
        if ($lowest['lowest_price_jpy'] ?? 0) {
            $last_bid = $params['last_bid'] ?? null;
            if ($last_bid && $last_bid->source == 'stock' && $lowest['lowest_price_jpy'] == $last_bid->price) {
                // 如果当前出价就是最低价，不做跟价处理
                $bid_price = $lowest['lowest_price_jpy'];
            } else {
                // 最低价>门槛价*2，出价金额 = 门槛价*1.2
                if ($lowest['lowest_price_jpy'] > bcmul($this->threshold_price, 2, 0)) {
                    $tmp = bcmul($this->threshold_price, 1.2, 0);
                    $bid_price = $tmp;
                    $price_jpy = $tmp;
                } else {
                    $bid_price = $lowest['lowest_price_jpy'];
                }
            }
            $price =  $bid_price > 0 ? $bid_price : null;
            $price_jpy = $price;
        } else {
            // 未获取到最低价，出价金额 = 门槛价*1.2
            $tmp = bcmul($this->threshold_price, 1.2, 0);
            $price = $tmp;
            $price_jpy = $tmp;
        }

        if (!$this->stockBiddingPriceCheck($price_jpy, $stock_product)) $price = null;

        $price = $price ? ceil($price / 100) * 100 : $price;

        $params['price_rmb'] = 0;
        $params['currency'] = $this->price_currency;
        $params['price_unit'] = $this->price_unit;
        $params['price'] = $price;
        $params['price_jpy'] = $price_jpy;
        $params['threshold_price'] = $this->threshold_price;
        $params['profit'] = $price_jpy - $this->threshold_price;
        return $params;
    }

    public $threshold_price = 0;
    public function stockBiddingPriceCheck($price, $stock_product)
    {
        $channel = $stock_product->stockxChannel;
        $this->threshold_price = $channel ? $channel->threshold_price : 0;

        // 未设置门槛价，不能出价
        if (!$this->threshold_price) return false;
        if ($price < $this->threshold_price) return false;
        return true;
    }

    // 保存订单信息并格式化
    static function syncOrder($order)
    {
        if (!($order['orderNumber'] ?? '')) return [];
        if (is_null($order['orderNumber'])) return [];

        $update = [
            'listingId' => $order['listingId'],
            'askId' => $order['askId'],
            'amount' => $order['amount'],
            'currencyCode' => $order['currencyCode'],
            'status' => $order['status'],
            'createdAt' => $order['createdAt'],
            'updatedAt' => $order['updatedAt'],
            'product' => $order['product'],
            'variant' => $order['variant'],
        ];
        if ($order['shipment'] ?? '') {
            $update['shipment'] = $order['shipment'];
        }
        if ($order['payout'] ?? '') {
            $update['payout'] = $order['payout'];
        }
        StockxOrder::updateOrCreate(['orderNumber' => $order['orderNumber']], $update);
        return (new self())->orderDetailFormat($order);
    }

    //订单金额处理
    public function orderPirceHandle(&$detail)
    {
        $detail['currency'] = $this->price_currency;
        $detail['price_unit'] = $this->price_unit;
        $detail['price_rmb'] = 0;
        if ($detail['currencyCode'] == 'USD') {
            $detail['price_jpy'] = $this->usd2jpy($detail['price']);
        }
        $detail['price_jpy'] = $this->usd2jpy($detail['price']);
    }

    /**
     * 商家确认发货
     *
     * @param ChannelOrder $order
     */
    public function businessConfirm($order)
    {
        $order->status = ChannelOrder::STATUS_CONFIRM;
        $order->business_confirm_time = time();
        $order->save();
        return $order;
    }

    //更新获取虚拟物流单号
    public function updateDispatchNum($order, $detail)
    {
        if (!($detail['deliver']['express_no'] ?? '')) {
            return false;
        }

        $api = new StockxApi();
        $data = $api->shipment($detail['deliver']['express_file']);
        if (!$data) return false;

        $finfo = new finfo(FILEINFO_MIME);
        $mime = $finfo->buffer($data);
        if ($mime != "application/pdf; charset=binary") {
            Robot::sendFail(sprintf('发货单获取失败，mime %s , data %s', $mime, $data));
            return false;
        }

        $order->dispatch_num = $detail['deliver']['express_no'];
        //上传到OSS
        $filename = sprintf('dispatch_num/%s_%s_%s.pdf', $order->channel_code, $detail['deliver']['express_no'], genRandomString());
        $oss = new OSSUtil();
        if (!$oss->addFileByData($filename, $data)) {
            return false;
        }
        $order->dispatch_num_url = $filename;
        $order->save();

        if ($order->source_format == ChannelOrder::SOURCE_APP) {
            CarrymeCallbackLogic::dispatchNum($order);
        }

        return true;
    }

    /**
     * 平台确认发货
     *
     * @param ChannelOrder $order
     */
    public function platformConfirm($order)
    {
        $order->update([
            'status' => ChannelOrder::STATUS_DELIVER,
            'platform_confirm_time' => time(),
        ]);
        return $order;
    }

    //跨境-取消出价
    public function biddingCancel($bidding)
    {
        if (in_array($bidding->status, [ChannelBidding::BID_CANCEL, ChannelBidding::BID_FAIL])) return true;

        // 取消失败直接返回
        $res = $this->bidCancel($bidding);
        if ($res['code'] == 200) {
            return true;
        }
        return false;
    }

    // 获取最低价并更新到sku上
    public function syncLowestPrice($params)
    {
        $price = $this->getLowestPrice($params['sku_id']);
        $this->updateLowestPrice(['channel_code' => self::$code, 'spu_id' => $params['spu_id'], 'sku_id' => $params['sku_id'], 'price' => $price]);
        return $price;
    }

    //订单取消
    public function orderCancel($order)
    {
        return [
            'is_success' => false,
            'msg' => 'stockx不支持订单取消',
        ];
    }

    // 商品下架
    public function productTakedown($params)
    {
        $this->_cancel($params['bidding_no']);
        return true;
    }

    // 取消出价是否是同步操作
    public function bidCancelIsSync()
    {
        return false;
    }

    // 根据app原始出价金额，计算加上续费后的应出价金额
    public function appBidPrice($arr)
    {
        $price = $arr['original_price'];
        $stockx_limit = $this->usd2jpy(8600);
        $add = $this->usd2jpy(560);
        if ($price < $stockx_limit) {
            return bcadd(bcdiv($price, 0.971), $add, 0);
        }
        return bcdiv(bcdiv($price, 0.971), 0.94, 0);
    }

    // 订单成立后执行的操作
    function afterOrderCreate(ChannelBidding $bidding, ChannelOrder $order)
    {
        $this->syncListInfo($bidding->bidding_no);
        return true;
    }

    // 订单取消后执行操作
    function afterOrderRefund(ChannelBidding $bidding, ChannelOrder $order)
    {
        $this->syncListInfo($bidding->bidding_no);
        return true;
    }

    // 订单关闭后执行的操作
    function afterOrderClose(ChannelBidding $bidding, ChannelOrder $order)
    {
        $this->syncListInfo($bidding->bidding_no);
        return true;
    }

    function syncListInfo($bidding_no)
    {
        $stockx_bidding = StockxBidding::where(['bidding_no' => $bidding_no])->first();
        if ($stockx_bidding && $stockx_bidding->listingId) {
            $api = new StockxApi();
            $res = $api->getList($stockx_bidding->listingId);
            if ($res) {
                $stockx_bidding->update(['status' => $res['status'], 'updatedAt' => $res['updatedAt']]);
            }
        }
    }
    /**
     * 平台批量确认发货
     *
     */
    public function batchPlatformConfirm($channel_order_ids)
    {
        return false;
    }
}
