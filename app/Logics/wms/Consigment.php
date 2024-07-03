<?php

namespace App\Logics\wms;

use App\Logics\BaseLogic;
use App\Logics\RedisKey;
use App\Logics\Robot;
use App\Models\Admin\V2\ConsignSettleRule;
use App\Models\Admin\V2\Inventory;
use App\Models\Admin\V2\RecvDetail;
use App\Models\Admin\V2\Supplier;
use App\Models\Admin\V2\WmsAfterSaleOrderDetail;
use App\Models\Admin\V2\WmsConsigmentSettlement;
use App\Models\Admin\V2\WmsConsigmentSettlementRule;
use App\Models\Admin\V2\WmsOptionLog;
use App\Models\Admin\V2\WmsOrderDetail;
use App\Models\Admin\V2\WmsShop;
use App\Models\Admin\V2\WmsWithdrawRequest;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * 寄卖
 */
class Consigment extends BaseLogic
{

    // 寄卖结算单查询 
    function billSearch($params, $export = false)
    {
        $model = new WmsConsigmentSettlement();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            $permission = ADMIN_INFO['data_permission'];
            $warehouse_names = $permission['warehouse_name']??[];
            if($warehouse_names){
                $model=$model->where(function($query) use($warehouse_names){
                    $query->whereIn('send_warehouse_name',$warehouse_names)->orWhereIn('return_warehouse_name',$warehouse_names);
                });
            }
            return $model->with(['createUser'])->orderBy('id', 'desc');
        });
        return $list;
    }

    /**
     * 订单生成寄卖结算账单
     *
     * @param WmsOrder $order
     */
    static function addBillByOrder($order)
    {
        // 订单未发货，暂不生成结算账单
        if ($order->deliver_status != 3) return;

        $details = $order->details;
        if (!$details) return;

        $stocks = Inventory::where(['warehouse_code' => $order->warehouse_code, 'sale_status' => 4, 'inv_type' => 1, 'lock_code' => $order->code])->pluck('uniq_code');
        // 非寄卖商品，不添加账单
        if (!$stocks->count()) return;

        $shop = WmsShop::where('code', $order->shop_code)->first();

        $arr = [];
        foreach ($details as $detail) {
            $items = $detail->items;
            $tmp = [];
            foreach ($items as $item) {
                if (!in_array($item->uniq_code, $stocks->toArray())) continue;
                $tmp[$item->sup_id][] = $item->uniq_code;
            }
            if (!$tmp) continue;
            foreach ($tmp as $sup_id => $val) {
                $res = self::_addBill([
                    'code' => $order->code, 'type' => 1, 'send_warehouse_name' => $order->warehouse_name, 'num' => count($val), 'sup_id' => $sup_id
                ], $detail, $order, $shop);
                $arr[] = $res->id;
            }
        }
        if ($arr) self::pushSettle($arr);
        return;
    }

    /**
     * 退货退款生成寄卖结算账单
     *
     * @param ArrivalRegist $regist
     */
    static function addBillByReturn($regist)
    {
        if ($regist->arr_type != 3) return;

        $arr = [];
        $ib_orders = $regist->ibOrder;
        foreach ($ib_orders as $ib_order) {
            // 退货退款/仅退款确认入库后生成寄卖结算账单
            if ($ib_order->doc_status != 3 || $ib_order->ib_type != 3) continue;
            $order = $ib_order->order;
            if (!$order) continue;

            $aftersale = $order->activeAfterSale;
            if (!$aftersale) continue;

            $details = WmsAfterSaleOrderDetail::where('origin_code', $aftersale->code)->get();
            if ($details->count() == 0) continue;
            $type = $aftersale->type == 1 ? 3 : 2;


            // 指定登记单、收货单收到的商品
            $uniq_codes = RecvDetail::where([
                'arr_id' => $regist->id, 'ib_id' => $ib_order->id, 'inv_type' => 1,
                'is_cancel' => 0, 'sup_confirm' => 1, 'ib_confirm' => 1, 'is_qc' => 1, 'is_putway' => 1
            ])->pluck('uniq_code')->toArray();
            $shop = WmsShop::where('code', $order->shop_code)->first();

            foreach ($details as $after_sale_detail) {
                $detail = WmsOrderDetail::where(['id' => $after_sale_detail->order_detail_id])->first();
                $items = $detail->items;
                $tmp = [];

                // 退货入库，唯一码不变，直接找到对应的销售单出库明细
                foreach ($items as $item) {
                    if (!in_array($item->uniq_code, $uniq_codes)) continue;
                    $tmp[$item->sup_id][] = $item->uniq_code;
                }
                if (!$tmp) continue;

                foreach ($tmp as $sup_id => $val) {
                    $res = self::_addBill([
                        'code' => $aftersale->code, 'type' => $type, 'num' => count($val), 'sup_id' => $sup_id,
                        'return_warehouse_name' => Warehouse::name($aftersale->warehouse_code),
                    ], $detail, $order, $shop);
                    $arr[] = $res->id;
                }
            }
        }
        if ($arr) self::pushSettle($arr);
    }

    static function pushSettle($ids)
    {
        $data = [
            'params' => ['tenant_id' => request()->header('tenant_id'), 'ids' => $ids],
            'class' => 'App\Logics\wms\Consigment',
            'method' => 'settleHandle',
            'time' => date('Y-m-d H:i:s'),
        ];
        Redis::rpush(RedisKey::QUEUE2_AYSNC_HADNLE, json_encode($data));
    }

    function settleHandle($params)
    {
        request()->headers->set('tenant_id', $params['tenant_id']);
        ConsignSettleRule::settleByRule($params['ids']);
    }

    private static function _addBill($data, $detail, $order, $shop)
    {
        $sku = $detail->product;
        $product = $sku ? $sku->product : null;
        $num = $data['num'] ?: $detail['num'];
        $sup_id = $data['sup_id'] ?: $detail['sup_id'];
        $sup = Supplier::find($sup_id);
        if ($detail && is_object($detail)) $detail = $detail->toArray();
        $settle = WmsConsigmentSettlement::create([
            'origin_code' => $data['code'],
            'status' => 2,
            'type' => $data['type'],
            'sup_id' => $sup_id,
            'sup_name' => $sup ? $sup->name : '',
            'third_code' => $order->third_no,
            'order_at' => $order->order_at,
            'sku' => $sku ? $sku->sku : '',
            'spec_one' => $sku ? $sku->spec_one : '',
            'product_sn' => $product ? $product->product_sn : '',
            'product_name' => $product ? $product->name : '',
            'quality_type' => $detail['quality_level'] == 'A' ? 1 : 2,
            'quality_level' => $detail['quality_level'],
            'num' => $num,
            'actual_deal_price' => $detail['price'],
            'deal_price' => $detail['price'],
            'retail_price' => $detail['retails_price'],
            'payment_amount' => $detail['payment_amount'],
            'actual_deal_amount' => bcmul($detail['price'], $num),
            'deal_amount' => bcmul($detail['price'], $num),
            'retail_amount' => bcmul($detail['retails_price'], $num),
            'send_warehouse_name' => $data['send_warehouse_name'] ?? '',
            'return_warehouse_name' => $data['return_warehouse_name'] ?? '',
            'shop_name' => $order->shop_name,
            'buyer_account' => $order->buyer_account, //买家账号
            'sku_total' => array_sum(array_column($detail, 'num')), //订单sku总数
            'order_channel' => $shop ? $shop->id : 0,
            'action_at' => date('Y-m-d H:i:s'),
            'tenant_id' => request()->header('tenant_id'),
            'created_user' => request()->header('user_id', 0),
        ]);
        return $settle;
    }

    /**
     * 指定结算规则
     *
     * @param array $params
     */
    function assignRule($params)
    {
        $bills = WmsConsigmentSettlement::where(['status' => 2])->whereIN('id', $params['ids'])->get();
        //仅待结算、待提现的可以重新指定
        if ($bills){
            foreach ($bills as $bill){
                if ($bill->stattlement_status != 0 && $bill->stattlement_status != 1){
                    $this->setErrorMsg(__('tips.consigment_not_allow_reassign'));
                    return false;
                }
            }
        }
        $rule = ConsignSettleRule::where(['id' => $params['rule_id'], 'status' => 1])->first();
        $types = array_unique($bills->pluck('type')->toArray());
        if ($rule->object == 1 && array_intersect([2, 3], $types)) {
            $this->setErrorMsg(__('tips.consigment_not_match'));
            return false;
        }

        if ($rule->object == 2 && in_array(1, $types)) {
            $this->setErrorMsg(__('tips.consigment_not_match'));
            return false;
        }

        try {
            DB::beginTransaction();
            foreach ($bills as $bill) {
                $bill = WmsConsigmentSettlement::where(['status' => 2])->whereIn('stattlement_status', [0, 1])->where('id', $bill->id)->lockForUpdate()->first();
                if (!$bill) {
                    DB::rollBack();
                    $this->setErrorMsg(sprintf(__('tips.consigment_status_change'), $bill->id));
                    return false;
                }

                $data = [
                    'old_rule' => $bill->rule_code, 'old_stattlement_amount' => $bill->stattlement_amount,
                ];

                // 结算
                $res = ConsignSettleRule::settleByRule([$bill->id], $rule->id);
                if (!$res[0]) {
                    DB::rollBack();
                    $this->setErrorMsg($res[1]);
                    return false;
                }

                // 操作记录
                $bill = WmsConsigmentSettlement::find($bill->id);
                $data['new_rule'] = $rule->code;
                $data['new_stattlement_amount'] = $bill->stattlement_amount;
                WmsOptionLog::add(WmsOptionLog::JMDJS, $bill->id, '指定结算规则', '', $data);
            }
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    /**
     * 指定结算金额
     *
     * @param array $params
     */
    function assignAmount($params)
    {
        $bill = WmsConsigmentSettlement::where(['status' => 2, 'id' => $params['id']])->first();
        if (!$bill) {
            $this->setErrorMsg(__('tips.status_deny_option'));
            return false;
        }
        if ($bill->stattlement_status != 0 && $bill->stattlement_status != 1){
            $this->setErrorMsg(__('tips.consigment_not_allow_reassign'));
        }
        $data = ['old_stattlement_amount' => $bill->stattlement_amount, 'stattlement_status' => $bill->stattlement_status,];
        $bill->update(['stattlement_status' => 1, 'stattlement_amount' => $params['amount'], 'remark' => $params['remark'] ?? '',]);

        $data['old_stattlement_amount'] = $params['amount'];
        $data['remark'] = $params['remark'] ?? '';
        WmsOptionLog::add(WmsOptionLog::JMDJS, $bill->id, '指定结算金额', '', $data);
        return true;
    }

    /**
     * 提现申请明细
     *
     * @param array $params
     */
    function withdrawApplyDetails($params)
    {
        $model = WmsConsigmentSettlement::where(['stattlement_status' => 1]);
        if ($params['sup_id'] ?? 0) $model = $model->where('sup_id', $params['sup_id']);
        if ($params['ids'] ?? []) $model = $model->whereIn('id', $params['ids']);
        $bills = $model->get();
        $sup_ids = $bills->pluck('sup_id')->unique()->toArray();
        if (count($sup_ids) > 1) {
            $this->setErrorMsg(__('tips.many_sup'));
            return false;
        }
        $sup_id = $sup_ids[0] ?? 0;
        return [
            'total_amount' => $bills->sum('stattlement_amount'),
            'total_num' => count($bills),
            'sup_id' => $sup_id,
            'sup_name' => $sup_id ? Supplier::getName($sup_id) : '',
            'list' => $bills,
        ];
    }

    // 提现申请
    function withdrawApply($params)
    {
        $type = $params['type'];
        $bills = WmsConsigmentSettlement::where(['stattlement_status' => 1, 'sup_id' => $params['sup_id']])->whereIn('id', $params['ids'])->get();
        $sup = array_unique($bills->pluck('sup_id')->toArray());
        if (count($sup) > 1) {
            $this->setErrorMsg(__('tips.many_sup'));
            return false;
        }

        try {
            DB::beginTransaction();
            $total = $bills->sum('num');
            $amount = $bills->sum('stattlement_amount');
            $request = WmsWithdrawRequest::create([
                'type' => 1,
                'source' => 1,
                'code' => WmsWithdrawRequest::code(),
                'apply_at' => date('Y-m-d H:i:s'),
                'sup_id' => $bills[0]->sup_id,
                'sup_name' => $bills[0]->sup_name,
                'total' => $total,
                'amount' => $amount,
                'remark' => $params['remark'] ?? '',
                'order_user' => ADMIN_INFO['user_id'],
                'created_user' => ADMIN_INFO['user_id'],
                'tenant_id' => ADMIN_INFO['tenant_id'],
            ]);
            $map = ['2' => '按供应商提现', '1' => '直接申请提现',];
            WmsOptionLog::add(WmsOptionLog::TXSQD, $request->code, '提现申请新增', $map[$type] ?? '', []);
            WmsConsigmentSettlement::where(['stattlement_status' => 1, 'sup_id' => $params['sup_id']])->whereIn('id', $params['ids'])->update([
                'stattlement_status' => 2, 'apply_code' => $request->code, 'apply_at' => $request->apply_at,
            ]);
            DB::commit();
            return $request;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 提现审核
    function withdrawAudit($params)
    {
        $requests = WmsWithdrawRequest::where(['status' => 0])->whereIn('id', $params['ids'])->get();
        if ($requests->count() == 0) {
            $this->setErrorMsg(__('tips.doc_status_err'));
            return false;
        }
        try {
            DB::beginTransaction();
            foreach ($requests as $request) {
                $request->update(['status' => 1, 'audit_at' => date('Y-m-d H:i:s'),]);
                WmsOptionLog::add(WmsOptionLog::TXSQD, $request->code, '提现申请审核', '提现申请审核通过', []);
                WmsConsigmentSettlement::where(['apply_code' => $request->code, 'stattlement_status' => 2])
                    ->update(['stattlement_status' => 3,]);
            }
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 提现申请单查询
    function WithdrawSearch($params, $export = false)
    {
        $model = new WmsWithdrawRequest();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            return $model->with(['createUser'])->orderBy('id', 'desc');
        });
        return $list;
    }

    // 提现申请单详情
    function WithdrawInfo($params)
    {
        $request = WmsWithdrawRequest::where('id', $params['id'])->first();
        if (!$request) return [];

        return [
            'info' => $request,
            'option_logs' => WmsOptionLog::list(WmsOptionLog::TXSQD, $request->code),
        ];
    }

    // 更新售后结算单确认时间
    static function confirm($codes)
    {
        WmsConsigmentSettlement::whereIn('origin_code', $codes)->whereIn('type', [2, 3])->update(['confirm_at' => date('Y-m-d H:i:s')]);
    }
}
