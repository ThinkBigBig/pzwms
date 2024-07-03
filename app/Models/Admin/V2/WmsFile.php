<?php

namespace App\Models\Admin\V2;

use App\Logics\traits\WmsAttribute;
use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WmsFile extends wmsBaseModel
{
    use HasFactory, SoftDeletes, WmsAttribute;
    protected $table = 'wms_files';
    protected $guarded = [];

    const TYPE_DIR = 0;
    const TYPE_FILE = 1;

    protected $appends = ['file_url'];
}
