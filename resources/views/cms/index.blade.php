@include('cms.header')
</style>
<div class="row wow fadeInUp animated animated title" data-wow-duration="1200ms" data-wow-delay="500ms" style="text-align: center;padding-top: 5em;">
	<span class="before"></span><span class="show-main">強化買取</span><span class="after"></span>
</div>
<!--admission-->
<div class="admission w3ls">
	<div class="container">
		<div class="faculty_top" id="recommen">
		</div>
	</div>
</div>
<script>
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
							<div class="price">買取金額：￥${recom[index].price}
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
							<div class="price">買取金額：￥${recom[index].price}
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
</script>
<div class="row wow fadeInUp animated animated title" data-wow-duration="1200ms" data-wow-delay="500ms" style="text-align: center;padding: 5em 0;">
	<span class="before"></span><span class="show-main">サービス</span><span class="after"></span>
</div>
<div class="events agile">
	<div class="container">
		<div class="events-grids">
			<div class="col-md-6 bnr-galry-right wow fadeInLeft animated" data-wow-delay=".5s">
				<a href="javascript:void(0);" class="mask">
					<img src=" static/img/shose.png" class="img-responsive zoom-img" alt="">
				</a>
			</div>
			<div class="bnr-galry">
				<div class="col-md-6 bnr-galry-left wow fadeInRight animated shosetalk" data-wow-delay=".5s">
					<h3>無料査定</h3>
					<div class="talklist"><span class="sanji"></span><span class="talk">24時間以内の査定</span></div>
					<div class="talklist"><span class="sanji"></span><span class="talk">業界No.1の査定金額</span></div>
					<div class="talklist"><span class="sanji"></span><span class="talk">リピーター率95％</span></div>
					<div class="talkbtn"><button onclick='javascript:location.href="/cms_enquiry"'></button>
					</div>
				</div>
				<div class="clearfix"></div>
			</div>
			<div class="clearfix"></div>
		</div>
	</div>
</div>

<!--events-->
<div class="row wow fadeInUp animated animated title" data-wow-duration="1200ms" data-wow-delay="500ms" style="text-align: center;padding-top: 5em;margin-bottom: 5em;">
	<span class="before"></span><span class="show-main">カテゴリ</span><span class="after"></span>
</div>
<div class="container">
	<div class="layui-tab layui-tab-brief" lay-filter="docDemoTabBrief">
		<ul class="layui-tab-title">
			<li class="layui-this">ブランド</li>
			<li>ジャンル</li>
			<li>スニーカー</li>
			<li>アパレル</li>
			<li>フィギュア</li>
		</ul>
	</div>
	<div class="layui-tab-content">
		<div class="layui-tab-item layui-show" id="brand">

		</div>
		<div class="gopro"><a href="/cms_market" id="togo">詳しくはこちら></a></div>
	</div>
