<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use App\Models\Admin\V2\Supplier;
use App\Models\Admin\V2\Warehouse;
use App\Models\Admin\V2\ProductSpecAndBar;
use App\Models\Admin\V2\PurchaseOrders;
use App\Models\Admin\V2\WmsLogisticsProduct;
use App\Models\AdminUsers as AdminModel;



class PurchaseOrdersImport implements ToArray
{
    /**
     * 使用 ToCollection
     * @param array $row
     *
     * @return User|null
     * 
     */

    public $code = 200;
    public $error = '';

    private $headers = [
        0 => 'code', //"单据编码"
        1 => 'pay_status', //"付款状态",
        2 => 'sup_id', //"供应商"
        3 => 'sup_card', //"供应商身份证"
        4 => 'warehouse_code', //"仓库"
        5 => 'estimate_receive_at', //"预计到货日期"
        6 => 'order_user', //"下单人"
        7 => 'order_at', //"下单时间"
        8 => 'remark', //"备注"
        9 => 'log_prod_code', //"物流"
        10 => 'deliver_no', //"物流单号"
        11 => 'send_at', //"发货日期"
        12 => 'third_code', // "第三方单据编码"
        13 => 'product_sn', // "货号"
        14 => 'spec', // "规格"
        15 => 'num', //"数量"
        16 => 'buy_price', //"采购价(¥)"
        17 => 'remark', //"备注"
    ];
    public function array(array $rows)
    {
        $data = $rows;
        //解析excel中的数据
        //    $data=json_decode(json_encode($rows),true);
        //查询当前登陆账号的id
        $user = request()->header('user_id');
        //去掉标题
        $h_count = 3;
        array_splice($data, 0, $h_count);

        $orders = [];
        $order = null;
        //
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
            if (!is_numeric($row[16])) {
                list($this->code, $this->error) = [500,  $line . '行采购价不存在'];
                return;
            };
            list($order_raw, $details_raw) = array_chunk($row, 13, true);
            $is_null = array_filter($order_raw, function ($v) {
                return $v != null;
            });
            if (!empty($is_null)) {
                //新增采购单
                if (!empty($order)) $orders[] = $order;
                $order = [];
                if (empty($row[2])) {
                    list($this->code, $this->error) = [500, '供应商 ' . $row[2] . ' 不存在'];
                    return;
                };
                if (empty($row[4])) {
                    list($this->code, $this->error) = [500, '仓库 ' . $row[4] . ' 不存在'];
                    return;
                };
                // 判断供应商
                if ($row[3] == null) $row[3] = '';
                $arr_code = $row[12] ?? '';
                $supplier = Supplier::getIDByNameCard($row[2], $row[3], $arr_code);
                if ($supplier == 0) {
                    list($this->code, $this->error) = [500, '供应商 ' . $row[2] . ' 不存在'];
                    return;
                };
                // 判断仓库
                $warehouse_code = Warehouse::getCode($row[4]);
                if (empty($warehouse_code)) {
                    list($this->code, $this->error) = [500, '仓库 ' . $row[4] . ' 不存在'];
                    return;
                };


                //下单人
                if (!empty($row[6])) {
                    $row[6] = AdminModel::getAdminUserID($row[6]);
                }
                //物流
                // if(!empty($row[8])){
                // }
                $order[$this->headers[0]] = $row[0] ?? '';
                $order[$this->headers[1]] = $row[1] == "已付款" ? 1 : 0;
                $order[$this->headers[2]] = $supplier;
                // $order[$this->headers[3]] = $row[3];
                $order[$this->headers[4]] = $warehouse_code;
                $order[$this->headers[5]] = $row[5] ?? null;
                $order[$this->headers[6]] = $row[6] ?? ADMIN_INFO['user_id'];
                $order[$this->headers[7]] = $row[7] ?? date('Y-m-d H:i:s');
                $order[$this->headers[8]] = $row[8] ?? '';
                $order[$this->headers[9]] = $row[9] ?? '';
                $order[$this->headers[10]] = $row[10] ?? '';
                $order[$this->headers[11]] = $row[11] ?? null;
                $order[$this->headers[12]] = $row[12] ?? '';
            }
            //产品明细
            //货号规格转条码
            $sku =  $row[13] . '#' . $row[14];
            $arr_code = $order['third_code'] ?? '';
            $bar_code = ProductSpecAndBar::getBarCode($sku, $arr_code);
            if (empty($bar_code)) {
                list($this->code, $this->error) = [500, $line . '行商品未录入系统'];
                return;
            };
            $detail['bar_code'] = $bar_code;
            $detail[$this->headers[15]] = $row[15] ?? 0;
            $detail[$this->headers[16]] = $row[16] ?? 0;
            $detail[$this->headers[17]] = $row[17] ?? "";
            $detail['sku'] = $sku;
            $order['skus'][] = $detail;
        }
        //最后一次的数据
        $orders[] = $order;
        $this->createData($orders);
    }

    public function createData($rows)
    {
        $model = new PurchaseOrders();
        foreach ($rows as $row) {
            $row = $model->add($row, 2);
            if (!$row) break;
        }
        if (!$row) {
            list($this->code, $this->error) = [500, '导入失败,请重试!'];
            return;
        };
        return true;
    }
}
