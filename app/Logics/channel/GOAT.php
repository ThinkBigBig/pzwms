<?php

namespace App\Logics\channel;

use App\Handlers\DwApi;
use App\Handlers\GoatApi;
use App\Handlers\OSSUtil;
use App\Jobs\stockProduct;
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
use App\Models\GoatOrder;
use App\Models\OperationLog;
use App\Models\SizeStandard;
use Exception;
use Psy\Util\Json;


class GOAT implements ThirdInterface
{
    use ProductSku;
    const BUSINESS_TYPE_KJ = 1; //业务类型-跨境
    static $code = 'GOAT';

    public $price_unit = BaseLogic::PRICE_UNIT_FEN;
    public $price_currency = BaseLogic::CURRENCY_US;
    public $from_currency = BaseLogic::CURRENCY_JP;

    //商品状态
    const SALE_STATUS_ACTIVE = 'active'; //已上架
    const SALE_STATUS_PENDING = 'pending'; //待上架
    const SALE_STATUS_RETURN = 'returned_to_seller'; //已经退回

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
        $exchange = new ExchangeLogic($this->price_currency);
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

    //美分转日元
    public function bidPrice2Jpy($price)
    {
        $exchange = new ExchangeLogic($this->price_currency);
        $exchange->getExchange();
        //1美元 = 100美分
        $price =  bcmul(bcdiv($price, 100, 2), $exchange->reverse_exchange, 2);
        return ceil($price);
    }

    //订单金额 - rmb 美元转人民币
    public function price2Jpy($price)
    {
        return $this->usd2jpy($price);
    }

    public function getProductInfo($product_sn)
    {
        $goat_product_sn = self::getGoatSku($product_sn);
        $api = new GoatApi();
        $data = $api->skuInfo(['sku' => $goat_product_sn]);
        foreach ($data['productTemplates'] as $item) {
            if (self::getGoatSku($item['sku']) == $goat_product_sn) {
                return $item;
            }
        }
        return [];
    }

    public function getSkuId($pt_id, $size)
    {
        $size = str_replace('.0', '', $size);
        return sprintf('%d_%s', $pt_id, $size);
    }

    public function exportSkuId($sku_id)
    {
        list($pt_id, $size) = explode('_', $sku_id);
        return compact('pt_id', 'size');
    }

    public function productDetailFormat($data): array
    {
        if (!$data) return [];
        $maps = [
            'active' => ChannelProduct::STATUS_ACTIVE,
        ];
        $status = $maps[$data['status']] ?? 0;
        $info = [
            'good_name' => $data['name'], //商品名
            'good_name_cn' => $data['name'], //商品名称-中文
            'spu_logo_origin' => $data['mainPictureUrl'], //商品图片
            'product_sn' => self::getProductSn($data['sku']), //货号
            'brand_id' => 0, //品牌id
            'brand_name' => $data['brandName'], //品牌名称
            'spu_id' => $data['id'], //所在渠道spu_id
            'status' => $status, //1上架 0下架
            'category_id' => 0, //分类id
            'category_name' => $data['productCategory'], //分类名
        ];
        $specialType = $data['specialType'] ?? '';
        $tag = $specialType && $specialType == 'standard' ? '' : $specialType;
        $skus = [];
        foreach ($data['sizeOptions'] as $size) {
            $properties = [];
            if ($data['productCategory'] == 'shoes') {
                $size_us = $size['presentation'];
                $size = SizeStandard::sizeInfo($size_us, $data['singleGender'], $data['sizeBrand'], $info['product_sn']);
                $properties = ['original' => $size_us, 'size_us' => $size_us, 'size_eu' => $size ? $size->size_eu : '', 'size_fr' => $size ? $size->size_fr : ''];
            }

            if ($data['productCategory'] == 'clothing') {
                $size_eu = $size['presentation'];
                $size_us = $size['value'];
                $properties = ['original' => $size, 'size_eu' => strtoupper($size_eu), 'size_us' => $size_us];
            }

            $skus[] = [
                'properties' => $properties,
                'barcode' => '',
                'sku_id' => $this->getSkuId($data['id'], $size_us),
                'spu_id' => $data['id'],
                'status' => $status,
                'tags'   => $tag,
            ];
        }
        return compact('info', 'skus');
    }

