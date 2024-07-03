<?php

namespace App\Logics;

use App\Handlers\DwApi;
use App\Models\AdminUser;
use App\Models\BondedStock;
use App\Models\BondedStockNumber;
use App\Models\ProductStock;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Psy\Util\Json;

class FreeTaxLogic extends BaseLogic
{
    //根据spu_id、sku_id、园区code获取出价所需信息
    static function bidReqInfo($spu_id, $sku_id = 0, $campus_code = '')
    {
        $params = [
            'bidding_type' => 23,
            'spu_id' => $spu_id,
        ];
        if ($sku_id) $params['sku_ids'] = [$sku_id];
        if ($campus_code) $params['campus_code'] = $campus_code;

        $method = '3,3,apiUrl';
        $data = (new DwApi($params))->uniformRequest($method, $params);
        $data = $data ? json_decode($data, true) : [];
        if ($data && $data['code'] == 200) {
            return $data['data'];
        }
        return [];
    }

    //根据货号获取园区信息
    function getCampusCode($spu_id, $sku_id)
    {
        //沙盒环境接口没有数据，默认返回值
        if (in_array(env('ENV_NAME'), ['dev', 'test'])) return 'SH01';

        $this->success = false;
        $this->err_msg = '未获取到商品仓库信息';

        $data = self::bidReqInfo($spu_id, $sku_id);
        if (!($data['list'])) {
            return '';
        }

        foreach ($data['list'] as $item) {
            if ($item['sku_id'] != $sku_id) continue;
            $this->success = true;
            return $item['campus_list'][0]['warehouse_inventory_no'] ?? '';
        }
        return '';
    }

    /**
     * 新增出价时仓库信息
     * @param $spu_id
     * @param $sku_id
     * @param $qty
     * @return array|string
     *      wh_inv_no 在仓库存编号
     *      qty 本次出价数量
     */
    function getInventoryListOffer($spu_id, $sku_id, $qty)
    {
        //沙盒环境接口没有数据，默认返回值
        if (in_array(env('ENV_NAME'), ['dev', 'test']))
            return Json::encode([
                'wh_inv_no' => '',
                'qty' => $qty,
            ]);

        $this->success = false;
        $this->err_msg = '未获取到商品仓库信息';

        //获取园区信息
        $data = self::bidReqInfo($spu_id, $sku_id);
        if (!($data['list'])) {
            return [];
        }
        $old = $qty;
        $res = [];
        //检查每个园区的可出价数量
        foreach ($data['list'] as $item) {
            if ($item['sku_id'] != $sku_id) continue;
            foreach ($item['campus_list'] as $campus) {
                if ($campus['qty'] && $qty) {
                    $res[] = [
                        'wh_inv_no' => $campus['warehouse_inventory_no'],
                        'qty' => min($campus['qty'], $qty)
                    ];
                    $qty = max(0, $qty - $campus['qty']);
                }
            }
        }
        if ($qty) {
            $this->success = false;
            $this->err_msg = '库存不足，剩余可出价库存：' . ($old - $qty);
            return [];
        }
        $this->success = true;
        return $res;
    }

