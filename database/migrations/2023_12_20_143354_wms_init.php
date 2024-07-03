<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WmsInit extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 物流公司
        Schema::create('wms_logistics_company', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id('id')->autoIncrement()->comment('ID');
            $table->string('company_code', 30)->default('')->comment('公司编码')->index('company_code');
            $table->string('company_name', 50)->default('')->comment('公司名称');
            $table->string('short_name', 30)->default('')->comment('简称');
            $table->tinyInteger('status')->unsigned()->default(0)->comment('状态 0未启用 1已启用');
            $table->string('contact_name', 30)->default('')->comment('联系人');
            $table->string('contact_phone', 30)->default('')->comment('手机号');
            $table->string('address', 200)->default('')->comment('地址');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最新操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
            $table->dateTimeTz('deleted_at')->nullable();
        });
        DB::statement("alter table wms_logistics_company comment '物流公司' ");

        // 物流产品
        Schema::create('wms_logistics_products', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id('id')->autoIncrement()->comment('ID');
            $table->string('product_code', 30)->comment('物流产品编码')->index('product_code');
            $table->string('product_name')->comment('物流产品名称');
            $table->string('company_code', 40)->comment('物流公司编码')->index('company_code');
            $table->tinyInteger('pickup_method')->default(0)->comment('提货方式 1-自提 2-第三方物流 3-快递 4-干线物流 5-其他');
            $table->tinyInteger('payment')->default(0)->comment('结算方式 1-月付 2-现结 3-到付 4-其他');
            $table->tinyInteger('status')->default(0)->comment('状态 0未启用 1已启用');
            $table->string('remark', 2000)->nullable()->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最新操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
            $table->dateTimeTz('deleted_at')->nullable();
        });
        DB::statement("alter table wms_logistics_products comment '物流产品' ");

        // 仓库
        // Schema::create('wms_warehouse', function (Blueprint $table) {
        //     $table->engine = 'InnoDB';
        //     $table->id('id')->autoIncrement()->comment('ID');
        //     $table->string('warehouse_code')->unique()->comment('仓库编码');
        //     $table->string('warehouse_name')->comment('仓库名称');
        //     $table->tinyInteger('type')->default(0)->comment('仓库类型 0-销售仓 1-退货仓 2-换季仓 3-虚拟仓');
        //     $table->tinyInteger('attribute')->default(0)->comment('仓库属性 0-自有仓 1-云仓');
        //     $table->string('contact_name')->nullable(false)->default('')->comment('联系人');
        //     $table->string('contact_phone')->nullable(false)->default('')->comment('手机号');
        //     $table->tinyInteger('status')->default(0)->comment('状态 0未启用 1已启用');
        //     $table->string('remark')->comment('备注');
        //     $table->char('tenant_id', 6)->default('0');
        //     $table->integer('admin_user_id')->default(0)->comment('最新操作人');
        //     $table->dateTimeTz('created_at');
        //     $table->dateTimeTz('updated_at');
        //     $table->dateTimeTz('deleted_at')->nullable();
        // });
        // DB::statement("alter table wms_warehouse comment '仓库' ");

        // 仓库物流产品
        // Schema::create('wms_warehouse_logistics_products', function (Blueprint $table) {
        //     $table->engine = 'InnoDB';
        //     $table->id('id')->autoIncrement()->comment('ID');
        //     $table->string('warehouse_code', 30)->notNull()->default('')->comment('仓库编码');
        //     $table->string('product_code', 30)->notNull()->default('')->comment('物流产品编码');
        //     $table->string('remark', 2000)->notNull()->default('')->comment('备注');
        //     $table->char('tenant_id', 6)->default('0');
        //     $table->integer('admin_user_id')->notNull()->default(0)->comment('最新操作人');
        //     $table->dateTimeTz('created_at');
        //     $table->dateTimeTz('updated_at');
        //     $table->dateTimeTz('deleted_at')->nullable();
        // });
        // DB::statement("alter table wms_warehouse_logistics_products comment '仓库物流产品' ");

        // 仓库库区
        Schema::create('wms_warehouse_area', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id('id')->autoIncrement()->comment('ID');
            $table->string('area_code', 30)->notNull()->comment('库区编码')->index('area_code');
            $table->string('area_name', 50)->nullable(false)->default('')->comment('库区名称');
            $table->string('warehouse_code', 30)->nullable(false)->default('')->comment('仓库编码')->index('warehouse_code');
            $table->tinyInteger('type')->default(0)->comment('库区类型 0-架上库区 1-收货暂存区 2-质检暂存区 3-下架暂存区');
            $table->tinyInteger('purpose')->default(0)->comment('库区用途 0-暂存 1-拣选 2-爆品 3-备货 ');
            $table->tinyInteger('status')->default(0)->comment('状态 0未启用 1已启用');
            $table->string('notes', 2000)->nullable(false)->default('')->comment('说明');
            $table->string('remark', 2000)->nullable(false)->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最新操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
            $table->dateTimeTz('deleted_at')->nullable();
        });
        DB::statement("alter table wms_warehouse_area comment '仓库库区' ");

        // 库区位置码
        // Schema::create('wms_area_location', function (Blueprint $table) {
        //     $table->engine = 'InnoDB';
        //     $table->id('id')->autoIncrement()->comment('ID');
        //     $table->string('location_code')->nullable(false)->comment('位置码');
        //     $table->string('area_code')->nullable(false)->comment('库区编码');
        //     $table->string('pick_number')->nullable(false)->comment('拣货序号');
        //     $table->tinyInteger('type')->nullable(false)->default(0)->comment('货位类型 0-混合货位 1-整箱货位 2-拆零货位');
        //     $table->integer('volume')->nullable(false)->default(0)->comment('货位容积');
        //     $table->tinyInteger('status')->nullable(false)->default(0)->comment('状态 0未启用 1已启用');
        //     $table->string('notes')->notNull()->default('')->comment('说明');
        //     $table->string('remark')->notNull()->default('')->comment('备注');
        //     $table->char('tenant_id', 6)->default('0');
        //     $table->integer('admin_user_id')->nullable(false)->default(0)->comment('最新操作人');
        //     $table->dateTimeTz('created_at');
        //     $table->dateTimeTz('updated_at');
        //     $table->dateTimeTz('deleted_at')->nullable();
        // });
        // DB::statement("alter table wms_area_location comment '库区位置码' ");


        // 操作日志
        // Schema::create('wms_option_log', function (Blueprint $table) {
        //     $table->engine = 'InnoDB';
        //     $table->id('id')->autoIncrement()->comment('ID');
        //     $table->tinyInteger('type')->comment('日志类型:1-到货登记日志');
        //     $table->string('doc_code',30)->nullable(false)->comment('单据编码');
        //     $table->string('option',100)->nullable(false)->default('')->comment('操作类型');
        //     $table->string('desc',100)->nullable(false)->default('')->comment('描述');
        //     $table->string('detail',2000)->nullable(false)->default('')->comment('详情');
        //     $table->char('tenant_id', 6)->default('0');
        //     $table->integer('admin_user_id')->nullable(false)->default(0)->comment('最新操作人');
        //     $table->string('admin_name',50)->nullable(false)->default('')->comment('操作人名称');
        //     $table->dateTimeTz('created_at');
        // });
        // DB::statement("alter table wms_option_log comment '操作日志' ");

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wms_logistics_company');
        Schema::dropIfExists('wms_logistics_products');
        // Schema::dropIfExists('wms_warehouse');
        // Schema::dropIfExists('wms_warehouse_logistics_products');
        Schema::dropIfExists('wms_warehouse_area');
        // Schema::dropIfExists('wms_area_location');
        // Schema::dropIfExists('wms_option_log');
    }
}
