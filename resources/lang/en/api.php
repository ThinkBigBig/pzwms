<?php

//验证包，规则 ----制器名.方法名.字段名.验证参数---
return [
    //登录
    'auth.login.key.required'=>'网络错误',
    'auth.login.username.required'=>'请输入用户名',
    'auth.login.password.required'=>'请输入密码',
    'auth.login.captcha.required'=>'请输入验证码',
    'auth.login.captcha.captcha_api'=>'验证码错误',

    //注册
    'auth.register.key.required'=>'网络错误',
    'auth.register.username.required'=>'请输入用户名',
    'auth.register.username.unique'=>'用户名已存在',
    'auth.register.nickname.required'=>'请输入昵称',
    'auth.register.mobile'=>'请输入正确的电话号码',
    'auth.register.mobile.unique'=>'电话号码已存在',
    'auth.register.email'=>'请输入正确的邮箱',
    'auth.register.email.unique'=>'邮箱已存在',
    'auth.register.password.required'=>'请输入密码',
    'auth.register.captcha.required'=>'请输入验证码',
    'auth.register.captcha.captcha_api'=>'验证码错误',

    //下单
    'order.addOrder.shopping_cart_ids.required'=>'请选择商品',
    'order.addOrder.shop_id.required'=>'请选择店铺',
    'order.addOrder.shop_name.required'=>'请选择店铺',
    'order.addOrder.order_type.required'=>'请选择类型',
    'order.addOrder.shoptime' => '请选择时间',
    'order.addOrder.shoptime.error'=>'当日のみ予約の方、対応致します。!',
    'order.addOrder.num'=>'商品购买种类不能超过八个',

    //加入购物车
    'shop.create.product_id.required'=>'请选择商品',
    'shop.create.sku_id.required'=>'请选择商品',
    'shop.create.num.required'=>'请输入正确的数量',
    'shop.create.num.integer'=>'数量必须是整数',
    'shop.create.num.between'=>'请输入正确的数量',
    'shop.create.examinetime.error' => '超时',
    'shop._oneFrom._price.tips' => '出价不存在',

    //商品相关
    'product.seriesClass.category_id.required'=>'请输入类型',
    'product.price'=>'暂未出价',

];
