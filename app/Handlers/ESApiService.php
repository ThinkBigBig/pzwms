<?php


namespace App\Handlers;

use Exception;

/**
 * php提供的es服务
 */
class ESApiService
{
    static function addRequestLog($url, $request, $response)
    {
        try {

            $data = [
                'url' => $url,
                'time' => date('Y-m-d H:i:s'),
                'request' => $request,
                'response' => $response
            ];
            $url = '/api/tools/request-log';
            // self::request($url, 'post', [
            //     'index' => 'erp-logs-' . date('Ym'),
            //     'body' => json_encode($data)
            // ]);
        } catch (Exception $e) {
            throw $e;
        }
    }


    static function request($url, $method, $params)
    {
        $host = env('ESAPI_HOST', '');
        $res = HttpService::request('post', $host . $url, [
            'form_data' => $params
        ]);
        $res = $res ? json_decode($res, true) : [];
        return $res;
    }

    // ES商品搜索
    static function serach($params)
    {
        $host = env('ESAPI_HOST', '');
        $res = HttpService::request('get', $host . '/v3/search', [
            'query' => $params
        ]);
        $res = $res ? json_decode($res, true) : [];
        return $res;
    }
}
