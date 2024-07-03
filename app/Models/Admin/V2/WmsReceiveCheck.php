<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsReceiveCheck extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_receive_check';

    protected static $arrIns = null;
    protected $guarded = [];

    const CREATED_AT = null;
    const UPDATED_AT = null;

    static function maps($name)
    {
        $arr = [
            'unit' => [0=> __('status.other'),1 => __('status.piece'), 2 => __('status.box'), 3 => __('status.sections')],
        ];
        return $arr[$name] ?? [];
    }
}
