<?php

namespace App\Models\Admin\V2;

use App\Logics\BaseLogic;

use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class WmsQualityList extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    public $table = 'wms_quality_list';

    const METHOD_ONE_STEP = 1; //一键质检
    const METHOD_ONE_BY_ONE = 2; //逐件质检
    const METHOD_RECEIVE = 3; //收货即质检

    const STATUS_STAGE = 0; //暂存
    const STATUS_AUDIT = 1; //已审核

    const QC_ING = 0; //质检中
    const QC_DONE = 1; //已完成

    const TYPE_STORE_IN = 1; //入库质检单
    const TYPE_WAREHOUSE = 2; //仓内质检单

    static function maps($attr, $option = false)
    {
        $maps = [
            'status' => [
                self::STATUS_STAGE => __('admin.wms.status.stash'), //'暂存',
                self::STATUS_AUDIT => __('admin.wms.status.audit'), //'已审核',
            ],
            'type' => [
                self::TYPE_STORE_IN => __('admin.wms.type.qc_stock_in'), //'入库质检单', 
                self::TYPE_WAREHOUSE => __('admin.wms.type.qc_warehouse'), //'仓内质检单'
            ],
            'qc_status' => [
                self::QC_ING => __('admin.wms.status.qc_ing'), //'质检中', 
                self::QC_DONE => __('admin.wms.status.qc_done'), //'已完成'
            ],
            'method' => [
                self::METHOD_ONE_STEP => __('admin.wms.method.qc_one_step'), //'一键质检', 
                self::METHOD_ONE_BY_ONE => __('admin.wms.method.qc_one_by_one'), //'逐件质检', 
                self::METHOD_RECEIVE => __('admin.wms.method.qc_receive'), //'收货即质检'
            ],
        ];
        if (!$option) return $maps[$attr];
        return self::cloumnOptions($maps[$attr]);
    }

    protected $appends = ['type_txt', 'status_txt', 'qc_status_txt', 'method_txt'];

    public function getTypeTxtAttribute(): string
    {
        return self::maps('type')[$this->type] ?? '';
    }

    public function getStatusTxtAttribute(): string
    {
        return self::maps('status')[$this->status] ?? '';
    }

    public function getQcStatusTxtAttribute(): string
    {
        return self::maps('qc_status')[$this->qc_status] ?? '';
    }

    public function getMethodTxtAttribute(): string
    {
        return self::maps('method')[$this->method] ?? '';
    }

    function adminUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'admin_user_id');
    }

    function details()
    {
        return $this->hasMany(WmsQualityDetail::class, 'qc_code', 'qc_code');
    }

    function createUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'create_user_id');
    }

    function submitUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'submit_user_id');
    }

    function receiveOrder()
    {
        return $this->hasOne(RecvOrder::class, 'recv_code', 'recv_code');
    }
    function arrOrder()
    {
        return $this->hasOne(ArrivalRegist::class, 'id', 'arr_id');
    }

    static function code()
    {
        return sprintf('ZJD%s%s', date('ymd'), rand(1000000000, 9999999999));
    }

    //搜索时用户相关的字段对应关系
    public function searchUser()
    {
        return [
            'submit_user_id' => 'submit_user_id',
        ];
    }

    function columnOptions()
    {
        return [
            'warehouse_code' => BaseLogic::warehouseOptions(),
            'submit_user_id' => BaseLogic::adminUsers(),
            'type' => WmsQualityList::maps('type', true),
            'status' => WmsQualityList::maps('status', true),
            'qc_status' => WmsQualityList::maps('qc_status', true),
            'method' => WmsQualityList::maps('method', true),
        ];
    }

    function columns()
    {
        // statusOPtion
        // export=true 导出时展示 ，search=true 搜索查询时展示
        return [
            ['value' => 'warehouse_name', 'label' => '仓库',  'export' => true, 'search' => false],
            ['value' => 'warehouse_code', 'label' => '仓库',  'search' => true, 'export' => false],
            ['value' => 'type_txt', 'label' => '单据类型',  'search' => false],
            ['value' => 'qc_code', 'label' => '单据编码'],
            ['value' => 'status', 'label' => '单据状态','search' => true, 'export' => false],
            ['value' => 'status_txt', 'label' => '单据状态', 'search' => false, 'export' => true],
            ['value' => 'qc_status_txt', 'label' => '质检状态',  'search' => false],
            ['value' => 'method_txt', 'label' => '质检方式',  'search' => false],
            ['value' => 'total_num', 'label' => '质检数量'],
            ['value' => 'probable_defect_num', 'label' => '疑似瑕疵数',  'search' => false],
            ['value' => 'normal_num', 'label' => '正品数'],
            ['value' => 'defect_num', 'label' => '瑕疵数'],
            ['value' => 'remark', 'label' => '备注'],
            ['value' => 'username','lang'=>'submit_user_id', 'label' => '质检人', 'export' => true ,'search' => false],
            ['value' => 'submit_user_id', 'label' => '质检人',  'export' => false],
            ['value' => 'created_at', 'label' => '质检开始时间'],
            ['value' => 'completed_at', 'label' => '质检完成时间'],
            ['value' => 'type', 'label' => '单据类型',  'export' => false],
            ['value' => 'method', 'label' => '质检方式',  'export' => false],
        ];
    }
}
