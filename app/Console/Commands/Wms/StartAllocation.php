<?php

namespace App\Console\Commands\Wms;

use App\Models\Admin\V2\ObOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Models\Admin\V2\PreAllocationStrategy;
use App\Models\Admin\V2\preAllocationDetail;

class StartAllocation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wms:start-allocation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '根据配货策略开始配货';

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
        define('ADMIN_INFO',[
            // 'tenant_id' => $user->tenant_id,
            // 'pid' => $user->p_id,
            'user_id' => 0,
            'roles_id' => 0,
            'org_code' => '',
            'user_code' => '',
        ]);

        //获取需要执行的任务
        $tasks = Redis::hgetall('wms_allocation_task');
        foreach($tasks as $k=>$info){
            $info = json_decode($info,1);
            $item = ObOrder::find($info['id']);
            if(!$item)continue;
            $pro_details  = $item->details->map(function ($detail) {
                return $detail->getRawOriginal();
            })->toArray();
            $task_data = ObOrder::taskData($item->toArray(), $pro_details);
            if(empty($task_data))continue;
            $res = preAllocationDetail::preTask($task_data);
            log_arr([$task_data['request_code']=>$res],'wms_allocation_task');
        }
    }


}
