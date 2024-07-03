<?php

namespace App\Models\Admin\V2;

use App\Logics\BaseLogic;

use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WmsStockMoveList extends wmsBaseModel
{
    use HasFactory;
    use SoftDeletes;
    protected $guarded = [];
    protected $table = "wms_stock_move_list";

    // 0-暂存 1-已提交  2-审核通过 3-驳回 4-取消
    const STASH = 0;
    const WAIT_AUDIT = 1;
    const PASS = 2;
    const REJECT = 3;
    const CANCEL = 4;

    const DOWN_WAIT = 0;
    const DOWNING = 1;
    const DOWN = 2;

    const SHELF_WAIT = 0;
    const SHELFING = 1;
    const SHELFED = 2;


    protected $appends = ['type_txt', 'status_txt', 'down_status_txt', 'shelf_status_txt', 'show_status', 'show_status_txt'];

    static function maps($attr, $option = false)
    {
        $maps = [

            'type' => [
                '1' => __('admin.wms.type.move'), //'计划移位单'
                '2' => __('admin.wms.type.indirect'), //'中转移位单',
                '3' => __('admin.wms.type.quick'), //'快速移位单',
            ],
            'status' => [
                self::CANCEL => __('admin.wms.status.cancel'), //'已取消',
                self::REJECT =>  __('admin.wms.status.reject'), //'审核未通过',
                self::PASS =>  __('admin.wms.status.audit'), //'已审核',
                self::WAIT_AUDIT =>  __('admin.wms.status.submit'), //'已提交',
                self::STASH =>  __('admin.wms.status.stash'), //'暂存',
            ],
            'down_status' => [
                self::DOWN => __('admin.wms.status.down'), //'已下架',
                self::DOWNING => __('admin.wms.status.down_ing'), //'下架中',
                self::DOWN_WAIT => __('admin.wms.status.wait_down'), //'待下架',
            ],
            'shelf_status' => [
                self::SHELFED => __('admin.wms.status.listed'), //'已上架',
                self::SHELFING => __('admin.wms.status.listing'), //'上架中',
                self::SHELF_WAIT => __('admin.wms.status.wait_list'), //'待上架',
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

    public function getDownStatusTxtAttribute(): string
    {
        return self::maps('down_status')[$this->down_status] ?? '';
    }

    public function getShelfStatusTxtAttribute(): string
    {
        return self::maps('shelf_status')[$this->shelf_status] ?? '';
    }

    public function getShowStatusAttribute(): string
    {
        // 1-待下架 2-下架中 3-待上架 4-上架中 5-已上架
        $map1 = [
            1 => 2, //下架中
            0 => 1, //待下架
        ];
        $map2 = [
            1 => 4, //上架中
            0 => 3, //待上架
            2 => 5, //已上架
        ];
        if (in_array($this->down_status, [0, 1])) {
            return $map1[$this->down_status] ?? 0;
        }
        return $map2[$this->shelf_status] ?? 0;
    }

    public function getShowStatusTxtAttribute(): string
    {
        if (in_array($this->down_status, [0, 1])) {
            return self::maps('down_status')[$this->down_status] ?? '';
        }
        return self::maps('shelf_status')[$this->shelf_status] ?? '';
    }


    static function code($type = 1)
    {
        $pres = [
            '1' => 'JHYWD',
            '2' => 'ZZYWD',
            '3' => 'KSYWD',
        ];
        return $pres[$type] . date('ymdHis') . rand(1000, 9999);
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

    function downUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'down_user_id');
    }

    function shelfUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'shelf_user_id');
    }


    function details()
    {
        return $this->hasMany(WmsStockMoveDetail::class, 'origin_code', 'code')->orderBy('id', 'desc');
    }

    function items()
    {
        return $this->hasMany(WmsStockMoveItem::class, 'origin_code', 'code')
            ->where('status', '>', 0);
    }

    function columnOptions()
    {
        return [
            'type' => self::maps('type', true),
            'status' => self::maps('status', true),
            'down_status' => self::maps('down_status', true),
            'shelf_status' => self::maps('shelf_status', true),
            'warehouse_code' => BaseLogic::warehouseOptions(),
            'order_user' => BaseLogic::adminUsers(),
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
            ['value' => 'status', 'label' => '单据状态', 'export' => false],
            ['value' => 'status_txt', 'label' => '单据状态', 'search' => false],
            ['value' => 'down_status', 'label' => '下架状态', 'export' => false],
            ['value' => 'down_status_txt', 'label' => '下架状态', 'search' => false],
            ['value' => 'shelf_status', 'label' => '上架状态', 'export' => false],
            ['value' => 'shelf_status_txt', 'label' => '上架状态', 'search' => false],
            ['value' => 'warehouse_code', 'label' => '移位仓', 'export' => false],
            ['value' => 'warehouse', 'label' => '移位仓', 'search' => false],
            ['value' => 'num', 'label' => '计划移位数'],
            ['value' => 'down_num', 'label' => '下架总数'],
            ['value' => 'down_diff', 'label' => '下架总差异'],
            ['value' => 'shelf_num', 'label' => '上架总数'],
            ['value' => 'shelf_diff', 'label' => '上架总差异'],
            ['value' => 'order_user', 'label' => '下单人', 'search' => false],
            ['value' => 'remark', 'label' => '备注'],
            ['value' => 'start_at', 'label' => '移位开始时间'],
            ['value' => 'end_at', 'label' => '移位完成时间'],
        ];
    }

    /**
     * 获取进行中的移位单，没有就创建一个
     *
     * @param array $params 
     *  warehouse_code 仓库编码
     * @param int $type 2-中转移位单 3-快速移位单
     */
    static function getActiveOrder($params, $type)
    {
        if (!in_array($type, [1, 2, 3])) return null;
        if ($type != 2) {
            $move = WmsStockMoveList::where([
                'type' => $type, 'warehouse_code' => $params['warehouse_code'], 'status' => 2, 'created_user' => ADMIN_INFO['user_id']
            ])->whereIn('shelf_status', [0, 1])->first();
            if ($move) return $move;
        }

        $data = [
            'type' => $type,
            'code' => self::code($type),
            'warehouse_code' => $params['warehouse_code'],
            'start_at' => date('Y-m-d H:i:s'),
            'down_user_id' => ADMIN_INFO['user_id'],
            'tenant_id' => ADMIN_INFO['tenant_id'],
            'created_user' => ADMIN_INFO['user_id'],
            'down_status' => 1,
            'status' => 2,
            'start_at' => date('Y-m-d H:i:s')
        ];
        if ($type != 1) $data['order_user'] = ADMIN_INFO['user_id'];
        $move = WmsStockMoveList::create($data);
        return $move;
    }
}
