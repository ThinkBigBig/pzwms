@include('cms.header')
<div class="container">
	<div class="layui-tab layui-tab-brief" lay-filter="docDemoTabBrief">
		<ul class="layui-tab-title">
			<li class="layui-this" lay-id="1">フランド</li>
			<li lay-id="2">ジャンル</li>
			<li lay-id="3">スニーカー</li>
			<li lay-id="4">アパレル</li>
			<li lay-id="5">フィギュア</li>
		</ul>
	</div>
	<div class="layui-tab-content">
		<div class="layui-tab-item layui-show" id="brand">

		</div>
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
		flex-wrap: wrap
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
		background-color: #fff;
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
		margin: 0 10px;
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
	}
</style>
<script>
	let search = window.location.search
	var searchObj = function searchObj(search) {
		return JSON.parse("{\"".concat(decodeURIComponent(search.substring(1)).replace(/"/g, '\\"').replace(/&/g, '","').replace(/=/g, '":"'), "\"}"));
	};
	let pramas = searchObj(search)
	if (pramas.index == 1) {
		$.ajax({
			type: 'get',
			url: baseUrl + '/api/brandAll',
			success(data) {
				let str = ''
				str += `<div class="brandgroup">`
				for (let index = 0; index < data.data.length; index++) {
					str += `
					<div class="brand">
						<a href=""><img src="${data.data[index].logo}"></a>
					</div>`
				}
				str += `</div>`
				$('#brand').html(str)
			}
		})
	} else if (pramas.index == 2) {
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
			}
		})
	} else if (pramas.index == 3) {
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
			}
		})
	} else if (pramas.index == 4) {
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
			}
		})
	} else if (pramas.index == 5) {
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
			}
		})
	}

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
					url: baseUrl + '/api/seriesAll',
					success(data) {
						let str = ''
						str += `<div class="brandgroup">`
						for (let index = 0; index < data.data.length; index++) {
							str += `
					<div class="brand">
						<a href=""><img src="${data.data[index].logo}"></a>
					</div>`
						}
						str += `</div>`
						$('#brand').html(str)
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
					}
				})
			}
		});
	});
</script>
@include('cms.footer')