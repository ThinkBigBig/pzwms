<?php

namespace App\Http\Controllers\PDA;

use App\Logics\wms\Stock;
use Illuminate\Http\Request;

class InventoryController extends BaseController
{
    // 库存查询
    function search(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'type' => 'required|in:1,2,3,4',
            'code' => 'required',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new Stock();
        $res = $logic->search($params);
        return $this->output($logic, $res);
    }

    // 唯一码明细
    function detail(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'bar_code' => 'required',
            'location_code' => 'required',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new Stock();
        $res = $logic->detail($params);
        return $this->output($logic, $res);
    }
}
