<?php
//前端路由
$api = app('Dingo\Api\Routing\Router');
$api->version('v1',[
    'namespace' => 'App\Http\Controllers',
    'cors'
], function ($api) {
    // $api->get('/', 'Api\V1\IndexController@index');
    // $api->get('/www', 'Api\V1\SlideController@www');
    // $api->get('/pass', 'Api\V1\OrderController@pass');
    // $api->get('/synchro', 'Api\V1\OrderController@synchro');
    // $api->get('/cms_index', 'Api\V1\IndexController@index');//主页
    // $api->get('/cms_appraisal', 'Api\V1\IndexController@index');//鉴定
    // $api->get('/cms_enquiry', 'Api\V1\IndexController@index');//查定
    // $api->get('/cms_brand', 'Api\V1\IndexController@index');//品牌五页
    // $api->get('/cms_detail', 'Api\V1\IndexController@index');//商品详情
    // $api->get('/cms_market', 'Api\V1\IndexController@index');//商品页
    // $api->get('/cms_problem', 'Api\V1\IndexController@index');//文章
    // $api->get('/cms_single', 'Api\V1\IndexController@index');//文章
    // $api->get('/cms_step', 'Api\V1\IndexController@index');//文章

    // $api->get('/member_car', 'Api\V1\IndexController@index');//下单
    // $api->get('/member_enquiry', 'Api\V1\IndexController@index');//查定
    // $api->get('/member_index', 'Api\V1\IndexController@index');//主页
    // $api->get('/member_login', 'Api\V1\IndexController@index');//登录
    // $api->get('/member_order', 'Api\V1\IndexController@index');//订单
    // $api->get('/member_profile', 'Api\V1\IndexController@index');//用户信息
    // $api->get('/member_register', 'Api\V1\IndexController@index');//注册
    // $api->get('/member_shopOrder', 'Api\V1\IndexController@index');//购物车


    // //规划前台接口
    // $api->group(['namespace' => 'Api\V1', "prefix" => 'api'], function ($api) {
    //     //注册
    //     $api->post('/register', 'Auth\AuthController@register');
    //     //验证码
    //     $api->get('/codeImg', 'Auth\AuthController@codeImg');
    //     //登录
    //     $api->post('/login', 'Auth\AuthController@login');
    //     //轮播
    //     $api->get('/slide', 'SlideController@BaseAll');
    //     //店铺
    //     $api->get('/shop', 'ShopController@BaseAll');
    //     //网站信息
    //     $api->get('/config', 'ConfigController@BaseAll');
    //     //品牌
    //     $api->get('/brand', 'ProductController@brandLimit');
    //     //品牌
    //     $api->get('/brandAll', 'ProductController@brandAll');
    //     //系列
    //     $api->get('/series', 'ProductController@seriesLimit');
    //     //商品
    //     $api->get('/product', 'ProductController@BaseLimit');
    //     //商品详情
    //     $api->get('/product/info', 'ProductController@BaseOne');
    //     //文章
    //     $api->get('/article', 'ArticleController@BaseAll');
    //     //文章
    //     $api->get('/seriesAll', 'ProductController@seriesAll');

    //     $api->group(['middleware' => 'auth:api'], function ($api) {
    //         $api->get('/pdf', 'OrderController@Pdf');
    //         $api->get('/logout', 'Auth\AuthController@destroy');
    //         //个人信息
    //         $api->get('/examine/info', 'ExamineController@BaseOne');
    //         $api->post('/examine/add', 'ExamineController@BaseCreate');
    //         $api->post('/examine/edit', 'ExamineController@BaseUpdate');
    //         $api->post('/member/edit', 'ExamineController@memberUpdate');
    //         //购物车
    //         $api->get('/shopping', 'ShoppingController@BaseLimit');
    //         //购物车详情
    //         $api->get('/shopping/info', 'ShoppingController@BaseOne');
    //         //加入购物车
    //         $api->get('/addShopping', 'ShoppingController@create');
    //         $api->post('/materiel_addShopping', 'ShoppingController@materiel_create');//查定新增购物车

    //         //购物车删除
    //         $api->get('/delShopping', 'ShoppingController@BaseDelete');
    //         //购物车下单
    //         $api->post('/addOrder', 'OrderController@addOrder');
    //         //订单列表
    //         $api->get('/orderList', 'OrderController@BaseLimit');
    //         //订单详情
    //         $api->get('/orderInfo', 'OrderController@BaseOne');
    //         //订单操作
    //         $api->get('/editOrder', 'OrderController@BaseUpdate');
    //         //订单操作
    //         $api->get('/delOrder', 'OrderController@BaseDelete');
    //         //查定模块
    //         $api->get('/materiel/list', 'MaterielController@BaseLimit');//列表
    //         $api->get('/materiel/info', 'MaterielController@BaseOne');//列表
    //         $api->post('/materiel/add', 'MaterielController@BaseCreate');//新增
    //         // $api->post('/materiel/edit', 'MaterielController@BaseUpdate');//修改
    //         $api->get('/materiel/del', 'MaterielController@BaseDelete');//删除
    //         $api->post('/attachment/add', 'AttachmentController@add');//新增
    //     });
    // });
});
// 其中 ： 刷新token 和 注销接口 请求的时候需要以下请求头：
// Authorization  ： Bearer + "your token"
