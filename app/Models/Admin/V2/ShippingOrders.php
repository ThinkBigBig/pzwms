<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use  App\Models\Admin\V2\ArrivalRegist;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;
use Illuminate\Support\Facades\Storage;
use App\Handlers\OSSUtil;
use PHPUnit\TextUI\XmlConfiguration\Group;

class ShippingOrders extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_shipping_orders'; //发货单

    // protected $map = [
    //     'type' => [0 => '发货单'],
    //     'status' => [0 => '已审核'],
    //     'request_status' => [0 => '已发货'],
    // ];

    protected $map;
    protected $appends = ['users_txt', 'type_txt', 'status_txt', 'request_status'];

    public function __construct($params=[])
    {
        parent::__construct($params);
        $this->map = [
            'type' => [0 => __('excel.shipping_order.title')],
            'status' => [0 => __('status.audited')],
            'request_status' => [0 => __('status.shipped')],
        ];
    }

    public function searchUser(){
        return [
            'users_txt.shipper_user' => 'shipper_id',
        ];
    }
    

    public function getUsersTxtAttribute($key)
    {
        $tenant_id = request()->header('tenant_id');
        $res['shipper_user'] = $this->getAdminUser($this->shipper_id, $tenant_id);
        return $res;
    }

    public function obOrder()
    {
        return $this->belongsTo(ObOrder::class, 'request_code', 'request_code');
    }

    public function EdPrint()
    {
        return $this->belongsTo(ExpressPrintLog::class, 'ship_code', 'ship_code');
    }

    public function withSearch($select)
    {
        return $this::with(['obOrder', 'EdPrint:ship_code,updated_at,print_count'])->select($select);
    }

    public static function add($pre_detail_ids)
    {
        if (!is_array($pre_detail_ids)) $pre_detail_ids = explode(',', $pre_detail_ids);
        $collect = preAllocationDetail::whereIn('id', $pre_detail_ids)->get();
        $actual_num =  $collect->sum->actual_num;
        $quality_num =  $collect->sum(function ($det) {
            if ($det['quality_level'] == 'A') return $det['actual_num'];
        });
        $defects_num = $actual_num - $quality_num;
        $send_order_data = [
            'warehouse_code'  => $collect->first()->warehouse_code,
            'warehouse_name'  => $collect->first()->warehouse()->first()->warehouse_name,
            'request_code' => $collect->first()->request_code,
            'actual_num' => $actual_num,
            'sku_num' => $collect->groupBy('bar_code')->count(),
            'quality_num' => $quality_num,
            'defects_num' => $defects_num,
        ];
        $ship_code = self::getErpCode('FHD');
        $send_order_data['ship_code'] = $ship_code;
        $send_order_data['shipper_id'] = request()->header('user_id');
        $send_order_data['tenant_id'] = request()->header('tenant_id');
        $date = date('Y-m-d H:i:s');
        $send_order_data['shipped_at'] = $date;
        $send_order_data['created_at'] = $date;
        $send_order_data['updated_at'] = $date;

        return [self::insert($send_order_data), $ship_code];
    }


    //打印快递单
    public static function printEdNo($request_code)
    {

        $ob_item = (new ObOrder())->where('request_code', $request_code)->first();
        if (empty($ob_item)) return [false, '出库单不存在'];
        $item =   self::where('request_code', $request_code)->first();
        if (empty($item)) return [false, '未发货不允许打印'];
        $deliver_no = $ob_item->deliver_no; //快递单号
        if ($deliver_no) {

            // $path = Storage::disk('pdf')->path($deliver_no.'.pdf');
            // if(is_file($path)){
            //     $url = Storage::disk('pdf')->url($deliver_no.'.pdf');
            //     //记录快递单打印日志
            //     $row = ExpressPrintLog::add($item->ship_code,$deliver_no);


            //     return [$row,['url'=>$url]];
            // }

            //oss
            $file = WmsFile::where('name',$deliver_no)->orderBy('id','desc')->first();
            if(!$file) return [false, __('status.ed_no_not_exists')];
            // $oss = new OSSUtil();
            $file_path = env('ALIYUN_OSS_HOST') . $file->file_path;
            // if ($oss->fileExist($file_path)) {
                //记录快递单打印日志
                $row = ExpressPrintLog::add($item->ship_code, $deliver_no);
                return [$row, ['url' => $file_path]];
            // }
        }
        return [false, __('status.ed_no_not_exists')];
    }

    public function details(){
        return $this->hasMany(preAllocationDetail::class,'request_code','request_code');

    }

    public function preAllocateList(){
        return $this->hasOne(preAllocationLists::class,'request_code','request_code')->where('status',1)->withDefault();

    }
    // public function  getDetailsAttribute($key){
    //     $data = [];
    //     ShippingDetail::where('')
    //     return $data;
    // }

    //发货单详情
    public function BaseOne($where = [], $select = ['*'], $order = [['id', 'desc']])
    {
        // $data = DB::table($this->table)->select($select);
        $data = $this->withInfoSearch($select);
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
        //   $pre_list = $reData->preAllocateList;
          $ob_order =$reData->obOrder;
           $rData =[
            'type'=>$reData->type_txt,
            'doc_status'=>$reData->status_txt,
            'wh_name'=>$ob_order->warehouse_name,
            'code'=>$reData->ship_code,
            'ob_type'=>$ob_order->type_txt,
            'ob_code' =>$ob_order->request_code,
            'shipper_user' =>$reData->users_txt['shipper_user'],
            'shipped_at' =>$reData->shipped_at,
            // 'created_at' =>$pre_list->created_at->format('Y-m-d H:i:s'),
            // 'updated_at' =>$pre_list->updated_at->format('Y-m-d H:i:s'),
            // 'create_user' =>$pre_list->users_txt['create_user'],
            // 'admin_user' =>$pre_list->users_txt['admin_user'],
            'created_at' =>$reData->shipped_at,
            'updated_at' =>$reData->shipped_at,
            'create_user' =>$reData->users_txt['shipper_user'],
            'admin_user' =>$reData->users_txt['shipper_user'],
        ];
        $rData['logistics']=[
            'pickup_method_txt'=>$ob_order->logistics->pickup_method_txt,
            'company_code'=>$ob_order->logistics->company_code,
            'product_code'=>$ob_order->logistics->product_code,
            'deliver_no'=>$ob_order->deliver_no,

        ];
        //    $rData['details'] = $reData->details()->selectRaw('bar_code,sup_id,quality_type,quality_level,batch_no,reviewer_id,review_at,if(quality_type=1,"",uniq_code) as uniq_code,sum(pre_num) as total,sum(actual_num) as send')
        //    ->groupByRaw('bar_code,sup_id,quality_type,quality_level,batch_no,reviewer_id,review_at')->get()
        // ->append(['product_sn','product_name','product_spec','product_spec','product_sku','supplier_name'])->makeHidden(['product','supplier'])->toArray();
        $rData['details'] = $reData->details()->selectRaw('request_code,bar_code,sup_id,quality_type,quality_level,batch_no,reviewer_id,review_at,uniq_code,pre_num as total,actual_num as send')->get()
     ->append(['third_no','deliver_no','product_sn','product_code','product_name','product_spec','product_spec','product_sku','supplier_name'])
     ->makeHidden(['product','supplier','shippingRequest'])->toArray();
        return objectToArray($rData);
    }

    public static function sendInfo($third_no){
        $request_code = ObOrder::where('third_no',$third_no)->whereNotIn('status',[3,5])->first();
        if(!$request_code)return [];
        $ship_item =  self::where('request_code',$request_code->request_code)->first();
        if(!$ship_item) return [];
        $data =[
            'request_code'=>$ship_item->request_code,
            'warehouse_name'=>$request_code->warehouse_name,
            'ship_code'=>$ship_item->ship_code,
            'shipped_at'=>$ship_item->shipped_at,
            'send_num'=>$ship_item->actual_num,
            'normal_count'=>$ship_item->quality_num,
            'flaw_count'=>$ship_item->defects_num,
            'deliver_type'=>$request_code->logistics()->first()->pickup_method_txt??'',
            'deliver_no'=>$request_code->deliver_no,

        ];
        return [$data];
    }
}
