<?php
    
namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\BaseController;
use Illuminate\Http\Request;
use App\Transformers\AuthorizationTransformer;
use Illuminate\Support\Facades\Hash;
use Dingo\Api\Exception\StoreResourceFailedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MemberController extends BaseController
{
    protected $BaseModels = 'App\Models\Member';
    protected $BaseAllVat = [];//获取全部验证
    protected $BAWhere  = [];//获取全部Where条件
    protected $BA  = ['*'];//获取全部选取字段 *是全部
    protected $BAOrder  = [['id','desc']];//获取全部字段排序

    protected $BaseLVat = [];//获取分页全部验证
    protected $BLWhere  = [
        'username'=> ['like',''],
        'mobile'=> ['like',''],
        'email'=> ['like',''],
        'status'=>['=','']
    ];//获取全部分页Where条件
    protected $BL  = [
                'id',
                'username',
                'nickname',
                'email',
                'mobile',
                'reg_ip',
                'last_login_time',
                'login',
                'status',
    ];//获取全部分页选取字段 *是全部
    protected $BLOrder  = [['id','desc']];//获取全部分页字段排序
    protected $BaseOneVat = ['id' => 'required',];//单个处理验证
    protected $BOWhere = ['id'=>['=','']];//单个查询验证
    protected $BO = ['*'];//单个选取字段；*是全部
    protected $BOOrder = [['id','desc']];//单个选取字段排序

    protected $BaseCreateVat = [];//新增验证
    protected $BaseCreate =[];//新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
    ];//新增验证
    protected $BaseUpdate =[
        'id'=>'',
        'nickname'=>'',
        'password'=>['type','password'],
        'mobile'=>'',
        'avatar'=>'',
        'email'=>'',
    ];//新增数据
    protected $BUWhere= ['id'=>['=','']];//新增数据

    public function _limitFrom($RData)
    {
        if(!empty($RData['data'])){
            foreach($RData['data'] as &$v){
                if(!empty($RData))
                {
                    $createtime = DB::table('examine')->where('uid','=',$v['id'])->pluck('createtime');
                    $v['createtime'] = !empty($createtime[0]) ? $createtime[0]: '';
                }
            }
        }
        return $RData;
    }

    /**
     * 审核
     *
     * @param Request $request
     * @return void
     */
    public function pass(Request $request){

        $validator = Validator::make($request->all(), [
            'ids' => 'required',
            'status' => 'required',
        ]);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $ids = explode(',',$request->ids);
        $update_data = ['status'=>$request->status];
        $RData = (new $this->BaseModels)->BaseUpdate([['id','in',$ids]],$update_data);
        if($RData)
            return  $this->success([],__('base.success'));
        else
            return  $this->error();
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
        if(!empty($data['username']) ){
            $username  = DB::table('member')->where('id','!=',$data['id'])->where('username','=',$data['username'])->count();
            if( $username > 0){
                return  $this->error(__('base.userCreate'));
            }
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
            if(!empty($v[0]) && !empty($v[1]) && $v[0]== 'type' && $v[1]== 'password'){
                //验证旧密码是否正确
                if(!empty($data[$k])){
                    $password = encrypt_password($data[$k]);
                    // var_dump($password );exit;
                    $update_data['password'] = $password['password'];
                    $update_data['encrypt'] =  $password['encrypt'];
                }
            }
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
        if($RData)
            return  $this->success($data,__('base.success'));
        else
            return  $this->error();
    }
}
