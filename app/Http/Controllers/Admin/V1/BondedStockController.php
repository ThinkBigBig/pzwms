<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\BaseController;
use Illuminate\Http\Request;
use App\Transformers\AuthorizationTransformer;
use App\Models\ProductStock;
use App\Models\BondedStockNumber;
use App\Models\AdminUsers;
use Illuminate\Support\Facades\Hash;
use Dingo\Api\Exception\StoreResourceFailedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Handlers\DwApi;
use App\Exports\Export;
use  App\Imports\ExcelExport;
use Maatwebsite\Excel\Facades\Excel;

class BondedStockController extends BaseController
{
    protected $BaseModels = 'App\Models\BondedStock';
    protected $BaseAllVat = [];//获取全部验证
    protected $BAWhere  = [];//获取全部Where条件
    protected $BA  = ['*'];//获取全部选取字段 *是全部
    protected $BAOrder  = [['id','desc']];//获取全部字段排序

    protected $BaseLVat = [];//获取分页全部验证
    protected $BLWhere  = [
        'status_type'=>['=',''],
        'bill_id'    =>['like',''],
        'appoint_no'=>['like',''],
    ];//获取全部分页Where条件
    protected $BL  = ['*'];//获取全部分页选取字段 *是全部
    protected $BLOrder  = [['id','desc']];//获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',];//单个处理验证
    protected $BOWhere = ['id'=>['=','']];//单个查询验证
    protected $BO = ['*'];//单个选取字段；*是全部
    protected $BOOrder = [['id','desc']];//单个选取字段排序

