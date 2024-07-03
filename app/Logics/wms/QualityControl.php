<?php

namespace App\Logics\wms;

use App\Handlers\OSSUtil;
use App\Logics\BaseLogic;
use App\Logics\RedisKey;
use App\Logics\Robot;
use App\Logics\wms\Warehouse as WmsWarehouse;
use App\Models\Admin\V2\ConsignmentDetails;
use App\Models\Admin\V2\IbDetail;
use App\Models\Admin\V2\IbOrder;
use App\Models\Admin\V2\Inventory;
use App\Models\Admin\V2\OIbDetails;
use App\Models\Admin\V2\PurchaseDetails;
use App\Models\Admin\V2\RecvDetail;
use App\Models\Admin\V2\RecvOrder;
use App\Models\Admin\V2\SupInv;
use App\Models\Admin\V2\UniqCodePrintLog;
use App\Models\Admin\V2\Warehouse;
use App\Models\Admin\V2\WmsOptionLog;
use App\Models\Admin\V2\WmsQualityConfirmList;
use App\Models\Admin\V2\WmsQualityDetail;
use App\Models\Admin\V2\WmsQualityList;
use App\Models\Admin\V2\WmsStockLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class QualityControl extends BaseLogic
{
    // 默认质检位置码
    protected static $default_location_code = 'ZJZCQ001';

    // 收货单一键质检
    function qcOneStep($params)
    {
        $fails = [];
        $recv = RecvOrder::find($params['recv_id']);
        $list = RecvDetail::where('recv_id', $params['recv_id'])->where('is_qc', 0)->where('is_cancel', 0)->get();
        $arr_ids = [];
        $uniq_codes = $list->pluck('uniq_code')->toArray();
        $bar_codes = $list->pluck('bar_code')->toArray();
        $ib_arr = [];
        try {
            DB::beginTransaction();
            foreach ($list as $sku) {
                try {
                    if ($sku->ib_id > 0) {
                        $ib_arr[] = ['ib_id' => $sku->ib_id, 'sup_id' => $sku->sup_id, 'bar_code' => $sku->bar_code, 'buy_price' => $sku->buy_price, 'origin_quality_type' => $sku->getRawOriginal('quality_type'), 'quality_type' => 1, 'quality_level' => 'A'];
                    }

                    $sku->quality_type = 1;
                    $sku->quality_level = 'A';
                    $this->_qcSave($sku, WmsQualityList::METHOD_ONE_STEP, WmsQualityList::TYPE_STORE_IN, [
                        'location_code' => self::$default_location_code,
                        'area_name' => '质检暂存区',
                        'recv_code' => $recv->recv_code,
                    ]);
                    $arr_ids[] = $sku->arr_id;
                } catch (Exception $e) {
                    $fails[] = $sku['uniq_code'];
                    throw $e;
                }
            }
            // 更新质检单信息
            $res = WmsQualityList::where('create_user_id', ADMIN_INFO['user_id'])
                ->where('status', WmsQualityList::STATUS_AUDIT)
                ->where('method', WmsQualityList::METHOD_ONE_STEP)
                // ->whereIn('arr_id', $arr_ids)->get();
                ->where('recv_code', $recv->recv_code)->get();
            foreach ($res as $qc) {
                $num = WmsQualityDetail::where('qc_code', $qc->qc_code)->selectRaw('COUNT(id) as total_num,COUNT(IF(quality_type=1 and status=1,id,NULL)) as normal_num,COUNT(IF(quality_type=2 and status=1,id,NULL)) as defect_num')->first()->toArray();
                $qc->update([
                    'total_num' => $num['total_num'] ?? 0,
                    'normal_num' => $num['normal_num'] ?? 0,
                    'defect_num' => $num['defect_num'] ?? 0,
                    'completed_at' => date('Y-m-d H:i:s'),
                    'submit_user_id' => ADMIN_INFO['user_id'],
                    'admin_user_id' => ADMIN_INFO['user_id'],
                ]);
                WmsOptionLog::add(WmsOptionLog::QC, $qc->qc_code, '质检完成', '一键质检完成', []);
                // $this->_updateInventoryStatus($qc, $params['recv_id']);
            }


            // 更新入库单的质检状态
            RecvDetail::where(['recv_id' => $recv->id, 'is_qc' => 0])->whereIn('uniq_code', $uniq_codes)->update(['is_qc' => 1, 'quality_type' => 1, 'quality_level' => 'A', 'area_code' => 'ZJZCQ001',]);
            // 更新唯一码的质检状态
            Inventory::where(['recv_id' => $recv->id, 'is_qc' => 0])->whereIn('uniq_code', $uniq_codes)->update(['in_wh_status' => 2,'inv_status'=>3, 'is_qc' => 1, 'quality_type' => 1, 'quality_level' => 'A', 'area_code' => 'ZJZCQ001']);
            DB::commit();

            // 刷新入库单质检类型数量
            $this->updateIbQcNumAsync($ib_arr);

            // 刷新supInv
            foreach ($uniq_codes as $uniq_code) {
                Inventory::invAsyncAdd(0, 3, '', '', $uniq_code);
            }

            // 刷新totalInv
            foreach($bar_codes as $bar_code){
                Inventory::invAsyncAdd(0, 2, $recv->warehouse_code,$bar_code);
            }

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            Log::info($e->__toString());
            return false;
        }
        return $fails;
    }

    public function updateIbQcNumAsync($arr)
    {
        if (count($arr) == 0) return;

        $data = [
            'params' => ['tenant_id' => request()->header('tenant_id'), 'data' => $arr],
            'class' => 'App\Logics\wms\QualityControl',
            'method' => 'updateIbQcNum',
            'time' => date('Y-m-d H:i:s'),
        ];
        Redis::rpush(RedisKey::QUEUE2_AYSNC_HADNLE, json_encode($data));
    }

    public function updateIbQcNum($params)
    {
        $arr = $params['data'];
        foreach ($arr as $item) {

            $ib_id = $item['ib_id'];
            $sup_id = $item['sup_id'];
            $bar_code = $item['bar_code'];
            $buy_price = $item['buy_price'];
            $quality_type = $item['quality_type'];
            $quality_level = $item['quality_level'];
            $origin_quality_type = $item['origin_quality_type'];


            $ib_item = $ib_id == 0 ? null : IbOrder::where('doc_status', 3)->find($ib_id);
            if (!$ib_item) continue;

            $ib_detail_item = IbDetail::where('ib_code', $ib_item->ib_code)->where('sup_id', $sup_id)
                ->where('bar_code', $bar_code)->where('buy_price', $buy_price)->first();
            $normal_count = $ib_detail_item->normal_count;
            $flaw_count = $ib_detail_item->flaw_count;
            if (in_array($ib_item->ib_type, [1, 4])) {
                //采购单/其他入库单匹配完入库单之后再质检时正品瑕疵数量更新
                if ($quality_type != $origin_quality_type) {
                    $flaw_count = $quality_type == 2 ? $flaw_count + 1 : $flaw_count - 1;
                    $normal_count = $quality_type == 2 ? $normal_count - 1 : $normal_count + 1;
                    $row['ib_detail_item'] = $ib_detail_item->update([
                        'quality_level' => $quality_level,
                        'quality_type' => $quality_type,
                        'flaw_count' => $flaw_count,
                        'normal_count' => $normal_count,
                    ]);
                    $row['ib_item'] = $ib_item->update([
                        'normal_count' => $quality_type == 2 ? $ib_item->normal_count - 1 : $ib_item->normal_count + 1,
                        'flaw_count' => $quality_type == 2 ? $ib_item->flaw_count + 1 : $ib_item->flaw_count - 1,
                    ]);
                    if ($ib_item->ib_type == 1) {
                        if (strpos($ib_item->source_code, 'CG')) {
                            $pur_details_item = PurchaseDetails::where('buy_code', $ib_item->source_code)->where('bar_code', $bar_code)->where('buy_price', $buy_price)->first();
                            $row['pur_details_item'] = $pur_details_item->update([
                                'normal_count' => $quality_type == 2 ? $pur_details_item->normal_count - 1 : $pur_details_item->normal_count + 1,
                                'flaw_count' => $quality_type == 2 ? $pur_details_item->flaw_count + 1 : $pur_details_item->flaw_count - 1,
                            ]);
                        }
                        if (strpos($ib_item->source_code, 'JMD')) {
                            $pur_details_item = ConsignmentDetails::where('origin_code', $ib_item->source_code)->where('bar_code', $bar_code)->where('buy_price', $buy_price)->first();
                            $row['consign_details_item'] = $pur_details_item->update([
                                'normal_count' => $quality_type == 2 ? $pur_details_item->normal_count - 1 : $pur_details_item->normal_count + 1,
                                'flaw_count' => $quality_type == 2 ? $pur_details_item->flaw_count + 1 : $pur_details_item->flaw_count - 1,
                            ]);
                        }
                    }
                    if ($ib_item->ib_type == 4) {
                        $oib_details_item = OIbDetails::where('oib_code', $ib_item->third_no)->where('bar_code', $bar_code)->where('buy_price', $buy_price)->first();
                        $row['oib_details_item'] = $oib_details_item->update([
                            'normal_count' => $quality_type == 2 ? $pur_details_item->normal_count - 1 : $pur_details_item->normal_count + 1,
                            'flaw_count' => $quality_type == 2 ? $pur_details_item->flaw_count + 1 : $pur_details_item->flaw_count - 1,
                        ]);
                    }
                }
            } else {
                //退调货质检之后正品瑕疵数量更新
                if ($quality_type == 2) $flaw_count += 1;
                else  $normal_count += 1;
                $row['ib_detail_item'] = $ib_detail_item->update([
                    'quality_level' => $quality_level,
                    'quality_type' => $quality_type,
                    'flaw_count' => $flaw_count,
                    'normal_count' => $normal_count,
                ]);
                $row['ib_item'] = $ib_item->update([
                    'normal_count' => $quality_type == 2 ? $ib_item->normal_count : $ib_item->normal_count + 1,
                    'flaw_count' => $quality_type == 2 ? $ib_item->flaw_count + 1 : $ib_item->flaw_count,
                ]);
            }
        }
    }

    // 更新商品库存的在库状态
    private function _updateInventoryStatus($qc, $recv_id)
    {
        // 更新收货单的在库状态和质量等级信息
        $where = ['qc_code' => $qc->qc_code, 'status' => 1];
        $details = WmsQualityDetail::where($where)->get();

        foreach ($details as $item) {
            // 根据登记单id、唯一码找到收货单id
            if (!$recv_id) {
                $recv_id = RecvDetail::where(['arr_id' => $item->arr_id, 'uniq_code' => $item->uniq_code, 'is_qc' => 0, 'is_cancel' => 0])->value('recv_id');
            }

            $res = RecvDetail::qualityUpdate($recv_id, $item->uniq_code, $item->getRawOriginal('quality_type'), $item->quality_level, false);
            if (!($res[0] ?? false)) {
                throw new Exception('收货单状态和质量类型修改失败');
            }
        }
    }




    // 收货质检
    function qcReceive($params)
    {
        $fails = [];
        $list = RecvDetail::where('recv_id', $params['recv_id'])->where('is_cancel', 0)->where('is_qc', 0)->get()->toArray();
        if (!$list) return true;
        $recv = RecvOrder::find($params['recv_id']);

        foreach ($list as $sku) {
            try {
                $this->_qcSave($sku, WmsQualityList::METHOD_RECEIVE, WmsQualityList::TYPE_STORE_IN, [
                    'location_code' => self::$default_location_code,
                    'area_name' => '质检暂存区',
                    'recv_code' => $recv->recv_code,
                ]);
            } catch (Exception $e) {
                $fails[] = $sku['uniq_code'];
                throw $e;
                // throw $e;
            }
        }
        if ($fails) {
            $this->setErrorMsg(sprintf(__('tips.option_fail_unique_info'), implode(',', $fails)));
            return false;
        }
        return true;
    }

    function scan($params)
    {
        $fails = [];
        foreach ($params['skus'] as $sku) {
            try {
                $this->_qcSave($sku, WmsQualityList::METHOD_ONE_BY_ONE, WmsQualityList::TYPE_WAREHOUSE, [
                    'location_code' => self::$default_location_code,
                    'area_name' => '质检暂存区',
                ]);
            } catch (Exception $e) {
                $fails[] = $sku['uniq_code'];
                // throw $e;
            }
        }
        return $fails;
    }

    private function _qcSave($sku, $method, $type, $info = [])
    {
        // 获取登记单编码
        $uniq_log = UniqCodePrintLog::where('uniq_code', $sku['uniq_code'])->first();
        if (!$uniq_log) {
            throw new Exception(__('tips.unqiue_not_exist'));
        }

        // 未质检过
        $find = WmsQualityDetail::where('uniq_code', $sku['uniq_code'])->where('arr_id', $uniq_log->arr_id)->where('status', 1)->first();
        if ($find) {
            throw new Exception(__('tips.has_check_log'));
        }

        $regist = $uniq_log->arrivalRegist;

        $recv_code = $info['recv_code'] ?? '';
        $where = [
            'create_user_id' => ADMIN_INFO['user_id'],
            'arr_id' => $uniq_log->arr_id,
        ];
        if ($recv_code) $where['recv_code'] = $recv_code;
        if ($method == WmsQualityList::METHOD_ONE_BY_ONE) $where['qc_status'] = WmsQualityList::QC_ING;

        // 获取入库仓库信息
        $warehouse_code = $regist ? $regist->warehouse_code : '';
        $warehouse_name = $uniq_log->warehouse_name;
        if ($warehouse_code) $warehouse_name = (new Warehouse())->getName($warehouse_code);
        $qc = WmsQualityList::where($where)->orderBy('id', 'desc')->first();
        if (!$qc) {
            $qc_status = 0;
            $status = 0;
            if (in_array($method, [WmsQualityList::METHOD_ONE_STEP, WmsQualityList::METHOD_RECEIVE])) {
                $qc_status = WmsQualityList::QC_DONE;
                $status = WmsQualityList::STATUS_AUDIT;
            }
            $qc = WmsQualityList::create([
                'type' => $type,
                'arr_id' => $uniq_log->arr_id,
                'create_user_id' => ADMIN_INFO['user_id'],
                'admin_user_id' => ADMIN_INFO['user_id'],
                'status' => $status,
                'qc_status' => $qc_status,
                'qc_code' => WmsQualityList::code(),
                'method' => $method,
                'tenant_id' => ADMIN_INFO['tenant_id'],
                'warehouse_code' => $warehouse_code,
                'warehouse_name' => $warehouse_name,
                'recv_code' => $recv_code,
            ]);
            // 质检单创建记录
            WmsOptionLog::add(WmsOptionLog::QC, $qc->qc_code, '创建', '质检单创建成功', []);
        }

        $detail = WmsQualityDetail::create([
            'qc_code' => $qc->qc_code,
            'bar_code' => $uniq_log->bar_code,
            'uniq_code' => $sku['uniq_code'],
            'quality_type' => WmsQualityDetail::getQualityTypeByLevel($sku['quality_level']),
            'quality_level' => $sku['quality_level'],
            'status' => 1,
            'admin_user_id' => ADMIN_INFO['user_id'],
            'area_name' => $info['area_name'],
            'location_code' => $info['location_code'],
            'arr_id' => $uniq_log->arr_id,
            'tenant_id' => ADMIN_INFO['tenant_id'],
            'remark' => $sku['remark'] ?? '',
            'pic' => $sku['pics'] ?? '',
        ]);

        WmsStockLog::add(WmsStockLog::ORDER_QC, $detail->uniq_code, $detail->qc_code, [
            'origin_value' => $detail->quality_type . '/' . $detail->quality_level,
        ]);

        // 收货单同步质量等级
        if ($method != WmsQualityList::METHOD_ONE_STEP) {
            $this->_syncRecvDetail($detail, 0);
        }

        return true;
    }

    // 更新商品库存的在库状态
    private function _syncRecvDetail($item, $recv_id)
    {
        // // 更新收货单的在库状态和质量等级信息
        // // 根据登记单id、唯一码找到收货单id
        // if (!$recv_id) {
        //     $recv_id = RecvDetail::where(['arr_id' => $item->arr_id, 'uniq_code' => $item->uniq_code, 'is_qc' => 0, 'is_cancel' => 0])->value('recv_id');
        // }

        // $res = RecvDetail::qualityUpdate($recv_id, $item->uniq_code, $item->getRawOriginal('quality_type'), $item->quality_level, false);
        // if (!($res[0] ?? false)) {
        //     throw new Exception(__('tips.option_fail_change_recv'));
        // }
        $data = [
            'params' => ['tenant_id' => request()->header('tenant_id'), 'recv_id' => $recv_id, 'arr_id' => $item->arr_id, 'uniq_code' => $item->uniq_code, 'quality_level' => $item->quality_level],
            'class' => 'App\Logics\wms\QualityControl',
            'method' => 'syncRecvDetail',
            'time' => date('Y-m-d H:i:s'),
        ];
        Redis::rpush(RedisKey::QUEUE_AYSNC_HADNLE, json_encode($data));
    }

    public function syncRecvDetail($params)
    {
        $recv_id = $params['recv_id'] ?? 0;
        $arr_id = $params['arr_id'];
        $uniq_code = $params['uniq_code'];
        $quality_level = $params['quality_level'];
        $quality_type = $quality_level == 'A' ? 1 : 2;
        // 更新收货单的在库状态和质量等级信息
        // 根据登记单id、唯一码找到收货单id
        if (!$recv_id) {
            $recv_id = RecvDetail::where(['arr_id' => $arr_id, 'uniq_code' => $uniq_code, 'is_qc' => 0, 'is_cancel' => 0])->value('recv_id');
        }

        $res = RecvDetail::qualityUpdate($recv_id, $uniq_code, $quality_type, $quality_level, true);
        if (!($res[0] ?? false)) {
            Robot::sendException('唯一码质量等级同步失败' . $uniq_code);
            // throw new Exception(__('tips.option_fail_change_recv'));
            // $data = [
            //     'params' => ['tenant_id' => request()->header('tenant_id'), 'recv_id' => $recv_id, 'arr_id' => $arr_id, 'uniq_code' => $uniq_code, 'quality_level' => $quality_level],
            //     'class' => 'App\Logics\wms\QualityControl',
            //     'method' => 'syncRecvDetail',
            //     'time' => date('Y-m-d H:i:s'),
            // ];
            // Redis::rpush(RedisKey::QUEUE_AYSNC_HADNLE, json_encode($data));

        }
    }


    // 质检单提交
    function scanSubmit($params = [])
    {
        try {
            DB::beginTransaction();
            // 未提交的质检单全部变更状态
            $res = WmsQualityList::where('create_user_id', ADMIN_INFO['user_id'])->where('status', WmsQualityList::STATUS_STAGE)->where('method', WmsQualityList::METHOD_ONE_BY_ONE)->with('receiveOrder')->get();
            foreach ($res as $qc) {
                $where = ['qc_code' => $qc->qc_code, 'status' => 1];
                $num = WmsQualityDetail::where($where)->selectRaw('COUNT(id) as total_num,COUNT(IF(quality_type=1 and status=1,id,NULL)) as normal_num,COUNT(IF(quality_type=2 and status=1,id,NULL)) as defect_num')->first()->toArray();

                $qc->update([
                    'status' => WmsQualityList::STATUS_AUDIT,
                    'qc_status' => WmsQualityList::QC_DONE,
                    'total_num' => $num['total_num'] ?? 0,
                    'normal_num' => $num['normal_num'] ?? 0,
                    'defect_num' => $num['defect_num'] ?? 0,
                    'completed_at' => date('Y-m-d H:i:s'),
                    'submit_user_id' => ADMIN_INFO['user_id'],
                ]);

                // 库存明细入库状态变更成已质检
                // $this->_updateInventoryStatus($qc, $qc->receive_order ? $qc->receive_order->id : 0);

                // 质检单创建记录
                WmsOptionLog::add(WmsOptionLog::QC, $qc->qc_code, '质检完成', '质检单质检完成', []);
            }
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 质检单查询
    function qcSearch($params, $export = false)
    {
        $model = new WmsQualityList();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            $model = $model->with('submitUser')->orderBy('created_at', 'desc');
            return $model;
        });

        $items = [];
        foreach ($list['data'] as $item) {
            $items[] = [
                'id' => $item['id'],
                'warehouse_code' => $item['warehouse_code'],
                'warehouse_name' => $item['warehouse_name'],
                'qc_code' => $item['qc_code'],
                'total_num' => $item['total_num'],
                'normal_num' => $item['normal_num'],
                'defect_num' => $item['defect_num'],
                'probable_defect_num' => $item['probable_defect_num'],
                'remark' => $item['remark'],
                'created_at' => date('Y-m-d H:i:s', strtotime($item['created_at'])),
                'completed_at' => $item['completed_at'],
                'username' => $item['submit_user']['username'],
                'type_txt' => $item['type_txt'],
                'status' => $item['status'],
                'status_txt' => $item['status_txt'],
                'qc_status_txt' => $item['qc_status_txt'],
                'method_txt' => $item['method_txt'],
            ];
        }
        $list['data'] = $items;
        return $list;
    }

    function qcInfo($params)
    {
        if (!empty($params['id'])) $info = WmsQualityList::where('id', $params['id'])->with(['createUser', 'submitUser', 'adminUser'])->first();
        if (!empty($params['code'])) $info = WmsQualityList::where('qc_code', $params['code'])->with(['createUser', 'submitUser', 'adminUser'])->first();
        if (!$info) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }
        $info = $info->toArray();

        $sql = "SELECT a.*,wupl.sku,wupl.spec_one,wupl.spec_two,wupl.spec_three,wp.name,product_sn from (
            SELECT  bar_code,quality_type,quality_level,pic,remark,location_code,area_name,count(id) as num FROM wms_quality_detail WHERE qc_code=? AND tenant_id=?  and status=1 GROUP BY bar_code,quality_type,quality_level,pic,remark,location_code,area_name
            ) as a 
            left JOIN wms_spec_and_bar wupl ON a.bar_code = wupl.bar_code and wupl.tenant_id=?
            LEFT JOIN wms_product wp ON wupl.product_id = wp.id AND wp.tenant_id=?";

        $info['create_user'] = $info['create_user'] ? $info['create_user']['username'] : '';
        $info['submit_user'] = $info['submit_user'] ? $info['submit_user']['username'] : '';
        $info['admin_user'] = $info['admin_user'] ? $info['admin_user']['username'] : '';
        $detail = DB::select($sql, [$info['qc_code'], ADMIN_INFO['tenant_id'], ADMIN_INFO['tenant_id'], ADMIN_INFO['tenant_id']]);

        $total = 0;
        $id = 1;
        foreach ($detail as &$item) {
            $item->quality_type = $item->quality_type == 1 ? '正品' : "疑似瑕疵";
            $item->id = $id;
            $total += $item->num;
            $id++;
            $pics = explode(',', $item->pic);
            $item->pics = array_map(function ($val) {
                return env('ALIYUN_OSS_HOST') . $val;
            }, $pics);
        }
        $logs = WmsOptionLog::where('type', WmsOptionLog::QC)->where('doc_code', $info['qc_code'])->orderBy('id', 'desc')->get();
        return compact('info', 'detail', 'total', 'logs');
    }

    function qcSave($params)
    {
        WmsQualityList::where('id', $params['id'])->update([
            'remark' => $params['remark'],
            'admin_user_id' => ADMIN_INFO['user_id'],
        ]);
        return true;
    }

    function qcDetail($params)
    {
        $model = WmsQualityDetail::where('qc_code', $params['qc_code'])->where('bar_code', $params['bar_code'])->where('quality_level', $params['quality_level']);
        if ($params['WHERE'] ?? []) {
            $model->addListWhere($params['WHERE']);
        }
        $list = $model->with(['specBar', 'arrivalRegist', 'adminUser'])->get();
        $res = [];
        foreach ($list as $item) {
            $sku = $item->specBar;
            $product = $sku ? $sku->product : null;
            $res[] = [
                'merchant_name' => '',
                'product_sn' => $product ? $product->product_sn : '',
                'name' => $product ? $product->name : '',
                'spec_one' => $sku ? $sku->spec_one : '',
                'quality_type_txt' => $item->quality_type,
                'quality_level' => $item->quality_level,
                'lot_num' => $item->arrivalRegist ? $item->arrivalRegist->lot_num : '',
                'uniq_code' => $item->uniq_code,
                'num' => 1,
                'status' => $item->status == 1 ? '否' : '是',
                'admin_user' => $item->adminUser ? $item->adminUser->username : '',
                'created_at' => Carbon::parse($item->created_at)->toDatetimeString(),
            ];
        }
        return $res;
    }

    function qcConfirmSubmit($params)
    {
        $uniq_code = $params['uniq_code'];
        $remark = $params['remark'] ?? '';

        $find = WmsQualityConfirmList::where(['uniq_code' => $uniq_code, 'status' => 0])->first();
        if ($find) {
            $this->setErrorMsg(__('tips.has_changing_log'));
            return false;
        }

        // 获取登记单编码
        $uniq_log = Inventory::where(['uniq_code' => $uniq_code, 'warehouse_code' => $params['warehouse_code']])->with('arrivalRegist')->first();
        if (!$uniq_log) {
            $this->setErrorMsg(__('tips.unqiue_not_exist'));
            return false;
        }

        if ($uniq_log->sale_status != 1) {
            $this->setErrorMsg(__('tips.status_deny_option'));
            return false;
        }

        if (!$uniq_log->arrivalRegist) {
            $this->setErrorMsg(__('tips.not_fund_origin'));
            return false;
        }
        $inventory = Inventory::where('uniq_code', $uniq_code)->first();
        $old_quality_level = $inventory ? $inventory->quality_level : '';
        $old_quality_type = $old_quality_level == 'A' ? 1 : 2;
        try {
            DB::beginTransaction();
            $qc = WmsQualityConfirmList::create([
                'pic' => $params['pics'] ?? '',
                'notes' => $remark,
                'qc_code' => WmsQualityList::code(),
                'arr_id' => $uniq_log->arr_id,
                'arr_code' => $uniq_log->arrivalRegist->arr_code,
                'warehouse_name' => WmsWarehouse::name($uniq_log->warehouse_code),
                'bar_code' => $uniq_log->bar_code,
                'old_quality_level' => $old_quality_level,
                'old_quality_type' => $old_quality_type,
                'quality_type' => self::getQualityType($params['quality_level']),
                'uniq_code' => $uniq_code,
                'quality_level' => $params['quality_level'],
                'type' => WmsQualityList::TYPE_WAREHOUSE,
                'location_code' => $inventory && $inventory->location_code ? $inventory->location_code : self::$default_location_code,
                'area_name' => $inventory && $inventory->area_txt ? $inventory->area_txt : '质检暂存区',
                'submitter_id' => ADMIN_INFO['user_id'],
                'admin_user_id' => ADMIN_INFO['user_id'],
                'tenant_id' => ADMIN_INFO['tenant_id'],
            ]);
            WmsOptionLog::add(WmsOptionLog::QC, $qc->qc_code, '创建', '质检单创建成功', []);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }

        return true;
    }

    // 疑似单确认
    function qcConfirmDone($params)
    {
        $quality_type = $params['quality_type'];
        $id = $params['id'];
        $find = WmsQualityConfirmList::where('id', $id)->where('status', 0)->first();
        if (!$find) {
            $this->setErrorMsg(__('tips.doc_not_exist_or_done'));
            return false;
        }
        try {
            DB::beginTransaction();
            $find->update([
                'confirm_quality_type' => $quality_type,
                'confirm_quality_level' => $quality_type == WmsQualityDetail::NOTMAL ? 'A' : 'B',
                'comfirmor_id' => ADMIN_INFO['user_id'],
                'confirm_at' => date('Y-m-d H:i:s'),
                'confirm_remark' => $params['remark'] ?? '',
                'admin_user_id' => ADMIN_INFO['user_id'],
                'status' => 1,
            ]);
            WmsOptionLog::add(WmsOptionLog::QC, $find->qc_code, '质检完成', '质检确认完成', []);
            // 质量等级有变化，更新库存中质量等级
            if ($find->confirm_quality_level != $find->old_quality_level) {
                $inv = Inventory::where(['uniq_code' => $find->uniq_code])->first();
                if ($inv) {
                    $inv->update(['quality_type' => $find->confirm_quality_type, 'quality_level' => $find->confirm_quality_level]);
                    Inventory::totalInvUpdateByUniq([$find->uniq_code]);
                    if ($inv->sup_id) SupInv::supInvUpdate([$find->uniq_code]);
                }
            }
            WmsStockLog::add(WmsStockLog::ORDER_QC_CONFIRM, $find->uniq_code, $find->qc_code, [
                'origin_value' => $find->quality_type . '/' . $find->quality_level,
            ]);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    function qcConfirmSearch($params, $export = false)
    {

        $model = new WmsQualityConfirmList();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            $model = $model->with(['submitter', 'comfirmor', 'arrivalRegist', 'specBar'])->orderBy('created_at', 'desc');
            return $model;
        });

        $items = [];
        foreach ($list['data'] as $item) {
            $sku = $item['spec_bar'] ?? [];
            $product = $sku['product'] ?? [];
            $items[] = [
                'id' => $item['id'],
                'qc_code' => $item['qc_code'],
                'arr_code' => $item['arr_code'],
                'warehouse_name' => $item['warehouse_name'],
                'status_txt' => $item['status_txt'],
                'pic_url' => $item['pic_url'],
                'notes' => $item['notes'],
                'uniq_code' => $item['uniq_code'],
                'quality_type' => $item['old_quality_type_txt'],
                'quality_level' => $item['old_quality_level'],
                'remark' => $item['remark'],
                'confirm_quality_type_txt' => $item['status'] == 1 ? $item['confirm_quality_type_txt'] : $item['quality_type_txt'],
                'confirm_quality_level' => $item['status'] == 1 ? $item['confirm_quality_level'] : $item['quality_level'],
                'sku' => $sku ? $sku['sku'] : '',
                'product_sn' => $product ? $product['product_sn'] : '',
                'name' => $product ? $product['name'] : '',
                'spec_one' => $sku ? $sku['spec_one'] : '',
                'type_txt' => $item['type_txt'],
                'area_name' => $item['area_name'],
                'location_code' => $item['location_code'],
                'submitter' => $item['submitter']['username'],
                'submit_at' => date('Y-m-d H:i:s', strtotime($item['created_at'])),
                'comfirmor' => $item['comfirmor']['username'] ?? '',
                'confirm_at' => $item['confirm_at'],
                'confirm_remark' => $item['confirm_remark'],
                'created_at'=>$item['created_at'],
            ];
        }
        $list['data'] = $items;
        return $list;
    }

    function qcConfirmInfo($params)
    {
        if (!empty($params['id'])) $info = WmsQualityConfirmList::where('id', $params['id'])->with(['submitter', 'comfirmor', 'specBar'])->first()->toArray();
        if (!empty($params['code'])) $info = WmsQualityConfirmList::where('qc_code', $params['code'])->with(['submitter', 'comfirmor', 'specBar'])->first()->toArray();
        $info['quality_level'] = 'B';
        $info['submitter'] = $info['submitter']['username'] ?? '';
        $info['submit_at'] = date('Y-m-d H:i:s', strtotime($info['created_at']));
        $info['comfirmor'] = $info['comfirmor']['username'] ?? '';
        $info['product_sn'] = $info['spec_bar']['product']['product_sn'] ?? '';
        $info['name'] = $info['spec_bar']['product']['name'] ?? '';
        $info['sku'] = $info['spec_bar']['sku'] ?? '';
        $info['spec_one'] = $info['spec_bar']['spec_one'] ?? '';
        $info['spec_two'] = $info['spec_bar']['spec_two'] ?? '';
        $info['spec_three'] = $info['spec_bar']['spec_three'] ?? '';
        unset($info['spec_bar']);

        $logs = WmsOptionLog::where('type', WmsOptionLog::QC)->where('doc_code', $info['qc_code'])->orderBy('id', 'desc')->get();

        return compact('info', 'logs');
    }

    function qcConfirmSave($params)
    {
        WmsQualityConfirmList::where('id', $params['id'])->update([
            'remark' => $params['remark'],
            'admin_user_id' => ADMIN_INFO['user_id'],
        ]);
        return true;
    }

    // 根据质量等级获取质量类型
    static function getQualityType($quality_level)
    {
        return $quality_level == 'A' ? 1 : 2;
    }

    // 根据质量等级获取质量类型
    static function getQualityTypeTxt($quality_level)
    {
        return $quality_level == 'A' ? __('admin.wms.type.normal') : __('admin.wms.type.flaw');
    }

    // 未质检的收货单
    function receiveOrder($params)
    {
        $list = RecvOrder::where('warehouse_code', ADMIN_INFO['current_warehouse'])
            ->where('created_user', ADMIN_INFO['user_id'])
            ->where('doc_status', 2)
            ->whereHas('details', function ($query) {
                $query->where('is_qc', 0)->where('is_cancel', 0);
            })->with('createUser')->get()->toArray();
        foreach ($list as &$item) {
            self::infoFormat($item);
        }
        return [
            'list' => $list,
            'count' => count($list),
        ];
    }

    // 收货单未质检详情
    function receiveOrderDetail($params)
    {
        $info = RecvOrder::where('warehouse_code', ADMIN_INFO['current_warehouse'])
            ->where('doc_status', 2)
            ->where('created_user', ADMIN_INFO['user_id'])
            ->where('id', $params['recv_id'])->select(['id', 'recv_code'])->first();
        $details = $info->details;
        $detail = [];
        foreach ($details as $pro) {
            $temp = [
                'sku' => $pro->product->sku,
                'product_sn' => $pro->product->product->product_sn ?? '',
                // 'name' => $pro->product->product->name ?? '',
                'spec' => $pro->product->spec_one,
                'quality_type' => $pro->is_qc ? $pro->quality_type : '',
                'quality_level' => $pro->is_qc ? $pro->quality_level : '',
            ];
            $key = sprintf('%s_%s', $temp['sku'], $temp['quality_level']);
            $last = $detail[$key] ?? [];
            $temp['recv_num'] = ($last['recv_num'] ?? 0) + 1;
            $detail[$key] = $temp;
        }

        // foreach ($info['details_group'] as $item) {
        //     $detail[] = [
        //         'quality_type' => $item['quality_type'],
        //         'quality_level' => $item['quality_level'],
        //         'spec' => $item['spec'] ?? '',
        //         'product_sn' => $item['product_sn'] ?? '',
        //         'sku' => $item['sku'] ?? '',
        //         'recv_num' => $item['recv_num'],
        //     ];
        // }
        return [
            'id' => $info['id'],
            'recv_code' => $info['recv_code'],
            'detail' => array_values($detail),
        ];
    }

    function uniqScan($params)
    {
        // 根据唯一码获取sku和品名
        $info = Inventory::where('uniq_code', $params['uniq_code'])->orderBy('id', 'desc')->first();
        if ($info && empty($info->is_qc)) {
            $info =  RecvDetail::where('uniq_code', $params['uniq_code'])->where('is_cancel', 0)->where('created_user', ADMIN_INFO['user_id'])->orderBy('id', 'desc')->first();
        }
        if (!$info) {
            $this->setErrorMsg(__('tips.unqiue_not_exist'));
            return false;
        }
        return [
            'quality_type' => $info['quality_type'],
            'quality_level' => $info['quality_level'],
            'sku' => $info['product']['sku'] ?? '',
            'name' => $info['product']['product']['name'] ?? '',
        ];
    }

    function flawReport($params)
    {
        try {
            $this->_qcSave($params, WmsQualityList::METHOD_ONE_BY_ONE, WmsQualityList::TYPE_STORE_IN, [
                'location_code' => self::$default_location_code,
                'area_name' => '质检暂存区',
            ]);
        } catch (Exception $e) {
            $this->setErrorMsg($e->getMessage());
            return false;
        }
        return true;
    }

    function flawSubmit($params)
    {
        try {
            $this->_qcSave($params, WmsQualityList::METHOD_ONE_BY_ONE, WmsQualityList::TYPE_WAREHOUSE, [
                'location_code' => self::$default_location_code,
                'area_name' => '质检暂存区',
            ]);
            return true;
        } catch (Exception $e) {
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }
}
