<?php

/**
 * Stockx 基础类
 */

namespace App\Handlers;

use App\Logics\BaseLogic;
use App\Logics\mock\stockxMock;
use App\Logics\RedisKey;
use App\Logics\Robot;
use Illuminate\Support\Facades\Redis;
use Psy\Util\Json;

class StockxApi
{
    // 获取商品信息
    public function searchCatalog($params)
    {
        $url = '/v2/catalog/search';
        $params = [
            'query' => $params['query'],
            'pageNumber' => 1,
            'pageSize' => 10,
        ];
        return $this->_request($url, 'get', $params);
    }

    // 获取一个商品下的所有sku
    public function productVariants($product_id)
    {
        $url = sprintf('/v2/catalog/products/%s/variants', $product_id);
        return $this->_request($url, 'get');
    }

    // 获取最低价
    public function marketData($product_id, $variant_id)
    {
        $url = sprintf('/v2/catalog/products/%s/variants/%s/market-data', $product_id, $variant_id);
        $params = [
            'currencyCode' => 'JPY',
        ];
        $mock = stockxMock::marketData();
        if ($mock) {
            $currency_code = $mock['currencyCode'] ?? '';
            if ($currency_code == 'USD') {
                $mock['earnMoreAmount'] = bcmul($mock['earnMoreAmount'] ?? 0, 100, 0);
                $mock['sellFasterAmount'] = bcmul($mock['sellFasterAmount'], 100, 0);
            }
            return $mock;
        }

        $res = $this->_request($url, 'get', $params);
        $currency_code = $res['currencyCode'] ?? '';
        if ($currency_code == 'USD') {
            $res['earnMoreAmount'] = bcmul($res['earnMoreAmount'], 100, 0);
            $res['sellFasterAmount'] = bcmul($res['sellFasterAmount'], 100, 0);
        }
        return $res;
    }

    // 出价
    public function createList($params)
    {
        // 最低价不能小于500日元
        if ($params['price'] < 500) {
            Robot::sendException('出价金额过低，程序异常!!!!');
            return [];
        }

        $url = '/v2/selling/listings';
        $params = [
            'amount' => (string)$params['price'],
            'variantId' => $params['variantId'],
            'currencyCode' => 'JPY',
            // 'expiresAt' => "2021-11-09T12:44:31.000Z",//出价过期时间
            // 'active' => true,
        ];

        $mock = stockxMock::createList($params);
        if ($mock) return $mock;

        return $this->_request($url, 'post', $params);
    }

    // 删除出价
    public function deleteList($listing_id)
    {
        $url = sprintf('/v2/selling/listings/%s', $listing_id);

        $mock = stockxMock::deleteList($listing_id);
        if ($mock) return $mock;

        return $this->_request($url, 'delete');
    }

    // 批量删除出价
    public function batchListDelete($lists)
    {
        if (!$lists) return [];
        $url = '/v2/selling/batch/delete-listing';
        $items = [];
        foreach ($lists as $list) {
            $items[] = ['listingId' => $list];
        }

        $mock = stockxMock::apiMock($url, 'post', ['items' => $items]);
        if ($mock) return $mock;

        return $this->_request($url, 'post', ['items' => $items]);
    }

    // 获取所有出价单信息
    public function allList($params)
    {
        $url = '/v2/selling/listings';
        $params = [
            'pageNumber' => $params['page'] ?? 1,
            'pageSize' => $params['size'] ?? 100,
        ];
        if ($params['productIds'] ?? []) {
            $params['productIds'] = implode(',', $params['productIds']);
        }
        if ($params['variantIds'] ?? []) {
            $params['variantIds'] = implode(',', $params['variantIds']);
        }
        if ($params['batchIds'] ?? []) {
            $params['batchIds'] = implode(',', $params['batchIds']);
        }

        if ($params['fromDate'] ?? '') {
            $params['fromDate'] = $params['fromDate']; // 2022-06-08
        }
        if ($params['toDate'] ?? '') {
            $params['toDate'] = $params['toDate']; // 2022-06-08
        }
        // 出价状态 "INACTIVE", "ACTIVE", "DELETED", "CANCELED", "MATCHED", "COMPLETED"
        if ($params['listingStatuses'] ?? []) {
            $params['listingStatuses'] = implode(',', $params['listingStatuses']);
        }
        if ($params['inventoryTypes'] ?? []) {
            $params['inventoryTypes'] = implode(',', $params['inventoryTypes']);
        }

        $mock = stockxMock::apiMock($url, 'get', $params);
        if ($mock) return $mock;

        $res = $this->_request($url, 'get', $params);
        return $res;
    }

    // 获取出价单详情
    public function getList($listing_id)
    {
        $mock = stockxMock::getList($listing_id);
        if ($mock) return $mock;

        $url = sprintf('/v2/selling/listings/%s', $listing_id);
        return $this->_request($url, 'get');
    }

