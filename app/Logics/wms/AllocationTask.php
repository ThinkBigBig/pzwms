<?php

namespace App\Logics\wms;

use App\Logics\BaseLogic;
use App\Logics\RedisKey;
use App\Logics\wms\Warehouse as WmsWarehouse;
use App\Models\Admin\V2\Inventory;
use App\Models\Admin\V2\ObOrder;
use App\Models\Admin\V2\preAllocationDetail;
use App\Models\Admin\V2\preAllocationLists;
use App\Models\Admin\V2\Product;
use App\Models\Admin\V2\ProductCategory;
use App\Models\Admin\V2\ShippingDetail;
use App\Models\Admin\V2\Warehouse;
use App\Models\Admin\V2\WarehouseLocation;
use App\Models\Admin\V2\WmsAllocationTask;
use App\Models\Admin\V2\WmsOptionLog;
use App\Models\Admin\V2\WmsPreAllocationDetail;
use App\Models\Admin\V2\WmsPutawayDetail;
use App\Models\Admin\V2\WmsPutawayList;
use App\Models\Admin\V2\WmsShippingCancel;
use App\Models\Admin\V2\WmsShippingOrder;
use App\Models\Admin\V2\WmsShippingRequest;
use App\Models\Admin\V2\WmsStockLog;
use App\Models\Admin\V2\WmsTaskStrategy;
use App\Models\Admin\V2\TransferOrder;
use App\Models\Admin\V2\OtherObOrder;
use App\Models\Admin\V2\WmsOrder;
use App\Models\Admin\V2\SupInv;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use WmsPutaway;

class AllocationTask extends BaseLogic
{

    function strategySave($params)
    {
        if (!$params['content'] ?? []) {
            $this->setErrorMsg(__('tips.empty_config'));
            return false;
        }
        $data = [
            'status' => WmsTaskStrategy::ON,
        ];

        if ($params['warehouse_code'] ?? '') {
            $data['warehouse_code'] = $params['warehouse_code'];
            $data['warehouse_name'] = (new Warehouse())->getName($params['warehouse_code']);
        }
        if ($params['name'] ?? '') $data['name'] = $params['name'];
        if ($params['mode'] ?? '') $data['mode'] = $params['mode'];
        if ($params['sort'] ?? '') $data['sort'] = $params['sort'];
        if ($params['upper_limit'] ?? '') $data['upper_limit'] = $params['upper_limit'];
        if ($params['remark'] ?? '') $data['remark'] = $params['remark'];
        if ($params['content'] ?? '') $data['content'] = $params['content']; //数据格式：{['column'=>'','condition'=>'','value'=>'','logic'=>'']}
        if (isset($params['status'])) $data['status'] = $params['status'];

        if ($params['id'] ?? '') {
            $startegy = WmsTaskStrategy::find($params['id']);
            if (!$startegy) {
                $this->setErrorMsg(__('tips.data_not_exists'));
                return 0;
            }
            $startegy->update($data);
            WmsOptionLog::add(WmsOptionLog::ALLOCATION_TASK_STARTEGY, $startegy->code, '修改', '修改', $data);
        } else {
            $code = self::startegyCode();
            $data['code'] = $code;
            $data['tenant_id'] = ADMIN_INFO['tenant_id'];
            $data['create_user_id'] = ADMIN_INFO['user_id'];
            $startegy = WmsTaskStrategy::create($data);
            WmsOptionLog::add(WmsOptionLog::ALLOCATION_TASK_STARTEGY, $code, '启用', '启用', []);
        }
        return $startegy->id;
    }

    function strategyDetail($id)
    {
        $info = WmsTaskStrategy::find($id);
        if (!$info) $info = [];
        $logs = [];
        if ($info) {
            $logs = WmsOptionLog::list(WmsOptionLog::ALLOCATION_TASK_STARTEGY, $info->code);
        }
        $select_options = self::startegySelectOptions();

        return compact('info', 'logs', 'select_options');
    }

