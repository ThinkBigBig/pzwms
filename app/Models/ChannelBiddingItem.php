<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelBiddingItem extends Model
{
    use HasFactory;
    protected $guarded = [];

    const STATUS_SHELF =1;//已上架
    const STATUS_TAKEDOWN = 2;//已下架
    
    //转换类型
    protected $casts = [
        'properties' => 'array',
    ];

    public function setPropertiesAttribute($properties)
    {
        $this->attributes['properties'] = json_encode($properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
