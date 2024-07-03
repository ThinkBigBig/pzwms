<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Controllers\Admin\V2\BaseController;
use App\Logics\BaseLogic;
use App\Logics\RedisKey;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\V2\Inventory;
use App\Models\Admin\V2\WmsLogisticsProduct;
use App\Models\AdminUsers;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
// use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Admin\V2\Supplier;
use App\Models\Admin\V2\Warehouse;
use App\Models\Admin\V2\WmsShop;
use App\Models\AdminUsers as ModelsAdminUsers;

class wmsBaseModel extends Model
{

    const COLOR  = [
        'red' => '#F56C6C',
        'green' => '#67C23A',
        'yellow' => '#E6A23C',
    ];
    protected $guarded = [];
    protected $inventoryIns = null;

    //带有租户id字段的表名
    protected static $tenant_tables = [
        'admin_users',
        'admin_roles',
    ];

    /**
     * 模型的 "booted" 方法
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope('tenant_id', function (Builder $builder) {
            //这里查询过滤租户id 
            $permission = false;
            $from = $builder->getQuery()->from;
            if (strpos($from, ' as') !== false) {
                $table = trim(explode('as', $from)[1]);
            } else   $table = $builder->getModel()->table;
            if (substr($table, 0, 4) == "wms_" || in_array($table, static::$tenant_tables)) {
                $tenant_id = request()->header('tenant_id');
                if ($tenant_id) {
                    $builder->where($table . '.tenant_id', $tenant_id);
                }
            }
            if (defined('ADMIN_INFO')) {
                if (!empty(ADMIN_INFO['data_permission'])) {
                    $permission = ADMIN_INFO['data_permission'];
                    $columns = $builder->getQuery()->columns;
                    if ($columns == null) $columns = ['*'];
                    $origin_table = $builder->getModel()->table;
                    if (!$table) $table = $origin_table;
                    if ($columns) {
                        $ret = self::searchKey('warehouse_code', $columns, $table, $origin_table);
                        foreach ($ret as $item) {
                            $codes = $permission['warehouse'];
                            if ($codes) $builder->whereIn($item, $codes);
                        }

                        $ret = self::searchKey('sup_code', $columns, $table, $origin_table);
                        foreach ($ret as $item) {
                            $codes = $permission['supplier'];
                            if ($codes) $builder->whereIn($item, $codes);
                        }

                        $ret = self::searchKey('sup_id', $columns, $table, $origin_table);
                        foreach ($ret as $item) {
                            $codes = $permission['supplier2'];
                            if ($codes) $builder->whereIn($item, $codes);
                        }
                        $ret = self::searchKey('shop_code', $columns, $table, $origin_table);
                        foreach ($ret as $item) {
                            $codes = $permission['shop'];
                            if ($codes) $builder->whereIn($item, $codes);
                        }

                        // if (self::searchKey('warehouse_code', $columns,$table)) {
                        //     $codes = ADMIN_INFO['data_permission']['warehouse'];
                        //     $builder->whereIn($table . '.warehouse_code', $codes);
                        // }
                        // if (self::searchKey('sup_code', $columns,$table)) {
                        //     $codes = ADMIN_INFO['data_permission']['supplier'];
                        //     $builder->whereIn($table . '.sup_code', $codes);
                        // }
                        // if (self::searchKey('sup_id', $columns,$table)) {
                        //     $codes = ADMIN_INFO['data_permission']['supplier2'];
                        //     $builder->whereIn($table . '.sup_code', $codes);
                        // }
                        // if (self::searchKey('shop_code', $columns,$table)) {
                        //     $codes = ADMIN_INFO['data_permission']['shop'];
                        //     $builder->whereIn($table . '.shop_code', $codes);
                        // }
                    }
                    // if ($table == 'wms_shops') {
                    //     $codes = ADMIN_INFO['data_permission']['shop'];
                    //     $builder->whereIn($table . '.code', $codes);
                    // }
                }
            }

            // dump($permission,$table,$builder->getQuery()->wheres);
        });

        // $tenant_id = request()->header('tenant_id');
        // $permission = $tenant_id ? DataPermission::userPermissionList2($tenant_id,request()->header('user_code')) : [];

        // if ($permission) {
        //     static::addGlobalScope('data_permission', function (Builder $builder) use($permission) {
        //     });  
        // }
    }

    static private function searchKey($name, $columns, $table, $origin_table)
    {
        $arr = [
            'warehouse_code' => [
                'wms_warehouse',
                'wms_warehouse_area' => ['warehouse_code'],
                'wms_area_location',
                'wms_ib_order',
                'wms_arrival_regist',
                'wms_recv_order', 'wms_quality_list', 'wms_quality_confirm_list', 'wms_putaway_list',
                'wms_orders', 'wms_after_sale_orders',

                'wms_pre_allocation_strategy', 'wms_shipping_request', 'wms_pre_allocation_lists', 'wms_allocation_tasks', 'wms_shipping_orders', 'wms_task_strategies',

                'wms_inv_goods_detail', 'wms_total_inv', 'wms_sup_inv', 'wms_stock_logs',
                'wms_transfer_order' => ['in_warehouse_code', 'out_warehouse_code'],
                'wms_shipping_cancel', 'wms_other_ob_order', 'wms_other_ib_order', 'wms_stock_check_list', 'wms_stock_check_request',
                'wms_stock_differences', 'wms_stock_move_list',
                'wms_consignment_orders', 'wms_purchase_orders', 'wms_order_statements',
            ],
            'warehouse_name' => ['wms_unicode_print_log'],
            'sup_id' => [
                'wms_supplier' => ['id'], 'wms_consignment_settlement', 'wms_withdraw_request', 'wms_purchase_order_statements', 'wms_inv_goods_detail', 'wms_sup_inv', 'wms_stock_logs', 'wms_consignment_orders', 'wms_consignment_settlement', 'wms_withdraw_request', 'wms_purchase_orders', 'wms_purchase_order_statements',
            ],
            'sup_code' => ['wms_supplier', 'wms_supplier_documents'],
            'shop_name' => ['wms_shops' => ['name'], 'wms_order_statements', 'wms_shipping_request'],
            'shop_code' => ['wms_shops' => ['code'], 'wms_orders']
        ];

        if ($arr[$name][$origin_table] ?? []) {
            if (in_array($name, $columns)) return [$table . '.' . $name];
            if (is_array($columns)) {
                foreach ($columns as $column) {
                    if (strpos($column, $name) !== false && $column != '*') return [$table . '.' . $name];
                }
            }
        }

        if (in_array($table, $arr[$name] ?? [])) {
            if (isset($arr[$name][$origin_table])) {
                $val = $arr[$name][$origin_table];
            } else  $val = $table;
            if (is_string($val)) return [$table . '.' . $name];
            $ret = [];
            foreach ($val as $item) {
                $ret[] = $table . '.' . $item;
            }
            return $ret;
        }

        return [];
    }

    //符号
    protected $whereSymbol = [
        '>',
        '>=',
        '<',
        '<=',
        '=',
        '!=',
    ];
    //返回判断
    protected $baseReturn = [
        false,
        null,
        Null,
        'null',
        'Null',
    ];

    //用于特殊where查询
    protected  $whereSymbolWhere = [
        'gt'            => '>', //大于
        'gte'           => '>=', //大于等于
        'egt'           => '>=', //大于等于
        'lt'            => '<', //小于
        'lte'           => '<=', //小于等于
        'elt'           => '<=', //小于等于
        'eq'            => '=', //等于
        'neq'           => '!=', //不等于

        'in'            => 'in', //包括
        'contain'       => 'like', //包含
        'leftContain'   => '!=', //左包含
        'rightContain'  => '!=', //右包含
        'isNotNull'     => ['!=', 'Null'], //不为空
        'isNull'        => ['=', 'Null'], //为空
        // 'dynamic'       => 
    ];


    /**
     * 时间处理
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate($date)
    {
        return $date->format($this->dateFormat ?: 'Y-m-d H:i:s');
    }


    public function insertGetId(array $values, $sequence = null)
    {
        //新增插入租户id 
        $table = $this->getTable();
        if (substr($table, 0, 4) == "wms_" || in_array($table, static::$tenant_tables)) {
            $tenant_id = request()->header('tenant_id');
            if ($tenant_id) {
                $values['tenant_id'] = $tenant_id;
            }
        }
        return parent::insertGetId($values, $sequence);
    }

    public function insert(array $values)
    {

        if (empty($values[0])) {
            //新增插入租户id 
            $table = self::query()->getModel()->getTable();
            if (substr($table, 0, 4) == "wms_" || in_array($table, static::$tenant_tables)) {
                $tenant_id = request()->header('tenant_id');
                if ($tenant_id) {
                    $values['tenant_id'] = $tenant_id;
                }
            }
        }
        return parent::insert($values);
    }


    //通用修改器
    protected function mutateAttribute($key, $value)
    {
        try {
            return $this->{'get' . Str::studly($key) . 'Attribute'}($value);
        } catch (\Throwable $th) {
            //throw $th;
            $key =  Str::before($key, '_txt');
            if (isset($this->map[$key]) && isset($this->attributes[$key])) {
                $origin = $this->attributes[$key];
                $value = isset($this->map[$key][$origin]) ? $this->map[$key][$origin] : '未知';
                return  $value;
            }
            return $value;
        }
    }

    //通用修改器
    public function getAttribute($key)
    {
        if (substr($key, -4) == '_txt') {
            if (method_exists($this, 'get' . Str::studly($key) . 'Attribute')) return parent::getAttribute($key);
            $key =  Str::before($key, '_txt');
            if (isset($this->map[$key]) && isset($this->attributes[$key])) {
                $origin = $this->attributes[$key];
                $value = isset($this->map[$key][$origin]) ? $this->map[$key][$origin] : '未知';
                return  $value;
            }
        } else {
            return parent::getAttribute($key);
        }
    }

    /**
     *  公共封装 查询全部
     *
     * @param [array] $where
     * @param array $select
     * @param array $order
     * @return array
     */
    public function BaseAll($where, $select = ['*'], $order = [['id', 'desc']])
    {
        // $data = DB::table($this->table)->select($select);
        $data = $this::select($select)->where('status', 1);
        //处理条件
        foreach ($where as $v) {
            if (empty($v[1])) continue;
            if ($v[1] == 'in' || $v[1] == 'IN') {
                if (is_array($v[2])) {
                    $data->whereIn($v[0], $v[2]);
                } else {
                    $in  = explode(',', $v[2]);
                    $data->whereIn($v[0], $in);
                }
            }
            if ($v[1] == 'like') $data->where($v[0], $v[1], '%' . $v[2] . '%');
            if ($v[1] == 'allLike') $data->whereRaw($v[0], $v[2]);
            if ($v[1] == 'WHERE') {
                $sql = $this->jsonWhere($v[2]);
                $data->where(function ($q) use ($sql) {
                    return $q->whereRaw($sql);
                });
            }
            if (in_array($v[1], $this->whereSymbol)) $data->where($v[0], $v[1], $v[2]);
        }
        //处理排序
        foreach ($order as $Ok => $Ov) {
            if (empty($Ov[0]) || empty($Ov[1])) continue;
            $data->orderBy($Ov[0], $Ov[1]);
        }
        $reData = $data->get();
        if (empty($reData) || $reData == NULL)
            return [];
        else
            return objectToArray($reData);
    }

