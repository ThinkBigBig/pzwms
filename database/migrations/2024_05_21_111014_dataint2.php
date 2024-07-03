<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Dataint2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wms_shendu_inv', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->string('product_sn', 40)->default('')->comment('货号');
            $table->string('spec_one', 40)->default('')->comment('规格');
            $table->decimal('cost_amount', 10, 2)->default(0)->comment('库存成本额');
            $table->decimal('weight_cost_price', 10, 2)->default(0)->comment('加权成本价');
            $table->string('uniq_code', 20)->default('')->comment('唯一码');
            $table->integer('wh_inv')->default(0)->comment('在仓库存');
            $table->integer('sale_inv')->default(0)->comment('可售库存');
            $table->integer('lock_inv')->default(0)->comment('锁定库存');
            $table->integer('wt_send_inv')->default(0)->comment('待发库存');
            $table->integer('freeze_inv')->default(0)->comment('冻结库存');
            $table->string('warehouse_code', 30)->comment('仓库编码')->index('warehouse_code');
            $table->string('warehouse_name', 30)->comment('仓库名称');
            $table->string('sup_name', 30)->comment('供应商名称');
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->tinyInteger('inv_type')->default(0)->comment('库存类型:0-自营 1-寄卖');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-瑕疵');
            $table->string('quality_level', 5)->default('')->comment('质量等级');
            $table->string('batch_no',30)->default('')->comment('批次号');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_shendu_inv comment '慎独产品库存明细' ");
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
