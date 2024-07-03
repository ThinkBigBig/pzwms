<?php

namespace App\Models\Admin\V2;


use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsStockDifference extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = "wms_stock_differences";

    const WAIT = 0;
    const DONE = 1;

    protected $casts = [
        'order_at' => 'datetime:Y-m-d H:i:s',
    ];
    protected $appends = ['type_txt', 'status_txt', 'origin_type_txt'];

    public function getTypeTxtAttribute(): string
    {
        return '少货';
    }

    public function getStatusTxtAttribute(): string
    {
        $maps = [
            self::DONE => '已处理',
            self::WAIT => '待处理',
        ];
        return $maps[$this->status] ?? '';
    }

    public function getOriginTypeTxtAttribute(): string
    {
        return '盘点';
    }


    static function code()
    {
        return 'CYD' . date('ymdHis') . rand(1000, 9999);
    }

    function warehouse()
    {
        return $this->hasOne(Warehouse::class, 'warehouse_code', 'warehouse_code');
    }

    function orderUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'order_user');
    }

    function createUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'create_user_id');
    }

    function adminUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'admin_user_id');
    }

    function details()
    {
        return $this->hasMany(WmsStockCheckDifference::class, 'origin_code', 'code')->where('status', WmsStockCheckDifference::REPORT);
    }

    function logs()
    {
        return $this->hasMany(WmsStockCheckLog::class, 'origin_code', 'code');
    }

    function columnOptions()
    {
        return [];
    }

    public $requiredColumns = [];

    function columns()
    {
        // export=true 导出时展示 ， search=true 搜索查询时展示
        return [
            ['value' => 'type', 'label' => '单据类型', 'export' => false],
            ['value' => 'type_txt', 'label' => '单据类型', 'search' => false],
            ['value' => 'code', 'label' => '单据编码'],
            ['value' => 'status', 'label' => '单据状态', 'export' => false],
            ['value' => 'status_txt', 'label' => '单据状态', 'search' => false],
            ['value' => 'origin_type', 'label' => '来源单据类型', 'export' => false],
            ['value' => 'origin_type_txt', 'label' => '来源单据类型', 'search' => false],
            ['value' => 'origin_code', 'label' => '来源单据编码', 'search' => false],
            ['value' => 'diff_num', 'label' => '差异总数', 'export' => false],
            ['value' => 'warehouse', 'label' => '仓库', 'search' => false],
            ['value' => 'order_user', 'label' => '下单人', 'search' => false],
            ['value' => 'order_at', 'label' => '下单时间', 'search' => false],
            ['value' => 'create_user', 'label' => '处理人', 'search' => false],
            ['value' => 'created_at', 'label' => '处理时间', 'search' => false],
            ['value' => 'remark', 'label' => '备注'],
        ];
    }
}
