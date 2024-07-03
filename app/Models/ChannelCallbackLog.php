<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelCallbackLog extends BaseModel
{
    use HasFactory;

    protected $table = 'channel_callback_logs';

    protected $guarded = [];

    const STATUS_PENDING = 0;//待处理
    const STATUS_DONE = 1;//已处理
}
