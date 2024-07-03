<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateWmsBase extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('admin_roles', function (Blueprint $table) {
            $table->string('tenant_id', 6)->nullable(false)->default('')->comment('租户编号')->after('description');
            $table->string('role_code', 30)->nullable(false)->default('')->comment('角色编码')->after('description');
            $table->dropUnique('roles_name_unique');
            $table->unique(['name', 'tenant_id'],'name_tenant_id_unique');
        });

        Schema::table('admin_users', function (Blueprint $table) {
            $table->dateTime('deleted_at')->nullable()->after('updated_at');
            $table->tinyInteger('is_tenant')->nullable(false)->default(0)->comment('租户管理员:0 不是 1 是')->after('config_id');
            $table->string('tenant_id', 6)->nullable(false)->default('')->comment('租户编号')->after('config_id');
            $table->string('org_code', 30)->nullable(false)->default('')->comment('所属组织编码')->after('config_id');
            $table->string('user_code', 30)->nullable(false)->default('')->comment('用户编码')->after('config_id');
            $table->string('current_warehouse', 40)->nullable(false)->default('')->comment('当前所在仓库编码')->after('config_id');
            $table->string('remark', 2000)->default('')->comment('备注')->after('config_id');
        });

        Schema::table('admin_menus', function (Blueprint $table) {
            $table->tinyInteger('type')->nullable(false)->default(0)->comment('类型 0-页面 1-操作')->after('routes');
            $table->tinyInteger('is_tenant')->nullable(false)->default(0)->comment('类型 0-平台菜单 1-租户菜单 2-平台和租户菜单')->after('routes');
        });

        // DB::statement("alter table admin_roles add column tenant_id CHAR(6) not null DEFAULT '' comment '租户编号' after description; ");
        // DB::statement("alter table admin_roles add column role_code VARCHAR(30) not null DEFAULT '' comment '角色编码' after description; ");
        // DB::statement("alter table admin_users add column deleted_at DATETIME DEFAULT NULL after updated_at; ");
        // DB::statement("alter table admin_users add column is_tenant TINYINT(2)  not null DEFAULT 0 comment '租户管理员:0 不是 1 是' after config_id;");
        // DB::statement("alter table admin_users add column tenant_id CHAR(6) not null DEFAULT '' comment '租户编号' after config_id; ");
        // DB::statement("alter table admin_users add column org_code VARCHAR(30) not null DEFAULT '' comment '所属组织编码' after config_id; ");
        // DB::statement("alter table admin_users add column user_code VARCHAR(30) not null DEFAULT '' comment '用户编码' after config_id; ");
        // DB::statement("alter table admin_menus add column type TINYINT(2) NOT NULL DEFAULT 0 COMMENT '类型 0-页面 1-操作' after routes; ");
        // DB::statement("alter table admin_menus add column is_tenant TINYINT(2) NOT NULL DEFAULT 0 COMMENT '类型 0-平台菜单 1-租户菜单 2-平台和租户菜单' after routes; ");
        DB::statement("update admin_menus set is_tenant = 2 where title in ('system','Admin','Role','menu');");


        // 组织
        Schema::create('wms_organization', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('name', 255)->default('')->comment('名称');
            $table->string('org_code', 30)->default('')->comment('组织编码');
            $table->tinyInteger('type')->default(1)->comment('组织类型 0-租户 1-供应商 2-仓库 3-店铺 4-客户');
            $table->tinyInteger('status')->default(1)->comment('状态 0未启用 1已启用');
            $table->integer('parent_id')->default(0)->comment('parent_id');
            $table->char('tenant_id', 6);
            $table->integer('updated_user')->default(0)->comment('最新操作人');
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->dateTimeTz('created_at', 0)->nullable()->comment('创建时间');
            $table->dateTimeTz('updated_at', 0)->nullable();
            $table->dateTimeTz('deleted_at', 0)->nullable();
        });
        DB::statement("alter table wms_organization comment '组织' ");

        // 租户记录
        Schema::create('wms_tenant_id_log', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('org_code', 30)->default('')->comment('组织编码');
            $table->char('tenant_id', 6)->default('')->unique()->comment('租户id');
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->dateTimeTz('created_at', 0)->nullable()->comment('创建时间');
        });
        DB::statement("alter table wms_tenant_id_log comment '租户记录' ");

        // 商品
        Schema::create('wms_product', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->integer('brand_id')->default(0)->comment('品牌id');
            $table->integer('category_id')->default(0)->comment('分类ID');
            $table->integer('serie_id')->default(0)->comment('系列编码');
            $table->string('img', 500)->default('')->comment('图片');
            $table->string('name', 255)->default('')->comment('名称');
            $table->string('product_sn', 100)->default('')->comment('货号')->index('product_sn');
            $table->tinyInteger('type')->default(1)->comment('类型 0:实物 1：虚拟 2：赠品 3：附属品 4：其他');
            $table->tinyInteger('sort')->default(1)->comment('排序（值越大越靠前）');
            $table->tinyInteger('status')->default(1)->comment('状态 0未启用 1已启用');
            $table->string('note', 1000)->default('')->comment('备注');
            $table->char('tenant_id', 6);
            $table->integer('updated_user')->default(0)->comment('最新操作人');
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->dateTimeTz('created_at', 0)->nullable()->comment('创建时间');
            $table->dateTimeTz('updated_at', 0)->nullable();
            $table->dateTimeTz('deleted_at', 0)->nullable();
        });
        DB::statement("alter table wms_product comment '商品' ");

        // 品牌
        Schema::create('wms_product_brands', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('code', 30)->default('')->comment('品牌编码')->unique();
            $table->string('name', 255)->default('')->comment('品牌名称');
            $table->tinyInteger('status')->default(1)->comment('状态 0未启用 1已启用');
            $table->tinyInteger('sort')->default(1)->comment('排序（值越大越靠前）');
            $table->string('note', 1000)->default('')->comment('备注');
            $table->char('tenant_id', 6);
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->integer('updated_user')->default(0)->comment('最新操作人');
            $table->dateTimeTz('created_at', 0)->nullable()->comment('创建时间');
            $table->dateTimeTz('updated_at', 0)->nullable();
        });
        DB::statement("alter table wms_product_brands comment '品牌' ");

        // 分类
        Schema::create('wms_product_category', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->integer('pid')->default(0)->comment('父级id');
            $table->string('code', 30)->default('')->comment('分类编码')->unique();
            $table->string('name', 100)->default('')->comment('分类名称');
            $table->string('note', 1000)->default('')->comment('备注');
            $table->tinyInteger('status')->default(1)->comment('状态 0未启用 1已启用');
            $table->tinyInteger('sort')->default(1)->comment('排序（值越大越靠前）');
            $table->tinyInteger('level')->default(1)->comment('级别');
            $table->string('path', 255)->default('')->comment('该类目所有父类目 id 用 - 连接');
            $table->char('tenant_id', 6);
            $table->integer('updated_user')->default(0)->comment('最新操作人');
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->dateTimeTz('created_at', 0)->nullable()->comment('创建时间');
            $table->dateTimeTz('updated_at', 0)->nullable();
        });
        DB::statement("alter table wms_product_category comment '分类' ");

        // 条码规格
        Schema::create('wms_spec_and_bar', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->integer('product_id')->default(0)->comment('商品id')->index('product_id');
            $table->string('code', 30)->default('')->comment('ERP编码');
            $table->string('sku', 100)->default('')->comment('sku编码')->index('sku');
            $table->tinyInteger('type')->default(0)->comment('产品类型 0-初始 1-唯一码产品 2-普通产品');
            $table->string('bar_code', 100)->default('')->comment('条形码')->index('bar_code');
            $table->string('spec_one', 100)->default('')->comment('规格1');
            $table->string('spec_two', 100)->default('')->comment('规格2');
            $table->string('spec_three', 100)->default('')->comment('规格3');
            $table->decimal('retails_price', 10, 2)->default(0)->comment('零售价');
            $table->decimal('tag_price', 10, 2)->default(0)->comment('吊牌价');
            $table->decimal('const_price', 10, 2)->default(0)->comment('参考成本价');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6);
            $table->integer('updated_user')->default(0)->comment('最新操作人');
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->dateTimeTz('created_at', 0)->nullable()->comment('创建时间');
            $table->dateTimeTz('updated_at', 0)->nullable();
            $table->dateTimeTz('deleted_at', 0)->nullable();
        });
        DB::statement("alter table wms_spec_and_bar comment '条码规格' ");

        // 仓库
        Schema::create('wms_warehouse', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('warehouse_code', 30)->default('')->comment('仓库编码')->index('warehouse_code');
            $table->string('warehouse_name', 50)->default('')->comment('仓库名称');
            $table->tinyInteger('type')->default(0)->comment('仓库类型 0-销售仓 1-退货仓 2-换季仓 3-虚拟仓');
            $table->tinyInteger('attribute')->default(0)->comment('仓库属性 0-自有仓 1-云仓');
            $table->string('contact_name', 30)->default('')->comment('联系人');
            $table->string('contact_phone', 30)->default('')->comment('手机号');
            $table->tinyInteger('status')->default(1)->comment('状态 0未启用 1已启用');
            $table->tinyInteger('tag')->default(0)->comment('标签 未启用');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->string('log_prod_ids', 2000)->default('')->comment('物流产品id');
            $table->char('tenant_id', 6);
            $table->integer('admin_user_id')->default(0)->comment('最新操作人');
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->dateTimeTz('created_at', 0)->nullable()->comment('创建时间');
            $table->dateTimeTz('updated_at', 0)->nullable();
            $table->dateTimeTz('deleted_at', 0)->nullable();
        });
        DB::statement("alter table wms_warehouse comment '仓库' ");

        // 位置码
        Schema::create('wms_area_location', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('location_code', 30)->default('')->comment('位置码')->index('location_code');
            $table->string('area_code', 30)->default('')->comment('库区编码');
            $table->string('warehouse_code', 30)->default('')->comment('仓库编码')->index('warehouse_code');
            $table->string('pick_number', 100)->default('')->comment('拣货序号');
            $table->tinyInteger('type')->default(0)->comment('货位类型 0-混合货位 1-整箱货位 2-拆零货位');
            $table->tinyInteger('tag')->default(0)->comment('状态 0未启用 1已启用');
            $table->integer('volume')->default(0)->comment('货位容积');
            $table->tinyInteger('status')->default(1)->comment('状态 0未启用 1已启用');
            $table->tinyInteger('is_able')->default(0)->comment('是否空闲 0不空闲 1空闲');
            $table->string('notes', 2000)->default('')->comment('说明');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6);
            $table->integer('admin_user_id')->default(0)->comment('最新操作人');
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->dateTimeTz('created_at', 0)->nullable()->comment('创建时间');
            $table->dateTimeTz('updated_at', 0)->nullable();
            $table->dateTimeTz('deleted_at', 0)->nullable();
        });
        DB::statement("alter table wms_area_location comment '位置码' ");

        // 自定义筛选
        Schema::create('wms_set_sift_plan', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->integer('user_id')->default(0)->comment('用户ID');
            $table->string('name', 50)->default('')->comment('筛选名称');
            $table->string('model', 300)->default('')->comment('使用的模型');
            $table->string('where_json', 1000)->default('')->comment('筛选条件');
            $table->string('show_json', 1000)->default('')->comment('字段展示顺序');
            $table->string('order_json', 1000)->default('')->comment('排序规则');
            $table->tinyInteger('with_start')->default(0)->comment('进页面带入 0-不带入 1-带入');
            $table->char('tenant_id', 6);
            $table->dateTimeTz('created_at', 0)->nullable()->comment('创建时间');
            $table->dateTimeTz('updated_at', 0)->nullable();
        });
        DB::statement("alter table wms_set_sift_plan comment '自定义筛选' ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('admin_roles', function (Blueprint $table) {
            $table->dropUnique('name_tenant_id_unique');
            $table->dropColumn(['tenant_id', 'role_code']);
            $table->unique(['name'],'roles_name_unique');
        });

        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn(['deleted_at', 'is_tenant', 'tenant_id', 'org_code', 'user_code','current_warehouse','remark']);
        });

        Schema::table('admin_menus', function (Blueprint $table) {
            $table->dropColumn(['type', 'is_tenant']);
        });

        Schema::dropIfExists('wms_organization');
        Schema::dropIfExists('wms_tenant_id_log');
        Schema::dropIfExists('wms_product');
        Schema::dropIfExists('wms_product_brands');
        Schema::dropIfExists('wms_product_category');
        Schema::dropIfExists('wms_spec_and_bar');
        Schema::dropIfExists('wms_warehouse');
        Schema::dropIfExists('wms_area_location');
        Schema::dropIfExists('wms_set_sift_plan');
    }
}
