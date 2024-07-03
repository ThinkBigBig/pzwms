<?php

namespace App\Console\Commands;

use App\Logics\channel\DW;
use App\Logics\Robot;
use App\Models\ChannelProduct;
use App\Models\ChannelProductSku;
use App\Models\ChannelPurchaseBidding;
use App\Models\PurchaseProduct;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class RefreshPurchaseBid extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refresh-purchase-bid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '定时刷新空卖出价';

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
        $key = 'erp:refresh-purchase-bid';
        if (!Redis::setnx($key, date('Y-m-d H:i:s'))) {
            // Robot::sendNotice($key . '进行中，本次不执行');
            return 0;
        }
        try {
            $this->_bidding_refresh();
            $this->_nobidding_refresh();
        } catch (Exception $e) {
        } finally {
            Redis::del($key);
        }
        return 0;
    }

    // 出价超过24小时,重新获取最低价
    function _bidding_refresh()
    {
        try {
            $list = ChannelPurchaseBidding::where('source', 'DW')
                ->where('status', 1)
                ->where('qty_sold', 0)
                ->where('updated_at', '<', date('Y-m-d H:i:s', time() - 24 * 3600))
                ->get();
            $channel = new DW();
            $num = 0;
            $start = time();
            foreach ($list as $item) {
                $num++;
                try {
                    // 刷新最低价
                    $channel->syncLowestPrice([
                        'sku_id' => $item->sku_id,
                        'spu_id' => $item->spu_id,
                    ]);
                } catch (Exception $e) {
                    Robot::sendException('RefreshPurchaseBid异常' . $e->__toString());
                }
            }
            Robot::sendNotice(sprintf('RefreshPurchaseBid 刷新sku %d 条，耗时 %d秒', $num++, time() - $start));
        } catch (Exception $e) {
        }
    }

    // 得物最低价超过24小时没有更新，且最低价为0，
    function _nobidding_refresh()
    {
        try {
            $start = time();
            $list = DB::table('purchase_products as pp')
                ->leftJoin('channel_product as cp', 'pp.product_sn', '=', 'cp.product_sn')
                ->leftJoin('channel_product_sku as cps', 'cp.id', 'cps.cp_id')
                ->where('cp.status', ChannelProduct::STATUS_ACTIVE)
                ->where('cp.channel_code', 'DW')
                ->where('cps.status', ChannelProductSku::STAUTS_ON)
                ->where('pp.status', PurchaseProduct::ACTIVE)
                ->where('pp.channel_code', 'DW')
                ->where('cps.lowest_price', 0)
                ->where('lowest_price_at', '<', date('Y-m-d H:i:s', time() - 24 * 3600))
                ->select(['pp.product_sn', 'cps.spu_id', 'cps.sku_id'])
                ->get();
            $num = 0;
            $channel = new DW();
            foreach ($list as $item) {
                $num++;
                try {
                    // 刷新最低价
                    $channel->syncLowestPrice([
                        'sku_id' => $item->sku_id,
                        'spu_id' => $item->spu_id,
                    ]);
                } catch (Exception $e) {
                    Robot::sendException('RefreshPurchaseBid2异常' . $e->__toString());
                }
            }
            Robot::sendNotice(sprintf('最低价为0刷新sku %d 条，耗时 %d秒', $num++, time() - $start));
        } catch (Exception $e) {

        }
    }
}
