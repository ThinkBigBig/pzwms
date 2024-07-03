<?php

namespace App\Http\Controllers\Admin\V2\Inventory;

use App\Http\Controllers\Admin\V2\BaseController;
use App\Models\Admin\V2\ProductSpecAndBar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class SupInvController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\SupInv';
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
    protected $BLOrder  = [['created_at', 'desc'], ['id', 'desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序

    protected $NAME;
    protected $exportField;

    public function setExcelField()
    {
        $this->NAME = __('excel.good_inv_detail');
        $this->exportField = [
            'product.sku' => __('excel.uniq_inv.product_sku'),
            'product.product.product_sn' => __('excel.uniq_inv.product_sn'),
            'product.product.name' => __('excel.uniq_inv.product_name'),
            'product.spec_one' => __('excel.uniq_inv.product_spec'),
            'product.spec_two' => __('excel.uniq_inv.product_spec').'2',
            'product.spec_three' => __('excel.uniq_inv.product_spec').'3',
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
            'inv_type' => __('excel.inv_type'),
            'quality_type' => __('excel.uniq_inv.quality_type'),
            'quality_level' => __('excel.uniq_inv.quality_level'),
            'lot_num' => __('excel.uniq_inv.lot_num'),
        ];
    }

    // public function exportFormat()
    // {
    //     $name = $this->NAME;
    //     $field =[
    //         'product_sku' => __('excel.uniq_inv.product_sku'),
    //         'product_sn' => __('excel.uniq_inv.product_sn'),
    //         'product_name' => __('excel.uniq_inv.product_name'),
    //         'product_spec' =>__('excel.uniq_inv.product_spec'),
    //         'amount' => __('excel.inv_amount'),
    //         'buy_price' => __('excel.buy_price'),
    //         'uniq_code_1' => __('excel.uniq_inv.uniq_code'),
    //         'wh_inv' => __('excel.total_inv.wh_inv'),
    //         'sale_inv' => __('excel.total_inv.sale_inv'),
    //         'lock_inv' => __('excel.lock_inv'),
    //         'wt_send_inv' => __('excel.total_inv.wt_send_inv'),
    //         'freeze_inv' => __('excel.total_inv.freeze_inv'),
    //         'warehouse_name' => __('excel.uniq_inv.warehouse_name'),
    //         'sup_name' => __('excel.supplier_name'),
    //         'type_txt' => __('excel.inv_type'),
    //         'quality_type' => __('excel.uniq_inv.quality_type'),
    //         'quality_level' => __('excel.uniq_inv.quality_level'),
    //         'lot_num' => __('excel.uniq_inv.lot_num'),
    //     ];
    //     $format = [
    //         // '分类名称'=>['color'=>'red']
    //     ];
    //     return [
    //         'name' => $name,
    //         'format' => $format,
    //         'field' => $field,
    //     ];
    // }

    public function saleField()
    {
        $this->NAME = __('excel.sale_inv_detail');
        $this->exportField = [
            'sup_name' => __('excel.supplier_name'),
            'warehouse_name' => __('excel.uniq_inv.warehouse_name'),
            'product.sku' => __('excel.uniq_inv.product_sku'),
            'product.product.product_sn' => __('excel.uniq_inv.product_sn'),
            'product.product.name' => __('excel.uniq_inv.product_name'),
            'product.spec_one' => __('excel.uniq_inv.product_spec'),
            'product.spec_two' => __('excel.uniq_inv.product_spec').'2',
            'product.spec_three' => __('excel.uniq_inv.product_spec').'3',
            'bar_code' => __('excel.uniq_inv.bar_code'),
            'quality_type' => __('excel.uniq_inv.quality_type'),
            'quality_level' => __('excel.uniq_inv.quality_level'),
            'uniq_code_1' => __('excel.uniq_inv.uniq_code'),
            'sale_inv' => __('excel.total_inv.sale_inv'),
            'lock_inv' => __('excel.lock_inv'),
            'freeze_inv' => __('excel.total_inv.freeze_inv'),
        ];
    }


    //可售产品库存明细
    public function saleList(Request $request)
    {
        $this->BLWhere['sale_inv'] = ['>', 0];
        $this->BL = ['id', 'warehouse_name', 'warehouse_code', 'bar_code', 'sup_name', 'quality_type', 'quality_level', 'uniq_code_1', DB::raw('sum(sale_inv) sale_inv'), DB::raw('sum(lock_inv) lock_inv'), DB::raw('sum(freeze_inv) freeze_inv')];
        $request->merge(['groupByRaw' => 'warehouse_name,bar_code,sup_name,quality_type,uniq_code_1']);
        $request->merge(['setExcelField' => 'saleField']);
        return $this->BaseLimit($request);
    }

    public function _exportFiledEdit()
    {
        $update = [

            __('excel.uniq_inv.product_sku') => 'product_sku',
            __('excel.uniq_inv.product_sn') => 'product_sn',
            __('excel.uniq_inv.product_name') => 'product_name',
            __('excel.uniq_inv.product_spec') => 'product_spec',
            __('excel.uniq_inv.product_spec').'2' => 'product_spec_two',
            __('excel.uniq_inv.product_spec').'3' => 'product_spec_three',
        ];
        if ($this->NAME == __('excel.good_inv_detail')) {
            $update[__('excel.inv_type')] = 'inv_type_txt';
        }
        return $update;
    }

    //可售库存导出
    public function saleExport(Request $request)
    {
        $params = $this->_beforeExport($request, 'saleField');
        $res = $this->saleList($request)->getData(1);
        if ($res['code'] != 200) return $this->error($res['msg']);
        $data = $res['data']['data'];
        ob_end_clean();
        ob_start();
        return  Excel::download((new $this->exportName($data, $params)), $this->NAME . date('YmdHis') . mt_rand(100, 999) . '.xlsx');
    }
    //产品库存明细
    public function  supInvList($request)
    {
        $WHERE = $request->get('WHERE');
        if ($WHERE) {
            if (is_string($WHERE)) $WHERE = json_decode($WHERE, true);
            $raw = $this->model->jsonWhere($WHERE);
            if ($raw) {
                $data = $this->model->whereRaw($raw);
            }
        }
        if (!isset($data)) $data = $this->model->query();
        // $data->where('sale_inv', '>', 0)
        // ->select('id', 'warehouse_name', 'warehouse_code', 'bar_code', 'sup_name', 'quality_type', 'quality_level', 'uniq_code_1', DB::raw('sum(sale_inv) sale_inv'), DB::raw('sum(lock_inv) lock_inv'), DB::raw('sum(freeze_inv) freeze_inv'))
        // ->groupByRaw('warehouse_name,bar_code,sup_name,quality_type,uniq_code_1')
        $ids = $request->get('ids');
        if ($ids && !is_array($ids)) $ids = explode(',', $ids);
        if($ids) $data->whereIn('id',$ids);
        $data->limit(15000);
        // if($data->count() > 15000) return ['code'=>500,'msg'=>'最多导出15000条','data'=>[]];
        $export_data = [];
        $bar_codes = $data->pluck('bar_code')->unique()->toArray();
        $spec =  ProductSpecAndBar::whereIn('bar_code', $bar_codes)->select('bar_code', 'spec_one','spec_two','spec_three', 'product_id', 'sku')->groupBy('bar_code');
        $pro = $spec->get()->keyBy('bar_code')->toArray();
        foreach ($data->get() as $d) {
            if (isset($pro[$d->bar_code])) {
                $sku = $pro[$d->bar_code]['sku'];
                $product_sn = $pro[$d->bar_code]['product']['product_sn'];
                $name = $pro[$d->bar_code]['product']['name'];
                $spec_one = $pro[$d->bar_code]['spec_one'];
                $spec_two = $pro[$d->bar_code]['spec_two'];
                $spec_three = $pro[$d->bar_code]['spec_three'];
            } else {
                $sku = '';
                $product_sn = '';
                $name = '';
                $spec_one = '';
                $spec_two = '';
                $spec_three = '';
            }
            $export_data[] = [
                // 'sup_name' => $d->sup_name,
                // 'warehouse_name' => $d->warehouse_name,
                // 'product.sku' => $sku,
                // 'product.product.product_sn' => $product_sn,
                // 'product.product.name' => $name,
                // 'product.spec_one' => $spec_one,
                // 'bar_code' => $d->bar_code,
                // 'quality_type' => $d->quality_type,
                // 'quality_level' => $d->quality_level,
                // 'uniq_code_1' => $d->uniq_code_1,
                // 'sale_inv' => $d->sale_inv,
                // 'lock_inv' => $d->lock_inv,
                // 'freeze_inv' => $d->freeze_inv,

                'product_sku' =>  $sku,
                'product_sn' => $product_sn,
                'product_name' => $name,
                'product_spec' => $spec_one,
                'product_spec_two' => $spec_two,
                'product_spec_three' => $spec_three,
                'amount' => $d->amount,
                'buy_price' => $d->buy_price,
                'uniq_code_1' => $d->uniq_code_1,
                'wh_inv' => $d->wh_inv,
                'sale_inv' => $d->sale_inv,
                'lock_inv' => $d->lock_inv,
                'wt_send_inv' => $d->wt_send_inv,
                'freeze_inv' => $d->freeze_inv,
                'warehouse_name' => $d->warehouse_name,
                'sup_name' => $d->sup_name,
                'inv_type' => $d->inv_type,
                'quality_type' => $d->quality_type,
                'quality_level' => $d->quality_level,
                'lot_num' => $d->lot_num,
            ];
        }
        return ['data' => ['data' => $export_data], 'code' => 200];
    }

    public function  Export(Request $request)
    {
        return $this->_export($request, function ($request) {
            $this->_beforeExport($request, 'setExcelField');
            return $this->supInvList($request);
        });
    }
}
