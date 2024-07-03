<?php

namespace App\Models;

use App\Logics\BaseLogic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelOrder extends BaseModel
{
    use HasFactory;

    protected $table = 'channel_order';

    protected $guarded = [];

    const STATUS_DEFAULT = 0;//默认
    const STATUS_CREATED = 1;//待商家确认发货
    const STATUS_CONFIRM = 2;//待平台发货
    const STATUS_DELIVER = 3;//已发货
    const STATUS_CANCEL = 4;//已取消
    const STATUS_CLOSE = 5;//已关闭
    const STATUS_COMPLETE = 6;//已完成

    static $status_maps = [
        self::STATUS_DEFAULT => '',
        self::STATUS_CREATED => '待商家确认发货',
        self::STATUS_CONFIRM => '待平台发货',
        self::STATUS_DELIVER => '已发货',
        self::STATUS_CANCEL => '已取消',
        self::STATUS_CLOSE => '已关闭',
        self::STATUS_COMPLETE => '已完成',
    ];

    //订单有效状态
    static $active_status = [
        self::STATUS_CREATED,
        self::STATUS_CONFIRM,
        self::STATUS_DELIVER
    ];

    // 未发货状态
    static $no_sendout_status = [
        self::STATUS_DEFAULT,
        self::STATUS_CREATED,
        self::STATUS_CONFIRM,
    ];

    // 订单已完结状态
    static $end_status = [
        self::STATUS_CANCEL,
        self::STATUS_CLOSE,
        self::STATUS_COMPLETE
    ];

    // 订单失效状态
    static $invalid_status = [
        self::STATUS_CANCEL,
        self::STATUS_CLOSE,
    ];

    const SOURCE_APP = 'app';
    const SOURCE_STOCK = 'stock';

    const PURCHASE_DEFAULT = 0;
    const PURCHASE_OK = 1;//采买完成

    const PROGRESS_DEFAULT = 0;//默认
    const PROGRESS_PENDING = 1;//进行中
    const PROGRESS_DONE = 2;//已完成

    public function channel(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Channel::class,'code','channel_code')->select(['code','name']);
    }

    public function channelBidding(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ChannelBidding::class,'id','channel_bidding_id');
    }

    public function channelBiddingItem(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ChannelBiddingItem::class,'id','channel_bidding_item_id');
    }

    public function carrymeNotice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CarrymeNoticeLog::class,'channel_order_id');
    }

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'paysuccess_time' => 'datetime:Y-m-d H:i:s',
        'business_confirm_time' => 'datetime:Y-m-d H:i:s',
        'platform_confirm_time' => 'datetime:Y-m-d H:i:s',
        'close_time' => 'datetime:Y-m-d H:i:s',
        'cancel_time' => 'datetime:Y-m-d H:i:s',
        'completion_time' => 'datetime:Y-m-d H:i:s',
    ];

    protected $appends = ['status_txt','show_price','price_unit_txt','dispatch_num_print','source_format','purchase_status_txt'];

    public function getStatusTxtAttribute(): string
    {
        return self::$status_maps[$this->status]??'';
    }


    const PRICE_UNIT_FEN = 1;
    const PRICE_UNIT_YUNA = 2;
    public function getShowPriceAttribute(): string
    {
        if($this->price_unit==self::PRICE_UNIT_FEN){
            return bcdiv($this->price,100,2);
        }
        return $this->price;
    }

    public function getPriceUnitTxtAttribute()
    {
        return BaseLogic::$currency_maps[$this->currency]??'';
    }

    public function getDispatchNumPrintAttribute()
    {
        if(in_array($this->channel_code , ['GOAT','STOCKX'])){
            return $this->dispatch_num_url;
        }
        return $this->dispatch_num;
    }

    public function getDispatchNumUrlAttribute()
    {
        if(!$this->attributes['dispatch_num_url']) return '';

        $dispatch_num_url = $this->attributes['dispatch_num_url'];
        if(strpos($dispatch_num_url,'http')===false){
            return env('ALIYUN_OSS_HOST', '') . $dispatch_num_url;
        }
        return $dispatch_num_url;
    }

    public function getPurchaseStatusTxtAttribute()
    {
        if(!$this->purchase_url) return '';
        $maps = [
            self::PURCHASE_DEFAULT => '待采购',
            self::PURCHASE_OK => '已采购',
        ];
        return $maps[$this->purchase_status]??'';
    }


    public function callbackOrder()
    {
        return $this->hasOne(CarrymeCallbackLog::class,'channel_order_id')->where('type',CarrymeCallbackLog::TYPE_ORDER_SUCCESS);
    }
    
    public function callbackCancel()
    {
        return $this->hasOne(CarrymeCallbackLog::class,'channel_order_id')->where('type',CarrymeCallbackLog::TYPE_ORDER_CANCEL);
    }

    public function getSourceFormatAttribute()
    {
        if($this->stock_source == self::SOURCE_STOCK){
            return self::SOURCE_STOCK;
        }
        return self::SOURCE_APP;
    }

    public function stockBddingItem()
    {
        return $this->hasOne(StockBiddingItem::class,'stock_bidding_item_id');
    }

    public function appOrder() {
        return $this->hasOne(AppOrder::class,'orderNo','order_no');
    }
    
}
