<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

class ProductSpecAndBar extends wmsBaseModel
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'wms_spec_and_bar';
    protected $with = ['product:id,name,product_sn,status,img,category_id,brand_id,serie_id'];
    public $unique_field = ['bar_code', 'code'];

    public function product()
    {
        return $this->belongsTo(Product::class)->withDefault([
            'name' => '',
            'product_sn' => '',
            'img' => '',

        ]);
    }

    //根据货号规格获取条码
    public static function getBarCode($sku, $arr_code = '')
    {
        // $sku = $product_sn . '#' . $spec;
        $bar_codes = [];
        if ($arr_code) {
            $arr = ArrivalRegist::where('arr_code', $arr_code)->first();
            if ($arr) {
                $bar_codes = RecvDetail::where('arr_id', $arr->id)->pluck('bar_code')->toArray();
            }
        }
        if ($bar_codes) $item = self::where('sku', $sku)->whereIn('bar_code', $bar_codes)->orderBy('created_at', 'desc')->first();
        else $item = self::where('sku', $sku)->orderBy('created_at', 'desc')->first();
        if (!$item) return '';
        return  $item->bar_code;
    }

    //获取商品信息
    public static function getInfo($bar_code)
    {
        $item = self::where('bar_code', $bar_code)
            // ->whereHas('product', function (Builder $query) {
            //     $query->where('status', 1);
            // })
            ->orderBy('id', 'desc')->first();
        $info = [];
        if ($item) {
            $info['name'] = $item->product->name;
            $info['product_sn'] = $item->product->product_sn;
            $info['spec'] = $item->spec_one;
            $info['spec_two'] = $item->spec_two;
            $info['spec_three'] = $item->spec_three;
            $info['sku'] = $item->sku;
        } else {
            $info['name'] = "";
            $info['product_sn'] = "";
            $info['spec'] = "";
            $info['spec_two'] = "";
            $info['spec_three'] = "";
            $info['sku'] = "";
        }
        return $info;
    }

    public function withSearch($select)
    {
        $data = $this->whereHas('product', function (Builder $query) {
            $query->where('status', 1);
        })->select($select);
        return $data;
    }


    //判断条码是否存在
    public function barExists($bar_code)
    {
        return $this::whereIn('bar_code', $bar_code)->get()->pluck('bar_code')->toArray();
    }


    //判断sku是否已有条码
    public function skuExists($sku_codes)
    {
        return $this::whereIn('sku', $sku_codes)->get()->pluck('sku')->toArray();
    }

    //新增条码
    public  function addBar($product_id, $product_sn, $bar_info)
    {
        $data = [];
        $created_at = date('Y-m-d H:i:s');
        $tenant_id = request()->header('tenant_id');
        $exists = [];
        $sku_codes = [];
        foreach ($bar_info as $k => $v) {
            if (is_array($v)) {
                //多条新增
                if (in_array($v['bar_code'], $exists)) return [false, sprintf(__('response.bar_repeat'), $v['bar_code'])];
                $exists[] = $v['bar_code'];
                $v['product_id'] = $product_id;
                $v['sku'] = $product_sn . '#' . $v['spec_one'];
                // if (in_array($v['sku'], $sku_codes)) return [false, sprintf(__('status.sku_exists'), $v['sku'])];
                // $sku_codes[] = $v['sku'];
                $v['code'] = $v['code'] ?? $v['sku'];
                $v['created_at'] = $created_at;
                $v['tenant_id'] = $tenant_id;
                $data[] = $v;
            } else {
                //单条新增
                $data = $bar_info;
                if ($this->barExists([$data['bar_code']])) return [false, __('status.bar_exists')];
                $data['product_id'] = $product_id;
                $data['sku'] = $product_sn . '#' . $data['spec_one'];
                // if ($this->skuExists([$data['sku']])) return [false, __('status.sku_exists')];
                if (empty($data['code'])) $data['code'] = $data['sku'];
                $data['created_at'] = $created_at;
                $data['tenant_id'] = $tenant_id;
                break;
            }
        }
        if (!empty($exists)) {
            $res = $this->barExists($exists);
            if ($res) return [false, sprintf(__('response.bar_exists'), implode(',', $res))];
        }
        // if (!empty($sku_codes)) {
        //     $res = $this->skuExists($sku_codes);
        //     if ($res) return [false, sprintf(__('response.skus_exists'), implode(',', $res))];
        // }
        $row = $this::insert($data);
        return [$row, ''];
    }


    //根据条码获取分类id
    public static  function getCategoryId($bar_code)
    {
        $item = self::where('bar_code', $bar_code)->first();
        if (!$item) return;
        return $item->toArray()['product']['category_id'];
    }

    //sku 列表
    public function skuList($where, $select = ['*'], $order = [['id', 'desc']], $cur_page = 1, $size = 10)
    {
        $data = $this::with('product')->whereHas('product', function (Builder $query) {
            $query->where('status', 1);
        });
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
        $reData = $data->paginate($size, ['*'], 'page', $cur_page)->toArray();
        foreach ($reData['data'] as &$one) {
            if ($one) {
                $temp = [
                    'id' => $one['id'],
                    'product_name' => $one['product']['name'],
                    'product_sn' => $one['product']['product_sn'],
                    // 'product_img'=>$one['product']['img'],
                    'sku' => $one['sku'],
                    'spec' => $one['spec_one'],
                    'spec_two' => $one['spec_two'],
                    'spec_three' => $one['spec_three'],
                    'bar_code' => $one['bar_code'],
                ];
                $one = $temp;
            }
        }
        return $reData;
    }

    //修改条码类型是普通产品还是唯一码产品
    public static function updateType($bar_codes, $type = 1)
    {
        return self::whereIn('bar_code', $bar_codes)->update([
            'type' => $type
        ]);
    }

    //判断是否是普通产品
    public static function getType($bar_code)
    {
        $item = self::where('bar_code', $bar_code)->first();
        if (!$item) return 0;
        return $item->type;
    }

    //删除
    public function del($ids, $name = 'id')
    {
        if (empty($ids)) return [false, ''];
        $ids = explode(',', $ids);
        // $data = DB::table($this->table)->whereIn($name,$id)->delete();
        //查询是否可以删除
        $query =  $this::whereIn($name, $ids)->where('type', 0);
        $id = $query->pluck('id')->toArray();
        if (!$id) return [false, __('response.bar_used_delete_err')];
        $data = $query->delete();
        if (empty($data)  || $data == false || $data == NULL) return [false, ''];
        return [true, array_diff($ids, $id)];
    }

    //修改
    public function BaseUpdate($where, $update)
    {
        if (empty($update)) return false;
        $data = $this::select();
        foreach ($where as $v) {
            if (empty($v[0]) || empty($v[1])) continue;
            if ($v[1] == 'in' || $v[1] == 'IN') $data->whereIn($v[0], $v[2]);
            if (in_array($v[1], $this->whereSymbol)) $data->where($v[0], $v[1], $v[2]);
        }

        $data = $data->first();
        if (!$data) return false;
        if (!empty($update['bar_code']) && $update['bar_code'] != $data->bar_code) {
            //检查是可以修改条码
            if (!self::checkUpdate($data->bar_code)) {
                return [false, __('response.bar_used_update_err')];
            }
        }
        if ($data) {
            if (empty($update['spec_one'])) $update['spec_one'] = $data->spec_one;
            if (!empty($update['product_sn']) || !empty($update['spec_one'])) {
                if ($update['product_sn']) {
                    $pro = Product::where('product_sn', $update['product_sn'])->where('status', 1)->first();
                    if (!$pro) return [false, '货号不存在'];
                    if ($data->product_id != $pro->id) {
                        $update['product_id'] = $pro->id;
                    }
                    $sku = $pro->product_sn . '#' . $update['spec_one'];
                } else {
                    if ($update['spec_one'] != $data->spec_one) {
                        $sku = explode('#', $data->sku)[0] . '#' . $update['spec_one'];
                    }
                }
                $update['sku'] = $sku;
                $update['code'] = $sku;
                if ($sku != $data->sku) {
                    //检查sku是否已存在
                    if ($this->skuExists([$update['sku']])) return [false, __('status.sku_exists')];
                }
            }
            unset($update['product_sn']);
            $reData = $data->update($update);
        }

        if (empty($reData) || $reData == false || $reData == NULL) return [false, ''];
        return [true, $reData];
    }

    //判断条码是否允许修改或删除
    public static  function checkUpdate($bar_code)
    {
        $bar = self::where('bar_code', $bar_code)->first();
        if (!$bar) return true;
        if ($bar && $bar->type == 0) return true;
        return false;
    }

    //判断条码是否为新品
    public static function isNewPro($bar_code)
    {
        $item = self::where('bar_code', $bar_code)->whereHas('product', function ($query) {
            $query->where('status', 1);
        });
        return $item->doesntExist();
    }

    public   function  getSku($product_sn, $spec)
    {
        return $product_sn . '#' . $spec;
    }

    public  static function  getSkuByBar($bar_code)
    {
        $item = self::where('bar_code', $bar_code)->first();
        if(!$item)return '';
        return $item->sku;
    }
}
