@include('member.header')
<div class="layui-row layui-col-space20">
    <div class="layui-col-md6">
        <div class="fly-home fly-panel main">
            <div><img id="avatarimg" src=""></div>
            <div>
                <div> <span id="username1"></span></div>
                <div><i class="iconfont icon-mail"></i><span id="email"></span></div>
                <div>Hi，<span id="username2"></span>，あなたはもうthe1snaker会員。</div>
                <div class="link"><a href="member_profile">パスワード変更></a></div>
            </div>
        </div>
    </div>
    <div class="layui-col-md6">
        <div class="layui-card num">
            <div>
                買取履歴
            </div>
            <div>
                <p class="amount"></p>
            </div>
        </div>
    </div>
    <div class="layui-col-md6">
        <div class="layui-card num">
            <div>
                査定履歴
            </div>
            <div>
                <p class="point"></p>
            </div>
        </div>
    </div>
</div>
<style>
    body {
        background-color: rgba(240, 241, 242, 1);
    }

    .main {
        display: flex;
        text-align: left;
        justify-content: space-around;
        height: 222px;
    }

    .fly-home {
        background-color: #fff;
    }

    .fly-home img {
        width: 60px;
        height: 60px;
    }

    .num {
        display: flex;
        align-items: center;
        justify-content: space-around;
        height: 100px;
    }

    .link {
        position: relative;
        bottom: -50%;
        right: 0;
        display: flex;
        justify-content: flex-end;
    }

    .link a {
        text-decoration: none;
        color: rgba(0, 149, 255, 1);
        font-size: small;
    }

    .layui-nav .layui-nav-item a:hover,
    .layui-nav .layui-this a {
        color: black;
    }
</style>
<script>
    $.ajax({
        type: 'get',
        url: baseUrl + "/api/examine/info",
        headers: {
            'Authorization': sessionStorage.getItem('token'),
        },
        success(data) {
            if (data.code == 200) {
                $('#avatar').attr('src', data.data.member.avatar)  
                $('#avatarimg').attr('src', data.data.member.avatar)
                $('#username').html(data.data.member.username)
                $('#username1').html(data.data.member.username)
                $('#username2').html(data.data.member.username)
                $('#email').html(data.data.member.email)
                $('.point').html(data.data.materiel_count)
                $('.amount').html(data.data.order_count)
            } else {
                layui.msg(data.msg)
            }
        }
    })
</script>
</div>
</div>
<div class="fly-footer">
    <p>古物営業許可第305502007435号 </p>
    <p><a href="http://www.beian.miit.gov.cn/" target="_blank">豫ICP备19030271号-1</a> <a href="/cms_index" target="_blank">the1sneaker</a></p>
</div>
</body>

</html>