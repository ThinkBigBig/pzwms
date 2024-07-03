<?php

namespace App\Logics\wms;

use App\Logics\BaseLogic;
use App\Models\Admin\V2\Inventory;
use App\Models\Admin\V2\ProductSpecAndBar;
use App\Models\Admin\V2\WmsStockLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use function PHPUnit\Framework\isNull;

/**
 * 库存
 */
class Stock extends BaseLogic
{

    function logSearch($params, $export = false)
    {
        $params['duration'] = getDuration($params['duration'] ?? '');
        $model = new WmsStockLog();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            return $model->whereBetween('created_at', $params['duration'])->orderBy('id', 'desc')->with(['supplier', 'specBar', 'warehouse', 'createUser', 'adminUser']);
        });

        foreach ($list['data'] as &$item) {
            self::sepBarFormat($item);
            if ($export) {
                $item['bar_code'] = $item['bar_code'] . "\t";
                $item['batch_no'] = $item['batch_no'] . "\t";
            }
        }
        return $list;
    }

    // pda库存查询
    function search($params)
    {
        $type = $params['type']; //1-库区 2-位置码 3-条形码 4-唯一码
        $res = [];
        switch ($type) {
            case 1:
                $params['area_code'] = $params['code'];
                $res = $this->searchByArea($params);
                break;
            case 2:
                $params['location_code'] =  $params['code'];
                $res = $this->searchByLocationCode($params);
                break;
            case 3:
                $params['bar_code'] =  $params['code'];
                $res = $this->searchByBarCode($params);
                break;
            case 4:
                $params['uniq_code'] =  $params['code'];
                $res = $this->searchByUniqCode($params);
                break;
        }
        $num = $res['num'] ?? [];
        $list = $res['list'] ?? [];
        $product = $res['product'] ?? [];
        foreach ($list as &$item) {
            $item['bar_code'] = $item['bar_code'] ?? '';
            $item['sku'] = $item['sku'] ?? '';
            $item['lot_num'] = $item['lot_num'] ?? '';
            $item['location_code'] = $item['location_code'] ?? '';
            $item['available'] = $item['available'] ?? 0;
            $item['lock_in'] = $item['lock_in'] ?? 0;
            $item['total'] = $item['total'] ?? 0;
            $item['quality_type'] = $item['quality_type'] ?? '';
            $item['quality_level'] = $item['quality_level'] ?? '';
            $item['supplier'] = $item['supplier'] ?? '';
        }
        // $data = compact('list', 'product');
        if ($num) $data['num'] = $num;
        if ($list) $data['list'] = $list;
        if ($product) $data['product'] = $product;
        return $data;
    }

    // 根据库区查询
    function searchByArea($params)
    {
        $where = [
            'area_code' => $params['area_code'],
            'warehouse_code' => $params['warehouse_code'],
        ];
        $list = Inventory::where($where)->whereNotIn('in_wh_status', [0, 4, 7])->selectRaw('location_code,COUNT(id) as total,COUNT(IF(inv_status=5,1,NULL)) as available,COUNT(IF(inv_status=6,1,NULL)) as lock_in')->groupBy('location_code')->get();

        // 产品数量 位置码数量
        return [
            // 'num' => $this->_searchNum($where, 'COUNT(DISTINCT product_id) as product_num,COUNT(DISTINCT location_code) as location_code_num,0 as sku_num'),
            'num' => $this->_searchNum($where, 'COUNT(*) as product_num,COUNT(DISTINCT location_code) as location_code_num,0 as sku_num'),
            'list' => $list,
        ];
    }

    private function _searchNum($where, $select)
    {
        $tenantId = ADMIN_INFO['tenant_id'];
        $num = DB::table('wms_inv_goods_detail')
            // ->leftJoin('wms_spec_and_bar', function ($join) use ($tenantId) {
            //     $join->on('wms_inv_goods_detail.bar_code', '=', 'wms_spec_and_bar.bar_code')
            //         ->where('wms_spec_and_bar.tenant_id', $tenantId);
            // })
            ->where('wms_inv_goods_detail.tenant_id', $tenantId)
            ->whereNotIn('in_wh_status', [0, 4, 7])
            ->where($where)->selectRaw($select)->first();
        $num = $num ? json_decode(json_encode($num), true) : [];
        return $num;
    }

    function searchByLocationCode($params)
    {
        $where = [
            'location_code' => $params['location_code'],
            'warehouse_code' => $params['warehouse_code'],
        ];
        $model = Inventory::where($where)->whereNotIn('in_wh_status', [0, 4, 7]);
        $list = $model->groupBy('bar_code')->groupBy('lot_num')->selectRaw('bar_code,lot_num,COUNT(id) as total,COUNT(IF(inv_status=5,1,NULL)) as available,COUNT(IF(inv_status=6,1,NULL)) as lock_in')->get();
        foreach ($list as &$item) {
            $item['spec_bar'] = $item['product'];
            self::sepBarFormat($item);
        }
        return [
            // 'num' => $this->_searchNum($where, '0 as location_code_num,count(distinct wms_inv_goods_detail.bar_code) as sku_num,count(distinct product_id) as product_num'),
            'num' => $this->_searchNum($where, '0 as location_code_num,count(distinct wms_inv_goods_detail.bar_code) as sku_num,count(*) as product_num'),
            'list' => $list,
        ];
    }

    function searchByBarCode($params)
    {
        $where = [
            'wms_inv_goods_detail.bar_code' => $params['bar_code'],
            'warehouse_code' => $params['warehouse_code'],
        ];
        $model = Inventory::where($where)->whereNotIn('in_wh_status', [0, 4, 7]);
        $list = $model->groupBy('location_code')->groupBy('lot_num')->selectRaw('location_code,lot_num,COUNT(id) as total,COUNT(IF(inv_status=5,1,NULL)) as available,COUNT(IF(inv_status=6,1,NULL)) as lock_in')->get();
        $product = ProductSpecAndBar::getInfo($params['bar_code']);
        return [
            // 'num' => $this->_searchNum($where, 'count(distinct location_code) as location_code_num,0 as sku_num,count(distinct product_id) as product_num'),
            'num' => $this->_searchNum($where, 'count(distinct location_code) as location_code_num,0 as sku_num,count(*) as product_num'),
            'list' => $list,
            'product' => $product,
        ];
    }

    function searchByUniqCode($params)
    {
        $where = [
            'uniq_code' => $params['uniq_code'],
            'warehouse_code' => $params['warehouse_code'],
        ];
        $list = Inventory::where($where)->with(['supplier', 'product'])->whereNotIn('in_wh_status', [0, 4, 7])->selectRaw('uniq_code,location_code,bar_code,quality_type,quality_level,lot_num,sup_id')->get()->toArray();
        foreach ($list as &$item) {
            $item['spec_bar'] = $item['product'];
            self::sepBarFormat($item);
            unset($item['product']);
        }
        return [
            'list' => $list,
            'product' => [
                'sku' => $list[0]['sku'] ?? '',
                'name' => $list[0]['name'] ?? '',
            ],
        ];
    }

    // 唯一码详情
    function detail($params)
    {
        $where = [
            'bar_code' => $params['bar_code'],
            'location_code' => $params['location_code'],
            'warehouse_code' => $params['warehouse_code'],
        ];
        $list = Inventory::where($where)->whereNotIn('in_wh_status', [0, 4, 7])->select(['location_code', 'lot_num', 'uniq_code', 'quality_type', 'quality_level'])->get()->toArray();
        $product = ProductSpecAndBar::getInfo($params['bar_code']);
        return compact('product', 'list');
    }

    //单据类型
    function logType($params)
    {
        $code = $params['code'];
        preg_match("/^[A-Z]+/", $code, $pre);
        $pre = $pre[0] ?? '';
        $type = 0;
        switch ($pre) {
            case 'RKD':
                $type = WmsStockLog::ORDER_RKD;
                break;
            case 'DJD':
                $type = WmsStockLog::ORDER_DJD;
                break;
            case 'CKD':
                $type = WmsStockLog::ORDER_CKD;
                break;
            case 'ZZYWD':
                $type = WmsStockLog::ORDER_YWD;
                break;
            case 'KSYWD':
                $type = WmsStockLog::ORDER_YWD;
                break;
            case 'PDSQ':
                $type = WmsStockLog::ORDER_PDSQ;
                break;
            case 'QXCKD':
                $type = WmsStockLog::ORDER_QXCKD;
                break;
            case 'PDD':
                $type = WmsStockLog::ORDER_PDD;
                break;
            case 'SHD':
                $type = WmsStockLog::ORDER_SHD;
                break;
            case 'ZJD':
                $type = WmsStockLog::ORDER_ZJD;
                break;
            case 'SJD':
                $type = WmsStockLog::ORDER_SJD;
                break;
            default:
                # code...
                break;
        }
        return $type;
    }
}
