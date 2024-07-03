<?php

namespace App\Console\Commands;

use App\Logics\OrderLogic;
use App\Logics\RedisKey;
use App\Logics\Robot;
use App\Models\ChannelOrder;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class OrderStatusTracking extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order-tracking';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '库存商品订单状态跟踪';

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
        $key = RedisKey::LOCK_ORDER_TRACKING;
        if (!Redis::setnx($key, date('Y-m-d H:i:s'))) {
            return 0;
        }
        try {
            set_time_limit(0);
            // dump('库存订单状态跟踪');
            // 每隔48小时，获取订单状态，直到已完成/已关闭/已取消
            $orders = ChannelOrder::where('status', ChannelOrder::STATUS_DELIVER)
                ->where('updated_at', '<', date('Y-m-d H:i:s', time() - 48 * 3600))
                ->whereIn('stock_source', ['DW', ChannelOrder::SOURCE_STOCK])->get();
            $logic = new OrderLogic();
            foreach ($orders as $order) {
                // dump($order->order_no);
                try {
                    $logic->orderSync(['order' => $order]);
                } catch (Exception $e) {
                    Robot::sendException('OrderStatusTracking Exception' . $e->__toString());
                }
            }
        } catch (Exception $e) {
            Robot::sendException('OrderStatusTracking2 Exception' . $e->__toString());
        } finally {
            Redis::del($key);
        }
        return 0;
    }
}
