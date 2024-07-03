<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WmsPutaway extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //上架单
        Schema::create('wms_putaway_list', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('type')->default(0)->comment('单据类型 1-入库上架单 2-移位上架单 3-取消上架单');
            $table->string('putaway_code',30)->comment('上架单据编码')->index('putaway_code');
            $table->string('origin_code',30)->default('')->comment('移位单编码');
            $table->tinyInteger('status')->default(0)->comment('单据状态 0-暂存 1-已审核');
            $table->tinyInteger('putaway_status')->default(0)->comment('上架状态 0-上架中 1-已完成');
            $table->integer('total_num')->default(0)->comment('上架总数');
            $table->string('warehouse_code',30)->comment('仓库编码')->index('warehouse_code');
            $table->string('warehouse_name',50)->comment('仓库名称');
            $table->string('remark',2000)->default('')->comment('备注');
            $table->integer('create_user_id')->default(0)->comment('创建人');
            $table->dateTime('completed_at')->nullable()->comment('完成时间');
            $table->integer('submitter_id')->default(0)->comment('上架单完成人');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->char('tenant_id', 6)->default('0');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_putaway_list comment '上架单' ");


        // 上架单明细
        Schema::create('wms_putaway_detail', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('putaway_code',30)->comment('上架单据编码')->index('putaway_code');
            $table->tinyInteger('type')->default(0)->comment('单据类型 1-入库上架单 2-移位上架单  3-取消上架单');
            $table->tinyInteger('put_unit')->default(0)->comment('上架单元:0-件 1-散件 2-箱');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->string('bar_code',100)->default('')->comment('条形码');
            $table->string('uniq_code',20)->default('')->comment('唯一码');
            $table->string('area_code',30)->default('')->comment('库区编码');
            $table->string('location_code',30)->default('')->comment('位置码');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->char('quality_level',5)->default('')->comment('质量等级');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_putaway_detail comment '上架单明细' ");

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wms_putaway_list');
        Schema::dropIfExists('wms_putaway_detail');
    }
}
