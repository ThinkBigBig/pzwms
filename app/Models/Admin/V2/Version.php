<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Version extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_version';
    protected static function booted()
    {
    }
    public function getDownloadLinkAttribute($key){
        return  cdnurl($key, true);
    }
}
