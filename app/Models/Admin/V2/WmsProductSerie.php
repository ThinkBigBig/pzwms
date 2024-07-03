<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WmsProductSerie extends wmsBaseModel
{
    use HasFactory, SoftDeletes;
    protected $guarded = [];
    public $table = 'wms_product_series';

    protected $appends = ['status_txt'];

    const ON = 1;
    const OFF = 0;

    static function maps($attr)
    {
        $maps = [
            'status' => [
                self::ON => __('admin.wms.status.on'), //'启用',
                self::OFF => __('admin.wms.status.off'), //'禁用',
            ],
        ];
        return $maps[$attr];
    }

    public function getStatusTxtAttribute(): string
    {
        return self::maps('status')[$this->status] ?? '';
    }

    static function code()
    {
        return 'SPXL' . date('ymdHis') . rand(1000, 9999);
    }

    function brand()
    {
        return $this->hasOne(ProductBrands::class, 'code', 'brand_code');
    }


    function columnOptions()
    {
        return [
            'brand_code' => ProductBrands::selectOptions(),
            'status' => self::cloumnOptions(self::maps('status')),
        ];
    }

    public $requiredColumns = ['name', 'brand_name'];
    function columns()
    {
        // export=true 导出时展示 ， search=true 搜索查询时展示
        return [
            ['value' => 'name', 'label' => '系列名称'],
            ['value' => 'brand_code', 'label' => '品牌', 'export' => false,],
            ['value' => 'brand_name', 'label' => '品牌', 'search' => false,],
            ['value' => 'status', 'label' => '状态', 'export' => false,],
            ['value' => 'status_txt', 'label' => '状态', 'search' => false,],
            ['value' => 'sort', 'label' => '排序'],
            ['value' => 'remark', 'label' => '备注'],
        ];
    }
}
