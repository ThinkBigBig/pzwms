<?php

namespace App\Models\Admin\V2;


use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsStockCheckBill extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = "wms_stock_check_bill";

    protected $appends = ['type_txt', 'status_txt'];

    const EXTRA = 1;
    const LEAK = 2;
    public function getTypeTxtAttribute(): string
    {
        $maps = [
            self::LEAK => __('admin.wms.type.check_leak'),//'盘亏单',
            self::EXTRA => __('admin.wms.type.check_extra'),//'盘盈单',
        ];
        return $maps[$this->type] ?? '';
    }

    const STASH = 0;
    const DONE = 1;
    public function getStatusTxtAttribute(): string
    {
        $maps = [
            self::DONE => __('admin.wms.status.audit'),//'已审核',
            self::STASH => __('admin.wms.status.stash'),//'已暂存',
        ];
        return $maps[$this->status] ?? '';
    }

    static function code()
    {
        return 'YKD' . date('ymdHis') . rand(1000, 9999);
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
        return $this->hasMany(WmsStockCheckDifference::class, 'bill_code', 'code');
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
            ['value' => 'origin_code', 'label' => '来源单据编码', 'search' => false],
            ['value' => 'diff_num', 'label' => '差异总数', 'search' => false],
            ['value' => 'warehouse', 'label' => '仓库', 'search' => false],
            ['value' => 'order_user', 'label' => '下单人', 'search' => false],
            ['value' => 'order_at', 'label' => '下单时间', 'search' => false],
            ['value' => 'remark', 'label' => '备注'],
        ];
    }
}
