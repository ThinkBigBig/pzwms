<!DOCTYPE HTML>
<html>

<head>
	<title>the1sneaker</title>
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="the1sneaker">
	<meta name="keywords" content="the1sneaker">
	<link href="static/css/bootstrap.min.css" rel="stylesheet" type="text/css" media="all">
	<link rel="stylesheet" href="static/js/layui/css/layui.css">
	<link rel="shortcut icon" href="static/img/favicon.ico" type="image/x-icon">
	<script src="static/js/jquery-1.11.0.min.js"></script>
	<script type="text/javascript" src="static/js/bootstrap.min.js"></script>
	<script src="static/js/layui/layui.js"></script>
	<link href="static/css/style.css" rel="stylesheet" type="text/css" media="all" />
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<script type="application/x-javascript">
		addEventListener("load", function() {
			setTimeout(hideURLbar, 0);
		}, false);

		function hideURLbar() {
			window.scrollTo(0, 1);
		}
	</script>
	<meta name="keywords" content="" />
	<link href="static/css/font-awesome.min.css" rel="stylesheet" type="text/css" media="all" />
	<link href="static/css/animate.css" rel="stylesheet" type="text/css" media="all">
	<script src="static/js/wow.min.js"></script>
	<script src="static/js/base.js"></script>
	<script>
		new WOW().init();
	</script>
</head>

