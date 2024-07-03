<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class Version extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
          // 版本管理
          Schema::create('wms_version', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('port')->default(0)->comment('APP端口：1->安卓Android；2->苹果iOS');
            $table->tinyInteger('type')->default(0)->comment('更新类型：1->静默更新；2->可选更新; 3->强制更新');
            $table->tinyInteger('check')->default(0)->comment('是否审核：1->审核中；2->通过审核');
            $table->string('version_num',100)->default('')->comment('版本号');
            $table->string('download_link',100)->default('')->comment('下载链接');
            $table->text('note_jp')->default('')->comment('更新提示: 日文');
            $table->text('note_zh')->default('')->comment('更新提示: 中文');
            $table->tinyInteger('status')->default(0)->comment('状态 0未启用 1已启用');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最新操作人');
            $table->dateTimeTz('version_at')->nullable()->comment('版本更新时间');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at')->nullable()->comment('版本更新时间');
        });
        DB::statement("alter table wms_version comment '版本管理' ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wms_version');
    }
}
