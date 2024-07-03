<?php
    
namespace App\Http\Controllers\Admin\V1;

use App\Models\Withdraw;
use Dingo\Api\Exception\StoreResourceFailedException;
use App\Http\Controllers\Admin\V1\BaseController;
use App\Transformers\AuthorizationTransformer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\WithdrawOrders;
use App\Models\WithdrawOrdersDetailed;
use App\Models\Config;


class WithdrawController extends BaseController
{
    protected $BaseModels = 'App\Models\Withdraw';
    protected $BaseAllVat = [];//获取全部验证
    protected $BAWhere  = [];//获取全部Where条件
    protected $BA  = ['*'];//获取全部选取字段 *是全部
    protected $BAOrder  = [['id','desc']];//获取全部字段排序

    protected $BaseLVat = [];//获取分页全部验证
    protected $BLWhere  = [
        'status'=> ['=',''],
        'admin_name'=>['like',''],
     ];//获取全部分页Where条件
    protected $BL  = ['*'];//获取全部分页选取字段 *是全部
    protected $BLOrder  = [['id','desc']];//获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',];//单个处理验证
    protected $BOWhere = ['id'=>['=','']];//单个查询验证
    protected $BO = ['*'];//单个选取字段；*是全部
    protected $BOOrder = [['id','desc']];//单个选取字段排序

