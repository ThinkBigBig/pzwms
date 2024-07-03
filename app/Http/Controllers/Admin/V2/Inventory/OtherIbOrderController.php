<?php

namespace App\Http\Controllers\Admin\V2\Inventory;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Models\Admin\V2\UniqCodePrintLog;


class OtherIbOrderController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\OtherIbOrder';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = []; //获取全部Where条件
    protected $BA  = ['*']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $BLWhere  = [
        'WHERE' => ['WHERE', ''],
        'ORDER' => ['ORDER', ''],
        'NUMBER' => [['oib_code', 'deliver_no'],''],
    ]; //获取全部分页Where条件
    protected $BL  = ['*']; //获取全部分页选取字段 *是全部
    protected $BLOrder  = [['created_at', 'desc'],['id','desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序
    protected $BaseUpdateVat = [
        'id' =>        'required',
        'warehouse_code' => 'exists:App\Models\Admin\V2\Warehouse,Warehouse_code',
        'paysuccess_user' => 'exists:App\Models\AdminUser,id',
        'paysuccess_time' => 'date_format:"Y-m-d H:i:s"',
        'skus.*.bar_code' => 'required_without:skus.*.id|exists:App\Models\Admin\V2\ProductSpecAndBar,bar_code',
        'skus.*.sup_id' => 'required_without:skus.*.id|exists:App\Models\Admin\V2\Supplier,id',
        'skus.*.buy_price' => 'required_without:skus.*.id',
        'skus.*.num' => 'required_without:skus.*.id|integer',
        'skus.*.inv_type' => 'required_without:skus.*.id|in:0,1',
    ]; //新增验证
    protected $BaseUpdate = [
        'id' => '',
        'warehouse_code' => '',
        'paysuccess_user' => '',
        'paysuccess_time' => '',
        'delivery_deadline' => '',
        'log_prod_code' => '',
        'remark' => '',
        'skus' => '',
        'updated_at' => ['type', 'date'],
        'updated_user' => ['type', 'user_id'],
    ]; //新增数据
    protected $BUWhere = ['id' => ['=', '']]; //新增数据

    protected $NAME ;
    protected $importName = '\App\Imports\ObIrderImport';
    protected $exportField = [

    ];

    public function setExcelField()
    {
        $this->NAME = __('excel.other_ib_order.title');
        $this->exportField = [
            'type' => __('excel.other_ib_order.type'),
            'oib_code' => __('excel.other_ib_order.oib_code'),
            'doc_status' => __('excel.other_ib_order.doc_status'),
            'recv_status_txt' => __('excel.other_ib_order.recv_status_txt'),
            'warehouse_code' => __('excel.warehouse_name'),
            'source_code' => __('excel.other_ib_order.source_code'),
            'sum_buy_price'=>__('excel.other_ib_order.sum_buy_price'),
            'total' => __('excel.other_ib_order.total'),
            'recv_num' => __('excel.other_ib_order.recv_num'),
            'wait_recv_num' => __('excel.other_ib_order.wait_recv_num'),
            'users_txt.paysuccess_user' => __('excel.other_ib_order.paysuccess_user'),
            'paysuccess_time' => __('excel.other_ib_order.paysuccess_time'),
            'remark' => __('excel.remark'),
        ];
    }
    
    public function _exportFiledEdit(){
        $update = [
            __('excel.warehouse_name')=>'warehouse_txt.warehouse_name',
        ];
        return $update;
    }

    //新增
    public function add(Request $request)
    {
        $vat = [
            'type' => 'required',
            'warehouse_code' => 'required|exists:App\Models\Admin\V2\Warehouse,Warehouse_code',
            'paysuccess_user' => 'required|exists:App\Models\AdminUser,id',
            'paysuccess_time' => 'required|date_format:"Y-m-d H:i:s"',
            'skus' => 'required',
            'skus.*.bar_code' => 'required|exists:App\Models\Admin\V2\ProductSpecAndBar,bar_code',
            'skus.*.sup_id' => 'required|exists:App\Models\Admin\V2\Supplier,id',
            'skus.*.buy_price' => 'required',
            'skus.*.num' => 'required|integer',
            'skus.*.inv_type' => 'required|in:0,1',
        ];
        $data = $this->vatReturn($request, $vat);
        $field  = [
            'type' => '',
            'warehouse_code' => '',
            'paysuccess_user' => '',
            'paysuccess_time' => '',
            'remark' => '',
            'skus' => '',
        ];

        $data = array_intersect_key($data, $field);

        return $this->modelReturn('add', [$data]);
    }

    //修改
    public function _updateBefore($data)
    {
        //判断单据状态是否是暂存中
        $item = $this->model->getItem($data['id']);
        if ($item) {
            if ($item->doc_status != 0) return $this->error(__('response.doc_status_not_edit'));
            //修改
        } else return $this->error(__('response.doc_not_exists'));
    }

    public function _updateFrom($data)
    {
        //更新
        if (empty($data['skus'])) {
            //只更新申请单
            return $data;
        } else {
            //更新申请单及明细
            $skus = $data['skus'];
            unset($data['skus']);
        }
        list($res, $res) =  $this->model->updateDetails($data['id'], $skus);
        if ($res) {
            $data['total'] = $res['total'];
            $data['sum_buy_price'] = $res['sum_buy_price'];
        } else return $this->error($data['id']);
        return $data;
    }

    //暂停
    public function pause(Request $request)
    {
        $vat = [
            'ids' => 'required',
            'paused_reason' => 'required',
        ];
        $data = $this->vatReturn($request, $vat);
        return $this->modelReturn('pause', [$data]);
    }


    //审核
    public function approve(Request $request)
    {
        $vat = [
            'ids' => 'required',
            'pass' => 'required|in:0,1',
        ];
        $data = $this->vatReturn($request, $vat);
        return $this->modelReturn('approve', [$data]);
    }

    public function __call($method, $parameters)
    {
        $vat = [
            'ids' => 'required',
        ];
        $data = $this->vatReturn(request(), $vat);
        return $this->modelReturn($method, [$data]);
    }
}
