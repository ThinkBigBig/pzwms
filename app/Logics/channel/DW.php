<?php

namespace App\Logics\channel;

use App\Handlers\DwApi;
use App\Logics\BaseLogic;
use App\Logics\ChannelLogic;
use App\Logics\ExchangeLogic;
use App\Logics\FreeTaxLogic;
use App\Logics\RedisKey;
use App\Logics\Robot;
use App\Logics\traits\ProductSku;
use App\Models\CarryMeBiddingItem;
use App\Models\ChannelBidding;
use App\Models\ChannelBiddingLog;
use App\Models\ChannelOrder;
use App\Models\ChannelRefundLog;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\SizeMatchRule;
use App\Models\SizeStandard;
use App\Models\StockProductLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Psy\Util\Json;

class DW implements ThirdInterface
{

    use ProductSku;
    const BUSINESS_TYPE_KJ = 1; //业务类型-跨境
    static $code = 'DW';

    public $price_unit = BaseLogic::PRICE_UNIT_FEN;
    public $price_currency = ExchangeLogic::CURRENCY_RMB;
    public $from_currency = BaseLogic::CURRENCY_JP;

    //订单类型
    const ORDER_TYPE_KJ = 3; //跨境
    const ORDER_TYPE_FREE_TAX = 25; //保税仓

    //人民币（分）转日元
    public function RMB2Jpy($price)
    {
        $logic = new ExchangeLogic(ExchangeLogic::CURRENCY_RMB);
        $logic->getExchange();
        return floor(bcmul(bcdiv($price, 100), $logic->reverse_exchange));
    }

    public function bidPrice2Jpy($price)
    {
        $logic = new ExchangeLogic(ExchangeLogic::CURRENCY_RMB);
        $logic->getExchange();
        return floor(bcmul(bcdiv($price, 100), $logic->reverse_exchange));
    }

    public function exchange()
    {
        $exchange = new ExchangeLogic($this->price_currency);
        $exchange->getExchange();
        return $exchange;
    }

    //出价金额处理
    public function bidPriceHandle($params, $lowest)
    {
        $origin = $params['price'];
        // 日元转人民币
        $price_data = $this->jpy2rmb($params['price']);
        $price = $price_data['price'];

        // 符合跟价规则就最低价-10，不符合就原始金额出价
        $bid_price = $price;
        if ($lowest['lowest_price'] ?? 0) {
            $last_bid = $params['last_bid'];
            // 如果当前出价就是最低价，不做跟价处理
            if ($last_bid && $lowest['lowest_price'] == $last_bid->price) {
                $bid_price = $lowest['lowest_price'];
            } else {
                $bid_price = $lowest['lowest_price'] - 1000;
                if ($bid_price <= 0 || $price > $bid_price) {
                    $bid_price = $price;
                }
            }
        }

        $params['price_rmb'] = $price_data['rmb'];
        $params['currency'] = $this->price_currency;
        $params['price_unit'] = $this->price_unit;
        $params['price'] = $bid_price;
        $params['price_jpy'] = $bid_price ? $this->RMB2Jpy($bid_price) : 0;
        $params['profit'] = $params['price_jpy'] - $origin;
        return $params;
    }

    // 日元转人民币分
    public function jpy2rmb($price)
    {
        $logic = new ExchangeLogic(ExchangeLogic::CURRENCY_RMB);
        $logic->getExchange();
        // 出价金额日元转人民币
        $rmb = ceil(bcmul(bcmul($price, $logic->positive_exchange), 100));

        $price1 = intval($rmb / 1000) * 1000;
        $price2 = $rmb % 1000;
        $price2 = ($price2 > 900) ? 1900 : 900;
        $price =  bcadd($price1, $price2); //单位人民币，金额向上取9
        return [
            'price' => $price,
            'rmb' => $rmb
        ];
    }
    //出价金额处理
    public function stockBidPriceHandle($params, $lowest, $stock_product)
    {
        $this->threshold_price = $stock_product->dwChannel->threshold_price;
        $price = null;
        $price_jpy = 0;
        if ($lowest['lowest_price'] ?? 0) {
            $last_bid = $params['last_bid'] ?? null;
            if ($last_bid && $last_bid->source == 'stock'  && $lowest['lowest_price'] == $last_bid->price) {
                // 如果当前出价就是最低价，不做跟价处理
                $bid_price = $lowest['lowest_price'];
            } else {
                // 最低价>门槛价*2，出价金额 = 门槛价*1.2
                if ($lowest['lowest_price_jpy'] > bcmul($this->threshold_price, 2, 0)) {
                    $tmp = bcmul($this->threshold_price, 1.2, 0);
                    $bid_price = $this->jpy2rmb($tmp)['price'];
                } else {
                    $bid_price = $lowest['lowest_price'] - 1000;
                }
            }
            $price = $bid_price > 0 ? $bid_price : null;
        } else {
            // 未获取到最低价，出价金额 = 门槛价*1.2
            $tmp = bcmul($this->threshold_price, 1.2, 0);
            $price = $this->jpy2rmb($tmp)['price'];
        }

        $price_jpy = $price ? $this->RMB2Jpy($price) : 0;
        if (!$this->stockBiddingPriceCheck($price_jpy, $stock_product)) $price = null;

        $params['price_rmb'] = $price;
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
        $this->threshold_price = $stock_product->dwChannel->threshold_price;
        if ($price < $this->threshold_price) return false;
        return true;
    }


