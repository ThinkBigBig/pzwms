<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelProductSku extends BaseModel
{
    use HasFactory;

    protected $table = 'channel_product_sku';

    protected $hidden = [
        'created_at', 'updated_at'
    ];

    protected $casts = [
        'properties' => 'array'
    ];

    protected $guarded = [];

    protected $appends = ['tag_desc'];

    const STAUTS_ON = 1;
    const STAUTS_OFF = 0;

    public function setPropertiesAttribute($properties)
    {
        $this->attributes['properties'] = json_encode($properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function channelProduct()
    {
        return $this->hasOne(ChannelProduct::class, 'id', 'cp_id');
    }

    public function getTagDescAttribute()
    {
        if (!$this->tags) return '';

        $maps = [
            'vintage' => '需要用app手工上架这双鞋（可能需要上传配图审核）'
        ];
        return $maps[$this->tags] ?? '';
    }
}
