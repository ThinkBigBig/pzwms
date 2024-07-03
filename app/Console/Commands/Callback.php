<?php

namespace App\Console\Commands;

use App\Logics\CarrymeCallbackLogic;
use App\Logics\RedisKey;
use App\Logics\Robot;
use App\Models\CarrymeCallbackLog;
use App\Models\CarrymeNoticeLog;
use App\Models\ChannelOrder;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class Callback extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'callback-carryme-retry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '回调carryme';

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
        if (!Redis::setnx(RedisKey::LOCK_CALLBACK, date('Y-m-d H:i:s'))) {
            // dump('正在处理中，本次不执行');
            return 0;
        }
        try {
            dump('延时发送处理');
            // 延时发送处理
            $logs = CarrymeCallbackLog::where(['status' => CarrymeCallbackLog::STATUS_DEFAULT, 'type' => CarrymeCallbackLog::TYPE_ORDER_SUCCESS])->where('send_time', '>', 0)->where('send_time', '<', time())->get();
            foreach ($logs as $log) {
                dump($log->channel_order_id);
                $order = ChannelOrder::where(['id' => $log->channel_order_id])->first();
                CarrymeCallbackLogic::orderSuccess($order);
            }

            dump('发送失败处理');
            // 发送失败的重试
            $logs = CarrymeCallbackLog::where(['status' => CarrymeCallbackLog::STATUS_FAIL, 'retry' => 1])->orderBy('updated_at')->get();
            foreach ($logs as $log) {
                dump($log->id);
                CarrymeCallbackLogic::retry($log);
            }
        } catch (Exception $e) {
            Robot::sendException($e);
        }

        Redis::del(RedisKey::LOCK_CALLBACK);
        return 0;
    }
}
