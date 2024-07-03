<?php

namespace App\Logics\wms;

use App\Logics\BaseLogic;
use App\Models\Admin\V2\Inventory;
use App\Models\Admin\V2\SupInv;
use App\Models\Admin\V2\WmsOptionLog;
use App\Models\Admin\V2\WmsStockCheckBill;
use App\Models\Admin\V2\WmsStockCheckDetail;
use App\Models\Admin\V2\WmsStockCheckDifference;
use App\Models\Admin\V2\WmsStockCheckList;
use App\Models\Admin\V2\WmsStockCheckRequest;
use App\Models\Admin\V2\WmsStockCheckRequestDetail;
use App\Models\Admin\V2\WmsStockDifference;
use App\Models\Admin\V2\WmsStockLog;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * 盘库申请
 */
class CheckRequest extends BaseLogic
{

    // 保存
    function save($params, $trans = true)
    {
        $id = $params['id'] ?? 0;
        if ($id) {
            $request = WmsStockCheckRequest::find($id);
            if ($request->status != WmsStockCheckRequest::STASH) {
                $this->setErrorMsg(__('tips.doc_status_not_edit'));
                return false;
            }
        }

        $update = [
            'status' => $params['status'] ?: WmsStockCheckRequest::STASH,
            'warehouse_code' => $params['warehouse_code'],
            'updated_user' => ADMIN_INFO['user_id'],
        ];
        if ($params['order_user'] ?? 0) {
            $update['order_user'] = $params['order_user'];
        }
        if ($params['order_at'] ?? 0) {
            // $update['order_at'] = strtotime($params['order_at']);
            $update['order_at'] = $params['order_at'];
        }
        if ($params['remark'] ?? '') {
            $update['remark'] = $params['remark'];
        }
        if ($params['check_time'] ?? 0) $update['check_time'] = $params['check_time'];

        try {
            if ($trans) DB::beginTransaction();
            if ($id) {
                //修改
                $request->update($update);
                WmsStockCheckRequestDetail::where('origin_code', $request->code)->where('status', 1)->update(['admin_user_id' => ADMIN_INFO['user_id'], 'status' => 0,]);
                WmsOptionLog::add(WmsOptionLog::CHECK_REQUEST, $request->code, '修改', '修改', []);
            } else {
                // 新增
                $code = WmsStockCheckRequest::code();
                $data = array_merge([
                    'type' => 1,
                    'code' => $code,
                    'created_user' => ADMIN_INFO['user_id'],
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                ], $update);
                $request = WmsStockCheckRequest::create($data);
                WmsOptionLog::add(WmsOptionLog::CHECK_REQUEST, $code, '新增', '新增', []);
            }

            foreach ($params['skus'] as $sku) {
                WmsStockCheckRequestDetail::updateOrCreate([
                    'origin_code' => $request->code,
                    'bar_code' => $sku['bar_code'],
                    'location_code' => $sku['location_code'],
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                    'quality_level' => $sku['quality_level'],
                ], [
                    'stock_num' => $sku['stock_num'], //架上库存
                    'quality_type' => $sku['quality_level'] == 'A' ? 1 : 2,
                    'remark' => $sku['remark'] ?? '',
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                    'admin_user_id' => ADMIN_INFO['user_id'],
                    'status' => 1,
                ]);
            }
            if ($trans) DB::commit();
            return $request;
        } catch (Exception $e) {
            if ($trans) DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 详情
    function info($params)
    {
        if(!empty($params['id']))$info = WmsStockCheckRequest::with(['warehouse', 'orderUser', 'createUser', 'adminUser'])->find($params['id']);
        if(!empty($params['code']))$info = WmsStockCheckRequest::with(['warehouse', 'orderUser', 'createUser', 'adminUser'])->where('code',$params['code'])->first();
        if(!$info){
            $this->setErrorMsg(__('response.doc_not_exists'));
            return ;
            // $info = [];
            // $operations = [];
            // $logs = [];
        }
        else $info=$info->toArray();
        $logs = [];
        $differences = [];
        if ($info) {
            // 差异明细
            $differences = WmsStockCheckDifference::where('request_code', $info['code'])->with(['specBar', 'supplier'])->get()->toArray();
            foreach ($differences as &$item) {
                self::sepBarFormat($item);
                $item['current_diff'] = $item['num'] + $item['recover_num'];
            }

            // 操作日志
            $logs = WmsOptionLog::where('type', WmsOptionLog::CHECK_REQUEST)->where('doc_code', $info['code'])->orderBy('id', 'desc')->get();
            unset($info['details']);
        }
        self::infoFormat($info);

        return compact('info', 'differences', 'logs');
    }

    // 明细
    function detail($params)
    {
        $id = $params['id'];
        $info = WmsStockCheckRequest::with(['details', 'details.specBar'])->find($id)->toArray();
        $detail = [];
        $total = [];
        if ($info) {
            foreach ($info['details'] as $item) {
                self::sepBarFormat($item);
                $item['diff_num'] = $item['check_num'] - $item['stock_num'];
                $detail[] = $item;
                $total['stock_num'] = ($total['stock_num'] ?? 0) + $item['stock_num'] ?? 0;
                $total['check_num'] = ($total['check_num'] ?? 0) + $item['check_num'];
                $total['diff_num'] = ($total['diff_num'] ?? 0) + $item['diff_num'];
                $total['check_time'] = ($total['check_time'] ?? 0) + $item['check_time'];
            }
        }

        return compact('detail', 'total');
    }

    // 撤销
    function revoke($params)
    {
        $request = WmsStockCheckRequest::find($params['id']);
        if (!$request || $request->status != WmsStockCheckRequest::STASH) {
            $this->setErrorMsg(__('tips.doc_not_withdraw'));
            return false;
        }
        try {
            DB::beginTransaction();
            $request->update([
                'status' => WmsStockCheckRequest::CANCEL,
                'updated_user' => ADMIN_INFO['user_id'],
            ]);
            WmsStockCheckRequestDetail::where('origin_code', $request->code)->where('status', 1)->update(['status' => 0]);
            WmsOptionLog::add(WmsOptionLog::CHECK_REQUEST, $request->code, '取消', '取消', []);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }


    // 删除
    function delete($params)
    {
        $request = WmsStockCheckRequest::find($params['id']);
        if (!$request || $request->status != WmsStockCheckRequest::STASH) {
            $this->setErrorMsg(__('tips.order_not_delete'));
            return false;
        }
        try {
            DB::beginTransaction();
            $request->delete();
            // WmsStockCheckRequestDetail::where('origin_code', $request->code)->where('status', 1)->update(['status' => 0]);
            WmsOptionLog::add(WmsOptionLog::CHECK_REQUEST, $request->code, '删除', '删除', []);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 提交
    function submit($params)
    {
        $request = WmsStockCheckRequest::find($params['id']);
        if (!$request || $request->status != WmsStockCheckRequest::STASH) {
            $this->setErrorMsg(__('tips.order_not_submit'));
            return false;
        }
        try {
            DB::beginTransaction();
            $request->update([
                'status' => WmsStockCheckRequest::WAIT_AUDIT,
                'updated_user' => ADMIN_INFO['user_id'],
            ]);
            WmsOptionLog::add(WmsOptionLog::CHECK_REQUEST, $request->code, '提交盘点申请', '提交盘点申请', []);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 审核
    function audit($params)
    {
        $request = WmsStockCheckRequest::find($params['id']);
        if (!$request || $request->status != WmsStockCheckRequest::WAIT_AUDIT) {
            $this->setErrorMsg(__('tips.status_deny_option'));
            return false;
        }
        try {
            DB::beginTransaction();
            $request->update([
                'status' => $params['status'],
                'updated_user' => ADMIN_INFO['user_id'],
            ]);
            WmsOptionLog::add(WmsOptionLog::CHECK_REQUEST, $request->code, '审核盘点申请', '审核盘点申请', $params);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 取消
    function cancel($params)
    {
        $request = WmsStockCheckRequest::find($params['id']);
        if (!$request || $request->status != WmsStockCheckRequest::PASS || $request->check_status != WmsStockCheckRequest::CHECK_WAIT) {
            $this->setErrorMsg(__('tips.status_deny_option'));
            return false;
        }
        try {
            DB::beginTransaction();
            $request->update([
                'status' => WmsStockCheckRequest::CANCEL,
                'updated_user' => ADMIN_INFO['user_id'],
            ]);
            WmsStockCheckRequestDetail::where('origin_code', $request->code)->where('status', 1)->update(['status' => 0]);
            WmsOptionLog::add(WmsOptionLog::CHECK_REQUEST, $request->code, '取消', '取消', []);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 下发盘点
    function send($params)
    {
        $request = WmsStockCheckRequest::find($params['id']);
        if (!$request || $request->status != WmsStockCheckRequest::PASS) {
            $this->setErrorMsg(__('tips.status_deny_option'));
            return false;
        }
        try {
            DB::beginTransaction();
            $request->update([
                'status' => WmsStockCheckRequest::SEND,
                'updated_user' => ADMIN_INFO['user_id'],
                'check_time' => $request->check_time + 1,
            ]);
            WmsOptionLog::add(WmsOptionLog::CHECK_REQUEST, $request->code, '生成盘点单', '生成盘点单', $params);
            $code = WmsStockCheckList::code();
            $details = $request->details;
            $this->_addCheck($request, $details, $code, $params);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 生成盘点单
    private function _addCheck($request, $details, $code, $params = [])
    {
        $check = WmsStockCheckList::create([
            'type' => 1,
            'code' => $code,
            'request_code' => $request->code,
            'status' => 2,
            'check_status' => 0,
            'check_type' => $params['type'],
            'warehouse_code' => $request->warehouse_code,
            'created_user' => $request->created_user,
            'updated_user' => $request->updated_user,
            'tenant_id' => ADMIN_INFO['tenant_id'],
            'check_user_id' => $request->created_user,
        ]);

        foreach ($details as $detail) {
            // 盘点单明细
            WmsStockCheckDetail::create([
                'origin_code' => $check->code,
                'bar_code' => $detail->bar_code,
                'location_code' => $detail->location_code,
                'stock_num' => $detail->stock_num,
                'quality_type' => $detail->quality_type,
                'quality_level' => $detail->quality_level,
                'tenant_id' => ADMIN_INFO['tenant_id'],
                'admin_user_id' => ADMIN_INFO['user_id'],
            ]);
        }
        WmsOptionLog::add(WmsOptionLog::CHECK, $code, '新增', '新增', $params);
    }

    // 复盘
    function second($params)
    {

        $request = WmsStockCheckRequest::find($params['id']);
        if (!$request || $request->status != WmsStockCheckRequest::SEND || $request->check_status != WmsStockCheckRequest::CHECK_DONE) {
            $this->setErrorMsg(__('tips.status_deny_option'));
            return false;
        }

        // 1-全部复盘 2-少货复盘 3-多货复盘 4-正常复盘 5-差异复盘
        $scope = $params['scope'];
        $where_raw = sprintf('status=1 and origin_code="%s"', $request->code);
        switch ($scope) {
            case 1: //全部复盘
                break;
            case 2: //少货复盘
                $where_raw = 'and stock_num>check_num';
                break;
            case 3: //多货复盘
                $where_raw = 'and stock_num<check_num';
                break;
            case 4: //正常复盘
                $where_raw = 'and stock_num=check_num';
                break;
            case 5: //差异复盘
                $where_raw = 'and stock_num!=check_num';
                break;
        }
        $details = WmsStockCheckRequestDetail::whereRaw($where_raw)->get();
        if ($details->count() == 0) {
            $this->setErrorMsg(__('tips.recheck_no_good'));
            return false;
        }

        try {
            DB::beginTransaction();
            $request->update([
                'updated_user' => ADMIN_INFO['user_id'],
                'check_time' => $request->check_time + 1,
            ]);
            WmsOptionLog::add(WmsOptionLog::CHECK_REQUEST, $request->code, '复盘', '生成盘点单', $params);
            $first = $request->check;
            $code = $first->code . '-' . ($request->check_time - 1);


            $this->_addCheck($request, $details, $code, $params);
            WmsStockCheckRequestDetail::whereRaw($where_raw)->update([
                'check_num' => 0,
                'admin_user_id' => ADMIN_INFO['user_id'],
            ]);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            throw $e;
            return false;
        }
    }

    function search($params, $export = false)
    {

        $model = new WmsStockCheckRequest();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            return $model->with(['warehouse', 'orderUser'])->orderBy('created_at', 'desc');
        });

        foreach ($list['data'] as &$item) {
            self::infoFormat($item);
            if ($export) {
                self::exportFormat($item, ["total_num", "total_diff", "report_num", "recover_num", "current_diff",]);
            }
        }
        return $list;
    }


    // 差异数据查询
    function differenceSearch($params)
    {
        $model = new WmsStockCheckDifference();
        $model = $this->commonWhere($params, $model);
        $list = $model->where('request_code', $params['code'])->with(['specBar', 'supplier'])->get()->toArray();
        $total = [];
        foreach ($list as &$item) {
            self::sepBarFormat($item);
            $item['current_diff'] = $item['num'] + $item['recover_num'];

            $total['num'] = $total['num'] ?? 0 + $item['num'];
            $total['report_num'] = ($total['report_num'] ?? 0) + $item['report_num'];
            $total['recover_num'] = ($total['recover_num'] ?? 0) + $item['recover_num'];
            $total['current_diff'] = ($total['current_diff'] ?? 0) + $item['current_diff'];
        }
        return compact('list', 'total');
    }

    // 少货寻回
    function recover($params)
    {
        $find = WmsStockCheckDifference::where(['request_code' => $params['code'], 'uniq_code' => $params['origin_uniq_code']])->first();
        if (!$find || $find->status != WmsStockCheckDifference::WAIT) {
            $this->setErrorMsg(__('tips.no_difference'));
            return false;
        }
        try {
            DB::beginTransaction();
            $update = [
                'recover_num' => $params['num'],
                'recover_uniq_code' => $params['uniq_code'],
                'recover_location_code' => $params['location_code'],
                'admin_user_id' => ADMIN_INFO['user_id'],
                'status' => WmsStockCheckDifference::RECOVER,
            ];
            if ($params['remark'] ?? '') {
                $update['remark'] = $params['remark'];
            }
            $find->update($update);

            WmsStockCheckRequest::syncDiffNum($params);
            //少货寻回库存流水
            WmsStockLog::add(WmsStockLog::ORDER_CHECK, $params['uniq_code'], $params['code'], ['diff' => $find, 'origin_value' => $find->location_code]);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }

        return true;
    }

    // 差异上报
    function report($params)
    {
        $request = WmsStockCheckRequest::where('code', $params['code'])->first();
        if (!$request) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }

        $diff_num = WmsStockCheckDifference::where('request_code', $request->code)->where('status', WmsStockCheckDifference::WAIT)->sum('num');
        if (!$diff_num) {
            $this->setErrorMsg(__('tips.no_wait_difference'));
            return false;
        }

        try {
            DB::beginTransaction();
            $diff = WmsStockDifference::create([
                'code' => WmsStockDifference::code(),
                'type' => 1,
                'origin_type' => 1,
                'origin_code' => $params['code'],
                'diff_num' => $diff_num, //差异总数
                'warehouse_code' => $request->warehouse_code,
                'remark' => $params['remark'] ?? '',
                'tenant_id' => ADMIN_INFO['tenant_id'],
                'create_user_id' => ADMIN_INFO['user_id'],
                'admin_user_id' => ADMIN_INFO['user_id'],
                'order_user' => $request->order_user,
                'order_at' => $request->order_at,
            ]);
            $details = WmsStockCheckDifference::where('request_code', $request->code)->where('status', WmsStockCheckDifference::WAIT)->get();
            foreach ($details as $detail) {
                $detail->update([
                    'origin_code' => $diff->code,
                    'report_num' => 1,
                    'status' => WmsStockCheckDifference::REPORT,
                ]);
                //少货上报库存流水
                WmsStockLog::add(WmsStockLog::ORDER_MOVE_APPLY, $detail->uniq_code, $diff->origin_code, ['diff' => $detail, 'origin_value' => $detail->location_code]);
                //库存状态更新-冻结
                Inventory::invStatusUpdate($detail->uniq_code,Inventory::FREEZED,5,['lock_code'=>$diff->code]);
                //供应商库存更新
                SupInv::supInvUpdate($detail->uniq_code);
            }
            WmsStockCheckRequest::syncDiffNum($params);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            throw $e;
            return false;
        }
    }


    // 差异处理记录查询
    function dSearch($params, $export = false)
    {
        $model = new WmsStockDifference();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            return $model->with(['warehouse', 'createUser', 'orderUser'])->orderBy('id', 'desc');
        });

        foreach ($list['data'] as &$item) {
            if ($export) {
                self::exportFormat($item, ["diff_num"]);
            }
        }
        return $list;
    }

    // 差异处理记录详情
    function dInfo($params)
    {
        $id = $params['id'];
        $info = WmsStockDifference::with(['warehouse', 'orderUser', 'createUser', 'adminUser'])->find($id)->toArray();
        $detail = [];
        $logs = [];
        $total = [];
        if ($info) {
            $logs = WmsOptionLog::where('type', WmsOptionLog::CHECK_REQUEST)->where('doc_code', $info['code'])->orderBy('id', 'desc')->get();
        }
        self::infoFormat($info);

        return compact('info', 'logs');
    }

    // 差异处理记录明细
    function dDetail($params)
    {
        $id = $params['id'];
        $info = WmsStockDifference::with(['details', 'details.specBar'])->find($id);
        if($info) $info= $info->toArray();
        $detail = [];
        $total = [];
        if ($info) {
            foreach ($info['details'] as $item) {
                self::sepBarFormat($item);
                $item['supplier'] = '';
                $detail[] = $item;
                $total['num'] = ($total['num'] ?? 0) + $item['num'];
            }
            unset($info['details']);
        }
        return compact('detail', 'total');
    }

    // 差异处理记录保存
    function dSave($params)
    {
        $id = $params['id'];
        $info = WmsStockDifference::find($id);
        if ($info) {
            $info->update([
                'remark' => $params['remark'],
                'admin_user_id' => ADMIN_INFO['user_id'],
            ]);
        }
        return true;
    }

    // 差异处理记录审核
    function dAudit($params)
    {
        $id = $params['id'];
        $info = WmsStockDifference::find($id);
        if (!$info) return true;
        if ($info->status == WmsStockDifference::DONE) return true;
        try {
            DB::beginTransaction();
            $info->update([
                'status' => WmsStockDifference::DONE,
                'admin_user_id' => ADMIN_INFO['user_id'],
            ]);

            $bill = WmsStockCheckBill::create([
                'type' => 2,
                'code' => WmsStockCheckBill::code(),
                'status' => 0,
                'origin_code' => $info->origin_code,
                'diff_code' => $info->code,
                'diff_num' => $info->diff_num,
                'warehouse_code' => $info->warehouse_code,
                'create_user_id' => ADMIN_INFO['user_id'],
                'admin_user_id' => ADMIN_INFO['user_id'],
            ]);

            WmsStockCheckDifference::where('origin_code', $info->code)
                ->where('bill_code', '')
                ->update(['bill_code' => $bill->code,]);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
        return true;
    }



    // 盈亏单查询
    function bSearch($params, $export = false)
    {
        $model = new WmsStockCheckBill();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            return $model->with(['warehouse', 'createUser', 'orderUser'])->orderBy('id', 'desc');
        });

        foreach ($list['data'] as &$item) {
            if ($export) {
                self::exportFormat($item, ["diff_num"]);
            }
        }
        return $list;
    }

    // 盈亏单详情
    function bInfo($params)
    {
        $id = $params['id'];
        $info = WmsStockCheckBill::with(['warehouse', 'orderUser'])->find($id)->toArray();
        $logs = [];
        // if ($info) {
        //     $logs = WmsOptionLog::where('type', WmsOptionLog::BILL)->where('doc_code', $info['code'])->orderBy('id', 'desc')->get();
        // }
        self::infoFormat($info);

        return compact('info', 'logs');
    }

    // 盈亏单明细
    function bDetail($params)
    {
        $id = $params['id'];
        $info = WmsStockCheckBill::with(['details', 'details.specBar'])->find($id)->toArray();
        $detail = [];
        $total = [];
        if ($info) {
            foreach ($info['details'] as $item) {
                self::sepBarFormat($item);
                $detail[] = $item;
                $total['num'] = ($total['num'] ?? 0) + $item['num'];
            }
            unset($info['details']);
        }
        return compact('detail', 'total');
    }

    // 盈亏单明细
    function bSave($params)
    {
        $id = $params['id'];
        $info = WmsStockCheckBill::find($id);
        if ($info) {
            $info->update([
                'remark' => $params['remark'],
                'admin_user_id' => ADMIN_INFO['user_id'],
            ]);
        }
        return true;
    }
}
