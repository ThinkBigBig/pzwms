<?php

namespace App\Models\Admin\V2;

use App\Logics\traits\WmsAttribute;
use App\Models\Admin\wmsBaseModel;
use  App\Models\Admin\V2\ArrivalRegist;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;
use Illuminate\Support\Facades\DB;
use App\Logics\wms\AllocationTask;

class ObOrder extends wmsBaseModel
{
    use HasFactory, WmsAttribute;
    protected $table = 'wms_shipping_request'; //出库需求单
    protected $guarded = [];
    // protected $fillable = ['request_status','tag','stockout_num','cancel_num','oversold_num','status','actual_num'];
    // protected $map = [
    //     'type' => [1=>'销售出库' ,2=>'调拨出库', 3=>'其他出库'],
    //     'status' => [1=>'审核中' ,2=>'已审核', 3=>'暂停',4=>'已确认',5=>'已取消'],
    //     'request_status' => [1=>'待发货',2=>'配货中',3=>'发货中',4=>'已发货',5=>'已取消'],
    //     'tag' => [0=>'', 1=>'预配中',2=>'超卖',3=>'系统缺货',4=>'商品数据异常',5=>'预配失败',6=>'终'],
    // ];
    protected $map;

    // type
    const TYPE_SALE = 1;
    const TYPE_TRANSFER = 2;
    const TYPE_OTHER = 3;

    // status
    const STATUS_WAIT = 1; //审核中
    const STATUS_PASS = 2; //审核通过
    const STATUS_PAUSE = 3; //暂停
    const STATUS_CONFIRMED = 4; //已确认
    const STATUS_CANCELED = 5; //已取消
    const STATUS_REJECT = 6; //审核拒绝

    // request_status
    const WAIT = 1; //待发货
    const ALLOCATE = 2; //配货中
    const ON_DELIVERY = 3; //发货中
    const DELIVERED = 4; //已发货
    const CANCELED = 5; //已取消

    // protected $with = ['details'];

    protected $appends = ['type_txt', 'status_txt', 'request_status_txt', 'diff_num', 'remainder', 'users_txt', 'tag_txt', 'order_platform_txt','order_channel_txt'];

    // protected $casts = [
    //     'paysuccess_time' => 'datetime:Y-m-d H:i:s',
    //     'delivery_deadline' => 'datetime:Y-m-d H:i:s',
    // ];

    public function __construct($params = [])
    {
        parent::__construct($params);
        $this->map = [
            'type' => [1 => __('status.sale_ob'), 2 => __('status.transfer_ob'), 3 => __('status.other_ob')],
            'status' => [1 => __('status.examining'), 2 => __('status.audited'), 3 => __('status.pause'), 4 => __('status.confirmed'), 5 => __('status.canceled')],
            'request_status' => [1 => __('status.wait_send'), 2 => __('status.in_distribution'), 3 => __('status.shipping'), 4 => __('status.shipped'), 5 => __('status.canceled')],
            'tag' => [0 => '', 1 => __('status.pre_allocation'), 2 => __('status.oversold'), 3 => __('status.out_of_stock'), 4 => __('status.abnormal_product_data'), 5 => __('status.pre_fail'), 6 => __('status.flag_end')],
        ];
    }

    public function withSearch($select)
    {
        return $this::with('details')->select($select);
    }
    public function logistics()
    {
        return $this->hasOne(WmsLogisticsProduct::class, 'product_code', 'deliver_type')->where('status', 1)->withDefault();
    }

    public function channel(){
        return $this->hasOne(WmsShop::class,'id','order_channel')->withDefault([
            'name'=>'',
        ]);
    }
    // 调拨申请单
    public function transfer()
    {
        return $this->hasOne(TransferOrder::class, 'tr_code', 'third_no')->orderBy('id', 'desc');
    }

    // 其他出库申请单
    public function otherOut()
    {
        return $this->hasOne(OtherObOrder::class, 'oob_code', 'third_no')->orderBy('id', 'desc');
    }

    //商品明细
    public function details()
    {
        return $this->hasMany(ShippingDetail::class, 'request_code', 'request_code');
    }

    public function getOrderPlatformTxtAttribute(): string
    {
        return self::orderPlatform()[$this->order_platform] ?? '';
    }

    public function getOrderChannelTxtAttribute(): string
    {
        return $this->channel()->first()->name??'';
    }
    //预配明细
    public function preDetails($request_code)
    {
        $model =  (new preAllocationDetail())->with(['product', 'supplier'])->where('request_code', $request_code);
        return  $model->select('bar_code', 'quality_level', 'sup_id', 'created_at', DB::raw("sum(pre_num) as pre_num"))->groupBy('bar_code', 'quality_level', 'sup_id')->get()->append(['supplier_name'])->makeHidden('supplier')->toArray();
    }

