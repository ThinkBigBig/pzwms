<?php

namespace App\Http\Controllers\PDA;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\Admin\v2\Inbound\ArrivalRegistController as adminArr;
use App\Logics\RedisKey;
use App\Logics\wms\Receive;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ArrivalRegistController extends BaseController
{
    //新增
    public function add(Request $request)
    {
        $controller = new adminArr($request);
        return  $controller->BaseCreate($request);
    }
    //详情
    public function info(Request $request)
    {

        $arr_id = $request->get('id');
        $builder = DB::table('wms_arrival_regist')->where('id', $arr_id)->where('tenant_id', ADMIN_INFO['tenant_id'])->where('warehouse_code', $this->warehouse_code)->where('doc_status', 1)->whereIn('arr_status', [2, 3]);

        $item = $builder->select('id',  'arr_name', 'log_product_code', 'log_number', 'arr_code', 'arr_num', 'recv_num', 'arr_status', 'tenant_id', 'remark')->first();
        if (!$item) return $this->error('登记单不存在');
        $log_item = DB::table('wms_logistics_products')->where('product_code', $item->log_product_code)->where('tenant_id', ADMIN_INFO['tenant_id'])->where('status', 1)->first();
        $item->log_product_name = empty($log_item->product_name) ? '' : $log_item->product_name;
        return  $this->success($item);
    }

    //入库单
    public function ibList(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, ['arr_type' => 'required']);
        $type = $params['arr_type'];
        $builder = DB::table('wms_ib_order')->where('ib_type', $type)->where('tenant_id', ADMIN_INFO['tenant_id'])->where('warehouse_code', $this->warehouse_code)->where('doc_status', 1)->where('recv_status', 1);

        $item = $builder->select('id',  'sup_id', 'source_code', 'ib_code', 'third_no', 'deliver_no', 're_total', 'tenant_id', 'remark')->get();
        return $this->success($item);
    }


    // 已扫描明细
    public function scanDetail(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'arr_id' => 'required',
            'scan_type' => 'required|in:0,1',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new Receive();
        $res = $logic->arrInfo($params);
        return $this->output($logic, $res);
    }

    // 检查条形码
    function checkBarCode(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'bar_code' => 'required',
            'scan_type' => 'required|in:0,1',
        ]);
        $logic = new Receive();
        $res = $logic->scanBarCode($params);
        return $this->output($logic, $res);
    }

    // 扫码收货
    function scan(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'arr_id' => 'required',
            'bar_code' => 'required',
            'scan_type' => 'required|in:0,1',
        ]);
        if ($params['scan_type'] == 0) {
            $this->validateParams($params, [
                'uniq_code' => 'required',
            ]);
        }
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new Receive();
        $res = $logic->scan($params);
        return $this->output($logic, $res);
    }

    // 扫码收货-减扫
    function subScan(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'arr_id' => 'required',
            'bar_code' => 'required',
            'scan_type' => 'required|in:0,1',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        if ($params['scan_type'] == 0) {
            $this->validateParams($params, [
                'uniq_codes' => 'required|array',
            ]);
        }
        if ($params['scan_type'] == 1) {
            $this->validateParams($params, [
                'num' => 'required',
                'quality_level' => 'required',
                'recv_unit' => 'required|in:0,1,2',
            ]);
        }
        $logic = new Receive();
        $res = $logic->subScan($params);
        return $this->output($logic, $res);
    }

    // 确认收货
    function confirm(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'arr_id' => 'required',
            'products' => 'required|array',
            'scan_type' => 'required|in:0,1',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $lock_key = RedisKey::lockArrConfirm($params['arr_id']);
        if (!Redis::setnx($lock_key, '')) {
            return $this->error('相同的登记单正在确认收货，请稍后再试');
        }

        $logic = new Receive();
        try {
            if ($params['scan_type'] == 0) {
                $this->validateParams($params, [
                    'products.*.bar_code' => 'required',
                    'products.*.uniq_code' => 'required',
                    'products.*.sku' => 'required',
                ]);
                $res = $logic->uniqConfirm($params);
            }
            if ($params['scan_type'] == 1) {
                $this->validateParams($params, [
                    'products.*.bar_code' => 'required',
                    'products.*.sku' => 'required',
                    'products.*.quality_level' => 'required',
                    'products.*.num' => 'required',
                ]);
                $res = $logic->normalConfirm($params);
            }
        } catch (Exception $e) {
            $logic->setErrorMsg($e->getMessage());
        } finally {
            Redis::del($lock_key);
        }
        return $this->output($logic, $res);
    }

    // 确认收货
    function confirm3(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'arr_id' => 'required',
            'products' => 'required|array',
            'scan_type' => 'required|in:0,1',
        ]);
        $lock_key = RedisKey::lockArrConfirm($params['arr_id']);
        if (!Redis::setnx($lock_key, date('Y-m-d H:i:s'))) {
            return $this->error('相同的登记单正在确认收货，请稍后再试');
        }

        $logic = new Receive();
        try {
            if ($params['scan_type'] == 0) {
                $this->validateParams($params, [
                    'products.*.bar_code' => 'required',
                    'products.*.uniq_code' => 'required',
                    'products.*.sku' => 'required',
                ]);
                $res = $logic->uniqConfirmV3($params);
            }
            if ($params['scan_type'] == 1) {
                $this->validateParams($params, [
                    'products.*.bar_code' => 'required',
                    'products.*.sku' => 'required',
                    'products.*.quality_level' => 'required',
                    'products.*.num' => 'required',
                ]);
                $res = $logic->normalConfirm($params);
            }
        } catch (Exception $e) {
            $logic->setErrorMsg($e->getMessage());
        } finally {
            Redis::del($lock_key);
        }
        return $this->output($logic, $res);
    }
}
