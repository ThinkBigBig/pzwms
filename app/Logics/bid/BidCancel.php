<?php

namespace App\Logics\bid;

use App\Logics\BaseLogic;
use App\Logics\BiddingAsyncLogic;
use App\Logics\BidExecute;
use App\Logics\CarrymeCallbackLogic;
use App\Logics\RedisKey;
use App\Logics\StockProductLogic;
use App\Models\BiddingOperation;
use App\Models\BiddingOperationLog;
use App\Models\BidExcutionLog;
use App\Models\CarryMeBidding;
use App\Models\CarryMeBiddingItem;
use App\Models\ChannelBidding;
use App\Models\StockBiddingItem;
use App\Models\StockProduct;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * 在这里指定的多条出价取消完成后，可执行指定的操作
 */
class BidCancel extends BaseLogic
{
    const CANCEL_REASON_APP_BID = 'APP_BID';
    const CANCEL_REASON_APP_CANCEL = 'APP_CANCEL';
    const CANCEL_REASON_STOCK_DELETE = 'STOCK_DETETE';
    const CANCEL_REASON_ORDER_CREATED = 'ORDER_CREATED';

    const REFRESH_STOCK = 'STOCK_REFRESH'; //定时刷新库存

    static function createOperation($input, $cancel_reason)
    {
        $operation_id = genRandomString(10) . '-' . date('YmdHis');
        $data = [
            'operation_id' => $operation_id,
            'operation_type' => 'cancel',
            'status' => 'pending',
            'scence' => $cancel_reason,
            'input'  => $input
        ];
        BiddingOperation::create($data);
        return $operation_id;
    }

    /**
     * app新增出价前，需要先取消库存出价，执行完后才能新增app出价
     * 因为app出价后会触发库存出价的刷新机制，如果库存只剩1件，刷新会不成功
     *
     * @param array $carryme_item_ids
     * @param string $product_sn
     * @param string $properties
     */
    static function appAddBid($carryme, $carryme_item_ids, $product_sn, $properties, $sku_id, $business_type)
    {
        // 相同属性和规格，库存是否有出价
        $stock_items = collect();
        $stock_product_ids = StockProduct::where(['product_sn' => $product_sn, 'properties' => $properties])->pluck('id');
        if ($stock_product_ids) {
            // $stock_items = StockBiddingItem::whereIn('status', [0, 1])
            //     ->where(['qty_sold' => 0])
            //     ->whereIn('stock_product_id', $stock_product_ids)
            //     ->whereIn('channel_code', ['DW', 'GOAT', 'STOCKX'])->get();
            StockBiddingItem::whereIn('status', [0, 1])
                ->where(['qty_sold' => 0])
                ->whereIn('channel_code', ['DW', 'GOAT', 'STOCKX','CARRYME'])
                ->whereIn('stock_product_id', $stock_product_ids)
                ->update([
                    'status' => StockBiddingItem::STATUS_CANCEL,
                    'remark' => 'app新增出价，库存出价取消',
                    'cancel_progress' => 1
                ]);

            // 更新商品库存信息
            $stock_product_ids = array_unique($stock_product_ids->toArray());
            foreach ($stock_product_ids as $product_id) {
                StockProductLogic::stockUpdate($product_id, StockProductLogic::STOCK_UPDATE_BID);
            }
        }

        // 相同sku_id 相同的business_type，在是否有进行中出价
        $old_ids = DB::table('carryme_bidding as cb')
            ->leftJoin('carryme_bidding_items as cbi', 'cb.id', '=', 'cbi.carryme_bidding_id')
            ->select('cbi.id')
            ->where('cb.product_sn', $product_sn)
            ->where('cb.sku_id', $sku_id)
            ->whereIn('cbi.status', [0, 1])
            ->where('cbi.business_type', $business_type)
            ->where('cbi.qty_sold', 0)
            ->pluck('id')
            ->diff($carryme_item_ids)
            ->toArray();


        if ($stock_items->count() || $old_ids) {

            $operation_id = self::createOperation([
                'stock_bidding_item_ids' => $stock_items->pluck('id')->toArray(),
                'carryme_bidding_item_ids' => $old_ids,
                'product_sn' => $product_sn,
                'properties' => $properties,
                'new_carryme_item_ids' => $carryme_item_ids,
                'carryme_cancel_callback' => false,
                'carryme_bidding_id' => $carryme->id,
            ], self::CANCEL_REASON_APP_BID);

            // foreach ($stock_items as $item) {
            //     $item->update(['cancel_progress' => CarryMeBiddingItem::CANCEL_PENDING]);
            //     BiddingOperationLog::create(['operation_id' => $operation_id, 'stock_bidding_item_id' => $item->id]);
            //     Excute::stockCancel([
            //         'stock_bidding_item_id' => $item->id,
            //         'remark' => 'app新增出价，库存出价取消 ',
            //         'product_sn' => $product_sn,
            //     ]);
            // }

            foreach ($old_ids as $id) {
                $item = CarryMeBiddingItem::where(['id' => $id])->first();
                $item->update(['cancel_progress' => CarryMeBiddingItem::CANCEL_PENDING]);
                BiddingOperationLog::create(['operation_id' => $operation_id, 'carryme_bidding_item_id' => $item->id]);
                Excute::appCancel([
                    'carryme_bidding_item_id' => $item->id,
                    'remark' => $params['remark'] ?? '',
                    'product_sn' => $product_sn,
                ]);
            }
        } else {
            // 没有待取消的出价直接触发库存出价
            // (new BiddingAsyncLogic())->stockBiddingAdd([
            //     'product_sn' => $product_sn,
            //     'properties' => $properties,
            //     'remark' => 'app出价触发，carryme_bidding_id ' . $carryme->id,
            // ]);
        }

        foreach ($carryme_item_ids as $id) {
            Excute::appBid(['carryme_bidding_item_id' => $id, 'product_sn' => $product_sn]);
        }

        // 没有待取消的出价直接触发库存出价
        (new BiddingAsyncLogic())->stockBiddingAdd([
            'product_sn' => $product_sn,
            'properties' => $properties,
            'remark' => 'app出价触发，carryme_bidding_id ' . $carryme->id,
        ]);
    }