    public function withSearch($select)
    {
        return $this::select($select);
    }

    public function withInfoSearch($select)
    {
        return $this::select($select);
    }

    /**
     * 公共封装 总查询
     *
     * @param [array] $where
     * @param array $select
     * @param array $order
     * @param integer $page
     * @param integer $size
     * @return array
     */
    public function BaseLimit($where, $select = ['*'], $order = [['id', 'desc']], $cur_page = 1, $size = 10)
    {
        $data = $this->withSearch($select);
        // $data = DB::table($this->table)->select($select);
        //处理条件
        foreach ($where as $v) {
            if (empty($v[1])) continue;
            if ($v[0] == 'NUMBER') {
                $number = $v[2];
                $fields = $v[1];
                foreach ($fields as $field) {
                    if (strpos($field, '.') !== false) {
                        list($local_key, $r_value, $cond) = $this->relationSearch($field, $number);
                        $data->orWhere($local_key, $r_value);
                    } else {
                        $data->orWhere($field, $number);
                    }
                }
            }
            if ($v[1] == 'in' || $v[1] == 'IN') {
                if (is_array($v[2])) {
                    $data->whereIn($v[0], $v[2]);
                } else {
                    $in  = explode(',', $v[2]);
                    $data->whereIn($v[0], $in);
                }
            }
            if ($v[1] == 'like') $data->where($v[0], $v[1], '%' . $v[2] . '%');
            if ($v[1] == 'allLike') $data->whereRaw($v[0], $v[2]);
            if ($v[1] == 'WHERE' && !empty($v[2])) {
                $sql = $this->jsonWhere($v[2]);
                if (!empty($sql)) {
                    if (preg_match('/and|or/', $sql)) {
                        $data->where(function ($q) use ($sql) {
                            return $q->whereRaw($sql);
                        });
                    } else {
                        $data->whereRaw($sql);
                    }
                }
            }
            if (in_array($v[1], $this->whereSymbol)) $data->where($v[0], $v[1], $v[2]);
        }
        // dd($order);
        //处理排序
        foreach ($order as $Ok => $Ov) {
            if (empty($Ov[0]) || empty($Ov[1])) continue;
            $data->orderBy($Ov[0], $Ov[1]);
        }
        // dd($data->toSql(),$data->getBindings());
        // dd($data->paginate($size,['*'],'page',$cur_page)->toArray());
        if (request()->get('groupByRaw')) $data->groupByRaw(request()->get('groupByRaw'));
        $reData = $data->paginate($size, ['*'], 'page', $cur_page);
        if (request()->get('with_as')) $reData->load(explode(',', request()->get('with_as')));
        if (method_exists($this, '_formatListObj')) $reData = $this->_formatListObj($reData);
        $reData = $reData->toArray();
        if (method_exists($this, '_formatList')) $reData = $this->_formatList($reData);

        return $reData;
    }

