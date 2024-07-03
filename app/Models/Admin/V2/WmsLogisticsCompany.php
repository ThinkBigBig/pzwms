<?php

namespace App\Models\Admin\V2;

use App\Logics\BaseLogic;
use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WmsLogisticsCompany extends wmsBaseModel
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'wms_logistics_company';
    protected $guarded = [];

    const ACTIVE = 1;
    const INACTIVE = 0;

    static $status = [self::ACTIVE, self::INACTIVE];

    static function maps($attr) {
        $maps = [
            'status'=>[
                self::ACTIVE => __('admin.wms.status.open'),//'开启',
                self::INACTIVE => __('admin.wms.status.close'),//'关闭',
            ],
        ];
        return $maps[$attr];
    }

    static $short_names = [
        "无",
        "顺丰",
        "德邦",
        "得物跨境",
        "韵达",
        "申通",
        "圆通",
        "中通",
        "邮政",
        "其他",
    ];

    protected $appends = ['status_txt'];

    public function product()
    {
        return $this->hasMany(WmsLogisticsProduct::class, 'company_code', 'company_code');
    }

    //赋值
    public function getStatusTxtAttribute(): string
    {
        return self::maps('status')[$this->status] ?? '';
    }


    function columnOptions()
    {
        return [
            'status' => BaseLogic::cloumnOptions(self::maps('status')),
        ];
    }

    public $requiredColumns = ['company_name','short_name'];

    function columns()
    {
        // export=true 导出时展示 ， search=true 搜索查询时展示
        return [
            ['value' => 'company_code', 'label' => '公司编码'],
            ['value' => 'company_name', 'label' => '公司名称'],
            ['value' => 'short_name', 'label' => '简称'],
            ['value' => 'status', 'label' => '状态', 'export' => false],
            ['value' => 'status_txt', 'label' => '状态', 'search' => false],
            ['value' => 'contact_name', 'label' => '联系人'],
            ['value' => 'contact_phone', 'label' => '联系电话'],
            ['value' => 'address', 'label' => '备注'],
        ];
    }
}
