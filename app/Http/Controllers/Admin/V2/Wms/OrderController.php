<?php

namespace App\Http\Controllers\Admin\V2\Wms;

use App\Exports\ExportView;
use App\Http\Controllers\Admin\V1\BaseController;
use App\Logics\wms\Order;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use stdClass;

/**
 * 销售订单
 */
class OrderController extends BaseController
{
    // 导入
    function import(Request $request)
    {
        $arr = Excel::toArray(new stdClass(), $request->file('file'));
        $logic = new Order();
        $data = $logic->import($arr[0] ?? $logic->err_msg);
        return $this->output($logic, $data);
    }

    // 新增
    function add(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'product_code' => 'required',
            'skus.*.bar_code' => 'required',
            'skus.*.num' => 'required|integer|min:1',
            'skus.*.quality_level' => 'required|in:A,B,C,D,E,F',
            'skus.*.uniq_code' => 'present',
            'skus.*.cost_price' => 'present',
            'skus.*.batch_no' => 'required',
            'skus.*.warehouse_code' => 'required',
            'skus.*.sup_id' => 'required',
            'skus.*.amount' => 'required',
            'skus.*.discount_amount' => 'required',
        ]);
        if ($params['created_at'] ?? '') $params['order_at'] = $params['created_at'];
        $logic = new Order();
        $data = $logic->add($params);
        return $this->output($logic, $data);
    }

    // 复制新增
    function copyAdd(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
        ]);
        $logic = new Order();
        $data = $logic->copyAdd($params);
        return $this->output($logic, $data);
    }


    // 修改
    function save(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
            'skus.*.bar_code' => 'required_with:skus',
            'skus.*.num' => 'required_with:skus|integer|min:1',
            'skus.*.quality_level' => 'required_with:skus|in:A,B,C,D,E,F',
            'skus.*.batch_no' => 'present',
            'skus.*.warehouse_code' => 'required_with:skus',
            'skus.*.sup_id' => 'required_with:skus',
            'skus.*.amount' => 'required_with:skus',
            'skus.*.discount_amount' => 'required_with:skus',
        ]);
        $logic = new Order();
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
        $logic = new Order();
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
        $logic = new Order();
        $data = $logic->detail($params);
        return $this->output($logic, $data);
    }

    // 撤回
    function revoke(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'ids' => 'required',
        ]);
        $logic = new Order();
        $data = $logic->revoke($params);
        return $this->output($logic, $data);
    }

    // 删除
    function delete($ids)
    {
        $fail = [];
        $ids = explode(',', $ids);
        $logic = new Order();
        $success = false;
        $err_msg = '';
        foreach ($ids as $id) {
            $res = $logic->delete($id);
            if (!$res) {
                $fail[] = $id;
                $err_msg = $logic->err_msg;
            } else $success = true;
        }
        $logic->success = $success;
        if ($fail) {
            $err_msg = implode(',', $fail) . $err_msg;
            $logic->err_msg = $err_msg;
        }
        return $this->output($logic, $err_msg);
    }

    // 提交
    function submit(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'ids' => 'required',
        ]);
        $fail = [];
        $ids = explode(',', $params['ids']);
        $logic = new Order();
        $success = false;
        $err_msg = '';
        foreach ($ids as $id) {
            $params['id'] = $id;
            $res = $logic->submit($params);
            if (!$res) {
                $fail[] = $id;
                $err_msg = $logic->err_msg;
            } else $success = true;
        }
        $logic->success = $success;
        if ($fail) {
            $err_msg = implode(',', $fail) . $err_msg;
            $logic->err_msg = $err_msg;
        }
        return $this->output($logic, $err_msg);
    }

    // 审核
    function audit(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'ids' => 'required',
            'status' => 'required|in:2,3'
        ]);
        $fail = [];
        $ids = explode(',', $params['ids']);
        $logic = new Order();
        $success = false;
        $err_msg = '';
        foreach ($ids as $id) {
            $params['id'] = $id;
            $res = $logic->audit($params);
            if (!$res) {
                $fail[] = $id;
                $err_msg = $logic->err_msg;
            } else $success = true;
        }
        $logic->success = $success;
        if ($fail) {
            $err_msg = implode(',', $fail) . $err_msg;
            $logic->err_msg = $err_msg;
        }
        return $this->output($logic, $err_msg);
    }

    // 暂停
    function pause(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'ids' => 'required'
        ]);
        $fail = [];
        $ids = explode(',', $params['ids']);
        $logic = new Order();
        $success = false;
        $err_msg = '';
        foreach ($ids as $id) {
            $params['id'] = $id;
            $res = $logic->pause($params);
            if (!$res) {
                $fail[] = $id;
                $err_msg = $logic->err_msg;
            } else $success = true;
        }
        $logic->success = $success;
        if ($fail) {
            $err_msg = implode(',', $fail) . $err_msg;
            $logic->err_msg = $err_msg;
        }
        return $this->output($logic, $err_msg);
    }

    // 恢复
    function recovery(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'ids' => 'required'
        ]);
        $fail = [];
        $ids = explode(',', $params['ids']);
        $logic = new Order();
        $success = false;
        $err_msg = '';
        foreach ($ids as $id) {
            $params['id'] = $id;
            $res = $logic->recovery($params);
            if (!$res) {
                $fail[] = $id;
                $err_msg = $logic->err_msg;
            } else $success = true;
        }
        $logic->success = $success;
        if ($fail) {
            $err_msg = implode(',', $fail) . $err_msg;
            $logic->err_msg = $err_msg;
        }
        return $this->output($logic, $err_msg);
    }

    // 取消
    function cancel(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'ids' => 'required',
        ]);
        $fail = [];
        $ids = explode(',', $params['ids']);
        $logic = new Order();
        $success = false;
        $err_msg = '';
        foreach ($ids as $id) {
            $params['id'] = $id;
            $res = $logic->cancel($params);
            if (!$res) {
                $fail[] = $id;
                $err_msg = $logic->err_msg;
            } else $success = true;
        }
        $logic->success = $success;
        if ($fail) {
            $err_msg = implode(',', $fail) . $err_msg;
            $logic->err_msg = $err_msg;
        }
        return $this->output($logic, $err_msg);
    }

    // 指定配货
    function assign(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
            'warehouse_code' => 'required',
        ]);
        $logic = new Order();
        $data = $logic->assign($params);
        return $this->output($logic, $data);
    }

    // 查询
    function search(Request $request)
    {
        $logic = new Order();
        $data = $logic->search($request->all());
        return $this->success($data);
    }

    // 导出
    function export(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new Order();
            $res = $logic->searchWithDetail($params, true);
            return $res;
        });

        // $params = $request->all();
        // $params['size'] = 1000;
        // $logic = new Order();
        // $res = $logic->searchWithDetail($params, true);
        // return $this->exportOutput($res, ['D']);
    }

    // 导入模板
    function templete(Request $request)
    {
        $params = $request->all();
        $params['size'] = 1000;
        $data = [];
        $title = __('columns.wms_orders.table_title');
        // return view('templetes.order',['data' => $data]);
        $name = sprintf($title . '模板.xlsx', date('YmdHis'));
        return Excel::download(new ExportView('templetes.order', $data, $title, ['E']), $name);
    }


    // 退款登记商品明细
    function afterSaleDetail(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
            'detail_id' => 'required'
        ]);
        $logic = new Order();
        $data = $logic->afterSaleDetail($params);
        return $this->output($logic, $data);
    }

    // 退款登记
    function afterSale(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
            'refund_reason' => 'required',
            'deadline' => 'required',
            'details.*.id' => 'required',
            'details.*.return_num' => 'required',
            'details.*.apply_num' => 'required',
            // 'details.*.remark' => 'required',
        ]);
        $logic = new Order();
        $data = $logic->afterSale($params);
        return $this->output($logic, $data);
    }

    // 结算确认
    function settle(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'ids' => 'required',
        ]);
        $logic = new Order();
        $data = $logic->settle($params);
        return $this->success($data);
    }

    // 查询
    function statementSearch(Request $request)
    {
        $logic = new Order();
        $data = $logic->statementSearch($request->all());
        return $this->success($data);
    }

    // 导出
    function statementExport(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new Order();
            $res = $logic->statementSearch($params, true);
            return $res;
        });
        // $params = $request->all();
        // $params['size'] = 10000;

        // $logic = new Order();
        // $res = $logic->statementSearch($params, true);
        // return $this->exportOutput($res, ['F', 'H']);
    }

    // 查询
    function summarySearch(Request $request)
    {
        $logic = new Order();
        $data = $logic->summarySearch($request->all());
        return $this->success($data);
    }

    // 导出
    function summaryExport(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new Order();
            $res = $logic->summarySearch($params, true);
            return $res;
        });
        // $params = $request->all();
        // $params['size'] = 10000;
        // $logic = new Order();
        // $res = $logic->summarySearch($params, true);
        // return $this->exportOutput($res, []);
    }

    //修改旗帜
    public function editFlag(Request $request)
    {
        $data = $request->all();
        $this->validateParams($data, [
            'ids' => 'required',
            'flag' => 'required|integer|min:0|max:7',
        ]);
        $logic = new Order();
        $data = $logic->editFlag($data);
        return $this->success($data);
    }

    public function editMessage(Request $request){
        $data = $request->all();
        $this->validateParams($data, [
            'ids' => 'required',
        ]);
        $logic = new Order();
        $data = $logic->editMessage($data);
        return $this->success($data);
    }
}
