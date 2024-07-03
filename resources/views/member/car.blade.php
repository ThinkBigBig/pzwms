@include('member.header')
<div class="layui-tab layui-tab-brief">
    <ul class="layui-tab-title">
        <li class="layui-this">カート</li>
    </ul>
    <div class="layui-tab-content" style="padding: 20px 0">
        <div class="layui-tab-item layui-show">
            <blockquote class="layui-elem-quote quoteBox">
                <div class="layui-input-inline">
                    <input type="hidden" name="checked_orders" id="checked_orders" value="" />
                    <button type="button" class="layui-btn layui-btn-now layui-btn-sm" onclick="addOrder()">
                        買取依頼
                    </button>
                </div>
            </blockquote>

            <table class="layui-hide" id="dataTable" lay-filter="dataTable"></table>
        </div>
    </div>
</div>
<div class="modal fade" id="enquiryDetailModal" tabindex="-1" role="dialog" aria-labelledby="enquiryDetailModalLabel" style="z-index: 10000">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="enquiryDetailModalLabel">
                    商品詳細
                </h4>
            </div>
            <div class="modal-body">
                <!-- 鉴定明细数据 -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    閉じ
                </button>
                <!-- <button type="button" class="btn btn-primary">确定</button> -->
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="addOrderModal" tabindex="-1" role="dialog" aria-labelledby="addOrderModalLabel" style="z-index: 10000">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="addOrderModalLabel">
                    オーダー確認
                </h4>
            </div>
            <div class="modal-body">
                <div class="order_list">
                    <table class="table table-condensed" style="width: 100%">
                        <caption style="font-weight: bold; font-size: 16px">
                            予約内容
                        </caption>
                        <thead>
                            <tr>
                                <th style="	overflow:hidden;text-overflow:ellipsis;white-space:nowrap;width:40%">商品名</th>
                                <th>品番</th>
                                <th>サイズ</th>
                                <th>価格</th>
                                <th>数量</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="numbers-row">
                    <div class="form-group">
                        <label for="shoptime" class="control-label" id="heji" style="float: right; width: 200px">合計商品点数（件）：2</label>
                        <div class="layui-input-block"></div>
                    </div>
                </div>

                <div class="numbers-row">
                    <div class="form-group">
                        <label class="control-label" style="width: 260px">買取方法の選択</label>
                        <div class="layui-input-block" style="margin-left: 0px">
                            <br />
                            <div class="btn-group" data-toggle="buttons" id="Select">
                                <label class="btn btn-default active">
                                    <input type="radio" name="rd" id="come" value="2" />
                                    来店買取
                                </label>
                                <label class="btn btn-default" style="margin-left: 80px">
                                    <input type="radio" name="rd" id="mail" value="1" />
                                    郵送買取
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="numbers-row">
                    <div class="form-group">
                        <label class="control-label" style="width: 200px">店鋪の選択</label>
                        <div class="layui-input-block" style="margin-left: 0px">
                            <div class="btn-group" data-toggle="buttons" id="Select2">
                                <label id="dianpu" name="mdianpu1 cdianpu2" class="btn btn-default">
                                    <input type="radio" />
                                    <p>地址:</p>
                                    <p>电话:</p>
                                    <p>Email:</p>
                                    <p>営業時間:</p>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <hr />
                <div>
                    <div class="form-group w" id="group123" style="margin-left: 0px">
                        <label for="shoptime" class="control-label">来店:</label>
                        <div style="display: flex; align-items: center">
                            <input type="text" class="form-control" id="shoptime" style="width: 18%; padding-right: 5px" name="shoptime" placeholder="HH:mm" readonly="readonly" />
                            <i class="layui-icon layui-icon-about" id="title" title="ご予約時間から当日の閉店時間までお問い合わせいただけます"></i>
                            <p id="prompt">
                                ご予約時間から当日の閉店時間までお問い合わせいただけます
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    閉じ
                </button>
                <button type="button" id="confirmOrder" class="btn">
                    確認買取
                </button>
            </div>
        </div>
    </div>
