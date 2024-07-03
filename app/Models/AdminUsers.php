<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\Admin\wmsBaseModel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
// use Illuminate\Database\Eloquent\SoftDeletes;
use App\Http\Service\TenantCodeService;

class AdminUsers extends wmsBaseModel
{
    // use SoftDeletes;
    public $table = 'admin_users';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';


    public function listModel($where, $select = ['*'], $order = [['id', 'desc']], $cur_page = 1, $size = 10)
    {
        // $data = DB::table($this->table)->select($select);
        $data = $this::select($select);
        $data->leftJoin('admin_roles', 'admin_users.roles_id', '=', 'admin_roles.id');
        //处理条件
        foreach ($where as $v) {
            if (empty($v[1])) continue;
            if ($v[1] == 'in' || $v[1] == 'IN') $data->whereIn($v[0], $v[2]);
            if ($v[1] == 'like') $data->where($v[0], $v[1], '%' . $v[2] . '%');
            if ($v[1] == 'allLike') $data->whereRaw($v[0], $v[2]);
            if (in_array($v[1], $this->whereSymbol)) $data->where($v[0], $v[1], $v[2]);
        }
        //处理排序
        foreach ($order as $Ok => $Ov) {
            if (empty($Ov[0]) || empty($Ov[1])) continue;
            $data->orderBy($Ov[0], $Ov[1]);
        }
        $reData = $data->paginate($size, ['*'], 'page', $cur_page);
        return  objectToArray($reData);
    }

    //获取用id
    public static function getAdminUserID($username)
    {
        $item = self::where('username', $username)->first();
        if (empty($item)) return 0;
        return $item->id;
    }
    /**
     * 公共封装删除 支持删除多个id,
     *
     * @param [string] $ids 要删除的数据
     * @param [string] $name 要删除的字段
     * @return false|true
     */
    public function BaseDelete($ids, $name = 'id')
    {
        if (empty($ids)) return false;
        $id = explode(',', $ids);
        //无法删除自身
        if (in_array(request()->header('user_id'), $id)) return false;
        //租户成员无法删除租户管理员
        if (request()->header('tenant_id')) {
            $data = $this::whereIn($name, $id)->where('p_id', '<>', 0)->delete();
        } else {
            // $data = DB::table($this->table)->whereIn($name,$id)->delete();
            $data = $this::whereIn($name, $id)->delete();
        }
        if (empty($data)  || $data == false || $data == NULL) return false;
        return $data;
    }

    /**
     * 导入数据
     *
     * @return array
     */
    public function import($data)
    {
        set_time_limit(0);
        $createData = [];
        $ks = 0;
        unset($data[0], $data[1]);
        $failData = (new self)::whereIn('username', array_column($data, '0'))->pluck('username')->toArray();
        foreach ($data as $k => $row) {
            $createDataSon = [
                'username'   =>  $row[0], //用户名
                'email'      => ('shendu' . $k . '@fit.com'), //邮件
                'name'       =>  $row[1], //用户名
                'password'   =>  Hash::make(123456), //密码
                'roles_id'   =>  20, //角色id
                'status'     => $row[4] == '启用' ? 1 : 2, //状态 1正常 2禁用'
            ];
            if (in_array($row[0], $failData)) continue;
            $createData[$ks] = $createDataSon;

            //            $createDataSon = [
            //                'username'   =>  $row[0],//用户名
            //                'email'      =>  $row[7]?:('shendu'.$k.'@fit.com'),//邮件
            //                'name'       =>  $row[3],//用户名
            //                'mobile'     =>  $row[8],//手机号码
            //                // 'sex'        =>  $row[3],//性别
            //                'password'   =>  Hash::make(123456),//密码
            //                'roles_id'   =>  20,//角色id
            //                'status'     => $row[9] == '启用' ? 1 : 2,//状态 1正常 2禁用'
            //            ];

            //            $where = [
            //                ['username','=', $createDataSon['username'] ],
            //            ];
            //            $ks = $k;
            //            $shipmentModel = (new self);
            //            $shipmentData  = $shipmentModel->BaseOne($where);//查表里是否已经被导入
            //            if(count($shipmentData)>0) {
            //                $failData[]= $createDataSon; continue;
            //            }else{
            //                $createData[$ks] =$createDataSon;
            //            }
        }
        $offset = 0;
        $limit = 100;
        while (true) {
            $arr = array_slice($createData, $offset, $limit);
            $offset += $limit;
            if (!$arr) break;
            (new self)->insert($arr);
        }

        return ['createData' => $createData, 'failData' => $failData];
    }

    // public  function BaseCreate($CreateData = [])
    // {
    //     if(empty($CreateData)) return false;
    //     // $id = DB::table($this->table)->insertGetId($CreateData);
    //     if(!empty($CreateData['tenant_id'] && $CreateData['tenant_id']=='wait_set')){
    //         $flag = true;
    //         unset($CreateData['tenant_id']);
    //     }
    //     DB::beginTransaction();
    //     $id = $this::insertGetId($CreateData);
    //     if(empty($id)) return false;
    //     if(!empty($flag)){
    //         $tenant_id = TenantCodeService::generateNumber($id,'',6);
    //         $row = $this::where('id',$id)->update(['tenant_id'=>$tenant_id]);
    //         if(empty($row)) return false;
    //     }
    //     DB::commit();
    //     return $id;
    // }

    public function BaseUpdate($where, $update)
    {
        if (empty($update)) return false;
        // $data = DB::table($this->table);
        $data = $this::select();
        // var_dump($where);exit;
        foreach ($where as $v) {
            if (empty($v[0]) || empty($v[1])) continue;
            if ($v[1] == 'in' || $v[1] == 'IN') $data->whereIn($v[0], $v[2]);
            if (in_array($v[1], $this->whereSymbol)) $data->where($v[0], $v[1], $v[2]);
        }
        $data = $data->first();
        //修改密码
        if (!empty($update['password'])) {
            if ($update['password'] != $data->password) {
                $update['password'] =  Hash::make($update['password']);
            } else {
                unset($update['password']);
            }
        }
        //如果修改了组织,修改租户id
        if(!empty($update['org_code']) && $update['org_code'] != $data->org_code ){
           $org = Organization::where('org_code',$update['org_code'])->where('status',1)->first();
           if($org)$update['tenant_id'] = $org->tenant_id;
           else unset($update['org_code']);
        }
        $reData = $data->update($update);
        if (empty($reData) || $reData == false || $reData == NULL) return false;
        if (method_exists($this, '_afterUpdate'))  $this->_afterUpdate($data, $update);
        return $reData;
    }
}
