<?php

return [
    'code_repeat' => '编码重复',
    'name_repeat' => '名称重复',
    'return_ib_match' => '退调货请匹配入库单',
    'warehouse_not_match' => '仓库不匹配',
    'doc_invalid' => '单据无效',
    'arr_edit_choose_not_recv' => '请选择待匹配或待收货的单据进行修改',
    'subject_repeat' => '科目重复',
    'doc_status_not_edit' => '单据状态不允许修改',
    'doc_not_exists' => '单据不存在',
    'doc_not_exist_or_done' => '单据不存在或已完成',
    'doc_error' => '单据异常',
    'data_not_exists' => ' 数据不存在',
    'order_not_exists' => ' 销售订单不存在',
    'order_not_return' => ' 订单状态不允许申请退款',
    'order_not_withdraw' => ' 销售单状态不允许撤回',
    'order_not_delete' => ' 单据状态不允许删除',
    'order_not_submit' => ' 单据状态不允许提交',
    'doc_not_withdraw' => ' 单据状态不允许撤回',
    'doc_not_examine' => ' 单据未提交或已审核',
    'order_not_pause' => ' 销售单当前状态不可暂停',
    'order_not_fail' => ' 销售单暂停失败',
    'ob_cancel_fail' => ' 出库单取消失败',
    'inv_lack' => ' 库存数量不足',
    'scan_err' => '唯一码或条形码扫描错误',
    'ib_match_err' => '已收货商品数量少于入库单商品数量',
    'sale_after_return_err' => '单据状态不允许确认退款',
    'doc_not_send_recovery' => '订单状态不允许发货追回',
    'doc_not_return_ib' => '订单状态不允许退货入库',
    'not_only_refund' => '订单状态不是仅退款',
    'recovery_number_err' => '追回数量与申请数量不一致',
    'ib_number_err' => '入库数量与申请数量不一致',
    'not_returns_and_refunds' => '订单状态不是退货退款',
    'absent_or_not_refund' => '销售单不存在或状态不允许退款',
    'ib_order_not_exists' => '入库单不存在',
    'type_not_match' => '单据类型不匹配',
    'registration_not_exists' => '登记单不存在',
    'status_not_match_ib' => '登记单状态不允许匹配入库单',
    'only_match_one' => '退调货只能匹配一个入库单',
    'product_not_exists' => '商品不存在',
    'flaw_must_uniqcode' => '瑕疵品必须选定唯一码',
    'pre_fail' => '预配失败!原因:',
    'product_info_lack' => '缺少商品信息',
    'pro_lack' => '商品仓库缺货,条码:',
    'strategy_system_lack' => '按策略配货-系统缺货',
    'system_lack' => '系统缺货,存在未上架或没有采购价的商品',
    'enter_pro_info_err' => '录入商品与系统信息不符',
    'bar_used_del_err' => '条码已使用,不能删除',
    'sup_not_exists' => '供应商不存在',
    'please_on' => '请先启用',
    'status_not_exists' => '不存在该状态',
    'ib_code_required' => '请填入入库单号',
    'ib_code_not_match' => '入库单号和登记单不匹配',
    'batch_not_match' => '批次号不匹配',
    'unicode_not_match' => '唯一码不匹配',
    'batch_code_err' => '批次号不匹配或者商品唯一码扫描错误',
    'return_pro_err' => '退/调商品不存在',
    'recv_not_exists' => '收货单不存在',
    'recv_done' => '该收货单已完成收货',
    'doc_status_err' => '单据状态有误',
    'params_err' => '传入参数错误',
    'new_pro' => '有需要维护的新品',
    'doc_status_settlement_err' => '单据状态不允许结算',
    'bar_repeat' => '条码 %s 录入重复',
    'bar_exists' => '条码 %s 已存在',
    'skus_exists' => 'sku %s 已存在',
    'bar_used_delete_err' => '条码已使用,不能删除',
    'bar_used_update_err' => '条码已使用,不能修改,可以新增商品条码',
    'sku_exists' => 'sku已存在',
    'settle_rules_del' => '所选分类编码:%s 下存在结算规则,不能删除',

    'prePrice' => '出价',
    'productDealPrice' => '成交价',
    'productRealDealPrice' => '实际成交价',
    'productNum' => '发货数量',
    'returnNum' => '退回数量',
    'formula' => '公式错误',

    'no_user' => '用户不存在',
    'login_info_error' => '用户名或密码不正确',
    'warehouse_error' => '仓库信息异常',

    'deny_confirm_check' => '当前状态不可确认盘点',
    'location_no_check' => '该位置码没有可盘点的库存',
    'check_done_deny_edit' => '盘点已完成，不能修改',
    'recheck_no_good' => '没有可复盘的商品',
    'no_difference' => '差异不存在或已处理完成',
    'no_wait_difference' => '不存在待处理的差异记录',


    'empty_config' => '条件配置不能为空',
    'has_task' => '请先完成现有的配货任务',
    'task_cancel' => '任务单已取消',
    'normal_to_unique' => '普通商品不能配货给唯一码任务',
    'order_assign_unique' => '订单指定了唯一码,唯一码不匹配',
    'stock_wait_confirm' => '商品库存状态待确认',
    'not_recheck_status' => '非待复核状态',
    'good_status_error' => '商品状态异常',
    'good_stock_status_error' => '商品库存状态异常',
    'no_outorder' => '未找到出库单',
    'sendout_order_deby_cancel' => '已发货订单不能申请取消',
    'not_wait_shelf' => '不是待上架状态',
    'not_wait_shelf_good' => '没有可上架的商品',
    'good_not_in_shelf_order' => '商品不在当前取消上架单内',
    'no_permission_edit' => '没有权限修改',
    'option_fail' => '操作失败',
    'simple_error' => '简称错误',
    'company_code_deny_edit' => '公司编码不可修改',
    'company_code_exist' => '公司编码已存在',
    'company_not_exist' => '物流公司不存在或已删除',
    'company_disable' => '物流公司已被禁用',
    'payment_not_exist' => '结算方式不存在',
    'pickup_not_exist' => '提货方式不存在',
    'product_not_exist' => '产品不存在或已删除',
    'product_code_deny_edit' => '产品编码不可修改',
    'product_code_exist' => '产品编码已存在',

    'unique_code_error' => '唯一码异常',
    'unique_code_scan_repeat' => '相同唯一码重复扫描',
    'option_repeat' => '重复操作',
    'location_empty_good' => '该位置码下没有可移位的商品',
    'move_need_shelf' => '存在上架中的移位单，请先完成上架',
    'good_stock_get_fail' => '商品库存信息获取失败',
    'has_wait_confirm' => '存在待确认入库的商品，请检查！',
    'move_location_error' => '移入位置码不存在或未启用',
    'move_location_empty' => '该位置码没有可迁移的库存！',

    'request_cancel_fail' => '出库需求单%s取消失败，原因：%s。请处理完后再重新指定配货。',
    'cancel_when_review' => '出库单已取消，请前往取消单重新上架商品。',

    'brand_not_exist' => '品牌不存在或已删除',
    'serie_repeat' => '系列名重复',
    'location_error' => '位置码不存在',
    'unqiue_not_exist' => '唯一码不存在',
    'good_not_wait_shelf' => '商品不在可上架状态',
    'location_error_info' => '位置码%s不存在',
    'uniq_not_wait_shelf' => '唯一码%s不在可上架状态',
    'option_fail_unique_info' => '如下唯一码操作失败:%s',
    'option_fail_change_recv' => '收货单状态和质量类型修改失败',
    'has_check_log' => '已经存在质检记录',
    'has_changing_log' => '有正在进行中的调整单',
    'not_fund_origin' => '未找到源单信息',

    'status_deny_option' => '当前状态不可操作',
    'consigment_not_match' => '新规则的结算对象和部分结算单类型不同',
    'consigment_status_change' => '结算单%s状态已改变，当前操作失败',
    'consigment_not_allow_reassign' => '当前订单状态不允许重新指定。',
    'many_sup' => '您选中的数据存在多个供应商，请重新选择!',

    'require_empty'=>'第%d行，必填数据为空',
    'warehouse_empty'=>'第%d行，仓库不存在',
    'shop_empty'=>'第%d行，店铺不存在',
    'quity_level_error'=>'第%d行，质量等级异常',
    'spu_empty'=>'第%d行，未找到对应的商品信息',
    'serie_brand_name_empty'=>'第%行，名称、品牌名称不能为空',
    'brand_not_exist_num'=>'第%行，品牌不存在',
    'add_fail_reason'=>'%s 添加失败，原因：%s',

    'uniq_not_exist'=>'唯一码不存在',
    'not_uniq'=>'非唯一码产品',
    'not_normal'=>'非普通产品',
    'temp_not_putaway' =>'暂存区不允许上架',
];