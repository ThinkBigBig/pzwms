<?php

namespace App\Models\Admin\V2;

use App\Logics\BaseLogic;
use App\Logics\wms\Warehouse;
use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class WmsWarehouseArea extends wmsBaseModel
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'wms_warehouse_area';

    protected $guarded = [];

    static $unselect_types = [1, 2, 3];
    static $unselect_purpoese = [0];

    const ACTIVE = 1;
    const INACTIVE = 0;

    static function maps($attr)
    {
        $maps = [
            'status' => [
                self::ACTIVE => __('admin.wms.status.on'), //'启用',
                self::INACTIVE => __('admin.wms.status.off'), //'禁用',
            ],
            'purpose' => [
                1 => __('admin.wms.purpose.choose'), //'拣选',
                2 => __('admin.wms.purpose.hot'), //'爆品',
                3 => __('admin.wms.purpose.stock_up'), //'备货',
                0 => __('admin.wms.purpose.stash'), //'暂存',
            ],
            'type' => [
                0 => __('columns.wms.warehouse_area.shelf'),//'架上库区',
                1 => __('columns.wms.warehouse_area.receive'),//'收货暂存区',
                2 => __('columns.wms.warehouse_area.qc'),//'质检暂存区',
                3 => __('columns.wms.warehouse_area.takedown'),//'下架暂存区',
            ]
        ];
        return $maps[$attr];
    }


    protected $appends = ['type_txt', 'purpose_txt', 'status_txt'];

    protected static function booted()
    {
        static::addGlobalScope('tenant_id', function (Builder $builder) {
            //这里查询过滤租户id 
            $table = $builder->getModel()->table;
            if (substr($table, 0, 4) == "wms_" || in_array($table, static::$tenant_tables)) {
                $tenant_id = request()->header('tenant_id');
                if ($tenant_id) {
                    $builder->where($table . '.tenant_id', $tenant_id);
                }
            }
        });
    }

    public function getTypeTxtAttribute(): string
    {
        return self::maps('type')[$this->type] ?? '';
    }
    public function getPurposeTxtAttribute(): string
    {
        return self::maps('purpose')[$this->purpose] ?? '';
    }

    public function getStatusTxtAttribute(): string
    {
        return self::maps('status')[$this->status] ?? '';
    }


    // function warehouse() {
    //     return $this->hasOne(WmsWarehouse::class,'warehouse_code','warehouse_code');
    // }

    function warehouse()
    {
        return $this->hasOne('App\Models\Admin\V2\Warehouse', 'warehouse_code', 'warehouse_code');
    }

    function WarehouseLocation()
    {
        return $this->hasMany(WarehouseLocation::class, 'area_code', 'area_code');
    }

    static function code()
    {
        return 'KQ' . date('ymdHis') . rand(1000, 9999);
    }

    function columnOptions()
    {
        return [
            'status' => self::cloumnOptions(self::maps('status')),
            'type' => self::cloumnOptions(self::maps('type')),
            'purpose' => self::cloumnOptions(self::maps('purpose')),
            'warehouse_code' => BaseLogic::warehouseOptions(),
        ];
    }

    public $requiredColumns = ['warehouse_name', 'area_name', 'type_txt', 'purpose_txt'];
    function columns()
    {
        // export=true 导出时展示 ， search=true 搜索查询时展示
        return [
            ['value' => 'area_code', 'label' => '库区编码'],
            ['value' => 'area_name', 'label' => '库区名称'],
            ['value' => 'warehouse_code', 'label' => '所属仓库', 'export' => false,],
            ['value' => 'warehouse_name', 'label' => '所属仓库', 'search' => false,],
            ['value' => 'type', 'label' => '库区类型', 'export' => false,],
            ['value' => 'type_txt', 'label' => '库区类型', 'search' => false,],
            ['value' => 'purpose', 'label' => '库区用途', 'export' => false,],
            ['value' => 'purpose_txt', 'label' => '库区用途', 'search' => false,],
            ['value' => 'status', 'label' => '状态', 'export' => false,],
            ['value' => 'status_txt', 'label' => '状态', 'search' => false,],
            ['value' => 'remark', 'label' => '备注'],
        ];
    }
}