</div>
<style>
	.gopro {
		text-align: center;
		margin-bottom: 5em;
	}

	.gopro a {
		color: #1890FF;
	}

	.brandgroup {
		margin: 20px 0;
		display: flex;
		justify-content: space-around;
	}

	.brandlist {
		margin: 20px 0;
		display: flex;
		justify-content: space-around;
		flex-direction: column
	}

	.brand {
		width: 12.5%;
		padding: 10px 0;
		margin: 10px;
		text-align: center;
		display: flex;
		align-items: center;
		justify-content: center;
		background-color: #fff;
	}

	.brandtitle {
		padding: 10px 0;
		margin: 10px;
		text-align: center;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.brand img {
		width: 50%;
	}

	.brandmain {
		display: flex;
		flex-wrap: wrap;
		justify-content: center;
		margin: 10px;
	}

	.brandmain img {
		width: 12.5%;
		margin: 10px;
	}

	.brandlist .brandtitle img {
		width: 10%;
	}

	.layui-tab-title {
		display: flex;
		justify-content: space-around;
	}

	.layui-tab-brief>.layui-tab-title .layui-this {
		color: #333333;
	}

	.layui-tab-brief>.layui-tab-more li.layui-this:after,
	.layui-tab-brief>.layui-tab-title .layui-this:after {
		border-bottom: 2px solid #333;
	}

	@media (max-width: 480px) {
		.brandgroup {
			flex-direction: column
		}
		.brand{
			width: 100%;
		}
	}

	.node-treemin a {
		pointer-events: none;
	}
</style>
<script>
	$.ajax({
		type: 'get',
		url: baseUrl + '/api/brandAll',
		success(data) {
			let str = ''
			str += `<div class="brandgroup">`
			if (data.data.length < 8) {
				for (let index = 0; index < data.data.length; index++) {
					str += `
					<div class="brand">
						<a href=""><img src="${data.data[index].logo}"></a>
					</div>`
				}
			} else {
				for (let index = 0; index < 8; index++) {
					str += `
					<div class="brand">
						<a href=""><img src="${data.data[index].logo}"></a>
					</div>`
				}
			}
			str += `</div>`
			$('#brand').html(str)
			$('#togo').attr('href', '/cms_brand?index=1')
		}
	})
	layui.use('element', function() {
		var $ = layui.jquery,
			element = layui.element; //Tab的切换功能，切换事件监听等，需要依赖element模块
		$('.site-demo-active').on('click', function() {
			var othis = $(this),
				type = othis.data('type');
			active[type] ? active[type].call(this, othis) : '';
		});
		element.on('tab(docDemoTabBrief)', function(data) {
			if (data.index == 0) {
				$.ajax({
					type: 'get',
					url: baseUrl + '/api/brandAll',
					success(data) {
						let str = ''
						str += `<div class="brandgroup">`
						if (data.data.length < 8) {
							for (let index = 0; index < data.data.length; index++) {
								str += `
								<div class="brand">
									<a href=""><img src="${data.data[index].logo}"></a>
								</div>`
							}
						} else {
							for (let index = 0; index < 8; index++) {
								str += `
								<div class="brand">
									<a href=""><img src="${data.data[index].logo}"></a>
								</div>`
							}
						}
						str += `</div>`
						$('#brand').html(str)
						$('#togo').attr('href', '/cms_brand?index=1')
					}
				})
			} else if (data.index == 1) {
				$.ajax({
					type: 'get',
					url: baseUrl + '/api/seriesAll',
					success(res) {
						let str = ''
						str += `<div class="brandlist">`
						for (let index = 0; index < res.data.length; index++) {
							str += `
							<div class="brandtitle">
								<span class="before"></span><img src="${res.data[index].logo}"><span class="after"></span>
								</div>
								<div class="brandmain">`
							for (let i = 0; i < res.data[index].list.length; i++) {
								str += `<img src="${res.data[index].list[i].icon}">`
							}
							str += `
								</div>`
						}
						str += `</div>`
						$('#brand').html(str)
						$('#togo').attr('href', '/cms_brand?index=' + data.index)
					}
				})
			} else if (data.index == 2) {
				$.ajax({
					type: 'get',
					url: baseUrl + '/api/seriesAll?category_id=70',
					success(res) {
						let str = ''
						str += `<div class="brandlist">`
						for (let index = 0; index < res.data.length; index++) {
							str += `
							<div class="brandtitle">
								<span class="before"></span><img src="${res.data[index].logo}"><span class="after"></span>
								</div>
								<div class="brandmain">`
							for (let i = 0; i < res.data[index].list.length; i++) {
								str += `<img src="${res.data[index].list[i].icon}">`
							}
							str += `
								</div>`
						}
						str += `</div>`
						$('#brand').html(str)
						$('#togo').attr('href', '/cms_brand?index=' + data.index)
					}
				})
			} else if (data.index == 3) {
				$.ajax({
					type: 'get',
					url: baseUrl + '/api/seriesAll?category_id=55',
					success(res) {
						let str = ''
						str += `<div class="brandlist">`
						for (let index = 0; index < res.data.length; index++) {
							str += `
							<div class="brandtitle">
								<span class="before"></span><img src="${res.data[index].logo}"><span class="after"></span>
								</div>
								<div class="brandmain">`
							for (let i = 0; i < res.data[index].list.length; i++) {
								str += `<img src="${res.data[index].list[i].icon}">`
							}
							str += `
								</div>`
						}
						str += `</div>`
						$('#brand').html(str)
						$('#togo').attr('href', '/cms_brand?index=' + data.index)
					}
				})
			} else if (data.index == 4) {
				$.ajax({
					type: 'get',
					url: baseUrl + '/api/seriesAll?category_id=58',
					success(res) {
						let str = ''
						str += `<div class="brandlist">`
						for (let index = 0; index < res.data.length; index++) {
							str += `
							<div class="brandtitle">
								<span class="before"></span><img src="${res.data[index].logo}"><span class="after"></span>
								</div>
								<div class="brandmain">`
							for (let i = 0; i < res.data[index].list.length; i++) {
								str += `<img src="${res.data[index].list[i].icon}">`
							}
							str += `
								</div>`
						}
						str += `</div>`
						$('#brand').html(str)
						$('#togo').attr('href', '/cms_brand?index=' + data.index)
					}
				})
			}
		});
	});
</script>
@include('cms.footer')