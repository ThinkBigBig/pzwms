<?php

namespace App\Http\Controllers\Admin\V2\Inbound;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ConsignSettleCategoryController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\ConsignSettleCategory';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = []; //获取全部Where条件
    protected $BA  = ['*']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $BLWhere  = [
        'id' => ['=', ''],
        'WHERE' => ['WHERE', ''],
        'ORDER' => ['ORDER', ''],
    ]; //获取全部分页Where条件
    protected $BL  = ['*']; //获取全部分页选取字段 *是全部
    protected $BLOrder  = [['id', 'desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序

    protected $BaseCreateVat =[
        'name' => 'required',
        'sort' => 'required|integer',
        'parent_id' =>'required',
    ];

    protected $BaseCreate=[
        'name' => '',
        'code' => '',
        'sort' => '',
        'parent_id'=>'',
        'created_at' => ['type', 'date'],
        'created_user' => ['type', 'user_id'],
    ];

    protected $BaseUpdateVat = [
        'id' =>        'required',
        'sort' => 'integer',
    ];

    //新增验证
    protected $BaseUpdate = [
        'id' => '',
        'name' => '',
        'code' => '',
        'sort' => '',
        'parent_id'=>'',
        'updated_at' => ['type', 'date'],
        'updated_user' => ['type', 'user_id'],
    ]; //新增数据
    protected $BUWhere = ['id' => ['=', '']]; //新增数据



    public function _createFrom($create_data)
    {
        if (empty($create_data['code'])) {
            $create_data['code'] = $this::getErpCode('SPFL',5);
        }
        if (!$this->checkRepeat('name', $create_data['name'])) return $this->vdtError(__('response.name_repeat'));
        if (!$this->checkRepeat('code', $create_data['code'])) return $this->vdtError(__('response.code_repeat'));
        return $create_data;
    }


    public function _updateFrom($update_data)
    {
        if(empty( $update_data['name']))return $update_data;
        if($this->model->where('name', $update_data['name'])->where('id','<>',$update_data['id'])->exists())return $this->vdtError(__('response.name_repeat'));
        return $update_data;
    }
    public  function getTreeList()
    {
        $list =  $this->model::select('id', 'parent_id', 'name', 'path','code')->orderBy('parent_id')->orderBy('sort','desc')->get()->toArray();
        // dd($list);
        $data = listToTree($list,'parent_id');
        return $this->success($data);
    }



    public function BaseDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required',
        ], ['ids.required' => __('base.vdt')]);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $ids = $request->ids;

        list($res,$msg)= (new $this->BaseModels)->del($ids);
        if(!$res) return $this->error($msg);
        return $this->success();
    }
}
