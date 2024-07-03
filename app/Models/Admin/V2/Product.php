<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use App\Models\Admin\V2\ProductSpecAndBar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Product extends wmsBaseModel
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'wms_product';
    protected $guarded = [];
    protected $appends = ['status_txt', 'type_txt'];
    protected $specsModel = '';

    public $unique_field = ['product_sn'];

    // protected $with =['brand:id,name','category:id,name','specs'];
    // protected $map = [
    //     'status'=>[1=>'启用' ,0=>'禁用' ],
    //     'type' =>  [0=>'实物产品' ,1=>'虚拟产品' ,2=>'赠品', 3=>'附属品', 4=>'其他',5=>'残次品'],
    // ];
    protected $map;
    public function __construct($params = [])
    {
        parent::__construct($params);
        $this->map =
            [
                'status' => [1 => __('status.enable'), 0 => __('status.disabled')],
                'type' =>  [0 => __('status.product.real'), 1 => __('status.product.virtually'), 2 => __('status.product.gift'), 3 => __('status.product.accessories'), 4 => __('status.product.other'), 5 => __('status.product.defects')],
            ];
    }

    public function specs()
    {
        return $this->hasMany(ProductSpecAndBar::class, 'product_id', 'id');
    }

    public function brand()
    {
        return $this->belongsTo(ProductBrands::class, 'brand_id');
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }


    public function getImgAttribute($value)
    {
        // if($value)return request()->root().$value;
        if ($value) {
            if (strpos($value, 'https://') !== false || strpos($value, 'http://') !== false) return $value;
            return config('ext.aliyun_oss_host') . $value;
        }
        return '';
    }

    static function imgAddHost($value)
    {
        if (!$value) return '';
        if (strpos($value, 'https://') !== false || strpos($value, 'http://') !== false) return $value;
        return config('ext.aliyun_oss_host') . $value;
    }


    protected function  is_specs(&$data)
    {
        if (!empty($data['specs'])) {
            $specs = $data['specs'];
            unset($data['specs']);
            return $specs;
        }
        if(isset($data['specs']))unset($data['specs']);
        return false;
    }

    public  function BaseCreate($CreateData = [])
    {
        if (empty($CreateData)) return false;
        $specs = $this->is_specs($CreateData);
        DB::beginTransaction();
        $id = $this::insertGetId($CreateData);
        if (empty($id)) return false;
        // $product = $this::find($id);
        if ($specs) {
            list($res, $msg) = $this->addBar($id, $CreateData['product_sn'], $specs);
            if (!$res) return [$res, $msg];
            // foreach($specs as $k=>&$v){
            //     $v['product_id'] = $product->id;
            //     $v['sku'] = $product->product_sn.'#'.$v['spec_one'];
            //     if(empty($v['code']))$v['code']=$v['sku'];
            // }
            // $rows = $this::find($id)->specs()->insert($specs);
            // if(empty($rows)) return false;
        }
        if($id)DB::commit();
        return [$id, ''];
    }
    public function withSearch($select)
    {
        return $this::with(['brand:id,name', 'category:id,name', 'specs'])->select($select);
    }


    public function BaseUpdate($where, $update)
    {
        if (empty($update)) return [false, ''];
        $specs = $this->is_specs($update);
        $data = $this::select();
        // var_dump($where);exit;
        foreach ($where as $v) {
            if ($v[0] == 'id') $id = $v[2];
            if (empty($v[0]) || empty($v[1])) continue;
            if ($v[1] == 'in' || $v[1] == 'IN') $data->whereIn($v[0], $v[2]);
            if (in_array($v[1], $this->whereSymbol)) $data->where($v[0], $v[1], $v[2]);
        }
        $one_item = $data->first();
        if (!$one_item) return [false, __('response.data_not_exists')];
        $product_sn = $one_item->product_sn;
        $product_id = $one_item->id;
        $this->startTransaction();
        $reData = $data->update($update);
        if (empty($reData) || $reData == false || $reData == NULL) return [false, '更新失败'];
        if ($specs) {
            $total = 0;
            foreach ($specs as $k => &$v) {
                $update_where = $v['where'];
                $update_data = $v['update_data'];
                if (empty($update_data['id'])) {
                    list($rows, $msg) =  $this->addBar($product_id, $product_sn, $update_data);
                    if (!$rows) return [false, $msg];
                } else {
                    // dd($this::find($id)->specs()->where($update_where)->get()->toArray());
                    $update_data['product_sn'] = $product_sn;
                    list($res, $rows) = $this->find($id)->specs()->getModel()->BaseUpdate($update_where, $update_data);
                    if (!$res) return [false, $rows];
                }

                if ($rows) $total += 1; //更新总条数
            }
        }
        $this->endTransaction([1]);
        return [true, $reData];
    }

    public function BaseOne($where = [], $select = ['*'], $order = [['id', 'desc']])
    {
        $data = $this::with(['brand:id,name', 'category:id,name', 'specs'])->select($select);
        // $data = $this::select($select);
        // dd($data);
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
            return objectToArray($reData);
    }

    public function checkProd($product_info)
    {
        $item = $this::where('product_sn', $product_info['product_sn'])->first();
        if (empty($item)) return [true, null];
        foreach ($product_info as $k => $v) {
            if ($k == 'img') {
                if (empty($item->img)) {
                    $item->img = $v;
                    $item->save();
                }
            } else {
                if ($item->$k != $v) return [false, __('response.enter_pro_info_err')];
            }
        }
        return [true, $item->id];
    }

    public function addProduct($product_info)
    {
        $product_info['created_at'] = date('Y-m-d H:i:s');
        $id = $this::insertGetId($product_info);
        return $id;
    }

    public function addBar($id, $product_sn, $bar_info)
    {
        return (new ProductSpecAndBar())->addBar($id, $product_sn, $bar_info);
    }

    //删除
    public function del($ids, $name = 'id')
    {
        if (empty($ids)) return [false, ''];
        $id = explode(',', $ids);
        // $data = DB::table($this->table)->whereIn($name,$id)->delete();
        //查询是否可以删除
        $pro = $this::whereIn($name, $id);
        $product_ids = $pro->pluck('id')->toArray();
        $specs = ProductSpecAndBar::whereIn('product_id',$product_ids)->where('type','<>',0);
        if($specs->exists()) return [false, __('response.bar_used_del_err')];
        $this->startTransaction();
        $data = $pro->delete();
        if (empty($data)  || $data == false || $data == NULL) return [false, ''];
        //删除商品下面条码
        ProductSpecAndBar::whereIn('product_id',$product_ids)->delete();
        return $this->endTransaction([$data],$product_ids);
    }

    //根据货号查名称
    public static function getProName($product_sn){
        $pro = self::where('product_sn',$product_sn)->where('status',1)->select('id','brand_id','category_id','serie_id','name','product_sn','type','img')->orderBy('id','desc')->first();
        return $pro;
    }
}
