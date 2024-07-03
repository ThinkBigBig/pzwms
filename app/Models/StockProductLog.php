<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockProductLog extends Model
{
    use HasFactory;
    protected $guarded = [];

    const SYNC_WAIT = 0;
    const SYNC_SUCCESS = 1;
    const SYNC_FAIL = 2;
}
