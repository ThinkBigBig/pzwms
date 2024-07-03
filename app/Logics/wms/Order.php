<?php

namespace App\Logics\wms;

use App\Logics\BaseLogic;
use App\Logics\wms\Warehouse as WmsWarehouse;
use App\Models\Admin\V2\Inventory;
use App\Models\Admin\V2\ObOrder;
use App\Models\Admin\V2\preAllocationDetail;
use App\Models\Admin\V2\Product;
use App\Models\Admin\V2\ProductSpecAndBar;
use App\Models\Admin\V2\Warehouse;
use App\Models\Admin\V2\WmsAfterSaleOrder;
use App\Models\Admin\V2\WmsAfterSaleOrderDetail;
use App\Models\Admin\V2\WmsFile;
use App\Models\Admin\V2\WmsLogisticsProduct;
use App\Models\Admin\V2\WmsOptionLog;
use App\Models\Admin\V2\WmsOrder;
use App\Models\Admin\V2\WmsOrderDeliverStatement;
use App\Models\Admin\V2\WmsOrderDetail;
use App\Models\Admin\V2\WmsOrderItem;
use App\Models\Admin\V2\WmsOrderPayment;
use App\Models\Admin\V2\WmsOrderStatement;
use App\Models\Admin\V2\WmsShippingOrder;
use App\Models\Admin\V2\WmsShop;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 销售订单
 */
class Order extends BaseLogic
{

    function import($params)
    {
        $warsehouse = Warehouse::where('status', 1)->select(['id', 'warehouse_code', 'warehouse_name'])->get()->keyBy('warehouse_name')->toArray();
        $shops = WmsShop::where('status', 1)->select(['id', 'code', 'name'])->get()->keyBy('name')->toArray();
        $logistics = WmsLogisticsProduct::where('status', 1)->get()->keyBy('product_name')->toArray();
        $data = [];
        $err = [];
        $warehouse_code = '';
        $shop_code = '';
        $product_code = '';
        $last_order_err = 0;
        $is_order = 1;
        foreach ($params as $k => $item) {
            if ($k < 3) continue;

            if (array_unique($item) == [null]) continue;
            $item = array_map(function ($v) {
                return trim($v);
            }, $item);
            list($order_raw, $details_raw) = array_chunk($item, 12, true);
            $is_order = array_filter($order_raw, function ($v) {
                return !empty($v);
            });
            if ($last_order_err && !$is_order) continue;
            $last_order_err = 0;
            $product_sn = $item[12];
            $property = $item[13];
            $sku = $product_sn . '#' . $property;
            $product = Product::where('product_sn', $product_sn)->where('status', 1)->first();
            if (!$product) {
                $err[] = sprintf(__('tips.spu_empty'), $k + 1);
                if (isset($last) && !$is_order) {
                    array_pop($data);
                }
                $last_order_err = 1;
                continue;
            }
            $bar_code = ProductSpecAndBar::where('product_id', $product->id)->where('spec_one', $property)->value('bar_code');
            if (!$bar_code) {
                $err[] = sprintf(__('tips.spu_empty'), $k + 1);
                if (isset($last) && !$is_order) {
                    array_pop($data);
                }
                $last_order_err = 1;
                continue;
            }

            if (!($item[12] && $item[13] && $item[14]  && $item[18])) {
                $err[] = sprintf(__('tips.require_empty'), $k + 1);
                if (isset($last) && !$is_order) {
                    array_pop($data);
                }
                $last_order_err = 1;
                continue;
            }


            if ($item[18] && (!in_array($item[18], ['A', 'B', 'C', 'D', 'E']))) {
                $err[] = sprintf(__('tips.quity_level_error'), $k + 1);
                if (isset($last) && !$is_order) {
                    array_pop($data);
                }
                $last_order_err = 1;
                continue;
            }
            if ($is_order) {
                $is_order = 1;
                if ($last_order_err) continue;
                $last =  count($data);
                $warehouse_code = $warsehouse[$item[7]]['warehouse_code'] ?? '';
                if (!$warehouse_code) {
                    $err[] = sprintf(__('tips.warehouse_empty'), $k + 1);
                    if (isset($last) && !$is_order) {
                        array_pop($data);
                    }
                    $last_order_err = 1;
                    continue;
                }
                $shop_code = $shops[$item[6]]['code'] ?? '';
                if (!$shop_code) {
                    $err[] = sprintf(__('tips.shop_empty'), $k + 1);
                    if (isset($last) && !$is_order) {
                        array_pop($data);
                    }
                    $last_order_err = 1;
                    continue;
                }
                if ($item[8]) $product_code = $logistics[$item[8]]['product_code'] ?? '';

                $data[] = [
                    'order_user' => $item[0] ?? ADMIN_INFO['user_id'],
                    'order_at' => $item[1] ?: '',
                    'paysuccess_time' => $item[2] ?: '',
                    'buyer_account' => $item[3],
                    'third_no' => $item[4],
                    'estimate_sendout_time' => $item[5] ?: '',
                    'shop_code' => $shop_code,
                    'shop_name' => $item[6],
                    'warehouse_code' => $warehouse_code,
                    'warehouse_name' => $item[7],
                    'product_code' => $product_code,
                    'product_name' => $item[8],
                    'deliver_fee' => $item[9],
                    'seller_message' => $item[10],
                    'remark' => $item[11],
                    'skus' => [
                        [
                            'num' => $item[14],
                            'amount' => $item[15],
                            'payment_amount' => $item[16],
                            'discount_amount' => 0,
                            'quality_level' => $item[18],
                            'uniq_code' => $item[19] ?: '',
                            'remark' => $item[20] ?: '',
                            'bar_code' => $bar_code,
                            'warehouse_code' => $warehouse_code,
                            'sku' => $sku,
                        ]
                    ]
                ];
            } else {
                if ($last_order_err) continue;
                $is_order = 0;
                if (isset($last) && !$last_order_err) $data[$last]['skus'][] = [
                    'num' => $item[14],
                    'amount' => $item[15],
                    'payment_amount' => $item[16],
                    'discount_amount' => 0,
                    'quality_level' => $item[18],
                    'uniq_code' => $item[19] ?: '',
                    'remark' => $item[20] ?: '',
                    'bar_code' => $bar_code,
                    'warehouse_code' => $warehouse_code,
                    'sku' => $sku,
                ];
            }
        }
        if ($last_order_err && !$is_order) array_pop($data);
        // if ($err) {
        //     $this->setErrorMsg(implode(PHP_EOL, $err));
        //     return false;
        // }
        if ($err) {
            $this->setErrorMsg(implode(PHP_EOL, $err));
            WmsOptionLog::add(127, 'XSD-import', '销售单导入', '导入报错', $err);
        }
        if (!$data) return false;
        foreach ($data as $item) {
            if ($item) $this->add($item);
        }
        return true;
    }

