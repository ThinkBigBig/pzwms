<?php

namespace App\Models\Admin\V2;

use App\Logics\BaseLogic;

use App\Logics\traits\WmsAttribute;
use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WmsTaskStrategy extends wmsBaseModel
{
    use HasFactory, SoftDeletes, WmsAttribute;
    protected $guarded = [];
    public $table = "wms_task_strategies";

    const OFF = 0; //关闭
    const ON = 1; //启用

    const MODE_SORT_OUT = 1; //分拣配货
    const MODE_ORDER = 2; //按单配货
    

    protected $casts = [
        'content' => 'array',
    ];

    static function maps($attr, $option = false)
    {
        $maps = [
            'status' => [
                self::ON => __('admin.wms.status.on'), //'启用',
                self::OFF => __('admin.wms.status.off'), //'禁用',
            ],
            'mode' => [
                self::MODE_ORDER => __('admin.wms.mode.order'), //'按单配货',
                self::MODE_SORT_OUT => __('admin.wms.mode.sort_out'), //'分拣配货',
            ],
        ];
        if (!$option) return $maps[$attr];
        return self::cloumnOptions($maps[$attr]);
    }


    protected $appends = ['content_info', 'mode_txt', 'status_txt'];

    public function setContentAttribute($content)
    {
        $this->attributes['content'] = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }


    public function getContentInfoAttribute()
    {
        // {['column'=>'','condition'=>'','value'=>'','logic'=>'']}
        $content = $this->content;
        $columns = self::$conditions['columns'];
        $options = self::$conditions['options']['number'];
        $options = collect($options)->keyBy('val')->toArray();
        $res = [];
        foreach ($content as $item) {
            $res[] = [
                'column' => $columns[$item['column']],
                'condition' => $options[$item['condition']]['name'],
                'value' => $item['value'],
                'logic' => $item['logic'],
            ];
        }
        return $res;
    }

    public function getModeTxtAttribute()
    {
        return self::maps('mode')[$this->mode];
    }
    public function getStatusTxtAttribute()
    {
        $maps = [
            self::ON => '启用',
            self::OFF => '禁用',
        ];
        return $maps[$this->status];
    }

    static $conditions = [
        'columns' => [
            'origin_type' => '单据类型',
            'shop_name' => '店铺',
            'order_platform' => '来源平台',
            'sku_num' => '订单产品数量',
            'delivery_time' => '剩余发货时长',
            'product_category' => '产品分类',
        ],
        'options' => [
            'number' => [
                ['val' => '=', 'name' => '等于'],
                ['val' => '!=', 'name' => '不等于'],
                ['val' => '>', 'name' => '大于'],
                ['val' => '<', 'name' => '小于'],
                ['val' => '>=', 'name' => '大于等于'],
                ['val' => '<=', 'name' => '小于等于'],
                ['val' => 'in', 'name' => '包括'],
                ['val' => 'is null', 'name' => '为空'],
                ['val' => 'is not null', 'name' => '不为空'],
            ],
            'string' => [
                ['val' => '=', 'name' => '等于'],
                ['val' => 'in', 'name' => '包括'],
                ['val' => 'is null', 'name' => '为空'],
                ['val' => 'is not null', 'name' => '不为空'],
            ]
        ],
    ];

    function adminUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'admin_user_id');
    }

    function createUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'create_user_id');
    }

    function columnOptions()
    {
        $user = BaseLogic::adminUsers();
        return [
            'warehouse_code' => BaseLogic::warehouseOptions(),
            'mode' => self::maps('mode',true),
            'status' => self::maps('status',true),
            'create_user_id' => $user,
            'admin_user_id' => $user,
        ];
    }

    public $requiredColumns = [];

    function columns()
    {
        // export=true 导出时展示 ， search=true 搜索查询时展示
        return [
            ['value' => 'warehouse_code', 'label' => '仓库', 'export' => false],
            ['value' => 'warehouse_name', 'label' => '仓库', 'search' => false],
            ['value' => 'code', 'label' => '分组编码'],
            ['value' => 'mode', 'label' => '配货模式', 'export' => false],
            ['value' => 'mode_txt', 'label' => '配货模式', 'search' => false],
            ['value' => 'name', 'label' => '分组名称'],
            ['value' => 'sort', 'label' => '优先级'],
            ['value' => 'upper_limit', 'label' => '配货任务领取上限'],
            ['value' => 'status', 'label' => '状态', 'export' => false],
            ['value' => 'status_txt', 'label' => '状态', 'search' => false],
            ['value' => 'remark', 'label' => '备注'],
            ['value' => 'create_user_id', 'label' => '创建人', 'export' => false],
            ['value' => 'create_user', 'label' => '创建人', 'search' => false],
            ['value' => 'created_at', 'label' => '创建时间'],
            ['value' => 'admin_user_id', 'label' => '最后更新人', 'export' => false],
            ['value' => 'admin_user', 'label' => '最后更新人', 'search' => false],
            ['value' => 'updated_at', 'label' => '最后更新时间'],
        ];
    }
}
