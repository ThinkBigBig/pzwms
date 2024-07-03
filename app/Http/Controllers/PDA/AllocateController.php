<?php

namespace App\Http\Controllers\PDA;

use App\Logics\wms\AllocationTask;
use App\Logics\wms\Auth;
use App\Logics\wms\Putaway;
use App\Logics\wms\Warehouse;
use App\Models\Admin\V2\WmsPutawayList;
use Illuminate\Http\Request;

class AllocateController extends BaseController
{
    // 待领取任务
    function pendingTask(Request $request)
    {
        $params = $request->input();
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new AllocationTask();
        $res = $logic->pendingTasks($params);
        return $this->output($logic, $res);
    }

    // 领取任务
    function getTask(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'strategy_code' => 'required',
            'num' => 'required',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new AllocationTask();
        $res = $logic->receiveTaskByStartegy($params);
        return $this->output($logic, $res);
    }

    // 已领取的任务
    function userTask(Request $request)
    {
        $params = $request->input();
        $params['size'] = 100;
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $params['alloction_status'] = [1, 2];
        $params['status'] = [1, 2];
        $params['receiver_id'] = ADMIN_INFO['user_id'];
        $logic = new AllocationTask();
        $res = $logic->taskSearch($params);
        return $this->output($logic, $res);
    }

    // 取消领取的任务
    function cancelTask(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'task_code' => 'required',
        ]);
        $logic = new AllocationTask();
        $res = $logic->taskCancel($params);
        return $this->output($logic, $res);
    }

    // 配货任务展示信息
    function taskShow(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'task_code' => 'required',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new AllocationTask();
        $res = $logic->taskShow($params);
        return $this->output($logic, $res);
    }

    // 配货任务明细
    function taskDetail(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'task_code' => 'required',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new AllocationTask();
        $res = $logic->taskDetail($params);
        return $this->output($logic, $res);
    }


    // 配货
    function allocate(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'task_code' => 'required',
            'type' => 'required|in:1,2',
            'location_code' => 'required',
            'uniq_code' => 'required_if:type,1',
            'bar_code' => 'required_if:type,2',
        ]);
        $logic = new AllocationTask();
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $res = $logic->allocate2($params);
        return $this->output($logic, $res);
    }

    // 跳过指定商品暂不配货
    function skip(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'task_code' => 'required',
            'location_code' => 'required',
            'type' => 'required|in:1,2',
            'bar_code' => 'required',
            'batch_no' => 'required',
            'quality_level' => 'required',
        ]);
        $logic = new AllocationTask();
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $res = $logic->skip($params);
        return $this->output($logic, $res);
    }

    // 出库取消单
    function cancelSearch(Request $request)
    {
        $params = $request->input();
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $params['cancel_status'] = [3, 4];
        $logic = new AllocationTask();
        $res = $logic->cancelSearch($params);
        return $this->output($logic, $res);
    }

    // 获取唯一码信息
    function getRecommendInfo(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'type' => 'required|in:1,2',
            'uniq_code' => 'required_if:type,1',
            'bar_code' => 'required_if:type,2',
            'quality_level' => 'sometimes|required|in:1,2'
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new AllocationTask();
        $res = $logic->getRecommendInfo($params);
        return $this->output($logic, $res);
    }

    // 取消单上架
    function cancelPutaway(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            // 'request_code' => 'required',
            // 'uniq_code' => 'required',
            'location_code' => 'required',
            'type' => 'required|in:1,2',
            'uniq_code' => 'required_if:type,1',
            'bar_code' => 'required_if:type,2',
            'quality_level' => 'sometimes|required|in:1,2'
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new AllocationTask();
        $res = $logic->cancelPutaway2($params);
        if (!$res) return $this->output($logic, $res);

        $sku_num = 0;
        $tmp = [];
        foreach ($res as $item) {
            $tmp[$item['location_code']][] = $item;
            $sku_num++;
        }

        $detail = [];
        foreach ($tmp as $location_code => $item) {
            $detail[] = [
                'location_code' => $location_code,
                'skus' => $item,
            ];
        }
        return $this->output($logic, [
            'location_num' => count($detail),
            'sku_num' => $sku_num,
            'detail' => $detail,
        ]);
    }

    // 取消单上架完成
    function cancelPutawayConfirm(Request $request)
    {
        $params = $request->input();
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $params['type'] = WmsPutawayList::TYPE_CANCEL;
        $logic = new Putaway();
        $res = $logic->submit($params);
        return $this->output($logic, $res);
    }
}