    //判断sku的属性是否匹配
    public function matchSku($sku, $properties, $product = null)
    {
        $arr = array_column($properties, 'valuecn');
        $arr2 = [$sku->properties['size_eu']];
        if (array_diff($arr, $arr2) || array_diff($arr2, $arr)) return false;
        return true;
    }

    /**
     * 库存出价，判断sku的属性是否匹配
     * 
     * - 38⅔ 匹配10
     * - 38.5-D宽 匹配 10
     * - 38.5 匹配 10
     * - 38.5-天蓝色 匹配10
     *
     */
    public function stockMatchSku($sku, $properties, $product = null)
    {
        $attr = $properties[0]['valuecn'];
        $attrs = explode('-', $attr);
        foreach ($attrs as $item) {
            if (is_numeric($item) || is_numeric(substr($item, 0, -3))) {
                if (in_array($item, [$sku->properties['size_eu'], $sku->properties['size_fr'] ?? ''])) {
                    return true;
                }
            }

            $arr = ['XXS','XS','S', 'M', 'L', 'XL', 'XXL'];
            if (is_string($item) && in_array($item, $arr) && $item === $sku->properties['size_eu']) {
                return true;
            }
        }
        return false;
    }

    /**
     * 新增商品
     * @throws Exception
     */
    public function bid($product, $price, $qty, $type = self::BUSINESS_TYPE_KJ, $params = [])
    {
        $api = new GoatApi();
        $size = $product['properties']['size_us'];
        $ext_tag = $product['ext_tag'];
        $data = $api->createProduct($qty, $product['spu_id'], $size, $price, $ext_tag);
        return $data['jobId'];
    }

    /**
     * 批量更新价格
     *
     * @param array $product_ids
     * @param int $price
     */
    public function updateProductPrice($product_ids, $price)
    {
        $api = new GoatApi();
        $params = array_map(function ($id) use ($price) {
            return ['id' => $id, 'priceCents' => $price];
        }, $product_ids);
        return $api->updateProductPrice($params);
    }


    //商家订单发货
    public function uploadOrderDeliver($order_no, $deliver_no)
    {
        $params = [
            'order_no' => $order_no,
            'dispatch_num' => $deliver_no,
            'carrier' => 100, //0:顺丰速运,1:京东配送,2:德邦,6:中通国际,7:UPS,8:联邦快递,9:美国邮政,10:宅急便,11:佐川急便,20:自提,30:得物自送,100:虚拟物流承运商
        ];
        $data = (new DwApi($params))->uniformRequest('1,24,apiUrl', $params);
        $data = $data ? json_decode($data, true) : [];
        if ($data && $data['code'] == 200) {
            return true;
        }
        throw new Exception($data['msg'] ?? '接口未响应');
    }

