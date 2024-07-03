@include('cms.header2')
</div>
<div class="contact w3l-3">
	<div class="container">
		<h2 style="color: #C5D0E1;">無料査定</h2>

		<form class="layui-form" id="emquiry">
			<div class="layui-form-item">
				<label class="layui-form-label">お名前</label>
				<div class="layui-input-block">
					<input type="text" name="name" required lay-verify="required" placeholder="お名前" autocomplete="off" class="layui-input">
				</div>
			</div>
			<div class="layui-form-item">
				<label class="layui-form-label">商品名</label>
				<div class="layui-input-block">
					<input type="text" name="commodity_name" required lay-verify="required" placeholder="商品名" autocomplete="off" class="layui-input">
				</div>
			</div>

			<div class="layui-form-item">
				<label class="layui-form-label">品番</label>
				<div class="layui-input-block">
					<input type="text" name="pinfan" required lay-verify="required" placeholder="品番" autocomplete="off" class="layui-input">
				</div>
			</div>

			<div class="layui-form-item">
				<label class="layui-form-label">サイズ</label>
				<div class="layui-input-block">
					<input type="text" name="rules" required lay-verify="required" placeholder="サイズ" autocomplete="off" class="layui-input">
				</div>
			</div>

			<div class="layui-form-item">
				<label class="layui-form-label">個数</label>
				<div class="layui-input-block">
					<select name="num" required lay-verify="required" class="layui-input">
						<option value="1" selected="selected">1</option>
						<option value="2">2</option>
						<option value="3">3</option>
						<option value="4">4</option>
						<option value="5">5</option>
						<option value="6">6</option>
						<option value="7">7</option>
						<option value="8">8</option>
						<option value="9">9</option>
						<option value="10">10</option>
						<option value="11">11</option>
						<option value="12">12</option>
						<option value="13">13</option>
						<option value="14">14</option>
						<option value="15">15</option>
						<option value="16">16</option>
						<option value="17">17</option>
						<option value="18">18</option>
						<option value="19">19</option>
						<option value="20">20</option>
					</select>

				</div>
			</div>
			<div class="layui-form-item layui-form-text">
				<label class="layui-form-label">お問い合わせ内容</label>
				<div class="layui-input-block">
					<textarea name="contents" placeholder="お問い合わせ内容" class="layui-textarea"></textarea>
				</div>
			</div>
			<div class="layui-form-item">
				<div class="layui-input-block">
					<button class="layui-btn" lay-submit lay-filter="formDemo" type="button" onclick="enquiry()">査定依頼</button>
					<button type="reset" id="reset" class="layui-btn layui-btn-primary" style="display: none;">リセット</button>
					<button class="layui-btn layui-btn-primary" type="button" onclick="goback()">キャンセル</button>
				</div>
			</div>
		</form>

	</div>
</div>

<style type="text/css">
	.layui-btn {
		background-color: #C5D0E1;
	}

	.layui-btn-primary {
		background-color: #fff;
	}

	h1 {
		margin-top: 15px;
		font-weight: 500;
	}

	.layui-form-checked[lay-skin=primary] i {
		border-color: #C5D0E1;
		background-color: #C5D0E1;
		color: #fff;
	}

	.layui-form-label {
		width: 105px;
	}
</style>
<script>
	if (!window.sessionStorage.getItem('token')) {
		location.href = '/member_login'
	}
	let search = window.location.search
	var searchObj = function searchObj(search) {
		return JSON.parse("{\"".concat(decodeURIComponent(search.substring(1)).replace(/"/g, '\\"').replace(/&/g, '","').replace(/=/g, '":"'), "\"}"));
	};
	let pramas = searchObj(search)
	var username = null
	$.ajax({
		type: 'get',
		url: baseUrl + "/api/examine/info",
		headers: {
			'Authorization': sessionStorage.getItem('token'),
		},
		success(data) {
			if (data.code == 200) {
				username = data.data.member.username
				if (pramas) {
					$("input[name='commodity_name']")[0].value = pramas.title
					$("input[name='pinfan']")[0].value = pramas.sn
					$("input[name='rules']")[0].value = pramas.size
					$("input[name='name']")[0].value = username
				}
			} else if (data.code == 401) {
				window.location.href = '/member_login'
			} else {
				layui.msg(data.msg)
			}
		}
	})

	function goback() {
		if (pramas) {
			window.history.back()
		} else {
			location.href = '/cms_index'
		}
	}

	function enquiry() {
		var d = {};
		var t = $('#emquiry [name]').serializeArray();
		$.each(t, function() {
			d[this.name] = this.value;
		});
		let data = d;
		$.ajax({
			type: 'post',
			url: baseUrl + '/api/materiel/add',
			headers: {
				Authorization: sessionStorage.getItem('token')
			},
			data: data,
			success(res) {
				if (res.code == 200) {
					layer.msg(res.msg)
					$('#reset').click()
				} else {
					layer.msg(res.msg)
				}
			}
		})
	}
</script>

@include('cms.footer')