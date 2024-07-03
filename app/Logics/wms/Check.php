<?php

namespace App\Logics\wms;

use App\Logics\BaseLogic;
use App\Models\Admin\V2\Inventory;
use App\Models\Admin\V2\ObOrder;
use App\Models\Admin\V2\ProductCategory;
use App\Models\Admin\V2\WmsOptionLog;
use App\Models\Admin\V2\WmsStockCheckDetail;
use App\Models\Admin\V2\WmsStockCheckDifference;
use App\Models\Admin\V2\WmsStockCheckList;
use App\Models\Admin\V2\WmsStockCheckLog;
use App\Models\Admin\V2\WmsStockCheckRequest;
use App\Models\Admin\V2\WmsStockCheckRequestDetail;
use App\Models\Admin\V2\WmsStockMoveList;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 盘点单
 */
class Check extends BaseLogic
{
    function add($params)
    {
        $data = [
            'type' => 1,
            'code' => WmsStockCheckList::code(),
            'request_code' => '',
            'status' => 2,
            'check_status' => 0,
            'check_type' => $params['check_type'] ?? 0, //0-明盘 1-盲盘
            'warehouse_code' => $params['warehouse_code'],
            'check_user_id' => ADMIN_INFO['user_id'],
            'created_user' => ADMIN_INFO['user_id'],
            'updated_user' => ADMIN_INFO['user_id'],
            'tenant_id' => ADMIN_INFO['tenant_id'],
        ];
        if ($params['start_at'] ?? '') {
            $data['start_at'] = $params['start_at'];
        }

        $check = WmsStockCheckList::create($data);
        WmsOptionLog::add(WmsOptionLog::CHECK, $check->code, '新增', '新增', $params);
        return $check;
    }

    function search($params, $export = false)
    {
        $model = new WmsStockCheckList();
        $list = $this->_search($params, $model, $export, function ($model, $params) {

            $where = self::filterEmptyData($params, ['warehouse_code', 'check_user_id']);
            if ($where) $model = $model->where($where);
            if ($params['check_status'] ?? []) $model = $model->whereIN('check_status', $params['check_status']);
            if ($params['code'] ?? '') $model = $model->where('code', 'like', '%' . $params['code'] . '%');

            return $model->with(['warehouse', 'checkUser', 'details'])->orderBy('created_at', 'desc');
        });
        foreach ($list['data'] as &$item) {
            $item['stock_num'] = array_sum(array_column($item['details'], 'stock_num'));
            $item['check_num'] = array_sum(array_column($item['details'], 'check_num'));
            unset($item['details']);
        }
        return $list;
    }

    function delete($id)
    {
        $check = WmsStockCheckList::find($id);
        if (!$check || $check->check_status != WmsStockCheckList::CHECK_WAIT) {
            $this->setErrorMsg(__('tips.order_not_delete'));
            return false;
        }
        $check->delete();
        WmsOptionLog::add(WmsOptionLog::CHECK, $check->code, '删除', '删除盘点单', []);
        return true;
    }

    function info($params)
    {
        $operations = [];
        if (!empty($params['id'])) $info = WmsStockCheckList::with(['warehouse', 'createUser', 'adminUser', 'logs', 'logs.specBar', 'logs.adminUser'])->find($params['id']);
        if (!empty($params['code'])) $info = WmsStockCheckList::with(['warehouse', 'createUser', 'adminUser', 'logs', 'logs.specBar', 'logs.adminUser'])->where('code', $params['code'])->first();
        if (!$info) {
            $this->setErrorMsg(__('response.doc_not_exists'));
            return;
            // $info = [];
            // $operations = [];
            // $logs = [];
            // return compact('info',  'operations', 'logs');
        }
        $info = $info->toArray();
        $id = $info['id'];
        if (!$info['check_user_id']) {
            WmsStockCheckList::where('id', $id)->update([
                'check_user_id' => ADMIN_INFO['user_id'],
                'start_at' => date('Y-m-d H:i:s'),
                'updated_user' => ADMIN_INFO['user_id'],
            ]);
        }

        // 盘点操作记录
        foreach ($info['logs'] as $item) {
            self::sepBarFormat($item);
            self::infoFormat($item);
            $item['num'] = 1;
            $operations[] = $item;
        }
        unset($info['logs']);
        self::infoFormat($info);

        // 操作日志
        $logs = WmsOptionLog::where('type', WmsOptionLog::CHECK)->where('doc_code', $info['code'])->orderBy('id', 'desc')->get();

        return compact('info', 'operations', 'logs');
    }

