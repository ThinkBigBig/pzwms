<?php
    
namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\BaseController;
use Illuminate\Http\Request;
use App\Transformers\AuthorizationTransformer;
use Illuminate\Support\Facades\Hash;
use Dingo\Api\Exception\StoreResourceFailedException;
use App\Models\RolesUser;

class BrandController extends BaseController
{
    protected $BaseModels = 'App\Models\Brand';
    protected $BaseAllVat = [];//获取全部验证
    protected $BAWhere  = [];//获取全部Where条件
    protected $BA  = ['*'];//获取全部选取字段 *是全部
    protected $BAOrder  = [['sort','desc']];//获取全部字段排序

    protected $BaseLVat = [];//获取分页全部验证
    protected $BLWhere  = [
        'name'=> ['like',''],
        'show_status'=>['=',''],
    ];//获取全部分页Where条件
    protected $BL  = ['*'];//获取全部分页选取字段 *是全部
    protected $BLOrder  = [['sort','desc']];//获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',];//单个处理验证
    protected $BOWhere = ['id'=>['=','']];//单个查询验证
    protected $BO = ['*'];//单个选取字段；*是全部
    protected $BOOrder = [['id','desc']];//单个选取字段排序

    protected $BaseCreateVat = [
        'name' => 'required',//品牌名日文
        'name_cn' => 'required',//品牌名中文
        'first_letter' => 'required',//首字母
        'factory_status' => 'required',//是否为品牌制造商：0->不是；1->是,
        'show_status' => 'required',//1展示，2隐藏,
        'logo' => 'required',//品牌logo,
        'brand_story' => 'required',//品牌故事,
        'brand_story_cn' => 'required',//品牌故事中文
    ];//新增验证
    protected $BaseCreate =[
        'name'=>'','name_cn'=>'','first_letter'=>'',
        'factory_status'=>'','show_status'=>'','logo'=>'',
        'brand_story'=>'','brand_story_cn'=>'',
        'createtime' => ['type','time'],
    ];//新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
    ];//新增验证
    protected $BaseUpdate =[
        'id'=>'','name'=>'','name_cn'=>'','first_letter'=>'',
        'factory_status'=>'','show_status'=>'','logo'=>'',
        'brand_story'=>'','brand_story_cn'=>'',
        'sort'=>'',
        'updatetime' => ['type','time'],
    ];//新增数据
    protected $BUWhere= ['id'=>['=','']];//新增数据
}
