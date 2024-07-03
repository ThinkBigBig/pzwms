<?php

namespace App\Logics\wms;

use App\Logics\BaseLogic;
use App\Models\Admin\V2\PurchaseOrders;
use App\Models\Admin\V2\PurchaseDetails;
use App\Models\Admin\V2\WmsPurchaseStatement;

class Purchase extends BaseLogic
{
    static function summaryUpdate($order,$details,$inv_type=0)
    {
        // if (in_array($order->status, [0, 1])) return false;
        // $info = PurchaseOrders::where('id', $order->id)->with(['details'])->first()->toArray();
        // foreach ($info['details'] as $detail) {
        foreach ($details as  $detail) {
            $sku = $detail['product'] ?? [];
            $product = $sku['product'] ?? [];
            WmsPurchaseStatement::updateOrCreate([
                'buy_code' => $inv_type?$detail['origin_code']: $detail['buy_code'],
                'bar_code' => $detail['bar_code'],
                'warehouse_code' => $order['warehouse_code'],
                'inv_type'=>$inv_type,
                'tenant_id' => ADMIN_INFO['tenant_id'],
            ], [
                'audit_at' => $order['audit_at'],
                'sup_id' => $order['sup_id'],
                'sup_name' => $order['supplier']['name'] ?? '',
                'warehouse_name' => $order['warehouse']['warehouse_name'] ?? '',
                'sku' => $sku['sku'] ?? '',
                'product_sn' => $product['product_sn'] ?? '',
                'name' => $product['name'] ?? '',
                'spec_one' => $sku['spec_one'] ?? '',
                'num' => $detail['num'],
                'recv_num' => $detail['recv_num'],
                'recv_rate' => bcmul(bcdiv($detail['recv_num'], $detail['num'], 6), 100, 2),
                'normal_num' => $detail['normal_count'],
                'flaw_num' => $detail['flaw_count'],
                'flaw_rate' => bcmul(bcdiv($detail['flaw_count'], $detail['recv_num'], 6), 100, 2),
                'purchase_price' =>  $detail['buy_price'],
                'purchase_amount' => $detail['amount'],
                'admin_user_id' => ADMIN_INFO['user_id'],
            ]);
        }
    }

    function summarySearch($params, $export = false)
    {
        // $size = $params['size'] ?? 10;
        // $cur_page = $params['cur_page'] ?? 1;
        // $select = ['*'];
        $model = new WmsPurchaseStatement();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            if ($params['sup_id'] ?? '') $model = $model->where('sup_id', $params['sup_id']);
            if ($params['sku'] ?? '') $model = $model->where('sku', $params['sku']);
            if ($params['name'] ?? '') $model = $model->where('name', 'like', '%' . $params['name'] . '%');
            if ($params['warehouse_code'] ?? '') $model = $model->where('warehouse_code', $params['warehouse_code']);
            if ($params['audit_date_start'] ?? '') $model = $model->where('audit_at', '>=', $params['audit_date_start']);
            if ($params['audit_date_end'] ?? '') $model = $model->where('audit_at', '<', $params['audit_date_end']);
            if ($params['code'] ?? '') $model = $model->where('buy_code', $params['code']);
            if ($params['inv_type'] ?? '') $model = $model->where('inv_type', $params['inv_type']);
            return $model->orderBy('created_at', 'desc');
        });
        $model = $this->commonWhere($params, $model);

        // if ($params['sup_id'] ?? '') $model->where('sup_id', $params['sup_id']);
        // if ($params['sku'] ?? '') $model->where('sku', $params['sku']);
        // if ($params['name'] ?? '') $model->whereLike('name', '%' . $params['sku'] . '%');
        // if ($params['warehouse_code'] ?? '') $model->where('warehouse_code', $params['warehouse_code']);
        // if ($params['audit_date_start'] ?? '') $model->where('audit_at', '>=', $params['audit_date_start']);
        // if ($params['audit_date_end'] ?? '') $model->where('audit_at', '<', $params['audit_date_end']);
        // if ($params['code'] ?? '') $model->where('buy_code', $params['code']);

        // $list = $model->orderBy('id', 'desc')->paginate($size, $select, 'page', $cur_page);
        // $list = json_decode(json_encode($list), true);
        foreach ($list['data'] as &$item) {
            $item['wait_num'] = $item['num'] - $item['recv_num'];
        }

        // if (!$export) {
        //     $list['column'] = [];
        // }
        return $list;
    }
}
