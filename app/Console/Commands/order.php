<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Handlers\DwApi;

class order extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '订单处理';

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
        $this->order();
    }

    //拉取订单
    public function order()
    {
        $method = '1,9,apiUrl';
        $time = time() - 3600;
        $time2 = $time - 3600;
        $size = 30;
        $page = 1;

        $requestArr['start_modified'] = date('Y-m-d H:i:s',$time2);
        $requestArr['end_modified']   = date('Y-m-d H:i:s',$time);
        $requestArr['page_size']      = $size;
        
        while (true) {
            $requestArr['page_no'] = $page;
            $data =  (new DwApi($requestArr))->uniformRequest($method, $requestArr);
            $arr  = json_decode($data, true);

            if (!isset($total)) $total = $arr['data']['total_results']??0;
            if (($arr['data']['orders'] ?? []) && ($page - 1) * $size < $total) {
                $this->orderHandle($arr);
                $page++;
            } else {
                break;
            }
            
        }
        
    }

    private function orderHandle($arr)
    {
        if (!empty($arr['code'])  && $arr['code'] == 200) {
            foreach ($arr['data']['orders'] as $v) {
                try {
                    // $order[] =
                    $orderDateCount  = DB::table('orders')->where('order_no', '=', $v['order_no'])->count();
                    // $orderDate  = objectToArray($orderDate);
                    $orderData = [
                        'order_no'                      => $v['order_no'], //订单编号
                        'order_type'                    => $v['order_type'], //订单类型
                        // 'closeType'                     => $v['closeType'],//订单类型
                        'order_status'                  => $v['order_status'], //订单状态
                        'pay_status'                    => $v['pay_status'], //支付状态
                        'pay_time'                      => $v['pay_time'], //支付时间
                        'amount'                        => $v['amount'], //订单总金额
                        'pay_amount'                    => $v['pay_amount'], //支付总金额
                        'free_postage_service_charge'   => !empty($v['free_postage_service_charge']) ? $v['free_postage_service_charge'] : 0, //订单编号
                        'merchant_subsidy_amount'       => $v['merchant_subsidy_amount'], //商家承担的优惠金额
                        'properties'                    => $v['properties'], //规格
                        'spu_id'                        => $v['spu_id'], //商品SPU_ID
                        'sku_id'                        => $v['sku_id'], //商品SKU_ID
                        'brand_id'                      => $v['brand_id'], //品牌id
                        'title'                         => $v['title'], //商品名称
                        'sku_price'                     => $v['sku_price'], //商品出价金额
                        'article_number'                => $v['article_number'], //货号
                        'other_numbers'                 => $v['other_numbers'], //辅助货号，多个逗号隔开
                        // 'other_merchant_sku_codes'      => $v['other_merchant_sku_codes'],//辅助商家商品编码
                        'seller_bidding_no'             => $v['seller_bidding_no'], //出价编号
                        'delivery_limit_time'           => $v['delivery_limit_time'], //发货截止时间（时间格式：yyyy
                        // 'close_order_deadline'          => $v['close_order_deadline'],//截止关单时间，格式yyyy
                        'qty'                           => $v['qty'], //销售数量
                        'poundage'                      => $v['poundage'], //商品费率
                        'poundage_percent'              => $v['poundage_percent'], //商品费率百分比（实际值需要除100）
                        'close_type'                    => $v['close_type'], //关闭类型：0
                        'close_time'                    => $v['close_time'], //订单关闭时间
                        'create_time'                   => $v['create_time'], //订单创建时间
                        'modify_time'                   => $v['modify_time'], //订单修改时间
                        'package_quantity'              => $v['package_quantity'], //包裹数量
                        // 'pickup_time_start'             => $v['pickup_time_start'],//预约上门取件开始时间，预约上门取件的订单才会返回，格式
                        // 'pickup_time_end'               => $v['pickup_time_end'],//预约上门取件结束时间
                    ];
                    // qty_sold
                    $method2 = '3,2,apiUrl';
                    $requestArr2['bidding_no'] = $v['seller_bidding_no'];
                    $bidding_no =  (new DwApi($requestArr2))->uniformRequest($method2, $requestArr2);
                    // var_dump($bidding_no );exit;
                    $bidding  = json_decode($bidding_no, true);
                    if ($bidding['code'] && $bidding['code'] == 200) {
                        DB::table('pms_bidding')->where('bidding_no', '=', $orderData['seller_bidding_no'])->update([
                            'qty_sold' => $bidding['data']['qty_sold'],
                            'qty'      => $bidding['data']['qty_remain']
                        ]);
                    } else {
                        log_arr([$bidding_no], 'synchronizeOrder');
                    }

                    if ($orderDateCount > 0) {
                        DB::table('orders')->where('order_no', '=', $v['order_no'])->update($orderData);
                    } else {
                        $admin_id = DB::table('pms_bidding')->where('bidding_no', '=', $orderData['seller_bidding_no'])->value('user_id');
                        // var_dump($admin_id);exit;
                        if ($admin_id) {
                            $admin_name = DB::table('admin_users')->where('id', '=', $admin_id)->value('username');
                            $orderData['admin_name'] = $admin_name;
                            $orderData['admin_id']   = $admin_id;
                        } else {
                            $orderData['admin_name'] = '';
                            $orderData['admin_id']   = 0;
                        }
                        DB::table('orders')->insert($orderData);
                        // DB::table('pms_bidding')->where('bidding_no','=',$orderData['seller_bidding_no'])->update(['qty']);
                    }
                } catch (\Exception $e) {
                    $error_arr['order_no'] = $e->getMessage();
                    $error_arr['info']     = $v;
                    log_arr([$error_arr], 'synchronizeOrder2');
                    // $msg = $e->getMessage();
                }
            }
        } else {
            log_arr([$arr], 'synchronizeOrder');
        }
    }
}
