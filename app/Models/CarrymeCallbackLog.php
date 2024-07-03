<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarrymeCallbackLog extends Model
{
    use HasFactory;
    protected $guarded = [];

    const STATUS_DEFAULT = 1; //待执行
    const STATUS_SUCCESS = 2; //成功
    const STATUS_FAIL = 3; //失败
    const STATUS_NOT_NEED = 4; //无需执行

    const TYPE_BID_ADD = 1;//出价完成
    const TYPE_BID_CANCEL = 2;//出价取消
    const TYPE_ORDER_SUCCESS = 3;//订单创建
    const TYPE_ORDER_CANCEL = 4;//订单取消
    const TYPE_ORDER_DISPATCH_NUM = 5;//虚拟物流单号
    const TYPE_LOWEST_PRICE = 6;//最低价

    protected $casts = [
        'request' => 'array',
        'response' => 'array',
    ];

    public function setRequestAttribute($request)
    {
        $this->attributes['request'] = json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function setResponseAttribute($response)
    {
        $this->attributes['response'] = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

}