    function detail($params)
    {
        $total = [
            'stock_num' => 0,
            'check_num' => 0,
            'diff_num' => 0,
        ];
        $detail = [];
        $operations = [];
        if ($params['id'] ?? 0) {
            $info = WmsStockCheckList::with(['details', 'details.specBar'])->find($params['id']);
        } else {
            $where = self::filterEmptyData($params, ['code', 'warehouse_code', 'check_user_id']);
            $info = WmsStockCheckList::with(['details', 'details.specBar'])->where($where)->where(['status' => 2])->whereIn('check_status', [0, 1])->first();
        }
        if ($info) $info = $info->toArray();

        $categories = ProductCategory::get()->keyBy('id')->toArray();
        // Log::info($info['details']);
        // 商品明细及汇总
        foreach ($info['details'] ?? [] as $item) {
            self::sepBarFormat($item);
            $item['diff_num'] = $item['stock_num'] - $item['check_num'];
            $total['stock_num'] +=  $item['stock_num'];
            $total['check_num'] +=  $item['check_num'];
            $total['diff_num'] += $item['diff_num'];
            $item['category_name'] = $categories[$item['category_id']]['parent']['name'] ?? '';
            $detail[] = $item;
        }

        // 按位置码将查询结果进行汇总
        $tmp = [];
        if ($params['group'] ?? false) {
            $tmp2 = [];
            foreach ($detail as $item) {
                $tmp2[$item['location_code']][] = $item;
            }
            $detail = [];
            foreach ($tmp2 as $location_code => $item) {
                $detail[] = [
                    'location_code' => $location_code,
                    'stock_num' => array_sum(array_column($item, 'stock_num')),
                    'check_num' => array_sum(array_column($item, 'check_num')),
                    'diff_num' => array_sum(array_column($item, 'diff_num')),
                    'skus' => $item,
                ];
            }
        }
        return compact('detail', 'total');
    }

    function scan($params)
    {
        $verify = true;
        if ($params['id'] ?? 0) {
            $check = WmsStockCheckList::find($params['id']);
            if ($check) $params['warehouse_code'] = $check->warehouse_code;
        } else {
            $where = self::filterEmptyData($params, ['code', 'warehouse_code', 'check_user_id']);
            $check = WmsStockCheckList::where($where)->where(['status' => 2])->whereIn('check_status', [0, 1])->first();
            $verify = false;
        }

        if ($verify && (!$check)) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }

        if ($check && $check->type == 0) {
            $location = WmsStockCheckDetail::where(['origin_code' => $check->code, 'location_code' => $params['location_code']])->first();
            // $location = $this->_getCheckDetail($check, $params);
            if (!$location) {
                $this->setErrorMsg(__('admin.wms.check.location_code_error'));
                return false;
            }
        }

        if (false == $this->productTypeVerify($params)) return false;

        $model = Inventory::where([
            'inv_status' => 5, 'in_wh_status' => 3,
            'location_code' => $params['location_code'], 'warehouse_code' => $params['warehouse_code']
        ]);
        if ($params['type'] == 1) {
            // 唯一码产品
            $model = $model->where('uniq_code', $params['uniq_code']);
        } elseif ($params['type'] == 2) {
            // 普通产品
            $quality_type = $params['quality_type'] ?: 1;
            $model = $model->where(['bar_code' => $params['bar_code'], 'quality_type' => $quality_type]);
            $uniq_codes = WmsStockCheckLog::where([
                'origin_code' => $params['code'],
                'bar_code' => $params['bar_code'],
                'location_code' => $params['location_code'],
                'quality_type' => $quality_type,
            ])->pluck('uniq_code');

            // 去掉已经盘点过的唯一码
            if (count($uniq_codes) > 0) {
                $model = $model->whereNotIn('uniq_code', $uniq_codes);
            }
        }
        $good = $model->first();
        if (!$good) {
            $this->setErrorMsg(__('admin.wms.product.status_exception'));
            return false;
        }

