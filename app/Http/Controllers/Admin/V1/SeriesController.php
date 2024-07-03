<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\BaseController;
use Illuminate\Http\Request;
use App\Transformers\AuthorizationTransformer;
use Illuminate\Support\Facades\Hash;
use Dingo\Api\Exception\StoreResourceFailedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SeriesController extends BaseController
{
    protected $BaseModels = 'App\Models\Series';
    protected $BaseAllVat = [];//获取全部验证
    protected $BAWhere  = ['brand_id'=>['=','']];//获取全部Where条件
    protected $BA  = ['*'];//获取全部选取字段 *是全部
    protected $BAOrder  = [['id','desc']];//获取全部字段排序

    protected $BaseLVat = ['parent_id' => 'required',];//获取分页全部验证
    protected $BLWhere  = [
        'name'=> ['like',''],
        'parent_id'=>['=',''],
        'brand_id'=>['=',''],
        'show_status'=>['=',''],
    ];//获取全部分页Where条件
    protected $BL  = ['*'];//获取全部分页选取字段 *是全部
    protected $BLOrder  = [['sort','desc']];//获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',];//单个处理验证
    protected $BOWhere = ['id'=>['=','']];//单个查询验证
    protected $BO = ['*'];//单个选取字段；*是全部
    protected $BOOrder = [['id','desc']];//单个选取字段排序

    protected $BaseCreateVat = [
        'parent_id' => 'required',//上级的编号：0表示一级分类
        'name' => 'required',//系列名称
        'name_cn' => 'required',//系列名称（中文）
        'level' => 'required',//级别：0->1级；1->2级
        'show_status' => 'required',//
        'icon' => 'required',//
        'brand_id' => 'required',//品牌的id
        'category_id' => 'required',//产品类型的id,
    ];//新增验证
    protected $BaseCreate =[
        'parent_id'=>'','name'=>'','name_cn'=>'',
        'level'=>'','product_count'=>'','product_unit'=>'',
        'nav_status'=>'','show_status'=>'','sort'=>'',
        'icon'=>'','keywords'=>'','keywords_cn'=>'',
        'description'=>'','description_cn'=>'','brand_id'=>'',
        'category_id'=>'',
        'createtime' => ['type','time'],
    ];//新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
    ];//新增验证
    protected $BaseUpdate =[
        'id'=>'','parent_id'=>'','name'=>'','name_cn'=>'',
        'level'=>'','product_count'=>'','product_unit'=>'',
        'nav_status'=>'','show_status'=>'','sort'=>'',
        'icon'=>'','keywords'=>'','keywords_cn'=>'',
        'description'=>'','description_cn'=>'','brand_id'=>'',
        'category_id'=>'',
        'updatetime' => ['type','time'],
    ];//新增数据
    protected $BUWhere= ['id'=>['=','']];//新增数据

    public function _oneFrom($RData)
    {
        if(!empty($RData)){
            $brand_name  = DB::table('pms_brand')->where('id','=',$RData['brand_id'])->pluck('name');
            $brand_name_cn = DB::table('pms_brand')->where('id','=',$RData['brand_id'])->pluck('name_cn');
            $category_name =  DB::table('pms_product_category')->where('id','=',$RData['category_id'])->pluck('name');
            $category_name_cn = DB::table('pms_product_category')->where('id','=',$RData['category_id'])->pluck('name_cn');
            $RData['brand_name'] =  !empty($brand_name[0]) ? $brand_name[0]: '';
            $RData['brand_name_cn'] =  !empty($brand_name_cn[0]) ? $brand_name_cn[0]: '';
            $RData['category_name'] =  !empty($category_name[0]) ? $category_name[0]: '';
            $RData['category_name_cn'] = !empty($category_name_cn[0]) ? $category_name_cn[0]: '';
        }
        return $RData;
    }

    public function _limitFrom($RData)
    {
        if(!empty($RData['data'])){
            foreach($RData['data'] as &$v){
                if(!empty($RData))
                {
                    $brand_name  = DB::table('pms_brand')->where('id','=',$v['brand_id'])->pluck('name');
                    $brand_name_cn = DB::table('pms_brand')->where('id','=',$v['brand_id'])->pluck('name_cn');
                    $category_name =  DB::table('pms_product_category')->where('id','=',$v['category_id'])->pluck('name');
                    $category_name_cn = DB::table('pms_product_category')->where('id','=',$v['category_id'])->pluck('name_cn');
                    $v['brand_name'] =  !empty($brand_name[0]) ? $brand_name[0]: '';
                    $v['brand_name_cn'] =  !empty($brand_name_cn[0]) ? $brand_name_cn[0]: '';
                    $v['category_name'] =  !empty($category_name[0]) ? $category_name[0]: '';
                    $v['category_name_cn'] = !empty($category_name_cn[0]) ? $category_name_cn[0]: '';
                }
            }
        }
        return $RData;
    }

}