    //更新订单数量
    // static function updateNum(array $ids){
    //     $order =  WmsOrder::whereIn('id',$ids)->get();
    //     if($order->isNotEmpty()){
    //         foreach($order as $item){
    //             $num = $order->details()->where('staus',1)->sum('num');
    //             $item->update(['num'=>$num]);
    //         }
    //     }
    // }



    // 新增
    function add($params, $source = 1)
    {
        $params['third_no'] = $params['third_no'] ?: WmsOrder::generateThirdNo();
        $params['payment_status'] = 1;
        $order = WmsOrder::where('third_no', $params['third_no'])->whereIn('status', WmsOrder::$active_status)->first();
        if ($order) {
            $this->setErrorMsg(__('admin.wms.order.third_no.exist'));
            return false;
        }

        if (($params['product_code'] ?? '') && empty($params['product_name'] ?? '')) {
            $params['product_name'] = WmsLogisticsProduct::where('product_code', $params['product_code'])->value('product_name');
        }
        if (($params['shop_code'] ?? '') && empty($params['shop_name'] ?? '')) {
            $params['shop_name'] = WmsShop::where('code', $params['shop_code'])->value('name');
        }

        // if (!($params['warehouse_name'] ?? '') && ($params['warehouse_code'] ?? '')) {
        //     $params['warehouse_name'] = Warehouse::getWarehouseName($params['warehouse_code'], ADMIN_INFO['tenant_id']);
        // }
        $params['warehouse_name'] = Warehouse::_getRedisMap('warehouse_map', $params['warehouse_code']);
        $params['order_at'] = ($params['order_at'] ?? '') ? $params['order_at'] : date('Y-m-d H:i:s');
        if ($params['estimate_sendout_time'] ?? '') $params['estimate_sendout_time'] = $params['estimate_sendout_time'];
        if ($params['paysuccess_time'] ?? '') $params['paysuccess_time'] = $params['paysuccess_time'];

        try {
            DB::beginTransaction();

            $data = self::filterEmptyData($params, ['shop_code', 'warehouse_code', 'warehouse_name', 'third_no', 'product_code', 'deliver_no', 'buyer_account', 'order_user', 'remark', 'seller_message', 'buyer_message',  'order_at', 'estimate_sendout_time', 'paysuccess_time', 'shop_name',  'product_name', 'estimate_sendout_time', 'paysuccess_time', 'payment_status']);
            $data['admin_user_id'] = ADMIN_INFO['user_id'];
            $data['tenant_id'] = ADMIN_INFO['tenant_id'];
            $data['create_user_id'] = ADMIN_INFO['user_id'];
            $data['code'] = WmsOrder::code();
            //匹配pdf
            $pdf  = WmsFile::where('name', $data['third_no'] . '.pdf')->first();
            if ($pdf) {
                $data['deliver_no'] = $pdf->name;
                $data['deliver_path'] = $pdf->file_path;
            }
            $order = WmsOrder::create($data);
            WmsOptionLog::add(WmsOptionLog::ORDER, $order->code, '新增', '新增', []);

            $this->_orderUpdate($order, $params, ['admin_user_id' => ADMIN_INFO['user_id']]);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            throw $e;
            return false;
        }
    }


