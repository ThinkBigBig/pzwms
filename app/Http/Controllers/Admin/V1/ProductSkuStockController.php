<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Export;
use App\Exports\StockProductExport;
use App\Logics\StockProductLogic;
use App\Models\ProductSkuStock;

class ProductSkuStockController extends BaseController
{
    protected $BaseModels = 'App\Models\ProductSkuStock';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = [
        'goodsCode' => ['like', ''],
        'batchCode' => ['like', ''],
        'supplierName' => ['like', ''],
        'storeGoodsNum' => ['>', ''],
        'WHERE' => ['WHERE', ''],
    ]; //获取全部Where条件
    protected $BA  = ['*']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $BLWhere  = [
        'goodsCode' => ['like', ''],
        'batchCode' => ['like', ''],
        'supplierName' => ['like', ''],
        'storeGoodsNum' => ['>', ''],
        'WHERE' => ['WHERE', ''],
    ]; //获取全部分页Where条件
    protected $BL  = ['*']; //获取全部分页选取字段 *是全部
    protected $BLOrder  = [['id', 'desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序

    protected $exportField = [
        "ownerCode" => "货主编码",
        "itemCode" => "商品编码",
        "goodsCode" => "货号",
        "itemName" => "品名",
        "barCode" => "商品条码",
        "skuProperty" => "规格",
        "weightCostPrice" => "加权成本价",
        "uniqueCode" => "唯一码",
        "storeGoodsNum" => "在仓库存",
        "lockGoodsNum" => "锁定库存",
        "waiteGoodsNum" => "待发库存",
        "freezeGoodsNum" => "冻结库存",
        "storeHouseCode" => "仓库编码",
        "storeHouseName" => "仓库名称",
        "supplierCode" => "供应商编码",
        "supplierName" => "供应商名称",
        "inventoryType" => "库存类型",
        "qualityType" => "质量类型",
        "qualityLevel" => "质量等级",
        "batchCode" => "批次编码",
        "remark" => "备注"
    ];

    protected $BaseCreateVat = []; //新增验证
    protected $BaseCreate = [
        'ownerCode' => '',
        'itemCode' => '',
        'goodsCode' => '',
        'itemName' => '',
        'barCode' => '',
        'skuProperty' => '',
        'weightCostPrice' => '',
        'uniqueCode' => '',
        'storeGoodsNum' => '',
        'lockGoodsNum' => '',
        'waiteGoodsNum' => '',
        'freezeGoodsNum' => '',
        'storeHouseCode' => '',
        'storeHouseName' => '',
        'supplierCode' => '',
        'supplierName' => '',
        'inventoryType' => '',
        'qualityType' => '',
        'qualityLevel' => '',
        'batchCode' => '',
        'remark' => '',
        'requestId' => '',
        'createtime' => ['type', 'time'],
    ]; //新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
    ]; //新增验证
    protected $BaseUpdate = [
        'id' => '',
        'ownerCode' => '',
        'itemCode' => '',
        'goodsCode' => '',
        'itemName' => '',
        'barCode' => '',
        'skuProperty' => '',
        'weightCostPrice' => '',
        'uniqueCode' => '',
        'storeGoodsNum' => '',
        'lockGoodsNum' => '',
        'waiteGoodsNum' => '',
        'freezeGoodsNum' => '',
        'storeHouseCode' => '',
        'storeHouseName' => '',
        'supplierCode' => '',
        'supplierName' => '',
        'inventoryType' => '',
        'qualityType' => '',
        'qualityLevel' => '',
        'batchCode' => '',
        'remark' => '',
        'requestId' => '',
        'updatetime' => ['type', 'time'],
    ]; //新增数据
    protected $BUWhere = ['id' => ['=', '']]; //新增数据

    /**
     *基础取全部值方法
     *
     * @param Request $request
     * @return void
     */
    public function BaseAll(Request $request)
    {
        $user_id  = Auth::id();
        $user_ids = $this->getUserIdentity($user_id, 'username', true);
        $where_data = [];
        if (count($user_ids) > 0) {
            $where_data = [['supplierCode', 'in', $user_ids]];
        }
        $msg = !empty($this->BaseAllVatMsg) ? $this->BaseAllVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseAllVat, $msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        //修改参数  request要存在或者BUWhere存在
        foreach ($this->BAWhere as $B_k => $B_v) {
            if (!empty($B_v) && empty($B_v[1]) && isset($data[$B_k])) {
                if ($B_v[0] == 'allLike') {
                    $where_data[] = ["concat({$B_v[2]}) like ?", $B_v[0], ["%{$data[$B_k]}%"]];
                    continue;
                }
                $where_data[] = [$B_k, $B_v[0], $data[$B_k]];
                continue;
            }
            if (!empty($B_v) && (!empty($B_v[1]) || $B_v[1] === 0)) {
                $where_data[] = [$B_k, $B_v[0], $B_v[1]];
                continue;
            }
        }
        $RData = (new $this->BaseModels)->BaseAll($where_data, $this->BA, $this->BAOrder);
        if (method_exists($this, '_allFrom')) $RData = $this->_oneFrom($RData);
        return  $this->success($RData, __('base.success'));
    }
    public function _oneFrom($RData)
    {
        // $BaseCreate =[
        //     'ownerCode' => '',
        //     'itemCode' => '',
        //     'goodsCode' => '',
        //     'itemName' => '',
        //     'barCode' => '',
        //     'skuProperty' => '',
        //     'weightCostPrice' => '',
        //     'uniqueCode' => '',
        //     'storeGoodsNum' => '',
        //     'lockGoodsNum' => '',
        //     'waiteGoodsNum' => '',
        //     'freezeGoodsNum' => '',
        //     'storeHouseCode' => '',
        //     'storeHouseName' => '',
        //     'supplierCode' => '',
        //     'supplierName' => '',
        //     'inventoryType' => '',
        //     'qualityType' => '',
        //     'qualityLevel'=>'',
        //     'batchCode'=>'',
        //     'remark'=>'',
        //     'requestId'=>'',
        //     'createtime' => ['type','time'],
        // ];
        // var_dump(json_encode($BaseCreate,true));
        if (!empty($RData)) {
            if (!empty($RData['weightCostPrice'])) {
                $RData['weightCostPrice'] = $RData['weightCostPrice'] * 100;
            }
        }
        return $RData;
    }

    /**
     *基础取多个值带分页方法
     *
     * @param Request $request
     * @return void
     */
    public function BaseLimit(Request $request)
    {
        $user_id  = Auth::id();
        $user_ids = $this->getUserIdentity($user_id);
        $where_data = [];
        if (count($user_ids) > 0) {
            $where_data = [['supplierCode', 'in', $user_ids]];
        }
        $msg = !empty($this->BaseLVatMsg) ? $this->BaseLVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseLVat, $msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        if (!empty($data['WHERE'])) {
            $data['WHERE'] = json_decode($data['WHERE'], true);
        }
        $cur_page = !empty($data['cur_page']) ? $data['cur_page'] : 1;
        $size = !empty($data['size']) ? $data['size'] : 10;
        //修改参数  request要存在或者BUWhere存在
        foreach ($this->BLWhere as $B_k => $B_v) {
            // var_dump($B_k[1]);exit;
            if (!empty($B_v) && empty($B_v[1]) && isset($data[$B_k])) {
                if ($B_v[0] == 'allLike') {
                    $where_data[] = ["concat({$B_v[2]}) like ?", $B_v[0], ["%{$data[$B_k]}%"]];
                    continue;
                }
                $where_data[] = [$B_k, $B_v[0], $data[$B_k]];
                continue;
            }
            if (!empty($B_v) && (!empty($B_v[1]) || $B_v[1] === 0)) {
                $where_data[] = [$B_k, $B_v[0], $B_v[1]];
                continue;
            }
        }
        $RData = (new $this->BaseModels)->BaseLimit($where_data, $this->BL, $this->BLOrder, $cur_page, $size);
        // if(method_exists($this,'_limitFrom')) $RData = $this->_limitFrom($RData);
        return  $this->success($RData, __('base.success'));
    }

    public function _limitFrom($RData)
    {
        if (!empty($RData['data'])) {
            foreach ($RData['data'] as &$v) {
                if (!empty($RData)) {
                    $v['weightCostPrice'] = $v['weightCostPrice'] * 100;
                }
            }
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
        if (empty($data['goodsCode'])) {
            return $this->error(__('base.vdt'));
        }
        $user_id  = Auth::id();
        $user_ids = $this->getUserIdentity($user_id);
        $where_data = [];
        if (count($user_ids) > 0) {
            $where_data[] = ['supplierCode', 'in', $user_ids];
        }
        $where_data[] = ['goodsCode', '=', $data['goodsCode']];
        $RData = (new $this->BaseModels)->BaseAll($where_data, ['*'], [['id', 'desc']]);
        foreach ($RData as &$v) {
            if (!empty($v)) {
                $v['weightCostPrice'] = $v['weightCostPrice'] * 100;
            }
        }
        return $this->success($RData);
    }

    /**
     * 重写列表 满足多条件
     *
     * @param Request $request
     * @return void
     */
    public function Limit(Request $request)
    {
        $user_id  = Auth::id();
        $user_ids = $this->getUserIdentity($user_id);
        $where_data = [];
        if (count($user_ids) > 0) {
            $where_data = [['supplierCode', 'in', $user_ids]];
        }
        $msg = !empty($this->BaseLVatMsg) ? $this->BaseLVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseLVat, $msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        //处理
        if (!empty($data['WHERE'])) {
            $data['WHERE'] = json_decode($data['WHERE'], true);
        }
        $cur_page = !empty($data['cur_page']) ? $data['cur_page'] : 1;
        $size = !empty($data['size']) ? $data['size'] : 10;
        //修改参数  request要存在或者BUWhere存在
        foreach ($this->BLWhere as $B_k => $B_v) {
            // var_dump($B_k[1]);exit;
            if (!empty($B_v) && empty($B_v[1]) && isset($data[$B_k])) {
                if ($B_v[0] == 'allLike') {
                    $where_data[] = ["concat({$B_v[2]}) like ?", $B_v[0], ["%{$data[$B_k]}%"]];
                    continue;
                }
                $where_data[] = [$B_k, $B_v[0], $data[$B_k]];
                continue;
            }
            if (!empty($B_v) && (!empty($B_v[1]) || $B_v[1] === 0)) {
                $where_data[] = [$B_k, $B_v[0], $B_v[1]];
                continue;
            }
        }
        $RData = (new $this->BaseModels)->BaseLimit($where_data, $this->BL, $this->BLOrder, $cur_page, $size);
        if (method_exists($this, '_limitFrom')) $RData = $this->_limitFrom($RData);
        return  $this->success($RData, __('base.success'));
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
        $where = [];
        if (!empty($data['ids'])) {
            $ids = explode(',', $data['ids']);
            $where = [['id', 'in', $ids]];
        }
        $exportField = $this->exportField;
        // $ssss = [];
        // foreach($exportField as $k=>$v){
        //     $ssss[] = [
        //        'value' => $k,
        //        'label' => $v,
        //     ];
        // }
        // var_dump(json_encode($ssss,true));exit;
        $exportFieldRow =  $exportField;
        if (!empty($data['field'])) {
            $exportFieldRow  = [];
            $field = explode(',', $data['field']);
            foreach ($field as $field_v) {
                $exportFieldRow[$field_v] = $exportField[$field_v];
            }
        }
        // $where = [['id','in',$ids ]];
        $bondedAdopt  = (new  $this->BaseModels)->BaseAll($where);
        $headers[]  = $exportFieldRow;
        $data = [];
        foreach ($bondedAdopt as $v) {
            $row = [];
            foreach ($exportFieldRow as $e_k => $e_sv) {
                $row[$e_k] = $v[$e_k];
                // var_dump($e_k);exit;
                // var_dump( $v);exit;
            }
            $data[] = $row;
        }
        // var_dump($data);exit;
        $export = new Export($headers, $data);
        return Excel::download($export, date('YmdHis') . '.xlsx',);
    }

    public function exportBidData(Request $request)
    {   
        $data = $request->all();
        $data['size'] = 100000;
        $data['user_id'] = Auth::id();
        $list = $this->stockList($data)['data'];


        foreach ($list as &$item) {
            $infoStr = explode(',',$item['infoStr'])[0];
            $info = explode('|',$infoStr);
            $item['itemName'] = $info[0]??'';
            $item['storeHouseName'] = $info[1]??'';
            $item['barCode'] = $info[2]??'';
            $item['weightCostPrice'] = $item['storeGoodsNum'] > 0 ? $item['weightCostPrice'] / $item['storeGoodsNum'] : 0;
            $item['left_stock'] = 0;
            $item['finnal_price'] = 0;
            $item['goat_threshold_price'] = 0;
            $item['dw_threshold_price'] = 0;
            $item['stockx_threshold_price'] = 0;
            $item['carryme_threshold_price'] = 0;
            $item['status'] = "0";
            $item['barCode'] = $item['barCode'] . "\t";
            $item['purchase_url'] = '';
            $item['purchase_name'] = '';
        }

        $headers[] = [
            'itemName' => '商品名',
            'goodsCode' => '货号',
            'skuProperty' => '规格',
            'storeGoodsNum' => '在仓库存',
            'left_stock' => '在售库存',
            'storeHouseName' => '所在仓库',
            'weightCostPrice' => '加权成本',
            'finnal_price' => '到手价',
            'goat_threshold_price' => 'goat门槛价',
            'dw_threshold_price' => '得物门槛价',
            'stockx_threshold_price' => 'stockx门槛价',
            'carryme_threshold_price' => 'carryme门槛价',
            'barCode' => '商品条码',
            'storeHouseCode' => '仓库编号',
            'status' => '上下架',
            'purchase_name' => '空卖名称',
            'purchase_url' => '空卖链接',
        ];
        $name = sprintf('慎独出价商品信息%s.xlsx', date('YmdHis'));
        $params = StockProductLogic::stockxThresholdFormulas([]);

        return Excel::download(new StockProductExport($headers, $list, $params), $name);
    }

    private function stockList($data)
    {
        $user_ids = $this->getUserIdentity($data['user_id']);
        $where_data = [];
        if (count($user_ids) > 0) {
            $where_data = [['supplierCode', 'in', $user_ids]];
        }

        if (!empty($data['WHERE'])) {
            $data['WHERE'] = json_decode($data['WHERE'], true);
        }
        $cur_page = !empty($data['cur_page']) ? $data['cur_page'] : 1;
        $size = !empty($data['size']) ? $data['size'] : 10;

        foreach ($this->BLWhere as $B_k => $B_v) {
            if (!empty($B_v) && empty($B_v[1]) && isset($data[$B_k])) {
                if ($B_v[0] == 'allLike') {
                    $where_data[] = ["concat({$B_v[2]}) like ?", $B_v[0], ["%{$data[$B_k]}%"]];
                    continue;
                }
                $where_data[] = [$B_k, $B_v[0], $data[$B_k]];
                continue;
            }
            if (!empty($B_v) && (!empty($B_v[1]) || $B_v[1] === 0)) {
                $where_data[] = [$B_k, $B_v[0], $B_v[1]];
                continue;
            }
        }

        $list = (new ProductSkuStock())
        ->addListWhere($where_data)
        ->where('storeGoodsNum', '>', 0)
        ->select([ 'goodsCode', 'skuProperty', 'storeHouseCode'])
            ->selectRaw('GROUP_CONCAT(itemName,"|",storeHouseName,"|",barCode) AS infoStr')
            ->selectRaw('SUM(storeGoodsNum) as storeGoodsNum')
            ->selectRaw('SUM(weightCostPrice*storeGoodsNum) as weightCostPrice')
            ->groupBy(['goodsCode', 'skuProperty', 'storeHouseCode'])
            ->paginate($size, ['*'], 'page', $cur_page)->toArray();
        return $list;
    }
}
