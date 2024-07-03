<?php

namespace App\Logics;

use App\Handlers\HttpService;
use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Redis;
use Psy\Util\Json;

class ExchangeLogic
{
    const CURRENCY_RMB = 'CNY'; //人民币
    const CURRENCY_USD = 'USD'; //美元

    public $currency;
    public $positive_exchange = 0; //日元转指定币种汇率
    public $reverse_exchange = 0; //指定币种转日元汇率

    public function __construct($currency)
    {
        $this->currency = $currency;
    }

    public function getRedisKey()
    {
        return sprintf('erp:exchange:JPY2%s', $this->currency);
    }

    //刷新汇率
    public function exchange()
    {
        if (Redis::get(RedisKey::EXCHNAGE_LOCK)) {
            return [];
        }

        $res = HttpService::get('https://op.juhe.cn/onebox/exchange/currency', [
            'key' => env('JUHE_KEY'),
            'from' => 'JPY',
            'to' => $this->currency
        ]);
        // $res = '{"reason":"查询成功!","result":[{"currencyF":"JPY","currencyF_Name":"日元","currencyT":"CNY","currencyT_Name":"人民币","currencyFD":"1","exchange":"0.05230700","result":"0.05230700","updateTime":"2023-03-29 11:42:34"},{"currencyF":"CNY","currencyF_Name":"人民币","currencyT":"JPY","currencyT_Name":"日元","currencyFD":"1","exchange":"19.11810000","result":"19.11810000","updateTime":"2023-03-29 11:42:34"}],"error_code":0}';
        $res = $res ? json_decode($res, true) : [];
        if ($res['result'] ?? '') {
            $data = [];
            foreach ($res['result'] as $item) {
                $key = $item['currencyF'] . '2' . $item['currencyT'];
                $item['getTime'] = date('Y-m-d H:i:s');
                $data[$key] = $item;
            }
            Redis::set($this->getRedisKey(), Json::encode($data));
            return $data;
        } else {
            $msg = sprintf('汇率信息获取失败，%s', json_encode($res));
            Robot::sendText(Robot::FAIL_MSG, $msg);
            // 频率超限，1小时候刷新
            if ($res['error_code'] == 10012) {
                Redis::setex(RedisKey::EXCHNAGE_LOCK, 3600, 1);
            }
        }
        return [];
    }

    //获取汇率
    public function getExchange()
    {
        $data = Redis::get($this->getRedisKey());
        $data = $data ? json_decode($data, true) : [];
        $key = 'JPY2' . $this->currency;
        $key2 = $this->currency . '2JPY';

        $update = (env('ENV_NAME', 'prod') == 'prod') && $data && ($data[$key]['getTime'] ?? '') < date('Y-m-d H:i:s', time() - env('EXCHANGE_EXPIRE', 7200));
        if (!$data || $update) {
            $exchange = $this->exchange();
            if ($exchange) $data = $exchange;

            Robot::sendNotice(sprintf('%s %s汇率更新：%s %s', date('Y-m-d H:i:s'), $this->currency, $data[$key]['exchange'], $data[$key2]['exchange']));

            if ($this->currency != 'JPY') {
                ExchangeRate::create([
                    $key => $data[$key]['exchange'],
                    $key2 => $data[$key2]['exchange']
                ]);
            }
        }
        $this->positive_exchange = $data[$key]['exchange'];
        $this->reverse_exchange = $data[$key2]['exchange'];
        return $data;
    }
}
