<?php

namespace App\Http\Controllers\Admin\V2\Inventory;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Redis;
use App\Models\Admin\V2\UniqCodePrintLog;
use Maatwebsite\Excel\Facades\Excel;


class TransferOrderController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\TransferOrder';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = []; //获取全部Where条件
    protected $BA  = ['*']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $BLWhere  = [
        'WHERE' => ['WHERE', ''],
        'ORDER' => ['ORDER', ''],
        'NUMBER' => [['tr_code', 'deliver_no'],''],
    ]; //获取全部分页Where条件
    protected $BL  = ['*']; //获取全部分页选取字段 *是全部
    protected $BLOrder  = [['created_at', 'desc'],['id','desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required_without:code','code' => 'required_without:id']; //单个处理验证
    protected $code_field = 'tr_code';
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序
    protected $BaseUpdateVat = [
        'id' =>        'required',
        'out_warehouse_code' => 'exists:App\Models\Admin\V2\Warehouse,Warehouse_code',
        'in_warehouse_code' => 'exists:App\Models\Admin\V2\Warehouse,Warehouse_code|different:out_warehouse_code',
        'paysuccess_user' => 'exists:App\Models\AdminUser,id',
        'paysuccess_time' => 'date_format:"Y-m-d H:i:s"',
        'delivery_deadline' => 'date_format:"Y-m-d H:i:s"',
        'skus.*.bar_code' => 'required_without:skus.*.id|exists:App\Models\Admin\V2\ProductSpecAndBar,bar_code',
        'skus.*.sup_id' => 'required_without:skus.*.id|exists:App\Models\Admin\V2\Supplier,id',
        'skus.*.buy_price' => 'required_without:skus.*.id',
        'skus.*.num' => 'required_without:skus.*.id|integer',
        'skus.*.quality_level' => 'required_without:skus.*.id|in:"A","B","C","D","E"',
        'skus.*.batch_no' => 'required_without:skus.*.id',
    ]; //新增验证
    protected $BaseUpdate = [
        'id' => '',
        'out_warehouse_code' => '',
        'in_warehouse_code' => '',
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
    protected $exportField  ;

    public function setExcelField()
    {
        $this->NAME = __('excel.transfer_order.title');
        $this->exportField= [
            'type' => __('excel.transfer_order.type'),
            'doc_status' => __('excel.transfer_order.doc_status'),
            'tr_code' => __('excel.transfer_order.tr_code'),
            'send_status' => __('excel.transfer_order.send_status'),
            'recv_status' =>__('excel.transfer_order.recv_status'),
            'out_warehouse_code' => __('excel.transfer_order.out_warehouse_name'),
            'in_warehouse_code' => __('excel.transfer_order.in_warehouse_name'),
            'source_code' => __('excel.transfer_order.source_code'),
            'total' =>__('excel.transfer_order.total'),
            'send_num' => __('excel.transfer_order.send_num'),
            'recv_num' => __('excel.transfer_order.recv_num'),
            'diff_num' => __('excel.transfer_order.diff_num'),
            'log_prod_name' => __('excel.transfer_order.log_prod_code'),
            'deliver_no' => __('excel.transfer_order.deliver_no'),
            'users_txt.paysuccess_user' => __('excel.transfer_order.paysuccess_user'),
            'paysuccess_time' => __('excel.transfer_order.paysuccess_time'),
            'delivery_deadline' => __('excel.transfer_order.delivery_deadline'),
            'remark' => __('excel.remark'),
            'users_txt.suspender_user' => __('excel.transfer_order.suspender_user'),
            'paused_at' => __('excel.transfer_order.paused_at'),
            'recovery_at' => __('excel.transfer_order.recovery_at'),
            'flag'=>__('excel.flag'),
            'users_txt.created_user' => __('excel.purchase_order.created_user'),
            'created_at' => __('excel.uniq_record.created_at'),
            // 'users_txt.updated_user' => __('excel.purchase_order.updated_user'),
            // 'updated_at' => __('excel.uniq_record.updated_at'),
        ];
    }

    public function _exportFiledEdit(){
        $update = [
            __('excel.transfer_order.out_warehouse_name')=>'warehouse_txt.out_warehouse_name',
            __('excel.transfer_order.in_warehouse_name') =>'warehouse_txt.in_warehouse_name'
        ];
        return $update;
    }

    public function list()
    {
        $data = $this->model::where('doc_status', 1)->get()->toArray();
        return $this->success($data);
    }


    //新增
    public function add(Request $request)
    {
        $vat = [
            'type' => 'required',
            'out_warehouse_code' => 'required|exists:App\Models\Admin\V2\Warehouse,Warehouse_code',
            'in_warehouse_code' => 'required|exists:App\Models\Admin\V2\Warehouse,Warehouse_code|different:out_warehouse_code',
            'paysuccess_user' => 'required|exists:App\Models\AdminUser,id',
            'paysuccess_time' => 'required|date_format:"Y-m-d H:i:s"',
            'delivery_deadline' => 'date_format:"Y-m-d H:i:s"',
            'skus' => 'required',
            'skus.*.bar_code' => 'required|exists:App\Models\Admin\V2\ProductSpecAndBar,bar_code',
            'skus.*.sup_id' => 'required|exists:App\Models\Admin\V2\Supplier,id',
            'skus.*.buy_price' => 'required',
            'skus.*.num' => 'required|integer',
            'skus.*.quality_level' => [
                'required', Rule::in(["A", "B", "C", "D", "E"]),
            ],
            'skus.*.batch_no' => 'required',
            'skus.*.uniq_code' => 'present',
        ];
        $data = $this->vatReturn($request, $vat);

        $field  = [
            'type' => '',
            'out_warehouse_code' => '',
            'in_warehouse_code' => '',
            'paysuccess_user' => '',
            'paysuccess_time' => '',
            'delivery_deadline' => '',
            'log_prod_code' => '',
            'remark' => '',
            'skus' => '',
        ];

        $data = array_intersect_key($data, $field);

        return $this->modelReturn('add', [$data]);
    }

    //删除
    public function del(Request $request)
    {
        $vat = [
            'ids' => 'required',
        ];
        $data = $this->vatReturn($request, $vat);
        return $this->modelReturn('del', [$data]);
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
        list($res, $count) =  $this->model->updateDetails($data['id'], $skus);
        if ($res) {
            $data['total'] = $count;
        } else return $this->error($count);
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

    //撤回
    public function withdraw(Request $request)
    {
        $vat = [
            'ids' => 'required',
        ];
        $data = $this->vatReturn($request, $vat);
        return $this->modelReturn('withdraw', [$data]);
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
                'buy_price' => __('excel.buy_price'),
                'quality_type_txt' => __('excel.uniq_inv.quality_type'),
                'quality_level' => __('excel.uniq_inv.quality_level'),
                'uniq_code' => __('excel.uniq_record.uniq_code'),
                'supplier.name' => __('excel.supplier_name'),
                'inv_type_txt' => __('excel.inv_type'),
                'batch_no' => __('excel.arrival.lot_num'),
                'remark' => __('excel.purchase_detail.remark'),


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

    public function Export(Request $request)
    {
        
        $params = $this->_beforeExport($request);
        $res = $this->BaseLimit($request)->getData(1);
        if ($res['code'] != 200) return $this->error($res['msg']);
        $data = $res['data']['data'];
        ob_end_clean();
        ob_start();
        return  Excel::download((new $this->exportName($data, $params)), $this->NAME . date('YmdHis') . mt_rand(100, 999) . '.xlsx');
    }


}