<body>
	<div class="first-header">
		<div class="searchmain">
			<div class="container">
				<div class="searchbox">
					<div class="logotop">
						<h1><a href="/cms_index"><img src="static/img/LOGO.png" alt="logo"></a>
						</h1>
					</div>
					<div class="seachdiv">
						<img id="sicon" src="static/img/searchblack.png">
						<input type="search" name="" id="searchhead" placeholder="何をお探しですが？">
					</div>
					<div>
						<img src="static/img/cha.png" id="cha">
					</div>
				</div>
			</div>
		</div>
		<div class="container">
			<div class="header">
				<div class="logo wow fadeInLeft animated" data-wow-delay=".5s">
					<h1><a href="/cms_index"><img src="static/img/LOGO.png" alt="logo"></a></h1>
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
							<li><a href="/cms_market?link=61&id=80" title="商品価格">商品価格</a></li>
							<li><a href="/cms_enquiry" title="無料査定">無料査定</a></li>
							<li><a href="/cms_step" title="利用ガイド">利用ガイド</a></li>
							<!-- <li><a href="/appraisal" title="無料鑑定">無料鑑定</a></li> -->
							<li><a href="/cms_problem" title="よくある質問">よくある質問</a></li>
						</ul>
					</div>
				</nav>
				<div id="sb-search" class="sb-search wow fadeInRight animated" data-wow-delay=".5s" style="line-height: 3;font-size: 14px;">
					<div class="token">
						<span class="icon"><img src=" static/img/search.png" id="searchicon"></span>
						<a href="/member_login">ログイン </a>
						<a href="/member_register"> 新規登録</a>
					</div>
					<div class="tokenin">
						<span class="icon"><img src=" static/img/search.png" id="searchicon2"></span>
						<span class="icon"><img onclick="window.location.href='/member_index'" src=" static/img/user.png" title="マイページ"></span>
						<span class="icon"><img src=" static/img/buycar.png" style="position: relative;" onclick="window.location.href='/member_car'" id="caricon">
							<span id="shopcar" class="layui-badge">9</span>
						</span>
					</div>
				</div>
			</div>
		</div>
		<div class="clearfix"> </div>
	</div>
	<style type="text/css">
		@media (min-width: 640px) {
			.logo img {
				width: 60%;
				position: relative;
			}
		}

		.navbar-default .navbar-nav>li>a:hover,
		.navbar-default .navbar-nav>li>a:focus {
			border-bottom: none;
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

		.sb-search {
			display: flex;
		}

		.sb-search a {
			font-weight: bold;
		}

		#shopcar {
			position: absolute;
			right: 0;
			background: #E1F3FF !important;
			border-radius: 50%;
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
		.swiper  a{
			width: 100%;
			height: 100%;
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
	<!--navigation end here-->
	</div>
	<div class="banner swiper">
		<!--navigation start here-->
		<div class="swiper-wrapper" id="swiper">
		</div>
		<div class="swiper-pagination"></div>
	</div>
	<link rel="stylesheet" href="static/css/swiper-bundle.min.css" type="text/css" media="screen" />
	<script defer src="static/js/swiper-bundle.min.js"></script>
	<script type="text/javascript">
		$.get(baseUrl + "/api/slide", {}, function(data) {
			let h5 = ''
			if (data.code == 200) {
				for (let index = 0; index < data.data.length; index++) {
					h5 += `<div class="banner-main swiper-slide" style="display: flex;flex-direction: column;background: url(${data.data[index].imagepath});background-size: 100% 100%;">`
					h5 += `<a href="${data.data[index].line_url}">`
					// if (data.data[index].form_type == 2) {
					// 	h5 += `<div class="banner-btn"><button class="main-btn"></button></div>`
					// }
					h5 += `</a></div>`
				}
			}
			$('#swiper').html(h5)
			var mySwiper = new Swiper('.swiper', {
				speed: 1000,
				autoplay: {
					delay: 3000
				},
				loop: true,
				disableOnInteraction: false, // 用户操作swiper之后，是否禁止autoplay]。默认为true：停止。
				pagination: {
					el: '.swiper-pagination',
					clickable: true
				}
			})
		})
	</script>
	<script>
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
				},
				complete: function() {}
			})
		} else {
			$('.tokenin').css('display', 'none')
			$('#shopcar').css('display', 'none')
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
		$('#caricon').click(function() {
			$.post("member/content/cartsList", function(data) {
				if (data.code == 0) {} else {
					layer.msg('网络错误!');
				}
			})
		})
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
	</script>
	<style type="text/css">
		body {
			background-color: #f6f6f6;
		}

		#fh5co-slider {
			background: #2A528A;
		}

		h3 {
			color: #000000;
		}

		.shosetalk {
			text-align: center;
		}

		.main-btn {
			margin-top: 50%;
			margin-right: 20%;
			margin-bottom: 5%;
			background: url(static/img/button_normal.png) no-repeat;
			background-size: 100% 100%;
			padding: 40px 124px;
			border: none;
		}

		.logotop img {
			width: 50%;
		}

		.title {
			display: flex;
			justify-content: center;
			align-items: center;
		}

		.show-main {
			color: #1D1D1F;
			font-size: 2.3em;
		}

		.sb-search a {
			color: #fff !important;
		}

		.before {
			display: inline-block;
			width: 180px;
			height: 1px;
			background: linear-gradient(89.95deg, rgba(82, 82, 82, 0.03) 20.01%, #000000 77.77%);
			margin-right: 5px;
		}

		.after {
			width: 180px;
			display: inline-block;
			height: 1px;
			background: linear-gradient(89.95deg, rgba(82, 82, 82, 0.03) 20.01%, #000000 77.77%);
			margin-left: 5px;
			transform: rotate(180deg);
			-ms-transform: rotate(180deg);
			/* IE 9 */
			-webkit-transform: rotate(180deg);
			/* Safari and Chrome */
		}

		#fh5co-slider .container div {
			margin-top: 2em;
			margin-bottom: 2em;
			/*margin-right: 2em;*/
		}

		.flex-control-nav {
			position: relative;
		}

		.first-header {
			width: 100%;
			position: absolute;
			z-index: 999;
		}

		.swiper {
			height: 700px;
		}

		@media (min-width: 1200px) {
			.swiper {
				height: 800px;
			}
		}

		@media (min-width: 1400px) {
			.main-btn {
				margin-top: 50%;
			}

			.swiper {
				height: 1000px;
			}
		}

		@media (min-width: 1600px) {
			.swiper {
				height: 1100px;
			}
		}

		@media (min-width: 1800px) {
			.swiper {
				height: 1200px;
			}
		}

		@media (min-width: 2000px) {
			.swiper {
				height: 1300px;
			}
		}

		@media (max-width: 900px) {
			.swiper {
				height: 550px;
			}

			.main-btn {
				padding: 20px 62px;
				margin-top: 40%;
				margin-right: 10%;
			}
		}

		@media (max-width: 800px) {
			.swiper {
				height: 500px;
			}

			.main-btn {
				padding: 20px 62px;
				margin-top: 50%;
				margin-right: 10%;
			}
		}

		@media (max-width: 480px) {
			.after {
				width: 100px;
			}

			.before {
				width: 100px;
			}

			.swiper {
				height: 360px;
			}

			.main-btn {
				padding: 20px 62px;
				margin-top: 60%;
				margin-right: 10%;
			}
		}

		.banner-main img {
			margin-top: 10%;
		}

		.banner-btn {
			display: flex;
			justify-content: flex-end
		}

		.main-btn:hover {
			background: url(static/img/button_hover.png);
			background-size: 100% 100%;
			border: none;
		}

		.recom {
			position: relative;
		}

		.recom a {
			padding: 0 1rem;
			background: linear-gradient(180deg, #FAFAFA 0%, #FBFBFB 100%);
		}

		.recom div {
			margin: 1em 0;
		}

		.good {
			font-weight: bold;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.number {
			left: 40px;
			top: 20px;
			position: absolute;
			width: 20px
		}

		.events {
			background: linear-gradient(90deg, #F8F9FF 1.21%, #EBF1F4 100%);
		}

		.talklist {
			text-align: left;
			font-size: 1.5em;
			padding: 0.5em 0;
			padding-left: 30%;
			display: flex;
			align-items: center;
		}

		.talk {
			padding-left: 0.5em;
		}

		.talkbtn button {
			background: url(static/img/talkbtn_normal.png) no-repeat;
			background-size: 100% 100%;
			padding: 30px 90px;
			border: none;
			margin-top: 2em;
		}

		.talkbtn button:hover {
			background: url(static/img/talkbtn_hover.png);
			background-size: 100% 100%;
		}

		.sanji {
			width: 0;
			height: 0;
			border-left: 10px solid rgba(2, 2, 2, 1);
			border-top: 10px solid transparent;
			border-bottom: 10px solid transparent;
		}

		@media (max-width: 900px) {
			.talklist {
				padding-left: 15%;
			}
		}

		@media (max-width: 700px) {
			.talklist {
				padding-left: 0;
			}
		}

		@media (max-width: 480px) {
			.show-main {
				font-size: 12px;
				font-weight: bold;
			}

			.talklist {
				padding-left: 15%;
			}
		}
	</style>