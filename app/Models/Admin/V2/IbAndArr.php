<?php

namespace App\Models\Admin\V2;


use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;
use Illuminate\Support\Facades\DB;

class IbAndArr extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_ib_and_arr'; //入库单和登记单关联关系
    protected $map ;

    public function __construct($params=[]){
        parent::__construct($params);
        $this->map = [
            'ib_type' => [1 => __('status.buy_ib'), 2 => __('status.transfer_ib'), 3 => __('status.return_ib'), 4 => __('status.other_ib')],
        ];
    }

}