    //发货明细
    public function sendDetails($request_code)
    {
        $model =  (new ShippingOrders())->where('request_code', $request_code);
        return  $model->select('ship_code', 'actual_num', 'shipper_id', 'shipped_at')->get()->toArray();
    }
    
    public static function  getBarCode($sku, $tenant_id)
    {
        $item = DB::table('wms_spec_and_bar')->where('tenant_id', $tenant_id)->where('sku', $sku)->first();
        if (empty($item)) return '';
        return $item->bar_code;
    }

    //搜索时用户相关的字段对应关系
    public function searchUser()
    {
        return [
            'users_txt.suspender_user' => 'suspender_id',
            'users_txt.recovery_operator_user' => 'recovery_operator_id',
            'users_txt.admin_user' => 'admin_user_id',

        ];
    }

    public function getUsersTxtAttribute($key)
    {
        $tenant_id = request()->header('tenant_id');
        // $res['suspender_user'] = $this->getAdminUser($this->suspender_id, $tenant_id);
        // $res['recovery_operator_user'] = $this->getAdminUser($this->recovery_operator_id, $tenant_id);
        // $res['admin_user'] = $this->getAdminUser($this->admin_user_id, $tenant_id);
        $res['suspender_user'] = self::_getRedisMap('',$this->suspender_id);
        $res['recovery_operator_user'] = self::_getRedisMap('',$this->recovery_operator_id);
        $res['admin_user'] = self::_getRedisMap('',$this->admin_user_id);
        return $res;
    }

    public function getDiffNumAttribute($key)
    {
        return $this->payable_num - $this->actual_num;
    }

    public function getRemainderAttribute($key)
    {
        round(( time() - strtotime($this->delivery_deadline)) / 60 /60,2) ;
        // return  round((time() - $this->delivery_deadline) / 60 / 60, 2);
    }

    public static function checkData($type, $data, $products)
    {
        // $product_field = ['bar_code','uniq_code','batch_no','sup_id','payable_num','quality_level','quality_type','buy_price'];
        if ($type == 1) {
            //销售单
            $required = [
                'type', 'source_code', 'payable_num', 'warehouse_name', 'buyer_account',
                'shop_name', 'paysuccess_time', 'delivery_deadline', 'order_channel'
            ];
            $field = ['seller_message', 'buyer_message', 'third_no', 'order_platform', 'remark', 'deliver_type', 'deliver_no'];
        }
        if ($type == 2) {
            //调拨单
            $required = ['type', 'source_code', 'payable_num', 'warehouse_name', 'paysuccess_time', 'delivery_deadline'];
            // $field = ['third_no','order_platform','remark','deliver_type','deliver_no'];
        }
        foreach ($products as $pro) {
            if (empty($pro['bar_code'])) return [false, '传入值缺少必要参数'];
        }
        $required_vat = array_filter($required, function ($f) use ($data) {
            return empty($data[$f]);
        });
        if ($required_vat) return [false, '传入值缺少必要参数'];
        return [true, 'success'];
    }

    //新增出库需求单
    public static function add($create, $products, $transcation = true)
    {
        //判断是否有进行中的单据,有的话不重复生成
        $is_exists = self::where('third_no',$create['third_no'])->whereIn('status',[1,2,4])->first();
        if($is_exists) return  [true, '重复操作'];
        $log_data = array_merge($create, ['products' => $products]);
        $request_code = self::getErpCode('CKD');
        $tenant_id = request()->header('tenant_id'); //系统自动执行 ,需要传入
        //判断商品
        if (empty($products)) return [false, __('response.product_not_exists')];

        if (empty($create['delivery_deadline'])) $create['delivery_deadline'] = null;
        if (empty($create['paysuccess_time'])) $create['paysuccess_time'] = null;
        if (empty($create['deliver_type'])) $create['deliver_type'] = '';
        foreach ($products as &$pro) {
            if (empty($pro)) continue;
            if (!isset($pro['buy_price'])) $pro['buy_price'] = 0;
            // unset($pro['buy_price']);
            $pro['request_code'] = $request_code;
            $pro['admin_user_id'] = empty(request()->header('user_id')) ? SYSTEM_ID : request()->header('user_id'); //系统id
            $pro['tenant_id'] = $tenant_id;
            $pro['created_at'] = date('Y-m-d H:i:s');
            //记录库存流水
            foreach (explode(',', $pro['lock_ids']) as $uniq_code) {
                WmsStockLog::add(WmsStockLog::ORDER_CKD, $uniq_code, $request_code);
            }
        }
        $create['request_code'] = $request_code;
        $create['erp_no'] = $create['request_code'];
        $create['status'] = 1;
        $create['request_status'] = 1;
        $create['tenant_id'] = $tenant_id;

        //时间戳转换
        // $create['paysuccess_time']  = strtotime($create['paysuccess_time']);
        // $create['delivery_deadline']  = strtotime($create['delivery_deadline']);
        $create['admin_user_id'] = request()->header('user_id');
        $create['created_at'] = date('Y-m-d H:i:s');
        // dd($create,$products,$log_data['products']);
        if ($transcation) DB::beginTransaction();
        $res['cre_ob'] = self::create($create);
        $res['cre_details'] = DB::table('wms_shipping_detail')->insert($products);

        // 获取预配任务数据
        // $task_data = self::taskData($create, $log_data['products'], $tenant_id, $request_code);

        //记录操作日志
        $res[] =  WmsOptionLog::add(WmsOptionLog::CKD, $request_code, '创建', '出库单创建成功', [$log_data]);

        //创建出库需求单就立刻预配
        // $res[] = (new preAllocationDetail())->preAllocation($task_data);
        $c = array_filter($res, function ($v) {
            return empty($v);
        });
        if (empty($c)) {
            if ($transcation) DB::commit();
            // 创建预配任务
            // self::setObGoodsTask($request_code, $task_data);
            self::setObGoodsTask($request_code,['id'=> $res['cre_ob']->id,'tenant_id'=>$tenant_id]);
            return [true, '创建成功'];
        } else {
            self::delObGoodsTask($request_code);
            if ($transcation) DB::rollBack();
            return [false, __('base.fail')];
        }
    }


