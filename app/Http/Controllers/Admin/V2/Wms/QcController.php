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
 * 质检
 */
class QcController extends BaseController
{

    // 一键质检
    function qcOneStep(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'recv_id' => 'required'
        ]);
        $logic = new QualityControl();
        $logic->qcOneStep($params);
        return $this->output($logic, []);
    }

    // 逐件质检
    function scan(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'skus' => 'required|array',
            'skus.*.uniq_code' => 'required',
            'skus.*.quality_level' => 'required|in:A,B,C,D,E,F',
        ]);
        $logic = new QualityControl();
        $logic->scan($params);
        return $this->output($logic, []);
    }

    // 逐件质检提交
    function scanSubmit(Request $request)
    {
        $params = $request->all();
        $logic = new QualityControl();
        $logic->scanSubmit($params);
        return $this->output($logic, []);
    }

    // 质检单查询
    function search(Request $request)
    {
        $logic = new QualityControl();
        $data = $logic->qcSearch($request->all());
        return $this->success($data);
    }

    // 质检单导出
    function export(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new QualityControl();
            $res = $logic->qcSearch($params, true);
            return $res;
        });
        // $params = $request->all();
        // $params['size'] = 1000;
        // $logic = new QualityControl();
        // $res = $logic->qcSearch($params,true);
        // return $this->exportOutput($res);
    }

    // 质检单详情
    function info(Request $request)
    {
        $params = $request->all();
        $logic = new QualityControl();
        $data = $logic->qcInfo($params);
        return $this->output($logic, $data);
    }

    // 质检单信息保存
    function save(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
            'remark' => 'required',
        ]);

        $logic = new QualityControl();
        $data = $logic->qcSave($params);
        return $this->output($logic, $data);
    }

    // 质检单sku明细
    function skuDetail(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'qc_code' => 'required',
            'bar_code' => 'required',
            'quality_level' => 'required',
        ]);

        $logic = new QualityControl();
        $data = $logic->qcDetail($params);
        return $this->output($logic, $data);
    }

    // 质检单明细导出
    function skuDetailExport(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'qc_code' => 'required',
            'bar_code' => 'required',
        ]);
        $params['size'] = 1000;
        $logic = new QualityControl();
        $data = $logic->qcDetail($params);
        $title = __('columns.wms_stock_check_details.table_title');
        $name = sprintf('%s%s.xlsx', $title, date('YmdHis'));
        return Excel::download(new ExportView('exports.qcDetail', $data, $title, []), $name);
    }
}
