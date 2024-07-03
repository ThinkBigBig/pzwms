<?php

namespace App\Logics\traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

trait DataPermission
{
    /**
     * 模型的 "booted" 方法
     *
     * @return void
     */
    // protected static function booted()
    // {
    //     Log::info('222222');
    //     return;
    //     if (!defined('ADMIN_INFO')) return;

    //     static::addGlobalScope('warehouse_code', function (Builder $builder) {
    //         $table = $builder->getModel()->table;
    //         if ($table->hasColumn('warehouse_code')) {
    //             $codes = ADMIN_INFO['data_permission']['warehouse'];
    //             $builder->whereIn($table . '.warehouse_code', $codes);
    //         }
    //     });
    // }
}
