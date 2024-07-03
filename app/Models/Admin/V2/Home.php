<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

class Home extends wmsBaseModel
{
    use HasFactory;
    protected $table = '';


    private function useModel($name, $code, $model = [], $params = [])
    {
        if (!empty($params['start_time']) && !empty($params['end_time'])) $time = true;
        foreach ($model as $item) {
            $model_name = __NAMESPACE__ . '\\' . $item;
            $model_item = new $model_name();
            if (!empty($time)) $model_item = $model_item->whereBetween('created_at', [$params['start_time'], $params['end_time']]);
            $data[$item] = $model_item;
        }
        if ($name == 'shop' && !empty($code)) {
            $shop = WmsShop::where('status', 1)->where('code', $code)->orderBy('id', 'desc')->first();
            // if($shop){
            $shop_id = $shop->id ?? 'false';
            $shop_name = $shop->name ?? 'false';
            foreach ($model as $item) {
                if ($item == 'WmsOrder') {
                    $data[$item] = $data[$item]->where('shop_code', $code);
                }
                if ($item == 'AfterSaleOrder') {
                    $sale_codes = WmsOrder::where('shop_code', $code)->where('status', WmsOrder::PASS)->pluck('code')->toArray();
                    $data[$item] = $data[$item]->whereIn('origin_code', $sale_codes);
                }
                if ($item == 'ObOrder') {
                    $data[$item] = $data[$item]->where('order_channel', $shop_id);
                }
                if ($item == 'WmsOrderStatement') {
                    $data[$item] = $data[$item]->where('shop_name', $shop_name);
                }
                if ($item == 'WmsOrderDetail') {
                    $sale_codes = isset($sale_codes) ? $sale_codes : WmsOrder::where('shop_code', $code)->where('status', WmsOrder::PASS)->pluck('code')->toArray();
                    $data[$item] = $data[$item]->whereIn('origin_code', $sale_codes);
                }
            }
            // }
        }
        if ($name == 'wh' && !empty($code)) {
            foreach ($model as $item) {
                $data[$item] = $data[$item]->where('warehouse_code', $code);
            }

            // if ($item == 'preAllocationLists') {
            //     $request_codes = ObOrder::where('warehouse_code', $code)->where('status', 2)->pluck('request_code')->toArray();
            //     $data[$item] = $data[$item]->whereIn('request_code', $request_codes);
            // }else{
            //     $data[$item] = $data[$item]->where('warehouse_code', $code);
            // }
        }
        return $data;
    }

    private function parseData($collection ,$start_time,$end_time,$default="0",$col=['day','total'],$name = 'day'){
        $startDate = new Carbon($start_time); // 设置起始日期为 2021年1月1日
        $endDate = new Carbon($end_time);
        $_date = [];
        $fill = array_fill_keys($col,$default);
        while ($startDate->lte($endDate)) {
            $_date[] = $startDate->format('Y-m-d');
            $startDate->addDay(); // 添加一天
        }
        foreach($_date as $day){
            $temp = [];
            if(!isset($collection[$day])){
                $temp[$name]=$day;
                $temp = array_merge($fill,$temp);
            }else{
                $temp = (array_intersect_key($collection[$day]->first()->toArray(),$fill));
            }
            $data[] = $temp;
        }
        return $data;
    }

    /***
     * 店铺面板-代办事项(店铺的所有数据)
     * 待审核订单-销售订单审核中的订单
     * 待发货订单-销售订单审核通过的待发货订单
     * 超卖订单-出库需求单是否是超卖
     * 待退款订单-售后工单已审核待退款的订单
     * 待结算账单-销售结算单待结算的订单
     */

    public function shopToDoList($shop_code = null)
    {
        $model = $this->useModel('shop', $shop_code, ['WmsOrder', 'ObOrder', 'AfterSaleOrder', 'WmsOrderStatement']);
        $wait_audit = $model['WmsOrder']->where('status', WmsOrder::WAIT_AUDIT)->count();
        $wait_send = $model['WmsOrder']->where('status', WmsOrder::PASS)->where('deliver_status', WmsOrder::WAIT)->count();
        $oversold  = $model['ObOrder']->where('status', 2)->where('tag', 2)->count();
        $wait_refund = $model['AfterSaleOrder']->where('status', 2)->where('refund_status', 0)->count();
        $wait_statement = $model['WmsOrderStatement']->where('status', 0)->count();
        $data = [
            'wait_audit' => $wait_audit,
            'wait_send' => $wait_send,
            'oversold' => $oversold,
            'wait_refund' => $wait_refund,
            'wait_statement' => $wait_statement,
        ];
        return $data;
    }


