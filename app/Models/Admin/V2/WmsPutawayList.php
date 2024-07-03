<?php

namespace App\Models\Admin\V2;

use App\Logics\BaseLogic;

use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsPutawayList extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    public $table = 'wms_putaway_list';

    const TYPE_STORE_IN = 1; //入库上架单
    const TYPE_MOVE = 2; //移位上架单
    const TYPE_CANCEL = 3; //取消上架单

    const STATUS_STAGE = 0; //暂存
    const STATUS_AUDIT = 1; //已审核

    const PUTAWAY_ING = 0; //上架中
    const PUTAWAY_DONE = 1; //已上架

    static function maps($attr, $option = false)
    {
        $maps = [
            'status' => [
                '0' => __('admin.wms.status.stash'),//'暂存',
                '1' => __('admin.wms.status.audit'),//'已审核',
            ],
            'type' => [
                self::TYPE_STORE_IN => __('admin.wms.type.list_stock_in'),//'入库上架单',
                self::TYPE_MOVE => __('admin.wms.type.list_move'),//'移位上架单'
                self::TYPE_CANCEL => __('admin.wms.type.list_cancel'),//'取消上架单'
            ],
            'putaway_status' => [
                '0' => __('admin.wms.status.listing'),//'上架中',
                '1' => __('admin.wms.status.listed'),//'已上架'
            ],
        ];
        if (!$option) return $maps[$attr];
        return self::cloumnOptions($maps[$attr]);
    }

    protected $appends = ['type_txt', 'status_txt', 'putaway_status_txt'];

    public function getTypeTxtAttribute(): string
    {
        return self::maps('type')[$this->type] ?? '';
    }

    public function getStatusTxtAttribute(): string
    {
        return self::maps('status')[$this->status] ?? '';
    }

    public function getPutawayStatusTxtAttribute(): string
    {
        return self::maps('putaway_status')[$this->putaway_status] ?? '';
    }

    function adminUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'admin_user_id');
    }

    function createUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'create_user_id');
    }

    function submitUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'submitter_id');
    }

    static function code()
    {
        return sprintf('SJD%s%s', date('ymd'), rand(1000000000, 9999999999));
    }

    public function searchUser()
    {
        return [
            'submitter_id' => 'submitter_id',
            'admin_user_id' => 'admin_user_id',
        ];
    }
    function columnOptions()
    {
        $user = BaseLogic::adminUsers();
        return [
            'type' => self::maps('type',true),
            'status' => self::maps('status',true),
            'putaway_status' => self::maps('putaway_status'),
            'warehouse_code' => BaseLogic::warehouseOptions(),
            'submitter_id' => $user,
        ];
    }

    public $requiredColumns = [];

    function columns()
    {
        // export=true 导出时展示 ， search=true 搜索查询时展示
        return [
            ['value' => 'type', 'label' => '单据类型', 'export' => false,],
            ['value' => 'type_txt', 'label' => '单据类型', 'search' => false,],
            ['value' => 'putaway_code', 'label' => '单据编码'],
            ['value' => 'status', 'label' => '单据状态', 'export' => false,],
            ['value' => 'status_txt', 'label' => '单据状态', 'search' => false,],
            ['value' => 'putaway_status', 'label' => '上架状态', 'export' => false,],
            ['value' => 'putaway_status_txt', 'label' => '上架状态', 'search' => false,],
            ['value' => 'total_num', 'label' => '上架总数'],
            ['value' => 'warehouse_code', 'label' => '上架仓库', 'export' => false,],
            ['value' => 'warehouse_name', 'label' => '上架仓库', 'search' => false,],
            ['value' => 'submitter_id', 'label' => '上架人', 'export' => false,],
            ['value' => 'submit_user', 'label' => '上架人', 'search' => false,],
            ['value' => 'created_at', 'label' => '上架开始时间'],
            ['value' => 'completed_at', 'label' => '上架完成时间'],
            ['value' => 'remark', 'label' => '备注'],
        ];
    }
}
