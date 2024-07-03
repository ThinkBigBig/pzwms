<?php

namespace App\Logics\wms;

use App\Handlers\OSSUtil;
use App\Logics\BaseLogic;
use App\Logics\RedisKey;
use App\Logics\wms\Product as WmsProduct;
use App\Models\Admin\V2\AfterSaleOrder;
use App\Models\Admin\V2\ArrivalRegist;
use App\Models\Admin\V2\IbDetail;
use App\Models\Admin\V2\IbOrder;
use App\Models\Admin\V2\Inventory;
use App\Models\Admin\V2\Product;
use App\Models\Admin\V2\ProductSpecAndBar;
use App\Models\Admin\V2\RecvDetail;
use App\Models\Admin\V2\RecvOrder;
use App\Models\Admin\V2\SupInv;
use App\Models\Admin\V2\TransferDetails;
use App\Models\Admin\V2\TransferOrder;
use App\Models\Admin\V2\UniqCodePrintLog;
use App\Models\Admin\V2\WithdrawUniqLog;
use App\Models\Admin\V2\WmsFile;
use App\Models\Admin\V2\WmsOptionLog;
use App\Models\Admin\V2\WmsOrder as V2WmsOrder;
use App\Models\Admin\V2\WmsReceiveCheck;
use App\Models\Admin\V2\WmsShippingRequest;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File as FacadesFile;
use Illuminate\Support\Facades\Redis;
use WmsOrder;

use function PHPUnit\Framework\returnSelf;

/**
 * 收货
 */
class Receive extends BaseLogic
{
    //收货单类型检查
    public function  _preCheck($arr, $recv_type = null)
    {
        $item = $arr;
        if (empty($item)) return $this->setErrorMsg(__('status.arr_not_exists'));

        $arr_type = $item->arr_type;
        if ($recv_type === null) $recv_type = $item->arr_type;
        if ($arr_type != $recv_type) return $this->setErrorMsg('doc type error');

        $arr_status = $item->arr_status;
        if ($arr_status == 4) return $this->setErrorMsg('status.done_not_recv');

        $doc_status = $item->doc_status;
        if (in_array($doc_status, [2, 3])) return $this->setErrorMsg('当前到货登记单' . $arr->map['doc_status'][$doc_status] . ',不允许收货');
        return true;
    }

