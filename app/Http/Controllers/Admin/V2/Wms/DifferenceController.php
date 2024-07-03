<?php

namespace App\Http\Controllers\Admin\V2\Wms;

use App\Exports\ExportSheets;
use App\Exports\ExportView;
use App\Http\Controllers\Admin\V1\BaseController;
use App\Logics\wms\Check;
use App\Logics\wms\CheckRequest;
use App\Logics\wms\Stock;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * 差异处理记录
 */
class DifferenceController extends BaseController
{
    // 查询
    function search(Request $request)
    {
        $logic = new CheckRequest();
        $data = $logic->dSearch($request->all());
        return $this->success($data);
    }

    // 导出
    function export(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new CheckRequest();
            $res = $logic->dSearch($params, true);
            return $res;
        });
        // $params = $request->all();
        // $params['size'] = 1000;
        // $logic = new CheckRequest();
        // $res = $logic->dSearch($params, true);
        // return $this->exportOutput($res);
    }


    // 详情
    function info(Request $request)
    {
        $logic = new CheckRequest();
        $data = $logic->dInfo($request->all());
        return $this->output($logic, $data);
    }

    // 详情
    function detail(Request $request)
    {
        $logic = new CheckRequest();
        $data = $logic->dDetail($request->all());
        return $this->output($logic, $data);
    }

    // 保存
    function save(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
            'remark' => 'required',
        ]);
        $logic = new CheckRequest();
        $data = $logic->dSave($request->all());
        return $this->output($logic, $data);
    }

    // 审核
    function audit(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
        ]);
        $logic = new CheckRequest();
        $data = $logic->dAudit($request->all());
        return $this->output($logic, $data);
    }
}