    // 获取有效订单列表
    public function activeOrder($params)
    {
        $url = '/v2/selling/orders/active';
        $request = [
            'pageNumber' => $params['pageNumber'] ?? 1,
            'pageSize' => $params['pageSize'] ?? 10,
        ];
        if ($params['orderStatus'] ?? '') {
            $request['orderStatus'] = $params['orderStatus'];
        }
        if ($params['productId'] ?? '') {
            $request['productId'] = $params['productId'];
        }
        if ($params['variantId'] ?? '') {
            $request['variantId'] = $params['variantId'];
        }
        return $this->_request($url, 'get', $request);
    }

    // 获取历史订单列表
    public function historyOrder($params)
    {
        $url = '/v2/selling/orders/history';
        $request = [
            'pageNumber' => $params['pageNumber'] ?? 1,
            'pageSize' => $params['pageSize'] ?? 10,
        ];
        if ($params['fromDate'] ?? '') {
            $request['fromDate'] = $params['fromDate'];
        }
        if ($params['toDate'] ?? '') {
            $request['toDate'] = $params['toDate'];
        }
        if ($params['orderStatus'] ?? '') {
            $request['orderStatus'] = $params['orderStatus'];
        }
        if ($params['productId'] ?? '') {
            $request['productId'] = $params['productId'];
        }
        if ($params['variantId'] ?? '') {
            $request['variantId'] = $params['variantId'];
        }
        return $this->_request($url, 'get', $request);
    }

    // 获取订单详情
    public function orderDetail($order_no)
    {
        $url = sprintf('/v2/selling/orders/%s', $order_no);
        $mock = stockxMock::orderDetail($order_no);
        if ($mock) return $mock;

        return $this->_request($url, 'get');
    }

    // 获取发货单数据
    public function shipment($url)
    {
        $url = str_replace(env('STOCKX_API_URL'), '', $url);
        return $this->_request($url, 'get', [], false);
    }


    private function _request($url, $method, $data = [], $decode = true)
    {
        $header = [
            'x-api-key:' . env('STOCKX_API_KEY'),
            'Authorization: Bearer ' . $this->getToken()
        ];

        if ($method == 'get') {
            $request_data = ['query' => $data, 'header' => $header];
        }

        if ($method == 'post') {
            $header[] = 'Content-Type: application/json';
            $request_data = ['data' => $data, 'header' => $header];
        }

        if ($method == 'delete') {
            $request_data = ['data' => $data, 'header' => $header];
        }

        $res = HttpService::request($method, env('STOCKX_API_URL') . $url, $request_data, true);
        BaseLogic::requestLog('【STOCKX】', ['url' => $url, 'quest' => $request_data, 'res' => $res]);
        if (!$decode) {
            return $res;
        }

        $res = $res ? json_decode($res, true) : [];
        if($res && isset($res['statusCode'])){
            Robot::sendApiRes($res, env('STOCKX_API_URL') . $url, $request_data);
        }

        // 登录失效，刷新token
        if (($res['statusCode'] ?? '') == 401) {
            $this->refreshToken();
            return [];
        }
        // 触发频率限制
        if (($res['statusCode'] ?? '') == 429) {
            Robot::sendNotice('stockx 触发频率限制');
            return [];
        }
        return $res;
    }

    public function getToken()
    {
        $res = Redis::get(RedisKey::STOCKX_TOKEN);
        if (!$res) {
            $res = $this->refreshToken();
            return $res['access_token'] ?? '';
        }
        $res = json_decode($res, true);
        return $res['access_token'] ?? '';
    }

    private $token_host = 'https://accounts.stockx.com/oauth/token';

    // 刷新token
    private function refreshToken()
    {
        $data = [
            'grant_type' => 'refresh_token',
            'client_id' => env('STOCKX_CLIENT_ID'),
            'client_secret' => env('STOCKX_CLIENT_SECRET'),
            'audience' => 'gateway.stockx.com',
            'refresh_token' => Redis::get(RedisKey::STOCKX_REFRESH_TOKEN)
        ];
        $res = HttpService::request('post-only', $this->token_host, ['data' => http_build_query($data), 'header' => ["application/x-www-form-urlencoded"]], true);
        $res = $res ? json_decode($res, true) : [];
        if ($res['token_type'] ?? '') {
            Redis::setex(RedisKey::STOCKX_TOKEN, $res['expires_in'] - 3600, Json::encode($res));
            return $res;
        }
        Robot::sendFail('stockx刷新缓存失败');
        return [];
    }

    // 初始化token
    public function initToken()
    {
        $data = [
            'grant_type' => 'authorization_code',
            'client_id' => env('STOCKX_CLIENT_ID'),
            'client_secret' => env('STOCKX_CLIENT_SECRET'),
            'code' => Redis::get(RedisKey::STOCKX_AUTHORIZATION_CODE),
            'redirect_uri' => env('STOCKX_CALLBACK_URL')
        ];
        $res = HttpService::request('post-only', $this->token_host, ['data' => http_build_query($data), 'header' => ["application/x-www-form-urlencoded"]]);
        $res = $res ? json_decode($res, true) : [];
        if ($res) {
            Redis::set(RedisKey::STOCKX_REFRESH_TOKEN, $res['refresh_token']);
            Redis::setex(RedisKey::STOCKX_TOKEN, $res['expires_in'] - 3600, Json::encode($res));
        }
        return $res;
    }
}
