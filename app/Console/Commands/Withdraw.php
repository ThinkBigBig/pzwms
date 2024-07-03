<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Handlers\DwApi;

class Withdraw extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'withdraw_orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '对账';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->withdraw_orders();
    }

    //拉取帐单
    public function withdraw_orders()
    {
        $page = 1;
        $size = 30;
        $method = '6,1,apiUrl';
        $time = time() - 3600;
        $time2 = $time-24*60*60*30;
        $requestArr['settle_start_time'] = date('Y-m-d',$time2);
        $requestArr['settle_end_time']   = date('Y-m-d',$time);
        $requestArr['page_size']      = $size;
        
        while (true) {
            $requestArr['page_no'] = $page;
            $data =  (new DwApi($requestArr))->uniformRequest($method, $requestArr);
            $arr  = json_decode($data, true);

            if (!isset($total)) $total = $arr['data']['total_results']??0;
            if (($arr['data']['list'] ?? [])  && ($page - 1) * $size < $total) {
                $this->withdraw_order_handle($arr);
                $page++;
            } else {
                break;
            }
            
        }
        
    }

    private function withdraw_order_handle($arr)
    {
        if(!empty($arr['code'] )  && $arr['code'] == 200)
        {
            foreach($arr['data']['list'] as $v)
            {
                try{
                    // $order[] =
                    $orderDateCount  = DB::table('orders')->where('order_no','=',$v['order_no'])->count();
                    // $orderDate  = objectToArray($orderDate);
                    $orderData = [
                        'order_no'                      => $v['order_no'],//订单编号
                        'order_type'                    => $v['order_type'],//订单类型
                        // 'closeType'                     => $v['closeType'],//订单类型
                        'product_name'                  => $v['product_name'],//商品名称

                        'article_number'                => $v['article_number'],//货号
                        'props'                         => $v['props'],//规格
                        'num'                           => $v['num'],//数量
                        'sku_price'                     => $v['sku_price'],//商品金额

                        'joint_marketing_fee'           => $v['joint_marketing_fee'],//联合营销费（元）
                        'activity_price'                => $v['activity_price'],//限时折扣/定金预售报名价（元）
                        'amount_receivable'             => $v['amount_receivable'],//应收金额（元）
                        'bid_time'                      => $v['bid_time'],//出价时间
                        'order_created_time'            => $v['order_created_time'],//订单创建时间
                        'order_pay_time'                => $v['order_pay_time'],//订单支付时间
                        'platform_deliver_time'         => $v['platform_deliver_time'],//发货时间
                        'is_activity'                   => $v['is_activity'],//发货时间
                        'activity_rate'                 => $v['activity_rate'],//活动费率
                        'activity_id'                   => $v['activity_id'],//费率活动ID

                        'standard_rate'                 => $v['standard_rate'],//技术服务费费率
                        'min_technical_fee'             => $v['min_technical_fee'],//费率下限
                        'max_technical_fee'             => $v['max_technical_fee'],//费率上限
                        'sale_rate'                     => $v['sale_rate'],//费率折扣
                        'technical_fee_coupon'          => $v['technical_fee_coupon'],//技术服务费券
                        'sum_technical_fee_total'       => $v['sum_technical_fee_total'],//合计技术服务费
                        'check_fee'                     => $v['check_fee'],//查验费（元）
                        'identify_fee'                  => $v['identify_fee'],//鉴别费（元）
                        'adjust_pack_fee'               => $v['adjust_pack_fee'],//打包费（元）

                        'transfer_fee'                  => $v['transfer_fee'],//转账手续费（元）
                        'operation_fee'                 => $v['operation_fee'],//操作服务费（元）
                        'sum_plate_fee'                 => $v['sum_plate_fee'],//合计平台服务费（元）
                        'return_insurance_fee'          => $v['return_insurance_fee'],//售后无忧服务费（元）
                        'advance_fee'                   => $v['advance_fee'],//预付款金额（元）
                        'seller_subsidies_postage_fee'  => $v['seller_subsidies_postage_fee'],//卖家承担包邮金额（元）
                        'seller_subsidies_coupon_fee'   => $v['seller_subsidies_coupon_fee'],//卖家承担优惠券金额（元）

                        'seller_subsidies_discount_fee' => $v['seller_subsidies_discount_fee'],//卖家承担折扣活动金额（元）
                        'seller_interest_free'          => $v['seller_interest_free'],//分期免息卖家承担金额（元）
                        'rebate_fee'                    => $v['rebate_fee'],//n商家返利（元）
                        'adjust_fee'                    => $v['adjust_fee'],//调整金额（元）
                        'stmt_fee'                      => $v['stmt_fee'],//应结金额（元）
                        'stmt_status'                   => $v['stmt_status'],//结算状态
                        'real_stmt_time'                => $v['real_stmt_time'],//实际结算时间
                        'settlement_channel'            => $v['settlement_channel'],//结算渠道
                        'settlement_account'            => $v['settlement_account'],//结算帐号
                        'consumer_freight_subsidy_fee'  => $v['consumer_freight_subsidy_fee'],//消费者邮费补贴金额（元）
                    ];

                    if($orderDateCount > 0 )
                    {
                        DB::table('withdraw_orders_detailed')->where('order_no','=',$v['order_no'])->update($orderData);
                    }else{
                        DB::table('withdraw_orders_detailed')->insert($orderData);
                    }
                } catch (\Exception $e) {
                    $error_arr['order_no'] = $e->getMessage();
                    $error_arr['info']     = $v;
                    log_arr([$error_arr],'synchronizeOrder2');
                    // $msg = $e->getMessage();
                }
            }
        }else{
            log_arr([$arr],'synchronizeOrder');
        }
    }
}
