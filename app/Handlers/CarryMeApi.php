<?php

namespace App\Handlers;

use App\Logics\Robot;
use Illuminate\Support\Facades\Log;
use Psy\Util\Json;

class CarryMeApi
{
    /**
     * 调接口通知carry me 下单成功
     *
     */
    public function paySuccess($params)
    {

        // $url = env('CARRYME_HOST', '') . '/carryme-order/oms/order/';
        // $data = HttpService::post($url, $params, ["content-type: application/json"]);
        // $data = $data ? json_decode($data, true) : [];
        $data = $this->request('/carryme-order/oms/order/', 'post', $params);
        return $data;
    }

    /**
     * 验证出价信息是否还有效
     *
     * @param array $params
     */
    public function bidVerify($params)
    {
        $url = '/carryme-product/third/party/bid/product/record/' . $params['carryme_bidding_id'] . '/check';
        $data = $this->request($url, 'get', []);
        if (!$data || $data['code'] != 200) return true;
        return $data['data'];
    }

    /**
     * 订单取消
     *
     * @param array $params
     */
    public function orderCancel($params)
    {
        $url = '/carryme-product/third/party/order/' . $params['order_no'] . '/cancel/third/party';
        $data = $this->request($url, 'get', []);
        return $data;
    }

    /**
     * 出价成功回调
     *
     * @param array $params
     */
    public function bidCallback($params)
    {
        $url = '/carryme-product/third/party/bid/product/record/callback/third/party';
        $res = $this->request($url, 'post', $params);
        return $res;
    }

    /**
     * 取消出价回调
     *
     * @param array $params
     */
    public function bidCancelCallback($params)
    {
        $url = '/carryme-product/third/party/bid/product/record/callback/third/party';
        $res = $this->request($url, 'delete', $params);
        return $res;
    }

    public function syncLowestPrice($params)
    {
        $url = '/carryme-product/third/party/bid/product/record/relation/refresh/low';
        $res = $this->request($url, 'get', $params);
        return $res;
    }

    /**
     * 更新虚拟物流单号
     *
     * @param array $params
     */
    public function dispatchNum($params)
    {
        $url = '/carryme-product/third/party/order/' . $params['order_no'] . '/dispatchNum';
        $data = [
            'string' => $params['dispatch_num']
        ];
        return $this->request($url, 'post', $data);
    }

    private function request($url, $method, $params)
    {
        $url = env('CARRYME_HOST', '') . $url;
        $res = '';
        switch ($method) {
            case 'get':
                $res = HttpService::get($url, $params, ["content-type: application/x-www-form-urlencoded"]);
                break;
            case 'post':
                $res = HttpService::post($url, $params, ["content-type: application/json"]);
                break;
            case 'delete':
                $options = [
                    'header' => ["content-type: application/json"],
                    "data" => $params,
                ];
                $res = HttpService::request('delete', $url, $options);
                break;
        }

        $res = $res ? json_decode($res, true) : [];
        if (($res['code'] ?? "") != 200) {
            $msg = sprintf('同步失败：%s 参数：%s 返回结果:%s', $url, Json::encode($params), Json::encode($res));
            Robot::sendText(Robot::FAIL_MSG, $msg);
        }
        // Robot::sendApiRes($res,$url,$params);
        $params['channel_code'] = 'CarryMe';
        ESApiService::addRequestLog($url,$params,$res);
        return $res;
    }
}
