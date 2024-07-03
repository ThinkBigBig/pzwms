<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsShenduInv extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    public $table = 'wms_shendu_inv';
}
