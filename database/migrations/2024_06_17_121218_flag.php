<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Flag extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('wms_purchase_orders', function (Blueprint $table) {
            $table->tinyInteger('flag')->default(0)->comment('旗帜 0-无 1-红旗 2-黄旗 3-绿旗 4-蓝旗 5-紫旗 6-粉旗 7-黑旗');
        });
        Schema::table('wms_consignment_orders', function (Blueprint $table) {
            $table->tinyInteger('flag')->default(0)->comment('旗帜 0-无 1-红旗 2-黄旗 3-绿旗 4-蓝旗 5-紫旗 6-粉旗 7-黑旗');
        });
        Schema::table('wms_arrival_regist', function (Blueprint $table) {
            $table->tinyInteger('flag')->default(0)->comment('旗帜 0-无 1-红旗 2-黄旗 3-绿旗 4-蓝旗 5-紫旗 6-粉旗 7-黑旗');
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
