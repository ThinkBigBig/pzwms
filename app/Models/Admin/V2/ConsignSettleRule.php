<?php

namespace App\Models\Admin\V2;

use App\Logics\traits\DataPermission;
use App\Logics\wms\Purchase;
use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;
use Illuminate\Support\Facades\DB;

class ConsignSettleRule extends wmsBaseModel
{
    use HasFactory, DataPermission;
    protected $table = 'wms_consignment_settlement_rules'; //寄卖结算规则

    protected $guarded = [];
    // protected $map = [
    //     'status' => [0 => '暂存', 1 => '审核中', 2 => '已审核', 4 => '已确认', 5 => '已取消', 6 => '已驳回'],
    //     'receive_status' => [0 => '待收货', 1 => '已收货'],
    //     'source_type' => [1 => '手工创建', 2 => '手工导入'],
    //     'pay_status' => [0 => '未付款', 1 => '已付款'],
    // ];

    protected $map;

    protected $appends = ['status_txt', 'object_txt', 'users_txt',];

    public function __construct($params = [])
    {
        parent::__construct($params);
        $this->map = [
            'status' => [1 => __('status.enable'), 0 => __('status.disabled')],
            'object' =>  [1 => __('status.sale'),  2 => __('status.sale_after'), 3 => __('status.product.accessories')],
        ];
    }

    private static function ruleMap()
    {
        return  [
            'bid_price' => __('response.prePrice'),
            'deal_price' => __('response.productDealPrice'),
            'actual_deal_price' => __('response.productRealDealPrice'),
            'num' => __('response.num'),
        ];
    }
    public function searchUser()
    {
        return [
            'users_txt.created_user' => 'created_user',
            'users_txt.updated_user' => 'updated_user',
        ];
    }

    public function getContentTxtAttribute($key)
    {
        if ($this->content) return json_decode($this->content, 1);
        return '';
    }

    public function getFormulaTxtAttribute($key)
    {
        // $map = array_flip($this->ruleMap());
        $formula =  $this->ruleFormat($this->formula, $this->ruleMap());
        return $formula;
    }

    public function category()
    {
        return $this->belongsTo(ConsignSettleCategory::class, 'category_code', 'code');
    }
    public function ruleFormat($formula, $map = [])
    {
        if (!$map) $map = array_flip($this->ruleMap());
        $formula = preg_replace_callback('/(?<=\[).+?(?=\])/', function ($match) use ($map) {
            if (isset($map[$match[0]])) {
                return $map[$match[0]];
            }
            return $match[0];
        }, $formula);
        return $formula ?? '';
    }
    public static  function ruleVerify($formula, $map = [])
    {
        if (!$map) $map = array_fill_keys(array_keys(self::ruleMap()), 1);
        $formula = preg_replace_callback('/\[(.+?)\]/', function ($match) use ($map) {
            if (isset($map[$match[1]])) {
                return $map[$match[1]];
            }
            return $match[0];
        }, $formula);
        try {
            eval("\$result =  $formula;");
            if (is_numeric($result)) return $result;
            else return false;
        } catch (\Throwable $th) {
            return false;
        }
    }
    public function getUsersTxtAttribute($key)
    {
        $res['created_user'] = $this->getAdminUser($this->created_user);
        $res['updated_user'] = $this->getAdminUser($this->updated_user);
        return $res;
    }
    public function _formatOne($reData)
    {
        $reData->category_name = $reData->category->name ?? '';
        $reData->content = $reData->content_txt;
        $reData->formula = $reData->formula_txt;
        $reData->makeHidden('category');
        return $reData;
    }

    public function ruleColumn()
    {
        $consign_model = new WmsConsigmentSettlement;
        $showColumns = $consign_model->showColumns();
        $field = ['sup_name', 'shop_name', 'warehouse_name', 'third_code', 'buyer_account', 'order_at', 'sku_total', 'deal_price', 'product_sn', 'spec_one', 'bid_price', 'sku'];
        $columns = [];
        foreach ($showColumns as $k => $v) {
            $index = array_search($v['value'], $field);

            if ($index !== false) {
                $columns[$index] = $v;
            }
        }
        $put_field = array_diff(array_keys($field), array_keys($columns));
        foreach ($put_field as $i) {
            $temp = [];
            if ($field[$i] == 'warehouse_name') {
                $temp['value'] = $field[$i];
                $temp['label'] = __('excel.warehouse_name');
                $warehouse = Warehouse::where('status', 1)->select('warehouse_code', 'warehouse_name')->get();
                foreach ($warehouse as $item) {
                    $temp['statusOPtion'][] = [
                        'label' => $item['warehouse_name'],
                        'value' => $item['warehouse_name'],
                    ];
                }
            }
            if ($field[$i] == 'buyer_account') {
                $temp['value'] = $field[$i];
                $temp['label'] = __('columns.wms_order_statements.buyer_account');
            }
            if ($field[$i] == 'sku_total') {
                $temp['value'] = $field[$i];
                $temp['label'] = __('columns.wms_pre_allocation_lists.sku_num');
            }
            $columns[$i] = $temp;
        }
        ksort($columns);
        $list['column'] = $columns;
        $list['formula'] = self::ruleMap();
        // $list['formula'] = array_map(function($v){
        //     return '['.$v.']';
        // },self::ruleMap());
        return $list;
    }

