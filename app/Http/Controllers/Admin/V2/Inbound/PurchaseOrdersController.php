<?php

namespace App\Http\Controllers\Admin\V2\Inbound;

use App\Exports\ExportView;
use App\Http\Controllers\Admin\V2\BaseController;
use App\Logics\wms\Purchase;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PurchaseOrdersController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\PurchaseOrders';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = []; //获取全部Where条件
    protected $BA  = ['*']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $BLWhere  = [
        'id' => ['=', ''],
        'WHERE' => ['WHERE', ''],
        'ORDER' => ['ORDER', ''],
        'NUMBER' => [['third_code', 'code'],''],
    ]; //获取全部分页Where条件
    protected $BL  = ['*']; //获取全部分页选取字段 *是全部
    protected $BLOrder  = [['created_at', 'desc'], ['id', 'desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序
    protected $BaseUpdateVat = [
        'id' =>        'required',
        'warehouse_code' => 'exists:App\Models\Admin\V2\Warehouse,Warehouse_code',
        'sup_id' => 'exists:App\Models\Admin\V2\Supplier,id',
        'order_user' => 'exists:App\Models\AdminUser,id',
        'order_at' => 'date_format:"Y-m-d H:i:s"',
        'send_at' => 'date_format:"Y-m-d H:i:s',
        'estimate_receive_at' => 'date_format:"Y-m-d H:i:s',
        'skus.*.bar_code' => 'required_without:skus.*.id|exists:App\Models\Admin\V2\ProductSpecAndBar,bar_code',
        'skus.*.buy_price' => 'required_without:skus.*.id',
        'skus.*.num' => 'required_without:skus.*.id|integer',

    ]; //新增验证
    protected $BaseUpdate = [
        'id' => '',
        'warehouse_code' => '',
        'sup_id' => '',
        'order_user' => '',
        'order_at' => '',
        'send_at' => '',
        'estimate_receive_at' => '',
        'skus' => '',
        'log_prod_code' => '',
        'deliver_no' => '',
        'pay_status'=>'',
        'pre_amount' => '',
        'remark' => '',
        'updated_at' => ['type', 'date'],
        'updated_user' => ['type', 'user_id'],
    ]; //新增数据
    protected $BUWhere = ['id' => ['=', '']]; //新增数据

    protected $NAME;
    protected $importName = '\App\Imports\PurchaseOrdersImport';
    protected $exportField;
    public function setExcelField()
    {
        $this->NAME = __('excel.purchase_order.title');
        $this->exportField = [
            'status_txt' => __('excel.purchase_order.status_txt'),
            'receive_status_txt' => __('excel.purchase_order.receive_status_txt'),
            'code' => __('excel.purchase_order.code'),
            'supplier.name' => __('excel.supplier_name'),
            'warehouse.warehouse_name' => __('excel.warehouse_name'),
            'source_type' => __('excel.purchase_order.source_type'),
            'third_code' => __('excel.purchase_order.third_code'),
            'order_at' => __('excel.purchase_order.order_at'),
            'pay_status' => __('excel.purchase_order.pay_status'),
            'num' => __('excel.purchase_order.num'),
            'amount' => __('excel.purchase_order.amount'),
            'wait_num' => __('excel.purchase_order.wait_num'),
            'received_num' => __('excel.purchase_order.received_num'),
            'recv_rate' => __('excel.purchase_order.recv_rate'),
            'estimate_receive_at' => __('excel.purchase_order.estimate_receive_at'),
            'users_txt.order_user' => __('excel.purchase_order.order_user'),
            'remark' => __('excel.remark'),
            'flag'=>__('excel.flag'),
            'users_txt.created_user' => __('excel.purchase_order.created_user'),
            'created_at' => __('excel.purchase_order.created_at'),
            'users_txt.updated_user' => __('excel.purchase_order.updated_user'),
            'updated_at' => __('excel.purchase_order.updated_at'),
        ];
    }

    public function add(Request $request)
    {
        $vat = [
            'warehouse_code' => 'required|exists:App\Models\Admin\V2\Warehouse,Warehouse_code',
            'pay_status'=>'in:0,1',
            'sup_id' => 'required|exists:App\Models\Admin\V2\Supplier,id',
            'order_user' => 'required|exists:App\Models\AdminUser,id',
            'order_at' => 'required|date_format:"Y-m-d H:i:s"',
            'send_at' => 'date_format:"Y-m-d H:i:s',
            'estimate_receive_at' => 'date_format:"Y-m-d H:i:s',
            'skus' => 'required',
            'skus.*.bar_code' => 'required|exists:App\Models\Admin\V2\ProductSpecAndBar,bar_code',
            'skus.*.buy_price' => 'required',
            'skus.*.num' => 'required|integer',
        ];
        $data = $this->vatReturn($request, $vat);
        $field  = [
            'sup_id' => '',
            'warehouse_code' => '',
            'pre_amount' => '',
            'order_user' => '',
            'order_at' => '',
            'estimate_receive_at' => '',
            'send_at' => '',
            'log_prod_code' => '',
            'deliver_no' => '',
            'third_code' => '',
            'pay_status'=>'',
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
            if ($item->status != 0) return $this->error(__('response.doc_status_not_edit'));
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
        list($res, $count) =  $this->model->updateDetails($data['id'], $skus);
        if ($res) {
            $data['num'] = $count['num'];
            $data['amount'] = $count['amount'];
        } else return $this->error($count);
        return $data;
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
        if ($method == 'test') {
            $id = request()->get('id');
            $amount = request()->get('amount');
            return $this->modelReturn('confirm', [$id, $amount]);
        }
        $vat = [
            'ids' => 'required',
        ];
        $data = $this->vatReturn(request(), $vat);
        return $this->modelReturn($method, [$data]);
    }

    public function exportFormat()
    {
        $name = $this->NAME;
        $field = $this->exportField;
        $detail = [
            'name' => __('excel.purchase_detail.title'),
            'with_as' => 'details',
            'field' => [
                'product.sku' => __('excel.purchase_detail.product_sku'),
                'product.product.product_sn' => __('excel.purchase_detail.product_sn'),
                'product.product.name' => __('excel.purchase_detail.product_name'),
                'product.spec_one' => __('excel.purchase_detail.product_spec'),
                'num' => __('excel.purchase_detail.num'),
                'buy_price' => __('excel.purchase_detail.buy_code'),
                'amount' => __('excel.purchase_detail.amount'),
                'recv_num' => __('excel.purchase_detail.recv_num'),
                'normal_count' => __('excel.purchase_detail.normal_count'),
                'flaw_count' => __('excel.purchase_detail.flaw_count'),
                'remark' => __('excel.purchase_detail.remark').'1',
            ]

        ];
        $format = [];
        return [
            'name' => $name,
            'format' => $format,
            'field' => $field,
            'detail' => $detail,
        ];

    }

    public function importFormat()
    {
        $count = 2;
        $field = $this->exportField;
        $rule = [
            'required' => $this->required,
            'auto' => [
                'created_user' => request()->header('user_id'),
                'sup_code'=>['method',"getErpCode",['G']],
            ],
            'relation' => [
                'approver' => [
                    'model' => ['App\Models\AdminUsers', 'username', 'id'],
                    'default' => '',
                ],
            ],
            'uniq_columns' => 'sup_code',

        ];
        return [
            'count' => $count,
            'rule' => $rule,
            'field' => $field,
        ];
    }


}
