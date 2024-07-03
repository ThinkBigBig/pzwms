<?php
    
namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\BaseController;
use Illuminate\Http\Request;
use App\Transformers\AuthorizationTransformer;
use Illuminate\Support\Facades\Hash;
use Dingo\Api\Exception\StoreResourceFailedException;

class ExamineController extends BaseController
{
    protected $BaseModels = 'App\Models\Examine';
    protected $BaseAllVat = [];//获取全部验证
    protected $BAWhere  = [];//获取全部Where条件
    protected $BA  = ['*'];//获取全部选取字段 *是全部
    protected $BAOrder  = [['id','desc']];//获取全部字段排序

    protected $BaseLVat = [];//获取分页全部验证
    protected $BLWhere  = [
    ];//获取全部分页Where条件
    protected $BL  = ['*'];//获取全部分页选取字段 *是全部
    protected $BLOrder  = [['id','desc']];//获取全部分页字段排序

    protected $BaseOneVat = ['uid' => 'required',];//单个处理验证
    protected $BOWhere = ['uid'=>['=','']];//单个查询验证
    protected $BO = ['*'];//单个选取字段；*是全部
    protected $BOOrder = [['id','desc']];//单个选取字段排序

    protected $BaseCreateVat = [ ];//新增验证
    protected $BaseCreate =[];//新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
    ];//新增验证

    protected $BaseUpdate =[
        'id'=>'', 'title'=>'','content'=>'',
        'imagepath'=>'','line_url'=>'','listorder'=>'',
        'status'=>'','type'=>'',
        'updatetime' => ['type','time'],
    ];//新增数据

    protected $BUWhere= ['id'=>['=','']];//新增数据

    public function _oneFrom($RData)
    {
        if(!empty($RData)){
            $RData['positive'] = cdnurl($RData['positive'] );
            $RData['other_side'] = cdnurl($RData['other_side'] );
        }
        return $RData;
    }
}
