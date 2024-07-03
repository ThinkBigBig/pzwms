<?php

namespace App\Models\Admin\V2;

use App\Logics\BaseLogic;
use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsQualityConfirmList extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    public $table = 'wms_quality_confirm_list';
    protected $appends = ['status_txt', 'pic_url', 'quality_type_txt', 'confirm_quality_type_txt', 'old_quality_type_txt', 'type_txt'];

    const NOTMAL = 1;
    const DEFECT = 2;

    const TYPE_STORE_IN = 1; //入库质检单
    const TYPE_WAREHOUSE = 2; //仓内质检单

    static function maps($attr, $option = false)
    {
        $maps = [
            'status' => [
                '1' => __('admin.wms.status.confirm'), //'已确认', 
                '2' => __('admin.wms.status.void'), //'已作废', 
                '0' => __('admin.wms.status.wait_confirm'), //'待确认',
            ],
            'quality_type' => [
                self::NOTMAL => __('admin.wms.type.normal'), //'正品', 
                self::DEFECT => __('admin.wms.type.flaw'), //'疑似瑕疵'
            ],
            'type' => [
                self::TYPE_STORE_IN => __('admin.wms.type.qc_stock_in'), //'入库质检单', 
                self::TYPE_WAREHOUSE => __('admin.wms.type.qc_warehouse'), //'仓内质检单'
            ],
        ];
        if (!$option) return $maps[$attr];
        return self::cloumnOptions($maps[$attr]);
    }

    public function getQualityTypeTxtAttribute(): string
    {
        return $this->quality_type;
        // return [self::NOTMAL => '正品', self::DEFECT => '疑似瑕疵'][$this->quality_type] ?? '';
    }

    public function getConfirmQualityTypeTxtAttribute(): string
    {
        return self::maps('quality_type')[$this->confirm_quality_type] ?? '';
    }

    public function getOldQualityTypeTxtAttribute(): string
    {
        return self::maps('quality_type')[$this->old_quality_type] ?? '';
    }

    public function getStatusTxtAttribute(): string
    {
        return self::maps('status')[$this->status] ?? '';
    }

    public function getPicUrlAttribute(): array
    {
        $pics = explode(',', $this->pic);
        $arr = [];
        foreach ($pics as $pic) {
            $arr[] = false === strpos($pic, 'http') ? env('ALIYUN_OSS_HOST') . $pic : $pic;
        }
        return $arr;
    }


    function adminUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'admin_user_id');
    }

    function submitter()
    {
        return $this->hasOne(AdminUser::class, 'id', 'submitter_id');
    }

    function comfirmor()
    {
        return $this->hasOne(AdminUser::class, 'id', 'comfirmor_id');
    }

    function arrivalRegist()
    {
        return $this->hasOne(ArrivalRegist::class, 'id', 'arr_id');
    }

    function specBar()
    {
        return $this->hasOne(ProductSpecAndBar::class, 'bar_code', 'bar_code');
    }

    public function getTypeTxtAttribute(): string
    {
        return self::maps('type')[$this->type] ?? '';
    }

    function columnOptions()
    {
        $user = BaseLogic::adminUsers();
        return [
            'warehouse_code' => BaseLogic::warehouseOptions(),
            'status' => self::maps('status', true),
            'quality_type' => self::maps('quality_type', true),
            'confirm_quality_type' => self::maps('quality_type', true),
            'type' => WmsQualityList::maps('type', true),
            'submitter_id' => $user,
            'comfirmor_id' => $user,
        ];
    }

    public $requiredColumns = [];
    public function searchUser()
    {
        return [
            'submitter_id' => 'submitter_id',
            'comfirmor_id' => 'comfirmor_id',
            'admin_user_id' => 'admin_user_id',
        ];
    }

    function columns()
    {
        // export=true 导出时展示 ， search=true 搜索查询时展示
        return [
            ['value' => 'qc_code', 'label' => '质检单据编码'],
            ['value' => 'arr_code', 'label' => '源单登记单编码'],
            ['value' => 'warehouse_code', 'label' => '质检仓库', 'export' => false],
            ['value' => 'warehouse_name', 'label' => '质检仓库', 'search' => false],
            ['value' => 'status', 'label' => '质检确认状态', 'export' => false],
            ['value' => 'status_txt', 'label' => '质检确认状态', 'search' => false],
            ['value' => 'pic', 'label' => '质检图片'],
            ['value' => 'notes', 'label' => '质检备注'],
            ['value' => 'uniq_code', 'label' => '商品唯一码'],
            ['value' => 'quality_type', 'label' => '质检质量类型', 'export' => false],
            ['value' => 'quality_type', 'label' => '质检质量类型', 'search' => false],
            ['value' => 'quality_level', 'label' => '质量等级'],
            ['value' => 'confirm_quality_type', 'label' => '确认后质量类型', 'export' => false],
            ['value' => 'confirm_quality_type_txt', 'label' => '确认后质量类型', 'search' => false],
            ['value' => 'confirm_quality_level', 'label' => '确认后质量等级'],
            ['value' => 'wms_spec_and_bar.sku', 'label' => 'SKU编码','export' => false],
            ['value' => 'wms_product.product_sn', 'label' => '货号' ,'export' => false],
            ['value' => 'wms_product.name', 'label' => '品名','export' => false],
            ['value' => 'wms_spec_and_bar.spec_one', 'label' => '规格','export' => false],
            ['value' => 'sku', 'label' => 'SKU编码','search' => false],
            ['value' => 'product_sn', 'label' => '货号' ,'search' => false],
            ['value' => 'name', 'label' => '品名','search' => false],
            ['value' => 'spec_one', 'label' => '规格','search' => false],
            ['value' => 'type', 'label' => '质检单据类型', 'export' => false],
            ['value' => 'type_txt', 'label' => '质检单据类型', 'search' => false],
            ['value' => 'area_name', 'label' => '质检库区'],
            ['value' => 'location_code', 'label' => '质检位置码'],
            ['value' => 'submitter_id', 'label' => '质检人', 'export' => false],
            ['value' => 'submitter', 'label' => '质检人', 'search' => false],
            ['value' => 'created_at', 'label' => '质检时间'],
            ['value' => 'comfirmor_id', 'label' => '质检确认人', 'export' => false],
            ['value' => 'comfirmor', 'label' => '质检确认人', 'search' => false],
            ['value' => 'confirm_at', 'label' => '质检确认时间'],
            ['value' => 'confirm_remark', 'label' => '质检确认备注'],
        ];
    }
}
