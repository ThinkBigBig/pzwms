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

class OrgController extends BaseController
{
    protected $BaseModels = 'App\Models\Organization';
    protected $BaseAllVat = [];//获取全部验证
    protected $BAWhere  = [];//获取全部Where条件
    protected $BA  = ['*'];//获取全部选取字段 *是全部
    protected $BAOrder  = [['id','desc']];//获取全部字段排序

    protected $BaseLVat = [];//获取分页全部验证
    protected $BLWhere  = [
        'name'=> ['like',''],
        'code'=> ['=',''],
        'WHERE' => ['WHERE', ''],
    ];//获取全部分页Where条件
    protected $BL  = ['*'];//获取全部分页选取字段 *是全部
    protected $BLOrder  = [['id','desc']];//获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',];//单个处理验证
    protected $BOWhere = ['id'=>['=','']];//单个查询验证
    protected $BO = ['*'];//单个选取字段；*是全部
    protected $BOOrder = [['id','desc']];//单个选取字段排序

    protected $BaseCreateVat = [
        // 'parent_id' => 'required',//上级组织
        'name' => 'required',//组织名称
    ];//新增验证
    protected $BaseCreate =[
        'name'=>'','org_code'=>'','type'=>'',
        'status'=>'','parent_id'=>'',
        'created_at' => ['type','date'],
        'created_user' => ['type','user_id'],

    ];//新增数据

    protected $BaseUpdateVat = [
        'id' => 'required',//组织id
    ];//新增验证
    protected $BaseUpdate =[
        'name'=>'','org_code'=>'','type'=>'',
        'status'=>'','parent_id'=>'',
        'updated_at' => ['type','date'],
        'updated_user' =>['type','user_id'],
    ];//新增数据
    protected $BUWhere= ['id'=>['=','']];//新增数据




    public function _createFrom($create_data){
        if(!isset($create_data['status']))$create_data['status']=1;
        if(empty($create_data['org_code'])){
            while(1){
                $create_data['org_code'] = $this::getErpCode('O');
                if($this->checkRepeat('org_code' ,$create_data['org_code']))break;

            }
        }else{
            if(!$this->checkRepeat('org_code' ,$create_data['org_code']))return $this->vdtError('编码重复');

        }
        if(!$this->checkRepeat('name' ,$create_data['name']))return $this->vdtError('名称重复');
        return $create_data;

    }

    public function orgList( Request $request){
        $list =  (new $this->BaseModels)::where('status',1)->select('id','parent_id','name','org_code')->get()->toArray();
        // dd($list);
        $data = listToTree($list,'parent_id');
        return $this->success($data);

    }


}
