<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsStockMoveDetail extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = "wms_stock_move_details";

    function specBar()
    {
        return $this->hasOne(ProductSpecAndBar::class, 'bar_code', 'bar_code');
    }

    function items($type = 0)
    {
        // type 0-全部 1-下架已扫描 2-待上架 3-上架扫描
        $where = [
            'origin_code' => $this->origin_code,
            'bar_code' => $this->bar_code,
            'location_code' => $this->location_code,
            'area_code' => $this->area_code, //
            'quality_level' => $this->quality_level,
            'tenant_id' => $this->tenant_id,
        ];
        // status 状态 0-待下架 1-下架中 2-已下架/待上架 3-上架中 4-已上架
        if ($type == 1) {
            return WmsStockMoveItem::where($where)->where('status', '>', 0)->get();
        }
        if ($type == 2) {
            return WmsStockMoveItem::where($where)->whereIN('status', [2, 3, 4])->get();
        }
        if ($type == 3) {
            return WmsStockMoveItem::where($where)->whereIN('status', [3, 4])->get();
        }
        return WmsStockMoveItem::where($where)->get();
    }
}