    /**
     * app取消出价，同时库存出价也取消，方便后续库存出价刷新
     *
     * @param array $params
     */
    static function appCancel($params)
    {
        if ($params['product_sn'] ?? '') {
            // 按货号批量取消
            $items = CarryMeBiddingItem::whereHas('carrymeBidding', function ($query) use ($params) {
                $query->where('product_sn', $params['product_sn']);
                if ($params['carryme_bid_ids'] ?? 0) {
                    $query->whereIn('id', $params['carryme_bid_ids']);
                }
            })->get();
        } else {

            $where = [];
            // 一次出价单渠道取消
            if ($params['carryme_bid_item_id'] ?? 0) {
                $where['id'] = $params['carryme_bid_item_id'];
            }
            // 一次出价全渠道取消
            if ($params['carryme_bid_id'] ?? 0) {
                $where['carryme_bidding_id'] = $params['carryme_bid_id'];
            }
            if (!$where) return true;
            $items = CarryMeBiddingItem::where($where)->get();
        }

        if (!$items->count()) return true;
        $product_sn = '';
        $properties = [];

        $ids = array_unique($items->pluck('carryme_bidding_id')->toArray());
        $carryme_bids = CarryMeBidding::whereIn('id', $ids)->get();
        foreach ($carryme_bids as $carryme) {
            $product_sn = $carryme->product_sn;
            if (($carryme->properties[0]['valuecn'] ?? '')) $properties[] = $carryme->properties[0]['valuecn'];
        }
        $item_ids = $items->pluck('id')->toArray();

        $stock_items = collect();
        $stock_product_ids = StockProduct::where(['product_sn' => $product_sn, 'properties' => $properties])->pluck('id');
        if ($stock_product_ids) {
            $stock_items = StockBiddingItem::whereIn('status', [0, 1])->whereIn('stock_product_id', $stock_product_ids)->get();
        }
        $stock_item_ids = [];
        if ($stock_items->count()) {
            $stock_item_ids = $stock_items->pluck('id')->toArray();
        }

        $operation_id = self::createOperation([
            'carryme_bidding_item_ids' => $item_ids,
            'stock_bidding_item_ids' => $stock_item_ids,
            'product_sn' => $product_sn,
            'properties' => $properties,
            'carryme_cancel_callback' => true,
        ], self::CANCEL_REASON_APP_CANCEL);

        foreach ($items as $item) {
            $item->update(['cancel_progress' => CarryMeBiddingItem::CANCEL_PENDING]);
            BiddingOperationLog::create(['operation_id' => $operation_id, 'carryme_bidding_item_id' => $item->id]);
            Excute::appCancel([
                'carryme_bidding_item_id' => $item->id,
                'remark' => $params['remark'] ?? '',
                'product_sn' => $product_sn,
            ]);
        }

        foreach ($stock_items as $item) {
            $item->update(['cancel_progress' => CarryMeBiddingItem::CANCEL_PENDING]);
            BiddingOperationLog::create(['operation_id' => $operation_id, 'stock_bidding_item_id' => $item->id]);
            Excute::stockCancel([
                'stock_bidding_item_id' => $item->id,
                'remark' => 'app出价取消，库存出价取消',
                'product_sn' => $product_sn,
            ]);
        }

        return true;
    }

