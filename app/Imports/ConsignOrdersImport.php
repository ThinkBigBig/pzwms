<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use App\Models\Admin\V2\Supplier;
use App\Models\Admin\V2\Warehouse;
use App\Models\Admin\V2\ProductSpecAndBar;
use App\Models\Admin\V2\PurchaseOrders;
use App\Models\Admin\V2\WmsLogisticsProduct;
use App\Models\AdminUsers as AdminModel;
use Consignment;

class ConsignOrdersImport  extends Import implements ToArray
{
    /**
     * 使用 ToCollection
     * @param array $row
     *
     * @return User|null
     *
     */

    public function model(array $row)
    {
    }
    public function array(array $rows)
    {
        $detail_header = [];
        foreach ($this->config['detail']['field'] as $k => $v) {
            $detail_header['detail.' . $k] = $v;
        }
        $headers = array_merge($this->config['field'],  $detail_header);
        $data = $rows;

        //解析excel中的数据
        //    $data=json_decode(json_encode($rows),true);
        //查询当前登陆账号的id
        $user = request()->header('user_id');
        $h_count = $this->count;
        // array_splice($data, 0, $h_count);
        $orders = [];
        $order = null;
        foreach ($data as $key => $row) {
            $all_empty = 1;
            $uniq_row = array_unique($row);
            foreach($uniq_row as $e){
                if(!empty( trim($e))){
                    $all_empty = 0;
                    break;
                }
            }
            if($all_empty == 1 )continue;
            // if (array_unique($row) == [null]) continue;
            $row = array_map(function ($v) {
                return trim($v);
            }, $row);
            $line = $key + $h_count + 1;
            if (!is_numeric($row[$headers['detail.buy_price']])) {
                $this->code = 500;
                $this->error = $line . '行' . $headers['detail.buy_price'] . '不存在';
                return;
            }
            if (empty($row[$headers['detail.product_sn']])) {
                $this->code = 500;
                $this->error = $line . '行' . $headers['detail.product_sn'] . '不存在';
                return;
            }
            if (empty($row[$headers['detail.spec_one']])){
                $this->code = 500;
                $this->error =  $line . '行' . $headers['detail.spec_one'] . '不存在';
                return;
            }
            if (empty($row[$headers['detail.num']])) {
                $this->code = 500;
                $this->error =  $line . '行' . $headers['detail.num'] . '不存在';
                return;
            }
            // if (empty($row[$headers['warehouse_code']])) {
            //     $this->code = 500;
            //     $this->error =  $line . '行' . $headers['warehouse_code'] . '不存在';
            //     return;
            // }
            // if (empty($row[$headers['sup_id']])) {
            //     $this->code = 500;
            //     $this->error =  $line . '行' . $headers['sup_id'] . '不存在';
            //     return;
            // }
            list($order_raw, $details_raw) = array_chunk($row, count($this->header), true);
            $is_null = array_filter($order_raw, function ($v) {
                return $v != null;
            });
            if (!empty($is_null)) {
                //新增采购单
                if (!empty($order)) $orders[] = $order;
                $order = [];
                // 判断供应商
                $arr_code = $row[$headers['third_code']]??'';
                $supplier = Supplier::getIDByNameCard($row[$headers['sup_id']], $row[$headers['sup_id_card']] ?? '',$arr_code);
                if ($supplier == 0) {
                    $this->code = 500;
                    $this->error =  $line . '行' . $headers['sup_id'] . '不存在';
                    return;
                }
                else $row[$headers['sup_id']] = $supplier;
                // 判断仓库
                $warehouse_code = Warehouse::getCode($row[$headers['warehouse_code']]);
                if (empty($warehouse_code)){
                    $this->code = 500;
                    $this->error = $line . '行' . $headers['warehouse_code'] . '不存在';
                    return;
                }
                else $row[$headers['warehouse_code']] = $warehouse_code;
                //下单人
                if (!empty($row[$headers['order_user']])) {
                    $row[$headers['order_user']] = AdminModel::getAdminUserID($row[$headers['order_user']]);
                }
                //物流
                // if(!empty($row[8])){
                // }
                $order['code'] = $row[$headers['code']] ?? '';
                $order['sup_id'] = $row[$headers['sup_id']] ?? '';
                $order['warehouse_code'] = $row[$headers['warehouse_code']] ?? '';
                $order['estimate_receive_at'] = $row[$headers['estimate_receive_at']] ?? null;
                $order['order_user'] = $row[$headers['order_user']] ?? $user;
                $order['order_at'] = $row[$headers['order_at']] ?? date('Y-m-d H:i:s');
                $order['remark'] = $row[$headers['remark']] ?? '';
                $order['log_prod_code'] = $row[$headers['log_prod_code']] ?? '';
                $order['deliver_no'] = $row[$headers['deliver_no']] ?? '';
                $order['send_at'] = $row[$headers['send_at']] ?? null;
                $order['third_code'] = $row[$headers['third_code']] ?? '';
            }
            //产品明细
            //货号规格转条码
            $sku = $row[$headers['detail.product_sn']].'#'. $row[$headers['detail.spec_one']];
            $arr_code = $order['third_code']??'';
            $bar_code = ProductSpecAndBar::getBarCode($sku,$arr_code);
            if (empty($bar_code)) {
                $this->code = 500;
                $this->error = $line . '行商品未录入系统';
                return;
            }
            $detail['bar_code'] = $bar_code;
            $detail['num'] = $row[$headers['detail.num']] ?? 0;
            $detail['buy_price'] = $row[$headers['detail.buy_price']] ?? 0;
            $detail['remark'] = $row[$headers['detail.remark']] ?? '';
            $detail['sku'] = $sku;
            $order['skus'][] = $detail;
        }
        //最后一次的数据
        if ($order) $orders[] = $order;
        if ($orders) $this->createData($orders);
    }

    public function createData($rows)
    {
        foreach ($rows as $row) {
            $row = $this->db->add($row, 2);
            if (!$row) break;
        }
        if (!$row) {
            $this->code = 500;
            $this->error = '导入失败,请重试!';
            return;
        }
        return true;
    }
}
