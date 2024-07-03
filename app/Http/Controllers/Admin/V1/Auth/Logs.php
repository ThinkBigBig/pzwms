<?php
    
namespace App\Http\Controllers\Admin\V1\Auth;

use App\Http\Controllers\Admin\V1\BaseController;
use Illuminate\Http\Request;
use App\Models\AdminAuthorization;
use App\Transformers\AuthorizationTransformer;
use Illuminate\Support\Facades\Hash;
use Dingo\Api\Exception\StoreResourceFailedException;
use App\Models\AdminUser;
use Auth;


class Logs extends BaseController
{
    protected $BaseModels = 'App\Models\Logs';
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

    public function _limitFrom($RData)
    {
        if(!empty($RData['data'])){
            if(!empty($RData['data'])){
                foreach($RData['data'] as $k => &$v){
                    $name = '';
                    if(!empty($v['admin_id'])){
                      $name =  (new AdminUser)::where('id','=',$v['admin_id'])->value('username');
                    }
                    $v['admin_name'] = $name;
                }
            }
        }
        return $RData;
    }

    public function _oneFrom($RData)
    {
        if(!empty($RData)){
            $name = '';
            if(!empty($RData['admin_id'])){
              $name = (new AdminUser)::where('id','=',$RData->admin_id)->value('username');
            }
            $RData['admin_name'] = $name;
        }
        return $RData;
    }
}
