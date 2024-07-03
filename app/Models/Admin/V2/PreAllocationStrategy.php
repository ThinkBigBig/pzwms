<?php

namespace App\Models\Admin\V2;


use App\Models\Admin\wmsBaseModel;
use  App\Models\Admin\V2\ArrivalRegist;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;
use Illuminate\Support\Facades\DB;

class PreAllocationStrategy extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_pre_allocation_strategy';
    protected $map = [
        'status' => [0=>'未启用' ,1=>'已启用'],
        'type' => [1=>'库龄',2=>'库区',3=>'位置码'],

    ];

    protected $condition = [
            'order_type' => [1=>'销售出库单',2=>'调拨出库单',3=>'其他出库单'],
            // 'source_platform' => self::orderPlatform(),
            // 'source_channel' => [],
            // 'product_category' => [1=>'箱包',2=>'潮玩',3=>'',4=>''],
            ];
    protected $appends = ['status_txt','type_txt','users_txt',];


    public function searchUser(){
        return [
            'users_txt.create_user' => 'create_user_id',
            'users_txt.admin_user' => 'admin_user_id',
        ];
    }
    public function getUsersTxtAttribute($key){
        $tenant_id = request()->header('tenant_id');
        $res['create_user'] = $this->getAdminUser($this->create_user_id,$tenant_id);
        $res['admin_user'] = $this->getAdminUser($this->admin_user_id,$tenant_id);
        return $res;
    }

    public function getContentAttribute($value){
        return json_decode($value,1);
    }

    public function getConditionAttribute($value){

        if($value) return array_merge(...json_decode($value,1));
        return [
            'order_type' => [],
            'source_platform'=>[],
            'source_channel' => [],
            'product_category' =>[],
        ];
        // return [
        //     'order_type' => '全部',
        //     'source_platform'=>'全部',
        //     'source_channel' => '全部',
        //     'product_category' =>'全部',
        // ];
    }

    //校验策略条件
    public static function findStrategy($warehouse_code,$condition){
        $model = PreAllocationStrategy::where('warehouse_code',$warehouse_code)->where('status',1)->orderBy('sort','desc')->orderBy('created_at','desc');
        $item = $model->first();
        if(empty($item)) return false;
        $cond = is_string($item['condition'])? json_decode($item['condition'],1):$item['condition'];
        $content = is_string($item->content)? json_decode($item->content,1):$item->content;
        $data = ['content'=>$content,'type'=>$item->type,'startegy_code'=>$item->startegy_code];
        foreach($cond as $key=>$con){
            if(empty($con[1]))unset($cond[$key]);
        }
        if(empty($cond)) return [true,$data];
        // 'source_channel','source_platform','warehouse_code,order_type'
        foreach($condition as $k=>$v){
            if(empty($cond[$k]))continue;
            if($cond[$k][0] == 'in'){
                if(!in_array($v,$cond[$k][1])) return false;
            }
            if($cond[$k][0] == 'not in'){
                if(in_array($v,$cond[$k][1])) return false;
            }
        }
        if(!empty($cond['product_category'])) return [true,array_merge($data,['category'=>$cond['product_category']])];

        return [true,$data];

    }

    //执行策略
    protected static function toStrategy($content,$type,$startegy_code){
        $order = [];
        $where = [];
        if(!$content) return;
        if(in_array($type,[1,3])){
            $v = $content[0]['val'] ;
            if($v == 1)  $order[] = ['created_at','asc']; //先进先出
            if($v == 2)  $order[] = ['created_at','desc'];  //先进后出
            if($v == 3)  $order[] = ['location_code','asc'];  //优先清空位置码
            if($v == 4)  $order[] = ['pick_number','asc'];  //按拣货顺序清空位置码
            // $this->inventoryIns->orderBy('')
        }
        if($type == 2){
            foreach( $content as $v){
                $op = $v['operation'] == 'eq'?'=':'<>';
                $temp = [
                    'area_code',
                    $op,
                    trim($v['val'])
                ];
                if(!empty($where[$v['sort']]))$where[$v['sort'].'-']=$temp;
                else $where[$v['sort']]= $temp;
            }
            krsort($where);
        }
        return ['where'=>$where,'order'=>$order,'startegy_code'=>$startegy_code];
    }

    public function BaseUpdate($where, $update)
    {
        if(empty($update)) return false;
        // $data = DB::table($this->table);
        $data = $this::select();
        // var_dump($where);exit;
        foreach($where as $v){
            if(empty($v[0]) || empty($v[1])  ) continue;
            if($v[1] == 'in' || $v[1] == 'IN' ) $data->whereIn($v[0],$v[2]);
            if(in_array($v[1],$this->whereSymbol)) $data->where($v[0],$v[1],$v[2]);
        }
        $code = $data->pluck('startegy_code')->first();
        $reData = $data->update($update);
        if(empty($reData) || $reData== false || $reData== NULL) return false;
        if(method_exists($this,'_afterUpdate'))  $this->_afterUpdate($data,$update);

        WmsOptionLog::add(WmsOptionLog::PHCL,$code,'修改','配货策略修改',$update);

        return $reData;
    }

}
