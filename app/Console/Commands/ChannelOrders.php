<?php

namespace App\Console\Commands;

use App\Handlers\GoatApi;
use App\Handlers\StockxApi;
use App\Logics\channel\DW;
use App\Logics\channel\GOAT;
use App\Logics\channel\STOCKX;
use App\Logics\OrderLogic;
use App\Logics\RedisKey;
use App\Logics\Robot;
use App\Models\ChannelBidding;
use App\Models\ChannelOrder as ModelsChannelOrder;
use App\Models\StockxBidding;
use App\Models\StockxOrder;
use App\Models\StockxProduct;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ChannelOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'channel-orders {start_at=""}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '获取并更新渠道订单状态';

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
        set_time_limit(0);
        // 正在运行，不处理
        $key = RedisKey::RUN_TAG_ORDER;
        if (!Redis::setnx($key, date('Y-m-d H:i:s'))) {
            // Robot::sendNotice('channel-orders脚本运行中，本次不执行。');
            return true;
        }

        try {

            $start_at = $this->argument('start_at');
            $this->_goat($start_at);

            $this->_sotckx_active();
            // $this->_stockx_active_v2();
            $this->_stockx_history();

            $this->_goat_confirm();
        } catch (Exception $e) {
            Robot::sendException('channel-orders '.$e->__toString());
        }finally{
            Redis::del($key);
        }
        return true;
    }

    private function _goat($start_at = '')
    {
        dump('同步goat订单');
        $add_num = 0;
        $key = RedisKey::GOAT_ORDER_UPDATE_TIME;
        //查最近30分钟内更新的订单
        $updated_at = Redis::get($key) ?: '2023-04-20T00:00:00.190Z'; //上次更新时间
        if ($start_at != '""') {
            $updated_at = $start_at;
        }

        $api = new GoatApi();
        $goat = new GOAT();
        $page = 1;
        $new_updated_at = '';
        $start = time();
        try {
            while (true) {
                $run = true;
                $res = $api->getOrders(['page' => $page]);
                $orders = $res['orders'];
                if (!$orders) $run = false;
                foreach ($orders as $order) {
                    try {
                        $goat::syncOrder($order);
                        $detail = $goat->orderDetailFormat($order);
                        if (!$new_updated_at) {
                            $new_updated_at = $detail['updated_at'];
                        }
                        if ($detail['updated_at'] < $updated_at) {
                            dump('时间已过');
                            $run = false;
                            break;
                        }
                        if (!$detail['bidding_no']) {
                            dump("非API出价订单");
                            continue;
                        }

                        if ($detail['order_status'] == ModelsChannelOrder::STATUS_DEFAULT) {
                            Robot::sendNotice(sprintf('风控订单暂不处理 订单号:%s 订单id:%s', $detail['order_no'], $detail['order_id']));
                            continue;
                        }

                        dump($detail['order_id']);
                        $where = ['tripartite_order_id' => $detail['order_id'], 'channel_code' => 'GOAT'];
                        $channel_order = ModelsChannelOrder::where($where)->first();
                        if (!$channel_order) {
                            dump('订单创建' . $detail['order_no']);
                            $goat->orderPirceHandle($detail);
                            $channel_order = OrderLogic::orderCreated($detail, 'GOAT');
                            if ($channel_order) {
                                $add_num++;
                            }
                        }

                        $logic = new OrderLogic();
                        $logic->orderSync(['order' => $channel_order], $detail);
                        dump('订单状态同步' . $detail['order_no']);
                    } catch (Exception $e) {
                        Robot::sendException('goat-order order:' . json_encode($order) . ' ' . $e->__toString());
                    }
                }

                $page++;
                // 更新订单执行完成或执行时间超过1个小时，重试开始获取
                if (!$run || time() - $start > 3600) {
                    Redis::set($key, $new_updated_at);
                    $msg = sprintf('goat-order 截止更新时间:%s 新增:%d条 耗时:%d秒', $updated_at, $add_num, time() - $start);
                    Robot::sendNotice($msg);
                    break;
                }
            }
        } catch (Exception $e) {
            Robot::sendException($e->__toString());
        }

        return true;
    }

    private function _stockx_active_v2()
    {
        dump('同步stockx活跃订单');

        $status_array = [
            "CREATED", "SHIPPED", "RECEIVED",
            //"CCAUTHORIZATIONFAILED", "AUTHENTICATING", "AUTHENTICATED", "PAYOUTPENDING", "PAYOUTCOMPLETED", "SYSTEMFULFILLED", "PAYOUTFAILED", "SUSPENDED"
        ];
        $api = new StockxApi();
        $num = 0;

        foreach ($status_array as $status) {
            $page = 1;
            while (true) {
                $res = $api->activeOrder(['pageNumber' => $page, 'pageSize' => 100, 'orderStatus' => $status]);
                $orders = $res['orders'] ?? [];
                if (!$orders) break;

                foreach ($orders as $order) {
                    if ($this->_stockx_orders_handle($order)) {
                        $num++;
                    }
                }

                if (!($res['hasNextPage'] ?? false)) break;
                $page++;
                sleep(1);
            }
        }

        Robot::sendNotice(sprintf('stockx同步活跃订单，新增/更新%d', $num));
    }

    function _stockx_orders_handle($order): bool
    {
        $channel = new STOCKX();
        try {
            $detail = $channel::syncOrder($order);
            if (!$detail) return false;

            $order = ModelsChannelOrder::where(['order_no' => $detail['order_no'], 'channel_code' => STOCKX::$code])->first();
            if (!$order) {
                $order = OrderLogic::orderCreated($detail, STOCKX::$code);
                // 订单列表中不包含发货单数据，调订单接口直接获取下
                if (!$order->dispatch_num) {
                    sleep(1);
                    $detail = $channel->getOrderDetail($order->order_no);
                    if ($detail) {
                        $channel->updateDispatchNum($order, $detail);
                    }
                }
            }
            if ($order && !in_array($order->status, ModelsChannelOrder::$end_status)) {
                (new OrderLogic())->orderSync(['order' => $order], $detail);
            }
        } catch (Exception $e) {
            Robot::sendException(sprintf('stockx同步活跃订单异常 order:%s %s', json_encode($order), $e->__toString()));
            return false;
        }
        return true;
    }

    // 同步stockx活跃订单
    private function _sotckx_active()
    {
        dump('同步stockx活跃订单');

        $num = 0;
        $add = 0;
        $api = new StockxApi();
        $channel = new STOCKX();


        try {
            $page = 1;
            while (true) {

                $res = $api->activeOrder(['pageNumber' => $page, 'pageSize' => 100,]);
                $orders = $res['orders'] ?? [];
                if (!$orders) break;

                foreach ($orders as $order) {
                    try {
                        $detail = $channel::syncOrder($order);
                        if (!$detail) continue;

                        dump($detail['order_no']);
                        $order = ModelsChannelOrder::where(['order_no' => $detail['order_no'], 'channel_code' => STOCKX::$code])->first();
                        if (!$order) {
                            $order = OrderLogic::orderCreated($detail, STOCKX::$code);
                            // 订单列表中不包含发货单数据，调订单接口直接获取下
                            if (!$order->dispatch_num) {
                                sleep(1);
                                $detail = $channel->getOrderDetail($order->order_no);
                                if ($detail) {
                                    $channel->updateDispatchNum($order, $detail);
                                }
                            }
                            $add++;
                        }
                        if ($order && !in_array($order->status, ModelsChannelOrder::$end_status)) {
                            (new OrderLogic())->orderSync(['order' => $order], $detail);
                            $num++;
                        }
                    } catch (Exception $e) {
                        Robot::sendException(sprintf('stockx同步活跃订单异常 order:%s %s', json_encode($order), $e->__toString()));
                    }
                }

                if (!($res['hasNextPage'] ?? false)) break;
                $page++;
                sleep(1);
            }
            Robot::sendNotice(sprintf('stockx同步活跃订单，新增%d 更新%d', $add, $num));
        } catch (Exception $e) {
            Robot::sendException(sprintf('stockx同步活跃订单异常 %s', $e->__toString()));
        }

        return true;
    }

    // 同步stockx历史订单
    public function _stockx_history()
    {
        dump('同步stockx历史订单');
        $api = new StockxApi();
        $channel = new STOCKX();
        $num = 0;
        $add = 0;
        $from_date = date('Y-m-d', strtotime('-7 days'));

        try {
            $page = 1;
            while (true) {
                $res = $api->historyOrder(['pageNumber' => $page, 'pageSize' => 100, 'fromDate' => $from_date,]);
                $orders = $res['orders'] ?? [];
                if (!$orders) break;

                foreach ($orders as $order) {
                    try {
                        $detail = $channel::syncOrder($order);
                        if (!$detail) continue;

                        dump($detail['order_no']);
                        $order = ModelsChannelOrder::where(['order_no' => $detail['order_no'], 'channel_code' => STOCKX::$code])->first();
                        if (!$order) {
                            $order = OrderLogic::orderCreated($detail, STOCKX::$code);
                            $add++;
                        }

                        if ($order && !in_array($order->status, ModelsChannelOrder::$end_status)) {
                            (new OrderLogic())->orderSync(['order' => $order], $detail);
                            $num++;
                        }
                    } catch (Exception $e) {
                        Robot::sendException(sprintf('stockx同步历史订单异常 order:%s %s', json_encode($order), $e->__toString()));
                    }
                }

                if (!($res['hasNextPage'] ?? false)) break;
                $page++;
                sleep(1);
            }

            Robot::sendNotice(sprintf('stockx同步历史订单，新增%d 更新%d', $add, $num));
        } catch (Exception $e) {
            Robot::sendException(sprintf('stockx同步历史订单异常 %s', $e->__toString()));
        }

        return true;
    }


    // goat订单成立后，立即确认发货
    function _goat_confirm()
    {
        dump('goat订单自动确认');
        $num = 0;
        $goat = new GOAT();
        $orders = ModelsChannelOrder::where(['channel_code' => GOAT::$code, 'status' => ModelsChannelOrder::STATUS_CREATED])->where('created_at', '>=', date('Y-m-d H:i:s', time() - 1200))->get();
        $logic = new OrderLogic();
        $goat = new GOAT();
        foreach ($orders as $order) {
            try {
                // app订单只确认不打包
                if ($order->source_format == ModelsChannelOrder::SOURCE_APP) {
                    $goat->confirm($order);
                } else {
                    // 库存订单确认并打包
                    $logic->businessConfirm([
                        'order_id' => $order->id,
                        'remark' => 'GOAT订单成立后自动确认',
                    ]);
                }
                $num++;
            } catch (Exception $e) {
                Robot::sendException(sprintf('goat订单自动确认异常 %s', $e->__toString()));
            }
        }

        Robot::sendNotice(sprintf('goat订单自动确认数量%d', $num));
    }

    // 同步得物未获取到的订单
    function _dw_sync()
    {
        dump('同步dw订单状态');
        $sql = "SELECT orders.* from orders 
        left join channel_bidding cb on orders.seller_bidding_no=cb.bidding_no AND cb.channel_code=?
        left join channel_order co on orders.order_no=co.order_no
        where order_type=3 and orders.create_time>? and orders.order_status IN(2000,2010,3000,3010,3020,3030,3040,4000,8010) and cb.id>0 and co.id is NULL";
        $list = DB::select($sql, [DW::$code, date('Y-m-d', time() - 3600)]);

        $add = 0;
        $update = 0;
        $channel = new DW();
        foreach ($list as $item) {
            try {
                $detail = $channel->getOrderDetail($item['order_no']);
                if (!$detail) continue;

                dump($detail['order_no']);
                $order = ModelsChannelOrder::where(['order_no' => $detail['order_no'], 'channel_code' => DW::$code])->first();
                if (!$order) {
                    $order = OrderLogic::orderCreated($detail, DW::$code);
                    $add++;
                }
                if ($order && !in_array($order->status, ModelsChannelOrder::$end_status)) {
                    (new OrderLogic())->orderSync(['order' => $order], $detail);
                    $update++;
                }
            } catch (Exception $e) {
                Robot::sendException('同步得物订单异常 ' . $e->__toString());
                continue;
            }
        }
        Robot::sendNotice(sprintf('同步得物订单，新增%d 更新%d', $add, $update));
    }
}