    // 指定规则结算 $ids //结算账单id  //$rule_id 规则id
    public static function settleByRule(array $ids, $rule_id = 0)
    {
        //查询结算规则
        $consign_model = new WmsConsigmentSettlement;
        $stattlement_status = [0];
        if($rule_id!=0)$stattlement_status = [0,1];
        $items_sale  = $consign_model->whereIn('id', $ids)->whereIn('stattlement_status', $stattlement_status)->where('type', 1);
        $items_sale_after  = $consign_model->whereIn('id', $ids)->whereIn('stattlement_status', $stattlement_status)->whereIn('type', [2, 3]);
        //无结算账单
        if ($items_sale->doesntExist() && $items_sale_after->doesntExist()) return [false,__('response.consign_wait_settlement_not_exists')];
        $time = date('Y-m-d H:i:s');
        //指定规则
        if ($rule_id != 0) {
            $rules = self::where('status', 1)->where('id',$rule_id)->where('start_at', '<=', $time)->where('end_at', '>=', $time)->orderBy('sort', 'desc')->orderBy('created_at','desc')->get()->groupBy('object');
        } else {
            //按规则结算
            $rules = self::where('status', 1)->where('start_at', '<=', $time)->where('end_at', '>=', $time)->orderBy('sort', 'desc')->orderBy('created_at','desc')->get()->groupBy('object');
        }

        //无规则 //直接返回
        if ($rules->isEmpty()) return [false,__('response.consign_rule_not_exists')];
        //按规则结算
        //销售结算
        $codes = [
            'all_codes'=>[],
            'success_codes'=>[],
        ];
        $un_rule_codes = [];
        if ($items_sale->exists()) {
            if (isset($rules[1])) {
               self::_toSettle($consign_model, $rules[1], $items_sale,$codes);
            }
        }
        //售后结算
        if ($items_sale_after->exists()) {
            if (isset($rules[2])) {
                self::_toSettle($consign_model, $rules[2], $items_sale_after,$codes, 2);
            }
        }
        if(empty($codes['success_codes'])) return [false,__('response.consign_settlement_all_fail')];
        $un_rule_codes = array_diff($codes['all_codes'],$codes['success_codes']);
        if(empty($un_rule_codes )) return [true,__('response.consign_settlement_all_success')];
        return [true,sprintf(__('response.consign_settlement_part_success'),implode(',',$codes['success_codes']),implode(',',$un_rule_codes))];
    }

    private static function _toSettle($model, $rules, $item2,&$codes, $type = 1)
    {
        $codes['all_codes'] = array_merge($codes['all_codes'],$item2->pluck('origin_code')->toArray());
        // $un_rule_codes =  $codes ;
        foreach ($rules as $rule) {
            $item = clone $item2;
            $query = $item->getQuery();
            $content = $rule->content;
            if (preg_match('/warehouse_name/', $content)) {
                $rep = $type = 1 ? 'send_warehouse_name' : 'return_warehouse_name';
                $content = preg_replace('/warehouse_name/', $rep, $content);
            };
            $content = json_decode($content, 1);
            if ($content) $sql = $model->jsonWhere($content);
            if (!empty($sql)) {
                if (preg_match('/and|or/', $sql)) {
                    $query->where(function ($q) use ($sql) {
                        return $q->whereRaw($sql);
                    });
                } else {
                    $query->whereRaw($sql);
                }
            }
            //符合规则
            if ($query->exists()) {
                foreach ($query->get() as $order) {
                    $map = [
                        'bid_price' => $order->bid_price,
                        'deal_price' => $order->deal_price,
                        'actual_deal_price' => $order->actual_deal_price,
                        'num' => $order->num,
                    ];
                    $stattlement_amount = self::ruleVerify($rule->formula, $map);
                    $update = [
                        'stattlement_status' => 1,
                        'stattlement_amount' => $stattlement_amount,
                        'settlement_at' => date('Y-m-d H:i:s'),
                        'rule_code' => $rule->code ?? '',
                        'rule_name' => $rule->name ?? '',
                        'updated_user' =>defined('ADMIN_INFO')?(ADMIN_INFO['user_id'] ?? 0):0,
                    ];
                    $row = $model->where('id', $order->id)->update($update);
                    // if (!$row) return false;
                    $codes['success_codes'][]=$order->origin_code;
                    // $index = array_search($order->code,$un_rule_codes);
                    // if($index !== false)array_splice($un_rule_codes,$un_rule_codes[$index],1);
                }
            }
        }
        // return [$codes,$un_rule_codes];
    }
}
