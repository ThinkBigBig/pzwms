<?php
    
namespace App\Http\Controllers\Admin\V1\Auth;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Http\Request;
use App\Transformers\AuthorizationTransformer;
use Illuminate\Support\Facades\Hash;
use Dingo\Api\Exception\StoreResourceFailedException;
use App\Models\RolesUser;
use App\Models\RoleMenu;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class RolesController extends BaseController
{
    protected $BaseModels = 'App\Models\Roles';
    protected $BaseAllVat = [];//获取全部验证
    protected $BAWhere  = [];//获取全部Where条件
    protected $BA  = ['*'];//获取全部选取字段 *是全部
    protected $BAOrder  = [['id','desc']];//获取全部字段排序

    protected $BaseLVat = [];//获取分页全部验证
    protected $BLWhere  = [];//获取全部分页Where条件
    protected $BL  = ['*'];//获取全部分页选取字段 *是全部
    protected $BLOrder  = [['id','desc']];//获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',];//单个处理验证
    protected $BOWhere = ['id'=>['=','']];//单个查询验证
    protected $BO = ['*'];//单个选取字段；*是全部
    protected $BOOrder = [['id','desc']];//单个选取字段排序

    protected $BaseCreateVat = [
        'name' => 'required',//角色名
        'display_name' => 'required',//显示名
        'description' => 'required',//描述
        // 'icon' => 'required',//图标
    ];//新增验证
    protected $BaseCreate =[
        'name'=>'','password'=>'','email'=>'',
        'display_name'=>'','description'=>'','role_code'=>'',
        'type'=>'',
        'created_at' => ['type','date'],
    ];//新增数据

    protected $BaseUpdateVat = [
        'id' => 'required',//角色名
        // 'name' => 'required',//角色名
        // 'display_name' => 'required',//显示名
        // 'description' => 'required',//描述
    ];//新增验证
    protected $BaseUpdate =[
        'id'=>'','name'=>'','password'=>'','email'=>'',
        'display_name'=>'','description'=>'','role_code'=>'',
        'type'=>'',
        'updated_at' => ['type','date'],
    ];//新增数据
    protected $BUWhere= ['id'=>['=','']];//新增数据

    public function roleList(Request $request)
    {
        $validator = Validator::make($request->all(),['id' => 'required'] );
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $RolesUserModel = (new RolesUser);
        $data = $RolesUserModel->menus($request->id);
        return $this->success($data,__('base.success'));
    }

    public function setRoleList(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'id' => 'required',
            'menu_ids' => 'required',
            ] );
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $RolesUserModel = (new RoleMenu());
        $roleList = $request->menu_ids;
        $roleList_ids = explode(',',$roleList);
        $array = [];
        foreach($roleList_ids as $v){
            $array[] = ['role_id'=>$request->id,'menu_id'=>$v,];
        }

        DB::beginTransaction();
        // 你也可以通过 rollBack 方法来还原事务：
        $RolesUserModel->where('role_id','=',$request->id)->delete();
        $status =  $RolesUserModel->insert($array);
        if($status){
            DB::commit();
            return $this->success();
        }else{
            DB::rollBack();
            return $this->error();
        }
    }

    public function _limitFrom($data)
    {
        foreach($data['data'] as $k => $v){
            $admin_role_menu = DB::table('admin_role_menu')->where('role_id','=',$v['id'])->pluck('menu_id');
            if(count( $admin_role_menu)>0){
                $data['data'][$k]['rolesList'] =  json_decode($admin_role_menu,true);
            }else{
                $data['data'][$k]['rolesList'] = 0;
            }
        }
        return $data;
    }

    public function _oneFrom($data)
    {
        $admin_role_menu = DB::table('admin_role_menu')->where('role_id','=',$data['id'])->pluck('menu_id');
        $data['rolesList'] = $admin_role_menu;
        return $data;
    }

    public function _createFrom($create_data){
        if(empty($create_data['role_code'])){
            $create_data['role_code'] = $this::getErpCode('JS');
        }
        if(empty($create_data['tenant_id']) && ADMIN_INFO['tenant_id']){
            $create_data['tenant_id'] = ADMIN_INFO['tenant_id'];
        }

        if(!$this->checkRepeat('name' ,$create_data['name'])) return $this->vdtError('名称被占用');
        if($this->checkRepeat('role_code' ,$create_data['role_code'])){
            return $create_data;
        }else{
            return $this->vdtError('编码重复');
        }
    }

    public function roleType(Request $request)
    {
        $data = [
            ['type' => 3, 'name' => '租户管理员'],
            ['type' => 2, 'name' => '组织'],
            ['type' => 1, 'name' => '管理员'],
        ];

        if (ADMIN_INFO['tenant_id'] ?? 0) {
            $data =  [
                ['type' => 4, 'name' => '供应商'],
                // ['type' => 5, 'name' => '租户'],
                // ['type' => 6, 'name' => '租户'],
            ];
        }
        return $this->success($data, __('base.success'));
    }

    /**
     *基础取多个值带分页方法
     *
     * @param Request $request
     * @return void
     */
    public function BaseLimit(Request $request)
    {
        $where_data = [];
        $msg = !empty($this->BaseLVatMsg) ? $this->BaseLVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseLVat, $msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        $cur_page   = !empty($data['cur_page']) ? $data['cur_page'] : 1;
        $size       = !empty($data['size']) ? $data['size'] : 10;

        $where_data[] = ['tenant_id', '=', ADMIN_INFO['tenant_id']];

        //修改参数  request要存在或者BUWhere存在
        foreach ($this->BLWhere as $B_k => $B_v) {
            // var_dump($B_k[1]);exit;
            if (!empty($B_v) && empty($B_v[1]) && isset($data[$B_k])) {
                if ($B_v[0] == 'allLike') {
                    $where_data[] = ["concat({$B_v[2]}) like ?", $B_v[0], ["%{$data[$B_k]}%"]];
                    continue;
                }
                $where_data[] = [$B_k, $B_v[0], $data[$B_k]];
                continue;
            }
            if (!empty($B_v) && (!empty($B_v[1]) || $B_v[1] === 0)) {
                $where_data[] = [$B_k, $B_v[0], $B_v[1]];
                continue;
            }
        }
        $RData = (new $this->BaseModels)->BaseLimit($where_data, $this->BL, $this->BLOrder, $cur_page, $size);
        if (method_exists($this, '_limitFrom')) $RData = $this->_limitFrom($RData);
        return  $this->success($RData, __('base.success'));
    }

}
