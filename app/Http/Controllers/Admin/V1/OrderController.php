<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\BaseController;
use App\Logics\OrderLogic;
use Illuminate\Http\Request;
use App\Transformers\AuthorizationTransformer;
use Illuminate\Support\Facades\Hash;
use Dingo\Api\Exception\StoreResourceFailedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Withdraw;
use App\Models\AdminUsers;
use PDF;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Export;

class OrderController extends BaseController
{
    protected $BaseModels = 'App\Models\Order';
    protected $BaseAllVat = [];//获取全部验证
    protected $BAWhere  = [];//获取全部Where条件
    protected $BA  = ['*'];//获取全部选取字段 *是全部
    protected $BAOrder  = [['id','desc']];//获取全部字段排序

    protected $BaseLVat = [];//获取分页全部验证'order_type' => 'required',
    protected $BLWhere  = [
        // 'order_status'=>['=',''],
        'order_status'=>['in',''],
        'close_type'=>['in',''],
        'withdrawal_status'=>['=',''],
        'admin_name'=>['like',''],
        'title' =>['like',''],
        // 'withdrawal_status' =>'',
        'article_number'=>['like',''],
        'order_no'=>['like',''],
    ];//获取全部分页Where条件
    protected $BL  = ['*'];//获取全部分页选取字段 *是全部
    protected $BLOrder  = [['id','desc']];//获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',];//单个处理验证
    protected $BOWhere = ['id'=>['=','']];//单个查询验证
    protected $BO = ['*'];//单个选取字段；*是全部
    protected $BOOrder = [['id','desc']];//单个选取字段排序

    protected $BaseCreateVat = [];//新增验证
    protected $BaseCreate =[];//新增数据

    protected $exportField = [
        'order_no'                      =>'订单编号',
        'order_type'                    =>'订单类型',
        'order_status'                  =>'订单状态',
        'pay_status'                    =>'支付状态',
        'pay_time'                      =>'支付时间',
        'amount'                        =>'订单总金额(单位:分)',
        'pay_amount'                    =>'支付总金额(单位:分)',
        'free_postage_service_charge'   =>'包邮服务费(分) 数据可能存在1～10s延迟',
        'merchant_subsidy_amount'       =>'商家承担的优惠金额(单位:分)',
        'properties'                    =>'规格',
        'spu_id'                        =>'商品SPU_ID',
        'sku_id'                        =>'商品SKU_ID',
        'brand_id'                      =>'品牌id',
        'title'                         =>'商品名称',
        'sku_price'                     =>'商品出价金额(单位:分)',
        'article_number'                =>'货号',
        'other_numbers'                 =>'辅助货号，多个逗号隔开',
        'other_merchant_sku_codes'      =>'辅助商家商品编码',
        'seller_bidding_no'             =>'出价编号',
        'delivery_limit_time'           =>'发货截止时间（时间格式：yyyy-MM-dd HH:mm:ss）',
        'close_order_deadline'          =>'截止关单时间，格式yyyy-MM-dd HH:mm:ss）',
        'qty'                           =>'销售数量',
        'poundage'                      =>'商品费率(单位:分)',
        'poundage_percent'              =>'商品费率百分比（实际值需要除100）',
        'close_type'                    =>'关闭类型',
        'close_time'                    =>'订单关闭时间',
        'modify_time'                   =>'订单修改时间',
        'create_time'                   =>'订单创建时间',

        'package_quantity'              =>'包裹数量',
        'pickup_time_start'             =>'预约上门时间',
        'pickup_time_end'               =>'预约上门时间',
        'withdrawal_status'             =>'提现状态',
        // 'withdrawal_status'             =>'提现状态'
    ];

    protected $BaseUpdateVat = [
        'ids' =>        'required',
    ];//新增验证
    protected $BaseUpdate =[
        'id'=>'', 'overdue'=>'','serialnum2'=>'','serialnum'=>'',
        'status'=>'','shoptime'=>'',
        'logistics_status' =>'',
        'withdrawal_status' =>'',
        // 'updatetime' => ['type','time'],
    ];//新增数据
    protected $BUWhere= ['id'=>['=','']];//新增数据

    /**
     *基础取多个值带分页方法
     *
     * @param Request $request
     * @return void
     */
    public function BaseLimit(Request $request){
        $where_data= [];
        $user_id  = Auth::id();
        $user_ids = $this->getUserIdentity($user_id,'id',true);
        if(count($user_ids) > 0){
            $where_data= [['admin_id','in',$user_ids]];
        }
        
        $msg = !empty($this->BaseLVatMsg) ? $this->BaseLVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseLVat,$msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        if(!empty( $data['create_time'])){
            $create_time = explode(',',$data['create_time']);
            $where_data[]= ['create_time','>=',$create_time[0]];
            $where_data[]= ['create_time','<=',$create_time[1]];
        }
        $cur_page= !empty($data['cur_page'])? $data['cur_page']: 1;
        $size= !empty($data['size'])? $data['size']: 10;
        //修改参数  request要存在或者BUWhere存在
        foreach($this->BLWhere as $B_k => $B_v){
            // var_dump($B_k[1]);exit;
            if(!empty($B_v) && empty($B_v[1]) && isset($data[$B_k])){
                if($B_v[0] == 'allLike')
                {
                    $where_data[] = ["concat({$B_v[2]}) like ?",$B_v[0],["%{$data[$B_k]}%"]];
                    continue;
                }
                $where_data[] = [$B_k,$B_v[0],$data[$B_k]];
                continue;
            }
            if(!empty($B_v) && (!empty($B_v[1]) || $B_v[1] ===0)){
                $where_data[] = [$B_k,$B_v[0],$B_v[1]];
                continue;
            }
        }
        $RData = (new $this->BaseModels)->BaseLimit($where_data,$this->BL,$this->BLOrder,$cur_page,$size);
        if(method_exists($this,'_limitFrom')) $RData = $this->_limitFrom($RData);
        return  $this->success($RData,__('base.success'));
    }

