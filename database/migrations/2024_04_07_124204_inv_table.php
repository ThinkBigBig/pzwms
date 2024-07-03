<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InvTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //仓库总库存
        Schema::create('wms_total_inv', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('warehouse_code', 30)->comment('仓库编码')->index('warehouse_code');
            $table->string('warehouse_name', 50)->comment('仓库名称')->index('warehouse_name');
            $table->string('bar_code', 100)->default('')->comment('条形码')->index('bar_code');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->char('quality_level', 5)->default('')->comment('质量等级');
            $table->integer('wh_inv')->default(0)->comment('在仓库存');
            $table->integer('shelf_inv')->default(0)->comment('架上库存');
            $table->integer('sale_inv')->default(0)->comment('可售库存');
            $table->integer('shelf_sale_inv')->default(0)->comment('架上可售库存');
            $table->integer('shelf_lock_inv')->default(0)->comment('架上锁定');
            $table->integer('wt_send_inv')->default(0)->comment('待发');
            $table->integer('wt_shelf_inv')->default(0)->comment('待上架');
            $table->integer('freeze_inv')->default(0)->comment('冻结');
            $table->integer('wt_shelf_cfm')->default(0)->comment('架上待确认');
            $table->integer('trf_inv')->default(0)->comment('调拨');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6);
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->integer('updated_user')->default(0)->comment('最新操作人');
            $table->dateTimeTz('created_at', 0)->nullable()->comment('创建时间');
            $table->dateTimeTz('updated_at', 0)->nullable();
        });
        DB::statement("alter table wms_total_inv comment '仓库总库存' ");

        //产品库存明细
        Schema::create('wms_sup_inv', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('warehouse_code', 30)->comment('仓库编码')->index('warehouse_code');
            $table->string('warehouse_name', 50)->comment('仓库名称');
            $table->tinyInteger('inv_type')->default(0)->comment('库存类型:0-自营 1-寄卖');
            $table->string('bar_code', 100)->default('')->comment('条形码')->index('bar_code');
            $table->integer('lot_num')->default(0)->comment('批次号');
            $table->string('uniq_code_1', 20)->default('')->comment('唯一码');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->char('quality_level', 5)->default('')->comment('质量等级');;
            $table->integer('sup_id')->default(0)->comment('供应商');
            $table->string('sup_name', 100)->comment('供应商名称');
            $table->decimal('buy_price', 10, 2)->default(0)->comment('采购价');
            $table->integer('wh_inv')->default(0)->comment('在仓库存');
            $table->integer('sale_inv')->default(0)->comment('可售库存');
            $table->integer('lock_inv')->default(0)->comment('锁定库存');
            $table->integer('wt_send_inv')->default(0)->comment('待发库存');
            $table->integer('freeze_inv')->default(0)->comment('冻结库存');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6);
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->integer('updated_user')->default(0)->comment('最新操作人');
            $table->dateTimeTz('created_at', 0)->nullable()->comment('创建时间');
            $table->dateTimeTz('updated_at', 0)->nullable();
        });
        DB::statement("alter table wms_sup_inv comment '供应商库存' ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::dropIfExists('wms_total_inv');
        Schema::dropIfExists('wms_sup_inv');
    }
}
