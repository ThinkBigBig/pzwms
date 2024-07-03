<?php

namespace App\Console\Commands;

use App\Handlers\AppApi;
use App\Logics\channel\CARRYME;
use App\Logics\channel\STOCKX;
use App\Logics\RedisKey;
use App\Logics\Robot;
use App\Models\AppBidding;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ChannelRetry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'channel-retry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '渠道接口调用失败后重试';

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
        $key = RedisKey::LOCK_CHANNEL_RETRY;
        if (!Redis::setnx($key, date('Y-m-d H:i:s'))) {
            return true;
        }
        set_time_limit(0);
        try{
            $this->_carrymeCancelRetry();
            $this->_stockxDeleteRetry();
        }catch(Exception $e){

        }finally{
            Redis::del($key);
        }
        return true;
    }

    // carryme渠道出价取消调用失败后重试
    function _carrymeCancelRetry()
    {
        $channel = new CARRYME();
        $api = new AppApi();

        try {
            // 一分钟以前，没有出价结果的数据,查出价结果，如果出价成功，直接取消
            $biddings = AppBidding::where(['logId' => 0, 'status' => 0])->where('created_at', '<', date('Y-m-d H:i:s', time() - 60))->get();
            foreach ($biddings as $bidding) {
                $detail = $api->bidDetail($bidding->bidding_no);
                if ($detail['sellBidId'] ?? 0) {
                    //取消对应出价
                    $channel->_cancel($bidding->bidding_no);
                } else {
                    $bidding->update(['status' => AppBidding::FAIL]);
                }
            }
        } catch (Exception $e) {
            Robot::sendException('_carrymeCancelRetry1 ' . $e->__toString());
        }

        try {
            // 出价成功取消失败的数据，取消重试
            $biddings = AppBidding::where(['status' => AppBidding::SUCCESS, 'cancel_retry' => 1])->get();
            foreach ($biddings as $bidding) {
                $channel->_cancel($bidding->bidding_no);
            }
        } catch (Exception $e) {
            Robot::sendException('_carrymeCancelRetry2 ' . $e->__toString());
        }
    }

    // stockx删除失败，批量重新删除
    function _stockxDeleteRetry()
    {
        // 批量删除出价
        try {
            $listings = DB::table('channel_bidding as cb')
                ->leftJoin('stockx_biddings as sb', 'cb.bidding_no', '=', 'sb.bidding_no')
                ->where('cb.channel_code', 'STOCKX')->where('cb.status', 2)->where('cb.qty_sold', 0)
                ->whereIn('sb.status', ['ACTIVE','INACTIVE'])
                ->pluck('sb.listingId')->toArray();
            $stockx = new STOCKX();
            $stockx->batchCancel($listings);
        } catch (Exception $e) {
            Robot::sendException('_stockxDeleteRetry ' . $e->__toString());
        }
    }
}
