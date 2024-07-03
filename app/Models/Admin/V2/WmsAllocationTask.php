<?php

namespace App\Models\Admin\V2;

use App\Logics\BaseLogic;

use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsAllocationTask extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'wms_allocation_tasks';

    // 状态 1-暂存 2-已审核 3-已取消
    const STASH = 1;
    const CHECKED = 2;
    const CANCEL = 3;

    static $status_map = [
        self::CANCEL => '已取消',
        self::CHECKED => '已审核',
        self::STASH => '暂存',
    ];

    const TASK_DEFAULT = 0; //待领取
    const TASK_CLEAR = 1; //已领取

    // 配货状态 1-待配货 2-配货中 3-已配货
    const ALLOCATE_WAIT = 1;
    const ALLOCATE_ING = 2;
    const ALLOCATE_DONE = 3;

    // 配货模式 1-分拣配货 2-按单配货
    const MODE_SORT_OUT = 1; //分拣配货
    const MODE_ORDER = 2; //按单配货

    static function maps($attr, $option = false)
    {
        $maps = [
            'status' => [
                self::CANCEL => __('admin.wms.status.cancel'), //'已取消',
                self::CHECKED => __('admin.wms.status.audit'), //'已审核',
                self::STASH => __('admin.wms.status.stash'), //'暂存',
            ],
            'mode' => [
                self::MODE_ORDER => __('admin.wms.mode.order'), //'按单配货',
                self::MODE_SORT_OUT => __('admin.wms.mode.sort_out'), //'分拣配货',
            ],
            'alloction_status' => [
                self::ALLOCATE_WAIT => __('admin.wms.status.wait_allocate'), //'待配货',
                self::ALLOCATE_ING => __('admin.wms.status.allocating'), //'配货中',
                self::ALLOCATE_DONE => __('admin.wms.status.allocated'), //'已配货',
            ],
            'method' => [
                '3' => __('admin.wms.method.out_intercept'), //'发货拦截',
                '2' =>  __('admin.wms.method.release_inventory'), //'释放库存',
                '1' =>  __('admin.wms.method.cancel_inventory'), //'取消库存',
            ],
            'cancel_status' => [
                '4' => __('admin.wms.status.part_list'), //'部分上架',
                '3' => __('admin.wms.status.wait_list'), //'待上架',
                '2' => __('admin.wms.status.listed'), //'已上架',
                '1' => __('admin.wms.status.done'), //'已完成',
            ],
            'type' => [
                '3' => __('admin.wms.type.out_other'), //'其他出库',
                '2' => __('admin.wms.type.out_transfer'), //'调拨出库',
                '1' => __('admin.wms.type.out_sale'), //'销售出库',
            ],
        ];
        if (!$option) return $maps[$attr];
        return self::cloumnOptions($maps[$attr]);
    }

    protected $appends = ['status_txt', 'alloction_status_txt', 'mode_txt'];
    protected $casts = [
        'custom_content' => 'array',
    ];

    public function getStatusTxtAttribute()
    {
        return self::$status_map[$this->status] ?? '';
    }

    public function getAlloctionStatusTxtAttribute()
    {
        return self::maps('alloction_status')[$this->alloction_status] ?? '';
    }

    public function getModeTxtAttribute()
    {
        return self::maps('mode')[$this->mode];
    }

    function ReceiveUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'receiver_id');
    }

    function createUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'create_user_id');
    }

    function updateUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'admin_user_id');
    }

    function taskDetail()
    {
        return $this->hasMany(preAllocationDetail::class, 'task_code', 'code');
    }

    function activeDetail()
    {
        return $this->hasMany(preAllocationDetail::class, 'task_code', 'code')->where('cancel_status', 0);
    }

    function warehouse()
    {
        return $this->hasOne(Warehouse::class, 'warehouse_code', 'warehouse_code');
    }

    public function setCustomContentAttribute($properties)
    {
        $this->attributes['custom_content'] = json_encode($properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    function columnOptions()
    {
        return [
            'mode'  => WmsAllocationTask::maps('mode', true),
            'status'  => WmsAllocationTask::maps('status', true),
            'alloction_status' => WmsAllocationTask::maps('alloction_status', true),
            'warehouse_code' => BaseLogic::warehouseOptions(),
            'receiver_id' => BaseLogic::adminUsers(),
        ];
    }

    public $requiredColumns = [];

    function columns()
    {
        // export=true 导出时展示 ， search=true 搜索查询时展示
        return [
            ['value' => 'type', 'label' => '单据类型'],
            ['value' => 'code', 'label' => '单据编码'],
            ['value' => 'mode', 'label' => '配货模式', 'export' => false],
            ['value' => 'mode_txt', 'label' => '配货模式', 'search' => false],
            ['value' => 'created_at', 'label' => '下单时间', 'search' => false],
            ['value' => 'status', 'label' => '单据状态', 'export' => false],
            ['value' => 'status_txt', 'label' => '单据状态', 'search' => false],
            ['value' => 'alloction_status', 'label' => '配货状态', 'export' => false],
            ['value' => 'alloction_status_txt', 'label' => '配货状态', 'search' => false],
            ['value' => 'group_no', 'label' => '波次号'],
            ['value' => 'order_num', 'label' => '配货订单数'],
            ['value' => 'warehouse_code', 'label' => '仓库', 'export' => false],
            ['value' => 'warehouse_name', 'label' => '仓库', 'search' => false],
            ['value' => 'print_at', 'label' => '首次打印时间'],
            ['value' => 'receiver_id', 'label' => '配货人', 'export' => false],
            ['value' => 'receive_user', 'label' => '配货人', 'search' => false],
            ['value' => 'start_at', 'label' => '开始配货时间'],
            ['value' => 'confirm_at', 'label' => '确认配货时间'],
        ];
    }
}
