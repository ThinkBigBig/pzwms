<?php

namespace App\Http\Controllers\Admin\V1;

use App\Exports\Export;
use App\Handlers\GoatApi;
use App\Handlers\OSSUtil;
use App\Handlers\StockxApi;
use App\Http\Controllers\Admin\V1\BaseController;
use App\Imports\Import;
use App\Imports\SizeStandardImport;
use App\Logics\bid\Excute;
use App\Logics\BiddingAsyncLogic;
use App\Logics\BidExecute;
use App\Logics\ChannelLogic;
use App\Logics\ExchangeLogic;
use App\Logics\FreeTaxLogic;
use App\Logics\NoticeLogic;
use App\Logics\ProductLogic;
use App\Logics\RedisKey;
use App\Logics\StockProductLogic;
use App\Models\Admin\V2\ProductCategory;
use App\Models\Shipment;
use Illuminate\Http\Request;
use App\Models\CarryMeBidding;
use App\Models\CarrymeCallbackLog;
use App\Models\CarrymeNoticeLog;
use App\Models\ChannelBidding;
use App\Models\ChannelOrder;
use App\Models\ChannelProduct;
use App\Models\ChannelProductSku;
use App\Models\SizeStandard;
use App\Models\ErpLog;
use App\Models\GoatOrderLog;
use App\Models\ProductBrand;
use App\Models\SizeMatchRule;
use App\Models\StockBiddingItem;
use App\Models\StockProduct;
use App\Models\StockProductChannel;
use App\Models\StockProductLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Maatwebsite\Excel\Facades\Excel;
use stdClass;

class ToolsController extends BaseController
{

    //给指定商品创建预约单
    function reservation(Request $request)
    {
        return (new FreeTaxLogic())->reservationInit($request->get('product_sn', []));
    }

    //初始化预约单
    public function reservationInit()
    {
        return (new FreeTaxLogic())->reservationInit();
    }


    //erp数据校验
    public function dataCheck(Request $request)
    {
        $tool = $request->get('tool');
        switch ($tool) {
            case 1: //三方出价日志
                return $this->erp_logs($request);
                break;
            case 2: //出价信息
                return $this->bidding_info($request);
                break;
            case 3: //更新出价单状态
                return $this->updateBidStatus($request);
                break;
            case 4: //回调信息
                return $this->notice_logs($request);
                break;
            case 5: //商品信息
                return $this->product_info($request);
                break;
            case 6: //最低价更新结果
                return $this->lowest_price($request);
                break;
            case 7: //上传OSS内容
                return $this->uploadOSS($request);
                break;
            case 8: //goat订单状态更新
                return $this->goat_order($request);
                break;
            case 9: //修改回调内容
                return $this->updateCarrymeCallback($request);
                break;
            case 10: //goat订单状态查询
                return $this->goatOrderLogs($request);
                break;
            case 11: //配置信息
                return $this->envConfig($request);
            case 12: //导出未匹配商品规格
                return $this->skuNotMatch($request);
                break;
            case 13:
                return $this->sizeSync($request);
                break;
            case 14:
                return $this->updateProductMatchRule($request);
                break;
            case 16: //同步指定货号的最低价
                $api = new ChannelLogic($request->get('channel_code', ''));
                return $api->syncLowestPrice($request->all());
                break;
            case 17:
                return $this->export();
                break;
            case 18:
                return $this->fixChannelBiddingQty();
            case 19: //导出渠道尺码信息
                return $this->exportSize($request);
                break;
            case 20: //更新尺码标准
                return $this->importSize($request);
                break;
            case 21: //查询库存商品更新纪录
                return $this->stockProductLog($request);
                break;
            case 22: //stockx初始化
                return $this->stockxInit($request);
            case 23:
                return $this->fixData($request);
                break;
            case 24: //取消指定出价
                return $this->cancelBidding($request);
                break;
            case 25: //redis
                return $this->redisList($request);
                break;
            case 26:
                return $this->stockProductUpdate($request);
                break;
            case 27:
                return $this->updateSizeBrand($request);
                break;
            case 28:
                return $this->stockRefreshBid($request);
                break;
            case 29:
                return $this->channelProductClear($request);
                break;
            case 30:
                return $this->unlock($request);
                break;
            case 31:
                return $this->biddingSync($request);
                break;
            case 32:
                return $this->sqlSelect($request);
                break;
            case 33:
                return $this->logDownload($request);
            case 34:
                return $this->refreshProductBiddingStock($request);
                break;
            case 100: //发货单数据
                return $this->shipment($request);
                break;
            case 200:
                return $this->wmsBc($request);
                break;
            case 201:
                return $this->wmsCache($request);
                break;
        }
    }
    
