<?php

namespace App\Console\Commands;

use App\Handlers\GoatApi;
use App\Handlers\StockxApi;
use App\Logics\BiddingAsyncLogic;
use App\Logics\channel\GOAT;
use App\Logics\channel\STOCKX;
use App\Logics\ChannelLogic;
use App\Logics\RedisKey;
use App\Logics\Robot;
use App\Models\ChannelBidding;
use App\Models\ChannelBiddingItem;
use App\Models\ChannelPurchaseBidding;
use App\Models\StockBiddingItem;
use App\Models\StockxBidding;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class BidResult extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bid-result';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '查询三方出价结果';

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
        // 正在运行，不处理
        $key = RedisKey::RUN_TAG_BID_RESULT;
        if (!Redis::setnx($key, date('Y-m-d H:i:s'))) {
            // Robot::sendNotice('bid-result脚本运行中，本次不执行。');
            return true;
        }

        try {
            $this->_goat();
            $this->_stockx();
            $this->_carryme();
        } catch (Exception $e) {
            Robot::sendException('bid-result 异常 ' . $e->__toString());
        }
        Redis::del($key);
        return true;
    }

    private function _goat()
    {
        dump('查goat出价结果');
        $start = time();
        $num = 0;
        $where = [
            'status' => ChannelBidding::BID_DEFAULT,
            'channel_code' => GOAT::$code
        ];
        // 查最近30分钟没有出价中的数据
        $where[] = [function ($query) {
            $query->whereIn('business_type', ChannelBidding::$cross_border_business);
        }];
        $where[] = ['updated_at', '>', date('Y-m-d H:i:s', strtotime('-30 minutes'))];
        $biddings = ChannelBidding::where($where)->limit(500)->get();
        $spu_ids = array_unique(array_column($biddings->toArray(), 'spu_id'));
        $goat = new GOAT();
        $logic = new BiddingAsyncLogic();
        foreach ($spu_ids as $spu_id) {

            //获取商品信息
            $res = (new GoatApi())->productSearch(['pt_id' => $spu_id]);
            $products = $res['products'] ?? [];
            foreach ($products as $product) {

                if (!($product['extTag'] ?? '')) {
                    dump("不是API出价");
                    continue;
                }
                if (!in_array($product['saleStatus'], ['active', 'completed'])) {
                    dump("不是上架状态，不更新状态");
                    continue;
                }

                dump("出价成功");
                $params = [
                    'channel_code' => $goat::$code,
                    'bidding_no' => $product['extTag'],
                    'spu_id' => $product['productTemplateId'],
                    'sku_id' => $goat->getSkuId($product['productTemplateId'], $product['size']),
                    'product_id' => $product['id'],
                    'price' => $product['priceCents'],
                    'properties' => ['size' => $product['size']],
                    'channel_bidding_item_status' => in_array($product['saleStatus'], ['active', 'completed']) ? ChannelBiddingItem::STATUS_SHELF : ChannelBiddingItem::STATUS_TAKEDOWN,
                    'shelf_at' => $product['createdAt'],
                    'takedown_at' => $product['updatedAt'],

                ];
                $logic->bidSingleSuccess($params);
                $num++;
            }
        }

        Robot::sendNotice(sprintf('goat出价查询执行完毕，耗时%d秒，出价成功%d条。', time() - $start, $num));
    }

    // 刷新stockx出价结果
    private function _stockx()
    {

        // 同步出价结果
        dump('同步出价结果');
        $biddings = ChannelBidding::where(['status' => ChannelBidding::BID_DEFAULT, 'channel_code' => STOCKX::$code])->get();
        foreach ($biddings as $bidding) {
            $list = $bidding->stockxBidding;
            if ($list && $list->createOperationStatus == STOCKX::OPERATION_STATUS_SUCCEEDED) {
                $this->_stockxHandle($list);
                continue;
            }
            // 超过5天没有执行出价，认为出价失败
            if (!$list && $bidding->updated_at < date('Y-m-d H:i:s', time() - 5 * 3600 * 24)) {
                $bidding->update(['status' => ChannelBidding::BID_FAIL]);
                continue;
            }
        }

        $start = time();
        $add_num = 0;
        $cancel_num = 0;
        dump('查stockx出价结果');
        $limit = 200;
        while (true) {
            // 查创建和删除出价中的数据
            $listingIds = StockxBidding::whereRaw("(`status` IN ('','MATCHED') AND `createOperationStatus` = 'PENDING') or (`status` IN ('INACTIVE','ACTIVE') AND `deleteOperationStatus` IN ('PENDING','SUCCEEDED'))")
                ->distinct()->limit($limit)->orderBy('updated_at')->pluck('listingId');
            if (!$listingIds) {
                break;
            }

            $api = new StockxApi();

            foreach ($listingIds as $listingId) {
                dump($listingId);
                $list = $api->getList($listingId);
                $res = $this->_stockxHandle($list);
                if ($res['add'] ?? false) $add_num++;
                if ($res['cancel'] ?? false) $cancel_num++;
                sleep(1);
            }

            if (count($listingIds) < $limit) {
                break;
            }
        }

        Robot::sendNotice(sprintf('stockx出价查询执行完毕，耗时%d秒，出价%d条，取消%d条。', time() - $start, $add_num, $cancel_num));
    }

    private function _stockxHandle($list)
    {
        $logic = new BiddingAsyncLogic();
        $channel = new STOCKX();
        $add = false;
        $cancel = false;

        $item = $channel->syncBiddingRes($list);
        dump($item);
        if (isset($item['add_success'])) {
            if ($item['add_success']) {
                // 出价成功
                $logic->bidSingleSuccess([
                    'channel_code' => $channel::$code,
                    'bidding_no' => $item['bidding_no'],
                    'spu_id' => $item['spu_id'],
                    'sku_id' => $item['sku_id'],
                    'product_id' => 0,
                    'price' => $item['amount'],
                    'properties' => ['size' => $item['variantValue']],
                    'channel_bidding_item_status' => 0,
                    'shelf_at' => '',
                    'takedown_at' => '',
                ]);
                $add = true;
            } else {
                // 出价失败
                $logic->bidFail([
                    'channel_code' => $channel::$code,
                    'bidding_no' => $item['bidding_no'],
                    'fail_reason' => $item['error'] ?? '',
                ]);
                $cancel = true;
            }
        }

        // 出价取消
        if ($item['delete_success'] ?? false) {
            $logic->bidCancelSuccess([
                'channel_code' => $channel::$code,
                'bidding_no' => $item['bidding_no'],
            ]);
            $cancel = true;
        }
        return compact('add', 'cancel');
    }

    function _carryme()
    {
        // 原始出价已取消，渠道出价还有效，取消这类出价
        dump('同步Carryme渠道未取消的出价');
        $ids = DB::table('stock_bidding_items as sbi')
            ->leftJoin('channel_bidding as cb', 'sbi.id', '=', 'cb.stock_bidding_item_id')
            ->where('sbi.status', StockBiddingItem::STATUS_CANCEL)
            ->where('sbi.updated_at', '<', date('Y-m-d H:i:s', time() - 60 * 3))
            ->where('cb.status', ChannelBidding::BID_SUCCESS)
            ->where('cb.qty_sold', 0)
            ->select('cb.*')
            ->pluck('cb.id')->toArray();
        if (!$ids) return;

        $biddings = ChannelBidding::whereIn('id', $ids)->get();
        foreach ($biddings as $bidding) {
            $channel = new ChannelLogic($bidding->channel_code);
            $channel->biddingCancel($bidding);
        }

        // DW商品空卖到CARRYME的出价
        $ids = DB::table('channel_purchase_biddings as cpb')
            ->leftJoin('channel_bidding as cb', 'cpb.bidding_no', '=', 'cb.bidding_no')
            ->where('cpb.status', ChannelPurchaseBidding::CANCEL)
            ->where('cpb.updated_at', '<', date('Y-m-d H:i:s', time() - 60 * 5))
            ->where('cb.status', ChannelBidding::BID_SUCCESS)
            ->where('cb.qty_sold', 0)
            ->select('cb.*')
            ->pluck('cb.id')->toArray();
        if (!$ids) return;

        $biddings = ChannelBidding::whereIn('id', $ids)->get();
        foreach ($biddings as $bidding) {
            $channel = new ChannelLogic($bidding->channel_code);
            $channel->biddingCancel($bidding);
        }

    }
}
