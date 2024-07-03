<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsStockMoveItem extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = "wms_stock_move_items";

    // 0-待下架  1-下架中 2-已下架/待上架 3-上架中 4-已上架
    const WAIT_DOWN = 0;
    const DOWNING = 1;
    const WAIT_PUTAWAY = 2;
    const PUTAWAY_ING = 3;
    const PUTAWAY = 4;

    function specBar()
    {
        return $this->hasOne(ProductSpecAndBar::class, 'bar_code', 'bar_code');
    }

    function supplier()
    {
        return $this->hasOne(Supplier::class, 'id', 'sup_id');
    }

    function takedownUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'down_user_id');
    }

    function shelfUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'shelf_user_id');
    }
    

}
