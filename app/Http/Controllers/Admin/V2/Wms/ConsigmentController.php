<?php

namespace App\Http\Controllers\Admin\V2\Wms;

use App\Exports\ExportSheets;
use App\Exports\ExportView;
use App\Http\Controllers\Admin\V1\BaseController;
use App\Logics\wms\Consigment;
use App\Logics\wms\Stock;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * 寄卖
 */
class ConsigmentController extends BaseController
{

    // 查询
    function billSearch(Request $request)
    {
        $logic = new Consigment();
        $data = $logic->billSearch($request->all());
        return $this->output($logic, $data);
    }

    // 导出
    function billExport(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new Consigment();
            return $logic->billSearch($params, true);
        });
    }

    // 指定结算规则
    function assignRule(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'ids' => 'required',
            'rule_id' => 'required',
        ]);

        $logic = new Consigment();
        $data = $logic->assignRule($params);
        return $this->output($logic, $data);
    }

    // 指定结算金额
    function assignAmount(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
            'amount' => 'required',
        ]);

        $logic = new Consigment();
        $data = $logic->assignAmount($params);
        return $this->output($logic, $data);
    }

    // 按供应商申请提现的明细
    function withdrawApplyDetail(Request $request)
    {
        $params = $request->all();
        if (empty($params['sup_id']) && empty($params['ids'])) {
            return $this->error('参数不能为空');
        }
        if (($params['ids'] ?? '') && is_string($params['ids'])) $params['ids'] = explode(',', $params['ids']);

        $logic = new Consigment();
        $data = $logic->withdrawApplyDetails($params);
        return $this->output($logic, $data);
    }


    // 按供应商申请提现
    function withdrawApply(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'sup_id' => 'required',
            'ids' => 'required',
            'type' => 'required|in:1,2',
        ]);

        $logic = new Consigment();
        $data = $logic->withdrawApply($params);
        return $this->output($logic, $data);
    }



    // 提现申请单查询
    function withdrawSearch(Request $request)
    {
        $params = $request->all();
        $logic = new Consigment();
        $data = $logic->withdrawSearch($params);
        return $this->output($logic, $data);
    }

    // 提现申请单导出
    function withdrawExport(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new Consigment();
            $res = $logic->withdrawSearch($params,true);
            return $res;
        });
    }

    // 提现申请单详情
    function withdrawInfo(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
        ]);

        $logic = new Consigment();
        $data = $logic->WithdrawInfo($params);
        return $this->output($logic, $data);
    }

    // 提现申请单审核
    function withdrawAudit(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'ids' => 'required',
        ]);

        $logic = new Consigment();
        $data = $logic->withdrawAudit($params);
        return $this->output($logic, $data);
    }
}
