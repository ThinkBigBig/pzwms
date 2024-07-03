<?php

namespace App\Models\Admin\V2;

use App\Logics\traits\DataPermission;
use App\Logics\wms\Purchase;
use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;
use Illuminate\Support\Facades\DB;

class Consignment extends wmsBaseModel
{
    use HasFactory, DataPermission;
    protected $table = 'wms_consignment_orders'; //寄卖单

    protected $guarded = [];
    // protected $map = [
    //     'status' => [0 => '暂存', 1 => '审核中', 2 => '已审核', 4 => '已确认', 5 => '已取消', 6 => '已驳回'],
    //     'receive_status' => [0 => '待收货', 1 => '已收货'],
    //     'source_type' => [1 => '手工创建', 2 => '手工导入'],
    //     'pay_status' => [0 => '未付款', 1 => '已付款'],
    // ];

    protected $map;

    protected $appends = ['status_txt', 'receive_status_txt', 'users_txt', 'source_type', 'wait_num', 'recv_rate'];

    public function __construct($params = [])
    {
        parent::__construct($params);
        $this->map = [
            'status' => [0 => __('status.stash'), 1 => __('status.examining'), 2 => __('status.audited'), 4 => __('status.confirmed'), 5 => __('status.canceled'), 6 => __('status.rejected')],
            'receive_status' => [0 => __('status.wait_recv'), 1 => __('status.received'),2=>__('status.recv_part')],
            'source_type' => [1 => '手工创建', 2 => '手工导入']
        ];
    }

    public function searchUser()
    {
        return [
            'users_txt.approve_user' => 'approve_id',
            'users_txt.created_user' => 'created_user',
            'users_txt.updated_user' => 'updated_user',
            'users_txt.order_user' => 'order_user',
        ];
    }