    // 收货中商品明细
    function arrInfo($params)
    {
        $id = $params['arr_id'];
        $scan_type = intval($params['scan_type']);
        $arr = ArrivalRegist::where(['id' => $id, 'warehouse_code' => $params['warehouse_code']])->first();
        $res = $this->_preCheck($arr);
        if (!$res) return false;

        $data['warehouse_name'] = $arr->warehouse;
        $data['warehouse_code'] = $arr->warehouse_code;
        $data['recv_type'] = $arr->arr_type;
        $data['arr_code'] = $arr->arr_code;
        $data['arr_id'] = $id;

        if (in_array($arr->arr_type, [2, 3])) $data['ib_code'] = $arr->ib_code;
        $recv = RecvOrder::where(['arr_id' => $id, 'recv_type' => $data['recv_type'], 'created_user' => $this->user_id, 'doc_status' => 1, 'recv_status' => 0, 'warehouse_code' => $data['warehouse_code']])->first();
        if ($recv) {
            $data['recv_code'] = $recv->recv_code;
            $data['recv_id'] = $recv->id;
        }

        // 已扫商品信息
        $list = DB::select("SELECT c.type as scan_type,c.uniq_code,c.bar_code,c.num,c.quality_level,b.sku,b.id as bar_id,b.type,p.img,p.`name`,pc.`name` as category_name,pc.name as category_txt,p.product_sn,p.name as product_name,p.img as product_img,c.unit as recv_unit,b.spec_one as product_spec  FROM wms_receive_check c
        left JOIN wms_spec_and_bar b ON c.bar_code=b.bar_code AND b.tenant_id=$this->tenant_id 
        LEFT JOIN wms_product p ON b.product_id=p.id AND p.tenant_id=$this->tenant_id AND p.`status`=1
        LEFT JOIN wms_product_category pc ON p.category_id=pc.id AND pc.tenant_id=$this->tenant_id
        WHERE  c.tenant_id=$this->tenant_id AND c.status=0 AND c.arr_id=$arr->id AND c.admin_user_id=$this->user_id and c.type=$scan_type");
        $new = 0;

        foreach ($list as &$item) {
            if (!$item->bar_id) $new++;
            $item->recv_unit_txt = WmsReceiveCheck::maps('unit')[$item->recv_unit] ?? '';
            $item->product_img = Product::imgAddHost($item->product_img);
        }

        // 已扫商品信息
        $data['recv_detail_list'] = $list;
        // 新品数量
        $data['new_product_count'] = $new;
        // 已扫数量
        $data['scan_count'] = count($list);

        // 待扫描的唯一码
        $data['uniq_codes'] = $this->_waitReceiveUniqCodes($arr, $scan_type);
        return $data;
    }

    private function _waitReceiveUniqCodes($arr, $scan_type = 0)
    {
        if ($scan_type > 0) return [];
        if ($arr->arr_type == 1) {
            $list = DB::select("SELECT uniq_code,bar_code FROM wms_unicode_print_log WHERE arr_code='$arr->arr_code' AND bar_code='' AND tenant_id=$this->tenant_id");
        } else {
            $list = DB::select("SELECT uniq_code,bar_code FROM wms_withdraw_uniq_log WHERE source_code='$arr->third_doc_code' AND is_scan=0 AND tenant_id=$this->tenant_id");
        }
        return objectToArray($list);
    }

    private function _allReceiveUniqCodes($arr)
    {
        if ($arr->arr_type == 1) {
            $list = DB::select("SELECT uniq_code FROM wms_unicode_print_log WHERE arr_code='$arr->arr_code' AND tenant_id=$this->tenant_id");
        } else {
            $list = DB::select("SELECT uniq_code FROM wms_withdraw_uniq_log WHERE source_code='$arr->third_doc_code' AND tenant_id=$this->tenant_id");
        }
        return array_column(objectToArray($list), 'uniq_code');
    }


    // 扫描条形码信息
    function scanBarCode($params)
    {
        $type = $params['scan_type'] ?? 0; //0-唯一码商品 1-普通商品
        $bar_code = $params['bar_code'];
        $sku = ProductSpecAndBar::where(['bar_code' => $bar_code])->first();
        //判断商品条码是否是普通产品
        if ($sku) {
            if ($type == 0 && $sku->type == 2) return $this->setErrorMsg(__('status.bar_is_ordinary'));
            if ($type == 1 && $sku->type == 1) return $this->setErrorMsg(__('status.bar_is_uniq'));
        }

        $is_new = false;
        if (!$sku || $sku->product->status != 1) $is_new = true;

        $product = $sku ? $sku->product : null;
        $category = $product ? $product->category : null;
        return ['is_new' => $is_new, 'info' => [
            'sku' => $sku ? $sku->sku : '',
            'bar_code' => $sku ? $sku->bar_code : '',
            'type' => $sku ? $sku->type : '',
            'img' => $product ? $product->img : '',
            'name' => $product ? $product->name : '',
            'quality_type' => 1,
            'quality_level' => 'A',
            'category_name' => $category ? $category->name : '',
        ]];
    }

    // 逐件收货扫描
    function scan($data)
    {
        $arr_id = $data['arr_id'];
        $bar_code =  $data['bar_code'];
        $uniq_code =  $data['uniq_code'] ?? '';
        $quality_level =  $data['quality_level'] ?? 'A';
        $scan_type = $data['scan_type'] ?? 0; //0-唯一码商品 1-普通商品
        $recv_methods = $data['recv_methods'] ?? 1;
        $ib_code = $data['ib_code'] ?? '';
        $add_num = $data['num'] ?? 1;

        $arr_model = new ArrivalRegist();
        $arr_item = $arr_model->find($arr_id);
        if (empty($arr_item)) return $this->setErrorMsg(__('status.arr_not_exists'));

        //检查状态
        $res = $this->_preCheck($arr_item);
        if (!$res) return false;

        // 检查唯一码和批次号
        $return = false;
        if (in_array($arr_item->arr_type, [2, 3])) $return = true;
        if ($return) {
            $ib_item = IbOrder::where('ib_code', $ib_code)->first();
            //退调货
            if ($arr_item->uni_num_count == 0) {
                $where2 = ['source_code' => $ib_item->third_no, 'is_scan' => 0, 'bar_code' => $bar_code];
                if ($uniq_code) $where2['uniq_code'] = $uniq_code;
                $pro_item =  WithdrawUniqLog::where($where2)->first();
                if (!$pro_item) return $this->setErrorMsg(__('response.unicode_not_match'));
            }
        } elseif ($uniq_code) {
            $uniq_batch_no = explode('-', $uniq_code)[0] ?? '';
            $uniq_num = explode('-', $uniq_code)[1] ?? '';
            if ($uniq_batch_no != $arr_item->lot_num) return $this->setErrorMsg(__('response.batch_code_err'));
            if ($uniq_num > $arr_item->uni_num_count) return $this->setErrorMsg(__('response.batch_code_err'));
        }

        $where = ['arr_id' => $arr_id, 'tenant_id' => ADMIN_INFO['tenant_id'], 'bar_code' => $bar_code];
        // 唯一码商品
        if ($scan_type == 0) {
            //兼容v1的收货逻辑，通过v1扫码收货的不再处理
            if (Redis::get($uniq_code)) return [false, __('response.uniq_scan_repeat')];
            Redis::setex($uniq_code, 10, 1);

            $where['uniq_code'] = $uniq_code;
            $where['type'] = 0;
            $find = WmsReceiveCheck::where(['arr_id' => $arr_id, 'tenant_id' => ADMIN_INFO['tenant_id'], 'uniq_code' => $uniq_code, 'type' => 0])->first();
            if ($find) return $this->setErrorMsg(__('response.uniq_scan_repeat'));
            WmsReceiveCheck::create(array_merge($where, ['admin_user_id' => ADMIN_INFO['user_id']]));
            return true;
        }

        // 普通商品
        $where['uniq_code'] = $bar_code;
        $where['quality_level'] = $quality_level;
        $where['type'] = 1;
        $where['admin_user_id'] = ADMIN_INFO['user_id'];
        $where['unit'] = $data['recv_unit'];
        $find = WmsReceiveCheck::where($where)->first();
        $num = $find ? $find->num + $add_num : $add_num;
        if ($find) {
            $find->update(['num' => $num]);
            return true;
        }
        WmsReceiveCheck::create(array_merge($where, ['num' => $num]));
        return true;
    }

    // 逐件收货减扫
    function subScan($data)
    {
        $arr_id = $data['arr_id'];
        $bar_code =  $data['bar_code'];
        $quality_level =  $data['quality_level'] ?? '';
        $scan_type = $data['scan_type'] ?? 0; //0-唯一码商品 1-普通商品
        $where = ['arr_id' => $arr_id, 'tenant_id' => ADMIN_INFO['tenant_id'], 'admin_user_id' => ADMIN_INFO['user_id']];

        // 唯一码商品
        if ($scan_type == 0) {
            $where['type'] = 0;
            WmsReceiveCheck::where($where)->whereIn('uniq_code', $data['uniq_codes'])->delete();
            return true;
        }

        // 普通商品
        $where['uniq_code'] = $bar_code;
        if ($quality_level) $where['quality_level'] = $quality_level;
        $where['type'] = 1;
        $where['admin_user_id'] = ADMIN_INFO['user_id'];
        $where['unit'] = $data['recv_unit'];
        $find = WmsReceiveCheck::where($where)->first();
        if ($find && $find->num >= $data['num']) {
            $num = $find->num - $data['num'];
            if ($num) {
                $find->update(['num' => $num]);
                return true;
            }

            WmsReceiveCheck::where($where)->delete();
            return true;
        }
    }

    // 唯一码确认收货
    function uniqConfirm($data)
    {
        $arr_id = $data['arr_id'];
        $scan_type = $data['scan_type'] ?? 0; //0-唯一码商品 1-普通商品
        $recv_methods = $data['recv_methods'] ?? 1;
        $ib_code = $data['ib_code'] ?? '';
        $products = $data['products'];

        $arr_model = new ArrivalRegist();
        $arr_item = $arr_model->find($arr_id);
        if (empty($arr_item)) return $this->setErrorMsg(__('status.arr_not_exists'));
        $recv_type = empty($data['recv_type']) ? $arr_item->arr_type : $data['recv_type'];

        //检查登记单状态
        $res = $this->_preCheck($arr_item);
        if (!$res) return false;

        // 收货单id存在，检查收货单状态
        $recv_id =  empty($data['recv_id']) ? 0 : $data['recv_id'];
        if ($recv_id) {
            $recv_item = RecvOrder::where('created_user', ADMIN_INFO['user_id'])->find($recv_id);
            if ($recv_item->doc_status == 2) return $this->setErrorMsg(__('response.recv_done'));
            if ($recv_item->doc_status != 1) return $this->setErrorMsg(__('response.doc_status_err'));
            if ($recv_item->arr_id != $arr_id) return $this->setErrorMsg(__('response.params_err'));
        }

        $warehouse_code  =  $arr_item->warehouse_code;
        $lot_num  =  $arr_item->lot_num;

        $return = false;
        if (in_array($recv_type, [2, 3])) $return = true;

        // 检查是否有暂存中的收货单
        $recv_item = RecvOrder::where('arr_id', $arr_id)->where('doc_status', 1)->where('created_user', request()->header('user_id'))->where('recv_status', 0)->where('scan_type', $scan_type)->first();
        if ($recv_item && ($recv_id != $recv_item->id)) return $this->setErrorMsg('recv_id参数错误');


        //判断批次号是否正确
        $print_count = $arr_item->uni_num_count;
        // 调拨到货/其他到货类型的登记单，不能没有批次号
        if ($print_count == 0 && !$return) return $this->setErrorMsg(__('response.batch_not_match'));

        // 检查是否存在新品
        $tenant_id = ADMIN_INFO['tenant_id'];
        $bar_codes = array_unique(array_column($products, 'bar_code'));
        // $bars = DB::select("SELECT bar.* FROM wms_spec_and_bar bar ,wms_product p WHERE bar.product_id=p.id AND bar.tenant_id=$tenant_id AND p.tenant_id=$tenant_id AND p.status=1 and bar.type=1  AND bar.bar_code IN(" . implode(',', $bar_codes) . ")");
        $bars = DB::table('wms_spec_and_bar as bar')
            ->join('wms_product as p', 'bar.product_id', '=', 'p.id')
            ->where('bar.tenant_id', $tenant_id)
            ->where('p.tenant_id', $tenant_id)
            ->where('p.status', 1)
            ->whereIn('bar.type', [0, 1])
            ->whereIn('bar.bar_code', $bar_codes)->selectRaw("bar.*")->get();
        $bars = objectToArray($bars);
        if (count($bars) != count($bar_codes)) return $this->setErrorMsg(__('response.new_pro'));

        ProductSpecAndBar::where('type', 0)->whereIn('id', array_column($bars, 'id'))->update(['type' => 1]);

        $keys = array_column($products, 'uniq_code');
        $products2 = array_combine($keys, $products);

        $products = WmsReceiveCheck::where(['arr_id' => $arr_id, 'type' => 0, 'status' => 0, 'admin_user_id' => ADMIN_INFO['user_id']])->whereIn('uniq_code', array_column($products, 'uniq_code'))->get();
        // 没有处理的收货信息，直接确认收货
        if (!$products->count()) {
            (new RecvOrder())->recvDone($arr_id, $recv_id);
            return true;
        }

        // 已经被v1逻辑收货的，不再处理，防止重复收货
        $uniq_codes2 = RecvDetail::where(['arr_id' => $arr_id])->whereIn('uniq_code', $keys)->pluck('uniq_code');
        if ($uniq_codes2) $uniq_codes2 = $uniq_codes2->toArray();
        $add_uniq_codes = array_diff($keys, $uniq_codes2);
        // 没有要处理的数据，直接返回
        if (!$add_uniq_codes) return true;

        $products = $products->toArray();
        $recv = new RecvOrder();
        try {
            DB::beginTransaction();
            if (!$recv_item) {
                //新增
                $recv_code  = $recv->getErpCode('SHD', 10);
                $create_data = [
                    'arr_id' => $arr_id,
                    'recv_type' => $recv_type,
                    'recv_methods' => $recv_methods,
                    'doc_status' => 1,
                    'recv_num' => 0,
                    'created_user' => request()->header('user_id'),
                    'recv_code' => $recv_code,
                    'warehouse_code' => $warehouse_code,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
                $recv_id =  $recv->insertGetId($create_data);
                if (!$recv_id) throw new Exception('收货单创建失败');
                $recv_item = RecvOrder::find($recv_id);
                $row['log_shd'] = WmsOptionLog::add(WmsOptionLog::SHD, $recv_code, '创建', '收货单创建成功', $create_data);
                //修改登记单的到货状态
                $old = $arr_item->arr_status;
                $arr_item->arr_status = 3;
                if ($old != 3) $row['arr_status'] = $arr_item->save();
                $row['log_djd'] = WmsOptionLog::add(WmsOptionLog::DJD, $arr_item->arr_code, '开始收货', '到货登记单开始收货', $old);
            }

            $pro_items = [];
            if ($return) {
                if (!isset($ib_item)) $ib_item = IbOrder::where('ib_code', $ib_code)->first();
                //退调货订单
                $pro_items =  WithdrawUniqLog::where('source_code', $ib_item->third_no)->whereIn('bar_code', $bar_codes)->where('is_scan', 0)->get()->keyBy('uniq_code');
            }

            $ib_item = null;
            if ($data['ib_code'] ?? '') $ib_item = IbOrder::where('ib_code', $ib_code)->first();
            $num = 0;
            foreach ($products as $product) {
                // 已经添加过的，收货明细不再处理
                if (in_array($product['uniq_code'], $uniq_codes2)) continue;
                $pro_return = [];
                $pro_item = $pro_items[$product['uniq_code']] ?? null;
                if ($pro_item) {
                    // 更新退调记录
                    $pro_item->is_scan = 1;
                    $pro_item->save();

                    //退调单数据
                    $pro_return = [
                        'sup_id' => $pro_item->sup_id,
                        'buy_price' => $pro_item->buy_price,
                        'quality_type' => $pro_item->getRawOriginal('quality_type'),
                        'quality_level' => $pro_item->quality_level,
                        'inv_type' => $pro_item->inv_type,
                        'sup_confirm' => 1,
                        'ib_confirm' => 1,
                        'ib_id' => $ib_item->id,
                    ];
                }
                //暂存
                $pro = [
                    'arr_id' => $arr_id,
                    'recv_id' => $recv_id,
                    'ib_id' => '',
                    'bar_code' => $product['bar_code'],
                    'uniq_code' => $product['uniq_code'],
                    'lot_num' => $lot_num,
                    'box_code' => $product['box_code'] ?? '',
                    'container_code' => $product['container_code'] ?? '',
                    'quality_type' => 1,
                    'quality_level' => 'A',
                    'warehouse_code' => $warehouse_code,
                    'created_user' => request()->header('user_id'),
                    'created_at' => date('Y-m-d H:i:s'),
                    'ib_at' => date('Y-m-d H:i:s'),
                    'sku' => $products2[$product['uniq_code']]['sku'],
                ];
                $pro_date = array_merge($pro, $pro_return);

                $detail = RecvDetail::where(['arr_id' => $arr_id, 'uniq_code' => $product['uniq_code']])->first();
                // 唯一码已经走v1版本逻辑被收货，无需再操作收货
                if ($detail)  continue;
                // 插入收货明细
                (new RecvDetail())->insert($pro_date);
                $num++;
                // 绑定唯一码数据
                UniqCodePrintLog::bindBarCode($product['uniq_code'], $arr_id, $product['bar_code'], $return);
            }
            // 更新收货数量
            $recv_item->update(['recv_num' => $recv_item->recv_num + $num]);

            // 更新收货检查表的状态
            WmsReceiveCheck::where(['arr_id' => $arr_id, 'type' => 0, 'status' => 0, 'admin_user_id' => ADMIN_INFO['user_id']])->whereIn('uniq_code', array_column($products, 'uniq_code'))->update(['status' => 1]);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }

        (new RecvOrder())->recvDone($arr_id, $recv_id);
        return true;
    }

    // 普通商品确认收货
    function normalConfirm($data)
    {
        $arr_id = $data['arr_id'];
        $scan_type = $data['scan_type'] ?? 0; //0-唯一码商品 1-普通商品
        $recv_methods = $data['recv_methods'] ?? 1;
        $ib_code = $data['ib_code'] ?? '';

        $products = $data['products'];

        $arr_model = new ArrivalRegist();
        $arr_item = $arr_model->find($arr_id);
        if (empty($arr_item)) return $this->setErrorMsg(__('status.arr_not_exists'));
        $recv_type = empty($data['recv_type']) ? $arr_item->arr_type : $data['recv_type'];

        //检查登记单状态
        $res = $this->_preCheck($arr_item);
        if (!$res) return false;

        // 收货单id存在，检查收货单状态
        $recv_id =  empty($data['recv_id']) ? 0 : $data['recv_id'];
        if ($recv_id) {
            $recv_item = RecvOrder::where('created_user', ADMIN_INFO['user_id'])->find($recv_id);
            if ($recv_item->doc_status == 2) return $this->setErrorMsg(__('response.recv_done'));
            if ($recv_item->doc_status != 1) return $this->setErrorMsg(__('response.doc_status_err'));
            if ($recv_item->arr_id != $arr_id) return $this->setErrorMsg(__('response.params_err'));
            if ($recv_item->scan_type != 1) return $this->setErrorMsg('唯一码收货单不能收普通商品');
        }

        $warehouse_code  =  $arr_item->warehouse_code;
        $lot_num  =  $arr_item->lot_num;

        $return = false;
        if (in_array($recv_type, [2, 3])) $return = true;

        // 检查是否有暂存中的收货单
        $recv_item = RecvOrder::where('arr_id', $arr_id)->where('doc_status', 1)->where('created_user', request()->header('user_id'))->where('recv_status', 0)->where('scan_type', $scan_type)->first();
        if ($recv_item && ($recv_id != $recv_item->id)) return $this->setErrorMsg('recv_id参数错误');


        //判断唯一码打印数量
        $print_count = $arr_item->uni_num_count;
        // 采购到货/其他到货类型的登记单，不必须要打印唯一码
        if ($scan_type == 0 && $print_count == 0 && !$return) return $this->setErrorMsg(__('response.batch_not_match'));

        // 检查是否存在新品
        $bar_codes = array_unique(array_column($products, 'bar_code'));
        if (WmsProduct::hasNewBarCode($bar_codes, 2)) return $this->setErrorMsg(__('response.new_pro'));

        $checks = WmsReceiveCheck::where(['arr_id' => $arr_id, 'type' => 1, 'status' => 0, 'admin_user_id' => ADMIN_INFO['user_id']])->whereIn('bar_code', array_column($products, 'bar_code'))->get();
        // 没待处理的收货信息，直接确认收货
        if (!$checks->count()) {
            (new RecvOrder())->recvDone($arr_id, $recv_id);
            return true;
        }

        $recv = new RecvOrder();
        try {
            DB::beginTransaction();
            if (!$recv_item) {
                //新增
                $recv_code  = $recv->getErpCode('SHD', 10);
                $create_data = [
                    'arr_id' => $arr_id,
                    'recv_type' => $recv_type,
                    'recv_methods' => $recv_methods,
                    'doc_status' => 1,
                    'recv_num' => 1,
                    'scan_type' => 1,
                    'created_user' => request()->header('user_id'),
                    'recv_code' => $recv_code,
                    'warehouse_code' => $warehouse_code,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
                $recv_id =  $recv->insertGetId($create_data);
                if (!$recv_id) throw new Exception('收货单创建失败');
                $recv_item = RecvOrder::find($recv_id);
                $row['log_shd'] = WmsOptionLog::add(WmsOptionLog::SHD, $recv_code, '创建', '收货单创建成功', $create_data);
                //修改登记单的到货状态
                $old = $arr_item->arr_status;
                $arr_item->arr_status = 3;
                if ($old != 3) $row['arr_status'] = $arr_item->save();
                $row['log_djd'] = WmsOptionLog::add(WmsOptionLog::DJD, $arr_item->arr_code, '开始收货', '到货登记单开始收货', $old);
            }

            $pro_items = [];
            if ($return) {
                if (!isset($ib_item)) $ib_item = IbOrder::where('ib_code', $ib_code)->first();
                //退调货订单
                $pro_items =  WithdrawUniqLog::where('source_code', $ib_item->third_no)->whereIn('bar_code', $bar_codes)->where('is_scan', 0)->get()->keyBy('uniq_code');
            }

            $ib_item = null;
            if ($data['ib_code'] ?? '') $ib_item = IbOrder::where('ib_code', $ib_code)->first();
            foreach ($products as $product) {
                $check = WmsReceiveCheck::where(['arr_id' => $arr_id, 'status' => 0, 'admin_user_id' => ADMIN_INFO['user_id'], 'bar_code' => $product['bar_code'], 'num' => $product['num'], 'quality_level' => $product['quality_level'], 'type' => 1])->first();
                $uniq_codes = $this->_getNormalUniqCodes($check, $lot_num, $recv_id, $pro_items);
                foreach ($uniq_codes as $uniq_code) {
                    $pro_return = [];
                    $pro_item = $pro_items[$uniq_code] ?? null;
                    if ($pro_item) {
                        // 更新退调记录
                        $pro_item->is_scan = 1;
                        $pro_item->save();

                        //退调单数据
                        $pro_return = [
                            //退调单
                            'sup_id' => $pro_item->sup_id,
                            'buy_price' => $pro_item->buy_price,
                            'quality_type' => $product['quality_level'] == 'A' ? 1 : 2,
                            'quality_level' =>  $product['quality_level'],
                            'sup_confirm' => 1,
                            'ib_confirm' => 1,
                            'inv_type' => $pro_item->inv_type,
                            'ib_id' => $ib_item->id,
                        ];
                    }
                    //暂存
                    $pro = [
                        'arr_id' => $arr_id,
                        'recv_id' => $recv_id,
                        'ib_id' => '',
                        'bar_code' => $product['bar_code'],
                        'uniq_code' => $uniq_code,
                        'lot_num' => $lot_num,
                        'box_code' => $product['box_code'] ?? '',
                        'recv_unit' => $product['recv_unit'] ?? 0,
                        'container_code' => $product['container_code'] ?? '',
                        'quality_type' => $product['quality_level'] == 'A' ? 1 : 2,
                        'quality_level' => $product['quality_level'],
                        'is_qc' => 1,
                        'warehouse_code' => $warehouse_code,
                        'created_user' => request()->header('user_id'),
                        'created_at' => date('Y-m-d H:i:s'),
                        'ib_at' => date('Y-m-d H:i:s'),
                    ];
                    $pro_date = array_merge($pro, $pro_return);
                    // 插入收货明细
                    (new RecvDetail())->insert($pro_date);
                }
                $check->update(['status' => 1]);
                $num = RecvDetail::where(['recv_id' => $recv_id, 'arr_id' => $arr_id])->count();
                $recv_item->update(['recv_num' => $num]);
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }

        (new RecvOrder())->recvDone($arr_id, $recv_id);
        return true;
    }

    function _addReceiveCheck($products, $arr_id, $scan_type)
    {
        foreach ($products as $product) {
            $bar_code = $product['bar_code'] ?? '';
            $uniq_code = $product['uniq_code'] ?? '';
            $quality_level = $product['quality_level'] ?? '';
            $where = ['arr_id' => $arr_id, 'tenant_id' => $this->tenant_id, 'bar_code' => $bar_code];
            // 唯一码商品
            if ($scan_type == 0) {
                //兼容v1的收货逻辑，通过v1扫码收货的不再处理
                if (Redis::get($uniq_code)) return [false, __('response.uniq_scan_repeat')];
                Redis::setex($uniq_code, 10, 1);

                $where['uniq_code'] = $uniq_code;
                $where['type'] = 0;
                $find = WmsReceiveCheck::where(['arr_id' => $arr_id, 'tenant_id' => $this->tenant_id, 'uniq_code' => $uniq_code, 'type' => 0])->first();
                if ($find) continue;
                WmsReceiveCheck::create(array_merge($where, ['admin_user_id' => $this->user_id]));
                continue;
            }

            // 普通商品
            $where['uniq_code'] = $bar_code;
            $where['quality_level'] = $quality_level;
            $where['type'] = 1;
            $where['admin_user_id'] = $this->user_id;
            $find = WmsReceiveCheck::where($where)->first();
            $num = $find ? $find->num + 1 : 1;
            if ($find) {
                $find->update(['num' => $num]);
                continue;
            }
            WmsReceiveCheck::create(array_merge($where, ['num' => $num]));
        }
    }

    function _addReceiveCheckV3($products, $arr_id, $scan_type)
    {
        foreach ($products as $product) {
            $bar_code = $product['bar_code'] ?? '';
            $uniq_code = $product['uniq_code'] ?? '';
            $quality_level = $product['quality_level'] ?? '';
            $where = ['arr_id' => $arr_id, 'tenant_id' => $this->tenant_id, 'bar_code' => $bar_code];
            // 唯一码商品
            if ($scan_type == 0) {
                //兼容v1的收货逻辑，通过v1扫码收货的不再处理
                if (Redis::get($uniq_code)) continue;
                Redis::setex($uniq_code, 10, 1);

                $where['uniq_code'] = $uniq_code;
                $where['type'] = 0;
                $find = WmsReceiveCheck::where(['arr_id' => $arr_id, 'tenant_id' => $this->tenant_id, 'uniq_code' => $uniq_code, 'type' => 0])->first();
                if ($find) continue;
                WmsReceiveCheck::create(array_merge($where, ['admin_user_id' => $this->user_id]));
                continue;
            }

            // 普通商品
            $where['uniq_code'] = $bar_code;
            $where['quality_level'] = $quality_level;
            $where['type'] = 1;
            $where['admin_user_id'] = $this->user_id;
            $find = WmsReceiveCheck::where($where)->first();
            $num = $find ? $find->num + 1 : 1;
            if ($find) {
                $find->update(['num' => $num]);
                continue;
            }
            WmsReceiveCheck::create(array_merge($where, ['num' => $num]));
        }
    }

    // 唯一码确认收货
    function uniqConfirmV3($data)
    {
        $arr_id = $data['arr_id'];
        $scan_type = $data['scan_type'] ?? 0; //0-唯一码商品 1-普通商品
        $recv_methods = $data['recv_methods'] ?? 1;
        $ib_code = $data['ib_code'] ?? '';
        $products = $data['products'];

        $arr_model = new ArrivalRegist();
        $arr_item = $arr_model->find($arr_id);
        if (empty($arr_item)) return $this->setErrorMsg(__('status.arr_not_exists'));
        $recv_type = empty($data['recv_type']) ? $arr_item->arr_type : $data['recv_type'];
        if (!$ib_code) $ib_code = $arr_item->ib_code;

        // // 唯一码校验
        // $wait_uniq_codes = $this->_allReceiveUniqCodes($arr_item);
        // $uniq_codes = array_column($products,'uniq_code');
        // $diff = array_diff($uniq_codes,$wait_uniq_codes);
        // if($diff) return $this->setErrorMsg('唯一码有误：'.implode(',',$diff));

        //检查登记单状态
        $res = $this->_preCheck($arr_item);
        if (!$res) return false;

        // 收货单id存在，检查收货单状态
        $recv_id =  empty($data['recv_id']) ? 0 : $data['recv_id'];
        if ($recv_id) {
            $recv_item = RecvOrder::where('created_user', ADMIN_INFO['user_id'])->find($recv_id);
            if ($recv_item->doc_status == 2) return $this->setErrorMsg(__('response.recv_done'));
            if ($recv_item->doc_status != 1) return $this->setErrorMsg(__('response.doc_status_err'));
            if ($recv_item->arr_id != $arr_id) return $this->setErrorMsg(__('response.params_err'));
            if ($recv_item->scan_type != 0) return $this->setErrorMsg('普通商品收货单不能收唯一码商品');
        }
        $this->_addReceiveCheckV3($products, $arr_id, 0);

        $warehouse_code  =  $arr_item->warehouse_code;
        $lot_num  =  $arr_item->lot_num;

        $return = in_array($recv_type, [2, 3]) ? true : false;

        // 检查是否有暂存中的收货单
        $recv_item = RecvOrder::where(['arr_id' => $arr_id, 'doc_status' => 1, 'created_user' => request()->header('user_id'), 'recv_status' => 0, 'scan_type' => $scan_type, 'warehouse_code' => $warehouse_code])->orderBy('id', 'desc')->first();
        if ($recv_item && ($recv_id != $recv_item->id)) return $this->setErrorMsg('recv_id参数错误');


        //判断批次号是否正确
        $print_count = $arr_item->uni_num_count;
        // 调拨到货/其他到货类型的登记单，不能没有批次号
        if ($print_count == 0 && !$return) return $this->setErrorMsg(__('response.batch_not_match'));

        // 检查是否存在新品
        $bar_codes = array_unique(array_column($products, 'bar_code'));
        if (WmsProduct::hasNewBarCode($bar_codes, 1)) return $this->setErrorMsg(__('response.new_pro'));

        WmsProduct::bindBarcodeType(array_column($bar_codes, 'id'), 1);

        $keys = array_column($products, 'uniq_code');
        $products2 = array_combine($keys, $products);

        $products = WmsReceiveCheck::where(['arr_id' => $arr_id, 'type' => 0, 'status' => 0, 'admin_user_id' => ADMIN_INFO['user_id']])->whereIn('uniq_code', array_column($products, 'uniq_code'))->get();
        // 没有处理的收货信息，直接确认收货
        if (!$products->count()) {
            $res = (new RecvOrder())->recvDone($arr_id, $recv_id);
            if (!$res[0]) return $this->setErrorMsg($res[1]);
            return true;
        }

        // 已经被v1逻辑收货的，不再处理，防止重复收货
        $skip_arr = [];
        $uniq_codes2 = RecvDetail::where(['arr_id' => $arr_id])->whereIn('uniq_code', $keys)->pluck('uniq_code');
        if ($uniq_codes2) $uniq_codes2 = $uniq_codes2->toArray();
        $add_uniq_codes = array_diff($keys, $uniq_codes2);
        if ($skip_arr) $skip_arr = $uniq_codes2;


        // 没有要处理的数据，直接返回
        if (!$add_uniq_codes)
            return [
                'total' =>  count($products),
                'skip_num' => count($skip_arr),
                'skip_detail' => $skip_arr,
            ];


        $products = $products->toArray();
        $recv = new RecvOrder();
        try {
            DB::beginTransaction();
            if (!$recv_item) {
                //新增
                $recv_code  = $recv->getErpCode('SHD', 10);
                $create_data = [
                    'arr_id' => $arr_id,
                    'recv_type' => $recv_type,
                    'recv_methods' => $recv_methods,
                    'doc_status' => 1,
                    'recv_num' => 0,
                    'created_user' => request()->header('user_id'),
                    'recv_code' => $recv_code,
                    'warehouse_code' => $warehouse_code,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
                $recv_id =  $recv->insertGetId($create_data);
                if (!$recv_id) throw new Exception('收货单创建失败');
                $recv_item = RecvOrder::find($recv_id);
                $row['log_shd'] = WmsOptionLog::add(WmsOptionLog::SHD, $recv_code, '创建', '收货单创建成功', $create_data);
                //修改登记单的到货状态
                $old = $arr_item->arr_status;
                $arr_item->arr_status = 3;
                if ($old != 3) $row['arr_status'] = $arr_item->save();
                $row['log_djd'] = WmsOptionLog::add(WmsOptionLog::DJD, $arr_item->arr_code, '开始收货', '到货登记单开始收货', $old);
            }

            $ib_item = null;
            if ($data['ib_code'] ?? '') $ib_item = IbOrder::where('ib_code', $ib_code)->first();

            $pro_items = [];
            if ($return) {
                if (!isset($ib_item)) $ib_item = IbOrder::where('ib_code', $ib_code)->first();
                //退调货订单
                $pro_items =  WithdrawUniqLog::where(['source_code' => $ib_item->third_no, 'is_scan' => 0])->whereIn('bar_code', $bar_codes)->get()->keyBy('uniq_code');
            }

            $num = 0;
            foreach ($products as $product) {
                // 已经添加过的，收货明细不再处理
                if (in_array($product['uniq_code'], $uniq_codes2)) continue;
                $pro_return = [];
                $pro_item = $pro_items[$product['uniq_code']] ?? null;
                if ($pro_item) {
                    // 更新退调记录
                    $pro_item->is_scan = 1;
                    $pro_item->save();

                    //退调单数据
                    $pro_return = [
                        'sup_id' => $pro_item->sup_id,
                        'buy_price' => $pro_item->buy_price,
                        'quality_type' => $pro_item->getRawOriginal('quality_type'),
                        'quality_level' => $pro_item->quality_level,
                        'inv_type' => $pro_item->inv_type,
                        'sup_confirm' => 1,
                        'ib_confirm' => 1,
                        'ib_id' => $ib_item->id,
                    ];
                }
                //暂存
                $pro = [
                    'arr_id' => $arr_id,
                    'recv_id' => $recv_id,
                    'ib_id' => '',
                    'bar_code' => $product['bar_code'],
                    'uniq_code' => $product['uniq_code'],
                    'lot_num' => $lot_num,
                    'box_code' => $product['box_code'] ?? '',
                    'container_code' => $product['container_code'] ?? '',
                    'quality_type' => 1,
                    'quality_level' => 'A',
                    'warehouse_code' => $warehouse_code,
                    'created_user' => request()->header('user_id'),
                    'created_at' => date('Y-m-d H:i:s'),
                    'ib_at' => date('Y-m-d H:i:s'),
                    'sku' => $products2[$product['uniq_code']]['sku'],
                ];
                $pro_date = array_merge($pro, $pro_return);

                $detail = RecvDetail::where(['arr_id' => $arr_id, 'uniq_code' => $product['uniq_code']])->first();
                // 唯一码已经走v1版本逻辑被收货，无需再操作收货
                if ($detail) {
                    $skip_arr[] = $product['uniq_code'];
                    continue;
                }
                // 插入收货明细
                (new RecvDetail())->insert($pro_date);
                $num++;
                // 绑定唯一码数据
                UniqCodePrintLog::bindBarCode($product['uniq_code'], $arr_id, $product['bar_code'], $return);
            }
            // 更新收货数量
            $recv_item->update(['recv_num' => $recv_item->recv_num + $num]);

            // 更新收货检查表的状态
            WmsReceiveCheck::where(['arr_id' => $arr_id, 'type' => 0, 'status' => 0, 'admin_user_id' => $this->user_id])->whereIn('uniq_code', array_column($products, 'uniq_code'))->update(['status' => 1]);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }

        $res = (new RecvOrder())->recvDone($arr_id, $recv_id);
        if (!$res[0]) return $this->setErrorMsg($res[1]);
        return [
            'total' =>  count($products),
            'skip_num' => count($skip_arr),
            'skip_detail' => $skip_arr,
        ];
    }

    // 给普通商品生成唯一码记录
    function _getNormalUniqCodes($check, $lot_num, $recv_id, $pro_items = [])
    {
        if ($check->type != 1) return [];
        $num = $check->num;

        // 退/调货，唯一码使用之前生成的数据
        if ($pro_items) {
            $arr = [];
            foreach ($pro_items as $item) {
                if ($item->bar_code == $check->bar_code && $item->quality_level == $item->quality_level) {
                    $arr[] = $item->uniq_code;
                }
                if (count($arr) >= $num) break;
            }
            return $arr;
        }

        // 采购/其他收货，生成新的唯一码
        $no = 1;
        $arr = [];
        while ($no <= $num) {
            $arr[] = 'PT' . $lot_num . '-' . $recv_id . '-' . $check->id . $no;
            $no++;
        }
        return $arr;
    }
}
