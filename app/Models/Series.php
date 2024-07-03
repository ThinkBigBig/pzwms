<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;

class Series extends BaseModel
{
    public $table = 'pms_product_series';

    const CREATEDTIME = 'createtime';
    const UPDATEDTIME = 'updatetime';

    //查询分类
    public function seriesClass($category_id =0){
        $data =  Series::where('show_status','=',1);
        if(!empty($category_id)){
            $data->where('category_id','=',$category_id);
        }
        $data->orderBy('sort','desc');
        $reData = $data->get();
        if(empty($reData) || $reData == NULL){
            return [];
        }else{
            $reData = objectToArray($reData);
            $deArray =[];
            foreach($reData as $v){
                if(empty($deArray[$v['brand_id']])){
                    $brand = Brand::where('show_status','=',1)->find($v['brand_id']);
                    if(empty($brand) || $brand == NULL)  continue;
                    $deArray[$v['brand_id']]['id'] = $v['brand_id'];
                    $deArray[$v['brand_id']]['name'] = !empty($brand['name'])? $brand['name']:'';
                    $deArray[$v['brand_id']]['name_cn'] = !empty($brand['name_cn'])? $brand['name_cn']:'';
                    $deArray[$v['brand_id']]['logo'] = !empty($brand['logo'])? $brand['logo']:'';
                    $deArray[$v['brand_id']]['product_comment_count'] = !empty($brand['product_comment_count'])? $brand['product_comment_count']:'';
                    $deArray[$v['brand_id']]['product_count'] = !empty($brand['product_count'])? $brand['product_count']:'';
                    $deArray[$v['brand_id']]['brand_story'] = !empty($brand['brand_story'])? $brand['brand_story']:'';
                    $deArray[$v['brand_id']]['sort'] = !empty($brand['sort'])? $brand['sort']: 0;
                    $deArray[$v['brand_id']]['list'][] = $v;
                }else{
                    $deArray[$v['brand_id']]['list'][] = $v;
                    // $deArray[$v['brand_id']]['sort'] = !empty($brand['sort'])? $brand['sort']: 0;
                }
            }
            $deArray = array_sort($deArray,'sort','desc');
            return array_values($deArray);
        }
    }
}
