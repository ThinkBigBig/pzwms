<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $environment = env('ENV_NAME', '');
        // if ($environment == 'prod') {
        //     // 同步慎独商品信息，每4小时执行一次
        //     $schedule->command('product')->cron('0 */4 * * *')->withoutOverlapping()->runInBackground()->onOneServer();
        //     // 获取得物所有订单（包括保税仓、跨境），每小时一次
        //     $schedule->command('order')->hourly()->withoutOverlapping()->runInBackground()->onOneServer();
        //     // 得物对账账单，每月10 15号各执行一次
        //     $schedule->command('withdraw_orders')->cron('0 0 10,25 * *')->withoutOverlapping()->runInBackground()->onOneServer();

        //     // 获取goat、stockx订单，每3分钟一次
        //     $schedule->command('channel-orders')->everyThreeMinutes()->withoutOverlapping()->runInBackground()->onOneServer();
        //     // 库存订单状态跟踪，每隔2天2:00执行一次
        //     $schedule->command('order-tracking')->cron('0 2 */2 * *')->withoutOverlapping()->runInBackground()->onOneServer();

        //     $schedule->command('ihad-order')->everyTenMinutes()->withoutOverlapping()->runInBackground()->onOneServer();
        // }


        // 渠道接口调用失败重试，每2分钟一次
        // $schedule->command('channel-retry')->everyTwoMinutes()->withoutOverlapping()->runInBackground()->onOneServer();

        // // 出价结果查询，每分钟一次
        // $schedule->command('bid-result')->everyMinute()->withoutOverlapping()->runInBackground()->onOneServer();


        // // 出价
        // $schedule->command('bid add 0')->everyTwoMinutes()->withoutOverlapping()->runInBackground()->onOneServer();
        // $schedule->command('bid add 1')->everyTwoMinutes()->withoutOverlapping()->runInBackground()->onOneServer();
        // $schedule->command('bid add 2')->everyTwoMinutes()->withoutOverlapping()->runInBackground()->onOneServer();

        // 取消出价
        // $schedule->command('bid cancel')->everyTwoMinutes()->withoutOverlapping()->runInBackground()->onOneServer();
        //  // 获取goat、stockx的发货单，每5分钟一次
        //  $schedule->command('dispatch-num')->everyFiveMinutes()->withoutOverlapping()->runInBackground()->onOneServer();

        // // 同步cm失败后重试，每分钟一次
        // $schedule->command('callback-carryme-retry')->everyMinute()->withoutOverlapping()->runInBackground()->onOneServer();

        // // 渠道空卖到CARRYME，每2分钟执行一次
        // $schedule->command('channel-purchase bid')->everyTwoMinutes()->withoutOverlapping()->runInBackground()->onOneServer();

        // // 渠道空卖取消，每10分钟执行一次
        // $schedule->command('channel-purchase cancel')->everyTenMinutes()->withoutOverlapping()->runInBackground()->onOneServer();
        
        // if ($environment != 'uat') {
        //     // 定时刷新得物app出价
        //     $schedule->command('refresh-bid')->cron('0 9,18 * * *')->withoutOverlapping()->runInBackground()->onOneServer();
        //     // 定时刷新库存出价，每2小时一次
        //     $schedule->command('refresh-stock-bid')->cron('0 */2 * * *')->withoutOverlapping()->runInBackground()->onOneServer();
           
        //     // 库存订单自动确认发货，每15分钟1次
        //     $schedule->command('order-confirm')->everyFifteenMinutes()->withoutOverlapping()->runInBackground()->onOneServer();

        //     // 库存订单自动确认并平台发货，每小时执行一次
        //     $schedule->command('order-sendout')->cron('0 */1 * * *')->withoutOverlapping()->runInBackground()->onOneServer();

        //     // 刷新空卖出价的DW最低价
        //     $schedule->command('refresh-purchase-bid')->cron('25 */1 * * *')->withoutOverlapping()->runInBackground()->onOneServer();
        // }

        // 每隔5天3点，清理一次过期的日志文件
        $schedule->command('log:clean')->cron('0 3 */5 * *')->withoutOverlapping()->runInBackground()->onOneServer();

        // 需要异步执行的操作 每10分钟触发一次
        // $schedule->command('async-handle')->cron('*/10 * * * *')->withoutOverlapping()->runInBackground()->onOneServer();
        // 需要异步执行的操作 每10分钟触发一次
        // $schedule->command('async-handle-level2')->cron('*/10 * * * *')->withoutOverlapping()->runInBackground()->onOneServer();

        // 仓储系统

        // 出库单预配 每2分钟执行一次
        $schedule->command('wms:start-allocation')->everyTwoMinutes()->withoutOverlapping()->runInBackground()->onOneServer();
        
        // 波次分组 每2分钟执行一次
        $schedule->command('wms:wave-group')->everyTwoMinutes()->withoutOverlapping()->runInBackground()->onOneServer();

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
