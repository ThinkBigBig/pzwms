<?php

namespace App\Http\Controllers\Admin\V2\Common;

use App\Http\Controllers\Admin\V2\BaseController;
use App\Logics\BaseLogic;
use App\Logics\wms\Suppiler;
use App\Models\Admin\V2\WmsSupplierDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class SupplierController extends BaseController
{
    protected $BaseModels = 'App\Models\Admin\V2\Supplier';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = []; //获取全部Where条件
    protected $BA  = ['*']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $BLWhere  = [
        'name' => ['like', ''],
        'sup_code' => ['=', ''],
        'sup_status' => ['=', ''],
        'WHERE' => ['WHERE', ''],
    ]; //获取全部分页Where条件
    protected $BL  = ['*']; //获取全部分页选取字段 *是全部
    protected $BLOrder  = [['sort', 'desc'], ['created_at', 'desc'], ['id', 'desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序

    protected $BaseCreateVat = [
        'name' => 'required', //分类名称
    ]; //新增验证
    protected $BaseCreate = [
        'name' => '',
        // 'sup_status'=>'',
        'type' => '',
        'id_card' => '',
        'email' => '',
        'contact_name' => '',
        'contact_phone' => '',
        'contact_landline' => '',
        'contact_addr' => '',
        'bank_number' => '',
        'account_name' => '',
        'bank_card' => '',
        'bank_name' => '',
        'id_card_front' => '',
        'id_card_reverse' => '',
        'remark' => '',
        'status' => '',
        'approver' => '',
        'sort' => '',
        'id_card_date' => ['type', 'date'],
        'created_at' => ['type', 'date'],
    ]; //新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
    ]; //新增验证
    protected $BaseUpdate = [
        'name' => '',
        // 'sup_status'=>'',
        'type' => '',
        'id_card' => '',
        'email' => '',
        'contact_name' => '',
        'contact_phone' => '',
        'contact_landline' => '',
        'contact_addr' => '',
        'bank_number' => '',
        'account_name' => '',
        'bank_card' => '',
        'bank_name' => '',
        'id_card_front' => '',
        'id_card_reverse' => '',
        'remark' => '',
        'status' => '',
        'sort' => '',
        'id_card_date' => ['type', 'date'],
        'updated_at' => ['type', 'date'],
    ]; //新增数据
    protected $BUWhere = ['id' => ['=', '']]; //新增数据

    protected $NAME;
    protected $exportField;
    protected $importFields;
    protected $repeat = ['name'];
    protected  $required = ['name'];

    public function setExcelField()
    {
        $this->NAME = __('excel.supplier.title');
        $this->exportField = [
            'sup_code' => __('excel.supplier.sup_code'),
            'name' => __('excel.supplier.name'),
            'sup_status_txt' => __('excel.supplier.sup_status_txt'),
            'created_at' => __('excel.supplier.created_at'),
            'type_txt' => __('excel.supplier.type_txt'),
            'status_txt' => __('excel.supplier.status_txt'),
            'approver_txt' => __('excel.supplier.approver_txt'),
            'approved_at' => __('excel.supplier.approved_at'),
        ];

        $this->importFields = [
            'sup_code' => __('excel.supplier.sup_code'),
            'name' => __('excel.supplier.name') . '|required',
            'sup_status_txt' => __('excel.supplier.sup_status_txt'),
            'created_at' => __('excel.supplier.created_at'),
            'type_txt' => __('excel.supplier.type_txt'),
            'status_txt' => __('excel.supplier.status_txt'),
            'approver_txt' => __('excel.supplier.approver_txt'),
            'approved_at' => __('excel.supplier.approved_at'),
        ];
    }
    public function _createFrom($create_data)
    {
        if (empty($create_data['sup_code'])) {
            while (1) {
                $code = $this::getErpCode('G');
                if ($this->checkRepeat('sup_code', $code)) break;
            }
            $create_data['sup_code'] = $code;
        }
        return $create_data;
    }

    public function approved(Request $request)
    {
        $id = $request->get('id');
        $sup_status = $request->get('sup_status');
        $approver = request()->header('user_id');
        if (empty($id) || empty($sup_status)) return $this->vdtError();
        list($res, $msg) = $this->model->approved($id, $sup_status, $approver);
        if (!$res) return $this->error($msg);
        return $this->success();
    }

    public function searchAll(Request $request)
    {
        return $this->modelReturn('searchAll', []);
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

    public function _limitFrom($RData)
    {
        $map = WmsSupplierDocument::$type_map;
        $info = WmsSupplierDocument::selectRaw('sup_code,GROUP_CONCAT(type) as types')->groupBy('sup_code')->get()->keyBy('sup_code')->each(function ($item) use ($map) {
            $types = explode(',', $item['types']);
            $options = [];
            foreach ($types as $type) {
                $options[] = ['value' => $type, 'label' => $map[$type]];
            }
            $item['options'] = $options;
        })->toArray();
        if ($RData['data'] ?? []) {
            foreach ($RData['data'] as &$v) {
                $v['doc_options'] = $info[$v['sup_code']]['options'] ?? [];
            }
        }

        return $RData;
    }

    public function _oneFrom($RData)
    {
        $RData['documents'] = Suppiler::documents($RData);
        return $RData;
    }

    // 添加证件信息
    function addDoc(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'sup_code' => 'required',
            'type' => 'required|in:1,2,3,4,5',
            'name' => 'required',
        ]);
        if ($params['type'] != 4) {
            if (empty($params['address'] ?? '')) {
                return $this->vdtError('The address field is invalid.');
            }
            if (empty($params['personal_number'] ?? '')) {
                return $this->vdtError('The personal number field is invalid.');
            }
        } else {
            if (empty($params['passport_type'] ?? '') || !in_array($params['passport_type'], [1, 2, 3])) {
                return $this->vdtError('The passport type field is invalid.');
            }
            if (empty($params['passport_number'] ?? '')) {
                return $this->vdtError('The passport number field is invalid.');
            }
        }

        $logic = new Suppiler();
        $logic->addDoc($params);
        return $this->output($logic, []);
    }
}
