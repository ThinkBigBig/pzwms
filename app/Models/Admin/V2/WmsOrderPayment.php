<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsOrderPayment extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'wms_order_payments';
    // protected $casts = [
    //     'paysuccess_time' => 'datetime:Y-m-d H:i:s',
    // ];
    protected $appends = ['type_txt', 'status_txt'];
    public function getTypeTxtAttribute(): string
    {
        return '';
    }

    public function getStatusTxtAttribute(): string
    {
        return '';
    }
}
