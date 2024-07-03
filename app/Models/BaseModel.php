<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BaseModel extends Model
{
    //符号
    protected $whereSymbol = [
        '>',
        '>=',
        '<',
        '<=',
        '=',
        '!=',
    ];
    //返回判断
    protected $baseReturn = [
        false,
        null,
        Null,
        'null',
        'Null',
    ];

    //用于特殊where查询
    protected  $whereSymbolWhere = [
        'gt'            => '>',//大于
        'gte'           => '>=',//大于等于
        'lt'            => '<',//小于
        'lte'           => '<=',//小于等于
        'eq'            => '=',//等于
        'neq'           => '!=',//不等于

        'in'            => 'in',//包括
        'contain'       => 'like',//包含
        'leftContain'   => '!=',//左包含
        'rightContain'  => '!=',//右包含
        'isNotNull'     => ['!=','Null'],//不为空
        'isNull'        => ['=','Null'],//为空
        // 'dynamic'       => 
    ];

    /**
     *  公共封装 查询全部
     *
     * @param [array] $where
     * @param array $select
     * @param array $order
     * @return array
     */
    public function BaseAll($where,$select=['*'],$order = [['id','desc']])
    {
        $data = DB::table($this->table)->select($select);
        //处理条件
        foreach($where as $v){
            if(empty($v[1])) continue;
            if($v[1] == 'in' || $v[1] == 'IN' ){
                if(is_array($v[2])){
                    $data->whereIn($v[0],$v[2]);
                }else{
                    $in  = explode(',',$v[2]);
                    $data->whereIn($v[0], $in);
                }
            }
            if($v[1] == 'like') $data->where($v[0],$v[1],'%'.$v[2].'%');
            if($v[1] == 'allLike') $data->whereRaw($v[0],$v[2]);
            if($v[1] == 'WHERE') {
                $sql = $this->jsonWhere($v[2]);
                $data->where(function($q)use($sql){
                   return $q->whereRaw($sql);
                });
            }
            if(in_array($v[1],$this->whereSymbol)) $data->where($v[0],$v[1],$v[2]);
        }
        //处理排序
        foreach($order as $Ok=>$Ov){
            if(empty($Ov[0]) || empty($Ov[1])  ) continue;
            $data->orderBy($Ov[0],$Ov[1]);
        }
        $reData = $data->get();
        if(empty($reData) || $reData == NULL)
            return [];
        else
            return objectToArray($reData);
    }

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
    public function BaseLimit($where,$select=['*'],$order = [['id','desc']],$cur_page=1,$size=10)
    {
        $data = DB::table($this->table)->select($select);
        //处理条件
        foreach($where as $v){
            if(empty($v[1])) continue;
            if($v[1] == 'in' || $v[1] == 'IN' ){
                if(is_array($v[2])){
                    $data->whereIn($v[0],$v[2]);
                }else{
                    $in  = explode(',',$v[2]);
                    $data->whereIn($v[0], $in);
                }
            }
            if($v[1] == 'like') $data->where($v[0],$v[1],'%'.$v[2].'%');
            if($v[1] == 'allLike') $data->whereRaw($v[0],$v[2]);
            if($v[1] == 'WHERE') {
                $sql = $this->jsonWhere($v[2]);
                $data->where(function($q)use($sql){
                   return $q->whereRaw($sql);
                });
            }
            if(in_array($v[1],$this->whereSymbol)) $data->where($v[0],$v[1],$v[2]);
        }
        //处理排序
        foreach($order as $Ok=>$Ov){
            if(empty($Ov[0]) || empty($Ov[1])  ) continue;
            $data->orderBy($Ov[0],$Ov[1]);
        }
        // var_dump($data->tosql());
        $reData = $data->paginate($size,['*'],'page',$cur_page);
        return  objectToArray($reData);
    }

    public function addListWhere($where)
    {
        $query = $this->query();
        //处理条件
        foreach ($where as $v) {
            if (empty($v[1])) continue;
            if ($v[1] == 'in' || $v[1] == 'IN') {
                if (is_array($v[2])) {
                    $query->whereIn($v[0], $v[2]);
                } else {
                    $in  = explode(',', $v[2]);
                    $query->whereIn($v[0], $in);
                }
            }
            if ($v[1] == 'like' && $v[2]) $query->where($v[0], $v[1], '%' . $v[2] . '%');
            if ($v[1] == 'allLike') $query->whereRaw($v[0], $v[2]);
            if ($v[1] == 'WHERE') {
                $sql = $this->jsonWhere($v[2]);
                $query->where(function ($q) use ($sql) {
                    return $q->whereRaw($sql);
                });
            }
            if (in_array($v[1], $this->whereSymbol)) $query->where($v[0], $v[1], $v[2]);
        }
        return $query;
    }

    /**
     *  公共封装 单个查询
     *
     * @param [array] $where
     * @param array $select
     * @param array $order
     * @return array
     */
    public function BaseOne($where=[],$select=['*'],$order = [['id','desc']])
    {
        $data = DB::table($this->table)->select($select);
        //处理条件
        foreach($where as $v){
            if(empty($v[0]) || empty($v[1])  ) continue;
            if($v[1] == 'in' || $v[1] == 'IN' ) $data->whereIn($v[0],$v[2]);
            if($v[1] == 'like') $data->where($v[0],$v[1],'%'.$v[2].'%');
            if(in_array($v[1],$this->whereSymbol)) $data->where($v[0],$v[1],$v[2]);
        }
        //处理排序
        foreach($order as $Ok=>$Ov){
            if(empty($Ov[0]) || empty($Ov[1])  ) continue;
            $data->orderBy($Ov[0],$Ov[1]);
        }
        $reData = $data->first();
        if(empty($reData) || $reData == NULL)
            return [];
        else
            return objectToArray($reData);
    }

    /**
     * 公共封装 新增数据
     *
     * @param array $data
     * @return int|false
     */
    public  function BaseCreate($CreateData = [])
    {
        if(empty($CreateData)) return false;
        $id = DB::table($this->table)->insertGetId($CreateData);
        if(empty($id)) return false;
        return $id;
    }

    /**
     *  公共封装修改数据
     *
     * @param [array] $where 修改条件
     * @param [array] $update 修改数据
     * @return false|array
     */
    public function BaseUpdate($where,$update)
    {
        if(empty($update)) return false;
        $data = DB::table($this->table);
        // var_dump($where);exit;
        foreach($where as $v){
            if(empty($v[0]) || empty($v[1])  ) continue;
            if($v[1] == 'in' || $v[1] == 'IN' ) $data->whereIn($v[0],$v[2]);
            if(in_array($v[1],$this->whereSymbol)) $data->where($v[0],$v[1],$v[2]);
        }
        $reData = $data->update($update);
        if(empty($reData) || $reData== false || $reData== NULL) return false;
        return $reData;
    }

    /**
     * 公共封装删除 支持删除多个id,
     *
     * @param [string] $ids 要删除的数据
     * @param [string] $name 要删除的字段
     * @return false|true
     */
    public function BaseDelete($ids,$name ='id')
    {
        if(empty($ids)) return false;
        $id = explode(',',$ids);
        $data = DB::table($this->table)->whereIn($name,$id)->delete();
        if(empty($data)  || $data== false || $data== NULL) return false;
        return $data;
    }

    //批量更新
    public function updateBatch($multipleData = [],$id ='id')
    {

        try {
            if (empty($multipleData)) {
                throw new \Exception("数据不能为空");
            }
            $tableName = DB::getTablePrefix() . $this->getTable(); // 表名
            $firstRow  = current($multipleData);

            $updateColumn = array_keys($firstRow);
            // 默认以id为条件更新，如果没有ID则以第一个字段为条件

            if (empty($firstRow[$id])) {
                throw new \Exception("缺少必要字段");
            }
            $referenceColumn = $id;
            // $referenceColumn = isset($firstRow['id']) ? 'id' : current($updateColumn);
            // unset($updateColumn[0]);
            // 拼接sql语句
            $updateSql = "UPDATE " . $tableName . " SET ";
            $sets      = [];
            $bindings  = [];
            foreach ($updateColumn as $uColumn) {
                $setSql = "`" . $uColumn . "` = CASE ";
                foreach ($multipleData as $data) {
                    $setSql .= "WHEN `" . $referenceColumn . "` = ? THEN ? ";
                    $bindings[] = $data[$referenceColumn];
                    $bindings[] = $data[$uColumn];
                }
                $setSql .= "ELSE `" . $uColumn . "` END ";
                $sets[] = $setSql;
            }
            $updateSql .= implode(', ', $sets);
            $whereIn   = collect($multipleData)->pluck($referenceColumn)->values()->all();
            $bindings  = array_merge($bindings, $whereIn);
            $whereIn   = rtrim(str_repeat('?,', count($whereIn)), ',');
            $updateSql = rtrim($updateSql, ", ") . " WHERE `" . $referenceColumn . "` IN (" . $whereIn . ")";
            // var_dump($updateSql );
            // 传入预处理sql语句和对应绑定数据
            return DB::update($updateSql, $bindings);
        } catch (\Exception $e) {
            // var_dump($e->getMessage());
            return false;
        }
    }

    //
    public function nameValue($id,$name,$id_name ='id',$where ='=')
    {
        $value = DB::table($this->table)->where($id_name,$where,$id)->value($name);
        return $value;
    }

    //多条件整理sql
    public static function jsonWhere($where){
        $sql ="";
        $whereSymbolWhere = new self();
        $SWhere = $whereSymbolWhere->whereSymbolWhere;
        foreach($where as $k=>$s_v){
            if($k > 0){
                $sql .= $s_v['condition_status'];
                $sql .= " ";
            }
            $sql .= $s_v['name'];//字段名
            $sql .= " ";
            $sql .=  is_array($SWhere[$s_v['condition']]) ? $SWhere[$s_v['condition']][0]: $SWhere[$s_v['condition']];//条件
            $sql .=  is_int($s_v['value']) ? " " :" '";
            if(is_array($SWhere[$s_v['condition']])){
                $sql .= $SWhere[$s_v['condition']][1];
            }else{
                if($s_v['condition'] =='contain'){
                    $sql .= '%'.$s_v['value'].'%';//条件;
                }else{
                    $sql .= $s_v['value'];//条件;
                }
            }
            $sql .=  is_int($s_v['value']) ? " " : "' ";
            // var_dump($SWhere[$s_v['condition']]);
        }
        // var_dump($sql);exit;
        return $sql;
    }
}
