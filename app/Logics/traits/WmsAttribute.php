<?php

namespace App\Logics\traits;


trait WmsAttribute
{

    static $order_platform_map = [
        1=>'其他',
        2=>'手工',
        3=>'淘宝',
        4=>'天猫',
        5=>'京东',
        6=>'寄卖召回',
        7=>'手工召回',
        8=>'采购系统',
        9=>'第三方平台',
        10=>'仓内移位',
        11=>'得物普通现货',
        12=>'抖音小店',
        13=>'得物极速预售',
        14=>'得物普货预售',
        15=>'拼多多',
        16=>'唯品会',
        17=>'得物跨境',
        18=>'得物品牌直发',
        19=>'得物极速',
        20=>'调拨',
    ];
    public function getOrderPlatformTxtAttribute(): string
    {
        return self::$order_platform_map[$this->order_platform] ?? '';
    }

    // 文件地址拼接
    public function getFileUrlAttribute(): string
    {
        if (!$this->file_path) return '';
        return env('ALIYUN_OSS_HOST') . $this->file_path;
    }

    // 文件地址拼接
    public function getDeliverUrlAttribute(): string
    {
        if (!$this->deliver_path) return '';
        return env('ALIYUN_OSS_HOST') . $this->deliver_path;
    }
}
