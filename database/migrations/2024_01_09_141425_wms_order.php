<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WmsOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //店铺
        Schema::create('wms_shops', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('code', 40)->default('')->comment('单据编码')->index('code');
            $table->string('name', 100)->default('')->comment('店铺名称')->index('name');
            $table->tinyInteger('sale_channel')->default(0)->comment('销售渠道 1-其他 2-得物跨境');
            $table->integer('manager_id')->default(0)->comment('店铺负责人id');
            $table->string('product_code', 30)->notNull()->default('')->comment('物流产品编码');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->tinyInteger('status')->default(0)->comment('状态 0-未启用 1-已启用');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('create_user_id')->default(0)->comment('创建人');
            $table->integer('admin_user_id')->default(0)->comment('更新人');
            $table->dateTimeTz('created_at')->comment('创建时间');
            $table->dateTimeTz('updated_at')->comment('更新时间');
            $table->dateTimeTz('deleted_at')->nullable();
        });
        DB::statement("alter table wms_shops comment '店铺'");

        // 销售订单
        Schema::create('wms_orders', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('type')->default(0)->comment('单据类型 1-销售订单');
            $table->string('code', 40)->default('')->comment('单据编码')->index('code');
            $table->string('shop_code', 40)->default('')->comment('店铺编码')->index('shop_code');
            $table->string('shop_name', 30)->default('')->comment('店铺名');
            $table->tinyInteger('order_platform')->default(0)->comment('来源平台');
            $table->string('warehouse_name', 50)->default('')->comment('仓库名称');
            $table->string('warehouse_code', 30)->comment('仓库编码')->index('warehouse_code');
            $table->tinyInteger('source_type')->default(0)->comment('订单类型 1-手工订单');
            $table->tinyInteger('status')->default(0)->comment('单据状态:0-暂存 1-审核中 2-审核通过 3-审核拒绝 4-已撤销 5-已暂停 6-已取消');
            $table->tinyInteger('deliver_status')->default(0)->comment('发货状态 0-待发货 1-发货中 2-部分发货 3-已发货 4-已取消');
            $table->tinyInteger('payment_status')->default(0)->comment('支付状态 0-未支付 1-已支付');
            $table->tinyInteger('num')->default(0)->comment('商品数量');
            $table->decimal('total_amount', 10, 2)->default(0)->comment('订单总额');
            $table->decimal('payment_amount', 10, 2)->default(0)->comment('实际支付总额');
            $table->decimal('discount_amount', 10, 2)->default(0)->comment('优惠总额');
            $table->string('third_no', 50)->default('')->comment('电商单号')->index('third_no');
            $table->string('product_code', 30)->notNull()->default('')->comment('物流产品编码');
            $table->string('product_name', 50)->notNull()->default('')->comment('物流产品名称');
            $table->string('deliver_no', 500)->default('')->comment('物流单号');
            $table->string('deliver_path', 255)->default('')->comment('三方物流单地址');
            $table->decimal('deliver_fee', 10, 2)->default(0)->comment('物流费用');
            $table->string('buyer_account', 30)->default('')->comment('买家账号');
            $table->integer('order_user')->default(0)->comment('下单人');
            $table->dateTimeTz('order_at')->nullable()->comment('下单时间');
            $table->dateTimeTz('paysuccess_time')->nullable()->comment('支付成功时间');
            $table->dateTimeTz('estimate_sendout_time')->nullable()->comment('预计发货时间');
            $table->string('seller_message', 2000)->default('')->comment('卖家留言');
            $table->string('buyer_message', 2000)->default('')->comment('买家留言');
            $table->string('tag', 50)->default('')->comment('标记');
            $table->integer('suspender_id')->default(0)->comment('暂停人');
            $table->dateTimeTz('paused_at')->nullable()->comment('暂停时间');
            $table->string('paused_reason', 2000)->default('')->comment('暂停原因');
            $table->tinyInteger('old_status')->default(0)->comment('暂停前的状态');
            $table->integer('recovery_operator_id')->default(0)->comment('恢复人');
            $table->dateTimeTz('recovery_at')->nullable()->comment('恢复时间');
            $table->string('recovery_reason', 2000)->default('')->comment('恢复原因');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->tinyInteger('flag')->default(0)->comment('旗帜 0-无 1-红旗 2-黄旗 3-绿旗 4-蓝旗 5-紫旗 6-粉旗 7-黑旗');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('create_user_id')->default(0)->comment('创建人');
            $table->integer('admin_user_id')->default(0)->comment('更新人');
            $table->dateTimeTz('created_at')->comment('创建时间');
            $table->dateTimeTz('updated_at')->comment('更新时间');
            $table->dateTimeTz('deleted_at')->nullable();
        });
        DB::statement("alter table wms_orders comment '销售订单'");

        // 订单支付明细
        Schema::create('wms_order_payments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('origin_code', 40)->default('')->comment('订单编码')->index('origin_code');
            $table->string('pay_no', 50)->default('')->comment('支付单号');
            $table->tinyInteger('status')->default(0)->comment('支付状态:0-待支付 1-支付成功 2-支付失败');
            $table->integer('paysuccess_time')->default(0)->comment('支付时间');
            $table->tinyInteger('type')->default(0)->comment('支付方式');
            $table->decimal('amount', 10, 2)->default(0)->comment('支付金额');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('更新人');
            $table->dateTimeTz('created_at')->comment('创建时间');
            $table->dateTimeTz('updated_at')->comment('更新时间');
        });
        DB::statement("alter table wms_order_payments comment '订单支付详情'");

        // 订单明细
        Schema::create('wms_order_details', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('origin_code', 40)->default('')->comment('订单编码')->index('origin_code');
            $table->string('third_no', 50)->default('')->comment('电商单号');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->string('uniq_code', 20)->default('')->comment('唯一码');
            $table->integer('batch_no')->default(0)->comment('批次号');
            $table->integer('num')->default(0)->comment('数量');
            $table->integer('apply_num')->default(0)->comment('申请售后的数量');
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->string('warehouse_code', 30)->default('')->comment('仓库编码')->index('warehouse_code');
            $table->integer('oversold_num')->default(0)->comment('超卖数量');
            $table->integer('sendout_num')->default(0)->comment('发货数量');
            $table->integer('refund_num')->default(0)->comment('发货前退款数量');
            $table->integer('sendout_refund_num')->default(0)->comment('发货后仅退款数量');
            $table->integer('return_num')->default(0)->comment('退货数量');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->char('quality_level', 5)->default('')->comment('质量等级');
            $table->decimal('cost_price', 10, 2)->default(0)->comment('成本价');
            $table->decimal('retails_price', 10, 2)->default(0)->comment('零售价');
            $table->decimal('price', 10, 2)->default(0)->comment('成交价');
            $table->decimal('amount', 10, 2)->default(0)->comment('实际成交额');
            $table->decimal('payment_amount', 10, 2)->default(0)->comment('实际支付额');
            $table->decimal('discount_amount', 10, 2)->default(0)->comment('优惠额');
            $table->tinyInteger('status')->default(0)->comment('状态:0-已取消 1-正常');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->string('lock_ids', 255)->default('')->comment('锁定的库存id');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('更新人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_order_details comment '销售订单详情'");

        Schema::create('wms_order_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('origin_code', 40)->default('')->comment('订单编码')->index('origin_code');
            $table->integer('detail_id')->default(0)->comment('订单详情id');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->string('uniq_code', 20)->default('')->comment('唯一码');
            $table->integer('batch_no')->default(0)->comment('批次号');
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->string('warehouse_code', 30)->default('')->comment('仓库编码')->index('warehouse_code');
            $table->char('quality_level', 5)->default('')->comment('质量等级');
            $table->decimal('cost_price', 10, 2)->default(0)->comment('成本价');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('更新人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_order_items comment '销售订单发货明细'");


        // 销售结算单
        Schema::create('wms_order_statements', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('status')->default(0)->comment('结算状态 0-待结算 1-已结算');
            $table->string('code', 40)->default('')->comment('单据编码')->index('code');
            $table->tinyInteger('type')->default(0)->comment('单据类型 1-销售订单 2-售后订单');
            $table->string('origin_code', 40)->default('')->comment('业务单据编码');
            $table->dateTime('order_at')->nullable(true)->comment('下单时间');
            $table->dateTime('amount_time')->nullable(true)->comment('收退款时间');
            $table->string('third_no', 50)->default('')->comment('电商单号');
            $table->decimal('amount', 10, 2)->default(0)->comment('总金额');
            $table->decimal('settle_amount', 10, 2)->default(0)->comment('应结算金额');
            $table->decimal('settled_amount', 10, 2)->default(0)->comment('已结算金额');
            $table->dateTime('settled_time')->nullable(true)->comment('结算时间');
            $table->integer('settled_user_id')->default(0)->comment('结算人');
            $table->string('shop_name', 30)->default('')->comment('店铺名');
            $table->string('buyer_account', 30)->default('')->comment('买家账号');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('create_user_id')->default(0)->comment('创建人');
            $table->integer('admin_user_id')->default(0)->comment('更新人');
            $table->dateTimeTz('created_at')->comment('创建时间');
            $table->dateTimeTz('updated_at')->comment('更新时间');
            $table->dateTimeTz('deleted_at')->nullable();
        });
        DB::statement("alter table wms_order_statements comment '销售结算单'");

        // 售后工单
        Schema::create('wms_after_sale_orders', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('code', 40)->default('')->comment('单据编码')->unique('code');
            $table->tinyInteger('source_type')->default(0)->comment('单据来源 1-手工创建');
            $table->tinyInteger('type')->default(0)->comment('单据类型 1-仅退款 2-退货退款');
            $table->tinyInteger('ib_status')->default(0)->comment('入库状态 0-初始状态 1-发货追回 2-退货入库');
            $table->tinyInteger('status')->default(0)->comment('单据状态:0-暂存 1-审核中 2-已审核  4-已确认 5-已取消 6-已驳回');
            $table->integer('apply_num')->default(0)->comment('申请数量');
            $table->integer('return_num')->default(0)->comment('退货总数');
            $table->decimal('refund_amount', 10, 2)->default(0)->comment('退款总额');
            $table->tinyInteger('return_status')->default(0)->comment('退货状态 0-无需退货 1-未收货 2-已收货');
            $table->tinyInteger('refund_status')->default(0)->comment('退款状态 0-待退款 1-已退款');
            $table->string('origin_code', 40)->default('')->comment('销售单据编码');
            $table->string('apply_no', 50)->default('')->comment('申请单号');
            $table->string('refund_reason', 100)->default('')->comment('退换原因');
            $table->dateTimeTz('deadline')->nullable()->comment('退款超时时间');
            $table->dateTimeTz('audit_time')->nullable()->comment('审核时间');
            // $table->integer('deadline')->default(0)->comment('退款超时时间');
            // $table->integer('audit_time')->default(0)->comment('审核时间');
            $table->integer('audit_user_id')->default(0)->comment('审核人');
            $table->dateTimeTz('refund_time')->nullable()->comment('确认退款时间');
            // $table->integer('refund_time')->default(0)->comment('确认退款时间');
            $table->integer('refund_user_id')->default(0)->comment('确认退款人');
            $table->string('warehouse_code', 30)->comment('退货仓库编码');
            $table->string('product_code', 40)->notNull()->default('')->comment('物流产品编码');
            $table->string('deliver_no', 500)->default('')->comment('物流单号');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->integer('order_user')->default(0)->comment('申请退款人');
            // $table->integer('order_at')->default(0)->comment('申请退款时间');
            $table->dateTimeTz('order_at')->nullable()->comment('申请退款时间');

            $table->char('tenant_id', 6)->default('0');
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->integer('admin_user_id')->default(0)->comment('更新人');
            $table->dateTimeTz('created_at')->comment('创建时间')->nullable();
            $table->dateTimeTz('updated_at')->comment('更新时间')->nullable();
            $table->dateTimeTz('deleted_at')->nullable();
        });
        DB::statement("alter table wms_after_sale_orders comment '售后工单'");


        // 售后工单详情
        Schema::create('wms_after_sale_order_details', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('origin_code', 40)->default('')->comment('售后编号')->index('origin_code');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->integer('num')->default(0)->comment('申请数量');
            $table->integer('return_num')->default(0)->comment('退货数量');
            $table->integer('refund_num')->default(0)->comment('仅退款数量');
            $table->decimal('retails_price', 10, 2)->default(0)->comment('零售价');
            $table->decimal('price', 10, 2)->default(0)->comment('成交价');
            $table->decimal('amount', 10, 2)->default(0)->comment('实际成交额');
            $table->decimal('refund_amount', 10, 2)->default(0)->comment('退款额');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->integer('order_detail_id')->default(0)->comment('销售单明细id');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('更新人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_after_sale_order_details comment '售后工单详情'");


        //退货单
        // Schema::create('wms_return_order', function (Blueprint $table) {
        //     $table->engine = 'InnoDB';
        //     $table->id()->autoIncrement();
        //     // $table->tinyInteger('status')->default(0)->comment('单据状态:0-暂存 1-审核中 2-已审核  4-已确认 5-已取消 6-已驳回');
        //     $table->string('code', 40)->default('')->comment('单据编码')->unique();
        //     $table->tinyInteger('type')->default(0)->comment('单据类型 1-仅退款 2-退货退款');
        //     $table->string('warehouse_code', 30)->comment('退货仓库编码');
        //     $table->string('log_prod_code', 40)->notNull()->default('')->comment('物流产品编码');
        //     $table->string('deliver_no', 500)->default('')->comment('物流单号');
        //     $table->string('origin_code', 40)->default('')->comment('售后单号');
        //     $table->integer('num')->default(0)->comment('入库数量');
        //     $table->integer('recover_num')->default(0)->comment('追回数量');
        //     $table->string('return_no', 50)->default('')->comment('退款单号');
        //     $table->decimal('amount', 10, 2)->default(0)->comment('退款金额');
        //     $table->tinyInteger('refund_status')->default(0)->comment('退款状态 0-待退款 1-已退款');
        //     $table->char('tenant_id', 6)->default('0');
        //     $table->integer('create_user_id')->default(0)->comment('创建人');
        //     $table->integer('admin_user_id')->default(0)->comment('更新人');
        //     $table->dateTimeTz('created_at')->comment('创建时间');
        //     $table->dateTimeTz('updated_at')->comment('更新时间')->nullable();
        // });
        // DB::statement("alter table wms_return_order comment '退货单'");

      
         // 采购单
        Schema::create('wms_purchase_orders', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('status')->default(0)->comment("单据状态:0-暂存 1-审核中 2-已审核  4-已确认 5-已取消 6-已驳回");
            $table->tinyInteger('receive_status')->default(0)->comment('收货状态 0-待收货 1-已收货 2-部分收货');
            $table->string('code', 40)->default('')->comment('单据编码')->unique();
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->string('warehouse_code', 30)->comment('仓库编码')->index('warehouse_code');
            $table->tinyInteger('source_type')->default(0)->comment('数据来源 1-手工创建');
            $table->string('third_code', 40)->default('')->comment('第三方单据编码')->index('third_code');
            $table->string('pre_amount', 40)->default('')->comment('预付款');
            $table->integer('order_user')->default(0)->comment('下单人');
            $table->dateTimeTz('order_at', 0)->nullable()->comment('下单时间');
            $table->tinyInteger('pay_status')->default(0)->comment('付款状态 0-未付款 1-已付款');
            $table->integer('num')->default(0)->comment('总数量');
            $table->decimal('amount', 10, 2)->default(0)->comment('采购总额');
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
        DB::statement("alter table wms_purchase_orders comment '采购单'");

        // 采购明细
        Schema::create('wms_purchase_details', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('buy_code', 40)->default('')->comment('采购单编码')->index('buy_code');
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
        DB::statement("alter table wms_purchase_details comment '采购明细' ");

        // 采购结算单
        Schema::create('wms_purchase_order_statements', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('status')->default(0)->comment('结算状态 0-待结算 1-已结算');
            $table->string('code', 40)->default('')->comment('单据编码')->unique();
            $table->string('origin_code', 40)->default('')->comment('采购单据编码')->index('origin_code');
            $table->integer('order_user')->default(0)->comment('下单人');
            $table->dateTimeTz('order_at')->comment('下单时间')->nullable();
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->string('warehouse_code', 30)->comment('仓库编码')->index('warehouse_code');
            $table->integer('num')->default(0)->comment('采购总数');
            $table->decimal('amount', 10, 2)->default(0)->comment('采购总额');
            $table->decimal('settle_amount', 10, 2)->default(0)->comment('应结算总额');
            $table->decimal('settled_amount', 10, 2)->default(0)->comment('已结算总额');
            $table->dateTimeTz('settled_time')->comment('结算时间')->nullable();
            $table->integer('settled_user')->default(0)->comment('结算人');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->integer('updated_user')->default(0)->comment('更新人');
            $table->dateTimeTz('created_at')->comment('创建时间')->nullable();
            $table->dateTimeTz('updated_at')->comment('更新时间')->nullable();
        });
        DB::statement("alter table wms_purchase_order_statements comment '采购结算单'");

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wms_shops');
        Schema::dropIfExists('wms_orders');
        Schema::dropIfExists('wms_order_payments');
        Schema::dropIfExists('wms_order_details');
        Schema::dropIfExists('wms_order_items');
        Schema::dropIfExists('wms_order_statements');
        Schema::dropIfExists('wms_after_sale_orders');
        Schema::dropIfExists('wms_after_sale_order_details');
        Schema::dropIfExists('wms_purchase_orders');
        Schema::dropIfExists('wms_purchase_details');
        Schema::dropIfExists('wms_purchase_order_statements');
    }
}
