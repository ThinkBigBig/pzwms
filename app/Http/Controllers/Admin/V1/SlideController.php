<?php
    
namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\BaseController;
use Illuminate\Http\Request;
use App\Transformers\AuthorizationTransformer;
use Illuminate\Support\Facades\Hash;
use Dingo\Api\Exception\StoreResourceFailedException;


class SlideController extends BaseController
{
    protected $BaseModels = 'App\Models\Slide';
    protected $BaseAllVat = [];//获取全部验证
    protected $BAWhere  = [];//获取全部Where条件
    protected $BA  = ['*'];//获取全部选取字段 *是全部
    protected $BAOrder  = [['id','desc']];//获取全部字段排序

    protected $BaseLVat = [];//获取分页全部验证
    protected $BLWhere  = [];//获取全部分页Where条件
    protected $BL  = ['*'];//获取全部分页选取字段 *是全部
    protected $BLOrder  = [['sort','desc']];//获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',];//单个处理验证
    protected $BOWhere = ['id'=>['=','']];//单个查询验证
    protected $BO = ['*'];//单个选取字段；*是全部
    protected $BOOrder = [['id','desc']];//单个选取字段排序

    protected $BaseCreateVat = [
        'title' => 'required',//标题
        'content' => 'required',//描述
        'imagepath' => 'required',//图片地址
        'line_url' => 'required',//链接地址
        // 'listorder' => 'required',//排序
        'status' => 'required',//是否禁用（1：正常； 2：禁用）',
        'type' => 'required',//图片类型:1=首页轮播,2=会员轮播',
    ];//新增验证
    protected $BaseCreate =[
        'title'=>'','content'=>'','imagepath'=>'',
        'line_url'=>'','listorder'=>'','status'=>'',
        'type'=>'',
        'createtime' => ['type','time'],
    ];//新增数据

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

    public function _limitFrom($RData)
    {
        if(!empty($RData['data'])){
            foreach($RData['data'] as &$v){
                if(!empty($RData))
                {
                    if(!empty($v['imagepath'])) $v['imagepath'] = cdnurl($v['imagepath']);
                }
            }
        }
        return $RData;
    }

    public function _allFrom($RData)
    {
        if(!empty($RData)){
            foreach($RData as &$v){
                if(!empty($v['imagepath'])) $v['imagepath'] = cdnurl($v['imagepath']);
            }
        }
        return $RData;
    }

    public function _oneFrom($RData)
    {
        if(!empty($RData)){
            $RData['imagepath'] = cdnurl($RData['imagepath']);
        }
        return $RData;
    }
}
