<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\Warehouse;
use App\Models\Admin\V2\UniqCodePrintLog;
use Illuminate\Support\Facades\DB;

class WmsOptionLog extends wmsBaseModel
{
    use HasFactory;
    // use SoftDeletes;
    protected $table = 'wms_option_log';


    const QC = 2; //质检
    const PUTAWAY = 3; //上架
    const ALLOCATION_TASK_STARTEGY = 4; //波次分组策略
    const CHECK_REQUEST = 5; //盘点申请单
    const CHECK = 6; //盘点单
    const DIFFERENCE = 7; //差异处理记录
    const BILL = 8; //盘盈亏单
    const MOVE = 9; //移位单

    const DJD = 1; //到货登记
    const RKD = 10; //入库单
    const SHD = 11; //收货单
    const CKD = 12; //出库单

    const PHCL = 13; //配货策略
    const PHDD = 14; //配货订单
    const FHD = 15; //发货单
    const DBSQD = 16; //调拨申请单
    const QTRKSQD = 17; //其他入库申请单
    const QTCKSQD = 18; //其他出库申请单

    const ORDER = 19;//销售订单
    const SHGD = 20;//售后单
    const CG  = 21;//采购
    const JMD  = 22;//寄卖单
    const JMDJS  = 23;//寄卖结算账单
    const TXSQD  = 24;//提现申请

    /**
     * 操作日志
     * @param int $type 操作类型
     * @param string $doc_code 单据编码
     * @param string $option 操作类型简述
     * @param string $desc 描述
     * @param array $detail 详情日志
     * @return bool
     */
    public static function add($type, $doc_code, $option, $desc, $detail)
    {

        $data['type'] = $type;
        $data['doc_code'] = $doc_code;
        $data['option'] = $option;
        $data['desc'] = $desc;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['admin_user_id'] = request()->header('user_id');
        $data['admin_name'] = request()->header('username');
        $data['detail'] = json_encode($detail,JSON_UNESCAPED_UNICODE);
        $data['tenant_id'] = request()->header('tenant_id');
        return DB::table('wms_option_log')->insert($data);
    }

    public static function list($type, $doc_code)
    {
        $res = self::where('type', $type)->where('doc_code', $doc_code)->orderBy('created_at', 'desc')->orderBy('id', 'desc')->get();
        $res->map(function ($item) {
            return $item->detail = json_decode($item->detail, 1);
        });
        return $res;
    }
}
