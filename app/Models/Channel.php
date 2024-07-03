<?php

namespace App\Models;

class Channel extends BaseModel
{
    protected $table = 'channel';
    protected $guarded = [];

    const STATUS_ON = 1; //启用
    const STATUS_OFF = 2; //禁用

    const ON = 1; //启用
}
