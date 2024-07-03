<?php

namespace App\Models\Admin\V2;

use App\Logics\BaseLogic;
use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsOrderStatement extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'wms_order_statements';

    // protected $casts = [
    //     // 'settled_time' => 'datetime:Y-m-d H:i:s',
    //     // 'amount_time' => 'datetime:Y-m-d H:i:s',
    //     // 'order_at' => 'datetime:Y-m-d H:i:s',
    // ];

    // 1-销售订单 2-售后订单
    const TYPE_ORDER = 1;
    const TYPE_AFTER_SALE = 2;

    protected $appends = ['type_txt', 'status_txt'];
    static function maps($attr, $option = false)
    {
        $maps =  [
            'type' => [
                self::TYPE_AFTER_SALE => __('admin.wms.type.aftersale'), //'售后订单',
                self::TYPE_ORDER => __('admin.wms.type.sale'), //'销售订单',
            ],

            'status' => [
                '1' => __('admin.wms.status.settled'), //'已结算',
                '0' => __('admin.wms.status.wait_settle'), //'待结算',
            ],
        ];
        if (!$option) return $maps[$attr];
        return self::cloumnOptions($maps[$attr]);
    }

    // public function getSettledTimeAttribute($value): string
    // {
    //     if ($value == 0) return '';
    //     return date('Y-m-d H:i:s', $value);
    // }

    // public function getAmountTimeAttribute($value): string
    // {
    //     if ($value == 0) return '';
    //     return date('Y-m-d H:i:s', $value);
    // }

    public function getTypeTxtAttribute(): string
    {
        return self::maps('type')[$this->type] ?? '';
    }

    public function getStatusTxtAttribute(): string
    {
        return self::maps('status')[$this->status] ?? '';
    }


    static function code()
    {
        return 'XSJSD' . date('ymdHis') . rand(1000, 9999);
    }

    function createUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'create_user_id');
    }

    function adminUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'admin_user_id');
    }

    function settledUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'settled_user_id');
    }

    function columnOptions()
    {
        $users = BaseLogic::adminUsers();
        return [
            'status' => self::maps('status', true),
            'type' => self::maps('type', true),
            'settled_user_id' => $users,
            'create_user_id' => $users,
            'admin_user_id' => $users,
        ];
    }

    function columns()
    {
        // export=true 导出时展示 ，search=true 搜索查询时展示
        return [
            ['value' => 'status', 'label' => '结算状态', 'export' => false],
            ['value' => 'status_txt', 'label' => '结算状态',  'search' => false],
            ['value' => 'code', 'label' => '单据编码'],
            ['value' => 'origin_code', 'label' => '业务单据编码'],
            ['value' => 'type', 'label' => '单据类型', 'export' => false],
            ['value' => 'type_txt', 'label' => '单据类型', 'search' => false],
            ['value' => 'amount_time', 'label' => '收退款时间'],
            ['value' => 'third_no', 'label' => '电商单号'],
            ['value' => 'shop_name', 'label' => '店铺'],
            ['value' => 'buyer_account', 'label' => '买家账号'],
            ['value' => 'amount', 'label' => '总金额'],
            ['value' => 'settle_amount', 'label' => '应结算总额'],
            ['value' => 'settled_amount', 'label' => '结算金额'],
            ['value' => 'settled_user_id', 'label' => '结算人',  'export' => false],
            ['value' => 'settled_user', 'label' => '结算人', 'search' => false],
            ['value' => 'settled_time', 'label' => '结算时间'],
            ['value' => 'create_user_id', 'label' => '创建人', 'export' => false],
            ['value' => 'create_user', 'label' => '创建人', 'search' => false],
            ['value' => 'created_at', 'label' => '创建时间'],
            ['value' => 'admin_user_id', 'label' => '最后更新人', 'export' => false],
            ['value' => 'admin_user', 'label' => '最后更新人', 'search' => false],
            ['value' => 'updated_at', 'label' => '最后更新时间'],
            ['value' => 'remark', 'label' => '备注'],
        ];
    }
}
