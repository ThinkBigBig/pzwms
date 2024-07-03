<?php

namespace App\Models;


use App\Models\BaseModel;

class WarehouseLocation extends BaseModel
{
    public $table = 'warehouse_location';

    public static function  checkNumber($identifier){
       return self::where('identifier',$identifier)->get()->isNotEmpty();
    }

    public static function getUsableVolume($identifier){
        return self::where('identifier',$identifier)->value('usable_volume');
    }

    public function BaseDelete($ids,$name ='id')
    {
        if(empty($ids)) return false;
        $id = explode(',',$ids);
        $del_ids =self::whereIn($name,$id)->whereColumn('volume','=','usable_volume')->pluck('id')->toArray();
        $data['result']=['code'=>500,'msg'=> '该库位有商品存在,不能删除!'];
        if(empty($del_ids)) return $data;
        $not_del =  array_values(array_diff($id,$del_ids));
        if(empty($not_del)) $msg  = '删除成功';
        else $msg = implode(',',$not_del).'库位有商品存在,不能删除!';
        $rows = self::whereIn($name, $del_ids)->delete();
        $data['result'] = ['code'=> 200,'msg'=> $msg];
        $data['s'] = 'admin/locator/del';
        return $data;
    }
}