    function strategySearch($params, $export = false)
    {
        $model = new WmsTaskStrategy();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            if ($params['number'] ?? '') {
                $model = $model->where(function ($query) use ($params) {
                    return $query->where('warehouse_name', $params['number'])
                        ->orWhere('code', $params['number'])
                        ->orWhere('name', $params['number']);
                });
            }
            return $model->with(['adminUser', 'createUser'])->orderBy('id', 'desc');
        });
        return $list;
    }

    function strategyDelete($id)
    {
        $info = WmsTaskStrategy::find($id);
        if (!$info) return true;
        $info->delete();
        WmsOptionLog::add(WmsOptionLog::ALLOCATION_TASK_STARTEGY, $info->code, '删除', '删除', []);
        return true;
    }


    static function startegyCode()
    {
        return 'BCFZ' . date('ymdHis') . rand(1000, 9999);
    }

    static function taskCode()
    {
        return 'PHD' . date('ymdHis') . rand(1000, 9999);
    }

    // 波次分组策略条件下拉信息
    static function startegySelectOptions()
    {
        $part1 = WmsTaskStrategy::$conditions['options']['string'];
        $part2 = WmsTaskStrategy::$conditions['options']['number'];
        $columns = WmsTaskStrategy::$conditions['columns'];

        // 店铺
        $shops = Shop::selectShopOptions();
        $platform = WmsTaskStrategy::$order_platform_map;
        // $platform = [
        //     'DP2311100215284400' => '其他',
        //     'DEWU_OVERSEA_FIT' => 'FIT得物跨境',
        // ];
        $category = ProductCategory::where('status', 1)
            // ->where('level', 1)
            ->select(['code', 'name'])->get();
        return [
            ['column' => 'origin_type', 'name' => $columns['origin_type'], 'condition_options' => $part1, 'options' => [1 => '销售出库单', 2 => '调拨出库单', 3 => '其他出库单', 4 => '中转移位单', 5 => '快速移位单',],],
            ['column' => 'shop_name', 'name' => $columns['shop_name'], 'condition_options' => $part1, 'options' => $shops],
            ['column' => 'order_platform', 'name' => $columns['order_platform'], 'condition_options' => $part2, 'options' => $platform],
            ['column' => 'sku_num', 'name' => $columns['sku_num'], 'condition_options' => $part2, 'options' => [],],
            ['column' => 'delivery_time', 'name' => $columns['delivery_time'], 'condition_options' => $part2, 'options' => [],],
            ['column' => 'product_category', 'name' => $columns['product_category'], 'condition_options' => $part1, 'options' => $category],
        ];
    }

    function pool($params, $export = false)
    {
        $model = new preAllocationLists();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            // $model = $this->commonWhere($params, $model);
            if ($params['number'] ?? '') {
                $model = $model->where(function ($query) use ($params) {
                    return $query->where('pre_alloction_code', $params['number'])
                        ->orWhereHas('shippingRequest', function ($query) use ($params) {
                            $query->where('third_no', $params['number'])->orWhere('deliver_no', $params['number']);
                        });
                });
            }
            if ($params['warehouse_code'] ?? '') {
                $model = $model->where('warehouse_code', $params['warehouse_code']);
            }
            $where = ['status' => 1, 'state' => 1];
            if ($params['warehouse_code'] ?? '') $where['warehouse_code'] = $params['warehouse_code'];
            return $model->whereRaw('pre_num>received_num')->where($where)->with('shippingRequest')
                ->whereHas('details', function ($query) {
                    $query->where('task_strategy_code', '>', '')->where('cancel_status', 0);
                })->orderBy('id', 'desc');
        });

        foreach ($list['data'] as &$item) {
            $request = $item['shipping_request'];
            $item['created_at'] = date('Y-m-d H:i:s', strtotime($item['created_at']));
            $item['updated_at'] = date('Y-m-d H:i:s', strtotime($item['updated_at']));
            $item['third_no'] = $request['third_no'] ?? '';
            $item['erp_no'] = $request['erp_no'] ?? '';
            $item['order_platform'] = $request['order_platform_txt'] ?? '';
            $item['order_channel'] = $request['order_channel'] ?? '';
            $item['deliver_no'] = $request['deliver_no'] ?? '';
            $item['warehouse_name'] = $request['warehouse_name'] ?? '';
            $item['order_at'] = date('Y-m-d H:i:s', strtotime($request['created_at']));
            $item['left_deliver_hours'] = ($request['delivery_deadline'] ?? 0) ? bcdiv(bcsub($request['delivery_deadline'], time(), 0), 3600, 2) : 0;
            unset($item['shipping_request']);
        }
        return $list;
    }

    // 配货订单分组
    function waveGroup($pre_details_ids = [], $tenant_id)
    {
        try {
            DB::beginTransaction();
            $strategies = WmsTaskStrategy::where('status', WmsTaskStrategy::ON)->where('tenant_id', $tenant_id)->orderBy('sort', 'asc')->get();
            foreach ($strategies as $strategy) {
                // 未设置分配条件，这条策略直接跳过
                if (!$strategy->content) continue;

                // 分拣配货
                if ($strategy->mode == WmsTaskStrategy::MODE_SORT_OUT) {
                    // 将content转成where查询语句
                    $model = DB::table('wms_pre_allocation_detail as pad')
                        ->where('pad.alloction_status', WmsPreAllocationDetail::WAIT_GROUP)
                        ->where('pad.warehouse_code', $strategy->warehouse_code)
                        ->where('pad.cancel_status', 0)->where('pad.tenant_id', $tenant_id);

                    if ($pre_details_ids) $model = $model->whereIn('pad.id', $pre_details_ids);

                    $columns = array_column($strategy->content, 'column');
                    // 根据查询条件判断是否要关联表
                    if (array_intersect(['shop_name', 'order_platform', 'delivery_time'], $columns)) {
                        $model = $model->leftJoin('wms_shipping_request as sr', 'sr.request_code', 'pad.request_code')->where('sr.tenant_id', $tenant_id);
                    }
                    if (array_intersect(['origin_type', 'sku_num'], $columns)) {
                        $model = $model->leftJoin('wms_pre_allocation_lists as pal', 'pal.pre_alloction_code', 'pad.pre_alloction_code')->where('pal.tenant_id', $tenant_id);
                    }
                    $category_ids = [];
                    $contents = collect($strategy->content)->keyBy('column')->toArray();

                    // 查所属的商品种类
                    if (in_array('product_category', $columns)) {
                        // 商品种类非空 = 所有商品，限制条件相当于不存在
                        if ($contents['product_category']['condition'] != 'is not null') {
                            unset($contents['product_category']);
                        } else {
                            $res = (new ProductCategory())->getChildren($contents['product_category']['value']);
                            if ($res) {
                                $category_ids = array_column($res, 'id');
                                $model = $model->leftJoin('wms_spec_and_bar as sab', 'sab.bar_code', 'pad.bar_code')
                                    ->leftJoin('wms_product as p', 'sab.product_id', 'p.id');
                            }
                            $contents['product_category']['value'] = $category_ids;
                        }
                    }

                    // 查询条件处理
                    $model = $model->where(function ($query) use ($contents) {
                        $logic = 'AND';
                        foreach ($contents as $item) {

                            $value = $item['value'];
                            $cloumn = $item['column'];
                            $condition = $item['condition'];
                            //数据格式：{['column'=>'','condition'=>'','value'=>'','logic'=>'']}
                            switch ($cloumn) {
                                case 'shop_name':
                                case 'order_platform':
                                    $cloumn = 'sr.' . $cloumn;
                                    if ($condition == 'is null') {
                                        $condition = '=';
                                        $value = '';
                                    }
                                    if ($condition == 'is not null') {
                                        $condition = '>';
                                        $value = '';
                                    }
                                    break;
                                case 'origin_type':
                                case 'sku_num':
                                    $cloumn = 'pal.' . $cloumn;
                                    if ($condition == 'is null') {
                                        $condition = '=';
                                        $value = 0;
                                    }
                                    if ($condition == 'is not null') {
                                        $condition = '>';
                                        $value = 0;
                                    }
                                    break;
                                case 'delivery_time':
                                    $cloumn = 'sr.delivery_deadline';
                                    // $cloumn = 'sr.' . $cloumn;
                                    $value = time() - $value * 3600;
                                    if ($condition == 'is null') {
                                        $condition = '=';
                                        $value = 0;
                                    }
                                    if ($condition == 'is not null') {
                                        $condition = '>';
                                        $value = 0;
                                    }
                                    if ($condition == 'in') {
                                        $condition = '=';
                                        $value = $value[0] * 3600;
                                    }
                                    break;
                                case 'product_category':
                                    $cloumn = 'p.category_id';
                                    if ($condition == '=') $condition = 'in';
                                    if ($condition == 'is null') {
                                        $condition = '=';
                                        $value = 0;
                                    }
                                    break;
                            }

                            if (in_array($condition, ['=', '!=', '>', '>=', '<', '<='])) {
                                if ($logic == 'AND') {
                                    $query->where($cloumn, $condition, $value);
                                } else {
                                    $query->orWhere($cloumn, $condition, $value);
                                }
                            }
                            if ($condition == 'in') {
                                $value = is_array($value) ? $value : [$value];
                                if ($logic == 'AND') {
                                    $query->whereIn($cloumn, $value);
                                } else {
                                    $query->orWhereIn($cloumn, $value);
                                }
                            }
                            $logic = $item['logic'] ?? 'AND';
                        }
                    });

                    $orders = $model->select(['pad.*'])->orderBy('pad.location_code', 'asc')->get();
                    $ids = $orders->pluck('id');
                    if ($ids) {
                        $pre_arr = preAllocationDetail::whereIn('id', $ids)->where('alloction_status', preAllocationDetail::WAIT_GROUP)->where('tenant_id', $tenant_id)->pluck('pre_alloction_code')->toArray();

                        // 更新配货状态
                        preAllocationDetail::whereIn('id', $ids)->where('tenant_id', $tenant_id)
                            ->where('alloction_status', preAllocationDetail::WAIT_GROUP)
                            ->update([
                                'alloction_status' => preAllocationDetail::WAIT_RECEIVER,
                                'task_strategy_code' => $strategy->code,
                            ]);
                        preAllocationLists::whereIn('pre_alloction_code', $pre_arr)->update(['state' => 1]);
                    }
                }


                // 按单配货
                if ($strategy->mode == WmsTaskStrategy::MODE_ORDER) {
                }
            }

            $where = ['alloction_status' => preAllocationDetail::WAIT_GROUP, 'tenant_id' => $tenant_id,];
            // 全部处理完后，未经过分组的统一改成待领取状态
            $pre_arr = preAllocationDetail::where($where)->pluck('pre_alloction_code')->toArray();
            preAllocationDetail::whereIn('pre_alloction_code', $pre_arr)->where('tenant_id', $tenant_id)
                ->update(['alloction_status' => preAllocationDetail::WAIT_RECEIVER, 'task_strategy_code' => 'no_group']);
            preAllocationLists::whereIn('pre_alloction_code', $pre_arr)->where('tenant_id', $tenant_id)->update(['state' => 1]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // 待领取任务
    function pendingTasks($params)
    {
        $details = WmsPreAllocationDetail::where('alloction_status', WmsPreAllocationDetail::WAIT_RECEIVER)
            ->where('warehouse_code', $params['warehouse_code'])
            ->where('cancel_status', 0)
            ->groupBy('task_strategy_code')->select(['task_strategy_code'])
            ->selectRaw('count(distinct bar_code) as pre_num')
            ->selectRaw('count(id) as num,count(distinct pre_alloction_code) as order_num')
            ->get();
        $strategy = WmsTaskStrategy::where('warehouse_code', $params['warehouse_code'])->get()->keyBy('code')->toArray();
        $total = 0;
        foreach ($details as &$item) {
            $total++;
            if ($item['task_strategy_code'] == 'no_group')
                $item['name'] = '未分组';
            else
                $item['name'] = $strategy[$item['task_strategy_code']]['name'] ?? '';
        }
        return compact('details', 'total');
    }

    // 根据配货订单领取任务
    function receiveTaskByCode($params)
    {
        $pre_alloction_code = $params['pre_alloction_code'] ?? 0;
        $details = preAllocationDetail::where('pre_alloction_code', $pre_alloction_code)->where('alloction_status', preAllocationDetail::WAIT_RECEIVER)->where(['cancel_status' => 0,])->get();
        if (!count($details)) {
            $this->setErrorMsg(__('admin.wms.task.no_task'));
            return false;
        }
        $this->_addTask($details);
        return true;
    }

    // 根据波次分组领取任务
    function receiveTaskByStartegy($params)
    {
        $where = [
            'task_strategy_code' => $params['strategy_code'],
            'alloction_status' => 2, 'cancel_status' => 0,
        ];
        if ($params['warehouse_code'] ?? '') $where['warehouse_code'] = $params['warehouse_code'];

        $codes = preAllocationDetail::where($where)->orderBy('location_code')->distinct(true)->pluck('pre_alloction_code');
        if (!$codes) {
            $this->setErrorMsg(__('admin.wms.task.no_task'));
            return false;
        }
        $len = min(count($codes), $params['num']);
        $get_codes = array_slice($codes->toArray(), 0, $len);
        $details = preAllocationDetail::where($where)->whereIn('pre_alloction_code', $get_codes)->get();

        $task = WmsAllocationTask::where('receiver_id', ADMIN_INFO['user_id'])
            ->whereIn('alloction_status', [1, 2])
            ->whereIn('status', [1, 2])
            ->where('warehouse_code', $params['warehouse_code'])->first();
        if ($task) {
            $this->setErrorMsg(__('tips.has_task'));
            return false;
        }
        $task = $this->_addTask($details);
        if ($task) return ['code' => $task->code];
        return false;
    }




    private function _addTask($details, $custome = [])
    {
        $strategy = null;
        $mode = 1;
        if ($custome) {
            $mode = $custome['mode'];
        } else {
            $strategy = $details[0]->taskStrategy;
        }
        $warehouse_code = $details[0]->warehouse_code;

        // 订单总数
        $order_num = count(array_unique($details->pluck('pre_alloction_code')->toArray()));
        // 商品件数
        $total_num = $details->sum('pre_num');
        if ($strategy) {
            $max = $strategy->upper_limit;
            if ($order_num > $max) {
                $this->setErrorMsg(__('admin.wms.task.upper_limit') . $max);
                return false;
            }
        }

        try {
            DB::beginTransaction();
            $num = WmsAllocationTask::where('created_at', date('Y-m-d 00:00:00'))->count();
            $group_no = date('ymd') . str_pad((string)$num + 1, 6, '0', STR_PAD_LEFT);

            $task_code = self::taskCode();
            // 生成一个任务单
            $task = WmsAllocationTask::create([
                'code' => $task_code,
                'type' => $strategy ? $strategy->name : '混合订单配货单',
                'status' => 1,
                'alloction_status' => 1,
                'mode' => $mode,
                'strategy_code' => $strategy ? $strategy->code : '',
                'group_no' => $group_no, //波次号
                'order_num' => $order_num, //订单总数
                'total_num' => $total_num, //商品件数
                'warehouse_code' => $strategy ? $strategy->warehouse_code : $warehouse_code,
                'receiver_id' => ADMIN_INFO['user_id'],
                'create_user_id' => ADMIN_INFO['user_id'],
                'admin_user_id' => ADMIN_INFO['user_id'],
                'tenant_id' => ADMIN_INFO['tenant_id'],
                'custom_content' => $custome,
            ]);
            $ids = $details->pluck('id')->toArray();
            preAllocationDetail::whereIn('id', $ids)->update([
                'alloction_status' => preAllocationDetail::WAIT_ALLOCATE,
                'task_code' => $task->code,
                'receiver_id' => ADMIN_INFO['user_id'],
                'received_at' => date('Y-m-d H:i:s'),
            ]);

            $pre_alloction_codes = preAllocationDetail::whereIn('id', $ids)->pluck('pre_alloction_code');
            $pre_alloctions = preAllocationLists::whereIn('pre_alloction_code', $pre_alloction_codes)->get();
            foreach ($pre_alloctions as $pre_alloction) {
                $num = preAllocationDetail::where('pre_alloction_code', $pre_alloction->pre_alloction_code)->where('receiver_id', '>', 0)->sum('pre_num');
                // 已领取任务数
                $pre_alloction->update(['received_num' => $num, 'allocation_status' => 1]);
            }
            DB::commit();
            return $task;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
            return false;
        }
    }

    function taskSearch($params, $export = false)
    {
        $model = new WmsAllocationTask();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            if ($params['number'] ?? '') {
                $model = $model->where(function ($query) use ($params) {
                    $pre = preAllocationLists::whereHas('shippingRequest', function ($query) use ($params) {
                        $query->where('third_no', $params['number'])->orWhere('deliver_no', $params['number']);
                    })->pluck('pre_alloction_code');
                    $query->where('code', $params['number']);
                    if ($pre) {
                        $query->orWhereIn('pre_alloction_code', $pre);
                    }
                });
            }
            $where = self::filterEmptyData($params, ['receiver_id', 'warehouse_code', 'code']);
            if ($where) $model = $model->where($where);
            if ($params['alloction_status'] ?? []) $model->whereIn('alloction_status', $params['alloction_status']);
            if ($params['status'] ?? []) $model->whereIn('status', $params['status']);
            return $model->where('status', '<>', 3)->with(['ReceiveUser', 'warehouse'])->orderBy('created_at', 'desc');
        });
        foreach ($list['data'] as &$item) {
            if ($export) {
                $item['group_no'] .= "\t";
            }
            $item['created_at'] = date('Y-m-d H:i:s', strtotime($item['created_at']));
            $item['updated_at'] = date('Y-m-d H:i:s', strtotime($item['updated_at']));
            $item['receive_user'] = $item['receive_user']['username'] ?? '';
            $item['warehouse_name'] = $item['warehouse'];
            unset($item['warehouse']);
        }
        return $list;
    }

    // 任务 - 取消领取
    function taskCancel($params)
    {
        if ($params['id'] ?? 0) {
            $task = WmsAllocationTask::where('id', $params['id'])->where('receiver_id', ADMIN_INFO['user_id'])->first();
        } else {
            $task = WmsAllocationTask::where('code', $params['task_code'])->where('receiver_id', ADMIN_INFO['user_id'])->first();
        }
        if (!$task) return true;
        if ($task->status != WmsAllocationTask::STASH) {
            $this->setErrorMsg(__('admin.wms.task.allocating'));
            return false;
        }

        try {
            DB::beginTransaction();
            // 取消任务单
            $task->update(['status' => WmsAllocationTask::CANCEL]);
            $where = [
                'task_code' => $task->code,
                'receiver_id' => ADMIN_INFO['user_id'],
                'alloction_status' => preAllocationDetail::WAIT_ALLOCATE
            ];
            $pre_alloction_codes = preAllocationDetail::where($where)->pluck('pre_alloction_code');

            // 配货单回退到待领取状态
            preAllocationDetail::where($where)
                ->update([
                    'alloction_status' => preAllocationDetail::WAIT_RECEIVER,
                    'task_code' => '',
                    'receiver_id' => 0,
                ]);

            $pre_alloctions = preAllocationLists::whereIn('pre_alloction_code', $pre_alloction_codes)->get();
            foreach ($pre_alloctions as $pre_alloction) {

                $num = preAllocationDetail::where('pre_alloction_code', $pre_alloction->pre_alloction_code)->where('receiver_id', '>', 0)->sum('pre_num');
                // 已领取任务数
                $pre_alloction->update(['received_num' => $num, 'allocation_status' => 1]);
            }
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }

        return true;
    }

    // 自定义领取配货任务
    function receiveCustome($params)
    {
        unset($params['ss']);
        // 找到满足条件的待分配订单
        // warehouse_code num mode order_channel startegy_code product_sn spu_id bar_code sku product_num order_type begin_location_code end_location_code remark
        $model = preAllocationDetail::where('alloction_status', 2);
        if ($params['warehouse_code']) {
            $model->where('warehouse_code', $params['warehouse_code']);
        }
        if ($params['startegy_code'] ?? '') {
            $model->where('task_strategy_code', $params['startegy_code']);
        }
        if ($params['sup_id'] ?? 0) {
            $model->where('sup_id', $params['sup_id']);
        }
        if ($params['bar_code'] ?? '') {
            $model->where('bar_code', $params['bar_code']);
        }
        if ($params['begin_location_code'] ?? '') {
            $model->where('location_code', '>=', $params['begin_location_code']);
        }
        if ($params['end_location_code'] ?? '') {
            $model->where('location_code', '<=', $params['end_location_code']);
        }
        if ($params['order_type'] ?? '') {
            $model->whereHas('list', function ($query) use ($params) {
                $query->where('type', $params['order_type']);
            });
        }
        if ($params['order_channel'] ?? '') {
            $model->whereHas('shippingRequest', function ($query) use ($params) {
                $query->where('order_channel', $params['order_channel']);
            });
        }
        if (($params['sku'] ?? '') || ($params['product_sn'] ?? '')) {
            $model->whereHas('property', function ($query) use ($params) {
                if ($params['sku'] ?? '') {
                    $query->where('spec_one', $params['sku']);
                }
                if ($params['product_sn'] ?? '') {
                    $query->whereHas('product', function ($query) use ($params) {
                        $query->where('product_sn', $params['product_sn']);
                    });
                }
            });
        }
        // TODO 待确认product_num
        $details = $model->limit($params['num'])->orderBy('location_code', 'asc')->get();
        if (!$details->count()) {
            $this->setErrorMsg(__('admin.wms.task.no_task'));
            return false;
        }

        $this->_addTask($details, $params);
        return true;
    }

    // 配货任务详情
    function info($id)
    {
        $task = WmsAllocationTask::with(['taskDetail', 'taskDetail.property', 'taskDetail.shippingRequest', 'taskDetail.recvDetail', 'warehouse', 'ReceiveUser', 'createUser', 'updateUser'])->find($id)->toArray();
        $detail = [];
        $order_at = '';
        foreach ($task['task_detail'] as $item) {
            $product = $item['property']['product'] ?? [];
            $detail[] = [
                'id' => $item['id'],
                'location_code' => $item['location_code'],
                'batch_no' => $item['batch_no'],
                'uniq_code' => $item['uniq_code'],
                'bar_code' => $item['bar_code'],
                'pre_num' => $item['pre_num'],
                'actual_num' => $item['actual_num'],
                // 'img' => ($product['img'] ?? '') ? env('ALIYUN_OSS_HOST', '') . $product['img'] : '',
                'img' => $product['img'] ?? '',
                'product_sn' => $product['product_sn'] ?? '',
                'sku' => $item['property']['sku'] ?? '',
                'name' => $product['name'] ?? '',
                'spec_one' => $item['property']['spec_one'] ?? '',
                'pre_alloction_code' => $item['pre_alloction_code'],
                'pre_alloction_id' => preAllocationLists::where('pre_alloction_code', $item['pre_alloction_code'])->where('status', 1)->orderBy('id', 'desc')->first()->id ?? 0,
                'deliver_no' => $item['shipping_request']['deliver_no'] ?? '',
                'request_code' => $item['shipping_request']['request_code'] ?? '',
                'request_id' => $item['shipping_request']['id'] ?? '',
                'third_no' => $item['shipping_request']['third_no'] ?? '',
                'erp_no' => $item['shipping_request']['erp_no'] ?? '',
                'quality_level' => $item['recv_detail']['quality_level'] ?? '',
                'quality_type' => $item['recv_detail']['quality_type'] ?? '',
            ];
            $order_at = $item['created_at'];
        }
        $task['order_at'] = $order_at;
        $task['warehouse_name'] = $task['warehouse']['warehouse_name'];
        $task['receive_user'] = $task['receive_user']['username'] ?? '';
        $task['create_user'] = $task['create_user']['username'] ?? '';
        $task['update_user'] = $task['update_user']['username'] ?? '';
        unset($task['task_detail']);
        unset($task['warehouse']);
        $logs = [];
        return compact('task', 'detail', 'logs');
    }

    // 配货任务单详情，按位置码汇总展示
    function taskDetail($params)
    {
        $task = WmsAllocationTask::with(['taskDetail', 'taskDetail.property'])->where('code', $params['task_code'])->first()->toArray();
        $detail = [];
        foreach ($task['task_detail'] ?? [] as $item) {
            if ($item['cancel_status'] > 0) continue;
            $detail[$item['location_code']][] = [
                'location_code' => $item['location_code'],
                'pre_num' => $item['pre_num'],
                'batch_no' => $item['batch_no'],
                'actual_num' => $item['actual_num'],
                'uniq_code' => $item['uniq_code'],
                'sku' => $item['property']['sku'] ?? '',
                'spec_one' => $item['property']['spec_one'] ?? '',
            ];
        }
        $data = [];
        foreach ($detail as $location_code => $skus) {
            $data['detail'][] = compact('location_code', 'skus');
        }
        $data['task_code'] = $task['code'];
        return $data;
    }

    function taskSave($params)
    {
        if (!($params['remark'])) {
            return true;
        }
        WmsAllocationTask::where('id', $params['id'])->update([
            'remark' => $params['remark'],
            'admin_user_id' => ADMIN_INFO['user_id'],
        ]);
        return true;
    }



    // 执行配货
    function allocate($params)
    {
        $location_code_required = 0;
        if (isset($params['location_code'])) $location_code_required = 1;
        $location_code = $params['location_code'] ?? '';
        if ($params['id'] ?? 0) {
            $task = WmsAllocationTask::find($params['id']);
        } else {
            $task = WmsAllocationTask::where('code', $params['task_code'])->first();
        }
        if (!$task || $task->receiver_id != ADMIN_INFO['user_id']) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }
        if ($task->alloction_status == WmsAllocationTask::ALLOCATE_DONE) {
            return [
                'completed' => true,
                'total' => $task->total_num,
                'selected_num' => $task->total_num,
            ];
        }
        if ($task->status == WmsAllocationTask::CANCEL) {
            $this->setErrorMsg(__('tips.task_cancel'));
            return false;
        }
        $actual_num = 0;
        try {
            DB::beginTransaction();
            $recv = Inventory::where('uniq_code', $params['uniq_code'])->where('recv_num', '>', 0)->lockForUpdate()->first();
            if (!$recv || !in_array($recv->sale_status, [1, 2])) {
                DB::rollBack();
                $this->setErrorMsg(__('admin.wms.product.can_not_sale'));
                return false;
            }
            if ($location_code_required  && $recv->location_code != $location_code) {
                DB::rollBack();
                $this->setErrorMsg(__('admin.wms.allocate.not_match'));
                return false;
            }

            if ($params['type'] == 1 && false == self::barcodeIsUnique($recv->bar_code)) {
                DB::rollBack();
                $this->setErrorMsg(__('tips.normal_to_unique'));
                return false;
            }


            $where = [
                'bar_code' => $recv->bar_code,
                'receiver_id' =>  ADMIN_INFO['user_id'],
                'warehouse_code' => $recv->warehouse_code,
                'task_code' => $task->code,
            ];
            $pre_inv_ids = preAllocationDetail::where($where)
                ->where('alloction_status', preAllocationDetail::WAIT_ALLOCATE)
                ->pluck('pre_inv_id');
            if ($pre_inv_ids) $pre_inv_ids = $pre_inv_ids->toArray();


            //配货明细

            $pre_detail = preAllocationDetail::where($where)
                ->whereIn('alloction_status', [preAllocationDetail::WAIT_ALLOCATE, preAllocationDetail::ALLOCATING])
                ->whereRaw('pre_num>actual_num');
            $pre_uniq_code = $pre_detail->pluck('uniq_code')->toArray();
            $pre_inv_ids = $pre_detail->pluck('id', 'pre_inv_id')->toArray();
            //判断预配时是否指定了唯一码
            $is_appoint = in_array($params['uniq_code'], $pre_uniq_code);
            if ($is_appoint || in_array('', $pre_uniq_code)) {
                if ($is_appoint) {
                    $detail = $pre_detail->where('uniq_code', $params['uniq_code'])->first();
                    $is_pre_inv_id = 1;
                } else {
                    //查找当前唯一码的供应商批次号采购价等是否符合配货订单中的商品
                    if (isset($pre_inv_ids[$recv->id])) {
                        $detail = $pre_detail->where('id', $pre_inv_ids[$recv->id])->first();
                    } else {
                        $detail = $pre_detail->where('uniq_code', '')
                            ->where('batch_no', $recv->lot_num)->where('buy_price', $recv->buy_price)->where('sup_id', $recv->sup_id)
                            ->where('location_code', $recv->location_code)
                            ->where('quality_level', $recv->quality_level)->where('quality_type', $recv->getRawOriginal('quality_type'))->first();
                        // $pre_ids =  $pre_details->pluck('pre_inv_id')->toArray();
                        // $detail  =  $pre_details ->first();
                    }
                }
            } else {
                if ($is_appoint) {
                    $this->setErrorMsg(__('tips.order_assign_unique'));
                } else {
                    $this->setErrorMsg(__('admin.wms.allocate.product_order_not_match'));
                }
                // 唯一码不匹配
                DB::rollBack();
                return false;
            }


            // // 是预配的商品
            // $model = preAllocationDetail::where($where)
            //     ->whereIn('alloction_status', [preAllocationDetail::WAIT_ALLOCATE, preAllocationDetail::ALLOCATING])
            //     ->where('pre_inv_id', $recv->id)
            //     ->whereRaw('pre_num>actual_num');
            // $detail = $model->lockForUpdate()->first();

            // 不是预配的商品
            // if (!$detail) {
            //     $model = preAllocationDetail::where($where)
            //         ->whereIn('alloction_status', [preAllocationDetail::WAIT_ALLOCATE, preAllocationDetail::ALLOCATING])
            //         ->whereRaw('pre_num>actual_num');
            //     $detail = $model->lockForUpdate()->first();
            // }
            if (!$detail) {
                DB::rollBack();
                $this->setErrorMsg(__('admin.wms.allocate.product_order_not_match'));
                return false;
            }
            if (!isset($is_pre_inv_id)) {
                $is_pre_inv_id =  $detail->pre_inv_id == $recv->id ? 1 : 0;
            }
            // 有预配商品且配货商品在预配商品中 或 没有预配商品
            // $check = ($pre_inv_ids && (!in_array($recv->id, $pre_inv_ids))) || (!$pre_inv_ids);
            if ($recv->sale_status != Inventory::SALE_STATUS_ENABLE && $is_pre_inv_id == 0) {
                //配货和预配的商品均为锁定状态时 查找配货商品锁定的订单
                $edit_pre =  preAllocationDetail::where('bar_code', $recv->bar_code)->where('warehouse_code', $recv->warehouse_code)->where('pre_inv_id', $recv->id)
                    ->where('location_code', $recv->location_code)->whereIn('alloction_status', [preAllocationDetail::WAIT_GROUP, preAllocationDetail::WAIT_RECEIVER, preAllocationDetail::WAIT_ALLOCATE, preAllocationDetail::ALLOCATING])
                    ->whereRaw('pre_num>actual_num')->lockForUpdate()->first();
                if ($edit_pre) {
                    $edit_pre->pre_inv_id = $detail->pre_inv_id;
                    $edit_pre->save();
                    $detail->pre_inv_id = $recv->id;
                    $detail->save();
                    //修改库存锁定订单
                    $is_pre_inv_id = 1;
                    $pre_lock_order = [
                        'lock_type' => $recv->lock_type,
                        'lock_code' => $recv->lock_code,
                    ];
                    $pre_inv = Inventory::where('id', $edit_pre->pre_inv_id)->first();
                    $recv_lock_order = [
                        'lock_type' => $pre_inv->lock_type,
                        'lock_code' => $pre_inv->lock_code,
                    ];
                    $pre_inv->update($pre_lock_order);
                    $recv->update($recv_lock_order);
                } else {
                    DB::rollBack();
                    $this->setErrorMsg(__('admin.wms.product.can_not_sale'));
                    return false;
                }
            }
            // 检查配货商品的批次号、供应商、成本价是否跟预配商品一致
            // $pres = Inventory::whereIn('id', $pre_inv_ids)->selecRaw('id,concat(lot_num,"_",sup_id,"_",buy_price) as skey')->get()->keyBy('skey');
            // $skey = sprintf('%s_%s_%s', $recv->lot_num, $recv->sup_id, $recv->buy_price);
            // if (!($pres[$skey] ?? [])) {
            //     DB::rollBack();
            //     $this->setErrorMsg('商品不满足出库申请的条件');
            //     return false;
            // }

            if ($task->status == WmsAllocationTask::STASH) {
                $task->update(['status' => WmsAllocationTask::CHECKED]);
            }
            //非预配时更新锁定库存
            if ($is_pre_inv_id == 0) {
                $pre_item = Inventory::where('id', $detail->pre_inv_id)->first();
                Inventory::lockInv([$recv->id], $pre_item->lock_type ?? 0, $pre_item->lock_code ?? '');
                if ($detail->pre_inv_id) Inventory::releaseInv([$detail->pre_inv_id]);
                $detail->update(['pre_inv_id' => $recv->id]);
            }
            // 更新商品库存的销售状态
            // if ($recv->id != $detail->pre_inv_id) {
            //     // 配货的商品预配的订单清空
            //     preAllocationDetail::where('alloction_status', preAllocationDetail::WAIT_ALLOCATE)
            //         ->where('cancel_status', 0)
            //         ->where('pre_inv_id', $recv->id)
            //         ->where('id', '<>', $detail->id)
            //         ->where('warehouse_code', $recv->warehouse_code)
            //         ->first();

            //     // 原锁定的库存释放
            //     if ($detail->pre_inv_id) {
            //         Inventory::releaseInv([$detail->pre_inv_id]);
            //         $detail->update(['pre_inv_id' => $recv->id]);
            //     }
            // }
            $request = $detail->shippingRequest;
            // 调拨单，修改库存的调拨状态
            if ($request && $request->type == ObOrder::TYPE_TRANSFER) {
                $recv->update(['in_wh_status' => 5, 'inv_status' => 8,]);
            } else {
                //库存修改为待发
                $recv->update(['sale_status' => 3, 'inv_status' => 7, 'in_wh_status' => 9]);
                //更新供应商库存
                // SupInv::supInvUpdate('', null, $recv->id);
            }

            //更新总库存
            // Inventory::totalInvUpdate($recv->warehouse_code, $recv->bar_code);

            $actual_num = $detail->actual_num + 1;
            $status = $detail->pre_num > $actual_num ? preAllocationDetail::ALLOCATING : preAllocationDetail::WAIT_REVIEW;
            // 更新配货单明细
            $detail->update([
                'actual_num' => $actual_num,
                'allocated_at' => date('Y-m-d H:i:s'),
                'alloction_status' => $status,
                'uniq_code' => $params['uniq_code'],
                'buy_price' => $recv->buy_price,
            ]);
            //查询位置码是否空闲
            if (!$location_code) $location_code = $recv->location_code;
            $location = WarehouseLocation::where('warehouse_code', $detail->warehouse_code)->where('location_code', $location_code)->first();
            $is_able_count = Inventory::where('in_wh_status', 3)->whereNotIn('sale_status', [3, 4])->where('warehouse_code', $detail->warehouse_code)->where('location_code', $location_code)->limit(2)->count();
            if ($is_able_count == 0) {
                if ($location && $location->is_able != 1) {
                    $location->is_able = 1;
                    $location->save();
                }
            }
            // 更新配货任务单状态
            if ($task->alloction_status == WmsAllocationTask::ALLOCATE_WAIT) {
                $task->update([
                    'alloction_status' => WmsAllocationTask::ALLOCATE_ING,
                    'start_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // 更新出库明细
            $shipping_detail = ShippingDetail::where('request_code', $detail->request_code)
                ->where('batch_no', $detail->batch_no)
                ->where('bar_code', $recv->bar_code)->first();
            if (!$shipping_detail) {
                $shipping_detail = ShippingDetail::where('request_code', $detail->request_code)
                    ->where('bar_code', $recv->bar_code)->first();
            }
            if ($shipping_detail->status == 0) {
                $shipping_detail->update(['status' => ShippingDetail::ALLOCATING,]);
            }

            // 任务单 - 配货完成
            $num = preAllocationDetail::where('task_code', $task->code)->where('alloction_status', preAllocationDetail::WAIT_ALLOCATE)->count();
            if (!$num) {
                $task->update(['alloction_status' => WmsAllocationTask::ALLOCATE_DONE, 'confirm_at' => date('Y-m-d H:i:s')]);
            }

            $request_code = $detail->request_code;

            $ob_item = ObOrder::where('request_code', $request_code)->whereIn('status', [1, 2])->orderBy('id', 'desc')->first();
            // 开始配货，出库需求单发货状态变为配货中
            $find = preAllocationDetail::where('request_code', $request_code)->whereIn('alloction_status', [4, 5, 6, 7])->first();
            if ($find) {
                // 出库需求单 - 配货中
                if ($ob_item->request_status == 1) {
                    $ob_item->update(['status' => ObOrder::STATUS_PASS, 'request_status' => ObOrder::ALLOCATE]);
                }
                // 配货订单 - 配货中
                preAllocationLists::where('request_code', $request_code)
                    ->where('status', preAllocationLists::AUDITED)
                    ->where('allocation_status', preAllocationLists::WAIT_ALLOCATE)
                    ->update(['allocation_status' => preAllocationLists::ALLOCATING]);
            }

            // 配货完成
            $find = preAllocationDetail::where('request_code', $request_code)->where('alloction_status', preAllocationDetail::WAIT_ALLOCATE)->first();
            if (!$find) {
                // 出库需求单 - 发货中
                if ($ob_item) {
                    $ob_item->update(['request_status' => ObOrder::ON_DELIVERY]);
                    // 出库明细 - 发货中
                    ShippingDetail::where('request_code', $request_code)
                        ->where('status', ShippingDetail::ALLOCATING)
                        ->update(['status' => ShippingDetail::SHIPPING,]);
                }
            }


            // 配货完成
            // $num = preAllocationDetail::where('request_code', $detail->request_code)->where('alloction_status', preAllocationDetail::WAIT_ALLOCATE)->count();
            // if (!$num) {
            //     // 出库需求单 - 发货中
            //     $ob_item = ObOrder::where('request_code', $detail->request_code)->where('status', 2)->orderBy('id', 'desc')->first();

            //     if ($ob_item) {
            //         $ob_item->update(['request_status' => ObOrder::ON_DELIVERY]);
            //         // 出库明细 - 发货中
            //         ShippingDetail::where('request_code', $detail->request_code)
            //             ->where('status', ShippingDetail::ALLOCATING)
            //             ->update(['status' => ShippingDetail::SHIPPING,]);

            //         //调拨 - 发货中
            //         TransferOrder::where('tr_code', $ob_item->third_no)->where('doc_status', 2)->update(['send_status' => 2]);
            //         //其他出库 - 发货中
            //         OtherObOrder::where('oob_code', $ob_item->third_no)->where('doc_status', 2)->update(['send_status' => 2]);
            //         //销售 - 发货中
            //         WmsOrder::where('code', $ob_item->source_code)->where('status', WmsOrder::PASS)->update(['deliver_status' => WmsOrder::ON_DELIVERY]);
            //     }

            //     // 任务单 - 配货完成
            //     // $task->update(['alloction_status' => WmsAllocationTask::ALLOCATE_DONE, 'confirm_at' => date('Y-m-d H:i:s')]);
            //     // 配货订单 - 已配货、实配数量
            //     $pres = preAllocationLists::where('request_code', $detail->request_code)
            //         ->where('status', preAllocationLists::AUDITED)
            //         ->whereIn('allocation_status', [preAllocationLists::WAIT_ALLOCATE, preAllocationLists::ALLOCATING])->get();
            //     foreach ($pres as $pre) {
            //         $num = preAllocationDetail::where('pre_alloction_code', $pre->pre_alloction_code)
            //             ->where('cancel_status', 0)
            //             ->whereIn('alloction_status', [4, 5, 6, 7])
            //             ->sum('actual_num');
            //         $pre->update(['allocation_status' => preAllocationLists::ALLOCATED, 'actual_num' => $num]);
            //     }
            // }

            DB::commit();
            // WmsStockLog::add(WmsStockLog::ORDER_ALLOCATE, $params['uniq_code'], $detail->pre_alloction_code);
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }

        // 配货状态
        $data = [
            'params' => ['tenant_id' => ADMIN_INFO['tenant_id'], 'request_code' => $detail->request_code, 'task_code' => $task->code],
            'class' => 'App\Logics\wms\AllocationTask',
            'method' => 'allocateStatusUpdate3',
            'time' => date('Y-m-d H:i:s'),
        ];
        Redis::rpush(RedisKey::QUEUE_AYSNC_HADNLE, json_encode($data));

        // 库存数据
        $data = [
            'params' => ['tenant_id' => ADMIN_INFO['tenant_id'], 'recv_id' => $recv->id, 'warehouse_code' => $recv->warehouse_code, 'bar_code' => $recv->bar_code],
            'class' => 'App\Logics\wms\AllocationTask',
            'method' => 'allocateInvUpdate',
            'time' => date('Y-m-d H:i:s'),
        ];
        Redis::rpush(RedisKey::QUEUE2_AYSNC_HADNLE, json_encode($data));

        // 操作日志
        $data = [
            'params' => ['tenant_id' => ADMIN_INFO['tenant_id'], 'type' => WmsStockLog::ORDER_ALLOCATE, 'uniq_code' => $params['uniq_code'], 'code' => $detail->pre_alloction_code, 'user_id' => ADMIN_INFO['user_id']],
            'class' => 'App\Logics\wms\AllocationTask',
            'method' => 'addLog',
            'time' => date('Y-m-d H:i:s'),
        ];
        Redis::rpush(RedisKey::QUEUE2_AYSNC_HADNLE, json_encode($data));


        $completed = $task->alloction_status == WmsAllocationTask::ALLOCATE_DONE ? true : false;
        $selected_num = preAllocationDetail::where('task_code', $task->code)->whereIn('alloction_status', [4, 5, 6, 7])->where('cancel_status', 0)->sum('actual_num');
        return [
            'completed' => $completed,
            'total' => $task->total_num,
            'selected_num' => $selected_num,
        ];;
    }

    function addLog($params)
    {
        WmsStockLog::add($params['type'], $params['uniq_code'], $params['code']);
    }

    function allocateInvUpdate($params)
    {
        SupInv::supInvUpdate('', null, $params['recv_id']);
        Inventory::totalInvUpdate($params['warehouse_code'], $params['bar_code']);
    }


    function allocateStatusUpdate3($params)
    {
        $request_code = $params['request_code'];
        $task_code = $params['task_code'];
        try {
            DB::beginTransaction();
            // 配货完成
            $num = preAllocationDetail::where('request_code', $request_code)->where('alloction_status', preAllocationDetail::WAIT_ALLOCATE)->count();
            if (!$num) {
                // 出库需求单 - 发货中
                $ob_item = ObOrder::where('request_code', $request_code)->where('status', 2)->orderBy('id', 'desc')->first();

                if (in_array($ob_item->request_status, [3, 4])) {
                    // 1-销售出库 2-调拨出库 3-其他出库
                    if ($ob_item->type == 1) {
                        // 调拨发货中
                        WmsOrder::where('code', $ob_item->source_code)->where('status', WmsOrder::PASS)->update(['deliver_status' => WmsOrder::ON_DELIVERY]);
                    }
                    if ($ob_item->type == 2) {
                        //调拨 - 发货中
                        TransferOrder::where('tr_code', $ob_item->third_no)->where('doc_status', 2)->update(['send_status' => 2]);
                    }
                    if ($ob_item->type == 3) {
                        //其他出库 - 发货中
                        OtherObOrder::where('oob_code', $ob_item->third_no)->where('doc_status', 2)->update(['send_status' => 2]);
                    }
                }

                // 配货订单 - 已配货、实配数量
                $pres = preAllocationLists::where('request_code', $request_code)
                    ->where('status', preAllocationLists::AUDITED)
                    ->whereIn('allocation_status', [preAllocationLists::WAIT_ALLOCATE, preAllocationLists::ALLOCATING])->get();
                foreach ($pres as $pre) {
                    $num = preAllocationDetail::where('pre_alloction_code', $pre->pre_alloction_code)
                        ->where('cancel_status', 0)
                        ->whereIn('alloction_status', [4, 5, 6, 7])
                        ->sum('actual_num');
                    $pre->update(['allocation_status' => preAllocationLists::ALLOCATED, 'actual_num' => $num]);
                }
            }

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    function allocateStatusUpdate($params)
    {
        $request_code = $params['request_code'];
        $task_code = $params['task_code'];
        try {
            DB::beginTransaction();
            // 开始配货，出库需求单发货状态变为配货中
            $num = preAllocationDetail::where('request_code', $request_code)->whereIn('alloction_status', [4, 5, 6, 7])->count();
            if ($num >= 1) {
                // 出库需求单 - 配货中
                ObOrder::where(['request_code' => $request_code, 'request_status' => 1])->update([
                    'status' => ObOrder::STATUS_PASS, 'request_status' => ObOrder::ALLOCATE
                ]);
                // 配货订单 - 配货中
                preAllocationLists::where('request_code', $request_code)
                    ->where('status', preAllocationLists::AUDITED)
                    ->where('allocation_status', preAllocationLists::WAIT_ALLOCATE)
                    ->update(['allocation_status' => preAllocationLists::ALLOCATING]);
            }

            // 配货完成
            $num = preAllocationDetail::where('request_code', $request_code)->where('alloction_status', preAllocationDetail::WAIT_ALLOCATE)->count();
            if (!$num) {
                // 出库需求单 - 发货中
                $ob_item = ObOrder::where('request_code', $request_code)->where('status', 2)->orderBy('id', 'desc')->first();

                if ($ob_item) {
                    $ob_item->update(['request_status' => ObOrder::ON_DELIVERY]);
                    // 出库明细 - 发货中
                    ShippingDetail::where('request_code', $request_code)
                        ->where('status', ShippingDetail::ALLOCATING)
                        ->update(['status' => ShippingDetail::SHIPPING,]);
                    // 1-销售出库 2-调拨出库 3-其他出库
                    if ($ob_item->type == 1) {
                        // 调拨发货中
                        WmsOrder::where('code', $ob_item->source_code)->where('status', WmsOrder::PASS)->update(['deliver_status' => WmsOrder::ON_DELIVERY]);
                    }
                    if ($ob_item->type == 2) {
                        //调拨 - 发货中
                        TransferOrder::where('tr_code', $ob_item->third_no)->where('doc_status', 2)->update(['send_status' => 2]);
                    }
                    if ($ob_item->type == 3) {
                        //其他出库 - 发货中
                        OtherObOrder::where('oob_code', $ob_item->third_no)->where('doc_status', 2)->update(['send_status' => 2]);
                    }
                }

                // 任务单 - 配货完成
                // $task->update(['alloction_status' => WmsAllocationTask::ALLOCATE_DONE, 'confirm_at' => date('Y-m-d H:i:s')]);
                // 配货订单 - 已配货、实配数量
                $pres = preAllocationLists::where('request_code', $request_code)
                    ->where('status', preAllocationLists::AUDITED)
                    ->whereIn('allocation_status', [preAllocationLists::WAIT_ALLOCATE, preAllocationLists::ALLOCATING])->get();
                foreach ($pres as $pre) {
                    $num = preAllocationDetail::where('pre_alloction_code', $pre->pre_alloction_code)
                        ->where('cancel_status', 0)
                        ->whereIn('alloction_status', [4, 5, 6, 7])
                        ->sum('actual_num');
                    $pre->update(['allocation_status' => preAllocationLists::ALLOCATED, 'actual_num' => $num]);
                }
            }

            // // 任务单 - 配货完成
            // $num = preAllocationDetail::where('task_code', $task_code)->where('alloction_status', preAllocationDetail::WAIT_ALLOCATE)->count();
            // if (!$num) {
            //     WmsAllocationTask::where('code', $params['task_code'])->update(['alloction_status' => WmsAllocationTask::ALLOCATE_DONE, 'confirm_at' => date('Y-m-d H:i:s')]);
            // }
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 唯一码产品、普通产品配货
    function allocate2($params)
    {
        if (false == $this->productTypeVerify($params)) return false;
        // 唯一码产品直接配货
        if ($params['type'] == 1) return $this->allocate($params);

        // 普通产品，先根据条形码获取到可用的唯一码，然后再进行配货
        $where = self::filterEmptyData($params, ['warehouse_code', 'bar_code', 'location_code']);
        $inventory = Inventory::where($where)->where('in_wh_status', 3)->whereIn('inv_status', [5, 6])->first();
        if (!$inventory) {
            $this->setErrorMsg(__('tips.stock_wait_confirm'));
            return false;
        }

        $params['uniq_code'] = $inventory->uniq_code;
        return $this->allocate($params);
    }

    // 配货保存
    function taskDone($params)
    {
        WmsAllocationTask::where('id', $params['id'])
            ->where('alloction_status', WmsAllocationTask::ALLOCATE_DONE)
            ->where('status', WmsAllocationTask::CHECKED)
            ->update([
                'confirm_at' => date('Y-m-d H:i:s'),
                'admin_user_id' => ADMIN_INFO['user_id'],
            ]);
        return true;
    }

    // 任务单发货
    function taskSendOut($params)
    {
        $task = WmsAllocationTask::with(['taskDetail', 'taskDetail.recvDetail', 'warehouse'])->find($params['id'])->toArray();

        $num = preAllocationDetail::where('task_code', $task['code'])->selectRaw('sum(pre_num) as pre_num')->selectRaw('sum(actual_num) as actual_num')->first()->toArray();
        if (!$num['pre_num']) {
            $this->setErrorMsg(__('admin.wms.deliver.no_product'));
            return false;
        }

        if ($num['pre_num'] > 0 && $num['pre_num'] != $num['actual_num']) {
            $this->setErrorMsg(__('admin.wms.deliver.allocating'));
            return false;
        }

        $warehouse_name = $task['warehouse']['warehouse_name'];
        $warehouse_code = $task['warehouse_code'];

        $uniq_codes = [];
        $arr = [];
        foreach ($task['task_detail'] as $item) {
            $key = $item['request_code'];
            $arr[$key]['skus'][] = $item['bar_code'];
            $arr[$key]['actual_num'] = ($arr[$key]['actual_num'] ?? 0) + $item['actual_num'];
            if (($item['recv_detail']['quality_level'] ?? '') == 'A') {
                $arr[$key]['quality_num'] = ($arr[$key]['quality_num'] ?? 0) + $item['actual_num'];
            } else {
                $arr[$key]['defects_num'] = ($arr[$key]['defects_num'] ?? 0) + $item['actual_num'];
            }
            $uniq_codes[] = $item['uniq_code'];
        }

        try {
            DB::beginTransaction();
            foreach ($arr as $request_code => $item) {
                WmsShippingOrder::create([
                    'warehouse_code' => $warehouse_code,
                    'warehouse_name' => $warehouse_name,
                    'ship_code' => self::shippingCode(),
                    'request_code' => $request_code,
                    'sku_num' => count(array_unique($item['skus'])),
                    'actual_num' => $item['actual_num'],
                    'quality_num' => $item['quality_num'] ?? 0,
                    'defects_num' => $item['defects_num'] ?? 0,
                    'shipper_id' => ADMIN_INFO['user_id'],
                    'shipped_at' => date('Y-m-d H:i:s'),
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                ]);

                ShippingDetail::where('request_code', $request_code)->whereIn('uniq_code', $uniq_codes)->update([
                    'status' => ShippingDetail::SHIPPED,
                ]);
                $find = ShippingDetail::where('request_code', $request_code)->where('status', '<>', ShippingDetail::SHIPPED)->first();

                // 全部已发货，更新出库需求单状态
                if (!$find) {
                    ObOrder::where('request_code', $request_code)->update([
                        'status' => ObOrder::STATUS_CONFIRMED, 'request_status' => ObOrder::DELIVERED,
                    ]);
                }
            }
            WmsPreAllocationDetail::where('task_code', $task['code'])->update([
                'alloction_status' => WmsPreAllocationDetail::DELIVERED,
            ]);

            // 变更唯一码的销售状态
            foreach ($uniq_codes as $uniq_code) {
                $detail = Inventory::where('uniq_code', $uniq_code)->orderBy('id', 'desc')->first();
                $detail->update([
                    'sale_status' => Inventory::SALE_STATUS_SHIPPED,
                    'in_wh_status' => Inventory::OUT,
                ]);
                //更新总库存
                if (!empty($detail->bar_code)) Inventory::totalInvUpdate($warehouse_code, $detail->bar_code);

                // WmsStockLog::add(WmsStockLog::ORDER_ALLOCATE, $uniq_code, $detail->pre_alloction_code);
            }
            //供应商更新
            if ($uniq_codes) SupInv::supInvUpdate($uniq_codes);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    static function shippingCode()
    {
        return 'FHD' . date('ymdHis') . rand(1000, 9999);
    }

    // 复核时生成出库取消单
    function cancelWhenReview($request, $transaction = true)
    {
        $cancel = WmsShippingCancel::where([
            'request_code' => $request->request_code, 'warehouse_code' => $request->warehouse_code,
            'status' => WmsShippingCancel::CONFIRMED,
        ])->first();

        // 已经生成，不再处理
        if ($cancel) return true;

        try {
            if ($transaction) DB::beginTransaction();
            // 未生成，直接创建
            $cancel = WmsShippingCancel::add($request, WmsShippingCancel::METHOD_PUTAWAY);
            $pres = preAllocationLists::where('request_code', $request->request_code)->where('status', 1)->where('allocation_status', 3)
                ->get();
            foreach ($pres as $pre) {
                $items = $pre->details;
                foreach ($items as $item) {
                    if ($item->alloction_status == 5) {
                        // 释放预配库存
                        Inventory::releaseInv([$item->pre_inv_id]);
                        // 更新配货信息
                        $item->update([
                            'cancel_num' => $item->cancel_num,
                            'cancel_status' => preAllocationDetail::CANCEL_WAIT_PUTAWAY,
                            'canceled_at' => date('Y-m-d H:i:s'),
                        ]);
                        // 取消出库流水
                        WmsStockLog::add(WmsStockLog::ORDER_CANCEL_OUT, $item->uniq_code, $cancel->code, ['request' => $request]);
                        //更新库存状态
                        Inventory::invStatusUpdate($item->uniq_code, 2, 1);
                        //更新供应商状态
                        SupInv::supInvUpdate($item->uniq_code);
                    }
                }
            }
            if ($transaction) DB::commit();
            return true;
        } catch (Exception $e) {
            if ($transaction) DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 按单复核
    function reviewByOrder($params)
    {
        // no uniq_code 唯一码
        $no = $params['no'];
        $request = ObOrder::where('request_code', $no)->orWhere('third_no', $no)->orWhere('erp_no', $no)->first();
        if (!$request) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }

        if ($request->request_status != ObOrder::ON_DELIVERY && $request->request_status != ObOrder::CANCELED) {
            $this->setErrorMsg(__('tips.not_recheck_status'));
            return false;
        }

        $find = preAllocationDetail::where('request_code', $request->request_code)->where('uniq_code', $params['uniq_code'])->first();
        if (!$find) {
            $this->setErrorMsg(__('tips.good_status_error'));
            return false;
        }

        if ($request->request_status == ObOrder::CANCELED) {
            $this->cancelWhenReview($request);
            $this->setErrorMsg(__('tips.cancel_when_review'));
            return false;
        }

        if ($find->alloction_status == preAllocationDetail::WAIT_REVIEW) {
            $find->update([
                'alloction_status' => preAllocationDetail::WAIT_DELIVER,
                'reviewer_id' => ADMIN_INFO['user_id'],
                'review_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $info = $find->property;

        $total = preAllocationDetail::where('request_code', $request->request_code)
            ->whereIn('alloction_status', [preAllocationDetail::WAIT_REVIEW, preAllocationDetail::REVIEWING, preAllocationDetail::WAIT_DELIVER])
            ->selectRaw('sum(actual_num) as actual_num')->first();
        // 已扫描商品
        $scanned_list = preAllocationDetail::where('request_code', $request->request_code)->where('alloction_status', preAllocationDetail::WAIT_DELIVER)->with(['recvDetail', 'property'])->get()->toArray();
        $scanned_arr = [];
        $num_arr = [];
        // 已扫描数量
        $scanned_num = 0;
        foreach ($scanned_list as $item) {
            $key = $item['bar_code'] . '_' . $item['recv_detail']['quality_level'];
            $num_arr[$key] = ($num_arr[$key] ?? 0) + $item['actual_num'];

            $product = $item['property']['product'] ?? [];
            $scanned_arr[$key] = [
                'img' => $product['img'] ?? '',
                'bar_code' => $item['bar_code'],
                'actual_num' => $num_arr[$key],
                'product_sn' => $product['product_sn'] ?? '',
                'name' => $product['name'] ?? '',
                'spec_one' => $item['property']['spec_one'] ?? '',
                'quality_level' => $item['recv_detail']['quality_level'] ?? '',
                'quality_type' => $item['recv_detail']['quality_type'] ?? '',
            ];

            $scanned_num += $item['actual_num'];
        }
        return [
            'img' => $info['product']['img'] ?? '',
            'deliver_type' => '', //物流产品
            'request_code' => $request->request_code,
            'third_no' => $request->third_no,
            'erp_no' => $request->erp_no,
            'scanned_num' => $scanned_num, //已扫描数量
            'total_num' => $total['actual_num'] ?? 0, //剩余应发总数
            'scanned_list' => array_values($scanned_arr),
        ];
    }

    // 复核页面的发货明细
    function reviewDetail($params)
    {
        $request = [];
        $no = $params['no'];
        $scanced_status = [preAllocationDetail::REVIEWING];
        // type 1-按单复核页面 2-整单复核页面
        if ($params['type'] == 1) {
            $request = WmsShippingRequest::where('request_code', $no)->orWhere('third_no', $no)->orWhere('erp_no', $no)->first();
        } elseif ($params['type'] == 2) {
            $request = WmsShippingRequest::where('request_code', $no)->orWhere('deliver_no', $no)->orWhere('erp_no', $no)->first();
            $scanced_status = [preAllocationDetail::REVIEWING, preAllocationDetail::WAIT_REVIEW];
        }

        if (!$request) {
            return [];
        }
        $model = preAllocationDetail::where('request_code', $request->request_code);
        if (($params['product_sn'] ?? '') || ($params['name'] ?? '')) {
            $model->whereHas('property', function ($q) use ($params) {
                if (($params['product_sn'] ?? '') || ($params['name'] ?? '')) {
                    $model2 = Product::whereRaw('1=1');
                    if ($params['product_sn'] ?? '') {
                        $model2->where('product_sn', $params['product_sn']);
                    }
                    if ($params['name'] ?? '') {
                        $model2->where('name', 'like', '%' . $params['name'] . '%');
                    }
                    $product_ids = $model2->pluck('id');
                    if ($product_ids) {
                        $q->whereIn('product_id', $product_ids);
                    } else {
                        return false;
                    }
                }
            });
        }

        $detail = $model->with(['recvDetail', 'shippingRequest', 'property'])->get()->toArray();
        $scanned_arr = [];
        $total = [];
        foreach ($detail as $item) {
            $key = $item['bar_code'] . '_' . $item['recv_detail']['quality_level'];
            $num_arr[$key]['pre_num'] = ($num_arr[$key]['pre_num'] ?? 0) + $item['pre_num'];
            $num_arr[$key]['actual_num'] = ($num_arr[$key]['actual_num'] ?? 0) + $item['actual_num'];
            $num_arr[$key]['cancel_num'] = ($num_arr[$key]['cancel_num'] ?? 0) + $item['cancel_num'];
            if ($item['alloction_status'] == preAllocationDetail::DELIVERED) {
                $num_arr[$key]['shipped_num'] = ($num_arr[$key]['shipped_num'] ?? 0) + $item['actual_num'];
            }
            if (in_array($item['alloction_status'], $scanced_status)) {
                $num_arr[$key]['scaned_num'] = ($num_arr[$key]['shipped_num'] ?? 0) + $item['actual_num'];
            }

            $product = $item['property']['product'] ?? [];
            $scanned_arr[$key] = [
                'img' => $product['img'] ?? '',
                'pre_num' => $num_arr[$key]['pre_num'], //应发总数
                'actual_num' => $num_arr[$key]['actual_num'], //已配货总数
                'cancel_num' => $num_arr[$key]['cancel_num'], //取消总数
                'left_num' => $num_arr[$key]['pre_num'] - ($num_arr[$key]['shipped_num'] ?? 0), //取消总数
                'scaned_num' => $num_arr[$key]['scaned_num'] ?? 0, //当前已扫
                'request_code' => $item['request_code'] ?? '',
                'third_no' => $item['shipping_request']['third_no'] ?? '',
                'erp_no' => $item['shipping_request']['erp_no'] ?? '',
                'status_txt' => '',
                'product_sn' => $product['product_sn'] ?? '',
                'name' => $product['name'] ?? '',
                'spec_one' => $item['property']['spec_one'] ?? '',
                'quality_level' => $item['recv_detail']['quality_level'] ?? '',
                'quality_type' => $item['recv_detail']['quality_type'] ?? '',
                'bar_code' => $item['bar_code'],
            ];
            $total['pre_num'] = ($total['pre_num'] ?? 0) + $scanned_arr[$key]['pre_num'];
            $total['actual_num'] = ($total['actual_num'] ?? 0) + $scanned_arr[$key]['actual_num'];
            $total['cancel_num'] = ($total['cancel_num'] ?? 0) + $scanned_arr[$key]['cancel_num'];
            $total['left_num'] = ($total['left_num'] ?? 0) + $scanned_arr[$key]['left_num'];
            $total['scaned_num'] = ($total['scaned_num'] ?? 0) + $scanned_arr[$key]['scaned_num'];
        }

        return [
            'detail' => array_values($scanned_arr),
            'total' => $total,
            'info' => ['deliver_no' => $request->deliver_no,]
        ];
    }

    // 按单复核发货
    function sendOutByOrder($params)
    {
        $no = $params['no'];
        $request = ObOrder::where('request_code', $no)->orWhere('third_no', $no)->orWhere('erp_no', $no)->first();
        if (!$request) {
            $this->setErrorMsg(__('tips.no_outorder'));
            return false;
        }
        if ($request->request_status == ObOrder::STATUS_CANCELED) {
            $this->cancelWhenReview($request);
            $this->setErrorMsg(__('tips.cancel_when_review'));
            return false;
        }
        //需求单写入redis 防止短时间内重复发货
        if (Redis::get('wms:sendGoods'.$request->request_code)){
            $this->setErrorMsg(__('tips.option_repeat'));
            return false;
        }
        Redis::setex('wms:sendGoods'.$request->request_code, 10, 1);
        $num = preAllocationDetail::where('request_code', $request->request_code)->where('alloction_status', '<>', preAllocationDetail::DELIVERED)
            ->selectRaw('sum(pre_num) as pre_num')->selectRaw('sum(actual_num) as actual_num')->first()->toArray();
        if (!$num['pre_num']) {
            $this->setErrorMsg(__('admin.wms.deliver.no_product'));
            return false;
        }

        $actual_num = 0;
        $quality_num = 0;
        $defects_num = 0;
        $details = preAllocationDetail::where('request_code', $request->request_code)->with('recvDetail')->where('alloction_status', preAllocationDetail::WAIT_DELIVER)->get()->toArray();
        if (!$details) {
            $this->setErrorMsg(__('admin.wms.deliver.no_product'));
            return false;
        }
        $uniq_codes = array_column($details, 'uniq_code');

        foreach ($details as $item) {
            $skus[] = $item['bar_code'];
            $actual_num += $item['actual_num'];
            if (($item['recv_detail']['quality_level'] ?? '') == 'A') {
                $quality_num +=  $item['actual_num'];
            } else {
                $defects_num += $item['actual_num'];
            }
        }

        if ($num['pre_num'] > 0 && $num['pre_num'] > $actual_num) {
            $this->setErrorMsg(__('admin.wms.deliver.reviewing'));
            return false;
        }

        try {
            DB::beginTransaction();
            // 发货单
            $ship_code = self::shippingCode();

            WmsShippingOrder::create([
                'warehouse_code' => $request->warehouse_code,
                'warehouse_name' => $request->warehouse_name,
                'ship_code' => $ship_code,
                'request_code' => $request->request_code,
                'sku_num' => count(array_unique($skus)),
                'actual_num' => $actual_num,
                'quality_num' => $quality_num,
                'defects_num' => $defects_num,
                'shipper_id' => ADMIN_INFO['user_id'],
                'shipped_at' => date('Y-m-d H:i:s'),
                'tenant_id' => ADMIN_INFO['tenant_id'],
            ]);
            // 配货单详情
            preAllocationDetail::where('request_code', $request->request_code)->where('alloction_status', preAllocationDetail::WAIT_DELIVER)->update(['alloction_status' => preAllocationDetail::DELIVERED,]);
            // 商品库存销售状态
            Inventory::whereIn('uniq_code', $uniq_codes)->update(['sale_status' => Inventory::SALE_STATUS_SHIPPED, 'in_wh_status' => Inventory::OUT,]);
            //更新总库存
            Inventory::totalInvUpdateByUniq($uniq_codes);
            //供应商更新
            SupInv::supInvUpdate($uniq_codes);
            //库存流水更新
            foreach ($details as $d) {
                WmsStockLog::add(WmsStockLog::ORDER_SENDOUT, $d['uniq_code'], $ship_code, ['origin_value' => $d['location_code']]);
            }
            // 出库明细
            ShippingDetail::where('request_code', $request->request_code)->whereIn('uniq_code', $uniq_codes)->update([
                'status' => ShippingDetail::SHIPPED,
            ]);
            // 出库申请单 已发货
            $request->update(['status' => ObOrder::STATUS_CONFIRMED, 'actual_num' => $actual_num, 'request_status' => ObOrder::DELIVERED]);

            // 销售订单
            if ($request->type == 1) {
                Order::sendOut($request->third_no);
            }
            // 调拨单
            if ($request->type == 2) {
                Transfer::sendOut($request);
            }
            // 其他出库申请单
            if ($request->type == 3) {
                Other::sendOut($request);
            }

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 整单复核页面信息
    function reviewInfoByWholeOrder($params)
    {
        $no = $params['no'];
        $request = WmsShippingRequest::where('request_code', $no)->orWhere('deliver_no', $no)->orWhere('erp_no', $no)->first();
        if (!$request) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }

        if ($request->request_status == ObOrder::STATUS_CANCELED) {
            $this->setErrorMsg(__('admin.wms.deliver.cancel_to_putaway'));
            return false;
        }

        if ($request->request_status != 3) {
            $this->setErrorMsg(__('tips.not_recheck_status'));
            return false;
        }

        $total = preAllocationDetail::where('request_code', $request->request_code)
            ->where('alloction_status', preAllocationDetail::WAIT_REVIEW)
            ->selectRaw('sum(actual_num) as actual_num')->first();
        // 已扫描商品
        $scanned_list = preAllocationDetail::where('request_code', $request->request_code)->where('alloction_status', preAllocationDetail::WAIT_REVIEW)->with(['recvDetail', 'property'])->get()->toArray();
        $scanned_arr = [];
        $num_arr = [];
        // 已扫描数量
        $scanned_num = 0;
        foreach ($scanned_list as $item) {
            $key = $item['bar_code'] . '_' . $item['recv_detail']['quality_level'];
            $num_arr[$key] = ($num_arr[$key] ?? 0) + $item['actual_num'];

            $product = $item['property']['product'] ?? [];
            $scanned_arr[$key] = [
                'img' => $product['img'] ?? '',
                'bar_code' => $item['bar_code'],
                'actual_num' => $num_arr[$key],
                'product_sn' => $product['product_sn'] ?? '',
                'name' => $product['name'] ?? '',
                'spec_one' => $item['property']['spec_one'] ?? '',
                'quality_level' => $item['recv_detail']['quality_level'] ?? '',
                'quality_type' => $item['recv_detail']['quality_type'] ?? '',
            ];
            $scanned_num += $item['actual_num'];
        }
        return [
            'deliver_type' => '', //物流产品
            'request_code' => $request->request_code,
            'third_no' => $request->third_no,
            'erp_no' => $request->erp_no,
            'scanned_num' => $scanned_num, //已扫描数量
            'total_num' => $total['actual_num'] ?? 0, //剩余应发总数
            'scanned_list' => array_values($scanned_arr),
        ];
    }

    // 整单复核发货
    function sendOutByWholeOrder($params)
    {
        $no = $params['no'];
        $request = ObOrder::where('request_code', $no)->orWhere('deliver_no', $no)->orWhere('erp_no', $no)->first();
        if (!$request) {
            $this->setErrorMsg(__('tips.no_outorder'));
            return false;
        }
        if ($request->request_status == ObOrder::STATUS_CANCELED) {
            $this->setErrorMsg(__('admin.wms.deliver.cancel_to_putaway'));
            return false;
        }
        //需求单写入redis 防止短时间内重复发货
        if (Redis::get('wms:sendGoods' . $request->request_code)) {
            $this->setErrorMsg(__('tips.option_repeat'));
            return false;
        }
        Redis::setex('wms:sendGoods' . $request->request_code, 10, 1);
        $num = preAllocationDetail::where('request_code', $request->request_code)->where('alloction_status', '<>', preAllocationDetail::DELIVERED)
            ->selectRaw('sum(pre_num) as pre_num')->selectRaw('sum(actual_num) as actual_num')->first()->toArray();
        if (!$num['pre_num']) {
            $this->setErrorMsg(__('admin.wms.deliver.no_product'));
            return false;
        }

        $actual_num = 0;
        $quality_num = 0;
        $defects_num = 0;
        $details = preAllocationDetail::where('request_code', $request->request_code)->with('recvDetail')->where('alloction_status', preAllocationDetail::WAIT_REVIEW)->get()->toArray();
        if (!$details) {
            $this->setErrorMsg(__('admin.wms.deliver.no_product'));
            return false;
        }
        $uniq_codes = array_column($details, 'uniq_code');

        foreach ($details as $item) {
            $skus[] = $item['bar_code'];
            $actual_num += $item['actual_num'];
            if (($item['recv_detail']['quality_level'] ?? '') == 'A') {
                $quality_num +=  $item['actual_num'];
            } else {
                $defects_num += $item['actual_num'];
            }
        }

        if ($num['pre_num'] > 0 && $num['pre_num'] > $actual_num) {
            $this->setErrorMsg(__('admin.wms.deliver.reviewing'));
            return false;
        }

        try {
            DB::beginTransaction();
            // 发货单
            $ship_code = self::shippingCode();
            WmsShippingOrder::create([
                'warehouse_code' => $request->warehouse_code,
                'warehouse_name' => $request->warehouse_name,
                'ship_code' => $ship_code,
                'request_code' => $request->request_code,
                'sku_num' => count(array_unique($skus)),
                'actual_num' => $actual_num,
                'quality_num' => $quality_num,
                'defects_num' => $defects_num,
                'shipper_id' => ADMIN_INFO['user_id'],
                'shipped_at' => date('Y-m-d H:i:s'),
                'tenant_id' => ADMIN_INFO['tenant_id'],
            ]);
            // 配货任务单
            WmsPreAllocationDetail::where('request_code', $request->request_code)->where('alloction_status', preAllocationDetail::WAIT_REVIEW)->update(['alloction_status' => WmsPreAllocationDetail::DELIVERED, 'reviewer_id' => ADMIN_INFO['user_id'], 'review_at' => date('Y-m-d H:i:s')]);
            // 商品在库信息
            Inventory::whereIn('uniq_code', $uniq_codes)->update(['sale_status' => Inventory::SALE_STATUS_SHIPPED, 'in_wh_status' => Inventory::OUT,]);

            WmsStockLog::addBtach(WmsStockLog::ORDER_SENDOUT, $uniq_codes, $ship_code, ['request' => $request]);
            // 发货明细
            ShippingDetail::where('request_code', $request->request_code)->whereIn('uniq_code', $uniq_codes)->update([
                'status' => ShippingDetail::SHIPPED,
            ]);
            // 出库申请单
            $request->update(['status' => ObOrder::STATUS_CONFIRMED, 'actual_num' => $actual_num, 'request_status' => ObOrder::DELIVERED]);

            DB::commit();

            //发货
            $send_redis = [
                'params' => ['tenant_id' => request()->header('tenant_id'), 'type' => $request->type, 'request_code' => $request->request_code],
                'class' => 'App\Models\Admin\V2\AllocationTaskDetail',
                'method' => 'sendUpdate',
                'time' => date('Y-m-d H:i:s'),
            ];
            Redis::rpush(RedisKey::QUEUE_AYSNC_HADNLE, json_encode($send_redis));

            // 更新唯一码库存信息
            $send_redis = [
                'params' => ['tenant_id' => request()->header('tenant_id'), 'uniq_codes' => $uniq_codes],
                'class' => 'App\Logics\wms\AllocationTask',
                'method' => 'updateInvTotal',
                'time' => date('Y-m-d H:i:s'),
            ];
            Redis::rpush(RedisKey::QUEUE_AYSNC_HADNLE, json_encode($send_redis));
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    function updateInvTotal($params)
    {
        $uniq_codes = $params['uniq_codes'] ?? [];
        if (!$uniq_codes) return;
        //仓库总库存
        Inventory::totalInvUpdateByUniq($uniq_codes);
    }

    static function cancelCode()
    {
        return 'QXCKD' . date('ymdHis') . rand(1000, 9999);
    }

    function orderCancel($params)
    {
        $transaction = $params['transaction'] ?? true;
        // method 1-取消库存 2-释放库存 3-发货拦截
        $model = ObOrder::where('third_no', $params['third_no']);
        if ($params['id'] ?? 0) {
            $model->where('id', $params['id']);
        }
        $request = $model->orderBy('id', 'desc')->first();
        // 出库需求单不存在，无需取消
        if (!$request) {
            return true;
        }
        // 订单已取消
        if ($request->status == ObOrder::STATUS_CANCELED) {
            return true;
        }

        // 已发货
        if ($request->request_status == ObOrder::DELIVERED) {
            $this->setErrorMsg(__('tips.sendout_order_deby_cancel'));
            return false;
        }
        // 配货中/复核中，未发货
        if (in_array($request->request_status, [ObOrder::ALLOCATE, ObOrder::ON_DELIVERY])) {
            return $this->_cancel2($request, $transaction);
        }

        if ($request->status == ObOrder::STATUS_PAUSE) { // 已暂停
            $this->setErrorMsg(__('admin.wms.ship_request.pause_can_not_cancel'));
            return false;
        }

        // 直接取消
        return $this->_cancel1($request, $transaction);
    }

    // 未执行预配，取消出库
    private function _cancel1($request, $transaction)
    {
        ObOrder::delObGoodsTask($request->request_code);
        try {
            if ($transaction) DB::beginTransaction();

            // 已经预配，取消并释放锁定的库存
            $pres = preAllocationLists::where('request_code', $request->request_code)
                ->where('status', 1)
                ->where('allocation_status', 1)
                ->get();
            $method = WmsShippingCancel::METHOD_CANCEL;
            if ($pres->count() > 0) {
                $method = WmsShippingCancel::METHOD_STOCK;
            } else {
                //未预配也需要释放锁定库存
                $lock_ids =  ShippingDetail::where('request_code', $request->request_code)->pluck('lock_ids')->toArray();
                $lock_ids = implode(',', $lock_ids);
                Inventory::releaseInv($lock_ids);
            }

            $add = true;
            // 释放库存前检查所属的配货任务
            if ($method == WmsShippingCancel::METHOD_STOCK) {
                // 配货任务已经在配货中，暂不生成出库取消单，在配货完成进行复核时生成
                $codes = preAllocationDetail::where('request_code', $request->request_code)->whereIN('pre_alloction_code', $pres->pluck('pre_alloction_code')->toArray())->pluck('task_code');
                if ($codes->count() > 0) {
                    $in_task = WmsAllocationTask::whereIN('code', $codes->toArray())->where(['alloction_status' => 1, 'status' => 1])->first();
                    if ($in_task) $add = false;
                }
            }

            if ($add) $cancel = WmsShippingCancel::add($request, $method);

            // 出库需求单
            $request->update([
                'status' => ObOrder::STATUS_CANCELED,
                'request_status' => ObOrder::CANCELED,
                'cancel_num' => $request->payable_num,
            ]);
            $details = ShippingDetail::where('request_code', $request->request_code)->where('status', 0)->get();
            foreach ($details as $detail) {
                // 发货明细
                $detail->update([
                    'status' => ShippingDetail::CNACELED,
                    'cancel_num' => $detail->payable_num,
                    'canceled_at' => date('Y-m-d H:i:s'),
                    'admin_user_id' => ADMIN_INFO['user_id'],
                ]);
            }


            $task_codes = [];
            foreach ($pres as $pre) {
                $pre->update(['status' => 2, 'cancel_num' => $pre->pre_num,]);
                $items = $pre->details;
                foreach ($items as $item) {
                    // 已预配待分组/已分组待领取/已领取待配货，直接取消并释放库存
                    if ($add && in_array($item->alloction_status, [1, 2, 3])) {
                        Inventory::releaseInv([$item->pre_inv_id]);
                        $item->update([
                            'cancel_num' => $item->cancel_num,
                            'cancel_status' => preAllocationDetail::CANCEL_FREE_STOCK,
                            'canceled_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                    // 已领取待配货
                    if ($item->alloction_status == 3) {
                        $task_codes[] = $item->task_code;
                    } else {
                        //取消出库流水
                        if ($add && in_array($item->alloction_status, [5, 6, 7])) {
                            WmsStockLog::add(WmsStockLog::ORDER_CANCEL_OUT, $item->uniq_code, $cancel->code, ['request' => $request]);
                            //更新库存状态
                            Inventory::invStatusUpdate($item->uniq_code, 2, 1);
                            //更新供应商状态
                            SupInv::supInvUpdate($item->uniq_code);
                        }
                    }
                }
            }
            // 任务单已领取未配货，任务取消
            $task_codes = array_unique($task_codes);
            foreach ($task_codes as $task_code) {
                $task = WmsAllocationTask::where('code', $task_code)->where('status', WmsAllocationTask::STASH)->first();
                if (!$task) continue;
                $active = $task->activeDetail;
                if ($active->count() == 0) {
                    $task->update(['status' => WmsAllocationTask::CANCEL,]);
                }
            }

            if ($transaction) DB::commit();
            return true;
        } catch (Exception $e) {
            if ($transaction) DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 预配完成，取消出库，发货拦截
    private function _cancel2($request, $transaction)
    {
        try {
            if ($transaction) DB::beginTransaction();
            // 创建出库取消单
            $cancel_code = self::cancelCode();
            $cancel = WmsShippingCancel::create([
                'type' => $request->type,
                'code' => $cancel_code,
                'request_code' => $request->request_code,
                'warehouse_code' => $request->warehouse_code,
                'status' => WmsShippingCancel::CONFIRMED,
                'cancel_status' => WmsShippingCancel::WAIT_PUTAWAY,
                'method' => WmsShippingCancel::METHOD_PUTAWAY,
                'third_no' => $request->third_no,
                'cancel_num' => $request->payable_num,
                'canceled_num' => $request->payable_num,
                'create_user_id' => ADMIN_INFO['user_id'],
                'admin_user_id' => ADMIN_INFO['user_id'],
                'tenant_id' => ADMIN_INFO['tenant_id'],
            ]);

            // 出库需求单
            $request->update([
                'status' => ObOrder::STATUS_CANCELED,
                'request_status' => ObOrder::CANCELED,
                'cancel_num' => $cancel->cancel_num,
            ]);
            // 出库明细
            $shipping_details = $request->details;
            foreach ($shipping_details as $detail) {
                $detail->update([
                    'status' => ShippingDetail::CNACELED,
                    'cancel_num' => $detail->payable_num,
                    'canceled_at' => date('Y-m-d H:i:s'),
                    'admin_user_id' => ADMIN_INFO['user_id'],
                ]);
            }

            $details = preAllocationDetail::where('request_code', $request->request_code)
                ->whereIn('alloction_status', [preAllocationDetail::WAIT_REVIEW, preAllocationDetail::REVIEWING, preAllocationDetail::WAIT_DELIVER])
                ->with('inventory')->get();
            $num = 0;
            foreach ($details as $detail) {
                $recv = $detail->inventory;
                // 配货明细
                $detail->update([
                    'cancel_status' => preAllocationDetail::CANCEL_WAIT_PUTAWAY,
                    'canceled_at' => date('Y-m-d H:i:s'),
                ]);
                $num++;

                //取消出库流水
                if (in_array($detail->alloction_status, [5, 6, 7])) {
                    WmsStockLog::add(WmsStockLog::ORDER_CANCEL_OUT, $detail->uniq_code, $cancel_code, ['request' => $request]);
                    //更新库存状态
                    Inventory::invStatusUpdate($detail->uniq_code, 2, 1);
                    //更新供应商状态
                    SupInv::supInvUpdate($detail->uniq_code);
                }
                // 库存销售状态还原
                // Inventory::invStatusUpdate($recv->uniq_code, null, Inventory::SALE_STATUS_ENABLE);
            }
            $cancel->update(['wait_putaway_num' => $num,]);
            if ($transaction) DB::commit();
            return true;
        } catch (Exception $e) {
            if ($transaction) DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 配货完成，取消出库，发货拦截
    private function _cancel3($request)
    {
        try {
            DB::beginTransaction();
            $detail_model = preAllocationDetail::where('request_code', $request->request_code)
                ->where('alloction_status', preAllocationDetail::DELIVERED)->orderBy('id', 'desc');
            $cancel = WmsShippingCancel::create([
                'type' => $request->type,
                'code' => self::cancelCode(),
                'request_code' => $request->request_code,
                'warehouse_code' => $request->warehouse_code,
                'status' => WmsShippingCancel::WAIT_PUTAWAY,
                'method' => WmsShippingCancel::METHOD_PUTAWAY,
                'third_no' => $request->third_no,
                'cancel_num' => $request->payable_num,
                'canceled_num' => $request->payable_num,
                'wait_putaway_num' => ($detail_model->selectRaw('sum(actual_num) as num')->first()->toArray())['num'] ?? 0,
                'create_user_id' => ADMIN_INFO['user_id'],
                'admin_user_id' => ADMIN_INFO['user_id'],
                'tenant_id' => ADMIN_INFO['tenant_id'],
            ]);

            // 出库需求单
            $request->update(['status' => 4, 'request_status' => 5, 'cancel_num' => $cancel->cancel_num,]);
            // 发货明细
            $details = ShippingDetail::where('request_code', $request->request_code)->where('status', 0)->get();
            foreach ($details as $detail) {
                $detail->update([
                    'status' => ShippingDetail::CNACELED,
                    'cancel_num' => $detail->payable_num,
                    'canceled_at' => date('Y-m-d H:i:s'),
                    'admin_user_id' => ADMIN_INFO['user_id'],
                ]);
            }
            // 配货明细
            $detail_model->update(['cancel_status' => preAllocationDetail::CANCEL_OUT,]);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 出库取消单查询
    function cancelSearch($params, $export = false)
    {
        $model = new WmsShippingCancel();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            if ($params['number'] ?? '') {
                $model = $model->where(function ($query) use ($params) {
                    return $query->where('third_no', $params['number'])
                        ->orWhere('code', $params['number']);
                });
            }
            if ($params['cancel_status'] ?? []) $model = $model->whereIn('cancel_status', $params['cancel_status']);
            if ($params['code'] ?? '') $model = $model->where('code', 'like', '%' . $params['code'] . '%');

            $where = self::filterEmptyData($params, ['warehouse_code']);
            if ($where ?? []) $model = $model->where($where);
            return $model->with(['request'])->orderBy('created_at', 'desc');
        });

        $total_num = 0; //应上架数量
        $putaway_num = 0; //已上架数量
        foreach ($list['data'] as &$item) {
            $item['request_order_at'] = date('Y-m-d H:i:s', strtotime($item['request']['created_at'] ?? ''));
            $item['created_at'] = date('Y-m-d H:i:s', strtotime($item['created_at']));
            $item['updated_at'] = date('Y-m-d H:i:s', strtotime($item['updated_at']));
            $item['total_num'] = $item['wait_putaway_num'] + $item['putaway_num'];
            unset($item['request']);
            $total_num += $item['total_num'];
            $putaway_num += $item['putaway_num'];
        }
        $list['total_num'] = $total_num;
        $list['putaway_num'] = $putaway_num;
        return $list;
    }

    // 出库取消单上架
    function cancelPutaway($params)
    {
        if ($params['id'] ?? 0) {
            $cancel = WmsShippingCancel::find($params['id']);
        } else {
            $where = self::filterEmptyData($params, ['request_code', 'warehouse_code']);
            $cancel = WmsShippingCancel::where($where)->whereIn('cancel_status', [3, 4])->first();
        }
        $detail = preAllocationDetail::where('request_code', $cancel->request_code)
            ->where('uniq_code', $params['uniq_code'])
            ->where('cancel_status', preAllocationDetail::CANCEL_WAIT_PUTAWAY)
            ->first();
        if (!$detail) {
            $this->setErrorMsg(__('tips.not_wait_shelf'));
            return false;
        }
        $location_code = $params['location_code'] ?? '';
        if (!$location_code) {
            $recv = Inventory::where('uniq_code', $params['uniq_code'])->where('warehouse_code', $params['warehouse_code'])->first();
            $location_code = $recv ? $recv->location_code : '';
        }
        if (!$location_code) {
            $this->setErrorMsg(__('admin.wms.location_code.not_found'));
            return false;
        }

        try {
            DB::beginTransaction();
            // 记录新的位置码
            $detail->update([
                'new_location_code' => $location_code,
                'admin_user_id' => ADMIN_INFO['user_id'],
                'cancel_status' => preAllocationDetail::CANCEL_PUTAWAY_ING,
            ]);
            // 取消单 -上架中
            if ($cancel->cancel_status == WmsShippingCancel::WAIT_PUTAWAY) {
                $cancel->update(['cancel_status' => WmsShippingCancel::PUTAWAY_ING,]);
            }

            // 更新在库信息
            $recv = $detail->inventory;
            $area_code = WarehouseLocation::where('warehouse_code', $detail->warehouse_code)->where('location_code', $detail->new_location_code)->value('area_code');
            $recv->update([
                'in_wh_status' => 3, //已上架
                'inv_status' => 5, //架上可售
                'sale_status' => 1, //待售
                'area_code' => $area_code,
                'location_code' => $detail->new_location_code,
            ]);

            // 全部上架完成更新取消单状态
            $find = preAllocationDetail::where('request_code', $cancel->request_code)->where('cancel_status', preAllocationDetail::CANCEL_WAIT_PUTAWAY)->first();
            if (!$find) $this->_cancelPutawayDone($cancel, $params);

            DB::commit();
            // return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            // throw $e;
            return false;
        }


        $data = preAllocationDetail::where('request_code', $cancel->request_code)->where('cancel_status', preAllocationDetail::CANCEL_PUTAWAY_ING)->get()->toArray();
        $categories = ProductCategory::get()->keyBy('id')->toArray();

        $res = [];
        foreach ($data as $item) {
            $product = $item['product']['product'] ?? [];
            $sku = $item['product'] ?? [];
            $category_id = $product['category_id'] ?? 0;
            $tmp = [
                'location_code' => $item['new_location_code'],
                'bar_code' => $item['bar_code'],
                'product_sn' => $product['product_sn'] ?? '',
                'name' => $product['name'] ?? '',
                'img' => $product['img'] ?? '',
                'category_name' => $categories[$category_id]['parent']['name'] ?? '',
                'spec_one' => $sku['spec_one'],
                'sku' => $sku['sku'],
                'quality_type' => $item['quality_type'] ?? '',
                'quality_level' => $item['quality_level'] ?? '',
            ];
            $key = $tmp['location_code'] . '_' . $tmp['bar_code'] . '_' . $tmp['quality_type'];
            $num = ($res[$key] ?? [])['num'] ?? 0;
            $tmp['num'] = $num + 1;
            $res[$key] = $tmp;
        }
        return array_values($res);
    }

    // 取消单上架完成
    function _cancelPutawayDone($cancel, $params = [], $putaway_code = null)
    {
        $details = preAllocationDetail::where('request_code', $cancel->request_code)->where('cancel_status', preAllocationDetail::CANCEL_PUTAWAY_ING)->get();

        $num = 0;
        // $detail2 = [];
        foreach ($details as $detail) {
            $detail->update(['cancel_status' => preAllocationDetail::CANCEL_PUTAWAY,]);
            // $area_code = WarehouseLocation::where('warehouse_code', $detail->warehouse_code)->where('location_code', $detail->new_location_code)->value('area_code');
            $num++;

            // $detail2[] = [
            //     'type' => 3,
            //     'bar_code' => $detail->bar_code,
            //     'uniq_code' => $detail->uniq_code,
            //     'area_code' => $area_code,
            //     'location_code' => $detail->new_location_code,
            //     'quality_type' => QualityControl::getQualityType($detail->quality_level),
            //     'quality_level' => $detail->quality_level,
            //     'tenant_id' => ADMIN_INFO['tenant_id'],
            //     'admin_user_id' => ADMIN_INFO['user_id'],
            // ];

            //取消单上架库存流水
            if ($putaway_code) WmsStockLog::add(WmsStockLog::ORDER_CANCEL_PUTAWAY, $detail->uniq_code, $putaway_code, ['cancel' => $cancel, 'origin_value' => 'XJZCQ001']);
        }

        $update = [
            'admin_user_id' => ADMIN_INFO['user_id'],
            'putaway_num' => $num,
            'cancel_status' => WmsShippingCancel::PUTAWAY,
            'wait_putaway_num' => $cancel->cancel_num - $num,
        ];
        if ($params['remark'] ?? '')  $update['remark'] = $params['remark'];
        $cancel->update($update);

        // Putaway::createEndList([
        //     'type' => WmsPutawayList::TYPE_CANCEL,
        //     'status' => 1,
        //     'putaway_status' => 1,
        //     'total_num' => $num,
        //     'warehouse_code' => $cancel->warehouse_code,
        //     'warehouse_name' => WmsWarehouse::name($cancel->warehouse_code),
        //     'create_user_id' => ADMIN_INFO['user_id'],
        //     'tenant_id' => ADMIN_INFO['tenant_id'],
        //     'submitter_id' => ADMIN_INFO['user_id'],
        //     'admin_user_id' => ADMIN_INFO['user_id'],
        //     'completed_at' => date('Y-m-d H:i:s'),
        //     'origin_code' => $cancel->code,
        // ], $detail2);
    }


    // PDA取消单上架，包含普通商品
    function cancelPutaway2($params)
    {
        // 校验产品类型
        if (false == $this->productTypeVerify($params)) return false;

        // 普通商品找到条形码对应的唯一码
        if ($params['type'] == 2) {
            $where = self::filterEmptyData($params, ['warehouse_code', 'bar_code', 'request_code', 'quality_level']);
            $find = preAllocationDetail::where($where)->where('cancel_status', 3)->first();
            if (!$find) {
                $this->setErrorMsg(__('tips.not_wait_shelf_good'));
                return false;
            }

            $params['uniq_code'] = $find->uniq_code;
        }

        $detail = preAllocationDetail::where('warehouse_code', $params['warehouse_code'])
            ->where('uniq_code', $params['uniq_code'])
            ->where('cancel_status', preAllocationDetail::CANCEL_WAIT_PUTAWAY)
            ->first();
        if (!$detail) {
            $this->setErrorMsg(__('tips.not_wait_shelf'));
            return false;
        }
        $cancel = WmsShippingCancel::where(['request_code' => $detail->request_code, 'warehouse_code' => $params['warehouse_code']])
            ->whereIn('cancel_status', [3, 4])->first();
        $location_code = $params['location_code'] ?? '';
        if (!$location_code) {
            $recv = Inventory::where('uniq_code', $params['uniq_code'])->where('warehouse_code', $params['warehouse_code'])->first();
            $location_code = $recv ? $recv->location_code : '';
        }
        if (!$location_code) {
            $this->setErrorMsg(__('admin.wms.location_code.not_found'));
            return false;
        }

        try {
            DB::beginTransaction();
            // 进行中的上架单
            $p_where = [
                'type' => WmsPutawayList::TYPE_CANCEL,
                'status' => 0,
                'warehouse_code' => $params['warehouse_code'],
                'create_user_id' => ADMIN_INFO['user_id'],
                'tenant_id' => ADMIN_INFO['tenant_id'],
            ];
            $putaway = WmsPutawayList::where($p_where)->first();
            if (!$putaway) {
                $putaway = WmsPutawayList::create(array_merge($p_where, [
                    'warehouse_name' => WmsWarehouse::name($params['warehouse_code']),
                    'putaway_code' => WmsPutawayList::code(),
                ]));
            }

            // 记录新的位置码
            $detail->update([
                'new_location_code' => $location_code,
                'admin_user_id' => ADMIN_INFO['user_id'],
                'cancel_status' => preAllocationDetail::CANCEL_PUTAWAY_ING,
            ]);
            // 取消单 -上架中
            if ($cancel->cancel_status == WmsShippingCancel::WAIT_PUTAWAY) {
                $cancel->update(['cancel_status' => WmsShippingCancel::PUTAWAY_ING,]);
            }

            // 更新在库信息
            $recv = $detail->inventory;
            $area_code = WarehouseLocation::where('warehouse_code', $detail->warehouse_code)->where('location_code', $detail->new_location_code)->value('area_code');
            $recv->update([
                'in_wh_status' => 3, //已上架
                'inv_status' => 5, //架上可售
                'sale_status' => 1, //待售
                'area_code' => $area_code,
                'location_code' => $detail->new_location_code,
            ]);

            WmsPutawayDetail::create([
                'putaway_code' => $putaway->putaway_code,
                'type' => 3,
                'bar_code' => $detail->bar_code,
                'uniq_code' => $params['uniq_code'],
                'area_code' => $area_code,
                'location_code' => $location_code,
                'quality_type' => $recv->quality_level == 'A' ? 1 : 2,
                'quality_level' => $recv->quality_level,
                'tenant_id' => ADMIN_INFO['tenant_id'],
                'admin_user_id' => ADMIN_INFO['user_id'],
            ]);

            // 全部上架完成更新取消单状态
            $find = preAllocationDetail::where('request_code', $cancel->request_code)->where('cancel_status', preAllocationDetail::CANCEL_WAIT_PUTAWAY)->first();
            if (!$find) $this->_cancelPutawayDone($cancel, $params, $putaway->putaway_code);

            DB::commit();
            // return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            // throw $e;
            return false;
        }


        // $data = preAllocationDetail::where('request_code', $cancel->request_code)->where('cancel_status', preAllocationDetail::CANCEL_PUTAWAY_ING)->get()->toArray();
        $data = WmsPutawayDetail::where('putaway_code', $putaway->putaway_code)->with('specBar')->get()->toArray();
        $categories = ProductCategory::get()->keyBy('id')->toArray();
        $res = [];
        foreach ($data as $item) {
            $product = $item['spec_bar']['product'] ?? [];
            $sku = $item['spec_bar'] ?? [];
            $category_id = $product['category_id'] ?? 0;
            $tmp = [
                'location_code' => $item['location_code'],
                'bar_code' => $item['bar_code'],
                'product_sn' => $product['product_sn'] ?? '',
                'name' => $product['name'] ?? '',
                'img' => $product['img'] ?? '',
                'category_name' => $categories[$category_id]['parent']['name'] ?? '',
                'spec_one' => $sku['spec_one'],
                'sku' => $sku['sku'],
                'quality_type' => $item['quality_type'] ?? '',
                'quality_level' => $item['quality_level'] ?? '',
            ];
            $key = $tmp['location_code'] . '_' . $tmp['bar_code'] . '_' . $tmp['quality_type'];
            $num = ($res[$key] ?? [])['num'] ?? 0;
            $tmp['num'] = $num + 1;
            $res[$key] = $tmp;
        }
        return array_values($res);
    }

    // 出库取消单-上架完成
    function cancelPutawayConfirm($params)
    {
        if ($params['id'] ?? 0) {
            $cancel = WmsShippingCancel::find($params['id']);
        } else {
            $where = self::filterEmptyData($params, ['request_code', 'warehouse_code']);
            $cancel = WmsShippingCancel::where($where)->whereIn('cancel_status', [3, 4])->first();
        }
        if (!$cancel) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }
        $details = preAllocationDetail::where('request_code', $cancel->request_code)
            ->where('cancel_status', preAllocationDetail::CANCEL_PUTAWAY_ING)->with('inventory')
            ->get();
        if (count($details) == 0) return true;

        try {
            DB::beginTransaction();
            $num = 0;
            $detail2 = [];
            foreach ($details as $detail) {
                $detail->update(['cancel_status' => preAllocationDetail::CANCEL_PUTAWAY,]);
                $recv = $detail->inventory;
                $area_code = WarehouseLocation::where('warehouse_code', $detail->warehouse_code)
                    ->where('location_code', $detail->new_location_code)->value('area_code');
                // 更新在库信息
                $recv->update([
                    'in_wh_status' => 3, //已上架
                    'inv_status' => 5, //架上可售
                    'sale_status' => 1, //待售
                    'area_code' => $area_code,
                    'location_code' => $detail->new_location_code,
                ]);
                $num++;

                $detail2[] = [
                    'type' => 3,
                    'bar_code' => $detail->bar_code,
                    'uniq_code' => $detail->uniq_code,
                    'area_code' => $area_code,
                    'location_code' => $detail->new_location_code,
                    'quality_type' => QualityControl::getQualityType($detail->quality_level),
                    'quality_level' => $detail->quality_level,
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                    'admin_user_id' => ADMIN_INFO['user_id'],
                ];
            }

            $update = [
                'admin_user_id' => ADMIN_INFO['user_id'],
                'putaway_num' => $num,
                'cancel_status' => WmsShippingCancel::PUTAWAY,
                'wait_putaway_num' => $cancel->cancel_num - $num,
            ];
            if ($params['remark'] ?? '')  $update['remark'] = $params['remark'];
            $cancel->update($update);

            Putaway::createEndList([
                'type' => WmsPutawayList::TYPE_CANCEL,
                'status' => 1,
                'putaway_status' => 1,
                'total_num' => $num,
                'warehouse_code' => $cancel->warehouse_code,
                'warehouse_name' => WmsWarehouse::name($cancel->warehouse_code),
                'create_user_id' => ADMIN_INFO['user_id'],
                'tenant_id' => ADMIN_INFO['tenant_id'],
                'submitter_id' => ADMIN_INFO['user_id'],
                'admin_user_id' => ADMIN_INFO['user_id'],
                'completed_at' => date('Y-m-d H:i:s'),
                'origin_code' => $cancel->code,
            ], $detail2);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
        return true;
    }

    // 出库取消单详情
    function cancelDetail($id)
    {
        $logs = [];
        $detail = [];
        $info = WmsShippingCancel::with(['request'])->where('id', $id)->orWhere('code', $id)->first();
        if (!$info) return compact('info', 'detail', 'logs');;
        $info = $info->toArray();

        $details = preAllocationDetail::where('request_code', $info['request_code'])
            ->where('cancel_status', '>', 0)->with('property')
            ->get();

        // $details = ShippingDetail::where('request_code', $info['request_code'])->with(['property'])->get();
        $info['third_no'] = $info['request']['third_no'];
        $info['order_created_at'] = date('Y-m-d H:i:s', strtotime($info['request']['created_at']));
        $info['created_at'] = date('Y-m-d H:i:s', strtotime($info['created_at']));
        $info['updated_at'] = date('Y-m-d H:i:s', strtotime($info['updated_at']));
        unset($info['request']);

        foreach ($details as $item) {
            $detail[] = [
                'code' => $item['property']['code'],
                'sku' => $item['property']['sku'],
                'spec_one' => $item['property']['spec_one'],
                'const_price' => $item['property']['const_price'], //采购价
                'order_price' => 0, //TODO 订单实际成交价
                'product_sn' => $item['property']['product']['product_sn'],
                'sku' => $item['property']['product']['product_sn'],
                'name' => $item['property']['product']['name'],
                'remark' => $item['remark'],
                'cancel_total' => 1, //应取消数量
                'canceled_num' => 1, //已取消数量
                'putaway_total' => in_array($item->cancel_status, [preAllocationDetail::CANCEL_PUTAWAY_ING, preAllocationDetail::CANCEL_WAIT_PUTAWAY]) ? 1 : 0, //应上架数量
                'putaway_num' => $item->cancel_status == preAllocationDetail::CANCEL_PUTAWAY ? 1 : 0, //已上架数量
            ];
        }

        return compact('info', 'detail', 'logs');
    }

    function cancelWaitPutaway($params)
    {
        $detail = [];
        $details = preAllocationDetail::where('warehouse_code', $params['warehouse_code'])
            ->whereIN('cancel_status', [3, 4])->with(['property', 'supplier'])->get();

        foreach ($details as $item) {
            $tmp = [
                'supplier' => $item['supplier']['name'] ?? '',
                'code' => $item['property']['code'],
                'sku' => $item['property']['sku'],
                'bar_code' => $item['property']['bar_code'],
                'spec_one' => $item['property']['spec_one'],
                'const_price' => $item['property']['const_price'], //采购价
                'product_sn' => $item['property']['product']['product_sn'],
                'name' => $item['property']['product']['name'],
                'img' => $item['property']['product']['img'],
                'remark' => $item['remark'],
                'batch_no' => $item['batch_no'],
                'quality_level' => $item['quality_level'],
                'quality_type_txt' => QualityControl::getQualityTypeTxt($item['quality_level']),
                'location_code' => $item['location_code'],
                'cancel_total' => 1, //应取消数量
                'canceled_num' => 1, //已取消数量
                'wait_putaway_num' => $item->cancel_status == preAllocationDetail::CANCEL_WAIT_PUTAWAY ? 1 : 0, //应上架数量
                'putaway_num' => $item->cancel_status == preAllocationDetail::CANCEL_PUTAWAY ? 1 : 0, //已上架数量
            ];
            $key = sprintf('%s_%s_%s_%s_%s_%s', $tmp['sku'], $tmp['bar_code'], $tmp['quality_level'], $tmp['supplier'], $tmp['batch_no'], $tmp['location_code']);
            if ($detail[$key] ?? []) {
                $detail[$key]['cancel_total'] += $tmp['cancel_total'];
                $detail[$key]['canceled_num'] += $tmp['canceled_num'];
                $detail[$key]['wait_putaway_num'] += $tmp['wait_putaway_num'];
                $detail[$key]['putaway_num'] += $tmp['putaway_num'];
                continue;
            }
            $detail[$key] = $tmp;
        }
        return array_values($detail);
    }

    // 按位置码挨个配货时展示的信息
    function taskShow($params)
    {
        $skip = $this->getSkipData($params);
        $where = [
            'task_code' => $params['task_code'], 'alloction_status' => 3,
            'receiver_id' => ADMIN_INFO['user_id'], 'warehouse_code' => $params['warehouse_code']
        ];
        $find1 = $this->_showDetail($params, $skip, 1);
        $find2 = $this->_showDetail($params, $skip, 2);

        $location_code = [];
        if ($find1) $location_code[] = $find1->location_code;
        if ($find2) $location_code[] = $find2->location_code;

        // 删除要跳过的数据
        if (($skip[1] ?? []) && empty($find1)) $this->cleanSkipData($params, 1);
        if (($skip[2] ?? []) && empty($find2)) $this->cleanSkipData($params, 2);
        if (!$location_code) {
            if (($skip[1] ?? []) || ($skip[2] ?? [])) {
                return $this->taskShow($params);
            }
            return [];
        }
        unset($where['alloction_status']);
        if (!empty($params['bar_code'])) $where['bar_code'] = $params['bar_code'];
        $details = preAllocationDetail::where($where)->get();
        $normal = [
            'matched_num' => 0, //已配数
            'should_match_num' => 0, //应配数
            'total' => 0, //应拣数
            'selected_num' => 0, //已拣数
            'skus' => [], //商品信息
            'location_code' => $find2 ? $find2->location_code : '',
            '_skus' => [],
            'bar_code' => '',
        ];
        $unique = [
            'matched_num' => 0, //已配数
            'should_match_num' => 0, //应配数
            'total' => 0,
            'selected_num' => 0,
            'skus' => [],
            'location_code' => $find1 ? $find1->location_code : '',
            '_skus' => [],
            'bar_code' => '',
        ];

        foreach ($details as $detail) {
            $barcode_type = $detail['product']['type'] ?? 0;
            // 唯一码商品
            if ($barcode_type == 1) $this->_formatTaskItem($detail, $unique, $location_code, $skip[1] ?? []);
            // 普通商品
            if ($barcode_type == 2) $this->_formatTaskItem($detail, $normal, $location_code, $skip[2] ?? []);
        }
        $normal['skus'] = array_values($normal['skus']);
        $unique['skus'] = array_values($unique['skus']);
        if ($normal['_skus']) $normal['_skus']  = [$normal['_skus'][$normal['bar_code']]];
        if ($unique['_skus']) $unique['_skus']  = [$unique['_skus'][$unique['bar_code']]];
        return [
            'task_code' => $params['task_code'],
            // 'location_code' => $find->location_code,
            'normal' => $normal, //普通产品
            'unique' => $unique, //唯一码产品
        ];
    }

    // 按位置码挨个配货时展示的信息
    private function _showDetail($params, $skip, $type)
    {
        $where = [
            'task_code' => $params['task_code'], 'alloction_status' => 3,
            'receiver_id' => ADMIN_INFO['user_id'], 'warehouse_code' => $params['warehouse_code']
        ];
        $model = preAllocationDetail::where($where)->whereExists(function ($query) use ($type) {
            $query->select(DB::raw(1))->from('wms_spec_and_bar as b')->whereRaw('wms_pre_allocation_detail.bar_code=b.bar_code')->where('b.type', $type);
        })->with('property');
        // 跳过指定商品
        if ($skip[$type] ?? []) {
            $model = $model->whereNotExists(function ($query) use ($skip, $type) {
                $query->select(DB::raw(1))->from('wms_pre_allocation_detail as a')->whereRaw('wms_pre_allocation_detail.id=a.id')->where(function ($query) use ($skip, $type) {
                    foreach ($skip[$type] as $item) {
                        $query->orWhereRaw('( location_code=? and bar_code=? and quality_level=? and batch_no=?)', [$item['location_code'], $item['bar_code'], $item['quality_level'], $item['batch_no']]);
                    }
                });
            });
        }
        return $model->orderBy('location_code', 'asc')->first();
    }

    private function _formatTaskItem($detail, &$data, $location_codes, $skip)
    {
        if (in_array($detail['location_code'], $location_codes)) {
            if (isset($data['_skus'][$detail['bar_code']])) {
                $data['_skus'][$detail['bar_code']]['total']++;
                if ($detail['alloction_status'] > 3) {
                    $data['_skus'][$detail['bar_code']]['selected_num']++;
                }
            } else {
                $data['_skus'][$detail['bar_code']]['total'] = 1;
                if ($detail['alloction_status'] > 3) {
                    $data['_skus'][$detail['bar_code']]['selected_num'] = 1;
                } else $data['_skus'][$detail['bar_code']]['selected_num'] = 0;
            }
        }
        $data['should_match_num']++; //应配数
        if ($detail['alloction_status'] > 3)  $data['matched_num']++; //已配数
        if (!in_array($detail['location_code'], $location_codes)) return;

        $data['total']++;
        if ($detail['alloction_status'] > 3) { //已配货
            $data['selected_num']++;
            return;
        }
        if ($detail['alloction_status'] == 3) { //待配货
            // 要跳过的配货商品
            $is_skip = false;
            foreach ($skip as $item) {
                if ($item['bar_code'] == $detail['bar_code'] && $item['batch_no'] == $detail['batch_no'] && $item['quality_level'] == $detail['quality_level'] && $item['location_code'] == $detail['location_code']) {
                    $is_skip = true;
                    break;
                }
            }
            if ($is_skip) return;

            $key = sprintf('%s_%s', $detail['bar_code'], $detail['quality_level']);
            if (empty($data['skus'][$key] ?? '')) {
                $tmp = [
                    'bar_code' => $detail['bar_code'],
                    'batch_no' => $detail['batch_no'],
                    'quality_level' => $detail['quality_level'],
                    'spec_bar' => $detail['product'],
                ];
                self::sepBarFormat($tmp);
                if (empty($data['bar_code'])) $data['bar_code'] = $detail['bar_code'];
                $data['skus'][$key] = $tmp;
            }
        }
    }

    function getSkipData($params)
    {
        $data = Redis::hget(RedisKey::WMS_SKIP, $params['task_code']);
        $data = $data ? json_decode($data, true) : [];
        return $data;
    }

    function skip($params)
    {
        $data = $this->getSkipData($params);
        $data[$params['type']][] = [
            'location_code' => $params['location_code'],
            'bar_code' => $params['bar_code'],
            'quality_level' => $params['quality_level'],
            'batch_no' => $params['batch_no'],
        ];
        Redis::hset(RedisKey::WMS_SKIP, $params['task_code'], json_encode($data));
        return true;
    }

    function cleanSkipData($params, $type)
    {
        $data = $this->getSkipData($params);
        if ($data[$type] ?? []) unset($data[$type]);
        if ($data) {
            Redis::hset(RedisKey::WMS_SKIP, $params['task_code'], json_encode($data));
            return true;
        }

        Redis::hdel(RedisKey::WMS_SKIP, $params['task_code']);
        return true;
    }

    // 取消单上架扫描
    function getUniqCodeInfo($params)
    {
        $where = self::filterEmptyData($params, ['warehouse_code']);
        // 找到上架中的出库单
        $request_code = preAllocationDetail::where($where)->where('admin_user_id', ADMIN_INFO['user_id'])->where('cancel_status', 4)->value('request_code');

        // 查唯一码对应的位置码和出库单
        $where = self::filterEmptyData($params, ['warehouse_code', 'uniq_code']);
        $where['cancel_status'] = 3;
        $detail = preAllocationDetail::where($where)->select(['location_code', 'request_code'])->first();

        if ($request_code && $detail->request_code != $request_code) {
            $this->setErrorMsg(__('tips.good_not_in_shelf_order'));
            return false;
        }
        return $detail;
    }

    // 获取推荐上架位置码
    function getRecommendInfo($params)
    {
        if (false == $this->productTypeVerify($params)) return false;

        $where = self::filterEmptyData($params, ['warehouse_code']);
        // 找到上架中的出库单
        $request_code = preAllocationDetail::where($where)->where('admin_user_id', ADMIN_INFO['user_id'])->where('cancel_status', 4)->value('request_code');

        if ($params['type'] == 1) {
            // 查唯一码对应的位置码和出库单
            $where = self::filterEmptyData($params, ['warehouse_code', 'uniq_code']);
            $where['cancel_status'] = 3;
            $detail = preAllocationDetail::where($where)->select(['location_code', 'request_code', 'uniq_code'])->first();
        } elseif ($params['type'] == 2) {
            // 找条形码对应的唯一码
            $params['quality_level'] = $params['quality_level'] ?: 'A';
            $where = self::filterEmptyData($params, ['warehouse_code', 'bar_code', 'quality_level']);
            $where['cancel_status'] = 3;
            $detail = preAllocationDetail::where($where)->select(['location_code', 'request_code', 'uniq_code'])->first();
        }

        if ($request_code && $detail && $detail->request_code != $request_code) {
            $this->setErrorMsg(__('tips.good_not_in_shelf_order'));
            return false;
        }
        return $detail;
    }
}
