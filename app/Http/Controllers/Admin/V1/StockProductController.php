<?php

namespace App\Http\Controllers\Admin\V1;

use App\Exports\Export;
use App\Exports\StockProductExport;
use App\Imports\StockProductImport;
use App\Jobs\stockProduct;
use App\Logics\channel\STOCKX;
use App\Logics\StockProductLogic;
use App\Models\OperationLog;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class StockProductController extends BaseController
{
    /**
     * 商品导入
     *
     * @param Request $request
     */
    public function import(Request $request)
    {
        try {
            $params = ['admin_user_id' => $request->header('user_id'), 'batch_no' => date('YmdHis')];
            $params2 = StockProductLogic::stockxThresholdFormulas($params, true);

            $model = new StockProductImport($params2);
            Excel::import($model, $request->file('stock_product'));


            $params['action'] = 'stock-product-sync';
            stockProduct::dispatch($params)->onQueue('product');

            $remark = '批量导入，批次号：' . $params['batch_no'];
            OperationLog::add(OperationLog::TYPE_ADD, 0, '', $remark, $params['admin_user_id']);
            return $this->success([], sprintf('上传完成，处理中。成功%d条，失败%d条。', $model->success_num, $model->fail_num));
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }


    /**
     * 商品导出
     *
     * @param Request $request
     */
    public function export(Request $request)
    {
        $logic = new StockProductLogic();
        $data = $logic->exportData($request->all());
        $headers[] = [
            'good_name' => '商品名',
            'product_sn' => '货号',
            'properties' => '规格',
            'store_stock' => '在仓库存',
            'left_stock' => '在售库存',
            'store_house_name' => '所在仓库',
            'cost_price' => '加权成本',
            'finnal_price' => '到手价',
            'goat_threshold_price' => 'goat门槛价',
            'dw_threshold_price' => '得物门槛价',
            'stockx_threshold_price' => 'stockx门槛价',
            'carryme_threshold_price' => 'carryme门槛价',
            'bar_code' => '商品条码',
            'store_house_code' => '仓库编号',
            'status' => '上下架',
            'purchase_name' => '空卖名称',
            'purchase_url' => '空卖链接',
        ];
        $name = sprintf('慎独出价商品%s.xlsx', date('YmdHis'));

        $params = StockProductLogic::stockxThresholdFormulas([]);
        return Excel::download(new StockProductExport($headers, $data, $params), $name);
    }


    /**
     * 商品明细导出
     *
     * @param Request $request
     */
    public function exportDetail(Request $request)
    {
        $logic = new StockProductLogic();
        $data = $logic->exportDetail($request->all());
        $headers[] = [
            'good_name' => '商品名',
            'product_sn' => '货号',
            'properties' => '规格',
            'store_stock' => '在仓库存',
            'left_stock' => '在售库存',
            'status_txt' => '上架状态',
            'store_house_name' => '所在仓库',
            'cost_price' => '加权成本',
            'finnal_price' => '到手价',
            'goat_threshold_price' => 'goat门槛价',
            'goat_lowest_price_jpy' => 'goat最低价',
            'dw_threshold_price' => '得物门槛价',
            'dw_lowest_price_jpy' => '得物最低价',
            'stockx_threshold_price' => 'stockx门槛价',
            'stockx_lowest_price_jpy' => 'stockx最低价',
            'carryme_threshold_price' => 'carryme门槛价',
            'carryme_lowest_price_jpy' => 'carryme最低价',
            'bar_code' => '商品条码',
            'store_house_code' => '仓库编号',
            'status' => '上下架',
            'purchase_url' => '空卖链接',
        ];
        $name = sprintf('慎独出价商品明细%s.xlsx', date('YmdHis'));
        $params['detail'] = 1;
        $params = StockProductLogic::stockxThresholdFormulas($params);
        return Excel::download(new StockProductExport($headers, $data, $params), $name);
    }

    /**
     * 商品列表
     *
     * @param Request $request
     */
    public function list(Request $request)
    {
        $params = $request->input();
        $logic = new StockProductLogic();
        $data = $logic->list($params);
        return $this->success($data);
    }

    /**
     * 商品上下架
     * @param Request $request
     */
    public function update(Request $request)
    {
        $params = $request->all();
        $params['admin_user_id'] = $request->header('user_id');
        $this->validateParams($params, [
            'product_sn' => 'required',
            'status' => ['required', Rule::in(['shelf', 'take_down'])],
        ]);
        $logic = new StockProductLogic();
        $res = $logic->update($params);
        if(!$res){
            return $this->error('操作失败，请稍后再试');
        }
        return $this->success();
    }

    /**
     * 删除指定规格的商品出价
     *
     * @param Request $request
     * @return void
     */
    public function delBid(Request $request)
    {
        $params = $request->all();
        $params['admin_user_id'] = $request->header('user_id');
        $this->validateParams($params, [
            'stock_product_id' => 'required',
        ]);
        $logic = new StockProductLogic();
        $logic->delBid($params);
        return $this->success();
    }

    /**
     * 商品出价详情
     *
     * @param Request $request
     */
    public function detail(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'product_sn' => ['required']
        ]);
        $logic = new StockProductLogic();
        $data = $logic->bidDetail($params);
        return $this->success($data);
    }

    /**
     * 商品信息编辑
     *
     * @param Request $request
     */
    public function edit(Request $request)
    {
        $params = $request->all();

        $params['admin_user_id'] = $request->header('user_id');
        $rule1 = [
            'stock_product_id' => ['required', 'exists:stock_products,id'],
            'type' => ['required', Rule::in([1])],
        ];
        $rule2 = [
            'threshold_price' => 'required|integer|min:1000',
            'channel_code' => 'required',
        ];
        if (!$this->validateParamsAdmin($params, $rule1)) {
            return $this->error($this->params_error);
        }
        $params['channel_code'] = strtoupper($params['channel_code']);

        //编辑门槛价
        $logic = new StockProductLogic();
        if ($params['type'] == 1) {
            if (!$this->validateParamsAdmin($params, $rule2)) {
                return $this->error($this->params_error);
            }
            $logic->updateThresholdPrice($params);
            return $this->success([]);
        }
        return $this->success([]);
    }

    /*
     * 清空所有商品
     *
     * @param Request $request
     */
    public function clear(Request $request)
    {
        $params = $request->all();
        $params['admin_user_id'] = $request->header('user_id');
        StockProductLogic::clear($params);
        return $this->success([]);
    }
}