</div>
<style type="text/css">
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

    .layui-laydate .layui-this {
        background-color: #dbe1ea !important;
    }

    .layui-elem-quote {
        border-left: none;
    }

    .layui-tab-brief>.layui-tab-title .layui-this {
        color: #000;
    }

    .layui-tab-brief>.layui-tab-more li.layui-this:after,
    .layui-tab-brief>.layui-tab-title .layui-this:after {
        border-bottom: 2px solid rgba(0, 0, 0, 0.3);
    }


    .btn-default.active,
    .open .dropdown-toggle.btn-default {
        background-color: #dbe1ea;
    }

    .btn-default:active,
    .btn-default:focus {
        background-color: transparent;
    }

    .layui-table-cell {
        height: auto !important;
        white-space: normal;
        text-align: center;
    }

    .layui-btn+.layui-btn {
        margin-left: 0px;
    }

    .laydate-time-list ol {
        overflow-x: hidden !important;
        overflow-y: auto !important;
    }

    .control-label {
        width: 100px;
        /*text-align: */
    }

    .layui-none {
        width: 100% !important;
    }

    .layui-icon-about {
        padding: 0 0 10px 5px;
        cursor: pointer;
    }

    #Select2 {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
    }

    #Select2 label {
        width: 48%;
        letter-spacing: -0.02em;
    }

    #prompt {
        display: none;
        font-size: 0.8rem;
        width: 50%;
    }

    .layui-btn-now {
        background: #a8b6cb;
    }

    .shop_p {
        white-space: normal;
        word-wrap: break-word;
    }
