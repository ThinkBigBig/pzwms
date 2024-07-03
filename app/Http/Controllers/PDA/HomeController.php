<?php

namespace App\Http\Controllers\PDA;

use App\Logics\wms\Auth;
use App\Logics\wms\Warehouse;
use Illuminate\Http\Request;

class HomeController extends BaseController
{
    // 仓库列表
    function warehouse(Request $request)
    {
        $params = $request->input();
        $logic = new Warehouse();
        $res = $logic->warehouseSearch($params);
        return $this->output($logic, $res);
    }

    // 切换仓库
    function warehouseChange(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'warehouse_code' => 'required',
        ]);
        $logic = new Auth();
        $res = $logic->changeWarehouse($params);
        return $this->output($logic, $res);
    }
    // 主页信息
    function info(Request $request)
    {
        $params = $request->input();
        $logic = new Auth();
        $res = $logic->padInfo($params);
        return $this->output($logic, $res);
    }
    
}
