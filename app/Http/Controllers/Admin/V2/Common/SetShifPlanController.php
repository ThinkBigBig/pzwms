<?php
    
namespace App\Http\Controllers\Admin\V2\Common;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SetShifPlanController extends BaseController
{
   
    protected $BaseModels = '\App\Models\Admin\V2\SetShifPlan';
    protected $BaseCreateVat = [
        'model' => 'required',
        'name' => 'required',

    ];//新增验证
    protected $BaseCreate =[
        'model'=>'',
        'name'=>'',
        'where_json'=>'',
        'show_json'=>'',
        'order_json'=>'',
        'with_start'=>'',
        'open'=>'',
        'created_at' => ['type','date'],
    ];//新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
        'model' => 'required',
    ];//新增验证
    protected $BaseUpdate =[
        'model'=>'',
        'name'=>'',
        'where_json'=>'',
        'show_json'=>'',
        'order_json'=>'',
        'with_start'=>'',
        'open'=>'',
        'updated_at' => ['type','date'],
    ];//新增数据
    protected $BUWhere= ['id'=>['=','']];//新增数据
    //数据重复检查
    protected $repeat = ['name'];

    //列表所需
    protected $BaseLVat = [];//获取分页全部验证
    protected $BLWhere = [];
    protected $BL = ['*'];
    protected $BLOrder  = [['created_at', 'desc'],['id','desc']];//获取全部分页字段排序
    protected $modelMap=[

        'productBrands' =>'App\Models\Admin\V2\ProductBrandsTenant',
        'productCategory'=>'App\Models\Admin\V2\ProductCategory',
        'product'=>'App\Models\Admin\V2\Product',
        'sperAndBar'=>'App\Models\Admin\V2\ProductSpecAndBar' ,
 
        'location'=>'App\Models\Admin\V2\WarehouseLocation',
        'warehouse'=>'App\Models\Admin\V2\Warehouse',
 
     ];


    //设置筛选
    public function _createFrom($create_data){
        if(!in_array($create_data['model'],array_keys($this->modelMap)))return  $this->vdtError('模型不存在');
        $order = json_decode(request()->get('ORDER',''),1);
        $where = json_decode(request()->get('WHERE',''),1);;
        $show = json_decode(request()->get('SHOW',''),1); ;
        if(empty($order)&&empty($where)&&empty($show))return $this->vdtError();
        $user_id = request()->header('user_id');
        $create_data['user_id'] = $user_id;
        // $model = (new $this->BaseModels())->getPlan($user_id,$create_data['model']);
        // if(!empty($model)) return $this->vdtError('筛选方案已存在');
        if(!empty($where))$create_data['where_json'] = json_encode($where);
        if(!empty($order))$create_data['order_json']=json_encode($order);
        if(!empty($show)) $create_data['show_json']=json_encode($show);

        return $create_data;

    }

    //修改筛选
    public function _updateFrom($update_data){
        if(!in_array($update_data['model'],array_keys($this->modelMap)))return  $this->vdtError('模型不存在');
        $order = json_decode(request()->get('ORDER',''),1);
        $where = json_decode(request()->get('WHERE',''),1);
        $show = json_decode(request()->get('SHOW',''),1);
        $user_id = request()->header('user_id');
        $update_data['user_id'] = $user_id;
        if(!empty($where))$update_data['where_json'] = json_encode($where);
        if(!empty($order))$update_data['order_json']=json_encode($order);
        if(!empty($show)) $update_data['show_json']=json_encode($show);
        return $update_data;
    }

    

    //获取筛选
    public  function getSiftPlan(Request $request,$model){
        $user_id = $request->header('user_id');
        if(empty($this->modelMap[$model]))return false;
        $model = $this->modelMap[$model];
        $plans = (new $this->BaseModels())->getPlan($user_id,$model);
        if(empty($plans)||$plans['with_start'] ==0)return false;
        if(!empty($plans['where_json']))$siftPlan['WHERE']=$plans['where_json'];
        if(!empty($plans['show_json']))$siftPlan['SHOW']=json_decode($plans['show_json'],1);
        if(!empty($plans['order_json']))$siftPlan['ORDER']=json_decode($plans['order_json']);
        if(!empty($siftPlan))return $siftPlan;

    }

    //删除筛选
    public function BaseDelete(Request $request)
    {
        $model = $request->get('model');
        $id = $request->get('id');
        if(empty($model) ||empty($id))return $this->vdtError();
        if(!in_array($model,array_keys($this->modelMap)))return  $this->vdtError('模型不存在');
        $user_id = $request->header('user_id');
        $data = (new $this->BaseModels)->delPlan($id,$user_id,$model);
        if($data){
            $admin_id = $user_id;
             DB::table('admin_logs')->insert(['admin_id' => $admin_id,
            'log_url' => empty($data['s']) ? '':$data['s'],
            'log_ip'=> get_ip(),
            'log_info'=> '删除内容',
            'log_time' => date('Y-m-d H:i:s'),
            'log_info_details' => $model,
            ]);
            return $this->success();
        }else{
            return  $this->error();
        }
    }
    //筛选列表
    public function BaseLimit(Request $request){
        $model = $request->get('model');
        $user_id = $request->header('user_id');
        if(empty($this->modelMap[$model]))return $this->vdtError();
        $baseModel = new $this->BaseModels();
        $plans = $baseModel->getPlan($user_id,$model);
        $is_start = $baseModel->isStart($user_id,$model);
        $data['data']=$plans;
        $data['is_start']=$is_start ;
        return $this->success($data);
    }



}