    /**
     *  公共封装 单个查询
     *
     * @param [array] $where
     * @param array $select
     * @param array $order
     * @return array
     */
    public function BaseOne($where = [], $select = ['*'], $order = [['id', 'desc']])
    {
        // $data = DB::table($this->table)->select($select);
        $data = $this->withInfoSearch($select);
        //处理条件
        foreach ($where as $v) {
            if (empty($v[0]) || empty($v[1])) continue;
            if ($v[1] == 'in' || $v[1] == 'IN') $data->whereIn($v[0], $v[2]);
            if ($v[1] == 'like') $data->where($v[0], $v[1], '%' . $v[2] . '%');
            if (in_array($v[1], $this->whereSymbol)) $data->where($v[0], $v[1], $v[2]);
        }
        //处理排序
        foreach ($order as $Ok => $Ov) {
            if (empty($Ov[0]) || empty($Ov[1])) continue;
            $data->orderBy($Ov[0], $Ov[1]);
        }
        $reData = $data->first();
        if (empty($reData) || $reData == NULL)
            return [];
        else
            if (method_exists($this, '_formatOne')) $reData = $this->_formatOne($reData);
        return objectToArray($reData);
    }

    /**
     * 公共封装 新增数据
     *
     * @param array $data
     * @return int|false
     */
    public  function BaseCreate($CreateData = [])
    {
        if (empty($CreateData)) return false;
        // $id = DB::table($this->table)->insertGetId($CreateData);
        $id = $this::insertGetId($CreateData);
        if (empty($id)) return false;
        if (method_exists($this, '_afterCreate'))  $this->_afterCreate($CreateData);
        return $id;
    }

