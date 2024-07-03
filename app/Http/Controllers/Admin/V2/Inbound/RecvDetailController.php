<?php

namespace App\Http\Controllers\Admin\V2\Inbound;

use App\Http\Controllers\Admin\V2\BaseController;
use App\Http\Controllers\Admin\V2\Inbound\RecvOrderController;
use App\Models\Admin\V2\UniqCodePrintLog;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;



class RecvDetailController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\RecvDetail';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = []; //获取全部Where条件
    protected $BA  = ['*']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $BLWhere  = [
        'WHERE' => ['WHERE', ''],
        'ORDER' => ['ORDER', ''],
    ]; //获取全部分页Where条件
    protected $BL  = ['*']; //获取全部分页选取字段 *是全部
    protected $BLOrder  = [['created_at', 'desc'], ['id', 'desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序

    protected $BaseCreateVat = [
        'arr_id' => 'required',
        'bar_code' => 'required',
        'uniq_code' => 'required',
        'recv_methods' => 'required',

    ]; //新增验证
    protected $BaseCreate = [
        'recv_id' => '',
        'bar_code' => '',
        'uniq_code' => '',
        'box_code' => '',
        'containerCode' => '',
        'remark' => '',
        // 'recv_num'=>'',
        'sku'=>'',
        'ib_at' => ['type', 'date'],
        'created_at' => ['type', 'date'],
    ]; //新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',

    ]; //新增验证
    protected $BaseUpdate = [
        'recv_id' => '',
        'bar_code' => '',
        'uniq_code' => '',
        'box_code' => '',
        'containerCode' => '',
        'quality_type' => '',
        'quality_level' => '',
        // 'recv_num'=>'',
        'remark' => '',
        // 'updated_at' => ['type','date'],
    ]; //新增数据
    protected $BUWhere = ['id' => ['=', '']]; //新增数据

    //减扫
    public function delByUniq(Request $request)
    {
        $delVat = [
            'bar_code' => 'required',
            'uniq_code' => 'required',
            'arr_id' => 'required',
            'recv_id' => 'required',
        ];
        $data = $request->all();
        $validator = Validator::make($data, $delVat);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        list($res, $msg) = $this->model->delByBarCode($data['arr_id'], $data['recv_id'], $data['bar_code'], $data['uniq_code']);
        if (!$res) return $this->error($msg);
        return $this->success();
    }

    //普通产品减扫
    public function delByOrdinary(Request $request)
    {
        $delVat = [
            'bar_code' => 'required',
            'count' => 'required',
            'arr_id' => 'required',
            'recv_id' => 'required',
        ];
        $data = $request->all();
        $validator = Validator::make($data, $delVat);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        list($res, $msg) = $this->model->delByBarCode($data['arr_id'], $data['recv_id'], $data['bar_code'],'', $data['count']);
        if (!$res) return $this->error($msg);
        return $this->success();
    }

    //普通产品增加
    public function addByOrdinary(Request $request){
        $addVat = [
            // 'bar_code' => 'required',
            'count' => 'required',
            'detail_id' => 'required',
        ];
        $data = $request->all();
        $validator = Validator::make($data, $addVat);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        list($res, $msg) = $this->model->addOrdinaryRecvCount( $data['detail_id'], $data['count']);
        if (!$res) return $this->error($msg);
        return $this->success();
    }

    //删除
    public function delByBar(Request $request)
    {
        $delVat = [
            'bar_code' => 'required',
            'arr_id' => 'required',
            'recv_id' => 'required',

        ];
        $validator = Validator::make($request->all(), $delVat);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        $count = $request->get('count','all');
        list($res, $msg) = $this->model->delByBarCode($data['arr_id'], $data['recv_id'], $data['bar_code'],null,$count);
        if (!$res) return $this->error($msg);
        return $this->success();
    }
}
