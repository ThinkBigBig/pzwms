<?php

namespace App\Console\Commands;

use App\Logics\channel\CARRYME;
use App\Logics\OrderLogic;
use App\Logics\RedisKey;
use App\Logics\Robot;
use App\Models\ChannelOrder;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Util\Json;
use Psy\Util\Json as UtilJson;

class OrderConfirm extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order-confirm';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '库存商品订单商家自动确认发货';

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
        $key = RedisKey::LOCK_ORDER_CONFIRM;
        if (!Redis::setnx($key, date('Y-m-d H:i:s'))) {
            return 0;
        }

        try {
            dump('订单自动确认');
            $logic = new OrderLogic();
            $where = ['channel_code' => 'DW', 'status' => ChannelOrder::STATUS_CREATED];
            $where[] = [function ($query) {
                $query->where(['stock_source' => ChannelOrder::SOURCE_STOCK])->orWhere(['unmatch' => 1]);
            }];


            $orders = ChannelOrder::where($where)->where('paysuccess_time', '<', time() - 47 * 3600)->get();

            foreach ($orders as $order) {
                $res = $logic->businessConfirm([
                    'order_id' => $order->id,
                ]);
                $msg = sprintf('DW自动确认发货 order_id:%s order_no:%s res:%s', $order->id, $order->order_no, UtilJson::encode($res));
                Robot::sendNotice($msg);
            }


            $where['channel_code'] = 'GOAT';
            $orders = ChannelOrder::where($where)->where('paysuccess_time', '<', time() - 23 * 3600)->get();

            foreach ($orders as $order) {
                $res = $logic->businessConfirm([
                    'order_id' => $order->id,
                ]);
                $msg = sprintf('GOAT自动确认发货 order_id:%s order_no:%s res:%s', $order->id, $order->order_no, UtilJson::encode($res));
                Robot::sendNotice($msg);
            }

            $this->_carryme_sync();
        } catch (Exception $e) {
            Robot::sendException('order-confirm异常 ' . $e->__toString());
        } finally {
            Redis::del($key);
        }
        return 0;
    }

    function _carryme_sync()
    {
        // 订单成立超过1小时的，同步下订单状态
        // Carryme订单超时不发货，会自动关闭
        $logic = new OrderLogic();
        $orders1 = ChannelOrder::where('channel_code', 'CARRYME')
            ->where('updated_at', '<', date('Y-m-d H:i:s', time() - 6*3600))
            ->where('status', ChannelOrder::STATUS_CREATED)->get();
        $orders2 = ChannelOrder::where('channel_code', 'CARRYME')
            ->where('updated_at', '<', date('Y-m-d H:i:s', time() - 6*3600))
            ->where('status', ChannelOrder::STATUS_CONFIRM)->get();

        $api = new CARRYME();
        foreach ($orders1 as $order) {
            $detail = $api->getOrderDetail($order->order_no);
            $logic->orderSync(['order' => $order], $detail);
        }
        foreach ($orders2 as $order) {
            $detail = $api->getOrderDetail($order->order_no);
            $logic->orderSync(['order' => $order], $detail);
        }
    }
}
