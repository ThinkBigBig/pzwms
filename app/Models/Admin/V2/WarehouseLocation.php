<?php

namespace App\Models\Admin\V2;


use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WarehouseLocation extends wmsBaseModel
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];
    protected $table = 'wms_area_location';
    public $unique_field = ['location_code'];
    protected $appends = ['status_txt', 'type_txt', 'is_able_txt','ware_area'];

    // protected $map =  [
    //     'status'=>[1=>'启用' ,0=>'禁用' ],
    //     'is_able'=> [0=>'不空闲' ,1=>'空闲' ],
    //     'type' => [0=>'混合货位' ,1=>'整箱货位' ,2=>'拆零货位'],

    // ];

    protected $map;

    // protected $with = ['wareArea:wms_warehouse_area.area_code,wms_warehouse_area.area_name,wms_warehouse_area.warehouse_code,wms_warehouse_area.status', 'wareArea.warehouse:warehouse_code,warehouse_name,status'];
    // protected $with = ['wareArea:area_code,area_name,warehouse_code,status', 'warehouse'];


    public function __construct($params=[])
    {
        parent::__construct($params);
        $this->map = [
            'status' => [1 => __('status.enable'), 0 => __('status.disabled')],
            'is_able' => [0 => __('status.not_idle'), 1 => __('status.idle')],
            'type' => [0 => __('status.mixed_space'), 1 => __('status.full_space'), 2 => __('status.splitting_space')],
        ];
    }


    public function wmsArea()
    {
        return $this->hasOne(WmsWarehouseArea::class, 'area_code', 'area_code');
        //return $this->hasOneThrough(WmsWarehouseArea::class, Warehouse::class, 'warehouse_code','warehouse_code','warehouse_code', 'warehouse_code');

    }
    public function warehouse()
    {
        return $this->hasOne(Warehouse::class, 'warehouse_code', 'warehouse_code')->select('warehouse_code','warehouse_name','status','type');
    }

    // public function withSearch($select){
        // $data = $this::join('wms_warehouse_area',function($join){
        //     $join->on('wms_warehouse_area.area_code','=','wms_area_location.area_code')
        //     ->on('wms_warehouse_area.warehouse_code','=','wms_area_location.warehouse_code');
        // })
        // ->selectRaw('wms_area_location.area_code,wms_warehouse_area.area_name,wms_area_location.warehouse_code,wms_area_location.status');
        // // dd($data->get()->toArray());
        // return $data;
    // }


    public function getWareAreaAttribute($key)
    {
        $area = WmsWarehouseArea::where('warehouse_code',$this->warehouse_code)->where('area_code',$this->area_code)->select('area_code','area_name','status','type','purpose')->first();
        if($area){
            $area->warehouse = $this->warehouse;
            return $area;
        }
        return [
            'area_name'=>'',
            'area_code'=>$this->area_code,
            'warehouse_code'=>$this->warehouse_code,
            'warehouse'=>[
                'warehouse_code'=>$this->warehouse_code,
                'warehouse_name'=>''
            ]
        ];
    }


    public function BaseDelete($ids, $name = 'id')
    {
        if (empty($ids)) return false;
        $id = explode(',', $ids);;
        // $del_ids = $this::whereIn('id',$id)->where('is_able',1)->pluck('location_code')->toArray();
        $del_ids = $this::whereIn('id', $id)->where('is_able', 1)->get()->modelKeys();
        $ndel_ids = array_values(array_diff($id, $del_ids));
        $data = $this::whereIn($name, $del_ids)->delete();
        if ($ndel_ids) {
            return ['ndel_ids' => $ndel_ids, 'del_ids' => $del_ids];
        }
        if (empty($data)  || $data == false || $data == NULL) return false;
        return $data;
    }

    public function checkRepeat($warehouse_code,$location_code){
       return self::where('location_code',$location_code)->where('warehouse_code',$warehouse_code)->doesntExist();
    }

    public function getPickNumber($code,$pick_number=''){
        if(!empty($pick_number)) return $pick_number;
        for ($i = 0; $i < strlen($code); $i++) {
            if ($code[$i] == '-') continue;
            $pick_number .= ord($code[$i]);
        }
        return $pick_number;
    }

    public function getAreaCode($area_name,$warehouse_code){
       $area = WmsWarehouseArea::where('area_name',$area_name)->where('warehouse_code',$warehouse_code)->where('status',1)->orderBy('id','desc')->first();
       return $area?$area->area_code:'';
    }
}