    /**
     * 修改出价园区库存信息
     * @param $spu_id
     * @param $sku_id
     * @param $qty int 总出价数量
     * @param $old_inventory string 上次仓库出价信息
     * @return array|string
     *          wh_inv_no 在仓库存编号
     *          qty 本次出价数量
     *          old_qty 上次出价数量
     */
    function getInventoryListRe($spu_id, $sku_id, $qty, $old_inventory)
    {
        //沙盒环境接口没有数据，默认返回值
        if (in_array(env('ENV_NAME'), ['dev', 'test']))
            return Json::encode([
                'wh_inv_no' => 'SH01',
                'qty' => $qty,
                'old_qty' => $qty,
            ]);

        $old_inventory = json_decode($old_inventory, true);

        $this->success = false;
        $this->err_msg = '未获取到商品仓库信息';

        $sum = 0;
        $res = [];
        foreach ($old_inventory as $inv) {
            $sum += $inv['qty'];
            $res[] = [
                'wh_inv_no' => $inv['wh_inv_no'],
                'qty' => $inv['qty'],
                'old_qty' => $inv['qty']
            ];
        }

        //新出价数量和之前出价数量一致
        //不用修改
        if ($sum == $qty) {
            $this->success = true;
            return $res;
        }

        //新出价数量比已出价库存数量少，减对应仓库的库存
        //找出已出价库存，修改对应的数量，倒着来，优先取消靠后仓库出价
        if ($qty < $sum) {
            $res = [];
            $sub_qty = $sum - $qty;
            $len = count($old_inventory);
            for ($i = $len - 1; $i >= 0; $i--) {
                $inv = $old_inventory[$i];
                if ($inv['qty'] && $sub_qty) {
                    $inv['old_qty'] = $inv['qty'];
                    $inv['qty'] = max($inv['qty'] - $sub_qty, 0);
                    $res[] = $inv;
                    $sub_qty = max($sub_qty - $inv['old_qty'], 0);
                } else {
                    $inv['old_qty'] = $inv['qty'];
                    $res[] = $inv;
                }
            }
            $this->success = true;
            return $res;
        }

        //获取园区信息
        $data = self::bidReqInfo($spu_id, $sku_id);
        if (!($data['list'] ?? '')) {
            return [];
        }

        $res = [];

        $keys = array_column($old_inventory, 'wh_inv_no');
        $old_inventory = array_combine($keys, $old_inventory);
        //新出价数量比已出价数量多，加对应仓库的库存
        //找出有可出价库存的仓库
        if ($qty > $sum) {
            $add_qty = $qty - $sum;
            $left_qty = 0;
            foreach ($data['list'] as $item) {
                if ($item['sku_id'] != $sku_id) continue;
                foreach ($item['campus_list'] as $campus) {
                    if ($campus['qty'] > 0 && $add_qty) {
                        $wh_inv_no = $campus['warehouse_inventory_no'];
                        $old = $old_inventory[$wh_inv_no]['qty'] ?? 0;
                        $res[$wh_inv_no] = [
                            'wh_inv_no' => $wh_inv_no,
                            'qty' => $old + min($add_qty, $campus['qty']),
                            'old_qty' => $old
                        ];
                        $add_qty = max(0, $add_qty - $campus['qty']);
                        $left_qty += $campus['qty'];
                    }
                }
            }

            if ($add_qty) {
                $this->err_msg = '库存不足，剩余可新增库存：' . $left_qty;
                return [];
            }
            foreach ($old_inventory as $no => $inv) {
                if (!($res[$no] ?? '')) {
                    $inv['old_qty'] = $inv['qty'];
                    $res[$no] = $inv;
                }
            }
            $this->success = true;
            return array_values($res);
        }
        return [];
    }

    //最低价 - 闪电直发
    static function lowestPriceBlot($sku_id, $use_lock = false): array
    {
        $redis_key = RedisKey::PRODUCT_LOWEST_LIMIT_BOLT;
        $price = 0;
        $price_free_tax = 0;

        $params = [
            'sku_id' => $sku_id,
        ];

        if ($use_lock && Redis::get($redis_key) > time()) goto RET;

        $method = '3,7,apiUrl';
        $data = (new DwApi($params))->uniformRequest($method, $params);
        $data = $data ? json_decode($data, true) : [];
        $code = $data['code'] ?? '';
        if ($data && $code == 200) {
            $price = $data['data']['items'][0]['lowest_price'] ?? 0;
            $price_free_tax = self::priceFreeTax($price);
        }
        if ($code == 5030) {
            Robot::sendNotice('DW调用频次超限（闪电直发最低价）');
            Redis::set($redis_key, time() + 600);
        }
        RET:
        return compact('price', 'price_free_tax');
    }

    //最低价 - 跨境
    static function lowestPriceCrossBorder($sku_id, $use_lock = false): array
    {
        $redis_key = RedisKey::PRODUCT_LOWEST_LIMIT_CROSS_BORDER;
        $price = 0;
        $price_free_tax = 0;
        if ($use_lock && Redis::get($redis_key) > time()) goto RET;

        $method = '3,37,apiUrl';
        $requestArr['sku_id'] = $sku_id;
        $data = (new DwApi($requestArr))->uniformRequest($method, $requestArr);
        $data = $data ? json_decode($data, true) : [];
        $code = $data['code'] ?? '';

        if ($data && $code == 200) {
            $price = $data['data']['items'][0]['lowest_price'] ?? 0;
            $price_free_tax = $data['data']['items'][0]['lowest_price_without_tax'] ?? 0;
            if($price && !$price_free_tax){
                $price_free_tax = self::priceFreeTax($price);
            }
        }
        if ($code == 5030) {
            Robot::sendNotice('DW调用频次超限（跨境最低价）');
            Redis::set($redis_key, time() + 600);
        }

        RET:
        return compact('price', 'price_free_tax');
    }

    //获取去税价格，单位分
    static function priceFreeTax($price): int
    {
        //价格去税
        $price = floor($price / 1.091);

        $price1 = intval($price / 1000) * 1000;
        //300以上，价格以9结尾
        if ($price1 > 30000) {
            $price2 = $price % 1000;
            $price2 = ($price2 > 900) ? 1900 : 900;
            return bcadd($price1, $price2);
        }
        return ceil($price / 100) * 100;
    }

