<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use  App\Models\Admin\V2\ArrivalRegist;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class TransferOrder extends wmsBaseModel
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'wms_transfer_order'; //调拨申请单

    protected $guarded = [];

    // protected $map = [
    //     'type' => [1 => '销售出库', 2 => '调拨出库申请单', 3 => '其他出库申请单'],
    //     'doc_status' => [0 => '暂存', 1 => '审核中', 2 => '已审核', 3 => '已暂停', 4 => '已确认', 5 => '已取消', 6 => '已驳回'],
    //     'send_status' => [1 => '待发货', 2 => '发货中', 3 => '部分发货', 4 => '已发货'],
    //     'recv_status' => [1 => '待收货', 2 => '部分收货', 3 => '已收货'],
    // ];

    protected $map;

    protected $appends = ['type_txt', 'doc_status_txt', 'recv_status_txt', 'send_status_txt', 'users_txt', 'warehouse_txt', 'diff_num', 'log_prod_name'];

    protected $warehouseIns = null;

    public function __construct($params = [])
    {
        parent::__construct($params);
        $this->map = [
            'type' => [1 => __('status.sale_ob'), 2 => __('status.transfer_ob'), 3 => __('status.other_ob')],
            'doc_status' => [0 => __('status.stash'), 1 => __('status.examining'), 2 => __('status.audited'), 3 => __('status.pause'), 4 => __('status.confirmed'), 5 => __('status.canceled'), 6 => __('status.rejected')],
            'send_status' => [1 => __('status.wait_send'), 2 => __('status.shipping'), 3 => __('status.send_part'), 4 => __('status.shipped')],
            'recv_status' => [1 => __('status.wait_recv'), 2 => __('status.recv_part'), 3 => __('status.received')],

        ];
    }

    public function details()
    {
        return $this->hasMany(TransferDetails::class, 'tr_code', 'tr_code');
    }

    public function obOrder()
    {
        return $this->belongsTo(ObOrder::class, 'third_no', 'tr_code');
    }

    public function ibOrder()
    {
        return $this->belongsTo(IbOrder::class, 'third_no', 'oib_code');
    }
    public function getDiffNumAttribute($key)
    {
        return $this->send_num - $this->recv_num;
    }

    public function  getSendInfoAttribute($key)
    {
        return  ShippingOrders::sendInfo($this->tr_code);
    }

    public function  getRecvInfoAttribute($key)
    {
        return RecvOrder::recvInfo($this->tr_code);
    }

    public function  getOptionInfoAttribute($key)
    {
        $list = WmsOptionLog::list(WmsOptionLog::DBSQD, $this->tr_code)->toArray();
        return $list;
    }


    public function _formatOne($data)
    {
        $data = $data->append(['option_info', 'recv_info', 'send_info'])->toArray();
        self::productFormat($data);
        return $data;
    }
    public function searchUser()
    {
        return [
            'users_txt.approve_user' => 'approve_id',
            'users_txt.created_user' => 'created_user',
            'users_txt.updated_user' => 'updated_user',
            'users_txt.paysuccess_user' => 'paysuccess_user',
            'users_txt.recovery_user' => 'recovery_operator_id',
            'users_txt.suspender_user' => 'suspender_id',
        ];
    }
    public function getUsersTxtAttribute($key)
    {
        // $tenant_id = request()->header('tenant_id');
        $res['approve_user'] = $this->_getRedisMap('user_map', $this->approve_id);
        $res['created_user'] = $this->_getRedisMap('user_map', $this->created_user);
        $res['updated_user'] = $this->_getRedisMap('user_map', $this->updated_user);
        $res['paysuccess_user'] = $this->_getRedisMap('user_map', $this->paysuccess_user);
        $res['recovery_user'] = $this->_getRedisMap('user_map', $this->recovery_operator_id);
        $res['suspender_user'] = $this->_getRedisMap('user_map', $this->suspender_id);
        return $res;
    }

    public function getWarehouseTxtAttribute($key)
    {
        // $tenant_id = request()->header('tenant_id');
        $res['out_warehouse_name'] =  $this->_getRedisMap('warehouse_map', $this->out_warehouse_code);
        $res['in_warehouse_name'] = $this->_getRedisMap('warehouse_map', $this->in_warehouse_code);
        return $res;
    }


    public function getLogProdNameAttribute($key)
    {

        if (!$this->log_prod_code) return '';
        return $this->getLogProdName($this->log_prod_code);
    }

    public function withInfoSearch($select)
    {
        return $this::with('details')->select($select);
    }

    public function optionLog($codes, $option, $desc, $detail = [])
    {
        foreach ($codes as $oib_code) {
            WmsOptionLog::add(WmsOptionLog::DBSQD, $oib_code, $option, $desc, $detail);
        }
    }
    //新增
    public function add($data)
    {
        $data['type'] = 2;
        // if($data['out_warehouse_code'] == $data['in_warehouse_code']) return [false,'调入仓和调出仓不能相同'];
        // if($this->getWarehouseName($data['out_warehouse_code']) == '') return [false,'调出仓不存在'];
        // if($this->getWarehouseName($data['in_warehouse_code']) == '') return [false,'调入仓不存在'];
        //设置默认值
        $time = date('Y-m-d H:i:s');
        $user_id = request()->header('user_id');
        if (empty($data['delivery_deadline'])) $data['delivery_deadline'] =  date('Y-m-d H:i:s', strtotime($data['paysuccess_time'] . ' +1 day'));
        $tr_code = $this->getErpCode('DBSQD');
        $data['tr_code'] = $tr_code;
        $data['created_user'] = $user_id;
        $data['created_at'] = $time;
        $data['send_status'] = 1;
        $data['recv_status'] = 1;
        $data['source_code'] = '手工创建';

        $skus = $data['skus'];
        unset($data['skus']);
        $total = 0;
        $repeat = [];
        foreach ($skus as $k => &$sku) {
            if ($sku['num'] == 0) {
                unset($skus[$k]);
                continue;
            }
            $field  = [
                'bar_code' => '',
                'sup_id' => '',
                'buy_price' => '',
                'quality_level' => '',
                'batch_no' => '',
                'num' => '',
                'uniq_code' => '',
                'remark' => ''
            ];
            $sku = array_intersect_key($sku, $field);
            $rep = $sku['bar_code'].','.$sku['sup_id'].','.$sku['buy_price'].','.$sku['quality_level'].','.$sku['batch_no'].','.$sku['uniq_code'] ;
            if(in_array($rep,$repeat)){
                unset($skus[$k]);
                continue;
            }
            $repeat[] = $rep;
            if ($sku['remark'] == null) $sku['remark'] = '';
            if (empty($sku['sku'])) $sku['sku'] = ProductSpecAndBar::getSkuByBar($sku['bar_code']);
            $total += $sku['num'];
            if ($sku['quality_level'] == 'A') {
                $sku['uniq_code'] = $sku['uniq_code'] ?? '';
                $sku['quality_type'] =  1;
            } else {
                if ($sku['num'] != 1) return  [false, __('response.inv_lack')];
                $sku['quality_type'] =  2;
            }
            if (!isset($sku['uniq_code'])) return [false, __('response.flaw_must_uniqcode')];
            $sku['tr_code'] = $tr_code;
            $sku['created_at'] = date('Y-m-d H:i:s');
            $sku['tenant_id'] = request()->header('tenant_id');
        }
        // $total = array_sum(array_column($skus,'num'));
        $data['total'] = $total;
        $this->startTransaction();
        $row['add_tr_order'] = $this::insert($data);
        $row['add_tr_detail'] = $this->details()->getModel()->insert($skus);
        $this->optionLog([$tr_code], '新增', '调拨申请单新增', [$data]);
        return $this->endTransaction($row);
    }

    //删除
    public function del($data)
    {
        $ids = explode(',', $data['ids']);
        //暂存中的可删除
        $items = $this->whereIn('id', $ids)->where('doc_status', 0);
        if ($items->doesntExist()) return [false,  __('status.doc_not_delete')];
        $not_del = array_values(array_diff($ids, $items->get()->modelKeys()));
        $tr_code = $items->get()->pluck('tr_code')->toArray();
        $this->startTransaction();
        $row['del_detail'] = $this->details()->getModel()->whereIn('tr_code', $tr_code)->delete();
        $row['del'] = $items->delete();
        list($res, $msg) = $this->endTransaction($row);
        if (!empty($not_del)) $msg = implode(',', $not_del) . '状态不允许删除,其他的删除成功';
        $this->optionLog($tr_code, '删除', '调拨申请单删除', [$data]);
        return [$res, $msg];
    }

    //更新调拨申请单详情
    public  function updateDetails($id, $skus)
    {
        $item = $this::find($id);
        if (!$item) return [false, __('response.doc_not_exists')];
        $tr_code = $item->tr_code;
        $create_data = [];
        $up_row = [];
        $this->startTransaction();
        $delete_ids = $item->details()->pluck('id')->toArray();
        $repeat = [];
        foreach ($skus as $k => $sku) {
            if ($sku['num'] == 0) {
                unset($skus[$k]);
                continue;
            }
            $field  = [
                'id' => '',
                'bar_code' => '',
                'sup_id' => '',
                'buy_price' => '',
                'quality_level' => '',
                'batch_no' => '',
                'num' => '',
                'uniq_code' => '',
                'remark' => '',
                'sku' => '',
            ];
            $sku = array_intersect_key($sku, $field);
            $rep = $sku['bar_code'].','.$sku['sup_id'].','.$sku['buy_price'].','.$sku['quality_level'].','.$sku['batch_no'].','.$sku['uniq_code'] ;
            if(in_array($rep,$repeat)){
                unset($skus[$k]);
                continue;
            }
            $repeat[] = $rep;
            $sku['admin_user_id'] = request()->header('user_id');
            if (empty($sku['sku'])) $sku['sku'] = ProductSpecAndBar::getSkuByBar($sku['bar_code']);
            if (empty($sku['id'])) {
                //新增
                if ($sku['remark'] == null) $sku['remark'] = '';
                $sku['quality_type'] = 2;
                if ($sku['quality_level'] == 'A') {
                    $sku['uniq_code'] = $sku['uniq_code'] ?? '';
                    $sku['quality_type'] = 1;
                }
                if (!isset($sku['uniq_code'])) return [false, __('response.flaw_must_uniqcode')];
                $sku['tr_code'] = $tr_code;
                $sku['created_at'] = date('Y-m-d H:i:s');
                $sku['tenant_id'] = request()->header('tenant_id');
                $sku['updated_at'] = date('Y-m-d H:i:s');
                $create_data[] = $sku;
            } else {
                //修改
                $index = array_search($sku['id'], $delete_ids);
                if ($index !== false) unset($delete_ids[$index]);
                $sku_item = $item->details()->find($sku['id']);
                if ($sku_item) $up_row[$k] = $sku_item->update($sku);
            }
        }
        if (!empty($delete_ids)) $item->details()->whereIn('id', $delete_ids)->delete();
        //新增
        if ($create_data) $up_row['cre_row'] = $item->details()->getModel()->insert($create_data);
        $res = $this->endTransaction($up_row);
        $this->optionLog([$tr_code], '修改', '调拨申请单修改', [$up_row]);
        if ($res[0]) {
            //返回更新数量
            $num = $item->details->sum('num');
            return [true, $num];
        }
        return $res;
    }

    //获取单条记录
    public function getItem($id)
    {
        return $this::find($id);
    }

    //锁定库存
    public function lock($items)
    {
        $fail = [];
        $success = [];
        foreach ($items as $item) {
            $inv_lock = [];
            $products = $item['details'];
            $warehouse_code = $item['out_warehouse_code'];
            $flag = true;
            $flush_bar_codes = [];
            foreach ($products as $pro) {
                $count = $pro['num'];
                //检查库存
                $ids = Inventory::checkInv($warehouse_code, $count, $pro);
                if (count($ids) != $count) {
                    $flag = false;
                    $fail[$item['tr_code']][] = empty($pro['product']['sku']) ? '' : $pro['product']['sku'];
                    $flush_bar_codes[$pro['sup_id']][] = $pro['product']['bar_code'];
                    continue;
                }
                $inv_lock[$pro['id']] = $ids;
            }
            //
            if ($flag) {
                //锁定库存
                foreach ($inv_lock as $id => $ids) {
                    $row = $this->details()->getModel()->where('id', $id)->update(['lock_ids' => implode(',', $ids)]);
                    if ($row) $row = Inventory::lockInvById($ids, 2, $item['tr_code']);
                }
                if ($row) $success[] = $item['id'];
                else $fail[] = $item['id'];
            } else {
                //失败时刷新库存;
                $inv_update_redis = [
                    'params' => [
                        'tenant_id' => request()->header('tenant_id'),
                        'user_id' => request()->header('user_id', 0),
                        'type'=>5,
                        'sup_data' => [$warehouse_code, $flush_bar_codes],
                        'total_data' => [$warehouse_code, array_merge(...array_values($flush_bar_codes))],
                    ],
                    'class' => 'App\Models\Admin\V2\Inventory',
                    'method' => 'invUpdate',
                    'time' => date('Y-m-d H:i:s'),
                ];
               self::query2SyncAdd($inv_update_redis);
            }
        }
        return [
            'fail' => $fail,
            'success' => $success,
        ];
    }

    //释放库存
    public  function release($tr_code)
    {
        $items = $this->details()->getModel()->whereIn('tr_code', $tr_code);
        // $lock_ids = implode(',', $items->pluck('lock_ids')->toArray());
        $row = Inventory::releaseInvByCode($tr_code);
        if ($row) return $items->update(['lock_ids' => '']);
    }

    //提交 锁定库存
    public function submit($data)
    {
        $ids = explode(',', $data['ids']);
        $items = $this::with(['details:id,tr_code,num,bar_code,batch_no,quality_level,sup_id,buy_price,uniq_code'])->whereIn('id', $ids)->where('doc_status', 0)->get()->toArray();
        if (empty($items)) return [false, __('status.doc_not_submit')];
        self::startTransaction();
        //检查库存并锁定
        $inv_res = $this->lock($items);
        //成功提交的数据
        $item_success = $this::whereIn('id', $inv_res['success']);
        $codes = $item_success->pluck('tr_code')->toArray();

        $update = [
            'doc_status' => 1,
            'updated_user' => request()->header('user_id'),
        ];
        $row = $item_success->update($update);
        if ($row) {
            $this->optionLog($codes, '调拨申请单提交', '调拨申请单提交', ['inv_res' => $inv_res, 'ids' => $ids]);

            if (!empty($inv_res['fail'])) {
                //存在失败的数据
                DB::commit();
                return [true, $inv_res];
            }
            DB::commit();
            return [true, ''];
        }
        DB::rollBack();
        $errmsg = '';
        foreach ($inv_res['fail'] as $code => $sku) {
            $errmsg .= $code . ': ' . implode(',', $sku) . ' 可售库存不足';
        };
        return [false, $errmsg];
    }

    //撤回 释放库存
    public function withdraw($data)
    {
        $ids = explode(',', $data['ids']);
        $items = $this->whereIn('id', $ids)->where('doc_status', 1);
        if ($items->doesntExist()) return [false, __('status.doc_not_withdraw')];
        $codes = $items->pluck('tr_code')->toArray();
        $this->startTransaction();
        $row = $this->release($codes);
        if (!$row) return [false, '释放库存失败'];
        $update = [
            'doc_status' => 0,
            'updated_user' => request()->header('user_id'),
        ];

        $row = $this->whereIn('id', $ids)->where('doc_status', 1)->update($update);
        $this->optionLog($codes, '调拨申请单撤回', '调拨申请单撤回', ['update' => $update, 'ids' => $ids, 'row' => $row]);
        return $this->endTransaction([$row]);
    }

    //审核
    public function approve($data)
    {
        $ids = explode(',', $data['ids']);
        $items = $this->whereIn('id', $ids)->where('doc_status', 1);
        $approve_ids = $items->get()->modelKeys();
        if ($items->doesntExist()) return [false, __('status.doc_not_approve')];
        $codes = $items->pluck('tr_code')->toArray();

        $pass = $data['pass'];
        if ($pass == 1) $update['doc_status'] = 2;
        if ($pass == 0) $update['doc_status'] = 6;
        if (!empty($data['approve_reason'])) $update['approve_reason'] = $data['approve_reason'];
        $user_id = request()->header('user_id');
        $con = [
            'approve_id' => $user_id,
            'updated_user' => $user_id,
        ];
        $this->startTransaction();
        $row['update'] = $items->update(array_merge($update, $con));
        if ($row['update'] && $pass == 1) {
            //审核通过 ,生成出库单和入库单
            $res =  $this->addRequestOrder($approve_ids);
            $this->endTransaction($res);
            $this->optionLog($codes, '调拨申请单审核', '调拨申请单审核通过', ['update' => $update, 'ids' => $ids]);

            return [true, $res];
        }
        $this->endTransaction($row);
        $this->optionLog($codes, '调拨申请单审核', '调拨申请单审核驳回', ['update' => $update, 'ids' => $ids]);

        return [$row, __('base.fail')];
    }


    //生成出库需求单 和 入库需求单
    public function addRequestOrder($id)
    {
        $success_count = 0;
        $items = $this::with('details')->find($id)->toArray();
        foreach ($items as $item) {
            $ob_products = [];
            $ib_products = [];
            $type = 2;
            //出库单
            $ob_data = [
                'type' => $type,
                'third_no' => $item['tr_code'],
                // 'source_code' => $item['tr_code'],
                'payable_num' => $item['total'],
                'warehouse_name' => $item['warehouse_txt']['out_warehouse_name'],
                'warehouse_code' => $item['out_warehouse_code'],
                'paysuccess_time' => $item['paysuccess_time'],
                'delivery_deadline' => $item['delivery_deadline'],
                'deliver_type' => $item['log_prod_code'],
                'deliver_no' => $item['deliver_no'],
                'order_platform' => 12,
                'order_channel' => '',
            ];
            //入库单
            $ib_data = [
                'ib_type' => $type,
                'third_no' => $item['tr_code'],
                // 'source_code' => $item['tr_code'],
                're_total' => $item['total'],
                'warehouse_code' => $item['in_warehouse_code'],
                'paysuccess_time' => $item['paysuccess_time'],
            ];
            $sup_id = [];
            $repeat =[];
            foreach ($item['details'] as $pro) {
                //出库单详情
                $ob_products[] = [
                    'bar_code' => $pro['bar_code'],
                    'quality_level' => $pro['quality_level'],
                    'quality_type' => $pro['quality_level'] == 'A' ? 1 : 2,
                    'batch_no' => $pro['batch_no'],
                    'uniq_code' => $pro['uniq_code'],
                    'sup_id' => $pro['sup_id'],
                    'payable_num' => $pro['num'],
                    'buy_price' => $pro['buy_price'],
                    'lock_ids' => $pro['lock_ids'],
                ];
                //入库单详情
                if (!in_array($pro['sup_id'], $sup_id)) $sup_id[] = $pro['sup_id'];
                $rep  =   $pro['bar_code'].','. $pro['quality_level'].','. $pro['sup_id'].','. $pro['buy_price'];
                if(in_array($rep,$repeat)){
                    $ib_products[$rep]['re_total'] += $pro['num'];
                }else{
                    $repeat[]= $rep;
                    $ib_products[$rep] = [
                        'bar_code' => $pro['bar_code'],
                        'quality_level' => $pro['quality_level'],
                        'quality_type' => $pro['quality_level'] == 'A' ? 1 : 2,
                        'sup_id' => $pro['sup_id'],
                        're_total' => $pro['num'],
                        'buy_price' => $pro['buy_price'],
                    ];
                }
            }
            if (count($sup_id) == 1) $ib_data['sup_id'] = $sup_id[0];
            $row['ob_add'] = ObOrder::add($ob_data, $ob_products)[0];
            $row['ib_add'] = IbOrder::add($ib_data, $ib_products)[0];
            if ($row['ob_add'] && $row['ib_add']) $success_count += 1;
        }
        return [
            'total' => count($id),
            'success_count' => $success_count,
        ];
    }

    //暂停 不释放库存,只修改状态 ,已审核待发货可以暂停
    public function pause($data)
    {
        $ids = explode(',', $data['ids']);
        $paused_reason = $data['paused_reason'];
        $item = $this::whereIn('id', $ids)->where('doc_status', 2)->where('send_status', 1);
        $fail_ids = array_values(array_diff($ids, $item->get()->modelKeys()));

        if ($item->doesntExist()) return [false, __('status.doc_not_pause')];
        $codes = $item->pluck('tr_code')->toArray();

        $user_id = request()->header('user_id');
        $time = date('Y-m-d H:i:s');
        $update = [
            'doc_status' => 3,
            'paused_reason' => $paused_reason,
            'suspender_id' => $user_id,
            'paused_at' => $time,
            'updated_user' => $user_id,
        ];
        $this->startTransaction();
        //修改出库单状态为暂停
        $obModel = $this->obOrder()->getModel();
        $ids = $obModel->whereIn('third_no', $item->pluck('tr_code'))->get()->modelKeys();
        $row = $item->update($update);
        if (!$row) return [false, __('base.fail')];
        $row = $obModel->pause($ids, '上游暂停.原因:' . $paused_reason)[0];
        if (!$row) return [false, '出库单暂停失败'];
        $res = $this->endTransaction([$row]);
        $this->optionLog($codes, '调拨申请单暂停', '调拨申请单暂停', ['update' => $update, 'ids' => $ids, 'fail' => $fail_ids]);

        if (!empty($fail_ids)) return [true, ['fail' => $fail_ids]];
        return $res;
    }

    //恢复
    public function recovery($data)
    {
        $ids = explode(',', $data['ids']);
        $item = $this::whereIn('id', $ids)->where('doc_status', 3)->where('send_status', 1);
        $fail_ids = array_values(array_diff($ids, $item->get()->modelKeys()));

        if ($item->doesntExist()) return [false, __('status.doc_not_restore')];
        $codes = $item->pluck('tr_code')->toArray();

        $user_id = request()->header('user_id');
        $time = date('Y-m-d H:i:s');
        $update = [
            'doc_status' => 2,
            'recovery_operator_id' => $user_id,
            'recovery_at' => $time,
            'updated_user' => $user_id,
        ];
        $reason = '上游恢复';
        if (!empty($data['recovery_reason'])) {
            $reason .= '.原因:' . $data['recovery_reason'];
            $update['recovery_reason'] = $data['recovery_reason'];
        }

        $this->startTransaction();
        //恢复出库单状态
        $obModel = $this->obOrder()->getModel();
        $ids = $obModel->whereIn('third_no', $item->pluck('tr_code'))->get()->modelKeys();
        $row = $item->update($update);
        if (!$row) return [false, __('base.fail')];
        $row = $obModel->recovery($ids, $reason)[0];
        if (!$row) return [false, '出库单恢复失败'];
        $res = $this->endTransaction([$row]);
        $this->optionLog($codes, '调拨申请单恢复', '调拨申请单恢复', ['update' => $update, 'ids' => $ids, 'fail' => $fail_ids]);

        if (!empty($fail_ids)) return [true, ['fail' => $fail_ids]];
        return $res;
    }


    //取消
    public function cancel($data)
    {
        $ids = explode(',', $data['ids']);
        $item = $this::whereIn('id', $ids)->whereIn('doc_status', [1, 2, 3])->where('send_status', '<>', 4)->where('recv_status', 1);
        $fail_ids = array_values(array_diff($ids, $item->get()->modelKeys()));

        if ($item->doesntExist()) return [false, __('status.doc_not_cancel')];

        $codes = $item->pluck('tr_code')->toArray();

        $user_id = request()->header('user_id');
        $time = date('Y-m-d H:i:s');
        $update = [
            'doc_status' => 5,
            'updated_user' => $user_id,
        ];

        //修改出入库单状态为取消
        $this->startTransaction();
        $obModel = $this->obOrder()->getModel();
        $ibModel = $this->ibOrder()->getModel();
        // $request_codes = $obModel->whereIn('third_no', $codes)->pluck('request_code')->toArray();
        $row = $item->update($update);
        if (!$row) return [false, __('base.fail')];
        //查看出库单是否生成-未生成取消直接释放库存
        $tr_codes = $obModel->whereIn('third_no', $codes)->pluck('third_no')->toArray();
        $release_codes = array_values(array_diff($codes, $tr_codes));
        if ($release_codes) $row = $this->release($release_codes);
        if (!$row) return [false, '释放库存失败'];
        if ($tr_codes) $row = $obModel::cancel($tr_codes);
        if (!$row) return [false, '出库单取消失败'];
        //入库单取消
        $ib_code = $ibModel->whereIn('third_no', $codes)->pluck('ib_code')->toArray();;
        if ($ib_code) $row = $ibModel::cancel($ib_code);
        if (!$row) return [false, '入库单取消失败'];
        $res = $this->endTransaction([$row]);
        $this->optionLog($codes, '调拨申请单取消', '调拨申请单取消', ['update' => $update, 'ids' => $ids, 'fail' => $fail_ids]);

        if (!empty($fail_ids)) return [true, ['fail' => $fail_ids]];
        return $res;
    }

    public function withSearch($select)
    {
        $permission = ADMIN_INFO['data_permission'];
        $warehouse_codes = $permission['warehouse'] ?? [];
        if ($warehouse_codes) {
            return $this::where(function ($query) use ($warehouse_codes) {
                $query->whereIn('out_warehouse_code', $warehouse_codes)->orWhereIn('in_warehouse_code', $warehouse_codes);
            })->select($select);
        }

        return $this::select($select);
    }
}
