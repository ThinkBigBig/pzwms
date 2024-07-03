<?php

namespace App\Models\Admin\V2;


use App\Models\Admin\wmsBaseModel;
use  App\Models\Admin\V2\ArrivalRegist;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;
use App\Logics\wms\QualityControl;
use Illuminate\Support\Facades\Redis;

class RecvOrder extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_recv_order';

    protected static $arrIns = null;
    protected $guarded = [];

    // protected $map = [
    //     'recv_type' => [1 => '采购收货', 2 => '调拨收货', 3 => '退货收货', 4 => '其他收货'],
    //     'doc_status' => [1 => '暂存', 2 => '已审核', 3 => '已作废'],
    //     'recv_status' => [0 => '收货中', 1 => '已完成'],
    //     'recv_methods' => [1 => '逐件收货', 2 => '其他'],
    // ];
    protected $map;

    // protected $with = ['arrItem'];

    protected $appends = ['recv_type_txt', 'doc_status_txt', 'recv_status_txt', 'created_user_txt', 'recv_methods_txt'];

    public function __construct($params = [])
    {
        parent::__construct($params);
        $this->map = [
            'recv_type' => [1 => __('status.buy_recv'), 2 => __('status.transfer_recv'), 3 => __('status.return_recv'), 4 => __('status.other_recv')],
            'doc_status' => [1 => __('status.stash'), 2 => __('status.audited'), 3 => __('status.abolished')],
            'recv_status' => [0 => __('status.receiving'), 1 => __('status.completed')],
            'recv_methods' => [1 => __('status.recv_by_one'), 2 => __('status.other')],
        ];
    }
    public function withInfoSearch($select)
    {
        return $this::with(['arrItem']);
    }
    public function _formatOne($data)
    {
        $data = $data->append(['details_group'])->toArray();
        // self::productFormat($data);
        return $data;
    }

    public function withSearch($select)
    {
        return $this::with(['arrItem']);
    }

    public function searchUser()
    {
        return [
            'created_user' => 'created_user',
        ];
    }


    public function getCreatedUserAttribute($key)
    {
        return  $this->getAdminUser($this->created_user);
    }

    public function arrItem()
    {
        return $this->belongsTo(ArrivalRegist::class, 'arr_id');
    }

    public function details()
    {
        return $this->hasMany(RecvDetail::class, 'recv_id');
    }

    function createUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'created_user');
    }

    //收货单详情汇总
    public function getDetailsGroupAttribute($value)
    {
        $normal = []; //正品
        $flaw = []; //瑕疵
        $data = [];
        foreach ($this->details as $pro) {
            //作废
            if ($this->doc_status == 3) $pro->is_void  = 1;
            else $pro->is_void  = 0;
            $pro->recv_num = 1;
            $bar_code = $pro->bar_code;
            if ($pro->getRawOriginal('quality_type') == 1) $name = 'normal';
            else $name = 'flaw';
            $temp = [
                'sku' => $pro->product->sku,
                'product_sn' => $pro->product->product->product_sn ?? '',
                'name' => $pro->product->product->name ?? '',
                'spec' => $pro->product->spec_one,
                'quality_type' => $pro->quality_type,
                'quality_level' => $pro->quality_level,
            ];
            if (in_array($bar_code, array_keys($$name))) {
                $$name[$bar_code]['recv_num'] += 1;
                $$name[$bar_code]['details'][] = $pro->append('supplier_name')->makeHidden('supplier');
            } else {
                $temp['details'][] = $pro->append('supplier_name')->makeHidden('supplier');
                $temp['recv_num'] = 1;
                $$name[$bar_code] = $temp;
            }
        }
        $data = array_merge(array_values($normal), array_values($flaw));
        return $data;
    }


    public static function getArrIns()
    {
        if (!self::$arrIns) self::$arrIns = new  ArrivalRegist();
        return self::$arrIns;
    }

    public function getArrItem($arr_id)
    {

        $item = $this->getArrIns()::with(['recvOrders' => function ($query) {
            $query->where('doc_status', 1)->where('created_user', request()->header('user_id'))->where('recv_status', 0)->first();
        }])->find($arr_id)->toArray();
        if (empty($item)) return false;
        return $item;
    }

    // public function getRecvId($arr_id){
    //     $item = $this->getArrItem($arr_id);
    //     if(empty($item)) return false;
    //     if(empty($item['recv_orders'][0]['id']))return false;
    //     return $item['recv_orders'][0]['id'];
    // }

    //检查暂存   //判断是否需要创建收货单
    public function checkStash($arr_id, $scan_type = 0)
    {
        return $this::where('arr_id', $arr_id)->where('doc_status', 1)->where('created_user', request()->header('user_id'))->where('recv_status', 0)->where('scan_type', $scan_type)->first();
    }


    //扫描新增
    public function add($data)
    {
        $arr_id = $data['arr_id'];
        $bar_code =  $data['bar_code'];
        $uniq_code =  $data['uniq_code'];
        $recv_methods =  $data['recv_methods'];
        //判断商品条码是否是普通产品
        $spec_type = ProductSpecAndBar::getType($bar_code);
        if ($spec_type == 2) return [false, __('status.bar_is_ordinary')];
        $recv_id =  empty($data['recv_id']) ? 0 : $data['recv_id'];
        $ib_code = empty($data['ib_code']) ? '' : $data['ib_code'];
        $arr_model = new ArrivalRegist();
        $arr_item = $arr_model->find($arr_id);
        $recv_type = empty($data['recv_type']) ? $arr_item->arr_type : $data['recv_type'];

        //检查状态
        list($check, $msg) = $arr_model->recvPreCheck($arr_id, $recv_type);
        if (!$check) return [false, $msg];

        $warehouse_code  =  $arr_item->warehouse_code;
        $lot_num  =  $arr_item->lot_num;
        $return = false;
        //到货登记是退调货时需要先匹配入库单
        if (in_array($recv_type, [2, 3])) {
            $return = true;
            if (empty($ib_code))  return [false, __('response.ib_code_required')];
            if (empty($arr_item->ib_code)) {
                //匹配入库单
                $ib_item = IbOrder::where('ib_code', $ib_code)->first();
                if (!$ib_item) return [false, __('response.ib_order_not_exists')];
                list($match, $msg) = $arr_model->ibMatch($arr_id, $ib_item->id);
                if (!$match) return [false, $msg];
            }
            if (!empty($arr_item->ib_code) && $arr_item->ib_code != $ib_code) return [false,  __('response.ib_code_not_match')];
        }

        //检查是否有暂存
        $recv_item = $this->checkStash($arr_id);
        if ($recv_item && ($recv_id != $recv_item->id)) return [false, 'recv_id参数错误'];
        $this->startTransaction();

        //判断批次号是否正确
        $print_count = $arr_item->uni_num_count;
        if ($print_count == 0 && !$return) return [false, __('response.batch_not_match')];
        if ($return) {
            if (!isset($ib_item)) $ib_item = IbOrder::where('ib_code', $ib_code)->first();
            //退调货
            if ($print_count == 0) {
                $pro_item =  WithdrawUniqLog::where('source_code', $ib_item->third_no)->where('is_scan', 0)->where('uniq_code', $uniq_code)->where('bar_code', $bar_code)->first();
                if (!$pro_item) return  [false, __('response.unicode_not_match')];
            } else {
                if (!UniqCodePrintLog::isUniqCode($uniq_code, $arr_id)) return [false, __('response.batch_code_err')];
            }
        } else {
            if (!UniqCodePrintLog::isUniqCode($uniq_code, $arr_id)) return [false, __('response.batch_code_err')];
        }

        // 检查v2是否收货
        if (WmsReceiveCheck::where(['arr_id' => $arr_id, 'tenant_id' => $arr_item->tenant_id, 'type' => 0, 'uniq_code' => $uniq_code])->first()) return [false, __('response.uniq_scan_repeat')];

        //新品检查
        $sku = '';
        $bar = ProductSpecAndBar::where('bar_code', $bar_code)->first();
        if (!$bar || $bar->product->status != 1) $is_new = true;
        else {
            $is_new = false;
            $sku = $bar->sku;
        }
        //唯一码写入redis 防止短时间内重复扫描
        if (Redis::get($uniq_code)) return [false, __('response.uniq_scan_repeat')];
        Redis::setex($uniq_code, 10, 1);
        if (!$recv_item) {
            //新增
            $recv_code  = $this->getErpCode('SHD', 10);
            $create_data = [
                'arr_id' => $arr_id,
                'recv_type' => $recv_type,
                'recv_methods' => $recv_methods,
                'doc_status' => 1,
                'recv_num' => 1,
                'created_user' => request()->header('user_id'),
                'recv_code' => $recv_code,
                'warehouse_code' => $warehouse_code,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $recv_id =  $this->insertGetId($create_data);
            if (!$recv_id) return [false, '收货单创建失败'];
            $row['log_shd'] = WmsOptionLog::add(WmsOptionLog::SHD, $recv_code, '创建', '收货单创建成功', $create_data);
            //修改登记单的到货状态
            $old = $arr_item->arr_status;
            $arr_item->arr_status = 3;
            if ($old != 3) $row['arr_status'] = $arr_item->save();
            $row['log_djd'] = WmsOptionLog::add(WmsOptionLog::DJD, $arr_item->arr_code, '开始收货', '到货登记单开始收货', $old);
        } else {
            //新增收货数量
            $recv_item->recv_num += 1;
            $row_recv = $recv_item->save();
            if (!$row_recv) return [false, '收货单更新数量失败'];
        }
        $pro_return = [];
        if (isset($pro_item)) {
            $pro_return = [
                //退调单
                'sup_id' => $pro_item->sup_id,
                'buy_price' => $pro_item->buy_price,
                'quality_type' => $pro_item->getRawOriginal('quality_type'),
                'quality_level' => $pro_item->quality_level,
                'inv_type' => $pro_item->inv_type,
                'sup_confirm' => 1,
                'ib_confirm' => 1,
                'ib_id' => $ib_item->id,
            ];
        }
        //暂存
        $pro = [
            'arr_id' => $arr_id,
            'recv_id' => $recv_id,
            'ib_id' => '',
            'bar_code' => $bar_code,
            'uniq_code' => $uniq_code,
            'lot_num' => $lot_num,
            'box_code' => $data['box_code'] ?? '',
            'container_code' => $data['container_code'] ?? '',
            'quality_type' => 1,
            'quality_level' => 'A',
            'warehouse_code' => $warehouse_code,
            'created_user' => request()->header('user_id'),
            'created_at' => date('Y-m-d H:i:s'),
            'ib_at' => date('Y-m-d H:i:s'),
            'sku' => $sku,
        ];
        $pro_date = array_merge($pro, $pro_return);
        //新增明细
        $row['detail_id'] = RecvDetail::insert($pro_date);
        //绑定成功
        $row['uniq_log'] = UniqCodePrintLog::bindBarCode($uniq_code, $arr_id, $bar_code, $return);
        //退调记录更新
        if (isset($pro_item)) {
            $pro_item->is_scan = 1;
            $pro_item->save();
        }
        //新品检查
        // $is_new = ProductSpecAndBar::isNewPro($bar_code);
        log_arr($row, 'wms');
        return $this->endTransaction($row, [['is_new' => $is_new]]);
    }


    //普通产品收货 - 收货即质检
    public function addOrdinary($data)
    {
        if (!isset($data['is_flaw'])) $data['is_flaw'] = 0;
        $quality_type = 1;
        $quality_level = 'A';
        if ($data['is_flaw'] == 1) {
            $quality_type = 2;
            $quality_level = 'B';
        }
        $arr_id = $data['arr_id'];
        $bar_code =  $data['bar_code'];
        //判断商品条码是否是唯一码产品
        $spec_type = ProductSpecAndBar::getType($bar_code);
        if ($spec_type == 1) return [false, __('status.bar_is_uniq')];
        $recv_id =  empty($data['recv_id']) ? 0 : $data['recv_id'];
        $ib_code = empty($data['ib_code']) ? '' : $data['ib_code'];
        $recv_methods = empty($data['recv_methods']) ? 1 : $data['recv_methods'];
        $arr_model = new ArrivalRegist();
        $arr_item = $arr_model->find($arr_id);
        $recv_type = empty($data['recv_type']) ? $arr_item->arr_type : $data['recv_type'];

        //检查状态
        list($check, $msg) = $arr_model->recvPreCheck($arr_id, $recv_type);
        if (!$check) return [false, $msg];

        $warehouse_code  =  $arr_item->warehouse_code;
        $lot_num  =  $arr_item->lot_num;
        $return = false;
        //到货登记是退调货时需要先匹配入库单
        if (in_array($recv_type, [2, 3])) {
            $return = true;
            if (empty($ib_code))  return [false, __('response.ib_code_required')];
            if (empty($arr_item->ib_code)) {
                //匹配入库单
                $ib_item = IbOrder::where('ib_code', $ib_code)->first();
                if (!$ib_item) return [false,  __('response.ib_order_not_exists')];
                list($match, $msg) = $arr_model->ibMatch($arr_id, $ib_item->id);
                if (!$match) return [false, $msg];
            }
            if (!empty($arr_item->ib_code) && $arr_item->ib_code != $ib_code) return [false,  __('response.ib_code_not_match')];
        }

        //检查是否有暂存
        $recv_item = $this->checkStash($arr_id, $data['scan_type']);
        if ($recv_item && ($recv_id != $recv_item->id)) return [false, 'recv_id参数错误'];


        $this->startTransaction();
        //退调
        if ($return) {
            if (!isset($ib_item)) $ib_item = IbOrder::where('ib_code', $ib_code)->first();
            //退调货
            $pro_item =  WithdrawUniqLog::where('source_code', $ib_item->third_no)->where('bar_code', $bar_code)->where('bar_code', $bar_code)->where('is_scan', 0)->first();
            if (!$pro_item) return  [false, __('response.return_pro_err')];
        }

        if (!$recv_item) {
            //新增
            $recv_code  = $this->getErpCode('SHD', 10);
            $create_data = [
                'arr_id' => $arr_id,
                'recv_type' => $recv_type,
                'doc_status' => 1,
                'scan_type' => $data['scan_type'] ?? 0,
                'recv_methods' => $recv_methods,
                'recv_num' => 1,
                'created_user' => request()->header('user_id'),
                'recv_code' => $recv_code,
                'warehouse_code' => $warehouse_code,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $recv_id =  $this->insertGetId($create_data);
            if (!$recv_id) return [false, '收货单创建失败'];
            $row['log_shd'] = WmsOptionLog::add(WmsOptionLog::SHD, $recv_code, '创建', '收货单创建成功', $create_data);
            //修改登记单的到货状态
            $old = $arr_item->arr_status;
            $arr_item->arr_status = 3;
            if ($old != 3) $row['arr_status'] = $arr_item->save();
            $row['log_djd'] = WmsOptionLog::add(WmsOptionLog::DJD, $arr_item->arr_code, '开始收货', '到货登记单开始收货', $old);
            $recv_num = 1;
        } else {
            //新增收货数量
            $recv_num = $recv_item->recv_num + 1;
            $recv_item->recv_num = $recv_num;
            $row_recv = $recv_item->save();
            if (!$row_recv) return [false, '收货单更新数量失败'];
        }

        //生成默认唯一码
        $uniq_code = 'PT' . $lot_num . '-' . $recv_id . ($recv_num + 1);

        $pro_return = [];
        if (isset($pro_item)) {
            $pro_return = [
                //退调单
                'sup_id' => $pro_item->sup_id,
                'buy_price' => $pro_item->buy_price,
                'quality_type' => $quality_type,
                'quality_level' => $quality_level,
                'sup_confirm' => 1,
                'ib_confirm' => 1,
                'inv_type' => $pro_item->inv_type,
                'ib_id' => $ib_item->id,
            ];
        }
        //暂存
        $pro = [
            'arr_id' => $arr_id,
            'recv_id' => $recv_id,
            'ib_id' => '',
            'bar_code' => $bar_code,
            'uniq_code' => $uniq_code,
            'lot_num' => $lot_num,
            'box_code' => $data['box_code'] ?? '',
            'recv_unit' => $data['recv_unit'] ?? 0,
            'container_code' => $data['container_code'] ?? '',
            'quality_type' => $quality_type,
            'quality_level' => $quality_level,
            'is_qc' => 1,
            'warehouse_code' => $warehouse_code,
            'created_user' => request()->header('user_id'),
            'created_at' => date('Y-m-d H:i:s'),
            'ib_at' => date('Y-m-d H:i:s'),
        ];
        $pro_date = array_merge($pro, $pro_return);
        //新增明细
        $row['detail_id'] = RecvDetail::insert($pro_date);
        //退调记录更新
        if (isset($pro_item)) {
            $pro_item->is_scan = 1;
            $pro_item->save();
        }
        //质检单更新记录
        // $row['qc'] = (new QualityControl())->qcReceive(['recv_id' => $recv_id]);
        log_arr($row, 'wms');
        return $this->endTransaction($row);
    }


    //添加库存流水
    public function invLog($uniq_codes, $code)
    {
        //添加库存流水记录
        WmsStockLog::addBtach(WmsStockLog::ORDER_RECEIVE, $uniq_codes, $code);
        // foreach ($uniq_codes as $uniq_code) {
        //     WmsStockLog::add(WmsStockLog::ORDER_RECEIVE, $uniq_code, $code);
        // }
    }

    //收货完成
    public function recvDone($arr_id, $recv_id)
    {
        $item = $this::where('created_user', ADMIN_INFO['user_id'])->find($recv_id);
        if (empty($item)) return [false, __('response.recv_not_exists')];
        if ($item->doc_status == 2) return [false, __('response.recv_done')];
        if ($item->doc_status != 1) return [false, __('response.doc_status_err')];
        if ($item->arr_id != $arr_id) return [false, __('response.params_err')];

        $details = $item->details()->get();
        $bar_codes = $details->pluck('bar_code')->toArray();
        $ordinary = false;
        $spec_type = 1;
        if ($item->scan_type == 1) {
            //普通产品收货
            $ordinary = true;
            $spec_type = 2;
        }

        $code = $item->recv_code;
        $type = $item->recv_type;
        $warehouse_code = $item->warehouse_code;
        $uniq_codes = [];
        $count = 0;
        DB::beginTransaction();
        $third_no = '';
        $user_id = request()->header('user_id');
        foreach ($details as $pro) {
            //判断是否有新品维护
            if (!$pro->product()->first()) return [false, __('response.new_pro')];
            $uniq_codes[] = $pro->uniq_code;
            //本次收货数量
            $count += 1;

            if (in_array($type, [2, 3])) {
                //退调货修改库存
                $inv_update = [
                    'warehouse_code' => $warehouse_code,
                    'arr_id' => $arr_id,
                    'recv_id' => $recv_id,
                    'sku' => $pro->sku ?? $pro->product()->first()->sku,
                    'start_at' => $pro->created_at,
                    'done_at' => date('Y-m-d H:i:s'),
                    'ib_at' => date('Y-m-d H:i:s'),
                    'updated_user' => request()->header('user_id'),
                    'created_user' => request()->header('user_id'),
                    'location_code' => '',
                    'area_code' => 'SHZCQ001',
                    'inv_status' => 0,
                    'sale_status' => 1,
                    'in_wh_status' => 1,
                    'is_qc' => 0,
                    'is_putway' => 0,
                    'lock_code' => '',
                    'lock_type' => 0,
                ];
                if ($ordinary) {
                    $inv_update['area_code'] = 'ZJZCQ001';
                    $inv_update['in_wh_status'] = 2;
                    $inv_update['is_qc'] = 1;
                }
                $res['inv_update'] = Inventory::where('uniq_code', $pro->uniq_code)->update($inv_update);
                // dump($inv_update);
                // dump($pro->uniq_code);

                // $res['total_inv_update'] = Inventory::totalInvUpdate($warehouse_code, $pro->bar_code);

                //退调货修改收货数量
                if (empty($third_no)) {
                    $ib_item = IbOrder::find($pro->ib_id);
                    if ($ib_item) $third_no = $ib_item->third_no;
                    else return [false, __('ib_order_not_exists')];
                }
                if ($type == 2) {
                    $tr_item = TransferDetails::where('tr_code', $third_no)->where('bar_code', $pro->bar_code)->where('sup_id', $pro->sup_id)
                        ->where('quality_level', $pro->quality_level)->where('buy_price', $pro->buy_price)->whereRaw(DB::raw('num > recv_num'))->first();
                    if ($tr_item) $res['tr_detail_update'] = $tr_item->update([
                        'recv_num' => DB::raw('recv_num + 1'),
                        'admin_user_id' => $user_id,
                    ]);
                }
                //入库单明细收货数量
                if ($ib_item) {
                    $ib_detail =  IbDetail::where('ib_code', $ib_item->ib_code)->where('bar_code', $pro->bar_code)->where('sup_id', $pro->sup_id)
                        ->where('quality_level', $pro->quality_level)->where('buy_price', $pro->buy_price)->whereRaw(DB::raw('re_total > rd_total'))->first();
                    if ($ib_detail) $res['ib_detail_update'] = $ib_detail->update([
                        'rd_total' => DB::raw('rd_total + 1'),
                        'admin_user_id' => $user_id,
                    ]);
                }

                // if($type == 3){
                //     if(empty($sale_code)){
                //         $sale_code = WmsOrder::where('third_no',$third_no)->first()->code;
                //         $after_item = AfterSaleOrder::where('origin_code',$sale_code)->where('status',1)->first();
                //         if($after_item)$after_code = $after_item->code;
                //     }
                //     $after_detail_item = WmsAfterSaleOrderDetail::where('origin_code',$after_code)->where('bar_code',$pro->bar_code)->first();
                //     if($after_detail_item) $res['after_detail_item']=$tr_item->update([
                //         'return_num'=>DB::raw('recv_num + 1'),
                //         'admin_user_id'=>$user_id,
                //     ]);
                // }

            }
            //更新总库存
            $where = [
                'quality_type' => $pro->getRawOriginal('quality_type'),
                'quality_level' => $pro->quality_level,
                'bar_code' => $pro->bar_code,
                'warehouse_code' => $pro->warehouse_code,
                'tenant_id' => $pro->tenant_id,
            ];
            Inventory::totalUpdate($where, 1, 1);
        }

        //收货完成
        $time = date('Y-m-d H:i:s');
        $item->doc_status = 2;
        $item->recv_status = 1;
        $item->done_at = $time;
        $res[] = $item->save();

        $update = [
            'area_code' => 'SHZCQ001',
            'done_at' => $time,
            'updated_user' => $user_id,
        ];
        if ($ordinary) {
            $update['area_code'] = 'ZJZCQ001';
        }

        //商品库区改为收货暂存区,状态修改为已收货
        $res['detail_update'] = $item->details()->update($update);


        //增加库存
        if (!in_array($type, [2, 3]) || !$res['inv_update']) {
            if (isset($res['inv_update'])) $res['inv_update'] = 1;
            $res['inv_add'] = Inventory::add($details, $update);
        } else {
            //供应商库存更新
            SupInv::supInvUpdate($uniq_codes);
        }

        //调拨单收货数量
        if ($third_no) {
            $tr_return_num = 0;
            if ($type == 2) {
                $tr_order_item = TransferOrder::where('tr_code', $third_no)->orderBy('id', 'desc')->first();
                if (!$tr_order_item) return [false, '调拨单丢失'];
                $tr_return_num = $tr_order_item->total ?? 0;
                $recv_num = $tr_order_item->recv_num + $count;
                if ($tr_return_num < $recv_num) return [false, '收货数量超出调拨单数量!'];
                $recv_status = $tr_return_num == $recv_num ? 3 : 2;
                $tr_update = [
                    'recv_num' => $recv_num,
                    'recv_status' => $recv_status,
                ];
                if ($tr_return_num == $recv_num) {
                    $tr_update['doc_status'] = 4;
                }
                $res['tr_update'] = $tr_order_item->update($tr_update);
            }
            if ($type == 3) {
                $sale_item = WmsOrder::where('third_no', $third_no)->first();
                if (!$sale_item) return [false, '销售单丢失'];
                $after_order = AfterSaleOrder::where('origin_code', $sale_item->code)->whereIn('status', [2, 4])->first();
                if (!$after_order) return [false, '售后工单不存在'];
                $tr_return_num = $after_order->apply_num ?? 0;
                $res['after_sale_update'] = $after_order->update([
                    'return_status' => 2,
                    'warehouse_code' => $warehouse_code,
                ]);
            }
            //入库单数量更新
            if ($ib_item) {
                $rd_total = $ib_item->rd_total + $count;
                $recv_status = $rd_total == $ib_item->re_total ? 3 : 2;
                $ib_update  = [
                    'rd_total' => $rd_total,
                    'recv_status' => $recv_status,
                    'updated_user' => $user_id,
                ];
                if ($rd_total == $ib_item->re_total) {
                    $ib_update['doc_status'] = 3;
                }
                $res['ib_update'] = $ib_item->update($ib_update);
            }
        }
        //刷新到货登记单收货数量
        $arr_count = $this::where('arr_id', $arr_id)->where('recv_status', 1)->sum('recv_num');

        $arr_item = self::getArrIns()::find($arr_id);
        // $recv_count = $arr_item->recv_num + $count;
        // $arr_item->recv_num = $recv_count;
        $arr_item->recv_num = $arr_count;
        if ($arr_item->arr_status != 3) $arr_item->arr_status = 3;
        if (isset($tr_return_num) && $tr_return_num == $arr_count) {
            // $arr_item->arr_status = 4;
            $arr_item->doc_status = 4;
        }
        if (!in_array($arr_item->arr_type, [2, 3])) $arr_item->confirm_num += $count;

        $res['arr_update'] = $arr_item->save();
        //记录操作日志
        $res['log_djd'] =  WmsOptionLog::add(WmsOptionLog::DJD, $arr_item->arr_code, '收货完成', '到货登记单收货完成', ['recv_id' => $recv_id, 'row' => $res]);
        $res['log_shd'] =  WmsOptionLog::add(WmsOptionLog::SHD, $item->recv_code, '收货完成', '收货单收货完成', ['recv_id' => $recv_id]);
        //修改条码的类型
        $res['spec_type'] = ProductSpecAndBar::updateType($bar_codes, $spec_type);
        // dd($res);
        $c = array_filter($res, function ($v) {
            return empty($v);
        });
        log_arr($res, 'wms');
        if (empty($c)) {
            DB::commit();
            $this->invLog($uniq_codes, $code);
            return [true, ''];
        } else {
            DB::rollBack();
            return [false, __('base.fail')];
        }
    }

    public static function recvInfo($third_no)
    {
        $ib_item = IbOrder::where(
            function ($query) use ($third_no) {
                $query->where('third_no', $third_no)->orWhere('source_code', $third_no);
            }

        )->where('doc_status', '<>', 2)->first();
        if (!$ib_item) return [];
        $recv_detail = RecvDetail::where('ib_id', $ib_item->id)->where('is_cancel', 0)->select('arr_id', 'recv_id', 'warehouse_code', DB::raw('count(*) as total,sum(if(quality_type=1,1,0)) as normal_count,sum(if(quality_type=2,1,0)) as flaw_count'), 'done_at')->groupBy('arr_id', 'recv_id', 'warehouse_code')->get();
        if (!$recv_detail) return [];
        $data = [];
        foreach ($recv_detail as $recv) {
            $recv_item = $recv->recvOrder()->first();
            $data[] = [
                'ib_code' => $ib_item->ib_code,
                'warehouse_name' => self::_getRedisMap('warehouse_map', $ib_item->warehouse_code),
                'recv_code' => $recv_item->recv_code ?? "",
                'recv_at' => $recv->done_at,
                'recv_count' => $recv->total,
                'normal_count' => $recv->normal_count,
                'flaw_count' => $recv->flaw_count,
            ];
        }
        return $data;
    }
}
