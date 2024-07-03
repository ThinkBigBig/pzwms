<!DOCTYPE HTML>
<html>

<head>
    <title>the1sneaker</title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="the1sneaker">
    <meta name="keywords" content="the1sneaker">
    <link href="static/css/bootstrap.min.css" rel="stylesheet" type="text/css" media="all">
    <link rel="stylesheet" href="static/css/layui.css">
    <link rel="shortcut icon" href="static/img/favicon.ico" type="image/x-icon">
    <script src="static/js/jquery-1.11.0.min.js"></script>
    <script type="text/javascript" src="static/js/bootstrap.min.js"></script>
    <script src="static/js/layui/layui.js"></script>
    <link href="static/css/style.css" rel="stylesheet" type="text/css" media="all" ../cms>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" ../cms>
    <script type="application/x-javascript">
        addEventListener("load", function() {
            setTimeout(hideURLbar, 0);
        }, false);

        function hideURLbar() {
            window.scrollTo(0, 1);
        }
    </script>
    <link href="static/css/font-awesome.min.css" rel="stylesheet" type="text/css" media="all" ../cms>
    <link href="static/css/animate.css" rel="stylesheet" type="text/css" media="all">
    <script src="static/js/wow.min.js"></script>
    <script src="static/js/base.js"></script>
    <script src=" static/js/classie.js"></script>
    <script src=" static/js/uisearch.js"></script>
    <script>
        new WOW().init();
    </script>
</head>

