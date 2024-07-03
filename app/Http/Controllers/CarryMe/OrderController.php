<?php

namespace App\Http\Controllers\CarryMe;

use App\Http\Controllers\Controller;
use App\Logics\OrderLogic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * 商家确认发货
     * @param Request $request
     * @return JsonResponse
     */
    public function businessConfirm(Request $request): JsonResponse
    {
        $params = $request->input();
        $this->validateParams($params, [
            'order_id' => 'required'
        ]);
        $logic = new OrderLogic();
        $data = $logic->businessConfirm($params);
        return $this->output($logic, $data);
    }

    /**
     * 平台确认发货
     * @param Request $request
     * @return JsonResponse
     */
    public function platformConfirm(Request $request): JsonResponse
    {
        $params = $request->input();
        $this->validateParams($params, [
            'order_id' => 'required'
        ]);
        $logic = new OrderLogic();
        $logic->platformConfirm($params);
        return $this->output($logic, []);
    }

    /**
     * 订单未匹配
     *
     * @param Request $request
     */
    public function unmatch(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'order_id' => 'required'
        ]);
        $logic = new OrderLogic();
        $logic->unmatch($params);
        return $this->output($logic, []);
    }
}