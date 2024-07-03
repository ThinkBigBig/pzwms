@include('cms.header2')
<script src="static/js/bootstrap-treeview.js"></script>
<div class="services w3l-4">
	<div class="container mainbox">
		<div class="tree">
			<div class="somemain">
				<div class="count">
					<div>対象商品</div>
					<div id="total"><span style="font-weight: bold;font-size: large;" id="totalin">
						</span><span>件</span></div>
				</div>
				<span class="sj">
				</span>
			</div>
			<div class="treemin" id="treemin"></div>
		</div>
		<div id="treeview_23">

		</div>
	</div>
	<div style="display: flex;justify-content: center;align-items: center;">
		<div id="demo1"></div>
	</div>
</div>

<style type="text/css">
	.treemin {
		width: 90%;
	}

	.somemain {
		width: 90%;
		position: relative;
	}

	.sj {
		position: absolute;
		top: 35%;
		right: -20px;
		width: 0;
		height: 0;
		border-top: 10px solid transparent;
		border-left: 20px solid rgba(47, 46, 65, 1);
		border-bottom: 10px solid transparent;
	}

	.count {
		padding: 20px;
		border-radius: 10px;
		background-color: rgba(47, 46, 65, 1);
		color: #fff;
	}

	#good_name {
		outline: 2px solid;
	}

	.glyphicon {
		color: #333;
		line-height: 1;
		font-size: 1em;
	}

	.tree-modle {
		position: relative;
	}

	.mainbox {
		display: flex;
	}

	.type {
		position: absolute;
		width: 30px;
		height: 30px;
		display: none;
		top: -6rem;
		right: 1rem;
		cursor: pointer;
	}

	#input-ssss12 {
		width: 100%;
		text-align: center;
		padding: 5% 20% 0 10%;
	}

	.i12 {
		display: flex;
		align-items: center;
	}

	#ssk {
		width: 30px;
		height: 30px;
		margin-left: -30px;
	}

	#ssk img {
		width: 30px;
		height: 30px;
		position: relative;
		right: 1rem;
	}

	.col-md-3 ul {
		display: flex;
		flex-wrap: wrap;
	}

	.col-md-3 li {
		display: flex;
	}

	.treeview1 ul {
		text-align: center;
	}

	.services-grid {
		margin-bottom: 1em;
	}

	.services-grid p {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.services-grid .good {
		color: rgba(66, 66, 69, 1);
		text-transform: capitalize;
		margin: 0.5em 0 0.3em;
	}

	.tree {
		width: 25%;
	}

	#treeview_23 {
		width: 75%;
		display: flex;
		flex-wrap: wrap;
	}

	.grid {
		background-color: #EEEDED;
	}

	.history {
		margin-left: 110px;
		border: 2px solid #d2d2d2;
		border-top: none;
		font-size: 14px;
		color: #555;
		padding-bottom: 10px;
	}

	.his_top {
		height: 2rem;
	}

	.tit {
		float: left;
		margin-left: 2%;
	}

	.del {
		float: right;
		margin-right: 2%;
		cursor: pointer;
	}

	.his_go {
		text-align: left;
		padding-left: 2em;
		cursor: pointer;
	}

	.his_none {
		text-align: center;
	}

	.grid {
		display: flex;
		flex-direction: column;
	}
	.grid img{
		background-color: #fff;
	}

	.good-fater {
		padding: 10px 15px;
		margin-bottom: -1px;
		background-color: #fff;
		border: 1px solid #ddd;
	}

	.goodli {
		width: 95%;
		padding: 10px 15px;
		margin-bottom: -1px;
		background-color: #fff;
		border: 1px solid #ddd;
	}

	.layui-laypage .layui-laypage-curr .layui-laypage-em {
		background-color: #2A528A;
	}

	@media screen and (min-width: 1440px) {
		#input-ssss12 {
			padding: 5% 25% 5% 20%;
		}
	}

	@media screen and (max-width: 750px) {
		.glyphicon-chevron-right:before {
			font-size: 14px;
		}

		.glyphicon-chevron-left:before {
			font-size: 14px;
		}

		.count {
			margin-right: 0;
		}
	}

	@media screen and (max-width: 600px) {
		.tree {
			width: 100%;
		}

		.somemain {
			width: 100%;
		}

		.treemin {
			width: 100%;
		}

		.sj {
			display: none;
		}

		#treeview_23 {
			width: 100%;
		}

		.mainbox {
			flex-direction: column;
		}
	}

	@media screen and (max-width: 480px) {

		.services-grid {
			width: 50%;
		}

		.history {
			margin-left: 0;
		}

		.layui-input-block {
			margin-left: 0px;
		}

		.glyphicon-chevron-right:before {
			font-size: 12px;
		}

		.glyphicon-chevron-left:before {
			font-size: 12px;
		}
	}
