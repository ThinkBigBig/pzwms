<?php

namespace App\Console\Commands;

use App\Handlers\GoatApi;
use App\Logics\RedisKey;
use App\Models\GoatOrderLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class GoatAllOrder extends Command
{
    public $api ;
    public $init_date = '2023-04-21T00:00:00.108Z';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'goat-all-order';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '全量拉取goat订单';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->api = new GoatApi('https://www.goat.com');
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
        $this->sell();
        $this->sell_with_issue();
        $this->need_to_confirm();
        $this->need_to_ship();
    }

    public function sell()
    {
        dump('sell');
        $page = 1;
        $run = true;
        $new_updated_at = '';

        $key = RedisKey::GOAT_ORDERS_SELL;
        //查最近30分钟内更新的订单
        $updated_at = Redis::get($key) ?: $this->init_date; //上次更新时间
        
        do {
            dump($page,date('Y-m-d H:i:s'));
            $res = $this->api->getOrders(['page' => $page,'status'=>'sell']);
            $orders = $res['orders'];
            $total = $res['metadata']['totalPages'];
            foreach ($orders as $order) {
                dump($order['updatedAt']);
                if (!$new_updated_at) $new_updated_at = $order['updatedAt'];
                if ($order['updatedAt'] < $updated_at) {
                    $run = false;
                    break;
                }
                $this->add($order);
            }
            $page++;
            sleep(1);
        } while ($run && $page <= $total && $run);
        Redis::set($key, $new_updated_at);
    }


    public function sell_with_issue()
    {
        dump('sell_with_issue');
        $page = 1;
        $run = true;
        $new_updated_at = '';

        $key = RedisKey::GOAT_ORDERS_ISSUE;
        //查最近30分钟内更新的订单
        $updated_at = Redis::get($key) ?: $this->init_date; //上次更新时间
        
        do {
            dump($page,date('Y-m-d H:i:s'));
            $res = $this->api->getOrders(['page' => $page,'status'=>'sell_with_issue']);
            $orders = $res['orders'];
            $total = $res['metadata']['totalPages'];
            foreach ($orders as $order) {
                dump($order['updatedAt']);
                if (!$new_updated_at) $new_updated_at = $order['updatedAt'];
                if ($order['updatedAt'] < $updated_at) {
                    $run = false;
                    break;
                }
                $this->add($order);
            }
            $page++;
            sleep(1);
        } while ($run && $page <= $total && $run);
        Redis::set($key, $new_updated_at);
    }

    public function need_to_confirm()
    {
        dump('need_to_confirm');
        $page = 1;
        $run = true;
        $new_updated_at = '';

        $key = RedisKey::GOAT_ORDERS_CONFIRM;
        //查最近30分钟内更新的订单
        $updated_at = Redis::get($key) ?: $this->init_date; //上次更新时间
        
        do {
            dump($page,date('Y-m-d H:i:s'));
            $res = $this->api->getOrders(['page' => $page,'status'=>'need_to_confirm']);
            $orders = $res['orders'];
            $total = $res['metadata']['totalPages'];
            foreach ($orders as $order) {
                dump($order['updatedAt']);
                if (!$new_updated_at) $new_updated_at = $order['updatedAt'];
                if ($order['updatedAt'] < $updated_at) {
                    $run = false;
                    break;
                }
                $this->add($order);
            }
            $page++;
            sleep(1);
        } while ($run && $page <= $total && $run);
        Redis::set($key, $new_updated_at);
    }

    public function need_to_ship()
    {
        dump('need_to_ship');
        $page = 1;
        $run = true;
        $new_updated_at = '';

        $key = RedisKey::GOAT_ORDERS_SHIP;
        //查最近30分钟内更新的订单
        $updated_at = Redis::get($key) ?: $this->init_date; //上次更新时间
        
        do {
            dump($page,date('Y-m-d H:i:s'));
            $res = $this->api->getOrders(['page' => $page,'status'=>'need_to_ship']);
            $orders = $res['orders'];
            $total = $res['metadata']['totalPages'];
            foreach ($orders as $order) {
                dump($order['updatedAt']);
                if (!$new_updated_at) $new_updated_at = $order['updatedAt'];
                if ($order['updatedAt'] < $updated_at) {
                    $run = false;
                    break;
                }
                $this->add($order);
            }
            $page++;
            sleep(1);
        } while ($run && $page <= $total && $run);
        Redis::set($key, $new_updated_at);
    }

    private function add($order)
    {
        $where = [
            'order_id' => $order['id'],
            'product_id' => $order['productId'],
            'number' => $order['number'],
            'purchase_order_number' => $order['purchaseOrderNumber'],
            'status' => $order['status'],
            'order_updated_at' => $order['updatedAt'],
        ];

        GoatOrderLog::firstOrCreate($where,[ 
            'purchased_at' => $order['purchasedAt'],
            'end_state' => $order['endState'],
            'seller_title' => $order['sellerTitle'],
            'seller_description' => $order['sellerDescription'],
            'final_sale' => $order['finalSale'],
        ]);
    }
}
