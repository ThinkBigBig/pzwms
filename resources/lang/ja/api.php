<?php

//验证包，规则 ----制器名.方法名.字段名.验证参数---
return [
    //登录
    'auth.login.key.required'=>'ログインエラー',
    'auth.login.username.required'=>'ユーザーネームを入力してください',
    'auth.login.password.required'=>'パスワードを入力してください',
    'auth.login.captcha.required'=>'認証コード入力してください',
    'auth.login.captcha.captcha_api'=>'認証コードが間違っています',

    //注册
    'auth.register.key.required'=>'ログインエラー',
    'auth.register.username.required'=>'ログインにはユーザーネームが必要です',
    'auth.register.username.unique'=>'このユーザーネームは既に存在します',
    'auth.register.nickname.required'=>'ニックネームを入力してください',
    'auth.register.mobile'=>'正しい電話番号を入力してください',
    'auth.register.mobile.unique'=>'この電話番号は既に存在します',
    'auth.register.email'=>'正しいメールアドレスを入力してください',
    'auth.register.email.unique'=>'このメールアドレスは既に登録されています',
    'auth.register.password.required'=>'登録にはパスワードが必要です',
    'auth.register.captcha.required'=>'認証コード入力してください',
    'auth.register.captcha.captcha_api'=>'認証コードが間違っています',

    //下单
    'order.addOrder.shopping_cart_ids.required'=>'商品追加してください。',
    'order.addOrder.shop_id.required'=>'店舗を選択してください。',
    'order.addOrder.shop_name.required'=>'　店舗を選択してください。',
    'order.addOrder.order_type.required'=>'　郵送買取、店頭買取のいずれかを選択してください。',
    'order.addOrder.shoptime' => '　時間を選択してください。',
    'order.addOrder.shoptime.error'=>'当日のみ予約の方、対応致します。!',
    'order.addOrder.num'=>'予約は7件までとなっております。',

    //加入购物车
    'shop.create.product_id.required'=>'商品を追加してください',
    'shop.create.sku_id.required'=>'商品を追加してください',
    'shop.create.num.required'=>'表示価額での買取数量の上限に達している可能性があります。下記にお問い合わせください。TEL:03-6914-0602',
    'shop.create.num.integer'=>'正しい数を入力してください',
    'shop.create.num.between'=>'表示価額での買取数量の上限に達している可能性があります。下記にお問い合わせください。TEL:03-6914-0602',
    'shop.create.examinetime.error' => '時間オーバー',
    'shop._oneFrom._price.tips' => '再度査定をお願いします',

    //商品相关
    'product.seriesClass.category_id.required'=>'　　認証コード入力してください。',
    'product.price'=>'查定中',

    //查定
    'Materiel.success' => '査定依頼完了',
];
