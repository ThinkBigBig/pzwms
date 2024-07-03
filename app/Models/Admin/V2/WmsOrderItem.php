<?php

namespace App\Models\Admin\V2;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsOrderItem extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'wms_order_items';
}
