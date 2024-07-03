@include('cms.header2')
<div class="single w3l-5">
	<!-- WRAPPER START -->
	<div class="wrapper bg-dark-white content">
		<div class="product-area single-pro-area pt-80 product-style-2">
			<div class="row shop-list single-pro-info no-sidebar">
				<!-- Single-product start -->
				<div class="col-lg-12">
					<div class="single-product clearfix">
						<div class="container">
							<div class="single-pro-slider single-big-photo view-lightbox slider-for">
								<div>
									<img id="pic" src="" alt="" ../cms />
									<a id="pica" class="view-full-screen" href="" data-lightbox="roadtrip" data-title="My caption">
										<i class="zmdi zmdi-zoom-in"></i>
									</a>
								</div>
							</div>
							<div class="product-info">
								<div class="fix">
									<h4 id="title" titleid='' class="post-title floatleft"></h4>
								</div>
								<!-- <div class="fix mb-20" style="margin-top: 20px;">
									<span class="pro-price good-price">カラー:<span id="car"></span> </span>
								</div> -->
								<div class="fix mb-20" style="margin-top: 20px;">
									<span class="pro-price good-price">品番:<span id="itemnumber"></span></span>
								</div>
								<!-- <div class="fix mb-20" style="margin-top: 20px;">
									<span class="pro-price good-price">定価:<span id="itprice"></span>
									</span>
								</div>
								<div class="fix mb-20" style="margin-top: 20px;">
									<span class="pro-price good-price">発売日:<span id="opentime"></span></span>
								</div> -->
								<div class="size-filter single-pro-size mb-35 clearfix">
									<li><span class="color-title text-capitalize" style="width: 100%;">サイズ/買取金額:</span>
									</li>
									<ul class="layui-row layui-col-space10 layui-this" id="listSize">
									</ul>
								</div>
								<div class="clearfix">
									<div class="cart-plus-minus">
										<input type="text" value="1" name="qtybutton" onclick="getNum()" class="cart-plus-minus-box">
									</div>
								</div>
							</div>
						</div>
						<!-- Single-product end -->
					</div>

				</div>
			</div>
			<div class="product-action clearfix">
				<div class="container buybtn">
					<div class="sime">
						<span id="size"></span><span style="margin:0 5px ;">×</span><span id="num" style="margin-right: 2em;"></span><span id="total"></span>
					</div>
					<button href="#" class="buy" title="買取予約" onclick="addCarts();"></button>
				</div>
			</div>
			<style>
				.buybtn {
					display: flex;
					justify-content: flex-end;
				}

				.cart-plus-minus {
					float: right;
				}

				.buy {
					background: url(static/img/buy.png) no-repeat;
					background-size: 100%;
					background-position: 50%;
					padding: 40px 100px;
				}
			</style>
			<div class="quick-desc clearfix">
				<hr />
				<div style="margin-bottom: 80px;">
					<div class="container">
						<div style="margin-bottom: 80px;">
							<div class="title">《来店買取》</div>
							・予約時間を60分超えた場合自動キャンセルとなりますのでご了承下さい。<br />
						</div>
						<div>
							<div class="title">《郵送買取》</div>
							・商品をカートインし、10分が経過してしまうと自動でカートから削除となります。<br />
							・買取依頼が完了した時点から、5分以内であればキャンセル可能。5分を過ぎた場合はキャンセル不可となります。<br />
							・郵送買取を予約してから、2時間以内に発送をお願い致します。<br />
							・発送時間を超えた場合、自動キャンセルとさせて頂きます。<br />
							・自動キャンセルになったお客様は、今後お買取りをお断りさせて頂きますので十分に気をつけてご予約下さい。<br />
							・商品が到着し、検品の段階で商品の破損、汚れ等が発覚した場合、お買取金額から3000円減額とさせて頂きます。<br />
							・発送前に十分確認の上、発送をお願い致します。<br />
							・発送を完了後、追跡番号の記入を必ずお願い致します。<br />
							・追跡番号の記入がなく2時間を超えてしまうと自動キャンセル扱いになりますのでご注意下さい。<br />
						</div>
					</div>
				</div>
			</div>

		</div>
	</div>
</div>
</div>
<div class="row wow fadeInUp animated animated" data-wow-duration="1200ms" data-wow-delay="500ms" style="text-align: center;display: flex;text-align: center;justify-content: center;align-items: center;">
	<span class="before"></span><span class="show-main">おすすめ</span><span class="after"></span>
