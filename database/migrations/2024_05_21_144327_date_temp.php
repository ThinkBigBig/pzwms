<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
class DateTemp extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("alter table wms_ib_order drop column paysuccess_time; ");
        Schema::table('wms_ib_order', function (Blueprint $table) {
            $table->dateTimeTz('paysuccess_time')->nullable()->comment('下单时间');
        });
        DB::statement("alter table wms_order_payments drop column paysuccess_time; ");
        Schema::table('wms_order_payments', function (Blueprint $table) {
            $table->dateTimeTz('paysuccess_time')->nullable()->comment('支付时间');
        });
        DB::statement("alter table wms_stock_check_request drop column order_at; ");
        Schema::table('wms_stock_check_request', function (Blueprint $table) {
            $table->dateTimeTz('order_at')->nullable()->comment('下单时间');
        });
        DB::statement("alter table wms_stock_differences drop column order_at; ");
        Schema::table('wms_stock_differences', function (Blueprint $table) {
            $table->dateTimeTz('order_at')->nullable()->comment('下单时间');
        });
        DB::statement("alter table wms_stock_check_bill drop column order_at; ");
        Schema::table('wms_stock_check_bill', function (Blueprint $table) {
            $table->dateTimeTz('order_at')->nullable()->comment('下单时间');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