        $params['uniq_code'] = $good->uniq_code;
        $log = null;
        if ($check) $log = WmsStockCheckLog::where('uniq_code', $params['uniq_code'])->where('origin_code', $check->code)->first();
        if (!$log) {
            try {
                DB::beginTransaction();
                // 新增盘点
                if (empty($params['code'] ?? '') && empty($check)) {
                    $check = $this->add($params);
                }

                $request = $check->request;
                if ($request && $request->check_status == WmsStockCheckRequest::CHECK_WAIT) {
                    $request->update(['check_status' => WmsStockCheckRequest::CHECK_ING,]);
                }
                if ($check->check_status == WmsStockCheckList::CHECK_WAIT) {
                    $check->update(['check_status' => WmsStockCheckList::CHECK_ING, 'start_at' => date('Y-m-d H:i:s')]);
                }

                WmsStockCheckLog::create([
                    'origin_code' => $check->code,
                    'request_code' => $check->request_code,
                    'bar_code' => $good->bar_code,
                    'location_code' => $params['location_code'],
                    'uniq_code' => $params['uniq_code'],
                    'quality_type' => $good->getOriginal('quality_type'),
                    'quality_level' => $good->quality_level,
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                    'admin_user_id' => ADMIN_INFO['user_id'],
                ]);

                $where = [
                    'origin_code' => $check->code,
                    'bar_code' => $good->bar_code,
                    'location_code' => $params['location_code'],
                    'quality_level' => $good->quality_level,
                ];
                $num = WmsStockCheckLog::where($where)->count();
                // 更新明细
                $detail = WmsStockCheckDetail::where($where)->first();
                if ($detail) {
                    $detail->update(['check_num' => $num,]);
                } else {
                    $stock_num = Inventory::where([
                        'warehouse_code' => $params['warehouse_code'],
                        'location_code' => $params['location_code'],
                        'in_wh_status' => 3,
                        'sale_status' => 1, 'inv_status' => 5,
                        'bar_code' => $good->bar_code,
                        'quality_level' => $good->quality_level
                    ])->count();
                    WmsStockCheckDetail::create(array_merge($where, [
                        'quality_type' => $good->getOriginal('quality_type'),
                        'stock_num' => $stock_num,
                        'check_num' => $num,
                        'admin_user_id' => ADMIN_INFO['user_id'],
                        'tenant_id' => ADMIN_INFO['tenant_id']
                    ]));
                }

                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                $this->setErrorMsg($e->getMessage());
                return false;
            }
        } else {
            $this->setErrorMsg(__('tips.option_repeat'));
            return false;
        }

        $data =  $this->detail($params);