    function wmsCache($request)
    {
        $type = $request->get('type');
        if ($type == 1) {
            ProductCategory::cacheAll();
        }
        return true;
    }

    function wmsBc($request)
    {
        $type = $request->get('type', 1);
        $map = [
            '1' => RedisKey::QUEUE_AYSNC_HADNLE,
            '2' => RedisKey::QUEUE2_AYSNC_HADNLE,
        ];
        $name = $map[$type];
        $data = json_decode($request->get('data'), true);
        Redis::rpush($name, json_encode($data));
        return true;
    }


    function logDownload(Request $request)
    {
        $path = $request->get('path');
        $file = storage_path($path);
        if (file_exists($file)) {
            // 获取文件大小
            $fileSize = filesize($file);

            // 设置响应头
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . pathinfo($file)['basename'] . '"');
            header('Content-Length: ' . $fileSize);

            // 打开文件并分块读取
            $bufferSize = 5 * 1024 * 1024; // 1MB
            $handle = fopen($file, 'rb');
            while (!feof($handle)) {
                // 读取文件块并写入输出缓冲区
                $buffer = fread($handle, $bufferSize);
                echo $buffer;
            }

            // 关闭文件句柄
            fclose($handle);

            // 刷新输出缓冲区
            ob_flush();
        } else {
            echo '文件不存在';
        }
    }

    // 刷新商品出价库存
    function refreshProductBiddingStock(Request $request)
    {
        $product_id = $request->get('product_id');
        StockProductLogic::stockUpdate($product_id, StockProductLogic::STOCK_UPDATE_BID);
        return StockProduct::where(['id' => $product_id])->first();
    }

    function sqlSelect(Request $request)
    {
        $sql = $request->get('sql');
        return DB::select($sql);
    }
    // 同步出价信息
    function biddingSync(Request $request)
    {
        $bidding_no = $request->get('bidding_no');
        $bidding = ChannelBidding::where(['bidding_no' => $bidding_no])->first();
        if (!$bidding) return [];
        $api = new ChannelLogic($bidding->channel_code);
        $api->syncBiddingInfo($bidding);
        return ChannelBidding::where(['bidding_no' => $bidding_no])->first();
    }

    // redis解锁
    function unlock(Request $request)
    {
        $key = $request->get('key');
        Redis::del('wms:' . $key);
        return $this->redisList($request);
    }

    // 清除渠道商品信息
    function channelProductClear(Request $request)
    {
        $product_sn = $request->get('product_sn');
        $channel_code = $request->get('channel_code');
        $where = ['channel_code' => $channel_code, 'product_sn' => $product_sn, 'status' => ChannelProduct::STATUS_ACTIVE];
        $cp_ids = ChannelProduct::where($where)->pluck('id')->toArray();
        ChannelProduct::where($where)->update(['status' => ChannelProduct::STATUS_DEFAULT]);
        ChannelProductSku::whereIn('cp_id', $cp_ids)->update(['status' => ChannelProductSku::STAUTS_OFF]);
        return ChannelProduct::where($where)->get();
    }

    // 刷新指定商品的库存出价
    function stockRefreshBid(Request $request)
    {
        $product_sn = $request->get('product_sn');
        $properties = $request->get('properties');
        $product = StockProduct::where(['product_sn' => $product_sn, 'properties' => $properties, 'is_deleted' => 0, 'status' => 0])->first();
        if (!$product) {
            return '未获取到商品信息';
        }
        StockProductLogic::stockUpdate($product->id, StockProductLogic::STOCK_UPDATE_BID);
        if ($product->stock == 0 || $product->stock <= $product->order_stock) {
            return '没有可售库存';
        }
        $lock = 'erp:stock_product_rebid:' . $product->id;
        Redis::del($lock);
        $logic = new BiddingAsyncLogic();
        $logic->stockProductRefreshBid($product, '手动刷新');
        return '执行完成';
    }

    // 更新商品所属尺码品牌
    public function updateSizeBrand(Request $request)
    {
        $arr = Excel::toArray(new stdClass(), $request->file('file'));

        $res = [];
        foreach ($arr[0] as $item) {
            if ($item[0] == '货号') continue;
            $size_brand = str_replace(' ', '_', $item[2]);
            $res[$item[0]] = ['brand' => strtolower($item[3]), 'size_brand' => strtolower($size_brand)];
        }
        foreach ($res as $product_sn => $data) {
            ProductBrand::updateOrCreate(['product_sn' => $product_sn], $data);
        }
        return count($res);
    }

    public function stockProductUpdate(Request $request)
    {
        $product_sn = $request->get('product_sn');
        // 找到货号中channel_product_sku_id为空的数据，挨个重新匹配
        $sql = "select sp.product_sn,sp.properties,spc.id,spc.channel_code,spc.stock_product_id 
        from erp.stock_product_channels spc 
        left join erp.stock_products sp on spc.stock_product_id = sp.id
        where channel_product_sku_id=0 and sp.product_sn=? and sp.is_deleted=0";
        $list = DB::select($sql, [$product_sn]);
        if (!$list) return [];
        foreach ($list as $item) {
            $skus = ProductLogic::getSku($item->product_sn, [['valuecn' => $item->properties]], true);
            $goat_where = [
                'channel_code' => "GOAT",
                'stock_product_id' => $item->stock_product_id,
            ];

            $goat_update = ['channel_product_sku_id' => $skus["GOAT"]['channel_product_sku_id'] ?? 0,];

            $dw_where = [
                'channel_code' => "DW",
                'stock_product_id' => $item->stock_product_id,
            ];

            $dw_update = ['channel_product_sku_id' => $skus["DW"]['channel_product_sku_id'] ?? 0,];

            $stockx_where = [
                'channel_code' => "STOCKX",
                'stock_product_id' => $item->stock_product_id,
            ];

            $stockx_update = ['channel_product_sku_id' => $skus["STOCKX"]['channel_product_sku_id'] ?? 0,];

            $carryme_where = [
                'channel_code' => "CARRYME", 'stock_product_id' => $item->stock_product_id,
            ];
            $carryme_update = ['channel_product_sku_id' => $skus["CARRYME"]['channel_product_sku_id'] ?? 0,];

            StockProductChannel::updateOrCreate($goat_where, $goat_update);
            StockProductChannel::updateOrCreate($dw_where, $dw_update);
            StockProductChannel::updateOrCreate($stockx_where, $stockx_update);
            StockProductChannel::updateOrCreate($carryme_where, $carryme_update);
        }
        return $list;
    }

    public function redisList(Request $request)
    {
        $redis = Redis::connection('redis_token');
        $token = $redis->get("DW_TOKEN");
        $key = $request->get('key', '');
        $data = $key ? Redis::get($key) : '';
        $type = $request->get('type', '');
        $len = 0;
        if ($type == 'queue') {
            $len = Redis::lLen($key);
        }
        return [
            'time' => date('Y-m-d H:i:s'),
            'bid-result' => Redis::get(RedisKey::RUN_TAG_BID_RESULT),
            'order' => Redis::get(RedisKey::RUN_TAG_ORDER),
            'dispatch_num' => Redis::get(RedisKey::RUN_TAG_DISPATCH_NUM),
            'queues:product' => Redis::llen('queues:product'),
            'queues:default' => Redis::llen('queues:default'),
            'erp:bid_lock_add_0' => Redis::get('erp:bid_lock_add_0'),
            'erp:bid_lock_add_1' => Redis::get('erp:bid_lock_add_1'),
            'erp:bid_lock_add_2' => Redis::get('erp:bid_lock_add_2'),
            'erp:bid_lock_cancel_0' => Redis::get('erp:bid_lock_cancel_0'),
            'erp:lock_channel_purchase' => Redis::get(RedisKey::LOCK_CHANNEL_PURCHASE),
            'dw_token' => $token,
            $key => $data,
            'len' => $len,
        ];
    }

    public function stockxInit(Request $request)
    {
        $api = new StockxApi();
        return $api->getToken();
    }
    // 取消指定出价
    public function cancelBidding(Request $request)
    {
        $params = $request->all();
        if ($params['carryme_bidding_id'] ?? 0) {
            $where = [
                'carryme_bidding_id' => $params['carryme_bidding_id']
            ];
            $callback = ($params['callback'] ?? false) ? true : false;
            $biddings = ChannelBidding::where($where)->get();
            $res = [];
            foreach ($biddings as $bidding) {
                $res[$bidding->bidding_no] = BidExecute::cancel($bidding, $params['remark'] ?? '', $callback);
            }
            return $res;
        }

        if ($params['stock_bidding_ids'] ?? '') {
            $res = [];
            $ids = explode(',', $params['stock_bidding_ids']);
            foreach ($ids as $id) {
                $items = StockBiddingItem::where('stock_bidding_id', $id)
                    ->whereIn('status', [StockBiddingItem::STATUS_SUCCESS, StockBiddingItem::STATUS_DEFAULT])->get();
                $product_sn = '';

                foreach ($items as $item) {
                    if (!$product_sn) {
                        $product = $item->stockProduct;
                        $product_sn = $product ? $product->product_sn : '';
                    }
                    if (!$product_sn) continue;

                    Excute::stockCancel([
                        'stock_bidding_item_id' => $item->id,
                        'product_sn' => $product_sn,
                        'remark' => $params['remark'] ?? '手动取消',
                    ]);
                    $res[] = $item->id;
                }
            }


            return $res;
        }


        return [];
    }


    public function fixData(Request $request)
    {
        $where = ['channel_code' => 'GOAT', 'order_no' => '639320605'];
        return ChannelOrder::where($where)->first();
    }

    public function stockProductLog(Request $request)
    {
        $size = $request->get('size', 20);
        $where = [];
        if ($request->get('product_sn', '')) {
            $where['product_sn'] = $request->get('product_sn');
        }
        if ($request->get('properties', '')) {
            $where['properties'] = $request->get('properties');
        }
        if ($request->get('batch_no', '')) {
            $where['batch_no'] = $request->get('batch_no');
        }

        return StockProductLog::where($where)->orderBy('id', 'desc')->limit($size)->get();
    }

    // 更新数量
    public function fixChannelBiddingQty()
    {
        $list = ChannelBidding::where(['status' => ChannelBidding::BID_SUCCESS, 'channel_code' => 'GOAT', 'qty' => 1])->whereExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('channel_order')
                ->whereIn('status', [ChannelOrder::STATUS_CREATED, ChannelOrder::STATUS_CONFIRM, ChannelOrder::STATUS_DELIVER])
                ->whereRaw('channel_order.channel_bidding_id = channel_bidding.id');
        })->get();
        foreach ($list as $bidding) {
            $bidding->update([
                'qty_sold' => 1,
                'qty_remain' => 0,
            ]);
        }
        return [
            'total' => count($list),
        ];
    }

    public function export()
    {
        $res = '';
        $headers[] = [
            'size_us' => '美码',
            'size_eu' => '欧码',
        ];
        $skus = json_decode($res, true)[0]['skus'];
        $data = [];
        foreach ($skus as $sku) {
            $data[] = $sku['properties'];
        }

        $export = new Export($headers, $data);
        $name = sprintf('L47346600 goat尺码%s.xlsx', date('YmdHis'));
        return Excel::download($export, $name);
    }

    public function envConfig(Request $request)
    {
        $key = $request->get('key', '');
        return [
            'CARRYME_HOST' => env('CARRYME_HOST', ''),
            'ESAPI_HOST' => env('ESAPI_HOST', ''),
            $key => $key ? env($key) : '',
        ];
    }
    public function importSize(Request $request)
    {
        $channels = explode(',', $request->get('channels'));
        $arr = Excel::toArray(new stdClass(), $request->file('file'));

        $product_sns = [];
        foreach ($arr[0] as $k => $item) {
            if (!$k) continue;
            if (strtoupper($item[1])) {
                $product_sns[] = strtoupper($item[1]);
            }
        }
        $product_sns = array_unique($product_sns);
        if ($product_sns) {
            SizeStandard::whereIn('product_sn', $product_sns)->delete();
        }
        // 导入尺码标准
        Excel::import(new SizeStandardImport(), $request->file('file'));
        foreach ($channels as $channel_code) {
            if ($channel_code == 'DW') continue;
            foreach ($product_sns as $product_sn) {
                ProductLogic::updateProduct($product_sn, $channel_code);
            }
        }

        return $product_sns;
    }

    public function exportSize(Request $request)
    {
        $channel_code = $request->get('channel_code');
        $product_sn = $request->get('product_sn', '');
        $where = ['channel_code' => $channel_code];
        if ($product_sn) {
            $where['product_sn'] = $product_sn;
        }
        $res = ChannelProduct::where($where)->with('skus')->get()->toArray();

        if ($channel_code == 'GOAT') {
            $headers[] = [
                'product_sn' => '货号',
                'size_us' => '美码',
                'size_eu' => '欧码',
                'brand_name' => '品牌名',
                'good_name' => '商品名',
            ];
        }
        if ($channel_code == 'DW') {
            $headers[] = [
                'product_sn' => '货号',
                '尺码' => '尺码',
                'brand_name' => '品牌名',
                'good_name' => '商品名',
            ];
        }

        $data = [];
        foreach ($res as $item) {
            foreach ($item['skus'] as $sku) {
                $tmp = $sku['properties'];
                $tmp['product_sn'] = $item['product_sn'];
                $tmp['brand_name'] = $item['brand_name'];
                $tmp['good_name'] = $item['good_name'];
                $data[] = $tmp;
            }
        }

        $export = new Export($headers, $data);
        $name = sprintf('%s %s尺码%s.xlsx', $product_sn, $channel_code, date('YmdHis'));
        return Excel::download($export, $name);
    }

    public function goatOrderLogs(Request $request)
    {
        $where = [];
        $model = GoatOrderLog::orderBy('order_updated_at');
        if ($request->input('order_id', '')) {
            $model->where(['order_id' => $request->input('order_id')]);
        }
        if ($request->input('number', '')) {
            $number = $request->input('number');
            $model->where(function ($query) use ($number) {
                $query->where('number', '=', $number)->orWhere('purchase_order_number', '=', $number);
            });
        }
        return $model->get();
    }

    public function updateCarrymeCallback(Request $request)
    {
        $where = [];
        if ($request->input('id', '')) {
            $where['id'] = $request->input('id');
        }
        if ($request->input('channel_order_id', '')) {
            $where['channel_order_id'] = $request->input('channel_order_id');
        }
        if ($request->input('channel_bidding_id', '')) {
            $where['channel_bidding_id'] = $request->input('channel_bidding_id');
        }
        if ($request->input('carryme_bidding_id', '')) {
            $where['carryme_bidding_id'] = $request->input('carryme_bidding_id');
        }
        if ($request->input('carryme_bidding_item_id', '')) {
            $where['carryme_bidding_item_id'] = $request->input('carryme_bidding_item_id');
        }
        if (!$where) {
            return '查询条件为空';
        }
        $update = [];
        if ($request->input('status', '')) {
            $update['status'] = $request->input('status');
        }
        if ($request->input('request', '')) {
            $update['request'] = $request->input('request');
        }
        if (!$update) {
            return '更新内容为空';
        }
        CarrymeCallbackLog::where($where)->update($update);
        return '成功';
    }

    //goat订单状态更新
    public function goat_order(Request $request)
    {
        $api = new ChannelLogic('GOAT');
        $res = $api->goatOrderUpdate($request->all());
        return $res;
    }

    //查看sku最低价
    public function lowest_price(Request $request)
    {
        $sku = ChannelProductSku::where([
            'cp_id' => $request->get('cp_id'),
            'spu_id' => $request->get('spu_id'),
            'sku_id' => $request->get('sku_id')
        ])->first();
        $res = ProductLogic::lowestPrice($sku);
        return $res;
    }

    public function uploadOSS(Request $request)
    {
        $pre_path = $request->get('pre_path');
        $files = $request->file('files');
        $oss = new OSSUtil(['pre_path' => $pre_path]);
        $res = [];
        foreach ($files as $file) {
            $res[] = $oss->addFileByData($file->getClientOriginalName(), file_get_contents($file));
        }
        return $res;
    }


    //消息补发
    public  function sendNotice(Request $request)
    {
        $id = $request->post('notice_id');
        if (!$id) {
            return '缺少必要参数';
        }

        return NoticeLogic::sendNotice($id);
    }

    public function tools(Request $request)
    {
        $tool = $request->get('tool');
        switch ($tool) {
            case 1:
                return $this->erp_logs($request);
                break;
        }
    }

    private function product_info(Request $request)
    {
        $where = ['status' => ChannelProduct::STATUS_ACTIVE];
        if ($request->get('product_sn', '')) {
            $where['product_sn'] = $request->get('product_sn');
        }
        if ($request->get('channel_code', '')) {
            $where['channel_code'] = $request->get('channel_code');
        }
        $product = ChannelProduct::where($where)->with('skus')->get();
        return $product;
    }

    private function erp_logs(Request $request)
    {
        $where = [];
        $start = $request->get('start', date('Y-m-d H:i:s', strtotime('-1 hour')));
        $end = $request->get('end', date('Y-m-d H:i:s'));
        if ($request->get('channel_code', '')) {
            $where['channel_code'] =  $request->get('channel_code');
        }
        if ($request->get('url', '')) {
            $where['url'] =  $request->get('url');
        }
        return ErpLog::whereBetween('created_at', [$start, $end])->where($where)->get();
    }

    private function bidding_info(Request $request)
    {
        $carryme_bid_id = $request->get('carryme_bid_id', 0);
        $where = [];
        if ($carryme_bid_id) {
            $where['carryme_bidding_id'] = $carryme_bid_id;
        }
        if ($request->get('product_sn', '')) {
            $where['product_sn'] = $request->get('product_sn', '');
        }
        if ($request->get('sku_id', '')) {
            $where['sku_id'] = $request->get('sku_id', '');
        }
        if ($request->get('bidding_no', '')) {
            $where['bidding_no'] = $request->get('bidding_no', '');
        }
        if ($request->get('order_no', '')) {
            $where['id'] = ChannelOrder::where('order_no', $request->get('order_no'))->value('channel_bidding_id');
        }
        if ($request->get('channel_code', '')) {
            $where['channel_code'] = $request->get('channel_code', '');
        }

        $model = ChannelBidding::with('carrymeBiddingItem')
            ->with('carrymeBidding')->with('channelOrders', function ($query) {
                $query->with('callbackOrder')->with('callbackCancel');
            })->with('callbackBid')->with('product')->with('productSku');
        if ($where) {
            $model->where($where);
        }
        $data = $model->limit(100)->orderBy('id', 'desc')->get();

        return $data;
    }

    /**
     * 从三方获取不到出价信息后，手动更新出价单状态
     *
     * @param Request $request
     */
    private function updateBidStatus(Request $request)
    {
        $status = $request->get('status');
        $bidding_no = $request->get('bidding_no');
        $qty_sold = $request->get('qty_sold', 0);
        $channel_code = $request->get('channel_code');
        $remark = $request->get('remark', '手动更新状态');
        if (!($bidding_no && $channel_code && $status)) {
            return [];
        }

        $find = ChannelBidding::where('bidding_no', $bidding_no)->where('channel_code', $channel_code)->first();
        if (!$find) {
            return [];
        }
        $find->update(['status' => $status, 'qty_sold' => $qty_sold]);
        $carryme_item = $find->carrymeBiddingItem;
        if ($carryme_item && $carryme_item->status != $status) {
            $carryme_item->update([
                'status' => $status,
                'qty_sold' => $qty_sold,
                'remark' => $remark,
            ]);
        }
        $stock_item = $find->stockBiddingItem;
        if ($stock_item && $stock_item->status != $status) {
            $stock_item->update([
                'status' => $status,
                'qty_sold' => $qty_sold,
                'remark' => $remark,
            ]);
        }
        return $find;
    }

    //查通知信息
    private function notice_logs(Request $request)
    {
        $where = [];
        if ($request->get('id', 0)) {
            $where['id'] = $request->get('id');
        }
        if ($request->get('carryme_bidding_id', 0)) {
            $where['carryme_bidding_id'] = $request->get('carryme_bidding_id');
        }
        if ($request->get('channel_order_id', 0)) {
            $where['channel_order_id'] = $request->get('channel_order_id');
        }

        $notice_logs = CarrymeCallbackLog::where($where)->orderBy('id', 'desc')->limit(100)->get();
        $queue = DB::select('select * from jobs');
        $fialed_jobs = DB::select('select * from failed_jobs order by id desc limit 1');

        return compact('notice_logs', 'queue', 'fialed_jobs');
    }

    //查发货单数据
    private function shipment(Request $request)
    {
        $where = [];
        if ($request->get('product_sn')) {
            $where['product_sn'] = $request->get('product_sn');
        }
        if ($request->get('invoice_no')) {
            $where['invoice_no'] = $request->get('invoice_no');
        }
        return Shipment::where($where)->get();
    }

    //尺码标准维护
    public function sizeStandard(Request $request)
    {
        if ($request->get('update', '')) {
            ProductLogic::sizeStandard();
        }
        $where = [];
        if ($request->get('gender', '')) {
            $where['gender'] = $request->get('gender');
        }
        if ($request->get('brand', '')) {
            $where['brand'] = $request->get('brand');
        }
        if ($request->get('size_eu', '')) {
            $where['size_eu'] = $request->get('size_eu');
        }
        return SizeStandard::where($where)->get();
    }

    public function bidInfo(Request $request)
    {
        $where = [];
        if ($request->get('id', '')) {
            $where['id'] = $request->get('id');
        }
        if ($request->get('product_sn', '')) {
            $where['product_sn'] = $request->get('product_sn');
        }
        if ($request->get('callback_id', '')) {
            $where['callback_id'] = $request->get('callback_id');
        }
        $base = CarryMeBidding::where($where)->first();

        if (!$base) {
            dd('数据不存在');
        }
        $info = $base->toArray();
        echo "<h1>基础信息</h1>";
        if ($info) unset($info['carryme_bidding_items']);
        dump($info);

        $items = $base->carrymeBiddingItems;
        echo "<h1>item</h1>";
        foreach ($items as $item) {
            $bids = $item->channelBids;
            $item->callbackBid;
            foreach ($bids as $bid) {
                $bid->channelBiddingItem;
                $bid->channelBiddingLogs;
                $orders = $bid->channelOrders;
                foreach ($orders as $order) {
                    $order->callbackOrder;
                    $order->callbackCancel;
                }
            }
            dump($item->toArray());
        }

        echo '<h1>历史出价记录</h1>';
        $bids = CarryMeBidding::where([
            'sku_id' => $base->sku_id
        ])->with('carrymeBiddingItems')->get();
        dump($bids->toArray());
    }

    //将sync_from货号的尺码标准同步给product_sn货号
    public function sizeSync(Request $request)
    {
        $sync_from = $request->get('sync_from', '');
        $product_sns = $request->get('product_sn', '');
        $product_sns = explode(',', $product_sns);

        $select = ['cm', 'size_eu', 'size_us', 'size_jp', 'size_fr'];
        $sizes = SizeStandard::where(['product_sn' => $sync_from])->select($select)->get()->toArray();
        foreach ($sizes as $size) {
            foreach ($product_sns as $product_sn) {
                $where = [
                    'product_sn' => $product_sn,
                    'size_eu' => $size['size_eu'],
                ];
                SizeStandard::updateOrCreate($where, $size);
            }
        }
        return SizeStandard::whereIn('product_sn', $product_sns)->get();
    }

    public function skuNotMatch(Request $request)
    {
        $info = DB::table('carryme_bidding_items')
            ->leftJoin('carryme_bidding as cb', 'carryme_bidding_items.carryme_bidding_id', 'cb.id')
            ->where([
                "carryme_bidding_items.status" => 3,
                "carryme_bidding_items.fail_reason" => '未找到对应的商品尺码信息'
            ])->select(['channel_code', 'product_sn', 'properties', 'fail_reason'])
            ->distinct(true)->get()->toArray();
        // return $info;
        $data = [];
        foreach ($info as $item) {
            $product = ChannelProduct::where(['product_sn' => $item->product_sn, 'channel_code' => $item->channel_code])->with('skus')->first();
            // $ss = [];
            // foreach ($product->skus as $sku) {
            //     $ss[] = $sku->properties;
            // }
            $data[] = [
                'good_name' => $product->good_name,
                'product_sn' => $item->product_sn,
                'properties' => $item->properties,
                'channel_code' => $item->channel_code,
                // 'skus' => $ss
            ];
        }
        // return $data;
        $headers[] = [
            'good_name' => '商品名',
            'product_sn' => '货号',
            'properties' => '规格',
            'channel_code' => '渠道',
            // 'skus' => '渠道商品全部规格信息',
        ];

        $export = new Export($headers, $data);
        $name = sprintf('未匹配商品规格%s.xlsx', date('YmdHis'));
        return Excel::download($export, $name);
    }

    //更新尺码匹配规则
    public function updateProductMatchRule(Request $request)
    {
        $rules = $request->get('rules', '');
        $product_sns = $request->get('product_sns', '');
        $product_sns = explode(',', $product_sns);
        $channel_code = $request->get('channel_code', '');
        foreach ($product_sns as $product_sn) {
            $where = [
                'channel_code' => $channel_code,
                'product_sn' => $product_sn
            ];
            $update = [
                'rules' => $rules
            ];
            SizeMatchRule::updateOrCreate($where, $update);
        }

        return SizeMatchRule::whereIn('product_sn', $product_sns)->get();
    }

    public function stockBidInfo(Request $request)
    {
        $where = [
            'product_sn' => $request->get('product_sn'),
            'properties' => $request->get('properties'),
            'is_deleted' => 0,
        ];
        $base = StockProduct::where($where)->with(['dwChannel', 'goatChannel', 'dw', 'goat', 'stockBiddingActive'])->first();
        if (!$base) {
            dump('没有库存出价记录');
            return;
        }

        $stock_bidding_active = $base->stockBiddingActive;
        if ($stock_bidding_active) {
            $channel_bidding = $stock_bidding_active->channelBidding;
            if ($channel_bidding) {
                $channel_bidding->channelOrders;
            }
        }

        dump($base->toArray());
        echo "<h1>出价记录</h1>";
        $biddings = $base->stockBiddings;
        foreach ($biddings as $bidding) {
            $bidding->stockBiddingItems;
        }
        dump($biddings->toArray());
    }
}
