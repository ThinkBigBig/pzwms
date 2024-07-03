<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
// use FiveSay\LaravelSchemaExtend\Facade as Schema;

class CreateWmsInvTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 调拨申请单
        Schema::create('wms_transfer_order', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('type')->default(2)->comment('单据类型 2-普通调拨单');
            $table->tinyInteger('doc_status')->default(0)->comment('单据状态:0-暂存 1-审核中 2-已审核 3-暂停 4-已确认 5-已取消 6-已驳回');
            $table->string('tr_code', 30)->default('')->comment('单据编码')->unique('tr_code');
            $table->tinyInteger('send_status')->default(0)->comment('发货状态  1-待发货 2-发货中 3-部分发货 4-已发货 ');
            $table->tinyInteger('recv_status')->default(0)->comment('收货状态  1-待收货 2-部分收货 3-已收货 ');
            $table->string('out_warehouse_code', 30)->default('')->comment('调出仓')->index('out_warehouse_code');
            $table->string('in_warehouse_code', 30)->default('')->comment('调入仓')->index('in_warehouse_code');
            $table->string('source_code', 30)->default('')->comment('单据来源');
            $table->integer('total')->default(0)->comment('总数量');
            $table->integer('send_num')->default(0)->comment('已发总数');
            $table->integer('recv_num')->default(0)->comment('已收总数');
            $table->string('log_prod_code', 20)->default('')->comment('物流产品编号');
            $table->string('deliver_no', 500)->default('')->comment('物流单号');
            $table->integer('paysuccess_user')->default(0)->comment('下单人');
            $table->dateTimeTz('paysuccess_time', 0)->nullable()->comment('下单时间');
            $table->dateTimeTz('delivery_deadline', 0)->nullable()->comment('预计发货时间');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->integer('suspender_id')->default(0)->comment('暂停人');
            $table->dateTime('paused_at')->nullable()->comment('暂停时间');
            $table->string('paused_reason', 2000)->default('')->comment('暂停原因');
            $table->integer('recovery_operator_id')->default(0)->comment('恢复人');
            $table->dateTime('recovery_at')->nullable()->comment('恢复时间');
            $table->string('recovery_reason', 2000)->default('')->comment('恢复原因');
            $table->integer('approve_id')->default(0)->comment('审核人');
            $table->string('approve_reason', 2000)->default('')->comment('审核备注');
            $table->tinyInteger('flag')->default(0)->comment('旗帜 0-无 1-红旗 2-黄旗 3-绿旗 4-蓝旗 5-紫旗 6-粉旗 7-黑旗');
            $table->char('tenant_id', 6);
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->dateTimeTz('created_at', 0)->nullable()->comment('创建时间');
            $table->integer('updated_user')->default(0)->comment('最新操作人');
            $table->dateTimeTz('updated_at', 0)->nullable();
            $table->dateTimeTz('deleted_at')->nullable();
        });
        DB::statement("alter table wms_transfer_order comment '调拨申请单' ");


        // 调拨申请详情
        Schema::create('wms_transfer_details', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('tr_code', 40)->default('')->comment('调拨申请单编码')->index('tr_code');
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->decimal('buy_price', 10, 2)->default(0)->comment('成本价');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->integer('num')->default(0)->comment('数量');
            $table->integer('send_num')->default(0)->comment('已发数量');
            $table->integer('recv_num')->default(0)->comment('已收数量');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->char('quality_level', 5)->default('')->comment('质量等级');
            $table->integer('batch_no')->default(0)->comment('批次号');
            $table->string('uniq_code', 20)->default('')->comment('唯一码');
            $table->string('lock_ids', 255)->default('')->comment('锁定ids');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_transfer_details comment '调拨申请详情' ");

        // 退货/调拨 唯一码记录
        Schema::create('wms_withdraw_uniq_log', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('type')->default(0)->comment('单据类型 1-普通调拨单 2-退货单');
            $table->string('source_code', 40)->default('')->comment('源单编码')->index('source_code');
            $table->tinyInteger('inv_type')->default(0)->comment('库存类型:0-自营 1-寄卖');
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->decimal('buy_price', 10, 2)->default(0)->comment('成本价');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->char('quality_level', 5)->default('')->comment('质量等级');
            $table->integer('batch_no')->default(0)->comment('批次号');
            $table->string('uniq_code', 20)->default('')->comment('唯一码');
            $table->integer('source_details_id')->default(0)->comment('源单明细id');
            $table->tinyInteger('is_scan')->default(0)->comment('是否再次入库 0-未入库 1-已入库');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_withdraw_uniq_log comment '退货/调拨 唯一码记录' ");

        // 其他入库申请单
        Schema::create('wms_other_ib_order', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('type')->default(4)->comment('单据类型 4-其他入库申请单');
            $table->tinyInteger('doc_status')->default(0)->comment('单据状态:0-暂存 1-审核中 2-已审核  4-已确认 5-已取消 6-已驳回');
            $table->string('oib_code', 30)->default('')->comment('单据编码')->unique('oib_code');
            $table->string('third_code', 40)->default('')->comment('第三方单据编码')->index('third_code');
            $table->tinyInteger('recv_status')->default(0)->comment('收货状态  1-待收货 2-部分收货 3-已收货 ');
            $table->string('warehouse_code', 30)->default('')->comment('仓库编码')->index('warehouse_code');
            $table->string('source_code', 30)->default('')->comment('单据来源');
            $table->decimal('sum_buy_price', 10, 2)->default(0)->comment('成本总额');
            $table->integer('total')->default(0)->comment('总数量');
            $table->integer('recv_num')->default(0)->comment('已收总数');
            $table->integer('paysuccess_user')->default(0)->comment('下单人');
            $table->dateTimeTz('paysuccess_time', 0)->nullable()->comment('下单时间');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->integer('approve_id')->default(0)->comment('审核人');
            $table->string('approve_reason', 2000)->default('')->comment('审核备注');
            $table->char('tenant_id', 6);
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->dateTimeTz('created_at', 0)->nullable()->comment('创建时间');
            $table->integer('updated_user')->default(0)->comment('最新操作人');
            $table->dateTimeTz('updated_at', 0)->nullable();
            $table->dateTimeTz('deleted_at')->nullable();
        });
        DB::statement("alter table wms_other_ib_order comment '其他入库申请单' ");

        // 其他入库申请详情
        Schema::create('wms_other_ib_details', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('oib_code', 40)->default('')->comment('其他入库申请单编码')->index('oib_code');
            $table->tinyInteger('inv_type')->default(0)->comment('库存类型:0-自营 1-寄卖');
            $table->integer('sup_id')->default(0)->comment('供应商id');
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
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_other_ib_details comment '其他入库申请详情' ");


        // 其他出库申请单
        Schema::create('wms_other_ob_order', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('type')->default(3)->comment('单据类型 3-其他出库申请单');
            $table->tinyInteger('doc_status')->default(0)->comment('单据状态:0-暂存 1-审核中 2-已审核 3-暂停 4-已确认 5-已取消 6-已驳回');
            $table->string('oob_code', 30)->default('')->comment('单据编码')->unique('oob_code');
            $table->tinyInteger('send_status')->default(0)->comment('发货状态  1-待发货 2-发货中 3-部分发货 4-已发货 ');
            $table->string('warehouse_code', 30)->default('')->comment('仓库编码')->index('warehouse_code');
            $table->string('source_code', 30)->default('')->comment('单据来源');
            $table->integer('total')->default(0)->comment('总数量');
            $table->integer('send_num')->default(0)->comment('已发总数');
            $table->string('log_prod_code', 20)->default('')->comment('物流产品编号');
            $table->string('deliver_no', 500)->default('')->comment('物流单号');
            $table->integer('paysuccess_user')->default(0)->comment('下单人');
            $table->dateTimeTz('paysuccess_time', 0)->nullable()->comment('下单时间');
            $table->dateTimeTz('delivery_deadline', 0)->nullable()->comment('预计发货时间');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->integer('suspender_id')->default(0)->comment('暂停人');
            $table->dateTime('paused_at')->nullable()->comment('暂停时间');
            $table->string('paused_reason', 2000)->default('')->comment('暂停原因');
            $table->integer('recovery_operator_id')->default(0)->comment('恢复人');
            $table->dateTime('recovery_at')->nullable()->comment('恢复时间');
            $table->string('recovery_reason', 2000)->default('')->comment('恢复原因');
            $table->integer('approve_id')->default(0)->comment('审核人');
            $table->string('approve_reason', 2000)->default('')->comment('审核备注');
            $table->char('tenant_id', 6);
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->dateTimeTz('created_at', 0)->nullable()->comment('创建时间');
            $table->integer('updated_user')->default(0)->comment('最新操作人');
            $table->dateTimeTz('updated_at', 0)->nullable();
            $table->dateTimeTz('deleted_at')->nullable();
        });
        DB::statement("alter table wms_other_ob_order comment '其他出库申请单' ");

        // 其他出库申请详情
        Schema::create('wms_other_ob_details', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('oob_code', 40)->default('')->comment('其他出库申请单编码')->index('oob_code');
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->decimal('buy_price', 10, 2)->default(0)->comment('成本价');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->integer('num')->default(0)->comment('数量');
            $table->integer('send_num')->default(0)->comment('已发数量');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->char('quality_level', 5)->default('')->comment('质量等级');
            $table->integer('batch_no')->default(0)->comment('批次号');
            $table->string('uniq_code', 20)->default('')->comment('唯一码');
            $table->string('lock_ids', 255)->default('')->comment('锁定ids');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_other_ob_details comment '其他出库申请详情' ");


        // 盘点单
        Schema::create('wms_stock_check_list', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('type')->default(0)->comment('单据类型 1-动态盘点单');
            $table->string('code', 30)->default('')->comment('单据编码')->index('code');
            $table->string('request_code', 30)->default('')->comment('盘点申请单编码')->index('request_code');
            $table->tinyInteger('status')->default(0)->comment('单据状态:0-暂存 1-审核中 2-审核通过');
            $table->tinyInteger('check_status')->default(0)->comment('单据状态:0-待盘点 1-盘点中 2-已盘点');
            $table->tinyInteger('check_type')->default(0)->comment('盘点类型:0-明盘 1-盲盘');
            $table->string('warehouse_code', 30)->default('')->comment('仓库编码')->index('warehouse_code');
            $table->integer('check_user_id')->default(0)->comment('盘点人');
            $table->dateTimeTz('start_at', 0)->nullable()->comment('开始时间');
            $table->dateTimeTz('end_at', 0)->nullable()->comment('完成时间');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6);
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->dateTimeTz('created_at', 0)->nullable()->comment('下单时间');
            $table->integer('updated_user')->default(0)->comment('最新操作人');
            $table->dateTimeTz('updated_at', 0)->nullable();
            $table->dateTimeTz('deleted_at')->nullable();
        });
        DB::statement("alter table wms_stock_check_list comment '盘点单' ");

        // 盘点单详情
        Schema::create('wms_stock_check_details', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('origin_code', 40)->default('')->comment('盘点单编码')->index('origin_code');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->string('location_code', 30)->default('')->comment('位置码');
            $table->integer('stock_num')->default(0)->comment('架上库存');
            $table->integer('check_num')->default(0)->comment('盘点数量');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->char('quality_level', 5)->default('')->comment('质量等级');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_stock_check_details comment '盘点单明细'");


        // 盘点操作记录
        Schema::create('wms_stock_check_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('origin_code', 40)->default('')->comment('盘点单编码')->index('origin_code');
            $table->string('request_code', 40)->default('')->comment('盘点申请单编码')->index('request_code');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->string('location_code', 30)->default('')->comment('位置码');
            $table->string('uniq_code', 20)->default('')->comment('唯一码');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->char('quality_level', 5)->default('')->comment('质量等级');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at')->comment('盘点时间');
        });
        DB::statement("alter table wms_stock_check_logs comment '盘点单操作记录'");


        // 盘点单申请单
        Schema::create('wms_stock_check_request', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('type')->default(0)->comment('单据类型 1-动态盘点单');
            $table->string('code', 30)->default('')->comment('单据编码')->index('code');
            $table->tinyInteger('status')->default(0)->comment('单据状态:0-暂存 1-审核中 2-审核通过 3-审核拒绝 4-已撤销  5-已下发');
            $table->tinyInteger('check_status')->default(0)->comment('单据状态:0-待盘点 1-盘点中 2-已盘点');
            $table->tinyInteger('check_time')->default(0)->comment('盘点次数');
            $table->string('warehouse_code', 30)->default('')->comment('仓库编码')->index('warehouse_code');
            $table->tinyInteger('source')->default(0)->comment('单据来源：0-手工创建');
            $table->integer('total_num')->default(0)->comment('终盘总数');
            $table->integer('total_diff')->default(0)->comment('终盘总差异');
            $table->integer('report_num')->default(0)->comment('上报总数');
            $table->integer('recover_num')->default(0)->comment('寻回总数');
            $table->integer('current_diff')->default(0)->comment('当前总差异数');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6);
            $table->integer('order_user')->default(0)->comment('下单人');
            $table->integer('order_at')->default(0)->comment('下单时间');
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->dateTimeTz('created_at', 0)->nullable()->comment('创建时间');
            $table->integer('updated_user')->default(0)->comment('最新操作人');
            $table->dateTimeTz('updated_at', 0)->nullable();
            $table->dateTimeTz('deleted_at')->nullable();
        });
        DB::statement("alter table wms_stock_check_request comment '盘点单申请单'");


        // 盘点申请单明细
        Schema::create('wms_stock_check_request_details', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('origin_code', 40)->default('')->comment('盘点单申请单编码')->index('origin_code');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->string('location_code', 30)->default('')->comment('位置码');
            $table->integer('stock_num')->default(0)->comment('架上库存');
            $table->integer('check_num')->default(0)->comment('盘点数量');
            $table->integer('check_time')->default(0)->comment('盘点次数');
            $table->string('last_code', 40)->default('')->comment('终盘单号');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->tinyInteger('status')->default(0)->comment('状态:0-已取消 1-已添加');
            $table->char('quality_level', 5)->default('')->comment('质量等级');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_stock_check_request_details comment '盘点申请单明细'");


        // 盘点申请单差异明细
        Schema::create('wms_stock_check_differences', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('status')->default(0)->comment('状态 0-待处理 1-少货寻回 2-已上报');
            $table->string('origin_code', 40)->default('')->comment('差异处理单编码');
            $table->string('request_code', 40)->default('')->comment('盘点单申请单编码')->index('request_code');
            $table->string('bill_code', 40)->default('')->comment('盘盈亏单编码');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->string('location_code', 30)->default('')->comment('位置码');
            $table->string('uniq_code', 20)->default('')->comment('唯一码');
            $table->integer('batch_no')->default(0)->comment('批次号');
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->tinyInteger('num')->default(0)->comment('差异数量');
            $table->integer('report_num')->default(0)->comment('上报数量');
            $table->integer('recover_num')->default(0)->comment('寻回数量');
            $table->string('recover_uniq_code', 20)->default('')->comment('寻回唯一码');
            $table->string('recover_location_code', 30)->default('')->comment('寻回位置码');
            $table->string('last_code', 40)->default('')->comment('终盘单号');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->char('quality_level', 5)->default('')->comment('质量等级');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->string('note', 255)->default('')->comment('系统处理备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
            $table->dateTimeTz('deleted_at')->nullable();
        });
        DB::statement("alter table wms_stock_check_differences comment '盘点申请单差异明细'");


        // 差异处理记录
        Schema::create('wms_stock_differences', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('type')->default(0)->comment('类型 1-少货');
            $table->string('code', 40)->default('')->comment('单据编码')->index('code');
            $table->tinyInteger('status')->default(0)->comment('状态 0-待处理 1-已处理');
            $table->tinyInteger('origin_type')->default(0)->comment('来源单据类型 1-盘点');
            $table->string('origin_code', 40)->default('')->comment('来源单据编码');
            $table->integer('diff_num')->default(0)->comment('差异总数');
            $table->string('warehouse_code', 30)->default('')->comment('仓库编码');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->integer('order_user')->default(0)->comment('下单人');
            $table->integer('order_at')->default(0)->comment('下单时间');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('create_user_id')->default(0)->comment('下单人');
            $table->integer('admin_user_id')->default(0)->comment('处理人');
            $table->dateTimeTz('created_at')->comment('下单时间');
            $table->dateTimeTz('updated_at')->comment('处理时间');
        });
        DB::statement("alter table wms_stock_differences comment '差异处理记录'");

        // 盘盈亏单
        Schema::create('wms_stock_check_bill', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('type')->default(0)->comment('类型 1-盘盈单 2-盘亏单');
            $table->string('code', 40)->default('')->comment('单据编码')->index('code');
            $table->tinyInteger('status')->default(0)->comment('状态 0-已暂存 1-已审核');
            $table->string('origin_code', 40)->default('')->comment('来源单据编码');
            $table->string('diff_code', 40)->default('')->comment('差异单据编码');
            $table->integer('diff_num')->default(0)->comment('差异总数');
            $table->string('warehouse_code', 30)->default('')->comment('仓库编码');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->integer('order_user')->default(0)->comment('下单人');
            $table->integer('order_at')->default(0)->comment('下单时间');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('create_user_id')->default(0)->comment('创建人');
            $table->integer('admin_user_id')->default(0)->comment('处理人');
            $table->dateTimeTz('created_at')->comment('创建时间');
            $table->dateTimeTz('updated_at')->comment('处理时间');
        });
        DB::statement("alter table wms_stock_check_bill comment '盘盈亏单'");

        // 库存流水表
        Schema::create('wms_stock_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            // $table->bigInteger('id');
            $table->tinyInteger('operation')->default(0)->comment('操作');
            $table->string('origin_value', 200)->default('')->comment('源值');
            $table->tinyInteger('type')->default(0)->comment('单据类型');
            $table->string('source_code', 40)->default('')->comment('单据编码')->index('source_code');
            $table->string('origin_type', 100)->default('')->comment('来源单据类型');
            $table->string('origin_code', 40)->default('')->comment('来源单据编码')->index('origin_code');
            $table->string('erp_no', 40)->default('')->comment('erp单据编码');
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->string('location_code', 30)->default('')->comment('位置码');
            $table->string('uniq_code', 20)->default('')->comment('唯一码');
            $table->integer('batch_no')->default(0)->comment('批次号');
            $table->integer('num')->default(0)->comment('库存流动数');
            $table->string('warehouse_code', 30)->default('')->comment('仓库编码')->index('warehouse_code');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->char('quality_level', 5)->default('')->comment('质量等级');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('create_user_id')->default(0)->comment('下单人');
            $table->integer('admin_user_id')->default(0)->comment('处理人');
            $table->date('dateline')->comment('操作日期');
            $table->dateTimeTz('created_at')->comment('下单时间');
            $table->dateTimeTz('updated_at')->comment('处理时间');
        });
        DB::statement("alter table wms_stock_logs comment '库存流水表'");
        // DB::statement("alter table wms_stock_logs add PRIMARY KEY(id,dateline)");
        // DB::statement("alter table wms_stock_logs modify id int AUTO_INCREMENT");
        // DB::statement("ALTER TABLE wms_stock_logs
        // PARTITION BY RANGE COLUMNS(dateline)(
        // PARTITION p2022 VALUES LESS THAN ('2023-01-01'),
        // PARTITION p2023 VALUES LESS THAN ('2024-01-01'),
        // PARTITION p2024 VALUES LESS THAN ('2025-01-01'),
        // PARTITION p2025 VALUES LESS THAN ('2026-01-01')
        // )");


        // 产品库存流水表
        Schema::create('wms_product_stock_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            // $table->bigInteger('id');
            $table->tinyInteger('node')->default(0)->comment('节点');
            $table->tinyInteger('type')->default(0)->comment('单据类型');
            $table->string('source_code', 40)->default('')->comment('单据编码')->index('source_code');
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->string('sup_name', 50)->default('')->comment('供应商名称');
            $table->tinyInteger('inv_type')->default(0)->comment('库存类型 0-自营 1-寄卖');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->char('quality_level', 5)->default('')->comment('质量等级');
            $table->string('uniq_code', 20)->default('')->comment('唯一码');
            $table->integer('batch_no')->default(0)->comment('批次号');
            $table->tinyInteger('inv_category')->default(0)->comment('库存类别');
            $table->integer('old_num')->default(0)->comment('起初库存');
            $table->integer('change_num')->default(0)->comment('库存流动');
            $table->integer('new_num')->default(0)->comment('结余库存');
            $table->decimal('cost_amount',10,2)->default(0)->comment('结余库存成本额');
            $table->decimal('cost_price',10,2)->default(0)->comment('成本价');
            $table->decimal('weighted_cost_price',10,2)->default(0)->comment('加权成本价');
            $table->string('origin_type', 100)->default('')->comment('来源单据类型');
            $table->string('origin_code', 40)->default('')->comment('来源单据编码')->index('origin_code');
            $table->string('third_no', 40)->default('')->comment('第三方单据编码');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->string('ip', 20)->default('')->comment('操作请求IP');
            $table->string('warehouse_code', 30)->default('')->comment('仓库编码')->index('warehouse_code');
            $table->string('warehouse_name', 50)->default('')->comment('仓库名称');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('create_user_id')->default(0)->comment('下单人');
            $table->integer('admin_user_id')->default(0)->comment('处理人');
            $table->date('dateline')->comment('操作日期');
            $table->dateTimeTz('created_at')->comment('下单时间');
            $table->dateTimeTz('updated_at')->comment('处理时间');
        });
        DB::statement("alter table wms_product_stock_logs comment '产品库存流水表'");


        // 移位单
        Schema::create('wms_stock_move_list', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('type')->default(0)->comment('单据类型 1-计划移位单');
            $table->string('code', 30)->default('')->comment('单据编码')->index('code');
            $table->tinyInteger('status')->default(0)->comment('单据状态:0-暂存 1-审核中 2-审核通过 3-驳回 4-取消');
            $table->tinyInteger('down_status')->default(0)->comment('下架状态 0-待下架 1-下架中 2-已下架');
            $table->tinyInteger('shelf_status')->default(0)->comment('上架状态 0-待上架 1-上架中  2-已上架');
            $table->string('warehouse_code', 30)->default('')->comment('仓库编码')->index('warehouse_code');
            $table->integer('num')->default(0)->comment('计划移位数');
            $table->integer('down_num')->default(0)->comment('下架总数');
            $table->integer('down_diff')->default(0)->comment('下架总差异数');
            $table->integer('shelf_num')->default(0)->comment('上架总数');
            $table->integer('shelf_diff')->default(0)->comment('上架总差异数');
            $table->dateTimeTz('start_at', 0)->nullable()->comment('移位开始时间');
            $table->dateTimeTz('end_at', 0)->nullable()->comment('移位完成时间');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->integer('down_user_id')->default(0)->comment('下架人id');
            $table->integer('shelf_user_id')->default(0)->comment('上架人id');
            $table->integer('order_user')->default(0)->comment('下单人');
            $table->char('tenant_id', 6);
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->dateTimeTz('created_at', 0)->nullable()->comment('创建时间');
            $table->integer('updated_user')->default(0)->comment('最新操作人');
            $table->dateTimeTz('updated_at', 0)->nullable();
            $table->dateTimeTz('deleted_at')->nullable();
        });
        DB::statement("alter table wms_stock_move_list comment '移位单' ");

        // 移位单详情
        Schema::create('wms_stock_move_details', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('origin_code', 40)->default('')->comment('移位单编码')->index('origin_code');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->string('location_code', 30)->default('')->comment('移出位置码');
            $table->string('area_code', 30)->notNull()->comment('移出库区编码');
            $table->integer('total')->default(0)->comment('计划移位数');
            $table->integer('down_num')->default(0)->comment('下架总数');
            $table->integer('shelf_num')->default(0)->comment('上架总数');
            $table->string('target_location_code', 30)->default('')->comment('计划移入位置码');
            $table->string('target_area_code', 30)->notNull()->comment('计划移入库区编码');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->tinyInteger('status')->default(0)->comment('状态 0-无效 1-有效');
            $table->char('quality_level', 5)->default('')->comment('质量等级');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_stock_move_details comment '移位单详情'");

        // 移位单操作记录
        Schema::create('wms_stock_move_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('origin_code', 40)->default('')->comment('移位单编码');
            $table->string('uniq_code', 20)->default('')->comment('唯一码');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->char('quality_level', 5)->default('')->comment('质量等级');
            $table->integer('batch_no')->default(0)->comment('批次号');
            $table->string('location_code', 30)->default('')->comment('移出位置码');
            $table->string('area_code', 30)->notNull()->comment('移出库区编码');
            $table->string('target_location_code', 30)->default('')->comment('计划移入位置码');
            $table->string('target_area_code', 30)->notNull()->comment('计划移入库区编码');
            $table->string('new_location_code', 30)->default('')->comment('实际移入位置码');
            $table->string('new_area_code', 30)->notNull()->comment('实际移入库区编码');
            $table->tinyInteger('status')->default(0)->comment('状态 0-待下架 1-下架中 2-已下架/待上架 3-上架中 4-已上架');
            $table->dateTimeTz('down_at')->nullable()->comment('下架时间');
            $table->dateTimeTz('shelf_at')->nullable()->comment('上架时间');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('down_user_id')->default(0)->comment('下架人id');
            $table->integer('shelf_user_id')->default(0)->comment('上架人id');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_stock_move_items comment '移位单操作记录'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wms_transfer_order');
        Schema::dropIfExists('wms_transfer_details');
        Schema::dropIfExists('wms_withdraw_uniq_log');
        Schema::dropIfExists('wms_other_ib_order');
        Schema::dropIfExists('wms_other_ib_details');
        Schema::dropIfExists('wms_other_ob_order');
        Schema::dropIfExists('wms_other_ob_details');
        Schema::dropIfExists('wms_stock_check_list');
        Schema::dropIfExists('wms_stock_check_details');
        Schema::dropIfExists('wms_stock_check_logs');
        Schema::dropIfExists('wms_stock_check_request');
        Schema::dropIfExists('wms_stock_check_request_details');
        Schema::dropIfExists('wms_stock_check_differences');
        Schema::dropIfExists('wms_stock_differences');
        Schema::dropIfExists('wms_stock_check_bill');
        Schema::dropIfExists('wms_stock_logs');
        Schema::dropIfExists('wms_stock_move_list');
        Schema::dropIfExists('wms_stock_move_details');
        Schema::dropIfExists('wms_stock_move_items');
    }
}
