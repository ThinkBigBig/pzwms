<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class Menus extends BaseModel
{
    public $table = 'admin_menus';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    public function getTitleAttribute($value): string
    {
        return __('admin.menus.' . $value);
    }

    protected static function booted()
    {
        static::addGlobalScope('is_tenant', function (Builder $builder) {
            //这里查询过滤租户id 
            $tenant_id = request()->header('tenant_id');
            if ($tenant_id) {
                $builder->whereIn('is_tenant', [1, 2]);
            }
        });
    }

    public function menuList($user_id, $to_role_id = 0, $to_user_id = 0, $tenant_id = '')
    {
        //当前用户所属角色
        $user = AdminUser::where('tenant_id', $tenant_id)->find($user_id);
        if (!$user) return [false, 'user not exists'];
        //角色拥有的菜单
        $role_id = $user->roles_id;
        if ($role_id == 0 || $role_id == 1) $lists = self::where('status', 1)->orderBy('parent_id')->orderBy('created_at')->orderBy('order')->get()->toArray();
        else {
            $menu_id = RoleMenu::where('role_id', $user->roles_id)->pluck('menu_id')->toArray();
            $lists = self::where('status', 1)->orderBy('parent_id')->orderBy('created_at')->orderBy('order')->get()->toArray();

        }
        $to_menu_id = [];
        //要分配的用户所拥有的菜单
        if ($to_user_id) {
            if ($tenant_id) $to_user = AdminUser::where('tenant_id', $tenant_id)->find($to_user_id);
            else $to_user = AdminUser::find($to_user_id);
            $to_menu_id  = RoleMenu::where('role_id', $to_user->roles_id)->pluck('menu_id')->toArray();
        }
        if ($to_role_id) {
            $to_menu_id  = RoleMenu::where('role_id', $to_role_id)->pluck('menu_id')->toArray();
        }


        foreach ($lists as &$li) {
            if (in_array($li['id'], $to_menu_id)) {
                $li['is_checked'] = 1;
            } else {
                $li['is_checked'] = 0;
            }
        }
        $data = listToTree($lists, 'parent_id');
        return [true, $data];
    }
}
