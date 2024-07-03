<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\BaseController;
use Illuminate\Http\Request;
use App\Transformers\AuthorizationTransformer;
use Illuminate\Support\Facades\Hash;
use Dingo\Api\Exception\StoreResourceFailedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Handlers\DwApi;
use App\Models\BondedStock;
use Illuminate\Support\Facades\Auth;
use App\Exports\Export;
use  App\Imports\ExcelExport;
use Maatwebsite\Excel\Facades\Excel;

class StockController extends BaseController
{
    protected $BaseModels = 'App\Models\Stock';
    protected $BaseAllVat = [];//获取全部验证
    protected $BAWhere  = [];//获取全部Where条件
    protected $BA  = ['*'];//获取全部选取字段 *是全部
    protected $BAOrder  = [['id','desc']];//获取全部字段排序

    protected $BaseLVat = [];//获取分页全部验证
    protected $BLWhere  = [
        'goodsCode'=> ['like',''],
        'batchCode'=> ['like',''],
        'supplierName'=> ['like',''],
        'threeOrderCode'=> ['like',''],
        'WHERE' => ['WHERE',''],
    ];//获取全部分页Where条件
    protected $BL  = ['*'];//获取全部分页选取字段 *是全部
    protected $BLOrder  = [['id','desc']];//获取全部分页字段排序
    protected $exportField =
    [
        "ownerCode"=>"货主编码",
        "changeTime"=>"出入库时间",
        "srcOrderType"=>"源单单据类型",
        "srcOrderCode"=>"源单单据编码",
        "threeOrderCode"=>"三方单据编码",
        "platformItemCode"=>"平台商家编码",
        "itemCode"=>"sku编码",
        "goodsCode"=>"货号",
        "goodsName"=>"品名",
        "skuProperty"=>"规格",
        "salePrice"=>"零售价",
        "realDealAmount"=>"实际成交金额",
        "costPrice"=>"成本价",
        "weightCostPrice"=>"加权成本价",
        "uniqueCode"=>"唯一码",
        "orderType"=>"单据类型",
        "changeNum"=>"出入库数量",
        "storeHouseName"=>"仓库名称",
        "supplierName"=>"供应商名称",
        "inventoryType"=>"库存类型（自营、寄卖）",
        "qualityType"=>"质量类型(正品，瑕疵)",
        "qualityLevel"=>"质量等级（优、良、一级）",
        "batchCode"=>"批次编码",
        "remark"=>"备注"
    ];

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
        // var_dump(json_encode($this->BaseCreate,true));
        if(!empty($RData)){
            $RData['salePrice']         =  $RData['salePrice']*100;
            $RData['realDealAmount']    =  $RData['realDealAmount']*100;
            $RData['costPrice']         =  $RData['costPrice']*100;
            $RData['weightCostPrice']   =  $RData['weightCostPrice']*100;
        }
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
        $where_data= [];
        if(count($user_ids) > 0){
            $where_data= [['supplierCode','in',$user_ids]];
        }
        $msg = !empty($this->BaseLVatMsg) ? $this->BaseLVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseLVat,$msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        if(!empty($data['WHERE']))
        {
            $data['WHERE'] = json_decode($data['WHERE'],true);
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
        foreach($RData['data'] as &$v){
            $v['salePrice']         =  $v['salePrice']*100;
            $v['realDealAmount']    =  $v['realDealAmount']*100;
            $v['costPrice']         =  $v['costPrice']*100;
            $v['weightCostPrice']   =  $v['weightCostPrice']*100;
        }
        return $RData;
    }


    /**
     *通过货号找在库的sku
     * @return void
     */
    public function stockAll(Request $request)
    {
        $data = $request->all();
        if(empty($data['goodsCode']))
        {
            return $this->error(__('base.vdt'));
        }
        $user_id  = Auth::id();
        $user_ids = $this->getUserIdentity($user_id);
        $where_data =[];
        if(count($user_ids) > 0){
            $where_data[]= ['supplierCode','in',$user_ids];
        }
        $where_data[] =['goodsCode','=',$data['goodsCode']];
        $RData = (new $this->BaseModels)->BaseAll($where_data,['*'],[['id','desc']]);
        foreach($RData as &$v){
            $v['salePrice']         =  $v['salePrice']*100;
            $v['realDealAmount']    =  $v['realDealAmount']*100;
            $v['costPrice']         =  $v['costPrice']*100;
            $v['weightCostPrice']   =  $v['weightCostPrice']*100;
        }
        return $this->success($RData);
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
        // var_dump($data['field']);exit;
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
}