<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsPutawayDetail extends wmsBaseModel
{
    use HasFactory;

    protected $guarded = [];
    public $table = 'wms_putaway_detail';

    function arrivalRegist()
    {
        return $this->hasOne(ArrivalRegist::class, 'id', 'arr_id');
    }

    function specBar()
    {
        return $this->hasOne(ProductSpecAndBar::class, 'bar_code', 'bar_code')->withDefault();
    }

    protected $appends = ['quality_type_txt',];

    public function getQualityTypeTxtAttribute(): string
    {
        return [WmsQualityDetail::NOTMAL => '正品', WmsQualityDetail::DEFECT => '瑕疵'][$this->quality_type] ?? '';
    }

    function adminUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'admin_user_id');
    }

    static function maps($attr, $option = false)
    {
        $maps = [
            'put_unit' => [0 => __('status.piece'), 1 => __('status.sections'), 2 => __('status.box')],
        ];
        if (!$option) return $maps[$attr];
        return self::cloumnOptions($maps[$attr]);
    }

    public function getPutUnitTxtAttribute()
    {
        $put_unit = isset($this->put_unit) ? self::maps('put_unit')[$this->put_unit] : '';
        return $put_unit;
    }
        
    //pda 获取暂存的上架详情
    public static function info($putaway_code){
        $data = [];
        $list = self::where('putaway_code',$putaway_code)->selectRaw('id,put_unit,bar_code,location_code,quality_type,quality_level,COUNT(id) as num')
        ->groupByRaw('bar_code,location_code,quality_type,quality_level')->get();
        $scan_total = 0;
        foreach($list as $item){
            $location_code = $item->location_code;
            $temp = [
                'detail_id'=>$item->id,
                'bar_code'=>$item->bar_code,
                'location_code'=>$location_code,
                'quality_type'=>$item->quality_type,
                'quality_level'=>$item->quality_level,
                'put_unit'=>$item->put_unit,
                'put_unit_txt'=>$item->put_unit_txt,
                'num'=>$item->num,
                'sku'=>$item->specBar->sku??'',
                'spec_one'=>$item->specBar->spec_one??'',
                'spec_two'=>$item->specBar->spec_two??'',
                'spec_three'=>$item->specBar->spec_three??'',
                'name'=>$item->specBar->product->name??'',
                'product_sn'=>$item->specBar->product->product_sn??'',
                'img'=>$item->specBar->product->img??'',
                'category'=>$item->specBar->product->category->parent->name??'',
            ];
            $scan_total+=$item->num;
            if(isset($data[$location_code]))$data[$location_code][]= $temp;
            else $data[$location_code]  = [$temp];
        }
        return ['detail'=>$data,'scan_total'=>$scan_total];
    }
}