    /**
     *  公共封装修改数据
     *
     * @param [array] $where 修改条件
     * @param [array] $update 修改数据
     * @return false|array
     */
    public function BaseUpdate($where, $update)
    {
        if (empty($update)) return false;
        // $data = DB::table($this->table);
        $data = $this::select();
        // var_dump($where);exit;
        foreach ($where as $v) {
            if (empty($v[0]) || empty($v[1])) continue;
            if ($v[1] == 'in' || $v[1] == 'IN') $data->whereIn($v[0], $v[2]);
            if (in_array($v[1], $this->whereSymbol)) $data->where($v[0], $v[1], $v[2]);
        }
        $reData = $data->update($update);
        if (empty($reData) || $reData == false || $reData == NULL) return false;
        if (method_exists($this, '_afterUpdate'))  $this->_afterUpdate($data, $update);
        return $reData;
    }

    /**
     * 公共封装删除 支持删除多个id,
     *
     * @param [string] $ids 要删除的数据
     * @param [string] $name 要删除的字段
     * @return false|true
     */
    public function BaseDelete($ids, $name = 'id')
    {
        if (empty($ids)) return false;
        $id = explode(',', $ids);
        // $data = DB::table($this->table)->whereIn($name,$id)->delete();
        request()->merge(['d_data' => DB::table($this->table)->whereIn($name, $id)->get()->toArray()]);
        $data = $this::whereIn($name, $id)->delete();
        if (empty($data)  || $data == false || $data == NULL) return false;
        return $data;
    }


    //
    public function nameValue($id, $name, $id_name = 'id', $where = '=')
    {
        // $value = DB::table($this->table)->where($id_name,$where,$id)->value($name);
        $value = $this::where($id_name, $where, $id)->value($name);
        return $value;
    }

    public function getRelValue($name, $value)
    {
        $relation = explode('.', $name);
        $last = end($relation);
        $method = null;
        foreach ($relation as $i => $item) {
            if ($item != $last) {
                $related =  Str::camel($item);
                if ($method) $relation = $method->$related();
                else  $relation = $this->$related();
                $method = $relation->getModel();
                $map[] = [
                    'model' => $relation->getModel(),
                    'ownerKey' => method_exists($relation, 'getOwnerKeyName') ? $relation->getOwnerKeyName() : null,
                    'foreignKey' => $relation->getForeignKeyName(),
                ];
            }
        }
        return ($this->getFinalData($map, $value, $last));
    }

    public function getFinalData($map, $value, $last_name)
    {
        $map = array_reverse($map);
        $value = explode(',', $value);
        foreach ($map as $k => $item) {
            $name = empty($name) ? $last_name : $map[$k - 1]['foreignKey'];
            $col_name =  $item['foreignKey'];
            $own = $item['ownerKey'] ?? $col_name;
            $value = $item['model']->whereIn($name, $value)->get()->pluck($own)->toArray();
        }
        return ['value' => $value, 'name' => $col_name];
    }


