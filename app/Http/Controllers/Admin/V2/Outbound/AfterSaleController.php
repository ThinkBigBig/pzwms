<?php

namespace App\Http\Controllers\Admin\V2\Outbound;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Http\Request;


class AfterSaleController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\AfterSaleOrder';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = []; //获取全部Where条件
    protected $BA  = ['*']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $BLWhere  = [
        'WHERE' => ['WHERE', ''],
        'ORDER' => ['ORDER', ''],
        'NUMBER' => [['origin_code', 'code', 'sale_order.third_no'], ''],
    ]; //获取全部分页Where条件
    protected $BL  = ['*']; //获取全部分页选取字段 *是全部
    protected $BLOrder  = [['created_at', 'desc'], ['id', 'desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序
    protected $BaseUpdateVat = [
        'id' =>        'required',
        'sale_code' => 'exists:App\Models\Admin\V2\WmsOrder,code',
        'refund_reason' => 'between :0,21',
        'deadline' => 'date_format:"Y-m-d H:i:s"',
        'order_user' => 'exists:App\Models\AdminUser,id',
        'order_at' => 'date_format:"Y-m-d H:i:s"',
        'product_code' => 'exists:App\Models\Admin\V2\WmsLogisticsProduct,product_code',
        'skus' => 'required',
        'skus.*.id' => 'required|integer',
        // 'skus.*.detail_id' => 'required_without:skus.*.id|integer',
        'skus.*.num' => 'required_without:skus.*.id|integer',
        'skus.*.return_num' => 'required_without:skus.*.id|integer',
        'skus.*.refund_amount' => 'required_without:skus.*.id|numeric',
    ]; //新增验证
    protected $BaseUpdate = [
        'id' => '',
        'skus' => '',
        'sale_code' => '',
        'refund_reason' => '',
        'deadline' => '',
        'apply_no' => '',
        'product_code' => '',
        'deliver_no' => '',
        'order_user' => '',
        'order_at' => '',
        'remark' => '',
        'updated_at' => ['type', 'date'],
        'admin_user_id' => ['type', 'user_id'],
    ]; //新增数据
    protected $BUWhere = ['id' => ['=', '']]; //新增数据

    protected $NAME;
    protected $exportField;

    public function setExcelField()
    {
        $this->NAME = __('excel.after_sale_order.title');
        $this->exportField = [
            'apply_at' => __('excel.after_sale_order.apply_at'),
            'deadline' => __('excel.after_sale_order.deadline'),
            'sale_order.third_no' => __('excel.after_sale_order.sale_order_third_no'),
            'sale_order.order_at' => __('excel.after_sale_order.sale_order_order_at'),
            'code' => __('excel.after_sale_order.code'),
            'status_txt' => __('excel.after_sale_order.status_txt'),
            'return_status_txt' => __('excel.after_sale_order.return_status_txt'),
            'refund_status_txt' => __('excel.after_sale_order.refund_status_txt'),
            'type_txt' => __('excel.after_sale_order.type_txt'),
            'sale_order.buyer_account' => __('excel.after_sale_order.sale_order_buyer_account'),
            'refund_time' => __('excel.after_sale_order.refund_time'),
            'origin_code' => __('excel.after_sale_order.origin_code'),
            'sale_order.shop_name' => __('excel.after_sale_order.sale_order_shop_name'),
            'warehouse_code' => __('excel.after_sale_order.warehouse_code'),
            'source_type' => __('excel.after_sale_order.source_type'),
            'log_product.product_name' => __('excel.after_sale_order.log_product_product_name'),
            'deliver_no' => __('excel.after_sale_order.deliver_no'),
            'return_num' => __('excel.after_sale_order.return_num'),
            'refund_reason' => __('excel.after_sale_order.refund_reason'),
            'refund_amount' => __('excel.after_sale_order.refund_amount'),
            'sale_order.deliver_status' => __('excel.after_sale_order.sale_order_deliver_status'),
            'users_txt.audit_user' => __('excel.after_sale_order.audit_user'),
            'users_txt.refund_user' => __('excel.after_sale_order.refund_user'),
            'users_txt.confirm_user' => __('excel.after_sale_order.confirm_user'),
            'users_txt.created_user' => __('excel.after_sale_order.created_user'),
            'created_at' => __('excel.after_sale_order.created_at'),
        ];
    }

    public function reasonList()
    {
        return $this->success($this->model->refundReasonList());
    }


    public function add(Request $request)
    {
        $vat = [
            'sale_code' => 'required|exists:App\Models\Admin\V2\WmsOrder,code',
            'refund_reason' => 'required|between :0,21',
            'deadline' => 'required|date_format:"Y-m-d H:i:s"',
            'order_user' => 'exists:App\Models\AdminUser,id',
            'order_at' => 'date_format:"Y-m-d H:i:s"',
            'product_code' => 'exists:App\Models\Admin\V2\WmsLogisticsProduct,product_code',
            'skus' => 'required',
            'skus.*.detail_id' => 'required|integer',
            'skus.*.bar_code' => 'required',
            'skus.*.num' => 'required|integer',
            'skus.*.return_num' => 'required|integer',
            'skus.*.refund_amount' => 'required|numeric',
        ];
        $data = $this->vatReturn($request, $vat);
        $field  = [
            'sale_code' => '',
            'refund_reason' => '',
            'deadline' => '',
            'apply_no' => '',
            'product_code' => '',
            'deliver_no' => '',
            'order_user' => '',
            'order_at' => '',
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
        if (!empty($data['deadline'])) {
            $data['deadline'] = strtotime($data['deadline']);
        }
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
            $data['apply_num'] = $count['apply_num'];
            $data['return_num'] = $count['return_num'];
            $data['refund_amount'] = $count['refund_amount'];
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

    //发货追回
    public function recoverIb(Request $request)
    {
        $vat = [
            'id' => 'required',
            'warehouse_code' => 'required|exists:App\Models\Admin\V2\Warehouse,Warehouse_code',
            'num' => 'required|integer',
        ];
        $data = $this->vatReturn($request, $vat);
        return $this->modelReturn('recoverIb', [$data]);
    }

    //退货入库
    public function returnIb(Request $request)
    {
        $vat = [
            'id' => 'required',
            'warehouse_code' => 'required|exists:App\Models\Admin\V2\Warehouse,Warehouse_code',
            'num' => 'required|integer',
            'product_code' => 'required',
            'deliver_no' => 'required',

        ];
        $data = $this->vatReturn($request, $vat);
        return $this->modelReturn('returnIb', [$data]);
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
            'name' => __('excel.product.product.spec_detail'),
            'with_as' => 'details',
            'field' => [
                'product.spec_one' => __('excel.product.product.spec'),
                'product.code' => __('excel.product.product.code'),
                'bar_code' => __('excel.product.product.bar_code'),
                'product.product.name' => __('excel.product.product.name'),
                'product.sku' => __('excel.product.product.sku'),
                'price' => __('excel.after_sale_detail.price'),
                'amount' => __('excel.after_sale_detail.amount'),
                'refund_amount' => __('excel.after_sale_detail.refund_amount'),
                'remark' => __('excel.remark'),
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
}
