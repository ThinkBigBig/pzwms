<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsAllocationTaskDetail extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'wms_allocation_task_detail';
}
