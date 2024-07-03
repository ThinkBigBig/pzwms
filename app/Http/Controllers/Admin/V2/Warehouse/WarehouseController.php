<?php

namespace App\Http\Controllers\Admin\V2\Warehouse;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Http\Request;
use App\Logics\BaseLogic;
use App\Models\Admin\V2\Warehouse;

class WarehouseController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\Warehouse';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = ['status' => ['=', 1]]; //获取全部Where条件
    protected $BA  = ['id', 'warehouse_code', 'warehouse_name', 'status']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $BLWhere  = [
        'warehouse_name' => ['like', ''],
        'warehouse_code' => ['=', ''],
        'WHERE' => ['WHERE', ''],
    ]; //获取全部分页Where条件
    protected $BL  = ['*']; //获取全部分页选取字段 *是全部
    // protected $BL  = ['id','warehouse_code','warehouse_name','attribute','contact_name','contact_phone','type','tag'];//获取全部分页选取字段 *是全部
    protected $BLOrder  = [['created_at', 'desc'], ['id', 'desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序

    protected $BaseCreateVat = [
        'warehouse_name' => 'required', //仓库名称
        'attribute' => 'required', //属性
        'type' => 'required', //类型

    ]; //新增验证
    protected $BaseCreate = [
        'warehouse_code' => '',
        'warehouse_name' => '',
        'type' => '',
        'attribute' => '',
        'contact_name' => '',
        'contact_phone' => '',
        'status' => '',
        'tag' => '',
        'remark' => '',
        'log_prod_ids' => '',
        'created_at' => ['type', 'date'],
        'created_user' => ['type', 'user_id'],

    ]; //新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
    ]; //新增验证
    protected $BaseUpdate = [
        'warehouse_name' => '',
        'type' => '',
        'attribute' => '',
        'contact_name' => '',
        'contact_phone' => '',
        'status' => '',
        'tag' => '',
        'remark' => '',
        'log_prod_ids' => '',
        'updated_at' => ['type', 'date'],
        'admin_user_id' => ['type', 'user_id'],

    ]; //新增数据
    protected $BUWhere = ['id' => ['=', '']]; //新增数据
    protected $NAME;
    protected $exportField;
    // 导入数据
    protected $importFields;

    public function setExcelField()
    {
        $this->NAME = __('excel.warehouse.title');
        $this->exportField = [
            'warehouse_code' => __('excel.warehouse.warehouse_code'),
            'warehouse_name' => __('excel.warehouse.warehouse_name'),
            'type_txt' => __('excel.warehouse.type_txt'),
            'status_txt' => __('excel.status_txt'),
            'remark' => __('excel.remark'),
        ];
        $this->importFields  = [
            'warehouse_code' => __('excel.warehouse.warehouse_code'),
            'warehouse_name' => __('excel.warehouse.warehouse_name') . '|required',
            'type_txt' => __('excel.warehouse.type_txt') . '|required',
            'status_txt' => __('excel.status_txt'),
            'remark' => __('excel.remark'),
        ];
    }


    public function _createFrom($create_data)
    {
        $create_data['admin_user_id'] = request()->header('user_id');
        if (empty($create_data['warehouse_code'])) {
            while (1) {
                $create_data['warehouse_code'] = $this::getErpCode('CK', 10);
                if ($this->checkRepeat('warehouse_code', $create_data['warehouse_code'])) break;
            }
        } else {
            if (!$this->checkRepeat('warehouse_code', $create_data['warehouse_code'])) return $this->vdtError(__('response.code_repeat'));
        }

        if (!$this->checkRepeat('warehouse_name', $create_data['warehouse_name'])) return $this->vdtError(__('response.name_repeat'));
        return $create_data;
    }

    public function getLogProduct(Request $request)
    {
        $vat = [
            'warehouse_code' => 'required',
        ];
        $data = $this->vatReturn($request, $vat);
        $tenant_id = $request->header('tenant_id');
        $res  =  $this->model->logProducts($data['warehouse_code'], $tenant_id);
        return $this->success($res);
    }

    //获取库区
    public function getWareArea(Request $request)
    {
        // $vat = [
        //     'warehouse_code'=>'required',
        // ];
        // $data = $this->vatReturn($request,$vat);
        return $this->modelReturn('getWareArea', [$request->all()]);
    }
    public function getLocation(Request $request)
    {
        $vat = [
            'warehouse_code' => 'required',
        ];
        $data = $this->vatReturn($request, $vat);
        return $this->modelReturn('getLocation', [$request->all()]);
    }
    public function importFormat()
    {
        $count = 2;
        $field = $this->exportField;
        $rule = [
            'required' => $this->required,
            'auto' => [
                'created_user' => request()->header('user_id'),
                'warehouse_code' => ['method', "getErpCode", ['CK']],
            ],
            'uniq_columns' => ['warehouse_name', 'warehouse_code'],

        ];
        return [
            'count' => $count,
            'rule' => $rule,
            'field' => $field,
        ];
    }

    //获取字段信息
    public function getColumn()
    {
        $model = new $this->BaseModels();
        $map = $model->excelSetAttr();
        $setExcelField = request()->get('setExcelField', 'setExcelField');
        if (method_exists($this, $setExcelField)) $this->$setExcelField();
        $col = empty($this->exportField) ? [] : $this->exportField;
        $column = [];
        foreach ($col as $k => $v) {
            $temp = [];
            //时间返回类型
            if (substr($k, -3) == '_at') $temp['type'] = 'date';
            if (substr($k, -4) == '_txt') $k = substr($k, 0, -4);
            $temp['value'] = $k;
            $temp['label'] = $v;
            if (!empty($map[$k])) {
                // $temp['map']=$map[$k];
                foreach ($map[$k] as $k => $v) {
                    $temp['statusOPtion'][] = [
                        'label' => $k,
                        'value' => $v,
                    ];
                }
            }
            if (substr($k, -14) == 'warehouse_name' || substr($k, -9) == 'warehouse') {
                $warehouse = Warehouse::where('status', 1)->select('warehouse_code', 'warehouse_name')->get();
                foreach ($warehouse as $item) {
                    $temp['statusOPtion'][] = [
                        'label' => $item['warehouse_name'],
                        'value' => $item['warehouse_name'],
                    ];
                }
            }
            BaseLogic::searchColumnAppend($temp);
            // if(substr($k, -14) == 'shop_code'){
            //     $warehouse = WmsShop::where('status',1)->select('code','name')->get();
            //     foreach ($warehouse as $item) {
            //         $temp['statusOPtion'][] = [
            //             'label' =>$item['name'],
            //             'value' => $item['code'],
            //         ];
            //     }
            // }

            $column[] = $temp;
        }
        return $column;
    }

    public function BaseAll(Request $request)
    {
        $RData =  $this->model->where('status',1)->get()->toArray();
        return  $this->success($RData, __('base.success'));
    }
}