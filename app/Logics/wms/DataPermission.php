<?php

namespace App\Logics\wms;

use App\Logics\BaseLogic;
use App\Logics\RedisKey;
use App\Models\Admin\V2\Supplier;
use App\Models\Admin\V2\WmsDataPermission;
use App\Models\Admin\V2\WmsUserDataPermission;
use App\Models\AdminUser;
use App\Models\AdminUsers;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class DataPermission extends BaseLogic
{
    static function userPermissionList($user, $refresh = false)
    {
        // $refresh = 1;
        if (!$refresh) {
            $data = Redis::hget(RedisKey::wmsPermission($user->tenant_id), $user->user_code);
            if ($data) return json_decode($data, true);
        }
        $all = WmsUserDataPermission::where('user_code', $user->user_code)->orderBy('type')->get();
        $sup_codes = $all->where('type', 1)->pluck('org_code')->toArray();
        $supplier = [];
        $supplier2 = [];
        $warehouse = [];
        $shop = [];
        $data = compact('supplier', 'supplier2', 'warehouse', 'shop');
        // if($sup_codes) $sup = Supplier::whereIn('sup_code',$sup_codes)->select(['id', 'sup_code'])->get()->keyBy('sup_code')->toArray();
        // else$sup = Supplier::select(['id', 'sup_code'])->get()->keyBy('sup_code')->toArray();
        foreach ($all->toArray() as $user_auth) {
            $data_auth = WmsDataPermission::where('code', $user_auth['org_code'])->where('status', 1)->first();
            if (!$data_auth) continue;
            self::_permission($data_auth, $data);
        }
        Redis::hset(RedisKey::wmsPermission($user->tenant_id), $user->user_code, json_encode($data));
        // dd($data);
        return $data;
    }

    static function userPermissionList2($tenant_id, $user_code, $refresh = false)
    {
        if (!$refresh) {
            $data = Redis::hget(RedisKey::wmsPermission($tenant_id), $user_code);
            if ($data) return json_decode($data, true);
        }

        $all = WmsUserDataPermission::where('user_code', $user_code)->get();
        $sup_codes = $all->where('type', 1)->pluck('org_code')->toArray();
        $supplier = [];
        $supplier2 = [];
        $warehouse = [];
        $shop = [];
        if ($sup_codes) $sup = Supplier::whereIn('sup_code', $sup_codes)->select(['id', 'sup_code'])->get()->keyBy('sup_code')->toArray();
        else $sup = Supplier::select(['id', 'sup_code'])->get()->keyBy('sup_code')->toArray();
        self::_permission1($all, $sup, $supplier, $supplier2, $warehouse, $shop);
        $data = compact('supplier', 'supplier2', 'warehouse', 'shop');
        Redis::hset(RedisKey::wmsPermission($tenant_id), $user_code, json_encode($data));
        return $data;
    }

    private static function _permission($data_auth, &$data)
    {
        if ($data_auth->type == 0) {
            $path = $data_auth->path . '_%';
            $type = WmsDataPermission::where('path', 'like', $path)->where('status', 1)->first();
            if (!$type) return;
            $type = $type->type;
            $where = [
                'status' => 1,
                'type' => $type,
            ];
            // $w_path = ['path','like',$path];
        } else {
            $type = $data_auth->type;
            $append = 1;
        }

        if (empty($append)) $is_set = WmsDataPermission::where($where)->where('path', 'not like', $path)->exists();
        switch ($type) {
            case '1':
                //供应商
                if (!empty($data['all']['supplier'])) return;
                if (isset($is_set)) {
                    if ($is_set) {
                        $supplier_codes =   WmsDataPermission::where($where)->where('path', 'like', $path)->pluck('code')->toArray();
                        $sup_map = Supplier::whereIn('sup_code', $supplier_codes)->where('status', 1)->pluck('sup_code', 'id')->toArray();
                        $data['supplier'] = array_merge($data['supplier'] ?? [], array_keys($sup_map));
                        $data['supplier2'] = array_merge($data['supplier2'] ?? [], array_values($sup_map));
                    } else {
                        $data['supplier'] = [];
                        $data['supplier2'] = [];
                        $data['all']['supplier'] = 1;
                    }
                }
                if (!empty($append)) {
                    $data['supplier'][] = $data_auth->code;
                    $id = Supplier::where('sup_code',$data_auth->code)->value('id');
                    $data['supplier2'][] = $id?:0;
                }
                break;
            case '2':
                //仓库
                if (!empty($data['all']['warehouse'])) return;
                if (isset($is_set)) {
                    if ($is_set) {
                        $wahouse =   WmsDataPermission::where($where)->where('path', 'like', $path)->get();
                        $wahouse_codes = $wahouse->pluck('code')->toArray();
                        $names = $wahouse->pluck('name')->toArray();
                        $data['warehouse'] = array_merge($data['warehouse'] ?? [], $wahouse_codes);
                        $data['warehouse_name'] = array_merge($data['warehouse_name'] ?? [], $names);
                    } else {
                        $data['warehouse'] = [];
                        $data['all']['warehouse'] = 1;
                    }
                }
                if (!empty($append)) {
                    $data['warehouse'][] = $data_auth->code;
                    $data['warehouse_name'][] = $data_auth->name;
                }
                break;
            case '3':
                //店铺
                if (!empty($data['all']['shop'])) return;
                if (isset($is_set)) {
                    if ($is_set) {
                        $shops =   WmsDataPermission::where($where)->where('path', 'like', $path)->get();
                        $shop_codes  = $shops->pluck('code')->toArray();
                        $shop_names  = $shops->pluck('name')->toArray();
                        $data['shop'] = array_merge($data['shop'] ?? [], $shop_codes);
                        $data['shop_name'] = array_merge($data['shop_name'] ?? [], $shop_names);
                    } else {
                        $data['shop'] = [];
                        $data['all']['shop'] = 1;
                    }
                }
                if (!empty($append)) {
                    $data['shop'][] = $data_auth->code;
                    $data['shop_name'][] = $data_auth->name;
                }
                break;
            default:
                # code...
                break;
        }
    }

    private static function _permission1($all, $sup, &$supplier, &$supplier2, &$warehouse, &$shop)
    {
        foreach ($all as $item) {
            if ($item->type == 0) {
                $find = WmsDataPermission::where('code', $item->org_code)->whereRaw('status=1 and type=0')->first();
                if (!$find) continue;
                if ($find->parent_code == '') {
                    $all2 = WmsDataPermission::whereRaw('type>0 and status=1')->selectRaw('code as org_code,type')->get();
                } else {
                    $id = $find->id;
                    // 找出组织的所有子组织
                    $all2 = WmsDataPermission::whereRaw('type>0 and status=1 and (path like ? or path like ?)', ['%_' . $id . '_%', $id . '_%'])->selectRaw('code as org_code,type')->get();
                }

                // if (!$org_codes) continue;
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
        $supplier = array_unique($supplier);
        $supplier2 = array_unique($supplier2);
        $warehouse = array_unique($warehouse);
        $shop = array_unique($shop);
    }


    static function getOrgOptions($params)
    {
        $info = WmsDataPermission::where('type', 0)->where('status', 1)->get()->keyBy('code')->toArray();
        $list = WmsDataPermission::where('type', 0)->where('status', 1)->get()->toArray();
        foreach ($list as &$item) {
            $item['parent_id'] = $info[$item['parent_code']]['id'] ?? 0;
        }
        return getTreeArray($list);
    }

    function search($params, $export = false)
    {
        $model = new WmsDataPermission();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            if ($params['parent_id'] ?? '') {
                $p = WmsDataPermission::find($params['parent_id']);
                $model = $model->where('parent_code', $p->code);
            }
            return $model->with('parent')->orderBy('type')->orderBy('id', 'desc');
        });
        foreach ($list['data'] as &$item) {
            $item['parent_name'] = $item['parent']['name'] ?? '';
            unset($item['parent']);
        }
        return $list;
    }

    function getOrgInfo($params)
    {
        $org = WmsDataPermission::where('id', $params['id'])->first();
        $users = WmsDataPermission::authorizeUsers($org);
        if ($org) $org = $org->toArray();
        $org_options = self::getOrgOptions([]);
        $parent = WmsDataPermission::where('code', $org['parent_code'])->first();
        $org['parent_id'] = $parent ? $parent->id : 0;
        return [
            'info' => $org,
            'options' => $org_options,
            'users' => $users,
        ];
    }

    function users($params)
    {
        $size = $params['size'] ?? 100;
        $cur_page = $params['cur_page'] ?? 1;
        $model = AdminUsers::where('status', 1);
        $model = $this->commonWhere($params, $model);
        $users = $model->selectRaw('id,user_code,username,name,"" as user_type')->orderBy('id', 'desc')
            ->paginate($size, ['*'], 'page', $cur_page);
        return $users;
    }

    // 新增组织
    function addOrg($params)
    {
        $p = WmsDataPermission::where('id', $params['parent_id'])->first();
        $find = WmsDataPermission::where('type', 0)->where('name', $params['name'])->first();
        if ($find) {
            $this->setErrorMsg(__('tips.name_repeat'));
            return false;
        }
        try {
            DB::beginTransaction();
            $org = WmsDataPermission::create([
                'code' => WmsDataPermission::code(),
                'type' => WmsDataPermission::ORG,
                'tenant_id' => ADMIN_INFO['tenant_id'],
                'name' => $params['name'],
                'parent_code' => $p->code,
                'type' => 0,
                'status' => 1,
                'created_user' => ADMIN_INFO['user_id'],
                'remark' => $params['remark'] ?? '',
            ]);
            $org->update(['path' => sprintf('%s_%s', $p->path, $org->id)]);
            // 授权用户
            $user_codes = [];
            if ($params['user_ids'] ?? []) {
                $user_codes = AdminUsers::whereIN('id', $params['user_ids'])->pluck('user_code');
                if ($user_codes) $user_codes = $user_codes->toArray();
            }

            if ($user_codes) {
                $this->authorize([
                    'org_codes' => [$org->code],
                    'user_codes' => $user_codes,
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                    'user_id' => ADMIN_INFO['user_id'],
                ]);
            }
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }

        return true;
    }

    // 更新组织
    function saveOrg($params)
    {
        $p = WmsDataPermission::where('id', $params['parent_id'])->first();
        $params['parent_code'] = $p->code;
        $find = WmsDataPermission::where('type', 0)->where('name', $params['name'])->where('id', '<>', $params['id'])->first();
        if ($find) {
            $this->setErrorMsg(__('tips.name_repeat'));
            return false;
        }
        $org = WmsDataPermission::where('id', $params['id'])->whereIn('type', [0, 1, 2, 3, 4])->first();
        if (!$org) {
            $this->setErrorMsg(__('tips.no_permission_edit'));
            return false;
        }

        try {
            DB::beginTransaction();
            $data = self::filterEmptyData($params, ['name', 'parent_code', 'remark']);
            $data = array_merge($data, [
                'updated_user' => ADMIN_INFO['user_id'], 'path' => sprintf('%s_%s', $p->path, $org->id)
            ]);
            $org->update($data);

            $user_codes = [];
            if ($params['user_ids'] ?? []) {
                $user_codes = AdminUsers::whereIN('id', $params['user_ids'])->pluck('user_code');
                if ($user_codes) $user_codes = $user_codes->toArray();
            }

            $now_users = WmsUserDataPermission::where(['org_code' => $org->code])->pluck('user_code');
            if ($now_users) $now_users = $now_users->toArray();
            // 要删除的用户
            $del_arr = array_diff($now_users, $user_codes);
            // 要新增的用户
            $add_arr = array_diff($user_codes, $now_users);
            if ($del_arr) WmsUserDataPermission::where('org_code', $org->code)->whereIn('user_code', $del_arr)->delete();
            foreach ($add_arr as $user_code) {
                WmsUserDataPermission::create([
                    'type' => $org->type,
                    'user_code' => $user_code,
                    'org_code' => $org->code,
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                    'created_user' => ADMIN_INFO['user_id'],
                ]);
            }
            foreach ($user_codes as $user_code) {
                Redis::hdel(RedisKey::wmsPermission(ADMIN_INFO['tenant_id']), $user_code);
            }
            foreach ($del_arr as $user_code) {
                Redis::hdel(RedisKey::wmsPermission(ADMIN_INFO['tenant_id']), $user_code);
            }
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }

        return true;
    }

    // 授权用户
    function authorize($params)
    {
        $orgs = WmsDataPermission::whereIn('id', $params['org_ids'])->get();
        $users = AdminUsers::whereIn('id', $params['user_ids'])->get()->keyBy('id')->toArray();
        foreach ($orgs as $org) {
            foreach ($users as $user) {
                $user_code = $user['user_code'] ?? '';
                if (!$user_code) continue;
                WmsUserDataPermission::firstOrCreate([
                    'user_code' => $user_code,
                    'org_code' => $org->code,
                    'tenant_id' => $params['tenant_id'],
                ], [
                    'type' => $org->type,
                    'user_code' => $user_code,
                    'org_code' => $org->code,
                    'tenant_id' => $params['tenant_id'],
                    'created_user' => $params['user_id'],
                ]);
            }
        }

        // 删除用户权限缓存
        foreach ($users as $user) {
            $user_code = $user['user_code'] ?? '';
            if (!$user_code) continue;
            Redis::hdel(RedisKey::wmsPermission($params['tenant_id']), $user_code);
        }
    }



    // 添加用户
    function orgAddUser($params)
    {
        $org = WmsDataPermission::where('id', $params['id'])->where('status', 1)->first();
        WmsUserDataPermission::create([
            'type' => $org->type,
            'user_code' => $params['user_code'],
            'org_code' => $org->code,
            'tenant_id' => ADMIN_INFO['tenant_id'],
            'created_user' => ADMIN_INFO['user_id'],
        ]);
    }

    // 删除用户
    function orgDelUser($params)
    {
        WmsUserDataPermission::where([
            'user_code' => $params['user_code'],
            'org_code' => $params['org_code'],
            'tenant_id' => ADMIN_INFO['tenant_id'],
        ])->delete();
    }

    // 删除组织
    function delOrgs($params)
    {
        $orgs = WmsDataPermission::whereIn('id', $params['ids'])->where(['status' => 1, 'type' => 0])->get();
        $err = [];
        foreach ($orgs as $org) {
            $find = WmsDataPermission::where('parent_code', $org->code)->where('status', 1)->first();
            if ($find) {
                $err[] = $org->id;
                continue;
            }
            $org->delete();
        }
        if (!$err) return true;
        $this->setErrorMsg(sprintf('%s' . __('tips.option_fail'), implode(',', $err)));
        return false;
    }

    // 更新状态
    function statusUpdate($params)
    {
        WmsDataPermission::whereIn('id', $params['ids'])->update([
            'status' => $params['status'],
            'updated_user' => ADMIN_INFO['user_id']
        ]);
        return true;
    }
    // 更新授权
    function authDel($params)
    {
        if (!is_array($params['ids'])) $params['ids'] = explode(',', $params['ids']);
        $item = WmsUserDataPermission::whereIn('id', $params['ids']);
        foreach ($item->get()->toArray() as $user) {
            $user_code = $user['user_code'] ?? '';
            if (!$user_code) continue;
            Redis::hdel(RedisKey::wmsPermission($user['tenant_id']), $user_code);
        }
        $row = $item->delete();
        if (!$row) {
            $this->setErrorMsg('');
            return false;
        }
        return true;
    }
}
