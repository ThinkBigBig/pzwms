<?php

namespace App\Logics;

use App\Jobs\stockProduct as JobsStockProduct;
use App\Logics\channel\STOCKX;
use App\Models\ChannelOrder;
use App\Models\ChannelProduct;
use App\Models\StockProduct;
use App\Models\StockProductLog;
use App\Models\OperationLog as operationLog;
use App\Models\StockBiddingItem;
use App\Models\StockProductChannel;
use App\Models\StockProductHistory;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class StockProductLogic extends BaseLogic
{

    /**
     * 商品信息同步
     *
     * @param StockProductLog $log
     */
    public function sync(StockProductLog $log)
    {
        $where = [
            'product_sn' => $log->product_sn,
            'properties' => $log->properties,
            'store_house_code' => $log->store_house_code,
            'is_deleted' => 0,
        ];
        $old_stock_product = StockProduct::where($where)->first();

        $update = [
            'good_name' => $log->good_name,
            'status' => $log->status,
            'store_stock' => $log->store_stock,
            'stock' => $log->stock,
            'order_stock' => 0,
            'bar_code' => $log->bar_code,
            'store_house_name' => $log->store_house_name,
            'cost_price' => $log->cost_price,
            'finnal_price' => $log->finnal_price,
            'info_updated_at' => date('Y-m-d H:i:s'),
            'batch_no' => $log->batch_no,
            'purchase_url' => $log->purchase_url,
            'purchase_name' => $log->purchase_name,
        ];
        //获取渠道商品信息
        $logic = new ProductLogic();
        $logic->product(['product_sn' => $log->product_sn]);

        $skus = ProductLogic::getSku($log->product_sn, [['valuecn' => $log->properties]], true);

        try {
            DB::beginTransaction();
            //更新同步状态
            $log->sync_status = StockProductLog::SYNC_SUCCESS;
            $log->save();
            $stock_product = StockProduct::updateOrCreate($where, $update);
            StockProductHistory::updateOrCreate([
                'stock_product_id' => $stock_product->id,
                'stock_batch_no' => $log->batch_no
            ], ['stock' => $stock_product->stock,]);

            $goat_where = [
                'channel_code' => self::goat_code(),
                'stock_product_id' => $stock_product->id,
            ];

            $goat_update = [
                'threshold_price' => $log->goat_threshold_price,
                'channel_product_sku_id' => $skus[self::goat_code()]['channel_product_sku_id'] ?? 0,
            ];

            $dw_where = [
                'channel_code' => self::dw_code(),
                'stock_product_id' => $stock_product->id,
            ];

            $dw_update = [
                'threshold_price' => $log->dw_threshold_price,
                'channel_product_sku_id' => $skus[self::dw_code()]['channel_product_sku_id'] ?? 0,
            ];

            $stockx_where = [
                'channel_code' => self::stockx_code(),
                'stock_product_id' => $stock_product->id,
            ];

            $stockx_update = [
                'threshold_price' => $log->stockx_threshold_price,
                'channel_product_sku_id' => $skus[self::stockx_code()]['channel_product_sku_id'] ?? 0,
            ];

            $carryme_where = [
                'channel_code' => self::carryme_code(),
                'stock_product_id' => $stock_product->id,
            ];

            $carryme_update = [
                'threshold_price' => $log->carryme_threshold_price,
                'channel_product_sku_id' => $skus[self::carryme_code()]['channel_product_sku_id'] ?? 0,
            ];

            StockProductChannel::updateOrCreate($goat_where, $goat_update);
            StockProductChannel::updateOrCreate($dw_where, $dw_update);
            StockProductChannel::updateOrCreate($stockx_where, $stockx_update);
            StockProductChannel::updateOrCreate($carryme_where, $carryme_update);
            DB::commit();

            $cancel = $old_stock_product && $old_stock_product->stockBiddingActive ? true : false;
            $bid = $log->status === StockProduct::STATUS_SHELF ? true : false;
            $clear = $stock_product->stock > 0 ? false : true;

            return [
                'bid' => $bid,
                'cancel' => $cancel,
                'clear' => $clear,
                'product_sn' => $log->product_sn,
                'properties' => $log->properties,
                'stock_product_id' => $stock_product->id,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Robot::sendException($e->__toString());
        }

        return [];
    }

    /**
     * 商品信息更新后取消出价，等出价完全取消后，重新出价
     *
     * @param array $params
     */
    public function afterUpdateProduct($params)
    {
        // 是否有有效出价
        // $list = StockProduct::where([
        //     'product_sn' => $params['product_sn'],
        //     'properties' => $params['properties'],
        //     'is_deleted' => 0,
        // ])->with('stockBiddingActive')->get();
        // foreach ($list as $product) {
        //     // 存在有效出价，暂不处理
        //     if ($product->stockBiddingActive) {
        //         return false;
        //     }
        // }

        // 出价全部取消，重新出价
        $bidding = new BiddingAsyncLogic();
        $bidding->stockBiddingAdd([
            'product_sn' => $params['product_sn'],
            'properties' => $params['properties'],
            'remark' => $params['remark'],
            'product_init' => true,
        ]);
        return true;
    }

    private function listWhere($params)
    {
        $where = ['is_deleted' => 0];
        if ($params['product_sn'] ?? '') {
            $where['product_sn'] = $params['product_sn'];
        }

        // 出价状态 1出价中 2未出价 3已下架
        if ($params['bidding_status'] ?? 0) {
            $where['status'] = StockProduct::STATUS_SHELF;
            if ($params['bidding_status'] == 1) {
                $where[] = [function ($query) {
                    $query->where('bidding_stock', '>', 0);
                }];
            }

            if ($params['bidding_status'] == 2) {
                $where['bidding_stock'] = 0;
            }

            if ($params['bidding_status'] == 3) {
                $where['status'] = StockProduct::STATUS_TAKE_DOWN;
            }
        }
        return $where;
    }

    public function exportData($params)
    {
        $where = $this->listWhere($params);
        $list =  StockProduct::where($where)->with(['dwChannel', 'goatChannel', 'stockxChannel', 'carrymeChannel'])->get()->toArray();
        foreach ($list as &$val) {
            $val['bar_code'] = $val['bar_code'] . "\t";
            $val['status'] = (string) $val['status'];
            $val['goat_threshold_price'] = $val['goat_channel']['threshold_price'] ?? 0;
            $val['dw_threshold_price'] = $val['dw_channel']['threshold_price'] ?? 0;
            $val['stockx_threshold_price'] = $val['stockx_channel']['threshold_price'] ?? 0;
            $val['carryme_threshold_price'] = $val['carryme_channel']['threshold_price'] ?? 0;
            $val['left_stock'] = (string)($val['stock'] - $val['bidding_stock'] - $val['order_stock']);
        }
        return $list;
    }

    /**
     * 商品明细数据
     *
     * @param array $params
     */
    public function exportDetail($params)
    {
        $where = $this->listWhere($params);
        $list =  StockProduct::where($where)->with(['dwChannel', 'goatChannel', 'stockxChannel', 'carrymeChannel', 'stockBiddingActive'])->get();
        $res = [];
        foreach ($list as $val) {
            if ($val->goatChannel) $val->goatChannel->channelProductSku;
            if ($val->dwChannel) $val->dwChannel->channelProductSku;
            if ($val->stockxChannel) $val->stockxChannel->channelProductSku;
            if ($val->carrymeChannel) $val->carrymeChannel->channelProductSku;
            $val = $val->toArray();
            $tmp = $val;
            $tmp['status_txt'] = $val['stock_bidding_active'] ? '出价中' : $val['status_txt'];
            $tmp['bar_code'] = $val['bar_code'] . "\t";
            $tmp['status'] = (string) $val['status'];
            $tmp['goat_threshold_price'] = (string)($val['goat_channel']['threshold_price'] ?? 0);
            $tmp['goat_lowest_price_jpy'] = (string)($val['goat_channel']['channel_product_sku']['lowest_price_jpy'] ?? 0);
            $tmp['dw_threshold_price'] = (string)($val['dw_channel']['threshold_price'] ?? 0);
            $tmp['dw_lowest_price_jpy'] = (string)($val['dw_channel']['channel_product_sku']['lowest_price_jpy'] ?? 0);
            $tmp['stockx_threshold_price'] = $val['stockx_channel']['threshold_price'] ?? 0;
            $tmp['stockx_lowest_price_jpy'] = (string)($val['stockx_channel']['channel_product_sku']['lowest_price_jpy'] ?? 0);
            $tmp['carryme_threshold_price'] = $val['carryme_channel']['threshold_price'] ?? 0;
            $tmp['carryme_lowest_price_jpy'] = (string)($val['carryme_channel']['channel_product_sku']['lowest_price_jpy'] ?? 0);
            $tmp['left_stock'] = (string)($val['stock'] - $val['bidding_stock'] - $val['order_stock']);
            $res[] = $tmp;
        }
        return $res;
    }

    /**
     * 商品列表
     *
     * @param array $params
     */
    public function list($params)
    {
        $size = $params['size'] ?? 10;
        $cur_page = $params['cur_page'] ?? 1;

        $where = $this->listWhere($params);
        $select = [
            'product_sn',
            DB::raw('group_concat(properties SEPARATOR " | ") as properties'),
            DB::raw('sum(stock) as stock'),
            DB::raw('sum(if(stock-bidding_stock-order_stock<0,0,stock-bidding_stock-order_stock)) as left_stock'),
            DB::raw('sum(bidding_stock) as freeze_stock'),
            DB::raw('sum(order_stock) as order_stock'),
            DB::raw('group_concat(id) as ids'),
            DB::raw('max(info_updated_at) as updated_at'),
            DB::raw('min(status) as status')
        ];

        $model = StockProduct::with('product')->groupBy('product_sn')->where($where);
        $list = $model->paginate($size, $select, 'page', $cur_page);

        foreach ($list as &$item) {
            $item['good_name'] = $item['product'] ? $item['product']->good_name : '';
            $ids = explode(',', $item['ids']);
            $bids = StockBiddingItem::whereIn('stock_product_id', $ids)->where(['status' => StockBiddingItem::STATUS_SUCCESS, 'qty_sold' => 0])->first();
            $item['bid_status'] = $bids ? '出价中' : (StockProduct::$list_maps[$item['status']]);
            $item['can_shelf'] = $item['status'] == StockProduct::STATUS_SHELF ? 0 : 1;
            $item['can_take_down'] = $item['status'] == StockProduct::STATUS_SHELF ? 1 : 0;

            // 在售规格
            $properties = StockProduct::where(['product_sn' => $item['product_sn'], 'is_deleted' => 0])
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))->from('stock_bidding_items')->whereRaw('stock_product_id=stock_products.id and stock_bidding_items.status IN (0,1) and stock_bidding_items.qty_sold=0');
                })->distinct()->pluck('properties')->toArray();
            $all_properties = explode(' | ', $item['properties']);

            // 已下架规格
            $except = array_diff($all_properties, $properties);
            $item['bidding_properties'] = implode(' | ', $properties);
            $item['off_properties'] = implode(' | ', $except);
            unset($item['product']);
        }
        return $list;
    }

    /**
     * 按货号上下架商品
     *
     * @param array $params
     * product_sn：货号 
     * status：shelf上架|take_down下架
     */
    public function update($params)
    {
        //货号下的所有规格
        $product_sn = $params['product_sn'];
        $key = 'erp:stock_update:' . $product_sn;
        if (!Redis::setnx($key, 1)) {
            return false;
        }

        $skus = StockProduct::where(['product_sn' => $product_sn, 'is_deleted' => 0])->get();

        $shelf_arr = [];
        $logic = new BiddingAsyncLogic();
        $status = $params['status']; //shelf take_down
        foreach ($skus as $sku) {
            $bidding = StockBiddingItem::where(['stock_product_id' => $sku->id, 'status' => StockBiddingItem::STATUS_SUCCESS, 'qty_sold' => 0])->first();
            if ($status == 'shelf') {
                $type = operationLog::TYPE_SHELF;
                // 已经出价不处理
                if ($bidding) continue;
                // 商品上架
                $sku->update(['status' => StockProduct::STATUS_SHELF]);
                $shelf_arr[] = $sku->product_sn . ',' . $sku->properties;
            }

            if ($status == 'take_down') {
                $type = operationLog::TYPE_TAKEDOWN;
                // 商品下架
                $sku->update(['status' => StockProduct::STATUS_TAKE_DOWN]);
                // 取消出价
                $logic->stockBiddingCancel(['stock_product_id' => $sku->id, 'remark' => $params['remark'] ?? '手动下架']);
                operationLog::add($type, $sku->id, '', '', $params['admin_user_id']);
            }
        }

        if ($shelf_arr) {
            $shelf_arr = array_unique($shelf_arr);
            foreach ($shelf_arr as $item) {
                $arr = explode(',', $item);
                $logic->stockBiddingAdd([
                    'product_sn' => $arr[0],
                    'properties' => $arr[1],
                    'remark' => '手动上架'
                ]);
            }
        }
        Redis::del($key);
        return true;
    }

    private function infoUpdatedAt($product_sn)
    {
        return StockProduct::where(['product_sn' => $product_sn, 'is_deleted' => 0])->max('info_updated_at');
    }

    /**
     * 商品出价详情
     *
     * @param array $params
     */
    public function bidDetail($params)
    {
        $product_sn = $params['product_sn'];
        $res = StockProduct::where(['product_sn' => $product_sn, 'is_deleted' => 0])->with(['dw', 'goat', 'stockx', 'dwChannel', 'goatChannel', 'stockxChannel', 'carrymeChannel'])->get();
        $list = [];

        $channel_product = ChannelProduct::where(['product_sn' => $product_sn])->first();
        $good_img = $channel_product ? $channel_product->spu_logo_url : '';
        $good_name = '';
        foreach ($res as $item) {
            if (!$good_name) $good_name = $item['good_name'];
            $sku = $item->goatChannel->channelProductSku;
            $goat = [
                'lowest_price' => $sku ? $sku->lowest_price_jpy : 0, //最低价
                'lowest_price_at' => $sku ? $sku->lowest_price_at : '', //最低价获取时间
                'bid_price' => '', //出价金额
                'bid_price_at' => '', //出价时间
                'tags' => $sku ? $sku->tags : '',
                'tag_desc' => $sku ? $sku->tag_desc : '',
            ];

            $sku = $item->dwChannel->channelProductSku;
            $dw = [
                'lowest_price' => $sku ? $sku->lowest_price_jpy : 0, //最低价
                'lowest_price_at' => $sku ? $sku->lowest_price_at : '', //最低价获取时间
                'bid_price' => '',
                'bid_price_at' => '',
                'tags' => $sku ? $sku->tags : '',
                'tag_desc' => $sku ? $sku->tag_desc : '',
            ];

            $sku = $item->stockxChannel ? $item->stockxChannel->channelProductSku : null;
            $stockx = [
                'lowest_price' => $sku ? $sku->lowest_price_jpy : 0, //最低价
                'lowest_price_at' => $sku ? $sku->lowest_price_at : '', //最低价获取时间
                'bid_price' => '',
                'bid_price_at' => '',
                'tags' => $sku ? $sku->tags : '',
                'tag_desc' => $sku ? $sku->tag_desc : '',
            ];

            $sku = $item->carrymeChannel ? $item->carrymeChannel->channelProductSku : null;
            $carryme = [
                'lowest_price' => $sku ? $sku->lowest_price_jpy : 0, //最低价
                'lowest_price_at' => $sku ? $sku->lowest_price_at : '', //最低价获取时间
                'bid_price' => '',
                'bid_price_at' => '',
                'tags' => $sku ? $sku->tags : '',
                'tag_desc' => $sku ? $sku->tag_desc : '',
            ];

            $dw_bidding = 0;
            $goat_bidding = 0;
            $stockx_bidding = 0;
            $carryme_bidding = 0;

            if ($item->lastStockBidding) {
                $bidding_items = $item->lastStockBidding->activeStockBiddingItems;
                foreach ($bidding_items as $itm) {
                    if ($itm->channel_code == self::goat_code()) {
                        $bidding = $itm->channelBidding;
                        if ($bidding) {
                            $goat['bid_price'] = $bidding->price_jpy;
                            $goat['bid_price_at'] = $bidding->created_at;
                            $goat_bidding = 1;
                        }
                    }
                    if ($itm->channel_code == self::dw_code()) {
                        $bidding = $itm->channelBidding;
                        if ($bidding) {
                            $dw['bid_price'] = $bidding->price_jpy;
                            $dw['bid_price_at'] = $bidding->created_at;
                            $dw_bidding = 1;
                        }
                    }
                    if ($itm->channel_code == self::stockx_code()) {
                        $bidding = $itm->channelBidding;
                        if ($bidding) {
                            $stockx['bid_price'] = $bidding->price_jpy;
                            $stockx['bid_price_at'] = $bidding->created_at;
                            $stockx_bidding = 1;
                        }
                    }
                    if ($itm->channel_code == self::carryme_code()) {
                        $bidding = $itm->channelBidding;
                        if ($bidding) {
                            $carryme['bid_price'] = $bidding->price;
                            $carryme['bid_price_at'] = $bidding->created_at;
                            $carryme_bidding = 1;
                        }
                    }
                }
            }

            // $bidding_num = $dw_bidding + $goat_bidding;
            // if (!$bidding_num) continue;

            $list[] = [
                'finnal_price' => $item['finnal_price'],
                'store_house_name' => $item['store_house_name'],
                'store_house_code' => $item['store_house_code'],
                'stock_product_id' => $item['id'],
                'properties' => $item['properties'],
                'stock' => max($item['stock'] - $item['order_stock'], 0),
                'goat_threshold_price' => $item->goatChannel->threshold_price,
                'dw_threshold_price' => $item->dwChannel->threshold_price,
                'stockx_threshold_price' => $item->stockxChannel ? $item->stockxChannel->threshold_price : 0,
                'carryme_threshold_price' => $item->carrymeChannel ? $item->carrymeChannel->threshold_price : 0,
                'cost_price' => $item->cost_price,
                'dw' => $dw,
                'dw_bidding' => $dw_bidding,
                'goat' => $goat,
                'goat_bidding' => $goat_bidding,
                'stockx' => $stockx,
                'stockx_bidding' => $stockx_bidding,
                'carryme' => $carryme,
                'carryme_bidding' => $carryme_bidding,
                'bidding_num' => $dw_bidding + $goat_bidding + $stockx_bidding + $carryme_bidding,
                'purchase_url' => $item['purchase_url'],
                'purchase_name' => $item['purchase_name'],
            ];
        }
        $data = [
            'good_name' => $good_name,
            'product_sn' => $product_sn,
            'good_img' => $good_img,
            'info_updated_at' => $this->infoUpdatedAt($product_sn),
            'list' => $list,
        ];
        return $data;
    }

    /**
     * 删除指定规格的出价
     *
     * @param array $params
     * @return bool
     */
    public function delBid($params)
    {
        //获取商品信息
        $stock_product = StockProduct::where(['id' => $params['stock_product_id']])->first();
        if (!$stock_product) return true;

        //取消出价
        $logic = new BiddingAsyncLogic();
        $logic->stockBiddingCancel(['stock_product_id' => $stock_product->id, 'remark' => '删除出价']);
        operationLog::add(operationLog::TYPE_DELETE, $params['stock_product_id'], '', '', $params['admin_user_id']);
        return true;
    }

    const STOCK_UPDATE_BID = 1;
    const STOCK_UPDATE_ORDER = 2;
    const STOCK_UPDATE_ORDER_CANCEL = 3;
    static function stockUpdate($stock_product_id, $scene)
    {
        // self::log('bid', ['desc' => '更新商品库存', 'stock_product' => $stock_product_id, 'scene' => $scene]);
        try {
            DB::beginTransaction();
            $product = StockProduct::where(['id' => $stock_product_id])->lockForUpdate()->first();
            if ($product) {
                switch ($scene) {
                    case self::STOCK_UPDATE_BID: // 出价
                        $product->update(['bidding_stock' => self::getBiddingStock($stock_product_id),]);
                        break;
                    case self::STOCK_UPDATE_ORDER: //订单创建
                        $product->update([
                            'bidding_stock' => self::getBiddingStock($stock_product_id),
                            'order_stock' => $product->order_stock + 1,
                        ]);
                        self::updateOrderStock($product);
                        break;
                    case self::STOCK_UPDATE_ORDER_CANCEL: //发货前订单取消
                        $product->update(['order_stock' => max($product->order_stock - 1, 0),]);
                        self::updateOrderStock($product);
                        break;
                }
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Robot::sendException('库存更新异常 ' . $stock_product_id);
            throw $e;
        }

        // 商品售完后，自动清空
        if ($product->order_stock > 0 && $product->stock - $product->order_stock <= 0) {
            JobsStockProduct::dispatch(['id' => $product->id, 'action' => JobsStockProduct::ITEM_CLEAR])->onQueue('product');
        }
    }

    // 获取正在出价中的库存数
    static function getBiddingStock($stock_product_id)
    {
        $where = [
            'stock_product_id' => $stock_product_id
        ];
        $where[] = [function ($query) {
            $query->where('status', StockBiddingItem::STATUS_DEFAULT)
                ->orWhere(function ($query) {
                    $query->where('status', StockBiddingItem::STATUS_SUCCESS);
                });
        }];
        $vaild = StockBiddingItem::where($where)
            ->selectRaw('sum(qty_sold) as qty_sold,stock_bidding_id')
            ->groupBy('stock_bidding_id')->havingRaw('qty_sold=0')->get()->toArray();
        return $vaild ? 1 : 0;
    }

    // 获取正在出价中的库存数
    static function updateOrderStock($product)
    {
        $history = $product->productHistory;
        if (!$history) return;
        $results = DB::table('channel_order as co')
            ->leftJoin('channel_bidding as cb', 'co.channel_bidding_id', '=', 'cb.id')
            ->leftJoin('stock_biddings as sb', 'cb.stock_bidding_id', '=', 'sb.id')
            ->where('sb.stock_product_id', '=', $product->id)
            ->where('sb.stock_batch_no', '=', $product->batch_no)
            ->where('co.status', '!=', ChannelOrder::STATUS_CANCEL)
            ->select(['ro.*'])
            ->count();
        $history->update(['order_stock' => $results,]);
    }

    /**
     * 门槛价变更
     *
     * @param array $params
     */
    public function updateThresholdPrice($params)
    {
        $logic = new BiddingAsyncLogic();

        $stock_product = StockProduct::where(['id' => $params['stock_product_id']])->first();

        $stock_product_channel = StockProductChannel::where(['stock_product_id' => $params['stock_product_id'], 'channel_code' => $params['channel_code']])->first();

        $old = $stock_product_channel->threshold_price;
        $new = $params['threshold_price'];
        // 更新前后一致，不再执行
        if ($old == $new) {
            return true;
        }

        // 更新门槛价
        $stock_product_channel->update(['threshold_price' => $params['threshold_price']]);
        // 添加操作日志
        $params['remark'] = sprintf('渠道%s门槛价变更，原%d 新%d', $params['channel_code'], $old, $new);
        operationLog::add2(operationLog::TYPE_CHANGE_THRESHOLD, $stock_product->id, '', $params);

        // 全渠道下架
        $logic->stockBiddingCancel([
            'product_sn' => $stock_product->product_sn,
            'properties' => $stock_product->properties,
            'remark' => '门槛价变更',
            'rebid' => true,
        ]);
        return true;
    }

    /**
     * 修改门槛价后取消出价，等出价完全取消后，重新出价。
     *
     * @param array $params
     */
    public function afterUpdateThresholdPrice($params)
    {
        $stock_product = StockProduct::where(['product_sn' => $params['product_sn'], 'properties' => $params['properties'], 'is_deleted' => 0])->first();
        if ($stock_product->bidding_stock) {
            return false;
        }
        $logic = new BiddingAsyncLogic();
        $logic->stockBiddingAdd($params);
        return true;
    }

    // 清空所有商品
    static function clear($params)
    {
        // self::log('bid', ['desc' => '清空商品', 'params' => $params]);

        $where = ['is_deleted' => 0];
        if ($params['product_sn'] ?? '') {
            $where['product_sn'] = $params['product_sn'];
        }

        if ($params['id'] ?? 0) {
            $where['id'] = $params['id'];
        }
        $remark = $params['remark'] ?? '清空商品';

        // 下架所有商品
        $product_sns = StockProduct::where($where)->where('bidding_stock', '>', 0)->distinct()->pluck('product_sn');
        $logic = new StockProductLogic();
        foreach ($product_sns as $product_sn) {
            $logic->update(['product_sn' => $product_sn, 'remark' => $remark, 'status' => 'take_down', 'admin_user_id' => $params['admin_user_id']]);
        }

        // 所有商品标记为删除
        StockProduct::where($where)->update(['is_deleted' => 1, 'deleted_at' => date('Y-m-d H:i:s'),]);
        OperationLog::add(OperationLog::TYPE_CLEAR, 0, '', $remark, $params['admin_user_id']);
        return true;
    }

    // 清空指定sku
    static function clearItem($params)
    {
        // self::log('bid', ['desc' => '清空指定sku', 'params' => $params]);
        $where = ['is_deleted' => 0];
        if ($params['product_sn'] ?? '') {
            $where['product_sn'] = $params['product_sn'];
        }

        if ($params['id'] ?? 0) {
            $where['id'] = $params['id'];
        }
        $remark = $params['remark'] ?? '清空单个商品';
        $product = StockProduct::where($where)->first();
        if (!$product) return false;

        $update = ['is_deleted' => 1, 'deleted_at' => date('Y-m-d H:i:s'),];
        if ($product->bidding_stock > 0) {
            $update['status'] = StockProduct::STATUS_TAKE_DOWN;
            $logic = new BiddingAsyncLogic();
            $logic->stockBiddingCancel(['stock_product_id' => $product->id, 'remark' => $remark]);
            operationLog::add(operationLog::TYPE_TAKEDOWN, $product->id, '', $remark, $params['admin_user_id'] ?? 0);
        }

        // 所有商品标记为删除
        $product->update($update);
        OperationLog::add(OperationLog::TYPE_CLEAR, $product->id, '', $remark, $params['admin_user_id'] ?? 0);
        return true;
    }

    /**
     * stockx门槛价计算公式
     *  - 到手价 <  86美元，门槛价 = 到手价/0.971 + 5.6美元
     *  - 到手价 >= 86美元，门槛价 = 到手价/0.971/0.94
     *
     * @param array $params
     * @param boolean $import
     */
    static function stockxThresholdFormulas($params, $import = false)
    {
        $api = new STOCKX();
        $stockx_limit = $api->usd2jpy(8600);
        $params['stockx_limit'] = $stockx_limit;
        $detail = $params['detail'] ?? 0;
        $add = $api->usd2jpy(560);

        $params['stockx_formula'] = function ($i) use ($stockx_limit, $add, $detail) {

            if ($detail) return "=IF(I{$i}>={$stockx_limit},I{$i}/0.971/0.94,I{$i}/0.971+{$add})";

            return "=IF(H{$i}>={$stockx_limit},H{$i}/0.971/0.94,H{$i}/0.971+{$add})";
        };

        $params['stockx_formula2'] = function ($data) use ($stockx_limit, $add, $detail) {
            if ($data >= $stockx_limit) {
                return $data / 0.971 / 0.94;
            } else {
                return $data / 0.971 + $add;
            }
        };

        return $params;
    }
}
