<?php

namespace App\Models\Admin\V2;


use App\Models\Admin\wmsBaseModel;
use  App\Models\Admin\V2\ArrivalRegist;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;
use Illuminate\Support\Facades\DB;

class OtherIbOrder extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_other_ib_order'; //其他入库申请单

    // protected $map = [
    //     'type' => [1 => '采购入库', 2 => '调拨入库', 3 => '退货入库', 4 => '其他入库申请单'],
    //     'doc_status' => [0 => '暂存', 1 => '审核中', 2 => '已审核', 4 => '已确认', 5 => '已取消', 6 => '已驳回'],
    //     'recv_status' => [1 => '待收货', 2 => '部分收货', 3 => '已收货'],
    // ];
    protected $map;

    protected $appends = ['type_txt', 'doc_status_txt', 'recv_status_txt', 'users_txt', 'warehouse_txt', 'wait_recv_num'];


    public function __construct($params=[])
    {
        parent::__construct($params);
        $this->map = [
            'type' => [1 => __('status.buy_ib'), 2 => __('status.transfer_ib'), 3 => __('status.return_ib'), 4 => __('status.other_ib')],
            'doc_status' => [0 => __('status.stash'), 1 => __('status.examining'), 2 => __('status.audited'), 4 => __('status.confirmed'), 5 => __('status.canceled'), 6 => __('status.rejected')],
            'recv_status' => [1 => __('status.wait_recv'), 2 => __('status.recv_part'), 3 => __('status.received')],
        ];
    }

    public function searchUser(){
        return [
            'users_txt.approve_user' => 'approve_id',
            'users_txt.created_user' => 'created_user',
            'users_txt.updated_user' => 'updated_user',
            'users_txt.paysuccess_user' => 'paysuccess_user',
        ];
    }

    public function getUsersTxtAttribute($key)
    {
        $tenant_id = request()->header('tenant_id');
        $res['approve_user'] = $this->getAdminUser($this->approve_id, $tenant_id);
        $res['created_user'] = $this->getAdminUser($this->created_user, $tenant_id);
        $res['updated_user'] = $this->getAdminUser($this->updated_user, $tenant_id);
        $res['paysuccess_user'] = $this->getAdminUser($this->paysuccess_user, $tenant_id);
        return $res;
    }

    public function details()
    {
        return $this->hasMany(OIbDetails::class, 'oib_code', 'oib_code');
    }

    public function ibOrder()
    {
        return $this->belongsTo(IbOrder::class, 'third_no', 'oib_code');
    }

    public function withSearch($select)
    {
        return $this::with('details')->select($select);
    }


    public function getWarehouseTxtAttribute($key)
    {
        $tenant_id = request()->header('tenant_id');
        $res['warehouse_name'] = $this->getWarehouseName($this->warehouse_code, $tenant_id);
        return $res;
    }

    public function getWaitRecvNumAttribute($key)
    {
        return $this->total - $this->recv_num;
    }

    public function _formatList($data){
        self::productFormat($data['data']);
        return $data;
    }

    //新增
    public function add($data)
    {
        $log_data = $data;
        $data['type'] = 4;
        //设置默认值
        $time = date('Y-m-d H:i:s');
        $user_id = request()->header('user_id');
        $oib_code = $this->getErpCode('QTRKSQD');
        $data['oib_code'] = $oib_code;
        $data['created_user'] = $user_id;
        $data['created_at'] = $time;
        $data['recv_status'] = 1;
        $data['source_code'] = '手工创建';
        if(empty($data['paysuccess_user']))$data['paysuccess_user']=$user_id;
        if(empty($data['paysuccess_time']))$data['paysuccess_time']=$time;
        $skus = $data['skus'];
        unset($data['skus']);
        $sum_buy_price = 0;
        $total = 0;
        foreach ($skus as &$sku) {
            $field  = [
                'bar_code' => '',
                'sup_id' => '',
                'buy_price' => '',
                'num' => '',
                'inv_type'=>'',
                'remark'=>'',
                'sku'=>'',
            ];
            $sku = array_intersect_key($sku, $field);
            if(empty($sku['remark']))$sku['remark']='';
            if(empty($sku['sku']))$sku['sku'] = ProductSpecAndBar::getSkuByBar($sku['bar_code']);
            $total += $sku['num'];
            $sku['oib_code'] = $oib_code;
            $sku['created_at'] = date('Y-m-d H:i:s');
            $sku['tenant_id'] = request()->header('tenant_id');
            $sum_buy_price += $sku['buy_price']*$sku['num'];
        }
        $data['sum_buy_price'] = $sum_buy_price;
        $data['total'] = $total;
        $this->startTransaction();
        $row['add_oib'] = $this::insert($data);
        $row['add_oib_detail'] = $this->details()->getModel()->insert($skus);
        WmsOptionLog::add(WmsOptionLog::RKD, $oib_code, '创建', '其他入库库申请单创建', $log_data);
        log_arr($row, 'wms');
        return $this->endTransaction($row);
    }

    //删除
    public function del($data)
    {
        $ids = explode(',', $data['ids']);
        //暂存中的可删除
        $items = $this->whereIn('id', $ids)->where('doc_status', 0);
        if ($items->doesntExist()) return [false, __('status.doc_not_delete')];
        $not_del = array_values(array_diff($ids, $items->get()->modelKeys()));
        $oib_code = $items->get()->pluck('oib_code');
        $this->startTransaction();
        $row['del_detail'] = $this->details()->getModel()->whereIn('oib_code', $oib_code)->delete();
        $row['del'] = $items->delete();
        list($res, $msg) = $this->endTransaction($row);
        WmsOptionLog::add(WmsOptionLog::RKD, $oib_code, '删除', '其他入库申请单删除', ['not_del' => $not_del, 'ids' => $ids]);
        if (!empty($not_del)) $msg = implode(',', $not_del) . '状态不允许删除,其他的删除成功';
        return [$res, $msg];
    }

    //更新其他出库申请单详情
    public function updateDetails($id, $skus)
    {
        $item = $this::find($id);
        $oib_code = $item->oib_code;
        $create_data = [];
        $this->startTransaction();
        $detail_ids  =  [];
        foreach ($skus as $k => $sku) {
            $field  = [
                'id' => '',
                'bar_code' => '',
                'sup_id' => '',
                'buy_price' => '',
                'num' => '',
                'inv_type'=>'',
                'remark'=>'',
                'sku'=>'',
            ];
            $sku = array_intersect_key($sku, $field);
            $sku['admin_user_id'] = request()->header('user_id');
            if(empty($sku['sku']))$sku['sku'] = ProductSpecAndBar::getSkuByBar($sku['bar_code']);
            if(empty($sku['remark']))$sku['remark']='';
            $where = $sku ;
            $where['oib_code'] = $oib_code;
            $where['tenant_id'] = request()->header('tenant_id');
            $update = [
                // 'num'=>DB::raw('num+'.$sku['num']),
                'updated_at'=>date('Y-m-d H:i:s'),
                'admin_user_id'=>request()->header('user_id'),
            ];
            $updateOrCreate = OIbDetails::updateOrCreate($where,$update);
            $detail_ids[] = $updateOrCreate ->id;
            // if (empty($sku['id'])) {
            //     //新增
            //     if(empty($sku['remark']))$sku['remark']='';
            //     $sku['oib_code'] = $oib_code;
            //     $sku['created_at'] = date('Y-m-d H:i:s');
            //     $sku['tenant_id'] = request()->header('tenant_id');
            //     $sku['updated_at'] = date('Y-m-d H:i:s');
            //     $create_data[] = $sku;
            // } else {
            //     //修改
            //     $up_row[$k] = $item->details()->find($sku['id'])->update($sku);
            // }
        }
        //删除多余的
        OIbDetails::whereNotIn('id',$detail_ids)->delete();
        //新增
        // $up_row['cre_row'] = $item->details()->getModel()->insert($create_data);
        // $res = $this->endTransaction($up_row);
        

        // if ($res[0]) {
            //返回更新数量
            $num = $item->details->sum('num');
            $sum_buy_price = $item->details->sum(function ($p) {
                return $p['num']*$p['buy_price'];
            });
            DB::commit();
            return [true, ['total'=>$num,'sum_buy_price'=>$sum_buy_price]];
        // }
        // return $res;
    }

    //获取单条记录
    public function getItem($id)
    {
        return $this::find($id);
    }

    public function optionLog($codes, $option, $desc, $detail = [])
    {
        foreach ($codes as $oib_code) {
            WmsOptionLog::add(WmsOptionLog::RKD, $oib_code, $option, $desc, $detail);
        }
    }

    //提交
    public function submit($data)
    {
        $ids = explode(',', $data['ids']);
        $items = $this::whereIn('id', $ids)->where('doc_status', 0);
        if ($items->doesntExist()) return [false, __('status.doc_not_submit')];
        $codes = $items->pluck('oib_code')->toArray();
        $update = [
            'doc_status' => 1,
            'updated_user' => request()->header('user_id'),
        ];
        $row = $items->update($update);
        $this->optionLog($codes, '其他入库申请单提交', '其他入库申请单提交', ['row' => $row, 'ids' => $ids]);

        return [$row, __('base.fail')];
    }

    //撤回
    public function withdraw($data)
    {
        $ids = explode(',', $data['ids']);
        $items = $this->whereIn('id', $ids)->where('doc_status', 1);
        if ($items->doesntExist()) return [false, __('status.doc_not_withdraw')];
        $codes = $items->pluck('oib_code')->toArray();
        $update = [
            'doc_status' => 0,
            'updated_user' => request()->header('user_id'),
        ];

        $row = $items->update($update);
        $this->optionLog($codes, '其他入库申请单撤回', '其他入库申请单撤回', ['row' => $row, 'ids' => $ids]);

        return [$row, __('base.fail')];
    }

    //审核
    public function approve($data)
    {
        $ids = explode(',', $data['ids']);
        $items = $this->whereIn('id', $ids)->where('doc_status', 1);
        $approve_ids = $items->get()->modelKeys();
        $codes = $items->pluck('oib_code')->toArray();
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
        $row['update'] = $items->update(array_merge($update, $con));
        if ($row['update'] && $pass == 1) {
            //审核通过 ,生成出库单
            $res =  $this->addRequestOrder($approve_ids);
            $this->endTransaction($res);
            $this->optionLog($codes, '其他入库申请单审核', '其他入库申请单审核通过', ['row' => $row, 'update' => $update, 'ids' => $ids]);

            return [true, $res];
        }
        $this->optionLog($codes, '其他入库申请单审核', '其他入库申请单审核驳回', ['row' => $row, 'update' => $update, 'ids' => $ids]);

        return [$row, __('base.fail')];
    }

    //生成出库需求单 和 入库需求单
    public function addRequestOrder($id)
    {
        $success_count = 0;
        $items = $this::with('details')->find($id)->toArray();
        foreach ($items as $item) {
            $ib_products = [];
            $type = 4;
            //入库单
            $ib_data = [
                'ib_type' => $type,
                'third_no' => $item['oib_code'],
                // 'source_code' => $item['oib_code'],
                're_total' => $item['total'],
                'warehouse_code' => $item['warehouse_code'],
                'paysuccess_time' => $item['paysuccess_time'],
            ];
            $sup_id = [];
            foreach ($item['details'] as $pro) {
                //入库单详情
                if (!in_array($pro['sup_id'], $sup_id)) $sup_id[] = $pro['sup_id'];
                $ib_products[] = [
                    'bar_code' => $pro['bar_code'],
                    'sup_id' => $pro['sup_id'],
                    're_total' => $pro['num'],
                    'buy_price' => $pro['buy_price'],
                    'inv_type'=> $pro['inv_type'],
                ];
            }
            if (count($sup_id) == 1) $ib_data['sup_id'] = $sup_id[0];
            $row['ib_add'] = IbOrder::add($ib_data, $ib_products)[0];
            if ($row['ib_add']) $success_count += 1;
        }
        return [
            'total' => count($id),
            'success_count' => $success_count,
        ];
    }


    //取消
    public function cancel($data)
    {
        $ids = explode(',', $data['ids']);
        $item = $this::whereIn('id', $ids)->whereIn('doc_status', [1, 2, 3])->where('recv_status','<>',3);
        $fail_ids = array_values(array_diff($ids, $item->get()->modelKeys()));

        if ($item->doesntExist()) return [false, __('status.doc_not_cancel')];
        $codes = $item->pluck('oib_code')->toArray();

        $user_id = request()->header('user_id');
        $update = [
            'doc_status' => 5,
            'updated_user' => $user_id,
        ];

        //修改入库单状态为取消
        $this->startTransaction();
        $ibModel = $this->ibOrder()->getModel();
        $ib_code = $ibModel->whereIn('third_no', $codes)->pluck('ib_code')->toArray();
        $row = $item->update($update);
        if (!$row) return [false, __('base.fail')];
        $row = $ibModel::cancel($ib_code);
        if (!$row) return [false, '入库单取消失败'];
        $res = $this->endTransaction([$row]);
        $this->optionLog($codes, '其他入库申请单取消', '其他入库申请单取消', ['update' => $update, 'ids' => $ids, 'fail' => $fail_ids]);

        if (!empty($fail_ids)) return [true, ['fail' => $fail_ids]];
        return $res;
    }

    //收货确认
    public static function confirm($code, $update_data)
    {

        $oib_item = self::where('oib_code', $code)->first();
        if (empty($oib_item)) return [false, '其他入库单不存在'];

        $received_num = $update_data['rd_total'] + $oib_item->recv_num ;
        $receive_status = $oib_item->total == $received_num?3:2;
        $status = $oib_item->total == $received_num?4:$oib_item->doc_status;
        $update = [
            'doc_status' =>  $status,
            'recv_status' =>  $receive_status,
            'recv_num' => $received_num,
        ];
        // if ($oib_item->total == $update_data['rd_total']) $update['recv_status'] = 3;
        foreach ($update_data['products'] as $pro) {
            if ($pro) {
                $item = $oib_item->details()->where(function($query) use($pro){
                    $query->where('sku', $pro['sku'])
                    ->orWhere('bar_code',$pro['bar_code']);
                })->whereRaw(DB::raw('num>=recv_num+'.$pro['rd_total']))->where('num', $pro['re_total'])->where('recv_num', $pro['old_rd_total'])->limit(1)->lockForUpdate();

                $row = $item->update([
                    'recv_num' => DB::raw('recv_num+'.$pro['rd_total']),
                    'normal_count' => DB::raw('normal_count+'.$pro['normal_count']),
                    'flaw_count' => DB::raw('flaw_count+'.$pro['flaw_count']),
                ]);
                // if (!$row) return [false, '更新失败'];
            }
        }
        $row = $oib_item->update($update);
        if (!$row) return [false, '其他入库单确认失败'];
        return [true, 'success'];
    }
}
