<?php

namespace App\Http\Controllers\PDA;

use App\Logics\wms\Auth;
use App\Logics\wms\QualityControl;
use App\Logics\wms\Warehouse;
use Illuminate\Http\Request;

class QcController extends BaseController
{
    // 一键质检收货单
    function receiveOrder(Request $request)
    {
        $params = $request->input();
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new QualityControl();
        $res = $logic->receiveOrder($params);
        return $this->output($logic, $res);
    }

    // 收货单详情
    function receiveOrderDetail(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'recv_id' => 'required',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new QualityControl();
        $res = $logic->receiveOrderDetail($params);
        return $this->output($logic, $res);
    }

    // 收货单一键质检
    function oneStep(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'recv_id' => 'required',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new QualityControl();
        $res = $logic->qcOneStep($params);
        return $this->output($logic, $res);
    }

    // 唯一码扫描
    function uniqScan(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'uniq_code' => 'required',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new QualityControl();
        $data = $logic->uniqScan($params);
        return $this->output($logic, $data);
    }

    // 瑕疵上报
    function flawReport(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'uniq_code' => 'required',
            'quality_level' => 'required|in:B,C,D,E,F',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new QualityControl();
        $logic->flawReport($params);
        return $this->output($logic, []);
    }


    // 瑕疵质检完成
    function flawSubmit(Request $request)
    {
        $params = $request->all();
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new QualityControl();
        $logic->scanSubmit($params);
        return $this->output($logic, []);
    }

    // 质量类型调整上报
    function changeReport(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'uniq_code' => 'required',
            'quality_level' => 'required|in:A,B,C,D,E,F',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new QualityControl();
        $logic->qcConfirmSubmit($params);
        return $this->output($logic, []);
    }

}
