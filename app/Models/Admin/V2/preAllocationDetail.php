<?php

namespace App\Models\Admin\V2;


use App\Models\Admin\wmsBaseModel;
use  App\Models\Admin\V2\ArrivalRegist;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromGenerator;

class preAllocationDetail extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'wms_pre_allocation_detail'; //预配明细

    // 配货状态 1-已预配待分组 2-已分组待领取 3-已领取待配货 4-配货中 5-已配货待复核 5-复核中 6-已复核待发货 7-已发货
    const WAIT_GROUP = 1; //已预配待分组
    const WAIT_RECEIVER = 2; //已分组待领取
    const WAIT_ALLOCATE = 3; //已领取待配货
    const ALLOCATING = 4; //配货中
    const WAIT_REVIEW = 5; //已配货待复核
    const REVIEWING = 5; //复核中
    const WAIT_DELIVER = 6; //已复核待发货
    const DELIVERED = 7; //已发货

    // 取消状态 1-已取消待释放库存 2-库存释放完成 3-待重新上架 4-已扫描待上架  5-上架完成 6-已出库等待重新入库
    const CANCEL_WAIT_FREE_STOCK = 1;
    const CANCEL_FREE_STOCK = 2;
    const CANCEL_WAIT_PUTAWAY = 3;
    const CANCEL_PUTAWAY_ING = 4;
    const CANCEL_PUTAWAY = 5;
    const CANCEL_OUT = 6;

    function shippingRequest()
    {
        return $this->hasOne(WmsShippingRequest::class, 'request_code', 'request_code');
    }

    public function getProductCodeAttribute($value)
    {
        $value = $this->product->code;
        return $value ? $value : '';
    }

    public function getDeliverNoAttribute($value)
    {
        $value = $this->shippingRequest->deliver_no;
        return $value ? $value : '';
    }

    public function getThirdNoAttribute($value)
    {
        $value = $this->shippingRequest->third_no;
        return $value ? $value : '';
    }

    function list()
    {
        return $this->hasOne(WmsShippingRequest::class, 'pre_alloction_code', 'pre_alloction_code');
    }

    function taskStrategy()
    {
        return $this->hasOne(WmsTaskStrategy::class, 'code', 'task_strategy_code');
    }

    function property()
    {
        return $this->hasOne(ProductSpecAndBar::class, 'bar_code', 'bar_code');
    }

    function task()
    {
        return $this->hasOne(WmsAllocationTask::class, 'code', 'task_code');
    }

    function recvDetail()
    {
        return $this->hasOne(RecvDetail::class, 'uniq_code', 'uniq_code')->orderBy('id', 'desc');
    }

    function inventory()
    {
        return $this->hasOne(Inventory::class, 'uniq_code', 'uniq_code')->orderBy('id', 'desc');
    }

    protected $strategyIns = null;

    // protected $map = [
    //     'alloction_status' => [1 => '已预配待分组', 2 => '已分组待领取', 3 => '已领取待配货', 4 => '配货中', 5 => '已配货待复核', 6 => '已复核待发货', 7 => '已发货'],

    // ];

    protected $map;
    protected $appends = ['alloction_status_txt', 'users_txt'];

    protected $with = ['product'];


    public function __construct($params = [])
    {
        parent::__construct($params);
        $this->map = [
            'alloction_status' => [1 => __('status.pre_wait_group'), 2 => __('status.group_wait_take'), 3 => __('status.take_wait_dist'), 4 => __('status.in_distribution'), 5 => __('status.wait_review'), 6 => __('status.pending_shipment'), 7 => __('status.shipped')],
        ];
    }
    public function getUsersTxtAttribute($key)
    {
        $tenant_id = request()->header('tenant_id');
        // $res['reviewer_user'] = $this->getAdminUser($this->reviewer_id, $tenant_id);
        // $res['receiver_user'] = $this->getAdminUser($this->receiver_id, $tenant_id);
        // $res['sup_user'] = Supplier::where(['id'=>$this->sup_id,'tenant_id'=>$tenant_id])->value('name');
        $res['reviewer_user'] = self::_getRedisMap('user_map', $this->reviewer_id);
        $res['receiver_user'] = self::_getRedisMap('user_map', $this->receiver_id);
        $res['sup_user'] = self::_getRedisMap('sup_map', $this->sup_id);

        return $res;
    }

    public function supplier()
    {
        return $this->hasOne(Supplier::class, 'id', 'sup_id');
    }

    public function warehouse()
    {
        return $this->hasOne(Warehouse::class, 'warehouse_code', 'warehouse_code');
    }

    public function product()
    {
        return $this->belongsTo(ProductSpecAndBar::class, 'bar_code', 'bar_code');
    }

    // public function withSearch($select)
    // {
    //     return $this::with(['product'])->select($select);
    // }
    //执行预配任务
    public static  function preTask($task_data)
    {
        $request_code = $task_data['request_code'];
        $ob_model = (new ObOrder())::where('request_code', $request_code)->first();
        //查询是否已经预配过
        if (empty($ob_model)) return [false,  __('status.doc_not_exists')];
        if ($ob_model->status == 2 && $ob_model->tag == 6) return [false, __('status.pre_done')];
        if ($ob_model->status == 5) {
            return [false, __('status.doc_canceled')];
        }
        DB::beginTransaction();
        //修改入库单tag
        list($res, $pre_data) = self::preAllocation($task_data);
        // dump($pre_data);
        log_arr([$request_code => $task_data, 'pre_res' => [$res, $pre_data]], 'wms_allocation_task');

        if ($res === true) {
            //预配成功

            $pre_lists = $pre_data['pre_lists'];
            $unlock_ids = array_values(array_diff($pre_data['unlock_ids'], $pre_data['lock_ids']));
            $lock_ids = array_values(array_diff($pre_data['lock_ids'], $pre_data['unlock_ids']));
            if (!empty($unlock_ids)) $row['unlock'] = Inventory::releaseInv($unlock_ids);
            if (!empty($lock_ids)) {
                $lock_type = $ob_model->type;
                $lock_code =  $ob_model->type == 1 ? $ob_model->source_code : $ob_model->third_no;
                $row['lock'] = Inventory::lockInv($lock_ids, $lock_type, $lock_code);
            }
            //写入预配明细
            // $row['pre_detail'] = self::add($pre_detail);
            //写入配货订单
            $row['add_lists'] = preAllocationLists::add($pre_lists);

            //修改出库单状态
            $ob_update = [
                'status' => 2,
                'request_status' => 1,
                'tag' => 6,
                'stockout_num' => 0,
                'oversold_num' => 0,

            ];
            $ob_update_detail = [
                'status' => 1,
            ];
            // if()
            //明细状态
            $row['update_ob_detail'] = $ob_model->details()->update($ob_update_detail);
            //修改状态;
            $row['update_ob'] = $ob_model->update($ob_update);
            $pre_msg = [true, '预配成功'];
        } else {
            //预配失败
            $update_data = [];
            list($tag, $data) = $pre_data;
            $update_data['tag']  =  $tag;
            //超卖
            if ($tag == 2) {
                $update_data['oversold_num'] = $data[0];
                $msg = $data[1];
            }
            //系统缺货
            elseif ($tag == 3) {
                $update_data['stockout_num'] = $data[0];
                $msg = $data[1];
            } else {
                $msg = $data;;
            }
            $row['update_ob'] = $ob_model->update($update_data);
            $pre_msg  = [false, __('response.pre_fail') . $msg];
        }
        $c = array_filter($row, function ($v) {
            return empty($v);
        });
        if (empty($c)) {
            DB::commit();
            self::delObGoodsTask($task_data['request_code']);
            return $pre_msg;
        } else {
            DB::rollBack();
            return [false, '预配失败'];
        }
    }

    //根据配货策略生成预配明细
    private  static  function  preAllocation($task_data)
    {
        // dd($task_data);
        $tenant_id = $task_data['tenant_id'];
        $warehouse_code = $task_data['warehouse_code'];
        $products = empty($task_data['products']) ? [] : $task_data['products'];
        if (empty($products) || !is_array($products[0])) return [false, [4, __('response.product_info_lack')]];

        //查询策略
        // 'source_channel','source_platform','warehouse_code,order_type'
        $to_strategy = PreAllocationStrategy::findStrategy($warehouse_code, $task_data['condition']);
        $pre_num = 0;
        $sku_num = [];
        $pre_details = [];
        $ob_item = ObOrder::where('request_code', $task_data['request_code'])->where('status', 1)->first();
        if (!$ob_item) return [false, '单据不存在'];
        $lock_code = $ob_item->third_no;
        if ($ob_item->type == 1) $lock_code = $ob_item->source_code;
        $unlock_ids = Inventory::where('lock_code', $lock_code)->pluck('id')->toArray();
        $inv_ids = [
            'lock_ids' => [],
            'unlock_ids' => $unlock_ids,
            're_lock_ids' => [],
            'lock_code' => $lock_code,
        ];

        foreach ($products as $pro) {
            $field = ['bar_code', 'payable_num', 'quality_level'];
            if (empty($pro['request_code'])) $pro['request_code'] = $task_data['request_code'];
            $required_vat = array_filter($field, function ($f) use ($pro) {
                return empty($pro[$f]);
            });
            // dd($pro);
            if ($required_vat) return [false, [4, '传入值缺少必要参数']];
            $pre_num  +=  $pro['payable_num'];
            if (!in_array($pro['bar_code'], $sku_num)) $sku_num[] = $pro['bar_code'];

            if ($to_strategy !== false) {
                $good_trategy  = $to_strategy[0];
                //判断商品分类是否符合
                if (is_array($good_trategy)) {
                    if (empty($pro['product_category'])) {
                        //查找商品分类id
                        $cid = ProductSpecAndBar::getCategoryId($pro['bar_code']);
                        $pro['product_category'] = $cid;
                    }
                    if ($good_trategy[0] == 'in') {
                        if (!in_array($pro['product_category'], $good_trategy[1])) $good_trategy = false;
                    }
                    if ($good_trategy[0] == 'not in') {
                        if (in_array($pro['product_category'], $good_trategy[1])) $good_trategy = false;
                    }
                }
                //如果条件是全部或者商品符合条件
                if ($good_trategy !== false) {
                    //执行策略
                    extract($to_strategy[1]);
                    $strage_res = PreAllocationStrategy::toStrategy($content, $type, $startegy_code);
                    // dump('按策略配货');
                    // dd($tenant_id,$warehouse_code,$pro,$strage_res);
                    // dd($pro);
                    $pre_detail = self::startAllocation($tenant_id, $warehouse_code, $pro, $inv_ids, $strage_res);

                    // dd($strage_res);
                }
            } else {
                //不存在策略
                // dump('无策略配货');
                $pre_detail = self::startAllocation($tenant_id, $warehouse_code, $pro, $inv_ids);
            }
            if ($pre_detail[0] === false) {
                if (isset($inv_ids['ob_detail_id'])) {
                    DB::transaction(function ()  use ($pre_detail, $inv_ids) {
                        //超卖
                        if ($pre_detail[1][0] == 2) {
                            $d_update['oversold_num'] =  $pre_detail[1][1][0];
                        }
                        //系统缺货
                        if ($pre_detail[1][0] == 3) {
                            $d_update['stockout_num'] = $pre_detail[1][1][0];
                        }
                        //更新明细
                        ShippingDetail::where('id', $inv_ids['ob_detail_id'])->update($d_update);
                    });
                }
                return  $pre_detail;
            } else {
                if (isset($inv_ids['ob_detail_id'])) {
                    DB::transaction(function ()  use ($pre_detail, $inv_ids) {
                        $d_update = [
                            'oversold_num' => 0,
                            'stockout_num' => 0,
                        ];
                        //更新明细
                        ShippingDetail::where('id', $inv_ids['ob_detail_id'])->update($d_update);
                    });
                }
                $pre_details = array_merge($pre_details, $pre_detail[1]);
            }
        }


        //预配成功记录结果

        $pre_lists = [
            'details' => $pre_details,
            'sku_num' => count($sku_num),
            'pre_num' => $pre_num,
            'request_code' => $task_data['request_code'],
            'type' => $task_data['pre_lists']['type'],
            'origin_type' => $task_data['pre_lists']['origin_type'],
            'tenant_id' => $tenant_id,
            'warehouse_code' => $warehouse_code,
        ];

        $return_data = [
            'pre_lists' => $pre_lists,
            'lock_ids' => $inv_ids['lock_ids'],
            'unlock_ids' => $inv_ids['unlock_ids'],
        ];

        return  [true, $return_data];
    }

    //开始配货
    protected static function startAllocation($tenant_id, $warehouse_code, $product, &$inv_ids, $strategy = [])
    {
        if ($product['lock_ids'] == -1 || $product['lock_ids'] == "") {
            //取消的订单重写lock_ids 
            $inv_ids['re_lock_ids'] = $product['id'];
        }
        if (isset($product['id'])) $inv_ids['ob_detail_id'] = $product['id'];
        // $unlock_ids = empty($product['lock_ids']) ? [] : explode(',', $product['lock_ids']);
        $lock_code =  $inv_ids['lock_code'];
        // $inv_ids['unlock_ids'] = array_merge($inv_ids['unlock_ids'], $unlock_ids);
        $bar_code = $product['bar_code'];
        $quality_level = $product['quality_level'];
        $count = $product['payable_num'];
        $res_data = [
            // 'pre_num_all' => $count,
            'tenant_id' => $tenant_id,
            'bar_code' => $bar_code,
            'request_code' => $product['request_code'],
        ];
        $actual = [];
        $model = DB::table('wms_inv_goods_detail')->where('tenant_id', $tenant_id)->where('warehouse_code', $warehouse_code)->where('bar_code', $bar_code)->where('quality_level', $quality_level);
        $warehoust_count = $model->count();
        if ($warehoust_count < $count) return [false, [2, [$count - $warehoust_count, __('response.pro_lack') . $bar_code]]];

        //这里需要 加上之前锁定的库存
        $inv = $model->where('is_putway', 1)->where(function ($query) use ($lock_code) {
            $query->Where('lock_code', $lock_code)->orWhere(function ($sub_qurey) {
                $sub_qurey->orWhere('sale_status', 1)->where('location_code', '<>', '');
                // if (empty($unlock_ids)) $qurey->where('sale_status', 1);
                // else $qurey->orWhere('lock_code', $lock_code)
                //     // ->whereIn('id', $unlock_ids)
                //     ->orWhere('sale_status', 1)->where('location_code', '<>', '');
            });
        });
        if (!empty($product['batch_no'])) {
            //给定批次号
            $batch_no = $product['batch_no'];
            $res_data['batch_no'] = $batch_no;
            $inv->where('lot_num', $batch_no);
        }
        if (!empty($product['sup_id'])) {
            //给定供应商
            $sup_id = $product['sup_id'];
            $res_data['sup_id'] = $sup_id;
            $inv->where('sup_id', $sup_id);
        }
        if (!empty($product['uniq_code'])) {
            //给定唯一码
            $uniq_code = $product['uniq_code'];
            $res_data['uniq_code'] = $uniq_code;
            $inv->where('uniq_code', $uniq_code);
        }
        // if (!empty((float)$product['buy_price'])) {
        if ($product['buy_price'] != 0) {
            //给定成本价
            $buy_price = $product['buy_price'];
            $res_data['buy_price'] = $buy_price;
            $inv->where('buy_price', $buy_price);
        } else {
            if (!empty($product['sup_id'])) {
                //给定成本价
                $buy_price = $product['buy_price'];
                $res_data['buy_price'] = $buy_price;
                $inv->where('buy_price', $buy_price);
            }
        }

        // 按库区策略配货
        if (!empty($strategy['where'])) {
            // dump('按库区策略配货');
            $tmp_inv = $inv;
            $res_data['startegy_code'] = $strategy['startegy_code'];
            foreach ($strategy['where'] as $where) {
                if ($count == 0) break;
                // $items = $tmp_inv->where(...$where)->orderBy('location_code')->orderBy('lot_num')->orderBy('ib_at')->limit($count)->get();
                $items = $tmp_inv->where(...$where)->orderBy('ib_at')->limit($count)->get();
                $pre_detail = self::preDetail($actual, $items, $quality_level, $count, $inv_ids, $strategy['startegy_code']);
            }
        }
        // 按库龄或位置码策略配货
        if (!empty($strategy['order'])) {
            // dump('按库龄或位置码策略配货');
            $tmp_inv = $inv;
            foreach ($strategy['order'] as $order) {
                $tmp_inv->orderBy(...$order);
            }
            $items = $tmp_inv->limit($count)->get();
            $pre_detail = self::preDetail($actual, $items, $quality_level, $count, $inv_ids, $strategy['startegy_code']);
        }
        // 无策略配货
        // if ($count != 0) {  //未找到商品执行默认策略
        if (empty($strategy)) {
            // dump('无策略配货');
            $tmp_inv = $inv;
            // $items =  $tmp_inv->orderBy('location_code')->orderBy('lot_num')->orderBy('ib_at')->limit($count)->get();
            // dd($tmp_inv->orderBy('ib_at')->limit($count)->toSql());
            $items =  $tmp_inv->orderBy('ib_at')->limit($count)->get();
            // dd($tmp_inv->orderBy('ib_at')->limit($count)->getBindings())
            $pre_detail = self::preDetail($actual, $items, $quality_level, $count, $inv_ids);
        }
        if ($count != 0) {
            if ($strategy)  return [false, [3, [$count, __('response.strategy_system_lack')]]];
            return [false, [3, [$count, __('response.system_lack')]]];
        }

        //预配条码质量维度汇总明细  $actual =>汇总明细
        // if(!empty($actual['uniq_code'])){
        //     $all = $actual['uniq_code'];
        // }
        // if(!empty($actual['location_code'])){
        //     foreach( $actual['location_code'] as $lot_num){
        //         foreach($lot_num as $location){
        //             $all[] = $location;
        //         }
        //     }
        // }

        //一对一明细
        $all = $pre_detail['uniq_detail'];

        $detail = [];
        foreach ($all  as $lot => $v) {
            $temp =  array_merge($v, $res_data);
            if (empty($temp['batch_no'])) $temp['batch_no'] = $lot;
            $detail[] = $temp;
        }
        return [true, $detail];

        // dd($tmp_inv->toSql());

    }

    //配货明细-预配明细
    protected static function preDetail(&$actual, $items, $quality_level, &$count, &$inv_ids, $startegy_code = null)
    {
        $temp_lock_ids = [];
        $uniq_detail = []; //唯一码维度明细
        if ($items->isNotEmpty()) {
            $actual['uniq_code'] = []; //瑕疵明细
            $actual['location_code'] = []; //汇总明细
            foreach ($items as $item) {
                if ($count == 0) break;
                $pre_inv_id = $item->id; //预配时锁定的唯一码
                $inv_ids['lock_ids'][] = $pre_inv_id;
                $temp_lock_ids[] = $pre_inv_id;
                $location_code = $item->location_code; //预配位置码
                if (empty($location_code)) continue;
                $batch_no = $item->lot_num;
                //该条件有货
                if ($quality_level  != 'A') {
                    //瑕疵品需匹配唯一码
                    $temp = [
                        'count' => 1,
                        'batch_no' => $batch_no,
                        'location_code' => $item->location_code,
                        'uniq_code' => $item->uniq_code,
                        'pre_inv_id' => $pre_inv_id,
                        'quality_level' => $quality_level,
                        'buy_price' => $item->buy_price,
                        'sup_id' => $item->sup_id,
                        'quality_type' => $item->quality_type,
                    ];
                    if ($startegy_code) $temp['startegy_code'] = $startegy_code;
                    $actual['uniq_code'][$item->uniq_code] = $temp;
                    $uniq_detail[] = $temp;
                    $count -= 1;
                } else {
                    $temp = &$actual['location_code'];
                    if (empty($temp[$batch_no])) $temp[$batch_no] = [];
                    if (empty($temp[$batch_no][$location_code])) {
                        $temp[$batch_no][$location_code]['count'] = 1;
                    } else {
                        $temp[$batch_no][$location_code]['count'] += 1;
                    }
                    $temp[$batch_no][$location_code]['sup_id'] = $item->sup_id;
                    $temp[$batch_no][$location_code]['buy_price'] = $item->buy_price;
                    $temp[$batch_no][$location_code]['pre_inv_id'] = $pre_inv_id;
                    $temp[$batch_no][$location_code]['location_code'] = $location_code;
                    $temp[$batch_no][$location_code]['batch_no'] = $batch_no;
                    $temp[$batch_no][$location_code]['quality_level'] = $quality_level;
                    $temp[$batch_no][$location_code]['quality_type'] = $item->quality_type;
                    if ($startegy_code) $temp[$batch_no][$location_code]['startegy_code'] = $startegy_code;
                    $temp[$batch_no][$location_code]['count'] = 1;
                    $uniq_detail[] = $temp[$batch_no][$location_code];
                    $count -= 1;
                }
            }
        }
        if ($inv_ids['unlock_ids'] == [-1] || $inv_ids['unlock_ids'] == []) {
            //重新填入预配库存
            $ob_detail_id = $inv_ids['re_lock_ids'];
            $re_lock_ids = implode(',', $temp_lock_ids);
            ShippingDetail::where('id', $ob_detail_id)->update(['lock_ids' => $re_lock_ids]);
            $inv_ids['unlock_ids'] = [];
        }
        return ['sum_detail' => $actual, 'uniq_detail' => $uniq_detail];
    }


    //新增预配明细
    public static function add($data)
    {
        // foreach($data as $cre){
        //     $temp['bar_code'] = $cre['bar_code'];
        //     $temp['uniq_code'] = empty($cre['uniq_code'])?'':$cre['uniq_code'];
        //     $temp['pre_alloction_code'] = empty($cre['pre_alloction_code'])?'':$cre['pre_alloction_code'];
        //     $temp['startegy_code'] = empty($cre['startegy_code'])?'':$cre['startegy_code'];
        //     $temp['request_code'] = empty($cre['request_code'])?'':$cre['request_code'];
        //     $temp['sup_id'] = $cre['sup_id'];
        //     $temp['pre_num'] = $cre['count'];
        //     $temp['location_code'] = $cre['location_code'];
        //     $temp['actual_num'] = $cre['count'];
        //     $temp['batch_no'] = $cre['batch_no'];
        //     $temp['remark'] = '系统预配';
        //     $temp['pre_inv_id'] = $cre['pre_inv_id'];
        //     $temp['tenant_id'] = $cre['tenant_id'];
        //     $temp['created_at'] = date('Y-m-d H:i:s');
        //     $create_data[] = $temp;
        // }
        // return self::insert($create_data);
        return self::insert($data);
        // dd($data);
        // return DB::table('wms_pre_allocation_detail')->insert($data);

    }

    //新的预配
    protected static function pre_task($id)
    {
        //获取数据
        $ob_item =  ObOrder::find($id);
        if (!$ob_item) return [false, '不存在'];
        request()->headers->set('tenant_id', $ob_item->tenant_id);
        // dd(request()->header('tenant_id'));
        $details = $ob_item->details()->get();
        //策略匹配
        $strategy  = PreAllocationStrategy::where('warehouse_code', $ob_item->warehouse_code)->where('status', 1)->orderBy('sort', 'desc')->orderBy('created_at', 'desc')->get();
        if ($strategy->isNotEmpty()) {
            $source_platform = $ob_item->order_platform;
            $source_channel = $ob_item->order_channel;
            $order_type = $ob_item->type;
            foreach ($strategy as $s) {
                $is_match = true;
                $category = false;
                $sku = [];
                $cond = $s->condition;
                // dd($s->content);
                foreach ($cond as $key => $con) {
                    if (empty($con[1])) unset($cond[$key]);
                }
                if ($cond) {
                    // dd($cond,$ob_item->toArray());
                    foreach ($cond as $k => $v) {
                        if ($k == 'product_category') {
                            $category = true;
                            $c_where = 'whereIn';
                            if ($v[0] == 'not in') $c_where = 'whereNotIn';
                            $product_ids = Product::$c_where('category_id', $v[1])->pluck('id')->toArray();
                            $sku = ProductSpecAndBar::whereIn('product_id', $product_ids)->pluck('bar_code', 'sku');
                            continue;
                        }

                        if ($v[0] == 'in') {
                            if (!in_array($$k, $v[1])) {
                                $is_match = false;
                                break;
                            }
                        }
                        if ($v[0] == 'not in') {
                            if (in_array($$k, $v[1])) {
                                $is_match = false;
                                break;
                            }
                        }
                    }
                }
                if (!$is_match) continue;
                //匹配规则,执行策略
                $content = $s->content;
                $order = [];
                $where = [];
                if (in_array($s->type, [1, 3])) {
                    $v = $content[0]['val'];
                    if ($v == 1)  $order[] = ['created_at', 'asc']; //先进先出
                    if ($v == 2)  $order[] = ['created_at', 'desc'];  //先进后出
                    if ($v == 3)  $order[] = ['location_code', 'asc'];  //优先清空位置码
                    if ($v == 4)  $order[] = ['pick_number', 'asc'];  //按拣货顺序清空位置码
                }
                if ($s->type == 2) {
                    foreach ($content as $v) {
                        $op = $v['operation'] == 'eq' ? '=' : '<>';
                        $temp = [
                            'area_code',
                            $op,
                            trim($v['val'])
                        ];
                        if (!empty($where[$v['sort']])) $where[$v['sort'] . '-'] = $temp;
                        else $where[$v['sort']] = $temp;
                    }
                    krsort($where);
                }
                // dd($order,$where);
            }
        } else {
            //无规则,开始预配

        }
    }

    //开始预配
    private static function _startAllocate($ob_item)
    {
        foreach ($ob_item->details()->get() as $item) {
        }
    }
}
