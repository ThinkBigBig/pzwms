<?php
    
namespace App\Http\Controllers\Admin\V1\Auth;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Transformers\AuthorizationTransformer;
use Illuminate\Support\Facades\Hash;
use Dingo\Api\Exception\StoreResourceFailedException;
use Illuminate\Support\Facades\DB;
use App\Models\Roles;
use App\Models\RoleMenu;
use App\Models\AdminUsers;
use App\Exports\Export;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\AdminUsers as AdminUsersImports;
use Illuminate\Support\Facades\Redis;

class UserController extends BaseController
{
    protected $BaseModels = 'App\Models\AdminUsers';
    protected $BaseAllVat = [];//获取全部验证
    protected $BAWhere  = [];//获取全部Where条件
    protected $BA  = ['*'];//获取全部选取字段 *是全部
    protected $BAOrder  = [['id','desc']];//获取全部字段排序

    protected $BaseLVat = [];//获取分页全部验证
    protected $BLWhere  = [
        'p_id' => ['=',''],
        'username' => ['like',''],
        'admin_users.name' => ['like',''],
        'email' => ['like',''],
        'mobile' => ['like',''],
        'status' => ['=',''],
        'config_id' => ['=','']
    ];//获取全部分页Where条件
    protected $BL  = ['admin_users.*','admin_roles.type','admin_roles.name as display_name'];//获取全部分页选取字段 *是全部
    protected $BLOrder  = [['id','desc']];//获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',];//单个处理验证
    protected $BOWhere = ['id'=>['=','']];//单个查询验证
    protected $BO = ['*'];//单个选取字段；*是全部
    protected $BOOrder = [['id','desc']];//单个选取字段排序

    protected $BaseCreateVat = [
        'username' => 'required',//名称
        'password' => 'required',//密码
        'email' => 'required',//邮箱
        'avatar' => 'required',//头像
        'roles_id' => 'required',//角色
        'org_code' => 'required',//所属组织编码
    ];//新增验证

    protected $BaseCreate =[
        'username' => '', 'password' => ['type', 'password'], 'email' => '', 'mobile' => '', 'name' => '',
        'sex'=>'','avatar'=>'','roles_id'=>'','p_id'=>'','rolesList'=>'',
        'status'=>'','name','is_tenant'=>'','tenant_code'=> '','org_code'=>'','user_code'=> '',
        'created_at' => ['type','date'],
    ];//新增数据

    protected $BaseUpdateVat = ['id' => 'required',];//新增验证
    protected $BaseUpdate =[
        'id' => '', 'username' => '', 'email' => '', 'mobile' => '', 'name' => '',
        'password'=> ['type','password'],'p_id'=>'','config_id'=>'',
        'sex'=>'','avatar'=>'','roles_id'=>'','rolesList'=>'','status'=>'','name',
        'org_code'=>'', 'updated_at' => ['type','date'],
    ];//新增数据
    protected $BUWhere= ['id'=>['=','']];//新增数据


    protected $exportField =['id'=>'ID','username'=>'名称','email'=>'邮箱','password'=>'Miami'];
    /**
     *基础取多个值带分页方法
     *
     * @param Request $request
     * @return void
     */
    public function BaseLimit(Request $request){
        $where_data= [];
        $id = Auth::id();
        $roles_id = (new $this->BaseModels)->nameValue($id,'roles_id');
        $type = (new Roles)->nameValue($roles_id,'type');
        if($type  == 2){
            $where_data[] = ['p_id','=',$id];
            // var_dump($where_data['type']);exit;
        }
        if($type  == 3){
        //    return $this->permissionError();
        }
        $where_data[] = ['admin_users.tenant_id', '=', ADMIN_INFO['tenant_id']];

        $validator = Validator::make($request->all(), $this->BaseLVat);
        if ($validator->fails()) return $this->errorBadRequest($validator->messages);
        $data = $request->all();
        $cur_page= !empty($data['cur_page'])? $data['cur_page']: 1;
        $size= !empty($data['size'])? $data['size']: 10;
        if(!empty($data['name'])) {
            $data['admin_users.name'] = $data['name'];
            unset($data['name']);
        }
        //修改参数  request要存在或者BUWhere存在
        foreach($this->BLWhere as $B_k => $B_v){
            // var_dump($B_k[1]);exit;
            if(!empty($B_v) && empty($B_v[1]) && isset($data[$B_k])){
                if($B_v[0] == 'allLike')
                {
                    $where_data[] = ["concat({$B_v[2]}) like ?",$B_v[0],["%{$data[$B_k]}%"]];
                    continue;
                }
                $where_data[] = [$B_k,$B_v[0],$data[$B_k]];
                continue;
            }
            if(!empty($B_v) && (!empty($B_v[1]) || $B_v[1] ===0)){
                $where_data[] = [$B_k,$B_v[0],$B_v[1]];
                continue;
            }
        }
        $RData = (new $this->BaseModels)->listModel($where_data,$this->BL,$this->BLOrder,$cur_page,$size);
        $RoleMenuModel = (new RoleMenu);
        if(!empty($RData['data'])){
            foreach($RData['data'] as $k => &$v)
            {
                $v['avatar'] = cdnurl($v['avatar']);
                if($v['type'] == 1){
                    // $v['rolesList']  = ;
                }
                if($v['type'] == 2){
                    $role_id = $RoleMenuModel::where('role_id','=',$v['roles_id'])->pluck('menu_id');
                    if(count($role_id) >0){
                        // value($role_id);exit;
                        $role_array = [];
                        foreach($role_id as &$s_v){
                            $role_array[] = (int)$s_v;
                        }
                        // var_dump($role_array);exit;
                        $v['rolesList']  = $role_array;
                    }else{
                        $v['rolesList'] = [];
                    }
                }
                if($v['type'] == 3){
                    if(empty($v['rolesList']) || $v['rolesList'] ==null){
                        $v['rolesList']  = [];
                    }else{
                        $v['rolesList'] =explode(',',$v['rolesList']);
                        $role_array = [];
                        foreach( $v['rolesList'] as &$s_v){
                            $role_array[] = (int)$s_v;
                        }
                        $v['rolesList']  = $role_array;
                    }
                }
            }
        }
        return  $this->success($RData,__('base.success'));
    }