</div>
<!--admission-->
<div class="admission w3ls">
	<div class="container">
		<div class="faculty_top" id="recommen">
		</div>
	</div>
</div>
<div id="quickview-wrapper">
	<!-- Modal -->
	<div class="modal fade" id="productModal" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				</div>
				<div class="modal-body">
					<div class="modal-product">
						<div class="product-images">
						</div><!-- .product-images -->
						<div class="product-info">
							<h1></h1>
							<div class="price-box-3">
								<hr />
							</div>
							<div class="quick-add-to-cart">
								<form method="post" class="cart">
									<input type="hidden" name="goodsimage" id="french-goodsimage" value="">
									<input type="hidden" name="goodsname" id="french-goodsname" value="">
									<input type="hidden" name="itemno" id="french-itemno" value="">
									<input type="hidden" name="catid" id="french-catid" value="">
									<input type="hidden" name="goodsid" id="french-goodsid" value="">
									<input type="hidden" name="french-ruleid" id="french-ruleid" value="">
									<input type="hidden" name="french-num" id="french-num" value="1">
									<input type="hidden" name="french-size" id="french-size" value="">
									<input type="hidden" name="french-price" id="french-price" value="">
									<button class="single_add_to_cart_button" type="button" onclick="addCarts()">カートに追加</button>
									<button class="single_add_to_cart_button" type="button" onclick="window.open('./appraisal.html')">無料鑑定</button>
								</form>
							</div>
						</div><!-- .product-info -->
					</div><!-- .modal-product -->
				</div><!-- .modal-body -->
			</div><!-- .modal-content -->
		</div><!-- .modal-dialog -->
	</div>
	<!-- END Modal -->
</div>
<!-- END QUICKVIEW PRODUCT -->