    function save($params, $source = 1)
    {
        $order = WmsOrder::find($params['id']);
        if (!$order) {
            $this->setErrorMsg(__('admin.wms.order.exception'));
            return false;
        }
        if ($order->status != WmsOrder::STASH) {
            $this->setErrorMsg(__('admin.wms.order.status.error'));
            return false;
        }

        if ($params['product_code'] ?? '') {
            $params['product_name'] = WmsLogisticsProduct::where('product_code', $params['product_code'])->value('product_name');
        }
        if (($params['shop_code'] ?? '')) {
            $params['shop_name'] = WmsShop::where('code', $params['shop_code'])->value('name');
        }
        if ($params['warehouse_code'] ?? '') {
            $params['warehouse_name'] = Warehouse::getWarehouseName($params['warehouse_code'], ADMIN_INFO['tenant_id']);
        }
        if ($params['order_at'] ?? '') $params['order_at'] = $params['order_at'];
        if ($params['estimate_sendout_time'] ?? '') $params['estimate_sendout_time'] = $params['estimate_sendout_time'];
        if ($params['paysuccess_time'] ?? '') $params['paysuccess_time'] = $params['paysuccess_time'];

        try {
            DB::beginTransaction();
            $order = WmsOrder::find($params['id']);
            if ($params['skus'] ?? []) {
                $details = $order->details;
                // 取消原明细，并释放锁定的库存
                foreach ($details as $detail) {
                    $detail->update(['status' => 0]);
                    // Inventory::releaseInv($detail->lock_ids);
                }
            }
            WmsOptionLog::add(WmsOptionLog::ORDER, $order->code, '修改', '修改', []);

            $update = self::filterEmptyData($params, ['shop_code', 'warehouse_code', 'warehouse_name', 'third_no', 'product_code', 'deliver_no', 'buyer_account', 'order_user', 'remark', 'seller_message', 'buyer_message', 'product_code', 'product_code', 'product_code', 'order_at', 'estimate_sendout_time', 'paysuccess_time', 'shop_name', 'product_name', 'estimate_sendout_time', 'paysuccess_time']);
            $update['admin_user_id'] = ADMIN_INFO['user_id'];
            $this->_orderUpdate($order, $params, $update);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    private function _orderUpdate($order, $params, $update)
    {
        if (empty($params['skus'] ?? [])) {
            $order->update($update);
            return;
        }
        if ($order->status != WmsOrder::STASH) {
            throw new Exception(__('admin.wms.order.status.error'));
        }

        $num = 0;
        $total_amount = 0;
        $payment_amount = 0;
        $discount_amount = 0;
        foreach ($params['skus'] as $sku) {
            $sku['warehouse_code'] = $params['warehouse_code'];
            // 成交额
            $amount = bcmul($sku['num'], $sku['amount'], 2);
            // 实际支付总额
            $pay_amount = $sku['payment_amount'] ?? 0;
            if (!$pay_amount) $pay_amount = bcsub($amount, $sku['discount_amount'], 2);
            if ($pay_amount < 0) {
                throw new Exception(__('admin.wms.order.amount.error'));
            }

            $where = [
                'origin_code' => $order->code,
                'bar_code' => $sku['bar_code'],
                'quality_level' => $sku['quality_level'],
                'tenant_id' => ADMIN_INFO['tenant_id'],
            ];
            if ($sku['uniq_code'] ?? '') $where['uniq_code'] = $sku['uniq_code'];
            if ($sku['cost_price'] ?? '') $where['cost_price'] = $sku['cost_price'];
            if ($sku['batch_no'] ?? '') $where['batch_no'] = $sku['batch_no'];
            if ($sku['sup_id'] ?? '') $where['sup_id'] = $sku['sup_id'];

            WmsOrderDetail::updateOrCreate($where, [
                'warehouse_code' => $sku['warehouse_code'],
                'sku' => $sku['sku'] ?? '',
                'num' => $sku['num'],
                'remark' => $sku['remark'] ?? "",
                'quality_type' => QualityControl::getQualityType($sku['quality_level']),
                'third_no' => $order->third_no,
                'cost_price' => $sku['cost_price'] ?? '',
                'price' => $sku['amount'] ?? 0, //成交价
                'amount' => $amount, //成交额
                'payment_amount' => $pay_amount, //实际支付金额
                'discount_amount' => $sku['discount_amount'] ?? 0, //优惠额
                'retails_price' => $sku['retails_price'] ?? 0, //零售额
                // 'lock_ids' => $ids,
                'status' => 1,
                'admin_user_id' => ADMIN_INFO['user_id'],
            ]);
            // $num += intval($sku['num']);
            // $total_amount = bcadd($total_amount, $amount, 2);
            // $payment_amount = bcadd($payment_amount, $pay_amount, 2);
            // $discount_amount = bcadd($discount_amount, $sku['discount_amount'] ?? 0, 2);
        }
        $details = $order->details()->where('status', 1)->get()->toArray();
        foreach ($details as $d) {
            $num += intval($d['num']);
            $total_amount = bcadd($total_amount, $d['amount'], 2);
            $payment_amount = bcadd($payment_amount, $d['payment_amount'], 2);
            $discount_amount = bcadd($discount_amount, $d['discount_amount'] ?? 0, 2);
        }
        $update['num'] = $num;
        $update['total_amount'] = $total_amount; //订单总额
        $update['payment_amount'] = $payment_amount; //实际支付总额
        $update['discount_amount'] = $discount_amount; //优惠总额
        $order->update($update);
    }

    //库存锁定
    function inv_lock($order)
    {
        // 重新锁定库存
        $ids = [];
        $details = $order->details()->get();
        foreach ($details as $d) {
            $where =  [
                'bar_code' => $d['bar_code'],
            ];
            if (!empty($d->uniq_code)) $where['uniq_code'] = $d->uniq_code;
            if (!empty($d->sup_id)) $where['sup_id'] = $d->sup_id;
            if ($d->cost_price != 0) $where['buy_price'] = $d->cost_price;
            else {
                if (!empty($d->sup_id)) $where['buy_price'] = $d->cost_price;
            }
            if (!empty($d->quality_level)) $where['quality_level'] = $d->quality_level;
            if (!empty($d->batch_no)) $where['batch_no'] = $d->batch_no;
            // $_ids = Inventory::checkInv($d->warehouse_code, $d->num, $where);
            $_ids = $this->_invIds($d, $where);
            // dump($d->warehouse_code, $d->num,$where,Inventory::checkInv($d->warehouse_code, $d->num,$where));
            // 检查库存
            if (count($_ids) < $d->num) {
                $this->setErrorMsg(__('admin.wms.order.understock'));
                return false;
                // throw new Exception(__('admin.wms.order.understock'));
            }
            $d->lock_ids = $_ids;
            $ids = array_merge($_ids, $ids);
            $d->save();
        }
        if ($ids) { // 锁定商品库存
            // Inventory::lockInv($ids, 1, $order->code);
            Inventory::lockInvById($ids, 1, $order->code);
        }
        return $ids;
    }

    // 查看是否有多条码，存在则挨个校验库存数据
    function _invIds($d, $where)
    {
        $bar_code = ProductSpecAndBar::where('bar_code', $d['bar_code'])->first();
        $bar_codes = ProductSpecAndBar::where('sku', $bar_code->sku)->distinct(true)->pluck('bar_code');
        foreach ($bar_codes as $bar_code) {
            $where['bar_code'] = $bar_code;
            $_ids = Inventory::checkInv($d->warehouse_code, $d->num, $where);
            if (count($_ids) >= $d->num) {
                if ($d->bar_code != $bar_code) $d->update(['bar_code' => $bar_code]);
                return $_ids;
            }
        }

        $this->setErrorMsg(__('admin.wms.order.understock'));
        return [];
    }

    //释放库存
    function inv_release($order)
    {
        // 释放原先锁定库存
        // $old = WmsOrderDetail::where($where)->get();
        // $old = $order->details()->get();
        // $old_ids = [];
        // foreach ($old as $item) {
        //     if (!$item->lock_ids) continue;
        //     $old_ids = array_merge($old_ids, $item->lock_ids);
        // }
        // if ($old_ids) Inventory::releaseInv($old_ids);
        $row = Inventory::releaseInvByCode([$order->code]);
        if ($row) return $order->details()->update(['lock_ids' => '']);

    }

    // 订单复制新增
    function copyAdd($params)
    {
        $order = WmsOrder::where('id', $params['id'])->first();
        if (!$order) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }
        $details = $order->details;
        if (!$details->count()) {
            $this->setErrorMsg(__('tips.doc_error'));
            return false;
        }

        $data = [
            "third_no" => $order->third_no,
            "shop_code" => $order->shop_code,
            "shop_name" => $order->shop_name,
            "warehouse_code" => $order->warehouse_code,
            "warehouse_name" => $order->warehouse_name,
            "product_code" => $order->product_code,
            "product_name" => $order->product_name,
            "deliver_no" => $order->deliver_no,
            "buyer_account" => $order->buyer_account,
            "order_user" => $order->order_user,
            "order_at" => $order->order_at,
            "paysuccess_time" => $order->paysuccess_time,
            "estimate_sendout_time" => $order->estimate_sendout_time,
            "remark" => $order->remark,
            "seller_message" => $order->seller_message,
            "buyer_message" => $order->buyer_message,
            "payment_status" => $order->payment_status,
        ];

        foreach ($details as $detail) {
            $data['skus'][] = [
                "sku" => $detail->sku,
                "batch_no" => $detail->batch_no,
                "bar_code" => $detail->bar_code,
                "quality_level" => $detail->quality_level,
                "num" => $detail->num,
                "amount" => $detail->amount,
                "payment_amount" => $detail->payment_amount,
                "discount_amount" => $detail->discount_amount,
                "sup_id" => $detail->sup_id,
                "warehouse_code" => $detail->warehouse_code,
                "uniq_code" => $detail->uniq_code,
                "cost_price" => $detail->cost_price,
            ];
        }
        return $this->add($data);
    }

    // 详情
    function info($params)
    {
        $payments = []; //支付信息
        $payment_total = []; //支付信息汇总
        $info = [];
        $logs = []; //操作记录
        $deliver = [];
        $pres = []; //预配信息
        $aftersale = []; //售后登记
        if (!empty($params['id'])) $info = WmsOrder::with(['orderUser', 'createUser', 'adminUser', 'request', 'afterSale'])->find($params['id']);
        if (!empty($params['code'])) $info = WmsOrder::with(['orderUser', 'createUser', 'adminUser', 'request', 'afterSale'])->where('code', $params['code'])->first();
        if (!$info) $info = [];
        if ($info) {
            $request = $info->request;
            $info = $info->toArray();
            self::infoFormat($info);

            // 预配明细
            $pres = empty($request) ? [] : ShippingRequest::preDetails(['request_code' => $request->request_code]);
            // 发货明细
            $deliver = empty($request) ? [] : WmsShippingOrder::where('request_code', $request->request_code)->get()->toArray();
            foreach ($deliver as &$item) {
                $item['product_name'] = $info['product_name'] ?? '';
                $item['deliver_no'] = $info['deliver_no'] ?? '';
                $item['has_deliver_no'] = $info['deliver_path'] ? 1 : 0;
                $item['deliver_url'] = $info['deliver_url'] ?? '';
                $item['deliver_path'] = $info['deliver_path'] ?? '';
            }
            // 售后单明细
            $aftersale = $info['after_sale'];
            unset($info['request']);
            unset($info['after_sale']);

            $payments = WmsOrderPayment::where('origin_code', $info['code'])->get()->toArray();
            foreach ($payments as $payment) {
                $payment_total['amount'] = ($payments_total['amount'] ?? 0) + $payment['amount'];
            }
            $logs = WmsOptionLog::list(WmsOptionLog::ORDER, $info['code']);
        }
        return compact('info', 'payments', 'payment_total', 'logs', 'deliver', 'pres', 'aftersale');
    }

    // 明细
    function detail($params)
    {
        $order = WmsOrder::with(['details', 'details.specBar'])->where('id', $params['id'])->first()->toArray();
        $data = [];
        foreach ($order['details'] as &$item) {
            self::sepBarFormat($item);
            $data[] = $item;
        }
        return $data;
    }

    // 搜索
    function search($params, $export = false)
    {

        $model = new WmsOrder();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            if ($params['number'] ?? '') {
                $model = $model->where(function ($query) use ($params) {
                    $query->orWhere('code', $params['number'])->orWhere('third_no', $params['number'])->orWhere('deliver_no', $params['number']);
                });
            }
            return $model->with(['createUser'])->orderBy('created_at', 'desc');
        });
        return $list;
    }

    // 搜索
    function searchWithDetail($params, $export = false)
    {
        $model = new WmsOrder();
        $params['export_type'] = 1;

        $export_config = [];
        $column = [];
        if ($export) {
            $export_config = $model->exportConfig($export, 2);
        } else {
            $column = $model->showColumns();
        }
        $size = $params['size'] ?? 10;
        $cur_page = $params['cur_page'] ?? 1;
        $select = ['*'];
        $model = $this->commonWhere($params, $model);
        if ($params['ids'] ?? []) {
            $ids = is_string($params['ids']) ? explode(',', $params['ids']) : $params['ids'];
            $model = $model->whereIn('id', $ids);
        }
        if ($export) $list = $model->with(['details', 'details.product', 'createUser'])->orderBy('order_at', 'desc')->orderBy('id', 'desc')->paginate($size, $select, 'page', $cur_page);
        else $list = $model->with(['details', 'details.product', 'createUser'])->orderBy('id', 'desc')->paginate($size, $select, 'page', $cur_page);

        $list = json_decode(json_encode($list), true);
        $arr = [];
        foreach ($list['data'] as $item) {
            self::infoFormat($item);
            $details = $item['details'];
            unset($item['details']);
            $base = $item;

            foreach ($details as $detail) {
                unset($detail['lock_ids']);
                $tmp = [];
                $tmp['wms_spec_and_bar.sku'] = $detail['product']['sku'] ?? '';
                $tmp['wms_spec_and_bar.spec_one'] = $detail['product']['spec_one'] ?? '';
                $tmp['wms_product.product_sn'] = $detail['product']['product']['product_sn'] ?? '';
                $tmp['wms_product.name'] = $detail['product']['product']['name'] ?? '';
                unset($detail['product']);
                foreach ($detail as $key => $val) {
                    $tmp['wms_order_details.' . $key] = $val;
                }
                $arr[] = array_merge($base, $tmp);
                $base = [];
            }
        }
        $list['data'] = $arr;
        $list['export_config'] = $export_config;
        $list['column'] = $column;
        return $list;
    }

    // 删除
    function delete($id)
    {
        $order = WmsOrder::find($id);
        if (!$order) {
            $this->setErrorMsg(__('admin.wms.order.exception'));
            return false;
        }
        if ($order->status != WmsOrder::STASH) {
            $this->setErrorMsg(__('admin.wms.order.status.error'));
            return false;
        }
        $details = $order->details;
        try {
            DB::beginTransaction();
            foreach ($details as $detail) {
                if ($detail->lock_ids) {
                    Inventory::releaseInv($detail->lock_ids);
                }
            }
            $order->delete();
            WmsOptionLog::add(WmsOptionLog::ORDER, $order->code, '删除', '删除销售订单', []);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
        return true;
    }

    // 撤回
    function revoke($params)
    {
        $fail = [];
        $orders = WmsOrder::whereIn('id', $params['ids'])->get();
        foreach ($orders as $order) {
            if ($order->status != WmsOrder::WAIT_AUDIT) {
                $fail[] = $order->id;
                continue;
            }
            $this->inv_release($order);
            $order->update(['status' => WmsOrder::STASH, 'admin_user_id' => ADMIN_INFO['user_id'],]);
            WmsOptionLog::add(WmsOptionLog::ORDER, $order->code, '撤回', '撤回到暂存状态', []);
        }
        if ($fail) {
            $this->setErrorMsg(sprintf('%s orderIds:%s', __('admin.wms.order.status.error'), implode($fail)));
            return false;
        }
        return true;
    }

    // 提交
    function submit($params)
    {
        $order = WmsOrder::find($params['id']);
        if (!$order) {
            $this->setErrorMsg(__('admin.wms.order.exception'));
            return false;
        }
        if ($order->status != WmsOrder::STASH) {
            $this->setErrorMsg(__('admin.wms.order.status.error'));
            return false;
        }
        //锁定库存
        DB::beginTransaction();
        $res = $this->inv_lock($order);
        if (empty($res)) {
            DB::rollBack();
            $order->tag = empty($order->tag) ? '超卖' : $order->tag . ',超卖';
            $order->save();
            DB::commit();
            return false;
        }
        $data = ['status' => WmsOrder::WAIT_AUDIT, 'admin_user_id' => ADMIN_INFO['user_id'],];
        if ($order->tag) {
            $tag = str_replace($order->tag, '超卖', '');
            if (substr($tag, -1, 1) == ',') $tag = substr($tag, 0, -1);
            $data['tag'] = $tag;
        }
        $order->update($data);
        WmsOptionLog::add(WmsOptionLog::ORDER, $order->code, '提交', '提交', []);
        DB::commit();
        return true;
    }

    // 审核
    function audit($params)
    {
        $order = WmsOrder::find($params['id']);
        if (!$order) {
            $this->setErrorMsg(__('admin.wms.order.exception'));
            return false;
        }
        if ($order->status != WmsOrder::WAIT_AUDIT) {
            $this->setErrorMsg(__('admin.wms.order.status.error'));
            return false;
        }

        try {
            DB::beginTransaction();
            $p = [
                'status' => $params['status'],
                'remark' => $params['remark'] ?? '',
            ];
            $desc = [
                WmsOrder::PASS => '销售订单审核通过',
                WmsOrder::REJECT => '销售订单审核未通过',
            ];
            $order->update(['status' => $params['status'], 'admin_user_id' => ADMIN_INFO['user_id'],]);
            WmsOptionLog::add(WmsOptionLog::ORDER, $order->code, '审核', $desc[$params['status']], $p);

            // 审核通过
            if ($params['status'] == WmsOrder::PASS) {
                // 生成出库需求单
                $this->_addShippingRequest($order, $order->warehouse_code, []);
            }
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            throw $e;
            return false;
        }
    }

    // 取消
    function cancel($params)
    {
        $order = WmsOrder::find($params['id']);
        if (!$order) {
            $this->setErrorMsg(__('admin.wms.order.exception'));
            return false;
        }
        if ($order->status != WmsOrder::PASS || !in_array($order->deliver_status, [0, 1])) {
            $this->setErrorMsg(__('admin.wms.order.status.error'));
            return false;
        }

        try {
            DB::beginTransaction();
            // 更新状态
            if ($order->status != WmsOrder::CANCEL) {
                $order->update(['status' => WmsOrder::CANCEL, 'deliver_status' => WmsOrder::CANCELED, 'admin_user_id' => ADMIN_INFO['user_id'],]);
                WmsOptionLog::add(WmsOptionLog::ORDER, $order->code, '取消', '销售订单取消', []);
            }

            $err = [];
            $shippings = $order->shipping;
            foreach ($shippings as $shipping) {
                $this->err_msg = '';
                $logic = new AllocationTask();
                $res = $logic->orderCancel([
                    'id' => $shipping->id,
                    'third_no' => $shipping->third_no,
                    'transaction' => false,
                ]);
                if (!$res) {
                    $err[] = sprintf('出库需求单%s取消失败，原因：%s', $shipping->request_no, $logic->err_msg);
                }
            }
            DB::commit();
            // return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }


        if (!$err) return true;
        $this->setErrorMsg(implode('|', $err));
        return false;
    }


    static function sendOut($third_no)
    {
        $order = WmsOrder::where('third_no', $third_no)->whereIn('status', WmsOrder::$active_status)->first();
        if (!$order) return;

        $shipping = $order->shipping;
        $details = $order->details;
        $request_codes = $shipping->pluck('request_code')->toArray();
        $num = 0;

        // 订单明细
        foreach ($details as $detail) {
            $where = ['bar_code' => $detail->bar_code, 'alloction_status' => preAllocationDetail::DELIVERED, 'cancel_status' => 0,];
            if ($detail->uniq_code) $where['uniq_code'] = $detail->uniq_code;
            if ($detail->batch_no) $where['batch_no'] = $detail->batch_no;
            $sum = preAllocationDetail::where($where)->whereIn('request_code', $request_codes)->selectRaw('sum(pre_num) as num,sum(buy_price) as buy_price')->get();
            $actual_num = $sum[0]['num'];
            $buy_price =  bcdiv($sum[0]['buy_price'], $actual_num, 2);
            $update = ['sendout_num' => $actual_num];
            if (!($detail->cost_price > 0)) $update['cost_price'] = $buy_price;
            $detail->update($update);
        }

        // 订单发货状态
        $num = $order->details->sum('sendout_num');
        $status = '';
        if ($order->num > $num) $status = WmsOrder::PARTIAL_DELIVERY;
        if ($order->num <= $num) $status = WmsOrder::DELIVERED;
        if ($status) {
            $order->update(['deliver_status' => $status,]);
            $amount = $order->payment_amount;
            if ($order->num > $num) $amount = bcmul(bcdiv($amount, $order->num, 4), $num, 0);
            // 生成销售结算账单
            WmsOrderStatement::create([
                'code' => WmsOrderStatement::code(),
                'type' => WmsOrderStatement::TYPE_ORDER,
                'origin_code' => $order->code,
                'order_at' => $order->order_at,
                'third_no' => $order->third_no,
                'amount' => $order->total_amount,
                'settle_amount' => $amount,
                'shop_name' => $order->shop_name,
                'buyer_account' => $order->buyer_account,
                'tenant_id' => request()->header('tenant_id'),
                'create_user_id' => request()->header('user_id', 0),
                'warehouse_code' => $order->warehouse_code,
                'warehouse_name' => $order->warehouse_name,
            ]);
        }
        // 订单发货明细
        self::addItems($order);

        // 销售发货明细汇总
        self::summaryUpdate($order);
        // 寄卖结算单
        Consigment::addBillByOrder($order);
    }

    // 给异步队列调用
    function orderSendOut($params)
    {
        self::sendOut($params['third_no']);
    }

    // 销售发货明细汇总
    static function summaryUpdate($order)
    {
        $info = WmsOrder::where('id', $order->id)->with(['details', 'details.specBar','details.specBar.product', 'details.specBar.product.brand', 'details.specBar.product.category', ])->first()->toArray();
        $warehouse = WmsWarehouse::warehouseKeyBy();
        $supplier = Suppiler::supplierKeyBy();
        $product = WmsLogisticsProduct::where('product_code', $info['product_code'])->with(['company'])->first();
        $company_name = $product && $product->company ? $product->company->company_name : '';
        $company_code = $product && $product->company ? $product->company->company_code : '';
        foreach ($info['details'] as $detail) {
            $sku = $detail['spec_bar'];
            $product = $sku['product'] ?? [];
            $brand = $product['brand'] ?? [];
            $category = $product['category'] ?? [];
            $items = WmsOrderItem::where(['detail_id' => $detail['id']])->get();
            $arr = [];
            $uniq_codes = $items->pluck('uniq_code');
            if ($uniq_codes->count() == 0) continue;
            $invs = Inventory::whereIn('uniq_code', $uniq_codes->toArray())->select(['uniq_code', 'inv_type'])->get()->keyBy('uniq_code');
            foreach ($items as $item) {
                $inv_type = $invs[$item->uniq_code]->inv_type;
                $key = sprintf('%s_%s_%s_%s', $item->sup_id, $inv_type, $item->quality_level, $item->batch_no);
                $tmp = $arr[$key] ?? [];
                $tmp['cost_amount'] = ($tmp['cost_amount'] ?? 0) + $item->cost_price;
                $tmp['num'] = ($tmp['num'] ?? 0) + 1;
                $tmp['sup_id'] = $item->sup_id;
                $tmp['inventory_type'] = $inv_type == 0 ? 1 : 2;
                $tmp['quality_level'] = $item->quality_level;
                $tmp['batch_no']  = $item->batch_no;
                $tmp['uniq_codes'][] = $item->uniq_code;
                $arr[$key] = $tmp;
            }
            if (!$arr) continue;


            foreach ($arr as  $item) {

                $cost_amount = $item['cost_amount'];
                $amount = $detail['amount'];
                $discount_amount = $detail['discount_amount'];
                $payment_amount = $detail['payment_amount'];

                if ($detail['num'] != $item['num']) {
                    $payment_amount = bcmul(bcdiv($detail['payment_amount'], $detail['num'], 2), $item['num'], 2);
                    $amount = bcmul(bcdiv($detail['amount'], $detail['num'], 2), $item['num'], 2);
                    $discount_amount = bcmul(bcdiv($detail['discount_amount'], $detail['num'], 2), $item['num'], 2);
                }

                // 毛利 = 实际支付额－成本额
                $gross_profit = bcsub($payment_amount, $cost_amount, 2);
                // 毛利率= (实际支付额－成本额)/实际支付额×100%
                $gross_profit_rate = $payment_amount > 0 ? (round(($payment_amount - $cost_amount) / $payment_amount * 100)) : 0;
                $quality_level = $item['quality_level'];
                $quality_type = $quality_level == 'A' ? 1 : 2;
                $batch_no = $item['batch_no'];
                $uniq_code = '';
                if ($quality_type == 2) {
                    $uniq_code = $item['uniq_codes'][0] ?? '';
                }
                $where = [
                    'origin_code' => $detail['origin_code'],
                    'third_no' => $info['third_no'],
                    'bar_code' => $detail['bar_code'],
                    'tenant_id' => request()->header('tenant_id'),
                    'warehouse_code' => $detail['warehouse_code'],
                    'sup_id' => $item['sup_id'],
                    'inventory_type' => $item['inventory_type'],
                    'quality_type' => $quality_type,
                    'uniq_code' => $uniq_code,
                    'batch_no' => $batch_no,
                ];
                $find = WmsOrderDeliverStatement::where($where)->first();
                if ($find) continue;

                WmsOrderDeliverStatement::create(array_merge($where, [
                    'shop_code' => $info['shop_code'],
                    'shop_name' => $info['shop_name'],
                    'warehouse_name' => $warehouse[$detail['warehouse_code']]['warehouse_name'] ?? '',
                    'order_at' => $info['order_at'],
                    'payment_at' => $info['paysuccess_time'],
                    'shipped_at' => $order->updated_at,
                    'category_code' => $category['code'] ?? '',
                    'category_name' => $category['name'] ?? '',
                    'brand_code' => $brand['code'] ?? '',
                    'brand_name' => $brand['name'] ?? '',
                    'sku' => $sku['sku'] ?? '',
                    'product_sn' => $product['product_sn'] ?? '',
                    'name' => $product['name'] ?? '',
                    'spec_one' => $sku['spec_one'] ?? '',
                    'num' => $item['num'],
                    'retails_price' => $detail['retails_price'],
                    'price' => $detail['price'],
                    'amount' => $amount,
                    'discount_amount' => $discount_amount,
                    'payment_amount' => $payment_amount,
                    'cost_amount' => $cost_amount, //成本额
                    'gross_profit' => $gross_profit, //毛利 = 实际支付额 - 成本额
                    'gross_profit_rate' => $gross_profit_rate,
                    'freight' => 0, //运费
                    'product_type' => $product['type'] ?? 0,
                    'sup_name' => $supplier[$item['sup_id']]['name'] ?? '',
                    'quality_level' => $quality_level,
                    'company_code' => $company_code,
                    'company_name' => $company_name,
                    'deliver_no' => $info['deliver_no'],
                    'deliver_path' => $info['deliver_path'],
                    'remark' => $info['remark'],
                    'admin_user_id' => request()->header('tenant_id', 0),
                ]));
            }
        }
    }

    static function addItems($order)
    {
        // 找到订单的库存信息
        $request = ObOrder::where(['third_no' => $order->third_no, 'status' => 4, 'request_status' => 4])->orderBy('id', 'desc')->first();
        if (!$request) return;
        $uniq_codes = preAllocationDetail::where(['request_code' => $request->request_code, 'alloction_status' => 7])->pluck('uniq_code');
        if ($uniq_codes->count() == 0) return;
        $invs = Inventory::where(['warehouse_code' => $order->warehouse_code, 'sale_status' => 4])->whereIn('uniq_code', $uniq_codes->toArray())->get();
        $details = $order->details;
        $arr = [];
        foreach ($details as $detail) {
            $tmp = [];
            // foreach ($invs as $inv) {
            //     if ($detail->num == count($tmp)) break;
            //     $check_sup = $detail->sup_id ? $inv->sup_id == $detail->sup_id : true;
            //     $check_price = $detail->cost_price > 0 ? intval($inv->buy_price) == intval($detail->cost_price) : true;
            //     if ($inv->bar_code == $detail->bar_code && $check_sup && $inv->quality_level == $detail->quality_level && $check_price) {
            //         if (!in_array($inv->uniq_code, $arr)) $tmp[] = $inv;
            //     }
            // }

            // if (!$tmp) {
            //     foreach ($invs as $inv) {
            //         if ($detail->num == count($tmp)) break;
            //         $check_sup = $detail->sup_id ? $inv->sup_id == $detail->sup_id : true;
            //         if ($inv->bar_code == $detail->bar_code && $check_sup && $inv->quality_level == $detail->quality_level) {
            //             if (!in_array($inv->uniq_code, $arr)) $tmp[] = $inv;
            //         }
            //     }
            // }

            foreach ($invs as $inv) {
                if ($detail->num == count($tmp)) break;
                $check_sup = $detail->sup_id ? $inv->sup_id == $detail->sup_id : true;
                if ($inv->bar_code == $detail->bar_code && $check_sup && $inv->quality_level == $detail->quality_level) {
                    if (!in_array($inv->uniq_code, $arr)) $tmp[] = $inv;
                }
            }


            if ($tmp) {
                $arr = array_unique(array_merge($arr, $tmp));
                foreach ($tmp as $inv) {
                    if ($inv->bar_code != $detail->bar_code) continue;
                    WmsOrderItem::create([
                        'sup_id' => $inv->sup_id, 'quality_level' => $detail->quality_level, 'cost_price' => $detail->cost_price,  'detail_id' => $detail->id, 'origin_code' => $detail->origin_code, 'warehouse_code' => $detail->warehouse_code,
                        'tenant_id' => $detail->tenant_id, 'admin_user_id' => $detail->admin_user_id,
                        'bar_code' => $inv->bar_code, 'uniq_code' => $inv->uniq_code, 'batch_no' => $inv->lot_num,
                    ]);
                }
            }
        }
    }

    // 暂停
    function pause($params)
    {

        $order = WmsOrder::find($params['id']);
        if (!$order) {
            $this->setErrorMsg(__('admin.wms.order.exception'));
            return false;
        }
        if (!in_array($order->status, [WmsOrder::WAIT_AUDIT, WmsOrder::PASS])) {
            $this->setErrorMsg(__('admin.wms.order.status.error'));
            return false;
        }
        $old_status = $order->status;
        $order->update([
            'status' => WmsOrder::PAUSE,
            'suspender_id' => ADMIN_INFO['user_id'],
            'paused_at' => date('Y-m-d H:i:s'),
            'paused_reason' => $params['reason'] ?? "",
            'old_status' => $old_status
        ]);
        WmsOptionLog::add(WmsOptionLog::ORDER, $order->code, '暂停', '销售订单暂停', [
            'old_status' => $old_status,
            'paused_reason' => $params['reason'] ?? ""
        ]);
        if ($old_status == WmsOrder::PASS) {
            // 暂停出库申请单
            $shippings = $order->shipping;
            ObOrder::pause(implode(',', $shippings->pluck('id')->toArray()), $params['reason']);
        }
        return true;
    }

    // 恢复
    function recovery($params)
    {
        $order = WmsOrder::find($params['id']);
        if (!$order) {
            $this->setErrorMsg(__('admin.wms.order.exception'));
            return false;
        }
        if ($order->status != WmsOrder::PAUSE) {
            $this->setErrorMsg(__('admin.wms.order.recovery.no_need'));
            return false;
        }
        $order->update([
            'status' => $order->old_status,
            'recovery_operator_id' => ADMIN_INFO['user_id'],
            'recovery_at' => date('Y-m-d H:i:s'),
            'recovery_reason' => $params['reason'] ?? '',
        ]);
        WmsOptionLog::add(WmsOptionLog::ORDER, $order->code, '恢复', '销售订单暂停恢复', []);
        if ($order->old_status == WmsOrder::PASS) {
            // 恢复出库申请单
            $shippings = $order->shipping;
            (new ObOrder())->recovery(implode(',', $shippings->pluck('id')->toArray()), $params['reason'] ?? '');
        }
        return true;
    }

    // 指定配货
    function assign($params)
    {
        $order = WmsOrder::find($params['id']);
        if (!$order) {
            $this->setErrorMsg(__('admin.wms.order.exception'));
            return false;
        }
        if ($order->status != WmsOrder::WAIT_AUDIT) {
            $this->setErrorMsg(__('admin.wms.order.status.error'));
            return false;
        }

        try {
            DB::beginTransaction();
            $shippings = $order->shipping;
            foreach ($shippings as $shipping) {
                // 取消已经生成的出库需求单
                $res = (new AllocationTask())->orderCancel([
                    'id' => $shipping->id,
                    'third_no' => $shipping->third_no,
                    'transaction' => false,
                ]);
                if (!$res) {
                    $this->setErrorMsg(sprintf(__('tips.request_cancel_fail'), $shipping->request_no, $this->err_msg));
                    return false;
                }
            }

            // 根据新仓库生成新的出库需求单
            $this->_addShippingRequest($order, $params['warehouse_code'], []);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
        return true;
    }

    // 根据销售订单生成出库需求单
    private function _addShippingRequest($order, $warehouse_code, $lock_ids)
    {
        // 没有选择仓库，生成一个出库需求单
        // 所有明细都选择了一个仓库，生成一个出库需求单
        // 不同明细选择了不同仓库，几个仓库就有几个出库需求单
        $warehouse_name = '';
        if ($warehouse_code) {
            $warehouse_name = Warehouse::getWarehouseName($warehouse_code, ADMIN_INFO['tenant_id']);
        }
        $details = $order->details;
        $ob_products = [];
        //获取来源渠道id
        $shop = WmsShop::where('code', $order->shop_code)->where('status', 1)->first();
        $order_channel = $shop->id ?? 0;
        $ob_data[$warehouse_code] = [
            'type' => 1,
            'third_no' => $order->third_no,
            'source_code' => $order->code,
            'payable_num' => $order->num,
            'warehouse_name' => $warehouse_name,
            'warehouse_code' => $warehouse_code,
            'paysuccess_time' => $order->order_at,
            'delivery_deadline' => $order->estimate_sendout_time ? strtotime($order->estimate_sendout_time) : 0,
            'deliver_type' => $order->product_code,
            'deliver_no' => $order->deliver_no,
            'order_platform' => $order->order_platform,
            'order_channel' => $order_channel,
            'shop_name' => $shop ? $shop->name : '',
        ];

        foreach ($details as $item) {
            $warehouse_code = $item->warehouse_code;
            if (empty($ob_data[$warehouse_code] ?? [])) {
                $ob_data[$warehouse_code] = [
                    'type' => 1,
                    'third_no' => $order->third_no,
                    'source_code' => $order->code,
                    'payable_num' => $order->num,
                    'warehouse_name' => $warehouse_name,
                    'warehouse_code' => $warehouse_code,
                    'paysuccess_time' => $order->paysuccess_time,
                    'delivery_deadline' => $order->deadline,
                    'deliver_type' => $order->product_code,
                    'deliver_no' => $order->deliver_no,
                    'order_platform' => $order->order_platform,
                    'order_channel' => $order_channel,
                ];
            }

            //出库单详情
            $ob_products[$warehouse_code][] = [
                'bar_code' => $item->bar_code,
                'quality_level' => $item->quality_level,
                'quality_type' => $item->getRawOriginal('quality_type'),
                'batch_no' => $item->batch_no,
                'uniq_code' => $item->uniq_code,
                'sup_id' => $item->sup_id,
                'payable_num' => $item->num,
                'buy_price' => $item->cost_price,
                'lock_ids' => $item->lock_ids ? implode(',', $item->lock_ids) : '',
            ];
        }
        foreach ($ob_data as $warehouse_code => $data) {
            $res = ObOrder::add($data, $ob_products[$warehouse_code], false);
            if (($res[0] ?? false) == false) {
                throw new Exception(__('tips.option_fail'));
            }
        }
    }

    // 退款登记商品明细
    function afterSaleDetail($params)
    {
        $detail_ids = explode(',', $params['detail_id']);
        $order = WmsOrder::with(['details', 'details.specBar'])->where('id', $params['id'])->first()->toArray();

        $data = [];
        foreach ($order['details'] as &$item) {
            if (!in_array($item['id'], $detail_ids)) continue;
            self::sepBarFormat($item);
            // 有仓库编码
            //已发货的，可以修改退回商品数量，未发货的不能修改，一直都是0
            $item['return_num'] = $item['sendout_num'];
            $item['refund_amount'] = 0;
            $data[] = $item;
        }
        return $data;
    }

    // 退款登记
    function afterSale($params)
    {
        // 1个商品明细对应一个售后单
        $order = WmsOrder::where('status', WmsOrder::PASS)->find($params['id']);
        if (!$order) {
            $this->setErrorMsg(__('wms.order.status.error'));
            return false;
        }
        $item = $params['details'][0];
        $detail = WmsOrderDetail::where('id', $item['id'])->where('status', 1)->first();
        if (count($detail->activeAfterSale()) > 1) {
            $this->setErrorMsg(__('admin.wms.order.repeat'));
            return false;
        }
        if ($item['apply_num'] > ($detail['num'] - $detail['apply_num'])) {
            $this->setErrorMsg(__('admin.wms.order.apply_num.exceed'));
            return false;
        }
        if ($item['return_num'] > $detail['num']) {
            $this->setErrorMsg(__('admin.wms.order.return_num.exceed'));
            return false;
        }

        try {
            DB::beginTransaction();
            $detail->update(['apply_num' => $detail->apply_num + $item['apply_num'],]);

            $return_num = $item['return_num'] ?? 0;
            $type = WmsAfterSaleOrder::TYPE_REFUND;
            // 售后单类型：发货申请单已经发货的退货退款，其他状态仅退款
            // if ($detail->sendout_num) {
            //     $return_num = $detail->sendout_num;
            //     $type = WmsAfterSaleOrder::TYPE_RETURN;
            // }
            if ($return_num) {
                $type = WmsAfterSaleOrder::TYPE_RETURN;
            }
            $return_status = $detail->sendout_num ? 1 : 0;

            $after_sale = WmsAfterSaleOrder::create([
                'code' => WmsAfterSaleOrder::code(),
                'source_type' => 1,
                'type' => $type, //1-仅退款 2-退货退款
                'return_status' => $return_status, //0-无需退货 1-未收货 2-已收货
                'origin_code' => $order->code,
                'apply_no' => $params['apply_no'] ?? '',
                'refund_reason' => $params['refund_reason'],
                'deadline' => $params['deadline'] ? $params['deadline'] : null,
                'order_user' => $params['order_user'] ?? 0,
                'order_at' => $params['order_at'],
                'tenant_id' => ADMIN_INFO['tenant_id'],
                'created_user' => ADMIN_INFO['user_id'],
                'return_num' => $item['return_num'], //退货总数
                'refund_amount' => $detail->payment_amount,
                'apply_num' => $item['apply_num'], //申请数量
            ]);

            WmsAfterSaleOrderDetail::create([
                'origin_code' => $after_sale->code,
                'bar_code' => $detail->bar_code,
                'num' => $detail->num, //申请数量
                'return_num' => $return_num, //退货退款数量
                'refund_num' => max($detail->num - $return_num, 0), //仅退款数量
                'retails_price' => $detail->retails_price, //零售价
                'price' => $detail->price, //成交价
                'amount' => $detail->amount, //实际成交额
                'refund_amount' => $detail->payment_amount, //退款额
                'remark' => $item['remark'] ?? '',
                'tenant_id' => ADMIN_INFO['tenant_id'],
                'admin_user_id' => ADMIN_INFO['user_id'],
                'order_detail_id' => $detail->id
            ]);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            throw $e;
            return false;
        }
    }

    // 销售结算单确认
    function settle($params)
    {
        $statements = WmsOrderStatement::where('status', 0)->whereIn('id', $params['ids'])->get();
        if ($statements->count() == 0) return true;

        foreach ($statements as $statement) {
            $statement->update([
                'settled_amount' => $statement->settle_amount,
                'settled_user_id' => ADMIN_INFO['user_id'],
                'admin_user_id' => ADMIN_INFO['user_id'],
                'settled_time' => date('Y-m-d H:i:s'),
                'status' => 1,
            ]);
        }

        return true;
    }

    // 销售结算单查询
    function statementSearch($params, $export = false)
    {
        $model = new WmsOrderStatement();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            $permission = ADMIN_INFO['data_permission'];
            $shop_name = $permission['shop_name'] ?? [];
            if ($shop_name) {
                $model = $model->whereIn('shop_name', $shop_name);
            }
            return $model->with(['createUser', 'adminUser', 'settledUser'])->orderBy('id', 'desc');
        });
        return $list;
    }

    // 销售发货明细汇总查询
    function summarySearch($params, $export = false)
    {
        $model = new WmsOrderDeliverStatement();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            if ($params['shop_code'] ?? '') $model = $model->where('shop_code', $params['shop_code']);
            if ($params['sku'] ?? '') $model = $model->where('sku', $params['sku']);
            if ($params['name'] ?? '') $model = $model->where('name', 'like', '%' . $params['name'] . '%');
            if ($params['shipped_date_start'] ?? '') $model = $model->where('shipped_at', '>=', $params['shipped_date_start']);
            if ($params['shipped_date_end'] ?? '') $model = $model->where('shipped_at', '<', $params['shipped_date_end']);
            $model = $model->orderBy('id', 'desc');
            return $model;
        });
        return $list;
    }

    //修改旗帜
    public function editFlag($data)
    {
        if (!is_array($data['ids'])) $data['ids'] = explode(',', $data['ids']);
        return WmsOrder::whereIn('id', $data['ids'])->update(['flag' => $data['flag']]);
    }

    //修改留言
    public function editMessage($data)
    {
        if (!is_array($data['ids'])) $data['ids'] = explode(',', $data['ids']);
        $update = [];
        if (!empty($data['seller_message'])) $update['seller_message'] = $data['seller_message'];
        if (!empty($data['buyer_message'])) $update['buyer_message'] = $data['buyer_message'];
        if (!empty($data['remark'])) $update['remark'] = $data['remark'];
        if (!$update) return;
        return WmsOrder::whereIn('id', $data['ids'])->update($update);
    }
}
