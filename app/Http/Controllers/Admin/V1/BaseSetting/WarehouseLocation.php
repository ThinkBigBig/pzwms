<?php

namespace App\Http\Controllers\Admin\V1\BaseSetting;

use App\Http\Controllers\Admin\V1\BaseController;
use Illuminate\Http\Request;

class WarehouseLocation extends BaseController
{
    //库位管理
    protected $BaseModels = 'App\Models\WarehouseLocation';
    // protected $BaseAllVat = [];//获取全部验证
    // protected $BAWhere  = [];//获取全部Where条件
    // protected $BA  = ['*'];//获取全部选取字段 *是全部
    // protected $BAOrder  = [['sort','desc']];//获取全部字段排序

    protected $BaseLVat = [];//获取分页全部验证
    protected $BLWhere  = [
        'identifier'=> ['like',''],
        'type'=>['=',''],
        
    ];
    //获取全部分页Where条件
    protected $BL  = [
        'id','identifier','type','volume','usable_volume'
    ];//获取全部分页选取字段 *是全部
    protected $BLOrder  = [];//获取全部分页字段排序

    // protected $BaseOneVat = ['id' => 'required',];//单个处理验证
    // protected $BOWhere = ['id'=>['=','']];//单个查询验证
    // protected $BO = ['*'];//单个选取字段；*是全部
    // protected $BOOrder = [['id','desc']];//单个选取字段排序

    protected $BaseCreateVat = [
        'identifier' => 'required|regex:/^[0-9a-zA-Z-]*$/',//库位编号
        'type' => 'required',//库位类型
        'volume' => 'required|integer|min:1|max:100',//库位容积
    ];//新增验证
    protected $BaseCreate =[
        'identifier'=>'',
        'type'=>1,
        'volume' => 0,
        'created_at' =>['type','date'],
        // 'created_at' =>['type','date','Asia/Shanghai'],
    ];//新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
    ];
    //新增验证
    // protected $BaseUpdate =[
    //     'id'=>'','identifier'=>'','type'=>'','volume'=>'',
    //     'usable_volume'=>'' ,'updated_at'=>['type','date','Asia/Shanghai'],
    // ];//新增数据
    // protected $BUWhere= ['id'=>['=','']];//新增数据


    public function BaseCreate(Request $request){
        $request = $this->setRepeat($request,'identifier');
        return parent::BaseCreate($request);
    }


    public function BaseLimit(Request $request){
        $is_volume = $request->get('is_volume');
        if($is_volume == 1){
            $this->BLWhere['usable_volume']=['>',0];
        }
        if($is_volume == 2){
            $this->BLWhere['usable_volume']=['=',0];
        }
        return parent::BaseLimit($request);
    }

    public function _createFrom($create_data){
        $create_data['usable_volume'] = $create_data['volume'];
        return $create_data;

    }

}
