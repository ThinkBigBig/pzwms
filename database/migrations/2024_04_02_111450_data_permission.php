<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DataPermission extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         // 数组组织
         Schema::create('wms_data_permission', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('name', 255)->default('')->comment('名称');
            $table->string('code', 30)->default('')->comment('组织编码');
            $table->tinyInteger('type')->default(0)->comment('组织类型 0-组织 1-供应商 2-仓库 3-店铺 4-客户');
            $table->tinyInteger('status')->default(1)->comment('状态 0未启用 1已启用');
            $table->string('parent_code',30)->default(0)->comment('上级code');
            $table->string('path', 50)->default('')->comment('层级');
            $table->string('remark', 200)->default('')->comment('备注');
            $table->char('tenant_id', 6);
            $table->integer('updated_user')->default(0)->comment('最新操作人');
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->dateTimeTz('created_at', 0)->nullable()->comment('创建时间');
            $table->dateTimeTz('updated_at', 0)->nullable();
            $table->dateTimeTz('deleted_at', 0)->nullable();
        });
        DB::statement("alter table wms_data_permission comment '组织数据权限' ");

        //
        Schema::create('wms_user_data_permission', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('type')->default(0)->comment('组织类型 0-组织 1-供应商 2-仓库 3-店铺 4-客户');
            $table->char('user_code', 30)->default('')->comment('用户编码');
            $table->char('org_code', 30)->default('')->comment('组织编码');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->integer('updated_user')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at')->comment('创建时间');
            $table->dateTimeTz('updated_at')->nullable();
            $table->dateTimeTz('deleted_at')->nullable();
        });
        DB::statement("alter table wms_user_data_permission comment '用户数据权限' ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::dropIfExists('wms_data_permission');
        Schema::dropIfExists('wms_user_data_permission');
    }
}
