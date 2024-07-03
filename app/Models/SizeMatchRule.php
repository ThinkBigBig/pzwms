<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SizeMatchRule extends Model
{
    use HasFactory;
    public $guarded = [];

    protected $casts = [
        'rules' => 'array'
    ];

    public function setRulesAttribute($properties)
    {
        $this->attributes['rules'] = json_encode($properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    static function getRules($product_sn,$channel_code)
    {
        $rules = SizeMatchRule::where(['product_sn'=>$product_sn,'channel_code'=>$channel_code])->value('rules');
        return $rules?json_decode($rules,true):[];
    }

}
