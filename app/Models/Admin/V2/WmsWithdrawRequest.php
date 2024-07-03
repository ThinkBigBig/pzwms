<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUsers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsWithdrawRequest extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'wms_withdraw_request';

    static function maps($attr, $option = false)
    {
        $maps = [
            'status' => [
                "1" => __('admin.wms.status.audit'), //'已审核',
                "0" => __('admin.wms.status.audit_ing'), //待审核,
            ],
            'type' => [
                '1' => __('admin.wms.type.handwork'), //'提现申请单',
            ],
            'source' => [
                '1' => __('admin.wms.type.handwork'), //'手工创建',
            ],
        ];
        if (!$option) return $maps[$attr];
        return self::cloumnOptions($maps[$attr]);
    }

    protected $appends = ['status_txt', 'type_txt', 'source_txt'];

    public function getStatusTxtAttribute()
    {
        return self::maps('status')[$this->status] ?? '';
    }

    public function getTypeTxtAttribute()
    {
        return self::maps('type')[$this->type] ?? '';
    }
    public function getSourceTxtAttribute()
    {
        return self::maps('source')[$this->source] ?? '';
    }

    function createUser()
    {
        return $this->hasOne(AdminUsers::class, 'id', 'created_user');
    }

    function updateUser()
    {
        return $this->hasOne(AdminUsers::class, 'id', 'updated_user');
    }


    function columnOptions()
    {
        return [
            'type'  => self::maps('type', true),
            'status'  => self::maps('status', true),
            'source' => self::maps('source', true),
        ];
    }

    public $requiredColumns = [];

    function columns()
    {
        // export=true 导出时展示 ， search=true 搜索查询时展示
        return [
            ['value' => 'type', 'label' => '单据类型', 'export' => false],
            ['value' => 'type_txt', 'label' => '单据类型', 'search' => false,],
            ['value' => 'code', 'label' => '单据编码'],
            ['value' => 'apply_at', 'label' => '申请时间'],
            ['value' => 'status', 'label' => '单据状态', 'export' => false],
            ['value' => 'status_txt', 'label' => '单据状态', 'search' => false,],
            ['value' => 'source', 'label' => '单据来源', 'export' => false],
            ['value' => 'source_txt', 'label' => '单据来源', 'search' => false,],
            ['value' => 'sup_name', 'label' => '供应商'],
            ['value' => 'total', 'label' => '总数量'],
            ['value' => 'amount', 'label' => '结算总额'],
            ['value' => 'order_user', 'label' => '下单人'],
            ['value' => 'remark', 'label' => '备注'],
            ['value' => 'created_user', 'label' => '创建人'],
            // ['value' => 'created_at', 'label' => '创建时间'],
            ['value' => 'updated_user', 'label' => '最后更新人'],
            // ['value' => 'updated_at', 'label' => '最后更新时间'],
        ];
    }

    static function code()
    {
        return 'TXSQD' . date('ymdHis') . rand(1000, 9999);
    }
}