</div>
</div>
<style type="text/css">
	body {
		line-height: 24px;
		font: 14px Helvetica Neue, Helvetica, PingFang SC, Tahoma, Arial, sans-serif;
	}

	.navbar-brand {
		padding: 0 0 0 15px;
	}

	.widget {
		background: none;
		width: 100%;
	}

	.content form input,
	.content form select {
		width: 45%;
	}

	.numbers-row p {
		font-weight: bold;
		/*color:#A52A2A*/
	}

	.title {
		background: radial-gradient(95.29% 46062.04% at 2.15% 66.2%, #C5D0E1 0%, rgba(197, 208, 225, 0) 100%);
		margin-bottom: 20px;
		line-height: 2em;
	}

	.qtybutton:focus-visible {
		outline: -webkit-focus-ring-color auto 1px;
		background-color: rgba(197, 208, 225, 1);
	}


	.single-pro-size li.rules a {
		padding: 10px;
		background: #F5F5F5;
		color: #000;
		border: 1px solid rgba(0, 0, 0, 0.3);
		border-radius: 20px;
	}

	.single-pro-size li.rules.active a {
		background: #C5D0E1;
		color: #000;
	}

	.show-main {
		color: #1D1D1F;
		font-size: 2.3em;
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

	.recom {
		position: relative;
	}

	.recom a {
		padding: 0 1rem;
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

	.recommen {
		background-color: linear-gradient(180deg, #FAFAFA 0%, #FBFBFB 100%);
	}

	.recom img {
		background-color: #fff;
	}

	@media (max-width: 480px) {
		.after {
			width: 100px;
		}

		.before {
			width: 100px;
		}

		.show-main {
			font-size: 12px;
			font-weight: bold;
		}
	}
</style>
<style type="text/css">
	.sime {
		display: flex;
		align-items: center;
		font-weight: bold;
		font-size: large;
		margin-right: 20%;
	}

	.quick-add-to-cart .single_add_to_cart_button {
		padding: 0 30px;
		margin-top: 10px;
	}

	@media (max-width: 480px) {
		.buy {
			padding: 40px 60px;
		}

		.sime {
			font-weight: normal;
			font-size: revert;
			margin-right: 10%;
		}
	}
</style>
<link rel="stylesheet" href="static/js/detail/css/jquery-ui.min.css">
<link rel="stylesheet" href="static/js/detail/css/meanmenu.min.css">
<link rel="stylesheet" href="static/js/detail/css/slick.css">
<link rel="stylesheet" href="static/js/detail/css/material-design-iconic-font.css">
<link rel="stylesheet" href="static/js/detail/css/default.css">
<link rel="stylesheet" href="static/js/detail/css/style.css">
<link rel="stylesheet" href="static/js/detail/css/shortcode.css">
<link rel="stylesheet" href="static/js/detail/css/responsive.css">
<script src="static/js/detail/js/jquery.meanmenu.js"></script>
<script src="static/js/detail/js/slick.min.js"></script>
<script src="static/js/detail/js/jquery.treeview.js"></script>
<script src="static/js/detail/js/jquery-ui.min.js"></script>
<script src="static/js/detail/js/jquery.nicescroll.min.js"></script>
<script src="static/js/detail/js/countdon.min.js"></script>
<script src="static/js/detail/js/plugins.js"></script>
<script src="static/js/detail/js/main.js"></script>
<script type="text/javascript">
	function getNum() {
		$(".cart-plus-minus-box").on('input', function() {
			$('#french-num').val($(".cart-plus-minus-box").val())
			$('#size').html($('.rules:first ._rule').html())
			$('#num').html($('#french-num').val())
			$('#total').html('￥' + formatNumberRgx(($('#french-price').val() * $('#french-num').val())))
		})
	}

	function formatNumberRgx(num) {
		var parts = num.toString().split(".");
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
		return parts.join(".");
	};
	let search = window.location.search
	var searchObj = function searchObj(search) {
		return JSON.parse("{\"".concat(decodeURIComponent(search.substring(1)).replace(/"/g, '\\"').replace(/&/g, '","').replace(/=/g, '":"'), "\"}"));
	};
	let pramas = searchObj(search)
	$.ajax({
		type: 'get',
		url: baseUrl + '/api/product?recommand_status=1&size=12',
		success(data) {
			let str = ''
			let recom = data.data.data
			if (recom.length < 12) {
				for (let index = 0; index < recom.length; index++) {
					str +=
						`<div class="col-md-3 faculty_grid wow animated recom" data-wow-delay=".5s">
							<a href="./cms_detail?id=${recom[index].id}" class="mask">
							<img src="${recom[index].pic}" class="img-responsive zoom-img">
							<img src="" class="number">
							<div class="good">${recom[index].name}</div>
							<div class="name">品番：${recom[index].product_sn}</div>
							<div class="prices">買取金額：￥${recom[index].price}
							</div>
							</a>
						</div>`
				}
			} else {
				for (let index = 0; index < 12; index++) {
					str +=
						`<div class="col-md-3 faculty_grid wow animated recom" data-wow-delay=".5s">
							<a href="./cms_detail?id=${recom[index].id}" class="mask">
							<img src="${recom[index].pic}" class="img-responsive zoom-img">
							<img src="" class="number">
							<div class="good">${recom[index].name}</div>
							<div class="name">品番：${recom[index].product_sn}</div>
							<div class="prices">買取金額：￥${recom[index].price}
							</div>
							</a>
						</div>`
				}
			}
			$('#recommen').html(str)
			$('.recom .number:first').attr("src", " static/img/first.png");
			$('.recom .number:eq(1)').attr("src", " static/img/second.png");
			$('.recom .number:eq(2)').attr("src", " static/img/third.png");
			$('.recom').click(function() {
				$(this).css({
					boxShadow: '8px 10px 10px 1px rgba(0,0,0,0.5)'
				})
			})
		}
	})
	$.ajax({
		type: 'get',
		url: baseUrl + '/api/product/info?id=' + pramas.id,
		async: false,
		success(data) {
			$('#pic').attr('src', data.data.pic)
			$('#pica').attr('href', data.data.pic)
			$('#title').html(data.data.name)
			$('#title').attr('titleid', data.data.id)
			$('#itemnumber').html(data.data.product_sn)
			let priceStr
			if (data.data.price != '暂未出价') {
				priceStr = '￥' + data.data.price
			} else {
				priceStr = data.data.price
			}
			$('#itprice').html(priceStr)
			$('#opentime').html(data.data.release_time)
			let size = data.data.list[0]
			$('#french-price').val(size.web_price);
			$('#french-ruleid').val(size.id);
			let str = ''
			$.each(data.data.list, function(index, item) {
				let size = JSON.parse(item.sp_data)
				if (index == 0) {
					str += `
						<li class="layui-col-xs4 rules active" ruleid="${item.id}"style="cursor:pointer;margin-top: 20px;width: 33%;">
							<a lay-href="javascript:void(0);">
								<div class="_rule" data-size="${size[0].value}">${size[0].value}</div>
									<div class="_price" data-price="${item.web_price}">${item._price=="查定中"?item._price:'￥'+item._price}
								</div>
							</a>
						</li>`
				} else {
					str += `
						<li class="layui-col-xs4 rules" ruleid="${item.id}"style="cursor:pointer;margin-top: 20px;width: 33%;">
							<a lay-href="javascript:void(0);">
								<div class="_rule" data-size="${size[0].value}">${size[0].value}</div>
									<div class="_price" data-price="${item.web_price}">${item._price=="查定中"?item._price:'￥'+item._price}
								</div>
							</a>
						</li>`
				}
			})
			$('#listSize').html(str)
		}
	})
	$(window).load(function() {
		let noprice = '查定中'
		$('#size').html($('.rules:first ._rule').html())
		$('#num').html($('#french-num').val())
		if ($('#french-price').val() * $('#french-num').val() == NaN || $('#french-price').val() * $('#french-num').val() == 0) {
			$('.buy').css({
				'background-image': "url('static/img/seach.png')",
				'background-repeat': 'no-repeat',
			})
			$('#total').html(noprice)
		} else {
			$('#total').html('￥' + formatNumberRgx(($('#french-price').val() * $('#french-num').val())))
		}
		$('.rules').click(function() {
			let size = $.trim($(this).find('._rule').text());
			let datasize = $.trim($(this).find('._rule').attr('data-size'));
			let price = $.trim($(this).find('._price').text());
			let dataprice = $.trim($(this).find('._price').attr('data-price'));
			$('#check_size').text(size);
			$('#check_price').text(price);
			$('#french-size').val(datasize);
			$('#french-price').val(dataprice);
			$('#french-ruleid').val($(this).attr('ruleid'));
			$('.rules').each(function(i) {
				$(this).removeClass('active');
			});
			$('#french-num').val(1)
			$('#num').html($('#french-num').val())
			$('.qtybutton').parent().find("input").val(1)
			$(this).addClass('active');
			$('#size').html($('.active ._rule').html())
			$('#num').html($('#french-num').val())
			if (isNaN($('#french-price').val() * $('#french-num').val())) {
				$('.buy').css({
					'background-image': "url('static/img/seach.png')",
					'background-repeat': 'no-repeat',
				})
				$('#total').html(noprice)
			} else {
				$('.buy').css({
					'background-image': "url('static/img/buy.png')",
					'background-repeat': 'no-repeat',
				})
				$('#total').html('￥' + formatNumberRgx(($('#french-price').val() * $('#french-num').val())))
			}
		})

		// 来店时间控件
		layui.use('laydate', function() {
			var laydate = layui.laydate;
			laydate.render({
				elem: '#shoptime',
				lang: 'en',
				// type: 'datetime',
				type: 'time',
				// format: 'yyyy-MM-dd HH:mm',
				format: 'HH:mm',
				done: function(value, date, endDate) {}
			});
		});

	})
	// 加入购物车
	function addCarts(type) {
		let token = sessionStorage.getItem('token')
		if (!token) {
			window.location.href = '/member_login'
		}
		var productid = $('#title').attr('titleid')
		var goodsimage = $('#french-goodsimage').val();
		var goodsname = $('#french-goodsname').val();
		var itemno = $('#french-itemno').val();
		var catid = $('#french-catid').val();
		var goodsid = $('#french-goodsid').val();
		var ruleid = $('#french-ruleid').val();
		var num = $('#french-num').val();
		var size = $('#french-size').val();
		var price = $('#french-price').val();
		layui.use('layer', function() {
			var layer = layui.layer;
			if ($('#total')[0].innerHTML == '查定中') {
				let size = $('.active ._rule').html()
				let title = $('#title').html()
				let sn = $('#itemnumber').html()
				window.location.href = '/cms_enquiry?size=' + size + '&title=' + title + '&sn=' + sn
			} else {
				$.ajax({
					type: 'get',
					url: baseUrl + '/api/addShopping?product_id=' + productid + '&sku_id=' + ruleid + '&num=' + num,
					headers: {
						'Authorization': sessionStorage.getItem('token'),
					},
					success(data) {
						if (data.code == 200) {
							layer.confirm(data.msg, {
								icon: 3,
								title: 'メッセージ',
								btn: ['さらに追加する', 'カートを見る']
							}, function(index) {
								window.location.reload()
								layer.close(index);
							}, function(index) {
								window.location.href = '/member_car';
								layer.close(index);
							});
						} else {
							layer.confirm(data.msg, {
								icon: 3,
								title: 'メッセージ',
								btn: ['確定', 'キャンセル']
							}, function(index) {
								window.location.reload()
								layer.close(index);
							});
						}
					}
				})
			}
		});
	}
</script>
@include('cms.footer')