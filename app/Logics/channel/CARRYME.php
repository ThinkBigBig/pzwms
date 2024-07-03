<?php

namespace App\Logics\channel;

use App\Handlers\AppApi;
use App\Handlers\OSSUtil;
use App\Handlers\StockxApi;
use App\Logics\BaseLogic;
use App\Logics\CarrymeCallbackLogic;
use App\Logics\ChannelLogic;
use App\Logics\ExchangeLogic;
use App\Logics\RedisKey;
use App\Logics\Robot;
use App\Logics\traits\ProductSku;
use App\Models\AppBidConfig;
use App\Models\AppBidding;
use App\Models\AppOrder;
use App\Models\AppProduct;
use App\Models\AppProductSku;
use App\Models\ChannelBidding;
use App\Models\ChannelOrder;
use App\Models\ChannelProduct;
use App\Models\ChannelPurchaseBidding;
use App\Models\ChannelRefundLog;
use App\Models\ProductBrand;
use App\Models\SizeStandard;
use App\Models\StockBiddingItem;
use Exception;
use finfo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Ramsey\Uuid\Uuid;

class CARRYME implements ThirdInterface
{
    use ProductSku;
    const BUSINESS_TYPE_KJ = 1; //业务类型-跨境
    static $code = 'CARRYME';

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

    // carryme的出价也是日元，不用进行汇率转换
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
        $product = AppProduct::where(['productSn' => $product_sn])->first();
        if ($product) return $product;

