<?php
    
namespace App\Http\Controllers\Admin\V1\Auth;

use App\Http\Controllers\Admin\V1\BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\AdminAuthorization;
use App\Transformers\AuthorizationTransformer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Dingo\Api\Exception\StoreResourceFailedException;
use App\Models\AdminUser;
use App\Models\AdminUsers;
use App\Models\RolesUser;
use App\Models\Roles;
use App\Models\Menus;
use App\Models\RoleMenu;
use App\Models\UserLoginLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
// use Auth;

class AuthController extends BaseController
{
    protected $BaseModels = 'App\Models\Test';
    protected $BaseAllVat = [];//获取全部验证
    protected $BAWhere  = [];//获取全部Where条件
    protected $BA  = ['*'];//获取全部选取字段 *是全部
    protected $BAOrder  = [['id','desc']];//获取全部字段排序

    protected $BaseLVat = [];//获取分页全部验证
    protected $BLWhere  = [];//获取全部分页Where条件
    protected $BL  = ['*'];//获取全部分页选取字段 *是全部
    protected $BLOrder  = [['id','desc']];//获取全部分页字段排序

    protected $BaseOneVat = [];//单个处理验证
    protected $BOWhere = ['id'=>['=','']];//单个查询验证
    protected $BO = ['*'];//单个选取字段；*是全部
    protected $BOOrder = [['id','desc']];//单个选取字段排序

    protected $BaseCreateVat = [];//新增验证
    protected $BaseCreate =[
        'name'=>'','password'=>'','email'=>'',
        'created_at' => ['type','date'],
    ];//新增数据

    protected $BaseUpdateVat = [];//新增验证
    protected $BaseUpdate =[
        'id'=>'','name'=>'','password'=>'','email'=>'',
        'created_at' => ['type','date'],
    ];//新增数据
    protected $BUWhere= ['id'=>['=','']];//新增数据


    protected function validator(array $data) {
        return Validator::make($data, [
            'username' => 'required',
            // 'phone' => 'required|unique:users',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|min:6',
        ]);
    }

