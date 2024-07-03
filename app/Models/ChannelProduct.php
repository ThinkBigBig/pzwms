<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelProduct extends BaseModel
{
    use HasFactory;

    const STATUS_DEFAULT = 0;//未上架
    const STATUS_ACTIVE = 1;//已上架
    
    protected $table = 'channel_product';

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected $guarded = [];
    protected $appends = ['spu_logo_url'];

    public function skus()
    {
        return $this->hasMany(ChannelProductSku::class, 'cp_id')->where('status',1);
    }

    public function getSpuLogoUrlAttribute()
    {
        $spu_logo = $this->attributes['spu_logo'];
        return env('ALIYUN_OSS_HOST', '') . $spu_logo;
    }
}
