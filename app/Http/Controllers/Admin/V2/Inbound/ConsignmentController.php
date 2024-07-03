<?php

namespace App\Http\Controllers\Admin\V2\Inbound;

use App\Exports\ExportView;
use App\Http\Controllers\Admin\V2\BaseController;
use App\Logics\wms\Purchase;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ConsignmentController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\Consignment';
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
        'pre_amount' => '',
        'remark' => '',
        'updated_at' => ['type', 'date'],
        'updated_user' => ['type', 'user_id'],
    ]; //新增数据
    protected $BUWhere = ['id' => ['=', '']]; //新增数据

    protected $NAME;
    protected $importName = '\App\Imports\ConsignOrdersImport';
    protected $exportField;
    public function setExcelField()
    {
        $this->NAME = __('excel.consignment_order');
        $this->exportField = [
            'status_txt' => __('excel.purchase_order.status_txt'),
            'receive_status_txt' => __('excel.purchase_order.receive_status_txt'),
            'code' => __('excel.purchase_order.code'),
            'supplier.name' => __('excel.supplier_name'),
            'warehouse.warehouse_name' => __('excel.warehouse_name'),
            'order_at' => __('excel.purchase_order.order_at'),
            'third_code' => __('excel.purchase_order.third_code'),
            'source_type' => __('excel.purchase_order.source_type'),
            'num' => __('excel.purchase_order.num'),
            'wait_num' => __('excel.purchase_order.wait_num'),
            'received_num' => __('excel.purchase_order.received_num'),
            'recv_rate' => __('excel.purchase_order.recv_rate'),
            'amount' => __('excel.purchase_order.amount'),
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
            'order_user' => '',
            'order_at' => '',
            'estimate_receive_at' => '',
            'send_at' => '',
            'log_prod_code' => '',
            'deliver_no' => '',
            'third_code' => '',
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
        $vat = [
            'ids' => 'required',
        ];
        $data = $this->vatReturn(request(), $vat);
        return $this->modelReturn($method, [$data]);
    }

    public function exportFormat()
    {
        $name = __('excel.consignment_order');
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
                'buy_price' => __('excel.consign_buy_price'),
                'amount' => __('excel.consign_amount'),
                'recv_num' => __('excel.purchase_detail.recv_num'),
                'normal_count' => __('excel.purchase_detail.normal_count'),
                'flaw_count' => __('excel.purchase_detail.flaw_count'),
                'wait_num' => __('excel.purchase_order.wait_num'),
                'remark' => __('excel.purchase_detail.remark'),
            ],
            'order_name'=> __('excel.base_info'),

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
        $count = 3;
        $field = [
            'code' => __('excel.purchase_order.code'),
            'sup_id' => __('excel.supplier_name'),
            'sup_id_card' => __('excel.id_card'),
            'warehouse_code' => __('excel.warehouse_name'),
            'estimate_receive_at' => __('excel.purchase_order.estimate_receive_at'),
            'order_user' => __('excel.purchase_order.order_user'),
            'order_at' => __('excel.purchase_order.order_at'),
            'remark' => __('excel.remark'),
            'log_prod_code'=>__('excel.shipping_order.ob_order_deliver_type'),
            'deliver_no'=>__('excel.shipping_order.ob_order_deliver_no'),
            'send_at'=>__('excel.send_at'),
            'third_code' => __('excel.purchase_order.third_code'),
        ];
        $detail = [
            'name' => __('excel.purchase_detail.title'),
            'with_as' => 'details',
            'field' => [
                'product.product.product_sn' => __('excel.purchase_detail.product_sn'),
                'product.spec_one' => __('excel.purchase_detail.product_spec'),
                'num' => __('excel.purchase_detail.num'),
                'buy_price' => __('excel.consign_buy_price'),
                'remark' => __('excel.purchase_detail.remark').'1',
            ],
            'order_name'=> __('excel.base_info'),

        ];
        $rule = [
            'required' => $this->required,
            'auto' => [
                'created_user' => request()->header('user_id'),
                'code'=>['method',"getErpCode",['JMD']],
                'detail.admin_user_id'=>request()->header('user_id'),
                'detail.bar_code'=>['method',"getBarCode",['detail.product_sn','detail.spec_one']],
            ],
            // 'default_val'=>[
            //     'send_at'=>null,
            //     'estimate_receive_at'=>null
            // ],
            'relation' => [
                'sup_id' => [
                    'model' => ['App\Models\Admin\V2\Supplier', 'name', 'id'],
                    'default' => '',
                ],
                'warehouse_code' => [
                    'model' => ['App\Models\Admin\V2\Warehouse', 'warehouse_name', 'warehouse_code'],
                    'default' => '',
                ]
            ],
            'uniq_columns' => 'code',

        ];
        $format = [
            __('excel.supplier_name') => ['color' => 'red'],
            __('excel.warehouse_name') => ['color' => 'red'],
            __('excel.purchase_detail.product_sn') => ['color' => 'red'],
            __('excel.purchase_detail.product_spec') => ['color' => 'red'],
            __('excel.purchase_detail.num') => ['color' => 'red'],
            __('excel.consign_buy_price') => ['color' => 'red'],
        ];
        return [
            'name' => __('excel.consignment_order'),
            'format' => $format,
            'count' => $count,
            'rule' => $rule,
            'field' => $field,
            'detail' => $detail,
        ];
    }

}
