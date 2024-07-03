<div class="footer">
    <div class="container">
        <div class="footer-grids">
            <div class="col-md-2 footer-grid animated wow fadeInUp animated animated" data-wow-duration="1200ms" data-wow-delay="500ms">
                <ul>
                    <li>
                        <a href="/cms_market">商品価格</span></a>
                    </li>
                    <li>
                        <a href="/cms_enquiry">無料査定</span></a>
                    </li>
                    <li>
                        <a href="/cms_step">利用ガイド</span></a>
                    </li>
                    <!-- <li>
                        <a href="/cms_index.html">無料鑑定</span></a>
                    </li> -->
                    <li>
                        <a href="/cms_problem">よくある質問</span></a>
                    </li>
                </ul>
            </div>
            <div class="col-md-2 footer-grid wow fadeInLeft animated" data-wow-delay=".5s">
                <ul>
                    <li><a href="/cms_single?catid=1&index=0">利用規約</a></li>
                    <li><a href="/cms_single?catid=1&index=1">プライバシーポリシー</a></li>
                    <li><a href="/cms_single?catid=1&index=2">個人情報保護法</a></li>
                    <li><a href="/cms_single?catid=1&index=3">ソーシャルメディアポリシー</a></li>
                    <li><a href="/cms_single?catid=1&index=4">特定商取引法に基づく表記</a></li>
                </ul>
            </div>
            <div id="shop">
            </div>
            <div class="clearfix"> </div>
        </div>
        <div class="foot-info">
            <div class="info">
                <ul>
                    <li><img src="static/img/footlogo.png" alt=""></li>
                    <li><a href="javascript:void(0)">会社紹介</a></li>
                    <li><a href="javascript:void(0)">お問い合わせ</a></li>
                    <li><a href="javascript:void(0)">採用情報</a></li>
                    <li><a href="/cms_step">利用規約</a></li>
                </ul>
            </div>
            <div class="col-md-2 footer-grid animated wow fadeInUp animated animated" data-wow-duration="1200ms" data-wow-delay="500ms" style="margin-right: 0;padding-right: 0;">
                <ul class="social-icons1" style="display: flex;justify-content: flex-end;">
                    <li><a href="https://twitter.com/the1sneaker?s=21&t=d5Nd9tL3ljpGv9pFs1Gonw"><img src="static/img/twitter.png" style="height:30px ;width:30px;"></img></a></li>
                    <li><a href="https://instagram.com/the1sneaker_?igshid=YmMyMTA2M2Y="><img src="static/img/ins.png" style="height:30px ;width:30px;"></img></a></li>
                    <!-- <li><a href="https://twitter.com/the1sneaker"><img src="static/img/line.png"
                                style="height:30px ;width:30px;"></img></a></li> -->
                    <li><a href="mailto:the1sneaker@gmail.com"><img src="static/img/mail.png" style="height:30px ;width:30px;"></img></a></li>
                </ul>
            </div>
        </div>
        <div class="copy-right wow fadeInUp animated animated" data-wow-duration="1200ms" data-wow-delay="500ms">
            <div class="earth">
                <img src="static/img/earth.png"><span>日本/日本語</span>
            </div>
            <p>東京都公安委員会 第305502007435号</p>
            <p>FIT株式会社 <a href="http://www.beian.miit.gov.cn/" target="_blank">豫ICP备19030271号-1</a> <a href="/cms_index" target="_blank" title="the1sneaker">the1sneaker</a></p>
        </div>
    </div>
</div>
<style>
    .footer {
        background: #333;
        /* margin-top: 40px; */
        color: #9FA3A6;
    }

    .footer a {
        color: #9FA3A6;
    }

    .footer a:hover {
        color: #fff;
    }

    .footer a span {
        color: #9FA3A6;
    }

    .footer a span:hover {
        color: #fff;
    }

    .footer .footer-grid h3 {
        color: #fff;
    }

    .footer-grids {
        margin-bottom: 5em;
    }

    .email-link {
        word-wrap: break-word;
    }

    .foot-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 5px;
        border-bottom: 1px solid #fff;
    }

    .info ul {
        display: flex;
    }

    .info ul li {
        padding: 0 20px;
    }

    .earth {
        float: right;
    }

    @media (max-width: 480px) {
        .info ul {
            flex-direction: column;
        }

        .copy-right p {
            font-size: 14px;
        }
    }
</style>
<script>
    var _hmt = _hmt || [];
    (function() {
        var hm = document.createElement("script");
        hm.src = "https://hm.baidu.com/hm.js?9fd62443f08c83cfd1fb5c01f92a3448";
        var s = document.getElementsByTagName("script")[0];
        s.parentNode.insertBefore(hm, s);
    })();

    //刷新底部栏
    $(function() {
        layui.use(['layer'], function() {
            $.get(baseUrl + "/api/shop", {}, function(data) {
                if (data.code == 200) {
                    var h5 = ''
                    $.each(data.data, function(index, item) {
                        if (index >= 2) {
                            return false;
                        } else {
                            h5 += '<div class="col-md-2 footer-grid wow fadeInRight animated" data-wow-delay=".5s">'
                            h5 += '<p>' + item.shop_name + '</p>'
                            h5 += '<p>' + item.shop_address + '</p>'
                            h5 += '<div class="footer-grid-address">'
                            h5 += '<p>Tel: ' + item.shop_phone + '</p>'
                            h5 += `<p>Email: <a class="email-link" href="mailto:${item.shop_email}">${item.shop_email}</a></p>`
                            h5 += '</div>'
                            h5 += '</div>'
                        }
                    });
                    $('#shop').html(h5)
                } else {
                    layer.alert(data.msg, {
                        icon: 5,
                        title: "信息"
                    });
                }
            }, "json");
        })
    })
</script>
</body>

</html>