    /**
     * app订单成立后，取消app和库存出价；库存订单成立后，取消库存出价，方便完成后的刷新出价
     * @param ChannelBidding $bidding
     */
    static function orderCreated($bidding)
    {
        if ((!$bidding->carryme_bidding_id) && (!$bidding->stock_bidding_id)) return false;

        $carryme_items = collect();
        $stock_items = collect();
        $input = [];
        $remark = '';
        // app订单成立，取消app和库存的所有出价
        if ($bidding->carryme_bidding_id) {
            $raw = "((`channel_code` in ('GOAT', 'STOCKX','CARRYME') and (`qty_sold` =0)) or `channel_code` = 'DW')";
            $carryme_items = CarrymeBiddingItem::where(['carryme_bidding_id' => $bidding->carryme_bidding_id])->whereIn('status', [0, 1])->whereRaw($raw)->get();
            $carryme = $bidding->carrymeBidding;
            $product_sn = '';
            $properties = '';
            if ($carryme) {
                $product_sn = $bidding->product_sn;
                $properties = $carryme->properties[0]['valuecn'] ?? '';
            }
            $stock_items = collect();
            if ($product_sn && $properties) {
                // 同规格的库存出价item
                $stock_product_ids = StockProduct::where(['product_sn' => $product_sn, 'properties' => $properties])->pluck('id');
                if ($stock_product_ids) {
                    $raw = "((`channel_code` in ('GOAT', 'STOCKX','CARRYME') and (`qty_sold` =0)) or `channel_code` = 'DW')";
                    $stock_items = StockBiddingItem::whereIn('status', [0, 1])->whereIn('stock_product_id', $stock_product_ids)->whereRaw($raw)->get();
                }
            }

            $input = [
                'carryme_bidding_item_ids' => $carryme_items->pluck('id')->toArray(),
                'stock_bidding_item_ids' => $stock_items->count() ? $stock_items->pluck('id')->toArray() : [],
                'product_sn' => $product_sn,
                'properties' => $properties,
                'carryme_cancel_callback' => false,
            ];
            $remark = 'app订单成立，全渠道下架';
        }
        // 库存订单，取消库存的所有出价
        if ($bidding->stock_bidding_id) {
            $stock_product = $bidding->stockBiddingItem->stockProduct;
            $product_sn = $stock_product->product_sn;
            $properties = $stock_product->properties;
            $raw = "((`channel_code` in ('GOAT', 'STOCKX','CARRYME') and (`qty_sold` =0)) or `channel_code` = 'DW')";
            $stock_items = StockBiddingItem::where(['stock_bidding_id' => $bidding->stock_bidding_id])->whereIn('status', [0, 1])->whereRaw($raw)->get();
            $input = [
                'stock_bidding_item_ids' => $stock_items->pluck('id')->toArray(),
                'product_sn' => $product_sn,
                'properties' => $properties,
                'carryme_cancel_callback' => false,
            ];
            $remark = '库存订单成立，全渠道下架';
        }

        // 出价已全部取消，直接刷新库存出价
        if (!(($input['stock_bidding_item_ids'] ?? []) || ($input['carryme_bidding_item_ids'] ?? []))) {
            (new BiddingAsyncLogic())->stockBiddingAdd([
                'product_sn' => $product_sn,
                'properties' => $properties,
                'remark' => $remark
            ]);
            return true;
        }


        $operation_id = self::createOperation($input, self::CANCEL_REASON_ORDER_CREATED);

        foreach ($carryme_items as $item) {
            BiddingOperationLog::create(['operation_id' => $operation_id, 'carryme_bidding_item_id' => $item->id]);
            if ($item->status == CarryMeBiddingItem::STATUS_BID) {
                // 执行取消
                BidExecute::appItemCancel([
                    'carryme_bidding_item_id' => $item->id,
                    'remark' => $remark,
                    'product_sn' => $input['product_sn'],
                ]);
            } else {
                Excute::appCancel([
                    'carryme_bidding_item_id' => $item->id,
                    'remark' => $remark,
                    'product_sn' => $input['product_sn'],
                ]);
            }
        }

        foreach ($stock_items as $item) {
            BiddingOperationLog::create(['operation_id' => $operation_id, 'stock_bidding_item_id' => $item->id]);
            if ($item->status == StockBiddingItem::STATUS_SUCCESS) {
                BidExecute::stockItemCancel([
                    'stock_bidding_item_id' => $item->id,
                    'remark' => $remark,
                    'product_sn' => $input['product_sn'],
                ]);
            } else {
                Excute::stockCancel([
                    'stock_bidding_item_id' => $item->id,
                    'remark' => $remark,
                    'product_sn' => $input['product_sn'],
                ]);
            }
        }
        return true;
    }

