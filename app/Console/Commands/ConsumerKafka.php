<?php

namespace App\Console\Commands;

use App\Handlers\KafkaService;
use App\Logics\RedisKey;
use App\Logics\Robot;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ConsumerKafka extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'consumer-kafka';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '处理异步kafka消息';

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
        $key = RedisKey::LOCK_CONSUMER_KAFKA;
        if (!Redis::setnx($key, date('Y-m-d H:i:s'))) {
            return 0;
        }
        set_time_limit(0);
        try{
            // $this->log('开始监听消息...');
            (new KafkaService())->consumer(env('KAFKA_URL'),env('KAFKA_GROUP'), env('KAFKA_TOPIC'));
        }catch(Exception $e){
            Robot::sendException('ConsumerKafka ' . $e->getMessage());
        }finally{
            Redis::del($key);
        }
        return 0;
    }
}