    /**
     *基础取单个值方法
     *
     * @param Request $request
     * @return void
     */
    public function BaseOne(Request $request)
    {
        $where_data= [];
        $validator = Validator::make($request->all(), $this->BaseOneVat);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        //修改参数  request要存在或者BUWhere存在
        // var_dump($this->BOWhere);exit;
        foreach($this->BOWhere as $B_k => $B_v){
            if(!empty($data[$B_k]) && empty($B_v[1])){
                $where_data[] = [$B_k,$B_v[0],$data[$B_k]];
            }
            if(!empty($B_v) && !empty($B_v[1])){
                $where_data[] = [$B_k,$B_v[0],$B_v[1]];
            }
        }
        $rolesModel = (new Roles);
        $RData = (new $this->BaseModels)->BaseOne($where_data,$this->BO,$this->BOOrder);
        if(!empty($RData) && $RData !='' && $RData !=NULL){
            $name = '';

            if(!empty($RData->roles_id)){
              $name = $rolesModel::where('id','=',$RData->roles_id)->value('display_name');
            }
            $RData->display_name = $name;
        }
        return  $this->success($RData,__('base.success'));
    }

    /**
     *基础修改方法
     *
     * @param Request $request
     * @return void
     */
    public function BaseUpdate(Request $request)
    {
        $where_data= [];
        $update_data = [];
        $validator = Validator::make($request->all(), $this->BaseUpdateVat);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        if(!empty( $data['username'])){
            $userStatus = AdminUsers::where('username','=',$data['username'])->
            where('id','!=',$data['id'])->first();
            if(!empty($userStatus) && $userStatus !=NULL) return  $this->error(__('base.userCreate'));
        }

        if(!empty( $data['email'])){
            $userStatus = AdminUsers::where('email','=',$data['email'])->
            where('id','!=',$data['id'])->first();
            if(!empty($userStatus) && $userStatus !=NULL) return  $this->error(__('base.userCreateEmail'));
        }

        //根据配置传入参数
        foreach($this->BaseUpdate as $k => $v){
            if(!empty($data[$k])){
                $update_data[$k] = $data[$k];
            }
            if(!empty($v) && empty($v[1])){
                $update_data[$k] = $v;
            }
            if(!empty($v[0]) && !empty($v[1]) && $v[0]== 'type' && $v[1]== 'time'){
                $update_data[$k] = time();
            }
            if(!empty($v[0]) && !empty($v[1]) && $v[0]== 'type' && $v[1]== 'date'){
                $update_data[$k] = date('Y-m-d H:i:s');
            }
            // if(!empty($v[0]) && !empty($v[1]) && $v[0]== 'type' && $v[1]== 'password'){
            //     $update_data[$k] =  Hash::make($update_data[$k]);
            // }
        }

        //修改参数  request要存在或者BUWhere存在
        foreach($this->BUWhere as $B_k => $B_v){
            if(!empty($data[$B_k]) && empty($B_v[1])){
                $where_data[] = [$B_k,$B_v[0],$data[$B_k]];
            }
            if(!empty($B_v) && !empty($B_v[1])){
                $where_data[] = [$B_k,$B_v[0],$B_v[1]];
            }
        }
        $RData = (new $this->BaseModels)->BaseUpdate($where_data,$update_data);
        Redis::del('user_map');
        Redis::del('user_name_map');
        if($RData)
            return  $this->success($data,__('base.success'));
        else
            return  $this->error();
    }