    public function getUsersTxtAttribute($key)
    {
        $tenant_id = request()->header('tenant_id');
        $res['approve_user'] = $this->getAdminUser($this->approve_id, $tenant_id);
        $res['created_user'] = $this->getAdminUser($this->created_user, $tenant_id);
        $res['updated_user'] = $this->getAdminUser($this->updated_user, $tenant_id);
        $res['order_user'] = $this->getAdminUser($this->order_user, $tenant_id);
        return $res;
    }
    //待收总数
    public function getWaitNumAttribute($key)
    {
        return $this->num - $this->received_num;
    }
    //收获率
    public function getRecvRateAttribute($key)
    {
        if ($this->num == 0) return 0;
        return round($this->received_num / $this->num, 4) * 100;
    }
    public function details()
    {
        return $this->hasMany(ConsignmentDetails::class, 'origin_code', 'code');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_code', 'warehouse_code')->withDefault([
            'id' => 0,
            'warehouse_code' => '',
            'warehouse_name' => '',
        ]);
    }
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'sup_id')->withDefault([
            'sup_code' => '',
            'name' => ''
        ]);
    }

    public function withSearch($select)
    {
        return $this::with(['warehouse:id,warehouse_code,warehouse_name,type,status', 'supplier:sup_code,name,id,status'])->select($select);
    }

    public function withInfoSearch($select)
    {
        return $this::with(['warehouse:id,warehouse_code,warehouse_name,type,status', 'supplier:sup_code,name,id,status', 'details'])->select($select);
    }



    public function  getOptionInfoAttribute($key)
    {
        $list = WmsOptionLog::list(WmsOptionLog::JMD, $this->code)->toArray();
        return $list;
    }

    public function  getRecvInfoAttribute($key)
    {
        return RecvOrder::recvInfo($this->code);
    }

    public function _formatOne($data)
    {
        $data = $data->append(['option_info', 'recv_info'])->toArray();
        self::productFormat($data);
        return $data;
    }

    public function ibOrder()
    {
        return $this->belongsTo(IbOrder::class, 'source_code', 'oib_code');
    }

    //新增
    public function add($data, $source_type = 1)
    {

        $log_data = $data;
        //设置默认值
        $time = date('Y-m-d H:i:s');
        $user_id = request()->header('user_id');
        $code = $this->getErpCode('JMD');
        $data['code'] = $code;
        $data['source_type'] = $source_type;
        $data['created_user'] = $user_id;
        $data['created_at'] = $time;
        if(!$data['estimate_receive_at'] )unset($data['estimate_receive_at'] );
        if(!$data['send_at'] )unset($data['send_at'] );
        if(empty($data['order_user']))$data['order_user']=$user_id;
        if(empty($data['order_at']))$data['order_at']=$time;
        $skus = $data['skus'];
        unset($data['skus']);

        $total = 0;
        $amount = 0;
        foreach ($skus as &$sku) {
            $field  = [
                'bar_code' => '',
                'buy_price' => '',
                'num' => '',
                'remark' => '',
                'sku'=>'',
            ];
            $sku = array_intersect_key($sku, $field);
            if(empty($sku['remark']))$sku['remark']='';
            if(empty($sku['sku']))$sku['sku'] = ProductSpecAndBar::getSkuByBar($sku['bar_code']);
            $total += $sku['num'];
            $amount += $sku['buy_price'] * $sku['num'];
            $sku['origin_code'] = $code;
            $sku['created_at'] = date('Y-m-d H:i:s');
            $sku['admin_user_id'] = $user_id;
            $sku['tenant_id'] = request()->header('tenant_id');
        }
        // $total = array_sum(array_column($skus,'num'));
        $data['num'] = $total;
        $data['amount'] = $amount;
        $this->startTransaction();
        $row['add_buy'] = $this::insert($data);
        $row['add_buy_detail'] = $this->details()->getModel()->insert($skus);
        WmsOptionLog::add(WmsOptionLog::JMD, $code, '创建', '寄卖单创建', $log_data);

        return $this->endTransaction($row);
    }

    //删除
    public function del($data)
    {
        $ids = explode(',', $data['ids']);
        //暂存中的可删除
        $items = $this->whereIn('id', $ids)->where('status', 0);
        if ($items->doesntExist()) return [false,  __('status.doc_not_delete')];
        $not_del = array_values(array_diff($ids, $items->get()->modelKeys()));
        $origin_code = $items->get()->pluck('code');
        $this->startTransaction();
        $row['del_detail'] = $this->details()->getModel()->whereIn('origin_code', $origin_code)->delete();
        $row['del'] = $items->delete();
        list($res, $msg) = $this->endTransaction($row);
        WmsOptionLog::add(WmsOptionLog::JMD, $origin_code, '删除', '寄卖单删除', ['not_del' => $not_del, 'ids' => $ids]);
        if (!empty($not_del)) $msg = implode(',', $not_del) . '状态不允许删除,其他的删除成功';
        return [$res, $msg];
    }

    //更新寄卖单详情
    public function updateDetails($id, $skus)
    {
        $item = $this::find($id);
        $origin_code = $item->code;
        $create_data = [];
        $this->startTransaction();
        $delete_ids = $item->details()->pluck('id')->toArray();
        $up_row = [];
        foreach ($skus as $k => $sku) {
            $field  = [
                'id' => '',
                'bar_code' => '',
                'buy_price' => '',
                'num' => '',
                'remark' => '',
                'sku'=>'',
            ];
            $sku = array_intersect_key($sku, $field);
            if(empty($sku['remark']))$sku['remark']='';
            if(empty($sku['sku']))$sku['sku'] = ProductSpecAndBar::getSkuByBar($sku['bar_code']);
            $sku['admin_user_id'] = request()->header('user_id');
            if (empty($sku['id'])) {
                //新增
                $sku['origin_code'] = $origin_code;
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
        WmsOptionLog::add(WmsOptionLog::JMD, $origin_code, '修改', '寄卖单修改', ['not_del' => $skus, 'ids' => $id]);

        if ($res[0]) {
            //返回更新数量
            $num = $item->details->sum('num');
            $amount = $item->details->sum(function ($pro) {
                return $pro['buy_price'] * $pro['num'];
            });
            return [true, ['num' => $num, 'amount' => $amount]];
        }
        return $res;
    }

    //获取单条记录
    public function getItem($id)
    {
        return $this::find($id);
    }

    public function log($codes, $option, $desc, $detail = [])
    {
        foreach ($codes as $code) {
            WmsOptionLog::add(WmsOptionLog::JMD, $code, $option, $desc, $detail);
        }
    }

    //提交
    public function submit($data)
    {
        $ids = explode(',', $data['ids']);
        $items = $this::whereIn('id', $ids)->where('status', 0);
        if ($items->doesntExist()) return [false, __('status.doc_not_submit')];
        $codes = $items->pluck('code')->toArray();
        $update = [
            'status' => 1,
            'updated_user' => request()->header('user_id'),
        ];
        $row = $items->update($update);
        $this->log($codes, '寄卖单提交', '寄卖单提交', ['row' => $row, 'ids' => $ids]);

        return [$row, __('base.fail')];
    }

    //撤回
    public function withdraw($data)
    {
        $ids = explode(',', $data['ids']);
        $items = $this->whereIn('id', $ids)->where('status', 1);
        if ($items->doesntExist()) return [false, __('status.doc_not_withdraw')];
        $codes = $items->pluck('code')->toArray();
        $update = [
            'status' => 0,
            'updated_user' => request()->header('user_id'),
        ];

        $row = $items->update($update);
        $this->log($codes, '寄卖单撤回', '寄卖单撤回', ['row' => $row, 'ids' => $ids]);

        return [$row, __('base.fail')];
    }

    //审核
    public function approve($data)
    {
        $ids = explode(',', $data['ids']);
        $items = $this->whereIn('id', $ids)->where('status', 1);
        $approve_ids = $items->get()->modelKeys();
        $codes = $items->pluck('code')->toArray();
        if ($items->doesntExist()) return [false, __('status.doc_not_approve')];
        $pass = $data['pass'];
        if ($pass == 1) $update['status'] = 2;
        if ($pass == 0) $update['status'] = 6;
        if (!empty($data['approve_reason'])) $update['approve_reason'] = $data['approve_reason'];
        $user_id = request()->header('user_id');
        $con = [
            'approve_id' => $user_id,
            'updated_user' => $user_id,
            'audit_at' => date('Y-m-d H:i:s'),
        ];
        $this->startTransaction();
        $row['update'] = $items->update(array_merge($update, $con));
        if ($row['update'] && $pass == 1) {
            //审核通过 ,生成入库单
            $res =  $this->addRequestOrder($approve_ids);
            // 采购汇总
            // foreach ($items as $item) {
            //     Purchase::summaryUpdate($item);
            // }
            $this->endTransaction($res);
            $this->log($codes, '寄卖单审核', '寄卖单审核通过', ['row' => $row, 'update' => $update, 'ids' => $ids]);

            return [true, $res];
        }
        $this->endTransaction($row);
        $this->log($codes, '寄卖单审核', '寄卖单审核驳回', ['row' => $row, 'update' => $update, 'ids' => $ids]);

        return [$row, __('base.fail')];
    }

    //生成出库需求单 和 入库需求单
    public function addRequestOrder($id)
    {
        $success_count = 0;
        $items = $this::with('details')->find($id)->toArray();
        foreach ($items as $item) {
            $ib_products = [];
            $type = 1;
            //入库单
            $ib_data = [
                'ib_type' => $type,
                'sup_id' => $item['sup_id'],
                'third_no' => $item['third_code'], //登记单
                'source_code' => $item['code'], //寄卖单
                're_total' => $item['num'],
                'warehouse_code' => $item['warehouse_code'],
                'paysuccess_time' => $item['order_at'],
            ];
            foreach ($item['details'] as $pro) {
                //入库单详情
                $ib_products[] = [
                    'bar_code' => $pro['bar_code'],
                    'sup_id' =>  $item['sup_id'],
                    're_total' => $pro['num'],
                    'buy_price' => $pro['buy_price'],
                    'sku' => $pro['sku'],
                    'inv_type' => 1,
                ];
            }
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
        $item = $this::whereIn('id', $ids)->whereIn('status', [1, 2, 3]);
        $fail_ids = array_values(array_diff($ids, $item->get()->modelKeys()));

        if ($item->doesntExist()) return [false, __('status.doc_not_cancel')];
        $codes = $item->pluck('code')->toArray();

        $user_id = request()->header('user_id');
        $update = [
            'status' => 5,
            'updated_user' => $user_id,
        ];

        //修改入库单状态为取消
        $this->startTransaction();
        $ibModel = $this->ibOrder()->getModel();
        $ib_code = $ibModel->whereIn('source_code', $item->pluck('code'))->pluck('ib_code')->toArray();
        $row = $item->update($update);
        if (!$row) return [false, __('base.fail')];
        $row = $ibModel::cancel($ib_code);
        if (!$row) return [false, '入库单取消失败'];
        $res = $this->endTransaction([$row]);
        $this->log($codes, '寄卖单取消', '寄卖单取消', ['update' => $update, 'ids' => $ids, 'fail' => $fail_ids]);

        if (!empty($fail_ids)) return [true, ['fail' => $fail_ids]];
        return $res;
    }

    //收货确认
    public static function confirm($code, $update_data)
    {

        $buy_item = self::where('code', $code)->first();
        if (empty($buy_item)) return [false, '寄卖单不存在'];

        $received_num = $update_data['rd_total'] + $buy_item->received_num ;
        $receive_status = $buy_item->num == $received_num?1:2;
        $status = $buy_item->num == $received_num?4:$buy_item->status;
        $update = [
            'status' =>  $status,
            'receive_status' => $receive_status,
            'received_num' => $received_num,
        ];
        if (isset($update_data['third_code'])) $update['third_code'] = $update_data['third_code'];

       
        // $details_ids = [];
        foreach ($update_data['products'] as $pro) {
            if ($pro) {
                $item = $buy_item->details()->where(function($query) use($pro){
                    $query->where('sku', $pro['sku'])
                    ->orWhere('bar_code',$pro['bar_code']);
                })->where('num', $pro['re_total'])->where('recv_num', $pro['old_rd_total'])->limit(1)->lockForUpdate();
                // $details_ids = array_merge($details_ids,$item->pluck('id')->toArray());

                $row = $item->update([
                    'normal_count' => DB::raw('normal_count+'.$pro['normal_count']),
                    'flaw_count' =>DB::raw('flaw_count+'.$pro['flaw_count']),
                    'recv_num' =>DB::raw('recv_num+'.$pro['rd_total']),
                ]);
                // if (!$row) return [false, '更新失败'];
               
            }
        }
        $row = $buy_item->update($update);
        // if (!$row) return [false, '寄卖单确认失败'];
        if($status == 4){
            // $amount = $buy_item->details()->select(DB::raw("sum(buy_price * recv_num) as sum"))->get()->sum('sum');
            Purchase::summaryUpdate($buy_item,$buy_item->details()->get(),1);
            // //生成采购结算单
            // $row = PurchaseStatements::add($code, $amount);
            // if (!$row) return [false, '采购结算单生成失败'];
        }
      
        return [true, 'success'];
    }

    public function getBarCode($product_sn,$spec){
        return ProductSpecAndBar::getBarCode($product_sn,$spec);
    }
}
