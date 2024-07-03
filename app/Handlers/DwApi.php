<?php

/**
 *得物api基础类
 */

namespace App\Handlers;

use App\Handlers\HttpService;

//curl 请求
use App\Logics\BaseLogic;
use App\Logics\Robot;
use App\Models\ErpLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Psy\Util\Json;

class DwApi
{
    protected $app_key = ''; //应用标识
    protected $appSecret = ''; //应用标识
    protected $access_token = ''; //请求令牌
    protected $timestamp = ''; //当前时间戳（毫秒）
    protected $sign = ''; //签名
    protected $host = ''; //请求路径
    protected $url = ''; //请求路径
    protected $config = []; //签名
    protected $quest_status = ''; //请求状态

    const TOKEN_KEY = 'DW_TOKEN';

    //析构函数
    public function __construct($paramArr = [], $conf = [])
    {
        $name = env('METHOD', '_name'); //取对应的参数
        if ($name == 'name' || ($conf['prod'] ?? false)) {
            $this->app_key = env('APP_KEY', ''); //应用标识
            $this->appSecret = env('APP_SECRET', ''); //应用标识
            $this->host = env('URL_NAME', 'https://openapi.dewu.com'); //取对应的参数
        } else {
            $this->app_key = env('_APP_KEY', ''); //应用标识
            $this->appSecret = env('_APP_SECRET', ''); //应用标识
            $this->host = env('_URL_NAME', 'https://openapi-sandbox.dewu.com'); //取对应的参数
        }
        $this->timestamp = getMillisecond(); //当前时间戳（毫秒）
        $paramArr['app_key'] = $this->app_key;
        $paramArr['access_token'] = self::access_token();
        $paramArr['timestamp'] = $this->timestamp;
        $this->sign = $this->createSign($paramArr); //获取签名
        $paramArr['sign'] = $this->sign; //签名
        $this->config = $paramArr; //得到最终的请求数据

        if ($conf) {
            $this->access_token = $conf['access_token'];
        }
    }

