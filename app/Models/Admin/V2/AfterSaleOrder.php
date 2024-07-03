<?php

namespace App\Models\Admin\V2;

use App\Logics\wms\Consigment;
use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;
use App\Logics\wms\Order;
use Illuminate\Support\Facades\DB;

class AfterSaleOrder extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_after_sale_orders'; //售后工单

    protected $guarded = [];

    const NOT_REQUEST = 0; //未生成出库需求单
    const NOT_DIST_LIST = 1; //未生成配货订单
    const WAIT_DIST = 2; //待配货
    const DISTING = 3; //配货中
    const DISTDONE = 4; //配货完成
    const SEND = 5; //已发货

    protected $casts = [
        // 'refund_time' => 'datetime:Y-m-d H:i:s',
        // 'deadline' => 'datetime:Y-m-d H:i:s',
        // 'order_at' => 'datetime:Y-m-d H:i:s',
        'refund_reason' => 'integer',
    ];
    protected $map;
    // protected $map = [
    //     'source_type' => [1 => '手工创建'],
    //     'type' => [1 => '仅退款', 2 => '退货退款'],
    //     'status' => [0 => '暂存', 1 => '审核中', 2 => '已审核', 4 => '已确认', 5 => '已取消', 6 => '已驳回'],
    //     'return_status' => [0 => '无需退货', 1 => '未收货', 2 => '已收货'],
    //     'refund_status' => [0 => '待退款', 1 => '已退款'],
    //     'refund_reason' => [
    //         0 => '默认退货原因',
    //         1 => '效果不好/不喜欢',
    //         2 => '缺货',
    //         3 => '不想要了',
    //         4 => '尺码不合适',
    //         5 => '大小尺寸与商品描述不符',
    //         6 => '卖家发错货',
    //         7 => '拍多了',
    //         8 => '材质、面料与商品描述不符',
    //         9 => '颜色、款式、图案与描述不符',
    //         10 => '质量问题',
    //         11 => '地址/电话信息填写错误',
    //         12 => '商品信息拍错(规格/尺码/颜色等)',
    //         13 => '未按约定时间发货',
    //         14 => '快递一直未送到',
    //         15 => '协商一致退款',
    //         16 => '其他',
    //         17 => '多拍/拍错/不想要',
    //         18 => '发货速度不满意',
    //         19 => '没用/少用优惠',
    //         20 => '空包裹/少货',
    //         21 => '付款之时起365天内,卖家未点击发货,自动退款给您',
    //     ],
    // ];


    protected $appends = ['status_txt', 'type_txt', 'source_type', 'return_status_txt', 'refund_status_txt', 'refund_reason_txt', 'users_txt', 'apply_at'];

    public function __construct($params = [])
    {
        parent::__construct($params);
        $this->map = [
            'source_type' => [1 => __('status.created_by_hand')],
            'type' => [1 => __('status.refund_only'), 2 => __('status.refunds')],
            'status' => [0 => __('status.stash'), 1 => __('status.examining'), 2 => __('status.audited'), 4 => __('status.confirmed'), 5 => __('status.canceled'), 6 => __('status.rejected')],
            'return_status' => [0 => __('status.no_need_for_returns'), 1 => __('status.unreceived'), 2 => __('status.received')],
            'refund_status' => [0 => __('status.pending_refund'), 1 => __('status.refunded')],
            'refund_reason' => [
                0 => __('status.refund_reason.defalut'),
                1 => __('status.refund_reason.dislike'),
                2 => __('status.refund_reason.lack'),
                3 => __('status.refund_reason.unwanted'),
                4 => __('status.refund_reason.size_not_right'),
                5 => __('status.refund_reason.size_err'),
                6 => __('status.refund_reason.send_err'),
                7 => __('status.refund_reason.too_many'),
                8 => __('status.refund_reason.material_err'),
                9 => __('status.refund_reason.color_err'),
                10 => __('status.refund_reason.quality_err'),
                11 => __('status.refund_reason.addr_err'),
                12 => __('status.refund_reason.info_err'),
                13 => __('status.refund_reason.not_time'),
                14 => __('status.refund_reason.not_send'),
                15 => __('status.refund_reason.consensus'),
                16 => __('status.refund_reason.other'),
                17 => __('status.refund_reason.err'),
                18 => __('status.refund_reason.send_speed'),
                19 => __('status.refund_reason.coupon'),
                20 => __('status.refund_reason.empty'),
                21 => __('status.refund_reason.system'),
            ],
        ];
    }
    public function getApplyAtAttribute($key)
    {
        return $this->created_at->format('Y-m-d H:i:s');
    }

    public function searchUser()
    {
        return [
            'users_txt.audit_user' => 'audit_user_id',
            'users_txt.created_user' => 'created_user',
            'users_txt.updated_user' => 'admin_user_id',
            'users_txt.refund_user' => 'order_user',
            'users_txt.confirm_user' => 'refund_user_id',
        ];
    }

    public function getUsersTxtAttribute($key)
    {
        $tenant_id = request()->header('tenant_id');
        $res['audit_user'] = $this->getAdminUser($this->audit_user_id, $tenant_id);
        $res['created_user'] = $this->getAdminUser($this->created_user, $tenant_id);
        $res['updated_user'] = $this->getAdminUser($this->admin_user_id, $tenant_id);
        $res['refund_user'] = $this->getAdminUser($this->order_user, $tenant_id);
        $res['confirm_user'] = $this->getAdminUser($this->refund_user_id, $tenant_id);
        return $res;
    }

    public function details()
    {
        return $this->hasMany(WmsAfterSaleOrderDetail::class, 'origin_code', 'code');
    }

    public function saleOrder()
    {
        return $this->belongsTo(WmsOrder::class, 'origin_code', 'code');
    }

    public function order()
    {
        return $this->hasOne(WmsOrder::class,  'code','origin_code');
    }

    public function logProduct()
    {
        return $this->belongsTo(WmsLogisticsProduct::class, 'product_code', 'product_code');
    }

    public function withSearch($select)
    {
        $model = $this::with(['details', 'logProduct', 'saleOrder:code,shop_name,third_no,buyer_account,order_at,deliver_status']);
        $permission = ADMIN_INFO['data_permission'];
        $shop_code = $permission['shop'] ?? [];
        if ($shop_code) {
            $model->with(['saleOrder' => function ($query) use ($shop_code) {
                $query->whereIn('shop_code', $shop_code);
            }]);
        }

        return $model->select($select);
    }

    public function refundReasonList()
    {
        $reason = $this->map['refund_reason'];
        $data = [];
        foreach ($reason as $key => $value) {
            $data[] = [
                'key' => $key,
                'value' => $value,
            ];
        }
        return $data;
    }

    //售后详情新增
    private function addDetails($code, &$sku)
    {
        $detail_id = $sku['detail_id'];
        unset($sku['detail_id']);
        $detail_item = WmsOrderDetail::find($detail_id);
        if (empty($detail_item)) return [false, $detail_id . __('response.data_not_exists')];
        $sku['origin_code'] = $code;
        $sku['retails_price'] = $detail_item->retails_price;
        $sku['bar_code'] = $detail_item->bar_code;
        $sku['price'] = $detail_item->price;
        $sku['amount'] = $detail_item->amount;
        $sku['order_detail_id'] = $detail_id;
        $sku['tenant_id'] = request()->header('tenant_id');
        $sku['created_at'] = date('Y-m-d H:i:s');
        // $products[] = [
        //     'bar_code' => $detail_item->bar_code,
        //     'quality_level' => $detail_item->quality_level,
        //     // 'buy_price'=>$detail_item->cost_price,

        // ];
        return [true, 'success'];
    }


    //新增
    public function add($data)
    {
        $log_data = $data;
        $sale_code = $data['sale_code'];
        $deadline = $data['deadline'];
        unset($data['deadline']);
        unset($data['sale_code']);
        //检查销售订单
        $sale_item = WmsOrder::where('code', $sale_code)->orderBy('id', 'desc')->first();
        if (empty($sale_item)) return [false,  __('response.order_not_exists')];
        if ($sale_item->status != WmsOrder::PASS) return [false,  __('response.order_not_return')];
        //销售订单信息
        $code = $this->getErpCode('SHGD');

        $skus =  $data['skus'];
        unset($data['skus']);
        // $products = [];

        $apply_num = 0;
        $return_num = 0;
        $refund_amount = 0;
        foreach ($skus as &$sku) {
            list($res, $msg) = $this->addDetails($code, $sku);
            if (!$res) return [false, $msg];
            $apply_num += $sku['num'];
            $return_num += $sku['return_num'];
            $refund_amount += round($sku['refund_amount'], 2);
        }


        //填充数据
        $type = $return_num == 0 ? 1 : 2;
        $data['code'] = $code;
        $data['source_type'] = 1;
        $data['type'] = $type;
        $data['apply_num'] = $apply_num;
        $data['return_num'] = $return_num;
        $data['refund_amount'] = $refund_amount;
        $data['return_status'] = $type == 1 ? 0 : 1;
        $data['origin_code'] = $sale_code;
        $data['deadline'] = $deadline;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['created_user'] = request()->header('user_id');

        $this->startTransaction();
        $row['add_buy'] = $this::insert($data);
        $row['add_buy_detail'] = $this->details()->getModel()->insert($skus);
        WmsOptionLog::add(WmsOptionLog::SHGD, $code, '创建', '售后工单创建', $log_data);

        return $this->endTransaction($row);
    }


    //销售订单重新提交
    private function resetSale($sale_codes)
    {
        $sale_item = WmsOrder::whereIn('code', $sale_codes)->whereIn('status', [WmsOrder::PASS, WmsOrder::PAUSE]);
        $ids = $sale_item->pluck('id')->toArray();
        if (!$ids) return [false, __('response.order_not_withdraw')];
        //销售订单改为提交状态
        $this->startTransaction();
        $sale_item->update([
            'status' => WmsOrder::WAIT_AUDIT,
            'admin_user_id' => SYSTEM_ID,
        ]);
        DB::commit();

        $logic = new Order();
        foreach ($ids as $id) {
            $sale_res = $logic->audit(['id' => $id, 'status' => 2]);
            if (!$sale_res) return [false, $logic->err_msg];
        }

        return [true, 'success'];
    }


    //删除
    public function del($data)
    {
        $ids = explode(',', $data['ids']);
        //暂存中的可删除
        $items = $this->whereIn('id', $ids)->where('status', 0);
        if ($items->doesntExist()) return [false, __('response.order_not_delete')];
        $not_del = array_values(array_diff($ids, $items->get()->modelKeys()));
        $sale_code = $items->get()->pluck('code');
        $this->startTransaction();
        $row['del_detail'] = $this->details()->getModel()->whereIn('origin_code', $sale_code)->delete();
        $row['del'] = $items->delete();
        list($res, $msg) = $this->endTransaction($row);
        WmsOptionLog::add(WmsOptionLog::SHGD, $sale_code, '删除', '售后工单删除', ['not_del' => $not_del, 'ids' => $ids]);
        if (!empty($not_del)) $msg = implode(',', $not_del) . '状态不允许删除,其他的删除成功';
        return [$res, $msg];
    }


    //更新售后工单详情
    public function updateDetails($id, $skus)
    {
        $item = $this::find($id);
        $sale_code = $item->code;
        $create_data = [];
        $this->startTransaction();
        foreach ($skus as $k => $sku) {
            $field  = [
                'id' => '',
                'detail_id' => '',
                'num' => '',
                'return_num' => '',
                'refund_amount' => '',
            ];
            $sku = array_intersect_key($sku, $field);

            $sku['admin_user_id'] = request()->header('user_id');
            if (empty($sku['id'])) {
                //新增
                list($res, $msg) = $this->addDetails($sale_code, $sku);
                if (!$res) return [false, $msg];
                $create_data[] = $sku;
            } else {
                //修改
                if (!empty($sku['detail_id'])) {
                    $sku['order_detail_id'] = $sku['detail_id'];
                    unset($sku['detail_id']);
                }
                $up_row[$k] = $item->details()->find($sku['id'])->update($sku);
            }
        }
        //新增
        if ($create_data) $up_row['cre_row'] = $item->details()->getModel()->insert($create_data);
        $res = $this->endTransaction($up_row);
        WmsOptionLog::add(WmsOptionLog::SHGD, $sale_code, '修改', '售后工单修改', ['not_del' => $skus, 'ids' => $id]);

        if ($res[0]) {
            //返回更新数量
            $apply_num = $item->details->sum('num');
            $return_num = $item->details->sum('return_num');
            $refund_amount = $item->details->sum('refund_amount');
            return [true, ['apply_num' => $apply_num, 'return_num' => $return_num, 'refund_amount' => $refund_amount]];
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
            WmsOptionLog::add(WmsOptionLog::SHGD, $code, $option, $desc, $detail);
        }
    }

    //提交
    public function submit($data)
    {
        $ids = explode(',', $data['ids']);
        $items = $this::whereIn('id', $ids)->where('status', 0);
        if ($items->doesntExist()) return [false, __('response.order_not_submit')];
        $sale_codes = $items->pluck('origin_code')->toArray();
        $codes = $items->pluck('code')->toArray();
        $update = [
            'status' => 1,
            'admin_user_id' => request()->header('user_id'),
        ];
        $this->startTransaction();
        $row[] = $items->update($update);

        //取消操作
        list($res, $msg) = $this->cancel($sale_codes);
        if (!$res) return [false, $msg];


        $this->log($codes, '售后工单提交', '售后工单提交', ['row' => $row, 'ids' => $ids]);
        return $this->endTransaction($row);
    }

    //撤回
    public function withdraw($data)
    {
        $ids = explode(',', $data['ids']);
        $items = $this->whereIn('id', $ids)->where('status', 1);
        if ($items->doesntExist()) return [false, __('response.doc_not_withdraw')];
        $codes = $items->pluck('code')->toArray();
        $sale_codes = $items->pluck('origin_code')->toArray();
        $this->startTransaction();

        $update = [
            'status' => 0,
            'admin_user_id' => request()->header('user_id'),
        ];

        $row[] = $items->update($update);
        //销售订单重新审核
        list($res, $msg) = $this->resetSale($sale_codes);
        if (!$res) return [false, $msg];
        $this->log($codes, '售后工单撤回', '售后工单撤回', ['row' => $row, 'ids' => $ids]);

        return $this->endTransaction($row);
    }

    //审核
    public function approve($data)
    {
        $ids = explode(',', $data['ids']);
        $items = $this->whereIn('id', $ids)->where('status', 1);
        $approve_ids = $items->get()->modelKeys();
        $codes = $items->pluck('code')->toArray();
        if ($items->doesntExist()) return [false,  __('response.doc_not_examine')];
        $pass = $data['pass'];
        if ($pass == 1) $update['status'] = 2;
        if ($pass == 0) $update['status'] = 6;
        if (!empty($data['approve_reason'])) $update['remark'] = $data['approve_reason'];
        $user_id = request()->header('user_id');
        $con = [
            'audit_user_id' => $user_id,
            'admin_user_id' => $user_id,
            'audit_time' => date('Y-m-d H:i:s'),
        ];
        // $this->startTransaction();
        $row['update'] = $items->update(array_merge($update, $con));
        if ($row['update'] && $pass == 1) {
            //审核通过 ,生成入库单
            // $res =  $this->addRequestOrder($approve_ids);
            // $this->endTransaction($row);
            $this->log($codes, '售后工单审核', '售后工单审核通过', ['row' => $row, 'update' => $update, 'ids' => $ids]);

            return [true, 'success'];
        }
        $this->log($codes, '售后工单审核', '售后工单审核驳回', ['row' => $row, 'update' => $update, 'ids' => $ids]);

        return [$row, __('base.fail')];
    }


    //取消下游单据
    private function cancel($sale_codes)
    {

        //暂停销售订单
        $reason = '退款暂停';
        $order = WmsOrder::where('code', $sale_codes)->whereIn('status', [WmsOrder::WAIT_AUDIT, WmsOrder::PASS]);
        if ($order->doesntExist()) return [false, __('response.order_not_pause')];
        $old_status = $order->pluck('status', 'id');
        $third_nos = $order->pluck('third_no')->toArray();

        $row = $order->update([
            'old_status' => DB::raw('status'),
            'tag' => '退',
            'status' => WmsOrder::PAUSE,
            'suspender_id' => ADMIN_INFO['user_id'],
            'paused_at' => date('Y-m-d H:i:s'),
            'paused_reason' => $reason,

        ]);
        if (!$row) return [false, __('response.order_not_fail')];
        // WmsOptionLog::add(WmsOptionLog::ORDER, $order->code, '退款暂停', '销售订单退款暂停', [
        //     'old_status' => $old_status,
        // ]);
        //取消出库单
        //生成出库取消单
        $row = ObOrder::cancel($third_nos);
        if (!$row) return [false, __('response.ob_cancel_fail')];
        return [true, 'success'];
    }

    //确认退款
    public function confirm($data)
    {
        $ids = explode(',', $data['ids']);
        $item = $this::whereIn('id', $ids)->where('status', 2);
        if ($item->doesntExist()) return [false, __('response.sale_after_return_err')];
        $codes = $item->pluck('code')->toArray();
        $order_codes = $item->pluck('origin_code')->toArray();
        $user_id = request()->header('user_id');
        $update = [
            'status' => 4,
            'refund_user_id' => $user_id,
            'refund_status' => 1,
            'refund_time' => date('Y-m-d H:i:s'),
            'admin_user_id' => $user_id,
        ];
        $row = $item->update($update);
        $this->log($codes, '确认退款', '售后工单确认退款', $data);
        //销售单结束
        $order_item  = WmsOrder::where('code', $order_codes);
        $order_item->update([
            'tag' => DB::raw("CONCAT(tag, ',终')"),
        ]);

        Consigment::confirm($codes);
        return [$row, '确认退款失败'];
    }

    //发货追回
    public function recoverIb($data)
    {
        $log_data = $data;
        $id = $data['id'];
        $item = $this::find($id);
        if (empty($item)) return [false, __('response.doc_not_exists')];
        if ($item->status != 2 || $item->ib_status != 0) return [false, __('response.doc_not_send_recovery')];
        if ($item->type !=  1) return [false, __('response.not_only_refund')];
        if ($data['num'] != $item->apply_num) return [false, __('response.recovery_number_err')];
        $code = $item->code;
        $update = [
            'ib_status' => 1,
            'warehouse_code' => $data['warehouse_code'],
            'admin_user_id' => request()->header('user_id'),
        ];
        if (!empty($data['remark'])) $update['remark'] = $data['remark'];
        $this->startTransaction();
        $row['update'] = $item->update($update);
        $row['addReq'] = $this->addRequestOrder($id, $data['warehouse_code']);
        $row['addLog'] = WmsOptionLog::add(WmsOptionLog::SHGD, $code, '发货追回', '售后工单发货追回', $log_data);
        return $this->endTransaction($row);
    }

    //退货入库
    public function returnIb($data)
    {
        $log_data = $data;
        $id = $data['id'];
        $item = $this::find($id);
        if (empty($item)) return [false, __('response.doc_not_exists')];
        if ($item->status != 2 || $item->ib_status != 0) return [false, __('response.doc_not_return_ib')];
        if ($item->type !=  2) return [false, __('response.not_returns_and_refunds')];
        if ($data['num'] != $item->apply_num) return [false, __('response.ib_number_err')];
        $code = $item->code;
        $update = [
            'ib_status' => 2,
            'warehouse_code' => $data['warehouse_code'],
            'product_code' => $data['product_code'],
            'deliver_no' => $data['deliver_no'],
            'admin_user_id' => request()->header('user_id'),
        ];
        if (!empty($data['remark'])) $update['remark'] = $data['remark'];
        $this->startTransaction();
        $row['update'] = $item->update($update);
        $row['addReq'] = $this->addRequestOrder($id, $data['warehouse_code']);
        $row['addLog'] = WmsOptionLog::add(WmsOptionLog::SHGD, $code, '退货入库', '售后工单退货入库', $log_data);
        return $this->endTransaction($row, $row['addReq']);
    }

    //退货入库单
    public function addRequestOrder($id, $warehouse_code)
    {
        $item = $this::find($id);
        if ($item) $sale_item = WmsOrder::where('code', $item->origin_code)->whereIn('status', [WmsOrder::PASS, WmsOrder::PAUSE])->first();
        if (!$sale_item) return [false, __('response.absent_or_not_refund')];
        $details = $item->details()->get();
        $bar_codes = $details->pluck('bar_code')->toArray();
        $type = 3;
        //判断是否发货,未发货生成取消单上架
        // $ob_item = ObOrder::where('third_no', $sale_item->third_no)->orderBy('id', 'desc')->first();
        // if(in_array($ob_item->request_status,[1,2,3])){
        //     return [ObOrder::cancel($sale_item->third_no),'取消单上架'];
        // }
        //写入退货明细
        list($uniq_log, $msg) = WithdrawUniqLog::getProData($sale_item->third_no, $bar_codes, $type);
        if (!$uniq_log) return [false, '添加唯一码明细失败:' . $msg];
        //入库单
        $ib_data = [
            'ib_type' => $type,
            'third_no' => $sale_item->third_no,
            're_total' => $item->apply_num,
            'warehouse_code' => $warehouse_code,
            'paysuccess_time' => $sale_item->order_at,
        ];
        $sup_ids = [];
        $bar_count = $details->countBy('bar_code')->toArray();
        foreach ($bar_count as $bar => $count) {
            //入库单详情
            $pro =  WithdrawUniqLog::where('source_code', $sale_item->third_no)->where('bar_code', $bar)->first();
            $sup_id = $pro->sup_id;
            if (!in_array($sup_id, $sup_ids)) $sup_ids[] = $sup_id;
            $ib_products[] = [
                'bar_code' => $bar,
                'sup_id' => $sup_id,
                're_total' => $count,
                'buy_price' => $pro['buy_price'],
                'quality_type' => $pro->getRawOriginal('quality_type'),
                'quality_level' => $pro['quality_level'],
                'inv_type' => $pro['inv_type'],
            ];
        }
        if (count($sup_ids) == 1) $ib_data['sup_id'] = $sup_ids[0];
        return  [IbOrder::add($ib_data, $ib_products), '退货入库单'];
    }
}
