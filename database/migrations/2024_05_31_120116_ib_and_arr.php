<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IbAndArr extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
               // 入库单&登记单关联关系
               Schema::create('wms_ib_and_arr', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id()->autoIncrement();
                $table->tinyInteger('ib_type')->default(0)->comment("单据类型 1-采购入库、2-调拨入库、3-退货入库、4-其他入库");
                $table->string('warehouse_code', 40)->default('')->comment('仓库编码');
                $table->string('arr_code', 40)->default('')->comment('登记单编码')->index('arr_code');;
                $table->string('ib_code', 40)->default('')->comment('入库单编码')->index('ib_code');
                $table->string('erp_no', 40)->default('')->comment('erp入库单编码');
                $table->string('third_no', 40)->default('')->comment('三方单号');
                $table->string('source_code', 40)->default('')->comment('采购单号');
                $table->integer('rd_total')->default(0)->comment("确认数量");
                $table->dateTimeTz('created_at', 0)->comment('确认到货时间');
                $table->dateTimeTz('updated_at', 0);
                $table->char('tenant_id', 6);
            });
            DB::statement("alter table wms_ib_and_arr comment '入库单和登记单关联关系' ");
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