    /**
     * 生成签名
     * @param $paramArr
     * @return string
     */
    private function createSign($paramArr)
    {
        ksort($paramArr);
        foreach ($paramArr as $key => $val) { //过滤空数据项
            if (is_array($val)) {
                $row = '';
                foreach ($val as $k => $val_v) {
                    if (is_array($val_v)) {
                        $row .= json_encode($val_v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $row .= ',';
                    } else {
                        $row .= $val_v;
                        $row .= ',';
                    }
                }
                $row = rtrim($row, ',');
                $paramArr[$key] = $row;
            }
        }
        $sign_str = http_build_query($paramArr, '&');
        // $sign_str = transferArrayToSignString($paramArr);
        $sign_str .= $this->appSecret;
        return strtoupper(md5($sign_str));
    }

    /**
     * 统一处理 返回结果
     *
     * @return string
     */
    public function uniformRequest($method, $quest)
    {
        $this->dwApi($method); //获取对应的api
        $quest = $this->config;
        $data = [];
        if ($this->quest_status == 'get') $data = HttpService::get($this->url, $quest, ["content-type: application/json"]);
        if ($this->quest_status == 'post') $data = HttpService::post($this->url, $quest, ["content-type: application/json"]);

        BaseLogic::requestLog('【DW】', ['url' => $this->url, 'quest' => $quest, 'data' => $data]);
        // $msg = sprintf('url：%s，参数：%s，响应结果：%s', $this->url, Json::encode($quest), $data);
        // Robot::sendText(Robot::API_MSG, $msg);


        $arr = $data ? json_decode($data, true) : [];
        if ($arr && $arr['code'] == 5044) {
            Redis::del(self::TOKEN_KEY);
            Robot::sendText(Robot::API_MSG, '响应结果：已删除过期token，可重试操作');
        }

        if (($arr['code'] ?? '') != 200) {
            Robot::sendApiRes($data, $this->url, $quest);
        }
        return $data;
    }

    /**
     * api处理 设定对应请求方式和路径
     *
     * @param string $method
     * @return void
     */
    public function dwApi($method = '')
    {
        $methodArray = explode(',', $method);
        $api = self::dwApiMethod();
        // var_dump( $api);
        $apiArray = json_decode($api, true);
        // var_dump($apiArray[$methodArray[0]]);
        $urlArr = $apiArray[$methodArray[0]][$methodArray[1]][$methodArray[2]];
        // $name = env('METHOD', '_name');//取对应的参数
        // $urlName = env('URL_NAME', 'https://openapi.dewu.com');//取对应的参数
        // $_urlName = env('_URL_NAME', 'https://openapi-sandbox.dewu.com');//取对应的参数

        $this->url = $this->host . $urlArr;

        // if ($name == 'name') {
        //     $this->url = $urlName . $urlArr;
        // } else {
        //     $this->url = $_urlName . $urlArr;
        // }
        // var_dump($apiArray[$methodArray[0]][$methodArray[1]]['method']);exit;
        if (!empty($apiArray[$methodArray[0]][$methodArray[1]]['method'])) {
            $this->quest_status = 'get';
        } else {
            $this->quest_status = 'post';
        }
    }

    //接口维护
    protected static function dwApiMethod()
    {
        //得物接口维护
        $name = __DIR__ . '/DwApi.json';
        $data = file_get_contents($name);
        return $data;
    }


    //获取access_token
    protected static function access_token()
    {
        $redis = Redis::connection('redis_token');
        $token = $redis->get(self::TOKEN_KEY);
        // $token = Redis::get(self::TOKEN_KEY);
        if (empty($token)) {
            $quest = [
                'client_id' => env('APP_KEY'),
                'client_secret' => env('APP_SECRET'),
                'refresh_token' => env('REFRESH_TOKEN'),
            ];
            $URL = 'https://open.dewu.com/api/v1/h5/passport/v1/oauth2/refresh_token';
            $refresh = HttpService::post($URL, $quest, ["content-type: application/json"]);
            $refresh = json_decode($refresh, true);
            if (!empty($refresh['data']['access_token'])) {
                // Redis::set('DW_TOKEN',$refresh['data']['access_token'],60);
                // Redis::setex('DW_TOKEN', 82800, $refresh['data']['access_token']);
                $redis->setex(self::TOKEN_KEY, 82800, $refresh['data']['access_token']);
                $token = $refresh['data']['access_token'];
            } else {
                $msg = 'DW access_token：' . Json::encode($refresh);
                Robot::sendException($msg);
                return '';
            }
        }
        return $token;
    }

    /**
     * 生成签名 验签使用
     * @param $paramArr
     * @return string
     */
    public function createSigns($paramArr)
    {
        ksort($paramArr);
        foreach ($paramArr as $key => $val) { //过滤空数据项
            if (is_array($val)) {
                $paramArr[$key] = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        $sign_str = http_build_query($paramArr, '&');
        $sign_str .= $this->appSecret;
        // $sign_str .= 'ee23a278be374e688b682a0b3de044281fa8700ff0874dee947906727ba3aafd';
        // var_dump(strtoupper(md5($sign_str)));exit;
        return strtoupper(md5($sign_str));
    }

    public function callbackDecrypt($str)
    {
        if (BaseLogic::isTest())
            // $this->appSecret = env('APP_SECRET', '');
            $this->appSecret = '27882f7a6c154f03aef3925726402d24fc53b09ba22c49fdb844b14a2e58c261';
        return json_decode($this->decrypt($str), true);
    }

    /**
     * [decrypt aes解密]
     * @param    [type]                   $sStr [要解密的数据]
     * @param    [type]                   $sKey [加密key]
     * @return   [type]                         [解密后的数据]
     */
    private function decrypt($encrypData)
    {
        $sKey = self::_sha1prng($this->appSecret);
        $decrypted = openssl_decrypt(base64_decode($encrypData), 'AES-128-ECB', $sKey, OPENSSL_RAW_DATA, '');
        return $decrypted;
    }

    /**
     * SHA1PRNG算法
     * @param  [type] $key [description]
     * @return [type]      [description]
     */
    private function _sha1prng($key)
    {
        return substr(openssl_digest(openssl_digest($key, 'sha1', true), 'sha1', true), 0, 16);
    }

    //海外直邮最低价
    public function overseaLowestPrice($params)
    {
        return $this->request('3,37,apiUrl', [
            'sku_id' => $params['sku_id']
        ]);
    }

    //闪电直发最低价
    public function lowestPrice($params)
    {
        return $this->request('3,7,apiUrl', [
            'sku_id' => $params['sku_id']
        ]);
    }

    //全款预售
    public function preLowestPrice($params)
    {
        return $this->request('3,30,apiUrl', [
            'sku_id' => $params['sku_id']
        ]);
    }
    //品牌专供最低价
    public function brandLowestPrice($params)
    {
        return $this->request('3,31,apiUrl', [
            'sku_id' => $params['sku_id']
        ]);
    }

    //急速发货最低价
    public function fastLowestPrice($params)
    {
        return $this->request('3,38,apiUrl', [
            'sku_id' => $params['sku_id']
        ]);
    }

    //普通发货最低价
    public function normalLowestPrice($params)
    {
        return $this->request('3,39,apiUrl', [
            'sku_id' => $params['sku_id']
        ]);
    }

    // 根据品牌和类目ID获取获取商品信息
    public function brandProducts($params)
    {
        return $this->request('2,3,apiUrl', [
            'brand_id' => $params['brand_id'],
            'last_id' => $params['last_id'] ?? 0,
        ]);
    }

    // 获取商家已授权的品牌和类目
    public function authBrandList($params)
    {
        return $this->request('2,5,apiUrl', [
            'page_no' => $params['page_no'],
            'page_size' => $params['page_size'] ?? 30,
        ]);
    }

    public function productInfo($param)
    {
        $data = $this->request('2,2,apiUrl', [
            'article_numbers' => [$param['product_sn']]
        ]);
        if ($data && $data['code'] == 200) {
            foreach ($data['data'] as $item) {
                if ($item['article_number'] == $param['product_sn']) {
                    return $item;
                }
            }
        }
    }

    private function request($method, $params)
    {
        $paramArr = $params;
        $paramArr['app_key'] = $this->app_key;
        $paramArr['access_token'] = $this->access_token;
        $paramArr['timestamp'] = getMillisecond();
        $this->sign = $this->createSign($paramArr); //获取签名
        $paramArr['sign'] = $this->sign; //签名
        $this->config = $paramArr; //得到最终的请求数据
        $data = $this->uniformRequest($method, $paramArr);
        //Log::info($data);
        $data = $data ? json_decode($data, true) : [];
        return $data;
    }
}
