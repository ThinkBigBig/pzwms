<?php

namespace App\Http\Controllers\Admin\V2\Inbound;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Http\Request;

class ConsignSettleRuleController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\ConsignSettleRule';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = []; //获取全部Where条件
    protected $BA  = ['*']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $BLWhere  = [
        'id' => ['=', ''],
        'WHERE' => ['WHERE', ''],
        'ORDER' => ['ORDER', ''],
        'NUMBER' => [['third_code', 'code'], ''],
    ]; //获取全部分页Where条件
    protected $BL  = ['*']; //获取全部分页选取字段 *是全部
    protected $BLOrder  = [['created_at', 'desc'], ['id', 'desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序

    protected $BaseCreateVat = [
        'name' => 'required',
        'sort' => 'required|integer',
        'start_at' => 'required|date_format:"Y-m-d H:i:s"',
        'end_at' => 'required|date_format:"Y-m-d H:i:s',
        'category_code' => 'required|exists:App\Models\Admin\V2\ConsignSettleCategory,code',
        'object' => 'required|in:1,2',
        'status' => 'required|in:0,1',
        'content' => 'required|array',
        'formula' => 'required',
    ];

    protected $BaseCreate = [
        'name' => '',
        'code' => '',
        'start_at' => '',
        'end_at' => '',
        'category_code' => '',
        'status' => '',
        'sort' => '',
        'remark' => '',
        'object' => '',
        'formula' => '',
        'content' => '',
        'created_at' => ['type', 'date'],
        'created_user' => ['type', 'user_id'],
    ];

    protected $BaseUpdateVat = [
        'id' => 'required',
        'sort' => 'integer',
        'start_at' => 'date_format:"Y-m-d H:i:s"',
        'end_at' => 'date_format:"Y-m-d H:i:s',
        'category_code' => 'exists:App\Models\Admin\V2\ConsignSettleCategory,code',
        'object' => 'in:1,2',
        'status' => 'in:0,1',
        'content' => 'array',
    ];

    //新增验证
    protected $BaseUpdate = [
        'id' => '',
        'category_code' => '',
        'status' => '',
        'sort' => '',
        'name' => '',
        'object' => '',
        'start_at' => '',
        'end_at' => '',
        'remark' => '',
        'formula' => '',
        'content' => '',
        'updated_at' => ['type', 'date'],
        'updated_user' => ['type', 'user_id'],
    ]; //新增数据
    protected $BUWhere = ['id' => ['=', '']]; //新增数据

    protected $NAME;
    protected $exportField;
    public function setExcelField()
    {
        $this->NAME = __('excel.consignment_rule');
        $this->exportField = [
            'code' => __('excel.consignment_rule_code'),
            'status_txt' => __('excel.status_txt'),
            'sort' => __('excel.pre_strategy.sort'),
            'name' => __('excel.consignment_rule_name'),
            'object_txt' => __('excel.consignment_rule_object'),
            'start_at' => __('excel.consignment_rule_start_at'),
            'end_at' => __('excel.consignment_rule_end_at'),
            'remark' => __('excel.remark'),
            'users_txt.created_user' => __('excel.purchase_order.created_user'),
            'created_at' => __('excel.purchase_order.created_at'),
            'users_txt.updated_user' => __('excel.purchase_order.updated_user'),
            'updated_at' => __('excel.purchase_order.updated_at'),
        ];
    }

    public function  _createFrom($create_data)
    {
        list($res, $formula) = $this->verifyRule($create_data['formula']);
        if (!$res) return $this->error(__('response.formula'));
        $create_data['formula'] = $formula;
        $create_data['content'] = json_encode($create_data['content'], 1);
        $create_data['code'] = $this->getErpCode('JSGZ');
        return $create_data;
    }

    public function  _updateFrom($update_data)
    {
        if (!empty($update_data['formula'])) {
            list($res, $formula) = $this->verifyRule($update_data['formula']);
            if (!$res) return $this->error(__('response.formula'));
            $update_data['formula'] = $formula;
        }
        if (!empty($update_data['content'])) $update_data['content'] = json_encode($update_data['content'], 1);
        return $update_data;
    }

    public function test(Request $request){
        $this->model->settleByRule($request->get('ids'));
    }

    //验证规则
    private function verifyRule($formula)
    {
        $formula = $this->model->ruleFormat($formula);
        $res = $this->model->ruleVerify($formula);
        if($res !== false)$res = true;
        // if(!$res) return $this->error();
        return [$res,$formula];
    }

    public function getRuleColumn(){
        return $this->success($this->model->ruleColumn());
    }
}