    /***
     * 店铺面板-其他(24时,7天,1月,1年)
     * 订单金额-销售订单实际支付总额
     * 退款金额-售后工单退款总额
     * 平均客单-订单数量/客户人数
     * 客户人数-销售订单买家账号数量
     * 热销产品-销售详情商品排序(包含未审核取消等)
     * 退货产品-售后详情商品排序(包含未审核取消等)
     * 店铺销量-销售订单店铺时间段内创建的订单的商品数量sum
     */
    public function shopAmount($shop_code, $start_time, $end_time)
    {
        $model = $this->useModel('shop', $shop_code, ['WmsOrder', 'AfterSaleOrder'], ['start_time' => $start_time, 'end_time' => $end_time]);
        $pay_amount_collect = $model['WmsOrder']->where('status', WmsOrder::PASS)
        ->select(DB::raw('date(created_at) as day,sum(payment_amount) as total'))
        ->groupBy('day')->get()->groupBy('day');
        $pay_amount = $this->parseData($pay_amount_collect,$start_time,$end_time,'0.00');
        $refund_amount_collect = $model['AfterSaleOrder']->select(DB::raw('date(created_at) as day,sum(refund_amount)'))
        ->groupBy('day')->get()->groupBy('day');
        $refund_amount = $this->parseData($refund_amount_collect,$start_time,$end_time,'0.00');
        return [
            'pay_amount' => $pay_amount,
            'refund_amount' =>$refund_amount,
        ];
    }

    public function shopBuyer($shop_code, $start_time, $end_time)
    {
        $model = $this->useModel('shop', $shop_code, ['WmsOrder'], ['start_time' => $start_time, 'end_time' => $end_time]);
        $order_count_collect = $model['WmsOrder']->where('status', WmsOrder::PASS)->where('buyer_account', '<>', '')
        ->select(DB::raw('date(created_at) as day,count(*) as total'))
        ->groupBy('day')->get()->groupBy('day');
        $order_count = $this->parseData($order_count_collect,$start_time,$end_time,0);
        $buyer_account_collect = $model['WmsOrder']->where('status', WmsOrder::PASS)->where('buyer_account', '<>', '')
        ->select(DB::raw('date(created_at) as day,round(count(1)/count(distinct buyer_account)) as total'))
        ->groupBy('day')->get()->groupBy('day');
        $buyer_account = $this->parseData($buyer_account_collect,$start_time,$end_time);

        return [
            'buyer_account' => $buyer_account,
            'pre_buyer_count' => $buyer_account,
        ];
    }

    public function shopTop($start_time, $end_time)
    {
        //店铺销量排行
        $model = $this->useModel('shop', '', ['WmsOrder'], ['start_time' => $start_time, 'end_time' => $end_time]);
        $shop_sale_top = $model['WmsOrder']->where('status', WmsOrder::PASS)->select('shop_code','shop_name',DB::raw('sum(num) as total'))->groupBy('shop_code')
        ->orderBy('total','desc')->limit(10)->get()->makeHidden(['type_txt','status_txt','source_type_txt','deliver_status_txt','payment_status_txt','order_platform_txt','tag_txt'])->toArray();
        return [
            'shop_sale_top' =>$shop_sale_top,
        ];
    }
    public function saleTop($shop_code, $start_time, $end_time)
    {
        //热销排行
        $start_time='2024-1-23 00:00:00';
        $end_time='2024-2-23 00:00:00';
        $model = $this->useModel('shop', $shop_code, ['WmsOrderDetail'], ['start_time' => $start_time, 'end_time' => $end_time]);
        $hot_sale_top = $model['WmsOrderDetail']->select('bar_code',DB::raw('sum(num) as total'))->groupBy('bar_code')
        ->orderBy('total','desc')->limit(10)->with('product:bar_code,sku,product_id')->get()->append('product_sku')->makeHidden('product')->toArray();
        return [
            'hot_sale_top' =>$hot_sale_top,
        ];
    }

    public function returnTop($shop_code, $start_time, $end_time)
    {
        //退货排行
        $model = $this->useModel('shop', $shop_code, ['WmsAfterSaleOrderDetail'], ['start_time' => $start_time, 'end_time' => $end_time]);
        $return_top = $model['WmsAfterSaleOrderDetail']->select('bar_code',DB::raw('sum(num) as total'))->groupBy('bar_code')
        ->orderBy('total','desc')->limit(10)->with('product:bar_code,sku,product_id')->get()->append('product_sku')->makeHidden('product')->toArray();
        return [
            'return_top' =>$return_top,
        ];
    }


