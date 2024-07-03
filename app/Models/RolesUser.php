<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;

class RolesUser extends BaseModel
{
    public $table = 'admin_role_user';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * 获取目录
     *
     * @return void
     */
    public function menus($id = 0,$parent=0)
    {
       
        if($id == 0 || $id == 1){
            if($parent)$data =Menus::where('parent_id',0)->orderBy('order','asc')->orderBy('id','desc')->get()->toArray();
            else $data = Menus::orderBy('order','asc')->get()->toArray();
            // var_dump($data);exit;
        }else{
            $menuId = [];
            // $roleMenus = RolesUser::where('user_id','=',$id)->pluck('role_id');
            // var_dump($roleMenus);exit;
            // if(!empty($roleMenus) && $roleMenus != NULL) $menuId = RoleMenu::whereIn('role_id',$roleMenus)->pluck('menu_id');
            $menuId = RoleMenu::whereIn('role_id',[$id])->pluck('menu_id');
            if($parent)$data = Menus::where('parent_id',0)->whereIn('id',$menuId)->orderBy('order','asc')->orderBy('id','desc')->get();
            else$data = Menus::whereIn('id',$menuId)->orderBy('order','asc')->get();
            if(!empty($data) && $data != NULL)
                $data = $data->toArray();
            else
                $data =[];
        }
        // var_dump($data);exit;
        // $data = self::getTree($data);
        return $data;
    }

    /**
     * 引用形式
     *
     * @param [type] $arr
     * @return void
     */
    public function getTree($arr){
        $items = array();
        $tree  = array();
        foreach($arr as $key => $value){
            $items[$value['id']]=$value;
        }

        foreach($items as $k => $v){
            if(isset($items[$v['parent_id']])){
                $items[$v['parent_id']]['children'][] = &$items[$k];
            }else{
                $tree[] = &$items[$k];
            }
        }
        return $tree;
    }

    /**
     * 递归
     *
     * @param [type] $arr
     * @param integer $pid
     * @param integer $level
     * @return void
     */
    public function getTree2($arr,$pid=0,$level=0){
        static $list=[];
        foreach($arr as $key => $value){
            if($value['pid'] == $pid){
                $value['level']=$level;
                $list[]=$value;
                unset($arr[$key]);
                self::getTree2($arr,$value['id'],$level+1);
            }
        }
        return $list;
         // $handle_arr = self::getTree($array);
        // print_r($handle_arr);
    }
    
}
