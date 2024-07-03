<?php

namespace App\Http\Controllers\Admin\V2\Wms;

use App\Exports\ExportSheets;
use App\Exports\ExportView;
use App\Http\Controllers\Admin\V1\BaseController;
use App\Logics\wms\Move;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * 移位单
 */
class MoveController extends BaseController
{

    // 新增/保存
    function save(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'type' => 'required',
            'warehouse_code' => 'required',
            'skus.*.bar_code' => 'required',
            'skus.*.location_code' => 'required',
            'skus.*.area_code' => 'required',
            'skus.*.total' => 'required',
            'skus.*.quality_type' => 'required',
            'skus.*.quality_level' => 'required',
            'skus.*.target_location_code' => 'required',
            'skus.*.target_area_code' => 'required',
        ]);
        $logic = new Move();
        $data = $logic->save($params);
        return $this->output($logic, $data);
    }

    // 详情
    function info(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required_without:code',
            'code'=>'required_without:id',
        ]);
        $logic = new Move();
        $data = $logic->info($params);
        return $this->output($logic, $data);
    }

    // 明细
    function detail(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
        ]);
        $logic = new Move();
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
        $logic = new Move();
        $data = $logic->revoke($params);
        return $this->output($logic, $data);
    }

    // 删除
    function delete($id)
    {
        $logic = new Move();
        $data = $logic->delete($id);
        return $this->output($logic, $data);
    }

    // 提交
    function submit(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
        ]);
        $logic = new Move();
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
        $logic = new Move();
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
        $logic = new Move();
        $data = $logic->cancel($params);
        return $this->output($logic, $data);
    }

    // 查询
    function search(Request $request)
    {
        $logic = new Move();
        $data = $logic->search($request->all());
        return $this->success($data);
    }

    // 导出
    function export(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new Move();
            $res = $logic->search($params, true);
            return $res;
        });
        // $params = $request->all();
        // $params['size'] = 1000;
        // $logic = new Move();
        // $res = $logic->search($params, true);
        // return $this->exportOutput($res);
    }

    // 扫描下架
    function takedown(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'code' => 'required',
            'uniq_code' => 'required',
        ]);
        $logic = new Move();
        $data = $logic->takedown($params);
        return $this->output($logic, $data);
    }

    // 确认下架
    function takedownConfirm(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'code' => 'required',
        ]);
        $logic = new Move();
        $data = $logic->takedownConfirm($params);
        return $this->output($logic, $data);
    }

    // 上架
    function shelf(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'code' => 'required',
            'uniq_code' => 'required',
            'location_code' => 'required',
        ]);
        $logic = new Move();
        $data = $logic->shelf($params);
        return $this->output($logic, $data);
    }

    // 一键上架
    function shelfConfirm(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'code' => 'required',
        ]);
        $logic = new Move();
        $data = $logic->shelfConfirm($params);
        return $this->output($logic, $data);
    }
}
