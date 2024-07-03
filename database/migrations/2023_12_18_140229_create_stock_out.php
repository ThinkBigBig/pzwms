<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateStockOut extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 配货策略
        Schema::create('wms_pre_allocation_strategy', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('warehouse_code', 30)->comment('仓库编码')->index('warehouse_code');
            $table->string('warehouse_name', 50)->comment('仓库名称');
            $table->string('startegy_code', 30)->comment('策略编码');
            $table->string('name', 50)->comment('策略名称');
            $table->tinyInteger('type')->default(0)->comment('策略维度 1-库龄 2-库区 3-位置码');
            $table->tinyInteger('sort')->default(0)->comment('优先级');
            $table->tinyInteger('status')->default(0)->comment('状态 0-未启用 1-已启用');
            $table->string('condition', 2000)->comment('条件配置'); //json串 in包括 not in不包括，包括全部的时候不用保存 [{"order_type":["in",(1,2,3)]},{"source_platform":["not in",(1,2,3)]},{"source_channel":["in",("得物")]}]
            $table->string('content', 2000)->comment('策略内容'); //json串 eq指定 neq屏蔽 [{operation:"eq",val:1,"sort":10},{operation:"neq",val:1,"sort":2}]
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('create_user_id')->default(0)->comment('创建人');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_pre_allocation_strategy comment '配货策略' ");

        // 出库需求单
        Schema::create('wms_shipping_request', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('type')->default(0)->comment('单据类型 1-销售出库 2-调拨出库 3-其他出库');
            $table->string('request_code', 40)->default('')->comment('出库需求单据编码')->index('request_code');
            $table->string('source_code', 40)->default('')->comment('源单编码')->index('source_code');
            $table->tinyInteger('status')->default(0)->comment('单据状态 1-审核中 2-已审核 3-暂停 4-已确认 5-已取消');
            $table->tinyInteger('request_status')->default(0)->comment('发货状态  1-待发货 2-配货中 3-发货中 4-已发货 5-已取消');
            $table->integer('payable_num')->default(0)->comment('应发总数');
            $table->integer('oversold_num')->default(0)->comment('超卖总数');
            $table->integer('stockout_num')->default(0)->comment('系统缺货总数');
            $table->integer('cancel_num')->default(0)->comment('取消总数');
            $table->integer('actual_num')->default(0)->comment('实发总数');
            $table->tinyInteger('tag')->default(0)->comment('标记');
            $table->string('seller_message', 2000)->default('')->comment('卖家留言');
            $table->string('buyer_message', 2000)->default('')->comment('买家留言');
            $table->string('warehouse_code', 30)->comment('仓库编码')->index('warehouse_code');
            $table->string('warehouse_name', 50)->comment('仓库名称');
            $table->string('third_no', 50)->default('')->comment('第三方单据编号')->index('third_no');
            $table->string('erp_no', 50)->default('')->comment('ERP出库单据编码');
            $table->string('buyer_account', 30)->default('')->comment('买家账号');
            $table->string('shop_name', 30)->default('')->comment('店铺名');
            $table->dateTime('paysuccess_time')->nullable()->comment('下单时间');
            $table->dateTime('delivery_deadline')->nullable()->comment('最晚发货时间');
            $table->tinyInteger('order_platform')->default(0)->comment('来源平台');
            $table->tinyInteger('order_channel')->default(0)->comment('来源渠道');
            $table->string('deliver_type', 20)->default('')->comment('物流');
            $table->string('deliver_no', 500)->default('')->comment('物流单号');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->dateTime('cancel_at')->nullable()->comment('取消时间');
            $table->integer('suspender_id')->default(0)->comment('暂停人');
            $table->dateTime('paused_at')->nullable()->comment('暂停时间');
            $table->string('paused_reason', 2000)->default('')->comment('暂停原因');
            $table->integer('recovery_operator_id')->default(0)->comment('恢复人');
            $table->dateTime('recovery_at')->nullable()->comment('恢复时间');
            $table->string('recovery_reason', 2000)->default('')->comment('恢复原因');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_shipping_request comment '出库需求单' ");

        // 出库明细
        Schema::create('wms_shipping_detail', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('request_code', 40)->default('')->comment('出库需求单据编码')->index('request_code');
            $table->string('ship_code', 100)->default('')->comment('发货单编码');
            $table->integer('batch_no')->default(0)->comment('批次号');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->string('uniq_code', 20)->default('')->comment('唯一码');
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->decimal('buy_price', 10, 2)->default(0)->comment('成本价');
            $table->tinyInteger('status')->default(0)->comment('发货状态 0-待发货 1-配货中 2-发货中 3-已发货 4-已取消');
            $table->string('location_code', 30)->default('')->comment('位置码');
            $table->integer('payable_num')->default(0)->comment('应发数量');
            $table->integer('oversold_num')->default(0)->comment('超卖数量');
            $table->integer('stockout_num')->default(0)->comment('系统缺货数量');
            $table->integer('cancel_num')->default(0)->comment('取消数量');
            $table->integer('actual_num')->default(0)->comment('实发数量');
            $table->string('third_no', 50)->default('')->comment('第三方单据编号');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->string('quality_level',5)->default('')->comment('质量等级');
            $table->string('lock_ids', 255)->default('')->comment('上游锁定库存id');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->integer('shipper_id')->default(0)->comment('发货人');
            $table->dateTime('shipped_at')->nullable()->comment('发货时间');
            $table->dateTime('canceled_at')->nullable()->comment('取消时间');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_shipping_detail comment '出库明细' ");

        // 配货订单
        Schema::create('wms_pre_allocation_lists', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('request_code', 40)->default('')->comment('需求单据编码')->index('request_code');
            $table->string('startegy_code', 30)->comment('配货策略编码');
            $table->tinyInteger('type')->default(0)->comment('单据类型 1-出库配货订单 2-移位配货订单');
            $table->string('pre_alloction_code', 40)->default('')->comment('单据编码');
            $table->tinyInteger('origin_type')->default(0)->comment('来源单据类型 1-销售出库单 2-调拨出库单 3-其他出库单 4-中转移位单 5-快速移位单');
            $table->tinyInteger('status')->default(0)->comment('单据状态 1-已审核 2-已取消 3-已暂停');
            $table->tinyInteger('allocation_status')->default(0)->comment('配货状态 1-待配货 2-配货中 3-已配货');
            $table->tinyInteger('state')->default(0)->comment('0-已预配待分组 1-已分组');
            $table->string('warehouse_code', 30)->comment('仓库编码')->index('warehouse_code');
            $table->integer('sku_num')->default(0)->comment('sku总数');
            $table->integer('pre_num')->default(0)->comment('预配总数');
            $table->integer('received_num')->default(0)->comment('已领任务数');
            $table->integer('cancel_num')->default(0)->comment('取消配货数');
            $table->integer('actual_num')->default(0)->comment('实配总数');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->integer('create_user_id')->default(0)->comment('创建人');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at'); //配货时间
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_pre_allocation_lists comment '配货订单' ");


        // 配货订单明细
        Schema::create('wms_pre_allocation_detail', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('pre_alloction_code', 40)->default('')->comment('配货单编码')->index('pre_alloction_code');
            $table->string('request_code', 40)->default('')->comment('需求单据编码')->index('request_code');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->string('bar_code', 100)->default('')->comment('条形码')->index('bar_code');
            $table->string('uniq_code', 20)->default('')->comment('唯一码')->index('uniq_code');
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->decimal('buy_price', 10, 2)->default(0)->comment('成本价');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->string('quality_level',5)->default('')->comment('质量等级');
            $table->string('warehouse_code', 30)->comment('仓库编码')->index('warehouse_code');
            $table->string('location_code', 30)->default('')->comment('位置码');
            $table->integer('pre_num')->default(0)->comment('预配数量');
            $table->integer('cancel_num')->default(0)->comment('取消配货数');
            $table->integer('batch_no')->default(0)->comment('预配批次');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->integer('pre_inv_id')->default(0)->comment('预配库存id'); //锁定库存
            $table->string('startegy_code', 30)->comment('配货策略编码');
            $table->string('task_strategy_code', 30)->default('')->comment('波次编码');
            $table->string('task_code', 40)->default('')->comment('配货任务单编码');
            $table->tinyInteger('alloction_status')->default(0)->comment('配货状态 1-已预配待分组 2-已分组待领取 3-已领取待配货 4-配货中 5-已配货待复核  6-已复核待发货 7-已发货');
            $table->integer('actual_num')->default(0)->comment('已配数');
            $table->integer('receiver_id')->default(0)->comment('配货人');
            $table->dateTime('received_at')->nullable()->comment('任务领取时间');
            $table->dateTime('allocated_at')->nullable()->comment('配货时间');
            $table->integer('reviewer_id')->default(0)->comment('复核人');
            $table->dateTime('review_at')->nullable()->comment('复核时间');
            $table->tinyInteger('cancel_status')->default(0)->comment('取消状态 1-已取消待释放库存 2-库存释放完成 3-待重新上架 4-已扫描待上架  5-上架完成');
            $table->string('new_location_code', 30)->default('')->comment('取消上架后的位置码');
            $table->dateTime('canceled_at')->nullable()->comment('执行取消的时间');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_pre_allocation_detail comment '配货订单明细' ");


        // 波次分组策略
        Schema::create('wms_task_strategies', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('warehouse_code', 30)->comment('仓库编码')->index('warehouse_code');
            $table->string('warehouse_name', 50)->comment('仓库名称');
            $table->string('code', 30)->comment('策略编码');
            $table->string('name', 50)->comment('分组名称');
            $table->tinyInteger('mode')->default(0)->comment('配货模式 1-分拣配货 2-按单配货');
            $table->tinyInteger('sort')->default(0)->comment('优先级');
            $table->tinyInteger('upper_limit')->default(0)->comment('配货任务领取上限');
            $table->tinyInteger('status')->default(0)->comment('状态 0-未启用 1-已启用');
            $table->string('content', 2000)->comment('策略内容'); //json串
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('create_user_id')->default(0)->comment('创建人');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
            $table->dateTime('deleted_at')->nullable();
        });
        DB::statement("alter table wms_task_strategies comment '波次分组策略' ");


        // 配货任务单
        Schema::create('wms_allocation_tasks', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('code', 40)->default('')->comment('配货任务单编码')->index('code');
            $table->string('type', 50)->notNull()->default('')->comment('单据类型');
            $table->tinyInteger('status')->default(0)->comment('状态 1-暂存 2-已审核 3-已取消');
            $table->tinyInteger('mode')->default(0)->comment('配货模式 1-分拣配货 2-按单配货');
            $table->integer('total_num')->default(0)->comment('总数量');
            $table->integer('received_num')->default(0)->comment('已领取数量');
            $table->tinyInteger('alloction_status')->default(0)->comment('配货状态 1-待配货 2-配货中 3-已配货');
            $table->string('pre_alloction_code', 40)->default('')->comment('预配单编码');
            $table->string('strategy_code', 30)->default('')->comment('波次编码');
            $table->string('group_no', 30)->default('')->comment('波次号');
            $table->integer('order_num')->default(0)->comment('配货订单数');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->string('custom_content', 2000)->default('')->comment('自定义领取配货的内容');
            $table->string('warehouse_code', 30)->comment('仓库编码')->index('warehouse_code');
            $table->dateTime('start_at')->nullable()->comment('开始配货时间');
            $table->dateTime('confirm_at')->nullable()->comment('确认配货时间');
            $table->dateTime('print_at')->nullable()->comment('首次打印时间');
            $table->integer('receiver_id')->default(0)->comment('领取人');
            $table->dateTime('received_at')->nullable()->comment('任务领取时间');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('create_user_id')->default(0)->comment('创建人');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_allocation_tasks comment '配货任务单' ");

        // 发货单
        Schema::create('wms_shipping_orders', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('type')->default(0)->comment('单据类型 0-发货单');
            $table->tinyInteger('status')->default(0)->comment('单据状态 0-已审核 ');
            $table->tinyInteger('request_status')->default(0)->comment('发货状态 0-已发货');
            $table->string('warehouse_code', 30)->comment('仓库编码')->index('warehouse_code');
            $table->string('warehouse_name', 50)->comment('仓库名称');
            $table->string('ship_code', 100)->default('')->comment('单据编码');
            $table->string('request_code', 40)->default('')->comment('出库需求单据编码')->index('request_code');
            $table->integer('sku_num')->default(0)->comment('sku种数');
            $table->integer('actual_num')->default(0)->comment('实发数量');
            $table->integer('quality_num')->default(0)->comment('实发正品数');
            $table->integer('defects_num')->default(0)->comment('实发瑕疵数');
            $table->integer('shipper_id')->default(0)->comment('发货人');
            $table->dateTime('shipped_at')->nullable()->comment('发货时间');
            $table->char('tenant_id', 6)->default('0');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_shipping_orders comment '发货单' ");


        // 出库取消单
        Schema::create('wms_shipping_cancel', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('type')->default(0)->comment('单据类型 1-销售出库 2-调拨出库 3-其他出库');
            $table->string('code', 40)->default('')->comment('单据编码');
            $table->string('request_code', 40)->default('')->comment('出库单编码')->index('request_code');
            $table->string('warehouse_code', 30)->default('')->comment('仓库编码')->index('warehouse_code');
            $table->tinyInteger('status')->default(0)->comment('单据状态 1-已确认');
            $table->tinyInteger('cancel_status')->default(0)->comment('取消状态  1-已完成 2-已上架 3-待上架 4-部分上架');
            $table->tinyInteger('method')->default(0)->comment('处理方式 1-取消库存 2-释放库存 3-发货拦截');
            $table->string('third_no', 50)->default('')->comment('第三方单据编号');
            $table->integer('cancel_num')->default(0)->comment('应取消总数');
            $table->integer('canceled_num')->default(0)->comment('已取消总数');
            $table->integer('wait_putaway_num')->default(0)->comment('待上架总数');
            $table->integer('putaway_num')->default(0)->comment('已上架总数');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('create_user_id')->default(0)->comment('创建人');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_shipping_cancel comment '出库取消单' ");

        // 出库取消单详情
        Schema::create('wms_shipping_cancel_detail', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('origin_code', 40)->default('')->comment('单据编码');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->string('quality_level',5)->default('')->comment('质量等级');
            $table->decimal('retail_price', 10, 2)->default(0)->comment('零售价');
            $table->decimal('actual_deal_price', 10, 2)->default(0)->comment('实际成交价');
            $table->decimal('discount_amount', 10, 2)->default(0)->comment('产品折扣');
            $table->integer('cancel_num')->default(0)->comment('应取消数量');
            $table->integer('canceled_num')->default(0)->comment('已取消数量');
            $table->integer('putaway_num')->default(0)->comment('已上架数量');
            $table->integer('wait_putaway_num')->default(0)->comment('待上架数量');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at');
        });
        DB::statement("alter table wms_shipping_cancel_detail comment '出库取消单详情' ");

        
        // 快递单打印记录
        Schema::create('wms_express_print_log', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('ship_code', 40)->default('')->comment('发货单编码')->index('ship_code');
            $table->string('deliver_no', 400)->default('')->comment('快递单编码');
            $table->integer('print_count')->default(0)->comment('打印次数');
            $table->char('tenant_id', 6)->default('0');
            $table->integer('created_user')->default(0)->comment('首次打印人id');
            $table->string('cre_user_name', 255)->default('')->comment('首次打印人');
            $table->integer('updated_user')->default(0)->comment('最后操作人');
            $table->string('upd_user_name', 255)->default('')->comment('最新操作人名称');
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('updated_at')->nullable();
        });
        DB::statement("alter table wms_express_print_log comment '快递单打印记录' ");

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wms_pre_allocation_strategy');
        Schema::dropIfExists('wms_shipping_request');
        Schema::dropIfExists('wms_shipping_detail');
        Schema::dropIfExists('wms_pre_allocation_lists');
        Schema::dropIfExists('wms_pre_allocation_detail');
        Schema::dropIfExists('wms_task_strategies');
        Schema::dropIfExists('wms_allocation_tasks');
        Schema::dropIfExists('wms_shipping_orders');
        Schema::dropIfExists('wms_shipping_cancel');
        Schema::dropIfExists('wms_shipping_cancel_detail');
        Schema::dropIfExists('wms_express_print_log');
    }
}
