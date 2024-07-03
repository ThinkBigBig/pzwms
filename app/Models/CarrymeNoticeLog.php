<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarrymeNoticeLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    const STATUS_DEFAULT = 0; //待执行
    const STATUS_SUCCESS = 1; //成功
    const STATUS_FAIL = 2; //失败
    const STATUS_NOT_NEED = 3; //无需执行

    //执行状态
    static $do_status = [
        self::STATUS_DEFAULT,
        self::STATUS_FAIL
    ];

    protected $casts = [
        'order_success_notice_info' => 'array',
        'order_cancel_notice_info' => 'array',
        'bid_sccess_notice_info' => 'array',
        'bid_cancel_notice_info' => 'array',
    ];

    public function setOrderSuccessNoticeInfoAttribute($order_success_notice_info)
    {
        $this->attributes['order_success_notice_info'] = json_encode($order_success_notice_info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function setOrderCancelNoticeInfoAttribute($order_cancel_notice_info)
    {
        $this->attributes['order_cancel_notice_info'] = json_encode($order_cancel_notice_info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function setBidCancelNoticeInfoAttribute($bid_cancel_notice_info)
    {
        $this->attributes['bid_cancel_notice_info'] = json_encode($bid_cancel_notice_info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function setBidSuccessNoticeInfoAttribute($bid_success_notice_info)
    {
        $this->attributes['bid_success_notice_info'] = json_encode($bid_success_notice_info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function channelOrder()
    {
        return $this->hasOne(ChannelOrder::class, 'id', 'channel_order_id');
    }

    /**
     * 新增通知记录
     *
     * @param ChannelOrder $order
     * @param string $update
     */
    static function addNotice(ChannelOrder $order, $update_name)
    {
        $bidding = $order->channelBidding;
        $where = ['channel_order_id' => $order->id, 'carryme_bidding_id' => $bidding->carryme_bidding_id, 'carryme_bidding_item_id' => $bidding->carryme_bidding_item_id];
        $notice = self::where($where)->first();
        if ($notice && !$update_name) {
            return $notice;
        }

        $data = [$update_name => self::STATUS_DEFAULT];
        if ($notice) {
            if (!$update_name) return $notice;

            //字段不在初始状态，不操作更新
            if ($notice->$update_name != -1) {
                return $notice;
            }
            $notice->update($data);
            return $notice;
        }

        return self::create(array_merge($where, $data));
    }
}
