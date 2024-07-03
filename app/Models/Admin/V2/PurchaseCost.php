<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;


class PurchaseCost extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_purchase_cost';

    protected $appends =  ['type_txt'];
    // protected $map = [
    //     'type'=>[1=>'装卸费',2=>'邮费',3=>'人工费'],
    // ];
    protected $map;
    public function __construct($params=[])
    {
        parent::__construct($params);
        $this->map = [
            'type' => [1 => __('status.handling_cost'), 2 => __('status.postage'), 3 => __('status.labor_costs')],
        ];
    }
}
