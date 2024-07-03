<?php

namespace App\Console\Commands;

use App\Logics\FreeTaxLogic;
use App\Logics\Robot;
use App\Models\PmsBidding;
use Illuminate\Console\Command;

class exceptIncome extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'except-income';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '批量更新每个sku每个出价的预期收益';

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
        return;
        set_time_limit(0);
        $begin = time();
        $list = PmsBidding::where(['status' => PmsBidding::STATUS_BIDDING])
            ->where('createed_at', '>', date('Y-m-d H:i:s', strtotime('-1 month')))
            ->select(['sku_id', 'price'])->limit(2000)->get();
        $data = [];
        foreach ($list as $val) {
            sleep(1);
            $key = sprintf('%d-%d', $val['sku_id'], $val['price']);
            if (in_array($key, $data)) continue;
            $data[] = $key;
            echo $key, PHP_EOL;
            FreeTaxLogic::setExceptIncome($val['sku_id'], $val['price']);
        }
        $end = time();
        $msg = sprintf('通知：更新預期收益。更新条数:%d，用时:%d秒', count($data), $end - $begin);
        Robot::sendText(Robot::NOTICE_MSG, $msg);
    }
}
