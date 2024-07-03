<?php

namespace App\Http\Controllers\Admin\V2\Inbound;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ArrivalExport;
use App\Exports\ConsignExport;

class ArrivalRegistController extends BaseController
{
    // protected  $exportName = '\App\Exports\ArrivalExport';
    protected $BaseModels = 'App\Models\Admin\V2\ArrivalRegist';
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
    protected $BLOrder  = [['created_at', 'desc'], ['id', 'desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序

    protected $BaseCreateVat = [
        'arr_type' => 'required|regex:/[1-4]/', //类型
        'arr_name' => 'required', //名称
        'warehouse_code' => 'required', //仓库
        'arr_num' => 'required', //登记箱数
        'doc_status' => 'regex:/[1-4]/',
        'arr_status' => 'regex:/[1-4]/',

    ]; //新增验证
    protected $BaseCreate = [
        'arr_type' => '',
        'arr_name' => '',
        'warehouse_code' => '',
        'arr_num' => '',
        'log_product_code' => '',
        'ib_code' => '',
        'log_number' => '',
        'remark' => '',
        'created_at' => ['type', 'date'],
        'created_user' => ['type', 'user_id'],
    ]; //新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
        'arr_type' => 'regex:/[1-4]/',
        'doc_status' => 'regex:/[1-4]/',
        'arr_status' => 'regex:/[1-4]/',

    ]; //新增验证
    protected $BaseUpdate = [
        'arr_type' => '',
        'arr_name' => '',
        // 'warehouse_code'=>'',
        'arr_num' => '',
        'log_product_code' => '',
        'ib_code' => '',
        'log_number' => '',
        'remark' => '',
        'updated_at' => ['type', 'date'],
        'updated_user' => ['type', 'user_id'],

    ]; //新增数据
    protected $BUWhere = ['id' => ['=', '']]; //新增数据

    protected $repeat  = ['arr_name'];
    protected $code_field = 'arr_code';
    protected $NAME;
    protected $exportField;

    public function setExcelField()
    {
        $this->NAME = __('excel.arrival.export');
        $this->exportField = [
            'arr_name' => __('excel.arrival.arr_name'),
            'arr_type_txt' => __('excel.arrival.arr_type'),
            'arr_code' =>  __('excel.arrival.arr_code'),
            'lot_num' =>  __('excel.arrival.lot_num'),
            'doc_status_txt' =>  __('excel.arrival.doc_status'),
            'arr_status_txt' => __('excel.arrival.arr_status'),
            'warehouse.warehouse_name' =>  __('excel.arrival.warehouse'),
            'product_sn' => __('columns.wms_product.product_sn'),
            'sku' => __('columns.wms_spec_and_bar.sku'),
            'ib_type_txt' => __('excel.arrival.ib_type_txt'),
            'ib_code' => __('excel.arrival.ib_code'),
            'third_doc_code' =>  __('excel.arrival.third_doc_code'),
            'arr_num' =>  __('excel.arrival.arr_num'),
            'recv_num' =>  __('excel.arrival.recv_num'),
            'confirm_num' =>  __('excel.arrival.confirm_num'),
            'uni_num_count' =>  __('excel.arrival.uni_num_count'),
            'log_product_code' =>  __('excel.arrival.log_product_code'),
            'log_number' => __('excel.arrival.log_number'),
            'remark' =>  __('excel.remark'),
            'flag'=>__('excel.flag'),
            'users_txt.created_user' => __('excel.purchase_order.created_user'),
            'created_at' => __('excel.uniq_record.created_at'),
            'users_txt.updated_user' => __('excel.purchase_order.updated_user'),
            'updated_at' => __('excel.uniq_record.updated_at'),
        ];
    }

    public function _exportFiledEdit()
    {
        $update = [
            __('excel.arrival.warehouse') => 'warehouse',
        ];
        return $update;
    }
    public function _createFrom($create_data)
    {

        if (in_array($create_data['arr_type'], [2, 3])) {
            if (empty($create_data['ib_code'])) return $this->error(__('response.return_ib_match'));
            //判断仓库是否一致;
            $third_warehouse_code = $this->model->checkWarehouse($create_data['ib_code']);
            if ($create_data['warehouse_code'] != $third_warehouse_code) return $this->error(__('response.warehouse_not_match'));
        }
        $create_data['arr_code']  = $this->getErpCode('DJD', 10);
        $create_data['doc_status']  = 1;
        $create_data['arr_status']  = 2;

        while (1) {
            $create_data['lot_num']  = $this->getLogNum();
            if ($this->checkRepeat('lot_num', $create_data['lot_num'])) break;
        }
        return $create_data;
    }

    public function _updateBefore($update_before)
    {
        //判断状态是已作废
        $arr_id = $update_before['id'];
        $doc_status =   $this->model->find($arr_id)->doc_status;
        if ($doc_status == 3) return $this->error(__('response.doc_invalid'));
        //判断是否生成收货单--且收货单的状态是已完成
        $res = $this->model->hasDone($arr_id);
        if ($res) return $this->error(__('response.arr_edit_choose_not_recv'));
        return $update_before;
    }


    //生成批次号

    public static function getLogNum()
    {
        $tenant_id = request()->header('tenant_id', '');
        $r_name = 'LNcode_' . $tenant_id;
        $end = strtotime(date('Y-m-d') . ' 23:59:59');
        $num = Redis::get($r_name);
        if (empty($num)) {
            Redis::setex($r_name, $end - time(), 1);
            $num = 1;
        }
        $code =   date('ymd') . str_pad($num, 3, 0, 0);
        Redis::setex($r_name, Redis::ttl($r_name) ?? 0, $num + 1);
        return $code;
    }

    // public static function getLogNum($pre='WZ',$len=10,$date=true){
    //     $tenant_id = request()->header('tenant_id','');
    //     $order = Redis::get($pre.$tenant_id.'_code')??1;
    //     if($date)$code =   date('ymd'). str_pad($order,$len,0,0);
    //     else $code = str_pad($order,$len,0,0) ;
    //     Redis::set($pre.$tenant_id.'_code',$order+1);
    //     return $pre.($code);
    // }


    //打印唯一码
    public function printUniqCode(Request $request)
    {
        $arr_id = $request->get('arr_id');
        $count = $request->get('count');
        if (empty($arr_id) || empty($count)) return $this->error(__('base.fail'));
        $model = new $this->BaseModels();
        $res = $model->addUniqCount($arr_id, $count);
        if ($res['code'] != 200) return $this->error($res['msg']);
        return $this->success($res['data']);
    }

    //作废
    public function cancel(Request $request)
    {
        $id = $request->get('id');
        if (empty($id)) return $this->error(__('base.fail'));
        list($res, $msg) = (new $this->BaseModels())->cancel($id);
        if (!$res) return $this->error($msg);
        return $this->success();
    }


    //删除
    public function BaseDelete(Request $request)
    {
        $ids = $request->get('ids');
        if (empty($ids)) return $this->error(__('base.fail'));
        list($res, $msg) = $this->model->del($ids);
        if (!$res) return $this->error($msg);
        return $this->success($msg);
    }

    //打印日志
    public function OptionLog(Request $request)
    {
        $id = $request->get('arr_code');
        if (empty($id)) return $this->error(__('base.fail'));
        $data = (new $this->BaseModels())->log($id);
        return $this->success($data);
    }

    //收货预检---开始收货
    public function recvPreCheck(Request $request)
    {
        $id = $request->get('arr_id');
        $type = $request->get('recv_type');
        $scan_type = $request->get('scan_type', 0);
        if (empty($id) || empty($type)) return $this->error(__('base.vdt'));
        list($res, $msg) = $this->model->recvPreCheck($id, $type);
        if (empty($res)) return $this->error($msg);
        $data = $this->model->getRecvOrder($id, $scan_type);
        if ($data === false) return $this->error('登记单' . $id . '下的收货单出错,请联系管理员!');
        return $this->success($data);
    }

    //到货完成
    public function arrStatusDone(Request $request)
    {
        $id = $request->get('id');
        if (empty($id)) return $this->error(__('base.fail'));
        list($res, $msg) = $this->model->arrStatusDone($id);
        if (!$res) return $this->error($msg);
        return $this->success();
    }

    //确认供应商
    public  function supConfirm(Request $request)
    {
        $id = $request->get('arr_id');
        $confirm = $request->get('confirm');
        $confirm = json_decode($confirm, 1);
        if (empty($id) || empty($confirm)) return $this->error(__('base.vdt'));
        list($res, $msg) = $this->model->supConfirm($id, $confirm);
        if (!$res) return $this->error($msg);
        return $this->success();
    }

    //匹配入库单
    public function ibMatch(Request $request)
    {
        $vat = [
            'id' => 'required',
            'ib_ids' => 'required'
        ];
        $this->vatReturn($request, $vat);
        $params[] = $request->get('id');
        $params[] = $request->get('ib_ids');
        $params[] = $request->get('force', 0);
        $ibs = explode(',', $request->get('ib_ids'));
        try {
            $success = [];

            foreach ($ibs as $ib_id) {

                if (Redis::get('Wms:IbOrder-' . $ib_id)) {
                    return $this->error('入库单正在匹配中,请2分钟后重试!');
                } else {
                    $success[] = $ib_id;
                }
                // if (!Redis::setnx('Wms:IbOrder-'.$ib_id, date('Y-m-d H:i:s'))) {

                //     return $this->error('入库单正在匹配中,请5分钟后重试!');
                // }
                // Redis::expire('Wms:IbOrder-'.$ib_id,300);

            }
            foreach ($success as $ib_id) {
                Redis::setex('Wms:IbOrder-' . $ib_id, 120, date('Y-m-d H:i:s'));
            }
            list($res, $msg) = $this->model->ibMatch(...$params);
            foreach ($ibs as $ib_id) {
                Redis::del('Wms:IbOrder-' . $ib_id);
            }
            if (!$res) return $this->error($msg);
            if (is_array($msg)) return $this->success($msg);
            return $this->success('');
        } catch (\Throwable $th) {
            //throw $th;
            $res = false;
            $msg = $th->getMessage();
            foreach ($ibs as $ib_id) {
                Redis::del('Wms:IbOrder-' . $ib_id);
            }
            return  $this->error($msg);
        }
    }


    public function ibList(Request $request)
    {
        $vat = ['id' => 'required'];
        $data = $this->vatReturn($request, $vat);
        return $this->modelReturn('ibList', [$data['id']]);
    }


    public function Export(Request $request)
    {
        $params = $this->_beforeExport($request);
        $data = $this->model->Export($request->all());
        // $this->setExcelField();
        ob_end_clean();
        ob_start();

        return  Excel::download((new $this->exportName($data, $this->exportFormat())), $this->NAME . date('YmdHis') . mt_rand(100, 999) . '.xlsx');
    }

    public function buyExport(Request $request)
    {
        $data = $this->model->buyExport($request->all());
        // dd($data);
        // array_unshift($data,$this->exportField);date('YmdHis') . mt_rand(100, 999)
        ob_end_clean();
        ob_start();
        return  Excel::download((new ArrivalExport($data)), __('excel.arrival.buy_export') . date('YmdHis') . mt_rand(100, 999)  . '.xlsx');
    }

    //寄卖导出
    public function consignExport(Request $request)
    {
        $data = $this->model->buyExport($request->all());
        // dd($data);
        // array_unshift($data,$this->exportField);date('YmdHis') . mt_rand(100, 999)
        ob_end_clean();
        ob_start();
        $consign = new ConsignmentController($request);
        return  Excel::download((new ConsignExport($data, $consign->importFormat())), __('excel.consignment_order') . date('YmdHis') . mt_rand(100, 999)  . '.xlsx');
    }

    public function purchaseCost(Request $request)
    {
        $vat = [
            'arr_id' => 'required',
            'is_pur_cost' => 'required',
            'pur_cost' => 'required_if:is_pur_cost,1',
            'pur_cost.*.type' => 'required_if:is_pur_cost,1|integer|between:1,3|distinct',
            'pur_cost.*.num' => 'required_if:is_pur_cost,1|integer',
            'pur_cost.*.cost' => 'required_if:is_pur_cost,1|numeric',
        ];
        $msg = [
            'distinct' => __('repponse.subject_repeat'),
        ];
        $data = $this->vatReturn($request, $vat, $msg);
        return $this->modelReturn('purchaseCost', [$data]);
    }
    public function editPurCost(Request $request)
    {
        $vat = [
            'arr_id' => 'required',
            'pur_cost' => 'required',
            'pur_cost.*.type' => 'required_without:pur_cost.*.id|integer|between:1,3|distinct',
            'pur_cost.*.num' => 'required_without:pur_cost.*.id|integer',
            'pur_cost.*.cost' => 'required_without:pur_cost.*.id|numeric',
        ];
        $msg = [
            'distinct' => __('repponse.subject_repeat'),
        ];
        $data = $this->vatReturn($request, $vat, $msg);
        return $this->modelReturn('editPurCost', [$data]);
    }

    public function getPurCost(Request $request)
    {
        $vat = [
            'arr_id' => 'required'
        ];
        $data = $this->vatReturn($request, $vat);
        return $this->modelReturn('getPurCost', [$data]);
    }


    public function checkQcAndPutway(Request $request)
    {
        $vat = [
            'arr_id' => 'required',
        ];
        $data = $this->vatReturn($request, $vat);
        $arr_id = $data['arr_id'];
        return $this->modelReturn('checkQcAndPutway', [$arr_id]);
    }

    public function exportFormat()
    {
        $name = $this->NAME;
        $field = $this->exportField;
        $detail = [
            'name' => __('excel.details_name'),
            'with_as' => 'recv_details',
            'field' => [
                'product.sku' => __('excel.product.product.sku'),
                'product.product.product_sn' => __('excel.product.product.product_sn'),
                'product.product.name' => __('excel.product.product.name'),
                'product.spec_one' => __('excel.product.product.spec'),
                'bar_code' => __('excel.product.product.bar_code'),
                'quality_type' => __('excel.uniq_inv.quality_type'),
                'quality_level' => __('excel.uniq_inv.quality_level'),
                'uniq_code_1' => __('excel.uniq_inv.uniq_code'),
                'recv_num' => __('excel.uniq_inv.recv_num'),
                'sup_confirm' => __('excel.arrival.sup_confirm'),
                'ib_confirm' => __('excel.arrival.ib_confirm'),
            ],
            'order_name' => __('excel.order_name'),

        ];
        $format = [];
        return [
            'name' => $name,
            'format' => $format,
            'field' => $field,
            'detail' => $detail,
        ];
    }


    //修改供应商
    public  function supEdit(Request $request)
    {
        $id = $request->get('arr_id');
        $confirm = json_decode($request->get('confirm'), 1);
        $old_confirm = json_decode($request->get('old_confirm'), 1);
        if (empty($id) || !is_array($confirm) || !is_array($old_confirm)) return $this->error(__('base.vdt'));
        list($res, $msg) = $this->model->supEdit($id, $confirm,$old_confirm);
        if (!$res) return $this->error($msg);
        return $this->success();
    }


    //修改供应商
    public  function supShowEdit(Request $request)
    {
        $id = $request->get('arr_id');
        $confirm = $request->get('confirm');
        if (empty($id) || !is_array($confirm)) return $this->error(__('base.vdt'));
        list($res, $msg) = $this->model->supShowEdit($id, $confirm);
        if (!$res) return $this->error($msg);
        return $this->success();
    }
}
