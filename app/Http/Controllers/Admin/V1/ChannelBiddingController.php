<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\BaseController;
use App\Logics\BiddingAsyncLogic;
use Illuminate\Http\Request;

class ChannelBiddingController extends BaseController
{

    // 渠道信息设置
    public function setChannelConfig(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'channel_code' => 'required',
            'status' => 'required|in:1,2',
            'stock_bid' => 'required|in:1,2',
            'app_bid' => 'required|in:1,2',
        ]);

        $logic = new BiddingAsyncLogic();
        $res = $logic->setChannelConfig($params);
        return $this->output($logic, $res);
    }

    public function search(Request $request)
    {
        $params = $request->all();
        $logic = new BiddingAsyncLogic();
        $res = $logic::search($params);
        return $this->output($logic, $res);
    }
}
