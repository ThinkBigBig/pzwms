<?php

namespace App\Models\Admin\V2;
use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Builder;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SetShifPlan extends wmsBaseModel
{
    use HasFactory;
    // use SoftDeletes;
    protected $table = 'wms_set_sift_plan';

    public function getWhereJsonAttribute($value)
    {
        return $value?json_decode($value,1):'';
    }
    public function getOrderJsonAttribute($value)
    {
        return $value?json_decode($value,1):'';
    }
    public function getShowJsonAttribute($value)
    {
        return $value?json_decode($value,1):'';
    }

    public  function getPlan($user_id,$model){
        $res = $this::where('user_id',$user_id)->where('model',$model)
        ->orderBy('with_start','desc')->orderBy('created_at','desc')
        ->select('id','name','model','where_json','show_json','order_json','with_start','open','created_at')
        ->get();
        return $res;
    }

    public function delPlan($id,$user_id,$model){
        $res = $this::where('id',$id)->where('user_id',$user_id)->where('model',$model)->orderBy('created_at','desc')->delete();
        return $res;
    }

    public function isStart($user_id,$model){
        return $this::where('user_id',$user_id)->where('model',$model)->where('with_start',1)->get()->isNotEmpty();
    }

}
