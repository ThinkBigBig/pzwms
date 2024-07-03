<?php

namespace App\Models\Admin\V2;

use App\Logics\BaseLogic;
use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WmsStockCheckList extends wmsBaseModel
{
    use HasFactory;
    use SoftDeletes;
    protected $table = "wms_stock_check_list";
    protected $guarded = [];
    protected $appends = ['type_txt', 'status_txt', 'check_status_txt', 'check_type_txt'];


    // 0-暂存 1-审核中 2-审核通过 3-已撤销 4-审核拒绝
    const STASH = 0;
    const WAIT_AUDIT = 1;
    const PASS = 2;

    // 0-待盘点 1-盘点中 2-已盘点
    const CHECK_WAIT = 0;
    const CHECK_ING = 1;
    const CHECK_DONE = 2;

    static function maps($attr, $option = false)
    {
        $maps = [
            'status' => [
                self::PASS => __('admin.wms.status.audit'), //'审核通过',
                self::STASH => __('admin.wms.status.stash'), //'暂存',
                self::WAIT_AUDIT => __('admin.wms.status.audit_ing'), //'审核中',
            ],
            'check_status' => [
                self::CHECK_DONE => __('admin.wms.status.check_done'), //'已盘点',
                self::CHECK_WAIT => __('admin.wms.status.wait_check'), //'待盘点',
                self::CHECK_ING => __('admin.wms.status.check_ing'), //'盘点中',
            ],
            'check_type'=>[
                '1' => __('admin.wms.type.blind_check'),//'盲盘', 
                '0' => __('admin.wms.type.open_check'),//'明盘',
            ],
            'type'=>[
                '1' => __('admin.wms.type.check_apply'),//'动盘申请单'
            ],
        ];
        if (!$option) return $maps[$attr];
        return self::cloumnOptions($maps[$attr]);
    }

    public function getTypeTxtAttribute(): string
    {
        return self::maps('type')[$this->type]??'';
    }

    public function getStatusTxtAttribute(): string
    {
        return self::maps('status')[$this->status] ?? '';
    }

    public function getCheckStatusTxtAttribute(): string
    {
        return self::maps('check_status')[$this->check_status] ?? '';
    }

    public function getCheckTypeTxtAttribute(): string
    {
        return self::maps('check_type')[$this->check_type] ?? '';
    }


    static function code()
    {
        return 'PDD' . date('ymdHis') . rand(1000, 9999);
    }

    function warehouse()
    {
        return $this->hasOne(Warehouse::class, 'warehouse_code', 'warehouse_code');
    }

    function checkUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'check_user_id');
    }

    function createUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'created_user');
    }

    function adminUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'updated_user');
    }

    function details()
    {
        return $this->hasMany(WmsStockCheckDetail::class, 'origin_code', 'code')->orderBy('id','desc');
    }

    function logs()
    {
        return $this->hasMany(WmsStockCheckLog::class, 'origin_code', 'code');
    }

    function request()
    {
        return $this->hasOne(WmsStockCheckRequest::class, 'code', 'request_code');
    }


    function columnOptions()
    {
        return [
            'type' => self::maps('type',true),
            'status' => self::maps('status',true),
            'check_status' => self::maps('check_status',true),
            'check_type' => self::maps('check_type',true),
            'warehouse_code' => BaseLogic::warehouseOptions(),
            'check_user_id' => BaseLogic::adminUsers(),
        ];
    }

    public $requiredColumns = [];

    function columns()
    {
        // export=true 导出时展示 ， search=true 搜索查询时展示
        return [
            ['value' => 'type', 'label' => '单据类型', 'export' => false],
            ['value' => 'type_txt', 'label' => '单据类型', 'search' => false],
            ['value' => 'code', 'label' => '单据编码'],
            ['value' => 'created_at', 'label' => '下单时间'],
            ['value' => 'status', 'label' => '单据状态', 'export' => false],
            ['value' => 'status_txt', 'label' => '单据状态', 'search' => false],
            ['value' => 'check_status', 'label' => '盘点状态', 'export' => false],
            ['value' => 'check_status_txt', 'label' => '盘点状态', 'search' => false],
            ['value' => 'check_type', 'label' => '盘点类型', 'export' => false],
            ['value' => 'check_type_txt', 'label' => '盘点类型', 'search' => false],
            ['value' => 'request_code', 'label' => '来源单据编码'],
            ['value' => 'warehouse_code', 'label' => '仓库', 'export' => false],
            ['value' => 'warehouse', 'label' => '仓库', 'search' => false],
            ['value' => 'check_user_id', 'label' => '盘点人', 'export' => false],
            ['value' => 'check_user', 'label' => '盘点人', 'search' => false],
            ['value' => 'start_at', 'label' => '盘点开始时间'],
            ['value' => 'end_at', 'label' => '盘点完成时间'],
            ['value' => 'remark', 'label' => '备注'],
        ];
    }
}
