<?php

namespace App\Logics\wms;

use App\Logics\BaseLogic;
use App\Models\Admin\V2\Inventory;
use App\Models\Admin\V2\preAllocationDetail;
use App\Models\Admin\V2\preAllocationLists;
use App\Models\Admin\V2\WarehouseLocation;
use App\Models\Admin\V2\WmsOptionLog;
use App\Models\Admin\V2\WmsPutawayList;
use App\Models\Admin\V2\WmsStockLog;
use App\Models\Admin\V2\WmsStockMoveDetail;
use App\Models\Admin\V2\WmsStockMoveItem;
use App\Models\Admin\V2\WmsStockMoveList;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * 移位单
 */
class Move extends BaseLogic
{
    function save($params)
    {
        if ($params['id'] ?? 0) {
            $move = WmsStockMoveList::find($params['id']);
            if (!$move) {
                $this->setErrorMsg(__('tips.doc_not_exists'));
                return false;
            }
            if ($move->status != WmsStockMoveList::STASH) {
                $this->setErrorMsg(__('tips.doc_status_not_edit'));
                return false;
            }
        }
        // 指定位置码下的商品数量
        // 区域码、位置码、唯一码、质量等级 数据校验

        try {
            DB::beginTransaction();
            if ($params['id'] ?? 0) {
                // 修改
                $move = WmsStockMoveList::find($params['id']);
                $update = [
                    'warehouse_code' => $params['warehouse_code'],
                    'updated_user' => ADMIN_INFO['user_id'],
                ];
                if ($params['order_user'] ?? 0) $update['order_user'] = $params['order_user'];
                if ($params['remark'] ?? '') $update['remark'] = $params['remark'];
                $move->update($update);
                WmsStockMoveDetail::where('origin_code', $move->code)->update(['status' => 0]);
                WmsOptionLog::add(WmsOptionLog::MOVE, $move->code, '修改', '修改移位单', []);
            } else {
                // 新增
                $move = WmsStockMoveList::create([
                    "type" => 1,
                    "code" => WmsStockMoveList::code(),
                    'warehouse_code' => $params['warehouse_code'],
                    'order_user' => $params['order_user'] ?? 0,
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                    'created_user' => ADMIN_INFO['user_id'],
                    'updated_user' => ADMIN_INFO['user_id'],
                ]);
                WmsOptionLog::add(WmsOptionLog::MOVE, $move->code, '新增', '新增移位单', []);
            }
            $num = 0;
            foreach ($params['skus'] as $sku) {
                WmsStockMoveDetail::updateOrCreate([
                    'origin_code' => $move->code,
                    'bar_code' => $sku['bar_code'],
                    'location_code' => $sku['location_code'],
                    'quality_level' => $sku['quality_level'],
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                ], [
                    'area_code' => $sku['area_code'],
                    'target_location_code' => $sku['target_location_code'],
                    'target_area_code' => $sku['target_area_code'],
                    'quality_type' => $sku['quality_type'],
                    'quality_level' => $sku['quality_level'],
                    'remark' => $sku['remark'] ?? '',
                    'admin_user_id' => ADMIN_INFO['user_id'],
                    'status' => 1,
                    'total' => $sku['total'],
                ]);
                $num += $sku['total'];
            }
            $move->update(['num' => $num,]);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 删除
    function delete($id)
    {
        $move = WmsStockMoveList::find($id);
        if (!$move) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }
        $move->delete();
        WmsOptionLog::add(WmsOptionLog::MOVE, $move->code, '删除', '删除移位单', []);
        return true;
    }

    // 撤回
    function revoke($params)
    {
        $move = WmsStockMoveList::find($params['id']);
        if (!$move) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }
        if ($move->status != WmsStockMoveList::STASH) {
            $this->setErrorMsg(__('tips.doc_not_withdraw'));
            return false;
        }
        $move->update([
            'status' => WmsStockMoveList::CANCEL,
            'updated_user' => ADMIN_INFO['user_id'],
        ]);
        WmsOptionLog::add(WmsOptionLog::MOVE, $move->code, '撤回', '撤回移位单', []);
        return true;
    }

