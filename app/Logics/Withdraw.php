<?php

namespace App\Logics;

use Illuminate\Support\Facades\DB;

class Withdraw
{
    static function orderList($params)
    {

        $list = DB::table('withdraw_orders_detailed')
            ->leftJoin('withdraw as w','withdraw_id','=','w.id')
            ->where(['withdraw_id' => $params['withdraw_id']])
            ->select(['withdraw_orders_detailed.*','w.admin_id','w.admin_name','withdraw_orders_detailed.product_name as title'])
            ->get();
        return $list ?? [];
    }
}