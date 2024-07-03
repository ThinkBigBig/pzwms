<?php

namespace App\Http\Controllers\PDA;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BaseController extends Controller
{

    //公共数据
    protected $warehouse_code;
    
    // 请求成功时对数据进行格式处理
    public function success($data = [], $msg = '')
    {
        if($data && !isset($data[0])) $data=[$data];
        return response()->json([
            'code' => self::SUCCESS,
            'message' => !empty($msg) ? $msg : __('base.success'),
            'time' => time(),
            'data' => $data
        ]);
    }

    // 错误提示方法
    public function error($msg = '',$data=[]){
        return response()->json([
            'code' => self::ERROR,
            'message' => !empty($msg) ? $msg :__('base.error'),
            'time' => time(),
            // 'data' => $data,
        ]);
    }

    protected function formatProduct($data){
        foreach($data as &$pro){
            $pro['product_sku'] = empty($pro['product']['sku'])?'':$pro['product']['sku'];
            $pro['product_spec'] = empty($pro['product']['spec_one'])?'':$pro['product']['spec_one'];
            $pro['product_name'] = empty($pro['product']['product']['name'])?'':$pro['product']['product']['name'];
            $pro['product_img'] = empty($pro['product']['product']['img'])?'':$pro['product']['product']['img'];
            $pro['product_sn'] = empty($pro['product']['product']['name'])?'':$pro['product']['product']['product_sn'];
            unset($pro['product']);
        }
        return $data;
    }
}
