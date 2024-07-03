<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BidExcutionLog extends Model
{
    use HasFactory;
    protected $guarded = [];

    const DEFAULT = 0;
    const WAIT = 1; //待执行
    const PENDING = 2; //执行中
    const DONE = 3; //已执行
    const COMPLETE = 4; //已完成

    //取消中的状态
    static $cancel_status = [
        self::WAIT, self::PENDING, self::DONE
    ];

    static function addLog($data)
    {
        //货号的最后一位是字母0 奇数1 偶数2
        $v = substr($data['product_sn'], -1);
        $slice = 0;
        if (is_numeric($v)) {
            $slice = ($v % 2 == 0) ? 2 : 1;
        }
        $data['slice'] = $slice;
        BidExcutionLog::create($data);
    }
}
