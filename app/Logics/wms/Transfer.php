<?php

namespace App\Logics\wms;

use App\Logics\BaseLogic;
use App\Models\Admin\V2\ObOrder;
use App\Models\Admin\V2\preAllocationDetail;
use App\Models\Admin\V2\WithdrawUniqLog;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * 调拨单
 */
class Transfer extends BaseLogic
{
    // 发货
    static function sendOut(ObOrder $request)
    {
        $transfer = $request->transfer;
        if (!$transfer) return;
        if ($transfer->doc_status != 2) return;

        $total = 0;
        $details = $transfer->details;
        $bar_codes=[];
        foreach ($details as $detail) {
            $bar_codes[]=  $detail->bar_code;
            $where = [
                'bar_code' => $detail->bar_code,
                'batch_no' => $detail->batch_no,
                'alloction_status' => preAllocationDetail::DELIVERED,
                'cancel_status' => 0,
            ];
            if($detail->uniq_code) $where['uniq_code'] = $detail->uniq_code;
            $actual_num = preAllocationDetail::where($where)->where('request_code', $request->request_code)->sum('pre_num');
            $detail->update([
                'send_num' => $actual_num,
            ]);
            $total += $actual_num;
        }

        $send_status = $transfer->total > $total ? 3 : 4; // 3-部分发货 4-已发货
        $transfer->update([
            'send_status' => $send_status,
            'send_num' => $total,
        ]);
        //退调货获取唯一码明细
        // $bar_codes = $transfer->details->pluck('bar_code')->toArray();
        // list($uniq_log, $msg) = 
        WithdrawUniqLog::getProData($transfer->tr_code, $bar_codes, $transfer->type);
        // if (!$uniq_log) return [false, '添加唯一码明细失败:' . $msg];
        $details = $transfer->details;
    }

    // 给异步队列调用
    function transferSendOut($params)
    {
        $request_code = $params['request_code'];
        $request = ObOrder::where('request_code', $request_code)->first();
        if($request){
            self::sendOut($request);
        }
    }
}