    public function getProductInfo($product_sn)
    {
        $params = [
            'article_numbers' => [$product_sn]
        ];
        $data = (new DwApi($params))->uniformRequest('2,2,apiUrl', $params);
        $data = $data ? json_decode($data, true) : [];
        if ($data && $data['code'] == 200) {
            foreach ($data['data'] as $item) {
                if ($item['article_number'] == $product_sn) {
                    return $item;
                }
            }
        }
        throw new Exception($data['msg'] ?? '接口未响应');
    }


    /**
     * 根据传入的商品规格匹配对应的sku
     * @throws Exception
     */
    public function productInfoFormat($data, $property): array
    {
        if (!$data) return [];

        foreach ($data as $product) {

            //商品已下架，不做处理
            if ($product['status'] == 0) continue;

            $spu_id = $product['spu_id'];
            $good_name = $product['title'];
            $skus = $product['skus'];
            $sku_id = 0;
            $product_sn = $product['article_number'];
            $spu_logo_origin = $product['spu_logo'];

            foreach ($skus as $sku) {
                $match = true;
                $pp = json_decode($sku['properties'], true);
                foreach ($property as $item) {
                    $val = $item['valuecn'] ?? $item['value'];
                    if (($pp[$item['key']] ?? '') != $val) {
                        $match = false;
                        break;
                    }
                }
                if ($match) {
                    $sku_id = $sku['sku_id'];
                    $properties = $sku['properties'];
                    break;
                }
            }
            return compact('spu_id', 'sku_id', 'good_name', 'properties', 'product_sn', 'spu_logo_origin');
        }
        throw new Exception('商品不存在或已下架');
    }

    public function productDetailFormat($data): array
    {
        $info = [
            'good_name' => $data['title'], //商品名
            'good_name_cn' => $data['title'], //商品名称-中文
            'spu_logo_origin' => $data['spu_logo'], //商品图片
            'product_sn' => $data['article_number'], //货号
            'brand_id' => $data['brand_id'], //品牌id
            'brand_name' => $data['brand_name'], //品牌名称
            'spu_id' => $data['spu_id'], //所在渠道spu_id
            'status' => $data['status'], //1上架 0下架
            'category_id' => $data['category_id'], //分类id
            'category_name' => $data['category_name'], //分类名
        ];
        $skus = [];
        foreach ($data['skus'] as $sku) {
            $attrs = json_decode($sku['properties'], true);
            $size_eu = $attrs['尺码'] ?? '';
            if ($size_eu) {
                // 根据尺码找到对应的欧码和法码 ，并保存
                $brand = ProductBrand::where(['product_sn' => $data['article_number']])->first();
                if ($brand) {
                    $brand_size = $brand->size_brand;
                } else {
                    $brand_size = strtolower($data['brand_name']);
                }
                $size = SizeStandard::getSizeFr($size_eu, 'men', $brand_size, $data['article_number']);
                $attrs['size_eu'] = $size_eu;
                if ($size) {
                    $attrs['size_eu'] = $size->size_eu;
                    $attrs['size_fr'] = $size->size_fr;
                }
            }
            $skus[] = [
                'properties' => $attrs,
                'barcode' => $sku['barcode'],
                'sku_id' => $sku['sku_id'],
                'spu_id' => $data['spu_id'],
                'status' => $data['status'],
                'tags'   => '',
            ];
        }
        return compact('info', 'skus');
    }

    // 自己生成虚拟物流单号
    function deliverNo()
    {
        $num = Redis::get(RedisKey::DW_DISPATCH_CURRENT);
        if (!$num) {
            $num = env('DW_DISPATCHNUM_INIT_NUM');
            if (date('Y-m-d') >= '2023-09-22') {
                $last = ChannelOrder::where(['channel_code' => self::$code])->where('dispatch_num', '>', '')->orderBy('id', 'desc')->first();
                if ($last) {
                    $num = substr($last->dispatch_num, 2, -3);
                }
            }
        }
        $num += 1;
        Redis::set(RedisKey::DW_DISPATCH_CURRENT, $num);
        $deliver_no = '49' . $num . rand(100, 999);
        $deliver_no = (string) $deliver_no;
        $find = ChannelOrder::where(['channel_code' => self::$code, 'dispatch_num' => $deliver_no])->first();
        if (!$find) return $deliver_no;
        return $this->deliverNo();
    }

    // 切换物流承运商的时间
    static private $carrierChangeDate = '2023-09-19';
    //根据订单号生成虚拟物流单
    public function getDeliverNo($order_no)
    {
        $order = ChannelOrder::where(['order_no' => $order_no])->first();
        // 2023.9.19号之后的成立的订单，物流承运商改用宅急便
        if (Carbon::parse($order->paysuccess_time)->format('Y-m-d') >= self::$carrierChangeDate) {
            return $this->deliverNo();
        }

        $params = ['order_no' => $order_no];
        $data = (new DwApi($params))->uniformRequest('1,20,apiUrl', $params);
        $data = $data ? json_decode($data, true) : [];
        if ($data && $data['code'] == 200) {
            return $data['data']['dispatch_num'];
        }
        throw new Exception($data['msg'] ?? '接口未响应');
    }

