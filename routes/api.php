<?php
//后台路由
$api = app('Dingo\Api\Routing\Router');
$api->version('v1', [
    'namespace' => 'App\Http\Controllers',
    'cors'
], function ($api) {

    $api->get('/callback', 'Admin\V1\IndexController@callback');
    $api->get('/check', 'Admin\V1\SynchronizeController@check'); //列表
    $api->get('/createSignature', 'Admin\V1\SynchronizeController@createSignature'); //列表
    $api->get('/dwApi', 'Admin\V1\IndexController@dwApi');
    $api->get('/redisToken', 'Admin\V1\IndexController@redisToken');
    $api->get('/dwApiMethod', 'Admin\V1\IndexController@dwApiMethod');

    $api->post('/task/product', 'Admin\V1\SynchronizeController@product'); //列表
    $api->post('/task/productStockDetailed', 'Admin\V1\SynchronizeController@productStockDetailed'); //列表
    $api->post('/task/productStock', 'Admin\V1\SynchronizeController@productStock'); //列表
    $api->group(['namespace' => 'Admin\V1', "prefix" => 'admin'], function ($api) {
        $api->post('/import', 'ProductController@import');
        $api->post('/productList', 'ProductController@productList');
        //登录
        $api->post('/login', 'Auth\AuthController@login');

        //需要权限接口
        $api->group(['middleware' => 'auth:admin'], function ($api) {

            //查指定货号的导入数据
            $api->post('/tools/reservation', 'ToolsController@reservation');
            $api->any('/tools/data-check', 'ToolsController@dataCheck');
            $api->post('/tools/send-notice', 'ToolsController@sendNotice');
            $api->get('/tools/size-standard', 'ToolsController@sizeStandard');
            $api->get('/tools/bid_info', 'ToolsController@bidInfo'); //得物
            $api->get('/tools/stock_bid_info', 'ToolsController@stockBidInfo'); //得物

            //初始化预约单
            $api->get('/tools/reservation-init', 'ToolsController@reservationInit');

            //退出接口
            $api->post('/logout', 'Auth\AuthController@destroy');
            //登录用户拥有菜单
            $api->get('/getMenus', 'Auth\AuthController@getMenus');

            //菜单模块
            $api->get('/menus/getMenus', 'Auth\MenuController@getMenus'); //列表
            $api->get('/menus/info', 'Auth\MenuController@BaseOne'); //详情
            $api->post('/menus/add', 'Auth\MenuController@BaseCreate'); //新增
            $api->post('/menus/edit', 'Auth\MenuController@BaseUpdate'); //修改
            $api->get('/menus/del', 'Auth\MenuController@BaseDelete'); //删除
            $api->get('/menus/menuList', 'Auth\MenuController@menuList'); //分配菜单获取

            //角色模块
            $api->get('/role/list', 'Auth\RolesController@BaseLimit'); //列表
            $api->get('/role/info', 'Auth\RolesController@BaseOne'); //详情f
            $api->post('/role/add', 'Auth\RolesController@BaseCreate'); //新增
            $api->post('/role/edit', 'Auth\RolesController@BaseUpdate'); //修改
            $api->get('/role/del', 'Auth\RolesController@BaseDelete'); //删除
            $api->get('/role/list', 'Auth\RolesController@BaseLimit'); //列表
            $api->get('/role/roleList', 'Auth\RolesController@roleList'); //列表
            $api->get('/role/type', 'Auth\RolesController@roleType'); //角色类型
            $api->post('/role/setRoleList', 'Auth\RolesController@setRoleList'); //列表

            //用户模块
            $api->get('/user/list', 'Auth\UserController@BaseLimit'); //列表
            $api->get('/user/info', 'Auth\UserController@BaseOne'); //详情
            $api->post('/user/add', 'Auth\UserController@BaseCreate'); //新增
            $api->post('/user/edit', 'Auth\UserController@BaseUpdate'); //修改
            $api->get('/user/del', 'Auth\UserController@BaseDelete'); //删除
            $api->post('/user/import', 'Auth\UserController@import'); //删除

            $api->get('/userInfo', 'Auth\UserController@UserInfo'); //用户列表
            //日志模块
            $api->get('/log/list', 'Auth\Logs@BaseLimit'); //列表
            $api->get('/log/info', 'Auth\Logs@BaseOne'); //详情
            // $api->get('/log/del', 'Logs@BaseDelete');//删除

            //库存信息
            $api->get('/product/stock/all', 'ProductSkuStockController@BaseAll'); //列表
            $api->get('/product/stock/list', 'ProductSkuStockController@BaseLimit'); //列表
            $api->get('/product/stock/info', 'ProductSkuStockController@BaseOne'); //详情
            $api->post('/product/stock/add', 'ProductSkuStockController@BaseCreate'); //新增
            $api->post('/product/stock/edit', 'ProductSkuStockController@BaseUpdate'); //修改
            $api->get('/product/stock/del', 'ProductSkuStockController@BaseDelete'); //删除
            $api->get('/product/stockAll', 'ProductSkuStockController@stockAll'); //通过货号找在库的sku
            $api->get('/product/stock/export', 'ProductSkuStockController@export'); //详情
            $api->get('/product/stock/export-bid-data', 'ProductSkuStockController@exportBidData'); //导出库存出价数据


            //库存明细
            $api->get('/stock/list', 'StockController@BaseLimit'); //列表
            $api->get('/stock/info', 'StockController@BaseOne'); //详情
            $api->post('/stock/add', 'StockController@BaseCreate'); //新增
            $api->post('/stock/edit', 'StockController@BaseUpdate'); //修改
            $api->get('/stock/del', 'StockController@BaseDelete'); //删除
            $api->get('/stock/stockAll', 'StockController@stockAll'); //
            $api->get('/stock/export', 'StockController@export'); //

            //保税入仓
            $api->get('/bonded/list', 'BondedStockController@BaseLimit'); //列表
            $api->get('/bonded/info', 'BondedStockController@BaseOne'); //详情
            $api->post('/bonded/add', 'BondedStockController@BaseCreate'); //新增
            $api->post('/bonded/edit', 'BondedStockController@BaseUpdate'); //修改
            $api->get('/bonded/del', 'BondedStockController@BaseDelete'); //删除
            //            $api->get('/bonded/all', 'BondedStockNumberController@BaseAll');//删除
            $api->get('/bonded/all', 'BondedStockNumberController@BaseLimit'); //删除
            $api->get('/bondedExport', 'BondedStockController@export'); //删除

            $api->post('/bondedInfo', 'BondedStockController@bondedInfo'); //详情
            $api->post('/bondedAdd', 'BondedStockController@bondedAdd'); //添加
            $api->post('/bondedAdopt', 'BondedStockController@bondedAdopt'); //通过
            $api->post('/bondedReject', 'BondedStockController@bondedReject'); //驳回
            $api->post('/bondedCancel', 'BondedStockController@bondedCancel'); //取消预约单
            $api->get('/bondedBinding', 'BondedStockController@bondedBinding'); //

            //库存明细
            $api->get('/SaleStock/list', 'SaleStockController@BaseLimit'); //列表
            $api->get('/SaleStock/info', 'SaleStockController@BaseOne'); //详情
            $api->post('/SaleStock/add', 'SaleStockController@BaseCreate'); //新增
            $api->post('/SaleStock/edit', 'SaleStockController@BaseUpdate'); //修改
            $api->get('/SaleStock/del', 'SaleStockController@BaseDelete'); //删除


            $api->get('/product/sssssss', 'ProductController@sssssss'); //列表
            $api->get('/product/checkSignature', 'ProductController@checkSignature'); //列表
            //商品模块
            $api->get('/product/list', 'ProductController@BaseLimit'); //列表
            $api->get('/product/info', 'ProductController@BaseOne'); //详情
            $api->get('/productSkuInfo', 'ProductController@productInfo'); //详情
            $api->post('/product/add', 'ProductController@BaseCreate'); //新增
            $api->post('/product/edit', 'ProductController@BaseUpdate'); //修改
            $api->get('/product/delete', 'ProductController@delete'); //删除
            $api->post('/product/skuUpdate', 'ProductController@skuUpdate'); //删除
            $api->post('/product/skuUpdateAll', 'ProductController@skuUpdateAll'); //删除
            $api->get('/product/skuDelete', 'ProductController@skuDelete'); //删除
            //订单模块
            $api->get('/order/list', 'OrderController@BaseLimit'); //列表
            $api->get('/order/info', 'OrderController@BaseOne'); //详情
            // $api->post('/order/add', 'OrderController@BaseCreate');//新增
            $api->post('/order/edit', 'OrderController@BaseUpdate'); //修改
            $api->get('/order/del', 'OrderController@BaseDelete'); //删除
            $api->get('/order/export', 'OrderController@export'); //导出

            $api->get('/order/channel-orders', 'OrderController@erpOrders'); //三方订单
            $api->get('/bidding/search', 'ChannelBiddingController@search'); //三方出价
            $api->post('/channel/config/save', 'ChannelBiddingController@setChannelConfig'); //渠道配置修改

            //结算
            $api->get('/withdraw/list', 'WithdrawController@BaseLimit'); //列表
            $api->post('/withdraw/check_money', 'WithdrawController@check_money'); //修改
            $api->get('/withdraw/allOrder', 'WithdrawController@allOrder'); //订单详情
            $api->get('/withdraw/withdrawOrder', 'WithdrawController@withdrawOrder'); //
            $api->get('/withdraw/withdrawAdopt', 'WithdrawController@withdrawAdopt'); //通过
            $api->get('/withdraw/withdrawReject', 'WithdrawController@withdrawReject'); //驳回
            $api->get('/withdraw/uWithdrawSettlement', 'WithdrawController@uWithdrawSettlement'); //管理员通过
            $api->get('/withdraw/uWithdrawExamine', 'WithdrawController@uWithdrawExamine'); //管理员处理异常让用户审核
            $api->post('/withdraw/uWithdrawExamineSon', 'WithdrawController@uWithdrawExamineSon'); //管理员处理异常让修改单个或多个字单
            $api->get('/financeConfig', 'WithdrawController@financeConfig'); //每单提现设置
            $api->post('/setFinanceConfig', 'WithdrawController@setFinanceConfig'); //修改每单提现设置
            $api->get('/withdraw/orderList', 'WithdrawController@orderList'); //结算单详情
            //出货单
            $api->post('/shipmentImport', 'ShipmentController@import'); //导入
            $api->get('/shipmentList', 'ShipmentController@shipmentList'); //列表
            $api->get('/shipmentInfo', 'ShipmentController@shipmentInfo'); //详情
            $api->get('/shipmentAll', 'ShipmentController@BaseAll'); //根据条件拿全部
            $api->get('/shipmentExport', 'ShipmentController@export'); //导出

            //出价
            $api->get('/bondedNumberList', 'ShipmentController@bondedNumberList'); //列表
            $api->get('/bondedNumberInfo', 'ShipmentController@bondedNumberInfo'); //列表
            $api->get('/stock/offer', 'ShipmentController@offer'); //出价
            $api->get('/stock/reBid', 'ShipmentController@reBid'); //修改出价
            $api->get('/stock/delBid', 'ShipmentController@delBid'); //取消出价
            $api->get('/biddingPrice', 'ShipmentController@biddingPrice'); //获取预计收入
            $api->get('/lowestPrice', 'ShipmentController@lowestPrice'); //获取最低价格

            //瑕疵
            $api->post('/shipmentFlawImport', 'ShipmentFlawController@import'); //导入
            $api->get('/shipmentFlawList', 'ShipmentFlawController@shipmentFlawList'); //列表
            $api->get('/shipmentFlawExport', 'ShipmentFlawController@export'); //导出

            //配置模块
            $api->get('/config/list', 'ConfigController@BaseLimit'); //列表
            $api->get('/config/info', 'ConfigController@BaseOne'); //列表
            $api->post('/config/add', 'ConfigController@BaseCreate'); //新增
            $api->post('/config/edit', 'ConfigController@BaseUpdate'); //修改
            $api->get('/config/del', 'ConfigController@BaseDelete'); //删除

            //轮播模块
            $api->get('/slide/list', 'SlideController@BaseLimit'); //列表
            $api->get('/slide/info', 'SlideController@BaseOne'); //详情s
            $api->post('/slide/add', 'SlideController@BaseCreate'); //新增
            $api->post('/slide/edit', 'SlideController@BaseUpdate'); //修改
            $api->get('/slide/del', 'SlideController@BaseDelete'); //删除

            //文章模块
            $api->get('/article/list', 'ArticleController@BaseLimit'); //列表
            $api->get('/article/info', 'ArticleController@BaseOne'); //详情
            $api->post('/article/add', 'ArticleController@BaseCreate'); //新增
            $api->post('/article/edit', 'ArticleController@BaseUpdate'); //修改
            $api->get('/article/del', 'ArticleController@BaseDelete'); //删除
            //图片文件模块
            $api->get('/attachment/list', 'AttachmentController@BaseLimit'); //列表
            $api->get('/attachment/info', 'AttachmentController@BaseOne'); //详情
            $api->post('/attachment/add', 'AttachmentController@add'); //新增
            $api->post('/attachment/edit', 'AttachmentController@BaseUpdate'); //修改
            $api->get('/attachment/del', 'AttachmentController@BaseDelete'); //删除
            $api->post('attachment/addWms', 'AttachmentController@addWms'); //Wms新增商品
            $api->post('attachment/package', 'AttachmentController@addPackage'); //pda安装包上传
            $api->post('attachment/addSupplier', 'AttachmentController@addSupplier'); //Wms新增供应商
            $api->post('attachment/addEd', 'AttachmentController@addEdNo'); //上传物流单号pdf


            //三方库存出价
            $api->post('/stock-product/import', 'StockProductController@import'); //导入
            $api->get('/stock-product/list', 'StockProductController@list'); //列表
            $api->get('/stock-product/detail', 'StockProductController@detail'); //详情
            $api->get('/stock-product/export', 'StockProductController@export'); //导出
            $api->get('/stock-product/export-detail', 'StockProductController@exportDetail'); //导出明细

            $api->post('/stock-product/update', 'StockProductController@update'); //商品上下架
            $api->post('/stock-product/del-bid', 'StockProductController@delBid'); //删除出价
            $api->post('/stock-product/clear', 'StockProductController@clear'); //清空商品
            $api->post('/stock-product/edit', 'StockProductController@edit'); //商品信息编辑

            $api->get('/order/search', 'ChannelOrderController@search'); //订单列表
            $api->get('/order/export', 'ChannelOrderController@export'); //订单导出
            $api->post('/order/cancel', 'ChannelOrderController@cancel'); //订单取消
            $api->post('/order/confirm', 'ChannelOrderController@confirm'); //订单确认
            $api->post('/order/batch-confirm', 'ChannelOrderController@batchConfirm'); //订单批量确认
            $api->post('/order/send-out', 'ChannelOrderController@sendOut'); //订单发货
            $api->get('/order/batch-export-deliver', 'ChannelOrderController@batchExportDeliver'); //批量导出发货单
            $api->post('/order/batch-send-out', 'ChannelOrderController@batchSendOut'); //订单批量发货
            $api->post('/order/sync', 'ChannelOrderController@syncOrderStatus'); //同步订单信息
            $api->post('/order/edit', 'ChannelOrderController@updateInfo'); //更新订单状态
            $api->any('/order/deliver', 'ChannelOrderController@deliver'); //得物虚拟物流单号展示





            //二期新增

            //组织模块
            $api->get('/org/orgList', 'Auth\OrgController@orgList'); //组织级别
            $api->get('/org/list', 'Auth\OrgController@BaseLimit'); //列表
            $api->get('/org/info', 'Auth\OrgController@BaseOne'); //详情
            $api->post('/org/add', 'Auth\OrgController@BaseCreate'); //新增
            $api->post('/org/edit', 'Auth\OrgController@BaseUpdate'); //修改
            $api->get('/org/del', 'Auth\OrgController@BaseDelete'); //删除




            //基础设置
            $api->get('/locator/list', 'BaseSetting\WarehouseLocation@BaseLimit'); //列表
            // $api->get('/locator/info', 'BaseSetting\WarehouseLocation@BaseOne');//详情
            $api->post('/locator/add', 'BaseSetting\WarehouseLocation@BaseCreate'); //新增
            // $api->post('/locator/edit', 'BaseSetting\WarehouseLocation@BaseUpdate');//修改
            $api->get('/locator/del', 'BaseSetting\WarehouseLocation@BaseDelete'); //删除

            //入库管理

            $api->get('/inbound/list', 'Warehousing\Warehousing@BaseLimit'); //列表
            $api->post('/inbound/add', 'Warehousing\MobileWarehouse@inbound'); //移动入库
            $api->post('/inbound/check', 'Warehousing\MobileWarehouse@inboundCheck'); //移动入库

            // 添加/删除空卖商品
            $api->post('/purchase-product/update', 'PurchaseProductController@update');
            $api->post('/purchase/config/price', 'PurchaseProductController@addPriceConfig'); //新增空卖出价配置
            $api->delete('/purchase/config/price', 'PurchaseProductController@delPriceConfig'); //删除空卖出价配置
        });
    });

    $api->group(['namespace' => 'Admin\V2', "prefix" => 'admin', 'middleware' => 'auth:admin'], function ($api) {
        //商品分类
        $api->get('/product/cateList', 'Product\CategoryController@getCategoryList'); //分类列表分级
        $api->post('/product/category/add', 'Product\CategoryController@BaseCreate'); //新增分类
        $api->get('/product/category/info', 'Product\CategoryController@BaseOne'); //分类详情
        $api->post('/product/category/edit', 'Product\CategoryController@BaseUpdate'); //修改分类
        $api->get('/product/category/del', 'Product\CategoryController@BaseDelete'); //删除分类
        $api->get('/product/category/list', 'Product\CategoryController@BaseLimit'); //分类列表
        $api->post('/product/category/import', 'Product\CategoryController@Import'); //导入
        $api->get('/product/category/export', 'Product\CategoryController@Export'); //导出
        $api->get('/product/category/example', 'Product\CategoryController@Example'); //导出模版
        $api->get('/product/category/test', 'Product\CategoryController1@test');
        $api->post('/product/category/test', 'Product\CategoryController1@test');

        //商品品牌
        $api->post('/product/brands/add', 'Product\BrandsTenantController@BaseCreate'); //新增分类
        $api->get('/product/brands/info', 'Product\BrandsTenantController@BaseOne'); //分类详情
        $api->post('/product/brands/edit', 'Product\BrandsTenantController@BaseUpdate'); //修改分类
        $api->get('/product/brands/del', 'Product\BrandsTenantController@BaseDelete'); //删除分类
        $api->get('/product/brands/list', 'Product\BrandsTenantController@BaseLimit'); //分类列表
        $api->post('/product/brands/import', 'Product\BrandsTenantController@Import'); //导入
        $api->get('/product/brands/export', 'Product\BrandsTenantController@Export'); //导出
        $api->get('/product/brands/example', 'Product\BrandsTenantController@Example'); //导出模版
        $api->get('/product/brands/all', 'Product\BrandsTenantController@BaseAll'); //品牌所有

        // 商品系列
        $api->get('wms/series/export', 'Wms\SeriesController@export'); //导出
        $api->get('wms/series/template', 'Wms\SeriesController@template'); //导入模板
        $api->post('wms/series/import', 'Wms\SeriesController@import'); //导入
        $api->get('wms/series/del', 'Wms\SeriesController@delete'); //删除
        $api->get('wms/series/search', 'Wms\SeriesController@search'); //列表
        $api->get('wms/series/info', 'Wms\SeriesController@info'); //详情
        $api->post('wms/series/save', 'Wms\SeriesController@save'); //保存
        $api->post('wms/series/status', 'Wms\SeriesController@status'); //修改状态


        //商品
        $api->post('/product/product/add', 'Product\ProductController@BaseCreate'); //新增分类
        $api->get('/product/product/info', 'Product\ProductController@BaseOne'); //分类详情
        $api->post('/product/product/edit', 'Product\ProductController@BaseUpdate'); //修改分类
        $api->get('/product/product/del', 'Product\ProductController@BaseDelete'); //删除分类
        $api->get('/product/product/list', 'Product\ProductController@BaseLimit'); //分类列表
        $api->post('/product/product/import', 'Product\ProductController@Import'); //导入
        $api->get('/product/product/export', 'Product\ProductController@Export'); //导出
        $api->get('/product/product/example', 'Product\ProductController@Example'); //导出模版
        $api->post('/product/addNewBar', 'Product\ProductController@addNewBar'); //新品维护
        $api->get('/product/skuList', 'Product\SpecAndBarController@skuList'); //新品维护
        $api->get('/product/getProName', 'Product\ProductController@getProName'); //根据货号获取商品

        


        //条形码
        // $api->post('/product/specbar/add', 'Product\SpecAndBarController@BaseCreate'); //新增分类
        $api->get('/product/specbar/info', 'Product\SpecAndBarController@BaseOne'); //分类详情
        $api->post('/product/specbar/edit', 'Product\SpecAndBarController@BaseUpdate'); //修改分类
        $api->get('/product/specbar/del', 'Product\SpecAndBarController@BaseDelete'); //删除分类
        $api->get('/product/specbar/list', 'Product\SpecAndBarController@BaseLimit'); //分类列表
        $api->post('/product/specbar/import', 'Product\SpecAndBarController@Import'); //导入
        $api->get('/product/specbar/export', 'Product\SpecAndBarController@Export'); //导出
        $api->get('/product/specbar/example', 'Product\SpecAndBarController@Example'); //导出模版



        //仓库
        $api->post('/warehouse/add', 'Warehouse\WarehouseController@BaseCreate'); //新增仓库
        $api->get('/warehouse/info', 'Warehouse\WarehouseController@BaseOne'); //仓库详情
        $api->post('/warehouse/edit', 'Warehouse\WarehouseController@BaseUpdate'); //修改仓库
        $api->get('/warehouse/del', 'Warehouse\WarehouseController@BaseDelete'); //删除仓库
        $api->get('/warehouse/list', 'Warehouse\WarehouseController@BaseLimit'); //仓库列表
        $api->post('/warehouse/import', 'Warehouse\WarehouseController@Import'); //导入
        $api->get('/warehouse/export', 'Warehouse\WarehouseController@Export'); //导出
        $api->get('/warehouse/example', 'Warehouse\WarehouseController@Example'); //导出模版
        $api->get('/logproduct/list', 'Warehouse\WarehouseController@getLogProduct'); //导出模版
        $api->get('/area/all', 'Warehouse\WarehouseController@getWareArea'); //库区列表
        $api->get('/location/all', 'Warehouse\WarehouseController@getLocation'); //库区列表



        //位置码
        $api->post('/warehouse/location/add', 'Warehouse\LocationController@BaseCreate'); //新增位置码
        $api->get('/warehouse/location/info', 'Warehouse\LocationController@BaseOne'); //位置码详情
        $api->post('/warehouse/location/edit', 'Warehouse\LocationController@BaseUpdate'); //修改位置码
        $api->get('/warehouse/location/del', 'Warehouse\LocationController@BaseDelete'); //删除位置码
        $api->get('/warehouse/location/list', 'Warehouse\LocationController@BaseLimit'); //位置码列表
        $api->post('/warehouse/location/import', 'Warehouse\LocationController@Import'); //导入
        $api->get('/warehouse/location/export', 'Warehouse\LocationController@Export'); //导出
        $api->get('/warehouse/location/example', 'Warehouse\LocationController@Example'); //导出模版

        // 物流
        $api->get('wms/logistics/company/export', 'Wms\LogisticsController@companyExport'); //导出
        $api->get('wms/logistics/company/template', 'Wms\LogisticsController@companyTemplate'); //导入模板
        $api->post('wms/logistics/company/import', 'Wms\LogisticsController@companyImport'); //导入
        $api->get('wms/logistics/company', 'Wms\LogisticsController@companyIndex'); //列表
        $api->get('wms/logistics/company/{id}', 'Wms\LogisticsController@companyShow'); //详情
        $api->post('wms/logistics/company', 'Wms\LogisticsController@companyStore'); //保存
        $api->delete('wms/logistics/company', 'Wms\LogisticsController@companyDelete'); //删除

        // 物流产品
        $api->get('wms/logistics/product/export', 'Wms\LogisticsController@productExport'); //导出
        $api->get('wms/logistics/product/template', 'Wms\LogisticsController@productTemplate'); //导入模板
        $api->post('wms/logistics/product/import', 'Wms\LogisticsController@productImport'); //导入
        $api->get('wms/logistics/product', 'Wms\LogisticsController@productIndex'); //列表
        $api->get('wms/logistics/product/{id}', 'Wms\LogisticsController@productShow'); //详情
        $api->post('wms/logistics/product', 'Wms\LogisticsController@productStore'); //保存
        $api->delete('wms/logistics/product', 'Wms\LogisticsController@productDelete'); //删除
        $api->get('wms/logistics/all', 'Wms\LogisticsController@companyAll');

        // 仓库库位
        $api->get('wms/warehouse/area/export', 'Wms\WarehouseController@areaExport'); //导出
        $api->get('wms/warehouse/area/template', 'Wms\WarehouseController@template'); //导入模板
        $api->post('wms/warehouse/area/import', 'Wms\WarehouseController@areaImport'); //导入
        $api->get('wms/warehouse/area', 'Wms\WarehouseController@areaIndex'); //列表
        $api->get('wms/warehouse/area/{id}', 'Wms\WarehouseController@areaShow'); //详情
        $api->post('wms/warehouse/area', 'Wms\WarehouseController@areaStore'); //保存
        $api->delete('wms/warehouse/area', 'Wms\WarehouseController@areaDelete'); //删除


        //筛选方案
        $api->post('/setSift/add', 'Common\SetShifPlanController@BaseCreate'); //新增筛选方案
        $api->post('/setSift/edit', 'Common\SetShifPlanController@BaseUpdate'); //修改筛选方案
        $api->post('/setSift/del', 'Common\SetShifPlanController@BaseDelete'); //删除筛选方案
        $api->get('/setSift/list', 'Common\SetShifPlanController@BaseLimit'); //筛选方案列表
        $api->get('option/log', 'Common\CommonController@optionLog'); //操作日志列表



        //入库
        //到货登记
        $api->post('/inbound/arrival/add', 'Inbound\ArrivalRegistController@BaseCreate'); //新增到货登记
        $api->get('/inbound/arrival/info', 'Inbound\ArrivalRegistController@BaseOne'); //到货登记详情
        $api->post('/inbound/arrival/edit', 'Inbound\ArrivalRegistController@BaseUpdate'); //修改到货登记
        $api->get('/inbound/arrival/cancel', 'Inbound\ArrivalRegistController@cancel'); //到货登记作废
        $api->get('/inbound/arrival/list', 'Inbound\ArrivalRegistController@BaseLimit'); //到货登记列表
        $api->post('/inbound/arrival/import', 'Inbound\ArrivalRegistController@Import'); //导入
        $api->get('/inbound/arrival/export', 'Inbound\ArrivalRegistController@BuyExport'); //导出采购单
        $api->get('/inbound/arrival/consignExport', 'Inbound\ArrivalRegistController@consignExport'); //导出寄卖单
        $api->get('/inbound/arrival/arrExport', 'Inbound\ArrivalRegistController@Export'); //导出登记单
        $api->get('/inbound/arrival/example', 'Inbound\ArrivalRegistController@Example'); //导出模版
        $api->get('/warehouse/all', 'Warehouse\WarehouseController@BaseAll'); //仓库名称列表
        $api->get('/inbound/arrival/unicodep', 'Inbound\ArrivalRegistController@printUniqCode'); //打印唯一码
        $api->get('/inbound/arrival/log', 'Inbound\ArrivalRegistController@OptionLog'); //操作日志
        $api->post('/inbound/arrival/arrStatusDone', 'Inbound\ArrivalRegistController@arrStatusDone'); //到货完成
        $api->post('/inbound/arrival/supConfirm', 'Inbound\ArrivalRegistController@supConfirm'); //确认供应商
        $api->post('/inbound/arrival/ibMatch', 'Inbound\ArrivalRegistController@ibMatch'); //确认供应商
        $api->get('/inbound/arrival/ibList', 'Inbound\ArrivalRegistController@ibList'); //入库单详情
        // $api->post('/inbound/arrival/ibMatchadd', 'Inbound\IbOrderController@BaseCreate'); //确认供应商
        $api->get('/inbound/ibMlist', 'Inbound\IbOrderController@list'); //入库单列表
        $api->get('/inbound/iblist', 'Inbound\IbOrderController@BaseLimit'); //入库单列表
        $api->get('/inbound/ibinfo', 'Inbound\IbOrderController@BaseOne'); //入库单详情
        $api->post('/inbound/ibedit', 'Inbound\IbOrderController@BaseUpdate'); //入库单修改
        $api->get('/inbound/ibexport', 'Inbound\IbOrderController@ExportByMaatwebsite'); //入库单导出
        $api->get('/inbound/ibexample', 'Inbound\IbOrderController@Example'); //入库单导出
        $api->get('/inbound/arrival/del', 'Inbound\ArrivalRegistController@BaseDelete'); //到货登记删除
        $api->post('/inbound/arrival/purCost', 'Inbound\ArrivalRegistController@purchaseCost'); //到货登记确认采购成本
        $api->post('/inbound/arrival/editPurCost', 'Inbound\ArrivalRegistController@editPurCost'); //到货登记确认采购成本
        $api->get('/inbound/arrival/getPurCost', 'Inbound\ArrivalRegistController@getPurCost'); //到货登记确认采购成本
        $api->get('/inbound/arrival/checkQcAndPutway', 'Inbound\ArrivalRegistController@checkQcAndPutway'); //到货登记入库单匹配预检
        $api->post('/inbound/arrival/supEdit', 'Inbound\ArrivalRegistController@supEdit'); //修改供应商
        // $api->post('/inbound/arrival/supShowEdit', 'Inbound\ArrivalRegistController@supShowEdit'); //修改供应商列表
        $api->post('/inbound/arrival/flag', 'Inbound\ArrivalRegistController@editFlag'); //到货登记单修改旗帜

        //唯一码 UniqCodeController
        $api->get('/inbound/unicode/list', 'Inbound\UniqCodeController@BaseLimit'); //打印唯一码
        $api->post('/inbound/unicode/printOne', 'Inbound\UniqCodeController@printOne'); //打印唯一码
        $api->get('/inbound/unicode/example', 'Inbound\UniqCodeController@Example'); //唯一码导出模版
        $api->get('/inbound/unicode/export', 'Inbound\UniqCodeController@Export'); //导出唯一码
        //收货单
        $api->get('/recv/startScan', 'Inbound\ArrivalRegistController@recvPreCheck'); //开始收货
        $api->get('/recv/list', 'Inbound\RecvOrderController@BaseLimit'); //收货单列表
        $api->get('/recv/info', 'Inbound\RecvOrderController@BaseOne'); //收货单列表
        $api->post('/recv/scanning_goods', 'Inbound\RecvOrderController@BaseCreate'); //扫描收货
        $api->post('/recv/addOrdinary', 'Inbound\RecvOrderController@addOrdinary'); //普通商品收货
        $api->post('/recv/delByUniq', 'Inbound\RecvDetailController@delByUniq'); //扫描收货-减扫
        $api->post('/recv/reduceOrdinary', 'Inbound\RecvDetailController@delByOrdinary'); //普通产品减扫
        $api->post('/recv/addByOrdinary', 'Inbound\RecvDetailController@addByOrdinary'); //普通产品增加
        $api->post('/recv/delByBar', 'Inbound\RecvDetailController@delByBar'); //扫描收货-删除
        $api->post('/recv/done', 'Inbound\RecvOrderController@recvDone'); //收货完成
        $api->get('/recv/export', 'Inbound\RecvOrderController@Export'); //导出
        $api->get('/recv/example', 'Inbound\RecvOrderController@Example'); //导出模版


        //供应商
        $api->post('/supplier/add', 'Common\SupplierController@BaseCreate'); //新增供应商
        $api->get('/supplier/info', 'Common\SupplierController@BaseOne'); //供应商详情
        $api->post('/supplier/edit', 'Common\SupplierController@BaseUpdate'); //修改供应商
        // $api->get('/supplier/del', 'Common\SupplierController@BaseDelete'); //删除供应商
        $api->get('/supplier/list', 'Common\SupplierController@BaseLimit'); //供应商列表
        $api->post('/supplier/import', 'Common\SupplierController@Import'); //导入
        $api->get('/supplier/export', 'Common\SupplierController@Export'); //导出
        $api->get('/supplier/example', 'Common\SupplierController@Example'); //导出模版
        $api->post('/supplier/approved', 'Common\SupplierController@approved'); //供应商审核
        $api->get('/supplier/all', 'Common\SupplierController@searchAll'); //供应商
        $api->post('/supplier/add-doc', 'Common\SupplierController@addDoc'); //新增证件

        // 质检
        $api->post('/wms/qc/one-step', 'Wms\QcController@qcOneStep'); //收货单一键质检
        $api->post('/wms/qc/scan', 'Wms\QcController@scan'); //暂存
        $api->post('/wms/qc/submit', 'Wms\QcController@scanSubmit'); //暂存提交
        $api->get('/wms/qc/search', 'Wms\QcController@search'); //列表
        $api->get('/wms/qc/export', 'Wms\QcController@export'); //导出
        $api->get('/wms/qc/info', 'Wms\QcController@info'); //详情
        $api->get('/wms/qc/detail', 'Wms\QcController@skuDetail'); //明细
        $api->get('/wms/qc/detail-export', 'Wms\QcController@skuDetailExport'); //明细导出
        $api->post('/wms/qc/save', 'Wms\QcController@save'); //保存


        // 质检确认
        $api->post('/wms/qc-deffect/submit', 'Wms\QcConfirmController@submit'); //提交
        $api->post('/wms/qc-deffect/confirm', 'Wms\QcConfirmController@confirm'); //确认
        $api->post('/wms/qc-deffect/batch-confirm', 'Wms\QcConfirmController@batchConfirm'); //批量确认
        $api->get('/wms/qc-deffect/search', 'Wms\QcConfirmController@search'); //列表
        $api->get('/wms/qc-deffect/export', 'Wms\QcConfirmController@export'); //导出
        $api->get('/wms/qc-deffect/info', 'Wms\QcConfirmController@info'); //详情
        $api->post('/wms/qc-deffect/save', 'Wms\QcConfirmController@save'); //保存

        // 上架
        $api->post('/wms/putaway/scan', 'Wms\PutawayController@scan'); //扫描
        $api->post('/wms/putaway/submit', 'Wms\PutawayController@submit'); //提交
        $api->get('/wms/putaway/search', 'Wms\PutawayController@search'); //列表
        $api->get('/wms/putaway/export', 'Wms\PutawayController@export'); //导出
        $api->get('/wms/putaway/info', 'Wms\PutawayController@info'); //详情
        $api->get('/wms/putaway/detail', 'Wms\PutawayController@detail'); //sku明细
        $api->get('/wms/putaway/detail-export', 'Wms\PutawayController@detailExport'); //sku明细导出
        $api->post('/wms/putaway/save', 'Wms\PutawayController@save'); //保存

        // 波次分组
        $api->get('/wms/allocation/strategy/search', 'Wms\AllocationController@strategyIndex'); //列表
        $api->get('/wms/allocation/strategy/export', 'Wms\AllocationController@strategyExport'); //导出
        $api->post('/wms/allocation/strategy/save', 'Wms\AllocationController@strategySave'); //保存
        $api->post('/wms/allocation/strategy/status', 'Wms\AllocationController@strategyStatus'); //更新启用状态
        $api->get('/wms/allocation/strategy/{id}', 'Wms\AllocationController@strategyShow'); //详情
        $api->delete('/wms/allocation/strategy/{id}', 'Wms\AllocationController@strategyDelete'); //删除

        // 配货池
        $api->get('/wms/allocation/pool', 'Wms\AllocationController@pool'); //配货池
        $api->get('/wms/allocation/pool/export', 'Wms\AllocationController@poolExport'); //导出
        $api->get('/wms/allocation/task/pending', 'Wms\AllocationController@pendingTask'); //待领取任务
        $api->post('/wms/allocation/task/get-by-code', 'Wms\AllocationController@getTaskByCode'); //根据配货订单领取任务
        $api->post('/wms/allocation/task/get-by-group', 'Wms\AllocationController@getTaskByGroup'); //根据分组领取配货任务
        $api->post('/wms/allocation/task/cancel', 'Wms\AllocationController@taskCancel'); //取消领取

        // 配货任务
        $api->get('/wms/allocation/task/search', 'Wms\AllocationController@taskIndex'); //列表
        $api->get('/wms/allocation/task/export', 'Wms\AllocationController@taskExport'); //导出
        $api->get('/wms/allocation/task/{id}', 'Wms\AllocationController@taskShow'); //详情
        $api->post('/wms/allocation/task/get-custom', 'Wms\AllocationController@getCustom'); //自定义领取任务
        $api->post('/wms/allocation/task/allocate', 'Wms\AllocationController@allocate'); //配货
        $api->post('/wms/allocation/task/done', 'Wms\AllocationController@taskDone'); //配货完成
        $api->post('/wms/allocation/task/save', 'Wms\AllocationController@taskSave'); //保存
        $api->post('/wms/allocation/task/sendout', 'Wms\AllocationController@taskSendOut'); //批量发货

        // 复核
        $api->post('/wms/allocation/review/order', 'Wms\AllocationController@reviewByOrder'); //按单复核
        $api->get('/wms/allocation/review/detail', 'Wms\AllocationController@reviewDetail'); //发货明细
        $api->post('/wms/allocation/review/order/sendout', 'Wms\AllocationController@sendOutByOrder'); //按单确认发货
        $api->get('/wms/allocation/review/whole-order/info', 'Wms\AllocationController@reviewInfoByWholeOrder'); //整单复核页面信息
        $api->post('/wms/allocation/review/whole-order/sendout', 'Wms\AllocationController@sendOutByWholeOrder'); //整单确认发货

        // 出库取消单
        $api->post('/wms/allocation/cancel', 'Wms\AllocationController@cancel'); //出库取消
        $api->get('/wms/allocation/cancel/search', 'Wms\AllocationController@cancelIndex'); //列表
        $api->get('/wms/allocation/cancel/export', 'Wms\AllocationController@cancelExport'); //导出
        $api->post('/wms/allocation/cancel/putaway', 'Wms\AllocationController@cancelPutaway'); //上架
        $api->post('/wms/allocation/cancel/putaway-confirm', 'Wms\AllocationController@cancelPutawayConfirm'); //上架完成
        $api->get('/wms/allocation/cancel/wait-putaway-detail', 'Wms\AllocationController@cancelWaitPutaway'); //待上架商品明细
        $api->get('/wms/allocation/cancel/{id}', 'Wms\AllocationController@cancelDetail'); //详情

        // 库存
        $api->get('/inv/list', 'Inventory\InventoryController@list'); //库存列表
        $api->get('/inv/export', 'Inventory\InventoryController@invExport'); //库存导出
        $api->get('/inv/invdetail', 'Inventory\InventoryController@invDetail'); //库存详情列表
        $api->get('/inv/uniq/list', 'Inventory\InventoryController@uniqCodeList'); //唯一码明细
        $api->get('/inv/uniq/export', 'Inventory\InventoryController@uniqCodeExport'); //唯一码明细
        $api->get('/inv/supplier', 'Inventory\InventoryController@supplierInv'); //供应商产品库存
        $api->get('/inv/supplier2', 'Inventory\InventoryController@supplierInvV2'); //供应商产品库存
        $api->get('/inv/putaway', 'Inventory\InventoryController@putawayInv'); //供应商产品库存
        $api->get('/inv/uniq', 'Inventory\InventoryController@uniqInv'); //供应商产品库存

        // 总库存流水
        $api->get('/wms/stock/search', 'Wms\StockController@logSearch'); //列表
        $api->get('/wms/stock/export', 'Wms\StockController@logExport'); //导出
        $api->get('/wms/stock/type', 'Wms\StockController@logType'); //导出

        // 盘点申请单
        $api->get('/wms/check/request/search', 'Wms\CheckRequestController@search'); //列表
        $api->get('/wms/check/request/export', 'Wms\CheckRequestController@export'); //导出
        $api->get('/wms/check/request/info', 'Wms\CheckRequestController@info'); //详情
        $api->get('/wms/check/request/detail', 'Wms\CheckRequestController@detail'); //明细
        $api->post('/wms/check/request/save', 'Wms\CheckRequestController@save'); //新增/修改
        $api->post('/wms/check/request/submit', 'Wms\CheckRequestController@submit'); //提交
        $api->post('/wms/check/request/revoke', 'Wms\CheckRequestController@revoke'); //撤回
        $api->post('/wms/check/request/audit', 'Wms\CheckRequestController@audit'); //审核
        $api->post('/wms/check/request/cancel', 'Wms\CheckRequestController@cancel'); //取消
        $api->delete('/wms/check/request/{id}', 'Wms\CheckRequestController@delete'); //删除
        $api->post('/wms/check/request/send', 'Wms\CheckRequestController@send'); //下发
        $api->post('/wms/check/request/second', 'Wms\CheckRequestController@second'); //复盘
        $api->get('/wms/check/request/difference', 'Wms\CheckRequestController@difference'); //差异处理列表
        $api->post('/wms/check/request/report', 'Wms\CheckRequestController@report'); //上报
        $api->post('/wms/check/request/recover', 'Wms\CheckRequestController@recover'); //少货寻回

        // 盘点单
        $api->get('/wms/check/search', 'Wms\CheckController@search'); //列表
        $api->get('/wms/check/export', 'Wms\CheckController@export'); //导出
        $api->delete('/wms/check/{id}', 'Wms\CheckController@delete'); //删除
        $api->get('/wms/check/info', 'Wms\CheckController@info'); //盘点详情
        $api->get('/wms/check/detail', 'Wms\CheckController@detail'); //明细
        $api->post('/wms/check/scan', 'Wms\CheckController@scan'); //盘点扫描
        $api->post('/wms/check/confirm', 'Wms\CheckController@confirm'); //盘点确认
        $api->post('/wms/check/save', 'Wms\CheckController@save'); //保存

        //差异处理记录
        $api->get('/wms/check/difference/search', 'Wms\DifferenceController@search'); //列表
        $api->get('/wms/check/difference/export', 'Wms\DifferenceController@export'); //导出
        $api->get('/wms/check/difference/info', 'Wms\DifferenceController@info'); //详情
        $api->get('/wms/check/difference/detail', 'Wms\DifferenceController@detail'); //明细
        $api->post('/wms/check/difference/save', 'Wms\DifferenceController@save'); //保存
        $api->post('/wms/check/difference/audit', 'Wms\DifferenceController@audit'); //审核

        // 盘盈亏单
        $api->get('/wms/check/bill/search', 'Wms\BillController@search'); //列表
        $api->get('/wms/check/bill/export', 'Wms\BillController@export'); //导出
        $api->get('/wms/check/bill/info', 'Wms\BillController@info'); //详情
        $api->get('/wms/check/bill/detail', 'Wms\BillController@detail'); //明细
        $api->post('/wms/check/bill/save', 'Wms\BillController@save'); //保存

        // 移位单
        $api->post('/wms/move/save', 'Wms\MoveController@save'); //保存
        $api->post('/wms/move/revoke', 'Wms\MoveController@revoke'); //撤回
        $api->delete('/wms/move/{id}', 'Wms\MoveController@delete'); //删除
        $api->post('/wms/move/submit', 'Wms\MoveController@submit'); //提交
        $api->post('/wms/move/audit', 'Wms\MoveController@audit'); //审核
        $api->post('/wms/move/cancel', 'Wms\MoveController@cancel'); //撤销
        $api->get('/wms/move/search', 'Wms\MoveController@search'); //列表
        $api->get('/wms/move/export', 'Wms\MoveController@export'); //导出
        $api->get('/wms/move/info', 'Wms\MoveController@info'); //详情
        $api->get('/wms/move/detail', 'Wms\MoveController@detail'); //明细
        $api->post('/wms/move/takedown/scan', 'Wms\MoveController@takedown'); //扫描下架
        $api->post('/wms/move/takedown/confirm', 'Wms\MoveController@takedownConfirm'); //确认下架
        $api->post('/wms/move/shelf/scan', 'Wms\MoveController@shelf'); //扫描上架
        $api->post('/wms/move/shelf/confirm', 'Wms\MoveController@shelfConfirm'); //一键上架

        $api->post('/wms/init/user', 'Wms\CommonController@userImport'); //导入用户
        $api->post('/wms/init/role', 'Wms\CommonController@roleImport'); //导入角色
        $api->post('/wms/init/suplier', 'Wms\CommonController@suplierImport'); //导入供应商
        $api->post('/wms/init/warehouse', 'Wms\CommonController@warehouseImport'); //仓库
        $api->post('/wms/init/area', 'Wms\CommonController@areaImport'); //导入库区
        $api->post('/wms/init/location_code', 'Wms\CommonController@locationCodeImport'); //导入位置码
        $api->post('/wms/init/stock', 'Wms\CommonController@skuDetailmport'); //导入库存明细
        $api->post('/wms/init/stocklog', 'Wms\CommonController@stockLogImport'); //导入仓库库存流水


        //出库
        $api->get('/outbound/test', 'Outbound\ObOrderController@test'); //出库单列表

        $api->get('/outbound/list', 'Outbound\ObOrderController@BaseLimit'); //出库单列表
        $api->post('/outbound/edit', 'Outbound\ObOrderController@BaseUpdate'); //出库单保存
        $api->post('/outbound/pause', 'Outbound\ObOrderController@pause'); //出库单暂停
        $api->post('/outbound/recovery', 'Outbound\ObOrderController@recovery'); //出库单暂停
        $api->post('/outbound/obimport', 'Outbound\ObOrderController@Import'); //出库单导入
        $api->get('/outbound/obexport', 'Outbound\ObOrderController@Export'); //出库单导出
        $api->get('/outbound/obexample', 'Outbound\ObOrderController@Example'); //出库单导出
        $api->post('/outbound/reTask', 'Outbound\ObOrderController@reTask'); //出库单重配
        $api->get('/outbound/info', 'Outbound\ObOrderController@getInfo'); //出库单详情



        //配货策略
        $api->post('/strategy/add', 'Outbound\PreStrategyController@BaseCreate'); //新增策略
        $api->get('/strategy/list', 'Outbound\PreStrategyController@BaseLimit'); //策略列表
        $api->post('/strategy/edit', 'Outbound\PreStrategyController@BaseUpdate'); //修改策略
        $api->get('/strategy/del', 'Outbound\PreStrategyController@BaseDelete'); //删除策略
        $api->get('/strategy/export', 'Outbound\PreStrategyController@Export'); //导出策略

        //配货订单
        $api->get('/prelists/list', 'Outbound\preListsController@BaseLimit'); //配货订单列表
        $api->get('/prelists/info', 'Outbound\preListsController@BaseOne'); //配货订单列表
        $api->get('/prelists/export', 'Outbound\preListsController@Export'); //配货订单列表
        $api->post('/prelists/toPool', 'Outbound\preListsController@toPool'); //流入配货池

        $api->get('/prindEd', 'Outbound\preListsController@printEdNo'); //打印快递单

        //逐件复核
        $api->post('/outbound/reviewOne', 'Outbound\ReviewOneController@review'); //逐渐复核
        $api->post('/outbound/sendGoods', 'Outbound\ReviewOneController@sendGoods'); //测试预配明细
        $api->post('/outbound/reviewReset', 'Outbound\ReviewOneController@reviewReset'); //复核重置

        //发货单
        $api->get('/send/list', 'Outbound\ShippingOrdersController@BaseLimit'); //发货单列表
        $api->get('/send/info', 'Outbound\ShippingOrdersController@BaseOne'); //发货单详情
        $api->get('/send/export', 'Outbound\ShippingOrdersController@Export'); //发货单列表

        //调拨申请单
        $api->post('/transfer/add', 'Inventory\TransferOrderController@add'); //调拨申请单新增
        $api->post('/transfer/edit', 'Inventory\TransferOrderController@BaseUpdate'); //调拨申请单新增
        $api->get('/transfer/list', 'Inventory\TransferOrderController@BaseLimit'); //调拨申请单新增
        $api->get('/transfer/info', 'Inventory\TransferOrderController@BaseOne'); //调拨申请单新增
        $api->post('/transfer/del', 'Inventory\TransferOrderController@del'); //调拨申请单删除
        $api->post('/transfer/submit', 'Inventory\TransferOrderController@submit'); //调拨申请单提交
        $api->post('/transfer/withdraw', 'Inventory\TransferOrderController@withdraw'); //调拨申请单撤回
        $api->post('/transfer/approve', 'Inventory\TransferOrderController@approve'); //调拨申请单审核
        $api->post('/transfer/pause', 'Inventory\TransferOrderController@pause'); //调拨申请单审核
        $api->post('/transfer/recovery', 'Inventory\TransferOrderController@recovery'); //调拨申请单审核
        $api->post('/transfer/cancel', 'Inventory\TransferOrderController@cancel'); //调拨申请单审核
        $api->get('/transfer/export', 'Inventory\TransferOrderController@Export'); //调拨申请单审核
        $api->post('/transfer/flag', 'Inventory\TransferOrderController@editFlag'); //调拨申请单旗帜修改

        //其他出库单
        $api->post('/oob/add', 'Inventory\OtherObOrderController@add'); //其他出库单新增
        $api->post('/oob/edit', 'Inventory\OtherObOrderController@BaseUpdate'); //其他出库单新增
        $api->get('/oob/info', 'Inventory\OtherObOrderController@BaseOne'); //其他出库单新增
        $api->get('/oob/list', 'Inventory\OtherObOrderController@BaseLimit'); //其他出库单新增
        $api->post('/oob/del', 'Inventory\OtherObOrderController@del'); //其他出库单删除
        $api->post('/oob/submit', 'Inventory\OtherObOrderController@submit'); //其他出库单提交
        $api->post('/oob/withdraw', 'Inventory\OtherObOrderController@withdraw'); //其他出库单撤回
        $api->post('/oob/approve', 'Inventory\OtherObOrderController@approve'); //其他出库单审核
        $api->post('/oob/pause', 'Inventory\OtherObOrderController@pause'); //其他出库单审核
        $api->post('/oob/recovery', 'Inventory\OtherObOrderController@recovery'); //其他出库单审核
        $api->post('/oob/cancel', 'Inventory\OtherObOrderController@cancel'); //其他出库单审核
        $api->get('/oob/export', 'Inventory\OtherObOrderController@Export'); //其他出库单审核

        //其他入库单
        $api->post('/oib/add', 'Inventory\OtherIbOrderController@add'); //其他入库单新增
        $api->post('/oib/edit', 'Inventory\OtherIbOrderController@BaseUpdate'); //其他入库单新增
        $api->get('/oib/list', 'Inventory\OtherIbOrderController@BaseLimit'); //其他入库单新增
        $api->post('/oib/del', 'Inventory\OtherIbOrderController@del'); //其他入库单删除
        $api->post('/oib/submit', 'Inventory\OtherIbOrderController@submit'); //其他入库单提交
        $api->post('/oib/withdraw', 'Inventory\OtherIbOrderController@withdraw'); //其他入库单撤回
        $api->post('/oib/approve', 'Inventory\OtherIbOrderController@approve'); //其他入库单审核
        $api->post('/oib/pause', 'Inventory\OtherIbOrderController@pause'); //其他入库单审核
        $api->post('/oib/recovery', 'Inventory\OtherIbOrderController@recovery'); //其他入库单审核
        $api->post('/oib/cancel', 'Inventory\OtherIbOrderController@cancel'); //其他入库单审核
        $api->get('/oib/export', 'Inventory\OtherIbOrderController@Export'); //其他入库单审核



        // 店铺
        $api->post('/wms/shop/save', 'Wms\ShopController@save'); //保存
        $api->get('/wms/shop/search', 'Wms\ShopController@search'); //列表
        $api->get('/wms/shop/template', 'Wms\ShopController@template'); //导入模板
        $api->get('/wms/shop/export', 'Wms\ShopController@export'); //导出
        $api->post('/wms/shop/import', 'Wms\ShopController@import'); //导入
        $api->delete('/wms/shop/{id}', 'Wms\ShopController@delete'); //删除
        $api->post('/wms/shop/status', 'Wms\ShopController@status'); //修改状态
        $api->get('/wms/shop/info', 'Wms\ShopController@info'); //详情


        // 销售单
        $api->post('/wms/order/add', 'Wms\OrderController@add'); //新增
        $api->post('/wms/order/copy-add', 'Wms\OrderController@copyAdd'); //复制新增
        $api->post('/wms/order/save', 'Wms\OrderController@save'); //保存
        $api->post('/wms/order/revoke', 'Wms\OrderController@revoke'); //撤回
        $api->delete('/wms/order/{id}', 'Wms\OrderController@delete'); //删除
        $api->post('/wms/order/submit', 'Wms\OrderController@submit'); //提交
        $api->post('/wms/order/audit', 'Wms\OrderController@audit'); //审核
        $api->post('/wms/order/cancel', 'Wms\OrderController@cancel'); //取消
        $api->post('/wms/order/pause', 'Wms\OrderController@pause'); //暂停
        $api->post('/wms/order/recover', 'Wms\OrderController@recovery'); //恢复
        $api->post('/wms/order/assign', 'Wms\OrderController@assign'); //指定配货
        $api->get('/wms/order/templete', 'Wms\OrderController@templete'); //导入模板
        $api->post('/wms/order/import', 'Wms\OrderController@import'); //导入
        $api->get('/wms/order/search', 'Wms\OrderController@search'); //列表
        $api->get('/wms/order/export', 'Wms\OrderController@export'); //导出
        $api->get('/wms/order/info', 'Wms\OrderController@info'); //详情
        $api->get('/wms/order/detail', 'Wms\OrderController@detail'); //明细
        $api->get('/wms/order/after-sale/detail', 'Wms\OrderController@afterSaleDetail'); //退款登记明细
        $api->post('/wms/order/after-sale', 'Wms\OrderController@afterSale'); //退款登记
        $api->post('/wms/order/flag', 'Wms\OrderController@editFlag'); //调拨申请单旗帜修改
        $api->post('/wms/order/message', 'Wms\OrderController@editMessage'); //调拨申请单旗帜修改

        // 销售结算账单
        $api->post('/wms/order/settle', 'Wms\OrderController@settle'); //结算
        $api->get('/wms/order/settle/search', 'Wms\OrderController@statementSearch'); //列表
        $api->get('/wms/order/settle/export', 'Wms\OrderController@statementExport'); //导出

        // 销售发货明细汇总
        $api->get('/wms/order/summary-search', 'Wms\OrderController@summarySearch'); //列表
        $api->get('/wms/order/summary-export', 'Wms\OrderController@summaryExport'); //导出

        //采购单
        $api->post('/buy/add', 'Inbound\PurchaseOrdersController@add'); //采购单新增
        $api->post('/buy/edit', 'Inbound\PurchaseOrdersController@BaseUpdate'); //采购单更新
        $api->get('/buy/list', 'Inbound\PurchaseOrdersController@BaseLimit'); //采购单列表
        $api->get('/buy/info', 'Inbound\PurchaseOrdersController@BaseOne'); //采购单列表
        $api->post('/buy/del', 'Inbound\PurchaseOrdersController@del'); //采购单删除
        $api->post('/buy/submit', 'Inbound\PurchaseOrdersController@submit'); //采购单提交
        $api->post('/buy/withdraw', 'Inbound\PurchaseOrdersController@withdraw'); //采购单撤回
        $api->post('/buy/approve', 'Inbound\PurchaseOrdersController@approve'); //采购单审核
        $api->post('/buy/pause', 'Inbound\PurchaseOrdersController@pause'); //采购单审核
        $api->post('/buy/recovery', 'Inbound\PurchaseOrdersController@recovery'); //采购单审核
        $api->post('/buy/cancel', 'Inbound\PurchaseOrdersController@cancel'); //采购单审核
        $api->get('/buy/export', 'Inbound\PurchaseOrdersController@ExportByMaatwebsite'); //采购单审核
        $api->post('/buy/import', 'Inbound\PurchaseOrdersController@Import'); //采购单审核
        $api->post('/buy/test', 'Inbound\PurchaseOrdersController@test'); //采购单审核
        $api->post('/buy/flag', 'Inbound\PurchaseOrdersController@editFlag');

        //采购结算单
        $api->get('/buy/settle/list', 'Inbound\PurchaseStatementsController@BaseLimit'); //采购结算单列表
        $api->post('/buy/settle', 'Inbound\PurchaseStatementsController@settle'); //采购结算
        $api->get('/buy/settle/export', 'Inbound\PurchaseStatementsController@export'); //采购结算导出
        $api->post('/buy/settle/editRemark', 'Inbound\PurchaseStatementsController@editRemark'); //采购结算备注
        //售后工单
        $api->post('/aftersale/add', 'Outbound\AfterSaleController@add'); //售后工单新增
        $api->post('/aftersale/edit', 'Outbound\AfterSaleController@BaseUpdate'); //售后工单修改
        $api->get('/aftersale/list', 'Outbound\AfterSaleController@BaseLimit'); //售后工单列表
        $api->post('/aftersale/del', 'Outbound\AfterSaleController@del'); //售后工单删除
        $api->post('/aftersale/submit', 'Outbound\AfterSaleController@submit'); //售后工单提交
        $api->post('/aftersale/withdraw', 'Outbound\AfterSaleController@withdraw'); //售后工单撤回
        $api->post('/aftersale/approve', 'Outbound\AfterSaleController@approve'); //售后工单审核
        $api->post('/aftersale/confirm', 'Outbound\AfterSaleController@confirm'); //售后工单确认退款
        $api->post('/aftersale/recovery', 'Outbound\AfterSaleController@recoverIb'); //售后工单发货追回
        $api->post('/aftersale/return', 'Outbound\AfterSaleController@returnIb'); //售后工单退货入库
        // $api->post('/aftersale/cancel', 'Outbound\AfterSaleController@cancel'); //售后工单审核
        $api->get('/aftersale/export', 'Outbound\AfterSaleController@Export'); //售后工单审核
        $api->get('/aftersale/reasons', 'Outbound\AfterSaleController@reasonList'); //售后工单原因列表
        $api->get('/aftersale/export', 'Outbound\AfterSaleController@Export'); //售后工单原因列表

        //首页
        $api->get('/home/shop', 'Common\HomeController@shop'); //首页-店铺
        $api->get('/home/warehouse', 'Common\HomeController@warehouse'); //首页-店铺
        $api->get('/home/shop/todolist', 'Common\HomeController@shopToDoList'); //首页-店铺-待办事项
        $api->get('/home/shop/amount', 'Common\HomeController@shopAmount'); //首页-店铺-订单金额&退款金额
        $api->get('/home/shop/buyer', 'Common\HomeController@shopBuyer'); //首页-店铺-平均客单&客户人数
        $api->get('/home/shop/top', 'Common\HomeController@top'); //首页-店铺-排行榜

        // 采购汇总
        $api->get('/wms/buy/summary-search', 'Wms\PurchaseController@summarySearch'); //列表
        $api->get('/wms/buy/summary-export', 'Wms\PurchaseController@summaryExport'); //导出

        // 文件管理
        $api->get('/wms/file/dirs', 'Wms\FileController@dirs'); //新建文件夹
        $api->get('/wms/file/list', 'Wms\FileController@list'); //文件列表
        $api->post('/wms/file/dir', 'Wms\FileController@newDir'); //新建文件夹
        $api->post('/wms/file/upload', 'Wms\FileController@upload'); //上传文件
        $api->post('/wms/file/del', 'Wms\FileController@del'); //删除文件/文件夹

        //版本管理
        $api->post('/wms/version/add', 'Common\VersionController@BaseCreate'); //新增
        $api->get('/wms/version/del', 'Common\VersionController@BaseDelete'); //删除
        $api->post('/wms/version/edit', 'Common\VersionController@BaseUpdate'); //修改
        $api->get('/wms/version/list', 'Common\VersionController@BaseLimit'); //列表

        // 数据权限
        $api->get('/wms/permission/org', 'Wms\DataPermissionController@org'); //组织列表
        $api->get('/wms/permission/search', 'Wms\DataPermissionController@search'); //查询
        $api->get('/wms/permission/export', 'Wms\DataPermissionController@export'); //导出
        $api->get('/wms/permission/info', 'Wms\DataPermissionController@info'); //详情
        $api->get('/wms/permission/users', 'Wms\DataPermissionController@users'); //用户列表
        $api->post('/wms/permission/add', 'Wms\DataPermissionController@add'); //新增
        $api->post('/wms/permission/save', 'Wms\DataPermissionController@save'); //更新
        $api->post('/wms/permission/del', 'Wms\DataPermissionController@del'); //删除
        $api->post('/wms/permission/authorize', 'Wms\DataPermissionController@authorizeUser'); //用户授权
        $api->post('/wms/permission/status', 'Wms\DataPermissionController@statusUpdate'); //状态更新
        $api->post('/wms/permission/authdel', 'Wms\DataPermissionController@authDel'); //删除授权

        //寄卖单
        $api->post('/consign/add', 'Inbound\ConsignmentController@add'); //寄卖单新增
        $api->post('/consign/edit', 'Inbound\ConsignmentController@BaseUpdate'); //寄卖单更新
        $api->get('/consign/list', 'Inbound\ConsignmentController@BaseLimit'); //寄卖单列表
        $api->get('/consign/info', 'Inbound\ConsignmentController@BaseOne'); //寄卖单详情
        $api->post('/consign/del', 'Inbound\ConsignmentController@del'); //寄卖单删除
        $api->post('/consign/submit', 'Inbound\ConsignmentController@submit'); //寄卖单提交
        $api->post('/consign/withdraw', 'Inbound\ConsignmentController@withdraw'); //寄卖单撤回
        $api->post('/consign/approve', 'Inbound\ConsignmentController@approve'); //寄卖单审核
        $api->post('/consign/cancel', 'Inbound\ConsignmentController@cancel'); //寄卖单取消
        $api->get('/consign/export', 'Inbound\ConsignmentController@ExportByMaatwebsite'); //导出
        $api->get('/consign/oExample', 'Inbound\ConsignmentController@oExample'); //导出模板
        $api->post('/consign/import', 'Inbound\ConsignmentController@Import'); //导入
        $api->get('/consign/iExample', 'Inbound\ConsignmentController@iExample'); //导入模板
        $api->post('/consign/flag', 'Inbound\ConsignmentController@editFlag');
        

        //产品库存明细
        $api->get('/supinv/list', 'Inventory\SupInvController@BaseLimit'); //产品库存明细
        $api->get('/supinv/export', 'Inventory\SupInvController@Export'); //产品库存导出

        //可售库存明细
        $api->get('/saleinv/list', 'Inventory\SupInvController@saleList'); //产品库存明细
        $api->get('/saleinv/export', 'Inventory\SupInvController@saleExport'); //产品库存导出

        //寄卖结算规则
        $api->post('/consign/settle/rule/add', 'Inbound\ConsignSettleRuleController@BaseCreate'); //新增寄卖结算规则
        $api->get('/consign/settle/rule/list', 'Inbound\ConsignSettleRuleController@BaseLimit'); //寄卖结算规则列表
        $api->get('/consign/settle/rule/info', 'Inbound\ConsignSettleRuleController@BaseOne'); //寄卖结算规则列表
        $api->post('/consign/settle/rule/edit', 'Inbound\ConsignSettleRuleController@BaseUpdate'); //修改寄卖结算规则
        $api->get('/consign/settle/rule/del', 'Inbound\ConsignSettleRuleController@BaseDelete'); //删除寄卖结算规则
        $api->get('/consign/settle/rule/export', 'Inbound\ConsignSettleRuleController@Export'); //导出寄卖结算规则
        $api->get('/consign/settle/rule/getColumn', 'Inbound\ConsignSettleRuleController@getRuleColumn'); //寄卖结算规则
        $api->get('/consign/settle/rule/test', 'Inbound\ConsignSettleRuleController@test'); //导出寄卖结算规则

        
        //寄卖结算分类
        $api->post('/consign/settle/category/add', 'Inbound\ConsignSettleCategoryController@BaseCreate'); //新增寄卖结算分类
        $api->get('/consign/settle/category/tree', 'Inbound\ConsignSettleCategoryController@getTreeList'); //新增寄卖结算分类
        $api->get('/consign/settle/category/list', 'Inbound\ConsignSettleCategoryController@BaseLimit'); //寄卖结算分类列表
        $api->post('/consign/settle/category/edit', 'Inbound\ConsignSettleCategoryController@BaseUpdate'); //修改寄卖结算分类
        $api->get('/consign/settle/category/del', 'Inbound\ConsignSettleCategoryController@BaseDelete'); //删除寄卖结算分类

        // 寄卖结算账单
        $api->get('/wms/consigment/bill-search', 'Wms\ConsigmentController@billSearch'); //查询
        $api->get('/wms/consigment/bill-export', 'Wms\ConsigmentController@billExport'); //导出
        $api->post('/wms/consigment/assign-amount', 'Wms\ConsigmentController@assignAmount'); //指定结算金额
        $api->post('/wms/consigment/assign-rule', 'Wms\ConsigmentController@assignRule'); //指定结算规则
        $api->get('/wms/consigment/apply-detail', 'Wms\ConsigmentController@withdrawApplyDetail'); //申请提现明细
        $api->post('/wms/consigment/withdraw-apply', 'Wms\ConsigmentController@withdrawApply'); //提现申请

        // 提现申请单
        $api->get('/wms/consigment/withdraw-search', 'Wms\ConsigmentController@withdrawSearch'); //查询
        $api->get('/wms/consigment/withdraw-export', 'Wms\ConsigmentController@withdrawExport'); //导出
        $api->get('/wms/consigment/withdraw-info', 'Wms\ConsigmentController@withdrawInfo'); //详情
        $api->post('/wms/consigment/withdraw-audit', 'Wms\ConsigmentController@withdrawAudit'); //审核

    });
    //回调处理
    $api->post('/callback/dw', 'CarryMe\CallbackController@dw'); //得物
    $api->post('/callback/dw-test', 'CarryMe\CallbackController@dw2'); //得物
    $api->post('/callback/goat-test', 'CarryMe\CallbackController@goatTest');
    $api->post('/callback/stockx-test', 'CarryMe\CallbackController@stockxTest');

    $api->any('/test', 'Admin\TestController@test'); //得物



    $api->group(['middleware' => 'api-sign', 'namespace' => 'CarryMe'], function ($api) {

        $api->post('/callback/carryme', 'CallbackController@carryme'); //CARRYME回调

        $api->get('/channels', 'CommonController@channels'); //渠道列表
        $api->get('/product/info', 'CommonController@product'); //渠道列表
        $api->get('/product/lowest_price/sync', 'CommonController@syncLowestPrice'); //同步商品最低价

        $api->post('/bid/add', 'BidAsyncController@bid'); //出价
        $api->post('/bid/add-by-productsn', 'BidAsyncController@batchBidByProductSn'); //按货号批量出价
        $api->post('/bid/cancel', 'BidAsyncController@cancel'); //取消出价
        $api->post('/bid/cancel-item', 'BidAsyncController@cancelSingle'); //取消出价
        $api->post('/bid/batch-cancel', 'BidAsyncController@batchCancel'); //批量取消
        $api->post('/bid/cancel-by-productsn', 'BidAsyncController@batchCancelByProductSn'); //按货号批量取消出价
        $api->post('/order/business-confirm', 'OrderController@businessConfirm'); //商家确认发货
        $api->post('/order/platform-confirm', 'OrderController@platformConfirm'); //平台确认发货
        $api->post('/order/unmatch', 'OrderController@unmatch'); //订单未匹配

        $api->post('/bid/search', 'BidAsyncController@search'); //出价信息查询
        $api->post('/bid/result-compensation', 'BidAsyncController@resultCompensation'); //出价结果补偿
    });


    //mock数据
    $api->any('/mock/{path}', 'MockController@mock')->where('path', '.*');

    // 商品库存信息
    $api->group(['middleware' => 'api-sign', 'namespace' => 'CustomerService', "prefix" => 'cs'], function ($api) {

        $api->get('/products', 'ProductController@product'); //商品信息
        $api->get('/product/stock', 'ProductController@stock'); //商品库存
        $api->get('/product/detail', 'ProductController@detail'); //商品出入库明细
    });

    // PDA接口
    $api->group(['middleware' => 'api-sign', "prefix" => 'pda'], function ($api) {

        $api->group(['namespace' => 'PDA'], function ($api) {
            $api->post('/login', 'AuthController@login'); //登录
            $api->post('/logout', 'AuthController@logout'); //退出登录
            $api->post('/user/upload', 'UserController@upload'); //上传文件

            $api->get('/home/warehouse', 'HomeController@warehouse'); //仓库列表
            $api->post('/home/warehouse-change', 'HomeController@warehouseChange'); //切换仓库
            $api->get('/home/info', 'HomeController@info'); //主页信息

            $api->post('/arr/add', 'ArrivalRegistController@add'); //到货登记新增
            $api->get('/arr/info', 'ArrivalRegistController@info'); //到货登记详情
            $api->get('/recv/one/list', 'RecvByItemController@list'); //逐渐收货登记单列表
            $api->get('/recv/one/startScan', 'RecvByItemController@startScan'); //逐渐收货收货预检
            $api->group(["prefix" => 'v2'], function ($api) {
                $api->get('/arr/scan-detail', 'ArrivalRegistController@scanDetail'); //已扫描明细
                $api->get('/arr/check-barcode', 'ArrivalRegistController@checkBarCode'); //检查条形码
                $api->post('/arr/scan', 'ArrivalRegistController@scan'); //扫码收货
                $api->post('/arr/sub-scan', 'ArrivalRegistController@subScan'); //减扫
                $api->post('/arr/confirm', 'ArrivalRegistController@confirm'); //确认收货
            });
            $api->group(["prefix" => 'v3'], function ($api) {
                $api->get('/arr/scan-detail', 'ArrivalRegistController@scanDetail'); //已扫描明细
                $api->post('/arr/confirm', 'ArrivalRegistController@confirm3'); //确认收货
            });


            // 质检
            $api->get('/qc/receive-order', 'QcController@receiveOrder'); //未质检的收货单
            $api->get('/qc/receive-order-detail', 'QcController@receiveOrderDetail'); //收货单详情
            $api->post('/qc/one-step', 'QcController@oneStep'); //一键质检
            $api->get('/qc/uniq-scan', 'QcController@uniqScan'); //唯一码扫描
            $api->post('/qc/flaw-report', 'QcController@flawReport'); //瑕疵上报
            $api->post('/qc/flaw-submit', 'QcController@flawSubmit'); //瑕疵质检完成

            // 质量类型调整
            $api->post('/qc/change-report', 'QcController@changeReport'); //质量类型调整上报

            // 配货任务
            $api->get('/allocate/pending-task', 'AllocateController@pendingTask'); //待领取任务
            $api->post('/allocate/get-task', 'AllocateController@getTask'); //领取任务
            $api->get('/allocate/user-task', 'AllocateController@userTask'); //已领取任务
            $api->post('/allocate/cancel-task', 'AllocateController@cancelTask'); //取消任务
            $api->get('/allocate/task-show', 'AllocateController@taskShow'); //配货任务展示信息
            $api->get('/allocate/task-detail', 'AllocateController@taskDetail'); //配货任务明细
            $api->post('/allocate/allocate', 'AllocateController@allocate'); //配货
            $api->post('/allocate/skip', 'AllocateController@skip'); //跳过配货

            // 取消单上架
            $api->get('/allocate/cancel-search', 'AllocateController@cancelSearch'); //查询
            $api->get('/allocate/recomment-info', 'AllocateController@getRecommendInfo'); //获取唯一码信息
            $api->post('/allocate/cancel-putaway', 'AllocateController@cancelPutaway'); //取消单上架
            $api->post('/allocate/cancel-putaway-confirm', 'AllocateController@cancelPutawayConfirm'); //上架完成

            // 库存查询
            $api->get('/inventory/search', 'InventoryController@search'); //查询
            $api->get('/inventory/detail', 'InventoryController@detail'); //明细

            // 盘点单
            $api->get('/check/search', 'CheckController@search'); //查询
            $api->get('/check/detail', 'CheckController@detail'); //详情
            $api->post('/check/scan', 'CheckController@scan'); //盘点
            $api->post('/check/scan-location-code', 'CheckController@scanLocationCode'); //盘点单新增位置码
            $api->post('/check/confirm', 'CheckController@confirm'); //确认盘点
            $api->post('/check/refresh', 'CheckController@refresh'); //刷新盘点单


            //上架
            $api->post('/putaway/scan', 'PutawayController@scan'); //扫描上架
            $api->post('/putaway/scanOrdinary', 'PutawayController@scanOrdinary'); //扫描上架-普通产品
            $api->post('/putaway/addOrdinary', 'PutawayController@addOrdinary'); //扫描上架-普通产品增加
            $api->get('/putaway/check', 'PutawayController@check'); //上架预检
            $api->post('/putaway/submit', 'PutawayController@submit'); //上架完成

            // 中转移位
            $api->get('/move/search', 'MoveController@search'); //查询
            $api->post('/move/takedown', 'MoveController@takedown'); //下架
            $api->post('/move/takedown-location-code', 'MoveController@takedownByLocationCode'); //位置码一键下架
            $api->post('/move/takedown-confirm', 'MoveController@takedownConfirm'); //下架确认
            $api->post('/move/shelf', 'MoveController@shelf'); //上架
            $api->post('/move/shelf-location-code', 'MoveController@shelfByLocationCode'); //位置码一键上架
            $api->get('/move/detail', 'MoveController@detail'); //移位单详情

            // 快速移位
            $api->get('/move/fast-search', 'MoveController@fastSearch'); //查询
            $api->post('/move/fast-move', 'MoveController@fastMove'); //移位
            $api->post('/move/fast-confirm', 'MoveController@fastConfirm'); //确认


            //个人中心
            $api->get('/my/info', 'MeController@info'); //个人信息
            $api->get('/my/version', 'MeController@version'); //版本获取
            $api->get('/my/task', 'MeController@task'); //我的任务
            $api->get('/my/recv/list', 'MeController@recv'); //收货单列表
            $api->get('/my/qc/list', 'MeController@qc'); //质检单列表
            $api->get('/my/putaway/list', 'MeController@putaway'); //入库上架单列表
            $api->get('/my/putaway/cancel', 'MeController@cancelPutaway'); //入库上架单列表
            $api->get('/my/allocate/list', 'MeController@allocate'); //配货
            $api->get('/my/stockCheck/list', 'MeController@stockCheck'); //盘点
            $api->get('/my/move/up', 'MeController@moveUp'); //移位上架
            $api->get('/my/move/down', 'MeController@moveDown'); //移位下架


        });

        $api->group(['namespace' => 'Admin\V2'], function ($api) {
            $api->post('/recv/one/scanningGoods', 'Inbound\RecvOrderController@BaseCreate'); //逐渐收货扫描收货
            $api->post('/recv/one/addOrdinary', 'Inbound\RecvOrderController@addOrdinary'); //普通产品扫描收货
            $api->post('/recv/one/del', 'Inbound\RecvDetailController@delByBar'); //逐渐收货删除扫描
            $api->post('/recv/one/reduce', 'Inbound\RecvDetailController@delByUniq'); //逐渐收货减扫
            $api->post('/recv/one/reduceOrdinary', 'Inbound\RecvDetailController@delByOrdinary'); //普通产品减扫
            $api->post('/recv/one/addByOrdinary', 'Inbound\RecvDetailController@addByOrdinary'); //普通产品增加
            $api->post('/recv/one/done', 'Inbound\RecvOrderController@recvDone'); //逐渐收货收货完成
        });
    });
});
