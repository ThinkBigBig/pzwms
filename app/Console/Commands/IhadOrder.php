<?php

namespace App\Console\Commands;

use App\Handlers\HttpService;
use App\Logics\BaseLogic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Psy\Util\Json;

class IhadOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ihad-order';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        // BaseLogic::log('开始同步ihad订单',[]);
        // 获取从15号开始的app订单，没有app_bidding的，同步给ihad
        $info = DB::select('SELECT ao.* from app_orders ao 
        left JOIN app_biddings ab ON ao.logId=ab.logId
        WHERE ao.created_at>? AND ab.id IS NULL', [date('Y-m-d H:i:s', time() - 600)]);
        
        if(!count($info)){
            // BaseLogic::log('没有可同步的订单',[]);
            return 0;
        }
        foreach ($info as $item) {
            BaseLogic::log($item->orderNo,[]);
            $data = [
                "orderNo" => $item->orderNo,
                "quantity" => $item->quantity,
                "orderId" => $item->orderId,
                "price" => $item->price,
                "logId" => $item->logId,
                "paytime" => $item->paytime,
                "source" => "carry-me-api",
                "timestamp" => time(),
            ];
            $this->sign($data);
            // dd($data);
            $res = HttpService::request('post', env('IHAD_HOST').'/callback/carryme', ['data' => $data, 'header' => ["content-type: application/json"]], true);
            $res = $res ? json_decode($res, true) : [];
            if (($res['code'] ?? '') == 200) {
                // 通知成功
                BaseLogic::log('通知成功',[]);
            } else {
                BaseLogic::log('通知失败',[]);
            }
        }
        return 0;
    }

    protected function sign(&$data): bool
    {
        // 移除sign字段
        if (isset($data['sign'])) {
            unset($data['sign']);
        }
        $secret_key = 'X0Km5kRdo3SwhE8RDeU3qTA2xzvDONIEwXN9XEsw2FlY0dnkm2Xi4hSia5kRFxt2';
        ksort($data);
        $arr = [];
        foreach ($data as $k => $v) {
            if ("sign" !== $k) {
                $v = is_array($v) ? Json::encode($v) : $v;
                $arr[] = $k . '=' . $v;
            }
        }

        $str = implode('&', $arr);
        $data['sign'] = md5($str . $secret_key);
        return true;
    }
}
