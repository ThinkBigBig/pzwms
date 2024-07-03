<?php

namespace App\Http\Controllers\PDA;

use App\Logics\wms\Check;
use Illuminate\Http\Request;

class CheckController extends BaseController
{
    // 盘点单
    function search(Request $request)
    {
        $params = $request->input();
        $logic = new Check();
        $params['check_status'] = [0, 1];
        $params['check_user_id'] = ADMIN_INFO['user_id'];
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $res = $logic->search($params);
        return $this->output($logic, $res);
    }

    // 盘点单详情
    function detail(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'code' => 'required',
        ]);
        $params['group'] = true;
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $params['check_user_id'] = ADMIN_INFO['user_id'];
        $logic = new Check();
        $res = $logic->detail($params);
        return $this->output($logic, $res);
    }

    // 盘点
    function scan(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'type' => 'required|in:1,2',
            'location_code' => 'required',
            'uniq_code' => 'required_if:type,1',
            'bar_code' => 'required_if:type,2',
            'quality_level' => 'sometimes|required|in:1,2'
        ]);
        $params['group'] = true;
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $params['check_user_id'] = ADMIN_INFO['user_id'];
        
        $logic = new Check();
        $res = $logic->scan($params);
        return $this->output($logic, $res);
    }

    // 盘点单扫描位置码
    function scanLocationCode(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'location_code' => 'required',
        ]);
        $params['group'] = true;
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $params['check_user_id'] = ADMIN_INFO['user_id'];
        $logic = new Check();
        $res = $logic->appendLocationCode($params);
        return $this->output($logic, $res);
    }

    // 更新实盘数
    function updateCheckNum(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'code' => 'required',
            'bar_code' => 'required',
            'location_code' => 'required',
            'quality_level' => 'required',
            'check_num' => 'required',
        ]);
        $params['group'] = true;
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $params['check_user_id'] = ADMIN_INFO['user_id'];
        $logic = new Check();
        $res = $logic->updateCheckNum($params);
        return $this->output($logic, $res);
    }

    // 更新实盘数
    function confirm(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'code' => 'required',
        ]);
        $params['group'] = true;
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $params['check_user_id'] = ADMIN_INFO['user_id'];
        $logic = new Check();
        $res = $logic->confirm($params);
        return $this->output($logic, $res);
    }

    // 刷新盘点单
    function refresh(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'code' => 'required',
        ]);
        $params['group'] = true;
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $params['check_user_id'] = ADMIN_INFO['user_id'];
        $logic = new Check();
        $res = $logic->refresh($params);
        return $this->output($logic, $res);
    }
}
