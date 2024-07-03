<?php

namespace App\Models\Admin\V2;

use App\Logics\BaseLogic;

use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WmsStockCheckRequest extends wmsBaseModel
{
    use HasFactory;
    use SoftDeletes;
    protected $guarded = [];
    protected $table = "wms_stock_check_request";

    // 0-暂存 1-审核中 2-审核通过 3-已撤销 4-审核拒绝 5-已下发
    const STASH = 0;
    const WAIT_AUDIT = 1;
    const PASS = 2;
    const REJECT = 3;
    const CANCEL = 4;
    const SEND = 5;

    // 0-待盘点 1-盘点中 2-已盘点
    const CHECK_WAIT = 0;
    const CHECK_ING = 1;
    const CHECK_DONE = 2;

    protected $casts = [
        'order_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected $appends = ['type_txt', 'status_txt', 'check_status_txt'];

    static function maps($attr, $option = false)
    {
        $maps = [
            'status' => [
                self::SEND => __('admin.wms.status.send'), //'已下发',
                self::STASH => __('admin.wms.status.stash'), //'暂存',
                self::WAIT_AUDIT => __('admin.wms.status.audit_ing'), //'审核中',
                self::PASS => __('admin.wms.status.audit'), //'审核通过',
                self::CANCEL => __('admin.wms.status.revoke'), //'已撤销',
                self::REJECT => __('admin.wms.status.reject'), //'审核拒绝',
            ],
            'check_status' => [
                self::CHECK_DONE => __('admin.wms.status.check_done'), //'已盘点',
                self::CHECK_WAIT => __('admin.wms.status.wait_check'), //'待盘点',
                self::CHECK_ING => __('admin.wms.status.check_ing'), //'盘点中',
            ],
            'type' => [
                '1' => __('admin.wms.type.check_apply'), //'动盘申请单'
            ],
            'source' => [
                '0' => __('admin.wms.source.create_human'), //'手工创建'
            ],
        ];
        if (!$option) return $maps[$attr];
        return self::cloumnOptions($maps[$attr]);
    }

    public function getTypeTxtAttribute(): string
    {
        return self::maps('type')[$this->type] ?? '';
    }

    public function getStatusTxtAttribute(): string
    {
        return self::maps('status')[$this->status] ?? '';
    }

    public function getCheckStatusTxtAttribute(): string
    {
        return self::maps('check_status')[$this->check_status] ?? '';
    }

    static function code()
    {
        return 'PDSQ' . date('ymdHis') . rand(1000, 9999);
    }

    function details()
    {
        return $this->hasMany(WmsStockCheckRequestDetail::class, 'origin_code', 'code')->where('status', 1);
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
        return $this->hasOne(AdminUser::class, 'id', 'created_user');
    }

    function adminUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'updated_user');
    }

    // 第一次生成的盘点单
    function check()
    {
        return $this->hasOne(WmsStockCheckList::class, 'request_code', 'code');
    }

    function columnOptions()
    {
        return [
            'type' => self::maps('type', true),
            'status' => self::maps('status', true),
            'check_status' => self::maps('check_status', true),
            'source' =>  self::maps('source', true),
            'warehouse_code' => BaseLogic::warehouseOptions(),
            'created_user' => BaseLogic::adminUsers(),
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
            ['value' => 'created_at', 'label' => '下单时间', 'export' => false],
            ['value' => 'status', 'label' => '单据状态', 'export' => false],
            ['value' => 'status_txt', 'label' => '单据状态', 'search' => false],
            ['value' => 'check_status', 'label' => '盘点状态', 'export' => false],
            ['value' => 'check_status_txt', 'label' => '盘点状态', 'search' => false],
            ['value' => 'warehouse_code', 'label' => '仓库', 'export' => false],
            ['value' => 'warehouse', 'label' => '仓库', 'search' => false],
            ['value' => 'source', 'label' => '盘点类型', 'export' => false],
            ['value' => 'source_txt', 'label' => '盘点类型', 'search' => false],
            ['value' => 'total_num', 'label' => '终盘总数'],
            ['value' => 'total_diff', 'label' => '终盘总差异'],
            ['value' => 'report_num', 'label' => '上报总数'],
            ['value' => 'recover_num', 'label' => '寻回总数'],
            ['value' => 'current_diff', 'label' => '当前总差异'],
            ['value' => 'created_user', 'label' => '下单人', 'export' => false],
            ['value' => 'order_user', 'label' => '下单人', 'search' => false],
            ['value' => 'order_at', 'label' => '下单时间'],
            ['value' => 'remark', 'label' => '备注'],
        ];
    }

    //更新申请单的差异数量
    static function syncDiffNum($params)
    {
        $request = self::where(['code' => $params['code']])->first();
        if ($request->check_status != self::CHECK_DONE) return;

        $recover_num = WmsStockCheckDifference::where(['request_code' => $params['code'], 'status' => 1])->sum('recover_num');
        $report_num = WmsStockCheckDifference::where(['request_code' => $params['code'], 'status' => 2])->sum('report_num');
        $request->update([
            'recover_num' => $recover_num,
            'report_num' => $report_num,
            'current_diff' => $recover_num + $report_num + $request->total_diff,
        ]);
    }
}
