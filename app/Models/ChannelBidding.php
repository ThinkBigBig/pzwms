<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ChannelBidding extends BaseModel
{
    use HasFactory;

    protected $table = 'channel_bidding';

    const BID_DEFAULT = 0; //已申请出价
    const BID_SUCCESS = 1; //出价成功
    const BID_CANCEL = 2; //出价取消
    const BID_FAIL = 3; //出价失败


    const BUSINESS_TYPE_BLOT = 1; //闪电直发
    const BUSINESS_TYPE_SPOT = 3; //现货
    const BUSINESS_TYPE_PRE_SALE = 2; //预售

    const SOURCE_APP = 'app';
    const SOURCE_STOCK = 'stock';
    const SOURCE_CHANNEL = 'channel';

    static $status_maps = [
        self::BID_DEFAULT => '已申请',
        self::BID_SUCCESS => '出价成功',
        self::BID_CANCEL => '出价取消',
        self::BID_FAIL => '出价失败',
    ];

    //跨境出价业务
    static $cross_border_business = [
        0,
        self::BUSINESS_TYPE_BLOT,
        self::BUSINESS_TYPE_SPOT
    ];

    //转换类型
    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];
    protected $guarded = [];

    protected $appends = ['status_txt', 'business_type_txt', 'carryme_business_type', 'source_format', 'spu_logo_url', 'show_price', 'show_lowest_price'];

    //赋值
    public function getStatusTxtAttribute(): string
    {
        return self::$status_maps[$this->status] ?? '';
    }

    public function getBusinessTypeTxtAttribute(): string
    {
        $maps = [
            self::BUSINESS_TYPE_BLOT => '闪电直发',
            self::BUSINESS_TYPE_SPOT => '现货',
            self::BUSINESS_TYPE_PRE_SALE => '预售',
        ];
        return $maps[$this->business_type] ?? '';
    }

    public function getCarrymeBusinessTypeAttribute(): string
    {
        $maps = [
            ChannelBidding::BUSINESS_TYPE_BLOT => 2, //闪电直发
            ChannelBidding::BUSINESS_TYPE_SPOT => 0, //现货
            ChannelBidding::BUSINESS_TYPE_PRE_SALE => 1, //预售
        ];
        return $maps[$this->business_type] ?? '';
    }

    public function getSpuLogoUrlAttribute(): string
    {
        return env('ALIYUN_OSS_HOST') . $this->spu_logo;
    }

    public function getShowPriceAttribute(): string
    {
        if ($this->channel_code == 'DW') {
            return sprintf('%.2f CNY', $this->price / 100);
        }
        if ($this->channel_code == 'GOAT') {
            return sprintf('%.2f USD', $this->price / 100);
        }
        if ($this->channel_code == 'STOCKX') {
            return sprintf('%s JPY', $this->price);
        }
        if ($this->channel_code == 'CARRYME') {
            return sprintf('%s JPY', $this->price);
        }
        return '';
    }

    public function getShowLowestPriceAttribute(): string
    {
        if ($this->channel_code == 'DW') {
            return sprintf('%.2f CNY', $this->lowest_price / 100);
        }
        if ($this->channel_code == 'GOAT') {
            return sprintf('%.2f USD', $this->lowest_price / 100);
        }
        if ($this->channel_code == 'STOCKX') {
            if ($this->lowest_price_at <= '2023-10-04 23:45:07') {
                return sprintf('%.2f USD', $this->lowest_price / 100);
            }
            return sprintf('%.2f JPY', $this->lowest_price);
        }
        if ($this->channel_code == 'CARRYME') {
            return sprintf('%.2f JPY', $this->lowest_price);
        }
        return '';
    }

    public function setPropertiesAttribute($properties)
    {
        $this->attributes['properties'] = json_encode($properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function carrymeBiddingItem()
    {
        return $this->hasOne(CarryMeBiddingItem::class, 'id', 'carryme_bidding_item_id');
    }

    public function carrymeBidding()
    {
        return $this->hasOne(CarryMeBidding::class, 'id', 'carryme_bidding_id');
    }

    public function channelOrders()
    {
        return $this->hasMany(ChannelOrder::class, 'channel_bidding_id', 'id');
    }

    public function product()
    {
        return $this->hasOne(ChannelProduct::class, 'spu_id', 'spu_id')->where(['channel_product.channel_code' => $this->channel_code]);
    }

    public function productSku()
    {
        return $this->hasOne(ChannelProductSku::class, 'sku_id', 'sku_id')->where(['channel_product_sku.spu_id' => $this->spu_id]);
    }

    public function channelBiddingItem()
    {
        return $this->hasMany(ChannelBiddingItem::class, 'channel_bidding_id', 'id');
    }

    public function channelBiddingLogs()
    {
        return $this->hasMany(ChannelBiddingLog::class, 'bidding_no', 'bidding_no');
    }

    public function callbackBid()
    {
        return $this->hasMany(CarrymeCallbackLog::class, 'carryme_bidding_item_id', 'carryme_bidding_item_id')->whereIn('type', [CarrymeCallbackLog::TYPE_BID_ADD, CarrymeCallbackLog::TYPE_BID_CANCEL]);
    }

    public function getSourceFormatAttribute()
    {
        if (in_array($this->source, ['', self::SOURCE_APP])) {
            return self::SOURCE_APP;
        }
        if ($this->source == self::SOURCE_STOCK) {
            return self::SOURCE_STOCK;
        }
        return self::SOURCE_CHANNEL;
    }

    public function stockBidding()
    {
        return $this->hasOne(StockBidding::class, 'id', 'stock_bidding_id');
    }

    public function stockBiddingItem()
    {
        return $this->hasOne(StockBiddingItem::class, 'id', 'stock_bidding_item_id');
    }

    public function stockxBidding()
    {
        return $this->hasOne(StockxBidding::class, 'bidding_no', 'bidding_no');
    }

    public function channelPurchaseBidding()
    {
        return $this->hasOne(ChannelPurchaseBidding::class, 'bidding_no', 'bidding_no');
    }
    public function appBidding()
    {
        return $this->hasOne(AppBidding::class, 'bidding_no', 'bidding_no');
    }
}
