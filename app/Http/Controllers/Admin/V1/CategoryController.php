<?php
    
namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\BaseController;
use Illuminate\Http\Request;
use App\Transformers\AuthorizationTransformer;
use Illuminate\Support\Facades\Hash;
use Dingo\Api\Exception\StoreResourceFailedException;
use App\Models\RolesUser;

class CategoryController extends BaseController
{
    protected $BaseModels = 'App\Models\Category';
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
        'parent_id' => 'required',//
        'name' => 'required',//
        'name_cn' => 'required',//
        'keywords' => 'required',//关键词
        'show_status' => 'required',//关键词
        'description' => 'required',//描述
        'unit' => 'required',//日文单位
        'unit_cn' => 'required',//中文单位
    ];//新增验证
    protected $BaseCreate =[
        'parent_id'=>'','name'=>'','name_cn'=>'',
        'level'=>'','product_count'=>'','product_unit'=>'',
        'product_unit_cn'=>'','nav_status'=>'','show_status'=>'',
        'sort'=>'','icon'=>'','keywords'=>'',
        'description'=>'','unit'=>'','unit_cn'=>'',
        // 'createtime' => ['type','time'],
    ];//新增数据
    protected $BaseUpdateVat = [
        'id' =>        'required',
    ];//新增验证
    protected $BaseUpdate =[
        'id'=>'', 'parent_id'=>'','name'=>'','name_cn'=>'',
        'level'=>'','product_count'=>'','product_unit'=>'',
        'product_unit_cn'=>'','nav_status'=>'','show_status'=>'',
        'sort'=>'','icon'=>'','keywords'=>'',
        'description'=>'','unit'=>'','unit_cn'=>'',
        'updatetime' => ['type','time'],
    ];//新增数据
    protected $BUWhere= ['id'=>['=','']];//新增数据

    public function _allFrom($RData)
    {
        if(!empty($RData)){
            $RData = getTreeArray($RData,'parent_id');
        }
        return $RData;
    }
}
