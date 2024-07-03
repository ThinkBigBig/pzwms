<?php

namespace App\Models\Admin\V2;

use App\Logics\wms\DataPermission;
use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Logics\wms\Warehouse as WareArea;
use Illuminate\Support\Facades\Redis;

class Warehouse extends wmsBaseModel
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'wms_warehouse';
    public $unqiue_field = ['warehouse_code', 'warehouse_name'];
    protected $appends = ['status_txt', 'type_txt', 'log_product'];

    protected static  $log_product = null;

    // protected $map = [
    //     'status'=>[1=>'启用' ,0=>'禁用' ],
    //     'type' => [0=>'销售仓' ,1=>'退货仓' ,2=>'换季仓', 3=>'虚拟仓', 4=>'其他'],
    // ];
    protected $map;



    public function __construct($params=[])
    {
        parent::__construct($params);
        $this->map = [
            'status' => [1  => __('status.enable'), 0 => __('status.disabled')],
            'type' => [0 => __('status.sales_wh'), 1 => __('status.return_wh'), 2 => __('status.seasonal_wh'), 3 => __('status.virtual_wh'), 4 => __('status.other')],

        ];
    }
    public function wareArea()
    {
        return $this->hasMany(WmsWarehouseArea::class, 'area_code', 'area_code');
    }



    public static function getName($warehouse_code)
    {
        $item = self::where('warehouse_code', $warehouse_code)->where('status',1)->first();
        if (empty($item)) return '';
        return $item->warehouse_name;
    }

    public static function getCode($warehouse_name)
    {
        $item = self::where('warehouse_name', $warehouse_name)->where('status',1)->first();
        if (empty($item)) return '';
        return $item->warehouse_code;
    }

    public function getLogProductAttribute($key)
    {
        return $this->logProducts($this->warehouse_code, $this->tenant_id);
    }

    public static function  logProducts($warehouse_code, $tenant_id)
    {
        $item = self::where('warehouse_code', $warehouse_code)->where('status',1)->orderBy('id', 'desc')->first();
        $log_product_ids =  $item->log_prod_ids;
        if (empty($log_product_ids)) return [];
        return WmsLogisticsProduct::whereIn('id', explode(',', $log_product_ids))->where('tenant_id', $tenant_id)->get()->toArray();
    }

    public  function BaseCreate($CreateData = [])
    {
        if (empty($CreateData)) return false;
        $id = $this::insertGetId($CreateData);
        if (empty($id)) return false;
        $warehouse1 = $this::find($id);
        Redis::del('warehouse_map');
        $warehouse = $warehouse1->warehouse_code;
        $defaultArea = [
            [
                'area_name' => '收货暂存区',
                'warehouse_code' => $warehouse,
                'area_code' => 'SHZCQ001',
                'type' => 1,
                'status' => 1,
                'purpose' => 0,
                'tenant_id' => request()->header('tenant_id'),
            ],
            [
                'area_name' => '质检暂存区',
                'warehouse_code' => $warehouse,
                'area_code' => 'ZJZCQ001',
                'type' => 2,
                'status' => 1,
                'purpose' => 0,
                'tenant_id' => request()->header('tenant_id'),

            ],
            [
                'area_name' => '下架暂存区',
                'warehouse_code' => $warehouse,
                'area_code' => 'XJZCQ001',
                'type' => 3,
                'status' => 1,
                'purpose' => 0,
                'tenant_id' => request()->header('tenant_id'),

            ]
        ];
        $logic = new WareArea();
        // foreach($defaultArea as &$item){
        //     $item['area_code'] = $logic->generateAreaCode($item['area_name']);
        // }
        $this->wareArea()->insert($defaultArea);
        WmsDataPermission::addWarehouse($warehouse1);
        return $id;
    }

    public function  getWareArea($data)
    {
        if (empty($data['warehouse_code'])) $search = WmsWarehouseArea::where('status', 1)->where('type', 0);
        else  $search = WmsWarehouseArea::where('warehouse_code', $data['warehouse_code'])->where('status', 1)->where('type', 0);
        return [true, $search->get()->toArray()];
    }

    public function  getLocation($data)
    {
        $area_codes = [];
        if (!empty($data['area_code'])) {
            $area_code = explode(',', $data['area_code']);
        } else {
            $area_code =  WmsWarehouseArea::where('warehouse_code', $data['warehouse_code'])->where('status', 1)->where('type', 0)->pluck('area_code')->toArray();
        }
        $area_codes = array_merge($area_code, $area_codes);
        $search = WarehouseLocation::without(['wareArea'])->whereIn('area_code', $area_codes)->where('status', 1);
        return [true, $search->get()->toArray()];
    }
}
