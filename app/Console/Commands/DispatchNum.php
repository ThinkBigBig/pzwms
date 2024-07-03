<?php

namespace App\Console\Commands;

use App\Logics\channel\CARRYME;
use App\Logics\channel\GOAT;
use App\Logics\channel\STOCKX;
use App\Logics\OrderLogic;
use App\Logics\RedisKey;
use App\Logics\Robot;
use App\Models\ChannelOrder;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class DispatchNum extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dispatch-num';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '更新虚拟物流单号';

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
        // 正在运行，不处理
        $key = RedisKey::RUN_TAG_DISPATCH_NUM;
        if (!Redis::setnx($key, date('Y-m-d H:i:s'))) {
            // Robot::sendNotice('dispatch-num脚本运行中，本次不执行。');
            return true;
        }

        try {
            $this->_goat();
            $this->_stockx();
            $this->_carryme();
        } catch (Exception $e) {
        }

        Redis::del($key);
        return true;
    }

    private function _goat()
    {
        dump('goat');
        $list = ChannelOrder::where(['channel_code' => GOAT::$code, 'dispatch_num_url' => ''])
            ->whereIn('status', [ChannelOrder::STATUS_CONFIRM, ChannelOrder::STATUS_DELIVER])
            ->where('business_confirm_time', '<', time() - 10)->limit(100)->get();
        $goat = new GOAT();
        foreach ($list as $order) {
            try {
                dump($order->order_no);
                $detail = $goat->getOrderDetail($order->order_no);

                if ($detail['order_status'] == ChannelOrder::STATUS_CANCEL && $order && $order->status != ChannelOrder::STATUS_CANCEL) {
                    OrderLogic::orderRefund($order->channel_code, $detail['refund']);
                    continue;
                }

                $goat->updateDispatchNum($order, $detail);
            } catch (Exception $e) {
                Robot::sendException($e->__toString());
            }
        }
    }

    private function _stockx()
    {
        dump('stockx');
        // 订单成立超3小时，获取保存发货单数据
        $orders = ChannelOrder::where(['channel_code' => STOCKX::$code, 'dispatch_num_url' => ''])->whereIn('status', ChannelOrder::$active_status)
            ->where('created_at', '<', date('Y-m-d H:i:s', strtotime('-3 hours')))
            ->limit(100)->get();
        $channel = new STOCKX();
        foreach ($orders as $order) {
            dump($order->order_no);
            // 获取订单详情
            $detail = $channel->getOrderDetail($order->order_no);
            if ($detail) {
                // 保存发货单
                $channel->updateDispatchNum($order, $detail);
            }
        }
    }

    function _carryme()
    {
        dump('stockx');
        $orders = ChannelOrder::where(['channel_code' => CARRYME::$code, 'dispatch_num' => ''])->whereIn('status', ChannelOrder::$active_status)->limit(100)->get();
        $channel = new CARRYME();
        foreach ($orders as $order) {
            $detail = $channel->getOrderDetail($order->order_no);
            $channel->updateDispatchNum($order, $detail);
        }
    }
}
