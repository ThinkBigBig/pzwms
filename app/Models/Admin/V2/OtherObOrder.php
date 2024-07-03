<?php

namespace App\Models\Admin\V2;


use App\Models\Admin\wmsBaseModel;
use  App\Models\Admin\V2\ArrivalRegist;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;
use Illuminate\Support\Facades\DB;

class OtherObOrder extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_other_ob_order'; //其他出库申请单
    // protected $map = [
    //     'type' => [1 => '销售出库', 2 => '调拨出库', 3 => '其他出库申请单'],
    //     'doc_status' => [0 => '暂存', 1 => '审核中', 2 => '已审核', 3 => '已暂停', 4 => '已确认', 5 => '已取消', 6 => '已驳回'],
    //     'send_status' => [1 => '待发货', 2 => '发货中', 3 => '部分发货', 4 => '已发货'],
    // ];
    protected $map;

    protected $appends = ['type_txt', 'doc_status_txt', 'send_status_txt', 'users_txt', 'warehouse_txt', 'log_prod_code'];

    public function __construct($params=[])
    {
        parent::__construct($params);
        $this->map = [
            'type' => [1 => __('status.sale_ob'), 2 => __('status.transfer_ob'), 3 => __('status.other_ob')],
            'doc_status' => [0 => __('status.stash'), 1 => __('status.examining'), 2 => __('status.audited'), 3 => __('status.pause'), 4 => __('status.confirmed'), 5 => __('status.canceled'), 6 => __('status.rejected')],
            'send_status' => [1 => __('status.wait_send'), 2 => __('status.shipping'), 3 => __('status.send_part'), 4 => __('status.shipped')],
        ];
    }

    public function details()
    {
        return $this->hasMany(OObDetails::class, 'oob_code', 'oob_code');
    }


    public function obOrder()
    {
        return $this->belongsTo(ObOrder::class, 'third_no', 'oob_code');
    }
    public function withSearch($select)
    {
        return $this::with('details')->select($select);
    }

   public function withInfoSearch($select)
    {
        return $this::with('details')->select($select);
    }
    
    public function _formatList($data){
        self::productFormat($data['data']);
        return $data;
    }
    
    public function searchUser(){
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
        $tenant_id = request()->header('tenant_id');
        $res['approve_user'] = $this->getAdminUser($this->approve_id, $tenant_id);
        $res['created_user'] = $this->getAdminUser($this->created_user, $tenant_id);
        $res['updated_user'] = $this->getAdminUser($this->updated_user, $tenant_id);
        $res['paysuccess_user'] = $this->getAdminUser($this->paysuccess_user, $tenant_id);
        $res['recovery_user'] = $this->getAdminUser($this->recovery_operator_id, $tenant_id);
        $res['suspender_user'] = $this->getAdminUser($this->suspender_id, $tenant_id);
        return $res;
    }

    public function getWarehouseTxtAttribute($key)
    {
        $tenant_id = request()->header('tenant_id');
        $res['warehouse_name'] = $this->getWarehouseName($this->warehouse_code, $tenant_id);
        return $res;
    }
    public function getLogProdNameAttribute($key)
    {

        if (!$this->log_prod_code) return '';
        return $this->getLogProdName($this->log_prod_code);
    }


    public function _formatOne($data)
    {
        self::productFormat($data);
        return $data;
    }
    //新增
    public function add($data)
    {
        $log_data = $data;
        $data['type'] = 3;
        //设置默认值
        $time = date('Y-m-d H:i:s');
        $user_id = request()->header('user_id');
        if (empty($data['delivery_deadline'])) $data['delivery_deadline'] =  date('Y-m-d H:i:s', strtotime($data['paysuccess_time'] . ' +1 day'));
        $oob_code = $this->getErpCode('QTCKSQD');
        $data['oob_code'] = $oob_code;
        $data['created_user'] = $user_id;
        $data['created_at'] = $time;
        $data['send_status'] = 1;
        $data['source_code'] = '手工创建';

        $skus = $data['skus'];
        unset($data['skus']);
        $total = 0;
        $repeat = [];
        foreach ($skus as $k=>&$sku) {
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
                'uniq_code'=>'',
                'remark'=>'',
                'sku'=>'',
            ];
            $sku = array_intersect_key($sku, $field);
            $rep = $sku['bar_code'].','.$sku['sup_id'].','.$sku['buy_price'].','.$sku['quality_level'].','.$sku['batch_no'].','.$sku['uniq_code'] ;
            if(in_array($rep,$repeat)){
                unset($skus[$k]);
                continue;
            }
            $repeat[] = $rep;
            if(empty($sku['sku']))$sku['sku'] = ProductSpecAndBar::getSkuByBar($sku['bar_code']);
            if(empty($sku['remark']))$sku['remark']='';
            $total += $sku['num'];
            if ($sku['quality_level'] == 'A') {
                $sku['uniq_code'] = $sku['uniq_code']??'';
                $sku['quality_type']=  1;
            }
            else {
                if ($sku['num'] != 1) return  [false, '库存数量不足'];
                $sku['quality_type']=  2;
            }
            if (!isset($sku['uniq_code'])) return [false, '瑕疵品必须选定唯一码'];
            $sku['oob_code'] = $oob_code;
            $sku['created_at'] = date('Y-m-d H:i:s');
            $sku['tenant_id'] = request()->header('tenant_id');
        }
        // $total = array_sum(array_column($skus,'num'));
        $data['total'] = $total;
        $this->startTransaction();
        $row['add_oob'] = $this::insert($data);
        $row['add_oob_detail'] = $this->details()->getModel()->insert($skus);
        WmsOptionLog::add(WmsOptionLog::CKD, $oob_code, '创建', '其他出库申请单创建', $log_data);

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
        $oob_code = $items->get()->pluck('oob_code');
        $this->startTransaction();
        $row['del_detail'] = $this->details()->getModel()->whereIn('oob_code', $oob_code)->delete();
        $row['del'] = $items->delete();
        list($res, $msg) = $this->endTransaction($row);
        WmsOptionLog::add(WmsOptionLog::CKD, $oob_code, '删除', '其他出库申请单删除', ['not_del' => $not_del, 'ids' => $ids]);

        if (!empty($not_del)) $msg = implode(',', $not_del) . '状态不允许删除,其他的删除成功';

        return [$res, $msg];
    }

    //更新其他出库申请单详情
    public  function updateDetails($id, $skus)
    {
        $item = $this::find($id);
        if(!$item)return [false, __('response.doc_not_exists')];
        $oob_code = $item->oob_code;
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
                'uniq_code'=>'',
                'remark'=>'',
                'sku'=>'',
            ];
            $sku = array_intersect_key($sku, $field);
            $rep = $sku['bar_code'].','.$sku['sup_id'].','.$sku['buy_price'].','.$sku['quality_level'].','.$sku['batch_no'].','.$sku['uniq_code'] ;
            if(in_array($rep,$repeat)){
                unset($skus[$k]);
                continue;
            }
            $repeat[] = $rep;
            if(empty($sku['sku']))$sku['sku'] = ProductSpecAndBar::getSkuByBar($sku['bar_code']);
            $sku['admin_user_id'] = request()->header('user_id');
            if (empty($sku['id'])) {
                //新增
                if($sku['remark']==null)$sku['remark']='';
                $sku['quality_type'] = 2;
                if ($sku['quality_level'] == 'A') {
                    $sku['uniq_code'] = $sku['uniq_code'] ?? '';
                    $sku['quality_type'] = 1;
                }
                if (!isset($sku['uniq_code'])) return [false, __('response.flaw_must_uniqcode')];
                $sku['oob_code'] = $oob_code;
                $sku['created_at'] = date('Y-m-d H:i:s');
                $sku['tenant_id'] = request()->header('tenant_id');
                $sku['updated_at'] = date('Y-m-d H:i:s');
                $create_data[] = $sku;
            } else {
                //修改
                $index = array_search($sku['id'],$delete_ids);
                if($index!== false)unset($delete_ids[$index]);
                $sku_item = $item->details()->find($sku['id']);
                if($sku_item) $up_row[$k] = $sku_item->update($sku);
            }
        }
        if(!empty($delete_ids)) $item->details()->whereIn('id',$delete_ids)->delete();
        //新增
        $up_row['cre_row'] = $item->details()->getModel()->insert($create_data);
        $res = $this->endTransaction($up_row);
        WmsOptionLog::add(WmsOptionLog::CKD, $oob_code, '修改', '其他出库申请单修改', ['not_del' => $skus, 'ids' => $id]);

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
            $warehouse_code = $item['warehouse_code'];
            $flag = true;
            $flush_bar_codes = [];
            foreach ($products as $pro) {
                $count = $pro['num'];
                //检查库存
                $ids = Inventory::checkInv($warehouse_code, $count, $pro);
                if (count($ids) != $count) {
                    $flag = false;
                    $fail[$item['oob_code']][] = empty($pro['product']['sku'])?'':$pro['product']['sku'];
                    $flush_bar_codes[$pro['sup_id']][] = $pro['product']['bar_code'];
                    break;
                }
                $inv_lock[$pro['id']] = $ids;
            }
            //
            if ($flag) {
                //锁定库存
                foreach ($inv_lock as $id => $ids) {
                    $row = $this->details()->getModel()->where('id', $id)->update(['lock_ids' => implode(',', $ids)]);
                    if ($row) $row = Inventory::lockInvById($ids,3,$item['oob_code']);
                }
                if ($row) $success[] = $item['id'];
                else $fail[] = $item['id'];
            }else {
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
    public  function release($oob_code)
    {
        $items = $this->details()->getModel()->whereIn('oob_code', $oob_code);
        // $lock_ids = implode(',', $items->pluck('lock_ids')->toArray());
        // $row = Inventory::releaseInv($lock_ids);
        $row = Inventory::releaseInvByCode($oob_code);
        if ($row) return $items->update(['lock_ids' => '']);
    }

    public function optionLog($codes, $option, $desc, $detail = [])
    {
        foreach ($codes as $oob_code) {
            WmsOptionLog::add(WmsOptionLog::CKD, $oob_code, $option, $desc, $detail);
        }
    }

    //提交 锁定库存
    public function submit($data)
    {
        $ids = explode(',', $data['ids']);
        $items = $this::with(['details:id,oob_code,num,bar_code,batch_no,quality_level,sup_id,buy_price,uniq_code'])->whereIn('id', $ids)->where('doc_status', 0)->get()->toArray();
        if (empty($items)) return [false, __('status.doc_not_submit')];
        self::startTransaction();
        //检查库存并锁定
        $inv_res = $this->lock($items);
        //成功提交的数据
        $item_success = $this::whereIn('id', $inv_res['success']);
        $codes  = $item_success->pluck('oob_code')->toArray();
        $update = [
            'doc_status' => 1,
            'updated_user' => request()->header('user_id'),
        ];
        $row = $item_success->update($update);
        if ($row) {
            $this->optionLog($codes, '其他出库申请单提交', '其他出库申请单提交', ['inv_res' => $inv_res, 'ids' => $ids]);
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
        foreach($inv_res['fail'] as $code=>$sku){
            $errmsg.= $code . ': '.implode(',',$sku).' 可售库存不足';
        };
        return [false, $errmsg];
    }

    //撤回 释放库存
    public function withdraw($data)
    {
        $ids = explode(',', $data['ids']);
        $items = $this->whereIn('id', $ids)->where('doc_status', 1);
        if ($items->doesntExist()) return [false, __('status.doc_not_withdraw')];
        $codes = $items->pluck('oob_code')->toArray();
        $this->startTransaction();
        $row = $this->release($codes);
        if (!$row) return [false, '释放库存失败'];
        $update = [
            'doc_status' => 0,
            'updated_user' => request()->header('user_id'),
        ];
        $row = $this->whereIn('id', $ids)->where('doc_status', 1)->update($update);
        $this->optionLog($codes, '其他出库申请单撤回', '其他出库申请单撤回', ['update' => $update, 'ids' => $ids, 'row' => $row]);

        return $this->endTransaction([$row]);
    }

    //审核
    public function approve($data)
    {
        $ids = explode(',', $data['ids']);
        $items = $this->whereIn('id', $ids)->where('doc_status', 1);
        $approve_ids = $items->get()->modelKeys();
        if ($items->doesntExist()) return [false, __('status.doc_not_approve')];
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
        $codes = $items->pluck('oob_code')->toArray();
        $row['update'] = $items->update(array_merge($update, $con));
        if ($row['update'] && $pass == 1) {
            //审核通过 ,生成出库单
            $res =  $this->addRequestOrder($approve_ids);
            $this->endTransaction($res);
            $this->optionLog($codes, '其他出库申请单审核', '其他出库申请单审核通过', ['update' => $update, 'ids' => $ids]);

            return [true, $res];
        }
        $this->endTransaction($row);

        $this->optionLog($codes, '其他出库申请单审核', '其他出库申请单审核驳回', ['update' => $update, 'ids' => $ids]);

        return [$row, __('base.fail')];
    }


    //生成出库需求单 和 入库需求单
    public function addRequestOrder($id)
    {
        $success_count = 0;
        $items = $this::with('details')->find($id)->toArray();
        foreach ($items as $item) {
            $ob_products = [];
            $type = 3;
            //出库单
            $ob_data = [
                'type' => $type,
                'third_no' => $item['oob_code'],
                // 'source_code' => $item['oob_code'],
                'payable_num' => $item['total'],
                'warehouse_name' => $item['warehouse_txt']['warehouse_name'],
                'warehouse_code' => $item['warehouse_code'],
                'paysuccess_time' => $item['paysuccess_time'],
                'delivery_deadline' => $item['delivery_deadline'],
                'deliver_type' => $item['log_prod_code'],
                'deliver_no' => $item['deliver_no'],
                'order_platform' => 12,
                'order_channel' => '',
            ];

            $sup_id = [];
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
            }
            $row['ob_add'] = ObOrder::add($ob_data, $ob_products)[0];
            if ($row['ob_add']) $success_count += 1;
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
        $codes = $item->pluck('oob_code')->toArray();

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
        $ids = $obModel->whereIn('third_no', $item->pluck('oob_code'))->get()->modelKeys();
        $row = $item->update($update);
        if (!$row) return [false, __('base.fail')];
        $row = $obModel->pause($ids, '上游暂停.原因:' . $paused_reason)[0];
        if (!$row) return [false, '出库单暂停失败'];
        $res = $this->endTransaction([$row]);
        $this->optionLog($codes, '其他出库申请单暂停', '其他出库申请单暂停', ['update' => $update, 'ids' => $ids, 'fail' => $fail_ids]);

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
        $codes = $item->pluck('oob_code')->toArray();
        $ids = $obModel->whereIn('third_no', $codes)->get()->modelKeys();
        $row = $item->update($update);
        if (!$row) return [false, __('base.fail')];
        $row = $obModel->recovery($ids, $reason)[0];
        if (!$row) return [false, '出库单恢复失败'];
        $res = $this->endTransaction([$row]);
        $this->optionLog($codes, '其他出库申请单恢复', '其他出库申请单恢复', ['update' => $update, 'ids' => $ids, 'fail' => $fail_ids]);

        if (!empty($fail_ids)) return [true, ['fail' => $fail_ids]];

        return $res;
    }


    //取消
    public function cancel($data)
    {
        $ids = explode(',', $data['ids']);
        $item = $this::whereIn('id', $ids)->whereIn('doc_status', [1, 2, 3])->where('send_status','<>',4);
        $fail_ids = array_values(array_diff($ids, $item->get()->modelKeys()));

        if ($item->doesntExist()) return [false, __('status.doc_not_cancel')];
        $user_id = request()->header('user_id');
        $update = [
            'doc_status' => 5,
            'updated_user' => $user_id,
        ];

        //修改出入库单状态为取消
        $this->startTransaction();
        $obModel = $this->obOrder()->getModel();
        $codes =  $item->pluck('oob_code')->toArray();
        // $request_codes = $obModel->whereIn('third_no', $codes)->pluck('request_code')->toArray();
        $row = $item->update($update);
        if (!$row) return [false, __('base.fail')];
        //查看出库单是否生成-未生成取消直接释放库存
        $oob_codes = $obModel->whereIn('third_no', $codes)->pluck('third_no')->toArray();
        $release_codes = array_values(array_diff($codes,$oob_codes));
        if($release_codes)$row = $this->release($release_codes);
        if (!$row) return [false, '释放库存失败'];
        if($oob_codes)$row = $obModel::cancel($oob_codes);
        if (!$row) return [false, '出库单取消失败'];
        $res = $this->endTransaction([$row]);
        $this->optionLog($codes, '其他出库申请单取消', '其他出库申请单取消', ['update' => $update, 'ids' => $ids, 'fail' => $fail_ids]);

        if (!empty($fail_ids)) return [true, ['fail' => $fail_ids]];
        return $res;
    }
}