    /**
     * 新增或更新出价
     * 查是否有可更新出价，没有再新增出价
     * 废弃
     *
     * @param ChannelBidding|null $last_bid
     * @param int $price
     * @param int $qty
     * @param array $product
     * @deprecated
     */
    public function bidOrUpdate1($last_bid, $price, $qty, $product)
    {
        $old_bidding_no = '';
        $bid_status = 0;
        $bidding_no = '';
        //没有出价，直接新增
        if (!$last_bid) {
            $bidding_no = 'G' . genRandomString() . time();
            $product['ext_tag'] = $bidding_no;
            $res = $this->bid($product, $price, $qty);
            goto BID_RES;
        }

        $bidding_no = $last_bid->bidding_no;
        //查已上架数量
        $product_ids = ChannelBiddingItem::where(['channel_bidding_id' => $last_bid->id, 'status' => ChannelBiddingItem::STATUS_SHELF])->pluck('product_id')->toArray();
        $num = count($product_ids);

        $update_num = min($qty, $num);
        $add_num = max($qty - $num, 0);
        $del_num = max($num - $qty, 0);

        //价格不变，部分数据维持不变
        if ($last_bid->price == $price) {
            $update_num = 0;
        }

        if ($update_num) {
            //批量更新指定商品的价格
            $params = [];
            $update_products = array_slice($product_ids, 0, $update_num);
            //更新价格数量接口
            $res = $this->updateProductPrice($update_products, $price);
        }

        if ($del_num) {
            $del_products = array_slice($product_ids, $update_num, $del_num);
            $api = new GoatApi();
            //调下架接口
            foreach ($del_products as $product_id) {
                $res = $api->cancelProduct($product_id);
                if (($res['saleStatus'] ?? '') == 'canceled') {
                    ChannelBiddingItem::where(['product_id' => $product_id])
                        ->update(['status' => ChannelBiddingItem::STATUS_TAKEDOWN, 'takedown_at' => date('Y-m-d H:i:s')]);
                    ChannelBiddingLog::addLog(ChannelBiddingLog::TYPE_CANCEL, $last_bid, [
                        'new_price' => $price,
                        'product_id' => $product_id
                    ]);
                } else {
                    //下架状态没有变化
                    $msg = sprintf('下架失败，渠道 %s ，product_id %s ，结果：%s', self::$code, $product_id, Json::encode($res));
                    Robot::sendText(Robot::FAIL_MSG, $msg);
                }
            }
        }

        if ($add_num) {
            $product['ext_tag'] = $last_bid->bidding_no;
            $res = $this->bid($product, $price, $add_num);
            goto BID_RES;
        }

        BID_RES:
        return compact('bidding_no', 'old_bidding_no', 'bid_status');
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
        if ($last_bid) {
            //取消出价
            $channel_bidding_items = $last_bid->channelBiddingItem;
            $api = new GoatApi();
            foreach ($channel_bidding_items as $val) {
                $product_id = $val->product_id;
                if (!$product_id) continue;
                $res = $api->cancelProduct($product_id);
                if (($res['saleStatus'] ?? '') == 'canceled') {
                    ChannelBiddingItem::where(['product_id' => $product_id])
                        ->update(['status' => ChannelBiddingItem::STATUS_TAKEDOWN, 'takedown_at' => date('Y-m-d H:i:s')]);
                    ChannelBiddingLog::addLog(ChannelBiddingLog::TYPE_CANCEL, $last_bid, [
                        'new_price' => $price,
                        'product_id' => $product_id
                    ]);
                } else {
                    //下架状态没有变化
                    $msg = sprintf('下架失败，渠道 %s ，product_id %s ，结果：%s', self::$code, $product_id, Json::encode($res));
                    Robot::sendText(Robot::FAIL_MSG, $msg);
                }
            }
        }

        //新增出价
        $bidding_no = 'G' . genRandomString() . time();
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
        $where = [
            // 'status' => ChannelBiddingItem::STATUS_SHELF,
            'channel_bidding_id' => $bidding->id
        ];
        $items = ChannelBiddingItem::where($where)->get();
        $cancel_num = 0;
        $qty = $bidding->qty;

        foreach ($items as $item) {
            if ($item->status == ChannelBiddingItem::STATUS_TAKEDOWN) {
                $cancel_num++;
                continue;
            }

            $success = $this->productTakedown(['product_id' => $item->product_id, 'bidding_no' => $bidding->bidding_no]);
            if ($success) {
                $cancel_num++;
                $item->update([
                    'status' => ChannelBiddingItem::STATUS_TAKEDOWN,
                    'takedown_at' => date('Y-m-d H:i:s')
                ]);
                ChannelBiddingLog::addLog(ChannelBiddingLog::TYPE_CANCEL, $bidding, [
                    'new_price' => $bidding->price,
                    'new_qty' => $item->qty,
                    'product_id' => $item->product_id,
                ]);
            }
        }
        return [
            'code' => 200,
            'data' => [
                'qty' => $qty,
                'qty_cancel' => $cancel_num
            ]
        ];
    }

    static function getProductSn($sku)
    {
        return strtoupper(str_replace(' ', '-', $sku));
    }

    static function getGoatSku($product_sn)
    {
        $product_sn = str_replace('-', '', $product_sn);
        return str_replace(' ', '', $product_sn);
    }

    public function getOrders($params)
    {
        $api = new GoatApi();
        $orders = $api->getOrders($params);
        return $orders;
    }

    // 保存订单信息
    static function syncOrder($origin)
    {
        if (!($origin['id'] ?? '')) return [];
        $update = [
            'productId' => $origin['productId'] ?? '',
            'number' => $origin['number'] ?? '',
            'purchaseOrderNumber' => $origin['purchaseOrderNumber'] ?? '',
            'status' => $origin['status'] ?? '',
            'priceCents' => $origin['priceCents'] ?? '',
            'purchasedAt' => $origin['purchasedAt'] ?? '',
        ];

        if ($origin['trackingToGoatCode'] ?? '') $update['trackingToGoatCode'] = $origin['trackingToGoatCode'];
        if ($origin['trackingToGoatCodeUrl'] ?? '') $update['trackingToGoatCodeUrl'] = $origin['trackingToGoatCodeUrl'];
        if ($origin['carrierToGoat'] ?? '') $update['carrierToGoat'] = $origin['carrierToGoat'];
        if ($origin['shippingLabelToGoat'] ?? '') $update['shippingLabelToGoat'] = $origin['shippingLabelToGoat'];
        GoatOrder::updateOrCreate(['orderId' => $origin['id']], $update);
        return [];
    }