    protected $BaseCreateVat = [
    ];//新增验证
    protected $BaseCreate =[
        'order_bonded' => '',
    ];//新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
    ];//新增验证
    protected $BaseUpdate =[
        'id'=>'',
        'order_bonded' => '',
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
        $where_data= [];
        $user_id  = Auth::id();
        $user_ids = $this->getUserIdentity($user_id,'id');
        if(count($user_ids) > 0){
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
        $RData = (new $this->BaseModels)->BaseLimit($where_data,$this->BL,$this->BLOrder,$cur_page,$size);
        if(method_exists($this,'_limitFrom')) $RData = $this->_limitFrom($RData);
        return  $this->success($RData,__('base.success'));
    }
    public function _oneFrom($RData)
    {
        // var_dump(json_encode($this->BaseCreate,true));
        // if(!empty($RData)){
        //     $brand_name  = DB::table('pms_brand')->where('id','=',$RData['brand_id'])->pluck('name');
        //     $brand_name_cn = DB::table('pms_brand')->where('id','=',$RData['brand_id'])->pluck('name_cn');
        //     $category_name =  DB::table('pms_product_category')->where('id','=',$RData['category_id'])->pluck('name');
        //     $category_name_cn = DB::table('pms_product_category')->where('id','=',$RData['category_id'])->pluck('name_cn');
        //     $RData['brand_name'] =  !empty($brand_name[0]) ? $brand_name[0]: '';
        //     $RData['brand_name_cn'] =  !empty($brand_name_cn[0]) ? $brand_name_cn[0]: '';
        //     $RData['category_name'] =  !empty($category_name[0]) ? $category_name[0]: '';
        //     $RData['category_name_cn'] = !empty($category_name_cn[0]) ? $category_name_cn[0]: '';
        // }
        return $RData;
    }

    public function _limitFrom($RData)
    {
        // if(!empty($RData['data'])){
        //     foreach($RData['data'] as &$v){
        //         if(!empty($RData))
        //         {
        //             $brand_name  = DB::table('pms_brand')->where('id','=',$v['brand_id'])->pluck('name');
        //             $brand_name_cn = DB::table('pms_brand')->where('id','=',$v['brand_id'])->pluck('name_cn');
        //             $category_name =  DB::table('pms_product_category')->where('id','=',$v['category_id'])->pluck('name');
        //             $category_name_cn = DB::table('pms_product_category')->where('id','=',$v['category_id'])->pluck('name_cn');
        //             $v['brand_name'] =  !empty($brand_name[0]) ? $brand_name[0]: '';
        //             $v['brand_name_cn'] =  !empty($brand_name_cn[0]) ? $brand_name_cn[0]: '';
        //             $v['category_name'] =  !empty($category_name[0]) ? $category_name[0]: '';
        //             $v['category_name_cn'] = !empty($category_name_cn[0]) ? $category_name_cn[0]: '';
        //         }
        //     }
        // }
        return $RData;
    }

    /**
     *  管理员 通过预约单
     * @param Request $request
     * @return void
     */
    public function bondedAdopt(Request $request)
    {
        if(empty($request->id)) return $this->error(__('base.vdt'));
        $where = [['id','=',$request->id]];
        $where2 = [['id','=',$request->id],['status_type','=',1]];
        $update =['status_type' => 2];
        $bondedAdopt  = (new  $this->BaseModels)->BaseOne($where2);
        if(count($bondedAdopt) ==0 ) return $this->error(__("预约单不存在,或不可以操作"));
        (new  $this->BaseModels)->BaseUpdate($where,$update);
        return  $this->success([],__('base.success'));
    }

    /**
     * 管理员 驳回 预约单
     * @param Request $request
     * @return void
     */
    public function bondedReject(Request $request)
    {
        if(empty($request->id)) return $this->error(__('base.vdt'));
        $where = [['id','=',$request->id]];
        $where2 = [['id','=',$request->id],['status_type','=',1]];
        $bondedAdopt  = (new  $this->BaseModels)->BaseOne($where2);
        if(count($bondedAdopt) ==0 ) return $this->error(__("预约单不存在,或不可以操作"));
        $update =['id' => $request->id,'status_type' => 3];
        (new  $this->BaseModels)->BaseUpdate($where,$update);
        return  $this->success([],__('base.success'));
    }

    /**
     * 管理员 驳回 预约单
     * @param Request $request
     * @return array
     */
    public function bondedCancel(Request $request){
        if(empty($request->id)) return $this->error(__('base.vdt'));
        $where = [['id','=',$request->id]];
        $where2 = [['id','=',$request->id],['status_type','in',[1,3]]];
        $bondedAdopt  = (new  $this->BaseModels)->BaseOne($where2);
        if(count($bondedAdopt) ==0 ) return $this->error(__("预约单不存在,或不可以操作"));
        $update =['id' => $request->id,'status_type' => -1];
        (new  $this->BaseModels)->BaseUpdate($where,$update);
        return  $this->success([],__('base.success'));
    }

    /**
     * 管理员绑定预约单
     * @param Request $request
     * @return array
     */
    public function bondedBinding(Request $request)
    {
        if(empty($request->ids) || empty($request->bill_id) ) return $this->error(__('base.vdt'));
        $ids = explode(',',$request->ids);
        $where = [['id','in',$ids ]];
        $where2 = [['id','in',$ids ]];
        $bondedAdopt  = (new  $this->BaseModels)->BaseAll($where2);
        $admin_id  =$bondedAdopt[0]['admin_id'];
        foreach($bondedAdopt as $v){
            if($admin_id != $v['admin_id']) return $this->error(__("出货单不能同时绑定多个用户"));
        }
        $update =['bill_id' => $request->bill_id];
        (new  $this->BaseModels)->BaseUpdate($where,$update);
        return  $this->success([],__('base.success'));
    }

    /**
     *
     * 用户提交驳回预约单
     *
     * @param Request $request
     * @return void
     */
    public function bondedSubmit(Request $request)
    {

    }

    /**
     *   废弃接口
     * @param Request $request
     * @return void
     */
    public function bondedInfo(Request $request)
    {
        $product_sn =$request->product_sn;
        $user_id  = Auth::id();
        $user_ids = $this->getUserIdentity($user_id);
        if(count($user_ids) == 0){
            $where= [['product_sn','=',$product_sn]];
        }else{
            $where= [['supplierCode','in',$user_ids],['goodsCode','=',$product_sn]];
        }
        $data = (new ProductStock)->BaseAll($where);
        foreach( $data as $k=>$v){
            if(empty($v['sku_id']) || empty($v['spu_id']))
            {
                if(empty($data2)){
                   $data2 = $this->batch_article_number($product_sn);
                   if($data2['code'] != 200) return  $this->error($data2,__('base.success'));
                   $data2 = $data2['data'][0];
                    //    $this->
                    // var_dump($data2['spu_id']);exit;
                   if(empty($v['spu_id'])){
                        // var_dump
                        $data[$k]['spu_id'] = $data2['spu_id'];
                        (new ProductStock)->where('id','=',$v['id'])->update(['spu_id' => $data2['spu_id']]);
                   }
                   if(!empty($v['sku_id']))
                   {
                        // $spu = $v['spu_id'];
                        $spu = explode('#',$v['skuProperty']);
                        // var_dump($spu);exit;
                        $sku_id =$this->seek_v($data2['skus'],$spu[0]);
                        if($sku_id)
                        {
                            $data[$k]['sku_id'] = $sku_id;
                            (new ProductStock)->where('id','=',$v['id'])->update(['sku_id' => $sku_id]);
                        }
                   }
                    //    var_dump($v);exit;
                }else{
                    if(empty($v['spu_id'])){
                        // var_dump
                        $data[$k]['spu_id'] = $data2['spu_id'];
                        (new ProductStock)->where('id','=',$v['id'])->update(['spu_id' => $data2['spu_id']]);
                   }
                   if(!empty($v['sku_id']))
                   {
                        // $spu = $v['spu_id'];
                        $spu = explode('#',$v['skuProperty']);
                        // var_dump($spu);exit;
                        $sku_id =$this->seek_v($data2['skus'],$spu[0]);
                        if($sku_id)
                        {
                            $data[$k]['sku_id'] = $sku_id;
                            (new ProductStock)->where('id','=',$v['id'])->update(['sku_id' => $sku_id]);
                        }
                   }
                }
            }
        }
        return  $this->success($data,__('base.success'));
    }

    /**
     *  用户提交预约单
     * @param Request $request
     * @return void
     */
    public function bondedAdd(Request $request)
    {
        $data = $request->all();
        $json = $request->json()->all();
        // var_dump($json);exit;
        $user_id  = Auth::id();
        $admin_name =  (new AdminUsers)->nameValue($user_id,'username');
        $user_ids = $this->getUserIdentity($user_id);
        $json_arr = [];
        $appoint_no = date('YmdHis').rand(10000,99999);
        $appoint_qty= 0;
        foreach($json as $v)
        {
            if(empty($v['id']) || empty($v['qty']) || !is_numeric($v['id']) || !is_numeric($v['qty']) )
            {
                return $this->error(__('admin.BondedStock.bondedAdd.number.error'));
            }
            if(count($user_ids) == 0){
                $where = [['id','=',$v['id']]];
            }else{
                $where = [['supplierCode','in',$user_ids],['id','=',$v['id']]];
            }
            $ProductStockInfo = (new ProductStock)->BaseOne($where);

            $numberWhere = [['pms_product_stock_id','=',$v['id']]];
            // status_type
            $qty = (new BondedStockNumber)
            ->where('bill_id','>',0)
            ->where('pms_product_stock_id','=',$v['id'])->sum('qty');
            if(count($ProductStockInfo) > 0)
            {
                // 判读是否超过最大库存10 --------------------------^^^
                if($v['qty'] > $ProductStockInfo['storeGoodsNum'])
                {
                    return $this->error(__('admin.BondedStock.bondedAdd.number2.error'));
                }
                if($ProductStockInfo['storeGoodsNum']<=0)
                {
                    return $this->error(__('admin.BondedStock.bondedAdd.number2.error'));
                }
                $surplus_qty = $ProductStockInfo['storeGoodsNum']-$qty;
                if($v['qty'] > $surplus_qty)
                {
                    return $this->error(__('admin.BondedStock.bondedAdd.number2.error'));
                }
                //判断是都已经有提交的预约单10 --------------------------^^^
                // var_dump($ProductStockInfo);
            }else{
                return $this->error(__('admin.BondedStock.bondedAdd.number2.error'));
            }

            $BondedStockNumber_data[] = [
                'pms_product_stock_id'  =>  $ProductStockInfo['id'],
                'admin_id'              =>  $user_id,
                'admin_name'            =>  $admin_name,
                'appoint_no'            =>  $appoint_no,
                'product_name'          =>  $ProductStockInfo['itemName'],//商品名称
                'article_number'        =>  $ProductStockInfo['goodsCode'],//货号
                'bar_code'              =>  $ProductStockInfo['barCode'],//条形码
                'qty'                   =>  $v['qty'],//预约数
                'props'                 =>  $ProductStockInfo['skuProperty'],//规格
                'sku_id'                =>  time(),//规格
                'spu_id'                =>  time(),//规格
            ];

            $appoint_qty+= $v['qty'];
            // $ProductStockInfo['pms_product_stock_id']
        }
        $BondedData = [
            'admin_id'      =>  $user_id,
            'admin_name'    =>  $admin_name,
            'appoint_no'    =>  $appoint_no,
            'appoint_qty'   =>  $appoint_qty,//预约数
            'status_type'   =>  1
        ];
        // var_dump($BondedStockNumber_data);exit;
        DB::beginTransaction();
        try {
            $Bonded_id = (new $this->BaseModels)->BaseCreate($BondedData);
            $add_arr =['pms_bonded_stock_id' => $Bonded_id ];
            //给子单压入聚合单id
            array_walk($BondedStockNumber_data, function (&$value, $key, $add_arr) {
                $value = array_merge($value, $add_arr);
            }, $add_arr);
            (new BondedStockNumber)->insert($BondedStockNumber_data);
            DB::commit();
            return $this->success();
        } catch (\Exception $th) {
            //throw $th;
            DB::rollback();
            return $this->error('',$th->getMessage());
        }
    }

    public function batch_article_number($article_numbers)
    {
        $method = '2,2,apiUrl';
        // var_dump($request->all());exit;
        // $requestArr['sku_id'] = $request->sku_id;
        $requestArr['article_numbers'] = $article_numbers;
        // $requestArr['inventory_list'] = [
        //     'wh_inv_no' => $request->wh_inv_no,
        //     'qty' => $request->qty,
        // ];
        // var_dump($method);
        $data =  (new DwApi($requestArr))->uniformRequest($method,$requestArr);
        $arr  = json_decode($data,true);
        return  $arr;
        // var_dump($arr);
    }

    /**
     *
     *
     * @param [type] $data
     * @param [type] $v
     * @return void
     */
    public function seek_v($data,$v)
    {
        foreach($data as $_v){
            // $_v['properties'];
            $properties =json_decode($_v['properties'],true);
            if($properties['尺码'] ==$v)
            {
                return $properties['sku_id'];
            }
        }
        return 0;
    }

    /**
     * 管理员导出预约单
     * @param Request $request
     * @return array
     */
    public function export(Request $request)
    {
        if(empty($request->ids) ) return $this->error(__('base.vdt'));
        $ids = explode(',',$request->ids);
        $where = [['id','in',$ids ]];
        $where2 = [['id','in',$ids ]];
        $bondedAdopt  = (new  $this->BaseModels)->BaseAll($where2);
        $admin_id  =$bondedAdopt[0]['admin_id'];
        $numberIds=[];
        foreach($bondedAdopt as $v){
            $numberIds[] = $v['appoint_no'];
            if($admin_id != $v['admin_id']) return $this->error(__("不能同时导出多个用户预约单"));
        }
        $BondedStockNumber = (new  BondedStockNumber)->select('sku_id', DB::raw("sum(qty) as qty2"))->whereIN('appoint_no',$numberIds)->groupBy('sku_id')->get();
        $BondedStockNumberArr = objectToArray($BondedStockNumber);
        // var_dump($BondedStockNumberArr);exit;
        //导出
        // $shipmentDataSku = $shipmentModel->BaseOne($where);//查表里是否已经有sku
        //     // var_dump($shipmentDataSku );exit;
        // if(count($shipmentDataSku)>0){
        //         // var_dump($shipmentDataSku);exit;
        //         $sku_id = $shipmentDataSku['sku_id'];
        //         $spu_id = $shipmentDataSku['spu_id'];
        // }else{
        //     // var_dump($data['properties']);
        //     $product = self::batch_article_number($createDataSon['product_sn']);
        //     if($product['code'] != 200) {
        //         $failData[]= $createDataSon; continue;
        //     }
        //     $data2 = $product['data'][0];
        //     $spu_id = $data2['spu_id'];
        //     $sku_id = self::seek_v($data2['skus'],$createDataSon['properties']);
        //     if(empty($sku_id ))
        //     {
        //         $failData[]= $createDataSon; continue;
        //     }
        // }
        $headers = [["id"=>'目的仓', "sku_id"=>'sku id',"qty2"=>'备货数量',"zy"=>'注意',]];
        $data[] = [
                'id'    =>'请确保备货商品的目的仓是一致的(宁波保税仓，金义保税仓，南沙保税仓)',
                'sku_id'=>'确保数据正确，无重复数据',
                'qty2'  =>'备货数量须小于可申请数量',
                'zy'    =>'请确保导入数据小于100条；导入时第1和第2行内容不用删除，导入数据从第3行开始'
        ];

        foreach($BondedStockNumberArr as $v){
            $data[] = [
               'id' =>'宁波保税仓',
               'sku_id' =>$v['sku_id'],
               'qty2' => $v['qty2'],
               'zy' => ''
            ];
        }
        // var_dump( $data);exit;
        $export = new Export($headers,$data);
        return Excel::download($export, date('YmdHis') . '.xlsx',);
        // return (new ExcelExport())->download();
        // return  $this->success([],__('base.success'));
    }
}
