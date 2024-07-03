<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="renderer" content="webkit">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>アカウントを作成する</title>
    <link rel="stylesheet" type="text/css" href="static/js/layui/css/layui.css" />
    <link rel="stylesheet" type="text/css" href="static/css/styleMember.css" />
    <link rel="stylesheet" type="text/css" href="static/css/bootstrap.min.css" />
    <link rel="stylesheet" href="static/css/global.css">
    <script src="static/js/jquery-1.11.0.min.js"></script>
    <script src="static/js/layui/layui.js"></script>
    <link href="static/css/font-awesome.min.css" rel="stylesheet" type="text/css" media="all" />
    <script src="static/js/base.js"></script>
    <script src="static/js/request/login.js"></script>
    <link rel="stylesheet" href="static/css/login.css">
</head>

<body>
    <div id="mydiv">
        <div class="login-main">
            <div class="layui-form-item htd">
                <a href="/cms_index">
                    <img class="llogo" src="static/img/LOGO.png" alt="layui">
                </a>
                <a href="/member_login" class="layadmin-user-jump-change llink ">会員の方</a>
            </div>
            <div style="display: flex;align-items: center;margin-bottom: 15px;justify-content: space-around;"><span class="before"></span><span class="show-main">アカウントを作成する</span><span class="after"></span></div>
            <form class="layui-form">
                <div class="layui-form-item">
                    <div class="layui-input-inline input-item">
                        <input type="text" name="nickname" lay-verify="required" autocomplete="off" placeholder="お名前" id="nickname" class="layui-input">
                    </div>
                </div>
                <div class="layui-form-item">
                    <div class="layui-input-inline input-item">
                        <input type="text" name="mobile" lay-verify="required" autocomplete="off" id="mobile" placeholder="連絡先電話番号" class="layui-input">
                    </div>
                </div>
                <div class="layui-form-item">
                    <div class="layui-input-inline input-item">
                        <input type="text" name="username" lay-verify="required" autocomplete="off" placeholder="ユーザーID" id="username" class="layui-input">
                    </div>
                </div>
                <div class="layui-form-item">
                    <div class="layui-input-inline input-item">
                        <input type="text" name="email" lay-verify="required|email" autocomplete="off" placeholder="メールアドレス" id="email" class="layui-input">
                    </div>
                </div>
                <div class="layui-form-item">
                    <div class="layui-input-inline input-item">
                        <input type="password" name="password" lay-verify="required" autocomplete="off" id="password" placeholder="パスワード" class="layui-input">
                    </div>
                </div>

                <div class="layui-form-item">
                    <div class="layui-input-inline input-item">
                        <input type="password" name="password_confirm" lay-verify="required" id="confirm" autocomplete="off" placeholder="パスワード再確認" class="layui-input">
                    </div>
                </div>

                <div class="layui-form-item">
                    <div class="layui-input-inline input-item verify-box">
                        <input type="text" name="verify" lay-verify="required" placeholder="確認コード" autocomplete="off" id="captcha" class="layui-input">
                        <img id="verify" src="" alt="確認コード" class="captcha" onclick="getagain()">
                    </div>
                </div>
                <div class="layui-form-item">
                    <div class="layui-input-inline login-btn">
                        <button class="layui-btn member-button" lay-filter="login" type="button" onclick="register()" lay-submit>新規登録</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script>
        function register() {
            console.log($('#password').val(), $('#confirm').val());
            if ($('#password').val() != $('#confirm').val()) {
                layer.msg('两次密码输入不一致！')
                return false
            }
            let params = {
                username: null,
                nickname: null,
                email: null,
                mobile: null,
                password: null,
                captcha: null,
                key: null
            }
            params.username = $('#username').val()
            params.nickname = $('#nickname').val()
            params.email = $('#email').val()
            params.mobile = $('#mobile').val()
            params.password = $('#password').val()
            params.captcha = $('#captcha').val()
            params.key = sessionStorage.getItem('key')
            $.post(baseUrl + '/api/register', params, function(data) {
                if (data.code == 200) {
                    sessionStorage.setItem('token', data.data.token.token_type + ' ' + data.data.token.token)
                    window.location.href = '/member_login'
                } else {
                    layer.msg(data.msg);
                    getagain()
                }
            })
        }
    </script>
    @include('cms.footer')