    //预配任务所需数据
    public  static function taskData($create, $products, $tenant_id = null, $request_code = null)
    {
        if (empty($tenant_id)) $tenant_id = $create['tenant_id'];
        if (empty($request_code)) $request_code = $create['request_code'];
        $lock_code  = $create['third_no'];
        if($create['type']==1)$lock_code =  $create['source_code'];
        $task_data = [
            'tenant_id' => $tenant_id,
            'condition' => [
                'source_platform' => $create['order_platform'],
                'source_channel' => $create['order_channel'],
                'order_type' => $create['type'],
            ],
            'request_code' => $request_code,
            'lock_code' => $lock_code,
            'warehouse_code' => $create['warehouse_code'],
            'pre_lists' => [
                'type' => 1,
                'origin_type' => $create['type'],
            ],
            'payable_num' => $create['payable_num'],
            'products' => $products,
            'time' => date('Y-m-d H:i:s'),
        ];
        return $task_data;
    }

    //出库单取消
    static function cancel($third_nos)
    {
        if (!is_array($third_nos)) $third_nos = explode(',', $third_nos);
        if (empty($third_nos)) return false;
        // dd($third_nos);
        $logic = new AllocationTask();
        $res = true;
        //查看出库单的发货状态
        $third_nos = self::whereIn('third_no', $third_nos)->whereIn('request_status', [1, 2, 3])->pluck('third_no')->toArray();
        foreach ($third_nos as $third_no) {
            $res = $logic->orderCancel(['third_no' => $third_no]);
            if ($logic->err_msg) log_arr(['msg' => $logic->err_msg], 'wms');
            if (!$res) return $res;
        }
        self::whereIn('third_no', $third_nos)->update(['cancel_at' => date('Y-m-d H:i:s')]);
        return $res;
    }

    //释放库存
    private static function release($request_codes)
    {
        $update = [
            'request_status' => 1,
            'oversold_num' => 0,
            'stockout_num' => 0,
            'cancel_num' => 0,
            'actual_num' => 0,
            'tag' => 0,
        ];
        $detail_update = [
            'ship_code' => '',
            'status' => 0,
            'oversold_num' => 0,
            'stockout_num' => 0,
            'cancel_num' => 0,
            'actual_num' => 0,
            'lock_ids'=>'-1',
        ];

        //获取锁定的库存id
        $model  = (new  ShippingDetail())->whereIn('request_code', $request_codes);
        $lock_ids = implode(',', $model->pluck('lock_ids')->toArray());
        self::whereIn('request_code', $request_codes)->update($update);
        $model->update($detail_update);
        // dd($lock_ids);
        return Inventory::releaseInv($lock_ids);
    }


