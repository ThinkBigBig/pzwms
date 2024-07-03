<?php

namespace App\Models\Admin\V2;

use App\Logics\wms\Warehouse;
use App\Models\Admin\wmsBaseModel;
use App\Models\Admin\V2\UniqCodePrintLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToArray;

class RecvDetail extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_recv_detail'; //扫描收货明细
    protected $with = ['product:id,product_id,sku,bar_code,tenant_id,spec_one', 'supplier:id,name'];
    protected $guarded = [];

    protected $map;
    public function __construct($params = [])
    {
        parent::__construct($params);
        $this->map = [
            'recv_unit' => [0 => __('status.piece'), 1 => __('status.sections'), 2 => __('status.box')],
        ];
    }
    public function getCreatedUserAttribute($key)
    {
        return  $this->getAdminUser($this->created_user);
    }

    public function getRecvUnitTxtAttribute()
    {
        $recv_unit = isset($this->recv_unit) ? $this->map['recv_unit'][$this->recv_unit] : '';
        return $recv_unit;
    }

    public function getCategoryTxtAttribute()
    {
        return ProductCategory::getName($this->product->product->category_id);
        // return $this->product->product->category->parent->name ?? '';
    }
    public  function product()
    {
        return $this->hasOne(ProductSpecAndBar::class, 'bar_code', 'bar_code')->withDefault([
            'sku' => '',
            'spec_one' => '',
            'product' => [
                'name' => '',
                'product_sn' => '',
                'img' => '',
            ]
        ]);
    }

    public function supplier()
    {
        return $this->hasOne(Supplier::class, 'id', 'sup_id');
    }

    public function warehouse()
    {
        return $this->hasOne(Warehouse::class, 'warehouse_code', 'warehouse_code');
    }

    public  function recvOrder()
    {
        return $this->belongsTo(RecvOrder::class, 'recv_id', 'id');
    }

    public  function arrOrder()
    {
        return $this->belongsTo(ArrivalRegist::class, 'arr_id')->withDefault();
    }

    //删除已扫描商品
    public function delByBarCode($arr_id, $recv_id, $bar_code, $uniq_code = '', $count = 0)
    {
        $order  = $this->recvOrder()->getModel()->where('created_user', ADMIN_INFO['user_id'])->find($recv_id);
        if (empty($order) || $order->recv_status == 1 || $order->doc_status == 3) return [false, __('status.doc_not_delete')];
        if ($order->scan_type == 1) {
            if ($count == 'all') $item =  $this::where('recv_id', $recv_id)->where('bar_code', $bar_code);
            else $item =  $this::where('recv_id', $recv_id)->where('bar_code', $bar_code)->limit($count);
        } else {
            if ($uniq_code) {
                $item =  $this::where('recv_id', $recv_id)->where('uniq_code', $uniq_code)->where('bar_code', $bar_code);
            } else {
                $item =  $this::where('recv_id', $recv_id)->where('bar_code', $bar_code);
            }
            $uniq_codes = $item->pluck('uniq_code');
        }

        if ($item->doesntExist()) return [false, __('response.scan_err')];
        DB::beginTransaction();
        try {
            $row = $item->delete();
            if (!$row) return [false, 'delete error'];
            if ($order->scan_type == 0) {
                $row = UniqCodePrintLog::unBbindBarCode($uniq_codes, $arr_id, $bar_code, in_array($order->recv_type, [2, 3]));
                if (!$row) return [false, 'uniq_log_error'];
            }
            $row =  $this->refreshRecvNum($recv_id);
            if (!$row) return [false, 'recv_num error'];
            if ($this->where('recv_id', $recv_id)->count() === 0) {
                $row = $this->recvOrder()->getModel()->find($recv_id)->delete();
                if (!$row) return [false, 'delete order error'];
            }
            DB::commit();
            return [true, ''];
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            return  [false, $th->getMessage()];
        }
    }

    //普通产品增加已扫描商品
    public function addOrdinaryRecvCount($id, $count, $scan_type = 1)
    {
        $item = self::where('created_user', ADMIN_INFO['user_id'])->find($id);
        if (!$item) return [false, 'product not exists'];
        //判断是否为暂存单据
        $recv_item = $item->recvOrder->checkStash($item->arr_id, $scan_type);
        if (!$recv_item || $recv_item->id != $item->recv_id) return [false, 'recv order status cant not add'];
        $this->startTransaction();
        //判断是否为退调货
        if (in_array($recv_item->recv_type, [2, 3])) {
            //退调货
            $ib_item = IbOrder::where('id', $item->ib_id)->first();
            if (!$ib_item) return [false, 'ib order not match'];
            $pro_item =  WithdrawUniqLog::where('source_code', $ib_item->third_no)->where('bar_code', $item->bar_code)->where('is_scan', 0)->limit($count);
            if ($pro_item->get()->count() != $count) return  [false, 'The received quantity exceeds the quantity of return/adjustment orders'];
            //修改状态
            $pro_return = [];
            foreach ($pro_item as $pro) {
                //退调单
                $pro_return[] = [
                    'sup_id' => $pro->sup_id,
                    'buy_price' => $pro->buy_price,
                ];
            }
        }
        $copy = $item->getAttributes();
        $insert_date = [];
        for ($i = 1; $i <= $count; $i++) {
            $temp = $copy;
            unset($temp['id']);
            $temp['uniq_code'] .= '-' . $i;
            $temp['ib_at'] = date('Y-m-d H:i:s');
            $temp['created_at'] = date('Y-m-d H:i:s');
            $insert_date[] = $temp;
            $index = $i - 1;
            if (isset($pro_return[$index])) {
                $temp['sup_id'] = $pro_return[$index]['sup_id'];
                $temp['buy_price'] = $pro_return[$index]['buy_price'];
            }
        }
        //新增
        $row['detail_insert'] = self::insert($insert_date);
        //新增收货数量
        $recv_num = $recv_item->recv_num + $count;
        $recv_item->recv_num = $recv_num;
        $row_recv = $recv_item->save();
        if (!$row_recv) return [false, '收货单更新数量失败'];
        //退调记录更新
        if (isset($pro_item)) {
            $pro_item->update(['is_scan' => 1]);
        }
        //质检单更新记录
        // $row['qc'] = (new QualityControl())->qcReceive(['recv_id' => $recv_id]);
        log_arr($row, 'wms');
        return $this->endTransaction($row);
    }

    //登记单商品详情列表
    public function recvDetailList($recv_id)
    {
        if (!is_array($recv_id)) $recv_id = (array)$recv_id;
        // $list = $this::whereIn('recv_id', $recv_id)
        //     ->selectRaw('sup_id,bar_code,buy_price,warehouse_code,quality_type,quality_level,if(quality_level<>"A",uniq_code,"" ) as uniq_code,count(*) as count,remark')
        //     ->groupByRaw('sup_id,bar_code,buy_price,warehouse_code,quality_type,quality_level')->get();

        // $list = $this::whereIn('recv_id', $recv_id)
        // ->selectRaw('bar_code,warehouse_code,quality_type,quality_level,if(quality_level<>"A",uniq_code,"" ) as uniq_code,count(*) as count,sum(if(sup_confirm=1,1,0)) as sup_confirm, sum(if(ib_confirm=1,1,0)) as ib_confirm,remark')
        // ->groupByRaw('bar_code,warehouse_code,quality_type,quality_level')->get()->append(['product_name','product_spec','product_sku','product_sn'])->makeHidden('product');

        $detail_item = $this::with('supplier')->whereIn('recv_id', $recv_id)->orderBy('bar_code')->get()->append(['product_name', 'product_spec', 'product_sku', 'product_sn'])->makeHidden('product');
        if ($detail_item->isEmpty()) return ['list'=> [],'customColumns'=>[]];
        $bar_codes = [];
        $lists = [];
        // $detail_item = self::whereIn('recv_id', $recv_id)
        // ->selectRaw('bar_code,warehouse_code,quality_type,quality_level,sup_id,if(quality_level<>"A",uniq_code,"" ) as uniq_code_1,count(*) as count,remark')
        // ->groupByRaw('bar_code,warehouse_code,quality_type,quality_level,sup_id')
        // ->get()->append(['product_name', 'product_spec', 'product_sku', 'product_sn'])->makeHidden('product');
        $sup_ids =  self::whereIn('recv_id', $recv_id)->pluck('sup_id')->unique()->toArray();
        $customColumns = [];
        foreach ($detail_item->pluck('sup_id')->toArray() as $sup) {
            if($sup == 0)continue;
            $customColumns[$sup] = [];
        }
        // $data = [];
        // foreach ($detail_item as $item) {
        //     $temp = [
        //         'product_sku' => $item['product_sku'],
        //         'product_sn' => $item['product_sn'],
        //         'product_name' => $item['product_name'],
        //         'product_spec' => $item['product_spec'],
        //         'bar_code' =>  $item['bar_code'],
        //         'quality_type' => $item->getRawOriginal('quality_type'),
        //         'quality_level' => $item['quality_level'],
        //         'uniq_code' => $item['uniq_code_1'],
        //         'count' => $item['count'],
        //         // 'sup_id' => $item['sup_id'],
        //         // 'supplier_name' => $item['supplier_name'],
        //         'sup_confirm' => $item['sup_confirm_count'],
        //         'ib_confirm' => $item['ib_confirm_count'],
        //         // 'sup_ids' => &$bar_codes[$uniq_code][$bar_code]['sup_ids'],
        //     ];
        //     foreach($sup_ids as $sup){
        //         $temp[ 'sup_'.$sup]= (int)self::whereIn('recv_id', $recv_id)
        //         ->where('bar_code',$item['bar_code'])
        //         ->where('warehouse_code',$item['warehouse_code'])
        //         ->where('quality_type',$item->getRawOriginal('quality_type'))
        //         ->where('quality_level',$item['quality_level'])
        //         ->where('sup_id',$sup)->count();
        //     }
        //     $data[]=$temp;
        // }
        // return $data;

        foreach ($detail_item as $k=>$item) {
            if (!empty($item['supplier'])) {
                if (empty($customColumns[$item['sup_id']])) $customColumns[$item['sup_id']] = [
                    'id' => $item['sup_id'],
                    'name' => $item['supplier']['name'] ?? '',
                ];
            }
            $bar_code = $item['bar_code'];
            $ib_confirm_count = $item['ib_confirm'] == 0 ? 1 : 0;
            $sup_confirm_count = $item['sup_confirm'] == 0 ? 1 : 0;
            $uniq_code = $item['quality_level'] == 'A' ? '' : $item['uniq_code'];
            if (empty($bar_codes[$uniq_code][$bar_code])) {
                $bar_codes[$uniq_code][$bar_code] = [
                    'count' => 1,
                    'ib_confirm' => $ib_confirm_count,
                    'sup_confirm' => $sup_confirm_count,
                    'sup_ids' => [$item['sup_id'] => [
                        // 'id' => $item['sup_id'],
                        // 'supplier_name' => $item['supplier_name'],
                        'count' => 1,
                    ]],
                ];
            } else {
                $bar_codes[$uniq_code][$bar_code]['count'] += 1;
                $bar_codes[$uniq_code][$bar_code]['ib_confirm'] += $ib_confirm_count;
                $bar_codes[$uniq_code][$bar_code]['sup_confirm'] += $sup_confirm_count;
                if (empty($bar_codes[$uniq_code][$bar_code]['sup_ids'][$item['sup_id']])) $bar_codes[$uniq_code][$bar_code]['sup_ids'][$item['sup_id']] = [
                    // 'id' => $item['sup_id'],
                    // 'supplier_name' => $item['supplier_name'],
                    'count' => 1,
                ];
                else $bar_codes[$uniq_code][$bar_code]['sup_ids'][$item['sup_id']]['count'] += 1;
                $lists[$uniq_code][$bar_code]['count'] = $bar_codes[$uniq_code][$bar_code]['count'];
                $lists[$uniq_code][$bar_code]['ib_confirm'] = $bar_codes[$uniq_code][$bar_code]['ib_confirm'];
                $lists[$uniq_code][$bar_code]['sup_confirm'] = $bar_codes[$uniq_code][$bar_code]['sup_confirm'];
                continue;
            }
            $temp = [
                'id'=>$k+1,
                'product_sku' => $item['product_sku'],
                'product_sn' => $item['product_sn'],
                'product_name' => $item['product_name'],
                'product_spec' => $item['product_spec'],
                'bar_code' => $bar_code,
                'quality_type' => $item['quality_type'],
                'quality_level' => $item['quality_level'],
                'uniq_code' => $uniq_code,
                'count' => $bar_codes[$uniq_code][$bar_code]['count'],
                'sup_id' => $item['sup_id'],
                'supplier_name' => $item['supplier_name'],
                'sup_confirm' => $bar_codes[$uniq_code][$bar_code]['sup_confirm'],
                'ib_confirm' => $bar_codes[$uniq_code][$bar_code]['ib_confirm'],
                'sup_ids' => &$bar_codes[$uniq_code][$bar_code]['sup_ids'],
            ];
            $lists[$uniq_code][$bar_code] = $temp;
        }
        $data = [];
        foreach ($lists as $item) {
            foreach ($item as $one) {
                if ($one['sup_ids']) {
                    foreach($customColumns as $id=>$custom){
                        $one[$id]=empty($one['sup_ids'][$id])?0:$one['sup_ids'][$id]['count'];
                    }
                    unset($one['sup_ids']);
                    // $one['sup_ids'] = array_values($one['sup_ids']);
                }
                $data[] = $one;
            }
        }
        return['list'=> $data,'customColumns'=>$customColumns];
    }

    //扫描详情
    public function scanDetail($recv_id)
    {
        $detail_item_new = $this::where('recv_id', $recv_id)->doesntHave('product')
            ->selectRaw('id as detail_id,bar_code,warehouse_code,quality_type,quality_level,if(quality_level<>"A",uniq_code,"" ) as uniq_code,count(*) as scan_count,recv_unit')
            ->groupByRaw('bar_code,warehouse_code,quality_type,quality_level')->orderBy('created_at', 'desc')->get();
        $detail_item = $this::where('recv_id', $recv_id)->whereHas('product')
            ->selectRaw('id as detail_id,bar_code,warehouse_code,quality_type,quality_level,if(quality_level<>"A",uniq_code,"" ) as uniq_code,count(*) as scan_count,recv_unit')
            ->groupByRaw('bar_code,warehouse_code,quality_type,quality_level')->orderBy('created_at', 'desc')->get();
        $detail_item = $detail_item_new->concat($detail_item);
        if ($detail_item->isEmpty()) return [];
        $scan_count = $detail_item->sum('scan_count');
        // $new_product_count = $detail_item->sum(function ($row) use (&$scan_count) {
        //     $scan_count += $row['scan_count'];
        //     if (empty($row['product']['sku'])) return $row['scan_count'];
        // });

        $new_product_count = $detail_item_new->sum('scan_count');
        $data = [
            'recv_detail_list' => $detail_item->append(['recv_unit_txt', 'category_txt'])->toArray(),
            'new_product_count' => $new_product_count,
            'scan_count' => $scan_count,
        ];
        return $data;
    }

    //收货数量更新
    public function refreshRecvNum($recv_id)
    {
        $recvOrderModel = new RecvOrder();
        $recv_count = $this::where('recv_id', $recv_id)->count();
        $recv_item = $recvOrderModel::find($recv_id);
        $recv_item->recv_num = $recv_count;
        $row = $recv_item->save();
        return $row;
    }

    //质检更新
    public static function qualityUpdate($recv_id, $uniq_code, $quality_type, $quality_level, $transcation = true, $is_confirm = false)
    {
        $update = [
            'quality_level' => $quality_level,
            'quality_type' => $quality_type,
            'area_code' => 'ZJZCQ001',
            'is_qc' => 1,
        ];
        $in_wh_status = 2;
        if ($is_confirm) {
            unset($update['area_code']);
            $in_wh_status = null;
        }
        if ($transcation) self::startTransaction();
        //收货单详情 质检更新
        if (empty($recv_id))   $recv_detail_item = self::where('uniq_code', $uniq_code);
        else  $recv_detail_item = self::where('recv_id', $recv_id)->where('uniq_code', $uniq_code);
        $recv_detail_item = $recv_detail_item->first();
        if ($recv_detail_item) {
            $origin_quality_type = $recv_detail_item->getRawOriginal('quality_type');
            $row['recv_details_qa_update'] =  $recv_detail_item->update($update);
            //库存 质检更新
            // $row['inv_qa_update'] = Inventory::where('recv_id', $recv_id)->where('uniq_code', $uniq_code)->update($update);
            $row['inv_qa_update'] = Inventory::invStatusUpdate($uniq_code, $in_wh_status, null, $update, ['recv_id' => $recv_id]);

            //供应商质检更新
            Inventory::invAsyncAdd(0, 3, '', '', $uniq_code);
            // $sup_inv_res = SupInv::supInvUpdate($uniq_code);
            // log_arr($sup_inv_res, 'wms');
            //入库单正品瑕疵数量更新

            $ib_id = $recv_detail_item->ib_id ?? 0;
            $ib_item = $ib_id == 0 ? null : IbOrder::where('doc_status', 3)->find($ib_id);
            if ($ib_item) {
                $ib_detail_item = IbDetail::where('ib_code', $ib_item->ib_code)->where('sup_id', $recv_detail_item->sup_id)
                    ->where('bar_code', $recv_detail_item->bar_code)->where('buy_price', $recv_detail_item->buy_price)->first();
                $normal_count = $ib_detail_item->normal_count;
                $flaw_count = $ib_detail_item->flaw_count;
                if (in_array($ib_item->ib_type, [1, 4])) {
                    //采购单/其他入库单匹配完入库单之后再质检时正品瑕疵数量更新
                    if ($quality_type != $origin_quality_type) {
                        $flaw_count = $quality_type == 2 ? $flaw_count + 1 : $flaw_count - 1;
                        $normal_count = $quality_type == 2 ? $normal_count - 1 : $normal_count + 1;
                        $row['ib_detail_item'] = $ib_detail_item->update([
                            'quality_level' => $quality_level,
                            'quality_type' => $quality_type,
                            'flaw_count' => $flaw_count,
                            'normal_count' => $normal_count,
                        ]);
                        $row['ib_item'] = $ib_item->update([
                            'normal_count' => $quality_type == 2 ? $ib_item->normal_count - 1 : $ib_item->normal_count + 1,
                            'flaw_count' => $quality_type == 2 ? $ib_item->flaw_count + 1 : $ib_item->flaw_count - 1,
                        ]);
                        if ($ib_item->ib_type == 1) {
                            if (strpos($ib_item->source_code, 'CG')) {
                                $pur_details_item = PurchaseDetails::where('buy_code', $ib_item->source_code)->where('bar_code', $recv_detail_item->bar_code)->where('buy_price', $recv_detail_item->buy_price)->first();
                                $row['pur_details_item'] = $pur_details_item->update([
                                    'normal_count' => $quality_type == 2 ? $pur_details_item->normal_count - 1 : $pur_details_item->normal_count + 1,
                                    'flaw_count' => $quality_type == 2 ? $pur_details_item->flaw_count + 1 : $pur_details_item->flaw_count - 1,
                                ]);
                            }
                            if (strpos($ib_item->source_code, 'JMD')) {
                                $pur_details_item = ConsignmentDetails::where('origin_code', $ib_item->source_code)->where('bar_code', $recv_detail_item->bar_code)->where('buy_price', $recv_detail_item->buy_price)->first();
                                $row['consign_details_item'] = $pur_details_item->update([
                                    'normal_count' => $quality_type == 2 ? $pur_details_item->normal_count - 1 : $pur_details_item->normal_count + 1,
                                    'flaw_count' => $quality_type == 2 ? $pur_details_item->flaw_count + 1 : $pur_details_item->flaw_count - 1,
                                ]);
                            }
                        }
                        if ($ib_item->ib_type == 4) {
                            $oib_details_item = OIbDetails::where('oib_code', $ib_item->third_no)->where('bar_code', $recv_detail_item->bar_code)->where('buy_price', $recv_detail_item->buy_price)->first();
                            $row['oib_details_item'] = $oib_details_item->update([
                                'normal_count' => $quality_type == 2 ? $pur_details_item->normal_count - 1 : $pur_details_item->normal_count + 1,
                                'flaw_count' => $quality_type == 2 ? $pur_details_item->flaw_count + 1 : $pur_details_item->flaw_count - 1,
                            ]);
                        }
                    }
                } else {
                    //退调货质检之后正品瑕疵数量更新
                    if ($quality_type == 2) $flaw_count += 1;
                    else  $normal_count += 1;
                    $row['ib_detail_item'] = $ib_detail_item->update([
                        'quality_level' => $quality_level,
                        'quality_type' => $quality_type,
                        'flaw_count' => $flaw_count,
                        'normal_count' => $normal_count,
                    ]);
                    $row['ib_item'] = $ib_item->update([
                        'normal_count' => $quality_type == 2 ? $ib_item->normal_count : $ib_item->normal_count + 1,
                        'flaw_count' => $quality_type == 2 ? $ib_item->flaw_count + 1 : $ib_item->flaw_count,
                    ]);
                }
            }

            log_arr($row, 'wms');
            if ($transcation) return self::endTransaction($row)[0];
        }



        return [true, 'success'];
    }

    //上架位置码更新
    public static function locationUpdate($uniq_code, $area_code, $location_code, $transcation = true)
    {
        $update = [
            'area_code' => $area_code,
            'location_code' => $location_code,
            'is_putway' => 1,
        ];
        if ($transcation) self::startTransaction();
        //收货单详情 位置更新
        $row['recv_details_location_update'] =  self::where('tenant_id', request()->header('tenant_id'))->where('uniq_code', $uniq_code)->update($update);
        //库存 位置更新 库存状态更新
        $inv_update = [
            'in_wh_status' => 3
        ];
        // $row['inv_location_update'] = Inventory::where('tenant_id', request()->header('tenant_id'))->where('uniq_code', $uniq_code)->update(array_merge($update,$inv_update));
        $row['inv_location_update'] = Inventory::invStatusUpdate($uniq_code, 3, null, $update);
        log_arr($row, 'wms');
        if ($transcation)  return self::endTransaction($row)[0];

        return [true, 'success'];
    }

    public static function buyExport($arr_id)
    {
        $items = self::where('arr_id', $arr_id)->where('sku', '')->select('bar_code','sku')->groupBy('bar_code')->get();
        foreach($items as $item){
            if($item->sku == ''){
                $item->sku = empty($item->product->sku)?'':$item->product->sku;
                $item->where('arr_id', $arr_id)->where('bar_code',$item->bar_code)->where('sku','')->update(['sku'=> $item->sku]);
                Inventory::where('arr_id', $arr_id)->where('bar_code',$item->bar_code)->where('sku','')->update(['sku'=> $item->sku]);
            }
        }
        $items = self::with('supplier')->where('arr_id', $arr_id)->where('ib_confirm', 0)->where('sup_confirm', 1)->selectRaw('arr_id,sku,bar_code,sup_id,count(*) as count')->groupByRaw('arr_id,sup_id,bar_code')->orderBy('arr_id')->orderBy('sup_id')->orderBy('sku');

        return $items->get()->toArray();
    }

    public static function arrExport($arr_id)
    {
        $items = self::where('arr_id', $arr_id)
            ->selectRaw('arr_id,sku,bar_code,quality_type,quality_level,if(quality_level="A","",uniq_code ) as uniq_code_1,count(*) as recv_num,count(if(sup_confirm=0,1,null)) as sup_confirm,count(if(ib_confirm=0,1,null)) as ib_confirm')
            ->groupByRaw('arr_id,bar_code,quality_type,quality_level')->orderBy('sku');
        return $items->get()->toArray();
    }
}
