<?php

namespace App\Http\Controllers\Admin\V1;

use App\Exports\Export;
use App\Http\Controllers\Admin\V1\BaseController;
use App\Jobs\stockProduct;
use App\Logics\BaseLogic;
use App\Logics\channel\STOCKX;
use App\Logics\OrderLogic;
use App\Logics\Robot;
use App\Models\ChannelOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Picqer\Barcode\BarcodeGeneratorPNG;

class ChannelOrderController extends BaseController
{

    /**
     * 订单取消
     *
     * @param Request $request
     */
    public function cancel(Request $request)
    {
        $params = $request->all();
        $params['admin_user_id'] = Auth::id();
        $this->validateParams($params, [
            'order_id' => ['required', 'exists:channel_order,id']
        ]);

        $logic  = new OrderLogic();
        $logic->orderCancel($params);
        return $this->output($logic, []);
    }

    /**
     * 订单确认
     *
     * @param Request $request
     */
    public function confirm(Request $request)
    {
        $params = $request->all();
        $params['admin_user_id'] = Auth::id();
        $this->validateParams($params, [
            'order_id' => ['required', 'exists:channel_order,id']
        ]);

        $logic  = new OrderLogic();
        $res = $logic->businessConfirm($params);
        return $this->output($logic, $res);
    }

    /**
     * 订单批量确认
     *
     * @param Request $request
     */
    public function batchConfirm(Request $request)
    {
        $params = $request->all();
        $params['admin_user_id'] = Auth::id();
        $this->validateParams($params, [
            'order_ids' => ['required']
        ]);

        $order_ids = explode(',', $params['order_ids']);
        if ($order_ids) {
            ChannelOrder::whereIn('id', $order_ids)->where(['status' => ChannelOrder::STATUS_CREATED, 'confirm_progress' => ChannelOrder::PROGRESS_DEFAULT])->update(['confirm_progress' => ChannelOrder::PROGRESS_PENDING]);
        }

        stockProduct::dispatch([
            'action' => 'order-confirm',
            'order_ids' => explode(',', $params['order_ids']),
            'admin_user_id' => $params['admin_user_id'],
        ])->onQueue('product');

        $logic  = new OrderLogic();
        $success_num = 0;
        $fail_num = 0;
        $fail_list = [];
        $logic->success = true;
        return $this->output($logic, compact('success_num', 'fail_num', 'fail_list'));
    }

    /**
     * 订单发货
     *
     * @param Request $request
     */
    public function sendOut(Request $request)
    {
        $params = $request->all();
        $params['admin_user_id'] = Auth::id();
        $this->validateParams($params, [
            'order_id' => ['required', 'exists:channel_order,id']
        ]);

        $logic  = new OrderLogic();
        $res = $logic->platformConfirm($params);
        return $this->output($logic, $res);
    }

    /**
     * 根据id批量发货
     *
     * @param Request $request
     */
    public function batchSendOut(Request $request)
    {
        $params = $request->all();
        $params['admin_user_id'] = Auth::id();
        $logic  = new OrderLogic();
        $order_ids = explode(',', $params['order_ids']);
        $errs = [];
        $success = 0;
        foreach ($order_ids as $order_id) {
            $params['order_id'] = $order_id;
            // $params['stock_source'] = ChannelOrder::SOURCE_STOCK;
            $logic->platformConfirm($params);
            if ($logic->success) {
                $success++;
            } else {
                $errs[] = sprintf('订单%s发货失败，原因%s', $order_id, $logic->err_msg);
            }
        }

        $logic->success = true;
        return $this->output($logic, [
            'success_num' => $success,
            'fail_data' => $errs
        ]);
    }

    /**
     * 订单信息同步
     *
     * @param Request $request
     */
    public function syncOrderStatus(Request $request)
    {
        $params = $request->all();
        $params['admin_user_id'] = Auth::id();
        $this->validateParams($params, [
            'order_id' => ['required', 'exists:channel_order,id']
        ]);

        $logic = new OrderLogic();
        $res = $logic->orderSync($params);
        return $this->output($logic, $res);
    }

    /**
     * 订单搜索
     */
    public function search(Request $request)
    {
        $params = $request->all();
        $logic = new OrderLogic();
        $res = $logic::stockOrderList($params);
        return $this->output($logic, $res);
    }

    /**
     * 订单导出
     *
     * @param Request $request
     */
    public function export(Request $request)
    {
        $params = $request->all();
        $params['size'] = 50000;
        $logic = new OrderLogic();
        $res = $logic::stockOrderList($params);

        $headers[] = [
            'order_id' => '订单号',
            'order_no' => '三方订单号',
            'channel_code' => "来源",
            'product_name' => "商品名称",
            'spu_logo' => "商品图",
            'product_sn' => "货号",
            'size' => "规格",
            'order_price' => "售价",
            'bidding_price' => "出价",
            'cost_price' => "成本",
            'paysuccess_time' => "下单时间",
            'business_confirm_time' => "确认时间",
            'platform_confirm_time' => "发货时间",
            'completion_time' => "完成时间",
            'close_time' => "关闭时间",
            'cancel_time' => "取消时间",
            'status_txt' => "订单状态",
            'is_abnormal' => "异常单",
            'purchase_status_txt' => '是否采购',
            'purchase_url' => '空卖链接',
            'purchase_name' => '空卖名称',
        ];
        $export = new Export($headers, $res['data']);
        $name = sprintf('库存出价订单%s.xlsx', date('YmdHis'));
        return Excel::download($export, $name);
    }

