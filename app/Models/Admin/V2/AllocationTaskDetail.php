<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use  App\Models\Admin\V2\ArrivalRegist;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;
use App\Models\Admin\V2\ObOrder;
use App\Models\Admin\V2\ShippingDetail;
use Illuminate\Support\Facades\DB;
use App\Logics\wms\AllocationTask;
use App\Logics\wms\Order;
use App\Logics\wms\Transfer;
use App\Logics\wms\Other;
use App\Logics\RedisKey;
use Illuminate\Support\Facades\Redis;

class AllocationTaskDetail extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_pre_allocation_detail'; //配货明细
    // protected $table = 'wms_allocation_task_detail'; //配货明细

    // protected $map = [
    //     'status' => [0 => '配货中', 1 => '已配货待复核', 2 => '已复核待发货', 4 => '已发货'],

    // ];

    protected $map;
    protected $appends = ['status_txt', 'users_txt'];

    public function __construct($params = [])
    {
        parent::__construct($params);
        $this->map = [
            'status' => [0 => __('status.in_distribution'), 1 => __('status.wait_review'), 2 => __('status.pending_shipment'), 4 => __('status.shipped')],
        ];
    }

    public function getUsersTxtAttribute($key)
    {
        $tenant_id = request()->header('tenant_id');
        $res['reviewer_user'] = $this->getAdminUser($this->reviewer_id, $tenant_id);
        $res['receiver_user'] = $this->getAdminUser($this->receiver_id, $tenant_id);
        return $res;
    }

    public function product()
    {
        return $this->belongsTo(ProductSpecAndBar::class, 'bar_code', 'bar_code');
    }
    public function obOrder($request_code)
    {
        return (new ObOrder())->where('request_code', $request_code)->first();
    }

    public static function add($data)
    {
        return self::insert($data);
    }


    public static function invUpdate($params)
    {
        request()->headers->set('tenant_id', $params['tenant_id']);
        $type = $params['type'];
        if ($type == 1) SupInv::supInvUpdate(...$params['sup_data']);
        if ($type == 2) Inventory::totalInvUpdate(...$params['total_data']);
        if ($type == 3) {
            SupInv::supInvUpdate(...$params['sup_data']);
            Inventory::totalInvUpdate(...$params['total_data']);
        }
    }

    public static function sendUpdate($params)
    {
        request()->headers->set('tenant_id', $params['tenant_id']);
        if (request()->header('user_id') == null) request()->headers->set('user_id', 0);
        $ob_item  = ObOrder::where('request_code', $params['request_code'])->first();
        if (!$ob_item) return;
        if ($ob_item->type == 1) {
            Order::sendOut($ob_item->third_no);
        }
        // 调拨单
        if ($ob_item->type == 2) {
            Transfer::sendOut($ob_item);
        }
        // 其他出库申请单
        if ($ob_item->type == 3) {
            Other::sendOut($ob_item);
        }
    }

    //逐件复核
    public function reviewOne($warehouse_code, $uniq_code, $is_print = null)
    {
        //返回值   单据编码 三方单据编码  物流单据 已扫描总数/剩余应发总数 已扫描产品明细 发货明细



        $item = $this::where('uniq_code', $uniq_code)->whereIn('alloction_status', [5, 6])->where('cancel_status', 0)->first();
        if (empty($item)) return [false, __('status.uniq_invalid')];
        if ($item->alloction_status == 6) $msg = __('response.uniq_scan_repeat');
        else $msg = '';
        if ($item->warehouse_code != $warehouse_code) return [false, __('status.uniq_not_match')];
        //查看出库单状态是否已取消
        $request_code = $item->request_code;
        $obOrder = ObOrder::where('request_code', $request_code)->first();
        if (!$obOrder) return [false, __('status.ob_not_exists')];
        if ($obOrder->request_status == 5 || $obOrder->status == 5) {
            (new AllocationTask())->cancelWhenReview($obOrder);
            return [false, __('status.cancel_shelving')];
        }

        //修改状态
        //更新复核人和时间
        $this->startTransaction();
        $item->alloction_status = 6;
        $item->reviewer_id = request()->header('user_id');
        $item->review_at = date('Y-m-d H:i:s');
        $res = $item->save();
        if (!$res) {
            DB::rollback();
            return [false, __('status.review_fail')];
        }

        //出库单

        $count = $obOrder->payable_num;
        $scan_item = $this::where('request_code', $request_code)->where('alloction_status', 6);
        $scan_count = $scan_item->count(); //已复核待发货
        //已扫描明细
        // $scan_detail = $scan_item->select('request_code', 'bar_code', 'quality_type', 'quality_level', DB::raw(' sum(actual_num) as actual_num '))->groupBy('request_code', 'bar_code', 'quality_level')->orderBy('id', 'desc')->get()->load('product:id,product_id,sku,bar_code,spec_one,tenant_id')->toArray();;
        $scan_detail = $scan_item->select('request_code', 'bar_code', 'uniq_code', 'quality_type', 'quality_level', DB::raw(' sum(actual_num) as actual_num '))->groupBy('request_code', 'bar_code', 'quality_level', 'uniq_code')->orderBy('id', 'desc')->get()->load('product:id,product_id,sku,bar_code,spec_one,tenant_id')->toArray();;
        // $scan_detail = $this->inventoryIns()::whereIn('uniq_code',$scan_uniq)->select('bar_code',DB::raw('count(*) as count'),'quality_type','quality_level')->groupBy('bar_code')->get()->toArray();

        $deliver_no = empty($obOrder->deliver_no) ? $obOrder->deliver_type : $obOrder->deliver_no;

        //发货明细

        $pre_detail_model = $this::where('request_code', $request_code)->where('warehouse_code', $warehouse_code);
        $send_detail = $pre_detail_model->select('request_code', 'bar_code', 'quality_level', DB::raw('sum(if(alloction_status=6,1,0)) as scaned_num ,sum(pre_num) as pre_num ,sum(if(alloction_status=5,1,0)) as left_num, sum(actual_num) as actual_num ,sum(cancel_num) as cancel_num'))->groupBy('request_code', 'bar_code', 'quality_level')->orderBy('id', 'desc')->get()->load('product:id,product_id,sku,bar_code,spec_one,tenant_id')->toArray();

        $group_no = $item->task_strategy_code ?? '';
        $group_name = '';
        if ($group_no) {
            $group_item = WmsTaskStrategy::where('code', $group_no)->where('status', 1)->first();
            $group_name = $group_item ? $group_item->name : '';
        }
        $data = [
            'payable_num' => $count, //应发总数
            'scan_num' => $scan_count, //已扫描总数
            'request_code' => $item->request_code, //单据编码
            'third_no' => $obOrder->third_no, //三方单据编码
            'deliver_no' => $deliver_no, //物流单据
            'scan_detail' => $scan_detail, //已扫描产品明细
            'erp_no' => $obOrder->erp_no,
            'send_detail' => $send_detail, //发货明细
            'request_info' => $obOrder->toArray(),
            'group_no' => $group_no,
            'group_name' => $group_name,

        ];



        //判断是否勾选匹配即打印发货
        if ($is_print && $is_print != 'false') {
            //判断本次订单是不是所有都是已复核待发货状态 , /如果是,发货
            if ($pre_detail_model->where('alloction_status', '<>', 6)->count() == 0) {
                $send_res = $this->sendGoods($request_code, false);
                //打印快递单
                if ($send_res[0]) {
                    DB::commit();
                    return [true, ['is_print' => 1, 'repeat_msg' => $msg, 'is_send' => 1, 'data' => $data, 'pdf_url' => ShippingOrders::printEdNo($request_code)]];
                } else {
                    return [true, ['is_print' => 1, 'repeat_msg' => $msg, 'is_send' => 0, 'data' => $data, 'pdf_url' => ShippingOrders::printEdNo($request_code)]];
                }
            }
        }
        return $this->endTransaction([true], ['data' => $data, 'repeat_msg' => $msg, 'is_print' => 0, 'pdf_url' => [false, '']]);
    }

    //发货
    public  function  sendGoods($request_code, $trans = true)
    {
        $item = $this::where('request_code', $request_code)->where('alloction_status', 6);
        $collect = $item->get();
        $send_count = $collect->sum('actual_num');
        $ob_item = $this->obOrder($request_code);
        if ($ob_item->status == ObOrder::STATUS_CANCELED) {
            // if($trans) DB::rollback();
            return [false, __('status.order_cancel_shelving')];
        }
        if ($ob_item->request_status != 3 || $ob_item->status != 2) return [false, __('status.ob_status_not_send')];
        $count = $ob_item->payable_num;
        if ($send_count != $count) return [false, __('status.wait_review')];
        if ($trans) DB::beginTransaction();
        //需求单写入redis 防止短时间内重复发货
        if (Redis::get('wms:sendGoods'.$request_code)) return [false, __('tips.option_repeat')];
        Redis::setex('wms:sendGoods'.$request_code, 10, 1);
        $uniq_codes = $item->pluck('uniq_code')->toArray();
        $total_inv_data = $this::where('request_code', $request_code)->where('alloction_status', 6)->select('warehouse_code', 'uniq_code', 'bar_code', 'location_code')->groupBy('warehouse_code', 'bar_code')->get()->toArray();
        //更改配货订单详情
        $res['pre_detail_send'] = $item->update(['alloction_status' => 7]);
        //发货  生成发货单
        list($res['add_send_order'], $ship_code) = ShippingOrders::add($collect->modelKeys());


        // 商品库存销售状态
        Inventory::whereIn('uniq_code', $uniq_codes)->update(['sale_status' => Inventory::SALE_STATUS_SHIPPED, 'in_wh_status' => Inventory::OUT,]);
        //供应商更新
        // SupInv::supInvUpdate($uniq_codes);


        //更新出库需求单状态
        $user_id = request()->header('user_id');
        $time = date('Y-m-d H:i:s');

        if ($ob_item) {
            $res['ob_detail'] = $ob_item->load('details')->details()->where('status', 2)->update([
                'status' => 3,
                'actual_num' => DB::raw('payable_num'),
                'ship_code' => $ship_code,
                'shipper_id' => $user_id,
                'shipped_at' => $time,
                'admin_user_id' => $user_id,
                //修改实发数量
                // 'actual_num'=>
            ]);
            $ob_item->request_status = 4;
            $ob_item->status = 4;
            $ob_item->actual_num = $send_count;
            $ob_item->admin_user_id = $user_id;

            $res['ob_status'] = $ob_item->save();

            // $r_data =$ob_item->third_no;
            // 销售订单
            // if ($ob_item->type == 1) {
            //     // $r_data =$ob_item->third_no;
            //     Order::sendOut($ob_item->third_no);
            // }
            // // 调拨单
            // if ($ob_item->type == 2) {
            //     Transfer::sendOut($ob_item);
            // }
            // // 其他出库申请单
            // if ($ob_item->type == 3) {
            //     Other::sendOut($ob_item);
            // }


        } else {
            $res['ob_status'] = null;
        }

        // foreach ($collect as $log) {
        //     //库存流水更新
        //     WmsStockLog::add(WmsStockLog::ORDER_SENDOUT, $log['uniq_code'], $ship_code, ['origin_value' => $log['location_code']]);
        // }
        $this->addStockLogAsync($collect, $ship_code);

        $c = array_filter($res, function ($v) {
            return empty($v);
        });
        if (empty($c)) {
            if ($trans) DB::commit();
            //发货
            $send_redis = [
                'params' => ['tenant_id' => request()->header('tenant_id'), 'type' => $ob_item->type, 'request_code' => $request_code],
                'class' => 'App\Models\Admin\V2\AllocationTaskDetail',
                'method' => 'sendUpdate',
                'time' => date('Y-m-d H:i:s'),
            ];
            Redis::rpush(RedisKey::QUEUE_AYSNC_HADNLE, json_encode($send_redis));
            //供应商更新
            $inv_update_redis = [
                'params' => ['tenant_id' => request()->header('tenant_id'), 'type' => 1, 'sup_data' => [$uniq_codes]],
                'class' => 'App\Models\Admin\V2\AllocationTaskDetail',
                'method' => 'invUpdate',
                'time' => date('Y-m-d H:i:s'),
            ];
            Redis::rpush(RedisKey::QUEUE2_AYSNC_HADNLE, json_encode($inv_update_redis));

            //总库存更新
            foreach ($total_inv_data as $d) {
                // Inventory::totalInvUpdate($d['warehouse_code'], $d['bar_code']);
                $inv_update_redis = [
                    'params' => ['tenant_id' => request()->header('tenant_id'), 'type' => 2, 'total_data' => [$d['warehouse_code'], $d['bar_code']]],
                    'class' => 'App\Models\Admin\V2\AllocationTaskDetail',
                    'method' => 'invUpdate',
                    'time' => date('Y-m-d H:i:s'),
                ];
                Redis::rpush(RedisKey::QUEUE2_AYSNC_HADNLE, json_encode($inv_update_redis));
            }
            return [true, __('status.send_success')];
        } else {
            if ($trans) DB::rollBack();
            return [false, __('status.send_fail')];
        }
    }

    function addStockLogAsync($collect, $ship_code)
    {
        $update = [
            'params' => ['tenant_id' => request()->header('tenant_id'), 'user_id' => request()->header('user_id'), 'ship_code' => $ship_code, 'collect' => objectToArray($collect)],
            'class' => 'App\Models\Admin\V2\AllocationTaskDetail',
            'method' => 'addStockLog',
            'time' => date('Y-m-d H:i:s'),
        ];
        Redis::rpush(RedisKey::QUEUE2_AYSNC_HADNLE, json_encode($update));
    }

    function addStockLog($params)
    {

        $ship_code = $params['ship_code'];
        $collect = $params['collect'];
        foreach ($collect as $log) {
            //库存流水更新
            WmsStockLog::add(WmsStockLog::ORDER_SENDOUT, $log['uniq_code'], $ship_code, ['origin_value' => $log['location_code']]);
        }
    }

    //复核重置
    public function reviewReset($request_code)
    {
        $item = $this::where('request_code', $request_code)->where('alloction_status', 6);
        if ($item->doesntExist()) return [true, ''];
        $update = [
            'alloction_status' => 5,
            'reviewer_id' => 0,
            'review_at' => null
        ];
        $row = $item->update($update);
        return [$row, ''];
    }
}
