<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelRefundLog extends BaseModel
{
    use HasFactory;

    protected $table = 'channel_refund_logs';

    protected $guarded = [];

    const TYPE_QUALITY = 1;//质检未通过
    const TYPE_REFUND = 2;//买家客退
    const TYPE_DELIVER_TIMEOUT = 3;//未及时发货
    const TYPE_CANCEL = 4;//订单取消（用户或客户操作取消）
    const TYPE_BUSINESS_CANCEL = 5;//卖家取消

}
