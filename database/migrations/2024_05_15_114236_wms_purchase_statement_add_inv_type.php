<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class WmsPurchaseStatementAddInvType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Schema::table('wms_purchase_statements', function (Blueprint $table) {
        //     $table->tinyInteger('inv_type')->default(0)->comment('库存类型 0-自营 1-寄卖');
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