    public function batchExportDeliver(Request $request)
    {
        set_time_limit(0);

        // 根据id获取信息
        $params = $request->all();
        $params['size'] = 1000;
        $params['deliver'] = 1;
        $params['admin_user_id'] = Auth::id();
        Robot::sendNotice(sprintf('批量导出发货单，params:%s', json_encode($params)));
        $logic = new OrderLogic();
        $res = $logic::stockOrderList($params);
        $array = $res['data'];
        if (!$array) {
            // Redis::del(RedisKey::ORDER_EXPORT_DELIVER_LOCK);
            header("Content-type: text/html; charset=utf-8");
            echo "<h3>没有可下载的物流单<h3>";
            echo "<script>
            function close_tag(){
                window.opener=null;
                window.close();
            }
            setTimeout(close_tag,2000);
            </script>";
            exit();
        }

        header("Content-type: text/html; charset=utf-8");

        $order_ids = [];
        $tmpFile = mkdirs(public_path('/upload/temp'), '');  //临时文件
        $zip = new \ZipArchive();  //php内置的压缩类
        $zip->open($tmpFile, \ZipArchive::CREATE);
        $logic = new BaseLogic();
        foreach ($array as $value) {
            $channel_code = explode(' ', $value['channel_code'])[0];
            $dispatch_num = $value['dispatch_num_print'];
            if (!$dispatch_num) continue;

            if (in_array($channel_code, ['DW', 'CARRYME'])) {
                $params = [
                    'product_name' => $value['product_name'],
                    'size' => $value['size'],
                    'order_no' => $value['order_no'],
                    'product_sn' => $value['product_sn'],
                    'dispatch_num' => $dispatch_num,
                    'paysuccess_time' => Carbon::parse($value['paysuccess_time'])->format('Y-m-d H:i:s'),
                    'token' => session("admin_token"),
                ];
                $url = sprintf("%s?%s", url('admin/order/deliver'), http_build_query($params));
                $fileContent = $logic->html2pdf(file_get_contents($url));
            } elseif (in_array($channel_code, ['GOAT', 'STOCKX'])) {
                $fileContent = file_get_contents($value['dispatch_num_print']);
            }

            $name = trim($value['order_no']);
            if ($channel_code  == STOCKX::$code) {
                $name = str_replace('-', '', $name);
            }
            $zip->addFromString($name . '.pdf', $fileContent);  //将文件循环压缩到压缩包
            $order_ids[] = $value['order_id'];
        }
        $zip->close();
        if ($order_ids) {
            // 批量确认发货
            stockProduct::dispatch(['order_ids' => $order_ids, 'action' => 'order-send-out', 'admin_user_id' => $params['admin_user_id'] ?? 0])->onQueue('product');
        }

        header('Content-Type: application/zip');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=" . date('YmdHis') . ".zip");
        header('Content-Length: ' . filesize($tmpFile));
        ob_end_clean();
        readfile($tmpFile);
        unlink($tmpFile);
    }



    /**
     * 得物 CARRYME 虚拟物流费发货单页面展示
     *
     * @param Request $request
     */
    public function deliver(Request $request)
    {
        $generator = new BarcodeGeneratorPNG();
        $barcode = $generator->getBarcode($request->get('dispatch_num', ''), $generator::TYPE_CODE_128, 2, 30, [3, 3, 3]);
        $blade = 'orders.deliver';
        $paysuccess_time = $request->get('paysuccess_time', '');
        if ($paysuccess_time && $paysuccess_time >= '2023-09-19 00:00:00') {
            $blade = 'orders.deliverV2';
        }

        return view($blade, ['info' => [
            'product_name' => $request->get('product_name', ''),
            'size'  => $request->get('size', ''),
            'product_sn' => $request->get('product_sn', ''),
            'order_no' => $request->get('order_no', ''),
            'time' => date('Y-m-d H:i:s'),
            'barcode' => base64_encode($barcode),
            "dispatch_num" => $request->get('dispatch_num', ''),
        ]]);
    }

    /**
     * 更新订单信息
     * - 空卖订单标记采购状态
     *
     * @param Request $request
     */
    public function updateInfo(Request $request)
    {
        $params = $request->all();
        $params['admin_user_id'] = Auth::id();
        $this->validateParams($params, [
            'order_id' => ['required', 'exists:channel_order,id']
        ]);

        $logic = new OrderLogic();
        $res = $logic->updateInfo($params);
        return $this->output($logic, $res);
    }
}
