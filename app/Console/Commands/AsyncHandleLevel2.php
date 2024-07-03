<?php

namespace App\Console\Commands;

use App\Logics\RedisKey;
use App\Logics\Robot;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class AsyncHandleLevel2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'async-handle-level2';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '紧急程度2的异步处理逻辑';

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
        try {
            while (true) {
                $data = Redis::lpop(RedisKey::QUEUE2_AYSNC_HADNLE);
                if (!$data) {
                    sleep(20);
                    continue;
                }
                $data = json_decode($data, true);
                $params = $data['params'] ?? [];

                $stop = $data['stop'] ?? false;
                if ($stop) break;

                request()->headers->set('tenant_id', $params['tenant_id'] ?? 0);
                request()->headers->set('user_id', $params['user_id'] ?? 0);
                $class = $data['class'] ?? '';
                $method = $data['method'] ?? '';
                if ($class && $method) {
                    try {
                        (new $class)->$method($params);
                    } catch (Exception $e) {
                        Robot::sendException($e->__toString());
                    }
                }
            }
        } catch (Exception $e) {
            Robot::sendException(sprintf('AsyncHandle2执行异常，%s', $e->__toString()));
        } finally {
        }
        return 0;
    }
}
