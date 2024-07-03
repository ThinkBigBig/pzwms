<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8" />
    <title>用户中心</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="keywords" content="" />
    <meta name="description" content="" />
    <link rel="stylesheet" href="static/css/bootstrap.min.css" media="screen" type="text/css" />
    <link rel="shortcut icon" href="static/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="static/js/layui/css/layui.css" />
    <link rel="stylesheet" href="static/css/global.css" />
    <link rel="stylesheet" href="static/font/iconfont.css" />
    <script src="static/js/layui/layui.js"></script>
    <script src="static/js/jquery-1.11.0.min.js"></script>
    <script src="static/js/bootstrap.min.js"></script>
    <script src="static/js/base.js"></script>
</head>

<body class="body">
    <div class="fly-header layui-bg-black">
        <div class="layui-container">
            <a class="fly-logo" href="/cms_index">
                <img class="memeberlogo" src="static/img/LOGO.png" alt="layui" />
            </a>
            <ul class="layui-nav fly-nav-user" style="display: flex; align-items: center">
                <li class="layui-nav-item">
                    <a class="fly-nav-avatar" href="javascript:;">
                        <img src="" id="avatar" />
                        <cite><span id="username"></span></cite>
                    </a>
                    <dl class="layui-nav-child">
                        <dd>
                            <a href="/member_index"><i class="layui-icon">&#xe612;</i>マイページ</a>
                        </dd>
                        <dd>
                            <a href="/member_profile"><i class="layui-icon">&#xe620;</i>会員情報</a>
                        </dd>
                        <hr style="margin: 5px 0" />
                        <dd>
                            <a href="/member_login" style="text-align: center">ログアウト</a>
                        </dd>
                    </dl>
                </li>
                <span class="layui-hide-xs" style="color: #000">|</span>
                <span class="layui-hide-xs" style="color: #000">日本 (日本語 / ¥ JPY)</span>
            </ul>
        </div>
    </div>
    <div>
        <div class="layui-container">
            <a class="fly-logo" href="/cms_index"> ホームページx </a>
        </div>
    </div>
    <div class="layui-container fly-marginTop fly-user-main">
        <ul class="layui-nav layui-nav-tree layui-inline" lay-filter="user">
            <li class="layui-nav-item">
                <a href="/member_index"><i class="iconfont icon-people"></i>&nbsp;アカウント概要</a>
            </li>
            <li class="layui-nav-item">
                <a href="/member_shopOrder"><i class="iconfont icon-oschina"></i>&nbsp;店頭履歴</a>
            </li>
            <li class="layui-nav-item">
                <a href="/member_order"><i class="iconfont icon-caigou"></i>&nbsp;郵送履歴</a>
            </li>
            <li class="layui-nav-item">
                <a href="/member_enquiry"><i class="iconfont icon-shenhe"></i>&nbsp;查定履歴</a>
            </li>
            <li class="layui-nav-item">
                <a href="/member_profile"><i class="iconfont icon-setup"></i>&nbsp;会員情報</a>
            </li>
            <li class="layui-nav-item">
                <a href="/member_car"><i class="iconfont icon-caigou-xianxing"></i>&nbsp;カート</a>
            </li>
        </ul>
        <div class="site-tree-mobile layui-hide">
            <i class="layui-icon">&#xe602;</i>
        </div>
        <div class="site-mobile-shade"></div>
        <div class="site-tree-mobile layui-hide">
            <i class="layui-icon">&#xe602;</i>
        </div>
        <div class="site-mobile-shade"></div>
        <div class="fly-panel fly-panel-user" pad20 style="padding-top: 20px">
            <style>
                .layui-laydate .layui-this {
                    background-color: #a8b6cb !important;
                }

                .layui-form-select dl dd.layui-this {
                    background-color: #a8b6cb !important;
                }

                .layui-laypage .layui-laypage-curr .layui-laypage-em {
                    background-color: #dbe1ea;
                }

                .layui-form-checked[lay-skin="primary"] i {
                    background-color: #a8b6cb;
                }

                .layui-btn-now {
                    background: #8ac5e7;
                }

                .layui-btn-now1 {
                    background-color: #84aeed;
                }

                .layui-nav .layui-nav-item a {
                    color: #000;
                }

                .layui-nav .layui-nav-item a:hover,
                .layui-nav .layui-this a {
                    color: #000;
                    text-decoration: none;
                }

                .memeberlogo {
                    width: 30%;
                }

                .layui-tab-brief>.layui-tab-title .layui-this {
                    color: #728EB7;
                }

                .layui-tab-brief>.layui-tab-more li.layui-this:after,
                .layui-tab-brief>.layui-tab-title .layui-this:after {
                    border-bottom: 2px solid rgba(0, 0, 0, 0.3);
                }
            </style>
            <script>
                $(function() {
                    var _nava = $('.layui-nav-tree a');
                    var _url = window.location.href;
                    var _host = window.location.host;
                    for (var i = 0; i < _nava.length; i++) {
                        var _astr = _nava.eq(i).attr('href');
                        _astr = _astr.split('/')[1]
                        if (_url.indexOf(_astr) != -1) {
                            _nava.eq(i).parent().addClass('layui-this').siblings().removeClass('layui-this');
                        } else if (_url == ('http://' + _host + '/')) {
                            _nava.eq(0).addClass('layui-this').siblings().removeClass('layui-this');
                        }
                    }
                    $.ajax({
                        type: 'get',
                        url: baseUrl + "/api/examine/info",
                        headers: {
                            'Authorization': sessionStorage.getItem('token'),
                        },
                        success(data) {
                            if (data.code == 200) {
                                $('#avatar').attr('src', data.data.member.avatar)
                                $('#username').html(data.data.member.username)
                            } else if (data.code == 401) {
                                window.location.href = '/member_login'
                            } else {
                                layui.msg(data.msg)
                            }
                        }
                    })
                })
            </script>
            <script>
                layui.define(['layer', 'laytpl', 'form', 'element', 'upload', 'util'], function(exports) {
                    var $ = layui.jquery,
                        layer = layui.layer,
                        laytpl = layui.laytpl,
                        form = layui.form,
                        element = layui.element,
                        upload = layui.upload,
                        util = layui.util,
                        device = layui.device(),
                        DISABLED = 'layui-btn-disabled';

                    //阻止IE7以下访问
                    if (device.ie && device.ie < 10) {
                        layer.alert('如果您非得使用 IE 浏览器访问，那么请使用 IE10+');
                    }

                    layui.focusInsert = function(obj, str) {
                        var result, val = obj.value;
                        obj.focus();
                        if (document.selection) { //ie
                            result = document.selection.createRange();
                            document.selection.empty();
                            result.text = str;
                        } else {
                            result = [val.substring(0, obj.selectionStart), str, val.substr(obj.selectionEnd)];
                            obj.focus();
                            obj.value = result.join('');
                        }
                    };

                    //显示当前tab
                    if (location.hash) {
                        element.tabChange('user', location.hash.replace(/^#/, ''));
                    }
                    element.on('tab(user)', function() {
                        var othis = $(this),
                            layid = othis.attr('lay-id');
                        if (layid) {
                            location.hash = layid;
                        }
                    });

                    //加载特定模块
                    if (layui.cache.page && layui.cache.page !== 'index') {
                        var extend = {};
                        extend[layui.cache.page] = layui.cache.page;
                        layui.extend(extend);
                        layui.use(layui.cache.page);
                    }
                    //手机设备的简单适配
                    var treeMobile = $('.site-tree-mobile'),
                        shadeMobile = $('.site-mobile-shade')

                    treeMobile.on('click', function() {
                        $('body').addClass('site-mobile');
                    });

                    shadeMobile.on('click', function() {
                        $('body').removeClass('site-mobile');
                    });
                    exports('fly', {});

                });
            </script>