<?php

namespace App\Http\Controllers\PDA;

use App\Logics\wms\Check;
use App\Logics\wms\Move;
use Illuminate\Http\Request;

class MoveController extends BaseController
{
    // 待移位任务查询
    function search(Request $request)
    {
        $params = $request->input();
        $logic = new Move();
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $params['type'] = 2;
        $res = $logic->pdaSearch($params);
        return $this->output($logic, $res);
    }

    // 中转移位-下架
    function takedown(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'type' => 'required|in:1,2',
            'uniq_code' => 'required_if:type,1',
            'location_code' => 'required_if:type,2',
            'bar_code' => 'required_if:type,2',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new Move();
        $res = $logic->indirectTakedownByUniqCode($params);
        return $this->output($logic, $res);
    }

    // 中转移位-位置码一键下架
    function takedownByLocationCode(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'code' => 'required',
            'location_code' => 'required',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new Move();
        $res = $logic->indirectTakedownByLocationCode($params);
        return $this->output($logic, $res);
    }

    // 中转移位-确认下架
    function takedownConfirm(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'code' => 'required',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new Move();
        $res = $logic->indirectTakedownConfirm($params);
        return $this->output($logic, $res);
    }


    // 中转移位-上架
    function shelf(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'code' => 'required',
            'type' => 'required|in:1,2',
            'location_code' => 'required',
            'uniq_code' => 'required_if:type,1',
            'bar_code' => 'required_if:type,2',
            // 'move_unit' => 'required_if:type,2',
        ]);
        $params['move_unit'] = $params['move_unit'] ?? 1; //移位单元 1-散件
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new Move();
        $res = $logic->indirectShelfByUniqCode($params);
        return $this->output($logic, $res);
    }

    // 中转移位-位置码一键上架
    function shelfByLocationCode(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'code' => 'required',
            'location_code' => 'required',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new Move();
        $res = $logic->indirectShelfByLocationCode($params);
        return $this->output($logic, $res);
    }

    // 中转移位-详情
    function detail(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'code' => 'required',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new Move();
        $res = $logic->pdaDetail($params);
        return $this->output($logic, $res);
    }

    // 快速移位 - 待移位任务查询
    function fastSearch(Request $request)
    {
        $params = $request->input();
        $logic = new Move();
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $params['type'] = 3;
        $res = $logic->pdaSearch($params);
        return $this->output($logic, $res);
    }

    // 快速移位
    function fastMove(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'location_code' => 'required',
            'new_location_code' => 'required',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new Move();
        $res = $logic->fastMove($params);
        return $this->output($logic, $res);
    }


    // 快速移位-确认移位
    function fastConfirm(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'code' => 'required',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new Move();
        $res = $logic->fastConfirm($params);
        return $this->output($logic, $res);
    }
}