    //多条件整理sql
    public  function jsonWhere($where)
    {
        $sql = "";
        // $whereSymbolWhere = new self();
        $SWhere = $this->whereSymbolWhere;
        $condition = [];
        foreach ($where as $k => $s_v) {
            $s_v['value'] = is_string($s_v['value']) ? trim($s_v['value']) : $s_v['value'];
            if (!in_array($s_v['condition'], ['isNull', 'isNotNull']) && ($s_v['value'] === null || $s_v['value'] === '')) continue;
            if (is_array($s_v['value']) && empty($s_v['value'])) continue;

            $condition[] = $s_v['condition_status'];
            if (count($condition) > 1) {
                $sql .= $condition[$k - 1];
                $sql .= " ";
            }
            $map = empty($this->map) ? [] : array_keys($this->map);
            if (method_exists($this, 'searchUser')) {
                $user_map = $this->searchUser();
            } else $user_map = [];
            // if (in_array($s_v['name'], $map)) {
            //     //判断字段是否在map 中
            //     $s_v['condition'] = 'eq';
            // } else
            if ($s_v['name'] == 'quality_type') {
                if ($s_v['value'] == '正品') {
                    $s_v['value'] = 1;
                } else {
                    $s_v['value'] = 2;
                }
            } elseif (method_exists($this, 'search' . Str::studly($s_v['name']))) {
                //自定义查找
                $method = 'search' . Str::studly($s_v['name']);
                list($s_v['name'], $s_v['value']) = $this->$method($s_v['value']);
                if (empty($s_v['value'])) $s_v['value'] = '';
                if (is_array($s_v['value'])) $s_v['condition'] = 'in';
            } elseif (in_array($s_v['name'], array_keys($user_map))) {
                //用户查找
                // $id = AdminUsers::where('username', $s_v['value'])->orderBy('created_at', 'desc')->value('id') ?? 0;
                $id = $this->_getRedisMap('user_name_map', $s_v['value']);
                $s_v['name'] = $user_map[$s_v['name']];
                $s_v['value'] = $id;
                $s_v['condition'] = 'eq';
            } elseif (strpos($s_v['name'], '.') !== false) {
                //判断是否是联表查询
                list($r_field, $r_value, $r_condition) = $this->relationSearch($s_v['name'], $s_v['value'], $s_v['condition']);
                $s_v['name'] = $r_field;
                if (empty($r_value)) $r_value = '未找到';
                $s_v['value'] = $r_value;
                if (is_string($s_v['value']) && $s_v['condition'] == 'in') $s_v['condition'] = 'eq';
                $s_v['condition'] = $r_condition;
            }
            if (!$s_v['name']) continue;
            if ($s_v['condition'] == 'activeTime' && in_array($s_v['value'], ['yesterday', 'today', 'thisWeek', 'thisMonth'])) {
                $start = '';
                $end = '';
                switch ($s_v['value']) {
                    case 'yesterday':
                        $start = date('Y-m-d 00:00:00', strtotime('-1 day'));
                        $end = date('Y-m-d 00:00:00');
                        break;
                    case 'today':
                        $start = date('Y-m-d 00:00:00');
                        $end = date('Y-m-d 00:00:00', strtotime('+1 day'));
                        break;
                    case 'thisWeek':
                        $start = date('Y-m-d H:i:s', strtotime("this week Monday"));
                        break;
                    case 'thisMonth':
                        $start = date('Y-m-01 00:00:00');
                        break;
                }
                $tmp = $end ? sprintf(' and %s < "%s"', $s_v['name'], $end) : '';
                if ($start) $sql .= sprintf('(%s >= "%s" %s)', $s_v['name'], $start, $tmp);
                continue;
            }
            if ($s_v['condition'] == 'leftContain') {
                $sql .= sprintf(' (%s like "%s")', $s_v['name'],  $s_v['value'] . '%');
                continue;
            }
            if ($s_v['condition'] == 'rightContain') {
                $sql .= sprintf(' (%s like "%s")', $s_v['name'],  '%' . $s_v['value']);
                continue;
            }
            if ($s_v['condition'] == 'notIn') {
                if (is_array($s_v['value'])) {
                    $sql .= sprintf(' (%s not IN (%s))', $s_v['name'], implode(',', $s_v['value']));
                } else {
                    $sql .= sprintf(' (%s !=%s)', $s_v['name'], $s_v['value']);
                }
                continue;
            }
            if ($s_v['condition'] == 'isNull') {
                $sql .= sprintf(' (%s is null or %s="")', $s_v['name'], $s_v['name']);
                continue;
            }
            if ($s_v['condition'] == 'isNotNull') {
                $sql .= sprintf(' (%s is not null)', $s_v['name']);
                continue;
            }

            $sql .= $s_v['name']; //字段名
            $sql .= " ";
            $sql .=  is_array($SWhere[$s_v['condition']]) ? $SWhere[$s_v['condition']][0] : $SWhere[$s_v['condition']]; //条件
            // $sql .=  is_int($s_v['value']) ? " " :" '";
            if (is_string($s_v['value']) &&  $SWhere[$s_v['condition']] != 'in') $sql .= " '";
            else $sql .= " ";
            if (is_array($SWhere[$s_v['condition']])) {
                $sql .= $SWhere[$s_v['condition']][1];
            } else {
                if ($s_v['condition'] == 'contain') {
                    if (is_string($s_v['value'])) $sql .= '%' . $s_v['value'] . '%'; //条件;
                    else $sql .= '\'%' . $s_v['value'] . '%\''; //条件;
                } elseif ($s_v['condition'] == 'in') {

                    if (is_string($s_v['value'])) {
                        $sql .= ' ("' .  $s_v['value'] . '")'; //条件;

                    } else {
                        $sql .= ' (';
                        if (is_array($s_v['value'])) {
                            foreach ($s_v['value'] as $v) {
                                $sql .= "'" . $v . "',";
                            }
                        }
                        $sql = substr_replace($sql, '', -1, 1);
                        $sql .= ' )';
                        // $s_v['value'] =$r_value ;
                        // $sql .= ' (' . implode(',', $s_v['value']) . ')'; //条件;
                    }
                } else {
                    $sql .= $s_v['value']; //条件;
                }
            }
            if (is_string($s_v['value']) &&  $SWhere[$s_v['condition']] != 'in') $sql .= "' ";
            else $sql .= " ";
            // var_dump($SWhere[$s_v['condition']]);
        }
        return $sql;
    }

    public static function  getErpCode($pre, $len = 10, $data = true)
    {
        return BaseController::getErpCode($pre, $len, $data);
    }

    //获取用户名
    public static function getAdminUser($id, $tenant_id = 0)
    {
        if ($id == SYSTEM_ID) return '系统';
        if ($id == 0) return '';
        $item = AdminUsers::where('id', $id)->first();
        if (empty($item)) return '';
        return $item->username;
    }

    //获取仓库名称
    public static function getWarehouseName($warehouse_code, $tenant_id = null)
    {
        if (empty($tenant_id)) $tenant_id = request()->header('tenant_id');
        $item = DB::table('wms_warehouse')->where('tenant_id', $tenant_id)->where('status', 1)->where('warehouse_code', $warehouse_code)->first();
        if (empty($item)) return '';
        return $item->warehouse_name;
    }