    //商家订单发货
    public function uploadOrderDeliver($order_no, $deliver_no)
    {
        $order = ChannelOrder::where(['order_no' => $order_no])->first();
        $carrier = 100;
        // 2023.9.19号之后的成立的订单物流承运商改用宅急便
        if (Carbon::parse($order->paysuccess_time)->format('Y-m-d') >= self::$carrierChangeDate) {
            $carrier = 10;
        }

        $params = [
            'order_no' => $order_no,
            'dispatch_num' => $deliver_no,
            'carrier' => $carrier, //0:顺丰速运,1:京东配送,2:德邦,6:中通国际,7:UPS,8:联邦快递,9:美国邮政,10:宅急便,11:佐川急便,20:自提,30:得物自送,100:虚拟物流承运商
        ];
        $data = (new DwApi($params))->uniformRequest('1,24,apiUrl', $params);
        $data = $data ? json_decode($data, true) : [];
        if ($data && $data['code'] == 200) {
            return true;
        }
        throw new Exception($data['msg'] ?? '接口未响应');
    }


    /**
     * 跨境商品 - 新增出价
     * @throws Exception
     */
    public function bid($product, $price, $qty, $type = self::BUSINESS_TYPE_KJ, $params = [])
    {
        $sku_id = $product['sku_id'];
        //跨境出价
        if ($type == self::BUSINESS_TYPE_KJ) {
            $params = [
                'sku_id' => $sku_id,
                'price' => $price,
                'qty' => $qty,
            ];
            $data = (new DwApi($params))->uniformRequest('3,48,apiUrl', $params);
            $data = $data ? json_decode($data, true) : [];
            if ($data && $data['code'] == 200) {
                return BaseLogic::testStr($data['data']['bidding_no']);
            }
            throw new Exception($data['msg'] ?? '接口未响应');
        }
        return '';
    }

    /**
     * 更新出价 - 跨境
     *
     * @return void
     */
    public function bidUpdate($bidding_no, $price, $qty, $old_qty, $type = self::BUSINESS_TYPE_KJ)
    {
        if ($type == self::BUSINESS_TYPE_KJ) {
            $params = [
                'bidding_no' => $bidding_no,
                'price' => $price,
                'qty' => $qty,
                'old_qty' => $old_qty,
            ];
            $data = (new DwApi($params))->uniformRequest('3,45,apiUrl', $params);
            // $data = '';
            // $data = '{"code":20900020,"msg":"出价已取消，请不要重复操作","data":null,"trace_id":"0aee043f642999e6036157ae7a2fcbfa"}';
            return $data ? json_decode($data, true) : [];
        }
        return [];
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
        if(!$price){
            throw new Exception('出价金额不能为空');
        }
        $bidding_no = '';
        $old_bidding_no = '';
        $bid_status = ChannelBidding::BID_SUCCESS;
        $log_bid = $last_bid;

        //没有出价，直接新增出价
        if (!$last_bid) {
            $bidding_no =  $this->bid($product, $price, $qty);
            goto BID_RES;
        }

        $old_bidding_no = $last_bid->bidding_no;
        //修改出价
        $res = $this->bidUpdate($old_bidding_no, $price, $qty, $last_bid->qty);

        //出价更新成功
        if (($res['code'] ?? '') == 200) {
            $bidding_no = $res['data']['bidding_no'] ?? '';
            $last_bid->qty_remain = max(0, $last_bid->qty_remain - $qty);
            //原出价信息更新
            if ($last_bid->qty_remain == 0 && $last_bid->qty_sold == 0) {
                $last_bid->status = ChannelBidding::BID_CANCEL;
                CarryMeBiddingItem::where(['id' => $last_bid->carryme_bidding_item_id])->update(['status' => CarryMeBiddingItem::STATUS_CANCEL, 'remark' => '相同规格更新出价']);
                $last_bid->save();
            }
            goto BID_RES;
        }

        //原出价信息已取消
        if (($res['code'] ?? '') == 20900020) {
            $last_bid->update(['status' => ChannelBidding::BID_CANCEL]);
            $msg = sprintf('通知：同步出价状态。出价单号: %s 原因： %s', $old_bidding_no, $res['msg'] ?? '');
            $old_bidding_no = '';
            $last_bid = null;
            Robot::sendText(Robot::NOTICE_MSG, $msg);
            //直接新增出价
            $bidding_no =  $this->bid($product, $price, $qty);
            goto BID_RES;
        }

        throw new Exception($res['msg'] ?? '接口未响应');

        BID_RES:
        if ($log_bid) {
            ChannelBiddingLog::addLog(ChannelBiddingLog::TYPE_CANCEL, $log_bid, [
                'new_price' => $log_bid->price,
                'new_qty' => $log_bid->qty
            ]);
        }
        return compact('bidding_no', 'old_bidding_no', 'bid_status');
    }