    /**
     * 库存出价取消后，检查要不要重新出价
     *
     * @param StockBiddingItem $stock_bidding_item
     */
    static function _stockAfterCancel($stock_bidding_item)
    {
        $product = $stock_bidding_item->stockProduct;
        if ($product) {
            // 刷新商品出价中库存
            StockProductLogic::stockUpdate($product->id, StockProductLogic::STOCK_UPDATE_BID);
        }

        $key = sprintf('%s_%s', $product->product_sn, $product->properties);
        // sku维度全部取消后重新出价，一般是更新商品时会触发
        $rebid_remark = Redis::hget(RedisKey::SKU_BID_AFTER_CANCEL, $key);
        if ($rebid_remark) {
            $channel_biddings = DB::table('channel_bidding as cb')
                ->leftJoin('stock_bidding_items as sbi', 'sbi.id', '=', 'cb.stock_bidding_item_id')
                ->leftJoin('stock_products as sp', 'sbi.stock_product_id', '=', 'sp.id')
                ->select('cb.*')
                ->where('sp.product_sn', $product->product_sn)
                ->whereRaw("sp.properties='{$product->properties}'")
                ->where('cb.product_sn', $product->product_sn)
                ->whereIn('cb.status', [ChannelBidding::BID_DEFAULT, ChannelBidding::BID_SUCCESS])
                ->where('cb.qty_sold', 0)
                ->where('cb.channel_code', '<>', 'CARRYME')
                ->get();

            $num = $channel_biddings->count();
            if (!$num) {
                // 全部取消，刷新出价
                (new BiddingAsyncLogic())->stockBiddingAdd([
                    'product_sn' => $product->product_sn,
                    'properties' => $product->properties,
                    'remark' => $rebid_remark,
                ]);
                Redis::hdel(RedisKey::SKU_BID_AFTER_CANCEL, $key);
                return;
            }
        }

        // 相同的stock_bid_id全部取消出价后重新出价，刷新出价时使用
        $remark = Redis::hget(RedisKey::BID_AFTER_CANCEL, $stock_bidding_item->stock_bidding_id);
        if ($remark) {
            $num = ChannelBidding::whereIn('status', [ChannelBidding::BID_DEFAULT, ChannelBidding::BID_SUCCESS, 'qty_sold' => 0])->where(['stock_bidding_id' => $stock_bidding_item->stock_bidding_id])->count();
            if ($num > 0) return;

            // 全部取消，刷新出价
            (new BiddingAsyncLogic())->stockBiddingAdd([
                'product_sn' => $product->product_sn,
                'properties' => $product->properties,
                'remark' => $remark,
            ]);
            Redis::hdel(RedisKey::BID_AFTER_CANCEL, $stock_bidding_item->stock_bidding_id);
        }
        return;
    }

