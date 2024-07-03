<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsPreAllocationList extends wmsBaseModel
{
    use HasFactory;

    protected $appends = ['status_txt','type_txt','origin_type_txt'];

    public function getStatusTxtAttribute()
    {
        $maps = [
            '1' => '已审核',
            '2' => '已取消',
            '3' => '已暂停',
        ];
        return $maps[$this->status]??'';
    }
    public function getTypeTxtAttribute()
    {
        $maps = [
            '1' => '已审核',
            '2' => '已取消',
            '3' => '已暂停',
        ];
        return $maps[$this->status]??'';
    }
    public function getOriginTypeTxtAttribute()
    {
        // $table->tinyInteger('origin_type')->default(0)->comment('来源单据类型 1-销售出库单 2-调拨出库单 3-其他出库单 4-中转移位单 5-快速移位单');
        $maps = [
            '1' => '销售出库单',
            '2' => '调拨出库单',
            '3' => '其他出库单',
            '4' => '中转移位单',
            '5' => '快速移位单',
        ];
        return $maps[$this->origin_type]??'';
    }
}