    /**
     * 取消出价 - 跨境
     * @throws Exception
     */
    public function bidCancel($bidding, $type = self::BUSINESS_TYPE_KJ): array
    {
        if ($type == 1) {
            $bidding_no = $bidding->bidding_no;
            $params = [
                'bidding_no' => $bidding_no,
            ];
            $data = (new DwApi($params))->uniformRequest('3,41,apiUrl', $params);
            // $data = '{
            //     "msg": "出价已取消，请不要重复操作",
            //     "trace_id": "0aee1fd7642b9881e4de8a8d4a3ca224",
            //     "code": 20900020
            // }';
            return $data ? json_decode($data, true) : [];
        }
        return [];
    }

    //格式化回调数据
    public function callbackFormat($params, $bidding_no = ''): array
    {
        $api = new DwApi();
        $data = $api->callbackDecrypt($params['msg']);

        if ($params['orderNo'] ?? '') {
            $data['orderNo'] = $params['orderNo'];
        }

        $origin = [
            'event' => $params['type'],
            'content' => Json::encode($data),
            'order_no' => $data['orderNo'] ?? ''
        ];

        $maps = [
            'ORDER_CREATED' => ChannelLogic::CALLBACK_EVENT_ORDER_CREATED,
            'ORDER_QUALITY_BAD' => ChannelLogic::CALLBACK_EVENT_ORDER_REFUND, //质检不通过
            'REFUND_ORDER' => ChannelLogic::CALLBACK_EVENT_ORDER_REFUND, //订单取消
            'ORDER_BUYER_CANCEL' => ChannelLogic::CALLBACK_EVENT_ORDER_REFUND, //买家客退
        ];
        $event = $maps[$params['type']] ?? '';
        $order_detail = [];
        if ($event == ChannelLogic::CALLBACK_EVENT_ORDER_CREATED) {
            $order_detail = $this->getOrderDetail($data['orderNo']);
            // 如果没有获取到，再尝试一次
            if (!$order_detail) {
                $order_detail = $this->getOrderDetail($data['orderNo']);
            }
        }

        $refund = [];
        if ($event == ChannelLogic::CALLBACK_EVENT_ORDER_REFUND) {
            $order_detail = $this->getOrderDetail($data['orderNo']);
            $type_map = [
                'ORDER_QUALITY_BAD' => ChannelRefundLog::TYPE_QUALITY, //质检不通过
                'REFUND_ORDER' => ChannelRefundLog::TYPE_CANCEL, //订单取消
                'ORDER_BUYER_CANCEL' => ChannelRefundLog::TYPE_REFUND, //买家客退
            ];
            $refund = [
                'type' => $type_map[$params['type']],
                'order_no' => $data['orderNo'],
                'cancel_time' => $order_detail['close_time'] ?? '',
                'close_time' => $order_detail['close_time'] ?? '',
            ];
        }

        $order_detail['price_rmb'] = $order_detail['price'];
        $order_detail['currency'] = $this->price_currency;
        $order_detail['price_jpy'] = $this->RMB2Jpy($order_detail['price']);
        $order_detail['price_unit'] = $this->price_unit;
        $order_detail['bidding_detail'] = $this->getBiddingDetail($order_detail['bidding_no']);

        $bidding = [];
        if ($event == ChannelLogic::CALLBACK_EVENT_BIDDING_CLOSE) {
            $bidding = [
                'bidding_no' => $data['sellerBiddingNo'],
                'qty_remain' => $data['remainQuantity'],
            ];
        }

        return compact('origin', 'event', 'order_detail', 'refund', 'bidding');
    }

    //格式化回调数据
    public function callbackFormatTest($params, $bidding_no = ''): array
    {
        $api = new DwApi();
        $data = $api->callbackDecrypt($params['msg']);

        if ($params['is_test'] ?? '') {
            $data['orderNo'] = $params['order_no'] ?? '';
        }

        $origin = [
            'event' => $params['type'],
            'content' => Json::encode($data),
            'order_no' => $data['orderNo'] ?? ''
        ];

        $maps = [
            'ORDER_CREATED' => ChannelLogic::CALLBACK_EVENT_ORDER_CREATED,
            'ORDER_QUALITY_BAD' => ChannelLogic::CALLBACK_EVENT_ORDER_REFUND, //质检不通过
            'REFUND_ORDER' => ChannelLogic::CALLBACK_EVENT_ORDER_REFUND, //订单取消
            'ORDER_BUYER_CANCEL' => ChannelLogic::CALLBACK_EVENT_ORDER_REFUND, //买家客退
            'SELLER_CANCEL_RESULT' => ChannelLogic::CALLBACK_EVENT_BIDDING_CLOSE, //出价关闭
        ];
        $event = $maps[$params['type']] ?? '';
        $order_detail = [];
        if ($event == ChannelLogic::CALLBACK_EVENT_ORDER_CREATED) {
            $order_detail = $this->getOrderDetail($data['orderNo']);
        }

        $refund = [];
        if ($event == ChannelLogic::CALLBACK_EVENT_ORDER_REFUND) {
            $order_detail = $this->getOrderDetail($data['orderNo']);
            $type_map = [
                'ORDER_QUALITY_BAD' => ChannelRefundLog::TYPE_QUALITY, //质检不通过
                'REFUND_ORDER' => ChannelRefundLog::TYPE_CANCEL, //订单取消
                'ORDER_BUYER_CANCEL' => ChannelRefundLog::TYPE_REFUND, //买家客退
            ];
            $refund = [
                'type' => $type_map[$params['type']],
                'order_no' => $data['orderNo'],
                'cancel_time' => $order_detail['close_time'] ?? '',
                'close_time' => $order_detail['close_time'] ?? '',
            ];
        }

        $bidding = [];
        if ($event == ChannelLogic::CALLBACK_EVENT_BIDDING_CLOSE) {
            $bidding = [
                'bidding_no' => $data['sellerBiddingNo'],
                'qty_remain' => $data['remainQuantity'],
            ];
        }

        $order_detail['price_rmb'] = $order_detail['price'];
        $order_detail['currency'] = $this->price_currency;
        $order_detail['price_jpy'] = $this->RMB2Jpy($order_detail['price']);
        $order_detail['price_unit'] = $this->price_unit;
        $order_detail['bidding_detail'] = $this->getBiddingDetail($order_detail['bidding_no']);

        //沙盒环境没有数据，自动模拟
        if (BaseLogic::isTest() || $params['is_test'] ?? '') {
            $order_no = 'XN' . date('YmdHis');
            $origin['order_no'] = $order_no;
            $refund['order_no'] = $order_no;
            $bid = ChannelBidding::where(['status' => ChannelBidding::BID_SUCCESS, 'channel_code' => 'DW'])->first();
            $order_detail['bidding_no'] = $bid->bidding_no;
            $order_detail['order_no'] = $order_no;
            $order_detail['qty'] = 1;
            $bidding['bidding_no'] = $bid->bidding_no;
        }
        return compact('origin', 'event', 'order_detail', 'refund', 'bidding');
    }