    protected $BaseCreateVat = [
        'title' => 'required',//标题
        'content' => 'required',//描述
        'imagepath' => 'required',//图片地址
        'line_url' => 'required',//链接地址
        // 'listorder' => 'required',//排序
        'status' => 'required',//是否禁用（1：正常； 2：禁用）',
        'type' => 'required',//图片类型:1=首页轮播,2=会员轮播',
    ];//新增验证
    protected $BaseCreate =[
        'title'=>'','content'=>'','imagepath'=>'',
        'line_url'=>'','listorder'=>'','status'=>'',
        'type'=>'',
        'createtime' => ['type','time'],
    ];//新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
    ];//新增验证
    protected $BaseUpdate =[
        'id'=>'', 'title'=>'','content'=>'',
        'imagepath'=>'','line_url'=>'','listorder'=>'',
        'status'=>'','type'=>'',
        'updatetime' => ['type','time'],
    ];//新增数据
    protected $BUWhere= ['id'=>['=','']];//新增数据

    /**
     *基础取多个值带分页方法
     *
     * @param Request $request
     * @return void
     */
    public function BaseLimit(Request $request){
        $user_id  = Auth::id();
        $user_ids = $this->getUserIdentity($user_id,'id');
        // var_dump($user_ids);exit;
        if(count($user_ids) == 0){
            $where_data= [];
        }else{
            $where_data= [['admin_id','in',$user_ids]];
        }
        $msg = !empty($this->BaseLVatMsg) ? $this->BaseLVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseLVat,$msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
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

        if($data['created_at']??''){
            $time = explode(',',$data['created_at']);
            $where_data[]= ['created_at','>=',$time[0]];
            $where_data[]= ['created_at','<=',$time[1]];
        }
        if($data['confirm_time']??''){
            $time = explode(',',$data['confirm_time']);
            $where_data[]= ['confirm_time','>=',$time[0]];
            $where_data[]= ['confirm_time','<=',$time[1]];
        }
        if($data['check_time']??''){
            $time = explode(',',$data['check_time']);
            $where_data[]= ['check_time','>=',$time[0]];
            $where_data[]= ['check_time','<=',$time[1]];
        }
        if($data['settlement_time']??''){
            $time = explode(',',$data['settlement_time']);
            $where_data[]= ['settlement_time','>=',$time[0]];
            $where_data[]= ['settlement_time','<=',$time[1]];
        }

        $RData = (new $this->BaseModels)->BaseLimit($where_data,$this->BL,$this->BLOrder,$cur_page,$size);
        if(method_exists($this,'_limitFrom')) $RData = $this->_limitFrom($RData);
        return  $this->success($RData,__('base.success'));
    }

    public function _limitFrom($RData)
    {
        if(!empty($RData['data'])){
            foreach($RData['data'] as &$v){
                if(!empty($RData))
                {
                    if(!empty($v['voucher'])) $v['voucher'] = cdnurl($v['voucher']);
                }
            }
        }
        return $RData;
    }

    public function _allFrom($RData)
    {
        if(!empty($RData)){
            foreach($RData as &$v){
                if(!empty($v['imagepath'])) $v['imagepath'] = cdnurl($v['imagepath']);
            }
        }
        return $RData;
    }

    public function _oneFrom($RData)
    {
        if(!empty($RData)){
            $RData['imagepath'] = cdnurl($RData['imagepath']);
        }
        return $RData;
    }

    /**
     * 结算单详情
     *
     * @return void
     */
    public function allOrder(Request $request)
    {
        $data = $request->all();
        $BAWhere =['withdraw_id'=>['=','']];
        $BaseAllVatMsg =[];
        $BaseAllVat = ['withdraw_id' => 'required',];
        $msg = !empty($BaseAllVatMsg) ? $BaseAllVatMsg : [];
        $validator = Validator::make($data, $BaseAllVat,$msg);
        if($validator->fails()) return $this->errorBadRequest($validator);
        $RData = \App\Logics\Withdraw::orderList($data);

//        $where_data= [['withdraw_id','=',$data['withdraw_id']]];
//        $RData = (new WithdrawOrdersDetailed)->BaseAll($where_data,['*'],[['id','desc']]);
//        $RData['title'] = $RData['product_name'];
//
//        $withdraw = (new Withdraw)->BaseOne(['id','=',$RData['withdraw_id']]);
//        $RData['admin_name'] = $withdraw['admin_name'];
//        $RData['admin_id'] = $withdraw['admin_id'];

        // if(method_exists($this,'_allFrom')) $RData = $this->_oneFrom($RData);
        return  $this->success($RData,__('base.success'));
    }

    /**
     *管理员一键下发
     *
     * @return void
     */
    public function withdrawOrder(Request $request)
    {
        $requestData = $request->all();
        $BaseAllVatMsg =['ids.required' => __('')];
        $BaseAllVat = ['ids' => 'required',];
        $where_data= [];
        $msg = !empty($BaseAllVatMsg) ? $BaseAllVatMsg : [];
        $validator = Validator::make($requestData, $BaseAllVat,$msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);

        $ids =  $requestData['ids'];
        $ids =  explode(',',$ids);
        $orderModel = DB::table('orders')->select('withdraw_orders_detailed.*')
        ->whereIn('withdraw_orders_detailed.id',$ids)->where('order_status','=',4000)->where('close_type','=',0);
        $orderModel->join('withdraw_orders_detailed', 'orders.order_no', '=', 'withdraw_orders_detailed.order_no');
        $orderModel->where('withdraw_orders_detailed.withdraw_id','>',0);
        $withdrawOrdersCount = $orderModel->count('withdraw_orders_detailed.id');
        if($withdrawOrdersCount >0)   return $this->error(__('admin.Withdraw.withdrawOrder.error'));

        $orderListModel = DB::table('orders')->select('withdraw_orders_detailed.*','orders.admin_id','orders.admin_name')
        ->whereIn('withdraw_orders_detailed.id',$ids)->where('order_status','=',4000)->where('close_type','=',0);
        $orderListModel->join('withdraw_orders_detailed', 'orders.order_no', '=', 'withdraw_orders_detailed.order_no');
        $orderList = $orderListModel->get();
        $orderList = objectToArray($orderList);
        // var_dump($orderList);exit;
        //写sql
        DB::beginTransaction();
        try {
            $withdraw =[];
            $admin_ids =[];
            foreach($orderList as $v){
                // var_dump($v);exit;
                if(!empty($withdraw[$v['admin_id']]['list']['stmt_fee'])){
                    $stmt_fee = $withdraw[$v['admin_id']]['list']['stmt_fee']+$v['stmt_fee'];
                }else{
                    $stmt_fee = $v['stmt_fee'];
                }
                $admin_ids[] =$v['admin_id'];
                $withdraw[$v['admin_id']]['list'] = [
                    'admin_id'       => $v['admin_id'],
                    'admin_name'     => $v['admin_name'],
                    // 'amount'        => $v['amount'],
                    // 'pay_amount'    => $v['pay_amount'],
                    'money'          => $v['stmt_fee'],
                    'status'         => 2,
                ];
                $withdraw[$v['admin_id']]['list_son'][] = $v['id'];
            }
            $config = DB::table('admin_users')->whereIn('admin_users.id',$admin_ids)
            ->join('config','admin_users.config_id', '=', 'config.id')->pluck('config.value','admin_users.id');
            // var_dump($config);exit;
            foreach($withdraw as $k =>&$v_s){
                $withdraw_orders_detailed = [];
                if(!empty($config[$k])){
                    $money =  count($withdraw[$k]['list_son'])*(int)$config[$k];
                    $v_s['list']['money']-=$money;
                    $v_s['list']['created_at']= date('Y-m-d H:i:s');
                    $withdraw_orders_detailed['each_cut'] = $config[$k];
                }
                $withdraw_id = (new $this->BaseModels)->BaseCreate($v_s['list']);
                $withdraw_orders_detailed['withdraw_id'] = $withdraw_id;
                DB::table('withdraw_orders_detailed')
                ->whereIn('id',$withdraw[$k]['list_son'])
                ->update($withdraw_orders_detailed);
            }
            DB::commit();
        } catch (\Exception $th) {
            //throw $th;
            DB::rollback();
            return $this->error('',$th->getMessage());
        }
        return $this->success();
    }

    /**
     *租户通过
     *
     * @return void
     */
    public function withdrawAdopt(Request $request)
    {
        $data = $request->all();
        if(empty($data['ids'])){
            return  $this->vdtError();
        }
        $ids = explode(',', $data['ids']);
        $wModel = (new $this->BaseModels);
        $wData = $wModel->wherein('id',$ids)->wherein('status',[1,3,4,5])->get();
        $wData = objectToArray($wData);
        if(count($wData)>0){
            return  $this->error();
        }
        $wModel->wherein('id',$ids)->update(['status' => 3,'confirm_time'=>date('Y-m-d H:i:s')]);
        return $this->success();
    }

    /**
     *租户驳回
     *
     * @return void
     */
    public function withdrawReject(Request $request)
    {
        $data = $request->all();
        if(empty($data['ids'])){
            return  $this->vdtError();
        }
        $ids = explode(',', $data['ids']);
        $wModel = (new $this->BaseModels);
        $wData = $wModel->wherein('id',$ids)->wherein('status',[1,3,4,5])->get();
        $wData = objectToArray($wData);
        if(count($wData)>0){
            return  $this->error();
        }
        $wModel->wherein('id',$ids)->update(['status' => 5,'confirm_time'=>date('Y-m-d H:i:s')]);
        return $this->success();

    }

    /**
     *管理员结算
     *
     * @return void
     */
    public function uWithdrawSettlement(Request $request)
    {
        $data = $request->all();
        if(empty($data['id'])){
            return  $this->vdtError();
        }
        // $ids = explode(',', $data['ids']);
        $id = $data['id'];
        $wModel = (new $this->BaseModels);
        $wData = $wModel->where('id','=',$id)->wherein('status',[1,2,4,5])->get();
        $wData = objectToArray($wData);
        if(count($wData)>0){
            return  $this->error();
        }
        $updateData = ['status' => 4,'settlement_time' =>date('Y-m-d H:i:s') ];
        if(!empty($data['voucher'])){
            $updateData['voucher'] = $data['voucher'];
        }
        if(!empty($data['desc'])){
            $updateData['desc'] = $data['desc'];
        }
        $wModel->where('id','=',$id)->update( $updateData);
        return $this->success();
    }

    /**
     *管理员处理异常让用户审核
     *
     * @return void
     */
    public function uWithdrawExamine(Request $request)
    {
        $data = $request->all();
        if(empty($data['id'])){
            return  $this->vdtError();
        }
        $id = $data['id'];
        $wModel = (new $this->BaseModels);
        $wData = $wModel->where('id','=',$id)->wherein('status',[1,2,3,4])->get();
        $wData = objectToArray($wData);
        if(count($wData)>0){
            return  $this->error();
        }
        $updateData = ['status' => 2];
        $wModel->where('id','=',$id)->update( $updateData);
        return $this->success();
    }

    /**
     *管理员处理异常让修改单个或多个字单
     *
     * @return void
     */
    public function uWithdrawExamineSon(Request $request)
    {
        $data = $request->json()->all();
        $updateData = [];
        foreach($data as $v)
        {
            $updateData[] =[
                'id' =>$v['id'],
                'withdraw_id' => $v['withdraw_id'],
                'pay_amount' =>$v['pay_amount'],
            ];
        }
       (new WithdrawOrders)->updateBatch($updateData);
       $pay_amount =(new WithdrawOrders)->where('withdraw_id','=',$updateData[0]['withdraw_id'])->sum('pay_amount');
       (new $this->BaseModels)->where('id','=',$updateData[0]['withdraw_id'])->update(['money' => $pay_amount]);
        return $this->success();
    }

    /**
     * 配置
     * @return void
     */
    public function financeConfig(Request $request)
    {
        $data = DB::table('config')->where('group','=','finance')->get();
        return $this->success($data);
    }

    /**
     * 配置
     * @return void
     */
    public function setFinanceConfig(Request $request)
    {
        $data = $request->json()->all();
        // var_dump($data);exit;
        $updateData = [];
        $createData = [];
        foreach($data as $v){
            if(empty($v['id'])){
                $createData[] = $v;
            }else{
                $updateData[] = $v;
            }
        }
        if(!empty($createData)){
            (new Config)->insert($createData);
        }
        if(!empty($updateData)){
            (new Config)->updateBatch($updateData);
        }
        return $this->success();
    }

    /**
     * 待下发
     * @return void
     */
    public function orderList(Request $request)
    {
        $where_data= [];
        $user_id  = Auth::id();
        $user_ids = $this->getUserIdentity($user_id,'id');
        $data = $request->all();
        $cur_page= !empty($data['cur_page'])? $data['cur_page']: 1;
        $size= !empty($data['size'])? $data['size']: 10;
        // $RData = (new Order)->BaseLimit($where_data,['order_no'],[['id','desc']],$cur_page,$size);
        $orders = DB::table('orders');
        if(count($user_ids) > 0){
            $orders->whereIn('admin_id',$user_ids);
            // $where_data= [['admin_id','in',$user_ids]];
        }
        $orders->where('order_status','=',4000);
        $orders->where('close_type','=',0);
        $orders->where('withdraw_orders_detailed.withdraw_id','=',0);
        $orders->join('withdraw_orders_detailed', 'orders.order_no', '=', 'withdraw_orders_detailed.order_no');
        // ->join('orders', 'users.id', '=', 'orders.user_id')
        $RData = $orders->paginate($size,['withdraw_orders_detailed.*','orders.admin_id','orders.admin_name'],'page',$cur_page);
        $RData = objectToArray($RData);
        $admin_ids = array_column($RData['data'],'admin_id');
        $config_id = DB::table('admin_users')->whereIn('id',$admin_ids)->pluck('config_id','id');
        $finance = DB::table('config')->where('group','=','finance')->pluck('value','id');
        foreach($RData['data'] as $k => $v){
            $RData['data'][$k]['each_cut'] = 0;
            if(!empty($config_id[$v['admin_id']]) &&!empty($finance[$config_id[$v['admin_id']]])){
                $RData['data'][$k]['each_cut'] = $finance[$config_id[$v['admin_id']]];
                $RData['data'][$k]['stmt_fee'] = $v['stmt_fee']-$RData['data'][$k]['each_cut'];
            }
        }
        return  $this->success($RData,__('base.success'));
    }

    /**
     * 校验价格
     *
     * @return void
     */
    public function check_money(Request $request)
    {
        $data = $request->all();
        if(empty($data['id'])|| empty($data['check_money'])){
            return  $this->vdtError();
        }
        $id = $data['id'];
        $wModel = (new $this->BaseModels);
        $updateData=[
            'check_money' => $data['check_money'],
            'check_time'  => date('Y-m-d H:i:s') //校验时间
        ];
        $wModel->where('id','=',$id)->update( $updateData);
        return $this->success();
    }
}