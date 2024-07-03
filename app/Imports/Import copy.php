<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToArray;

class Import1 implements ToArray
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */

    protected $header;
    protected $db;
    protected $append;

    public function __construct($db,$header,$append){
        $this->db = $db;
        $this->header = array_keys($header);
        $this->append = $append;

    }
    public function array($rows)
    {
        
        dd($rows);
        //如果需要去除表头
        unset($rows[0]);
        unset($rows[1]);
        //$rows 是数组格式
        $this->createData($rows);
    }

    public function createData($rows)
    {
        //todo
        // $depend=false;
        // $dependData = [];
        // $dependMap=[];
        $uniq = $this->db->unique_field;
        $uniqData = [];
        $data = [];
        $conRelation = [];
        $default = [];
        foreach($rows as $num =>$row){
            // $status_index = array_search('status_txt',$this->header);
            // if( $status_index !== false)  $this->header[$status_index]='status';

            if(count($this->header) != count($row)){
                abort('500','表格数据有误！'); 
            }
            $tenant_id = request()->header('tenant_id');
            $res=array_combine($this->header,$row);
            // $res['created_at']=time();
            $res['created_at'] = date('Y-m-d H:i:s');
            if($tenant_id)$res['tenant_id']=$tenant_id;
            $relation = [];
            // foreach($res as $k=>$v){
            //     if (strpos($k, '.') !== false){
            //         $relation[$k] = $v;
            //         unset($res[$k]);
            //     }
            //     if(is_null($v))unset($res[$k]);
            // }
            dd($this->append);
            if($this->append){
                $dmd5 = md5(json_encode($relation));
                if(in_array($dmd5,array_keys($conRelation))){
                    foreach($conRelation[$dmd5] as $append ){
                        $res[$append['appendK']]=$append['appendV'];
                    }
                    
                }else{
                    foreach($this->append as $fild=>$item){
                       if(is_array($item)){
                            $appendV = $this->getAppend($relation,$item);
                            // dd($appendV);
                            if($appendV === null)continue;
                            if($appendV === false){
                                //依赖插入
                                abort('500','第'.($num+1).'行表格数据有依赖,请先插入依赖数据！'); 
                                // $depend = true;
                                // $dependData = array_pop($data);
                                // $ins = $this->db->insertGetId($dependData);
                                // if($ins){
                                //     $appendV = $ins;
                                //     $dependMap;
                                // }
                            }
                            $res[$fild]=$appendV;
                            $conRelation[$dmd5][]=['appendK'=>$fild ,'appendV'=>$appendV];
                       }else{
                            $default[$fild]=$item;
                          
                       }
                    }
                }
                if(!empty($default)){
                    foreach($default as $f=>$i){
                        $res[$f]=$res[$i];
                    }

                }

            }
            // $res = array_filter($res,function($field,$k){
            //     if (strpos($k, '.') === false)
            //     return !is_null($field);
            // },1);

            //修改器修改数据
            $setMap = $this->db->excelSetAttr();
            $mapKeys = array_keys($setMap);
            foreach($res as $col_n=>$col_v){
                if( substr($col_n,-4) == '_txt'){
                    $origin_n = substr($col_n,0,-4);
                    if($col_v !== null){
                        if(in_array($origin_n,$mapKeys)){
                            if(isset($setMap[$origin_n][$col_v])){
                                $res[$origin_n]=$setMap[$origin_n][$col_v];
                            }else abort('500','第'.$num.'数据有误');
                        }else{
                            $func_n = 'set'.ucfirst($origin_n);
                            if(method_exists($this->db,$func_n)){
                                $col_a =  $this->db->$func_n($col_v);
                                $res = array_merge($res,$col_a);

                            }

                        }
                    }else{
                        $res[$origin_n]=null;
                    }

                    unset($res[$col_n]);
                }
            }
    
            $data[]=$res;
            // dump($res);
            if($uniq){
                foreach($uniq as $uk){
                    if(!isset( $res[$uk]))abort('500','数据有误');
                    if(isset($uniqData[$uk])&& !empty($uniqData[$uk][$res[$uk]]))abort('500','第'.$num.'行数据有重复');
                    $uniqData[$uk][$res[$uk]]=$num+1;
                    
                }
            }

        }
        foreach($uniqData as $udk=>$udv){
            $noEmpty = $this->db->whereIn($udk,array_keys($udv))->pluck($udk);;
            $repList = [];
            foreach($noEmpty as $repeat){
                $line = $uniqData[$udk][$repeat];
                if(!in_array($line,$repList))$repList[] =$line;
            }
            if(!$noEmpty->isEmpty())abort(500,'第'.implode(',',$repList).'行数据有重复');
        }
        $insert = $this->db->insert($data);
        return $insert;
    }


    /**
     * 把下划线风格字段名转化为驼峰风格.
     *
     * @param  array  $array
     * @return array
     */
    public  function getAppend($relation,$append)
    {
        foreach($append as $item){
            $model = explode('.',$item);
            if(count($model)==3){
                $related_1 =  Str::camel($model[0]);
                $related_2 =  Str::camel($model[1]);
                $filed  = $model[2];
                $filedV = $relation[$item];
                $foreKey = $prevF = $this->db->$related_1()->getModel()->$related_2()->getForeignKeyName();
                $prev_V = $this->db->$related_1()->getModel()->$related_2()->getModel()->where($filed,$filedV)->value($foreKey );
            }
            if(count($model)==2){
                $related =  Str::camel($model[0]);
                $filed = $model[1];
                $filedV = $relation[$item];
                $foreKey = $this->db->$related()->getForeignKeyName();
                $ownerKey = $this->db->$related()->getOwnerKeyName();
                if($filedV === null) return ;
                if(empty($prevF)){
                    // dd($this->db->$related()->getModel()->where($filed,$filedV)->value($ownerKey));
                    $value = $this->db->$related()->getModel()->where($filed,$filedV)->value($ownerKey);
                    if($value===null)return false;
                    return $value ;
                } else {
                    $value = $this->db->$related()->getModel()->where($filed,$filedV)->where($prevF,$prev_V)->value($foreKey);
                    if($value===null)return false;
                    return $value ;
                }
            }
  
        }
    }
}
