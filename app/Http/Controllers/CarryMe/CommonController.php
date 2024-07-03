<?php

namespace App\Http\Controllers\CarryMe;

use App\Http\Controllers\Controller;
use App\Logics\BaseLogic;
use App\Logics\BiddingLogic;

use App\Logics\ProductLogic;
use App\Logics\RedisKey;
use \Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class CommonController extends Controller
{
    //渠道信息
    public function channels(): JsonResponse
    {
        $logic = new BiddingLogic();
        $data = $logic::getChannels();
        foreach ($data as &$item) {
            $item['code_alias'] = strtolower($item['code']); //给前端用
        }
        return $this->output($logic, $data);
    }

    //渠道商品信息
    public function product(Request $request): JsonResponse
    {
        $params = $request->input();
        $this->validateParams($params, [
            'product_sn' => 'required'
        ]);
        $logic = new ProductLogic();
        $data = $logic->product($params);
        return $this->output($logic, $data);
    }

    //同步商品最低价
    public function syncLowestPrice(Request $request): JsonResponse
    {
        $params = $request->input();
        $this->validateParams($params, [
            'product_sn' => 'required'
        ]);
        $data = [
            'product_init' => 0,
            'product_sn' => $params['product_sn'],
        ];
        Redis::rpush(RedisKey::PURCHASE_PRODUCT_QUEUE, json_encode($data));
        $logic = new BaseLogic();
        return $this->output($logic, []);
    }
}