    /**
     * 以上取消操作执行完后进行的后续操作
     *  - app出价前取消、app出价取消、订单成立后取消出价后，都需要刷新一次库存出价
     *  - app出价取消后，执行Carryme取消回调
     *
     * @param CarrymeBiddingItem $carryme_bidding_item
     * @param StockBiddingItem $stock_bidding_item
     * @param bool $result
     */
    static function cancelCompleted($carryme_bidding_item = null, $stock_bidding_item = null, $result)
    {
        $app = false;
        $where = [];
        if ($carryme_bidding_item) {
            Log::channel('daily2')->info('bid', ['取消完成', 'carryme_item_id' => $carryme_bidding_item->id]);
            $app = true;
            $operation_ids = BiddingOperationLog::where(['carryme_bidding_item_id' => $carryme_bidding_item->id])->pluck('operation_id');
            $where['carryme_bidding_item_id'] = $carryme_bidding_item->id;
        }
        if ($stock_bidding_item) {
            Log::channel('daily2')->info('bid', ['取消完成', 'stock_item_id' => $stock_bidding_item->id]);

            self::_stockAfterCancel($stock_bidding_item);

            $operation_ids = BiddingOperationLog::where(['stock_bidding_item_id' => $stock_bidding_item->id])->pluck('operation_id');
            $where['stock_bidding_item_id'] = $stock_bidding_item->id;
        }

        if ($where) {
            BidExcutionLog::where($where)->whereIn('cancel_status', BidExcutionLog::$cancel_status)->update(['cancel_status' => BidExcutionLog::COMPLETE]);
        }

        if (!$operation_ids->count()) return true;
        $operations = BiddingOperation::where(['status' => 'pending'])->whereIn('operation_id', $operation_ids)->get();
        foreach ($operations as $operation) {
            // app出价单取消完成后，app回调
            if ($app && $operation->input['carryme_cancel_callback'] ?? false) {
                $carryme = $carryme_bidding_item->carrymeBidding;
                CarrymeCallbackLogic::bidCancelNew([
                    'carryme_bidding_id' => $carryme_bidding_item->carryme_bidding_id,
                    'carryme_bidding_item_id' => $carryme_bidding_item->id,
                    'callback_id' => $carryme ? $carryme->callback_id : 0,
                    'result' => $result,
                ]);
            }

            $carryme_bidding_item_ids = $operation->input['carryme_bidding_item_ids'] ?? [];
            $stock_bidding_item_ids = $operation->input['stock_bidding_item_ids'] ?? [];
            if ((!$carryme_bidding_item_ids) && (!$stock_bidding_item_ids)) {
                continue;
            }

            try {
                $stock_num = 0;
                $carryme_num = 0;
                DB::beginTransaction();
                if ($stock_bidding_item_ids) {
                    $stock_num = StockBiddingItem::whereIn('id', $stock_bidding_item_ids)
                        ->whereIn('status', [StockBiddingItem::STATUS_DEFAULT, StockBiddingItem::STATUS_SUCCESS])
                        ->where('qty_sold', 0)->count();
                }
                if ($carryme_bidding_item_ids) {
                    $carryme_num = CarryMeBiddingItem::whereIn('id', $carryme_bidding_item_ids)
                        ->whereIn('status', [CarryMeBiddingItem::STATUS_DEFAULT, CarryMeBiddingItem::STATUS_BID])
                        ->where('qty_sold', 0)->count();
                }
                $num = $stock_num + $carryme_num;
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                continue;
            }

            // 取消操作执行完后开始执行后续流程
            if (!$num) {
                $operation->update(['status' => 'successed']);
                // app新增出价前取消完成，开始新增出价
                if ($operation->scence == self::CANCEL_REASON_APP_BID) {
                    // (new BiddingAsyncLogic())->stockBiddingAdd([
                    //     'product_sn' => $operation->input['product_sn'],
                    //     'properties' => $operation->input['properties'],
                    //     'remark' => '比价触发，carryme_bidding_id .' . $operation->input['carryme_bidding_id'],
                    //     'carryme_id' => $operation->input['carryme_bidding_id'],
                    // ]);
                }

                // app出价取消完成，新增库存出价
                if ($operation->scence == self::CANCEL_REASON_APP_CANCEL) {
                    if (is_array($operation->input['properties'])) {
                        foreach ($operation->input['properties'] as $pro) {
                            (new BiddingAsyncLogic())->stockBiddingAdd([
                                'product_sn' => $operation->input['product_sn'],
                                'properties' => $pro,
                                'remark' => 'app出价取消，刷新出价'
                            ]);
                        }
                    }

                    continue;
                }
                // 订单成立取消完成，新增库存出价
                if ($operation->scence == self::CANCEL_REASON_ORDER_CREATED) {
                    (new BiddingAsyncLogic())->stockBiddingAdd([
                        'product_sn' => $operation->input['product_sn'],
                        'properties' => $operation->input['properties'],
                        'remark' => '订单成立，刷新出价'
                    ]);
                    continue;
                }
            }
        }
    }
}
