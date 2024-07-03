<?php

namespace App\Models\Admin\V2;

use App\Logics\RedisKey;
use App\Logics\wms\Consigment;
use App\Models\Admin\wmsBaseModel;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\Warehouse;
use App\Models\Admin\V2\UniqCodePrintLog;
use App\Models\Admin\V2\WmsOptionLog;
use App\Models\Admin\V2\RecvOrder;
use App\Models\Admin\V2\WmsPutawayDetail;
use App\Models\Admin\V2\WmsPutawayList;
use App\Models\Admin\V2\WmsQualityConfirmList;
use App\Models\Admin\V2\WmsQualityDetail;
use App\Models\Admin\V2\IbOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ArrivalRegist extends wmsBaseModel
{
    use HasFactory;
    // use SoftDeletes;
    protected $table = 'wms_arrival_regist';
    // public $unique_field =['warehouse_code','warehouse_name'];
    protected $appends = [
        'warehouse', 'log_product', 'doc_status_txt', 'arr_type_txt',
        'arr_status_txt', 'ib_type_txt', 'pur_cost_txt', 'color', 'bg_color'
    ];
    //'arr_details_group', 
    // protected $map = [
    //     'arr_type' => [1 => '采购到货', 2 => '调拨到货', 3 => '退货到货', 4 => '其他到货'],
    //     'doc_status' => [1 => '已审核', 2 => '已取消', 3 => '已作废', 4 => '已确认'],
    //     'arr_status' => [1 => '待匹配', 2 => '待收货', 3 => '收货中', 4 => '已完成'],
    // ];

    protected $map;
    protected $guarded = [];
    protected static $detailIns = null;

    protected static $recvOrderIns = null;


    public function __construct($params = [])
    {
        parent::__construct($params);
        $this->map = [
            'arr_type' => [1 => __('status.buy_arr'), 2 => __('status.transfer_arr'), 3 => __('status.return_arr'), 4 => __('status.other_arr')],
            'doc_status' => [1 => __('status.audited'), 2 => __('status.canceled'), 3 => __('status.abolished'), 4 => __('status.confirmed')],
            'arr_status' => [1 => __('status.wait_match'), 2 => __('status.wait_recv'), 3 => __('status.receiving'), 4 => __('status.completed')],
            'ib_type' => [1 => __('status.buy_ib'), 2 => __('status.transfer_ib'), 3 => __('status.return_ib'), 4 => __('status.other_ib')],
        ];
    }
    public function details()
    {
        return $this->hasManyThrough(RecvDetail::class, RecvOrder::class, 'arr_id', 'recv_id');
    }

    public function logProduct()
    {
        return $this->hasOne(WmsLogisticsProduct::class,  'product_code', 'log_product_code');
    }

    public function invDetails()
    {
        return $this->hasMany(Inventory::class, 'arr_id');
    }

    public function recvOrders()
    {
        return $this->hasMany(RecvOrder::class, 'arr_id',);
    }
    public function ibOrder()
    {
        return $this->hasMany(IbOrder::class, 'arr_id')->where('doc_status','<>',2);
    }

    public function ibAndArr()
    {
        return $this->hasMany(IbAndArr::class, 'arr_code', 'arr_code');
    }

    public function searchUser()
    {
        return [
            'users_txt.created_user' => 'created_user',
            'users_txt.updated_user' => 'updated_user',
        ];
    }

    public function getUsersTxtAttribute($key)
    {
        $res['created_user'] = $this->_getRedisMap('user_map', $this->created_user) ?? '';
        $res['updated_user'] = $this->_getRedisMap('user_map', $this->updated_user) ?? '';
        return $res;
    }


    public function getColorAttribute($key)
    {
        if ($this->doc_status == 2) return self::COLOR['red'];
        if ($this->doc_status == 4) return self::COLOR['green'];
        return '';
    }


    public function getBgColorAttribute($key)
    {
        if ($this->doc_status == 1 && $this->confirm_num != 0) return self::COLOR['yellow'];
        return '';
    }

    public function getArrDetailsGroupAttribute($value)
    {
        $recv_ids = self::recvOrderIns()->where('arr_id', $this['id'])->where('doc_status', 2)->where('recv_status', 1)->get()->modelKeys();
        return self::detailIns()->recvDetailList($recv_ids);
    }

    //采购成本显示
    public function getPurCostTxtAttribute($value)
    {
        if ($this->is_pur_cost == 0) {
            return '';
        }
        if ($this->is_pur_cost == 1) {
            return $this->pur_cost;
        }
        if ($this->is_pur_cost == 2) {
            return __('status.cost_null');
        }
    }
    public static function detailIns()
    {
        if (empty(self::$detailIns)) self::$detailIns = new RecvDetail();
        return self::$detailIns;
    }

    public static function recvOrderIns()
    {
        if (empty(self::$recvOrderIns)) self::$recvOrderIns = new RecvOrder();
        return self::$recvOrderIns;
    }

    public function warehouse()
    {
        return $this->hasOne(Warehouse::class, 'warehouse_code', 'warehouse_code');
    }

    public function  getWarehouseAttribute($value)
    {
        return (new Warehouse)->getName($this['warehouse_code']);
    }

    public function  getIbTypeTxtAttribute($value)
    {
        // if (!empty($this->ibOrder)) return $this->ibOrder[0]['ib_type_txt'];
        $res = empty($this->map['ib_type'][$this->arr_type]) ? '' : $this->map['ib_type'][$this->arr_type];
        return $res;
    }


    public function searchSku($v)
    {
        $bar_codes = ProductSpecAndBar::where('sku', $v)->pluck('bar_code')->toArray();
        $codes = RecvDetail::whereIn('bar_code', $bar_codes)->pluck('arr_id')->toArray();
        return ['id', $codes];
    }

    public function searchProductSn($v)
    {
        $product_ids = Product::where('product_sn', $v)->pluck('id')->toArray();
        $bar_codes = ProductSpecAndBar::whereIn('product_id', $product_ids)->pluck('bar_code')->toArray();
        $codes = RecvDetail::whereIn('bar_code', $bar_codes)->pluck('arr_id')->toArray();
        return ['id', $codes];
    }

    public function withSearch($select)
    {
        return $this::with(['logProduct' => function ($query) {
            $query->where('status', 1)->orderBy('id', 'desc')
                ->select('product_name', 'product_code', 'company_code');
        }]);
    }

    public function  getOptionInfoAttribute($key)
    {
        $list = WmsOptionLog::list(WmsOptionLog::DJD, $this->arr_code)->toArray();
        return $list;
    }

    public function  _formatListObj($data)
    {
        $data->append('users_txt');
        return $data;
    }

    public function BaseOne($where = [], $select = ['*'], $order = [['id', 'desc']])
    {
        $data = $this::with(['logProduct' => function ($query) {
            $query->where('status', 1)->orderBy('id', 'desc')
                ->select('product_name', 'product_code', 'company_code');
        }])->select($select);;
        //处理条件
        foreach ($where as $v) {
            if (empty($v[0]) || empty($v[1])) continue;
            if ($v[1] == 'in' || $v[1] == 'IN') $data->whereIn($v[0], $v[2]);
            if ($v[1] == 'like') $data->where($v[0], $v[1], '%' . $v[2] . '%');
            if (in_array($v[1], $this->whereSymbol)) $data->where($v[0], $v[1], $v[2]);
        }
        //处理排序
        foreach ($order as $Ok => $Ov) {
            if (empty($Ov[0]) || empty($Ov[1])) continue;
            $data->orderBy($Ov[0], $Ov[1]);
        }
        $reData = $data->first();
        if (empty($reData) || $reData == NULL)
            return [];
        else
            $reData->created_user =  $reData->usersTxt['created_user'];
        $reData->updated_user =  $reData->usersTxt['updated_user'];
        $res = $reData->arr_details_group;
        $reData->customColumns = $res['customColumns'];
        if ($reData->ibAndArr->isEmpty()) {
            $reData->ibOrder;
        } else {
            $reData->ib_order = $reData->ibAndArr->append('ib_type_txt')->toArray();
            $reData->makeHidden('ibAndArr');
        }
        $reData->append('option_info');
        $reData = $reData->toArray();
        $reData['arr_details_group'] = $res['list'];
        return objectToArray($reData);
    }

    public function getLotNum($arr_code)
    {
        $arr = $this::where('arr_code', $arr_code)->select('lot_num', 'uni_num_count', 'warehouse_code')->get();
        // if($arr->isEmpty()) return ['code'=>500,'msg'=>'到货登记编码不存在!'];
        if ($arr->count() > 1) return ['code' => 500, 'msg' => '到货登记编码存在重复,请联系管理员!'];
        $arr = $arr->first()->toArray();
        if (empty($arr['lot_num'])) return ['code' => 500, 'msg' => '批次号不存在,请联系管理员!'];
        $lot_num = $arr['lot_num'];
        if ($this::where('lot_num', $lot_num)->count() > 1) return ['code' => 500, 'msg' => '批次号重复,请联系管理员!'];
        $last_count = $arr['uni_num_count'];
        return ['code' => 200, 'data' => ['lot_num' => $lot_num, 'last_count' => $last_count, 'warehouse' => $arr['warehouse']]];
    }



    public  function addUniqCount($arr_id, $count)
    {

        $item = $this::where('id', $arr_id)->first();
        if (empty($item)) return ['code' => 500, 'msg' => __('status.arr_not_exists')];
        if ($item->arr_status == 4) return ['code' => 500, 'msg' => __('status.arr_done')];
        if (in_array($item->arr_type, [2, 3])) return ['code' => 500, 'msg' => __('status.uniq_print_err')];

        $ln_res = $this->getLotNum($item->arr_code);
        if ($ln_res['code'] != 200) return $ln_res;

        $warsehouse = $item->warehouse;
        if (empty($warsehouse)) return ['code' => 500, 'msg' => __('status.warehouse_not_exists')];
        $start = $item->uni_num_count;
        $start += 1;
        $end = (int)$start + $count;
        $lot_num = $item->lot_num;
        //记录唯一码日志
        $data = UniqCodePrintLog::add($arr_id, $item->arr_code, $warsehouse, $start, $end, $lot_num);
        if (!$data) return ['code' => 500, 'msg' => '唯一码创建出错!'];
        $item->uni_num_count += $count;
        $res = $item->save();
        if (!$res)  return ['code' => 500, 'msg' => '到货登记更新出错!'];
        return ['code' => 200, 'data' => $data];
    }


    //作废
    public function cancel($id)
    {
        $item = $this::find($id);
        if (empty($item)) return [false, '登记单不存在!'];
        //判断是否上架 上架不支持作废
        //登记单包含的唯一码
        // $uniq_codes = $item->details()->get()->pluck('uniq_code')->toArray();
        // //唯一码在上架单的状态
        // $putDetailIns = new WmsPutawayDetail();
        // $putway_codes = $putDetailIns::whereIn('uniq_code', $uniq_codes)->get()->pluck('putaway_code');
        // $putListIns = new WmsPutawayList();
        // $is_put = $putListIns::whereIn('putaway_code', $putway_codes)->where('putaway_status', 1)->exists();

        $is_put = $item->details()->where('is_putway', 1)->first();
        if ($is_put) return [false, __('status.not_support_abolished')];

        $old = $item->doc_status;
        if ($old == 3) return [false, __('status.invalid_arr')];
        $item->doc_status = 3;
        DB::beginTransaction();
        $res[] = $item->save();
        //退调货入库单状态恢复
        if (in_array($item->arr_type, [2, 3])) {
            $ib_build = IbOrder::where('ib_code', $item->ib_code)->where('ib_type', $item->arr_type)->where('doc_status', 3);
            $ib_codes = $ib_build->pluck('ib_code')->toArray();
            $row['ib_order'] = $ib_build->update([
                'doc_status' => 1,
                'recv_status' => 1,
                'rd_total' => 0,
                'normal_count' => 0,
                'flaw_count' => 0
            ]);
            $row['ib_order_detail'] = IbDetail::whereIn('ib_code', $ib_codes)->update([
                'quality_level' => '',
                'quality_type' => 0,
                'rd_total' => 0,
                'normal_count' => 0,
                'flaw_count' => 0
            ]);

            $row['with_uniq_log'] = WithdrawUniqLog::where('source_code', $item->third_doc_code)->where('is_scan', 1)->update(['is_scan' => 0]);
            //调拨单收货数量和详情更新
            if ($item->arr_type == 2) {
                $tr_build = TransferOrder::where('tr_code', $item->third_doc_code)->where('doc_status', 2);
                $tr_codes = $tr_build->pluck('tr_code')->toArray();
                $row['tr_order'] = $tr_build->update([
                    'recv_status' => 1,
                    'recv_num' => 0,
                ]);
                $row['tr_order_detail'] = TransferDetails::whereIn('tr_code', $tr_codes)->update([
                    'recv_num' => 0,
                ]);
            }
            //退货收货数量更新
            if ($item->arr_type == 3) {
                $sale_item = WmsOrder::where('third_no', $item->third_doc_code)->first();
                if (!$sale_item) return [false, '销售单丢失'];
                $after_order = AfterSaleOrder::where('origin_code', $sale_item->code)->where('status', 2)->first();
                if (!$after_order) return [false, '售后工单不存在'];
                $row['after_sale_update'] = $after_order->update([
                    'return_status' => 1,
                ]);
            }
        }

        //收货单 和 质检单作废
        $row['recv_update'] = self::recvOrderIns()->where('arr_id', $id)->update(['updated_user' => request()->header('user_id'), 'doc_status' => 3]);
        $row['qa_detail'] = (new WmsQualityDetail())->where('arr_id', $id)->update(['status' => 2]);
        $row['qac_detail'] = (new WmsQualityConfirmList())->where('arr_id', $id)->update(['status' => 2]);
        //商品状态修改为作废
        $row['recv_detail_update'] = $item->details()->update(['is_cancel' => 1]);
        $row['inv_delete'] = $item->invDetails()->delete();
        //记录操作日志
        $res[] =  WmsOptionLog::add(1, $item->arr_code, '作废', '到货登记单作废', ['old' => $old, 'row' => $row]);
        $c = array_filter($res, function ($v) {
            return empty($v);
        });
        if (empty($c)) {
            DB::commit();
            //总库存更新
            Inventory::totalInvUpdate($item->warehouse_code, $item->details()->pluck('bar_code')->toArray());
            return [true, ''];
        } else {
            DB::rollBack();
            return [false, __('base.fail')];
        }
    }

    public  function BaseCreate($CreateData = [])
    {
        if (empty($CreateData)) return false;
        //登记单创建时就匹配入库单
        if (!empty($CreateData['ib_code'])) $ib_ids = $CreateData['ib_code'];
        unset($CreateData['ib_code']);
        // $id = DB::table($this->table)->insertGetId($CreateData);
        $this->startTransaction();
        $id = $this::insertGetId($CreateData);
        if (empty($id)) return false;
        WmsOptionLog::add(WmsOptionLog::DJD, $CreateData['arr_code'], '创建', '到货登记单创建成功', $CreateData);
        if (isset($ib_ids)) {
            if ($CreateData['arr_type'] == 1) {
                DB::commit();
                return [true, __('status.done_match_ib_order')];
            }
            $res = $this->ibMatch($id, $ib_ids);
            log_arr([$res], 'wms');
            if (!$res[0]) return [false, __('status.ib_match_err') . $res[1]];
        }
        DB::commit();
        return $id;
    }

    public function _afterUpdate($builder, $update)
    {
        $item = $builder->first();
        return WmsOptionLog::add(WmsOptionLog::DJD, $item->arr_code, '修改', '修改到货登记单', $update);
    }

    public function log($arr_code)
    {
        return WmsOptionLog::list(WmsOptionLog::DJD, $arr_code);
    }

    //收货单类型检查
    public function recvPreCheck($id, $recv_type = null)
    {
        $item = $this::find($id);
        if (empty($item)) return [false, __('status.arr_not_exists')];
        $arr_type = $item->arr_type;
        if ($recv_type === null) $recv_type = $item->arr_type;
        if ($arr_type != $recv_type) return [false, 'doc type error'];
        $arr_status = $item->arr_status;
        if ($arr_status == 4) return [false, __('status.done_not_recv')];
        $doc_status = $item->doc_status;
        if (in_array($doc_status, [2, 3])) return [false, '当前到货登记单' . $this->map['doc_status'][$doc_status] . ',不允许收货'];
        return [true, 'success'];
    }

    //条码维度 --- 扫描收货详情
    public function getRecvOrder($id, $scan_type = 0)
    {
        $item = $this::with(['recvOrders' => function ($query) use ($scan_type) {
            $query->where('created_user', ADMIN_INFO['user_id'])->where('doc_status', 1)->where('scan_type', $scan_type);
        }])->find($id);
        if (empty($item)) return [];
        $data['warehouse_name'] = $item->warehouse;
        $data['warehouse_code'] = $item->warehouse_code;
        $data['recv_type'] = $item->arr_type;
        $data['arr_code'] = $item->arr_code;
        $data['arr_id'] = $id;
        if (in_array($item->arr_type, [2, 3])) $data['ib_code'] = $item->ib_code;
        $recv_order = $item->recvOrders->first();
        if (empty($recv_order)) return $data;
        $recv_id = $recv_order->id;
        $data['recv_code'] = $recv_order->recv_code;
        $data['recv_id'] = $recv_id;
        $res = (new RecvDetail())->scanDetail($recv_id);
        $data['recv_detail_list'] = $res['recv_detail_list'] ?? [];
        $data['new_product_count'] = $res['new_product_count'] ?? 0;
        $data['scan_count'] = $res['scan_count'] ?? 0;
        return $data;
    }

    //收货单是否存在已完成
    public function hasDone($id)
    {
        return $this::find($id)->recvOrders()->whereIn('doc_status', [2, 3])->exists();
    }

    //到货完成
    public function arrStatusDone($id)
    {
        $item = $this::find($id);
        $doc_status = $item->doc_status;
        $arr_status = $item->arr_status;
        $this->startTransaction();
        if (in_array($doc_status, [1, 4])  && $arr_status == 3) {
            $temp =  $item->recvOrders()->whereIn('doc_status', [1])->exists();
            if ($temp) return [false, __('status.have_not_done')];
            $item->arr_status = 4;
            if ($item->confirm_num == 0)  $item->doc_status = 4;
            // $item->doc_status = 4;
            $res['save'] = $item->save();
            WmsOptionLog::add(WmsOptionLog::DJD, $item->arr_code, '到货完成', '到货登记单已完成', $arr_status);
            // 退货入库增加寄卖结算账单
            if ($item->arr_type == 3) Consigment::addBillByReturn($item);
            // 删除收货检查信息
            WmsReceiveCheck::where(['arr_id' => $id, 'tenant_id' => $item->tenant_id])->delete();
            return $this->endTransaction($res);
        } else {
            return [false, __('status.arr_status_err')];
        }
    }


    //添加库存流水
    public function invLog($uniq_codes, $code)
    {
        //添加库存流水记录

        foreach ($uniq_codes as $uniq_code) {
            WmsStockLog::add(WmsStockLog::ORDER_SUPPLIER, $uniq_code, $code);
        }
    }

    //确认供应商
    public function supConfirm($id, $confirm)
    {

        $arr_item = $this::find($id);
        // dd($arr_item->details()->where('sup_id',0)->get()->toArray());
        $doc_status = $arr_item->doc_status;
        $arr_status = $arr_item->arr_status;
        $uniq_codes = [];
        $code = $arr_item->arr_code;
        $ids = array_column($confirm, 'sup_id');
        $suppliers = Supplier::whereIn('id', $ids)->select(['id', 'sup_code'])->with('documents')->get()->keyBy('id')->toArray();
        foreach ($confirm as &$con_item) {
            if ($con_item['doc_type'] ?? 0) {
                foreach ($suppliers[$con_item['sup_id']]['documents'] ?? [] as $doc) {
                    if ($doc['type'] == $con_item['doc_type']) {
                        $con_item['sup_document_id'] = $doc['id'];
                        break;
                    }
                }
            }
        }

        if (!in_array($doc_status, [2, 3]) && in_array($arr_status, [3, 4])) {
            //确认数量
            $where = [];
            // $table = self::detailIns()->getTable();
            // $updateSql = 'UPDATE '.$table.' SET sup_ids = Case';
            // $all_count = 0;
            DB::beginTransaction();
            foreach ($confirm as $item) {
                $bar_code = $item['bar_code'];
                $sup_id = $item['sup_id'];
                $count = $item['count'];
                $uniq_code_1 = $item['uniq_code'];
                // $all_count += $count;
                if (empty($bar_code) || empty($sup_id)) return [false, '传入参数有误'];
                if ($count == 0) continue;
                if (empty($where[$bar_code][$sup_id])) $where[$bar_code][$sup_id] = $count;
                else $where[$bar_code][$sup_id] += $count;
                if (!empty($uniq_code_1)) $set = $arr_item->details()->where('uniq_code', $uniq_code_1)->where('sup_id', 0)->where('ib_confirm',0)->where('bar_code', $bar_code)->limit($count);
                else $set = $arr_item->details()->where('sup_id', 0)->where('bar_code', $bar_code)->where('ib_confirm',0)->where('quality_type', 1)->limit($count);
                if ($set->count() == 0) return [false, '没有需待确认的数量'];
                $uniq_code = $set->pluck('uniq_code')->toArray();
                $uniq_codes = array_merge($uniq_codes, $uniq_code);
                // dd($set->update(['sup_id' => $sup_id, 'sup_confirm' => 1]));
                $row1 = $set->whereIn('uniq_code', $uniq_code)->lockForUpdate()->update(['sup_id' => $sup_id, 'sup_confirm' => 1, 'sup_document_id' => $item['sup_document_id'] ?? 0]);
                if (empty($row1)) break;
                //库存同步
                $row2 = Inventory::whereIn('uniq_code', $uniq_code)->where('sale_status',0)->lockForUpdate()->update(['sup_id' => $sup_id]);
                if (empty($row2)) break;
                // $updateSql = 'UPDATE '.$table.' SET sup_id = '.$sup_id.' WHERE sup_id = 0 AND bar_code = '.$bar_code. ' LIMIT '.$count.' ;';
                // $row1 = DB::update($updateSql);
                // if(!$row1)break;
            }
            // foreach($where as $bar_code=>$sup_ids){
            //     $updateSql.=' WHEN bar_code = '.$bar_code."  THEN '".json_encode($sup_ids)."'";
            // }
            // $updateSql.=' ELSE "" END WHERE bar_code IN ('.implode(',',array_keys($where)).')';
            // dd($updateSql);
            // $row1 = DB::update($updateSql);
            $row2 = WmsOptionLog::add(WmsOptionLog::DJD, $arr_item->arr_code, '确认供应商', '到货登记单确认供应商', $where);
            //登记单的确认数量修改
            // $arr_item->confirm_num = $arr_item->recv_num - $all_count;
            // $arr_item->confirm_num = $arr_item->details()->where('sup_id', 0)->get()->count();
            $arr_item->updated_user = request()->header('user_id');
            $row3 = $arr_item->save();
            if ($row1 && $row2 && $row3) {
                DB::commit();
                $this->invLog($uniq_codes, $code);
                //更新供应商库存
                $sup_inv_res = SupInv::supInvUpdate($uniq_codes);
                log_arr($sup_inv_res, 'wms');
                return [true, ''];
            } else {
                DB::rollBack();
                return  [false, __('base.fail')];
            }
        } else {
            return [false, __('status.arr_status_err')];
        }
    }

    //更新采购价
    private function setBuyPrice($arr_id, $ib_id, $ib_code = null)
    {
        $uniq_codes = [];
        $tenant_id = request()->header('tenant_id');
        $user_id = request()->header('user_id');
        $arr_item = $this::find($arr_id);
        if ($ib_code) {
            $ib_item = IbOrder::where('tenant_id', $tenant_id)->where('doc_status', 1)->where('ib_code', $ib_code)->first();
        } else {
            $ib_item = IbOrder::where('tenant_id', $tenant_id)->where('doc_status', 1)->find($ib_id);
            if (empty($ib_item)) return [false, __('response.ib_order_not_exists')];
        }
        // if ($ib_item->third_no  && $ib_item->third_no != $arr_item->arr_code) return [false, __('response.ib_code_not_match')];
        if ($ib_item->ib_type != $arr_item->arr_type) return [false, __('response.type_not_match')];
        if ($ib_item->warehouse_code != $arr_item->warehouse_code) return [false, __('response.warehouse_not_match')];
        $ib_update = ['arr_id' => $arr_id, 'doc_status' => 3, 'updated_user' => $user_id];
        $ib_detail = [];
        if (in_array($arr_item->arr_type, [1, 4])) {
            //入库单实收总数初始化
            $o_rd_total = $ib_item->rd_total;
            $o_normal_count = $ib_item->normal_count; //实收正品
            $o_flaw_count = $ib_item->flaw_count; //实收瑕疵

            //采购单或者其他入库单明细数据
            $origin_product_data = [];

            $ib_detail = $ib_item->details->toArray();

            foreach ($ib_detail as $k => $pro) {
                //入库单明细实收总数初始化
                $recv_detail_ids = [];
                $rd_total = $pro['rd_total'];
                $normal_count = $pro['normal_count']; //实收正品
                $flaw_count = $pro['flaw_count']; //实收瑕疵
                $bar_code = $pro['bar_code'];
                $count = $pro['re_total'];
                $price = $pro['buy_price'];
                // if ($price != 0) {
                $update = ['buy_price' => $price, 'ib_confirm' => 1, 'ib_id' => $ib_item->id, 'inv_type' => $pro['inv_type']];
                $set = $arr_item->detailIns()->where('bar_code', $bar_code)->where('warehouse_code', $ib_item->warehouse_code)->where('sup_id', $ib_item->sup_id)->where('ib_confirm', 0)
                    ->limit($count)->get();
                if ($set->count() != $count) return [false, __('response.ib_match_err')];

                //修改库存状态
                foreach ($set as $detail_item) {
                    $inv_data = [
                        'sale_status' => 1,
                        'sup_id' => $detail_item->sup_id,
                        'buy_price' => $price,
                        'inv_type' => $pro['inv_type'],
                        'sku' => $pro['sku'],

                    ];
                    $recv_detail_ids[] = $detail_item->id;
                    $o_rd_total += 1;
                    $rd_total += 1;
                    if ($detail_item->original['quality_type'] == 1) {
                        $o_normal_count += 1;
                        $normal_count += 1;
                    }
                    if ($detail_item->original['quality_type'] == 2) {
                        $o_flaw_count += 1;
                        $flaw_count += 1;
                    }
                    list($inv_res, $msg) = Inventory::invStatusUpdate($detail_item->uniq_code, null, 1, $inv_data, ['recv_id' => $detail_item->recv_id]);
                    if (!$inv_res) return [false, 'invStatus:' . $msg];
                    $uniq_codes[] = $detail_item->uniq_code;
                    //增加库存流水
                    // WmsStockLog::add(WmsStockLog::ORDER_SUPPLIER,$uniq_code,);
                };
                //更新收货明细
                $row = $arr_item->detailIns()->whereIn('id', $recv_detail_ids)->update($update);
                if (empty($row)) return [false, '匹配更新失败'];
                //更新入库单明细
                $row = $ib_item->details()->find($pro['id'])->update([
                    'rd_total' => $rd_total,
                    'normal_count' => $normal_count,
                    'flaw_count' => $flaw_count,
                    'sku' => $pro['sku'],

                ]);
                if (empty($row)) return [false, '匹配更新失败!'];
                //
                $origin_product_data[] = [
                    'rd_total' => $rd_total,
                    'normal_count' => $normal_count,
                    'flaw_count' => $flaw_count,
                    'bar_code' => $pro['bar_code'],
                    're_total' => $pro['re_total'],
                ];
                // }else{
                //     return [false,'入库单商品没有采购价'];
                // }
                // WmsOptionLog::add(WmsOptionLog::RKD, $ib_item->ib_code, '入库确认回传', '匹配入库单',[$ib_item->id=>$pro]);

            }

            $ib_update = array_merge($ib_update, ['normal_count' => $o_normal_count, 'flaw_count' => $o_flaw_count, 'rd_total' => $o_rd_total]);

            //收货状态修改
            if ($ib_item->re_total == $o_rd_total) $ib_update['recv_status'] = 3;
            else $ib_update['recv_status'] = 2;

            //采购单或者其他入库单数据
            $origin_data = [

                're_total' => $ib_item->re_total,
                'normal_count' => $o_normal_count,
                'flaw_count' => $o_flaw_count,
                'rd_total' => $o_rd_total,
                'third_code' => $arr_item->arr_code,
            ];
            $origin_data['products'] = $origin_product_data;

            //采购单收货数据更新
            if ($ib_item->ib_type == 1) {
                $origin_code = trim($ib_item->source_code);
                if (strpos($origin_code, 'CG') == 0) PurchaseOrders::confirm($origin_code, $origin_data);
                if (strpos($origin_code, 'JMD') == 0) Consignment::confirm($origin_code, $origin_data);
            }
            //其他入库单收货数据更新
            if ($ib_item->ib_type == 4) {
                $origin_code = $ib_item->third_no;
                OtherIbOrder::confirm($origin_code, $origin_data);
            }
            // dump($origin_code,$origin_data,$ib_update);
        }
        WmsOptionLog::add(WmsOptionLog::RKD, $ib_item->ib_code, '入库确认回传', '匹配入库单', [$ib_item->id => $ib_detail]);

        //供应商库存更新
        // $sup_inv_res = SupInv::supInvUpdate($uniq_codes);
        // log_arr($sup_inv_res, 'wms');
        self::invAsyncAdd($uniq_codes);


        return [$ib_item->update($ib_update), '入库单更新失败'];
    }

    // 异步更新供应商和商品库存
    static public function invAsyncAdd($uniq_codes)
    {
        $data = [
            'params' => ['tenant_id' => request()->header('tenant_id'), 'uniq_codes' => $uniq_codes],
            'class' => 'App\Models\Admin\V2\ArrivalRegist',
            'method' => 'supInvUpdate',
            'time' => date('Y-m-d H:i:s'),
        ];
        Redis::rpush(RedisKey::QUEUE2_AYSNC_HADNLE, json_encode($data));
    }


    // 异步更新供应商和商品库存
    static public function invAsyncByBarcode($params)
    {
        $params['tenant_id'] = request()->header('tenant_id');
        $params['user_id'] = request()->header('user_id', 0);
        $params['type'] = 4;
        $inv_update_redis = [
            'params' => $params,
            'class' => 'App\Models\Admin\V2\Inventory',
            'method' => 'invUpdate',
            'time' => date('Y-m-d H:i:s'),
        ];
        Redis::rpush(RedisKey::QUEUE2_AYSNC_HADNLE, json_encode($inv_update_redis));
    }

    function supInvUpdate($params)
    {
        $uniq_codes = $params['uniq_codes'] ?? [];
        $sup_inv_res = SupInv::supInvUpdate($uniq_codes);
        log_arr($sup_inv_res, 'wms');
    }


    //匹配入库单
    public function ibMatch($arr_id, $ib_ids, $force = 0)
    {
        $arr_item = $this::find($arr_id);
        if (empty($arr_item)) return [false, __('response.registration_not_exists')];
        if ($arr_item->doc_status != 1) return [false, __('response.status_not_match_ib')];
        $warehouse_code = $arr_item->warehouse_code;
        $this->startTransaction();
        //判断是否是退货或者调拨
        if (in_array($arr_item->arr_type, [2, 3])) {
            if (!is_numeric($ib_ids)) return [false, __('response.only_match_one')];
            $ib_item = IbOrder::where('doc_status', 1)->find($ib_ids);
            if (empty($ib_item)) return [false, __('response.ib_order_not_exists')];
            if ($arr_item->arr_type != $ib_item->ib_type) return [false, __('response.type_not_match')];
            if ($arr_item->warehouse_code != $ib_item->warehouse_code) return [false,  __('response.warehouse_not_match')];
            $arr_item->ib_code = $ib_item->ib_code;
            $arr_item->third_doc_code = $ib_item->third_no;
            //退调货获取唯一码明细
            // $bar_codes = $ib_item->details()->pluck('bar_code')->toArray();
            // list($uniq_log, $msg) = WithdrawUniqLog::getProData($ib_item->third_no, $bar_codes, $ib_item->ib_type);
            // if (!$uniq_log) return [false, '添加唯一码明细失败:' . $msg];
            $arr_item->updated_user = request()->header('user_id');
            $res['arr_update'] = $arr_item->save();
            $res['ib_update'] = $this->setBuyPrice($arr_id, $ib_ids);

            // 退货入库增加寄卖结算账单
            // if ($arr_item->arr_type == 3) Consigment::addBillByReturn($arr_item);
        } else {
            //采购匹配
            if (!in_array($arr_item->arr_status, [3, 4])) return [false, __('response.status_not_match_ib')];
            $ib_ids = explode(',', $ib_ids);
            // $ib_items =DB::table('wms_ib_detail as ib_d')
            $ib_items = IbDetail::from('wms_ib_detail as ib_d')
                ->join('wms_ib_order as ib', 'ib.ib_code', 'ib_d.ib_code')
                ->where('ib.tenant_id', request()->header('tenant_id'))
                ->whereIn('ib.id', $ib_ids)
                ->where('ib.doc_status', 1)
                ->whereRaw(DB::raw('ib_d.re_total > ib_d.rd_total'))
                ->select('ib.id', 'warehouse_code', 'ib.ib_code', 'ib.erp_no', 'ib_type', 'source_code', 'third_no', 'ib_d.id as did', 'ib_d.sup_id', 'ib.re_total as order_re_total', 'ib_d.re_total', DB::raw('ib_d.rd_total as old_rd_total'), DB::raw('ib.rd_total as old_total'), 'buy_price', 'bar_code', 'sku', 'inv_type', DB::raw('sum(ib_d.re_total) as total'))
                ->groupBy('ib.id', 'ib_type', 'source_code', 'third_no', 'ib_d.id', 'ib_d.sup_id', 'ib.re_total', 'ib_d.re_total', 'ib_d.rd_total', 'ib.rd_total', 'buy_price', 'bar_code', 'sku', 'inv_type')->get();
            if ($ib_items->isEmpty()) return [false, __('response.doc_not_exists')];
            $not_force_msg = [true, ['confirm_msg' => __('response.ib_confirm_msg')]];
            $bar_codes = [];
            $ib_codes = [];
            foreach ($ib_items as &$ib) {
                if ($ib->third_no && $ib->recv_status == 1 && $ib->third_no != $arr_item->arr_code) return [false, __('response.ib_code_not_match')];
                if ($ib->ib_type != $arr_item->arr_type) return [false, __('response.type_not_match')];
                if ($ib->warehouse_code != $arr_item->warehouse_code) return [false, __('response.warehouse_not_match')];
                $_recv_item =  RecvDetail::where('arr_id', $arr_id)->where('ib_confirm', 0)
                    ->where('sup_id', $ib->sup_id)
                    ->where(function ($query) use ($ib) {
                        $query->where('sku', $ib->sku)
                            ->orWhere('bar_code', $ib->bar_code);
                    })->limit($ib->total)->lockForUpdate();
                if ($ib->total > $_recv_item->count() && $force == 0) {
                    return $not_force_msg;
                }
                $collect = $_recv_item->get();
                $quality = $collect->groupBy('quality_type');
                if ($quality->isEmpty() && $force == 0) {
                    return $not_force_msg;
                }
                $recv_bar_code = $collect->groupBy('bar_code');
                foreach($recv_bar_code as $bar=>$b){
                    $bar_codes[$ib->sup_id . '#' . $bar] = $bar;
                }
                $ib->normal_count = isset($quality['正品']) ? $quality['正品']->count() : 0;
                $ib->flaw_count = isset($quality['瑕疵']) ? $quality['瑕疵']->count() : 0;
                $count = $_recv_item->lockForUpdate()->update([
                    'ib_id' => $ib->id,
                    'buy_price' => $ib->buy_price,
                    'ib_confirm' => 1,
                    'inv_type' => $ib->inv_type,
                    'sku' => $ib->sku,
                    'updated_user' => request()->header('user_id'),
                ]);
                if($count == 0){
                    $ib->rd_total += $count;
                    continue;
                }
                $inv_count = Inventory::where('arr_id', $arr_id)->where('sale_status', 0)->where('sup_id', $ib->sup_id)->where('buy_price', 0)
                    ->where(function ($query) use ($ib) {
                        $query->where('sku', $ib->sku)
                            ->orWhere('bar_code', $ib->bar_code);
                    })->limit($ib->total)->lockForUpdate()
                    ->update([
                        'buy_price' => $ib->buy_price,
                        'sale_status' => 1,
                        'inv_status' => DB::raw('if(inv_status=4,5,inv_status)'),
                        'inv_type' => $ib->inv_type,
                        'sku' => $ib->sku,
                        'updated_user' => request()->header('user_id'),
                    ]);

                if ($count != $inv_count) return [false, '匹配更新失败'];
                $ib->rd_total += $count;
            }
            //更新入库单状态
            //详情
            $origin_data = [];

            foreach ($ib_items as &$ib) {
                // $ib_codes[]=$ib->ib_code;
                if($ib->rd_total == 0)continue;
                $bar_codes[$ib->sup_id . '#' . $ib->bar_code] = $ib->bar_code;
                $d_up = $ib->where('id', $ib->did)->lockForUpdate()
                    ->update([
                        'rd_total' => DB::raw('rd_total +' . $ib->rd_total),
                        'normal_count' => DB::raw('normal_count +' . $ib->normal_count),
                        'flaw_count' => DB::raw('flaw_count +' . $ib->flaw_count),
                        'admin_user_id' => request()->header('user_id'),
                    ]);
                if (!$d_up) return [false, '详情更新失败'];
                if ($ib->ib_type == 1) {
                    $origin_code = trim($ib->source_code);
                    if ($ib->third_no &&  $ib->third_no != $arr_item->arr_code) {
                        $ib->third_no = '';
                    } else {
                        $ib->third_no = $arr_item->arr_code;
                    }
                }
                //其他入库单收货数据更新
                if ($ib->ib_type == 4) {
                    $origin_code = trim($ib->third_no);
                }
                $pro = [
                    'rd_total' => $ib->rd_total,
                    'normal_count' => $ib->normal_count,
                    'flaw_count' => $ib->flaw_count,
                    'bar_code' => $ib->bar_code,
                    're_total' => (int)$ib->total,
                    'sku' => $ib->sku,
                    'old_rd_total' => $ib->old_rd_total,
                ];
                if (isset($origin_data[$origin_code])) {
                    $origin_data[$origin_code]['rd_total'] += $ib->rd_total;
                    $origin_data[$origin_code]['old_total'] = $ib->old_total;
                    $origin_data[$origin_code]['normal_count'] += $ib->normal_count;
                    $origin_data[$origin_code]['flaw_count'] += $ib->flaw_count;
                    $origin_data[$origin_code]['products'][] = $pro;
                    $origin_data[$origin_code]['ib_id'] = $ib->id;
                    $origin_data[$origin_code]['third_code'] = $ib->third_no;
                } else {
                    $origin_data[$origin_code] = [
                        'third_code' => $ib->third_no,
                        'ib_code' => $ib->ib_code,
                        'old_total' => $ib->old_total,
                        'rd_total' => $ib->rd_total,
                        'normal_count' => $ib->normal_count,
                        'flaw_count' => $ib->flaw_count,
                        'ib_id' => $ib->id,
                        'products' => [
                            $pro
                        ],
                    ];
                }
                //关联关系写入
                IbAndArr::updateOrCreate([
                    'warehouse_code' => $warehouse_code,
                    'ib_type' => $ib->ib_type,
                    'arr_code' => $arr_item->arr_code,
                    'ib_code' => $ib->ib_code,
                    'erp_no' => $ib->erp_no,
                    'source_code' => $ib->source_code,
                    'tenant_id' => request()->header('tenant_id'),
                ], [
                    'third_no' => $ib->third_no,
                    'rd_total' => DB::raw('rd_total+' . $ib->rd_total),
                ]);
            }
            //更新采购/寄卖/其他入库单状态
            //采购寄卖其他入库申请单更新
            foreach ($origin_data as $code => $orig) {
                if($orig['rd_total']==0)continue;
                IbOrder::where('id', $orig['ib_id'])->lockForUpdate()->update([
                    'arr_id' => $arr_id,
                    'doc_status' =>   DB::raw('if(' . ($orig['rd_total'] + $orig['old_total']) . '=re_total,3,doc_status)'),
                    'rd_total' => DB::raw('rd_total +' . $orig['rd_total']),
                    'normal_count' =>  DB::raw('normal_count +' . $orig['normal_count']),
                    'flaw_count' =>  DB::raw('flaw_count +' . $orig['flaw_count']),
                    'updated_user' => request()->header('user_id', 0),
                    'recv_status' => DB::raw('if(' . ($orig['rd_total'] + $orig['old_total']) . '=re_total,3,2)'),
                    'third_no' => $orig['third_code'],
                ]);
                if (strpos($code, 'CG') == 0) PurchaseOrders::confirm($code, $orig);
                if (strpos($code, 'JMD') == 0) Consignment::confirm($code, $orig);
                if (strpos($code, 'QTRKSQD') == 0)  OtherIbOrder::confirm($code, $orig);
                WmsOptionLog::add(WmsOptionLog::RKD, $orig['ib_code'], '入库确认回传', '匹配入库单', [$orig['ib_code'] => $orig]);
            }
            //更新登记单状态
            //登记单的确认数量修改
            $confirm_num = $arr_item->details()->where('ib_confirm', 0)->get()->count();
            $arr_item->confirm_num = $confirm_num;
            $arr_item->updated_user = request()->header('user_id');
            if ($confirm_num == 0 && $arr_item->arr_status == 4) $arr_item->doc_status = 4;
            $res['arr_update'] = $arr_item->save();
        }
        WmsOptionLog::add(WmsOptionLog::DJD, $arr_item->arr_code, '匹配入库单', '到货登记单匹配入库单', ['ib_ids' => $ib_ids, 'res' => $res]);

        $c = array_filter($res, function ($v) {
            return empty($v);
        });
        if (empty($c)) {
            DB::commit();
            if (!empty($bar_codes)) {
                foreach ($bar_codes as $k => $bar_code) {
                    //供应商库存更新
                    $sup_id = explode('#', $k)[0];
                    self::invAsyncByBarcode([
                        'sup_data' => [$warehouse_code, [$bar_code], $sup_id],
                        'total_data' => [$warehouse_code, $bar_code]
                    ]);
                    //仓库库存更新
                    // self::invAsyncByBarcode([]);
                }
            }
            return [true, ['confirm_msg' => '']];
        } else {
            DB::rollBack();
            return [false, __('base.fail')];
        }
    }



    //登记单已匹配的入库单列表
    public function ibList($id)
    {
        $arr_item = $this::find($id);
        if (empty($arr_item)) return [false, __('status.arr_not_exists')];
        $ibModel = new IbOrder();

        $data =  $ibModel->where('arr_id', $id)->get()->toArray();
        return [true, $data];
    }

    //采购单导出
    public function buyExport($data)
    {
        $res_data = [];
        if (!empty($data['ids'])) {
            $ids = explode(',', $data['ids']);
            // $sup_item = RecvDetail::arrExport($ids);
            // foreach($sup_item as $item){
            //     $arr_item =  $this->where('id',$item['arr_id'])->whereIn('arr_status',[3,4])->first()->makeHidden('arr_details_group');
            //     if($arr_item){
            //         $arr_item->details_group = $item;
            //         $res_data[]=$arr_item->toArray();
            //     }
            // }
            $items = $this->whereIn('id', $ids)->whereIn('arr_status', [3, 4])->get()->makeHidden('arr_details_group')->toArray();
            foreach ($items as $item) {
                if ($item) {
                    $temp = $item;
                    $temp['details_group'] = RecvDetail::buyExport($item['id']);
                    $res_data[] = $temp;
                }
            }
        }
        // dd($res_data);
        return $res_data;
    }

    //登记单导出
    public function Export($data)
    {
        $res_data = [];
        $rData = $this->query();
        if (!empty($data['ids'])) {
            $ids = explode(',', $data['ids']);
            $rData = $rData->whereIn('id', $ids);
        }
        $items = $rData->orderBy('id', 'desc')->paginate($data['size'] ?? 10, ['*'], 'page', $data['cur_page'] ?? 1);
        $items->append('users_txt');
        $items = $items->toArray();
        // ->toArray();
        foreach ($items['data'] as $item) {
            if ($item) {
                $temp = $item;
                $temp['recv_details'] = RecvDetail::arrExport($item['id']);
                $res_data[] = $temp;
            }
        }
        return $res_data;
    }

    //删除
    public function del($ids, $name = 'id')
    {
        if (empty($ids)) return [false, false, __('base.fail')];
        $id = explode(',', $ids);
        $success = $this::whereIn($name, $id)->where('arr_status', 2)->pluck('id')->toArray();
        $fail = array_diff($id, $success);
        if (empty($success)) return [false, __('response.order_not_delete')];
        $data = $this::whereIn('id', $success)->delete();
        if (empty($data)  || $data == false || $data == NULL) return [false, __('base.fail')];
        if (!empty($fail)) return [true, ['fail' => $fail, 'success' => $success]];
        return [true, $data];
    }

    //确认采购成本
    public function purchaseCost($data)
    {
        $arr_id = $data['arr_id'];
        $is_pur_cost = $data['is_pur_cost'];
        $arr_item  =  self::find($arr_id);
        if (!$arr_item) return [false, __('status.arr_not_exists')];
        // if ($arr_item->is_pur_cost != 0) return [false, '已确认过采购成本'];
        $user = request()->header('user_id');
        $tenant_id = request()->header('tenant_id');
        $time = date('Y-m-d H:i:s');
        $arr_update = [];
        $this->startTransaction();
        //有采购成本
        if ($is_pur_cost == 1) {
            $pur_cost_insert = [];
            $pur_cost = 0;
            foreach ($data['pur_cost'] as $item) {
                $pur_cost += $item['cost'] * $item['num'];
                $temp = [
                    'arr_id' => $arr_id,
                    'type' => $item['type'],
                    'num' => $item['num'],
                    'cost' => $item['cost'],
                    'tenant_id' => $tenant_id,
                    'created_at' => $time,
                    'created_user' => $user,
                ];
                $pur_cost_insert[] = $temp;
            }
            $arr_update['is_pur_cost'] = 1;
            $arr_update['pur_cost'] = $pur_cost;
            $arr_update['updated_user'] = $user;
            $res['pur_cost_insert'] = PurchaseCost::insert($pur_cost_insert);
        }
        //没有采购成本
        if ($is_pur_cost == 0) {
            $arr_update['is_pur_cost'] = 2;
            $arr_update['updated_user'] = $user;
        }

        $res['arr_update'] = $arr_item->update($arr_update);
        $res['option_log'] = WmsOptionLog::add(WmsOptionLog::DJD, $arr_item->arr_code, '确认采购成本', '到货登记单确认采购成本', ['data' => $data]);
        return $this->endTransaction($res);
    }

    //修改采购成本
    public function editPurCost($data)
    {
        $arr_id = $data['arr_id'];
        $arr_item  =  self::find($arr_id);
        if (!$arr_item) return [false, __('status.arr_not_exists')];
        if ($arr_item->is_pur_cost != 1) return [false, __('status.not_support_edit')];
        $pur_cost_item = $data['pur_cost'];
        $user = request()->header('user_id');
        $tenant_id = request()->header('tenant_id');
        $time = date('Y-m-d H:i:s');
        $this->startTransaction();
        $delete_ids = PurchaseCost::where('arr_id', $arr_id)->pluck('id')->toArray();
        foreach ($pur_cost_item as $item) {
            if (empty($item['id'])) {
                //新增
                $temp = [
                    'arr_id' => $arr_id,
                    'type' => $item['type'],
                    'num' => $item['num'],
                    'cost' => $item['cost'],
                    'tenant_id' => $tenant_id,
                    'created_at' => $time,
                    'updated_at' => $time,
                    'created_user' => $user,
                    'updated_user' => $user,
                ];
                $insert[] = $temp;
            } else {
                //修改
                $index = array_search($item['id'], $delete_ids);
                if ($index !== false) unset($delete_ids[$index]);
                $update = [
                    'updated_at' => $time,
                    'updated_user' => $user,
                ];
                if (!empty($item['type'])) $update['type'] = $item['type'];
                if (!empty($item['num'])) $update['num'] = $item['num'];
                if (!empty($item['cost'])) $update['cost'] = $item['cost'];
                $res['pru_cost_update'] = PurchaseCost::where('id', $item['id'])->where('arr_id', $arr_id)->update($update);
            }
        }
        if (!empty($insert)) $res['pru_cost_insert'] = PurchaseCost::insert($insert);
        if (!empty($delete_ids)) PurchaseCost::where('arr_id', $arr_id)->whereIn('id', $delete_ids)->delete();
        $pur_cost = PurchaseCost::where('arr_id', $arr_id)->select(DB::raw('sum(num*cost) as costs'))->value('costs');
        $res['arr_update'] = $arr_item->update(['updated_user' => request()->header('user_id'), 'pur_cost' => $pur_cost]);
        return $this->endTransaction($res);
    }

    //获取采购成本
    public function getPurCost($data)
    {
        $arr_id = $data['arr_id'];
        $data = PurchaseCost::where('arr_id', $arr_id)->select('id', 'arr_id', 'type', 'num', 'cost', 'remark')->get()->toArray();
        return [true, $data];
    }

    //匹配入库单前校验是否存在未质检或者未上架的商品
    public function checkQcAndPutway($arr_id)
    {
        $recv_detail_item = RecvDetail::where('arr_id', $arr_id)->where('is_cancel', 0);
        if ($recv_detail_item->doesntExist()) return [false, __('status.not_start_recv')];
        // $sup_confirm = $recv_detail_item->where('sup_confirm', 0)->exists();
        // if ($sup_confirm) return [false, __('status.sup_not_confirm')];
        $qc_or_putway = RecvDetail::where('arr_id', $arr_id)->where('is_cancel', 0)->where(function ($query) {
            $query->where('is_qc', 0)->orWhere('is_putway', 0);
        })->exists();

        if ($qc_or_putway) {
            return [true, ['confirm_msg' => __('status.not_qc')]];
        }
        return [true, ''];
    }

    //判断新增时所选仓库与退调订单仓库是否一致
    public function checkWarehouse($ib_code)
    {
        $item =  IbOrder::where('id', $ib_code)->orWhere('ib_code', $ib_code)->where('doc_status', 1)->first();
        if (!$item)  return '';
        return $item->warehouse_code;
    }

    //修改供应商
    public function supEdit($arr_id, array $confirm, array $old_confirm)
    {

        $con = [];
        $old = '';

        foreach ($confirm as $c) {
            if ($c['count'] === 0) continue;
            // $con[$c['bar_code'].'|'.$c['uniq_code']][$c['sup_id']]=$c['count'];
            $con[$c['bar_code'] . ',' . $c['uniq_code'] . ',' . $c['sup_id']] = $c['count'];
        }
        foreach ($old_confirm as $o) {
            if ($o['count'] === 0) continue;
            if (isset($con[$o['bar_code'] . ',' . $o['uniq_code'] . ',' . $o['sup_id']]) && $con[$o['bar_code'] . ',' . $o['uniq_code'] . ',' . $o['sup_id']] == $o['count']) {
                unset($con[$o['bar_code'] . ',' . $o['uniq_code'] . ',' . $o['sup_id']]);
                continue;
            }
            $old .= '***' . $o['bar_code'] . ',' . $o['uniq_code'] . ',' . $o['sup_id'] . ',' . $o['count'] . '***';
        }
        $format_confirm = [];
        $append = [];
        foreach ($con as $k => &$v) {
            list($bar_code, $uniq_code_1, $sup_id) = explode(',', $k);
            $lastCommaPos = strrpos($k, ","); // 找到最后一个逗号的位置
            $result = substr($k, 0, $lastCommaPos + 1);
            $pattern = '/\*\*\*' . $result . '(\d+),' . $v . '\*\*\*/';
            preg_match($pattern, $old, $match);
            $old_sup_id = array_pop($match);
            if (is_numeric($old_sup_id)) {
                $del = '***' . $result . $old_sup_id . ',' . $v . '***';
                $count = 1;
                $old = str_replace($del, '', $old, $count);
            } else {
                $pattern = '/\*\*\*(' . $result . '\d+,\d+)\*\*\*/';
                preg_match_all($pattern, $old, $match_1);
                if (isset($match_1[1])) {
                    $_append = [];
                    foreach ($match_1[1] as $m) {
                        $_temp = explode(',', $m);
                        if ($_temp[3] < $v) {
                            $_append[] = $_temp;
                            // $_append[$m]=$_temp[3];
                            continue;
                        }
                        $edit_before = '***' . $m . '***';
                        $old_sup_id = $_temp[2];
                        $_temp[3] = $_temp[3] - $v;
                        $edit_after = '***' . implode(',', $_temp) . '***';
                        $old = str_replace($edit_before, $edit_after, $old, $count);
                        break;
                    }
                    if (!is_numeric($old_sup_id)) {
                        if ($_append) {
                            $a_count = $v;
                            foreach ($_append as $ak => $av) {
                                // $a_temp = explode(',',$ak);
                                if ($a_count == 0) continue;
                                $a_temp = $av;
                                $a_count -= $a_temp[3];
                                if( $a_temp[2] == $sup_id )continue;
                                $append[] = [
                                    'bar_code' => $a_temp[0],
                                    'old_sup_id' => $a_temp[2],
                                    'sup_id' => $sup_id,
                                    'uniq_code' =>  $a_temp[1],
                                    'count' => (int)$a_temp[3],
                                ];
                            }
                            if ($a_count > 0) {
                                $append[] = [
                                    'bar_code' => $a_temp[0],
                                    'old_sup_id' => 0,
                                    'sup_id' => $sup_id,
                                    'uniq_code' =>  $a_temp[1],
                                    'count' => $a_count,
                                ];
                            }
                        }
                    }
                } else {
                    $old_sup_id = 0;
                }
            }
            if ($old_sup_id === null) continue;
            if ($old_sup_id == $sup_id) continue;
            $format_confirm[] = [
                'bar_code' => $bar_code,
                'old_sup_id' => $old_sup_id,
                'sup_id' => $sup_id,
                'uniq_code' => $uniq_code_1,
                'count' => $v,
            ];
        }
        $format_confirm = array_merge($format_confirm, $append);
        if(empty($format_confirm )) return [true,''];
        $arr_item = $this::find($arr_id);
        if (!$arr_item) return [false, __('response.doc_not_exists')];
        $doc_status = $arr_item->doc_status;
        $arr_status = $arr_item->arr_status;
        $uniq_codes = [];
        $code = $arr_item->arr_code;
        $ids = array_column($confirm, 'sup_id');
        $suppliers = Supplier::whereIn('id', $ids)->select(['id', 'sup_code'])->with('documents')->get()->keyBy('id')->toArray();
        foreach ($confirm as &$con_item) {
            if ($con_item['doc_type'] ?? 0) {
                foreach ($suppliers[$con_item['sup_id']]['documents'] ?? [] as $doc) {
                    if ($doc['type'] == $con_item['doc_type']) {
                        $con_item['sup_document_id'] = $doc['id'];
                        break;
                    }
                }
            }
        }
        if (!in_array($doc_status, [2, 3]) && in_array($arr_status, [3, 4])) {
            //确认数量
            $where = [];
            DB::beginTransaction();
            foreach ($format_confirm as $item) {
                $bar_code = $item['bar_code'];
                $old_sup_id = $item['old_sup_id'];
                $count = $item['count'];
                $uniq_code_1 = $item['uniq_code'];
                $sup_id = $item['sup_id'];
                if (empty($bar_code) || empty($sup_id)) return [false, '传入参数有误'];
                if ($count == 0) continue;
                if (empty($where[$bar_code][$sup_id])) $where[$bar_code][$sup_id] = $count;
                else $where[$bar_code][$sup_id] += $count;
                if (!empty($uniq_code_1)) $set = $arr_item->details()->where('uniq_code', $uniq_code_1)->where('ib_confirm',0)->where('sup_id', $old_sup_id)->where('bar_code', $bar_code)->limit($count);
                else $set = $arr_item->details()->where('sup_id', $old_sup_id)->where('ib_confirm',0)->where('bar_code', $bar_code)->where('quality_type', 1)->limit($count);
                if ($set->count() == 0) return [false, '没有需要修改的数量'];
                $uniq_code = $set->pluck('uniq_code')->toArray();
                $uniq_codes = array_merge($uniq_codes, $uniq_code);
                $row1 = $set->whereIn('uniq_code', $uniq_code)->lockForUpdate()->update(['sup_id' => $sup_id, 'sup_confirm' => 1, 'sup_document_id' => $item['sup_document_id'] ?? 0]);
                if (empty($row1)) break;
                //库存同步
                $row2 = Inventory::whereIn('uniq_code', $uniq_code)->where('sale_status',0)->lockForUpdate()->update(['updated_user'=>request()->header('user_id'),'sup_id' => $sup_id]);
                if (empty($row2)) break;
            }
            $row2 = WmsOptionLog::add(WmsOptionLog::DJD, $arr_item->arr_code, '修改供应商', '到货登记单修改供应商', $where);
            $arr_item->updated_user = request()->header('user_id');
            $row3 = $arr_item->save();
            if ($row1 && $row2 && $row3) {
                DB::commit();
                $this->invLog($uniq_codes, $code);
                //更新供应商库存
                $sup_inv_res = SupInv::supInvUpdate($uniq_codes);
                log_arr($sup_inv_res, 'wms');
                return [true, ''];
            } else {
                DB::rollBack();
                return  [false, __('base.fail')];
            }
        } else {
            return [false, __('status.arr_status_err')];
        }
    }
}
