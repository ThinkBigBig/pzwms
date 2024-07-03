<?php

namespace App\Models\Admin\V2;


use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsAfterSaleOrder extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];

    // 0-暂存 1-审核中 2-审核通过 3-审核拒绝 4-已撤销
    const STASH = 0;
    const WAIT_AUDIT = 1;
    const PASS = 2;
    const REJECT = 6;
    const REVOKE = 0;

    // 0-无需退货 1-未收货 2-已收货
    const RETURN_DEFAULT = 0;
    const RETURN_NOT = 1;
    const RETURN_RECEIVED = 2;

    // 0-待退款 1-已退款
    const REFUND_WAIT = 0;
    const REFUND_END = 1;

    // 1-仅退款 2-退货退款
    const TYPE_REFUND = 1;
    const TYPE_RETURN = 2;

    static $active_status = [self::STASH, self::WAIT_AUDIT, self::PASS,];

    static $refund_reason_map = [
        "1" => "效果不好/不喜欢",
        "0" => "默认退货原因",
        "2" => "缺货",
        "3" => "不想要了",
        "4" => "尺码不合适",
        "5" => "大小尺寸与商品描述不符",
        "6" => "卖家发错货",
        "7" => "拍多了",
        "8" => "材质、面料与商品描述不符",
        "9" => "颜色、款式、图案与描述不符",
        "10" => "质量问题",
        "11" => "地址/电话信息填写错误",
        "12" => "商品信息拍错(规格/尺码/颜色等)",
        "13" => "未按约定时间发货",
        "14" => "快递一直未送到",
        "15" => "协商一致退款",
        "16" => "其他",
        "17" => "多拍/拍错/不想要",
        "18" => "发货速度不满意",
        "19" => "没用/少用优惠",
        "20" => "空包裹/少货",
        "21" => "付款之时起365天内,卖家未点击发货,自动退款给您",
    ];

    static function code()
    {
        return 'SHGD' . date('ymdHis') . rand(1000, 9999);
    }
}
