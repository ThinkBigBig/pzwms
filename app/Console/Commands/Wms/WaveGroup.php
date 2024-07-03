<?php

namespace App\Console\Commands\Wms;

use App\Logics\RedisKey;
use App\Logics\Robot;
use App\Logics\wms\AllocationTask;
use App\Models\Organization;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class WaveGroup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wms:wave-group';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '出库配货单波次分组';

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
        $key = RedisKey::WMS_WAVE_GROUP;
        if (!Redis::setnx($key, date('Y-m-d H:i:s'))) {
            return 0;
        }

        $tenant_ids = Organization::where('type', 0)->where('status', 1)->pluck('tenant_id');
        try {
            $logic = new AllocationTask();
            foreach ($tenant_ids as $tenant_id) {
                $logic->waveGroup([], $tenant_id);
            }
        } catch (Exception $e) {
            Robot::sendException(sprintf('waveGroup执行异常，%s', $e->__toString()));
        } finally {
            Redis::del($key);
        }
    }
}
