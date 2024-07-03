<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WmsDataPermission extends wmsBaseModel
{
    use HasFactory, SoftDeletes;
    protected $guarded = [];
    protected $table = 'wms_data_permission';

    protected $appends = ['type_txt', 'status_txt'];

    // 0-组织 1-供应商 2-仓库 3-店铺 4-客户
    const ORG = 0;
    const SUPPLIER = 1;
    const WAREHOUSE = 2;
    const SHOP = 3;
    const CUSTOMER = 4;

    static function maps($attr, $option = false)
    {
        $maps =  [
            'type' => [
                self::CUSTOMER => '终端客户', //'终端客户',
                self::SHOP => '店铺', //'店铺',
                self::WAREHOUSE => '仓库', //'仓库',
                self::SUPPLIER => '供应商', //'供应商',
                self::ORG => '组织', //'组织',
            ],
            'status' => [
                '1' =>  '启用', //'禁用',
                '0' =>  '禁用', //'禁用',
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

    function parent()
    {
        return $this->hasOne(WmsDataPermission::class,  'parent_code', 'code');
    }

    function users()
    {
        return $this->hasMany(WmsUserDataPermission::class, 'org_code', 'code');
    }

    // 获取已授权的用户
    static function authorizeUsers($model)
    {
        $users = WmsUserDataPermission::where('org_code', $model->code)->with('user')->get();
        $res = [];
        foreach ($users as $item) {
            $res[] = [
                'id' => $item->user ? $item->user->id : '',
                'user_code' => $item->user_code,
                'org_code' => $item->org_code,
                'username' => $item->user ? $item->user->username : '',
                'user_type' => '',
            ];
        }
        return $res;
    }

    static function code()
    {
        return 'SJ' . date('ymdHis') . rand(1000, 9999);
    }

    static function addSupplier($supplier)
    {
        $where = [
            'code' => $supplier->sup_code,
            'type' => self::SUPPLIER,
            'tenant_id' => $supplier->tenant_id,
        ];
        $find =  WmsDataPermission::where($where)->first();
        if($find) return;

        $p = self::where(['name' => '供应商', 'type' => self::ORG, 'tenant_id' => $supplier->tenant_id])->first();
        $tmp = WmsDataPermission::create([
            'code' => $supplier->sup_code,
            'type' => self::SUPPLIER,
            'tenant_id' => $supplier->tenant_id,
            'name' => $supplier->name,
            'parent_code' => $p->code,
            'status' => 1,
        ]);
        $tmp->update(['path' => sprintf('%s_%s', $p->path, $tmp->id)]);
    }

    static function addWarehouse($warehouse)
    {
        $p = self::where(['name' => '仓库', 'type' => self::ORG, 'tenant_id' => $warehouse->tenant_id])->first();
        $tmp = WmsDataPermission::updateOrcreate([
            'code' => $warehouse->warehouse_code,
            'type' => self::WAREHOUSE,
            'tenant_id' => $warehouse->tenant_id,
        ], [
            'name' => $warehouse->warehouse_name,
            'parent_code' => $p->code,
            'status' => 1,
        ]);
        $tmp->update(['path' => sprintf('%s_%s', $p->path, $tmp->id)]);
    }

    static function addShop($shop)
    {
        $p = self::where(['name' => '店铺', 'type' => self::ORG, 'tenant_id' => $shop->tenant_id])->first();
        $tmp = WmsDataPermission::updateOrcreate([
            'code' => $shop->code,
            'type' => self::SHOP,
            'tenant_id' => $shop->tenant_id,
        ], [
            'name' => $shop->name,
            'parent_code' => $p->code,
            'status' => 1,
        ]);
        $tmp->update(['path' => sprintf('%s_%s', $p->path, $tmp->id)]);
    }

    // 初始化一个组织的基本数据权限
    static function orgInit($tenant_id, $name)
    {
        $root = self::where(['tenant_id' => $tenant_id, 'parent_code' => ''])->first();
        if (!$root) {
            $root = WmsDataPermission::create([
                'name' => $name,
                'code' => self::code(),
                'type' => 0,
                'status' => 1,
                'parent_code' => '',
                'tenant_id' => $tenant_id,
                'created_user' => ADMIN_INFO['user_id'],
            ]);
        }
        $parent_code = $root->code;

        $supplier0 = WmsDataPermission::where(['name' => '供应商', 'type' => 0, 'tenant_id' => $tenant_id,])->first();
        if (!$supplier0) {
            $supplier0 = WmsDataPermission::create(['name' => '供应商', 'type' => 0, 'tenant_id' => $tenant_id, 'code' => WmsDataPermission::code(), 'status' => 1, 'parent_code' => $parent_code]);
            $supplier0->update(['path' => sprintf('1_%s', $supplier0->id)]);
        }
        $warehouse0 = WmsDataPermission::where(['name' => '仓库', 'type' => 0, 'tenant_id' => $tenant_id,])->first();
        if (!$warehouse0) {
            $warehouse0 = WmsDataPermission::create(['name' => '仓库', 'type' => 0, 'tenant_id' => $tenant_id, 'code' => WmsDataPermission::code(), 'status' => 1, 'parent_code' => $parent_code]);
            $warehouse0->update(['path' => sprintf('1_%s', $warehouse0->id)]);
        }
        $shop0 = WmsDataPermission::where(['name' => '店铺', 'type' => 0, 'tenant_id' => $tenant_id,])->first();
        if (!$shop0) {
            $shop0 = WmsDataPermission::create(['name' => '店铺', 'type' => 0, 'tenant_id' => $tenant_id, 'code' => WmsDataPermission::code(), 'status' => 1, 'parent_code' => $parent_code]);
            $shop0->update(['path' => sprintf('1_%s', $shop0->id)]);
        }
    }

    function columnOptions()
    {
        return [
            'status' => self::maps('status', true),
            'type' => self::maps('type', true),
        ];
    }

    function columns()
    {
        // statusOPtion
        // export=true 导出时展示 ，search=true 搜索查询时展示
        return [
            ['value' => 'code', 'label' => '编码'],
            ['value' => 'name', 'label' => '名称'],
            ['value' => 'parent_name', 'label' => '上级组织'],
            ['value' => 'type', 'label' => '组织类型', 'export' => false],
            ['value' => 'type_txt', 'label' => '组织类型', 'search' => false],
            ['value' => 'status', 'label' => '是否启用', 'export' => false],
            ['value' => 'status_txt', 'label' => '是否启用', 'search' => false],
            ['value' => 'remark', 'label' => '备注'],
        ];
    }

    function searchParentName($value)
    {
        $code = self::where('name', $value)->value('code');
        return ['parent_code', $code];
    }
}
