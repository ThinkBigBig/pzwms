<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateWmsQc extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        // 质检单
        Schema::create('wms_quality_list', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('warehouse_code',30)->comment('仓库编码')->index('warehouse_code');
            $table->string('warehouse_name',50)->comment('仓库名称');
            $table->tinyInteger('type')->default(0)->comment('质检单据类型 1-入库质检单 2-仓内质检单');
            $table->string('qc_code',40)->default('')->comment('质检单据编码')->index('qc_code');
            $table->string('recv_code',40)->default('')->comment('收货单编码');
            $table->integer('arr_id')->default(0)->comment('到货登记id');
            $table->tinyInteger('status')->default(0)->comment('单据状态 0-暂存 1-已审核');
            $table->tinyInteger('qc_status')->default(0)->comment('单据状态 0-质检中 1-已完成');
            $table->tinyInteger('method')->default(0)->comment('质检方式 1-一键质检 2-逐件质检 3-收货即质检');
            $table->integer('total_num')->default(0)->comment('质检数量');
            $table->integer('normal_num')->default(0)->comment('正品数');
            $table->integer('defect_num')->default(0)->comment('瑕疵数');
            $table->integer('probable_defect_num')->default(0)->comment('疑似瑕疵数');
            $table->string('remark',2000)->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('create_user_id')->default(0)->comment('创建人');
            $table->integer('submit_user_id')->default(0)->comment('质检人');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTime('completed_at')->nullable()->comment('质检完成时间');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_quality_list comment '质检单' ");


        // 质检明细
        Schema::create('wms_quality_detail', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('qc_code',40)->default('')->comment('质检单据编码')->index('qc_code');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->string('bar_code',100)->default('')->comment('条形码')->index('bar_code');
            $table->string('uniq_code',20)->default('')->comment('唯一码')->index('uniq_code');
            $table->integer('arr_id')->default(0)->comment('到货登记id');
            $table->string('pic',300)->default('')->comment('质检图片');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->char('quality_level',5)->default('')->comment('质量等级');
            $table->tinyInteger('status')->default(0)->comment('状态 1-生效 2-作废');
            $table->string('remark',2000)->default('')->comment('备注');
            $table->string('location_code',30)->default('')->comment('质检位置码');
            $table->string('area_name',30)->default('')->comment('质检库区');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_quality_detail comment '质检明细' ");

        // 质检确认单
        Schema::create('wms_quality_confirm_list', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('qc_code',40)->default('')->comment('质检单据编码')->index('qc_code');
            $table->integer('arr_id')->default(0)->comment('到货登记id');
            $table->string('arr_code',40)->default('')->comment('源单登记单编码');
            $table->string('warehouse_code',30)->comment('仓库编码');
            $table->string('warehouse_name',50)->comment('仓库名称');
            $table->tinyInteger('status')->default(0)->comment('状态 0-待确认 1-已确认 2-已作废');
            $table->string('pic',300)->default('')->comment('质检图片');
            $table->string('notes',2000)->default('')->comment('提交说明');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->string('bar_code',100)->default('')->comment('条形码');
            $table->string('uniq_code',20)->default('')->comment('唯一码');
            $table->tinyInteger('old_quality_type')->default(0)->comment('原质量类型 1-正品 2-疑似瑕疵');
            $table->char('old_quality_level',5)->default('')->comment('原质量等级');
            $table->tinyInteger('quality_type')->default(0)->comment('上报质量类型 1-正品 2-疑似瑕疵');
            $table->char('quality_level',5)->default('')->comment('上报质量等级');
            $table->tinyInteger('confirm_quality_type')->default(0)->comment('确认后质量类型');
            $table->char('confirm_quality_level',5)->default('')->comment('确认后的质量等级');
            $table->tinyInteger('type')->default(0)->comment('质检单据类型 1-入库质检单 2-仓内质检单');
            $table->string('location_code',30)->default('')->comment('质检位置码');
            $table->string('area_name',30)->default('')->comment('质检库区');
            $table->integer('submitter_id')->default(0)->comment('质检人');
            $table->integer('comfirmor_id')->default(0)->comment('质检确认人');
            $table->dateTime('confirm_at')->nullable()->comment('质检确认时间');
            $table->string('confirm_remark',2000)->default('')->comment('质检确认备注');
            $table->string('remark',2000)->default('')->comment('备注');
            $table->integer('admin_user_id')->default(0)->comment('最后更新人');
            $table->char('tenant_id', 6)->default('0');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_quality_confirm_list comment '质检确认单' ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wms_quality_list');
        Schema::dropIfExists('wms_quality_detail');
        Schema::dropIfExists('wms_quality_confirm_list');
    }
}