        // 当前扫描商品信息
        $sku = $good ? $good->product : null;
        $p = $sku ? $sku->product : null;
        $data['product'] = [
            'bar_code' => $good->bar_code,
            'uniq_code' => $good->uniq_code,
            'location_code' => $good->location_code,
            'quality_level' => $good->quality_level,
            'sku' => $sku ? $sku->sku : '',
            'name' => $p ? $p->name : '',
        ];
        return $data;
    }

    private function _getCheckDetail($check, $params)
    {
        $location = WmsStockCheckDetail::where(['origin_code' => $check->code, 'location_code' => $params['location_code']])->first();
        if ($location) return $location;
        // 盲盘，未找到明细就新建一个
        if ($check->type == 1) {
            // 找到位置码对应架上可售商品信息
            $stock = Inventory::where(['warehouse_code' => $params['warehouse_code'], 'location_code' => $params['location_code'], 'in_wh_status' => 3, 'sale_status' => 1, 'inv_status' => 5])->groupBy('location_code', 'bar_code', 'quality_level')->selectRaw('location_code,bar_code,quality_level,count(id) as stock_num')->get();
            foreach ($stock as $item) {
                WmsStockCheckDetail::create([
                    'origin_code' => $check->code,
                    'bar_code' => $item->bar_code,
                    'location_code' => $item->location_code,
                    'quality_type' => $item->quality_level == 'A' ? 1 : 2,
                    'quality_level' => $item->quality_level,
                    'stock_num' => $item->stock_num,
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                    'admin_user_id' => ADMIN_INFO['user_id'],
                ]);
            }
            $location = WmsStockCheckDetail::where('origin_code', $check->code)->where('location_code', $params['location_code'])->first();
        }
        return $location;
    }

    function save($params)
    {
        $check = WmsStockCheckList::find($params['id']);
        if ($params['remark']) {
            $check->update([
                'remark' => $params['remark'],
                'updated_user' => ADMIN_INFO['user_id'],
            ]);
        }

        foreach ($params['details'] as $item) {
            if ($item['remark'] ?? '') {
                WmsStockCheckDetail::where('id', $item['id'])
                    ->where('origin_code', $check->code)
                    ->update(['remark' => $item['remark'], 'admin_user_id' => ADMIN_INFO['user_id'],]);
            }
        }

        return true;
    }

    function confirm($params)
    {
        if ($params['id'] ?? 0) {
            $check = WmsStockCheckList::find($params['id']);
        } else {
            $where = self::filterEmptyData($params, ['code', 'warehouse_code', 'check_user_id']);
            $check = WmsStockCheckList::where($where)->first();
        }
        if (!$check || $check->check_status != WmsStockCheckList::CHECK_ING) {
            $this->setErrorMsg(__('tips.deny_confirm_check'));
            return false;
        }

        try {
            DB::beginTransaction();
            $check->update([
                'check_status' => WmsStockCheckList::CHECK_DONE,
                'end_at' => date('Y-m-d H:i:s'),
                'updated_user' => ADMIN_INFO['user_id'],
            ]);
            $request = $check->request;

            // 创建盘点申请单
            if (!$request) {
                $details = $check->details;
                $request = (new CheckRequest())->save([
                    'warehouse_code' => $check->warehouse_code, 'status' => WmsStockCheckRequest::PASS, 'check_time' => 1,
                    'skus' => $details->toArray(),
                ], false);
                if (!$request) {
                    DB::rollBack();
                    return false;
                }
                $check->update(['request_code' => $request->code]);
                WmsStockCheckLog::where(['origin_code' => $check->code, 'request_code' => ''])
                    ->update(['request_code' => $request->code]);
            }

            $details = $request->details;
            foreach ($details as $detail) {
                $where = ['location_code' => $detail->location_code, 'bar_code' => $detail->bar_code, 'quality_level' => $detail->quality_level];
                $check_detail = WmsStockCheckDetail::where('origin_code', $check->code)->where($where)->first();

                $stock = Inventory::where($where)->where('warehouse_code', $request->warehouse_code)->where(['in_wh_status' => 3, 'inv_status' => 5])->get()->keyBy('uniq_code')->toArray();
                // 盘点数量为0，库存数量为0，说明盘点过程中质量类型或库存状态有变化，直接删除盘点明细即可
                if ($check_detail->check_num && count($stock) == 0) {
                    $detail->delete();
                    $check_detail->delete();
                    continue;
                }
                $update1 = ['check_num' => $check_detail->check_num,];
                if (!$detail->last_code) $update1['last_code'] = $check->code;
                if (!$detail->check_time) $update1['check_time'] = 1;
                $detail->update($update1);

                // 符合条件的所有商品
                $both = array_keys($stock);
                // 已经盘点的商品
                $check_arr = WmsStockCheckLog::where($where)->where('request_code', $request->code)->pluck('uniq_code')->toArray();
                $both = array_unique($both);
                $check_arr = array_unique($check_arr);

                // 缺少的商品
                $lack = array_diff($both, $check_arr);
                // 盘点正常的商品
                $normal = array_intersect($both, $lack);


                // 差异记录维护
                if ($normal) {
                    WmsStockCheckDifference::where('request_code', $request->code)->whereIn('uniq_code', $normal)->delete();
                }
                foreach ($lack as $uniq_code) {
                    WmsStockCheckDifference::updateOrCreate([
                        'request_code' => $request->code,
                        'uniq_code' => $uniq_code,
                        'tenant_id' => ADMIN_INFO['tenant_id'],
                    ], [
                        'bar_code' => $detail->bar_code,
                        'location_code' => $detail->location_code,
                        'num' => -1, //差异数量
                        'last_code' => $check->code,
                        'quality_type' => $detail->quality_type,
                        'quality_level' => $detail->quality_level,
                        'admin_user_id' => ADMIN_INFO['user_id'],
                        'batch_no' => $stock[$uniq_code]['lot_num'] ?? '',
                        'sup_id' => $stock[$uniq_code]['sup_id'] ?? 0,
                    ]);
                }
            }

            // 盘库申请 总数量、差异数量更新
            $num = WmsStockCheckRequestDetail::where('origin_code', $request->code)->where('status', 1)->selectRaw('sum(check_num) as check_num,sum(stock_num) as stock_num')->first();
            $request->update([
                'total_num' => $num['stock_num'],
                'total_diff' => $num['check_num'] - $num['stock_num'],
                'check_status' => WmsStockCheckRequest::CHECK_DONE,
                'current_diff' => $num['check_num'] - $num['stock_num'],
            ]);

            // 删除差异记录中复盘后找回的数据
            $uniq_codes  = $check->logs->pluck('uniq_code');
            if ($uniq_codes->count()) {
                WmsStockCheckDifference::where(['status' => 0, 'request_code' => $request->code])
                    ->whereIn('uniq_code', $uniq_codes->toArray())
                    ->update(['note' => sprintf('盘点单%s已找回', $check->code), 'deleted_at' => date('Y-m-d H:i:s')]);
            }

            WmsOptionLog::add(WmsOptionLog::CHECK, $check->code, '盘点确认', '盘点单确认', []);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 给盘点单上追加位置码
    function appendLocationCode($params)
    {
        try {
            DB::beginTransaction();
            if ($params['code'] ?? '') {
                $check = WmsStockCheckList::where('code', $params['code'])->first();
            } else {
                $where = self::filterEmptyData($params, ['warehouse_code', 'check_user_id']);
                $check = WmsStockCheckList::where($where)->whereIn('check_status', [0, 1])->first();
                if (!$check) {
                    $check = $this->add(['warehouse_code' => $params['warehouse_code'], 'start_at' => date('Y-m-d H:i:s'),]);
                }
            }
            $where = self::filterEmptyData($params, ['warehouse_code', 'location_code']);
            $inventory = Inventory::where($where)->where('in_wh_status', 3)->where('inv_status', 5)->groupBy('bar_code')->groupBy('quality_level')->selectRaw('bar_code,quality_level,sum(recv_num) as num')->get()->toArray();
            if (!$inventory) {
                $this->setErrorMsg(__('tips.location_no_check'));
                DB::rollBack();
                return false;
            }
            foreach ($inventory as $item) {
                WmsStockCheckDetail::updateOrCreate([
                    'origin_code' => $check->code,
                    'bar_code' => $item['bar_code'],
                    'location_code' => $params['location_code'],
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                    'admin_user_id' => ADMIN_INFO['user_id'],
                    'quality_level' => $item['quality_level'],
                ], [
                    'stock_num' => $item['num'],
                    'quality_type' => $item['quality_level'] == 'A' ? 1 : 2,
                ]);
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            throw $e;
            return false;
        }

        $params['group'] = true;
        $detail = $this->detail($params);
        $detail['code'] = $check->code;
        return $detail;
    }

    // 刷新盘点单的架上库存
    function refresh($params)
    {
        $where = self::filterEmptyData($params, ['warehouse_code', 'code']);
        $check = WmsStockCheckList::where($where)->first();
        $details = $check->details;
        $location_codes = $details->pluck('location_code')->toArray();
        $inventory = Inventory::where('warehouse_code', $params['warehouse_code'])
            ->whereIn('location_code', $location_codes)
            ->where('in_wh_status', 3)
            ->where('inv_status', 5)
            ->groupBy('location_code')
            ->groupBy('bar_code')
            ->groupBy('quality_level')
            ->selectRaw('location_code,bar_code,quality_level,sum(recv_num) as num,CONCAT(location_code,"_",bar_code,"_",quality_level) as kk')->get()->keyBy('kk')->toArray();

        $total = 0;
        foreach ($details as $detail) {
            $key = sprintf('%s_%s_%s', $detail->location_code, $detail->bar_code, $detail->quality_level);
            $stock_num = $inventory[$key]['num'] ?? 0;
            if ($stock_num != $detail->stock_num) {
                $total += $stock_num;
                $detail->update(['stock_num' => $stock_num, 'admin_user_id' => ADMIN_INFO['user_id']]);
            } else {
                $total += $detail->stock_num;
            }
        }
        return $this->detail($params);
    }

    // 更新盘点数量
    function updateCheckNum($params)
    {
        $where = self::filterEmptyData($params, ['warehouse_code', 'code']);
        $check = WmsStockCheckList::where($where)->first();
        if (!$check) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }
        if ($check->check_status == 2) {
            $this->setErrorMsg(__('tips.check_done_deny_edit'));
            return false;
        }
        $params['origin_code'] = $params['code'];
        $where = self::filterEmptyData($params, ['origin_code', 'bar_code', 'location_code', 'quality_level']);
        $detail = WmsStockCheckDetail::where($where)->first();
        if (!$detail) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }
        if ($params['check_num'] > $detail->stock_num) {
            $this->setErrorMsg('');
            return false;
        }
    }
}