    // 提交
    function submit($params)
    {
        $move = WmsStockMoveList::find($params['id']);
        if (!$move) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }
        if ($move->status != WmsStockMoveList::STASH) {
            $this->setErrorMsg(__('tips.order_not_submit'));
            return false;
        }
        $move->update([
            'status' => WmsStockMoveList::WAIT_AUDIT,
            'updated_user' => ADMIN_INFO['user_id'],
        ]);
        WmsOptionLog::add(WmsOptionLog::MOVE, $move->code, '提交', '提交移位单', []);
        return true;
    }

    // 审核
    function audit($params)
    {
        $move = WmsStockMoveList::find($params['id']);
        if (!$move) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }
        if ($move->status != WmsStockMoveList::WAIT_AUDIT) {
            $this->setErrorMsg(__('tips.doc_not_examine'));
            return false;
        }
        if ($params['status'] == WmsStockMoveList::PASS) {
            // 审核通过
            $move->update([
                'status' => WmsStockMoveList::PASS,
                'updated_user' => ADMIN_INFO['user_id'],
            ]);
            WmsOptionLog::add(WmsOptionLog::MOVE, $move->code, '审核', '移位单审核通过', []);
        }

        if ($params['status'] == WmsStockMoveList::REJECT) {
            // 审核未通过
            $move->update([
                'status' => WmsStockMoveList::REJECT,
                'updated_user' => ADMIN_INFO['user_id'],
                'remark' => $params['remark'] ?? '',
            ]);
            WmsOptionLog::add(WmsOptionLog::MOVE, $move->code, '审核', '移位单审核拒绝', []);
            return true;
        }
    }

    // 取消
    function cancel($params)
    {
        $move = WmsStockMoveList::find($params['id']);
        if (!$move) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }
        if ($move->status != WmsStockMoveList::PASS || $move->down_status > 0 || $move->shelf_status > 0) {
            $this->setErrorMsg(__('tips.status_deny_option'));
            return false;
        }
        $move->update([
            'status' => WmsStockMoveList::CANCEL,
            'updated_user' => ADMIN_INFO['user_id'],
        ]);
        WmsOptionLog::add(WmsOptionLog::MOVE, $move->code, '取消', '取消移位单', []);
        return true;
    }

    // 搜索
    function search($params, $export = false)
    {
        $model = new WmsStockMoveList();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            return $model->with(['warehouse', 'orderUser'])->orderBy('created_at', 'desc');
        });
        foreach ($list['data'] as &$item) {
            if ($export) {
                self::exportFormat($item, ['num', 'down_num', 'down_diff', 'shelf_num', 'shelf_diff']);
            }
        }
        return $list;
    }

    // 详情
    function info($params)
    {
        $info = [];
        $takedown = [];
        $shelf = [];
        $takedown_total = [];
        $shelf_total = [];
        $logs = [];
        if(!empty($params['id']))$info = WmsStockMoveList::with(['warehouse', 'orderUser', 'createUser', 'adminUser', 'items', 'items.specBar', 'items.supplier', 'items.takedownUser', 'items.shelfUser'])->find($params['id']);
        if(!empty($params['code']))$info = WmsStockMoveList::with(['warehouse', 'orderUser', 'createUser', 'adminUser', 'items', 'items.specBar', 'items.supplier', 'items.takedownUser', 'items.shelfUser'])->where('code',$params['code'])->first();
        if ($info) {
            $info = $info->toArray();
            self::infoFormat($info);

            $pre = preAllocationLists::where('type', 2)->where('request_code', $info['code'])->first();
            $putaway = WmsPutawayList::where('type', WmsPutawayList::TYPE_MOVE)->where('origin_code', $info['code'])->first();
            foreach ($info['items'] as &$item) {
                $key = sprintf('%s_%s_%s_%s', $item['bar_code'], $item['sup_id'], $item['quality_level'], $item['location_code']);
                self::sepBarFormat($item);
                $item['takedown_user'] = $item['takedown_user']['username'] ?? '';
                $item['shelf_user'] = $item['shelf_user']['username'] ?? '';
                if ($pre) {
                    $tmp = $item;
                    $tmp['pre_alloction_code'] = $pre->pre_alloction_code;
                    $tmp['pre_status_txt'] = $pre->status_txt;
                    $tmp['takedown_num'] = ($takedown[$key]['takedown_num'] ?? 0) + 1;
                    $takedown[$key] = $tmp;
                    $takedown_total['takedown_num'] = ($takedown_total['takedown_num'] ?? 0) + 1;
                }
                if ($putaway) {
                    $tmp = $item;
                    $tmp['putaway_code'] = $putaway->putaway_code;
                    $tmp['putaway_status_txt'] = $putaway->status_txt;
                    $tmp['putaway_num'] = ($shelf[$key]['putaway_num'] ?? 0) + 1;
                    $shelf[$key] = $tmp;
                    $shelf_total['putaway_num'] = ($shelf_total['putaway_num'] ?? 0) + 1;
                }
            }
            unset($info['items']);
            $logs = WmsOptionLog::where('type', WmsOptionLog::MOVE)->where('doc_code', $info['code'])->orderBy('id', 'desc')->get();
            $takedown = array_values($takedown);
            $shelf = array_values($shelf);
        }
        return compact('info', 'takedown', 'takedown_total', 'shelf', 'shelf_total', 'logs');
    }

    // 明细
    function detail($params)
    {
        $details = [];
        $total = [];
        $info = WmsStockMoveList::with(['details', 'details.specBar', 'items', 'items.supplier'])->find($params['id']);
        if ($info) {
            $details = $info->details->toArray();
            $items = $info->items->toArray();
            $arr = [];
            foreach ($items as $item) {
                $key = sprintf('%s_%s_%s_%s', $item['bar_code'], $item['area_code'], $item['location_code'], $item['quality_level']);
                if (!isset($arr[$key])) {
                    $arr[$key] = [
                        'supplier' => [],
                        'new_area_code' => [],
                        'new_location_code' => [],
                    ];
                }
                if ($item['supplier']['name'] ?? '') $arr[$key]['supplier'][] = $item['supplier']['name'];
                if ($item['new_area_code']) $arr[$key]['new_area_code'][] = $item['new_area_code'];
                if ($item['new_location_code']) $arr[$key]['new_location_code'][] = $item['new_location_code'];
            }
            foreach ($details as &$item) {
                $key = sprintf('%s_%s_%s_%s', $item['bar_code'], $item['area_code'], $item['location_code'], $item['quality_level']);
                self::sepBarFormat($item);
                $item['new_location_code'] = implode(',', $arr[$key]['new_location_code'] ?? []);
                $item['supplier'] = implode(',', $arr[$key]['supplier'] ?? []);
                $item['down_diff'] = $item['total'] - $item['down_num'];
                $item['shelf_diff'] = $item['total'] - $item['shelf_num'];
                $total['down_num'] = ($total['down_num'] ?? 0) + $item['down_num'];
                $total['down_diff'] = ($total['down_diff'] ?? 0) + $item['down_diff'];
                $total['shelf_num'] = ($total['shelf_num'] ?? 0) + $item['shelf_num'];
                $total['shelf_diff'] = ($total['shelf_diff'] ?? 0) + $item['shelf_diff'];
            }
        }
        return compact('details', 'total');
    }

    // 下架
    function takedown($params)
    {
        $code = $params['code'];
        $good = Inventory::where('uniq_code', $params['uniq_code'])->where('in_wh_status', Inventory::PUTAWAY)->orderBy('id', 'desc')->first();
        if (!$good) {
            $this->setErrorMsg(__('tips.unique_code_error'));
            return false;
        }
        $move = WmsStockMoveList::where('code', $code)->where('status', WmsStockMoveList::PASS)->first();
        if (!$move) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }
        $detail = WmsStockMoveDetail::where('origin_code', $code)
            ->where('area_code', $good->area_code)
            ->where('location_code', $good->location_code)
            ->where('quality_level', $good->quality_level)
            ->where('status', 1)
            ->first();
        if (!$detail) {
            $this->setErrorMsg(__('admin.wms.move.uniq_code_error'));
            return false;
        }
        $find = WmsStockMoveItem::where('origin_code', $move->code)->where('uniq_code', $params['uniq_code'])->first();
        if ($find) {
            $this->setErrorMsg(__('tips.unique_code_scan_repeat'));
            return false;
        }
        try {
            DB::beginTransaction();

            if ($move->down_status == WmsStockMoveList::DOWN_WAIT)
                $move->update(['down_status' => WmsStockMoveList::DOWNING, 'start_at' => date('Y-m-d H:i:s')]);

            $detail->update([
                'down_num' => $detail->down_num + 1,
            ]);
            WmsStockMoveItem::create([
                'origin_code' => $move->code,
                'uniq_code' => $params['uniq_code'],
                'location_code' => $good->location_code,
                'area_code' => $good->area_code,
                'bar_code' => $good->bar_code,
                'sup_id' => $good->sup_id,
                'batch_no' => $good->lot_num,
                'quality_level' => $good->quality_level,
                'target_location_code' => $detail->target_location_code,
                'target_area_code' => $detail->target_area_code,
                'down_at' => date('Y-m-d H:i:s'),
                'down_user_id' => ADMIN_INFO['user_id'],
                'status' => 1,
                'tenant_id' => ADMIN_INFO['tenant_id'],
                'admin_user_id' => ADMIN_INFO['user_id'],
            ]);
            $good->update(['in_wh_status' => Inventory::MOVING]);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 确认下架
    function takedownConfirm($params)
    {
        $code = $params['code'];
        $move = WmsStockMoveList::where('code', $code)->where('status', WmsStockMoveList::PASS)->first();
        if (!$move) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }
        try {
            DB::beginTransaction();
            $items = WmsStockMoveItem::where('origin_code', $code)->where('status', 1)->get();
            if ($items) {
                $details = [];
                $num = 0;
                $uniq_codes = [];
                foreach ($items as $item) {
                    $item->update(['status' => 2, 'admin_user_id' => ADMIN_INFO['user_id'],]);
                    $uniq_codes[] = $item->uniq_code;
                    $details[] = [
                        'request_code' => $move->code,
                        'bar_code' => $item->bar_code,
                        'sup_id' => $item->sup_id,
                        'batch_no' => $item->batch_no,
                        'quality_level' => $item->quality_level,
                        'uniq_code' => $item->uniq_code,
                        'warehouse_code' => $move->warehouse_code,
                        'location_code' => $item->location_code,
                        // 'count' => 1,
                        'pre_inv_id' => 0,
                        'tenant_id' => ADMIN_INFO['tenant_id'],
                    ];
                    $num++;
                }

                $move->update([
                    'down_status' => WmsStockMoveList::DOWN,
                    'down_num' => $num,
                    'down_diff' => $move->num - $num,
                    'down_user_id' => ADMIN_INFO['user_id'],
                ]);

                // 生成已配货的配货订单
                $pre = preAllocationLists::create([
                    'pre_alloction_code' => (new preAllocationLists())->getErpCode('PHDD'),
                    'type' => preAllocationLists::TYPE_MOVE,
                    'origin_type' => 4,
                    'request_code' => $move->code,
                    'startegy_code' => '',
                    'status' => 1,
                    'allocation_status' => 3,
                    'state' => 1,
                    'sku_num' => count(array_unique(array_column($details, 'bar_code'))),
                    'pre_num' => 0,
                    'actual_num' => $num,
                    'received_num' => $num,
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                    'create_user_id' => ADMIN_INFO['user_id'],
                    'admin_user_id' => ADMIN_INFO['user_id'],
                ]);
                foreach ($details as $detail) {
                    $detail['pre_alloction_code'] = $pre->pre_alloction_code;
                    $detail['startegy_code'] = '';
                    $detail['alloction_status'] = 7;
                    $detail['actual_num'] = 1;
                    $detail['allocated_at'] = date('Y-m-d H:i:s');
                    $detail['admin_user_id'] = ADMIN_INFO['user_id'];
                    preAllocationDetail::create($detail);
                }
                // 确认下架后唯一码库区位置变为下架暂存区
                if ($uniq_codes) {
                    Inventory::whereIn('uniq_code', $uniq_codes)->where('in_wh_status', Inventory::MOVING)->update(['area_code' => 'XJZCQ001']);
                }
            }
            WmsOptionLog::add(WmsOptionLog::MOVE, $move->code, '确认下架', '移位单确认下架', []);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 上架
    function shelf($params)
    {
        $code = $params['code'];
        $move = WmsStockMoveList::where('code', $code)->where('status', WmsStockMoveList::PASS)->first();
        if (!$move) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }
        $item = WmsStockMoveItem::where('origin_code', $code)->where('uniq_code', $params['uniq_code'])->first();
        if (!$item || $item->status != WmsStockMoveItem::WAIT_PUTAWAY) {
            $this->setErrorMsg(__('admin.wms.move.uniq_code.status_error'));
            return false;
        }
        if (in_array($item['location_code'], ['SHZCQ001', 'ZJZCQ001', 'XJZCQ001'])) {
            $this->setErrorMsg(__('admin.wms.move.area_error'));
            return false;
        }
        $area = WarehouseLocation::where('warehouse_code', $move->warehouse_code)->where('location_code', $params['location_code'])->where('status', 1)->first();
        if (!$area) {
            $this->setErrorMsg(__('admin.wms.move.area.status_error'));
            return false;
        }
        $where = [
            'origin_code' => $code,
            'area_code' => $item->area_code,
            'location_code' => $item->location_code,
            'quality_level' => $item->quality_level,
            'status' => 1,
        ];
        $detail = WmsStockMoveDetail::where($where)->first();
        if (!$detail) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }

        try {
            DB::beginTransaction();

            if ($move->shelf_status == WmsStockMoveList::SHELF_WAIT)
                $move->update(['shelf_status' => WmsStockMoveList::SHELFING]);

            $detail->update(['shelf_num' => $detail->shelf_num + 1,]);

            $item->update([
                'status' => WmsStockMoveItem::PUTAWAY_ING,
                'new_area_code' => $area->area_code,
                'new_location_code' => $params['location_code'],
                'shelf_at' => date('Y-m-d H:i:s'),
                'shelf_user_id' => ADMIN_INFO['user_id'],
            ]);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 一键上架
    function shelfConfirm($params)
    {
        $code = $params['code'];
        $move = WmsStockMoveList::where('code', $code)->where('status', WmsStockMoveList::PASS)->first();
        if (!$move) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }

        try {
            DB::beginTransaction();
            $items = WmsStockMoveItem::where('origin_code', $code)->where('status', WmsStockMoveItem::PUTAWAY_ING)->get();
            if ($items) {
                $details = [];
                $num = 0;
                foreach ($items as $item) {
                    $item->update([
                        'status' => WmsStockMoveItem::PUTAWAY,
                        'admin_user_id' => ADMIN_INFO['user_id'],
                    ]);
                    $num++;
                    $details[] = [
                        'type' => 2,
                        'bar_code' => $item->bar_code,
                        'uniq_code' => $item->uniq_code,
                        'area_code' => $item->new_area_code,
                        'location_code' => $item->new_location_code,
                        'quality_type' => QualityControl::getQualityType($item->quality_level),
                        'quality_level' => $item->quality_level,
                        'tenant_id' => ADMIN_INFO['tenant_id'],
                        'admin_user_id' => ADMIN_INFO['user_id'],
                    ];

                    $good = Inventory::where('uniq_code', $item->uniq_code)->where('in_wh_status', Inventory::MOVING)->orderBy('id', 'desc')->first();
                    $good->update([
                        'in_wh_status' => Inventory::PUTAWAY,
                        'area_code' => $item->new_area_code,
                        'location_code' => $item->new_location_code,
                    ]);
                }

                $move->update([
                    'shelf_status' => WmsStockMoveList::SHELFED,
                    'shelf_num' => $num,
                    'shelf_diff' => $move->num - $num,
                    'shelf_user_id' => ADMIN_INFO['user_id'],
                    'end_at' => date('Y-m-d H:i:s'),
                ]);

                // 上架单
                Putaway::createEndList([
                    'type' => WmsPutawayList::TYPE_MOVE,
                    'status' => 1,
                    'putaway_status' => 1,
                    'total_num' => $num,
                    'warehouse_code' => $move->warehouse_code,
                    'warehouse_name' => Warehouse::name($move->warehouse_code),
                    'create_user_id' => ADMIN_INFO['user_id'],
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                    'submitter_id' => ADMIN_INFO['user_id'],
                    'admin_user_id' => ADMIN_INFO['user_id'],
                    'completed_at' => date('Y-m-d H:i:s'),
                    'origin_code' => $move->code,
                ], $details);
            }
            WmsOptionLog::add(WmsOptionLog::MOVE, $move->code, '上架', '移位单一键上架', []);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }


    // 中转移位 - 唯一码下架
    function indirectTakedownByUniqCode($params)
    {
        $move = null;
        if ($params['code'] ?? '') {
            $move = WmsStockMoveList::where('code', $params['code'])->where(['type' => 2, 'status' => 2])->first();
            if (!$move) {
                $this->setErrorMsg(__('tips.doc_not_exists'));
                return false;
            }
            if ($move->down_status == 2) {
                $this->setErrorMsg(__('tips.option_repeat'));
                return false;
            }
        }
        if (false == $this->productTypeVerify($params)) return false;

        $where = self::filterEmptyData($params, ['warehouse_code', 'uniq_code', 'location_code', 'bar_code']);
        $find = Inventory::where($where)->whereRaw('(in_wh_status !=3 or inv_status!=5)')->first();
        if ($find) {
            $this->setErrorMsg(__('tips.good_stock_status_error'));
            return false;
        }
        $stock = Inventory::where($where)->where(['in_wh_status' => 3, 'inv_status' => 5])->first();
        $uniq_codes = [];
        // 普通商品，先根据条码获取唯一码信息
        if ($params['type'] == 2) {
            $uniq_codes = Inventory::where($where)->where(['in_wh_status' => 3, 'inv_status' => 5])->pluck('uniq_code');
            if (count($uniq_codes) == 0) {
                $this->setErrorMsg(__('tips.location_empty_good'));
                return false;
            }
        }

        $sku = $stock ? $stock->product : null;
        $p = $sku ? $sku->product : null;
        $product = [
            'uniq_code' => $params['uniq_code'] ?? '',
            'bar_code' => $stock->bar_code,
            'quality_level' => $stock->quality_level,
            'sku' => $sku ? $sku->sku : '',
            'name' => $p ? $p->name : '',
        ];

        try {
            DB::beginTransaction();

            if (!$move) $move = WmsStockMoveList::getActiveOrder($params, 2);
            if ($move->down_status == 2) {
                $this->setErrorMsg(__('tips.move_need_shelf'));
                DB::rollBack();
                return false;
            }
            if ($params['uniq_code'] ?? '') $this->_appendUniqCode($move, $params['uniq_code'], $params);
            foreach ($uniq_codes as $uniq_code) {
                $this->_appendUniqCode($move, $uniq_code);
            }

            $move->update(['num' => $move->details->sum('total'),]);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }

        $params['code'] = $move->code;
        $data =  $this->groupDetail($params, 1);
        $data['location_code_num'] = count($data);
        $data['takedown_total'] = array_sum(array_column($data, 'takedown_num'));
        $data['code'] = $move->code;
        $data['product'] = $product;
        return $data;
    }

    // 移位单中新增下架的唯一码
    private function _appendUniqCode($move, $uniq_code, $params = [])
    {
        $where = self::filterEmptyData($params, ['warehouse_code']);
        $inventory = Inventory::where($where)->where('uniq_code', $uniq_code)->where('in_wh_status', 3)->where('inv_status', 5)->lockForUpdate()->first();
        if (!$inventory) {
            throw new Exception(__('tips.good_stock_get_fail'));
        }

        $update = self::filterEmptyData($params, ['target_location_code', 'target_area_code']);
        $update1 = [
            'quality_type' => QualityControl::getQualityType($inventory->quality_level),
            'admin_user_id' => ADMIN_INFO['user_id'],
        ];
        $detail = WmsStockMoveDetail::updateOrCreate([
            'origin_code' => $move->code,
            'bar_code' => $inventory->bar_code,
            'location_code' => $inventory->location_code,
            'area_code' => $inventory->area_code, //
            'quality_level' => $inventory->quality_level,
            'tenant_id' => ADMIN_INFO['tenant_id'],
            'status' => 1
        ], array_merge($update, $update1));

        $update2 = [
            'bar_code' => $inventory->bar_code,
            'sup_id' => $inventory->sup_id,
            'quality_type' => QualityControl::getQualityType($inventory->quality_level),
            'quality_level' => $inventory->quality_level,
            'batch_no' => $inventory->lot_num, //
            'location_code' => $inventory->location_code,
            'area_code' => $inventory->area_code, //
            'down_user_id' => ADMIN_INFO['user_id'],
            'admin_user_id' => ADMIN_INFO['user_id'],
            'status' => 1,
            'down_at' => date('Y-m-d H:i:s'),
        ];

        WmsStockMoveItem::updateOrCreate([
            'origin_code' => $move->code,
            'uniq_code' => $inventory->uniq_code,
            'tenant_id' => ADMIN_INFO['tenant_id'],
        ], array_merge($update, $update2));
        // $inventory->update(['in_wh_status' => Inventory::MOVING]);
        Inventory::invStatusUpdate($inventory->uniq_code, Inventory::MOVING);
        $detail->update(['total' => $detail->items()->count(), 'down_num' => $detail->items(1)->count()]);
    }

    // 中转移位 - 位置码下架
    function indirectTakedownByLocationCode($params)
    {
        $where = self::filterEmptyData($params, ['warehouse_code', 'code']);
        $move = WmsStockMoveList::where($where)->where('status', 2)->where('down_status', 1)->first();
        if (!$move) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }

        $where = self::filterEmptyData($params, ['warehouse_code', 'location_code']);
        $find = Inventory::where($where)->whereNotIn('in_wh_status', [0, 4, 7])->whereRaw('(in_wh_status !=3 or inv_status!=5)')->first();
        if ($find) {
            $this->setErrorMsg(__('tips.has_wait_confirm'));
            return false;
        }

        $uniq_codes = Inventory::where($where)->whereNotIn('in_wh_status', [0, 4, 7])->where('in_wh_status', 3)->where('inv_status', 5)->pluck('uniq_code');

        try {
            DB::beginTransaction();
            if (!$move) {
                $move = WmsStockMoveList::getActiveOrder($params, 2);
            }
            foreach ($uniq_codes as $uniq_code) {
                $this->_appendUniqCode($move, $uniq_code, $params);
            }
            $move->update(['num' => $move->details->sum('total'),]);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }

        $params['code'] = $move->code;
        $data =  $this->groupDetail($params, 1);
        $data['location_code_num'] = count($data);
        $data['takedown_total'] = array_sum(array_column($data, 'takedown_num'));
        $data['code'] = $move->code;
        return $data;
    }

    /**
     * 商品明细
     *
     * @param array $params
     * @param integer $type 1-下架商品明细 2-上架商品明细
     */
    function groupDetail($params, $type = 1)
    {
        // type 1-下架商品明细 2-上架商品明细
        $where = self::filterEmptyData($params, ['warehouse_code', 'code']);
        $where['status'] = 2;
        if ($type == 1) {
            $move = WmsStockMoveList::where($where)->whereIn('down_status', [0, 1])->with(['details', 'details.specBar'])->first();
        }
        if ($type == 2) {
            $move = WmsStockMoveList::where($where)->where('down_status', 2)->whereIn('shelf_status', [0, 1, 2])
                ->with(['details' => function ($query) {
                    $query->where('shelf_num', '>', 0);
                }, 'details.specBar'])->first();
        }

        if (!$move) return [];

        $items = $move->details->toArray();
        $tmp = [];
        foreach ($items as $item) {
            self::sepBarFormat($item);
            $key = sprintf('%s_%s', $item['location_code'], $item['target_location_code']);
            $tmp[$key][] = [
                'bar_code' => $item['bar_code'],
                'down_num' => $item['down_num'],
                'shelf_num' => $item['shelf_num'],
                'quality_type' => $item['quality_type'],
                'quality_level' => $item['quality_level'],
                'sku' => $item['sku'] ?? '',
                'name' => $item['name'] ?? '',
                'img' => $item['img'] ?? '',
            ];
        }
        $data = [];
        foreach ($tmp as $key => $skus) {
            list($location_code, $target_location_code) = explode('_', $key);
            $data['detail'][] = [
                'location_code' => $location_code,
                'target_location_code' => $target_location_code,
                'takedown_num' => array_sum(array_column($skus, 'down_num')),
                'shelf_num' => array_sum(array_column($skus, 'shelf_num')),
                'skus' => $skus,
            ];
        }
        return $data;
    }

    // 中转移位 - 确认下架
    function indirectTakedownConfirm($params)
    {
        $where = self::filterEmptyData($params, ['warehouse_code', 'code']);
        $move = WmsStockMoveList::where($where)->where('status', 2)->where('down_status', 1)->with(['items' => function ($query) {
            $query->where('status', 1);
        }])->whereIn('down_status', [0, 1])->where('type', 2)->first();

        try {
            DB::beginTransaction();
            $this->_takedownConfirm($move);
            WmsOptionLog::add(WmsOptionLog::MOVE, $move->code, '确认下架', '移位单确认下架', []);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }

        $data = $this->groupDetail($params, 2);
        $data['takedown_total'] = $move->down_num;
        $data['shelf_total'] = ($data['detail'] ?? []) ? array_sum(array_column($data['detail'], 'shelf_num')) : 0;
        $data['code'] = $move->code;
        return $data;
    }

    // 确认下架
    private function _takedownConfirm($move)
    {
        $items = $move->items;
        $details = [];
        $num = 0;
        $uniq_codes = [];
        $pre_code = preAllocationLists::getErpCode('PHDD');
        foreach ($items as $item) {
            $item->update(['status' => 2, 'admin_user_id' => ADMIN_INFO['user_id'],]);
            $uniq_codes[] = $item->uniq_code;
            $details[] = [
                'request_code' => $move->code,
                'bar_code' => $item->bar_code,
                'sup_id' => $item->sup_id,
                'batch_no' => $item->batch_no,
                'quality_level' => $item->quality_level,
                'uniq_code' => $item->uniq_code,
                'warehouse_code' => $move->warehouse_code,
                'location_code' => $item->location_code,
                // 'count' => 1,
                'pre_inv_id' => 0,
                'tenant_id' => ADMIN_INFO['tenant_id'],
            ];
            $num++;
            //库存状态更新;
            Inventory::where('uniq_code', $item->uniq_code)->where('in_wh_status', Inventory::MOVING)->update(['area_code' => 'XJZCQ001', 'location_code' => '']);
            //快速移位库存流水
            if ($move->type == 3) WmsStockLog::add(WmsStockLog::ORDER_QUICK_MOVE, $item->uniq_code, $move->code, ['origin_value' => $item->location_code, 'move' => $move]);
            //中转移位流水
            if ($move->type == 2) WmsStockLog::add(WmsStockLog::ORDER_MOVE_ALLOCATE, $item->uniq_code, $pre_code, ['origin_value' => $item->location_code, 'move' => $move]);
        }

        $move->update([
            'down_status' => WmsStockMoveList::DOWN,
            'down_num' => $num,
            'down_diff' => $move->num - $num,
            'down_user_id' => ADMIN_INFO['user_id'],
        ]);

        // 生成已配货的配货订单
        $pre = preAllocationLists::create([
            'pre_alloction_code' => $pre_code,
            'type' => preAllocationLists::TYPE_MOVE,
            'origin_type' => 4,
            'request_code' => $move->code,
            'startegy_code' => '',
            'status' => 1,
            'allocation_status' => 3,
            'warehouse_code' => $move->warehouse_code,
            'state' => 1,
            'sku_num' => count(array_unique(array_column($details, 'bar_code'))),
            'pre_num' => $num,
            'actual_num' => $num,
            'received_num' => $num,
            'tenant_id' => ADMIN_INFO['tenant_id'],
            'create_user_id' => ADMIN_INFO['user_id'],
            'admin_user_id' => ADMIN_INFO['user_id'],
        ]);
        foreach ($details as $detail) {
            $detail['pre_alloction_code'] = $pre->pre_alloction_code;
            $detail['startegy_code'] = '';
            $detail['alloction_status'] = 7;
            $detail['actual_num'] = 1;
            $detail['allocated_at'] = date('Y-m-d H:i:s');
            $detail['admin_user_id'] = ADMIN_INFO['user_id'];
            preAllocationDetail::create($detail);
        }
        // 确认下架后唯一码库区位置变为下架暂存区
        // if ($uniq_codes) {
        //     Inventory::whereIn('uniq_code', $uniq_codes)->where('in_wh_status', Inventory::MOVING)->update(['area_code' => 'XJZCQ001']);
        // }
        //移位下架流水
        // foreach ($uniq_codes as $uniq_code) {
        //     $inv = Inventory::where('uniq_code', $uniq_code)->where('in_wh_status', Inventory::MOVING)->first();
        //     $location_code = $inv->location_code;
        //     $inv->update(['area_code' => 'XJZCQ001']);
        //       //快速移位库存流水
        //     if($move->type == 3)WmsStockLog::add(WmsStockLog::ORDER_QUICK_MOVE, $uniq_code, $move->code, ['origin_value' => $location_code,'move'=> $move]);
        //     //中转移位流水
        //     if($move->type == 2) WmsStockLog::add(WmsStockLog::ORDER_MOVE_ALLOCATE, $uniq_code,$pre_code, ['origin_value' => $location_code,'move'=> $move]);
        // }
    }



    // 中转移位 - 唯一码上架
    function indirectShelfByUniqCode($params)
    {
        if (in_array($params['location_code'], ['SHZCQ001', 'ZJZCQ001'])) {
            $this->setErrorMsg(__('admin.wms.move.area_error'));
            return false;
        }

        $code = $params['code'];
        $where = self::filterEmptyData($params, ['warehouse_code', 'code']);
        $move = WmsStockMoveList::where($where)->where('status', 2)->where('down_status', 2)->first();
        if (!$move) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }
        if (false == $this->productTypeVerify($params)) return false;

        // $item = WmsStockMoveItem::where('origin_code', $code)->where('uniq_code', $params['uniq_code'])->first();
        // if (!$item || $item->status != WmsStockMoveItem::WAIT_PUTAWAY) {
        //     $this->setErrorMsg(__('admin.wms.move.uniq_code.status_error'));
        //     return false;
        // }

        // $where = [
        //     'origin_code' => $code,
        //     'area_code' => $item->area_code,
        //     'location_code' => $item->location_code,
        //     'quality_level' => $item->quality_level,
        //     'status' => 1,
        // ];
        // $detail = WmsStockMoveDetail::where($where)->first();
        // if (!$detail) {
        //     $this->setErrorMsg('未找到迁移信息');
        //     return false;
        // }

        try {
            DB::beginTransaction();
            $items = null;
            $model = WmsStockMoveItem::where('origin_code', $code)->where('status', 2);
            if ($params['type'] == 1) {
                $items = $model->where('uniq_code', $params['uniq_code'])->get();
            } elseif ($params['type'] == 2) {
                $items = $model->where(['bar_code' => $params['bar_code'], 'location_code' => $params['location_code']])->get();
            }

            if (!($items->count())) throw new Exception(__('admin.wms.move.uniq_code.status_error'));

            $this->_shelf($params, $move, $items);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
        $data =  $this->groupDetail($params, 2);
        $data['takedown_total'] = $move->down_num;
        $data['shelf_total'] = ($data['detail'] ?? []) ? array_sum(array_column($data['detail'], 'shelf_num')) : 0;
        $data['code'] = $move->code;
        return $data;
    }



    // 中转移位 - 位置码上架
    function indirectShelfByLocationCode($params)
    {
        if (in_array($params['location_code'], ['SHZCQ001', 'ZJZCQ001'])) {
            $this->setErrorMsg(__('admin.wms.move.area_error'));
            return false;
        }

        // 找到所有待上架商品
        $where = self::filterEmptyData($params, ['warehouse_code', 'code']);
        $move = WmsStockMoveList::where($where)->where('status', 2)->where('down_status', 2)->with(['items' => function ($query) {
            $query->where('status', 2);
        }])->first();
        if (!$move) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }
        $items = $move->items;
        try {
            DB::beginTransaction();
            $this->_shelf($params, $move, $items);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }

        $data =  $this->groupDetail($params, 2);
        $data['takedown_total'] = $move->down_num;
        $data['shelf_total'] = array_sum(array_column($data['detail'] ?? [], 'shelf_num'));
        $data['code'] = $move->code;
        return $data;
    }

    // 上架
    private function _shelf($params, $move, $items)
    {
        $area = WarehouseLocation::where('warehouse_code', $params['warehouse_code'])->where('location_code', $params['location_code'])->where('status', 1)->first();
        if (!$area) {
            throw new Exception(__('admin.wms.move.area.status_error'));
        }

        if ($move->shelf_status == WmsStockMoveList::SHELF_WAIT)
            $move->update(['shelf_status' => WmsStockMoveList::SHELFING]);

        foreach ($items as $item) {
            $item->update([
                'status' => WmsStockMoveItem::PUTAWAY,
                'new_area_code' => $area->area_code,
                'new_location_code' => $params['location_code'],
                'shelf_at' => date('Y-m-d H:i:s'),
                'shelf_user_id' => ADMIN_INFO['user_id'],
            ]);
        }
        $details = $move->details;
        foreach ($details as $detail) {
            $detail->update(['shelf_num' => $detail->items(3)->count(),]);
        }

        $shef_num = $move->details->sum('shelf_num');
        $update = ['shelf_num' => $shef_num, 'shelf_diff' => $move->num - $shef_num, 'updated_user' => ADMIN_INFO['user_id'],];
        if ($shef_num == $move->down_num) {
            $update['end_at'] = date('Y-m-d H:i:s');
            $update['shelf_user_id'] = ADMIN_INFO['user_id'];
            $update['shelf_status'] = 2;
        }
        $move->update($update);

        $detail3  = [];
        if ($move->shelf_status == 2) {
            $putaway_code = WmsPutawayList::code();
            $items = WmsStockMoveItem::where(['origin_code' => $move->code])->where('status', '>', 0)->get();
            foreach ($items as $item) {
                $detail3[] = [
                    'type' => 2,
                    'bar_code' => $item->bar_code,
                    'uniq_code' => $item->uniq_code,
                    'area_code' => $item->new_area_code,
                    'location_code' => $item->new_location_code,
                    'quality_type' => QualityControl::getQualityType($item->quality_level),
                    'quality_level' => $item->quality_level,
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                    'admin_user_id' => ADMIN_INFO['user_id'],
                ];
                Inventory::invStatusUpdate($item->uniq_code, Inventory::PUTAWAY, 1, ['area_code' => $item->new_area_code, 'location_code' => $item->new_location_code]);
                //移位上架库存流水
                WmsStockLog::add(WmsStockLog::ORDER_MOVE_UP, $item->uniq_code, $move->code, ['move' => $move, 'origin_value' => 'XJZCQ001']);
            }

            // 上架单
            Putaway::createEndList([
                'putaway_code' => $putaway_code,
                'type' => WmsPutawayList::TYPE_MOVE,
                'status' => 1,
                'putaway_status' => 1,
                'total_num' => count($items),
                'warehouse_code' => $move->warehouse_code,
                'warehouse_name' => Warehouse::name($move->warehouse_code),
                'create_user_id' => ADMIN_INFO['user_id'],
                'tenant_id' => ADMIN_INFO['tenant_id'],
                'submitter_id' => ADMIN_INFO['user_id'],
                'admin_user_id' => ADMIN_INFO['user_id'],
                'completed_at' => date('Y-m-d H:i:s'),
                'origin_code' => $move->code,
            ], $detail3);
        }
    }

    // 待移位任务
    function pdaSearch($params, $export = false)
    {
        $params['status'] = 2;
        $where = self::filterEmptyData($params, ['warehouse_code', 'code', 'status', 'type']);
        $list = WmsStockMoveList::where($where)->whereIn('shelf_status', [0, 1])->with(['downUser', 'shelfUser'])->get()->toArray();
        $data = [];
        foreach ($list as $item) {
            $data[] = [
                'code' => $item['code'],
                'show_status' => $item['show_status'],
                'show_status_txt' => $item['show_status_txt'],
                'takedown_user' => $item['down_user']['username'] ?? '',
                'shelf_user' => $item['shelf_user']['username'] ?? '',
                'down_user_id' => $item['down_user_id'] ?? 0,
                'shelf_user_id' => $item['shelf_user_id'] ?? 0,
            ];
        }
        return $data;
    }

    // pda展示的移位单详情
    function pdaDetail($params)
    {
        $where = self::filterEmptyData($params, ['warehouse_code', 'code']);
        $where['status'] = 2;
        $move = WmsStockMoveList::where($where)->with(['details', 'details.specBar'])->first();
        if (!$move) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }

        // 待下架/下架中
        if (in_array($move->down_status, [0, 1])) {
            $data =  $this->groupDetail($params, 1);
            $data['location_code_num'] = count($data);
            $data['takedown_total'] = array_sum(array_column($data, 'takedown_num'));
            $data['code'] = $move->code;
            return $data;
        }

        // 已下架完成，未上架
        if ($move->down_status == 2 && $move->shelf_status == 0) {
            $data['takedown_total'] = $move->down_num;
            $data['shelf_total'] = array_sum(array_column($data['detail'] ?? [], 'shelf_num'));
            $data['code'] = $move->code;
            return $data;
        }

        // 上架中/上架完成
        if (in_array($move->shelf_status, [1, 2])) {
            $data =  $this->groupDetail($params, 2);
            $data['takedown_total'] = $move->down_num;
            $data['shelf_total'] = array_sum(array_column($data['detail'] ?? [], 'shelf_num'));
            $data['code'] = $move->code;
            return $data;
        }
        return [];
    }

    // 快速移位
    function fastMove($params)
    {
        $params['type'] = 3;
        $where = self::filterEmptyData($params, ['warehouse_code', 'location_code']);
        $find = Inventory::where($where)->whereNotIn('in_wh_status', [0, 4, 7])->whereRaw('(in_wh_status !=3 or inv_status!=5)')->first();
        if ($find) {
            $this->setErrorMsg(__('tips.has_wait_confirm'));
            return false;
        }
        $area = WarehouseLocation::where('warehouse_code', $params['warehouse_code'])->where('location_code', $params['new_location_code'])->where('status', 1)->first();
        if (!$area) {
            $this->setErrorMsg(__('tips.move_location_error'));
            return false;
        }

        unset($params['type']);
        $uniq_codes = Inventory::where($where)->whereNotIn('in_wh_status', [0, 4, 7])->where(['in_wh_status' => 3, 'inv_status' => 5])->pluck('uniq_code');
        if (count($uniq_codes) == 0) {
            $this->setErrorMsg(__('tips.move_location_empty'));
            return false;
        }
        $params['uniq_codes'] = $uniq_codes;

        try {
            DB::beginTransaction();
            $move = WmsStockMoveList::getActiveOrder($params, 3);

            foreach ($uniq_codes as $uniq_code) {
                $this->_appendUniqCode($move, $uniq_code, array_merge($params, [
                    'target_location_code' => $params['new_location_code'],
                    'target_area_code' => $area->area_code,
                ]));
            }
            $move->update(['num' => $move->details->sum('total'),]);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }

        $data = $this->groupDetail($params, 1);
        $data['total'] = array_sum(array_column($data['detail'] ?? [], 'takedown_num'));
        $data['code'] = $move->code;
        return $data;
    }

    // 快速移位 - 确认移位
    function fastConfirm($params)
    {
        $params['type'] = 3;
        $where = self::filterEmptyData($params, ['warehouse_code', 'code', 'type']);
        $move = WmsStockMoveList::where($where)->where('down_status', 1)->with(['items' => function ($query) {
            $query->where('status', 1);
        }])->first();
        if (!$move) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }
        $target_location_codes = $move->items->pluck('target_location_code');
        try {
            DB::beginTransaction();
            // 下架确认
            $this->_takedownConfirm($move);
            // 上架
            foreach ($target_location_codes as $location_code) {
                $items = WmsStockMoveItem::where(['origin_code' => $move->code, 'target_location_code' => $location_code, 'status' => 2])->get();
                $this->_shelf(['location_code' => $location_code, 'warehouse_code' => $params['warehouse_code']], $move, $items);
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
        return true;
    }
}
