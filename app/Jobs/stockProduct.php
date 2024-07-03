<?php

namespace App\Jobs;

use App\Logics\BaseLogic;
use App\Logics\BiddingAsyncLogic;
use App\Logics\channel\GOAT;
use App\Logics\OrderLogic;
use App\Logics\RedisKey;
use App\Logics\Robot;
use App\Logics\StockProductLogic;
use App\Models\ChannelBidding;
use App\Models\ChannelProduct;
use App\Models\ChannelProductSku;
use App\Models\StockProductChannel;
use App\Models\StockProductLog;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class stockProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $params;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($params)
    {
        $this->params = $params;
    }

    const ITEM_CLEAR = 'item-clear';
    const GOAT_REFRESH = 'goat-lowest-refresh';

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        try {
            switch ($this->params['action']) {
                case 'stock-product-sync':
                    $this->stockProductSync();
                    break;
                case 'order-confirm':
                    $this->orderConfirm();
                    break;
                case 'order-send-out':
                    $this->orderSendOut();
                    break;
                case self::ITEM_CLEAR:
                    StockProductLogic::clearItem(['id' => $this->params['id'], 'remark' => '售罄下架']);
                    break;
                case self::GOAT_REFRESH:
                    $this->goatLowestRefresh();
                    break;
            }
        } catch (Exception $e) {
            Robot::sendException($e->__toString());
        }
    }

    // goat商品最低价有变化，判断是否要重新出价
    function goatLowestRefresh()
    {
        $spu_id = $this->params['spu_id'];
        $sku_id = $this->params['sku_id'] ?? 0;
        $cp_id = $this->params['cp_id'] ?? 0;
        if (!$sku_id) return;

        $sku = ChannelProductSku::where(['spu_id' => $spu_id, 'sku_id' => $sku_id, 'cp_id' => $cp_id, 'status' => 1])->first();
        if (!$sku) return;

        $goat = new GOAT();
        $logic = new BiddingAsyncLogic();
        $channel_sku = StockProductChannel::where(['channel_code' => 'GOAT', 'channel_product_sku_id' => $sku->id])->orderBy('id', 'desc')->first();
        if (!$channel_sku) return;
        $stock_product = $channel_sku->stockProductActive;
        if (!$stock_product) return;
        // 商品有库存
        if ($stock_product->stock <= $stock_product->order_stock) return;

        $bidding  = ChannelBidding::where(['channel_code' => 'GOAT', 'sku_id' => $sku->sku_id, 'qty_sold' => 0, 'status' => ChannelBidding::BID_SUCCESS])->orderBy('id', 'desc')->first();
        // 最低价有变化
        $change = (!$bidding) || ($bidding->lowest_price > 0 && $bidding->lowest_price != $sku->lowest_price);
        if (!$change) return;
        // 出价金额已经是最低价，不再刷新
        if ($bidding && $bidding->price == $sku->lowest_price) return;

        // 满足出价规则
        $data = $goat->stockBidPriceHandle([], ['lowest_price' => $sku->lowest_price, 'lowest_price_jpy' => $sku->lowest_price_jpy], $stock_product);
        // BaseLogic::log('GOAT最低价变更2', ['stock_product_id' => $stock_product->id, 'old_lowest_price' => $bidding ? $bidding->lowest_price : 0, 'new_lowest_price' => $sku->lowest_price]);
        // 重新出价
        if ($data['price'] ?? '') {
            $logic->stockProductRefreshBid($stock_product, 'goat最低价变更');
        }
    }

    // 批量发货
    public function orderSendOut()
    {
        $order_ids = $this->params['order_ids'];
        Log::channel('daily2')->info('order-send-out', [$order_ids]);
        $logic = new OrderLogic();
        foreach ($order_ids as $order_id) {
            $logic->platformConfirm(['order_id' => $order_id, 'remark' => '导出发货单发货', 'admin_user_id' => $this->params['admin_user_id']]);
        }
    }

    // 批量确认
    public function orderConfirm()
    {
        $data = $this->params['order_ids'];
        $admin_user_id = $this->params['admin_user_id'] ?? 0;

        $this->_order_confirm($data, $admin_user_id);
        return true;

        if (count($data) <= 4) {
            $this->_order_confirm($data, $admin_user_id);
            return true;
        }

        $len = ceil(count($data) / 4);

        for ($i = 0; $i < 4; $i++) {
            $arr = array_slice($data, $i * $len, $len);
            DB::disconnect();
            // 在当前位置创建子进程,父进程中执行，返回值>0，返回的是子进程号,子进程中执行，返回的是0
            $pid = pcntl_fork();
            if ($pid == -1) die('process fork failed');
            if ($pid) {
                Log::channel('daily2')->info('order', ['当前在父进程，进程号 ' . getmypid() . '，新建进程号 ' . $pid]);
            } else {
                Log::channel('daily2')->info('order', ['当前在子进程，进程号 ' . getmypid()]);
                $this->_order_confirm($arr, $admin_user_id);
                exit();
            }
        }

        while (pcntl_waitpid(0, $status) != -1) {
            $status = pcntl_wexitstatus($status);
            echo "子进程结束，PID: " . $status . "\n";
        }
        return true;
    }

    private function _order_confirm($arr, $admin_user_id)
    {
        Log::channel('daily2')->info('order-confirm', [$arr]);
        $pdo = DB::getPdo();
        $api = new OrderLogic();
        foreach ($arr as $order_id) {
            $api->businessConfirm(['order_id' => $order_id, 'remark' => '批量确认发货', 'admin_user_id' => $admin_user_id]);
            $msg = sprintf('订单确认 order_id:%d res:%s err_msg:%s', $order_id, $api->success, $api->err_msg);
            dump($msg);
        }
    }

    private function stockProductSync()
    {
        $logic = new StockProductLogic();
        // dump($this->params['batch_no']);
        $logs = StockProductLog::where(['batch_no' => $this->params['batch_no'], 'sync_status' => StockProductLog::SYNC_WAIT])->get();

        $cancel_arr = [];
        $add_arr = [];
        foreach ($logs as $log) {
            try {
                $res = $logic->sync($log);
                if ($res) {
                    // 要取消的规格
                    if ($res['cancel']) {
                        $cancel_arr[] = $log->product_sn . ',' . $log->properties;
                    }
                    // 要新增出价的规格
                    if ($res['bid']) {
                        $add_arr[] = $log->product_sn . ',' . $log->properties;
                    }

                    // 清空商品
                    if (($res['clear'] ?? false) && ($res['stock_product_id'] ?? 0)) {
                        StockProductLogic::clearItem(['id' => $res['stock_product_id'], 'remark' => '0库存清空']);
                    }
                }
            } catch (Exception $e) {
                Robot::sendException($e->__toString());
            }
        }

        $cancel_arr = array_unique($cancel_arr);
        $add_arr = array_unique($add_arr);

        $bidding = new BiddingAsyncLogic();
        foreach ($cancel_arr as $item) {
            $arr = explode(',', $item);
            $action = [];
            // 出价全部取消后重新上架
            $key = sprintf('%s_%s', $arr[0], $arr[1]);
            Redis::hset(RedisKey::SKU_BID_AFTER_CANCEL, $key, '商品信息更新');
            $bidding->stockBiddingCancel([
                'product_sn' => $arr[0],
                'properties' => $arr[1],
                'remark' => '商品更新取消出价',
                'after_action' => $action,
            ]);
        }

        // 新增商品上架
        $add_arr2 = array_diff($add_arr, $cancel_arr);
        foreach ($add_arr2 as $item) {
            $arr = explode(',', $item);
            $bidding->stockBiddingAdd([
                'product_sn' => $arr[0],
                'properties' => $arr[1],
                'remark' => '商品信息更新',
                'product_init' => true,
            ]);
        }
    }
}