    // 将北京时间转成东京时间
    static function dateTimeConvert($datetime): string
    {
        if (!$datetime) return '';
        return Carbon::parse($datetime, 'Asia/Shanghai')->setTimezone('Asia/Tokyo')->toDateTimeString();
    }

    /**
     * 获取订单详细信息
     * @param $order_no [订单编号]
     * @return array
     */
    public function getOrderDetail($order_no): array
    {
        $params = [
            'order_no' => $order_no,
        ];
        $data = (new DwApi($params))->uniformRequest('1,10,apiUrl', $params);
        $data = $data ? json_decode($data, true) : [];
        if ($data && $data['code'] == 200) {
            $data = $data['data']['orders'][0];
            //order_type 0:普通现货,1:普通预售,2:立即变现,3:跨境,6:入仓,7:极速现货,8:极速预售,9:定金预售入仓,10:(非在仓)定金预售,11:汽车出价,12:品牌专供,13:品牌专供入仓,15:虚拟商品订单,21:盲盒,25:保税仓,27:虚拟卡券,36:众筹直发,37:众筹入仓,38:定金预售,100:限时折扣活动,200:跨境极速,201:跨境寄售,1000:拍卖直发,1001:拍卖寄售,1002:拍卖入仓
            $paysuccess_time = self::dateTimeConvert($data['pay_time']); //购买时间
            $sku_id = $data['sku_id'];
            $spu_id = $data['spu_id'];
            $good_name = $data['title'];
            $properties = $data['properties'];
            $qty = $data['qty']; //购买数量
            $bidding_no = $data['seller_bidding_no']; //商家出价编号
            $price = $data['sku_price'];
            $modify_time = self::dateTimeConvert($data['modify_time']); //订单修改时间
            $close_time = self::dateTimeConvert($data['close_time']); //订单关闭时间
            $deliver = [
                'express_no' => $data['express_to_platform']['express_no'],
                'delivery_time' => $data['express_to_platform']['delivery_time'],
            ];
            //1100:定金已支付(定金预售),1200:待支付尾款(定金预售),2000:支付成功,2007:待卖家发货(针对众筹订单,众筹成功),2010:待平台收货,3000:平台已收货,3010:质检通过,3020:鉴别通过,3030:待平台发货,3040:待买家收货,4000:交易成功(买家收货),7000:交易失败(未支付),7500:定金已支付，交易关闭(未支付尾款),8010:交易关闭成功
            $maps = [
                1100 => ChannelOrder::STATUS_DEFAULT,
                1200 => ChannelOrder::STATUS_DEFAULT,
                2000 => ChannelOrder::STATUS_CREATED,
                2007 => ChannelOrder::STATUS_CREATED,
                3030 => ChannelOrder::STATUS_CONFIRM,
                2010 => ChannelOrder::STATUS_DELIVER,
                3000 => ChannelOrder::STATUS_DELIVER,

                3010 => ChannelOrder::STATUS_DELIVER,
                3020 => ChannelOrder::STATUS_DELIVER,
                3030 => ChannelOrder::STATUS_DELIVER,
                3040 => ChannelOrder::STATUS_DELIVER,
                4000 => ChannelOrder::STATUS_COMPLETE,

                7000 => ChannelOrder::STATUS_CANCEL,
                7500 => ChannelOrder::STATUS_CANCEL,
                8010 => ChannelOrder::STATUS_CANCEL,
            ];
            $order_status = $maps[$data['order_status']] ?? ChannelOrder::STATUS_DELIVER;
            //order_type 0:普通现货,1:普通预售,2:立即变现,3:跨境,6:入仓,7:极速现货,8:极速预售,9:定金预售入仓,10:(非在仓)定金预售,11:汽车出价,12:品牌专供,13:品牌专供入仓,15:虚拟商品订单,21:盲盒,25:保税仓,27:虚拟卡券,36:众筹直发,37:众筹入仓,38:定金预售,100:限时折扣活动,200:跨境极速,201:跨境寄售,1000:拍卖直发,1001:拍卖寄售,1002:拍卖入仓
            $order_type = $data['order_type'];
            $res =  compact('order_no', 'paysuccess_time', 'sku_id', 'spu_id', 'good_name', 'properties', 'qty', 'bidding_no', 'deliver', 'order_status', 'price', 'order_type', 'modify_time', 'close_time');

            // 鉴别结果 0：未鉴别，1：鉴别为真，2：鉴别为假，3：有瑕疵，4：开始鉴别
            $res['completion_time'] = '';
            if ($order_status == ChannelOrder::STATUS_COMPLETE) {
                $res['completion_time'] = $modify_time;
            }

            //close_type 0:默认(无业务含义),5:交易关闭,10:超时未支付,11:买家关闭,12:超时未发货,13:质检未通过,14:鉴别未通过,15:平台取消,16:补单超时,17:补单不同意,18:退货关闭,19:申请换货,20:退货关闭, 需要退卖家,21:盲盒超时未取回,22:退货中
            $close_type_maps = [
                '5' => '交易关闭',
                '10' => '超时未支付',
                '11' => '买家关闭',
                '12' => '超时未发货',
                '13' => '质检未通过',
                '14' => '鉴别未通过',
                '15' => '平台取消',
                '16' => '补单超时',
                '17' => '补单不同意',
                '18' => '退货关闭',
                '19' => '申请换货',
                '20' => '退货关闭, 需要退卖家',
                '21' => '盲盒超时未取回',
                '22' => '退货中',

            ];
            $res['sub_status'] = $data['order_status'];
            $close_type = $data['close_type'] ?? 0;
            $res['close_remark'] = $close_type . ' ' . ($close_type_maps[$close_type] ?? '');

            if ($data['order_status'] == 8010) {
                if (in_array($close_type, [13, 14, 18, 19, 20, 21, 22])) {
                    $order_status = ChannelOrder::STATUS_CLOSE;
                }
            }

            $refund = [
                'type' => '',
                'order_no' => $order_no,
                'order_id' => 0,
                'cancel_time' => $close_time,
            ];
            $res['refund'] = $refund;
            return $res;
        }
        return [];
    }

