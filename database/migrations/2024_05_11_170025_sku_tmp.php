<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SkuTmp extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('wms_recv_order', function (Blueprint $table) {
            $table->string('arr_code', 30)->default('')->comment('到货登记单编码')->after('arr_id');
        });
        Schema::table('wms_recv_detail', function (Blueprint $table) {
            $table->string('recv_code', 30)->default('')->comment('收货单编码')->after('recv_id');
        });

        // Schema::table('wms_after_sale_order_details', function (Blueprint $table) {
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        // });
        // Schema::table('wms_consignment_details', function (Blueprint $table) {
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        // });
        // Schema::table('wms_shipping_detail', function (Blueprint $table) {
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        // });
        // Schema::table('wms_pre_allocation_detail', function (Blueprint $table) {
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        // });
        // Schema::create('wms_shipping_cancel_detail', function (Blueprint $table) {
        //     $table->engine = 'InnoDB';
        //     $table->id()->autoIncrement();
        //     $table->string('origin_code', 40)->default('')->comment('单据编码');
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        //     $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
        //     $table->string('quality_level', 5)->default('')->comment('质量等级');
        //     $table->decimal('retail_price', 10, 2)->default(0)->comment('零售价');
        //     $table->decimal('actual_deal_price', 10, 2)->default(0)->comment('实际成交价');
        //     $table->decimal('discount_amount', 10, 2)->default(0)->comment('产品折扣');
        //     $table->integer('cancel_num')->default(0)->comment('应取消数量');
        //     $table->integer('canceled_num')->default(0)->comment('已取消数量');
        //     $table->integer('putaway_num')->default(0)->comment('已上架数量');
        //     $table->integer('wait_putaway_num')->default(0)->comment('待上架数量');
        //     $table->string('remark', 2000)->default('')->comment('备注');
        //     $table->char('tenant_id', 6)->default('0');
        //     $table->integer('admin_user_id')->default(0)->comment('最后操作人');
        //     $table->dateTimeTz('created_at');
        //     $table->dateTimeTz('updated_at');
        // });
        // DB::statement("alter table wms_shipping_cancel_detail comment '出库取消单详情' ");

        // Schema::table('wms_transfer_details', function (Blueprint $table) {
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        // });
        // Schema::table('wms_other_ib_details', function (Blueprint $table) {
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        // });
        // Schema::table('wms_other_ob_details', function (Blueprint $table) {
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        // });
        // Schema::table('wms_putaway_detail', function (Blueprint $table) {
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        // });
        // Schema::table('wms_quality_detail', function (Blueprint $table) {
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        // });
        // Schema::table('wms_quality_confirm_list', function (Blueprint $table) {
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        // });

        // Schema::table('wms_stock_move_items', function (Blueprint $table) {
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        // });
        // Schema::table('wms_stock_move_details', function (Blueprint $table) {
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        //     $table->integer('sup_id')->default(0)->comment('供应商id');
        // });
        // Schema::table('wms_stock_check_details', function (Blueprint $table) {
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        // });
        // Schema::table('wms_stock_check_differences', function (Blueprint $table) {
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        // });
        // Schema::table('wms_purchase_details', function (Blueprint $table) {
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        // });
        // Schema::table('wms_inv_goods_detail', function (Blueprint $table) {
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        // });
        // Schema::table('wms_recv_detail', function (Blueprint $table) {
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        // });
        // Schema::table('wms_stock_logs', function (Blueprint $table) {
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        // });
        // Schema::table('wms_withdraw_uniq_log', function (Blueprint $table) {
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        // });
        // Schema::table('wms_stock_check_request_details', function (Blueprint $table) {
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        // });
        
        // 产品库存流水表
        // Schema::create('wms_product_stock_logs', function (Blueprint $table) {
        //     $table->engine = 'InnoDB';
        //     $table->id()->autoIncrement();
        //     // $table->bigInteger('id');
        //     $table->tinyInteger('node')->default(0)->comment('节点');
        //     $table->tinyInteger('type')->default(0)->comment('单据类型');
        //     $table->string('source_code', 40)->default('')->comment('单据编码')->index('source_code');
        //     $table->integer('sup_id')->default(0)->comment('供应商id');
        //     $table->string('sup_name', 50)->default('')->comment('供应商名称');
        //     $table->tinyInteger('inv_type')->default(0)->comment('库存类型 0-自营 1-寄卖');
        //     $table->string('sku', 100)->default('')->comment('sku编码');
        //     $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
        //     $table->char('quality_level', 5)->default('')->comment('质量等级');
        //     $table->string('uniq_code', 20)->default('')->comment('唯一码');
        //     $table->integer('batch_no')->default(0)->comment('批次号');
        //     $table->tinyInteger('inv_category')->default(0)->comment('库存类别');
        //     $table->integer('old_num')->default(0)->comment('起初库存');
        //     $table->integer('change_num')->default(0)->comment('库存流动');
        //     $table->integer('new_num')->default(0)->comment('结余库存');
        //     $table->decimal('cost_amount',10,2)->default(0)->comment('结余库存成本额');
        //     $table->decimal('cost_price',10,2)->default(0)->comment('成本价');
        //     $table->decimal('weighted_cost_price',10,2)->default(0)->comment('加权成本价');
        //     $table->string('origin_type', 100)->default('')->comment('来源单据类型');
        //     $table->string('origin_code', 40)->default('')->comment('来源单据编码')->index('origin_code');
        //     $table->string('third_no', 40)->default('')->comment('第三方单据编码');
        //     $table->string('remark', 2000)->default('')->comment('备注');
        //     $table->string('ip', 20)->default('')->comment('操作请求IP');
        //     $table->string('warehouse_code', 30)->default('')->comment('仓库编码')->index('warehouse_code');
        //     $table->string('warehouse_name', 50)->default('')->comment('仓库名称');
        //     $table->char('tenant_id', 6)->default('0');
        //     $table->integer('create_user_id')->default(0)->comment('下单人');
        //     $table->integer('admin_user_id')->default(0)->comment('处理人');
        //     $table->date('dateline')->comment('操作日期');
        //     $table->dateTimeTz('created_at')->comment('下单时间');
        //     $table->dateTimeTz('updated_at')->comment('处理时间');
        // });
        // DB::statement("alter table wms_product_stock_logs comment '产品库存流水表'");

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
