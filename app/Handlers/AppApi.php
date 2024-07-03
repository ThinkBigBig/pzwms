<?php

namespace App\Handlers;

use App\Logics\BaseLogic;
use App\Logics\RedisKey;
use App\Logics\Robot;
use App\Models\AppBidding;
use App\Models\AppOrder;
use App\Models\AppProduct;
use App\Models\AppProductSku;
use Exception;
use Illuminate\Support\Facades\Redis;

class AppApi
{
    private $username = ''; //用户名
    private $password = ''; //密码
    private $address_id = ''; //回寄地址id
    private $ship_day = 5;
    private $type = 0; //0-现货 1-预售
    private $channel_id = 0; //发货渠道id
    private $shop_id = 0; //售后到店id

    function __construct($params = [])
    {
        if($params){
            if (isset($params['business_type'])) $this->type = $params['business_type'];
            $this->username = $params['username'];
            $this->password = $params['user_token'];
            $this->address_id = $params['address_id'];
            $this->ship_day = $params['ship_day'];
            $this->channel_id = $params['channel_id'];
            $this->shop_id = $params['shop_id'];
        }
    }

    /**
     * 根据货号初始化商品信息
     *
     * @param string $product_sn
     * @return array
     */
    function productInit($product_sn)
    {
        $find = AppProduct::where(['productSn' => $product_sn])->first();
        if ($find) {
            return $find;
        }
        $info = $this->syncLowestPrice($product_sn);
        $productId = $info['id'] ?? 0;
        if ($productId) {
            $product =  $this->product($productId);
            if ($product) {
                $product->update(['eligibility' => $info['eligibility']]);
            }
        }
        return [];
    }

    /**
     * 同步商品最低价
     *
     * @param string $product_sn
     * @return array
     */
    function syncLowestPrice($product_sn)
    {
        $url = '/carryme-product/product/' . $product_sn . '/price/info';
        $res = $this->_requestCarryme($url, 'get', []);
        if ($res['data'] ?? []) {

            $productId = $res['data']['id'] ?? 0;
            $skuPrices = $res['data']['skuPrices'] ?? [];
            if ($productId && $skuPrices) {
                foreach ($skuPrices as $price) {
                    $where = ['productId' => $productId, 'standardId' => $price['id']];
                    $update = [
                        'spData' => json_decode($price['spData'], true),
                        'stockPrice' => $price['stockPrice'] ?: 0,
                        'lightPrice' => $price['lightPrice'] ?: 0,
                        'presalePrice' => $price['presalePrice'] ?: 0,
                    ];
                    AppProductSku::updateOrCreate($where, $update);
                }
            }
        }
        return $res['data'] ?? [];
    }

    /**
     * 根据商品id获取商品信息
     *
     * @param int $product_id
     */
    function product($product_id)
    {
        $res = AppProduct::where(['productId' => $product_id])->first();
        if (!$res) {
            $url = '/carryme-product/product/' . $product_id;
            $res = $this->_requestCarryme($url, 'get', []);
            $product = $res['data']['product'] ?? [];
            AppProduct::create([
                'productId' => $product['id'],
                'productSn' => $product['productSn'],
                'pic' => $product['pic'],
                'nameCn' => $product['nameCn'],
                'name' => $product['name'],
                'brandId' => $product['brandId'],
                'brandName' => $product['brandName'],
            ]);
            $res = AppProduct::where(['productId' => $product_id])->first();
        }
        return $res ?: [];
    }


    /**
     * 开始出价
     *
     * @param int $price
     * @param int $qty
     * @param int $productId
     * @param int $standardId
     * @param string $thirdPartyId
     */
    function bid($price, $qty, $productId, $standardId, $thirdPartyId)
    {
        $data = [
            'earnestMoney' => 0, //保证金
            'price' => $price,
            'productId' => $productId,
            'quantity' => $qty,
            'standardId' => $standardId,
            'type' => $this->type, //0-现货 1-预售
            'isThirdParty' => true,
            'thirdPartyId' => $thirdPartyId,
            'shipDay' => $this->ship_day,
        ];
        // $url = '/sell/addSell?' . http_build_query($data);
        // $res = $this->_requestApp($url, 'post', []);
        $res = $this->_requestApp('/carryme-order/oms-sell-log-entity/addSell?'.http_build_query($data), 'post', []);
        
        $code = $res['code'] ?? 0;
        if ($code == 200 && ($res['data'] ?? 0)) {
            $data['logId'] = $res['data'];
            return $data;
        }
        if (in_array($code, [0, 500, 200])) {
            sleep(5);
            // 查出价详情接口并同步出价信息
            $res = $this->bidDetail($thirdPartyId);
            if ($res['sellBidId'] ?? 0) {
                $data['logId'] = $res['sellBidId'];
                return $data;
            }
        }
        throw new Exception(sprintf('出价失败，%s %s', $res['code'] ?? '', $res['message'] ?? ''));
    }

