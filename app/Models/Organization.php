<?php

namespace App\Models;

use App\Models\Admin\V2\WmsDataPermission;
use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Organization extends wmsBaseModel
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'wms_organization';

    protected $guarded = [];

    protected $with = ['parent:id,name,org_code'];

    public function parent()
    {
        return $this->belongsTo(Organization::class, 'parent_id');
    }
    public  function BaseCreate($CreateData = [])
    {
        if (empty($CreateData)) return false;
        // if(!empty($CreateData['parent_id'])){
        //     $org_code = DB::table('admin_users')->where('tenant_id',$CreateData['tenant_id'])->where('is_tenant',1)->value('org_code');
        //     $pid =  DB::table('wms_organization')->where('org_code',$org_code)->value('id');
        //     $CreateData['parent_id']=$pid;
        // }
        // $id = DB::table($this->table)->insertGetId($CreateData);


        //在组织上新增租户id,同一个组织同一个tenant_id ,可以有多个租户管理员
        $tenant_id = request()->header('tenant_id');
        if ($tenant_id) {
            //租户新增的组织tenant_id 延用租户id
            $CreateData['tenant_id'] = $tenant_id;
            if (empty($CreateData['parent_id'])) {
                $pid = $this->where('tenant_id', $tenant_id)->where('parent_id', 0)->where('status', 1)->first();
                if ($pid) $CreateData['parent_id'] = $pid->id;
            }
        } else {
            //平台管理员新增组织

            //存在上级
            if (!empty($CreateData['parent_id'])) {
                $parent = $this->where('status', 1)->find($CreateData['parent_id']);
                if ($parent) $CreateData['tenant_id'] = $parent->tenant_id;
                else {
                    //传入的父级不存在或已禁用
                    $CreateData['parent_id'] = 0;
                }
            }
            if (empty($CreateData['tenant_id'])) {
                while (1) {
                    $tenant_id = $this->getErpCode('', 6, false);
                    $CreateData['tenant_id'] = $tenant_id;
                    $tenant_log = DB::table('wms_tenant_id_log')->where('tenant_id', $tenant_id)->first();
                    if (!$tenant_log) break;
                }
                //写入记录
                DB::table('wms_tenant_id_log')->insert([
                    'org_code' => $CreateData['org_code'],
                    'tenant_id' => $CreateData['tenant_id'],
                    'created_user' => request()->header('user_id'),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
        $id = $this::insertGetId($CreateData);
        if (empty($id)) return false;

        if(!($CreateData['parent_id']??0)) WmsDataPermission::orgInit($tenant_id,$CreateData['name']);
        return $id;
    }

    static function code()
    {
        return 'SJ' . date('ymdHis') . rand(1000, 9999);
    }
}
