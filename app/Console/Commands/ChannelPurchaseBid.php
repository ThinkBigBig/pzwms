<?php

namespace App\Console\Commands;

use App\Logics\channel\DW;
use App\Logics\ChannelPurchaseLogic;
use App\Logics\ProductLogic;
use App\Logics\RedisKey;
use App\Logics\Robot;
use App\Models\ChannelProduct;
use App\Models\ChannelPurchaseBidding;
use App\Models\PurchaseProduct;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ChannelPurchaseBid extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'channel-purchase  {type="bid"}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '三方商品空卖到CARRYME-出价执行';

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
        $type = $this->argument('type');
        if ($type == 'bid') {
            $this->bid();
            return 0;
        }

        if ($type == 'cancel') {
            $this->cancel();
            return 0;
        }
    }

    function bid()
    {
        $key = RedisKey::LOCK_CHANNEL_PURCHASE;
        if (!Redis::setnx($key, date('Y-m-d H:i:s'))) {
            // Robot::sendNotice($key . '进行中，本次不执行');
            return 0;
        }

        $start = time();
        $logic = new ChannelPurchaseLogic();
        try {
            // 同步商品信息
            while (true) {
                $data = Redis::lpop(RedisKey::CHANNEL_PURCHASE_QUEUE);
                dump($data);
                if (!$data) {
                    sleep(10);
                } else {
                    $data = json_decode($data, true);
                    $logic->bid($data);
                }
                
                if (!Redis::get($key)) break;
                if (time() - $start > 3600) break;
            }
        } catch (Exception $e) {
            Robot::sendException('channel-purcahse-bid ' . $e->__toString());
        } finally {
            Redis::del($key);
        }
    }

    // 添加商品后初始化并触发出价
    function _init()
    {
        try {
            while (true) {
                $data = Redis::lpop(RedisKey::PURCHASE_PRODUCT_QUEUE);
                $data = json_decode($data, true);
                dump($data);
                $product_sn = $data['product_sn'] ?? '';
                $init = $data['product_init'] ?? 0;
                if (!$product_sn) {
                    break;
                } else {
                    $api = new DW();
                    if ($init) {
                        // 更新商品信息得物和CARRYME的商品信息
                        ProductLogic::updateProduct($product_sn, 'DW');
                        ProductLogic::updateProduct($product_sn, 'CARRYME');
                    }

                    $product = ChannelProduct::where(['channel_code' => 'DW', 'product_sn' => $product_sn, 'status' => ChannelProduct::STATUS_ACTIVE])->first();
                    if (!$product) {
                        continue;
                    }
                    // 刷新所有规则最低价
                    $skus = $product->skus;
                    foreach ($skus as $sku) {
                        $api->syncLowestPrice(['spu_id' => $sku->spu_id, 'sku_id' => $sku->sku_id]);
                    }
                }
            }
        } catch (Exception $e) {
            Robot::sendException('channel-purcahse-init ' . $e->__toString());
        }
    }

    function cancel()
    {
        $key = RedisKey::LOCK_CHANNEL_PURCHASE_CANCEL;
        if (!Redis::setnx($key, date('Y-m-d H:i:s'))) {
            // Robot::sendNotice($key . '进行中，本次不执行');
            return 0;
        }
        $list = DB::table('channel_purchase_biddings as cpb')
            ->leftJoin('purchase_products as pp', 'cpb.product_sn', 'pp.product_sn')
            ->where('cpb.status', ChannelPurchaseBidding::SUCCESS)
            ->where('cpb.qty_sold', 0)
            ->where('pp.status', PurchaseProduct::INACTIVE)
            ->select(['cpb.*'])
            ->get();

        $logic = new ChannelPurchaseLogic();
        foreach ($list as $bidding) {
            try {
                $bid = ChannelPurchaseBidding::where('id', $bidding->id)->first();
                dump($bid->bidding_no);
                $logic->bidCancel($bid, ['cancel_remark' => '商品下架']);
            } catch (Exception $e) {
                Robot::sendException('channel-purcahse-cancel ' . $e->__toString());
            }
        }

        $this->_init();

        Redis::del($key);
    }
}
