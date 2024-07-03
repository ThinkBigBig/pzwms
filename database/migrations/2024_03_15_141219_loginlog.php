<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Loginlog extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_login_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('type')->default(0)->comment('0-后台登录 1-pda登录');
            $table->integer('user_id')->default(0)->comment('用户id');
            $table->string('path',100)->default('')->comment('url');
            $table->string('ip',20)->default('')->comment('ip');
            $table->string('info',255)->default('')->comment('说明');
            $table->dateTimeTz('created_at');
        });
        DB::statement("alter table user_login_logs comment '用户登录日志' ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_login_logs');
    }
}
