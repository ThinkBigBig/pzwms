<?php

namespace App\Models\Admin\V2;


use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;
use Illuminate\Support\Facades\DB;

class IbOrder extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_ib_order'; //入库单

    protected static $arrIns = null;
    const APPROVE = 1;
    const CONFIRM = 3;
    const CANCEL = 2;
    // protected $map = [
    //     'ib_type' => [1 => '采购入库', 2 => '调拨入库', 3 => '退货入库', 4 => '其他入库'],
    //     'doc_status' => [1 => '已审核', 2 => '已取消', 3 => '已确认'],
    //     'recv_status' => [1 => '待收货', 2 => '部分收货', 3 => '已收货'],
    // ];

    // protected $with = ['details'];

    protected $appends = ['ib_type_txt', 'doc_status_txt', 'recv_status_txt', 'diff_num'];
    protected $guarded = [];
    protected $map;

    // protected $fillable = ['arr_id','doc_status','updated_user'];
    // public function arrItem(){
    //     return $this->belongsTo(ArrivalRegist::class,'arr_id');
    // }


    public function __construct($params = [])
    {
        parent::__construct($params);
        $this->map = [
            'ib_type' => [1 => __('status.buy_ib'), 2 => __('status.transfer_ib'), 3 => __('status.return_ib'), 4 => __('status.other_ib')],
            'doc_status' => [1 => __('status.audited'), 2 => __('status.canceled'), 3 => __('status.confirmed')],
            'recv_status' => [1 => __('status.wait_recv'), 2 => __('status.recv_part'), 3 => __('status.received')],
        ];
    }

    public function details()
    {
        return $this->hasMany(IbDetail::class, 'ib_code', 'ib_code');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_code', 'warehouse_code')->withDefault(['status' => 1]);
    }

    public function supplier()
    {
        return $this->hasOne(Supplier::class, 'id', 'sup_id')->withDefault(['status' => 1]);
    }

    public function ibAndArr()
    {
        return $this->hasMany(IbAndArr::class, 'ib_code', 'ib_code');
    }

    public function aftersale()
    {
        if ($this->ib_type != 3) return null;
        return $this->hasOne(AfterSaleOrder::class, 'code', 'source_code');
    }

    public function order()
    {
        return $this->hasOne(WmsOrder::class, 'third_no', 'third_no')->orderBy('id', 'desc')->limit(1);
    }

    public  function withSearch($select)
    {
        if (request()->get('is_match') == 1) {
            return $this::with(['warehouse', 'details'])->where('doc_status', 1)->select($select);
        }
        return $this::with(['warehouse'])->select($select);
    }
    public function searchSku($v)
    {
        $bar_codes = ProductSpecAndBar::where('sku', $v)->pluck('bar_code')->toArray();
        $codes = IbDetail::whereIn('bar_code', $bar_codes)->pluck('ib_code')->toArray();
        return ['ib_code', $codes];
    }

    public function  withInfoSearch($select)
    {
        return $this::with(['details' => function ($query) {
            $query->with(['product', 'supplier']);
        }])->select($select);
    }

    public function _formatOne($data)
    {
        $arr_codes = $data->ibAndArr->pluck('rd_total', 'arr_code')->toArray();
        if (empty($arr_codes)) $arr_codes = [$data->third_no=>$data->rd_total];
        $arrs =  ArrivalRegist::whereIn('arr_code', array_keys($arr_codes))->select('arr_type', 'doc_status', 'arr_code', 'arr_status', 'arr_num', 'warehouse_code', 'recv_num')->get();
        $arr_info = [];
    
        foreach ($arrs as $arr) {
            $arr_info[] = [
                'arr_type'=>$arr->arr_type,
                'ib_type_txt'=>$arr->ib_type_txt,
                'doc_status'=>$arr->doc_status,
                'doc_status_txt'=>$arr->doc_status_txt,
                'arr_code'=>$arr->arr_code,
                'arr_status'=>$arr->arr_status,
                'arr_status_txt'=>$arr->arr_status_txt,
                'warehouse_name'=>$arr->warehouse,
                'arr_num'=>$arr->arr_num,
                'recv_num'=>$arr->recv_num,
                'confirm_num'=>$arr_codes[$arr->arr_code],
            ];
        }
        
        $data->arr_info = $arr_info;
        $data = $data->makeHidden('ibAndArr')->toArray();
        $data['warehouse_name'] = self::_getRedisMap('warehouse_map',$data['warehouse_code']);
        return $data;
    }
    public function getDiffNumAttribute($key)
    {
        return $this->re_total - $this->rd_total;
    }

    public function getCreatedUserAttribute($key)
    {
        return  $this->_getRedisMap('user_map', $this->created_user) ?? '';
    }

    public function getUpdatedUserAttribute($key)
    {
        return  $this->_getRedisMap('user_map', $this->updated_user) ?? '';
    }
    public static function add($create, $products)
    {
        // dd($create,$products);
        $log_data = array_merge($create, ['products' => $products]);
        $ib_code = self::getErpCode('RKD', 10);
        $tenant_id = request()->header('tenant_id');
        //判断商品
        if (empty($products)) return [false, __('response.product_not_exists')];
        foreach ($products as &$pro) {
            if (empty($pro)) continue;
            $pro['ib_code'] = $ib_code;
            $pro['admin_user_id'] = empty(request()->header('user_id')) ? SYSTEM_ID : request()->header('user_id'); //系统id
            $pro['tenant_id'] = $tenant_id;
            $pro['created_at'] = date('Y-m-d H:i:s');
        }
        $create['ib_code'] = $ib_code;
        $create['erp_no'] = $create['ib_code'];
        $create['doc_status'] = 1;
        $create['recv_status'] = 1;
        $create['tenant_id'] = $tenant_id;

        //时间戳转换
        $create['paysuccess_time']  = date('Y-m-d H:i:s');
        $create['created_user'] = request()->header('user_id');
        $create['created_at'] = date('Y-m-d H:i:s');
        $create['updated_user'] = request()->header('user_id');
        $create['updated_at'] = date('Y-m-d H:i:s');
        // dd($create,$products,$log_data['products']);
        self::startTransaction();
        $res['cre_ob'] = self::insert($create);
        $res['cre_details'] = DB::table('wms_ib_detail')->insert($products);
        WmsOptionLog::add(WmsOptionLog::RKD, $ib_code, '创建', '入库单创建成功', ['data' => $log_data, 'res' => $res]);
        return self::endTransaction($res);
    }

    protected static function cancel($ib_codes)
    {
        $update = [
            'doc_status' => self::CANCEL,
            'updated_user' => request()->header('user_id'),
            'cancel_at' => date('Y-m-d H:i:s'),
        ];
        return self::whereIn('ib_code', $ib_codes)->update($update);
    }

}
