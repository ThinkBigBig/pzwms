<?php

namespace App\Http\Controllers\Admin\V2\Wms;

use App\Exports\ExportSheets;
use App\Exports\ExportView;
use App\Http\Controllers\Admin\V1\BaseController;
use App\Http\Controllers\Controller;
use App\Logics\wms\QualityControl;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * 质检确认
 */
class QcConfirmController extends BaseController
{

    // 质检存疑
    function submit(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'uniq_code' => 'required',
        ]);

        $logic = new QualityControl();
        $data = $logic->qcConfirmSubmit($params);
        return $this->output($logic, $data);
    }

    // 确认
    function confirm(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
            'quality_type' => 'required|in:1,2',
        ]);

        $logic = new QualityControl();
        $data = $logic->qcConfirmDone($params);
        return $this->output($logic, $data);
    }

    // 批量确认
    function batchConfirm(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'ids' => 'required|array',
            'quality_type' => 'required|in:1,2',
        ]);

        $logic = new QualityControl();
        foreach ($params['ids'] as $id) {
            $params['id'] = $id;
            $params['remark'] = '批量确认';
            $logic->qcConfirmDone($params);
        }

        return $this->output($logic, []);
    }

    // 质检确认单查询
    function search(Request $request)
    {
        $logic = new QualityControl();
        $data = $logic->qcConfirmSearch($request->all());
        return $this->success($data);
    }

    // 质检确认单导出
    function export(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new QualityControl();
            $res = $logic->qcConfirmSearch($params, true);
            return $res;
        });
        // $params = $request->all();
        // $params['size'] = 1000;
        // $logic = new QualityControl();
        // $res = $logic->qcConfirmSearch($params, true);
        // return $this->exportOutput($res,[]);
    }

    // 质检单确认详情
    function info(Request $request)
    {
        $params = $request->all();
        $logic = new QualityControl();
        $data = $logic->qcConfirmInfo($params);
        return $this->output($logic, $data);
    }

    // 质检单确认单信息保存
    function save(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
            'remark' => 'required',
        ]);

        $logic = new QualityControl();
        $data = $logic->qcConfirmSave($params);
        return $this->output($logic, $data);
    }
}
