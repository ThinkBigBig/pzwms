<?php

namespace App\Console\Commands;

use App\Logics\bid\BidQueue;
use App\Logics\BiddingAsyncLogic;
use App\Logics\Robot;
use App\Models\ChannelBidding;
use Illuminate\Console\Command;

class RefreshBid extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refresh-bid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '刷新出价';

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
        $channels = ['DW'];
        $start = time();

        $action_at = date('Y-m-d H:i:s');
        $logic = new BiddingAsyncLogic();
        $num = 0;
        foreach ($channels as $channel) {
            $where = [
                'channel_code' => $channel, 'status' => ChannelBidding::BID_SUCCESS, 'qty_sold' => 0
            ];
            // 有出价的SKU
            $biddings = ChannelBidding::where($where)->whereIn('source', [ChannelBidding::SOURCE_APP, ''])->where('updated_at', '<', date('Y-m-d H:i:s', time() - 1800))->get();
            foreach ($biddings as $bidding) {
                $logic->bidRefresh($bidding, $action_at);

                $num++;
            }
        }

        Robot::sendNotice(sprintf('刷新出价完成，本次刷新出价单数量%d 耗时%d秒', $num, time() - $start));
    }
}
