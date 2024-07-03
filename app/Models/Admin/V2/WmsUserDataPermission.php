<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUser;
use App\Models\AdminUsers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WmsUserDataPermission extends wmsBaseModel
{
    use HasFactory, SoftDeletes;
    protected $guarded = [];
    public $table = "wms_user_data_permission";
    const TYPE_ZH = 0; //租户
    const TYPE_SUPPLIER = 2; //供应商
    const TYPE_WAREHOUSE = 3; //仓库
    const TYPE_CUSTOMER = 4; //客户

    function user()
    {
        return $this->hasOne(AdminUsers::class, 'user_code', 'user_code');
    }

    static function userPermission($user)
    {
        // 找到所有授权
        // 直接是具体授权，直接返回
        // 如果是组织类型，找到所属下级的所有授权
        $all = self::where('user_code', $user->user_code)->get();
        $supplier = [];
        $supplier2 = [];
        $warehouse = [];
        $shop = [];
        $sup = Supplier::select(['id', 'sup_code'])->get()->keyBy('sup_code')->toArray();
        self::_permission($all, $sup, $supplier, $supplier2, $warehouse, $shop);
        return compact('supplier', 'supplier2', 'warehouse', 'shop');
    }

    private static function _permission($all, $sup, &$supplier, &$supplier2, &$warehouse, &$shop)
    {
        foreach ($all as $item) {
            if ($item->type == 0) {
                // 找出组织的所有子组织
                $org_codes = WmsDataPermission::where('path', 'like', '%_' . $item->id . '_%')->orWhere('path', 'like', $item->id . '_%')->pluck('code');
                if (!$org_codes) continue;
                $all2 = WmsDataPermission::whereIn('parent_code', $org_codes)->get();
                self::_permission($all2, $sup, $supplier, $supplier2, $warehouse, $shop);
            }
            if ($item->type == 1) {
                $supplier[] = $item->org_code;
                $sup_id = $sup[$item->org_code]['id'] ?? 0;
                if ($sup_id) $supplier2[] = $sup_id;
            }
            if ($item->type == 2) $warehouse[] = $item->org_code;
            if ($item->type == 3) $shop[] = $item->org_code;
        }
    }
}
