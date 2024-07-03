<?php

namespace App\Http\Controllers\Admin\V1\Warehousing;

use App\Http\Controllers\Admin\V1\BaseController;
use Illuminate\Http\Request;

class Warehousing extends BaseController
{
    //库位管理
    protected $BaseModels = 'App\Models\Warehousing';
    protected $BaseLVat = [];//获取分页全部验证
    protected $BLWhere  = [
        'consignment_code'=>['=',''],
    ];
    //获取全部分页Where条件
    protected $BL  = [
        'id','img','name','product_sn','consignment_code','locator_number','status'
    ];
    
    //获取全部分页选取字段 *是全部
    protected $BLOrder  = [];

    // protected $BaseCreateVat = [
    //     'identifier' => 'required|regex:/^[0-9a-zA-Z-]*$/',//库位编号
    //     'type' => 'required',//库位类型
    //     'volume' => 'required|integer|min:1|max:100',//库位容积
    // ];

    //新增验证
    // protected $BaseCreate =[
    //     'identifier'=>'',
    //     'type'=>1,
    //     'volume' => 0,
    //     'created_at' =>['type','date','Asia/Shanghai'],
    // ];

    // //新增数据
    // protected $BaseUpdateVat = [
    //     'id' =>  'required',
    // ];

}
