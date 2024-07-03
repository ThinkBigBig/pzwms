<?php

namespace App\Models\Admin\V2;
use App\Models\Admin\wmsBaseModel;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductBrands extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_product_brands';
    public $unique_field =[];
    protected $map = [
        'status'=>[1=>'启用' ,0=>'禁用' ],
    ];
    protected $appends = ['status_txt'];
    
    public function product(){
        return $this->hasMany(Product::class,'brand_id','id');
    }
    
    public function getStatusTxtAttribute()
    {
        $status=isset($this->status)?$this->map['status'][$this->status]:'';
        return $status;
    }
    
    public function BaseDelete($ids,$name ='id')
    {
        if(empty($ids)) return false;
        $id = explode(',',$ids);;
        // dd($this::with('product:id,category_id,name')->doesntHave('product')->whereIn($name,$id)->delete());
        $del_ids = $this::with('product:id,category_id,name')->doesntHave('product')->whereIn($name,$id)->select('id')->get()->modelKeys();
        $ndel_ids = array_values(array_diff($id,$del_ids));
        $data = $this::whereIn($name,$del_ids)->delete();
        if($ndel_ids){
            return ['ndel_ids'=>$ndel_ids,'del_ids'=>$del_ids];
        }
        if(empty($data)  || $data== false || $data== NULL) return false;
        return $data;
    }

    static function selectOptions() {
        return self::selectRaw('code as value,name as label')->get()->toArray();
    }

    function series() {
        return $this->hasMany(WmsProductSerie::class,'code','brand_code')->select(['id','name','code']);
    }
}