    // 格式化订单数据
    function orderDetailFormat($data): array
    {
        $order_no = $data['order_no'];
        //order_type 0:普通现货,1:普通预售,2:立即变现,3:跨境,6:入仓,7:极速现货,8:极速预售,9:定金预售入仓,10:(非在仓)定金预售,11:汽车出价,12:品牌专供,13:品牌专供入仓,15:虚拟商品订单,21:盲盒,25:保税仓,27:虚拟卡券,36:众筹直发,37:众筹入仓,38:定金预售,100:限时折扣活动,200:跨境极速,201:跨境寄售,1000:拍卖直发,1001:拍卖寄售,1002:拍卖入仓
        $paysuccess_time = $data['pay_time']; //购买时间
        $sku_id = $data['sku_id'];
        $spu_id = $data['spu_id'];
        $good_name = $data['title'];
        $properties = $data['properties'];
        $qty = $data['qty']; //购买数量
        $bidding_no = $data['seller_bidding_no']; //商家出价编号
        $price = $data['sku_price'];
        $modify_time = $data['modify_time']; //订单修改时间
        $close_time = $data['close_time']; //订单关闭时间


        $deliver = ['express_no' => '', 'delivery_time' => '',];
        if ($data['express_to_platform'] ?? []) {
            $deliver = [
                'express_no' => $data['express_to_platform']['express_no'] ?? '',
                'delivery_time' => $data['express_to_platform']['delivery_time'] ?? '',
            ];
        }
        //1100:定金已支付(定金预售),1200:待支付尾款(定金预售),2000:支付成功,2007:待卖家发货(针对众筹订单,众筹成功),2010:待平台收货,3000:平台已收货,3010:质检通过,3020:鉴别通过,3030:待平台发货,3040:待买家收货,4000:交易成功(买家收货),7000:交易失败(未支付),7500:定金已支付，交易关闭(未支付尾款),8010:交易关闭成功
        $maps = [
            1100 => ChannelOrder::STATUS_DEFAULT,
            1200 => ChannelOrder::STATUS_DEFAULT,
            2000 => ChannelOrder::STATUS_CREATED,
            2007 => ChannelOrder::STATUS_CREATED,
            3030 => ChannelOrder::STATUS_CONFIRM,
            2010 => ChannelOrder::STATUS_DELIVER,
            3000 => ChannelOrder::STATUS_DELIVER,

            3010 => ChannelOrder::STATUS_DELIVER,
            3020 => ChannelOrder::STATUS_DELIVER,
            3030 => ChannelOrder::STATUS_DELIVER,
            3040 => ChannelOrder::STATUS_DELIVER,
            4000 => ChannelOrder::STATUS_COMPLETE,

            7000 => ChannelOrder::STATUS_CANCEL,
            7500 => ChannelOrder::STATUS_CANCEL,
            8010 => ChannelOrder::STATUS_CANCEL,
        ];
        $order_status = $maps[$data['order_status']] ?? ChannelOrder::STATUS_DELIVER;
        //order_type 0:普通现货,1:普通预售,2:立即变现,3:跨境,6:入仓,7:极速现货,8:极速预售,9:定金预售入仓,10:(非在仓)定金预售,11:汽车出价,12:品牌专供,13:品牌专供入仓,15:虚拟商品订单,21:盲盒,25:保税仓,27:虚拟卡券,36:众筹直发,37:众筹入仓,38:定金预售,100:限时折扣活动,200:跨境极速,201:跨境寄售,1000:拍卖直发,1001:拍卖寄售,1002:拍卖入仓
        $order_type = $data['order_type'];
        $res =  compact('order_no', 'paysuccess_time', 'sku_id', 'spu_id', 'good_name', 'properties', 'qty', 'bidding_no', 'deliver', 'order_status', 'price', 'order_type', 'modify_time', 'close_time');

        // 鉴别结果 0：未鉴别，1：鉴别为真，2：鉴别为假，3：有瑕疵，4：开始鉴别
        $res['completion_time'] = '';
        if ($order_status == ChannelOrder::STATUS_COMPLETE) {
            $res['completion_time'] = $modify_time;
        }

        //close_type 0:默认(无业务含义),5:交易关闭,10:超时未支付,11:买家关闭,12:超时未发货,13:质检未通过,14:鉴别未通过,15:平台取消,16:补单超时,17:补单不同意,18:退货关闭,19:申请换货,20:退货关闭, 需要退卖家,21:盲盒超时未取回,22:退货中
        $close_type_maps = [
            '5' => '交易关闭',
            '10' => '超时未支付',
            '11' => '买家关闭',
            '12' => '超时未发货',
            '13' => '质检未通过',
            '14' => '鉴别未通过',
            '15' => '平台取消',
            '16' => '补单超时',
            '17' => '补单不同意',
            '18' => '退货关闭',
            '19' => '申请换货',
            '20' => '退货关闭, 需要退卖家',
            '21' => '盲盒超时未取回',
            '22' => '退货中',

        ];
        $res['sub_status'] = $data['order_status'];
        $close_type = $data['close_type'] ?? 0;
        $res['close_remark'] = $close_type . ' ' . ($close_type_maps[$close_type] ?? '');

        if ($data['order_status'] == 8010) {
            if (in_array($close_type, [13, 14, 18, 19, 20, 21, 22])) {
                $order_status = ChannelOrder::STATUS_CLOSE;
            }
        }

        $refund = [
            'type' => '',
            'order_no' => $data['order_no'],
            'order_id' => 0,
            'cancel_time' => $close_time,
        ];
        $res['refund'] = $refund;
        return $res;
    }

