<?php

namespace App\Http\Controllers\Admin\V2\Wms;

use App\Exports\ExportSheets;
use App\Exports\ExportView;
use App\Http\Controllers\Admin\V1\BaseController;
use App\Logics\wms\AllocationTask;
use App\Logics\wms\Putaway;
use App\Models\Admin\V2\WmsPutawayList;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * 配货
 * - 波次分组策略管理
 * - 配货池管理
 * - 配货任务单管理
 * - 发货单复核
 * - 发货单取消
 */
class AllocationController extends BaseController
{

    public function strategySave(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'warehouse_code' => 'required',
            'name' => 'required',
            'mode' => 'required|in:1,2',
            'sort' => 'required',
            'upper_limit' => 'required',
            'content.*.column' => 'required',
            'content.*.condition' => 'required',
            'content.*.value' => 'required',
            'content.*.logic' => 'required|in:AND,OR',
        ]);

        $logic = new AllocationTask();
        $data = $logic->strategySave($params);
        return $this->output($logic, $data);
    }

    // 详情
    public function strategyShow($id)
    {
        $logic = new AllocationTask();
        $data = $logic->strategyDetail($id);
        return $this->output($logic, $data);
    }

    // 列表
    public function strategyIndex(Request $request)
    {
        $logic = new AllocationTask();
        $data = $logic->strategySearch($request->all());
        return $this->success($data);
    }

    // 导出
    function strategyExport(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new AllocationTask();
            $res = $logic->strategySearch($params, true);
            return $res;
        });
        // $params = $request->all();
        // $params['size'] = 1000;
        // $logic = new AllocationTask();
        // $res = $logic->strategySearch($params, true);
        // return $this->exportOutput($res);
    }

    // 删除
    function strategyDelete($id)
    {
        $logic = new AllocationTask();
        $logic->strategyDelete($id);
        return $this->success([]);
    }

    function strategyStatus(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
            'status' => 'required|in:0,1',
        ]);
        $logic = new AllocationTask();
        $logic->strategySave($params);
        return $this->output($logic);
    }

    // 待领取任务
    public function pendingTask(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'warehouse_code' => 'required',
        ]);
        $logic = new AllocationTask();
        $data = $logic->pendingTasks($params);
        return $this->success($data);
    }

    public function pool(Request $request)
    {
        $logic = new AllocationTask();
        $data = $logic->pool($request->all());
        return $this->success($data);
    }

    public function poolExport(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new AllocationTask();
            $res = $logic->pool($params, true);
            return $res;
        });
        // $logic = new AllocationTask();
        // $res = $logic->pool($request->all(), true);
        // return $this->exportOutput($res, ['F']);
    }

    // 根据配货订单领取任务
    public function getTaskByCode(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'pre_alloction_code' => 'required',
        ]);

        $logic = new AllocationTask();
        $data = $logic->receiveTaskByCode($params);
        return $this->success($data);
    }

    // 领取任务 - 按组领取
    public function getTaskByGroup(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'strategy_code' => 'required',
            'num' => 'required',
        ]);

        $logic = new AllocationTask();
        $data = $logic->receiveTaskByStartegy($params);
        return $this->success($data);
    }

    // 列表
    public function taskIndex(Request $request)
    {
        $logic = new AllocationTask();
        $data = $logic->taskSearch($request->all());
        return $this->success($data);
    }

    // 导出
    public function taskExport(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new AllocationTask();
            $res = $logic->taskSearch($params, true);
            return $res;
        });
        // $logic = new AllocationTask();
        // $res = $logic->taskSearch($request->all(), true);
        // return $this->exportOutput($res, ['G']);
    }

    // 取消领取
    public function taskCancel(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
        ]);
        $logic = new AllocationTask();
        $data = $logic->taskCancel($params);
        return $this->output($logic, $data);
    }

    // 自定义领取
    public function getCustom(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            // 'id' => 'required',
        ]);
        $logic = new AllocationTask();
        $data = $logic->receiveCustome($params);
        return $this->output($logic, $data);
    }

    // 详情
    public function taskShow($id)
    {
        $logic = new AllocationTask();
        $data = $logic->info($id);
        return $this->output($logic, $data);
    }

    // 扫码配货
    public function allocate(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
            'uniq_code' => 'required',
        ]);
        $logic = new AllocationTask();
        $data = $logic->allocate($params);
        return $this->output($logic, $data);
    }

    // 保存任务备注
    public function taskSave(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
            // 'remark' => 'required',
        ]);
        $logic = new AllocationTask();
        $data = $logic->taskSave($params);
        return $this->output($logic, $data);
    }

    // 配货完成
    public function taskDone(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
        ]);
        $logic = new AllocationTask();
        $data = $logic->taskDone($params);
        return $this->output($logic, $data);
    }

    // 任务单发货
    public function taskSendOut(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
        ]);
        $logic = new AllocationTask();
        $data = $logic->taskSendOut($params);
        return $this->output($logic, $data);
    }

    // 按单复核
    public function reviewByOrder(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'no' => 'required',
            'uniq_code' => 'required'
        ]);
        $logic = new AllocationTask();
        $data = $logic->reviewByOrder($params);
        return $this->playErr($logic, $data);
    }

    // 复核页面 - 发货明细
    public function reviewDetail(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'no' => 'required',
            'type' => 'required',
        ]);
        $logic = new AllocationTask();
        $data = $logic->reviewDetail($params);
        return $this->output($logic, $data);
    }

    // 按单复核 - 确认发货
    public function sendOutByOrder(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'no' => 'required',
        ]);
        $logic = new AllocationTask();
        $data = $logic->sendOutByOrder($params);
        return $this->output($logic, $data);
    }

    // 整单复核查询
    public function reviewInfoByWholeOrder(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'no' => 'required'
        ]);
        $logic = new AllocationTask();
        $data = $logic->reviewInfoByWholeOrder($params);
        return $this->playErr($logic, $data);
    }

    // 整单复核 - 确认发货
    public function sendOutByWholeOrder(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'no' => 'required',
        ]);
        $logic = new AllocationTask();
        $data = $logic->sendOutByWholeOrder($params);
        return $this->output($logic, $data);
    }

    // 出库取消
    public function cancel(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'third_no' => 'required',
        ]);
        $logic = new AllocationTask();
        $data = $logic->orderCancel($params);
        return $this->output($logic, $data);
    }

    // 取消单查询
    public function cancelIndex(Request $request)
    {
        $logic = new AllocationTask();
        $data = $logic->cancelSearch($request->all());
        return $this->success($data);
    }

    // 取消单导出
    public function cancelExport(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new AllocationTask();
            $res = $logic->cancelSearch($params, true);
            return $res;
        });
        // $logic = new AllocationTask();
        // $res = $logic->cancelSearch($request->all(), true);
        // return $this->exportOutput($res, ['G']);
    }

    // 出库取消单对应商品上架
    public function cancelPutaway(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'warehouse_code' => 'required',
            'location_code' => 'required',
            'type' => 'required|in:1,2',
            'uniq_code' => 'required_if:type,1',
            'bar_code' => 'required_if:type,2',
            'quality_level' => 'sometimes|required|in:A,B'
        ]);
        $logic = new AllocationTask();
        $data = $logic->cancelPutaway2($params);
        return $this->output($logic, $data);
    }


    // 出库取消单明细
    function cancelDetail($id)
    {

        $logic = new AllocationTask();
        $data = $logic->cancelDetail($id);
        return $this->output($logic, $data);
    }

    // 出库取消单上架完成
    function cancelPutawayConfirm(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'warehouse_code' => 'required',
        ]);
        $params['type'] = WmsPutawayList::TYPE_CANCEL;
        $logic = new Putaway();
        $data = $logic->submit($params);
        return $this->output($logic, $data);
    }

    // 取消单待上架商品明细
    function cancelWaitPutaway(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'warehouse_code' => 'required',
        ]);
        $params['type'] = WmsPutawayList::TYPE_CANCEL;
        $logic = new AllocationTask();
        $data = $logic->cancelWaitPutaway($params);
        return $this->output($logic, $data);
    }
}