    /**
     *基础新增方法
     *
     * @param Request $request
     * @return void
     */
    public function BaseCreate(Request $request)
    {
        $create_data = [];
        $validator = Validator::make($request->all(), $this->BaseCreateVat);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        $userStatus = AdminUsers::where('username','=',$data['username'])->first();
        if(!empty($userStatus) && $userStatus !=NULL) return  $this->error(__('base.userCreate'));
        $userStatus = AdminUsers::where('email','=',$data['email'])->first();
        if(!empty($userStatus) && $userStatus !=NULL) return  $this->error(__('base.userCreateEmail'));
        //根据配置传入参数
        foreach($this->BaseCreate as $k => $v){
            if(!empty($data[$k])){
                $create_data[$k] = $data[$k];
            }
            if(!empty($v[0]) && !empty($v[1]) && $v[0]== 'type' && $v[1]== 'time'){
                $create_data[$k] = time();
            }
            if(!empty($v[0]) && !empty($v[1]) && $v[0]== 'type' && $v[1]== 'date'){
                $create_data[$k] = date('Y-m-d H:i:s');
            }
            if(!empty($v[0]) && !empty($v[1]) && $v[0]== 'type' && $v[1]== 'password'){
                $create_data[$k] =  Hash::make($create_data[$k]);
            }
        }
        //增加租户
        if(empty($data['user_code'] )){
            $create_data['user_code']=$this::getErpCode('YH');
        }

        if(empty($request->header('tenant_id'))){
            // if(!empty($create_data['is_tenant']))$create_data['tenant_id']=$this->getErpCode('',6,false);
            // if(!empty($create_data['is_tenant']))$create_data['tenant_id']='wait_set';
            //管理员新增用户
            $org_item = DB::table('wms_organization')->where('org_code',$create_data['org_code'])->first();
            if(empty($org_item))return $this->error('组织编码不存在');
            $create_data['tenant_id']=$org_item->tenant_id;

        }else{
            // unset( $create_data['is_tenant']);
            $create_data['tenant_id']=$request->header('tenant_id');
        }

        // if(!$this->checkRepeat('user_code' ,$create_data['user_code']))return $this->vdtError('编码重复');
        $create_data['p_id']=$request->header('user_id');
        //修改参数  request要存在或者BUWhere存在
        $id = (new $this->BaseModels)->BaseCreate($create_data);
        Redis::del('user_map');
        Redis::del('user_name_map');
        if($id){
            $data['id'] = $id;
            return  $this->success($data,__('base.success'));
        }else{
            return  $this->error();
        }
    }

    /**
     * 导出
     * @param Request $request
     * @return array
     */
    public function export(Request $request)
    {
        // if(empty($request->ids) ) return $this->error(__('base.vdt'));
        $data = $request->all();
        $where=[];
        if(!empty($data['ids'])){
            $ids = explode(',',$data['ids']);
            $where = [['id','in',$ids ]];
        }
        $exportField =$this->exportField;
        // $ssss = [];
        // foreach($exportField as $k=>$v){
        //     $ssss[] = [
        //        'value' => $k,
        //        'label' => $v,
        //     ];
        // }
        // var_dump(json_encode($ssss,true));exit;
        $exportFieldRow=  $exportField;
        if(!empty($data['field'])){
            $exportFieldRow  = [];
            $field = explode(',',$data['field']);
            foreach($field as $field_v){
                $exportFieldRow[$field_v] = $exportField[$field_v];
            }
        }
        // $where = [['id','in',$ids ]];
        $bondedAdopt  = (new  $this->BaseModels)->BaseAll($where);
        $headers[]  = $exportFieldRow;
        $data = [];
        foreach($bondedAdopt as $v){
            $row = [];
            foreach($exportFieldRow as $e_k=>$e_sv){
                $row[$e_k] = $v[$e_k];
                // var_dump($e_k);exit;
                // var_dump($v);exit;
            }
            $data[] = $row;
        }
        var_dump($headers);exit;
        $export = new Export($headers,$data);
        return Excel::download($export, date('YmdHis') . '.xlsx',);
    }

    /**
     * 导入
     * @param Request $request
     * @return array
     */
    public function import(Request $request)
    {
        $file = $request->file('file');
        try{
            $data  = Excel::toArray(new AdminUsersImports, $file);
            // var_dump( $data );exit;
            $reData = (new $this->BaseModels)->import($data[0]);
        } catch (\Exception $e) {
            return  $this->error('',$e->getMessage());
        }
        return  $this->success([
            'allCount'=> count($reData['createData'])+count($reData['failData']),
            'success' =>[
                'number' => count($reData['createData']),
                'data'   => $reData['createData'],
                // 'data'   => [],
            ],
            'fail' =>[
                'number' => count($reData['failData']),
                'data'   => $reData['failData'],
            ]
        ],__('base.success'));
    }


    public function UserInfo(Request $request){
        $fields = $request->get('fields','username,mobile');
        $field = explode(',',$fields);
        $data = (new $this->BaseModels)->select($field)->get()->toArray();
        return $this->success($data);

    }
}