    /***
     * 仓库面板-代办事项(仓库的所有数据)
     * 待质检-收货明细中库区是收货暂存区的是待收货的商品
     * 待上架-收货明细中库区是质检暂存区的是待质检的商品
     * 待质检确认-质检确认单待确认的数据
     * 待到货确认-未匹配入库单的商品
     * 待配货-配货订单中待配货的数据
     * 待发货-出库单待发,配货中,发货中的数据
     * 系统缺货-出库需求单已审核且标记是系统缺货
     * 实物缺货-出库需求单已审核且标记是超卖
     */

    public function whToDoList($warehouse_code)
    {
        $model = $this->useModel('wh', $warehouse_code, ['RecvDetail', 'WmsQualityConfirmList', 'preAllocationLists', 'ObOrder']);
        $wait_qc = $model['RecvDetail']->where('is_qc', 0)->count();
        $wait_putaway = $model['RecvDetail']->where('is_putway', 0)->count();
        $wait_qc_confirm = $model['WmsQualityConfirmList']->where('status', 0)->count();
        $arr_confirm = $model['RecvDetail']->where('ib_confirm', 0)->count();
        $wait_allocation = $model['preAllocationLists']->where('status', 1)->where('allocation_status', 1)->count();
        $wait_send = $model['ObOrder']->where('status', 2)->whereIn('request_status', [1, 2, 3])->count();
        $out_of_stock = $model['ObOrder']->where('status', 2)->where('tag', 3)->count();
        $oversold = $model['ObOrder']->where('status', 2)->where('tag', 2)->count();
        return [
            'wait_qc' => $wait_qc,
            'wait_putaway' => $wait_putaway,
            'wait_qc_confirm' => $wait_qc_confirm,
            'arr_confirm' => $arr_confirm,
            'wait_allocation' => $wait_allocation,
            'wait_send' => $wait_send,
            'out_of_stock' => $out_of_stock,
            'oversold' => $oversold,
        ];
    }

    /***
     * 仓库面板-出入库数据(当日数据)
     * 今日已收货-收货单今日创建的数据sum(不统计暂存)
     * 今日已质检-质检单今日创建的数据sum
     * 今日已上架-上架单今日创建的数据sum
     * 今日已配货-配货订单今日创建的数据且取消状态cancel_status为0
     * 今日已发货-发货单今日创建的数据
     * 今日已取消-出库需求单取消数量和入库取消单取消数量
     */

    public function whOutIn($warehouse_code)
    {
        $start_time =  date('Y-m-d 00:00:00');
        $end_time = date('Y-m-d H:i:s');
        $model = $this->useModel('wh', $warehouse_code, ['RecvOrder', 'WmsQualityList', 'WmsPutawayList', 'preAllocationDetail', 'ShippingOrders', 'ObOrder', 'IbOrder']);

        $day_recv = $model['RecvOrder']->whereBetween('done_at', [$start_time, $end_time])->where('doc_status', 2)->sum('recv_num');
        $day_qc = $model['WmsQualityList']->whereBetween('completed_at', [$start_time, $end_time])->where('qc_status', WmsQualityList::QC_DONE)->sum('total_num');
        $day_putaway = $model['WmsPutawayList']->whereBetween('completed_at', [$start_time, $end_time])->where('status', WmsPutawayList::STATUS_AUDIT)->where('putaway_status', WmsPutawayList::PUTAWAY_DONE)->sum('total_num');
        $day_allocation = $model['preAllocationDetail']->whereBetween('allocated_at', [$start_time, $end_time])->where('cancel_status', 0)->whereIn('alloction_status', [5, 6, 7])->pluck('request_code')->count();
        $day_send = $model['ShippingOrders']->whereBetween('shipped_at', [$start_time, $end_time])->where('request_status', 0)->where('status', 0)->count();
        $ob_cancel = $model['ObOrder']->whereBetween('cancel_at', [$start_time, $end_time])->where('status', ObOrder::CANCELED)->count();
        $ib_cancel = $model['IbOrder']->whereBetween('cancel_at', [$start_time, $end_time])->where('doc_status', IbOrder::CANCEL)->count();
        $day_cancel = $ob_cancel + $ib_cancel;
        return [
            'day_recv' => (int)$day_recv,
            'day_qc' => (int)$day_qc,
            'day_putaway' => (int)$day_putaway,
            'day_allocation' => $day_allocation,
            'day_send' => $day_send,
            'day_cancel' => $day_cancel,
        ];
    }
}
