<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class WmsShippingRequestTemp extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        // DB::statement("alter table wms_shipping_request drop column paysuccess_time, drop column delivery_deadline; ");
        // Schema::table('wms_shipping_request', function (Blueprint $table) {
        //     $table->dateTime('paysuccess_time')->nullable()->comment('下单时间');
        //     $table->dateTime('delivery_deadline')->nullable()->comment('最晚发货时间');
        // });
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
