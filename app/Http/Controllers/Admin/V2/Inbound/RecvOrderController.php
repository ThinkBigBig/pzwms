<?php

namespace App\Http\Controllers\Admin\V2\Inbound;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Models\Admin\V2\UniqCodePrintLog;
use App\Models\Admin\V2\WmsOptionLog;
use App\Models\Admin\V2\WmsQualityList;

class RecvOrderController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\RecvOrder';
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
        'recv_type' => 'required|regex:/[1-4]/', //类型
        'arr_id' => 'required',
        'recv_methods' => 'required',
        'bar_code' => 'required',
        'uniq_code' => 'required',
        'ib_code' => 'required_if:recv_type,2,3',

    ]; //新增验证
    protected $BaseCreateVatMsg = [
        'required_if' => '请先匹配入库单',
    ];
    protected $BaseCreate = [
        'recv_id' => '',
        'ib_code' => '',
        'uniq_code' => '',
        'arr_id' => '',
        'recv_methods' => '',
        'remark' => '',
        'created_at' => '',
        'bar_code' => '',
        'box_code' => '',
        'containerCode' => '',
        'is_flaw'=>'',
        'recv_unit'=>'',
        'scan_type'=>'',
        'ib_at' => '',
    ]; //新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
        'doc_status' => 'regex:/[1-4]/',
        'arr_status' => 'regex:/[1-4]/',

    ]; //新增验证
    protected $BaseUpdate = [
        'arr_id' => '',
        'recv_methods' => '',
        // 'recv_num'=>'',
        'remark' => '',
        // 'updated_at' => ['type','date'],
    ]; //新增数据
    protected $BUWhere = ['id' => ['=', '']]; //新增数据
    protected $code_field = 'recv_code';
    protected $NAME;
    protected $exportField;
    public function setExcelField()
    {
        $this->NAME = __('excel.recv_order.title');
        $this->exportField = [
            'recv_type_txt' =>  __('excel.recv_order.recv_type_txt'),
            'recv_code' =>  __('excel.recv_order.recv_code'),
            'doc_status_txt' =>  __('excel.recv_order.doc_status_txt'),
            'recv_status_txt' =>  __('excel.recv_order.recv_status_txt'),
            'arr_item.lot_num' =>  __('excel.recv_order.arr_item_lot_num'),
            'arr_item.arr_code' =>  __('excel.recv_order.arr_item_arr_code'),
            'recv_num' =>  __('excel.recv_order.recv_num'),
            'warehouse_code' =>  __('excel.recv_order.arr_item_warehouse'),
            'recv_methods_txt' =>  __('excel.recv_order.recv_methods'),
            'remark' =>  __('excel.remark'),
            'created_user' =>  __('excel.recv_order.created_user'),
            'created_at' =>  __('excel.recv_order.created_at'),
            'updated_at' =>  __('excel.recv_order.updated_at'),
        ];
    }

    public function _exportFiledEdit(){
        $update = [
            __('excel.recv_order.arr_item_warehouse')=>'arr_item.warehouse',
        ];
        return $update;
    }

    //开始收货
    //唯一码绑定条形码
    public function BaseCreate(Request $request)
    {
        $create_data = [];
        $msg = !empty($this->BaseCreateVatMsg) ? $this->BaseCreateVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseCreateVat, [
            'required_if' => __('status.please_ib_match'),
        ]);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        //根据配置传入参数
        foreach ($this->BaseCreate as $k => $v) {
            if (isset($data[$k]) && $data[$k] !== '') {
                $create_data[$k] = $data[$k];
            }
            if (!empty($v[0]) && !empty($v[1]) && $v[0] == 'type' && $v[1] == 'time') {
                $create_data[$k] = time();
            }
            if (!empty($v[0]) && !empty($v[1]) && $v[0] == 'type' && $v[1] == 'date') {
                $create_data[$k] = date('Y-m-d H:i:s');
            }
        }
        return $this->modelReturn('add', [$create_data]);
    }


    //普通产品收货
    public function addOrdinary(Request $request)
    {
        $create_data = [];
        $validator = Validator::make($request->all(), [
            'recv_type' => 'required|regex:/[1-4]/', //类型
            'arr_id' => 'required',
            'bar_code' => 'required',
            'recv_unit'=>'required',
            'scan_type'=>'required',
            'recv_methods' => 'required',
            'ib_code' => 'required_if:recv_type,2,3',
        ],[
            'required_if' => __('status.please_ib_match'),
        ]);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        //根据配置传入参数
        foreach ($this->BaseCreate as $k => $v) {
            if (isset($data[$k]) && $data[$k] !== '') {
                $create_data[$k] = $data[$k];
            }
            if (!empty($v[0]) && !empty($v[1]) && $v[0] == 'type' && $v[1] == 'time') {
                $create_data[$k] = time();
            }
            if (!empty($v[0]) && !empty($v[1]) && $v[0] == 'type' && $v[1] == 'date') {
                $create_data[$k] = date('Y-m-d H:i:s');
            }
        }
        return $this->modelReturn('addOrdinary', [$create_data]);
    }


    public function  arrCheck($arr_id)
    {
        return  $this->model->getArrItem($arr_id);
    }

    public function isUniqCode($uniq_code, $arr_id)
    {
        return UniqCodePrintLog::isUniqCode($uniq_code, $arr_id);
    }

    //收货完成
    public function recvDone(Request $request)
    {
        $arr_id = $request->get('arr_id');
        $recv_id = $request->get('recv_id');
        if (empty($arr_id) || empty($recv_id)) return $this->vdtError();

        list($res, $msg) = $this->model->recvDone($arr_id, $recv_id);

        if (!$res) return $this->error($msg);
        return $this->success();
    }

    public function _limitFrom($RData)
    {
        if ($RData['data'] ?? []) {
            $codes = array_column($RData['data'], 'recv_code');
            $recv_codes = WmsQualityList::whereIn('recv_code', $codes)->pluck('recv_code');
            if ($recv_codes) $recv_codes = $recv_codes->toArray();
            foreach ($RData['data'] as &$v) {
                $v['is_qc'] = $recv_codes && in_array($v['recv_code'], $recv_codes) ? 1 : 0;
            }
        }

        return $RData;
    }
}
