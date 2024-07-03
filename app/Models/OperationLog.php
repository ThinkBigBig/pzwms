<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperationLog extends Model
{
    use HasFactory;
    protected $guarded = [];
    const UPDATED_AT = NULL;


    const TYPE_ADD = '10001';
    const TYPE_SHELF = '10002';
    const TYPE_TAKEDOWN = '10003';
    const TYPE_SOLD = '10004';
    const TYPE_DELETE = '10005';
    const TYPE_BIDDING = '10006';
    const TYPE_CLEAR = '10007';
    const TYPE_CHANGE_THRESHOLD = '10008';

    const ORDER_CREATE = '11001';
    const ORDER_BUSING = '11002';
    const ORDER_PLATFORM = '11003';
    const ORDER_NUMBER = '11004';
    const ORDER_CANCEL = '11005';
    const ORDER_CLOSE = '11006';
    const ORDER_AUTO_CONFIRM = '11007';//订单自动确认
    const ORDER_BATCH_PLATFORM = '11008';//订单批量确认

    protected $appends = ['type_txt'];
    public function getTypeTxtAttribute(): string
    {
        $maps = [
            self::TYPE_ADD => '商品导入',
            self::TYPE_SHELF => '商品上架',
            self::TYPE_TAKEDOWN => '商品下架',
            self::TYPE_SOLD => '售出下架',
            self::TYPE_DELETE => '删除出价',
            self::TYPE_BIDDING => '新增出价',
            self::TYPE_CLEAR => '清空商品',
            self::TYPE_CHANGE_THRESHOLD => '门槛价变更',

            self::ORDER_CREATE => '订单创建',
            self::ORDER_BUSING => '商家确认发货',
            self::ORDER_PLATFORM => '平台确认发货',
            self::ORDER_NUMBER => '获取虚拟物流单号',
            self::ORDER_CANCEL => '订单取消',
            self::ORDER_CLOSE => '订单关闭',
        ];
        return $maps[$this->type] ?? '';
    }

    static function add($type, $product_id = 0, $order_no = '', $remark = '', $admin_user_id = 0)
    {
        if ($order_no && !$product_id) {
            $channel_order = ChannelOrder::where(['order_no' => $order_no])->first();
            $bidding = $channel_order->channelBidding;
            $stock_bidding_item = $bidding->stockBiddingItem;
            if ($stock_bidding_item) $product_id = $stock_bidding_item->stock_product_id;
        }
        OperationLog::create([
            'stock_product_id' => $product_id,
            'type' => $type,
            'admin_user_id' => $admin_user_id,
            'order_no' => $order_no,
            'remark' => $remark,
        ]);
    }

    static function add2($type, $product_id = 0, $order_no = '', $params = [])
    {
        if ($order_no && !$product_id) {
            $channel_order = ChannelOrder::where(['order_no' => $order_no])->first();
            $bidding = $channel_order->channelBidding;
            $stock_bidding_item = $bidding->stockBiddingItem;
            if ($stock_bidding_item) $product_id = $stock_bidding_item->stock_product_id;
        }
        OperationLog::create([
            'stock_product_id' => $product_id,
            'type' => $type,
            'admin_user_id' => $params['admin_user_id'] ?? 0,
            'order_no' => $order_no,
            'remark' => $params['remark'] ?? '',
        ]);
    }
}