    /**
     * 出价详情
     *
     * @param string $thirdPartyId
     */
    function bidDetail($thirdPartyId)
    {
        $url = '/carryme-order/sell/log/third/party/' . $thirdPartyId . '/thirdParty';
        $res = $this->_requestCarryme($url, 'get', []);
        if ($res['data'] ?? []) {
            AppBidding::updateOrCreate(['bidding_no' => $thirdPartyId], [
                'logId' => $res['data']['sellBidId'],
                'memberId' => $res['data']['memberId'],
                'status' => $res['data']['status'],
                'price' => $res['data']['price'],
                'quantity' => $res['data']['quantity'],
            ]);
        }
        return $res['data'] ?? [];
    }

    /**
     * 出价取消
     *
     * @param int logId 出价ID
     * @param int qty 取消数量
     */
    function bidCancel($logId, $qty)
    {
        $query = [
            'logId' => $logId,
            'quantity' => $qty,
            'type' => 1,
        ];
        // $url = '/sell/modifySellLog?' . http_build_query($query);
        // $res = $this->_requestApp($url, 'get', []);
        $url = '/carryme-order/oms-sell-log-entity/cancel/' . $logId;
        $res = $this->_requestApp($url, 'post', []);
        return $res;
    }

    /**
     * 订单发货
     *
     * @param array $orderInfos
     */
    function orderDeliver($orderInfos)
    {
        $data = [
            'orderInfos' => $orderInfos,
            'addressId' => $this->address_id,
            'deliveryChannelId' => $this->channel_id,
            'shopId' => $this->shop_id,
            'memberld' => env('CARRYME_APP_MEMBER_ID'),
        ];
        $url = '/carryme-order/delivery/sellerDelivery4Php';
        $res = $this->_requestCarryme($url, 'post', $data);
        if (!($res['code'] ?? '')) {
            throw new Exception('接口调用失败');
        }
        return $res;
    }

    /**
     * 根据订单ID查订单状态
     *
     * @param array $order_ids
     */
    function orderSearch($order_ids)
    {
        $url = '/carryme-order/oms/order/erp/search';
        $res = $this->_requestCarryme($url, 'post', $order_ids);
        if ($res && $res['code'] == 200) {
            foreach ($res['data'] as $item) {
                AppOrder::where(['orderId' => $item['id']])->update(['status' => $item['status'], 'sellDeliverySn' => $item['sellDeliverySn']]);
            }
        }
        return $res['data'] ?? [];
    }

    private function _requestCarryme($url, $method, $params)
    {
        $appid = env('CARRYME_APPID');
        $appSecret = env('CARRYME_APPSECRET');
        $timestamp = time();
        $sign = md5('appId=' . $appid . '&timestamp=' . $timestamp . '&key=' . $appSecret);

        $header = [
            'appId:' . $appid,
            'sign:' . $sign,
            'timestamp:' . $timestamp,
            'Content-Type:application/json',
        ];
        $res = '';
        if ($method == 'get') {
            $res =  HttpService::request('GET', env('CARRYME_HOST') . $url, ['header' => $header]);
        }
        if ($method == 'post') {
            $res = HttpService::post(env('CARRYME_HOST') . $url, $params, $header);
        }
        $res = $res ? json_decode($res, true) : [];
        BaseLogic::requestLog('【CARRYME】', ['url' => $url, 'data' => $res]);
        Robot::sendApiRes($res, $url, $params);
        return $res;
    }

    private function _requestCarrymeToken($url, $method, $data)
    {
        $token = $this->getToken();
        $header = ['Authorization:Bearer ' . $token,];
        $res = '';
        $url = env('CARRYME_HOST') . $url;
        $options = ['header' => $header,];
        if ($method == 'post') {
            $options['data'] = $data;
            $options['header'][] = 'Content-Type:application/json';
            $res = HttpService::request('post', $url, $options);
        }
        if ($method == 'get') {
            $res = HttpService::get($url, [], $options);
        }
        $res = $res ? json_decode($res, true) : [];
        BaseLogic::requestLog('【CARRYME】', ['url' => $url, 'options' => $options, 'data' => $res]);
        Robot::sendApiRes($res, $url, $data);
        return $res;
    }

    private function _requestApp($url, $method, $data)
    {
        $token = $this->getToken();
        $header = ['Authorization:Bearer ' . $token,];
        $res = '';
        // $url = env('CARRYME_APP_HOST') . $url;
        $url = env('CARRYME_HOST') . $url;
        $options = ['header' => $header,];
        if ($method == 'post') {
            $options['data'] = $data;
            $res = HttpService::request('post', $url, $options);
        }
        if ($method == 'get') {
            $res = HttpService::get($url, [], $options);
        }
        $res = $res ? json_decode($res, true) : [];
        BaseLogic::requestLog('【CARRYME】', ['url' => $url, 'data' => $res]);
        Robot::sendApiRes($res, $url, $data);
        return $res;
    }

    private function getToken()
    {
        // BaseLogic::log('出价账户2',['username'=>$this->username]);
        $key = RedisKey::APP_TOKEN . ":" . $this->username;
        $token = Redis::get($key);
        if ($token) return $token;

        $url = '/api/v3/userlogin';
        $res = HttpService::request('post-only', env('CARRYME_APP_HOST2') . $url, [
            'data' => [
                'username' => $this->username,
                'password' => $this->password,
            ]
        ]);
        $res = $res ? json_decode($res, true) : [];
        $token = $res['token'] ?? '';
        if ($token) {
            Redis::setex($key, 20 * 24 * 3600, $token);
        }
        return $token;
    }
}