    //获取平台最低价
    function getLowestPrice($sku_id)
    {
        //海外直邮最低价
        // $method = '3,37,apiUrl';
        // $params = [
        //     'sku_id' => $sku_id,
        // ];
        // $lowest_price_jpy = 0;
        // $lowest_price = 0;
        // $data = (new DwApi($params))->uniformRequest($method, $params);
        // $data = $data ? json_decode($data, true) : [];
        // if ($data && $data['code'] == 200) {
        //     $lowest_price = $data['data']['items'][0]['lowest_price'] ?? 0;
        //     $lowest_price_jpy = $this->RMB2Jpy($lowest_price);
        // }

        $lowest_price = FreeTaxLogic::getLowestPrice($sku_id)['lower_price3'];
        $lowest_price_jpy = $this->RMB2Jpy($lowest_price);
        return compact('lowest_price', 'lowest_price_jpy');
    }

    //判断sku的属性是否匹配
    public function matchSku($sku, $properties, $product = null)
    {
        $arr = array_column($properties, 'valuecn');

        $properties = $sku['properties'];
        if (isset($properties['size_eu'])) unset($properties['size_eu']);
        if (isset($properties['size_fr'])) unset($properties['size_fr']);

        $arr2 = array_values($properties);
        // 根据商品的尺码匹配规则获取尺码信息
        if ($product) {
            $rules = SizeMatchRule::getRules($product->product_sn, 'DW');
            $arr2 = [];
            if ($rules) {
                foreach ($rules as $rule) {
                    $arr2[] = $sku['properties'][$rule];
                }
            } else {
                $arr2 = array_values($sku['properties']);
            }
        }
        // 欧码匹配
        if ((!array_diff($arr, $arr2)) && (!array_diff($arr2, $arr))) return true;

        $arr3 = [];
        if ($product) {
            $arr3 = $this->sizeFr($arr, $product->product_sn);
        }
        if (!$arr3) return false;

        //法码匹配
        if ((!array_diff($arr3, $arr2)) && (!array_diff($arr2, $arr3))) return true;
        return false;
    }

