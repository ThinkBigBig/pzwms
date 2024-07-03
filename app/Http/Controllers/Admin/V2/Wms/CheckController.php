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
 * 盘点单
 */
class CheckController extends BaseController
{
    // 查询
    function search(Request $request)
    {
        $logic = new Check();
        $data = $logic->search($request->all());
        return $this->success($data);
    }

    // 导出
    function export(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new Check();
            $res = $logic->search($params, true);
            return $res;
        });
        // $params = $request->all();
        // $params['size'] = 1000;
        // $logic = new Check();
        // $res = $logic->search($params, true);
        // return $this->exportOutput($res);
    }

    // 删除
    function delete($id)
    {
        $logic = new Check();
        $data = $logic->delete($id);
        return $this->output($logic, $data);
    }

    // 详情
    function info(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required_without:code','code' => 'required_without:id'
        ]);
        $logic = new Check();
        $data = $logic->info($params);
        return $this->output($logic, $data);
    }

    // 详情
    function detail(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
        ]);
        $logic = new Check();
        $data = $logic->detail($params);
        return $this->output($logic, $data);
    }

    // 扫码
    function scan(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
            'location_code' => 'required',
            'uniq_code' => 'required',
        ]);
        $logic = new Check();
        $data = $logic->scan($params);
        return $this->output($logic, $data);
    }

    // 保存
    function save(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required'
        ]);
        $logic = new Check();
        $data = $logic->save($params);
        return $this->output($logic, $data);
    }


    // 确认盘点
    function confirm(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
        ]);
        $logic = new Check();
        $data = $logic->confirm($params);
        return $this->output($logic, $data);
    }
}
