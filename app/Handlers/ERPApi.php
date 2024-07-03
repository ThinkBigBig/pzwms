<?php

namespace App\Handlers;

use Psy\Util\Json;

class ERPApi
{

    // 商品
    function products($params)
    {
        $data['method'] = 'sync.products';
        $data['item'] = json_encode(['items' => $params]);
        return $this->request('post', '/up/sync/product',  $data);
    }

    // 库存
    function stock($params,$ownerCode)
    {
        $data['method'] = 'sync.stock';
        $data['item'] = json_encode([
            'remark' => json_encode($params),
            'ownerCode' => $ownerCode,
        ]);
        return $this->request('post', '/up/sync/productStock',  $data);
    }

    // 库存明细
    function stockDetail($params,$ownerCode)
    {
        $data['method'] = 'sync.stockDetail';
        $data['item'] = json_encode([
            'remark' => json_encode($params),
            'ownerCode' => $ownerCode,
        ]);

        return $this->request('post', '/up/sync/productStockDetailed',  $data);
    }

    function request($method, $url, $params = [])
    {
        $host = env('ERP_HOST');
        $this->sign($params);
        switch ($method) {
            case 'get':
                $res = HttpService::get($host . $url, $params, []);
                break;
            case 'post':
                $res = HttpService::post($host . $url, $params, ["content-type: application/json"]);
                break;
        }

        return $res ? json_decode($res, true) : [];
    }

    /**
     * 签名
     *
     * @param $data
     */
    protected function sign(&$data)
    {
        $data['source'] = 'wms';
        $data['timestamp'] = time();
        $secret_key = env('ERP_SECRET');

        ksort($data);

        $arr = [];
        foreach ($data as $k => $v) {
            if ("sign" !== $k) {
                $v = is_array($v) ? Json::encode($v) : $v;
                $arr[] = $k . '=' . $v;
            }
        }

        $str = implode('&', $arr);
        $data['sign'] = md5($str . $secret_key);
    }
}