    public static function getWarehouseCode($warehouse_name, $tenant_id = null)
    {
        if (empty($tenant_id)) $tenant_id = request()->header('tenant_id');
        $item = DB::table('wms_warehouse')->where('tenant_id', $tenant_id)->where('status', 1)->where('warehouse_name', $warehouse_name)->orderBy('id', 'desc')->first();
        if (empty($item)) return '';
        return $item->warehouse_code;
    }

    //获取物流
    public static function getLogProdName($log_prod_code)
    {
        $product_name = WmsLogisticsProduct::where('product_code', $log_prod_code)->value('product_name');
        return $product_name ?? '';
    }

    //Excel 修改器
    public function excelSetAttr()
    {
        $map = $this->map;
        $dedit = [];
        if (empty($map)) return $dedit;
        foreach ($map as $k => $v) {
            $dedit[$k] = array_flip($v);
        }
        return $dedit;
    }

    //Model 获取器
    public function getAttrMap()
    {
        $map = $this->map;
        if (empty($map)) return;
        $dedit = [];
        foreach ($map as $k => $v) {
            $dedit[$k] = array_flip($v);
        }
        return $dedit;
    }

    public function inventoryIns()
    {
        if (!$this->inventoryIns) $this->inventoryIns = new Inventory();
        return $this->inventoryIns;
    }

    public static function getObGoodsTask($key)
    {
        return json_decode(Redis::hget('wms_allocation_task', $key), 1);
    }

    public static function setObGoodsTask($key, $data)
    {
        return Redis::hset('wms_allocation_task', $key, json_encode($data));
    }

    public static function delObGoodsTask($key)
    {
        return Redis::hdel('wms_allocation_task', $key);
    }

    public function startTransaction()
    {
        DB::beginTransaction();
    }
    public function endTransaction($res, $data = null)
    {
        $err_msg = '操作失败';
        $c = array_filter($res, function ($v) use (&$err_msg) {
            if (is_array($v)) {
                $err_msg = $v[1];
                return empty($v[0]);
            } else {
                return empty($v);
            }
        });
        if (empty($c)) {
            DB::commit();
            if ($data) return [true, $data];
            return [true, '创建成功'];
        } else {
            log_arr($res, 'wms', $err_msg);
            DB::rollBack();
            return [false, $err_msg];
        }
    }

    // 格式化展示商品信息
    public static function productFormat(&$item, $append = [])
    {
        if (!$item) return;
        if (isset($item[0])) {
            foreach ($item as &$order) {
                if (!empty($order['details'])) {
                    foreach ($order['details'] as &$pro) {
                        $pro['product_sn'] = $pro['product']['product']['product_sn'];
                        $pro['name'] = $pro['product']['product']['name'];
                        // $pro['img'] = $pro['product']['product']['img'];
                        $pro['sku_code'] = $pro['product']['sku'];
                        $pro['spec_one'] = $pro['product']['spec_one'];
                        // $pro['product_bar_code'] = $pro['product']['bar_code'];
                        unset($pro['product']);
                        if ($append) {
                            foreach ($append as $k => $v) {
                                $pro[$k] = $v;
                            }
                        }
                    }
                }
            }
        } else {
            if (!empty($item['details'])) {
                foreach ($item['details'] as &$pro) {
                    $pro['product_sn'] = $pro['product']['product']['product_sn'];
                    $pro['name'] = $pro['product']['product']['name'];
                    // $pro['img'] = $pro['product']['product']['img'];
                    $pro['sku_code'] = $pro['product']['sku'];
                    $pro['spec_one'] = $pro['product']['spec_one'];
                    // $pro['product_bar_code'] = $pro['product']['bar_code'];
                    if (method_exists($pro, 'makeHidden')) $pro->makeHidden('product');
                    else unset($pro['product']);
                    if ($append) {
                        foreach ($append as $k => $v) {
                            $pro[$k] = $v;
                        }
                    }
                }
            }
        }
    }

    public function getQualityTypeAttribute($value)
    {
        return $value == 2 ? '瑕疵' : '正品';
    }

    public function getProductNameAttribute($value)
    {
        $value =  $this->product->product->name;
        return $value ? $value : '';
    }
    public function getProductSpecAttribute($value)
    {
        $value = $this->product->spec_one;
        return $value ? $value : '';
    }
    public function getProductSkuAttribute($value)
    {
        $value =  $this->product->sku;
        return $value ? $value : '';
    }
    public function getProductSnAttribute($value)
    {
        $value = $this->product->product->product_sn;
        return $value ? $value : '';
    }


    //仓库名
    public function getWhNameAttribute($value)
    {
        $value =  $this->warehouse->warehouse_name;
        return $value ? $value : '';
    }
    public function getSupplierNameAttribute($value)
    {
        $value = $this->supplier->name;
        return $value ? $value : '';
    }

