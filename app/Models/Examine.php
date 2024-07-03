<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;
use Illuminate\Support\Facades\DB;

class Examine extends BaseModel
{
    public $table = 'examine';

    // const CREATEDTIME = 'createtime';
    // const UPDATEDTIME = 'updatetime';

    /**
     * 公共封装 总查询
     *
     * @param [array] $where
     * @param array $select
     * @param array $order
     * @param integer $page
     * @param integer $size
     * @return array
     */
    public function Limit($where,$select=[],$order = [['id','desc']],$cur_page=1,$size=10)
    {
        $data = DB::table($this->table)
        ->leftJoin('examine','member.id = examine.uid')
        ->select('
        a.id,
        a.username,
        a.nickname,
        a.password,
        a.encrypt,
        a.point,
        a.amount,
        a.login,
        a.email,
        a.mobile,
        a.avatar,
        a.groupid,
        a.modelid,
        a.reg_ip,
        a.reg_time,
        a.last_login_ip,
        a.last_login_time,
        a.ischeck_email,
        a.ischeck_mobile,
        a.status,
        b.uid,
        b.createtime
        ');
        //处理条件
        foreach($where as $v){
            if(empty($v[1])) continue;
            if($v[1] == 'in' || $v[1] == 'IN' ) $data->whereIn($v[0],$v[2]);
            if(in_array($v[1],$this->whereSymbol)) $data->where($v[0],$v[1],$v[2]);
        }
        //处理排序
        foreach($order as $Ok=>$Ov){
            if(empty($Ov[0]) || empty($Ov[1])  ) continue;
            $data->orderBy($Ov[0],$Ov[1]);
        }
        $reData = $data->paginate($size,['*'],'page',$cur_page);
        var_dump(123);exit;
        return  objectToArray($reData);
    }
}
