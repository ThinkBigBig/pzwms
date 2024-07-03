<?php

namespace App\Console\Commands;

use App\Logics\bid\BidCancel;
use App\Logics\bid\BidQueue;
use App\Logics\BiddingAsyncLogic;
use App\Logics\BidExecute;
use App\Logics\channel\GOAT;
use App\Logics\channel\STOCKX;
use App\Logics\RedisKey;
use App\Logics\Robot;
use App\Models\ChannelBidding;
use App\Models\ChannelProductSku;
use App\Models\StockBidding;
use App\Models\StockBiddingItem;
use App\Models\StockProduct;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class RefreshStockBid extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refresh-stock-bid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '刷新库存出价';

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

        if (!Redis::setnx(RedisKey::LOCK_REFRESH_STOCK_BID, date('Y-m-d H:i:s'))) {
            // Robot::sendNotice('RefreshStockBid 正在运行中，本次不执行');
            return 0;
        }

        try {
            $this->_rebid_all();
            $this->_refresh_dw();
        } catch (Exception $e) {
            Robot::sendException($e->__toString());
        }

        Redis::del(RedisKey::LOCK_REFRESH_STOCK_BID);
    }

    // 全渠道重新出价
    private function _rebid_all()
    {
        try {
            $time = time();
            $num = 0;
            $num2 = 0;
            $logic = new BiddingAsyncLogic();
            // 有库存、已上架的商品
            $products = StockProduct::where(['is_deleted' => 0, 'status' => StockProduct::STATUS_SHELF])->whereRaw('stock>0 and stock>order_stock')->get();
            foreach ($products as $product) {
                $bidding = StockBidding::where(['stock_product_id' => $product->id])->orderBy('id', 'desc')->first();
                // 2小时内刚出过价，不刷新
                if ($bidding && strtotime($bidding->updated_at) > time() - 2 * 3600) continue;
                // 刷新最低价，如果满足出价规则，重新出价
                if ($this->_lowest_refresh($product)) {
                    if ($logic->stockProductRefreshBid($product, '最低价刷新')) {
                        $num2++;
                    }
                    continue;
                }

                if ($bidding && strtotime($bidding->updated_at) > time() - 12 * 3600) continue;
                if ($logic->stockProductRefreshBid($product, '定时刷新')) {
                    $num++;
                    continue;
                }
            }
            Robot::sendNotice(sprintf('定时刷新出价，sku数量：12小时刷新%s 最低价刷新%s 耗时%s', $num, $num2, time() - $time));
        } catch (Exception $e) {
            Robot::sendException('_rebid_all异常，' . $e->__toString());
        }
    }

    // goat 和stockx渠道的最低价超过两个小时没有更新，重新获取下最低价，并判断是否要重新出价
    function _lowest_refresh(StockProduct $product)
    {
        $sku_goat = $product->goatChannel;
        if ($sku_goat && $sku_goat->channel_product_sku_id) {
            $sku = $sku_goat->channelProductSku;
            // 最低价已有两个小时没有更新
            if ($sku && $sku->lowest_price_at < date('Y-m-d H:i:s', time() - 2 * 3600)) {
                $goat = new GOAT();
                $goat->syncLowestPrice(['spu_id' => $sku->spu_id]);
                $sku = $sku_goat->channelProductSku;
                $bidding  = ChannelBidding::where(['channel_code' => 'GOAT', 'sku_id' => $sku->sku_id, 'qty_sold' => 0, 'status' => ChannelBidding::BID_SUCCESS])->orderBy('id', 'desc')->first();
                // 最低价有变化
                $lowest_change = !$bidding || ($bidding->lowest_price > 0 && $bidding->lowest_price != $sku->lowest_price);
                // 满足出价规则
                $data = $goat->stockBidPriceHandle([], ['lowest_price' => $sku->lowest_price, 'lowest_price_jpy' => $sku->lowest_price_jpy], $product);
                // 可以重新出价
                if ($lowest_change && ($data['price'] ?? 0)) {
                    return true;
                }
            }
        }

        $sku_stockx = $product->stockxChannel;
        if ($sku_stockx && $sku_stockx->channel_product_sku_id) {
            $sku = $sku_stockx->channelProductSku;
            if ($sku && $sku->lowest_price_at < date('Y-m-d H:i:s', time() - 2 * 3600)) {
                $stockx = new STOCKX();
                $stockx->syncLowestPrice(['spu_id' => $sku->spu_id, 'sku_id' => $sku->sku_id]);
                $sku = $sku_stockx->channelProductSku;
                $bidding  = ChannelBidding::where(['channel_code' => 'STOCKX', 'sku_id' => $sku->sku_id, 'qty_sold' => 0, 'status' => ChannelBidding::BID_SUCCESS])->orderBy('id', 'desc')->first();
                // 最低价有变化
                $lowest_change = !$bidding || $bidding->lowest_price != $sku->lowest_price;
                // 满足出价规则
                $data = $stockx->stockBidPriceHandle([], ['lowest_price' => $sku->lowest_price, 'lowest_price_jpy' => $sku->lowest_price_jpy], $product);
                // 可以重新出价
                if ($lowest_change && ($data['price'] ?? 0)) {
                    return true;
                }
            }
        }
        return false;
    }

    // 库存出价 得物渠道 已经出价超过4个小时，重新出一次价
    function _refresh_dw()
    {
        try {
            $num = 0;
            $time = time();
            $action_at = date('Y-m-d H:i:s');
            $list = ChannelBidding::where(['channel_code' => 'DW', 'status' => ChannelBidding::BID_SUCCESS, 'qty_sold' => 0, 'source' => ChannelBidding::SOURCE_STOCK])->where('updated_at', '<', date('Y-m-d H:i:s', time() - 4 * 3600))->get();
            $logic = new BiddingAsyncLogic();
            foreach ($list as $bidding) {
                if ($logic->stockBidRefresh($bidding, $action_at)) {
                    $num++;
                }
                // $logic::log('bid', ['msg' => $logic->err_msg]);
            }
            Robot::sendNotice(sprintf('库存出价DW渠道刷新，sku数量%s 耗时%s', $num, time() - $time));
        } catch (Exception $e) {
            Robot::sendException('_refresh_dw 异常，' . $e->__toString());
        }
    }
}
