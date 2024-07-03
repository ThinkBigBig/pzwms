<?php

namespace App\Logics;

use App\Handlers\HttpService;
use Illuminate\Support\Facades\Log;
use Psy\Util\Json;

class Robot
{
    const FAIL_MSG = 1;
    const API_MSG = 2;
    const NOTICE_MSG = 3;
    const NOTICE2 = 4; //群2通知
    const EXCEPTION2 = 5; //群2异常

    static function sendText($type, $msg)
    {
        $robots = [
            self::FAIL_MSG => 'https://open.larksuite.com/open-apis/bot/v2/hook/0e9a44e8-5767-4d57-80fe-54290127b56b',
            self::API_MSG => 'https://open.larksuite.com/open-apis/bot/v2/hook/14689929-2416-4d5d-84d3-44a40f0a57b7',
            self::NOTICE_MSG => 'https://open.larksuite.com/open-apis/bot/v2/hook/e5ed6694-787f-44d3-adfa-99d9a1afddb8',
            self::NOTICE2 => env('ORDER_NOTICE'),
            self::EXCEPTION2 => env('ORDER_EXCEPTION'),
        ];

        $environment = env('ENV_NAME', 'prod');
        if (!in_array($environment, ['prod', 'uat'])) return;

        $msg = "【" . $environment . "】" . $msg;
        $url = $robots[$type];
        HttpService::post($url, [
            'msg_type' => 'text',
            'content' => [
                'text' => $msg
            ]
        ], ["content-type: application/json"]);
    }

    static function sendNotice($msg)
    {
        // Log::channel('daily2')->info('bid', [$msg]);
        self::sendText(self::NOTICE_MSG, '通知：' . $msg);
    }

    static function sendFail($msg)
    {
        // Log::channel('daily2')->info('bid', [$msg]);
        self::sendText(self::FAIL_MSG, '操作失败：' . $msg);
    }

    static function sendException($msg)
    {
        // Log::channel('daily2')->info('bid', [$msg]);
        self::sendText(self::FAIL_MSG, '异常：' . $msg);
    }

    static function sendApiRes($res, $url, $params)
    {
        if ($params['header'] ?? []) {
            unset($params['header']);
        }
        if (is_array($res)) $res = Json::encode($res);
        $msg = sprintf('url：%s，参数：%s，响应结果：%s', $url, Json::encode($params), $res);
        self::sendText(self::API_MSG, $msg);
    }

    static function sendNotice2($msg)
    {
        // Log::channel('daily2')->info('order', [$msg]);
        self::sendText(self::NOTICE2, $msg);
    }
}
