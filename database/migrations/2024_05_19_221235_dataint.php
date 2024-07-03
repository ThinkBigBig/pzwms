<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Dataint extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('wms_pre_allocation_detail', function (Blueprint $table) {
            $table->string('sup_name', 100)->default('')->comment('供应商名称')->after('sup_id');
        });

        // 配货任务单详情
        Schema::create('wms_allocation_task_detail', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('origin_code', 40)->default('')->comment('任务单编码')->index('origin_code');
            $table->string('pre_alloction_code', 40)->default('')->comment('配货单编码')->index('pre_alloction_code');
            $table->string('request_code', 40)->default('')->comment('需求单编码')->index('request_code');
            $table->string('warehouse_code', 30)->comment('仓库编码')->index('warehouse_code');
            $table->string('location_code', 30)->default('')->comment('位置码');
            $table->integer('batch_no')->default(0)->comment('预配批次');
            $table->string('uniq_code', 20)->default('')->comment('唯一码');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-瑕疵');
            $table->string('quality_level',5)->default('')->comment('质量等级');
            $table->integer('num')->default(0)->comment('数量');
            $table->integer('actual_num')->default(0)->comment('已配数量');
            $table->integer('receiver_id')->default(0)->comment('配货人');
            $table->dateTime('allocated_at')->nullable()->comment('配货时间');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_allocation_task_detail comment '配货任务单详情' ");
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
