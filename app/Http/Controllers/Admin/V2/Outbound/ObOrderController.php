<?php

namespace App\Http\Controllers\Admin\V2\Outbound;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Models\Admin\V2\UniqCodePrintLog;
use App\Logics\BaseLogic;
use App\Models\Admin\V2\Warehouse;
use App\Models\Admin\V2\WmsShop;



class ObOrderController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\ObOrder';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = []; //获取全部Where条件
    protected $BA  = ['*']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $BLWhere  = [
        'WHERE' => ['WHERE', ''],
        'NUMBER' => [['third_no', 'deliver_no', 'request_code'], '',],
        'ORDER' => ['ORDER', ''],
    ]; //获取全部分页Where条件
    protected $BL  = ['*']; //获取全部分页选取字段 *是全部
    protected $BLOrder  = [['created_at', 'desc'], ['id', 'desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序
    protected $BaseUpdateVat = [
        'id' =>        'required',
        'remark'  =>        'required',
    ]; //新增验证
    protected $BaseUpdate = [
        'remark' => '',
        'updated_at' => ['type', 'date'],
        'admin_user_id' => ['type', 'user_id'],
    ]; //新增数据
    protected $BUWhere = ['id' => ['=', '']]; //新增数据
    protected $code_field = 'oob_code';
    protected $NAME;
    protected $importName = '\App\Imports\ObOrderImport';
    protected $exportField;

    public function setExcelField()
    {
        $this->NAME = __('excel.ob_order.title');
        $this->exportField = [
            'type' => __('excel.ob_order.type'),
            'request_code' => __('excel.ob_order.request_code'),
            'status' => __('excel.ob_order.status'),
            'request_status' => __('excel.ob_order.request_status'),
            'payable_num' => __('excel.ob_order.payable_num'),
            'oversold_num' => __('excel.ob_order.oversold_num'),
            'stockout_num' => __('excel.ob_order.stockout_num'),
            'cancel_num' => __('excel.ob_order.cancel_num'),
            'actual_num' => __('excel.ob_order.actual_num'),
            'diff_num' => __('excel.ob_order.diff_num'),
            'tag' => __('excel.tag'),
            'seller_message' => __('excel.ob_order.seller_message'),
            'warehouse_name' => __('excel.warehouse_name'),
            'third_no' => __('excel.ob_order.third_no'),
            'erp_no' => __('excel.ob_order.erp_no'),
            'paysuccess_time' => __('excel.ob_order.paysuccess_time'),
            'remainder' => __('excel.ob_order.remainder'),
            'paysuccess_time' => __('excel.ob_order.paysuccess_time'),
            'delivery_deadline' => __('excel.ob_order.delivery_deadline'),
            'order_platform' => __('excel.ob_order.order_platform'),
            'order_channel' => __('excel.ob_order.order_channel'),
            'deliver_no' => __('excel.ob_order.deliver_no'),
            'remark' => __('excel.remark'),
            'users_txt.suspender_user' => __('excel.ob_order.suspender_user'),
            'paused_at' => __('excel.ob_order.paused_at'),
            'paused_reason' => __('excel.ob_order.paused_reason'),
            'users_txt.recovery_operator_user' => __('excel.ob_order.recovery_operator_user'),
            'recovery_at' => __('excel.ob_order.recovery_at'),
            'recovery_reason' => __('excel.ob_order.recovery_reason'),

        ];
    }
    public  $data = [
        'type' => 1,
        'source_code' => 'testcode',
        'payable_num' => 1,
        'warehouse_name' => 'testedit1',
        'buyer_account' => 'safa',
        'shop_name' => 'shop',
        'paysuccess_time' => "2023-12-19 17:36:17",
        'delivery_deadline' => '2023-12-19 17:36:17',
        'order_channel' => 1,
        'deliver_type' => '',
        'seller_message' => 'x',
        'buyer_message' => 'xx',
        'third_no' => 'fasaf',
        'order_platform' => 4,
        'remark' => 'fsaf',

        //商品详情
        'products' => [
            [
                'quality_level' => 'B',
                'sku' => '231208-3#12313',
                'count' => 1,
                'sup_id' => 1,
            ]
        ]
    ];

    public function test(Request $request)
    {
        list($res, $msg) = $this->addOne($this->data);
        if (!$res) return $this->vdtError($msg);
        return $this->success();
    }

    //任务自动执行
    protected function addOne($data)
    {


        //必填字段 type  warehouse_code  third_no  payable_num paysuccess_time delivery_deadline order_channel
        //自动生成字段 request_code erp_no
        //可填字段  sale_id  flag  remark

        $required = [
            'type', 'source_code', 'payable_num', 'warehouse_name', 'buyer_account',
            'shop_name', 'paysuccess_time', 'delivery_deadline', 'order_channel', 'products'
        ];

        $field = ['seller_message', 'buyer_message', 'third_no', 'order_platform', 'remark', 'deliver_type', 'deliver_no'];
        $required_vat = array_filter($required, function ($f) use ($data) {
            return empty($data[$f]);
        });

        if ($required_vat) return [false, '传入值缺少必要参数'];
        $fields = array_merge($field, $required);
        foreach ($fields as $v) {
            if (!isset($data[$v])) unset($create[$v]);
            else $create[$v] = $data[$v];
        }

        return $this->model->add($create);
    }

    //获取详情
    public  function getInfo(Request $request)
    {
        $vat = [
            'id' => 'required_without:code',
            'code' => 'required_without:id'
        ];
        $data = $this->vatReturn($request, $vat);
        return $this->modelReturn('getAllDetail', [$data]);
    }

    //暂停
    public function pause(Request $request)
    {
        $vat = [
            'ids' => 'required',
            'paused_reason' => 'required',
        ];
        $is_release = $request->get('is_release');
        $data = $this->vatReturn($request, $vat);
        return $this->modelReturn('pause', [$data['ids'], $data['paused_reason'], $is_release]);
    }


    //恢复
    public function recovery(Request $request)
    {
        $vat = [
            'ids' => 'required',
            // 'recovery_reason'=>'required',
        ];
        $data = $this->vatReturn($request, $vat);
        return $this->modelReturn('recovery', [$data['ids'], $request->get('recovery_reason')]);
    }

    //重配
    public function reTask(Request $request)
    {
        $vat = [
            'id' => 'required'
        ];
        $data = $this->vatReturn($request, $vat);
        return $this->modelReturn('reAllocationTask', [$data['id']]);
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
             if (substr($k, -3) == '_at' || substr($k, -5) == '_time' || substr($k, -8) == 'deadline' ) $temp['type'] = 'date';
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
                 $warehouse =$this->model::_getRedisMap('warehouse_map');
                 foreach ($warehouse as $warehouse_code=>$warehouse_name) {
                     $temp['statusOPtion'][] = [
                         'label' => $warehouse_name,
                         'value' => $warehouse_name,
                        //  'label' => $item['warehouse_name'],
                        //  'value' => $item['warehouse_name'],
                     ];
                 }
             }
             if (substr($k, -14) == 'warehouse_code') {
                 $warehouse =$this->model::_getRedisMap('warehouse_map');
                 foreach ($warehouse as  $warehouse_code=>$warehouse_name) {
                     $temp['statusOPtion'][] = [
                         'label' => $warehouse_name,
                         'value' => $warehouse_code,
                     ];
                 }
             }
             if($k == 'order_platform'){
                 $platform = $this->model::orderPlatform();
                 foreach ($platform as  $k=>$v) {
                    $temp['statusOPtion'][] = [
                        'label' => $v,
                        'value' => $k,
                    ];
                }
             }

             if($k == 'order_channel'){
                $chnn =$this->model::_getRedisMap('shop_map');
                foreach ($chnn as  $k=>$v) {
                   $temp['statusOPtion'][] = [
                       'label' => $v,
                       'value' => $k,
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
 
}
