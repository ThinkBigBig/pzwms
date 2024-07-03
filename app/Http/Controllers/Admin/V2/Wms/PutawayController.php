<?php

namespace App\Http\Controllers\Admin\V2\Wms;

use App\Exports\ExportSheets;
use App\Exports\ExportView;
use App\Http\Controllers\Admin\V1\BaseController;
use App\Logics\wms\Putaway;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * 上架
 */
class PutawayController extends BaseController
{
    // 扫描上架
    function scan(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'warehouse_code' => 'required',
            'skus' => 'required|array',
            'skus.*.uniq_code' => 'required',
            'skus.*.location_code' => 'required',
        ]);
        $logic = new Putaway();
        $data = $logic->scan($params);
        return $this->output($logic, $data);
    }

    // 扫描上架完成
    function submit(Request $request)
    {
        $params = $request->all();
        $logic = new Putaway();
        $logic->submit($params);
        return $this->output($logic, []);
    }

    // 上架单查询
    function search(Request $request)
    {
        $logic = new Putaway();
        $data = $logic->search($request->all());
        return $this->success($data);
    }

    // 上架单导出
    function export(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new Putaway();
            $res = $logic->search($params, true);
            return $res;
        });
        // $params = $request->all();
        // $params['size'] = 1000;
        // $logic = new Putaway();
        // $res = $logic->search($request->all(), true);
        // return $this->exportOutput($res);
    }

    // 上架单详情
    function info(Request $request)
    {
        $params = $request->all();
        $logic = new Putaway();
        $data = $logic->info($params);
        return $this->output($logic, $data);
    }

    // 明细
    function detail(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'putaway_code' => 'required',
            'bar_code' => 'required',
            'location_code' => 'required',
            'quality_level' => 'required',
        ]);

        $logic = new Putaway();
        $data = $logic->detail($params);
        return $this->output($logic, $data);
    }

    // sku明细
    function detailExport(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'putaway_code' => 'required',
            'bar_code' => 'required',
            'location_code' => 'required',
            'quality_level' => 'required',
        ]);

        $logic = new Putaway();
        $data = $logic->detail($params);
        $headers[] = [
            'id' => '序号',
            'merchant_name' => '供应商',
            'product_sn' => '货号',
            'name' => '品名',
            'spec_one' => '规格',
            'quality_type_txt' => '质量类型',
            'quality_level' => '质量等级',
            'lot_num' => '批次号',
            'uniq_code' => '唯一码',
            'num' => '数量',
            'admin_user' => '操作人',
            'created_at' => '操作时间',
        ];
        $title = __('columns.wms_putaway_detail.table_title');
        $name = sprintf('%s_%s.xlsx', $title, date('YmdHis'));
        return Excel::download(new ExportView('exports.common', [
            'headers' => $headers,
            'data' => $data,
            'title' => $title,
        ], $title, []), $name);
    }

    // 上架单信息保存
    function save(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
            'remark' => 'required',
        ]);

        $logic = new Putaway();
        $data = $logic->save($params);
        return $this->output($logic, $data);
    }
}