    static function specialUser()
    {
        $usernames = explode(',', env('SPECIAL_USERNAME', ''));
        if (!$usernames) return null;
        return AdminUser::whereIn('username', $usernames)->get();
    }

    //预约单初始化
    function reservationInit($product_sn = [])
    {
        print_r('开始创建预约单');
        $user = self::specialUser()[0];
        $user_id = $user->id;
        $admin_name = $user->username;

        $appoint_no = date('YmdHis') . rand(10000, 99999);
        $appoint_qty = 0;

        $where = [];
        if ($product_sn) {
            $where['goodsCode'] = $product_sn;
        }
        $model = ProductStock::select([DB::raw('max(id) AS id'), 'goodsCode', 'skuProperty', 'itemName', 'barCode']);
        if ($where) {
            $model->where($where);
        }
        $list = $model->groupBy(['goodsCode', 'skuProperty'])->get()->toArray();

        $good_num = 0;
        //在库数量大于0 、可预约数量
        foreach ($list as $v) {
            if ($v['goodsCode'] == '-') continue;
            $qty = 1;
            $BondedStockNumber_data[] = [
                'pms_product_stock_id' => $v['id'],
                'admin_id' => $user_id,
                'admin_name' => $admin_name,
                'appoint_no' => $appoint_no,
                'product_name' => $v['itemName'], //商品名称
                'article_number' => $v['goodsCode'], //货号
                'bar_code' => $v['barCode'], //条形码
                'qty' => $qty, //预约数
                'props' => $v['goodsCode'], //规格
                'sku_id' => time(), //规格
                'spu_id' => time(), //规格
            ];

            $appoint_qty += $qty;
            $good_num++;
        }
        $BondedData = [
            'admin_id' => $user_id,
            'admin_name' => $admin_name,
            'appoint_no' => $appoint_no,
            'appoint_qty' => $appoint_qty, //预约数
            'status_type' => 1
        ];

        DB::beginTransaction();
        try {
            $Bonded_id = (new BondedStock())->BaseCreate($BondedData);
            $add_arr = ['pms_bonded_stock_id' => $Bonded_id];
            //给子单压入聚合单id
            array_walk($BondedStockNumber_data, function (&$value, $key, $add_arr) {
                $value = array_merge($value, $add_arr);
            }, $add_arr);

            $offset = 0;
            $limit = 100;
            while (1) {
                $arr = array_slice($BondedStockNumber_data, $offset, $limit);
                (new BondedStockNumber)->insert($arr);
                $offset += $limit;
                if (!$arr) break;
            }

            DB::commit();
            print_r("预约单创建成功，预约单号：" . $appoint_no . " 预约数量：" . $good_num);
            return compact('appoint_no', 'good_num');
        } catch (\Exception $th) {
            DB::rollback();
            $this->success = false;
            $this->err_msg = $th->getMessage();
            print_r("预约单创建失败，原因：" . $th->getMessage());
            return false;
        }
    }

    //解决相同sku_id 不同仓库库存展示的问题
    static function fixShipment($invoice_no)
    {

        $where = [
            'invoice_no' => $invoice_no
        ];
        $old = Shipment::where($where)->count();
        $old_qty = Shipment::where($where)->sum('identification_passed_qty');

        //两个问题 1-相同sku，库存相同，出价时相同的sku被扣减了两次 2-取消订单后，取消的库存没有被加到剩余库存里面
        $list = Shipment::where($where)->select([
            DB::raw('GROUP_CONCAT(id) AS ids'),
            DB::raw('COUNT(id) AS num'),
            DB::raw('GROUP_CONCAT(identification_passed_qty) AS qq'),
            DB::raw('SUM(identification_passed_qty) AS identification_passed_qty'),
            'sku_id'
        ])->groupBy('sku_id')->having('num', '>', 1)->get()->toArray();

        $del_num = 0;
        $update_num = 0;
        foreach ($list as $item) {
            $update_num += 1;
            $ids = explode(',', $item['ids']);
            $id = array_shift($ids);
            Shipment::where('id', $id)->update(['identification_passed_qty' => $item['identification_passed_qty']]);
            Shipment::where(['id' => $ids])->delete();
            $del_num += count($ids);
        }

        $new = Shipment::where($where)->count();
        $new_qty = Shipment::where($where)->sum('identification_passed_qty');
        print_r([
            '更新前总条数：' . $old . ' 库存总量：' . $old_qty,
            "更新条数：" . $update_num,
            '删除条数:' . $del_num,
            '更新后总条数：' . $new . ' 库存总量：' . $new_qty,
        ]);
    }

