<?php

namespace App\Http\Controllers\CarryMe;

use App\Logics\BaseLogic;
use App\Logics\ChannelLogic;
use App\Logics\OrderLogic;
use App\Models\AppOrder;
use App\Models\ChannelOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CallbackController
{
    //得物回调处理
    public function dw(Request $request): array
    {
        // Log::channel('daily2')->info('callback', $request->post());

        set_time_limit(0);
        $api = new ChannelLogic('DW');
        $api->callbackHandle($request->post());
        return [
            'msg' => 'SUCCESS',
            'code' => 200,
            'data' => $request->post('uuid', '')
        ];
    }

    function carryme(Request $request): array
    {
        // BaseLogic::log('callback',$request->post());
        $api = new ChannelLogic('CARRYME');
        $api->callbackHandle($request->post());

        return [
            'msg' => 'SUCCESS',
            'code' => 200,
            'data' => $request->post('uuid', '')
        ];
    }

    //得物回调处理
    public function dw2(Request $request): array
    {
        $api = new ChannelLogic('DW');
        $api::callbackHandleTest($request->post());
        return [
            'msg' => 'SUCCESS',
            'code' => 200,
            'data' => $request->post('uuid', '')
        ];
    }

    public function goatTest(Request $request)
    {
        $api = new ChannelLogic('GOAT');
        return $api->gaotOrderTest($request->post());
    }

    // 模拟stockx订单
    public function stockxTest(Request $request)
    {
        $api = new ChannelLogic('STOCKX');
        return $api->stockxOrderTest($request->post());
    }
}
