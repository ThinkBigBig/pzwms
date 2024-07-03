<?php

namespace App\Http\Controllers\Admin\V2\Wms;

use App\Exports\ExportSheets;
use App\Exports\ExportView;
use App\Http\Controllers\Admin\V1\BaseController;
use App\Logics\wms\CheckRequest;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * 盘库申请
 */
class CheckRequestController extends BaseController
{

    // 申请单新增/保存
    function save(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'warehouse_code' => 'required',
            'skus.*.bar_code' => 'required',
            'skus.*.location_code' => 'required',
            'skus.*.quality_type' => 'required',
            'skus.*.quality_level' => 'required',
            'skus.*.stock_num' => 'required',
        ]);
        $logic = new CheckRequest();
        $data = $logic->save($params);
        return $this->output($logic, $data);
    }

    // 申请单详情
    function info(Request $request)
    {
        $logic = new CheckRequest();
        $data = $logic->info($request->all());
        return $this->output($logic, $data);
    }

    // 申请单详情
    function detail(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
        ]);
        $logic = new CheckRequest();
        $data = $logic->detail($params);
        return $this->output($logic, $data);
    }

    // 撤回
    function revoke(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
        ]);
        $logic = new CheckRequest();
        $data = $logic->revoke($params);
        return $this->output($logic, $data);
    }

    // 删除
    function delete(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
        ]);
        $logic = new CheckRequest();
        $data = $logic->delete($params);
        return $this->output($logic, $data);
    }

    // 提交
    function submit(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
        ]);
        $logic = new CheckRequest();
        $data = $logic->submit($params);
        return $this->output($logic, $data);
    }

    // 审核
    function audit(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
            'status' => 'required|in:2,3'
        ]);
        $logic = new CheckRequest();
        $data = $logic->audit($params);
        return $this->output($logic, $data);
    }

    // 取消
    function cancel(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
        ]);
        $logic = new CheckRequest();
        $data = $logic->cancel($params);
        return $this->output($logic, $data);
    }

    // 下发
    function send(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
            'type' => 'required|in:0,1',
        ]);
        $logic = new CheckRequest();
        $data = $logic->send($params);
        return $this->output($logic, $data);
    }

    // 复盘
    function second(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
            'type' => 'required|in:0,1',
            'scope' => 'required|in:1,2,3,4,5',
        ]);
        $logic = new CheckRequest();
        $data = $logic->second($params);
        return $this->output($logic, $data);
    }

    // 查询
    function search(Request $request)
    {
        $logic = new CheckRequest();
        $data = $logic->search($request->all());
        return $this->success($data);
    }

    // 导出
    function export(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new CheckRequest();
            $res = $logic->search($params, true);
            return $res;
        });
        // $params = $request->all();
        // $params['size'] = 1000;
        // $logic = new CheckRequest();
        // $res = $logic->search($params, true);
        // return $this->exportOutput($res, []);
    }

    // 差异处理列表
    function difference(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'code' => 'required',
        ]);
        $logic = new CheckRequest();
        $data = $logic->differenceSearch($params);
        return $this->output($logic, $data);
    }

    // 上报
    function report(Request $request)
    {
        $logic = new CheckRequest();
        $data = $logic->report($request->all());
        return $this->output($logic, $data);
    }

    // 少货寻回
    function recover(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'code' => 'required',
            'origin_uniq_code' => 'required',
            'uniq_code' => 'required',
            'location_code' => 'required',
            'num' => 'required',
        ]);
        $logic = new CheckRequest();
        $data = $logic->recover($params);
        return $this->output($logic, $data);
    }
}
