<?php

namespace App\Console\Commands;

use App\Logics\OrderLogic;
use App\Logics\RedisKey;
use App\Logics\Robot;
use App\Models\ChannelOrder;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class OrderSendout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order-sendout';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '库存订单自动发货';

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
        $key = RedisKey::LOCK_ORDER_SENDOUT;
        if (!Redis::setnx($key, date('Y-m-d H:i:s'))) {
            return 0;
        }

        try {
            dump('订单自动发货');
            $this->_carrymeDw();
        } catch (Exception $e) {
            Robot::sendException('orderSendOut异常 ' . $e->__toString());
        } finally {
            Redis::del($key);
        }
        return 0;
    }

    // 得物空卖到APP的订单，超过9.5天没有发货，自动确认并发货
    function _carrymeDw()
    {
        $orders = ChannelOrder::where('channel_code', 'CARRYME')
            ->where('stock_source', 'DW')
            ->where('status', ChannelOrder::STATUS_CREATED)
            ->where('paysuccess_time', '<', time() - 228 * 3600)
            ->get();
        $logic = new OrderLogic();
        foreach ($orders as $order) {
            try {
                // 确认
                $logic->businessConfirm(['order_id' => $order->id]);
                // 发货
                $logic->platformConfirm(['order_id' => $order->id, 'remark' => '超时自动发货']);
            } catch (Exception $e) {
                Robot::sendException('_carrymeDw SendOut异常 ' . $e->__toString());
            }
        }
    }
}
