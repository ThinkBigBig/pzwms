<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Yh extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wms_receive_check', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->integer('arr_id')->default(0)->comment('登记单id')->index('arr_id');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->string('uniq_code', 100)->default('')->comment('唯一码/条形码')->index('uniq_code');
            $table->tinyInteger('type')->default(0)->comment('0-唯一码商品 1-普通商品');
            $table->tinyInteger('unit')->default(0)->comment('1-箱 2-散件');
            $table->tinyInteger('status')->default(0)->comment('0-未处理 1-已处理');
            $table->integer('num')->default(0)->comment('数量');
            $table->string('quality_level', 5)->comment('质量等级');
            $table->integer('admin_user_id')->default(0)->comment('操作人');
            $table->char('tenant_id', 6)->default('0');
        });
        DB::statement("alter table wms_receive_check comment '收货扫码检查表' ");

        // Schema::create('', function (Blueprint $table) {
        //     $table->engine = 'InnoDB';
        //     $table->id()->autoIncrement();
        //     $table->integer('type')->default(0)->comment('类型 1-确认收货');


        //     $table->integer('arr_id')->default(0)->comment('登记单id')->index('arr_id');
        //     $table->string('bar_code', 100)->default('')->comment('条形码');
        //     $table->string('uniq_code', 100)->default('')->comment('唯一码/条形码')->index('uniq_code');
        //     $table->tinyInteger('type')->default(0)->comment('0-唯一码商品 1-普通商品');
        //     $table->tinyInteger('unit')->default(0)->comment('1-箱 2-散件');
        //     $table->tinyInteger('status')->default(0)->comment('0-未处理 1-已处理');
        //     $table->integer('num')->default(0)->comment('数量');
        //     $table->string('quality_level', 5)->comment('质量等级');
        //     $table->integer('admin_user_id')->default(0)->comment('操作人');
        //     $table->char('tenant_id', 6)->default('0');
        // });
        // DB::statement("alter table wms_async_log comment '异步处理数据表' ");

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
