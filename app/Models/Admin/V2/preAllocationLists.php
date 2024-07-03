<?php

namespace App\Models\Admin\V2;

use App\Logics\BaseLogic;

use App\Logics\wms\AllocationTask;
use App\Models\Admin\wmsBaseModel;
use  App\Models\Admin\V2\ArrivalRegist;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;
use Illuminate\Support\Facades\DB;

class preAllocationLists extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'wms_pre_allocation_lists'; //配货订单

    // 单据类型 1-出库配货订单 2-移位配货订单
    const TYPE_OUT = 1;
    const TYPE_MOVE = 2;

    // protected $map = [
    //     'status' => [1 => '已审核', 2 => '已取消', 3 => '已暂停'],
    //     'origin_type' => [1 => '销售出库单', 2 => '调拨出库单', 3 => '其他出库单', 4 => '中转移位单', 5 => '快速移位单'],
    //     'allocation_status' => [1 => '待配货', 2 => '配货中', 3 => '已配货'],
    //     'type' => [1 => '出库配货订单', 2 => '移位配货订单'],

    // ];

    protected $map;
    const AUDITED = 1;
    const CANCELED = 2;
    const PAUSED = 3;
    static $status_map = [1 => '已审核', 2 => '已取消', 3 => '已暂停'];

    static $origin_type_map = [1 => '销售出库单', 2 => '调拨出库单', 3 => '其他出库单', 4 => '中转移位单', 5 => '快速移位单'];

    const WAIT_ALLOCATE = 1;
    const ALLOCATING = 2;
    const ALLOCATED = 3;
    static $allocation_status_map = [1 => '待配货', 2 => '配货中', 3 => '已配货'];
    static $type_map = [1 => '出库配货订单', 2 => '移位配货订单'];

    protected $appends = ['status_txt', 'type_txt', 'users_txt', 'origin_type_txt', 'allocation_status_txt', 'diff_num'];

    public function __construct($params=[])
    {
        parent::__construct($params);
        $this->map = [
            'status' => [1 => __('status.audited'), 2 => __('status.canceled'), 3 => __('status.pause')],
            'origin_type' => [1 => __('status.sale_ob'), 2 => __('status.transfer_ob'), 3 => __('status.other_ob'), 4 => __('status.transfer_shift'), 5 => __('status.rapid_traverse')],
            'allocation_status' => [1 => __('status.wait_dist'), 2 => __('status.in_distribution'), 3 => __('status.allocated')],
            'type' => [1 => __('status.ob_allocation'), 2 => __('status.shift_allocation')],
        ];
    }

    public function searchUser(){
        return [
            'users_txt.create_user' => 'create_user_id',
            'users_txt.admin_user' => 'admin_user_id',
        ];
    }

    public function getUsersTxtAttribute($key)
    {
        $tenant_id = request()->header('tenant_id');
        $res['create_user'] = $this->getAdminUser($this->create_user_id, $tenant_id);
        $res['admin_user'] = $this->getAdminUser($this->admin_user_id, $tenant_id);
        return $res;
    }

    public function getStatusTxtAttribute()
    {
        return self::$status_map[$this->status] ?? '';
    }

    public function getTypeTxtAttribute()
    {
        // 1-出库配货订单 2-移位配货订单
        return self::$type_map[$this->type] ?? '';
    }
    public function getOriginTypeTxtAttribute()
    {
        return self::$origin_type_map[$this->origin_type] ?? '';
    }

    public function getAllocationStatusTxtAttribute()
    {
        return self::$allocation_status_map[$this->allocation_status] ?? '';
    }

    public function getDiffNumAttribute($key)
    {
        return $this->pre_num - $this->actual_num;
    }

    public  function details()
    {
        return $this->hasMany(preAllocationDetail::class, 'pre_alloction_code', 'pre_alloction_code');
    }

    public function warehouse()
    {
        return $this->hasOne(Warehouse::class, 'warehouse_code', 'warehouse_code');
    }

    public  function obOrder()
    {
        return $this->hasOne(ObOrder::class, 'request_code', 'request_code');
    }
    public function add($data)
    {
        $details = $data['details'];;
        if ($data['pre_num'] < 1) return [false, '没有配货商品'];

        if (empty($data['type'])) return [false, 'type参数缺失'];
        if (empty($data['origin_type'])) return [false, 'origin_type参数缺失'];;
        $type = $data['type'];
        $origin_type = $data['origin_type'];
        if ($type == 1) {
            if (!in_array($origin_type, [1, 2, 3])) return [false, 'origin_type参数不正确'];
        }
        if ($type == 2) {
            if (!in_array($origin_type, [3, 4])) return  [false, 'origin_type参数不正确'];
        }

        //预配订单
        $create_data['request_code'] = $data['request_code']; //'必需的，不一定是需求单编码,也可能是移位单编码'
        $startegy_code = empty($data['startegy_code']) ? '' : $data['startegy_code'];
        $create_data['startegy_code'] = $startegy_code;
        $create_data['type'] = $type;
        $create_data['origin_type'] = $origin_type;
        $create_data['sku_num'] = $data['sku_num'];
        $create_data['pre_num'] = $data['pre_num'];
        $create_data['warehouse_code'] = $data['warehouse_code'];

        //自动添加
        $pre_alloction_code = self::getErpCode('PHDD');
        $create_data['pre_alloction_code'] = $pre_alloction_code;
        $create_data['status'] = 1;
        $create_data['allocation_status'] = 1;
        $create_data['create_user_id'] = SYSTEM_ID;
        $create_data['created_at'] = date('Y-m-d H:i:s');
        $create_data['tenant_id'] = $data['tenant_id'];


        //明细数据
        $details_create = [];
        foreach ($details as $detail) {
            $temp['buy_price'] = $detail['buy_price'];
            $temp['pre_alloction_code'] = $pre_alloction_code;
            $temp['request_code'] = $detail['request_code'];
            $temp['startegy_code'] = empty($detail['startegy_code']) ? '' : $detail['startegy_code'];
            $temp['bar_code'] = $detail['bar_code'];
            $temp['sup_id'] = $detail['sup_id'];
            $temp['batch_no'] = $detail['batch_no'];
            $temp['warehouse_code'] = $data['warehouse_code'];
            $temp['quality_type'] = $detail['quality_type'];
            $temp['quality_level'] = $detail['quality_level'];
            $temp['uniq_code'] = empty($detail['uniq_code']) ? '' : $detail['uniq_code'];
            $temp['location_code'] = $detail['location_code'];
            $temp['pre_num'] = $detail['count'];
            $temp['pre_inv_id'] = $detail['pre_inv_id'];
            $temp['created_at'] = date('Y-m-d H:i:s');
            $temp['tenant_id'] = $detail['tenant_id'];
            $temp['alloction_status'] = preAllocationDetail::WAIT_GROUP;
            $details_create[] = $temp;
        }

        $row['cre_lists'] = DB::table('wms_pre_allocation_lists')->insert($create_data);

        // self::insert($create_data);
        // $row['cre_details'] = AllocationTaskDetail::add($details_create);
        // dd($details_create,$create_data);
        $row['cre_details'] = preAllocationDetail::add($details_create);

        $c = array_filter($row, function ($v) {
            return empty($v);
        });
        if (empty($c)) return true;
        return;
    }


    public function withInfoSearch($select)
    {
        return $this::with(['details', 'obOrder', 'warehouse'])->select($select);
    }

    public function _formatList($data)
    {
        foreach ($data['data'] as &$item) {
            if (empty($item['warehouse']['warehouse_name'])) $item['wh_name'] = '';
            else $item['wh_name'] = $item['warehouse']['warehouse_name'];
            unset($item['warehouse']);
        }
        return $data;
    }
    public function withSearch($select)
    {
        $permission = ADMIN_INFO['data_permission'];
        $shop_name = $permission['shop_name'] ?? [];
        if ($shop_name) {
            return $this::with(['warehouse','obOrder' => function ($query) use ($shop_name) {
                $query->whereIn('shop_name', $shop_name);
            }])->select($select);
        }
        return $this::with(['obOrder', 'warehouse'])->select($select);
    }

    public function _formatOne($data)
    {
        return $data->append('wh_name')->makeHidden('warehouse');
    }

    function shippingRequest()
    {
        return $this->hasOne(ObOrder::class, 'request_code', 'request_code');
    }


    public function printEdNo($request_code)
    {
        return (new ShippingOrders())->printEdNo($request_code);
    }

    //流入配货池
    public function toPool($data)
    {
        $ids = explode(',', $data['ids']);
        $pre_alloction_codes = $this::whereIn('id', $ids)->where('state', 0)->pluck('pre_alloction_code');
        $ids = preAllocationDetail::whereIn('pre_alloction_code', $pre_alloction_codes)->pluck('id')->toArray();
        try {
            (new AllocationTask())->waveGroup($ids, ADMIN_INFO['tenant_id']);
            return [true, 'success'];
        } catch (\Exception $e) {
            return [false, $e->getMessage()];
        }
    }


    // 配货订单分组
    function waveGroup($pre_details_ids)
    {
        try {
            DB::beginTransaction();
            $strategies = WmsTaskStrategy::where('status', WmsTaskStrategy::ON)->orderBy('sort', 'asc')->get();
            foreach ($strategies as $strategy) {
                // 分拣配货
                if ($strategy->mode == WmsTaskStrategy::MODE_SORT_OUT) {
                    // 将content转成where查询语句
                    $model = DB::table('wms_pre_allocation_detail as pad')
                        ->whereIn('pad.id', $pre_details_ids)
                        ->where('pad.alloction_status', WmsPreAllocationDetail::WAIT_GROUP)->where('pad.warehouse_code', $strategy->warehouse_code);

                    $columns = array_column($strategy->content, 'column');
                    // 根据查询条件判断是否要关联表
                    if (array_intersect(['order_channel', 'order_platform', 'delivery_time'], $columns)) {
                        $model->leftJoin('wms_shipping_request as sr', 'request_no', 'request_no');
                    }
                    if (array_intersect(['origin_type', 'sku_num'], $columns)) {
                        $model->leftJoin('wms_pre_allocation_lists as pal', 'pal.pre_alloction_code', 'pad.pre_alloction_code');
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
                                $model->leftJoin('wms_spec_and_bar as sab', 'sab.bar_code', 'pad.bar_code')
                                    ->leftJoin('wms_product as p', 'sab.product_id', 'p.id');
                            }
                            $contents['product_category']['value'] = $category_ids;
                        }
                    }

                    // 查询条件处理
                    $model->where(function ($query) use ($contents) {
                        $logic = 'AND';
                        foreach ($contents as $item) {
                            $value = $item['value'];
                            $cloumn = $item['column'];
                            $condition = $item['condition'];
                            //数据格式：{['column'=>'','condition'=>'','value'=>'','logic'=>'']}
                            switch ($cloumn) {
                                case 'order_channel':
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
                                    $cloumn = 'sr.' . $cloumn;
                                    $value = time() - $$value * 3600;
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
                                    // TODO 分类数据为空时如何处理待确认
                                    if (!$value) {
                                    }
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
                                $value = explode(',', $value);
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
                        $pre_arr = preAllocationDetail::whereIn('id', $ids)->where('alloction_status', preAllocationDetail::WAIT_GROUP)->pluck('pre_alloction_code')->toArray();
                        preAllocationDetail::whereIn('id', $ids)->where('alloction_status', preAllocationDetail::WAIT_GROUP)
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

            // 全部处理完后，未经过分组的统一改成待领取状态
            $pre_arr = preAllocationDetail::where('alloction_status', preAllocationDetail::WAIT_GROUP)->pluck('pre_alloction_code')->toArray();
            preAllocationDetail::where('alloction_status', preAllocationDetail::WAIT_GROUP)
                ->update(['alloction_status' => preAllocationDetail::WAIT_RECEIVER, 'task_strategy_code' => 'no_group']);
            preAllocationLists::whereIn('pre_alloction_code', $pre_arr)->update(['state' => 1]);

            DB::commit();
            return [true, 'success'];
        } catch (\Exception $e) {
            DB::rollBack();
            return [false, $e->getMessage()];
        }
    }

    function columnOptions()
    {
        return [
            'type' => self::cloumnOptions(preAllocationLists::$type_map),
            'status' => self::cloumnOptions(preAllocationLists::$status_map),
            'allocation_status' => self::cloumnOptions(preAllocationLists::$allocation_status_map),
            'warehouse_code' => BaseLogic::warehouseOptions(),
        ];
    }

    public $requiredColumns = [];

    function columns()
    {
        // export=true 导出时展示 ， search=true 搜索查询时展示
        return [
            ['value' => 'type', 'label' => '单据类型', 'export' => false],
            ['value' => 'type_txt', 'label' => '单据类型', 'search' => false,],
            ['value' => 'pre_alloction_code', 'label' => '单据编码'],
            ['value' => 'origin_type_txt', 'label' => '来源单据类型', 'search' => false,],
            ['value' => 'request_code', 'label' => '来源单据编码'],
            ['value' => 'wms_shipping_request.erp_no', 'label' => 'ERP出库单编码'],
            ['value' => 'wms_shipping_request.third_no', 'label' => '第三方单据编码'],
            ['value' => 'wms_shipping_request.created_at', 'label' => '下单时间'],
            ['value' => 'status', 'label' => '单据状态', 'export' => false],
            ['value' => 'status_txt', 'label' => '单据状态', 'search' => false,],
            ['value' => 'allocation_status', 'label' => '配货状态', 'export' => false],
            ['value' => 'allocation_status_txt', 'label' => '配货状态', 'search' => false,],
            ['value' => 'left_deliver_hours', 'label' => '剩余发货时长', 'search' => false,],
            ['value' => 'warehouse_code', 'lang'=>'wms_shipping_request.warehouse_code', 'label' => '仓库', 'export' => false],
            ['value' => 'wms_shipping_request.warehouse_name', 'label' => '仓库', 'search' => false],
            ['value' => 'pre_num', 'label' => '预配总数'],
            ['value' => 'received_num', 'label' => '已领任务数', 'search' => false,],
            ['value' => 'wms_shipping_request.order_platform', 'label' => '来源平台'],
            ['value' => 'wms_shipping_request.order_channel', 'label' => '来源渠道'],
            ['value' => 'wms_shipping_request.deliver_no', 'label' => '物流单号'],
            ['value' => 'remark', 'label' => '备注'],
            ['value' => 'created_at', 'label' => '配货时间', 'export' => false],
            ['value' => 'sku_num', 'label' => 'SKU种数', 'export' => false],
        ];
    }
}