</style>
<script type="text/javascript">
	$(function() {
		let url = location.href
		if (url.indexOf('name') == -1) {
			var defaultData = new Array();
			// 获取菜单
			layui.use(['layer'], function() {
				layer = layui.layer;
				laypage = layui.laypage
				$.ajax({
					type: 'get',
					url: baseUrl + '/api/seriesAll',
					success(data) {
						var menu = new Array();
						$.each(data.data, function(index, itemparent) {
							var _tag = new Array("0");
							var _data = {
								text: itemparent.name,
								href: '/cms_market?link=' + itemparent.id,
								tags: _tag,
								nodes: [],
								nodeId: itemparent.id
							}
							if (itemparent.list.length > 0) {
								$.each(itemparent.list, function(index, item) {
									var _data_child = {
										text: item.name,
										href: '/cms_market?link=' + itemparent.id + '&id=' + item.id,
										tags: _tag,
										nodeId: item.id
									}
									_data['nodes'].push(_data_child)
								});
							}
							menu.push(_data);
						});
						defaultData = menu;
						$('#treemin').treeview({
							data: defaultData,
							expandIcon: "glyphicon glyphicon-chevron-down",
							collapseIcon: "glyphicon glyphicon-chevron-up", //可收缩的节点图标
							enableLinks: true,
							levels: 1,
							selectedBackColor: "rgba(197, 208, 225, 1)", //设置选定节点的背景色
						});
						if (window.location.href.indexOf('?') == -1) {
							// $('#treemin').treeview('expandNode', [0, { silent: true }]);
							// $('li[data-nodeid="1"]').css({ "color": "#FFFFFF", "background-color": "rgba(197, 208, 225, 1)" })
							$.ajax({
								type: 'get',
								url: baseUrl + '/api/product?size=18&current_page=1',
								success(res) {
									num = res.data.total
									layui.use(['laypage', 'layer'], function() {
										var laypage = layui.laypage,
											layer = layui.layer;
										laypage.render({
											elem: 'demo1',
											count: num,
											layout: ['prev', 'page', 'next'],
											prev: '<em>←</em>',
											next: '<em>→</em>',
											limit: 18,
											jump: function(obj) {
												current_page = obj
												$.ajax({
													type: 'get',
													url: baseUrl + '/api/product?size=18&cur_page=' + current_page.curr,
													success(res) {
														$('#totalin').html(res.data.total)
														let str = ''
														$.each(res.data.data, function(index, item) {
															str += `	
																<div class="col-md-4 services-grid">
																<div class="grid">
																	<a href="/cms_detail?id=${item.id}" class="mask">
																		<img src="${item.pic}" class="img-responsive zoom-img">
																	</a>
																	<p class="good">${item.name}</p>
																	<p>品番：${item.product_sn}<br />買取金額：
																	${item.price=='查定中'?item.price:'￥'+item.price}
																	</p>
																</div>
															</div>`
														})
														$('#treeview_23').html(str)
													}
												})
											}
										})
									})
									$('#totalin').html(res.data.total)
									let str = ''
									$.each(res.data.data, function(index, item) {
										str += `	
											<div class="col-md-4 services-grid">
											<div class="grid">
												<a href="/cms_detail?id=${item.id}" class="mask">
													<img src="${item.pic}" class="img-responsive zoom-img">
												</a>
												<p class="good">${item.name}</p>
												<p>品番：${item.product_sn}<br />買取金額：
												${item.price=='查定中'?item.price:'￥'+item.price}
												</p>
											</div>
										</div>`
									})
									$('#treeview_23').html(str)
								}
							})
						} else {
							let search = window.location.search
							var searchObj = function searchObj(search) {
								return JSON.parse("{\"".concat(decodeURIComponent(search.substring(1)).replace(/"/g, '\\"').replace(/&/g, '","').replace(/=/g, '":"'), "\"}"));
							};
							let pramas = searchObj(search)
							if (pramas.id) {
								if ($('li[id =' + pramas.id + ']')) {
									let p = $('li[id =' + pramas.link + ']').attr('data-nodeid') - 0
									$('#treemin').treeview('expandNode', [p, {
										silent: true
									}]);
									$('li[id =' + pramas.id + ']').css({
										"color": "#FFFFFF",
										"background-color": "rgba(197, 208, 225, 1)"
									})
								}
								$.ajax({
									type: 'get',
									url: baseUrl + '/api/product?series_id=' + pramas.id,
									success(res) {
										num = res.data.total
										layui.use(['laypage', 'layer'], function() {
											var laypage = layui.laypage,
												layer = layui.layer;
											laypage.render({
												elem: 'demo1',
												count: num,
												layout: ['prev', 'page', 'next'],
												prev: '<em>←</em>',
												next: '<em>→</em>',
												limit: 18,
												jump: function(obj) {
													current_page = obj
													$.ajax({
														type: 'get',
														url: baseUrl + '/api/product?size=18&series_id=' + pramas.id + '&cur_page=' + current_page.curr,
														success(res) {
															$('#totalin').html(res.data.total)
															let str = ''
															$.each(res.data.data, function(index, item) {
																str += `	
																<div class="col-md-4 services-grid">
																<div class="grid">
																	<a href="/cms_detail?id=${item.id}" class="mask">
																		<img src="${item.pic}" class="img-responsive zoom-img">
																	</a>
																	<p class="good">${item.name}</p>
																	<p>品番：${item.product_sn}<br />買取金額：
																	${item.price=='查定中'?item.price:'￥'+item.price}
																	</p>
																</div>
															</div>`
															})
															$('#treeview_23').html(str)
														}
													})
												}
											})
										})
										$('#totalin').html(res.data.total)
										let str = ''
										$.each(res.data.data, function(index, item) {
											str += `	
											<div class="col-md-4 services-grid">
											<div class="grid">
												<a href="/cms_detail?id=${item.id}" class="mask">
													<img src="${item.pic}" class="img-responsive zoom-img">
												</a>
												<p class="good">${item.name}</p>
												<p>品番：${item.product_sn}<br />買取金額：
												${item.price=='查定中'?item.price:'￥'+item.price}
												</p>
											</div>
										</div>`
										})
										$('#treeview_23').html(str)
									}
								})
							} else {
								if ($('li[id =' + pramas.id + ']')) {
									$('li[id =' + pramas.link + ']').css({
										"color": "#FFFFFF",
										"background-color": "rgba(197, 208, 225, 1)"
									})
								}
								$.ajax({
									type: 'get',
									url: baseUrl + '/api/product?brand_id=' + pramas.link,
									success(res) {
										num = res.data.total
										layui.use(['laypage', 'layer'], function() {
											var laypage = layui.laypage,
												layer = layui.layer;
											laypage.render({
												elem: 'demo1',
												count: num,
												layout: ['prev', 'page', 'next'],
												prev: '<em>←</em>',
												next: '<em>→</em>',
												limit: 18,
												jump: function(obj) {
													current_page = obj
													$.ajax({
														type: 'get',
														url: baseUrl + '/api/product?size=18&brand_id=' + pramas.link + '&cur_page=' + current_page.curr,
														success(res) {
															$('#totalin').html(res.data.total)
															let str = ''
															console.log(res);
															$.each(res.data.data, function(index, item) {
																str += `	
																<div class="col-md-4 services-grid">
																<div class="grid">
																	<a href="/cms_detail?id=${item.id}" class="mask">
																		<img src="${item.pic}" class="img-responsive zoom-img">
																	</a>
																	<p class="good">${item.name}</p>
																	<p>品番：${item.product_sn}<br />買取金額：
																		${item.price=='查定中'?item.price:'￥'+item.price}
																	</p>
																</div>
															</div>`
															})
															$('#treeview_23').html(str)
														}
													})
												}
											})
										})
										$('#totalin').html(res.data.total)
										let str = ''
										$.each(res.data.data, function(index, item) {
											str += `	
											<div class="col-md-4 services-grid">
											<div class="grid">
												<a href="/cms_detail?id=${item.id}" class="mask">
													<img src="${item.pic}" class="img-responsive zoom-img">
												</a>
												<p class="good">${item.name}</p>
												<p>品番：${item.product_sn}<br />買取金額：
												${item.price=='查定中'?item.price:'￥'+item.price}
												</p>
											</div>
										</div>`
										})
										$('#treeview_23').html(str)
									}
								})
							}

						}
					}
				})
			});
		} else {
			let search = window.location.search
			var searchObj = function searchObj(search) {
				return JSON.parse("{\"".concat(decodeURIComponent(search.substring(1)).replace(/"/g, '\\"').replace(/&/g, '","').replace(/=/g, '":"'), "\"}"));
			};
			let pramas = searchObj(search)
			$.ajax({
				type: 'get',
				url: baseUrl + '/api/product?name=' + pramas.name,
				success(res) {
					$('#totalin').html(res.data.total)
					let str = ''
					if (res.data.data.length > 0) {
						$.each(res.data.data, function(index, item) {
							str += `	
								<div class="col-md-4 services-grid">
									<div class="grid">
										<a href="/cms_detail?id=${item.id}" class="mask">
											<img src="${item.pic}" class="img-responsive zoom-img">
										</a>
										<p class="good">${item.name}</p>
										<p>品番：${item.product_sn}<br />買取金額：
										${item.price=='查定中'?item.price:'￥'+item.price}
										</p>
									</div>
								</div>`
						})
					} else {
						str += `<div>没有该商品</div>`
					}
					$('#treeview_23').html(str)
				}
			})
			$('.tree').css('opacity', '0')
		}
	});
</script>
@include('cms.footer')