<body>
    <div class="banner-1">
        <div class="searchmain">
            <div class="container">
                <div class="searchbox">
                    <div class="logotop">
                        <h1><a href="/cms_index"><img src="static/img/LOGO.png" alt="logo"></a>
                        </h1>
                    </div>
                    <div class="seachdiv">
                        <img id="sicon" src=" static/img/searchblack.png">
                        <input type="search" name="" id="searchhead" placeholder="何をお探しですが？">
                    </div>
                    <div>
                        <img src=" static/img/cha.png" id="cha">
                    </div>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="header">
                <div class="logo wow fadeInLeft animated" data-wow-delay=".5s">
                    <h1><a href="/cms_index"><img src=" static/img/LOGO.png" alt="logo"></a></h1>
                </div>
                <nav class="navbar navbar-default">
                    <div class="navbar-header">
                        <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
                            <span class="sr-only">Toggle navigation</span>
                            <span class="icon-bar"></span>
                            <span class="icon-bar"></span>
                            <span class="icon-bar"></span>
                        </button>
                    </div>
                    <!--/.navbar-header-->
                    <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                        <ul class="nav navbar-nav animated wow fadeInUp animated animated" data-wow-duration="1200ms" data-wow-delay="500ms">
                            <li class="active"><a href="/cms_index" title="ホームページ">ホームページ</a></li>
                            <li><a href="/cms_market" title="商品価格">商品価格</a></li>
                            <li><a href="/cms_enquiry" title="無料査定">無料査定</a></li>
                            <li><a href="/cms_step" title="利用ガイド">利用ガイド</a></li>
                            <!-- <li><a href="/cms_appraisal" title="無料鑑定">無料鑑定</a></li> -->
                            <li><a href="/cms_problem" title="よくある質問">よくある質問</a></li>
                        </ul>
                    </div>
                </nav>
                <div id="sb-search" class="sb-search wow fadeInRight animated" data-wow-delay=".5s" style="line-height: 3;font-size: 14px;">
                    <div class="token">
                        <span class="icon"><img src=" static/img/blacksearch.png" id="searchicon"></span>
                        <a href="/member_login">ログイン </a>
                        <a href="/member_register"> 新規登録</a>
                    </div>
                    <div class="tokenin">
                        <span class="icon"><img src=" static/img/blacksearch.png" id="searchicon2"></span>
                        <span class="icon"><img onclick="window.location.href='/member_index'" src=" static/img/blackuser.png" title="マイページ"></span>
                        <span class="icon"><img src=" static/img/blackcar.png" style="position: relative;" id="caricon" onclick="window.location.href='/member_car'">
                            <span id="shopcar" class="layui-badge">9</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="clearfix"> </div>
    </div>

    <style type="text/css">
        .layui-form-select dl dd.layui-this {
            background-color: #C5D0E1;
        }

        @media (min-width: 640px) {
            .logo img {
                width: 60%;
                position: relative;
            }
        }

        .navbar-default .navbar-nav>li>a {
            color: #000;
            font-size: 1.1em;
            font-weight: 400;
        }

        .navbar-default .navbar-nav>li>a:hover,
        .navbar-default .navbar-nav>li>a:focus {
            color: #000;
            background-color: rgba(0, 0, 0, 0);
            border-bottom: 2px solid #000;
        }

        .navbar-default .navbar-nav>.active>a,
        .navbar-default .navbar-nav>.active>a:hover,
        .navbar-default .navbar-nav>.active>a:focus {
            color: #000;
            background-color: rgba(0, 0, 0, 0);
            border-bottom: 2px solid #000;
        }

        .searchmain {
            width: 100%;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 99;
            background-color: #fff;
            display: none;
        }

        .seachdiv {
            width: 70%;
        }

        .header {
            display: flex;
        }

        #searchhead {
            background-color: transparent;
            border: none;
            outline: none;
            width: 80%;
        }

        .searchbox {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
        }

        .logotop img {
            width: 50%;
        }

        .sb-search {
            display: flex;
        }

        .sb-search a {
            font-weight: bold;
        }

        #shopcar {
            position: absolute;
            right: 0;
            background: rgba(197, 208, 225, 1) !important;
            border-radius: 50%;
            color: #000;
        }

        .icon {
            display: inline-block;
        }

        .icon img {
            margin: 10px;
            width: 25px;
            height: 25px;
        }

        .icon:hover {
            border-radius: 100%;
            background-color: rgba(254, 254, 254, 0.3);
        }

        .swiper {
            --swiper-theme-color: #fff !important;
        }

        .swiper .swiper-pagination-bullet {
            border-radius: 0%;
            height: .2rem !important;
        }

        .swiper-pagination-bullet-active {
            width: 1rem !important;
        }

        @media (max-width: 480px) {
            .sb-search {
                top: 1rem;
            }

            .icon img {
                width: 15px;
                height: 15px;
            }

            .searchbox #cha {
                width: 12.5px;
            }

            .searchbox #sicon {
                width: 12.5px;
            }

            .logotop {
                width: 20%;
            }

            .logotop img {
                width: 180%;
            }
        }
    </style>
    <script>
        let token = sessionStorage.getItem('token')
        if (token) {
            $('.token').css('display', 'none')
            $.ajax({
                type: 'get',
                url: baseUrl + "/api/shopping",
                headers: {
                    'Authorization': sessionStorage.getItem('token'),
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                success(data) {
                    if (data.code == 200) {
                        if (data.data.total == 0) {
                            $('#shopcar').css('display', 'none')
                        } else {
                            $('#shopcar').html(data.data.total)
                        }
                    } else if (data.code == 401) {
                        window.location.href = '/member_login'
                    } else {
                        layui.msg(data.msg)
                    }
                }
            })
        } else {
            $('.tokenin').css('display', 'none')
        }
        $("#searchicon").click(function() {
            $(".searchmain").slideDown();
        });
        $("#searchicon2").click(function() {
            $(".searchmain").slideDown();
        });
        $("#cha").click(function() {
            $(".searchmain").slideUp();
        });
        $(function() {
            var _nava = $('.navbar-nav a');
            var _url = window.location.href;
            var _host = window.location.host;
            for (var i = 0; i < _nava.length; i++) {
                var _astr = _nava.eq(i).attr('href');
                _astr = _astr.split('/')[1]
                if (_url.indexOf(_astr) != -1) {
                    _nava.eq(i).parent().addClass('active').siblings().removeClass('active');
                } else if (_url == ('http://' + _host + '/')) {
                    _nava.eq(0).addClass('active').siblings().removeClass('active');
                }
            }
        })
        $('#searchhead').keydown(function(e) {
            var good_name = $("#searchhead").val()
            if (good_name == null || good_name == '') {
                return
            }
            if (e.keyCode == 13) {
                let parse = {
                    good_name: null,
                    productsn: null,
                    page: null,
                    limit: null
                }
                parse.good_name = good_name
                window.location.href = '/cms_market?name=' + parse.good_name
            }
        })
    </script>