        $api = new AppApi();
        $api->productInit($product_sn);
        return AppProduct::where(['productSn' => $product_sn])->first();
    }

    private function _skus($product)
    {
        $skus = AppProductSku::where(['productId' => $product->productId])->get();
        if ($skus->count() > 0) return $skus;

        $api = new AppApi();
        $api->syncLowestPrice($product->productSn);
        return AppProductSku::where(['productId' => $product->productId])->get();
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
            'good_name' => $product->name, //商品名
            'good_name_cn' => $product->nameCn, //商品名称-中文
            'spu_logo' => $product->pic, //商品图片
            'product_sn' => $product->productSn, //货号
            'brand_id' => $product->brandId, //品牌id
            'brand_name' => $product->brandName, //品牌名称
            'spu_id' => $product->id, //所在渠道spu_id
            'status' => $status, //1上架 0下架
            'category_id' => 0, //分类id
            'category_name' => '', //分类名
        ];

        $skus = [];
        foreach ($data['skus'] as $item) {
            $skus[] = [
                'properties' => $this->sizeFormat(strtolower($product->brand), $info['product_sn'], $product->eligibility, $item->spData),
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
    private function sizeFormat($brand, $product_sn, $gender, $value)
    {
        $res = ['original' => $value,];
        // 适用人群:0->通用；1->男；2->女；3->儿童；4->中童
        $maps = [
            '2' => 'women',
            '1' => 'men',
            '4' => 'youth',
            '3' => 'infant',
        ];
        // 如果商品信息中有品牌信息，按照商品信息的获取
        $size_brand = ProductBrand::where(['product_sn' => $product_sn])->value('size_brand');
        if ($size_brand) {
            $brands = [$size_brand];
        } else {
            // Nike, Union
            $brands = explode(',', $brand);
        }

        foreach ($value as $item) {
            // [{"key": "Yeezy 男","value": "22.5","valuecn": "36.5"}]
            $res['size_jp'] = $item['value'];
            $res['size_cn'] = $item['valuecn'];
            $res['size_eu'] = is_string($item['valuecn']) ? strtoupper($item['valuecn']) : $item['valuecn'];
            $size = SizeStandard::getSizeFr($item['valuecn'], $maps[$gender] ?? '', $brands[0], $product_sn);
            if ($size) {
                $res['size_fr'] = $size->size_fr;
            }
        }

        return $res;
    }

    //判断sku的属性是否匹配 app出价不同步CARRYME
    public function matchSku($sku, $properties, $product = null)
    {
        return false;
    }

    /**
     * 库存出价判断sku的属性是否匹配
     * - 38.5 匹配38.5
     * - 38.5-D宽 匹配38.5
     */
    public function stockMatchSku($sku, $properties, $product)
    {
        $attr = $properties[0]['valuecn'];
        $attrs = explode('-', $attr);
        $arr2 = [];
        $arr3 = [];

        if ($sku->properties['size_eu'] ?? '') $arr2[] = $sku->properties['size_eu'];
        if ($sku->properties['size_fr'] ?? '') $arr3[] = $sku->properties['size_fr'];

        $notmatch_eu = array_diff($attrs, $arr2) || array_diff($arr2, $attrs);
        $notmatch_fr = array_diff($attrs, $arr3) || array_diff($arr3, $attrs);

        if ($notmatch_eu && $notmatch_fr) return false;
        return true;
    }



    public function _cancel($bidding_no, $params = [])
    {
        // 查出价单状态并同步
        $api = new AppApi($params);
        $detail = $api->bidDetail($bidding_no);
        $status = $detail['status'] ?? 0;
        // 已取消
        if ($status == AppBidding::CANCEL) {
            return true;
        }
        // 已售出不再取消
        if ($status == AppBidding::SOLD) {
            return false;
        }

        $bidding = AppBidding::where(['bidding_no' => $bidding_no])->first();
        if (!$bidding) return true;

        // 出价已取消或删除
        if (in_array($bidding->status, [AppBidding::CANCEL])) return true;
        if (!$params) {
            $username = $bidding->username ?: env('CARRYME_APP_USERNAME');
            $params = $this->channelPurchaseAccount($username);
            $api = new AppApi($params);
        }

        $res = $api->bidCancel($bidding->logId, $bidding->quantity,);
        $code = $res['code'] ?? 0;
        // code 10057-商品已经被购买  10102-正在买入，不能取消
        if (in_array($code, [200, 10113])) {
            $bidding->update(['status' => AppBidding::CANCEL, 'cancel_retry' => 0]);
            return true;
        }
        if ($code == 10057) {
            $bidding->update(['cancel_retry' => 0, 'status' => AppBidding::SOLD]);
            return true;
        }
        // 未响应0、服务器错误500 、10102订单支付中 这几种场景下重试删除出价
        if (in_array($code, [0, 500, 10102])) {
            $bidding->update(['cancel_retry' => 1, 'retry_times' => $bidding->retry_times + 1]);
        }
        Robot::sendFail(sprintf('carryme 删除出价失败 params：%s res: %s', json_encode($bidding), json_encode($res)));
        return false;
    }

    /**
     * 新增商品
     * @throws Exception
     */
    public function bid($product, $price, $qty, $type = self::BUSINESS_TYPE_KJ, $params = [])
    {
        $bidding = AppBidding::where(['bidding_no' => $product['ext_tag']])->first();
        if ($bidding) return true;

        // 根据sku 和spu找获取productId 、
        $va = AppProductSku::where(['id' => $product['sku_id']])->first();
        if (!$va) {
            throw new Exception('carryme 未找到对应的Sku信息');
        }

        $source = $params['source'] ?? 'stock';
        $params = $this->channelPurchaseAccount('', $source);

        $bidding = AppBidding::create([
            'bidding_no' => $product['ext_tag'],
            'price' => $price,
            'quantity' => $qty,
            'productId' => $va->productId,
            'standardId' => $va->standardId,
            'username' => $params['username'],
        ]);

        $api = new AppApi($params);
        $data = $api->bid($price, $qty, $va->productId, $va->standardId, $product['ext_tag']);
        if ($data) {
            $bidding->update([
                'earnestMoney' => $data['earnestMoney'], //保证金
                'price' => $data['price'],
                'productId' => $data['productId'],
                'standardId' => $data['standardId'],
                'quantity' => $data['quantity'],
                'type' => $data['type'], //0-现货 1-预售
                'status' => AppBidding::SUCCESS,
                'logId' => $data['logId'],
                'username' => $params['username'],
            ]);
            return true;
        }

        throw new Exception('carryme 出价失败' . json_encode($data));
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
        $bid_status = 1;
        $bidding_no = '';
        if ($last_bid) { //取消出价
            $this->_cancel($bidding_no);
        }

        //新增出价
        $bidding_no = 'C' . genRandomString() . time();
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
        return [
            'code' => 200,
            'data' => [
                'qty' => 1,
                'qty_cancel' => 0
            ]
        ];
    }

    //格式化回调数据
    public function callbackFormat($params, $bidding_no = ''): array
    {
        $where = ['orderId' => $params['orderId']];
        $update = [
            'orderNo' => $params['orderNo'],
            'logId' => $params['logId'],
            'quantity' => $params['quantity'],
            'price' => $params['price'],
            'paytime' => substr($params['paytime'], 0, 10),
        ];
        AppOrder::updateOrCreate($where, $update);

        $order_detail = $this->orderDetailFormat($params, true);
        $event = ChannelLogic::CALLBACK_EVENT_ORDER_CREATED;
        if ($order_detail['bidding_no'] ?? '') {
            $order_detail['order_type'] = 3;
        }
        $origin = [
            'order_no' => $params['orderNo'],
            'event' => $event,
            'content' => json_encode($params),
        ];
        $refund = [];
        $bidding = [];

        return compact('origin', 'event', 'order_detail', 'refund', 'bidding');
    }

    function dispatch_num($app_bid)
    {
        if ($app_bid->username == (self::config()['DW']['username'] ?? '')) {
            $dispatch_num = 'CM' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        } else {
            $uuid = Uuid::uuid4()->toString();
            $dispatch_num = 'xshd-85a' . substr($uuid, 0, 8);
        }

        $find = ChannelOrder::where(['channel_code' => 'CARRYME', 'dispatch_num' => $dispatch_num])->first();
        if (!$find) return $dispatch_num;
        return $this->dispatch_num($app_bid);
    }

    // 格式化订单数据
    public function orderDetailFormat($data, $update = false)
    {
        if (!$data) return [];
        $app_bid = AppBidding::where(['logId' => $data['logId']])->first();
        $bidding = $app_bid ? $app_bid->channelBidding : null;
        if (!$bidding) return [];

        if ($update) {
            $api = new AppApi();
            $api->orderSearch([$data['orderId']]);
        }

        $data = AppOrder::where(['orderId' => $data['orderId']])->first();

        $bidding_no = $bidding->bidding_no;
        $order = ChannelOrder::where(['channel_code' => self::$code, 'order_no' => $data['orderNo']])->first();
        $order_no = $data['orderNo'];
        $paysuccess_time = date('Y-m-d H:i:s', $data['paytime']); //购买时间
        $sku_id = $bidding->sku_id;
        $spu_id = $bidding->spu_id;
        $good_name = $app_bid->appProduct->name;
        $properties = $app_bid->appProductSku->spData;
        $qty = 1; //购买数量
        $express_no = ($order && $order->dispatch_num) ? $order->dispatch_num : ($data['sellDeliverySn'] ?? '');
        if (!$express_no) {
            $express_no = $this->dispatch_num($app_bid);
        }

        $deliver = [
            'express_no' => $express_no,
            'express_file' => '',
            'delivery_time' => '',
        ];

        // 订单状态，*0->求购中；1->待付款；2->待发货；3->已发货；4->已完成；5->已关闭；6->无效订单；7->平台鉴定中；8->平台已发货（鉴定通过）；9->待付定金如果status为5此值为0和1  10->待平台发货；11->待出价；12->出售中; 13->退货中；14->取回中
        $maps  = [
            '0' => ChannelOrder::STATUS_CREATED,
            '1' => ChannelOrder::STATUS_CREATED,
            '2' => ChannelOrder::STATUS_CREATED,
            '3' => ChannelOrder::STATUS_DELIVER,
            '4' => ChannelOrder::STATUS_COMPLETE,
            '5' => ChannelOrder::STATUS_CLOSE,
            '7' => ChannelOrder::STATUS_DELIVER,
            '8' => ChannelOrder::STATUS_DELIVER,
        ];

        $order_status = $maps[$data['status']] ?? 0;
        if ($order && $order_status == ChannelOrder::STATUS_CREATED && $order->status == ChannelOrder::STATUS_CONFIRM) {
            $order_status = ChannelOrder::STATUS_CONFIRM;
        }
        if ($order && $order_status == ChannelOrder::STATUS_CLOSE && in_array($order->status, ChannelOrder::$no_sendout_status)) {
            $order_status = ChannelOrder::STATUS_CANCEL;
        }
        $order_type = '';
        $modify_time = ''; //订单修改时间
        $close_time = date('Y-m-d H:i:s'); //订单关闭时间
        $res =  compact('order_no', 'paysuccess_time', 'sku_id', 'spu_id', 'good_name', 'properties', 'qty', 'bidding_no', 'deliver', 'order_status', 'order_type', 'modify_time', 'close_time');

        $price_jpy = $data['price'];
        $res['price'] = $price_jpy;
        $res['currency'] = $this->price_currency;
        $res['price_unit'] = $this->price_unit;
        $res['price_rmb'] = 0;
        $res['price_jpy'] = $price_jpy;
        $res['updated_at'] =  '';
        $res['sub_status'] = $data['status'];
        $res['close_remark'] = '';

        //订单完成时间
        $res['completion_time'] = '';
        if ($order_status == ChannelOrder::STATUS_COMPLETE) {
            $res['completion_time'] = date('Y-m-d H:i:s');
        }
        $res['sub_status_txt'] = '';
        //根据出价单，统计剩余数量、已售数量
        $res['bidding_detail'] = $this->getBiddingDetail($res['bidding_no']);

        $maps = [
            '5' => ChannelRefundLog::TYPE_DELIVER_TIMEOUT
        ];
        $refund = [
            'type' => $maps[$data['status']] ?? '',
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
        $order = AppOrder::where(['orderNo' => $order_no])->first();
        if (!$order) return [];
        return $this->orderDetailFormat($order, true);
    }

    //统计已售数量和剩余数量
    public function getBiddingDetail($bidding_no)
    {
        return [];
        $qty = 0;
        $qty_sold = 0;
        $qty_remain = 0;
        return compact('qty', 'qty_sold', 'qty_remain');
    }

    // 获取最低价 
    function getLowestPrice($sku_id)
    {
        $sku = AppProductSku::where(['id' => $sku_id])->first();
        $spu = $sku ? $sku->product : null;
        if ($sku && $spu) {
            return $this->syncLowestPrice([
                'spu_id' => $spu->id,
                'sku_id' => $sku_id,
            ]);
        }

        $lowest_price = 0;
        $lowest_price_jpy = 0;
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
        $params['price'] = null; //日元
        $params['price_jpy'] = null; //日元
        $params['profit'] = $params['price_jpy'] - $origin;
        return $params;
    }

    //erp库存出价金额处理
    public function stockBidPriceHandle($params, $lowest, $stock_product)
    {
        $channel = $stock_product->carrymeChannel;
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

        // $price = $price ? ceil($price / 100) * 100 : $price;

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
        $channel = $stock_product->carrymeChannel;
        $this->threshold_price = $channel ? $channel->threshold_price : 0;

        // 未设置门槛价，不能出价
        if (!$this->threshold_price) return false;
        if ($price < $this->threshold_price) return false;
        return true;
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
        $order->dispatch_num = $detail['deliver']['express_no'];
        $order->save();
        return true;
    }

    static function config()
    {
        $info = Redis::get(RedisKey::CM_BID_CONFIG);
        if (!$info) {
            $info = AppBidConfig::where('status', AppBidConfig::ACTIVE)->get()->keyBy('source')->toArray();
            Redis::set(RedisKey::CM_BID_CONFIG, json_encode($info));
            return $info;
        }
        return json_decode($info, true);
    }

    /**
     * 平台确认发货
     *
     * @param ChannelOrder $order
     */
    public function platformConfirm($order)
    {
        $order = ChannelOrder::where(['id' => $order->id])->first();
        $data = $this->getOrderDetail($order->order_no);

        //如果三方订单是已发货状态
        if (in_array($data['order_status'], [ChannelOrder::STATUS_DELIVER, ChannelOrder::STATUS_COMPLETE])) {
            $order->update([
                'status' => ChannelOrder::STATUS_DELIVER,
                'platform_confirm_time' => time(),
            ]);
            return true;
        }

        // 订单已关闭
        if ($data['order_status'] == ChannelOrder::STATUS_CLOSE) {
            $order->update([
                'status' => $data['order_status'],
                'close_time' => $data['close_time'],
            ]);
            throw new Exception('订单已关闭');
        }

        if ($order->status != ChannelOrder::STATUS_CONFIRM) {
            throw new Exception('非平台确认发货状态，不能操作发货');
        }
        $params = [];
        $params = $this->channelPurchaseAccount('', $order->stock_source);
        $origin = AppOrder::where(['orderNo' => $order->order_no])->first();

        $api = new AppApi($params);
        $res = $api->orderDeliver([['orderId' => $origin->orderId, 'deliverySn' => $order->dispatch_num]]);
        if ($res && $res['code'] == 200 && ($res['data'] ?? '')) {
            $order->update([
                'status' => ChannelOrder::STATUS_DELIVER,
                'platform_confirm_time' => time(),
            ]);
            return true;
        } elseif ($order->stock_source == 'DW' && ($res['message'] ?? '') && strpos($res['message'], $order->dispatch_num . '已存在') !== false) {
            // 发货单已存在，重新生成，并再次发货
            $app_bid = $origin->appBidding;
            if ($app_bid) {
                $order->update(['dispatch_num' => $this->dispatch_num($app_bid),]);
                return $this->platformConfirm($order);
            }
        }
        throw new Exception($res['message'] ?? '操作失败');
    }

    protected function allAccount()
    {
        return [
            env('CARRYME_APP_USERNAME') => [
                'username' => env('CARRYME_APP_USERNAME'),
                'password' => env('CARRYME_APP_PASSWORD'),
                'address_id' => env('CARRYME_APP_ADDRESSID'),
                'ship_day' => env('CARRYME_APP_SHIPDAY'),
                'type' => 0, //现货
            ],
            env('CARRYME_APP_USERNAME2') => [
                'username' => env('CARRYME_APP_USERNAME2'),
                'password' => env('CARRYME_APP_PASSWORD2'),
                'address_id' => env('CARRYME_APP_ADDRESSID2'),
                'ship_day' => env('CARRYME_APP_SHIPDAY2'),
                'type' => 1, //预售
            ],
        ];
    }


    protected function channelPurchaseAccount($username = '', $source = '')
    {
        $config = self::config();
        if ($source) {
            return $config[$source];
        } elseif ($username) {
            foreach ($config as $item) {
                if ($username == $item['username']) {
                    return $item;
                }
            }
        }
        return [];
    }
    //跨境-取消出价
    public function biddingCancel($bidding)
    {
        if (in_array($bidding->status, [ChannelBidding::BID_CANCEL, ChannelBidding::BID_FAIL])) return true;
        // 取消失败直接返回
        $res = $this->_cancel($bidding->bidding_no);
        if ($res) {
            $update = [
                'qty_cancel' => 1,
                'qty_remain' => 0,
                'status' => ChannelBidding::BID_CANCEL,
            ];
            //更新出价状态
            $bidding->update($update);
            return true;
        }

        return false;
    }

    // 获取最低价并更新到sku上
    public function syncLowestPrice($params)
    {
        $lowest_price = 0;
        $lowest_price_jpy = 0;
        $price = compact('lowest_price', 'lowest_price_jpy');

        $product = AppProduct::where(['id' => $params['spu_id']])->first();
        if (!$product) return $price;
        $product_sn = $product->productSn;

        $res = (new AppApi())->syncLowestPrice($product_sn)['skuPrices'] ?? [];
        if (!$res) {
            return $price;
        }
        foreach ($res as $item) {
            $tmp = ['lowest_price' => $item['stockPrice'] ?: 0, 'lowest_price_jpy' => $item['stockPrice'] ?: 0,];
            $sku = AppProductSku::where(['standardId' => $item['id']])->first();
            if ($sku) {
                $this->updateLowestPrice(['channel_code' => self::$code, 'spu_id' => $product->id, 'sku_id' => $sku->id, 'price' => $tmp]);
            }
            if ($sku->id == $params['sku_id']) {
                $price = $tmp;
            }
        }
        return $price;
    }

    //订单取消
    public function orderCancel($order)
    {
        return [
            'is_success' => false,
            'msg' => 'carryme不支持订单取消',
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
        $order_nos = ChannelOrder::whereIn('id', $channel_order_ids)->where('status', ChannelOrder::STATUS_CONFIRM)->pluck('order_no')->toArray();
        $order_ids = AppOrder::whereIn('orderNo', $order_nos)->where('status', AppOrder::WAITDELIVER)->pluck('orderId')->toArray();
        if (!$order_ids) return true;

        // [['orderId' => $origin->orderId, 'deliverySn' => $order->dispatch_num]
        $api = new AppApi();
        $api->orderDeliver($order_ids);
        $api->orderSearch($order_ids);
        return true;
    }

    /**
     * 渠道最低价出价到CARRYME
     * 跟库存出价到CARRYME的区别是使用的账号不同
     *
     * @param int $price
     * @param int $qty
     * @param array $product
     */
    public function channelPurchaseBid($price, $qty, $product)
    {
        if (!$price) {
            throw new Exception('出价金额不能为空');
        }
        $bid_status = 1;
        //新增出价
        $bidding_no = 'C2' . genRandomString() . time();
        $product['ext_tag'] = $bidding_no;
        $res = $this->bid($product, $price, $qty, self::BUSINESS_TYPE_KJ, ['source' => 'DW']);
        return compact('bidding_no', 'bid_status');
    }

    function syncBiddingInfo(ChannelBidding $bidding)
    {
        // 查出价单状态并同步
        $api = new AppApi();
        $detail = $api->bidDetail($bidding->bidding_no);
        $status = $detail['status'] ?? 0;

        $stock_item = $bidding->stockBiddingItem;
        $purchase_item = $bidding->channelPurchaseBidding;

        // 更新取消状态
        $need_cancel = ($bidding->status != ChannelBidding::BID_CANCEL)
            || ($stock_item && $stock_item->status == StockBiddingItem::STATUS_SUCCESS)
            || ($purchase_item && $purchase_item->status == ChannelPurchaseBidding::SUCCESS);

        if ($status == AppBidding::CANCEL && $need_cancel) {
            $bidding->update(['status' => ChannelBidding::BID_CANCEL, 'qty_cancel' => 1]);
            if ($stock_item) {
                $stock_item->update(['status' => StockBiddingItem::STATUS_CANCEL]);
            }
            if ($purchase_item) {
                $purchase_item->update(['status' => ChannelPurchaseBidding::CANCEL, 'cancel_remark' => '同步出价状态取消']);
            }
            return $bidding;
        }

        if ($status == AppBidding::SOLD && $bidding->qty_sold == 0) {
            $bidding->update(['qty_sold' => 1]);
            if ($stock_item) {
                $stock_item->update(['qty_sold' => 1]);
            }
            if ($purchase_item) {
                $purchase_item->update(['qty_sold' => 1]);
            }
            return $bidding;
        }
        return $bidding;
    }
}
