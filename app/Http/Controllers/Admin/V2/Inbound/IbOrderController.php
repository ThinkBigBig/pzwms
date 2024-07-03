<?php

namespace App\Http\Controllers\Admin\V2\Inbound;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Models\Admin\V2\UniqCodePrintLog;
use Maatwebsite\Excel\Facades\Excel;


class IbOrderController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\IbOrder';
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
    protected $BLOrder  = [ ['id', 'desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序
    protected $code_field = 'ib_code';
    protected $BaseCreateVat = [
        'buy_id' => 'required',
        // 'ib_type'=>'required',

    ]; //新增验证
    protected $BaseCreate = [
        'sup_id' => '',
        'buy_id' => '',
        'ib_type' => '',
        'remark' => '',
        'created_at' => ['type', 'date'],
    ]; //新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
        'remark'  =>        'required',

    ]; //新增验证
    protected $BaseUpdate = [
        'remark' => '',
        'updated_at' => ['type', 'date'],
        'updated_user' => ['type', 'user_id'],
    ]; //新增数据
    protected $BUWhere = ['id' => ['=', '']]; //新增数据

    protected $NAME;
    protected $exportField;
    public function setExcelField()
    {
        $this->NAME = __('excel.ib_order.title');
        $this->exportField = [
            'sku'=>__('excel.purchase_detail.product_sku'),
            'ib_type_txt' => __('excel.ib_order.ib_type'),
            'ib_code' => __('excel.ib_order.ib_code'),
            'source_code' => __('excel.ib_order.source_code'),
            'erp_no' => __('excel.ib_order.erp_no'),
            'third_no' => __('excel.ib_order.third_no'),
            'doc_status_txt' => __('excel.ib_order.doc_status'),
            'recv_status_txt' => __('excel.ib_order.recv_status_txt'),
            'warehouse.warehouse_name' => __('excel.warehouse_name'),
            're_total' => __('excel.ib_order.re_total'),
            'rd_total' => __('excel.ib_order.rd_total'),
            'normal_count' => __('excel.ib_order.normal_count'),
            'flaw_count' => __('excel.ib_order.flaw_count'),
            // 'diff_num' => __('excel.ib_order.diff_num'),
            'deliver_no' => __('excel.ib_order.deliver_no'),
            'paysuccess_time' => __('excel.ib_order.paysuccess_time'),
        ];
    }

    public function _createFrom($create_data)
    {

        $create_data['created_user'] = request()->header('user_id');
        while (1) {
            $create_data['ib_code']  = $this->getErpCode('RKD', 10);
            if ($this->checkRepeat('ib_code', $create_data['ib_code'])) break;
        }
        $create_data['erp_code'] = $create_data['ib_code'];
        return $create_data;
    }

    public function list()
    {
        $data = $this->model::where('doc_status', 1)->orderBy('id', 'desc')->get()->append(['wh_name'])->makeHidden('warehouse')->toArray();
        return $this->success($data);
    }

    public function exportFormat()
    {
        $name = $this->NAME;
        $field = $this->exportField;
        $detail = [
            'name' => __('excel.purchase_detail.title'),
            'with_as' => 'details',
            'with'=>'details,details.product,details.product,details.supplier',
            'field' => [
                'product.sku' => __('excel.purchase_detail.product_sku'),
                'product.product.product_sn' => __('excel.purchase_detail.product_sn'),
                'product.product.name' => __('excel.purchase_detail.product_name'),
                'product.spec_one' => __('excel.purchase_detail.product_spec'),
                'buy_price' => __('excel.buy_price'),
                'quality_type' => __('excel.uniq_inv.quality_type'),
                'quality_level' => __('excel.uniq_inv.quality_level'),
                'uniq_code' => __('excel.uniq_record.uniq_code'),
                'supplier.name' => __('excel.supplier_name'),
                're_total' => __('excel.ib_order.re_total'),
                'rd_total' => __('excel.ib_order.rd_total'),
                'normal_count' => __('excel.ib_order.normal_count'),
                'flaw_count' => __('excel.ib_order.flaw_count'),
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
