<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WmsSeries extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wms_supplier_documents', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('sup_code', 40)->comment('供应商编码');
            $table->tinyInteger('type')->default(0)->comment('证件类型 1-个人番号卡 2-保险证 3-在留卡 4-护照 5-免许证');
            $table->string('name', 100)->default('')->comment('氏名');
            $table->string('personal_number', 100)->default('')->comment('个人番号');
            $table->string('address', 255)->default('')->comment('住所');
            $table->tinyInteger('passport_type')->default(0)->comment('护照类型 1-普通护照 2-公务护照 3-外交护照');
            $table->string('passport_number', 64)->default('')->comment('护照编号');
            $table->char('tenant_id', 6);
            $table->integer('updated_user')->default(0)->comment('最后操作人');
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->dateTimeTz('created_at', 0);
            $table->dateTimeTz('updated_at', 0)->nullable();
            $table->dateTimeTz('deleted_at')->nullable();
        });
        DB::statement("alter table wms_supplier_documents comment '供应商证件信息' ");

        Schema::create('wms_product_series', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->integer('parent_id')->default(0)->comment('上级的编号');
            $table->string('name', 64)->default('')->comment('系列名');
            $table->string('code', 40)->default('')->comment('系列编码');
            $table->string('brand_code', 40)->default('')->comment('品牌编码');
            $table->tinyInteger('status')->default(0)->comment('启用状态 0-未启用 1-已启用');
            $table->integer('sort')->default(0)->comment('排序');
            $table->text('remark')->nullable()->default('')->comment('描述');
            $table->char('tenant_id', 6);
            $table->integer('updated_user')->default(0)->comment('最后操作人');
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->dateTimeTz('created_at', 0);
            $table->dateTimeTz('updated_at', 0)->nullable();
            $table->dateTimeTz('deleted_at')->nullable();
        });
        DB::statement("alter table wms_product_series comment '产品系列' ");


        Schema::create('wms_files', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->integer('parent_id')->default(0)->comment('上级的编号');
            $table->tinyInteger('type')->default(0)->comment('类型 0-文件夹 1-文件');
            $table->tinyInteger('level')->default(0)->comment('层级');
            $table->string('name',100)->default('')->comment('名称');
            $table->string('path',255)->default('')->comment('路径');
            $table->integer('file_size')->default(0)->comment('文件大小');
            $table->string('file_path',100)->default('')->comment('文件路径');
            $table->char('tenant_id', 6);
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->integer('updated_user')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at', 0);
            $table->dateTimeTz('updated_at', 0)->nullable();
            $table->dateTimeTz('deleted_at')->nullable();
        });
        DB::statement("alter table wms_files comment '文件' ");

        // 销售发货明细汇总
        Schema::create('wms_order_deliver_statements', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('origin_code', 40)->default('')->comment('订单编码');
            $table->string('third_no', 50)->default('')->comment('电商单号');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->string('shop_code', 100)->default('')->comment('店铺');
            $table->string('shop_name', 50)->default('')->comment('店铺');
            $table->string('warehouse_code', 30)->comment('仓库编码');
            $table->string('warehouse_name', 30)->comment('仓库');
            $table->integer('batch_no')->default(0)->comment('批次号');
            $table->dateTimeTz('order_at')->nullable()->comment('下单时间');
            $table->dateTimeTz('payment_at')->nullable()->comment('支付时间');
            $table->dateTimeTz('shipped_at')->nullable()->comment('发货时间');
            $table->string('category_code', 40)->default('')->comment('产品分类编码');
            $table->string('category_name', 40)->default('')->comment('产品分类');
            $table->string('brand_code', 40)->default('')->comment('品牌编码');
            $table->string('brand_name', 40)->default('')->comment('品牌名称');
            $table->string('sku', 40)->default('')->comment('SKU编码');
            $table->string('product_sn', 20)->default('')->comment('货号');
            $table->string('name', 200)->default('')->comment('品名');
            $table->string('spec_one', 20)->default('')->comment('规格');
            $table->integer('num')->default(0)->comment('数量');
            $table->decimal('retails_price', 10, 2)->default(0)->comment('零售价');
            $table->decimal('price', 10, 2)->default(0)->comment('成交价');
            $table->decimal('amount', 10, 2)->default(0)->comment('成交额');
            $table->decimal('discount_amount', 10, 2)->default(0)->comment('优惠额');
            $table->decimal('payment_amount', 10, 2)->default(0)->comment('实际支付额');
            $table->decimal('cost_amount', 10, 2)->default(0)->comment('成本额');
            $table->decimal('gross_profit', 10, 2)->default(0)->comment('毛利');
            $table->float('gross_profit_rate')->default(0)->comment('毛利率');
            $table->decimal('freight', 10, 2)->default(0)->comment('运费');
            $table->tinyInteger('product_type')->default(0)->comment('产品类型 0:实物 1：虚拟 2：赠品 3：附属品 4：其他');
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->string('sup_name', 20)->default('')->comment('供应商');
            $table->tinyInteger('inventory_type')->default(0)->comment('库存类型 1-自营 2-寄卖');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->char('quality_level', 5)->default('')->comment('质量等级');
            $table->string('uniq_code', 20)->default('')->comment('唯一码');
            $table->string('company_code', 30)->default('')->comment('物流公司编码');
            $table->string('company_name', 30)->default('')->comment('物流公司');
            $table->string('deliver_no', 500)->default('')->comment('物流单号');
            $table->string('deliver_path', 255)->default('')->comment('三方物流单地址');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('更新人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_order_deliver_statements comment '销售发货明细汇总'");

        // 采购明细
        Schema::create('wms_purchase_statements', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('buy_code', 40)->default('')->comment('采购单编码');
            $table->dateTimeTz('audit_at')->nullable()->comment('审核时间');
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->string('sup_name', 20)->default('')->comment('供应商');
            $table->string('warehouse_code', 30)->comment('仓库编码');
            $table->string('warehouse_name', 30)->comment('仓库');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->string('sku', 20)->default('')->comment('SKU编码');
            $table->string('product_sn', 20)->default('')->comment('货号');
            $table->string('name', 20)->default('')->comment('品名');
            $table->string('spec_one', 20)->default('')->comment('规格');
            $table->integer('num')->default(0)->comment('总数量');
            $table->integer('recv_num')->default(0)->comment('已收总数');
            $table->float('recv_rate')->default(0)->comment('收获率');
            $table->integer('normal_num')->default(0)->comment('正品数');
            $table->integer('flaw_num')->default(0)->comment('瑕疵数');
            $table->float('flaw_rate')->default(0)->comment('瑕疵率');
            $table->decimal('purchase_price', 10, 2)->default(0)->comment('采购价');
            $table->decimal('purchase_amount', 10, 2)->default(0)->comment('采购总额');
            $table->tinyInteger('inv_type')->default(0)->comment('库存类型 0-自营 1-寄卖');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at')->nullable();
            $table->dateTimeTz('updated_at')->nullable();
        });
        DB::statement("alter table wms_purchase_statements comment '采购汇总' ");

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wms_supplier_documents');
        Schema::dropIfExists('wms_product_series');
        Schema::dropIfExists('wms_files');
        Schema::dropIfExists('wms_order_deliver_statements');
        Schema::dropIfExists('wms_purchase_statements');
    }
}
