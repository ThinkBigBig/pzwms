<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppBidConfig extends Model
{
    use HasFactory;
    public $table = 'app_bid_config';

    const ACTIVE = 1;
    const INACTIVE = 0;

}
