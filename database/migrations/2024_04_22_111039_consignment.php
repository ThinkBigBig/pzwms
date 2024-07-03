<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class Consignment extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 寄卖单
        Schema::create('wms_consignment_orders', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('status')->default(0)->comment("单据状态:0-暂存 1-审核中 2-已审核  4-已确认 5-已取消 6-已驳回");
            $table->tinyInteger('receive_status')->default(0)->comment('收货状态 0-待收货 1-已收货');
            $table->string('code', 40)->default('')->comment('单据编码')->unique();
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->string('warehouse_code', 30)->comment('仓库编码')->index('warehouse_code');
            $table->tinyInteger('source_type')->default(0)->comment('数据来源 1-手工创建');
            $table->string('third_code', 40)->default('')->comment('第三方单据编码')->index('third_code');
            $table->integer('order_user')->default(0)->comment('下单人');
            $table->dateTimeTz('order_at', 0)->nullable()->comment('下单时间');
            $table->integer('num')->default(0)->comment('总数量');
            $table->decimal('amount', 10, 2)->default(0)->comment('成本总额');
            $table->integer('received_num')->default(0)->comment('已收总数');
            $table->dateTimeTz('estimate_receive_at', 0)->nullable()->comment('预计到货时间');
            $table->dateTimeTz('send_at', 0)->nullable()->comment('发货日期');
            $table->string('log_prod_code', 40)->notNull()->default('')->comment('物流产品编码');
            $table->string('deliver_no', 500)->default('')->comment('物流单号');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->integer('approve_id')->default(0)->comment('审核人');
            $table->string('approve_reason', 2000)->default('')->comment('审核备注');
            $table->dateTimeTz('audit_at')->nullable()->comment('审核时间');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->integer('updated_user')->default(0)->comment('更新人');
            $table->dateTimeTz('created_at')->comment('创建时间')->nullable();
            $table->dateTimeTz('updated_at')->comment('更新时间')->nullable();
            $table->dateTimeTz('deleted_at')->nullable();
        });
        DB::statement("alter table wms_consignment_orders comment '寄卖单'");

        // 寄卖明细
        Schema::create('wms_consignment_details', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('origin_code', 40)->default('')->comment('寄卖单编码')->index('origin_code');
            $table->decimal('buy_price', 10, 2)->default(0)->comment('成本价');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->integer('num')->default(0)->comment('数量');
            $table->integer('recv_num')->default(0)->comment('已收数量');
            $table->integer('normal_count')->default(0)->comment('实收正品');
            $table->integer('flaw_count')->default(0)->comment('实收瑕疵');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at')->nullable();
            $table->dateTimeTz('updated_at')->nullable();
        });
        DB::statement("alter table wms_consignment_details comment '寄卖明细' ");

        // 寄卖结算账单
        Schema::create('wms_consignment_settlement', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('origin_code', 40)->default('')->comment('编码');
            $table->tinyInteger('status')->default(0)->comment("单据类型:1-已确认 2-已审核");
            $table->tinyInteger('type')->default(0)->comment("单据类型:0-电商订单 1-手工订单 2-退货退款 3-仅退款 4-退换货");
            $table->tinyInteger('stattlement_status')->default(0)->comment("结算状态:0-待结算 1-待提现 2-提现中 3-已提现");
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->string('sup_name', 100)->default('')->comment('供应商名称');
            $table->dateTimeTz('confirm_at', 0)->nullable()->comment('确认时间');
            $table->string('third_code', 40)->default('')->comment('第三方单据编码');
            $table->dateTimeTz('order_at', 0)->nullable()->comment('下单时间');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->string('spec_one', 100)->default('')->comment('规格');
            $table->string('product_sn', 100)->default('')->comment('货号');
            $table->string('product_name', 100)->default('')->comment('品名');
            $table->tinyInteger('quality_type')->default(0)->comment("质量类型 1-正品 2-疑似瑕疵");
            $table->string('quality_level', 5)->default('')->comment('质量等级');
            $table->integer('num')->default(0)->comment('数量');
            $table->decimal('bid_price', 10, 2)->default(0)->comment('出价');
            $table->decimal('actual_deal_price', 10, 2)->default(0)->comment('实际成交价');
            $table->decimal('deal_price', 10, 2)->default(0)->comment('成交价');
            $table->decimal('retail_price', 10, 2)->default(0)->comment('零售价');
            $table->decimal('payment_amount', 10, 2)->default(0)->comment('实际支付金额');
            $table->decimal('subsidy_amount', 10, 2)->default(0)->comment('平台补贴');
            $table->decimal('bid_amount', 10, 2)->default(0)->comment('出价总额');
            $table->decimal('actual_deal_amount', 10, 2)->default(0)->comment('实际成交总额');
            $table->decimal('deal_amount', 10, 2)->default(0)->comment('成交总额');
            $table->decimal('stattlement_amount', 10, 2)->default(0)->comment('结算总额');
            $table->decimal('retail_amount', 10, 2)->default(0)->comment('结算总额');
            $table->string('rule_code', 40)->default('')->comment('结算规则编码');
            $table->string('rule_name', 255)->default('')->comment('结算规则名称');
            $table->string('send_warehouse_name', 255)->default('')->comment('发货仓库名');
            $table->string('return_warehouse_name', 255)->default('')->comment('退货仓库名');
            $table->string('shop_name', 255)->default('')->comment('店铺名');
            $table->integer('order_channel')->default(0)->comment('来源渠道');
            $table->string('buyer_account', 30)->default('')->comment('买家账号');
            $table->integer('sku_total')->default(0)->comment('订单sku总数');
            $table->dateTimeTz('action_at', 0)->nullable()->comment('收发货时间');
            $table->dateTimeTz('settlement_at', 0)->nullable()->comment('结算时间');
            $table->string('apply_code', 40)->default('')->comment('提现申请单编码');
            $table->dateTimeTz('apply_at', 0)->nullable()->comment('提现申请时间');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->integer('updated_user')->default(0)->comment('更新人');
            $table->dateTimeTz('created_at')->nullable();
            $table->dateTimeTz('updated_at')->nullable();
        });
        DB::statement("alter table wms_consignment_settlement comment '寄卖结算账单' ");

        // 寄卖规则
        Schema::create('wms_consignment_settlement_rules', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('code', 40)->default('')->comment('编码');
            $table->string('category_code', 40)->default('')->comment('分类编码');
            $table->tinyInteger('status')->default(0)->comment("单据状态:0-禁用 1-启用");
            $table->integer('sort')->default(0)->comment('优先级');
            $table->string('name', 255)->default('')->comment('规则名称');
            $table->tinyInteger('object')->default(0)->comment("结算对象:1-销售 2-售后");
            $table->dateTimeTz('start_at')->nullable()->comment('生效时间');
            $table->dateTimeTz('end_at')->nullable()->comment('失效时间');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->string('formula', 2000)->nullable(false)->comment('公式');
            $table->string('content', 2000)->nullable(false)->comment('条件配置');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->integer('updated_user')->default(0)->comment('更新人');
            $table->dateTimeTz('created_at')->nullable();
            $table->dateTimeTz('updated_at')->nullable();
        });
        DB::statement("alter table wms_consignment_settlement_rules comment '寄卖结算规则' ");

        // 寄卖结算规则分类
        Schema::create('wms_consignment_settlement_category', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('code', 40)->default('')->comment('编码');
            $table->integer('parent_id')->default(0)->comment('上级id');
            $table->string('name', 255)->default('')->comment('分类名称');
            $table->integer('sort')->default(0)->comment('优先级');
            $table->integer('level')->default(0)->comment('当前层级');
            $table->string('path', 255)->default('')->comment('路径');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->integer('updated_user')->default(0)->comment('更新人');
            $table->dateTimeTz('created_at')->nullable();
            $table->dateTimeTz('updated_at')->nullable();
        });
        DB::statement("alter table wms_consignment_settlement_category comment '寄卖结算规则分类' ");

        // 提现申请
        Schema::create('wms_withdraw_request', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('code', 40)->default('')->comment('编码');
            $table->tinyInteger('status')->default(0)->comment("单据类型:0-待审核 1-已审核");
            $table->tinyInteger('type')->default(0)->comment("单据类型:1-提现申请单");
            $table->tinyInteger('source')->default(0)->comment("单据来源:1-手工创建");
            $table->dateTimeTz('apply_at', 0)->nullable()->comment('申请时间');
            $table->dateTimeTz('audit_at', 0)->nullable()->comment('审核时间');
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->string('sup_name', 100)->default('')->comment('供应商名称');
            $table->integer('total')->default(0)->comment('总数量');
            $table->decimal('amount', 10, 2)->default(0)->comment('结算总额');
            $table->integer('order_user')->default(0)->comment('下单人');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->integer('updated_user')->default(0)->comment('更新人');
            $table->dateTimeTz('created_at')->nullable();
            $table->dateTimeTz('updated_at')->nullable();
        });
        DB::statement("alter table wms_withdraw_request comment '提现申请单' ");

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::dropIfExists('wms_consignment_orders');
        Schema::dropIfExists('wms_consignment_details');
        Schema::dropIfExists('wms_consignment_settlement');
        Schema::dropIfExists('wms_consignment_settlement_rules');
        Schema::dropIfExists('wms_consignment_settlement_category');
        Schema::dropIfExists('wms_withdraw_request');
    }
}