</style>
<script>
    let dateTime = null
    var data = null;
    $("#title").click(function() {
        $("#prompt").show("fast");
        $("#prompt").delay(3000).hide("fast");
    });

    function timestampToTime(timestamp) {
        timestamp = timestamp ? timestamp : null;
        let date = new Date(timestamp * 1000); //时间戳为10位需*1000，时间戳为13位的话不需乘1000
        let Y = date.getFullYear() + '-';
        let M = (date.getMonth() + 1 < 10 ? '0' + (date.getMonth() + 1) : date.getMonth() + 1) + '-';
        let D = (date.getDate() < 10 ? '0' + date.getDate() : date.getDate()) + ' ';
        let h = (date.getHours() < 10 ? '0' + date.getHours() : date.getHours()) + ':';
        let m = (date.getMinutes() < 10 ? '0' + date.getMinutes() : date.getMinutes()) + ':';
        let s = date.getSeconds() < 10 ? '0' + date.getSeconds() : date.getSeconds();
        return Y + M + D + h + m + s;
    }
    layui.use(["table"], function() {
        var table = layui.table,
            $ = layui.$,
            form = layui.form;
        table.render({
            elem: "#dataTable",
            url: baseUrl + '/api/shopping' //数据接口
                ,
            method: 'get',
            headers: {
                'Authorization': sessionStorage.getItem('token')
            },
            text: {
                none: "オーダー0", //默认：无数据。注：该属性为 layui 2.2.5 开始新增
            },
            cols: [
                [{
                        checkbox: true
                    },
                    {
                        field: "goodsimage",
                        title: "商品写真",
                        width: 120,
                        templet: function(d) {
                            return `<div><img src="${d.product.pic}"></div>`
                        }
                    },
                    {
                        field: "goodsname",
                        title: "商品名",
                        width: 120,
                        templet: function(d) {
                            return `<div>${ d.product.name}</div>`
                        }
                    },
                    // {field: 'order_num', title: 'オーダー番号', width:180},
                    {
                        field: "size",
                        title: "サイズ",
                        width: 90,
                        sort: true,
                        "templet": function(d) {
                            if (d.sku_stock.sp_data) {
                                return getOrderItemInfo(d.sku_stock.sp_data);
                            } else {
                                return '暂无'
                            }
                        },
                    },
                    {
                        field: "itemno",
                        title: "商品番号",
                        width: 105,
                        sort: true,
                        templet: function(d) {
                            return `<div>${d.product.product_sn}</div>`
                        },
                    },
                    {
                        field: "price",
                        title: "価格",
                        width: 80,
                        sort: true,
                        templet: function(d) {
                            return `<div>${d.sku_stock._price}</div>`
                        },
                    },
                    {
                        field: "num",
                        title: "数量",
                        width: 60
                    },
                    {
                        field: "createtime",
                        title: "残り時間",
                        width: 120,
                        templet: function(d) {
                            return `<div><dd title="请在有效时长内完成下单" style="cursor:pointer" id="timer${ d.id }" class="timer" data="${ d.createtime }" cartId = "${ d.id }"></dd></div>`
                        }
                    },
                    {
                        field: "number",
                        title: "詳細",
                        width: 120,
                        templet: function(d) {
                            return `<a href="#" onclick="javascript:return false;" class="layui-btn layui-btn-now1 layui-btn-xs" data-toggle="modal" data-target="#enquiryDetailModal" data-whatever="${ d.id }">詳細</a><a href="${d.id}" class="layui-btn layui-btn-danger layui-btn-xs layui-tr-del2" lay-event="del">削除</a>`
                        }
                    },
                ],
            ],
            page: {},
            request: {
                pageName: 'cur_page', // page
                limitName: 'size' // limit
            },
            parseData: function(res) { //res 即为原始返回的数据
                return {
                    "code": 0, //解析接口状态
                    "msg": res.msg, //解析提示文本
                    "count": res.data.total, //解析数据长度
                    "data": res.data.data //解析数据列表
                }
            }
        });
    });

    function getOrderItemInfo(data) {
        data = JSON.parse(data)
        var strHtml = "<div>";
        $.each(data, function(i, ele) {
            strHtml += ele.value + "<br/>";
        })
        strHtml += "</div>";
        return strHtml;
    }

    function addOrder() {
        var that = $(this),
            query = "";
        var tableObj = that.attr("data-table") ?
            that.attr("data-table") :
            "dataTable";

        layui.use(["table", "layer"], function() {
            var table = layui.table,
                layer = layui.layer;
            if ($(".checkbox-ids:checked").length <= 0) {
                var checkStatus = table.checkStatus(tableObj);
                if (checkStatus.data.length <= 0) {
                    layer.msg("買取希望の商品を選択して下さい。");
                    return false;
                }
                for (var i in checkStatus.data) {
                    if (i > 0) {
                        query += ",";
                    }
                    query += checkStatus.data[i].id;
                }
            } else {
                if (that.parents("form")[0]) {
                    query = that.parents("form").serialize();
                } else {
                    query = $("#pageListForm").serialize();
                }
            }
        });
        if (query != "") {
            $("#checked_orders").val(query);
            $("#addOrderModal").modal("show");
        }
    }

    $(function() {
        let checked_orders = null
        // 来店时间控件
        layui.use("laydate", function() {
            var laydate = layui.laydate;
            var data_h = new Date();
            var data_min = data_h.getHours(); //获取系统时，
            data_min += ":";
            data_min += data_h.getMinutes(); //分
            data_min += ":";
            data_min += data_h.getSeconds(); //分
            var shopTime = laydate.render({
                elem: "#shoptime",
                lang: "en",
                type: "time",
                trigger: "click",
                min: data_min,
                max: '20:00:00',
                value: new Date(),
                format: "HH:mm",
                ready: function(date) {
                    var layKey = layui.$(this.elem).attr("lay-key");
                    layui
                        .$("#layui-laydate" + layKey)
                        .find(".layui-laydate-list>li")
                        .width("50%")
                        .last("li")
                        .hide();
                },
                done: function(value, date) {
                    $('#shoptime').change();
                    dateTime = date
                }
            });
        });
        layui.use(["layer", "upload"], function() {
            layer = layui.layer;

            var $ = layui.jquery,
                upload = layui.upload;
            // 获取查定履歴详细信息
            $("#enquiryDetailModal").on("show.bs.modal", function(event) {
                var button = $(event.relatedTarget);
                var cartId = button.data("whatever");
                var modal = $(this);
                modal.find('.modal-body').find('.order_list').find('table').find('tbody').html('')
                $.ajax({
                    type: 'get',
                    url: baseUrl + "/api/shopping/info?id=" + cartId,
                    headers: {
                        'Authorization': sessionStorage.getItem('token'),
                        "Access-Control-Allow-Origin": "*"
                    },
                    success(data) {
                        if (data.code == 200) {
                            let size = JSON.parse(data.data.sku_stock.sp_data)[0].value
                            let time = data.data.createtime
                            time = timestampToTime(time)
                            str = `<div class="form-group"><label for="message-text" class="control-label">商品名:</label><strong>${data.data.product.name}</strong></div><div class="form-group"><label for="message-text" class="control-label">品番:</label><strong>${data.data.product.product_sn}</strong></div><div class="form-group"><label for="message-text" class="control-label">サイズ:</label><strong>${size}</strong></div><div class="form-group"><label for="message-text" class="control-label">価格:</label><strong>${'￥' +data.data.sku_stock._price}</strong></div><div class="form-group"><label for="message-text" class="control-label">数量:</label><strong>${data.data.num}</strong></div><div class="form-group"><label for="message-text" class="control-label">オーダー時間:</label><strong>${time}</strong></div>`
                            modal.find(".modal-body").html(str);
                        } else if (
                            data.code == 401
                        ) {
                            window.location.href = '/member_login'
                        } else {
                            layer.alert(data.msg, {
                                icon: 5,
                                title: "信息",
                            });
                        }
                    }
                })
            });
            // 添加订单
            $('#addOrderModal').on('show.bs.modal', function(event) {
                checked_orders = $('#checked_orders').val();
                let data = checked_orders
                let arr = data.split(',')
                if (arr.length > 7) {
                    layer.msg("予約は7件までとなっております");
                    $('#addOrderModal').modal('hidden')
                }
                var modal = $(this)
                modal.find('.modal-body').find('.order_list').find('table').find('tbody').html('')
                $.ajax({
                    type: 'get',
                    url: baseUrl + "/api/shopping?ids=" + checked_orders,
                    headers: {
                        'Authorization': sessionStorage.getItem('token'),
                    },
                    success(data) {
                        var shop = []
                        if (data.code == 200) {
                            let str = ''
                            let total = 0
                            $.each(data.data.data, function(index, item) {
                                let sku = JSON.parse(item.sku_stock.sp_data)[0].value
                                str += `<tr><td>${item.product.name}</td><td>${item.product.product_sn}</td><td>${sku}</td><td>${item.sku_stock._price}</td><td>${item.num}</td></tr>`
                                total += item.num
                            })
                            modal.find('.modal-body').find('.order_list').find('table').find('tbody').html(str)
                            $("#heji").html("合計商品点数（件）：" + total);
                            var dianpu = ""
                            $.ajax({
                                type: 'get',
                                url: baseUrl + '/api/shop',
                                success(data) {
                                    for (let i = 0; i < data.data.length; i++) {
                                        dianpu += "<label id='" + data.data[i].id + "' data-name=" + data.data[i].shop_name + " name='mdianpu" + data.data[i].mail_status + " cdianpu" + data.data[i].come_status + "' class='btn btn-default' for='" + data.data[i].id + "'>"
                                        dianpu += "<input type='radio' id='" + data.data[i].id + "' name='dianpu' value='" + data.data[i].id + "'/>";
                                        dianpu += "<p class='shop_p'>住所: " + data.data[i].shop_address + "</p>";
                                        dianpu += "<p class='shop_p'>電話: " + data.data[i].shop_phone + "</p>";
                                        dianpu += "<p class='shop_p'>Email: " + data.data[i].shop_email + "</p>";
                                        dianpu += "<p class='shop_p 12'>営業時間: " + data.data[i].shop_start + '-' + data.data[i].shop_end + "</p>";
                                        dianpu += "<p class='m" + data.data[i].mail_status + "' style ='display:none' > " + data.data[i].mail_status + "</p>"
                                        dianpu += "<p class='c" + data.data[i].come_status + " 'style ='display:none' > " + data.data[i].come_status + "</p>"
                                        dianpu += "</label >"
                                        shop.push(data.data[i])
                                    }
                                    $("#Select2").html(dianpu);
                                    var type = $("#Select label[class='btn btn-default active'] input").val();
                                    if (type == 2) {
                                        var shoparr = $("#Select2 label");
                                        for (let i = 0; i < shoparr.length; i++) {
                                            let element = shoparr[i];
                                            if (element.id == 2) {
                                                $(element).click()
                                            }
                                        }
                                        $("#group123").show();
                                        $(".c1").parent().hide()
                                        $(".c2").parent().show()
                                        let arr2 = []
                                        for (let i = 0; i < shop.length; i++) {
                                            if (arr2.indexOf(shop[i].come_status) == -1) {
                                                arr2.push(shop[i].come_status);
                                            }
                                        }
                                        if (arr2.length == 1 && arr2[0] == 1) {
                                            $('#confirmOrder').attr("disabled", true)
                                        } else {
                                            $('#confirmOrder').removeAttr("disabled")
                                        }
                                    }
                                },
                            })
                        }

                        $(document).on('click', '#Select', function() {
                            var rd = $("input[name='rd']:checked").val();
                            self.hw(rd);
                            if (rd == 1) {
                                var shoparr = $("#Select2 label");
                                for (let i = 0; i < shoparr.length; i++) {
                                    let element = shoparr[i];
                                    if (element.id == 1) {
                                        $(element).click()
                                    }
                                }
                            }
                        });
                        hw = function(sw) {
                            if (sw == 1) {
                                $("#group123").hide();
                                $(".m1").parent().hide()
                                $(".m2").parent().show()
                                let arr = []
                                for (let i = 0; i < shop.length; i++) {
                                    if (arr.indexOf(shop[i].mail_status) == -1) {
                                        arr.push(shop[i].mail_status);
                                    }
                                }
                                if (arr.length == 1 && arr[0] == 1) {
                                    $('#confirmOrder').attr("disabled", true)
                                } else {
                                    $('#confirmOrder').removeAttr("disabled")
                                }
                            }

                            if (sw == 2) {
                                $("#group123").show();
                                $(".c1").parent().hide()
                                $(".c2").parent().show()
                                let arr2 = []
                                for (let i = 0; i < shop.length; i++) {
                                    if (arr2.indexOf(shop[i].come_status) == -1) {
                                        arr2.push(shop[i].come_status);
                                    }
                                }
                                if (arr2.length == 1 && arr2[0] == 1) {
                                    $('#confirmOrder').attr("disabled", true)
                                } else {
                                    $('#confirmOrder').removeAttr("disabled")
                                }
                            }
                        }
                    }
                })
                $('#confirmOrder').click(function() {
                    var type = $("#Select label[class='btn btn-default active'] input").val();
                    var shop_id = $("#Select2 label[class='btn btn-default active'] input").val();
                    var shop_name = $("#Select2 label[class='btn btn-default active']").attr('data-name')
                    if (type == 2) {
                        //来店予約
                        var shoptime = $('#shoptime').val();
                        // if (JSON.stringify(dateTime) == '{}' || dateTime == null) {
                        //     layer.msg("ご来店時間選択してください.");
                        //     return false
                        // }
                        // timestr = dateTime.year + '-' + dateTime.month + '-' + dateTime.date + ' ' + dateTime.hours + ':' + dateTime.minutes + ':' + dateTime.seconds
                        // var time = new Date(timestr);
                        // time = time.getTime() / 1000;
                        if (shoptime == '') {
                            layer.msg("ご来店時間選択してください.");
                            return false;
                        }
                        $.ajax({
                            type: 'post',
                            url: baseUrl + "/api/addOrder",
                            headers: {
                                'Authorization': sessionStorage.getItem('token'),
                            },
                            data: {
                                shopping_cart_ids: checked_orders,
                                shop_id: shop_id,
                                shoptime: shoptime,
                                order_type: type
                            },
                            success(data) {
                                if (data.code == 200) {
                                    layer.confirm(data.msg, {
                                        icon: 3,
                                        title: 'メッセージ',
                                        btn: ['確定', '来店時間確認'],
                                        cancel: function() {
                                            window.location.reload()
                                        }
                                    }, function(index) {
                                        window.location.href = '/member_shopOrder';
                                        layer.close(index);
                                    }, function(index) {
                                        window.location.href = '/member_shopOrder';
                                        layer.close(index);
                                    });
                                    $('#addOrderModal').modal('hide');
                                } else if (data.code == 401) {
                                    window.location.href = '/member_login'
                                } else {
                                    layer.msg(data.msg);
                                    window.location.reload()
                                }
                            }
                        })
                    }
                    if (type == 1) {
                        $.ajax({
                            type: 'post',
                            url: baseUrl + "/api/addOrder",
                            headers: {
                                'Authorization': sessionStorage.getItem('token'),
                            },
                            data: {
                                shopping_cart_ids: checked_orders,
                                shop_id: shop_id,
                                order_type: type
                            },
                            success(data) {
                                if (data.code == 200) {
                                    layer.confirm(data.msg, {
                                        icon: 3,
                                        title: 'メッセージ',
                                        btn: ['確定', '追跡番号を記入'],
                                        cancel: function() {
                                            window.location.reload()
                                        }
                                    }, function(index) {
                                        window.location.href = '/member_order'
                                        layer.close(index);
                                    }, function(index) {
                                        window.location.href = '/member_order';
                                        layer.close(index);
                                    });
                                    $('#addOrderModal').modal('hide');
                                } else if (data.code == 401) {
                                    window.location.href = '/member_login'
                                } else {
                                    layer.msg(data.msg);
                                    window.location.reload()
                                }
                            }
                        })
                    }
                });
            });
            var getEleTimer = setInterval(function() {
                var eleNum = $(".timer").length;
                if (eleNum != 0) {
                    $(".timer").each(function(index, domEle) {
                        var createtime = $(this).attr('data');
                        createtime = timestampToTime(createtime)
                        createtime = createtime.replace(/-/g, '/')
                        var eleId = $(this).attr('id');
                        var endTime = new Date(createtime.replace(/-/g, '/')).getTime().valueOf() + 10 * 60 * 1000;
                        var cartId = $(this).attr('cartId');
                        var serverTime = new Date(); //假设为当前服务器时间，这里采用的是本地时间，实际使用一般是取服务端的
                        //结束时间
                        var endDate = new Date(endTime);
                        //当前时间
                        var nowDate = new Date();
                        //相差的总秒数
                        var totalSeconds = parseInt((endDate - nowDate) / 1000);
                        //天数
                        var days = Math.floor(totalSeconds / (60 * 60 * 24));
                        //取模（余数）
                        var modulo = totalSeconds % (60 * 60 * 24);
                        //小时数
                        var hours = Math.floor(modulo / (60 * 60));
                        modulo = modulo % (60 * 60);
                        //分钟
                        var minutes = Math.floor(modulo / 60);
                        var seconds = modulo % 60;
                        var str = minutes + '分' + seconds + '秒';
                        if (hours <= 0 && minutes <= 0 && seconds <= 0) {
                            lay('#' + eleId).html('時間切れ引き取り終了');
                            $.ajax({
                                type: 'get',
                                url: baseUrl + '/api/delShopping?ids=' + cartId,
                                headers: {
                                    'Authorization': sessionStorage.getItem('token'),
                                },
                                success(res) {
                                    if (res.code == 200) {
                                        $('#' + eleId).parent().parent().parent('tr').remove();
                                        window.location.reload()
                                    } else {
                                        layer.msg(res.msg);
                                        window.location.reload()
                                    }
                                }
                            })
                        } else {
                            lay('#' + eleId).html(str);
                        }
                    });
                } else {
                    clearInterval(getEleTimer)
                }
            }, 1000);
        })
        $(document).on('click', '.layui-tr-del2', function() {
            var that = $(this),
                href = !that.attr('data-href') ? that.attr('href') : that.attr('data-href');
            layer.confirm('カートから削除しますか？', {
                icon: 3,
                title: 'メッセージ',
                btn: ['削除', '戻る']
            }, function(index) {
                $.ajax({
                    type: 'get',
                    url: baseUrl + '/api/delShopping?ids=' + href,
                    headers: {
                        'Authorization': sessionStorage.getItem('token'),
                    },
                    success(res) {
                        if (res.code == 200) {
                            layer.msg(res.msg);
                            that.parents('tr').remove();
                        } else {
                            layer.msg(res.msg);
                            window.location.reload()
                        }
                    }
                })
                layer.close(index);
            });
            return false;
        });

        function formatminutes(date) {
            var aa = $(".laydate-time-list li ol")[1];
            var showtime = $($(".laydate-time-list li ol")[1]).find("li");
            for (var i = 0; i < showtime.length; i++) {
                var t00 = showtime[i].innerText;
                if (t00 != "00" && t00 != "20" && t00 != "30" && t00 != "40" && t00 != "50") {
                    showtime[i].remove()
                }
            }
            $($(".laydate-time-list li ol")[2]).find("li").remove(); //清空秒
        }
    })
</script>
<style>
    .shop_p {
        text-align: justify;
        text-justify: inter-ideograph;
    }
</style>
<!-- </div> -->
</div>
<div class="fly-footer">
    <p>古物営業許可第305502007435号 </p>
    <p><a href="http://www.beian.miit.gov.cn/" target="_blank">豫ICP备19030271号-1</a> <a href="/cms_index" target="_blank">the1sneaker</a></p>
</div>
</body>

</html>