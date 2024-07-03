<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WmsStockCheckDifference extends wmsBaseModel
{
    use HasFactory;
    use SoftDeletes;
    protected $guarded = [];
    protected $table = "wms_stock_check_differences";

    // 0-待处理 1-少货寻回 2-已上报
    const WAIT = 0;
    const RECOVER = 1;
    const REPORT = 2;

    protected $appends = ['status_txt'];


    public function getStatusTxtAttribute(): string
    {
        $maps = [
            self::REPORT => __('admin.wms.status.diff_report'), //'已上报',
            self::RECOVER => __('admin.wms.status.diff_recover'), //'已寻回',
            self::WAIT => __('admin.wms.status.diff_wait'), //'待处理',
        ];
        return $maps[$this->status] ?? '';
    }

    function specBar()
    {
        return $this->hasOne(ProductSpecAndBar::class, 'bar_code', 'bar_code');
    }

    public function supplier()
    {
        return $this->hasOne(Supplier::class, 'id', 'sup_id');
    }

    public function request()
    {
        return $this->hasOne(WmsStockCheckRequest::class, 'code', 'request_code');
    }

    public function check()
    {
        return $this->hasOne(WmsStockCheckList::class, 'request_code', 'request_code');
    }
}
