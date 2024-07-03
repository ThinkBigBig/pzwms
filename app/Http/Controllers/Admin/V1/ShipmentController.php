<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\BaseController;
use App\Logics\FreeTaxLogic;
use Illuminate\Http\Request;
use App\Transformers\AuthorizationTransformer;
use Illuminate\Support\Facades\Hash;
use Dingo\Api\Exception\StoreResourceFailedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use App\Handlers\DwApi;
use App\Models\BondedStock;
use Illuminate\Support\Facades\Auth;
use App\Imports\ShipmentImport;
use Maatwebsite\Excel\Facades\Excel;
use Psy\Util\Json;

class ShipmentController extends BaseController
{
    protected $BaseModels = 'App\Models\Shipment';
    protected $BaseAllVat = ['invoice_no' => 'required',];//获取全部验证
    protected $BAWhere  = ['invoice_no' =>['=','']];//获取全部Where条件
    protected $BA  = ['*'];//获取全部选取字段 *是全部
    protected $BAOrder  = [['id','desc']];//获取全部字段排序

    protected $BaseLVat = [];//获取分页全部验证
    protected $BLWhere  = [
        'invoice_no'=>['like',''],
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
        'ownerCode' => '',
        'changeTime' => '',
        'srcOrderType' => '',
        'srcOrderCode' => '',
        'threeOrderCode' => '',
        'platformItemCode' => '',
        'skuCode' => '',
        'goodsCode' => '',
        'goodsName' => '',
        'skuProperty' => '',
        'salePrice' => '',
        'realDealAmount' => '',
        'costPrice' => '',
        'weightCostPrice' => '',
        'uniqueCode' => '',
        'orderType' => '',
        'orderCode' => '',
        'changeNum' => '',
        'storeHouseCode' => '',
        'storeHouseName' => '',
        'supplierCode' => '',
        'supplierName' => '',
        'inventoryType' => '',
        'qualityType' => '',
        'qualityLevel' => '',
        'batchCode' => '',
        'remark' => '',
        'extendProps' => '',
        'requestId' => '',
        'createtime' => ['type','time'],
    ];//新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
    ];//新增验证
    protected $BaseUpdate =[
        'id'=>'',
        'ownerCode' => '',
        'changeTime' => '',
        'srcOrderType' => '',
        'srcOrderCode' => '',
        'threeOrderCode' => '',
        'platformItemCode' => '',
        'skuCode' => '',
        'goodsCode' => '',
        'goodsName' => '',
        'skuProperty' => '',
        'salePrice' => '',
        'realDealAmount' => '',
        'costPrice' => '',
        'weightCostPrice' => '',
        'uniqueCode' => '',
        'orderType' => '',
        'orderCode' => '',
        'changeNum' => '',
        'storeHouseCode' => '',
        'storeHouseName' => '',
        'supplierCode' => '',
        'supplierName' => '',
        'inventoryType' => '',
        'qualityType' => '',
        'qualityLevel' => '',
        'batchCode' => '',
        'remark' => '',
        'extendProps' => '',
        'requestId' => '',
        'createtime' => '',
        'updatetime' => ['type','time'],
    ];//新增数据
    protected $BUWhere= ['id'=>['=','']];//新增数据

    public function _oneFrom($RData)
    {
        return $RData;
    }

    /**
     *基础取多个值带分页方法
     *
     * @param Request $request
     * @return void
     */
    public function BaseLimit(Request $request){
        $user_id  = Auth::id();
        $user_ids = $this->getUserIdentity($user_id);
        // var_dump($user_ids);exit;
        if(count($user_ids) == 0){
            $where_data= [];
        }else{
            $where_data= [['supplierCode','in',$user_ids]];
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

    public function _limitFrom($RData)
    {
        return $RData;
    }

    /**
     * 导入出货单
     * @param Request $request
     * @return array
     */
    public function import(Request $request)
    {
        $file = $request->file('file');
        $destinationPath = '/uploads/admin/excel/'; // public文件夹下面uploads/xxxx-xx-xx 建文件夹
        $extension = $file->getClientOriginalExtension();   // 上传文件后缀
        $fileName = date('YmdHis').mt_rand(100,999).'.'.$extension; // 重命名
        $status = $file->move(public_path().$destinationPath, $fileName); // 保存图片
        if( !$status){ return $this->error();}
        // var_dump(public_path().$destinationPath.$fileName);exit;
        $path = public_path().$destinationPath.$fileName;
        try{
            $data  = Excel::toArray(new ShipmentImport, $path);
            $allCount =count($data[0]);
            $reData = (new $this->BaseModels)->import($data[0]);
        } catch (\Exception $e) {
            // var_dump( $e->getMessage());exit;
            log_arr([$e->getMessage()] ,'shipment');
            return  $this->error('',$e->getMessage());
        }
        return  $this->success([
            'allCount'=> $allCount-1,
            'success' =>[
                'number' => count($reData['createData']),
                'data'   => $reData['createData'],
                // 'data'   => [],
            ],
            'fail' =>[
                'number' => count($reData['failData']),
                'data'   => $reData['failData'],
            ]
        ],__('base.success'));
    }

    /**
     * 出货聚合单
     *
     * @param Request $request
     * @return array
     */
    public function shipmentList(Request $request)
    {
        $data_arr = $request->all();
        $cur_page= !empty($data_arr['cur_page'])? $data_arr['cur_page']: 1;
        $size= !empty($data_arr['size'])? $data_arr['size']: 10;
        $data = DB::table('pms_shipment')->select('invoice_no');
        if(!empty($data_arr['invoice_no'])){
            $data->where('invoice_no','like','%'.$data_arr['invoice_no'].'%');
        }
        $data ->groupBy('invoice_no');
        $data->orderBy('invoice_no','desc');
        $reData = $data->paginate($size,['*'],'page',$cur_page);
        $reData = objectToArray($reData);
        foreach($reData['data'] as $k=> $v){
            // var_dump($v);exit;
            $reData['data'][$k]['objective'] = DB::table('pms_shipment')->where('invoice_no','=',$v['invoice_no'])->value('objective');
            $reData['data'][$k]['stock_no_list'] = DB::table('pms_shipment')->where('invoice_no','=',$v['invoice_no'])->pluck('stock_no');
            $reData['data'][$k]['grouping'] = DB::table('pms_shipment')->where('invoice_no','=',$v['invoice_no'])->value('grouping');

            $reData['data'][$k]['appoint_qty'] = DB::table('pms_shipment')->where('invoice_no','=',$v['invoice_no'])->sum('appoint_qty');
            $reData['data'][$k]['shipment_qty'] = DB::table('pms_shipment')->where('invoice_no','=',$v['invoice_no'])->sum('shipment_qty');
            $reData['data'][$k]['warehouse_qty'] = DB::table('pms_shipment')->where('invoice_no','=',$v['invoice_no'])->sum('warehouse_qty');
            $reData['data'][$k]['variance_qty'] = DB::table('pms_shipment')->where('invoice_no','=',$v['invoice_no'])->sum('variance_qty');

            $reData['data'][$k]['identification_passed_qty'] =  DB::table('pms_shipment')->where('invoice_no','=',$v['invoice_no'])->sum('identification_passed_qty');// 鉴别通过数量
            $reData['data'][$k]['identification_failed_qty'] =  DB::table('pms_shipment')->where('invoice_no','=',$v['invoice_no'])->sum('identification_failed_qty');// 鉴别不通过数量
            $reData['data'][$k]['inspection_failed_qty'] =  DB::table('pms_shipment')->where('invoice_no','=',$v['invoice_no'])->sum('inspection_failed_qty');// 质检未通过数量
            $reData['data'][$k]['no_identified_qty'] =  DB::table('pms_shipment')->where('invoice_no','=',$v['invoice_no'])->sum('no_identified_qty');// 未鉴定数
        }
        return  $this->success(objectToArray($reData));
    }

    /**
     *出价列表
     * @param Request $request
     * @return array
     */
    public function bondedNumberList(Request $request)
    {
        //加权
        $data_arr = $request->all();
        $cur_page= !empty($data_arr['cur_page'])? $data_arr['cur_page']: 1;
        $size= !empty($data_arr['size'])? $data_arr['size']: 10;
        $user_id  = Auth::id();
        $user_ids = $this->getUserIdentity($user_id,'id',true);
        $data = DB::table('pms_shipment')
        ->select('product_sn','good_name','spu_id',DB::raw('sum(identification_passed_qty2) as offer_qty'))
        ->groupBy('product_sn');
        if(count($user_ids) > 0){
            $invoice_no = $this->bill_id($user_ids);
            // var_dump($invoice_no );exit;
            $data->whereIn('invoice_no',$invoice_no);
        }

        if(!empty($data_arr['good_name'])){
            $data->where('good_name','like','%'.$data_arr['good_name'].'%');
        }

        if(!empty($data_arr['product_sn'])){
            $data->where('product_sn','like','%'.$data_arr['product_sn'].'%');
        }

        if(!empty($data_arr['spu_id'])){
            $data->where('spu_id','=',$data_arr['spu_id']);
        }

        $data->orderBy('product_sn','desc');
        $reData = $data->paginate($size,['*'],'page',$cur_page);
        $reData = objectToArray($reData);
        foreach($reData['data'] as $k=> $v)
        {
            $reData['data'][$k]['properties_list'] = DB::table('pms_bidding')->where('status','=',1)->where('product_sn','=',$v['product_sn'])->distinct()->pluck('properties');
            $qty = DB::table('pms_bidding')->where('status','=',1)->where('product_sn','=',$v['product_sn'])->sum('qty');
            // $pms_shipment = DB::table('pms_shipment')->where('product_sn','=',$v['product_sn']);
            // if(count($user_ids) > 0)
            // {
            //     $invoice_no = $this->bill_id($user_ids);
            //     // var_dump($invoice_no );exit;
            //     $pms_shipment->whereIn('invoice_no',$invoice_no);
            // }
            // $sell_qty = DB::table('pms_bidding')->where('product_sn','=',$v['product_sn'])->sum('qty_sold');
            //已出价数量
            $reData['data'][$k]['qty'] = $qty;
            //待出价总数
            $reData['data'][$k]['offer_qty'] = empty($reData['data'][$k]['offer_qty']) ? 0 :$reData['data'][$k]['offer_qty'];
        }
        return  $this->success(objectToArray($reData));
    }

    /**
     *出价详情
     * @param Request $request
     */
    public function bondedNumberInfo(Request $request)
    {
        $user_id  = Auth::id();
        $user_ids = $this->getUserIdentity($user_id,'id',true);
        $data = $request->all();
        if(empty($data['product_sn'])) return  $this->vdtError();

        $reData = DB::table('pms_bidding')->where('status','=',1);
        if(count($user_ids) >0)   $reData->whereIn('user_id',$user_ids);
        $reDataArr = $reData->where('product_sn','=',$data['product_sn'])->get();
        $reData = objectToArray($reDataArr);

        $shipment= DB::table('pms_shipment')
        ->select('id','product_sn','properties','good_name','sku_id','spu_id',DB::raw('sum(identification_passed_qty2) as sale_qty'))
        ->where('product_sn','=',$data['product_sn']);
        if(count($user_ids) > 0){
            $invoice_no = $this->bill_id($user_ids);
            // var_dump($invoice_no);exit;
            $shipment->whereIn('invoice_no',$invoice_no);
        }
        $shipment = $shipment->groupBy('sku_id')->get();
        $shipment = objectToArray($shipment);
        $sale_qty =[];//合并数组中间字段
        $objective = '宁波保税仓';//目的厂库
        foreach($reData as $k=> $v){
            //预计收入
            // $reData[$k]['objective'] = DB::table('pms_shipment')->where('product_sn','=',$v['product_sn'])->value('objective');
            //最低出价
            $lowest_price = FreeTaxLogic::lowestPriceDefaultData($v['sku_id']);
            $reData[$k] = array_merge($v,$lowest_price);

//            $price =  $this->bidding_oversea_lowest_price($v['sku_id']);
//            $reData[$k]['lower_price1']     = !empty($price[0]) ? $price[0]: 0;
//            $reData[$k]['lower_price2']     = !empty($price[1]) ? $price[1]: 0;
//            $reData[$k]['lower_price3']     = $reData[$k]['lower_price1']> $reData[$k]['lower_price2'] ?$reData[$k]['lower_price2'] : $reData[$k]['lower_price1'];

            // $bidding_price  = $this->bidding_price($v['sku_id'],$v['price']);
            $reData[$k]['bidding_price']   = FreeTaxLogic::getExceptIncome($v['sku_id'],$v['price']);
            $reData[$k]['objective']       = $objective;
            $reData[$k]['sale_qty']        =  0;//可售预占值
            $reData[$k]['shipment_id']     =  0;//预占库存id
            $sale_qty[$v['sku_id']]        = $k;
        }

        foreach($shipment as $k_s =>$s_v){
            // var_dump($sale_qty[$s_v['sku_id']]);exit;
            if(isset($sale_qty[$s_v['sku_id']])){
                $reData[$sale_qty[$s_v['sku_id']]]['sale_qty'] = empty($s_v['sale_qty']) ? 0 : $s_v['sale_qty'];//可售
                continue;
            }
            $shipmentRow =[];
//            $price =  $this->bidding_oversea_lowest_price($s_v['sku_id']);

            $lowest_price = FreeTaxLogic::lowestPriceDefaultData($s_v['sku_id']);

            $shipmentRow['sale_qty']       = empty($s_v['sale_qty']) ? 0 : $s_v['sale_qty'];//可售
            $shipmentRow['good_name']      = $s_v['good_name'];//商品名
            $shipmentRow['product_sn']     = $s_v['product_sn'];//货号
            $shipmentRow['properties']     = $s_v['properties'];//规格
            $shipmentRow['sku_id']         = $s_v['sku_id'];//sku_id
            $shipmentRow['qty']            = 0;//已出价数量
            $shipmentRow['status']         = 1;//状态
            $shipmentRow['price']          = NULL;//出售价格
            $shipmentRow['user_id']        = NULL;//出售用户
            $shipmentRow['id']             = NULL;//ID
            $shipmentRow['bidding_no']     = NULL;//出价编号
            $shipmentRow['qty_sold']       = NULL;//卖出个数
            $shipmentRow['sold_sty']       = NULL;//在售数量
            $shipmentRow['lower_price1']   = $lowest_price['lower_price1'];
            $shipmentRow['lower_price2']   = $lowest_price['lower_price2'];
            $shipmentRow['lower_price3']   = $lowest_price['lower_price3'];
//            $shipmentRow['lower_price1']   = !empty($price[0]) ? $price[0]: 0;
//            $shipmentRow['lower_price2']   = !empty($price[1]) ? $price[1]: 0;
//            $shipmentRow['lower_price3']   = $shipmentRow['lower_price1']> $shipmentRow['lower_price2'] ?$shipmentRow['lower_price2'] : $shipmentRow['lower_price1'];
            $shipmentRow['createed_at']    = NULL;//创建时间
            $shipmentRow['updated_at']     = NULL;//修改时间
            $shipmentRow['objective']      = $objective;//目的厂库
            $shipmentRow['shipment_id']    = $s_v['id'];
            $shipmentRow['bidding_price']  = [
                "transfer_fee"              => NULL,
                "tech_service_fee"          => NULL,
                "expect_income"             => NULL,
                "operate_fee"               => NULL,
                "bidding_price"             => NULL,
                "free_postage_service_fee"  => NULL
            ];
            $reData[] = $shipmentRow;
        }
        return $this->success(array_values(array_sort($reData,'sku_id')));
    }

    /**
     *
     * 最低价
     * @return void
     */
    public function bidding_oversea_lowest_price($sku_id){
        $method = '3,37,apiUrl';
        // $request_Data = $request->all();
        $requestArr['sku_id'] = $sku_id;
        // $id = $request->id;
        $data =  (new DwApi($requestArr))->uniformRequest($method,$requestArr);
        $arr  = json_decode($data,true);
        $reData= [];
        if(!empty($arr['code'] )  && $arr['code'] == 200)
        {
            if(!empty($arr['data']['items'][0]) ){
                $reData[0] = empty($arr['data']['items'][0]['lowest_price']) ? 0 :$arr['data']['items'][0]['lowest_price'];
            }else{
                $reData[0] =  0;
            }
            if(!empty($arr['data']['items'][1]) ){
                $reData[1] = empty($arr['data']['items'][1]['lowest_price']) ? 0 :$arr['data']['items'][1]['lowest_price'];
            }else{
                $reData[1] =  0;
            }
            return $reData;
        }else{
            return [];
        }
    }


    //和预计收入
    public function bidding_price($sku_id,$bidding_price,$bidding_type =6){
        $method = '3,6,apiUrl';
        // $request_Data = $request->all();
        $requestArr['sku_id'] = $sku_id;
        $requestArr['bidding_price'] = $bidding_price;
        $requestArr['bidding_type'] = $bidding_type;

        $key = sprintf('dw:except_income:%d-%d-%d',$sku_id,$bidding_price,$bidding_type);
        $data = Redis::get($key);
        if($data){
            return json_decode($data,true);
        }

        $data =  (new DwApi($requestArr))->uniformRequest($method,$requestArr);
        $arr  = json_decode($data,true);
        if(!empty($arr['code'] )  && $arr['code'] == 200)
        {
            Redis::setex($key,3600,Json::encode($arr['data']));
            return $arr['data'];
        }else{
            return [];
        }
    }

    /**
     *出价
     *
     * @param Request $request
     * @return
     */
    public function offer(Request $request)
    {

        $request_Data = $request->all();
        // var_dump($request->all());exit;
        //校验价格
        if(empty($request_Data['price']) ) return $this->error(__('admin.Shipment.price.error'));
        if(!is_numeric($request_Data['price']) ) return $this->error(__('admin.Shipment.price.error'));
        if($request_Data['price']<= 0 ||$request_Data['price']>100000000) return $this->error(__('admin.Shipment.price.error'));
        //校验价格 qty
        if(!is_numeric($request_Data['qty'])) return $this->error(__('admin.Shipment.qty.error'));
        //校验价格 qty 是否足够
        if($request_Data['qty']<= 0) return $this->error(__('admin.Shipment.qty.error'));

        $user_id  = Auth::id();
        $user_ids = $this->getUserIdentity($user_id,'id',true);
        //校验价格 qty 是否足够
        if($request_Data['qty']<= 0) return $this->error(__('admin.Shipment.qty.error'));
        $pms_bidding_id= $request_Data['shipment_id'];
        $shipmentRow = DB::table('pms_shipment')->select('spu_id','sku_id','good_name','product_sn','properties')->where('id','=',$pms_bidding_id)->first();
        $shipmentRow    = objectToArray($shipmentRow );

        $sku_id         = $shipmentRow['sku_id'];
        $spu_id         = $shipmentRow['spu_id'];
        $good_name      = $shipmentRow['good_name'];
        $product_sn     = $shipmentRow['product_sn'];
        $properties     = $shipmentRow['properties'];
        $shipment= DB::table('pms_shipment')->where('sku_id','=',$sku_id);
        if(count($user_ids) > 0){
            $invoice_no = $this->bill_id($user_ids);
            // var_dump($invoice_no);exit;
            $shipment->whereIn('invoice_no',$invoice_no);
        }
        $shipmentList    = $shipment->orderBy('id','asc')->get(); //实际有的库存
        $shipmentList    = objectToArray($shipmentList );
        if(count($shipmentList)<=0) return $this->error(__('Shipment._qty.error'));
        $identification_passed_qty2 =array_column($shipmentList,'identification_passed_qty2');
        $qty = array_sum($identification_passed_qty2);
        if($request_Data['qty']> $qty) return $this->error(__('Shipment._qty.error'));
        $method = '3,15,apiUrl';
        $requestArr['sku_id'] = $sku_id;
        $requestArr['price'] = $request_Data['price'];

        //获取园区列表
        $logic = (new FreeTaxLogic());
        $inventory_list = $logic->getInventoryListOffer($spu_id, $sku_id,$request_Data['qty']);
        if(!$inventory_list){
            return $this->error($logic->err_msg);
        }

        $requestArr['inventory_list'] = $inventory_list;
        $data =  (new DwApi($requestArr))->uniformRequest($method,$requestArr);
        $arr  = $data ? json_decode($data,true) : [];
        if(!$arr || $arr['code']!=200){
            return $this->error($arr['msg']??'接口调用失败');
        }

        // var_dump($data);exit;
        if(!empty($arr['data']['bidding_no']))
        {
            $user_id  = Auth::id();
            $update_bidding = [
                'user_id' => $user_id,
                'bidding_no' => $arr['data']['bidding_no'],
                'price' => $requestArr['price'],
                'wh_inv_no' => Json::encode($inventory_list),
                'qty' => $request_Data['qty'],
                'sku_id'  =>$sku_id,
                'good_name' =>$good_name,
                'product_sn' =>$product_sn,
                'properties' =>$properties,
                'createed_at' => date('Y-m-d H:i:s'),
            ];
            $shipmentUpdate = [];
            //减库存
            $shipmentUpdateQty =$request_Data['qty'];
            foreach($shipmentList as $s_l_v)
            {
                if($shipmentUpdateQty<=0) break;
                if($shipmentUpdateQty > $s_l_v['identification_passed_qty2']){
                    $shipmentUpdate[] =[
                        'id' => $s_l_v['id'],
                        'identification_passed_qty2' => 0
                    ];
                }else{
                    $shipmentUpdate[] =[
                        'id' => $s_l_v['id'],
                        'identification_passed_qty2' => $s_l_v['identification_passed_qty2']-$shipmentUpdateQty
                    ];
                }
                $shipmentUpdateQty -= $s_l_v['identification_passed_qty2'];
            }
            if(count($shipmentUpdate)>0){
                (new $this->BaseModels)->updateBatch($shipmentUpdate);
            }
            DB::table('pms_bidding')->insert($update_bidding);
            return $this->success();
        }else{
            log_arr([$data]);
            return $this->error('未获取到出价编号');
        }
    }

    /**
     *修改出价
     *
     * @param Request $request
     * @return
     */
    public function reBid(Request $request)
    {
        $method = '3,16,apiUrl';
        // $id = $request->id;
        $request_Data = $request->all();
        // var_dump($request->all());exit;
        if(empty($request_Data['bidding_no'])) return $this->error(__('admin.Shipment.delBid.error'));
        //校验价格
        if(empty($request_Data['price']) ) return $this->error(__('admin.Shipment.price.error'));
        if(!is_numeric($request_Data['price']) ) return $this->error(__('admin.Shipment.price.error'));
        if($request_Data['price']< 0 ||$request_Data['price']>100000000) return $this->error(__('admin.Shipment.price.error'));
        //校验价格 qty
        if(!is_numeric($request_Data['qty'])) return $this->error(__('admin.Shipment.qty.error'));
        //校验价格 qty 是否足够
        if($request_Data['qty']<= 0) return $this->error(__('admin.Shipment.qty.error'));

        $bidding_no = $request_Data['bidding_no'];
        $user_id  = Auth::id();
        $user_ids = $this->getUserIdentity($user_id,'id',true);
        $biddingModel = DB::table('pms_bidding');
        if(count($user_ids) > 0){
            $biddingModel->whereIn('user_id',$user_ids);
        }
        $bidding = $biddingModel->where('bidding_no','=',$bidding_no)->first();
        $bidding = objectToArray($bidding);

        if(empty($bidding) || count($bidding) <= 0) return $this->error(__('admin.Shipment.delBid.error'));
        //校验价格 qty 是否足够
        if($request_Data['qty']<= 0) return $this->error(__('admin.Shipment.qty.error'));

        $shipment= DB::table('pms_shipment')->where('sku_id','=',$bidding['sku_id']);
        if(count($user_ids) > 0){
            $invoice_no = $this->bill_id($user_ids);
            // var_dump($invoice_no );exit;
            $shipment->whereIn('invoice_no',$invoice_no);
        }
        $shipmentList    = $shipment->orderBy('id','asc')->get(); //实际有的库存
        $shipmentList    = objectToArray($shipmentList );
        if(count($shipmentList)<=0) return $this->error(__('Shipment._qty.error'));
        $identification_passed_qty2 =array_column($shipmentList,'identification_passed_qty2');
        $qty_sale = array_sum($identification_passed_qty2);

        //获取园区列表
        $logic = (new FreeTaxLogic());
//        $wh_inv_no = $logic->getCampusCode($shipmentList[0]['spu_id'], $bidding['sku_id']);
//        if(empty($wh_inv_no)){
//            return $this->error($logic->err_msg);
//        }
        $inventory_list = $logic->getInventoryListRe($shipmentList[0]['spu_id'], $bidding['sku_id'],$request_Data['qty'],$bidding['wh_inv_no']);
        if(!$inventory_list){
            return $this->error($logic->err_msg);
        }

        //TODO::判断条件
        if($request_Data['qty'] > $qty_sale+$bidding['qty'] ) return $this->error(__('Shipment._qty.error'));
        $requestArr['bidding_no'] = $request_Data['bidding_no'];
        $requestArr['price']      = $request_Data['price'];
        $requestArr['inventory_list'] = $inventory_list;
        // var_dump($requestArr);exit;
        $data =  (new DwApi($requestArr))->uniformRequest($method,$requestArr);
        $arr  = json_decode($data,true);
        $shipmentUpdate = [];
        if(!empty($arr['data']['bidding_no']))
        {
            $user_id  = Auth::id();
            $update_bidding = [
                // 'user_id' => $user_id,
                'bidding_no' => $arr['data']['bidding_no'],
                'price' => $requestArr['price'],
                'wh_inv_no' => Json::encode($inventory_list),
                'qty' => $request_Data['qty'],
                // 'sku_id' => $requestArr['inventory_list']['qty'],
                // 'properties' => $requestArr['inventory_list']['qty'],
                // 'old_qty' => $requestArr['inventory_list']['old_qty'],
            ];
            //大于扣库存 小于加库存
            if($request_Data['qty'] > $bidding['qty']){

                foreach($shipmentList as $s_l_v)
                {
                    $shipmentUpdateQty = $request_Data['qty']-$bidding['qty'];
                    if($shipmentUpdateQty<=0) break;
                    if($shipmentUpdateQty > $s_l_v['identification_passed_qty2']){
                        $shipmentUpdate[] =[
                            'id' => $s_l_v['id'],
                            'identification_passed_qty2' => 0
                        ];
                    }else{
                        $shipmentUpdate[] =[
                            'id' => $s_l_v['id'],
                            'identification_passed_qty2' => $s_l_v['identification_passed_qty2']-$shipmentUpdateQty
                        ];
                    }
                    $shipmentUpdateQty -= $s_l_v['identification_passed_qty2'];
                }
            }else{

                $shipmentList  = array_reverse($shipmentList);
                //倒着加库存
                foreach($shipmentList as $s_l_v)
                {
                    $shipmentUpdateQty = $bidding['qty']-$request_Data['qty'];
                    if($shipmentUpdateQty<=0) break;
                    if($s_l_v['identification_passed_qty2'] == $s_l_v['identification_passed_qty']) continue;
                    if($shipmentUpdateQty - $s_l_v['identification_passed_qty'] > 0){
                        $shipmentUpdate[] =[
                            'id' => $s_l_v['id'],
                            'identification_passed_qty2' => $s_l_v['identification_passed_qty']
                        ];
                        $shipmentUpdateQty -= $s_l_v['identification_passed_qty'];
                    }else{
                        $shipmentUpdate[] =[
                            'id' => $s_l_v['id'],
                            'identification_passed_qty2' => $s_l_v['identification_passed_qty2']+$shipmentUpdateQty
                        ];
                        $shipmentUpdateQty = 0;
                    }
                }
            }
            // DB::table('pms_bidding')->insert($update_bidding);
            if(count($shipmentUpdate)>0){
                (new $this->BaseModels)->updateBatch($shipmentUpdate);
            }
            DB::table('pms_bidding')->where('bidding_no','=',$request_Data['bidding_no'])->update($update_bidding );
            return $this->success();
        }else{
            log_arr([$data]);
            return $this->error('未获取到出价编号');
        }
    }

    /**
     *取消出价
     *
     * @param Request $request
     * @return
     */
    public function delBid(Request $request)
    {
        $method = '3,17,apiUrl';
        $request_Data = $request->all();
        $user_id  = Auth::id();
        $user_ids = $this->getUserIdentity($user_id,'id',true);
        if(empty($request_Data['bidding_no'])) return $this->error(__('admin.Shipment.delBid.error'));
        $bidding_no = $request_Data['bidding_no'];
        $bidding = DB::table('pms_bidding')->where('status','=',1)->where('bidding_no','=',$bidding_no)->first();
        if(empty($bidding)) return $this->error(__('admin.Shipment.delBid.error'));
        $requestArr['bidding_no'] = $bidding_no;
        $data =  (new DwApi($requestArr))->uniformRequest($method,$requestArr);
        $arr  = json_decode($data,true);
        if (!empty($arr['code']) && ($arr['code'] == 200 || $arr['msg'] == '出价已取消，请不要重复操作'))
        {
            if ($arr['code'] == 200) {
                $qty_sold = $arr['data']['qty'] - $arr['data']['qty_cancel'];//出价数量-取消数量
            }else{
                $qty_sold = FreeTaxLogic::biddingInfo($bidding_no)['qty_sold'];
            }
            $update = ['status' =>2,'qty_sold' =>$qty_sold];
            $shipment= DB::table('pms_shipment')->where('sku_id','=',$bidding->sku_id);
            if(count($user_ids) > 0){
                $invoice_no = $this->bill_id($user_ids);
                // var_dump($invoice_no );exit;
                $shipment->whereIn('invoice_no',$invoice_no);
            }
            $shipmentList    = $shipment->orderBy('id','desc')->get(); //实际有的库存
            $shipmentList    = objectToArray($shipmentList );
            $shipmentUpdate = [];
            $shipmentUpdateQty = $bidding->qty;
            //倒着加库存
            foreach($shipmentList as $s_l_v)
            {
                if($shipmentUpdateQty<=0) break;
                if($s_l_v['identification_passed_qty2'] == $s_l_v['identification_passed_qty']) continue;
                if($shipmentUpdateQty - $s_l_v['identification_passed_qty2'] > 0){
                    $shipmentUpdate[] =[
                        'id' => $s_l_v['id'],
                        'identification_passed_qty2' => $s_l_v['identification_passed_qty']
                    ];
                    $shipmentUpdateQty -= $s_l_v['identification_passed_qty'];
                }else{
                    $shipmentUpdate[] =[
                        'id' => $s_l_v['id'],
                        'identification_passed_qty2' => $s_l_v['identification_passed_qty2']+$shipmentUpdateQty
                    ];
                    $shipmentUpdateQty = 0;
                }
            }
            // DB::table('pms_bidding')->insert($update_bidding);
            if(count($shipmentUpdate)>0){
                (new $this->BaseModels)->updateBatch($shipmentUpdate);
            }
            DB::table('pms_bidding')->where('bidding_no','=',$bidding_no)->update($update);
            return $this->success();
        }else{
            log_arr([$data]);
            return $this->error($arr['msg']??'接口调用失败');
        }
    }

    /**
     *预计所得
     *
     * @return void
     */
    public function biddingPrice(Request $request)
    {
        if(empty($request->sku_id) || empty($request->bidding_price) ) return $this->error(__('base.vdt'));
        $sku_id = $request->sku_id;
        $price = $request->bidding_price;
        $type = empty($request->type) ? 6 : $request->type;
        // $data =  $this->bidding_price($sku_id,$price,$type);
        $data = FreeTaxLogic::setExceptIncome($sku_id,$price,$type);
        // $data['transfer_fee'] = 121;
        // $data['tech_service_fee'] = 121;
        // $data['expect_income'] = 121;
        // $data['operate_fee'] = 1212;
        // $data['bidding_price'] = 121;
        // $data['free_postage_service_fee'] = 122;
        return $this->success($data);
    }

    /**
     * 最低价
     * @param Request $request
     * @return void
     */
    public function lowestPrice(Request $request){
        if(empty($request->sku_id)) return $this->error(__('base.vdt'));
        $sku_id = $request->sku_id;
        $row = FreeTaxLogic::getLowestPrice($sku_id);
        return $this->success($row);
    }

    //获取园区列表
    public static function wh_inv_no($sku_id)
    {
        $requestArr['sku_id'] = $sku_id;
        $requestArr['bidding_types'] = [23];
        //出价类型,0:普通现货，1:普通预售，3：跨境，6:入仓，7:极速现货，8:极速预售，12:品牌专供现货，13 :品牌专供入仓，14：品牌直发，23：保税仓，25：跨境寄售
        $method = '3,0,apiUrl';
        $data =  (new DwApi($requestArr))->uniformRequest($method,$requestArr);
        $arr  = json_decode($data,true);
        if(!empty($arr['data'][0]['campus_list'][0]))
        {
            $wh_inv_no = '';
            if(!empty($arr['data'][0]['campus_list'][0]['wh_inv_no'])){
                $wh_inv_no  = $arr['data'][0]['campus_list'][0]['wh_inv_no'];
            }
            return $wh_inv_no ;
        }else{
            return '';
        }
    }
}