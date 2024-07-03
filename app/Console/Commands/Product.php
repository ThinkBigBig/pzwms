<?php

namespace App\Console\Commands;

use App\Logics\RedisKey;
use App\Logics\Robot;
use App\Logics\ShenDu;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class Product extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步慎独商品库存信息';

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
        $num1 = 0;
        $num2 = 0;
        $num3 = 0;
        $logic = new ShenDu();
        try {
            // 同步商品信息
            while (true) {
                $data = Redis::lpop(RedisKey::PRODUCT_QUEUE);
                if (!$data) {
                    break;
                }
                $logic->productSync($data);
                $num1++;
            }
        } catch (Exception $e) {
            Robot::sendException($e->__toString());
        }


        try {
            // 同步库存信息
            while (true) {
                $data = Redis::lpop(RedisKey::PRODUCT_STOCK_QUEUE);
                if (!$data) {
                    break;
                }
                $logic->productStockSync($data);
                $num2++;
            }
        } catch (Exception $e) {
            Robot::sendException($e->__toString());
        }


        try {
            // 同步库存明细
            while (true) {
                $data = Redis::lpop(RedisKey::PRODUCT_STOCK_DETAIL_QUEUE);
                if (!$data) {
                    break;
                }
                $logic->productStockDetailSync($data);
                $num3++;
            }
        } catch (Exception $e) {
            Robot::sendException($e->__toString());
        }
        Robot::sendNotice(sprintf('同步慎独商品信息，批次数：商品%d 商品库存%d 商品库存明细%d', $num1, $num2, $num3));
        return 0;
    }
}
