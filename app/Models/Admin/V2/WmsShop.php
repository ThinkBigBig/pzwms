<?php

namespace App\Models\Admin\V2;

use App\Logics\BaseLogic;

use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class WmsShop extends wmsBaseModel
{
    use HasFactory, SoftDeletes;
    protected $guarded = [];
    protected $table = 'wms_shops';

    const ON = 1;
    const OFF = 0;

    static function maps($attr)
    {
        $maps = [
            'status' => [
                self::ON => __('admin.wms.status.on'), //'启用',
                self::OFF => __('admin.wms.status.off'), //'禁用',
            ],
            'sale_channel' => [
                '1' => __('columns.wms.channel.other'),//其他
                '2' => __('columns.wms.channel.dw_cross_border'),//'得物跨境'
            ],
        ];
        return $maps[$attr];
    }

    protected $appends = ['sale_channel_txt', 'status_txt'];

    public function getSaleChannelTxtAttribute(): string
    {
        return self::maps('sale_channel')[$this->sale_channel] ?? '';
    }

    public function getStatusTxtAttribute(): string
    {
        return self::maps('status')[$this->status];
    }

    static function code()
    {
        return 'DP' . date('ymdHis') . rand(1000, 9999);
    }

    function manager()
    {
        return $this->hasOne(AdminUser::class, 'id', 'manager_id');
    }

    function createUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'create_user_id');
    }

    function adminUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'admin_user_id');
    }


    function columnOptions()
    {
        return [
            'sale_channel' => self::cloumnOptions(self::maps('sale_channel')),
            'status' => self::cloumnOptions(self::maps('status')),
            'manager_id' => BaseLogic::adminUsers(),
        ];
    }

    public $requiredColumns = ['name', 'sale_channel_txt', 'manager',];
    function columns()
    {
        // export=true 导出时展示 ， search=true 搜索查询时展示
        return [
            ['value' => 'code', 'label' => '编码'],
            ['value' => 'name', 'label' => '店铺名称'],
            ['value' => 'sale_channel', 'label' => '销售渠道', 'export' => false,],
            ['value' => 'sale_channel_txt', 'label' => '销售渠道', 'search' => false,],
            ['value' => 'status', 'label' => '状态', 'export' => false,],
            ['value' => 'status_txt', 'label' => '状态', 'search' => false,],
            ['value' => 'manager_id', 'label' => '店铺负责人', 'export' => false,],
            ['value' => 'manager', 'label' => '店铺负责人', 'search' => false,],
            ['value' => 'remark', 'label' => '备注'],
        ];
    }
}