    public function _limitFrom($RData)
    {
        if(!empty($RData['data'])){
            // foreach($RData['data'] as &$v){
            //     if(!empty($RData))
            //     {
            //         $shop_name = DB::table('shop')->where('id','=',$v['shop_id'])->pluck('shop_name');
            //         $v['shop_name'] = !empty($shop_name[0]) ? $shop_name[0]: '';
            //     }
            // }
        }
        return $RData;
    }

    public function _oneFrom($RData)
    {
        // if(!empty($RData)){
        //     $RData['list'] = DB::table('orders_reinsurance')
        //     ->where('order_num','=',$RData['order_num'])
        //     ->orderBy('id','desc')
        //     ->get();
        // }
        return $RData;
    }

    public function _oneFroms($RData)
    {
        if(!empty($RData)){
            $RData['list'] = DB::table('orders_reinsurance')
            ->where('order_num','=',$RData['order_num'])
            ->orderBy('id','desc')
            ->get();
        }
        return $RData;
    }

    /**
     *基础修改方法
     *
     * @param Request $request
     * @return void
     */
    public function BaseUpdate(Request $request)
    {
        $where_data= [];
        $update_data = [];
        $msg = !empty($this->BaseUpdateVatMsg) ? $this->BaseUpdateVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseUpdateVat,$msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        if(empty($data['withdrawal_id']) && $data['withdrawal_status'] >1){
            return  $this->error(__('admin.order.operation'));
        }
        $ids = explode(',',$data['ids']);
        $money = (new $this->BaseModels)
        ->where('withdrawal_status','=',$data['withdrawal_status'])
        ->where('order_status','=',4000)
        ->wherein('id',$ids)
        ->sum('pay_amount');
        $admin_id = Auth::id();
        $admin_name = (new AdminUsers)->nameValue($admin_id,'username');
        // if(!empty($data['withdrawal_id'])){
        //     $withdraw = [
        //        'status' =>$data['withdrawal_status'],
        //        'update_at' => date('Y-m-d H:i:s'),
        //     ];
        //     $withdraw_id = (new Withdraw)->where('withdrawal_id','=',$data['withdrawal_id'])->update($withdraw);
        // }else{
        $withdraw = [
            'admin_id' => $admin_id,
            'admin_name' => $admin_name,
            'money' => $money,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $withdraw_id = (new Withdraw)->insert($withdraw);
        // }
        $RData = (new $this->BaseModels)->where('withdrawal_status','=',$data['withdrawal_status'])
        ->where('order_status','=',4000)
        ->wherein('id',$ids)->update(
            [
                'withdrawal_status' =>$data['withdrawal_status'],
                'withdraw_id'       => $withdraw_id
            ]
        );
        if($RData){
            $admin_id = Auth::id();
            DB::table('admin_logs')->insert(['admin_id' => $admin_id,
            'log_url' => empty($data['s']) ? '':$data['s'],
            'log_ip'=> get_ip(),
            'log_info'=> '修改内容',
            'log_time' => date('Y-m-d H:i:s'),
            'log_info_details' => json_encode($update_data,true)
            ]);
            return  $this->success($data,__('base.success'));
        }else{
            return  $this->error();
        }
    }


    /**
     * 搜索
     * @return void
     */
    public function order(Request $request)
    {
        $data = $request->all();
        foreach($data as $v){
        }
        return ;
    }

    /**
     * 导出
     * @param Request $request
     * @return array
     */
    public function export(Request $request)
    {
        // if(empty($request->ids) ) return $this->error(__('base.vdt'));
        $data = $request->all();
        $where=[];
        if(!empty($data['ids'])){
            $ids = explode(',',$data['ids']);
            $where = [['id','in',$ids ]];
        }
        $exportField = $this->exportField;
        // var_dump($exportField);exit;
        $exportFieldRow=  $exportField;
        if(!empty($data['field'])){
            $exportFieldRow  = [];
            $field = explode(',',$data['field']);
            foreach($field as $field_v){
                $exportFieldRow[$field_v] = $exportField[$field_v];
            }
        }
        // $where = [['id','in',$ids ]];
        $bondedAdopt  = (new  $this->BaseModels)->BaseAll($where);
        $headers[]  = $exportFieldRow;
        $data = [];
        foreach($bondedAdopt as $v){
            $row = [];
            foreach($exportFieldRow as $e_k=>$e_sv){
                $row[$e_k] = $v[$e_k];
                // var_dump($e_k);exit;
                // var_dump( $v);exit;
            }
            $data[] = $row;
        }
        // var_dump($data);exit;
        $export = new Export($headers,$data);
        return Excel::download($export, date('YmdHis') . '.xlsx',);
    }

    //三方渠道订单列表
    function erpOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        return $this->success(OrderLogic::channelOrderList($request), __('base.success'));
    }
}