    // 登录接口
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(),
            [
                'username' => 'required',
                'password' => 'required|min:6|max:25',
            ],
            [
                'username.required' => __('admin.auth.login.username.required'),
                'password.required' =>__('admin.auth.login.password.required'),
                'password.min' =>__('admin.auth.login.password.min'),
                'password.max' =>__('admin.auth.login.password.max')
            ]
        );
        if ($validator->fails()) {
            return $this->errorBadRequest($validator);
        }

        $select =[
            'id',
            'username',
            'email',
            'mobile',
            'sex',
            'password',
            'avatar',
            'created_at',
            'updated_at',
            'roles_id',
            'p_id',
            'status',
            'rolesList',
            'remember_token'
        ];
        $user = AdminUser::select($select)
        ->where('username', $request->username)
        ->where('status', '=',1)
        ->first();
        if ($user && Hash::check($request->get('password'), $user->password)){

            $token = Auth::fromUser($user);
            if($token !="" || $token !=null) {
                $authorization = new AdminAuthorization($token);
                $authorization_array = $authorization->toArray();
                $user->remember_token = $authorization_array['token'];
                $user->save();
                $user['type'] = (new Roles)->nameValue($user->roles_id,'type');
                // if(empty($user['type']) && $user['type'] ==2){
                // }
                // $user['rolesList']  = explode(',',$user['rolesList']);
                //清除token
                $token_rides_name =  $user->id.'-token_rides_name';
                $token = Redis::del($token_rides_name);
                $user->avatar =   cdnurl($user->avatar);
                
                UserLoginLog::create([
                    'type' => 0,
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                    'user_id' => $user->id,
                    'info' => ['token' => $user->remember_token]
                ]);
                return $this->success(['token' => $authorization_array, 'user' => $user]);
            }
            return $this->error(trans('auth.incorrect'));
        }
        return $this->responseError(__('auth.failed'));
    }

    //刷新token接口 (一个 token 只能刷新一次 ,并且需要在 token 的过期时间内进行刷新） 
    public function update()
    {
        $authorization = new AdminAuthorization(Auth::refresh());
        return $this->success(['token' => $authorization->toArray(), 'user' => $authorization->user()]);
    }

    //权限列表
    public function getMenus_old()
    {
        $AdminUsersModel = (new AdminUsers);
        $RolesUserModel = (new RolesUser);
        $Menus = (new Menus);
        $id = Auth::id();
        $info  = $AdminUsersModel->BaseOne([['id','=',$id]],['*'],[]);
        $Roles = $RolesUserModel->BaseOne([['user_id','=',$info['roles_id']]],['*'],[]);
        $parent = request()->get('is_parent');
        //缺验证
        if(!empty($info['rolesList']))
        {

        }
        // if(!empty($Roles) && $Roles != NULL && empty($Roles['rolesList'])){
            // var_dump($info['roles_id']);exit;
            $menus = $RolesUserModel->menus($info['roles_id'],$parent);
        // }else{
        //
        //    $menus = $RolesUserModel->menus();
        // }
        $roles_id = $AdminUsersModel->nameValue($id,'roles_id');
        $type = (new Roles)->nameValue($info['roles_id'],'type');
        if(empty($user['type']) && $type ==3 && $info['rolesList'] !=NULL)
        {
            // var_dump($info['rolesList'] );exit;
            $info['rolesList'] = explode(',',$info['rolesList']);
            $menus = $Menus->BaseAll([['id','in',$info['rolesList']]]);
            // var_dump( $menus );
        }
        $data = [
            'menus'         => $menus,
            'roles'         => ['TEST'],
            'icon'          => cdnurl($info['avatar']),
            'username'      => $info['username'],
            'type'          => $type,
        ];
        return $this->success($data,__('base.success'));
    }


    //权限列表
    public function getMenus()
    {
        $AdminUsersModel = (new AdminUsers);
        $RolesUserModel = (new RolesUser);
        $Menus = (new Menus);
        $id = Auth::id();
        $info  = $AdminUsersModel->BaseOne([['id','=',$id]],['*'],[]);
        // $Roles = $RolesUserModel->BaseOne([['user_id','=',$info['roles_id']]],['*'],[]);
        $parent = request()->get('is_parent');
        //缺验证
        if(!empty($info['rolesList']))
        {

        }
        // if(!empty($Roles) && $Roles != NULL && empty($Roles['rolesList'])){
            // var_dump($info['roles_id']);exit;
            // $menus = $RolesUserModel->menus($info['roles_id'],$parent);
        // }else{
        //
        //    $menus = $RolesUserModel->menus();
        // }
        $roles_id = $AdminUsersModel->nameValue($id,'roles_id');
        $model = (new Menus())->orderBy('id','desc');
        if(!empty(request()->header('tenant_id'))){
            $type = 4;
            // dd($menus);
            if(!empty( $info['rolesList'] )){
                $info['rolesList'] = explode(',',$info['rolesList']);
                $menus = $model->whereIn('id',$info['rolesList'])->get();
            }else{
                $ids = RoleMenu::where('role_id', $roles_id)->pluck('menu_id');
                // 租户管理员展示所有租户菜单
                $menus = $model->whereIn('id', $ids)->whereIn('is_tenant', [1, 2])->get(); 
            }
  
        }else{
            $type = (new Roles)->nameValue($info['roles_id'],'type');
            if(empty($user['type']) && $type ==3 && $info['rolesList'] !=NULL)
            {
                $info['rolesList'] = explode(',',$info['rolesList']);
                $menus = $model->whereIn('id',$info['rolesList'])->get();
            }

            // 管理员不展示租户菜单
            if ($type == 1) {
               if($parent) $menus = $model->whereIn('is_tenant',[0,2])->where('parent_id',0)->get();
               else $menus = $model->whereIn('is_tenant',[0,2])->get();
            }
        }
        if(empty($menus)){
            $menus = $RolesUserModel->menus($info['roles_id'],$parent);
        }

        $data = [
            'menus'         => $menus,
            'roles'         => ['TEST'],
            'icon'          => cdnurl($info['avatar']),
            'username'      => $info['username'],
            'user_id'       => $info['id'],
            'type'          => $type,
        ];
        return $this->success($data,__('base.success'));
    }

    // 注销接口
    public function destroy(Request $request)
    {
        $data = $request->all();
        $id = Auth::id();
        Auth::logout();
        DB::table('admin_logs')->insert(['admin_id' => $id,
        'log_url' => empty($data['s']) ? '':$data['s'],
        'log_ip'=> get_ip(),
        'log_info'=> '退出登陆',
        'log_time' => date('Y-m-d H:i:s')
        ]);
        return $this->success([],__('auth.destroy'));
    }
}