    /**
     * 如下场景可匹配成功
     * - 8.5 匹配 38⅔
     * - 38⅔ 匹配 38.5
     * - 38.5-D宽 匹配  ["规格"=> "D宽","尺码"=> "36"]
     * - 38.5 匹配  ["规格"=> "D宽","尺码"=> "36"]
     * - 38.5 匹配 ["颜色"=> "白黑红", "尺码"=> "38.5"]
     * - 38.5-白黑红 匹配 ["颜色"=> "白黑红", "尺码"=> "38.5"]
     */
    public function stockMatchSku($sku, $properties, $product = null)
    {
        // properties：[["valuecn" => '36-D宽']]
        // $sku['properties']： ["规格"=> "D宽","尺码"=> "36"]

        $properties = $properties[0]['valuecn'];
        $arr2 = array_values($sku['properties']);
        // 根据商品的尺码匹配规则获取尺码信息
        if ($product) {
            $rules = SizeMatchRule::getRules($product->product_sn, 'DW');
            $arr2 = [];
            if ($rules) {
                foreach ($rules as $rule) {
                    $arr2[] = $sku['properties'][$rule];
                }
            } else {
                $arr2 = array_values($sku['properties']);
            }
        }

        $attrs = explode('-', $properties);
        $attrs = array_map('trim',$attrs);
        if (!array_diff($attrs, $arr2)) {
            return true;
        }
        return false;
    }

    /**
     * 获取出价详情
     *
     * @param string $bidding_no
     */
    public function getBiddingDetail($bidding_no)
    {
        $method2 = '3,2,apiUrl';
        $requestArr2['bidding_no'] = $bidding_no;
        $res =  (new DwApi($requestArr2))->uniformRequest($method2, $requestArr2);
        $bidding  = json_decode($res, true);
        if ($bidding['code'] && $bidding['code'] == 200) {
            //pirce spu_id qty bidding_time campus_list bidding_no article_number sku_id bidding_type qty_sold qty_remain
            //status 1:上架,10:下架 （ 永久下架 ）,11:保证金不足下架 （ 临时下架, 可恢复上架 ）,20:售空
            return $bidding['data'];
        }
        return [];
    }

    // 同步出价状态
    function syncBiddingInfo($bidding)
    {
        $detail = $this->getBiddingDetail($bidding->bidding_no);
        if ($detail) {
            $maps = [
                '1' => 1, //上架
                '10' => 2, //下架
                '11' => 1, //保证金下架
                '20' => 1 //售空
            ];
            $status = $maps[$detail['status']] ?? '';
            if ($status && $bidding->status != $status) {
                $bidding->update([
                    'status' => $status,
                    'qty_sold' => $detail['qty_sold'] ?? 0,
                    'qty_remain' => $detail['qty_remain'] ?? 0,
                ]);
            }
        }
        return true;
    }

    /**
     * 商家确认发货
     *
     * @param ChannelOrder $order
     */
    public function businessConfirm($order)
    {
        //获取虚拟物流单号
        if (!$order->dispatch_num) {
            $dispatch_num = $this->getDeliverNo($order->order_no);
            $order->dispatch_num = $dispatch_num;
            $order->save();
        }

        //上传三方并确认发货
        $this->uploadOrderDeliver($order->order_no, $order->dispatch_num);
        $order->status = ChannelOrder::STATUS_CONFIRM;
        $order->business_confirm_time = time();
        $order->save();
        return $order;
    }
    /**
     * 平台确认发货
     *
     * @param ChannelOrder $order
     */
    public function platformConfirm($order)
    {
        $data = $this->getOrderDetail($order->order_no);
        //如果三方订单是已发货状态
        if (!in_array($data['order_status'], [ChannelOrder::STATUS_DELIVER, ChannelOrder::STATUS_COMPLETE])) {
            throw new Exception('非待发货状态，请先确认三方订单状态。');
        }
        $order->status = ChannelOrder::STATUS_DELIVER;
        $order->platform_confirm_time = time();
        $order->save();
        return $order;
    }

    //跨境-取消出价
    public function biddingCancel($bidding)
    {
        if (in_array($bidding->status, [ChannelBidding::BID_CANCEL, ChannelBidding::BID_FAIL])) return true;
        $detail = $this->getBiddingDetail($bidding->bidding_no);
        if (!$detail) {
            Robot::sendNotice(sprintf('出价详情获取失败，出价单号：%s ', $bidding->bidding_no));
            return false;
        }

        if (!in_array($detail['status'], [1, 10, 11, 20])) {
            Robot::sendNotice('DW取消出价时的出价单状态异常：' . Json::encode($detail));
            return false;
        }

        if (in_array($detail['status'], [1, 11, 20])) { //在售，调接口取消出价
            $this->bidCancel($bidding)['data'];
            $detail = $this->getBiddingDetail($bidding->bidding_no);
        }
        $qty = $detail['qty'];
        $qty_sold = $detail['qty_sold'];
        $qty_cancel = $detail['qty_remain'];

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
        $lowest_price = FreeTaxLogic::getLowestPrice($params['sku_id'], $params['use_lock'] ?? true)['lower_price3'];
        $lowest_price_jpy = $this->RMB2Jpy($lowest_price);
        $data =  compact('lowest_price', 'lowest_price_jpy');

        //更新渠道最低价
        $this->updateLowestPrice(['channel_code' => self::$code, 'spu_id' => $params['spu_id'], 'sku_id' => $params['sku_id'], 'price' => $data]);
        return $data;
    }

    //得物不支持订单取消
    public function orderCancel($order)
    {
        return [
            'is_success' => false,
            'msg' => '得物不支持订单取消',
        ];
    }

    // 商品下架
    public function productTakedown($params)
    {
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
