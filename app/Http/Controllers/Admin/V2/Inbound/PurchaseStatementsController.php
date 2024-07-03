<?php

namespace App\Http\Controllers\Admin\V2\Inbound;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Http\Request;


class PurchaseStatementsController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\PurchaseStatements';
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

    protected $NAME;
    protected $exportField;

    public function setExcelField()
    {
        $this->NAME = __('excel.purchase_statements.title');
        $this->exportField =  [
            'status_txt' =>  __('excel.purchase_statements.status_txt'),
            'code' =>  __('excel.purchase_statements.code'),
            'users_txt.order_user' =>  __('excel.purchase_statements.order_user'),
            'order_at' =>  __('excel.purchase_statements.order_at'),
            'origin_code' =>  __('excel.purchase_statements.origin_code'),
            'supplier.name' =>  __('excel.supplier_name'),
            'warehouse.warehouse_name' =>  __('excel.warehouse_name'),
            'num' =>  __('excel.purchase_statements.num'),
            'amount' =>  __('excel.purchase_statements.amount'),
            'settle_amount' =>  __('excel.purchase_statements.settle_amount'),
            'settled_amount' =>  __('excel.purchase_statements.settled_amount'),
            'users_txt.settled_user' =>  __('excel.purchase_statements.settled_user'),
            'settled_time' =>  __('excel.purchase_statements.settled_time'),
            'remark' =>  __('excel.remark'),
        ];
    }

    public function settle(Request $request)
    {
        $vat = [
            'ids' => 'required',
            // 'settled_amount' => 'required|numeric',
        ];
        $data = $this->vatReturn($request, $vat);
        return $this->modelReturn('settle', [$data['ids']]);
    }
}
