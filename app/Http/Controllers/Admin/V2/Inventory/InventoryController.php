<?php

namespace App\Http\Controllers\Admin\V2\Inventory;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Admin\V2\ProductSpecAndBar;
use App\Exports\Export;
use App\Models\Admin\V2\WmsWarehouseArea;

class InventoryController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\Inventory';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = []; //获取全部Where条件
    protected $BA  = ['*']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $BLWhere  = [
        'WHERE' => ['WHERE', ''],
        'ORDER' => ['ORDER', ''],
    ]; //获取全部分页Where条件
    protected $BL  = ['*']; //获取全部分页选取字段 *是全部
    // protected $BLOrder  = [['created_at', 'desc'], ['id', 'desc']]; //获取全部分页字段排序
    protected $BLOrder  = [];
    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序
    protected $NAME;
    protected $exportField;



    public function setInvField()
    {

        $this->NAME =   __('excel.total_inv.title');
        $this->exportField = [
            'warehouse_name' => __('excel.warehouse_name'),
            'warehouse_code' =>  __('excel.total_inv.warehouse_code'),
            'product.sku' =>  __('excel.total_inv.product_sku'),
            'product.product.product_sn' =>  __('excel.total_inv.product_sn'),
            'product.product.name' => __('excel.total_inv.product_name'),
            'product.spec_one' =>  __('excel.total_inv.product_spec'),
            'product.spec_two' =>  __('excel.total_inv.product_spec').'2',
            'product.spec_three' =>  __('excel.total_inv.product_spec').'3',
            'quality_type' =>  __('excel.total_inv.quality_type'),
            'quality_level' =>  __('excel.total_inv.quality_level'),
            'wh_inv' =>  __('excel.total_inv.wh_inv'),
            'shelf_inv' =>  __('excel.total_inv.shelf_inv'),
            'sale_inv' =>  __('excel.total_inv.sale_inv'),
            'shelf_sale_inv' =>  __('excel.total_inv.shelf_sale_inv'),
            'shelf_lock_inv' =>  __('excel.total_inv.shelf_lock_inv'),
            'wt_send_inv' =>  __('excel.total_inv.wt_send_inv'),
            'wt_shelf_inv' =>  __('excel.total_inv.wt_shelf_inv'),
            'freeze_inv' =>  __('excel.total_inv.freeze_inv'),
            'wt_shelf_cfm' =>  __('excel.total_inv.wt_shelf_cfm'),
            'trf_inv' =>  __('excel.total_inv.trf_inv'),
        ];
    }


    public function setUniqField()
    {

        $this->NAME =  __('excel.uniq_inv.title');
        $this->exportField = [
            'uniq_code' => __('excel.uniq_inv.uniq_code'),
            'product.sku' => __('excel.uniq_inv.product_sku'),
            'product.product.product_sn' => __('excel.uniq_inv.product_sn'),
            'product.product.name' => __('excel.uniq_inv.product_name'),
            'product.spec_one' => __('excel.uniq_inv.product_spec'),
            'product.spec_two' => __('excel.uniq_inv.product_spec').'2',
            'product.spec_three' => __('excel.uniq_inv.product_spec').'3',
            'bar_code' => __('excel.uniq_inv.bar_code'),
            'lot_num' => __('excel.uniq_inv.lot_num'),
            'supplier.name' => __('excel.supplier_name'),
            'buy_price' => __('excel.uniq_inv.buy_price'),
            'quality_type' => __('excel.uniq_inv.quality_type'),
            'quality_level' => __('excel.uniq_inv.quality_level'),
            'inv_status_txt' => __('excel.uniq_inv.inv_status_txt'),
            'recv_num' => __('excel.uniq_inv.recv_num'),
            'warehouse.warehouse_name' => __('excel.uniq_inv.warehouse_name'),
            'area_txt' => __('excel.uniq_inv.area_txt'),
            'location_code' => __('excel.uniq_inv.location_code'),
            'created_at' => __('excel.uniq_inv.created_at'),
        ];
    }

    public function list(Request $request)
    {
        $this->setInvField();
        $where_data = [];
        $data = $request->all();
        $cur_page   = !empty($data['cur_page']) ? $data['cur_page'] : 1;
        $size       = !empty($data['size']) ? $data['size'] : 10;

        //修改参数  request要存在或者BUWhere存在

        if (!empty($data['WHERE'])) {
            $data['WHERE'] = json_decode($data['WHERE'], true);
        }
        if (!empty($data['ORDER'])) {
            $this->BLOrder = array_merge(json_decode($data['ORDER'], true), $this->BLOrder);
        }

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
        $RData = (new $this->BaseModels)->list($where_data, $this->BL, $this->BLOrder, $cur_page, $size);
        // if (method_exists($this, '_limitFrom')) $RData = $this->_limitFrom($RData);
        $RData['column'] = $this->getColumn();
        return  $this->success($RData, __('base.success'));
    }


    public function  invExport(Request $request)
    {
        $this->_export($request, function ($request) {
            $this->_beforeExport($request, 'setInvField');
            $res = $this->list($request)->getData(1);
            return $res;
        });
        // $this->_beforeExport($request,'setInvField');
        // $res = $this->list($request)->getData(1);
        // if ($res['code'] != 200) return $this->error($res['msg']);
        // $data = $res['data']['data'];
        // $this->obClear();
        // return  Excel::download((new $this->exportName($data, $this->exportFormat())), $this->NAME . date('YmdHis') . mt_rand(100, 999) . '.xlsx');
    }

    // //导出模版
    // public function Example(Request $request)
    // {
    //     return  Excel::download((new Export([$this->exportField], [])), $this->NAME . '-模板文件.xlsx');
    // }

    public function uniqCodeList(Request $request)
    {
        $this->setUniqField();
        return parent::BaseLimit($request);
    }

    public function uniqExportList($request){
        $this->exportField =  [
            'uniq_code' => __('excel.uniq_inv.uniq_code'),
            'sku' => __('excel.uniq_inv.product_sku'),
            'product_sn' => __('excel.uniq_inv.product_sn'),
            'product_name' => __('excel.uniq_inv.product_name'),
            'spec_one' => __('excel.uniq_inv.product_spec'),
            'spec_two' => __('excel.uniq_inv.product_spec').'2',
            'spec_three' => __('excel.uniq_inv.product_spec').'3',
            'bar_code' => __('excel.uniq_inv.bar_code'),
            'lot_num' => __('excel.uniq_inv.lot_num'),
            'supplier_name' => __('excel.supplier_name'),
            'buy_price' => __('excel.uniq_inv.buy_price'),
            'quality_type' => __('excel.uniq_inv.quality_type'),
            'quality_level' => __('excel.uniq_inv.quality_level'),
            'inv_status_txt' => __('excel.uniq_inv.inv_status_txt'),
            'recv_num' => __('excel.uniq_inv.recv_num'),
            'warehouse_name' => __('excel.uniq_inv.warehouse_name'),
            'area_txt' => __('excel.uniq_inv.area_txt'),
            'location_code' => __('excel.uniq_inv.location_code'),
            'created_at' => __('excel.uniq_inv.created_at'),
        ];
        $WHERE = $request->get('WHERE');
        if ($WHERE) {
            if (is_string($WHERE)) $WHERE = json_decode($WHERE, true);
            $raw = $this->model->jsonWhere($WHERE);
            if ($raw) {
                $data = $this->model->whereRaw($raw);
            }
        }
        if(!isset($data))$data = $this->model->query();
        $ids = $request->get('ids');
        if ($ids && (!is_array($ids))) $ids = explode(',', $ids);
        if($ids) $data->whereIn('id',$ids);
        $data->whereNotIN('in_wh_status', [0, 4, 7])
        ->limit(15000);
        // if($data->count() > 15000) return ['code'=>500,'msg'=>'最多导出15000条','data'=>[]];
        $export_data = [];
        $bar_codes = $data->pluck('bar_code')->unique()->toArray();
        $spec =  ProductSpecAndBar::whereIn('bar_code',$bar_codes)->select('bar_code','spec_one','spec_two','spec_three','product_id','sku')->groupBy('bar_code');
        $pro =$spec->get()->keyBy('bar_code')->toArray();
        $area_codes =$data->pluck('area_code')->unique()->toArray();
        $warehouse_area = WmsWarehouseArea::whereIn('area_code',$area_codes)->select('area_code','warehouse_code','area_name')->groupBy('area_code');
        $area =$warehouse_area->get()->keyBy('area_code')->toArray();
        foreach($data->get() as $d){
            if(isset( $pro[$d->bar_code])){
                $sku = $pro[$d->bar_code]['sku'];
                $product_sn= $pro[$d->bar_code]['product']['product_sn'];
                $name = $pro[$d->bar_code]['product']['name'];
                $spec_one = $pro[$d->bar_code]['spec_one'];
                $spec_two = $pro[$d->bar_code]['spec_two'];
                $spec_three = $pro[$d->bar_code]['spec_three'];
            }else{
                $sku = '';
                $product_sn='';
                $name = '';
                $spec_one ='';
                $spec_two ='';
                $spec_three ='';
            }
            if(isset($area[$d->area_code]))$area_name = $area[$d->area_code]['area_name'];
            else $area_name ='';
            $export_data[] = [
                'uniq_code' => $d->uniq_code,
                'sku' => $sku,
                'product_sn' =>$product_sn,
                'product_name' => $name  ,
                'spec_one' => $spec_one,
                'spec_two' => $spec_two,
                'spec_three' => $spec_three,
                'bar_code' => $d->bar_code,
                'lot_num' => $d->lot_num,
                'supplier_name' => $this->model->_getRedisMap('sup_map',$d->sup_id),
                'buy_price' =>$d->buy_price,
                'quality_type' => $d->quality_type,
                'quality_level' => $d->quality_level,
                'inv_status_txt' => $d->inv_status_txt,
                'recv_num' => $d->recv_num,
                'warehouse_name' => $this->model->_getRedisMap('warehouse_map',$d->warehouse_code),
                'area_txt' => $area_name,
                'location_code' => $d->location_code,
                'created_at' => $d->created_at->format('Y-m-d H:i:s'),
            ]    ;
        }
        return['data'=>['data'=> $export_data],'code'=>200];
    }

    public function  uniqCodeExport(Request $request)
    {
        // $this->_beforeExport($request,'setUniqField');
        // if($this->exportAll){
        //     return $this->success([],'后台导出');
        return $this->_export($request, function ($request) {
            $this->setUniqField();
            $this->_beforeExport($request, 'setUniqField');
            return $this->uniqExportList($request);
        });
        // $page = 1;
        // $name = '';
        // while (true) {
        //     $request->merge(['cur_page' => $page, 'size' => 1000]);
        //     $this->setUniqField();
        //     $this->_beforeExport($request);
        //     $res = $this->uniqCodeList($request)->getData(1);
        //     $name = $this->exportExcelTmp($res, $name);
        //     // Log::info($res['data']['current_page']);
        //     if (!($res['data']['total'] ?? 0) || $page >= 1) break;
        //     $page++;
        // }
        // exportExcelEnd($name);
    }

    public function invDetail(Request $request)
    {
        $vat = [
            // 'warehouse_code'=>'required',
            // 'bar_code' => 'required',
            'name' => 'required',
            'row' => 'required',
        ];
        $data = $this->vatReturn($request, $vat);
        extract($this->invSearch($request));
        $rData =  $this->model->invDetail($data, $where_data,  $this->BL, $this->BLOrder, $cur_page, $size);
        return  $this->success($rData);
    }

    protected function  invSearch(Request $request)
    {
        $where_data = [];
        $warehouse_code = $request->get('warehouse_code');
        $data = $request->all();
        $cur_page   = !empty($data['cur_page']) ? $data['cur_page'] : 1;
        $size       = !empty($data['size']) ? $data['size'] : 10;

        //修改参数  request要存在或者BUWhere存在

        if (!empty($data['WHERE'])) {
            $data['WHERE'] = json_decode($data['WHERE'], true);
        }
        if (!empty($data['ORDER'])) {
            $this->BLOrder = array_merge(json_decode($data['ORDER'], true), $this->BLOrder);
        }
        // if (!empty($data['SHOW'])) {
        //     $this->BL=json_decode($data['SHOW'],true);

        // }

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

        return     [
            'warehouse_code' => $warehouse_code,
            'where_data' => $where_data,
            'cur_page' => $cur_page,
            'size' => $size,
        ];
    }


    public function putawayInv(Request $request)
    {
        extract($this->invSearch($request));
        $RData = $this->model->putawayInv($warehouse_code, $where_data, $this->BL, $this->BLOrder, $cur_page, $size);
        return  $this->success($RData, __('base.success'));
    }



    //供应商库存
    public  function  supplierInv(Request $request)
    {
        extract($this->invSearch($request));

        $RData = $this->model->supInv($warehouse_code, $where_data, $this->BL, $this->BLOrder, $cur_page, $size);

        return  $this->success($RData, __('base.success'));
    }

    //唯一码库存
    public function uniqInv(Request $request)
    {
        $uniq_code = $request->get('uniq_code', '');
        extract($this->invSearch($request));
        if (empty($warehouse_code) || empty($uniq_code)) return $this->error(__('base.vdt'));
        $RData = $this->model->uniqInv($warehouse_code, $uniq_code, $where_data, $select = ['*'], $order = [['id', 'desc']], $cur_page = 1, $size = 10);

        return  $this->success($RData, __('base.success'));
    }

    //供应商库存，兼容唯一码查询
    public  function  supplierInvV2(Request $request)
    {
        extract($this->invSearch($request));

        $this->exportField = [
            'product.sku' => __('excel.uniq_inv.product_sku'),
            'product.product.product_sn' => __('excel.uniq_inv.product_sn'),
            'product.product.name' => __('excel.uniq_inv.product_name'),
            'product.spec_one' =>__('excel.uniq_inv.product_spec'),
            'product.spec_two' =>__('excel.uniq_inv.product_spec').'2',
            'product.spec_three' =>__('excel.uniq_inv.product_spec').'3',
            'amount' => __('excel.inv_amount'),
            'buy_price' => __('excel.buy_price'),
            'uniq_code_1' => __('excel.uniq_inv.uniq_code'),
            'wh_inv' => __('excel.total_inv.wh_inv'),
            'sale_inv' => __('excel.total_inv.sale_inv'),
            'lock_inv' => __('excel.lock_inv'),
            'wt_send_inv' => __('excel.total_inv.wt_send_inv'),
            'freeze_inv' => __('excel.total_inv.freeze_inv'),
            'warehouse_name' => __('excel.uniq_inv.warehouse_name'),
            'sup_name' => __('excel.supplier_name'),
            // 'type_txt' => __('excel.inv_type'),
            'quality_type' => __('excel.uniq_inv.quality_type'),
            'quality_level' => __('excel.uniq_inv.quality_level'),
            'lot_num' => __('excel.uniq_inv.lot_num'),
        ];
        // 有唯一码，优先查询唯一码
        if ($request->get('uniq_code', '')) {
            $uniq_code = preg_replace('/\r?\n|(?<!\n)\r/', ',', $request->get('uniq_code'));
            if ($uniq_code) {
                if (empty($warehouse_code)) return $this->error(__('base.vdt'));
                $RData = $this->model->uniqInv($warehouse_code, $uniq_code, $where_data, $select = ['*'], $order = [['id', 'desc']], $cur_page = $request->get('cur_page',10), $size = $request->get('size',10));

                $RData['column'] = $this->getColumn();
                return  $this->success($RData, __('base.success'));
            }
        }

        $RData = $this->model->supInv($warehouse_code, $where_data, $this->BL, $this->BLOrder, $cur_page, $size);

        $RData['column'] = $this->getColumn();
        return  $this->success($RData, __('base.success'));
    }


    //sku商品列表
    public function skuList(Request $request)
    {
        extract($this->invSearch($request));

        $RData = $this->model->skuList($warehouse_code, $where_data, $this->BL, $this->BLOrder, $cur_page, $size);

        return  $this->success($RData, __('base.success'));
    }
}