    //出价信息
    static function fixShipmentQty($invoice_no)
    {
        //指定sku的有效出价，包括有效出价，已取消的但是已售出的出价
        //更新更新对应sku的出价库存

        $list = DB::table('pms_bidding')->select(
            DB::raw('sum(if(status=1,qty-qty_sold,0)) as qty'),
            DB::raw('sum(qty_sold) as qty_sold'),
            'sku_id'
        )->groupBy('sku_id')->get()->toArray();

        foreach ($list as $item) {
            //有效出价数量
            $shipment = Shipment::where(['sku_id' => $item->sku_id, 'invoice_no' => $invoice_no])->first();
            if (!$shipment) {
                continue;
            }
            $old = $shipment->identification_passed_qty2;
            $num = $item->qty + $item->qty_sold;
            $shipment->identification_passed_qty2 = max($shipment->identification_passed_qty - $num, 0);
            $shipment->save();
            print_r([
                'sku_id' => $item->sku_id,
                '更新前库存' => $old,
                '有效出价数量' => $num,
                '更新后剩余库存' => $shipment->identification_passed_qty2
            ]);
        }


        Shipment::where(['product_sn' => 'DM6213-045', 'properties' => '44.5'])->update(['identification_passed_qty' => 33]);
        Shipment::where(['product_sn' => 'DQ1823-006', 'properties' => '39'])->update(['identification_passed_qty' => 19, 'identification_passed_qty2' => 0]);
    }

    //根据出价编号获取出价详情
    static function biddingInfo($bidding_no)
    {
        $method = '3,2,apiUrl';
        $requestArr['bidding_no'] = $bidding_no;
        $data =  (new DwApi($requestArr))->uniformRequest($method, $requestArr);
        $data = $data ? json_decode($data, true) : [];
        if (!$data || ($data['code'] ?? '') != 200) {
            return [];
        }
        return $data['data'];
    }

    /**
     * 获取sku最低价
     *
     * @param int $sku_id
     */
    static function getLowestPrice($sku_id, $use_lock = false)
    {
        //海外直邮（境外）
        $price_cross_border = FreeTaxLogic::lowestPriceCrossBorder($sku_id, $use_lock);
        //闪电直发（国内）
        $price_blot = FreeTaxLogic::lowestPriceBlot($sku_id, $use_lock);

        //国内最低价
        $reData['price_blot'] = $price_blot['price'] ?? 0;
        $reData['lower_price1'] = $price_blot['price_free_tax'] ?? 0;
        //跨境最低价
        $reData['price_cross_border'] = $price_cross_border['price'] ?? 0;
        $reData['lower_price2'] = $price_cross_border['price_free_tax'] ?? 0;
        //参考最低价
        if (in_array(0, [$reData['lower_price1'], $reData['lower_price2']])) {
            $reData['lower_price3'] = max($reData['lower_price1'], $reData['lower_price2']);
        } else {
            $reData['lower_price3'] = min($reData['lower_price1'], $reData['lower_price2']);
        }
        return $reData;
    }

    static function lowestPriceDefaultData($sku_id)
    {
        return [
            'price_blot' => 0,
            'lower_price1' => 0,
            'price_cross_border' => 0,
            'lower_price2' => 0,
            'lower_price3' => 0,
        ];
    }

    static function getExceptIncomeRedisKey($sku_id, $price, $bidding_type = 6)
    {
        return sprintf('dw:except_income:%d-%d-%d', $sku_id, $price, $bidding_type);
    }

    /**
     * 获取sku的预期收益
     *
     * @param int $sku_id
     * @param int $price
     * @param integer $bidding_type
     */
    static function getExceptIncome($sku_id, $price, $bidding_type = 6)
    {
        $key = self::getExceptIncomeRedisKey($sku_id, $price);
        $data = Redis::get($key);
        if ($data) {
            return json_decode($data, true);
        }

        return [
            'bidding_price' => 0,
            'expect_income' => 0,
            'operate_fee' => 0,
            'tech_service_fee' => 0,
            'transfer_fee' => 0,
            'free_postage_service_fee' => 0,
        ];
    }

    /**
     * 更新sku预期收益
     *
     * @param int $sku_id
     * @param int $price
     * @param integer $bidding_type
     */
    static function setExceptIncome($sku_id, $price, $bidding_type = 6)
    {
        $method = '3,6,apiUrl';
        $requestArr['sku_id'] = $sku_id;
        $requestArr['bidding_price'] = $price;
        $requestArr['bidding_type'] = $bidding_type;

        $key = self::getExceptIncomeRedisKey($sku_id, $price);

        $data =  (new DwApi($requestArr))->uniformRequest($method, $requestArr);
        $arr  = json_decode($data, true);
        if (!empty($arr['code'])  && $arr['code'] == 200) {
            Redis::setex($key, 14400, Json::encode($arr['data']));
            return $arr['data'];
        } else {
            return [];
        }
    }
}
