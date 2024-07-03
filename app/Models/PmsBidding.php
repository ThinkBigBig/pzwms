<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PmsBidding extends Model
{
    use HasFactory;
    protected $table = 'pms_bidding';

    const STATUS_BIDDING = 1;//已出價
    const STATUS_CANCEL = 2;//已取消
}
