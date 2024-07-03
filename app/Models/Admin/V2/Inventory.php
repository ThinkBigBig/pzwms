<?php

namespace App\Models\Admin\V2;

use App\Logics\RedisKey;
use App\Models\Admin\wmsBaseModel;
use App\Models\Admin\V2\UniqCodePrintLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\V2\ProductSpecAndBar;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class Inventory extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_inv_goods_detail';
    protected $guarded = [];

    // 销售 0-不可售 1-待售 ,2-已匹配销售单 3-已配货 4-已发货
    const SALE_STATUS_DISABLE = 0; //不可售
    const SALE_STATUS_ENABLE = 1; //可售
    const SALE_STATUS_ORDER = 2; //已匹配销售单
    const SALE_STATUS_MATCH = 3; //已配货
    const SALE_STATUS_SHIPPED = 4; //已发货

    // 在仓状态 0-暂存 1-已收货 ,2-已质检 3-已上架 4-已出库 5-调拨中 6-冻结 7-作废 8-移位中
    const STASH = 0;
    const RECEIVED = 1;
    const QC = 2;
    const PUTAWAY = 3;
    const OUT = 4;
    const TRANSFERING = 5;
    const FREEZED = 6;
    const INVALID = 7;
    const MOVING = 8;
    // protected $with = ['product:id,product_id,sku,bar_code,spec_one,tenant_id,type'];
    protected $appends = ['inv_status_txt'];

    // protected $with = ['product:id,product_id,sku,bar_code,spec_one,tenant_id','location:location_code,area_code,status'];

    // protected $map = [
    //     'inv_status' => [
    //         0 => '在仓',
    //         1 => '架上',
    //         2 => '可售',
    //         3 => '待上架',
    //         4 => '架上待确认',
    //         5 => '架上可售',
    //         6 => '架上锁定',
    //         7 => '待发',
    //         8 => '调拨',
    //         9 => '冻结',
    //     ],
    // ];
    protected $map;
    public function __construct($params = [])
    {
        parent::__construct($params);
        $this->map = [
            'inv_status' => [
                0 => __('status.wh_inv'),
                1 => __('status.shelf_inv'),
                2 => __('status.sale_inv'),
                3 => __('status.wt_shelf_inv'),
                4 => __('status.wt_shelf_cfm'),
                5 => __('status.shelf_sale_inv'),
                6 => __('status.shelf_lock_inv'),
                7 => __('status.wt_send_inv'),
                8 => __('status.trf_inv'),
                9 => __('status.freeze_inv'),
            ],
        ];
    }
    public function product()
    {
        return $this->hasOne(ProductSpecAndBar::class, 'bar_code', 'bar_code')->withDefault();
    }

    
    public function getProductSpecTwoAttribute($value)
    {
        $value = $this->product->spec_one;
        return $value ? $value : '';
    }

    
    public function getProductSpecThreeAttribute($value)
    {
        $value = $this->product->spec_one;
        return $value ? $value : '';
    }


    public function supplier()
    {
        return $this->hasOne(Supplier::class, 'id', 'sup_id');
    }

    public function warehouse()
    {
        return $this->hasOne(Warehouse::class, 'warehouse_code', 'warehouse_code');
    }

    public function arrItem()
    {
        return $this->hasOne(ArrivalRegist::class, 'id', 'arr_id');
    }

    public function warehouseArea()
    {
        return $this->hasOne(WmsWarehouseArea::class, 'area_code', 'area_code');
    }

    public function arrivalRegist()
    {
        return $this->hasOne(ArrivalRegist::class, 'id', 'arr_id');
    }

    public function  searchArea($value)
    {
        if ($value == __('status.qc_staging_area')) return ['area_code', 'ZJZCQ001'];
        if ($value == __('status.off_shelf_staging_area')) return ['area_code', 'XJZCQ001'];
        if ($value == __('status.recv_staging_area')) return ['area_code', 'SHZCQ001'];
        $area = WmsWarehouseArea::where('area_name', $value)->where('status', 1)->orderBy('id', 'desc')->first();
        if ($area) return ['area_code', $area->area_code];
    }


    public function getAreaTxtAttribute($key)
    {
        if ($this->area_code == 'ZJZCQ001') return __('status.qc_staging_area');
        if ($this->area_code == 'XJZCQ001') return __('status.off_shelf_staging_area');
        if ($this->area_code == 'SHZCQ001') return __('status.recv_staging_area');
        if ($this->area_code == 'SJZCQ001') return __('status.on_shelf_staging_area');
        $tenant_id = $this->tenant_id;
        $item = DB::table('wms_warehouse_area')->where('tenant_id', $tenant_id)->where('warehouse_code', $this->warehouse_code)->where('area_code', $this->area_code)->where('status', 1)->first();
        if (empty($item)) return '';
        return $item->area_name;
    }

    public function area_txt($area_code, $warehouse_code)
    {
        if ($area_code == 'ZJZCQ001') return __('status.qc_staging_area');
        if ($area_code == 'XJZCQ001') return __('status.off_shelf_staging_area');
        if ($area_code == 'SHZCQ001') return __('status.recv_staging_area');
        if ($area_code == 'SJZCQ001') return __('status.on_shelf_staging_area');
        $tenant_id = request()->header('tenant_id');
        $item = DB::table('wms_warehouse_area')->where('tenant_id', $tenant_id)->where('warehouse_code', $warehouse_code)->where('area_code', $area_code)->where('status', 1)->first();
        if (empty($item)) return '';
        return $item->area_name;
    }



    // public function getInvStatusTxTAttribute($key)
    // {

    //     $inv_status = $this->in_wh_status;
    //     $sale_status = $this->sale_status;
    //     $res = '';
    //     switch ($inv_status) {
    //         case 6:
    //             $res  = '冻结';break;
    //         case 5:
    //             $res  = '调拨中';break;
    //         case 4:
    //             $res  = '已出库'; break;
    //         case 3:
    //             if($sale_status == 0 && $this->buy_price == 0 )$res ='待上架确认';
    //             if($sale_status == 1)$res  = '架上可售';
    //             if($sale_status == 2)$res  = '架上锁定';
    //             if($sale_status == 3)$res  = '待发货';
    //             break;
    //         default:
    //             if($this->buy_price != 0)$res = '待上架可售';
    //             else $res = '在仓';
    //             break;
    //     }
    //     return  $res;
    // }


    public function withSearch($select)
    {
        return $this::with(['warehouse:warehouse_code,warehouse_name,status,type', 'supplier', 'product:id,product_id,sku,bar_code,spec_one,tenant_id,type'])->whereNotIN('in_wh_status', [0, 4, 7])->select($select);
    }

    // public function listold($where, $select = ['*'], $order = [['id', 'desc']], $cur_page = 1, $size = 10)
    // {
    //     $data = $this::with(['warehouse'])->whereNotIN('in_wh_status', [0, 4, 7]);
    //     foreach ($where as $v) {
    //         if (empty($v[1])) continue;
    //         if ($v[1] == 'in' || $v[1] == 'IN') {
    //             if (is_array($v[2])) {
    //                 $data->whereIn($v[0], $v[2]);
    //             } else {
    //                 $in  = explode(',', $v[2]);
    //                 $data->whereIn($v[0], $in);
    //             }
    //         }
    //         if ($v[1] == 'like') $data->where($v[0], $v[1], '%' . $v[2] . '%');
    //         if ($v[1] == 'allLike') $data->whereRaw($v[0], $v[2]);
    //         if ($v[1] == 'WHERE' && !empty($v[2])) {
    //             $sql = $this->jsonWhere($v[2]);
    //             if (!empty($sql)) {
    //                 if (preg_match('/and|or/', $sql)) {
    //                     $data->where(function ($q) use ($sql) {
    //                         return $q->whereRaw($sql);
    //                     });
    //                 } else {
    //                     $data->whereRaw($sql);
    //                 }
    //             }
    //         }
    //         if (in_array($v[1], $this->whereSymbol)) $data->where($v[0], $v[1], $v[2]);
    //     }
    //     // dd($order);
    //     //处理排序
    //     foreach ($order as $Ok => $Ov) {
    //         if (empty($Ov[0]) || empty($Ov[1])) continue;
    //         $data->orderBy($Ov[0], $Ov[1]);
    //     }
    //     // $item = $data->select('bar_code','quality_type','quality_level','location_code','warehouse_code','area_code','tenant_id','inv_status',
    //     // DB::raw('count(If(in_wh_status in(1,2,3),true,null)) "wh_inv"'), //在仓库存
    //     // DB::raw('count(If(in_wh_status = 3,true,null)) "shelf_inv"'), //架上库存
    //     // DB::raw('count(If(buy_price !=0,true,null)) "sale_inv"'), //可售库存
    //     // DB::raw('count(If(in_wh_status = 3 and g.sale_status = 1 ,true,null)) "shelf_sale_inv"'), //架上可售库存
    //     // DB::raw('count(If(in_wh_status = 3 and g.sale_status = 2,true,null)) "shelf_lock_inv"'), //架上锁定库存
    //     // DB::raw('count(If(sale_status = 3,true,null)) "wt_send_inv"'), //待发库存
    //     // DB::raw('count(If(in_wh_status in(1,2),true,null)) "wt_shelf_inv"'), //待上架库存
    //     // DB::raw('count(If(in_wh_status = 6,true,null)) "freeze_inv"'), //冻结库存
    //     // DB::raw('count(If(in_wh_status = 3 and  g.buy_price = 0,true,null)) "wt_shelf_cfm"'), //架上待确认
    //     // DB::raw('count(If(in_wh_status = 5,true,null)) "trf_inv"') //调拨中
    //     // )->groupBy('bar_code')
    //     // ->paginate($size,'','page',$cur_page);
    //     // return  $item->toArray();

    //     $item = $data->select(
    //         'bar_code',
    //         'quality_type',
    //         'quality_level',
    //         'warehouse_code',
    //         'in_wh_status',
    //         'sale_status',
    //         'inv_status',
    //         DB::raw('count(*) as wh_inv'), //在仓库存
    //         DB::raw('count(If(in_wh_status = 3,true,null)) "shelf_inv"'), //架上库存
    //         DB::raw('count(If(sale_status =1 ,true,null)) "sale_inv"'), //可售库存
    //         DB::raw('count(If(inv_status = 5 ,true,null)) "shelf_sale_inv"'), //架上可售库存
    //         DB::raw('count(If(inv_status = 6,true,null)) "shelf_lock_inv"'), //架上锁定库存
    //         DB::raw('count(If(inv_status = 7,true,null)) "wt_send_inv"'), //待发库存
    //         DB::raw('count(If(inv_status = 3,true,null)) "wt_shelf_inv"'), //待上架库存
    //         DB::raw('count(If(in_wh_status = 6,true,null)) "freeze_inv"'), //冻结库存
    //         DB::raw('count(If(inv_status = 4,true,null)) "wt_shelf_cfm"'), //架上待确认
    //         DB::raw('count(If(in_wh_status = 5,true,null)) "trf_inv"') //调拨中
    //     )->groupBy('bar_code')
    //         ->paginate($size, '', 'page', $cur_page);
    //     return  $item->toArray();
    // }

    public function list($where_data, $select = ['*'], $order = [['id', 'desc']], $cur_page = 1, $size = 10)
    {
        // $data = TotalInv::query()->with(['warehouse','product']);
        $data = TotalInv::query()->with(['product']);
        $this->invSearch($where_data, $order, $data);
        $reData = $data->paginate($size, ['*'], 'page', $cur_page)->toArray();
        // if (method_exists($this, '_formatList')) $reData = $this->_formatList($reData);
        return $reData;
    }

    //仓库总库存点击数字查看唯一码信息
    public function invDetail($input, $where_data, $select = ['*'], $order = [['id', 'desc']], $cur_page = 1, $size = 10)
    {
        $row = json_decode($input['row'], 1);
        if ($row) {
            $item = $this::with(['product'])->where('warehouse_code', $row['warehouse_code'])->where('bar_code', $row['bar_code'])
                ->where('quality_level', $row['quality_level'])
                ->whereNotin('in_wh_status', [0, 4, 7]);
            if (!empty($row['uniq_code_1'])) $item->where('uniq_code', $row['uniq_code_1']);
            if (isset($row['sup_id'])) $item->where('sup_id', $row['sup_id']);
            if (isset($row['buy_price'])) $item->where('buy_price', $row['buy_price']);
            if (isset($row['lot_num'])) $item->where('lot_num', $row['lot_num']);

            $this->invSearch($where_data, $order, $item);
            switch ($input['name']) {
                case 'wh_inv':
                    $data  = $item;
                    break;
                case 'shelf_inv':
                    $data  = $item->where('in_wh_status', 3);
                    break;
                case 'sale_inv':
                    $data  = $item->where('sale_status', 1);
                    break;
                case 'shelf_sale_inv':
                    $data  = $item->where('inv_status', 5);
                    break;
                case 'shelf_lock_inv':
                    $data  = $item->where('inv_status', 6);
                    break;
                case 'wt_send_inv':
                    $data  = $item->where('inv_status', 7);
                    break;
                case 'wt_shelf_inv':
                    $data  = $item->where('inv_status', 3);
                    break;
                case 'freeze_inv':
                    $data  = $item->where('in_wh_status', 6);
                    break;
                case 'wt_shelf_cfm':
                    $data  = $item->where('inv_status', 4);
                    break;
                case 'trf_inv':
                    $data  = $item->where('in_wh_status', 5);
                    break;
                case 'lock_inv':
                    $data  = $item->where('sale_status', 2);
                    break;
                default:
                    $data = $item;
            }
        } else {
            $data = $this::with(['product'])->where('id', 0);
        }
        if (!empty($input['get_order_info']) && in_array($input['name'], ['freeze_inv', 'wt_send_inv', 'lock_inv', 'shelf_lock_inv'])) {
            return SupInv::invOrderInfo($input['name'], $data);
        } else {
            $reData = $data->paginate($size, ['*'], 'page', $cur_page)->toArray();
            if (method_exists($this, '_formatList')) $reData = $this->_formatList($reData, 0);
            // $data->append(['product_sn','product_name','product_sku','product_spec'])->makeHidden(['product']);
            return $reData;
        }
    }


    //新增库存
    public static function add($products, $update)
    {
        $create_data = [];
        $bar_codes = [];
        foreach ($products as $pro) {
            $pro = $pro->attributes;
            $bar_codes[] = $pro['bar_code'];
            $temp = [
                'sale_status' => 0,
                'recv_num' => 1,
                'area_code' => $pro['area_code'],
                'ib_at' => $pro['ib_at'],
                'done_at' => $pro['done_at'],
                'created_user' => $pro['created_user'],
                'start_at' => $pro['created_at'],
                'arr_id' => $pro['arr_id'],
                'lot_num' => $pro['lot_num'],
                'recv_id' => $pro['recv_id'],
                'bar_code' => $pro['bar_code'],
                'uniq_code' => $pro['uniq_code'],
                'warehouse_code' => $pro['warehouse_code'],
                'quality_type' => $pro['quality_type'],
                'quality_level' => $pro['quality_level'],
                'sup_id' => $pro['sup_id'],
                'recv_unit' => $pro['recv_unit'],
                'is_qc' => $pro['is_qc'],
                'is_putway' => $pro['is_putway'],
                'buy_price' => $pro['buy_price'],
                'tenant_id' => $pro['tenant_id'],
                'created_at' => date('Y-m-d H:i:s'),
                'sku' => $pro['sku'],
            ];
            $temp = array_merge($temp, $update);
            $temp['in_wh_status'] = $temp['is_qc'] == 1 ? 2 : 1;
            $create_data[] = $temp;
            Inventory::updateOrCreate([
                'updated_user' => $pro['created_user'],
                'uniq_code' => $pro['uniq_code'], 'warehouse_code' => $pro['warehouse_code'], 'tenant_id' => $pro['tenant_id'],
            ], $temp);
        }
        // $row = self::insert($create_data);

        //刷新总库存
        foreach ($bar_codes as $bar_code) {
            // $total_inv_update = self::totalInvUpdate($pro['warehouse_code'], $bar_code);
            // log_arr($total_inv_update, 'wms');
            self::invAsyncAdd(0, 2, $pro['warehouse_code'], $bar_code);
        }
        return true;
    }


    //库存查询
    protected function invSearch($where, $order, &$data)
    {
        foreach ($where as $v) {
            if (empty($v[1])) continue;
            if ($v[1] == 'in' || $v[1] == 'IN') {
                if (is_array($v[2])) {
                    $data->whereIn($v[0], $v[2]);
                } else {
                    $in  = explode(',', $v[2]);
                    $data->whereIn($v[0], $in);
                }
            }
            if ($v[1] == 'like') $data->where($v[0], $v[1], '%' . $v[2] . '%');
            if ($v[1] == 'allLike') $data->whereRaw($v[0], $v[2]);
            if ($v[1] == 'WHERE' && !empty($v[2])) {
                $sql = $this->jsonWhere($v[2]);
                if (!empty($sql)) {
                    if (preg_match('/and|or/', $sql)) {
                        $data->where(function ($q) use ($sql) {
                            return $q->whereRaw($sql);
                        });
                    } else {
                        $data->whereRaw($sql);
                    }
                }
            }
            if (in_array($v[1], $this->whereSymbol)) $data->where($v[0], $v[1], $v[2]);
        }
        //处理排序
        foreach ($order as $Ok => $Ov) {
            if (empty($Ov[0]) || empty($Ov[1])) continue;
            $data->orderBy($Ov[0], $Ov[1]);
        }
    }

    //库存状态修改
    public static function invStatusUpdate($uniq_code, $in_wh_status = 1, $sale_status = 0, $update = [], $_id = [])
    {
        if (!empty($_id['recv_id'])) $item = self::where('recv_id', $_id['recv_id'])->where('uniq_code', $uniq_code);
        elseif (!empty($_id['id'])) $item = self::where('id', $_id['id']);
        else $item = self::where('uniq_code', $uniq_code);
        if ($item->get()->count() != 1) return [false, '库存数量不正确'];
        $item = $item->first();
        if ($in_wh_status != null || $sale_status != null) {
            $in_wh_status_final = $item->in_wh_status;
            $sale_status_final = $item->sale_status;
            if (!empty($in_wh_status) && $in_wh_status != 1) {
                //修改在仓状态
                $update['in_wh_status'] = $in_wh_status;
                $in_wh_status_final = $in_wh_status;
            }
            if (!empty($sale_status)) {
                //修改销售状态
                $update['sale_status'] = $sale_status;
                $sale_status_final = $sale_status;
            }

            //判断库存状态
            // if (in_array($in_wh_status_final, [0, 1, 2, 8]) && $sale_status_final == 0) {
            if (in_array($in_wh_status_final, [0, 1, 2, 8])) {
                $inv_status = 3;
                // if($sale_status_final == 0) $inv_status = 0;
                // elseif($sale_status_final == 1) $inv_status = 2;
            } elseif ($in_wh_status_final == 3) {
                if ($sale_status_final == 0) $inv_status = 4; //架上待确认
                elseif ($sale_status_final == 1) $inv_status = 5; //架上可售
                elseif ($sale_status_final == 2) $inv_status = 6; //架上锁定
                else $inv_status = 1; //架上
            } elseif ($in_wh_status_final == 5) {
                $inv_status = 8; //调拨
            } elseif ($in_wh_status_final == 6) {
                $inv_status = 9; //冻结
            } else {
                if ($sale_status_final == 3 && $in_wh_status_final == 9) $inv_status = 7; //待发
                else $inv_status = 0;
            }
            $update['inv_status'] = $inv_status;
        }
        $row = $item->update($update);
        //总库存更新
        if ($row) {
            // list($row, $msg) = self::totalInvUpdate($item->warehouse_code, $item->bar_code);
            // if (!$row) return [false, $msg];
            self::invAsyncAdd(0, 2, $item->warehouse_code, $item->bar_code);
        }
        return [$row, ''];
    }

    //供应商产品库存
    // public function supInv1($warehouse_code = null, $where_data, $select = ['*'], $order = [['id', 'desc']], $cur_page = 1, $size = 10)
    // {
    //     $data = $this::with(['supplier:id,name', 'warehouse'])->whereNotin('in_wh_status', [0, 4, 7])->where('sup_id', '<>', 0);
    //     // $data = $this::from('wms_inv_goods_detail as g')->whereNotin('g.in_wh_status', [0, 4, 7])->where('g.sup_id', '<>', 0);
    //     if ($warehouse_code) $data = $data->where('warehouse_code', $warehouse_code);
    //     $this->invSearch($where_data, $order, $data);
    //     $model = $data->select(
    //         'bar_code',
    //         'warehouse_code',
    //         'lot_num',
    //         'quality_level',
    //         'sup_id',
    //         'buy_price',
    //         'sale_status',
    //         DB::raw('count(*) as wh_inv'),
    //         DB::raw('count(if(sale_status=1,true,null )) as sale_inv'),
    //         DB::raw('count(if(sale_status=2,true,null )) as lock_inv'),
    //         DB::raw('if(quality_level<>"A",uniq_code,"" ) as uniq_code_1'),
    //     )->groupByRaw('bar_code,lot_num,uniq_code_1,sup_id,buy_price')->orderBy('id', 'desc')->limit(10);
    //     // ->append(['product_name', 'product_sku', 'product_spec','product_sn', 'supplier_name', 'wh_name', 'quality_type'])->makeHidden(['product', 'supplier', 'warehouse', 'inv_status_txt', 'sale_status']);
    //     $count = DB::table($model)->count();
    //     // dd($this->invPaginator($model,$count,$size,$select,'page',$cur_page));
    //     return  $this->invPaginator($model, $count, $size, $select, 'page', $cur_page);
    // }

    //供应商产品库存
    public function supInv($warehouse_code = null, $where_data, $select = ['*'], $order = [['id', 'desc']], $cur_page = 1, $size = 10)
    {
        $data = SupInv::query()->where('sale_inv', '<>', 0);
        if ($warehouse_code) $data = $data->where('warehouse_code', $warehouse_code);
        $this->invSearch($where_data, [['id', 'desc']], $data);
        $reData = $data->paginate($size, ['*'], 'page', $cur_page)->toArray();
        if (method_exists($this, '_formatList')) $reData = $this->_formatList($reData);
        return $reData;
    }

    function _formatList($item, $search = 1)
    {
        foreach ($item['data'] as &$pro) {
            if (!empty($pro['area_code'])) $pro['area_txt'] = $this->area_txt($pro['area_code'], $pro['warehouse_code']);
            $pro['wh_name'] =  empty($pro['warehouse_name']) ? Warehouse::getName($pro['warehouse_code']) : $pro['warehouse_name'];
            $pro['supplier_name'] = empty($pro['sup_name']) ? Supplier::getName($pro['sup_id']) : $pro['sup_name'];
            if ($search) {
                $pro_info = ProductSpecAndBar::getInfo($pro['bar_code']);
                $pro['product_name'] =  $pro_info['name'];
                $pro['product_sku'] =  $pro_info['sku'];
                $pro['product_spec'] =  $pro_info['spec'];
                $pro['product_spec_two'] =  $pro_info['spec_two'];
                $pro['product_spec_three'] =  $pro_info['spec_three'];
                $pro['product_sn'] =  $pro_info['product_sn'];
            } else {
                $pro['product_sn'] = $pro['product']['product']['product_sn'] ?? "";
                $pro['name'] = $pro['product']['product']['name'] ?? "";
                // $pro['img'] = $pro['product']['product']['img']??";
                $pro['sku_code'] = $pro['product']['sku'] ?? "";
                $pro['spec_one'] = $pro['product']['spec_one'] ?? "";
                $pro['spec_two'] = $pro['product']['spec_two'] ?? "";
                $pro['spec_three'] = $pro['product']['spec_three'] ?? "";
                // $pro['product_bar_code'] = $pro['product']['bar_code'];
                unset($pro['product']);
            }
        }
        return $item;
    }

    //唯一码库存
    public function  uniqInv($warehouse_code, $uniq_code, $where_data, $select = ['*'], $order = [['id', 'desc']], $cur_page = 1, $size = 10)
    {
        $uniq_code = explode(',', $uniq_code);
        $data = $this::where('warehouse_code', $warehouse_code)->whereIn('uniq_code', $uniq_code)->whereNotin('in_wh_status', [0, 4, 7])->where('sup_id', '<>', 0);
        $this->invSearch($where_data, [['id', 'desc']], $data);
        $data = $data->select(
            'bar_code',
            'warehouse_code',
            'lot_num',
            'quality_level',
            'sup_id',
            'buy_price',
            'sale_status',
            DB::raw('1 as wh_inv'),
            DB::raw('if(sale_status=1,true,0 ) as sale_inv'),
            DB::raw('if(sale_status=2,true,0 ) as lock_inv'),
            DB::raw('uniq_code as uniq_code_1'),
        );
        // ->paginate($size, ['*'], 'page', $cur_page)->toArray();;
        //  $reData = $this->_formatList($reData);
        // return $reData;
        $total = $data->count();
        if (!$total) $data = [];
        return  $this->invPaginator($data, $total, $size, '*', 'page', $cur_page);
    }


    //架上库存
    public function putawayInv($warehouse_code, $where_data, $select = ['*'], $order = [['id', 'desc']], $cur_page, $size = 10)
    {
        $data = $this::selectRaw('warehouse_code,bar_code,area_code,location_code,inv_status,quality_type,quality_level,count(*) as count');
        if ($warehouse_code) {
            $data->where('warehouse_code', $warehouse_code);
        }
        $this->invSearch($where_data, $order, $data);
        $items = $data->where('in_wh_status', 3)
            ->groupByRaw('warehouse_code,bar_code,location_code,quality_type,quality_level');
        $total = DB::table($items)->count();
        // foreach ($items as $pro) {
        //     $pro->name = $pro->product->product->name ?? '';
        //     $pro->product_sn = $pro->product->product->product_sn ?? '';
        //     $pro->product_sku = $pro->product->sku ?? '';
        //     $pro->product_spec = $pro->product->spec_one ?? '';
        //     $pro->product_bar_code = $pro->product->bar_code ?? '';
        //     $pro->makeHidden('product');
        //     ('product');
        // }

        // return [true, $items->toArray()];
        return $this->invPaginator($items, $total, $size, $select, $cur_page);
    }

    //业务库存--检查库存
    public static function checkInv($warehouse_code, $count, $pro)
    {
        // dump($pro,$count);
        $where = [
            'bar_code' => $pro['bar_code'],
        ];
        if (!empty($pro['uniq_code'])) $where['uniq_code'] = $pro['uniq_code'];
        if (!empty($pro['sup_id'])) $where['sup_id'] = $pro['sup_id'];
        if (!empty($pro['buy_price']) && $pro['buy_price'] != 0) $where['buy_price'] = $pro['buy_price'];
        else {
            if (!empty($pro['sup_id']) && !empty($pro['buy_price'])) $where['buy_price'] = $pro['buy_price'];
        }
        if (!empty($pro['quality_level'])) $where['quality_level'] = $pro['quality_level'];
        if (!empty($pro['batch_no'])) $where['lot_num'] = $pro['batch_no'];
        $ids = self::where('warehouse_code', $warehouse_code)->where('sale_status', 1)->where($where)->limit($count)->get()->modelKeys();
        // if ($uniq_code) {
        //     $ids =  self::where('warehouse_code', $warehouse_code)->where('sale_status', 1)->where('bar_code', $bar_code)->where('uniq_code', $uniq_code)->get()->modelKeys();
        // } else {
        //     if ($buy_price) {
        //         $ids = self::where('warehouse_code', $warehouse_code)->where('sale_status', 1)->where('bar_code', $bar_code)
        //             ->where('lot_num', $batch_no)->where('quality_level', $quality_level)->where('sup_id', $sup_id)->where('buy_price', $buy_price)->limit($count)->get()->modelKeys();
        //     } else {
        //         $ids = self::where('warehouse_code', $warehouse_code)->where('sale_status', 1)->where('bar_code', $bar_code)
        //             ->where('lot_num', $batch_no)->where('quality_level', $quality_level)->where('sup_id', $sup_id)->limit($count)->get()->modelKeys();
        //     }
        // }
        return $ids;
    }

    // 异步更新供应商和商品库存
    static public function invAsyncAdd($id = 0, $type = 1, $warehouse_code = '', $bar_code = '', $uniq_code = '', $lock = 0)
    {
        $data = [
            'params' => ['tenant_id' => request()->header('tenant_id'), 'id' => $id, 'type' => $type, 'warehouse_code' => $warehouse_code, 'bar_code' => $bar_code, 'uniq_code' => $uniq_code, 'lock' => $lock],
            'class' => 'App\Models\Admin\V2\Inventory',
            'method' => 'supInvAsync',
            'time' => date('Y-m-d H:i:s'),
        ];
        Redis::rpush(RedisKey::QUEUE2_AYSNC_HADNLE, json_encode($data));
    }

    // 异步更新供应商和商品库存
    public function supInvAsync($params)
    {
        $id = $params['id'];
        $type = $params['type'];
        $uniq_code = $params['uniq_code'] ?? '';
        $lock = $params['lock'] ?? 0;
        if ($type == 1) {
            SupInv::supInvUpdate('', $lock, $id);
        }
        if ($type == 2) {
            self::totalInvUpdate($params['warehouse_code'] ?? '', $params['bar_code'] ?? '');
        }
        if ($type == 3) {
            SupInv::supInvUpdate($uniq_code);
        }
    }

    //锁定库存
    public static function lockInv($ids, $type = 0, $code = '')
    {
        foreach ($ids as $id) {
            $row = self::invStatusUpdate('', null, 2, ['lock_type' => $type, 'lock_code' => $code], ['id' => $id])[0];
            if (!$row) return false;
            // $res = SupInv::supInvUpdate('',1,$id);
            // $res = SupInv::supInvUpdate('', null, $id);
            self::invAsyncAdd($id, 1, '', '', '', 1);
            // log_arr($res, 'wms');
        }
        return  true;
    }

    //锁定库存
    public static function lockInvById($ids, $lock_type = 0, $code = '')
    {
        $query = self::whereIn('id', $ids);
        $flush_data = Inventory::getInvFlushData($query);
        $row = $query->update([
            'lock_type' => $lock_type,
            'lock_code' => $code,
            'sale_status' => 2,
            'inv_status' => DB::raw('if(inv_status=5,6,inv_status)'),
        ]);
        if (!$row || $row != count($ids)) return false;
        //供应商和总库存更新
        self::newInvByBar($flush_data, 6);
        return $row;
    }

    //释放库存
    public static function releaseInvByCode($codes)
    {
        $query =  self::whereIn('lock_code', $codes)->lockForUpdate();
        $flush_data = self::getInvFlushData($query);
        $row = $query->update([
            'lock_type' => 0,
            'lock_code' => '',
            'sale_status' => 1,
            'inv_status' => DB::raw('if(inv_status=6,5,inv_status)'),

        ]);
        if (!$row) return false;
        //供应商和总库存更新
        self::newInvByBar($flush_data, 11);
        return $row;
    }
    
    //修改价格
    public static function editBuyPriceInv(self $query)
    {
        $query =  $query->lockForUpdate();
        $flush_data = self::getInvFlushData($query);
        $row = $query->update([
            'lock_type' => 0,
            'lock_code' => '',
            'sale_status' => 1,
            'inv_status' => DB::raw('if(inv_status=6,5,inv_status)'),

        ]);
        if (!$row) return false;
        //供应商和总库存更新
        self::newInvByBar($flush_data, 11);
        return $row;
    }

    //释放库存
    public static function releaseInv($ids)
    {
        if (is_string($ids)) $ids = explode(',', $ids);
        foreach ($ids as $id) {
            $row = self::invStatusUpdate('', null, 1, ['lock_type' => 0, 'lock_code' => ''], ['id' => $id])[0];
            //供应商库存更新
            if (!$row) return false;
            // $res = SupInv::supInvUpdate('',-1,$id);
            // $res = SupInv::supInvUpdate('', null, $id);
            // log_arr($res, 'wms');
            self::invAsyncAdd($id, 1, '', '', '', -1);
        }
        return  true;
    }

    //手动创建分页器
    public static function invPaginator($items, $total, $perPage = 15, $columns = ['*'], $pageName = 'page', $currentPage = null)
    {
        $currentPage = $currentPage ?: Paginator::resolveCurrentPage($pageName);
        $options = [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ];
        if ($total == 0) return  Container::getInstance()->makeWith(LengthAwarePaginator::class, compact(
            'items',
            'total',
            'perPage',
            'currentPage',
            'options'
        ))->toArray();
        $items = $items->get()->append(['product_name', 'product_sku', 'product_spec', 'product_spec_two','product_spec_three','product_sn', 'supplier_name', 'wh_name', 'quality_type'])->makeHidden(['product', 'supplier', 'warehouse', 'inv_status_txt', 'sale_status'])->toArray();

        $items = array_slice($items, ($currentPage - 1) * $perPage, $perPage); //注释1
        return  Container::getInstance()->makeWith(LengthAwarePaginator::class, compact(
            'items',
            'total',
            'perPage',
            'currentPage',
            'options'
        ))->toArray();
    }


    //仓库总库存数据更新
    public static function totalInvUpdate($warehouse_code, $bar_code)
    {
        $inv_data = self::from('wms_inv_goods_detail as inv')->where('inv.warehouse_code', $warehouse_code)->where('inv.bar_code', $bar_code)
            ->whereNotIn('in_wh_status', [0, 4, 7])
            // ->leftJoin('wms_warehouse as w','w.warehouse_code','=','inv.warehouse_code')
            ->select(
                'bar_code',
                'quality_type',
                'quality_level',
                'inv.warehouse_code',
                // 'w.warehouse_name',
                DB::raw('count(*) AS wh_inv'), //在仓库存
                DB::raw('count(If(in_wh_status = 3,true,null)) shelf_inv'), //架上库存
                DB::raw('count(If(sale_status = 1,true,null)) sale_inv'), //可售库存
                DB::raw('count(If(inv_status = 5 ,true,null)) shelf_sale_inv'), //架上可售库存
                DB::raw('count(If(inv_status = 6 ,true,null)) shelf_lock_inv'), //架上锁定库存
                DB::raw('count(If(inv_status = 7,true,null)) wt_send_inv'), //待发库存
                DB::raw('count(If(inv_status = 3,true,null)) wt_shelf_inv'), //待上架库存
                DB::raw('count(If(in_wh_status = 6,true,null)) freeze_inv'), //冻结库存
                DB::raw('count(If(inv_status = 4 ,true,null)) wt_shelf_cfm'), //架上待确认
                DB::raw('count(If(in_wh_status = 5,true,null)) trf_inv'), //调拨中
                'inv.tenant_id',
            )->groupBy('bar_code', 'quality_type', 'quality_level', 'warehouse_code', 'tenant_id')->get();
        $total_data = TotalInv::where('bar_code', $bar_code)->where('warehouse_code', $warehouse_code)->lockForUpdate();
        if($inv_data->isEmpty() && $total_data->exists()){
            $total_data->delete();
            return [true, '总库存更新成功'];
        }

        $total_level = $total_data->pluck('quality_level', 'id')->toArray();

        $warehouse_name = Warehouse::getName($warehouse_code);
        foreach ($inv_data as $item) {
            $upd_data = [
                'warehouse_name' => $warehouse_name,
                'wh_inv' => $item->wh_inv,
                'shelf_inv' => $item->shelf_inv,
                'sale_inv' => $item->sale_inv,
                'shelf_sale_inv' => $item->shelf_sale_inv,
                'shelf_lock_inv' => $item->shelf_lock_inv,
                'wt_send_inv' => $item->wt_send_inv,
                'wt_shelf_inv' => $item->wt_shelf_inv,
                'freeze_inv' => $item->freeze_inv,
                'wt_shelf_cfm' => $item->wt_shelf_cfm,
                'trf_inv' => $item->trf_inv,
                'updated_user' => request()->header('user_id', 0),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $cre_date = array_merge($upd_data, [
                'quality_type' => $item->getRawOriginal('quality_type'),
                'quality_level' => $item->quality_level,
                'bar_code' => $item->bar_code,
                'warehouse_code' => $item->warehouse_code,
                'tenant_id' => $item->tenant_id,
                'created_at' => date('Y-m-d H:i:s'),
                'created_user' => request()->header('user_id', 0),
            ]);
            $total_item = TotalInv::where('bar_code', $bar_code)->where('warehouse_code', $warehouse_code)
                ->where('quality_level', $item->quality_level)
                ->first();
            if ($total_item) {
                //更新
                $row = $total_item->update($upd_data);
                if (!$row) return [false, '总库存更新失败'];
                unset($total_level[$total_item->id]);
            } else {
                //新增
                $row = TotalInv::insert($cre_date);
                if (!$row) return [false, '总库存新增失败'];
            }
            if ($row) {
                unset($total_level[$item->quality_level]);
            }
        }
        if ($total_level) {
            //删除多余数据
            foreach ($total_level as $id => $level) {
                $row = TotalInv::find($id)->delete();
                if (!$row) return [false, '总库存id:' . $id . '删除失败'];
            }
        }
        return [true, '总库存更新成功'];
    }


    public static function invUpdate($params)
    {
        request()->headers->set('tenant_id', $params['tenant_id']);
        request()->headers->set('user_id', $params['user_id']);
        $type = $params['type'];
        if ($type == 1) SupInv::supInvUpdate(...$params['sup_data']);
        if ($type == 2) Inventory::totalInvUpdate(...$params['total_data']);
        if ($type == 3) {
            SupInv::supInvUpdate(...$params['sup_data']);
            Inventory::totalInvUpdate(...$params['total_data']);
        }
        if ($type == 4) {
            SupInv::supInvUpdateByBarcode(...$params['sup_data']);
            Inventory::totalInvUpdate(...$params['total_data']);
        }
        if ($type == 5) {
            SupInv::supInvUpdateByBarcodes(...$params['sup_data']);
            Inventory::totalInvUpdateByBarcodes(...$params['total_data']);
        }
    }

    //更新总库存
    public static function totalInvUpdateByUniq($uniq_codes)
    {
        $total_inv_data = self::whereIn('uniq_code', $uniq_codes)->select('warehouse_code', 'bar_code')->groupBy('warehouse_code', 'bar_code')->get()->toArray();
        foreach ($total_inv_data as $d) {
            self::totalInvUpdate($d['warehouse_code'], $d['bar_code']);
        }
    }

    //更新总库存
    public static function totalInvUpdateByBarcodes($warehouse_code,$bar_codes)
    {
        foreach ($bar_codes as $bar_code) {
            self::totalInvUpdate($warehouse_code, $bar_code);
        }
    }

    public static function getInvType($uniq_code)
    {
        $item =  self::where('uniq_code', $uniq_code)->orderBy('id', 'desc')->first();
        if ($item) return $item->inv_type;
        return 0;
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param  array $data  function getInvFlushData
     * @param  int  $type 1-收货 2-质检 3-上架 4-确认供应商 5-匹配入库单 6-锁定 7-配货 8-调拨 9-冻结 10-出库 11-解锁
     * @return
     */
    public static function newInvByBar($data, $type)
    {
        foreach ($data as $d) {
            $total_where = [
                'warehouse_code' => $d['warehouse_code'],
                'bar_code' => $d['bar_code'],
                'quality_level' => $d['quality_level'],
            ];
            $sup_where  = array_merge($total_where, [
                'sup_id' => $d['sup_id'],
                'lot_num' => $d['lot_num'],
                'buy_price' => $d['buy_price'],
            ]);
            // if (isset($d['buy_price'])) $sup_where['buy_price'] = $d['buy_price'];
            $is_shelf = $d['in_wh_status'] == 3?1:0;
            $res[] = self::totalUpdate($total_where, $d['num'], $type,$is_shelf);
            $res[] = self::supUpdate($sup_where, $d['num'], $type);
        }
        return [true, ''];
    }

    public static function getInvFlushData($query, $buy_price = null)
    {
        // if ($buy_price !== null) return $query->select('warehouse_code', 'bar_code', 'sup_id', 'quality_level', 'inv_type', 'buy_price','lot_num', DB::raw('count(*) as num'))->groupBy('warehouse_code', 'bar_code', 'sup_id', 'quality_level', 'buy_price','lot_num', 'inv_type')->get();
        // return $query->select('warehouse_code', 'bar_code', 'sup_id', 'quality_level', 'inv_type','lot_num', DB::raw('count(*) as num'))->groupBy('warehouse_code', 'bar_code', 'sup_id', 'quality_level', 'inv_type','lot_num')->get();
        return $query->select('warehouse_code', 'in_wh_status','bar_code', 'sup_id', 'quality_level', 'inv_type', 'buy_price','lot_num', DB::raw('count(*) as num'))->groupBy('warehouse_code', 'in_wh_status','bar_code', 'sup_id', 'quality_level', 'buy_price','lot_num', 'inv_type')->get();
    }

    //总库存直接更新不用group by
    public static function totalUpdate($where, $num, $type = 0,$is_shelf=0)
    {
        //1-收货 2-质检 3-上架 4-确认供应商 5-匹配入库单 6-锁定 7-配货 8-调拨 9-冻结 10-出库 11-解锁
        $update = [];
        switch ($type) {
            case '1':
                $update = [
                    'wh_inv' => DB::raw('wh_inv + ' . $num),
                ];
                break;
            case '6':
                $update = [
                    'sale_inv' => DB::raw('sale_inv - ' . $num),
                ];
                if($is_shelf){
                    $update['shelf_sale_inv'] = DB::raw('shelf_sale_inv - ' . $num);
                    $update['shelf_lock_inv'] = DB::raw('shelf_lock_inv + ' . $num);
                }
                break;
            case '11':
                $update = [
                    'sale_inv' => DB::raw('sale_inv + ' . $num),
                ];
                if($is_shelf){
                    $update['shelf_sale_inv'] = DB::raw('shelf_sale_inv + ' . $num);
                    $update['shelf_lock_inv'] = DB::raw('shelf_lock_inv - ' . $num);
                }
                break;
            default:
                # code...
                break;
        }
        $update['warehouse_name'] = self::_getRedisMap('warehouse_map', $where['warehouse_code']);
        $update['updated_user'] = request()->header('user_id', 0);
        $update['updated_at'] = date('Y-m-d H:i:s');
        $update['created_user'] = DB::raw('if(created_user=0,'.request()->header('user_id', 0).',created_user)');
        $row =  TotalInv::where($where)->lockForUpdate()
            ->updateOrCreate($where,$update);
        if (!$row) return [false, '总库存:' . implode(',', $where) . '更新失败'];
        return [true, '总库存更新成功'];
    }

    //供应商直接更新不用group by
    public static function supUpdate($where, $num, $type = 0, $buy_price = null)
    {
        switch ($type) {
            case '6':
                $update = [
                    'sale_inv' => DB::raw('sale_inv - ' . $num),
                    'lock_inv' => DB::raw('lock_inv + ' . $num),
                ];
                break;
            case '11':
                $update = [
                    'sale_inv' => DB::raw('sale_inv + ' . $num),
                    'lock_inv' => DB::raw('lock_inv - ' . $num),
                ];
                break;
            default:
                # code...
                break;
        }
        $update['sup_name'] = self::_getRedisMap('sup_map', $where['sup_id']);
        $update['warehouse_name'] = self::_getRedisMap('warehouse_map', $where['warehouse_code']);
        $row =   SupInv::where($where)->lockForUpdate()->updateOrCreate($where,$update);
        if (!$row) return [false, '供应商库存:' . implode(',', $where) . '更新失败'];
        return [true, '供应商库存更新成功'];
    }

}