    //关联关系搜索
    public function relationSearch($field, $value, $condition = 'eq')
    {
        // //用户查找
        // if (method_exists($this, 'searchUser')) {
        //     $user_map = $this->searchUser();
        //     if (in_array($field, array_keys($user_map))) {;
        //         $id = AdminUsers::where('username', $value)->orderBy('created_at', 'desc')->value('id') ?? 0;
        //         return [$user_map[$field], $id, 'eq'];
        //     }
        // }
        $where = 'where';
        //判断条件
        $SWhere = $this->whereSymbolWhere;
        $r_condition = '=';
        if ($condition == 'isNull') {
            $where = 'whereNull';
        } elseif ($condition == 'isNotNull') {
            $where = 'whereNotNull';
        } elseif ($condition == 'contain') {
            $r_condition = 'like';
            $value = '%' . $value . '%'; //条件;
        } elseif ($condition == 'in') {
            $where = 'whereIn';
            if (!is_array($value)) $value = explode(',', $value);
        } else {
            if (is_array($SWhere[$condition])) {
                $r_condition = $SWhere[$condition][1];
            }
        }

        $relation = explode('.', $field);
        $s_name = array_pop($relation);
        $relation_model = $this;
        $relation_models = [];
        $s_key[] = $s_name;
        $local_keys = [];
        $fore_keys = [];
        $r_value = $value;

        foreach ($relation as $i => $r) {
            $r_name = Str::camel($r);
            if (method_exists($relation_model, $r_name)) {
                if (method_exists($relation_model->$r_name(), 'getChild')) {
                    $local_key = $relation_model->$r_name()->getOwnerKeyName();
                    $fore_key = $relation_model->$r_name()->getForeignKeyName();
                } else {
                    //hasOne关系
                    $fore_key = $relation_model->$r_name()->getLocalKeyName();
                    $local_key = $relation_model->$r_name()->getForeignKeyName();
                }
                $relation_model = $relation_model->$r_name()->getModel();
                $relation_models[] = $relation_model;
                $local_keys[] = $local_key;
                $fore_keys[] = $fore_key;
            }
        }
        $local_keys = array_reverse($local_keys);
        $fore_keys = array_reverse($fore_keys);

        foreach (array_reverse($relation_models) as $i => $model) {
            if (in_array($condition, ['isNull', 'isNotNull'])) {
                $_value = $model->$where($s_key[$i])->pluck($local_keys[$i]);
            } elseif ($condition == 'in') {
                $_value = $model->$where($s_key[$i], $r_value)->pluck($local_keys[$i]);
            } else {
                $_value =  $model->$where($s_key[$i], $r_condition, $r_value)->pluck($local_keys[$i]);
            }
            if ($_value->isEmpty())  return [array_pop($fore_keys), '未找到符合条件的数据', $condition];

            if ($local_keys[$i] == 'bar_code') {
                if ($_value->count() > 1) {
                    $where = 'whereIn';
                    $condition = 'in';
                    $r_value = $_value->diff(['']);
                } else {
                    $where = 'where';
                    $condition = 'eq';
                    $r_value =  $_value->first();
                }
            } else {
                if ($_value->count() > 1) {
                    $where = 'whereIn';
                    $condition = 'in';
                    $r_value = $_value;
                } else {
                    $where = 'where';
                    $condition = 'eq';
                    $r_value =  $_value->first();
                }
            }

            $s_key[] = $fore_keys[$i];
        }
        if (is_object($r_value) && $condition == 'in') $r_value = objectToArray($r_value);
        if (is_object($r_value)) {
            $r_value = strval($r_value);
            $r_value =  substr_replace($r_value, '', 0, 2);
            $r_value =  substr_replace($r_value, '', -2, 2);
        }
        return [array_pop($fore_keys), $r_value, $condition];
    }

    // 导入时必填字段
    public $requiredColumns = [];

    function columnOptions()
    {
        return [];
    }

    function showColumns()
    {
        $name = $this->getTable();
        $options = $this->columnOptions();
        foreach ($this->columns() as $item) {
            if ($item['search'] ?? true) {
                if (substr($item['value'], -3) == '_at' || substr($item['value'], -5) == '_time' || substr($item['value'], -8) == 'deadline') $item['type'] = 'date';
                if (empty($item['lang'])) {
                    if (strpos($item['value'], '.') === false) {
                        $item['label'] = __(sprintf('columns.%s.%s', $name, $item['value']));
                    } else {
                        $item['label'] = __(sprintf('columns.%s', $item['value']));
                    }
                } else {
                    if (strpos($item['lang'], '.') === false) {
                        $item['label'] = __(sprintf('columns.%s.%s', $name, $item['lang']));
                    } else {
                        $item['label'] = __(sprintf('columns.%s', $item['lang']));
                    }
                }
                if ($options[$item['value']] ?? []) {
                    $item['statusOPtion'] = $options[$item['value']];
                }
                BaseLogic::searchColumnAppend($item);
                $arr[] = $item;
            }
        }
        return $arr;
    }


