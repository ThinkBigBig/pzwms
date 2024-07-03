<?php

namespace App\Logics\wms;

use App\Logics\BaseLogic;
use App\Models\Admin\V2\ObOrder;
use App\Models\Admin\V2\preAllocationDetail;
use App\Models\Admin\V2\ShippingDetail;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * 其他出库申请
 */
class Other extends BaseLogic
{
    // 发货
    static function sendOut(ObOrder $request)
    {
        $other = $request->otherOut;
        if (!$other) return;
        if ($other->doc_status != 2) return;

        $total = 0;
        $details = $other->details;
        foreach ($details as $detail) {
            $where = [
                'bar_code' => $detail->bar_code,
                'batch_no' => $detail->batch_no,
                'alloction_status' => preAllocationDetail::DELIVERED,
                'cancel_status' => 0,
            ];
            $actual_num = preAllocationDetail::where($where)->where('request_code', $request->request_code)->sum('pre_num');
            $detail->update([
                'send_num' => $actual_num,
            ]);
            $total += $actual_num;
        }

        $send_status = $other->total > $total ? 3 : 4;// 3-部分发货 4-已发货
        $update = [
            'send_status' => $send_status,
            'send_num' => $total,
        ];
        if($send_status==4)$update['doc_status']=4;
        $other->update($update);
        $details = $other->details;
    }
}
