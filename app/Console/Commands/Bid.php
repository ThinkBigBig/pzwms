<?php

namespace App\Console\Commands;

use App\Logics\BaseLogic;
use App\Logics\BiddingAsyncLogic;
use App\Logics\BidExecute;
use App\Logics\Robot;
use App\Logics\StockProductLogic;
use App\Models\BidExcutionLog;
use App\Models\CarryMeBidding;
use App\Models\CarryMeBiddingItem;
use App\Models\ChannelBidding;
use App\Models\StockBiddingItem;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Psy\Util\Json;

class Bid extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bid {type="add"} {slice=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '执行出价和取消出价';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }
    protected $slice = 0;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $type = $this->argument('type');
        $this->slice = $this->argument('slice');

        $key = sprintf('erp:bid_lock_%s_%s', $type, $this->slice);
        if (!Redis::setnx($key, date('Y-m-d H:i:s'))) {
            // Robot::sendNotice($key . '进行中，本次不执行');
            return 0;
        }

        $start = time();

        try {
            // 出价
            if ($type == 'add') {
                while (true) {
                    $this->bid();

                    if(!Redis::get($key)) break;
                    if (time() - $start > 3600) break;
                    sleep(10);
                }
            }
            // 取消出价
            if ($type == 'cancel') {
                while (true) {
                    $this->cancel();
                    
                    if(!Redis::get($key)) break;
                    if (time() - $start > 3600) break;
                    sleep(10);
                }
            }
        } catch (Exception $e) {
            Robot::sendException(sprintf('bid脚本执行异常，%s', $e->__toString()));
            // throw $e;
        }
        Redis::del($key);
        return 0;
    }

    function bid(): bool
    {
        $limit = 500;
        while (true) {
            // 待出价数据
            $list = BidExcutionLog::where(['bid_status' => BidExcutionLog::WAIT, 'slice' => $this->slice])->limit($limit)->get();
            if (count($list) == 0) break;

            $logic = new BiddingAsyncLogic();
            foreach ($list as $log) {

                $log = BidExcutionLog::where(['id' => $log->id, 'bid_status' => BidExcutionLog::WAIT])->first();
                if (!$log) continue;

                $log = BidExcutionLog::where(['id' => $log->id])->first();
                // 已经申请出价，不用处理
                $bidding = ChannelBidding::where(['stock_bidding_item_id' => $log->stock_bidding_item_id, 'carryme_bidding_item_id' => $log->carryme_bidding_item_id])->first();
                if ($bidding) {
                    $log->update(['bid_status' => BidExcutionLog::DONE, 'bid_at' => date('Y-m-d H:i:s')]);
                    continue;
                }

                if ($log->cancel_status == BidExcutionLog::WAIT) {
                    $log = $this->_cancel($log);
                    if ($log->cancel_status == BidExcutionLog::DONE) {
                        $log->update([
                            'bid_status' => BidExcutionLog::DONE,
                            'bid_at' => date('Y-m-d H:i:s'),
                            'bid_desc' => '未出价已取消',
                        ]);
                    }
                    continue;
                }

                // 同一个sku_id，有取消中的出价，先不执行
                $find = BidExcutionLog::whereIn('bid_status', [BidExcutionLog::DEFAULT, BidExcutionLog::DONE])->whereIn('cancel_status', BidExcutionLog::$cancel_status)
                    ->where(['channel_product_sku_id' => $log->channel_sku_id, 'channel_code' => $log->channel_code])->first();
                if ($find) continue;

                $log->update(['bid_status' => BidExcutionLog::PENDING]);
                if ($log->created_at == date('Y-m-d H:i:s')) {
                    sleep(1);
                }
                // cm出价
                if ($log->carryme_bidding_item_id) {
                    $item = CarryMeBiddingItem::where(['id' => $log->carryme_bidding_item_id, 'status' => CarryMeBiddingItem::STATUS_DEFAULT])->first();
                    $update = ['bid_status' => BidExcutionLog::DONE, 'bid_at' => date('Y-m-d H:i:s')];
                    if (!$item) {
                        $update['bid_desc'] = '未找到原始出价信息';
                        $log->update($update);
                        continue;
                    }
                    // 出价
                    $logic->bidSingle($item);
                    if ($logic->success) {
                        $update['bid_desc'] = '出价执行完成';
                    } else {
                        $update['bid_desc'] = $logic->err_msg ?: '';
                        // $update['bid_status'] = BidExcutionLog::WAIT;
                    }
                    $log->update($update);
                    continue;
                }

                // stock出价
                if ($log->stock_bidding_item_id) {
                    $item = StockBiddingItem::where(['id' => $log->stock_bidding_item_id, 'status' => StockBiddingItem::STATUS_DEFAULT])->first();
                    $update = ['bid_status' => BidExcutionLog::DONE, 'bid_at' => date('Y-m-d H:i:s')];
                    if (!$item) {
                        $update['bid_desc'] = '未找到原始出价信息';
                        $log->update($update);
                        continue;
                    }
                    // 出价
                    if ($item->channel_code == 'CARRYME') {
                        $logic->bidSingleStockV2($item);
                    } else {
                        $logic->bidSingleStock($item);
                    }

                    if ($logic->success) {
                        $update['bid_desc'] = '出价执行完成';
                    } else {
                        $update['bid_desc'] = $logic->err_msg ?: '';
                        // $update['bid_status'] = BidExcutionLog::WAIT;
                    }
                    $log->update($update);
                    continue;
                }
            }
        }

        return true;
    }

    function cancel(): bool
    {
        // 出价中取消
        $page = 1;
        $limit = 500;
        while (true) {
            $offset = ($page - 1) * $limit;
            $list = BidExcutionLog::where('cancel_status', BidExcutionLog::WAIT)->where('bid_status', BidExcutionLog::DEFAULT)->offset($offset)->limit($limit)->get();
            if (count($list) == 0) break;

            foreach ($list as $log) {
                $this->_cancel($log);
            }
            $page++;
        }

        // 出价成功后取消
        $page = 1;
        $limit = 500;
        while (true) {
            $offset = ($page - 1) * $limit;
            $list = BidExcutionLog::where('cancel_status', BidExcutionLog::WAIT)->where('bid_status', BidExcutionLog::DONE)->offset($offset)->limit($limit)->get();
            if (count($list) == 0) break;

            foreach ($list as $log) {
                $this->_cancel($log);
            }
            $page++;
        }

        return true;
    }

    function _cancel($log)
    {
        $log->update(['cancel_status' => BidExcutionLog::PENDING]);
        try {
            // cm取消
            if ($log->carryme_bidding_item_id) {
                $res = BiddingAsyncLogic::appItemCancelV2([
                    'carryme_bidding_item_id' => $log->carryme_bidding_item_id,
                    'remark' => $log->cancel_remark,
                ]);
            }
            // stock取消
            if ($log->stock_bidding_item_id) {
                $res = BiddingAsyncLogic::stockItemCancelV2([
                    'stock_bidding_item_id' => $log->stock_bidding_item_id, 'remark' => $log->cancel_remark
                ]);
            }
            $update = [];
            if ($res['msg'] ?? '') {
                $update['cancel_desc'] = $res['msg'];
            }
            if ($res['result']) {
                // $update['cancel_status'] = BidExcutionLog::DONE;
                $update['cancel_at'] =  date('Y-m-d H:i:s');
            } else {
                $update['cancel_status'] = BidExcutionLog::WAIT;
            }
            $log->update($update);
        } catch (Exception $e) {
            $log->update(['cancel_status' => BidExcutionLog::WAIT]);
            Robot::sendException('bidCancel异常 ' . $e->__toString());
        }

        return $log;
    }
}