    function exportConfig($templete = false, $type = 1)
    {
        $name = $this->getTable();
        $columns = [];
        foreach ($this->columns() as $item) {
            if ($item['export'] ?? true) {
                $value = str_replace('_txt', '', $item['value']);
                if (strpos($value, '.') !== false) {
                    $item['label'] = __(sprintf('columns.%s', $value));
                } else {
                    $item['label'] = __(sprintf('columns.%s.%s', $name, $value));
                }
                if ($templete && in_array($item['value'], $this->requiredColumns)) {
                    if ($type == 1) $item['label'] .= '|required';
                    if ($type == 2) $item['required'] = true;
                }
                if ($type == 2) {
                    $item['colspan'] = $item['colspan'] ?? 1;
                }
                $index = $item['index'] ?? 1;
                $columns[$index][] = $item;
            }
        }
        if ($type == 1) {
            $headers = [];
            foreach ($columns as $col) {
                $keys = array_column($col, 'value');
                $value = array_column($col, 'label');
                $headers[] = array_combine($keys, $value);
            }
        }
        if ($type == 2) {
            $headers = array_values($columns);
        }

        return [
            'headers' => $headers,
            'title' => __(sprintf('columns.%s.table_title', $name)),
            'type' => $type,
        ];
    }

    static function cloumnOptions($maps): array
    {
        $data = [];
        foreach ($maps as $value => $label) {
            $data[] = [
                'label' => $label, 'value' => $value
            ];
        }
        return $data;
    }

    static function orderPlatform()
    {
        return  [
            1 => '其他',
            2 => '手工',
            3 => '淘宝',
            4 => '天猫',
            5 => '京东',
            6 => '寄卖召回',
            7 => '手工召回',
            8 => '采购系统',
            9 => '第三方平台',
            10 => '仓内移位',
            11 => '得物普通现货',
            12 => '抖音小店',
            13 => '得物极速预售',
            14 => '得物普货预售',
            15 => '拼多多',
            16 => '唯品会',
            17 => '得物跨境',
            18 => '得物品牌直发',
            19 => '得物极速',
            20 => '调拨',
        ];
    }

    static private function _mapRedis($name, $map)
    {
        foreach ($map as $code => $id) {
            Redis::hset('wms:'.$name. ':'. request()->header('tenant_id'), $code, $id);
        }
    }

    static function _allRedisMap($name,$tenant_name)
    {
        $data =  Redis::hgetall($tenant_name)??[];
        switch ($name) {
            case 'sup_map':
                $supplier2 = ADMIN_INFO['data_permission']['supplier2'] ?? [];
                if (!$supplier2) return $data;
                $arr = [];
                foreach ($data as $k => $val) {
                    if (in_array($k, $supplier2)) {
                        $arr[$k] = $val;
                    }
                }
                return $arr;
            case 'warehouse_map':
                $warehouse = ADMIN_INFO['data_permission']['warehouse'] ?? [];
                if (!$warehouse) return $data;
                $arr = [];
                foreach ($data as $k => $val) {
                    if (in_array($k, $warehouse)) {
                        $arr[$k] = $val;
                    }
                }
                return $arr;
            case 'warehouse_map':
                $shop = ADMIN_INFO['data_permission']['shop_name'] ?? [];
                if (!$shop) return $data;
                $arr = [];
                foreach ($data as $k => $val) {
                    if (in_array($val, $shop)) {
                        $arr[$k] = $val;
                    }
                }
                return $arr;
        }
        return $data;
    }

    static function _getRedisMap($name, $field = null)
    {
        $tenant_name ='wms:'.$name. ':'.request()->header('tenant_id');
        if ($field === null) {
            // $data =  Redis::hgetall($tenant_name)??[];
            $data = self::_allRedisMap($name,$tenant_name);
        }
        $data = Redis::hget($tenant_name, $field)??'';

        if (empty($data)) {
            switch ($name) {
                case 'sup_map':
                    if (!Redis::exists($tenant_name)) {
                        $sup_map = Supplier::where('tenant_id', request()->header('tenant_id'))->pluck('name', 'id')->toArray();
                        self::_mapRedis('sup_map', $sup_map);
                    }
                    break;
                case 'warehouse_map':
                    if (!Redis::exists($tenant_name)) {
                        $warehouse_map  = Warehouse::where('tenant_id', request()->header('tenant_id'))->pluck('warehouse_name', 'warehouse_code');
                        self::_mapRedis('warehouse_map', $warehouse_map);
                    }
                    break;
                case 'user_map':
                    if (!Redis::exists($tenant_name)) {
                        $user_map = ModelsAdminUsers::where('tenant_id', request()->header('tenant_id'))->pluck('username', 'id')->toArray();
                        self::_mapRedis('user_map', $user_map);
                    }
                    break;
                case 'user_name_map':
                    if (!Redis::exists($tenant_name)) {
                        $user_map = ModelsAdminUsers::where('tenant_id', request()->header('tenant_id'))->pluck('id', 'username')->toArray();
                        self::_mapRedis('user_name_map', $user_map);
                    }
                    break;
                case 'shop_map':
                    if (!Redis::exists($tenant_name)) {
                        $user_map = WmsShop::where('tenant_id', request()->header('tenant_id'))->pluck('name', 'id')->toArray();
                        self::_mapRedis('shop_map', $user_map);
                    }
                    break;

                default:
                    # code...
                    break;
            }
        }
        if ($field === null) {
            // return Redis::hgetall($tenant_name)??[];
            return self::_allRedisMap($name,$tenant_name);
        }
        return Redis::hget($tenant_name, $field)??'';
    }

    public static function query2SyncAdd($inv_update_redis){
        Redis::rpush(RedisKey::QUEUE2_AYSNC_HADNLE, json_encode($inv_update_redis));
    }
}
