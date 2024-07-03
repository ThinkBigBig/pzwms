<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppProductSku extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'spData' => 'array',
    ];

    public function setSpDataAttribute($spData)
    {
        $this->attributes['spData'] = json_encode($spData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function product() {
        return $this->hasOne(AppProduct::class,'productId','productId');
    }
}
