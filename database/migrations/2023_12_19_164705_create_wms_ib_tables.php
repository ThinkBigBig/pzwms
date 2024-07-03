<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


class CreateWmsIbTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 到货登记
        Schema::create('wms_arrival_regist', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('arr_name', 50)->default('')->comment('到货登记名称');
            $table->tinyInteger('arr_type')->default(0)->comment('到货类型:1-采购到货、2-调拨到货、3-退货到货、4-其他到货');
            $table->string('arr_code', 30)->default('')->comment('到货登记编码')->unique();
            $table->integer('lot_num')->default(0)->comment('批次号');
            $table->tinyInteger('doc_status')->default(0)->comment('单据状态:1.已审核-创建成功后初始状态2.已取消-操作取消单据后3.已作废-操作作废单据后4.已确认-入库完成');
            $table->tinyInteger('arr_status')->default(0)->comment('到货状态:1.待匹配-调拨/退货需要先匹配入库单2.待收货-收货前3.收货中-收货至点击入库完成期间4.已完成-点击到货完成');
            $table->integer('arr_num')->default(0)->comment('到货登记数量');
            $table->integer('recv_num')->default(0)->comment('收货数量');
            $table->integer('confirm_num')->default(0)->comment('待确认数量');
            $table->string('warehouse_code', 30)->default('')->comment('仓库编码')->index('warehouse_code');
            $table->string('ib_code', 30)->default('')->comment('入库编号');
            $table->string('third_doc_code', 30)->default('')->comment('三方单据编号:调拨、退货单号');
            $table->integer('uni_num_count')->default(0)->comment('已生成唯一编码数');
            $table->string('log_product_code', 30)->default('')->comment('物流产品编码');
            $table->string('log_number', 30)->default('')->comment('物流单号');
            $table->tinyInteger('is_pur_cost')->default(0)->comment('是否有采购成本:0-没有确认1-有2-没有');
            $table->decimal('pur_cost')->default(0)->comment('采购成本');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6);
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->integer('updated_user')->default(0)->comment('最新操作人');
            $table->dateTimeTz('created_at', 0)->nullable()->comment('创建时间');
            $table->dateTimeTz('updated_at', 0)->nullable();
        });
        DB::statement("alter table wms_arrival_regist comment '到货登记' ");

        //采购成本详情
        Schema::create('wms_purchase_cost', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->integer('arr_id')->default(0)->comment('登记单id')->index('arr_id');
            $table->tinyInteger('type')->default(0)->comment('科目:1-装卸费 2-邮费 3-人工');
            $table->tinyInteger('num')->default(0)->comment('数量');
            $table->decimal('cost')->default(0)->comment('费用');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6);
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->integer('updated_user')->default(0)->comment('最新操作人');
            $table->dateTimeTz('created_at', 0)->nullable()->comment('创建时间');
            $table->dateTimeTz('updated_at', 0)->nullable();
        });
        DB::statement("alter table wms_purchase_cost comment '采购成本详情' ");

        // 操作日志
        Schema::create('wms_option_log', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('type')->default(0)->comment('日志类型:1-到货登记日志');
            $table->string('doc_code', 30)->default('')->comment('单据编码')->index('doc_code');
            $table->string('option', 100)->default('')->comment('操作类型');
            $table->string('desc', 1000)->default('')->comment('描述');
            $table->text('detail')->default('')->comment('详情');
            $table->integer('admin_user_id')->default(0)->comment('操作人');
            $table->string('admin_name', 100)->default('')->comment('操作人名称');
            $table->char('tenant_id', 6);
            $table->dateTimeTz('created_at', 0)->nullable()->comment('记录时间');
        });
        DB::statement("alter table wms_option_log comment '操作日志' ");

        //唯一码打印记录
        Schema::create('wms_unicode_print_log', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->integer('arr_id')->default(0)->comment('登记单id');
            $table->string('arr_code', 30)->default('')->comment('登记单编码')->index('arr_code');
            $table->string('warehouse_name', 50)->default('')->comment('仓库名称');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->string('uniq_code', 20)->default('')->comment('唯一码');
            $table->integer('print_count')->default(0)->comment('打印次数');
            $table->integer('created_user')->default(0)->comment('首次打印人id');
            $table->string('cre_user_name', 100)->default('')->comment('首次打印人');
            $table->integer('updated_user')->default(0)->comment('最新操作人');
            $table->string('upd_user_name', 100)->default('')->comment('最新操作人名称');
            $table->char('tenant_id', 6);
            $table->dateTimeTz('created_at', 0)->nullable()->comment('首打印时间');
            $table->dateTimeTz('updated_at', 0)->nullable();
            $table->unique(['uniq_code','tenant_id'],'uniq_code_tenant_id_unique');
        });
        DB::statement("alter table wms_unicode_print_log comment '唯一码打印记录' ");



        //收货单
        Schema::create('wms_recv_order', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->integer('arr_id')->default(0)->comment('登记单id');
            $table->string('source_code', 40)->default('')->comment('源单编码');
            $table->tinyInteger('recv_type')->default(0)->comment('收货类型:1-采购收货、2-调拨收货、3-退货收货、4-其他收货');
            $table->tinyInteger('doc_status')->default(0)->comment('单据状态:1.暂存-收货中2.已审核-收货完成3.已作废-到货登记单作废');
            $table->tinyInteger('recv_status')->default(0)->comment('收货状态:0.收货中1.已完成');
            $table->tinyInteger('recv_methods')->default(0)->comment('收货方式:1.逐件收货2.其他');
            $table->tinyInteger('scan_type')->default(0)->comment('扫描类型:0-唯一码 1-普通产品');
            $table->string('recv_code', 30)->default('')->comment('收货单编码')->unique();
            $table->string('warehouse_code', 50)->default('')->comment('仓库编码')->index('warehouse_code');
            $table->integer('recv_num')->default(0)->comment('收货数量');
            $table->integer('created_user')->default(0)->comment('收货人');
            $table->integer('updated_user')->default(0)->comment('最新操作人');
            $table->char('tenant_id', 6);
            $table->dateTimeTz('created_at', 0)->nullable()->comment('收货开始时间');
            $table->dateTimeTz('done_at', 0)->nullable()->comment('收货结束时间');
            $table->dateTimeTz('updated_at', 0)->nullable();
        });
        DB::statement("alter table wms_recv_order comment '收货单' ");

        //  扫描收货明细单
        Schema::create('wms_recv_detail', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('inv_type')->default(0)->comment('库存类型:0-自营 1-寄卖');
            $table->integer('arr_id')->default(0)->comment('登记单id')->index('arr_id');
            $table->integer('recv_id')->default(0)->comment('收货单ID')->index('recv_id');
            $table->integer('ib_id')->default(0)->comment('入库单ID');
            $table->integer('buy_id')->default(0)->comment('采购单ID');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->string('uniq_code', 20)->default('')->comment('唯一码')->index('uniq_code');
            $table->integer('lot_num')->default(0)->comment('批次号');
            $table->tinyInteger('recv_unit')->default(0)->comment('收货单元:0-件 1-散件 2-箱');
            $table->string('box_code', 100)->default('')->comment('来货箱号');
            $table->string('container_code', 100)->default('')->comment('容器编码');
            $table->string('warehouse_code', 50)->default('')->comment('仓库编码')->index('warehouse_code');
            $table->string('area_code', 30)->default('')->comment('库区编码');
            $table->string('location_code', 30)->default('')->comment('位置码');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->string('quality_level', 5)->default('A')->comment('质量等级A、B、C、D、E、F');
            $table->integer('created_user')->default(0)->comment('收货人');
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->integer('sup_document_id')->default(0)->comment('供应商证件id');
            $table->decimal('buy_price', 10, 2)->default(0)->comment('采购价');
            $table->tinyInteger('sup_confirm')->default(0)->comment('确认供应商:0.未确认1.已确认');
            $table->tinyInteger('ib_confirm')->default(0)->comment('匹配入库单:0.未匹配1.已匹配');
            $table->tinyInteger('is_qc')->default(0)->comment('是否质检:0.未质检1.已质检');
            $table->tinyInteger('is_putway')->default(0)->comment('是否上架:0.未上架 1.已上架');
            $table->tinyInteger('is_cancel')->default(0)->comment('是否作废:0.正常 1.作废');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6);
            $table->dateTimeTz('created_at', 0)->nullable()->comment('收货开始时间');
            $table->dateTimeTz('done_at', 0)->nullable()->comment('收货完成时间');
            $table->dateTimeTz('ib_at', 0)->nullable()->comment('最新入库时间');
            $table->integer('updated_user')->default(0)->comment('最后操作人');
            $table->dateTimeTz('updated_at', 0)->nullable()->comment('最近更新时间');
        });
        DB::statement("alter table wms_recv_detail comment '收货明细单' ");


        //  库存商品明细
        Schema::create('wms_inv_goods_detail', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('in_wh_status')->default(0)->comment('在仓状态 0-暂存 1-已收货 ,2-已质检 3-已上架 4-已出库 5-调拨中 6-冻结 7-作废 8-移位中 9-已下架');
            $table->tinyInteger('sale_status')->default(0)->comment('销售 0-不可售 1-待售 ,2-已匹配销售单/调拨单 3-已配货 4-已发货 5-冻结');
            $table->tinyInteger('inv_status')->default(0)->comment('库存状态 0-在仓 1-架上 2-可售 3-待上架 4-架上待确认 5-架上可售 6-架上锁定 7-待发 8-调拨 9-冻结');
            $table->tinyInteger('lock_type')->default(0)->comment('锁定库存单据类型 0-未锁定 1-销售 2-调拨 3-其他出库');
            $table->string('lock_code', 30)->default('')->comment('锁定单据编码');
            $table->integer('arr_id')->default(0)->comment('登记单id');
            $table->integer('lot_num')->default(0)->comment('批次号');
            $table->integer('recv_id')->default(0)->comment('收货单ID')->index('recv_id');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->string('bar_code', 100)->default('')->comment('条形码')->index('bar_code');
            $table->string('uniq_code', 20)->default('')->comment('唯一码')->index('uniq_code');
            $table->string('warehouse_code', 40)->default('')->comment('仓库编码')->index('warehouse_code');
            $table->string('area_code', 30)->default('')->comment('库区编码');
            $table->string('location_code', 30)->default('')->comment('位置码')->index('location_code');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->string('quality_level', 5)->default('A')->comment('质量等级A、B、C、D、E、F');
            $table->integer('recv_num')->default(0)->comment('收货数量');
            $table->tinyInteger('recv_unit')->default(0)->comment('收货单元:0-件 1-散件 2-箱');
            $table->decimal('buy_price', 10, 2)->default(0)->comment('采购价');
            $table->integer('created_user')->default(0)->comment('收货人');
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->tinyInteger('inv_type')->default(0)->comment('库存类型:0-自营 1-寄卖');
            $table->tinyInteger('is_qc')->default(0)->comment('是否质检:0.未质检1.已质检');
            $table->tinyInteger('is_putway')->default(0)->comment('是否上架:0.未上架 1.已上架');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6);
            $table->dateTimeTz('created_at', 0)->nullable()->comment('创建时间');
            $table->dateTimeTz('start_at', 0)->nullable()->comment('收货开始时间');
            $table->dateTimeTz('done_at', 0)->nullable()->comment('收货完成时间');
            $table->dateTimeTz('ib_at', 0)->nullable()->comment('最新入库时间');
            $table->integer('updated_user')->default(0)->comment('最后操作人');
            $table->dateTimeTz('updated_at', 0)->nullable()->comment('最近更新时间');
        });
        DB::statement("alter table wms_inv_goods_detail comment '库存商品明细' ");

        //供应商
        Schema::create('wms_supplier', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->string('sup_code', 40)->default('')->comment('供应商编码');
            $table->string('name', 100)->default('')->comment('供应商名称');
            $table->tinyInteger('sup_status')->default(1)->comment('供应商状态 1-暂存 ,2-已通过');
            $table->tinyInteger('type')->default(1)->comment('供应商类别: 1-个人 2-公司');
            $table->string('id_card', 100)->default('')->comment('身份证号');
            $table->date('id_card_date')->comment('身份证有效期');
            $table->string('email', 255)->default('')->comment('电子邮箱');
            $table->string('contact_name', 40)->default('')->comment('联系人');
            $table->string('contact_phone', 40)->default('')->comment('手机号');
            $table->string('contact_landline', 40)->default('')->comment('座机');
            $table->string('contact_addr', 100)->default('')->comment('地址');
            $table->string('bank_number', 40)->default('')->comment('卡号');
            $table->string('account_name', 255)->default('')->comment('开户名');
            $table->string('bank_card', 100)->default('')->comment('开户行');
            $table->string('bank_name', 100)->default('')->comment('开户网点');
            $table->string('id_card_front', 255)->default('')->comment('身份证正面');
            $table->string('id_card_reverse', 255)->default('')->comment('身份证反面');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->tinyInteger('status')->default(1)->comment('状态 0-未启用 1-已启用');
            $table->integer('approver')->default(0)->comment('审批人id');
            $table->dateTime('approved_at', 0)->nullable()->comment('审批时间');
            $table->integer('sort')->default(0)->comment('排序');
            $table->char('tenant_id', 6);
            $table->integer('updated_user')->default(0)->comment('最后操作人');
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->dateTimeTz('created_at', 0);
            $table->dateTimeTz('updated_at', 0)->nullable();
            $table->unique(['sup_code','tenant_id'],'sup_code_unique');
        });
        DB::statement("alter table wms_supplier comment '供应商' ");


        //入库需求单
        Schema::create('wms_ib_order', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->integer('arr_id')->default(0)->comment('登记单id');
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->string('source_code', 40)->default('')->comment('源单编码/采购单')->index('source_code');
            $table->tinyInteger('ib_type')->default(0)->comment('单据类型:1-采购入库、2-调拨入库、3-退货入库、4-其他入库');
            $table->string('ib_code', 40)->default('')->comment('入库单编号')->unique();
            $table->string('erp_no', 40)->default('')->comment('erp入库单编号');
            $table->string('third_no', 50)->default('')->comment('第三方单据编码')->index('third_no');
            $table->string('deliver_no', 500)->default('')->comment('物流单号');
            $table->tinyInteger('doc_status')->default(0)->comment('单据状态:1.已审核 2.已取消 3.已确认');
            $table->tinyInteger('recv_status')->default(0)->comment('收货状态:1.待收货 2.部分收获 3.已收货');
            $table->string('warehouse_code', 40)->default('')->comment('仓库编码')->index('warehouse_code');
            $table->integer('re_total')->default(0)->comment('应收总数');
            $table->integer('rd_total')->default(0)->comment('实收总数');
            $table->integer('normal_count')->default(0)->comment('实收正品');
            $table->integer('flaw_count')->default(0)->comment('实收瑕疵');
            $table->integer('paysuccess_time')->default(0)->comment('下单时间');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->dateTime('cancel_at')->nullable()->comment('取消时间');
            $table->char('tenant_id', 6);
            $table->integer('updated_user')->default(0)->comment('最后操作人');
            $table->integer('created_user')->default(0)->comment('创建人');
            $table->dateTimeTz('created_at', 0);
            $table->dateTimeTz('updated_at', 0)->nullable();
        });
        DB::statement("alter table wms_ib_order comment '入库需求单' ");

        //入库明细单
        Schema::create('wms_ib_detail', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id()->autoIncrement();
            $table->tinyInteger('inv_type')->default(0)->comment('库存类型:0-自营 1-寄卖');
            $table->string('ib_code', 40)->default('')->comment('入库需求单据编码')->index('ib_code');
            $table->string('bar_code', 100)->default('')->comment('条形码');
            $table->string('sku', 100)->default('')->comment('sku编码');
            $table->char('quality_level', 5)->default('')->comment('质量等级');
            $table->tinyInteger('quality_type')->default(0)->comment('质量类型 1-正品 2-疑似瑕疵');
            $table->integer('sup_id')->default(0)->comment('供应商id');
            $table->string('uniq_code', 20)->default('')->comment('唯一码');
            $table->integer('re_total')->default(0)->comment('应收总数');
            $table->integer('rd_total')->default(0)->comment('实收总数');
            $table->integer('normal_count')->default(0)->comment('实收正品');
            $table->integer('flaw_count')->default(0)->comment('实收瑕疵');
            $table->decimal('buy_price', 10, 2)->default(0)->comment('采购价');
            $table->string('remark', 2000)->default('')->comment('备注');
            $table->char('tenant_id', 6);
            $table->integer('admin_user_id')->default(0)->comment('最后操作人');
            $table->dateTimeTz('created_at', 0);
            $table->dateTimeTz('updated_at', 0)->nullable();
        });
        DB::statement("alter table wms_ib_detail comment '入库明细单' ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wms_arrival_regist');
        Schema::dropIfExists('wms_purchase_cost');
        Schema::dropIfExists('wms_option_log');
        Schema::dropIfExists('wms_unicode_print_log');
        Schema::dropIfExists('wms_recv_order');
        Schema::dropIfExists('wms_recv_detail');
        Schema::dropIfExists('wms_inv_goods_detail');
        Schema::dropIfExists('wms_supplier');
        Schema::dropIfExists('wms_ib_order');
        Schema::dropIfExists('wms_ib_detail');
    }
}