    //暂停
    public static function pause($ids, $reason, $is_release = false)
    {
        if (is_string($ids)) $ids = explode(',', $ids);
        $item = self::whereIn('id', $ids)->whereIn('status', [1, 2]);
        if ($item->doesntExist()) return [false, __('status.doc_not_pause')];
        $request_codes = $item->pluck('request_code');
        $user_id = request()->header('user_id');
        $time = date('Y-m-d H:i:s');
        self::startTransaction();
        //修改出库单状态
        $update = [
            'status' => 3,
            'paused_reason' => $reason,
            'suspender_id' => $user_id,
            'paused_at' => $time
        ];
        //配货订单的更新数据
        $pre_data = [
            'status' => 3,
            'admin_user_id' => $user_id,
        ];

        //暂停的一些操作
        //判断未进行预配的-redis 中删除任务
        $default = self::whereIn('id', $ids)->where('status', 1)->where('tag', 0);
        if ($default->exists()) {
            //删除任务
            $del_keys = $default->pluck('request_code')->toArray();
        }

        $pre_task = self::whereIn('id', $ids)->whereIn('status', [1, 2])->where('tag', 6)->exists();
        if ($is_release) {
            //出库取消
            $res['cancel'] = self::cancel($request_codes);
            //释放库存
            $res['release'] =  self::release($request_codes);
            //出库单状态修改
            $update['tag'] = 0;
            $pre_data['status'] = 2;
        }

        //判断是否已经预配结束并且生成预配订单
        if ($pre_task) {
            //需要修改预配订单的状态
            //配货订单状态暂停
            $res['pause_prelists'] = (new preAllocationLists())->whereIn('request_code', $request_codes)->update($pre_data);
        }
        $res['pause_ob'] = $item->update($update);
        $c = array_filter($res, function ($v) {
            return empty($v);
        });
        if (empty($c)) {
            DB::commit();
            if (!empty($del_keys)) self::delObGoodsTask($del_keys);
            return [true, ''];
        } else {
            DB::rollBack();
            return [false, __('base.fail')];
        }
    }

    //恢复
    public function recovery($ids, $reason = null)
    {
        if (is_string($ids)) $ids = explode(',', $ids);
        $item = self::whereIn('id', $ids)->where('status', 3);
        if ($item->doesntExist()) return [false, __('status.doc_not_restore')];
        $update_ids = $item->get()->modelKeys();
        $user_id = request()->header('user_id');
        $time = date('Y-m-d H:i:s');
        self::startTransaction();
        //恢复的一些操作
        $pre_log = [];
        //判断是否需要预配
        $pre_task = self::whereIn('id', $ids)->where('status', 3)->where('request_status', 1)->where('tag', 0);
        if ($pre_task->exists()) {
            //执行预配
            foreach ($pre_task->get() as $pre_item) {
                $task_data = self::taskData($pre_item->toArray(), $pre_item->details->toArray());
                $pre_res =  preAllocationDetail::preTask($task_data);
                $pre_log[$pre_item->id] = $pre_res;
            }
        } else {
            //判断是否有配货明细
            $details =  self::whereIn('id', $ids)->where('status', 3)->where('tag', 6);
            if ($details->exists()) {
                //修改配货明细的状态为
                $res['recovery_prelists'] = (new preAllocationLists())->whereIn('request_code', $details->pluck('request_code')->toArray())->update([
                    'status' => 1,
                    'admin_user_id' => $user_id,
                ]);
            }
        }

        $update = [
            'status' => 2,
            'recovery_operator_id' => $user_id,
            'recovery_at' => $time,
        ];
        if ($reason) $update['recovery_reason'] = $reason;
        $res['recovery_ob'] = self::whereIn('id', $update_ids)->update($update);
        return self::endTransaction($res, ['pre_log' => $pre_log]);
    }

    //缺货重配
    public  function reAllocationTask($id)
    {
        $item = $this::find($id);
        if (empty($item)) return [false, __('response.doc_not_exists')];
        $tag = $item->tag;
        $flag = false;
        if ($tag == 1) {
            $over = time() - strtotime($item->created_at) / 60 / 60;
            if ($over > 2) {
                //超时
                $flag = true;
            }
        }
        if (in_array($item->tag, [3, 2, 0, 4])) {
            //系统或仓库缺货
            $flag = true;
        }
        if ($flag) {
            $pro_details  = $item->details->map(function ($item) {
                return $item->attributes;
            })->toArray();
            $task_data = self::taskData($item->toArray(), $pro_details);
            // dd($task_data);
            return  preAllocationDetail::preTask($task_data);
        }
        return [false, __('status.ob_not_re_allocation')];
    }

    public function getAllDetail($data)
    {
        if(!empty($data['id'])) $item = $this::with('details')->find($data['id']);
        if(!empty($data['code'])) $item = $this::with('details')->where('request_code',$data['code'])->orderBy('id','desc')->first();
        // dump($item->toArray());
        if (empty($item)) return [false, __('response.doc_not_exists')];
        //预配明细
        $pre_details =  $this->preDetails($item->request_code);
        //发货明细
        $send_details =  $this->sendDetails($item->request_code);
        //取消明细
        // $send_details =  $this->sendDetails($item->request_code);
        $data = [
            'ob_and_details' => $item->toArray(),
            'pre_details' => $pre_details,
            'send_details' => $send_details,
        ];
        return [true, $data];
    }
}