    public function orderDetailFormat($data)
    {
        if (!$data) return [];
        $order_no = $data['number'];
        $paysuccess_time = $data['purchasedAt']; //购买时间
        $sku_id = $this->getSkuId($data['product']['productTemplate']['id'], $data['product']['size']);
        $spu_id = $data['product']['productTemplate']['id'];
        $good_name = $data['product']['productTemplate']['name'];
        $properties = $data['product']['size'];
        $qty = $data['product']['quantity']; //购买数量
        $bidding_no = $data['product']['extTag'] ?? ''; //商家出价编号
        $price = $data['product']['priceCents'];
        $deliver = [
            'express_no' => $data['trackingToGoatCode'] ?? '',
            'express_file' => $data['shippingLabelToGoat'] ?? '',
            'delivery_time' => $data['updatedAt'],
        ];

        $maps = [
            'sold' => ChannelOrder::STATUS_CREATED, //用户下单并风控审核通过
            'goat_review' => ChannelOrder::STATUS_DEFAULT, //等待风险评估
            'goat_review_risk_rejected' => ChannelOrder::STATUS_DEFAULT, //风险评估-待人工审核
            'goat_review_skipped' => ChannelOrder::STATUS_DEFAULT, //风险评估-待用户提供验证码

            'fraudulent' => ChannelOrder::STATUS_CANCEL, //风控未通过，订单已退款
            'canceled_by_buyer' => ChannelOrder::STATUS_CANCEL, //买家取消
            'canceled_by_seller' => ChannelOrder::STATUS_CANCEL, //卖家取消

            'seller_confirmed' => ChannelOrder::STATUS_CREATED, //卖家确认发货，用户无法取消
            'canceled_by_seller_review' => ChannelOrder::STATUS_CREATED, //卖家超时未发货，快要取消
            'seller_packaging' => ChannelOrder::STATUS_CONFIRM, //卖家打包发货
            'with_courier' => ChannelOrder::STATUS_DELIVER, //商品交付给物流
            'shipped_goat' => ChannelOrder::STATUS_DELIVER, //运输中
            'delivered_goat' => ChannelOrder::STATUS_DELIVER, //运输中
            'goat_received' => ChannelOrder::STATUS_DELIVER, //goat已签收
            'goat_verified' => ChannelOrder::STATUS_DELIVER, //鉴定通过

            'goat_issue' => ChannelOrder::STATUS_CLOSE, //质检有问题
            'goat_issue_return_to_seller' => ChannelOrder::STATUS_CLOSE, //退货卖家
            'goat_issue_resolved' => ChannelOrder::STATUS_CLOSE, //商品寄存或退回给卖家

            'shipped_buyer' => ChannelOrder::STATUS_DELIVER, //发货至买家
            'goat_pick' =>  ChannelOrder::STATUS_DELIVER, //仓库内部操作
            'goat_packaged' =>  ChannelOrder::STATUS_DELIVER, //已打包准备发给买家，已打款

            'delivered' => ChannelOrder::STATUS_COMPLETE, //已送达买家，已打款
            'goat_consigned' => ChannelOrder::STATUS_COMPLETE, //被GOAT收入，已打款
            'goat_acquired' => ChannelOrder::STATUS_COMPLETE, //被GOAT收入，已打款
            'buyer_confirmed' =>  ChannelOrder::STATUS_COMPLETE, //买家已确认收货，已打款
        ];
        $order_status = $maps[$data['status']] ?? ChannelOrder::STATUS_DELIVER;
        $order_type = '';
        $modify_time = $data['updatedAt']; //订单修改时间
        $close_time = $data['updatedAt']; //订单关闭时间
        $res =  compact('order_no', 'paysuccess_time', 'sku_id', 'spu_id', 'good_name', 'properties', 'qty', 'bidding_no', 'deliver', 'order_status', 'price', 'order_type', 'modify_time', 'close_time');


        //订单取消截止时间
        if ($data['status'] == 'sold') {
            $res['cancel_end_time'] = self::cancelEndTime($data['updatedAt']);
        }

        $res['order_id'] = $data['id'];
        $res['product_id'] = $data['product']['id'];
        $res['updated_at'] =  $data['updatedAt'];
        $res['sub_status'] = $data['status'];
        $res['close_remark'] = ($data['sellerTitle'] ?? '') . '.' . ($data['sellerDescription'] ?? '');

        //订单完成时间
        $res['completion_time'] = '';
        if ($order_status == ChannelOrder::STATUS_COMPLETE) {
            $res['completion_time'] = $data['updatedAt'];
        }

        $maps = [
            'goat_review_skipped' => '风险评估中，待用户提供验证码（24小时内审核通过）',
            'goat_review_risk_rejected' => '风险评估中，等待人工审核',
            'fraudulent' => '风控未通过，订单已退款',
        ];
        $res['sub_status_txt'] = $maps[$data['status']] ?? $data['status'];
        //根据出价单，统计剩余数量、已售数量
        $res['bidding_detail'] = $this->getBiddingDetail($res['bidding_no']);

        $type_map = [
            'fraudulent' => ChannelRefundLog::TYPE_CANCEL, //订单取消
            'canceled_by_buyer' => ChannelRefundLog::TYPE_REFUND, //买家客退
            // 'canceled_by_seller_review' => ChannelRefundLog::TYPE_DELIVER_TIMEOUT,
            'canceled_by_seller' => ChannelRefundLog::TYPE_BUSINESS_CANCEL,
        ];
        $refund = [
            'type' => $type_map[$data['status']] ?? '',
            'order_no' => $order_no,
            'order_id' => $res['order_id'],
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
        $api = new GoatApi();
        $data = $api->getOrderInfo($order_no);
        return $this->orderDetailFormat($data);
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
        // 根据出价单号，找到所有商品，轮询
        $api = new GoatApi();
        $products = $api->productSearch(['ext_tag' => $bidding->bidding_no])['products'] ?? [];
        if (!$products) return true;

        $qty_sold = 0;
        $qty = 0;
        $qty_cancel = 0;
        foreach ($products as $product) {
            $qty++;
            if (in_array($product['saleStatus'], ['pending', 'canceled'])) {
                $qty_cancel++;
            }
            if ($product['saleStatus'] == 'completed') {
                $qty_sold++;
            }
        }
        $status = 0;
        if ($qty_cancel >= $qty) {
            $status = ChannelBidding::BID_CANCEL;
        }
        if ($status && $bidding->status != $status) {
            $bidding->update([
                'status' => $status,
                'qty_sold' => $qty_sold,
                'qty_remain' => max($qty - $qty_cancel - $qty_sold, 0),
            ]);
        }

        return true;
    }

    function getLowestPrice($sku_id)
    {
        $sku = self::exportSkuId($sku_id);
        $lowest_price = 0;
        $lowest_price_jpy = 0;

        //根据sku_id获取商品id
        $data = $this->getLowestPriceAll($sku['pt_id'])[$sku['size']] ?? [];
        if ($data && isset($data['lowestPriceCents']['amountUsdCents'])) {
            $lowest_price = max($data['lowestPriceCents']['amountUsdCents'] - bcmul(env('GOAT_FIXED_INCREASE'), 100), 0);
            $lowest_price_jpy = $this->usd2jpy($lowest_price);
        }
        return compact('lowest_price', 'lowest_price_jpy');
    }

    //获取指定商品的最低价
    private function getLowestPriceAll($spu_id)
    {
        $data = (new GoatApi())->getLowestPrice($spu_id);
        $res = [];
        foreach ($data as $item) {
            if ($item['shoeCondition'] == 'new_no_defects' && $item['boxCondition'] == 'good_condition') {
                $res[(string)$item['sizeOption']['value']] = $item;
            }
        }
        return $res;
    }



    //出价金额处理
    public function bidPriceHandle($params, $lowest)
    {
        $origin = $params['price'];
        $price = $this->jpy2usd($params['price']);

        // 符合跟价规则就最低价，不符合就不出价
        $bid_price = null;
        if ($lowest['lowest_price'] ?? 0) {
            $last_bid = $params['last_bid'] ?? null;
            // 如果当前出价就是最低价，不做跟价处理
            if ($last_bid && $lowest['lowest_price'] == $last_bid->price) {
                $bid_price = $lowest['lowest_price'];
            } else {
                $bid_price = $lowest['lowest_price'];
            }
            // 实际出价金额 < 应出价金额
            if ($bid_price <= 0 || $bid_price < $price) {
                $bid_price = null;
            }
        }


        $params['price_rmb'] = 0;
        $params['currency'] = $this->price_currency;
        $params['price_unit'] = $this->price_unit;
        $params['price'] = $bid_price; //美分
        $params['price_jpy'] = $bid_price ? $this->usd2jpy($bid_price) : 0;
        $params['profit'] = $params['price_jpy'] - $origin;
        return $params;
    }

    //出价金额处理
    public function stockBidPriceHandle($params, $lowest, $stock_product)
    {
        $this->threshold_price = $stock_product->goatChannel->threshold_price;
        $price = null;
        $price_jpy = 0;
        if ($lowest['lowest_price'] ?? 0) {
            $last_bid = $params['last_bid'] ?? null;
            if ($last_bid && $last_bid->source == 'stock' && $lowest['lowest_price'] == $last_bid->price) {
                // 如果当前出价就是最低价，不做跟价处理
                $bid_price = $lowest['lowest_price'];
            } else {
                // 最低价>门槛价*2，出价金额 = 门槛价*1.2
                if ($lowest['lowest_price_jpy'] > bcmul($this->threshold_price, 2, 0)) {
                    $tmp = bcmul($this->threshold_price, 1.2, 0);
                    $bid_price = $this->jpy2usd($tmp);
                    $price_jpy = $tmp;
                } else {
                    $bid_price = $lowest['lowest_price'];
                }
            }
            $price =  $bid_price > 0 ? $bid_price : null;
        } else {
            // 未获取到最低价，出价金额 = 门槛价*1.2
            $tmp = bcmul($this->threshold_price, 1.2, 0);
            $price = $this->jpy2usd($tmp);
            $price_jpy = $tmp;
        }

        if (!$price_jpy) {
            $price_jpy = $price ? $this->usd2jpy($price) : 0;
        }

        if (!$this->stockBiddingPriceCheck($price_jpy, $stock_product)) $price = null;

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
        $this->threshold_price = $stock_product->goatChannel->threshold_price;
        if ($price < $this->threshold_price) return false;
        return true;
    }

    //订单金额处理
    public function orderPirceHandle(&$detail)
    {
        $detail['currency'] = $this->price_currency;
        $detail['price_unit'] = $this->price_unit;
        $detail['price_rmb'] = 0;
        $detail['price_jpy'] = $this->usd2jpy($detail['price']);
    }

    /**
     * 商家确认发货
     * 确认并打包
     *
     * @param ChannelOrder $order
     */
    public function businessConfirm($order)
    {
        $api = new GoatApi();
        $order_no = $order->order_no;
        //查订单状态
        $detail = $this->getOrderDetail($order_no);

        // 订单默认、已取消、已关闭状态时，不可操作发货
        if (!in_array($detail['order_status'], [ChannelOrder::STATUS_CREATED, ChannelOrder::STATUS_CONFIRM, ChannelOrder::STATUS_DELIVER, ChannelOrder::STATUS_COMPLETE])) {
            throw new Exception('订单 %s 处于 %s 状态 ，不可操作发货', $detail['order_no'], $detail['sub_status']);
        }

        if ($detail['sub_status'] == 'sold') {
            //卖家确认
            if (!$api->updateOrderStatus($order_no, 'seller_confirm')) throw new Exception('卖家确认操作失败');
            $detail['sub_status'] = 'seller_confirmed';
        }
        if ($detail['sub_status'] == 'seller_confirmed') {
            //卖家打包
            if (!$api->updateOrderStatus($order_no, 'seller_packaging')) throw new Exception('卖家打包操作失败');
        }
        $order->status = ChannelOrder::STATUS_CONFIRM;
        $order->business_confirm_time = time();
        $order->save();
        return $order;
    }

    /**
     * 仅确认不打包
     *
     * @param ChannelOrder $order
     */
    public function confirm($order)
    {
        $api = new GoatApi();
        $order_no = $order->order_no;
        //查订单状态
        $detail = $this->getOrderDetail($order_no);

        // 订单默认、已取消、已关闭状态时，不可操作发货
        if (!in_array($detail['order_status'], [ChannelOrder::STATUS_CREATED, ChannelOrder::STATUS_CONFIRM, ChannelOrder::STATUS_DELIVER, ChannelOrder::STATUS_COMPLETE])) {
            throw new Exception('订单 %s 处于 %s 状态 ，不可操作发货', $detail['order_no'], $detail['sub_status']);
        }

        if ($detail['sub_status'] == 'sold') {
            //卖家确认
            if (!$api->updateOrderStatus($order_no, 'seller_confirm')) throw new Exception('卖家确认操作失败');
            OperationLog::add2(OperationLog::ORDER_AUTO_CONFIRM, 0, $order_no, ['remark' => '脚本自动确认']);
        }
        return $order;
    }


    //更新获取虚拟物流单号
    public function updateDispatchNum($order, $detail)
    {
        if ($detail['deliver']['express_no']) {
            $order->dispatch_num = $detail['deliver']['express_no'];
            //上传到OSS
            $filename = sprintf('dispatch_num/%s_%s_%s.%s', $order->channel_code, $detail['deliver']['express_no'], genRandomString(), pathinfo($detail['deliver']['express_file'])['extension']);
            $oss = new OSSUtil();
            if ($oss->addFileByUrl($filename, $detail['deliver']['express_file'])) {
                $order->dispatch_num_url = $filename;
            } else {
                $order->dispatch_num_url = $detail['deliver']['express_file'];
            }
            $order->save();

            if ($order->source_format == ChannelOrder::SOURCE_APP) {
                CarrymeCallbackLogic::dispatchNum($order);
            }

            return true;
        } else {
            Robot::sendNotice(sprintf('GOAT未获取到虚拟物流单号，距确认时间%d秒，订单号：%s', time() - strtotime($order->business_confirm_time), $order->order_no));
        }
        return false;
    }

    /**
     * 平台确认发货
     *
     * @param ChannelOrder $order
     */
    public function platformConfirm($order)
    {
        $data = $this->getOrderDetail($order->order_no);

        if (!$order->dispatch_num) {
            throw new Exception('尚未获取到虚拟物流单号，不可操作发货。');
        }

        //如果三方订单是已发货状态
        if (in_array($data['order_status'], [ChannelOrder::STATUS_DELIVER, ChannelOrder::STATUS_COMPLETE])) {
            goto SAVE;
        }

        if ($data['order_status'] != ChannelOrder::STATUS_CONFIRM) {
            throw new Exception(sprintf('当前订单在GOAT处于 【%s】 状态，不可操作发货。', $data['sub_status_txt'] ?? ''));
        }

        $api = new GoatApi();
        if ($api->updateOrderStatus($order->order_no, 'with_courier')) {
            goto SAVE;
        }
        throw new Exception('已发货操作失败');

        SAVE:
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
        if (!$res['data']['qty_cancel']) {
            return false;
        }
        $detail = $this->getBiddingDetail($bidding->bidding_no);

        $qty = $detail['qty'] ?? 1;
        $qty_sold = $detail['qty_sold'] ?? 0;
        $qty_cancel = $detail['qty_remain'] ?? 1;

        $update = [
            'qty_cancel' => $qty_cancel,
            'qty_remain' => max(0, $qty - $qty_sold - $qty_cancel),
            'status' => ChannelBidding::BID_CANCEL,
        ];
        //更新出价状态
        $bidding->update($update);
        return true;
    }

    public function syncLowestPrice($params)
    {
        $data = $this->getLowestPriceAll($params['spu_id']);
        foreach ($data as $size => $price) {
            if (!isset($price['lowestPriceCents']['amountUsdCents'])) continue;
            $lowest_price = max($price['lowestPriceCents']['amountUsdCents'] - bcmul(env('GOAT_FIXED_INCREASE'), 100), 0);
            $price = [
                'lowest_price' => $lowest_price,
                'lowest_price_jpy' => $this->usd2jpy($lowest_price),
            ];
            $this->updateLowestPrice(['channel_code' => self::$code, 'spu_id' => $params['spu_id'], 'sku_id' => $this->getSkuId($params['spu_id'], $size), 'price' => $price]);
        }

        return $data;
    }

    //订单取消
    public function orderCancel($order)
    {
        $msg = '接口调用失败';
        $is_success = false;

        $detail = $this->getOrderDetail($order->order_no);
        if (!in_array($detail['sub_status'], ['sold', 'seller_packaging', 'seller_confirmed', 'with_courier'])) {
            return [
                'is_success' => $is_success,
                'msg' => '当前状态不可取消，' . $detail['sub_status'],
            ];
        }

        $api = new GoatApi();
        if ($api->updateOrderStatus($order->order_no, 'seller_cancel_review')) {
            $is_success = true;
        } else {
            $msg = json_encode($api->messages);
        }

        return [
            'is_success' => $is_success,
            'msg' => $msg,
        ];
    }

    // 计算订单取消截止时间
    static function cancelEndTime($from)
    {
        $h = date('H', strtotime($from));
        $week = date('w', strtotime($from));
        if ($week == 0) $week = 7;

        $add = $h >= 13 ? 3 : 2;
        $start = $week;
        if ($h >= 13) $start = $week + 1; //开始计算是星期几
        $end = $start + 2; //加三天后是星期几
        if ($end >= 7) {
            if ($week == 7) {
                $add = 3;
            } else {
                $add = $h >= 13 ? 4 : 3;
            }
        }

        $time = strtotime(date('Y-m-d', strtotime($from) + $add * 24 * 3600));
        $time = $time + 61 * 3600 + 0 * 3600; //61小时 + extra buffer(0小时)
        return date('Y-m-d H:i:s', $time);
    }

    // 商品下架
    public function productTakedown($params)
    {
        $api = new GoatApi();
        $products = $api->productSearch(['ext_tag' => $params['bidding_no']])['products'] ?? [];
        $total = 0;
        $cancel = 0;
        foreach ($products as $product) {
            if ($product['extTag'] != $params['bidding_no']) continue;
            $total++;
            if (in_array($product['saleStatus'], ['canceled', 'pending'])) {
                $cancel++;
                continue;
            }

            if ($product['saleStatus'] == 'completed') {
                continue;
            }

            if ($product['saleStatus'] == 'active') {
                $res = $api->cancelProduct($product['id']);
                if (!$res || ($res['success'] ?? '-1') == false) {
                    $msg = sprintf('商品下架失败，product_id:%s，res:%s', $params['product_id'], Json::encode($res));
                    Robot::sendText(Robot::FAIL_MSG, $msg);
                    continue;
                }

                if ($res && ($res['saleStatus'] ?? '') == 'canceled') {
                    $cancel++;
                    continue;
                }
            }
        }
        if ($total > $cancel) return false;
        return true;
    }

    // 商品下架
    public function productTakedown2($params)
    {
        $ids = [];
        $api = new GoatApi();
        $products = $api->productSearch(['ext_tag' => $params['bidding_no']])['products'] ?? [];
        foreach ($products as $product) {
            if ($product['saleStatus'] == 'active') {
                $ids[] = ['id' => $product['id']];
            }
        }
        if ($ids) {
            // 批量下架商品
            $api->deactivateProducts($ids);
            $products = $api->productSearch(['ext_tag' => $params['bidding_no']])['products'] ?? [];
        }
        if (!$products) {
            Robot::sendFail('商品信息获取失败 bidding_no:' . $params['bidding_no']);
            return false;
        }

        $total = 0;
        $cancel = 0;
        foreach ($products as $product) {
            $total++;
            if (in_array($product['saleStatus'], ['canceled', 'deleted', 'pending'])) {
                $cancel++;
            }
        }
        if ($total > $cancel) {
            Robot::sendFail('商品下架失败，products:%s', Json::encode($products));
            return false;
        }
        return true;
    }

    // 取消出价是否是同步操作
    public function bidCancelIsSync()
    {
        return true;
    }

    // 根据app原始出价金额，计算加上续费后的应出价金额
    public function appBidPrice($arr)
    {
        return $arr['price'];
    }

    // 订单成立后执行的操作
    function afterOrderCreate(ChannelBidding $bidding, ChannelOrder $order)
    {
        return true;
    }

    // 订单取消后执行操作
    function afterOrderRefund(ChannelBidding $bidding, ChannelOrder $order)
    {
        return true;
    }

    // 订单关闭后执行的操作
    function afterOrderClose(ChannelBidding $bidding, ChannelOrder $order)
    {
        return true;
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
