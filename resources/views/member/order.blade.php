@include('member.header')
<div class="layui-tab layui-tab-brief" lay-filter="user">
    <ul class="layui-tab-title" id="LAY_mine">
        <li class="layui-this">発送待ち</li>
        <li>発送済み</li>
        <li>鑑定中</li>
        <li>送金待ち</li>
        <li>鑑定失敗</li>
        <li>買取成立</li>
        <li>返品済み</li>
        <li>引き取り終了</li>
        <li>全オーダー</li>
    </ul>
    <div class="layui-tab-content" style="padding: 20px 0;">
        <div class="layui-tab-item layui-show">

            <table class="layui-table" lay-filter="rules" id="rules">
            </table>

        </div>

        <div class="layui-tab-item">
            <table class="layui-table" lay-filter="rules2" id="rules2">
            </table>
        </div>

        <div class="layui-tab-item">
            <table class="layui-table" lay-filter="rules3" id="rules3">
            </table>
        </div>

        <div class="layui-tab-item">
            <table class="layui-table" lay-filter="rules4" id="rules4">
            </table>
        </div>

        <div class="layui-tab-item">
            <table class="layui-table" lay-filter="rules5" id="rules5">
            </table>
        </div>

        <div class="layui-tab-item">
            <table class="layui-table" lay-filter="rules6" id="rules6">
            </table>
        </div>

        <div class="layui-tab-item">
            <table class="layui-table" lay-filter="rules7" id="rules7">
            </table>
        </div>

        <div class="layui-tab-item">
            <table class="layui-table" lay-filter="rules8" id="rules8">
            </table>
        </div>

        <div class="layui-tab-item">
            <table class="layui-table" lay-filter="rules9" id="rules9">
            </table>
        </div>

    </div>
</div>

<!-- 订单明细详情modal -->
<div class="modal fade" id="orderDetailModal" tabindex="-1" role="dialog" aria-labelledby="orderDetailModalLabel" style="z-index: 10000;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="orderDetailModalLabel">オーダー詳細</h4>
            </div>
            <div class="modal-body">
                <!-- 订单明细数据 -->

                <div class="order_detail">

                </div>

                <div class="order_list">

                    <table class="table table-condensed">
                        <caption style="font-weight: bold;font-size: 16px;">商品明細</caption>
                        <thead>
                            <tr>
                                <th style="width: 40%;">商品名</th>
                                <th>品番</th>
                                <th>サイズ</th>
                                <th>価格</th>
                                <th>数量</th>
                            </tr>
                        </thead>
                        <tbody>

                        </tbody>
                    </table>

                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">閉じる</button>
                <!-- <button type="button" class="btn btn-primary">确定</button> -->
            </div>
        </div>
    </div>
</div>

<!-- 物流明细信息 modal -->
<div class="modal fade" id="serialDetailModal" tabindex="-1" role="dialog" aria-labelledby="serialDetailModalLabel" style="z-index: 10000;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="serialDetailModalLabel">引取状況</h4>
            </div>
            <div class="modal-body">
                <!-- 物流明细数据 -->
                <div class="row" style="font-size:12px; text-align:center">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">閉じる</button>
            </div>
        </div>
    </div>
</div>

<!-- 物流单号录入 modal -->
<div class="modal fade" id="serialnumDetailModal" tabindex="-1" role="dialog" aria-labelledby="serialnumDetailModalLabel" style="z-index: 10000;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="serialnumDetailModalLabel">追跡番号入力</h4>
            </div>
            <div class="modal-body">

                <form>
                    <div class="form-group">
                        <label for="recipient-name" class="control-label">追跡番号:</label>
                        <input type="text" class="form-control" id="recipient-name">
                        <input type="hidden" class="form-control" id="recipient-orderid">
                    </div>
                </form>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">閉じ</button>
                <button type="button" class="btn btn-primary determine">登録する</button>
            </div>
        </div>
    </div>
</div>
<style type="text/css">
    .layui-table-cell {
        height: auto !important;
        white-space: normal;
        text-align: center;
    }

    .layui-btn+.layui-btn {
        margin-left: 0px;
    }

    .control-label {
        width: 100px;
        /*text-align: */
    }

    .layui-none {
        width: 100% !important;
    }
</style>

<script type="text/javascript">
    function letstatus(value) {
        // 1’ => ‘待发货’,’2’ => ‘已发货’,’3’ => ‘已到货’, ‘4’ => ‘待转账’,’5’ => ‘鉴定失败’, ‘6’ => ‘交易成功’, ‘7’ => ‘已退货’,’8’ => ‘交易关闭’
        if (value == 1) {
            return '発送待ち'
        } else if (value == 2) {
            return '発送済み'
        } else if (value == 3) {
            return '鑑定中'
        } else if (value == 4) {
            return '送金待ち'
        } else if (value == 5) {
            return '鑑定失敗'
        } else if (value == 6) {
            return '引取完了'
        } else if (value == 7) {
            return '返品済み'
        } else if (value == 8) {
            return '引き取り終了'
        }
    }

    function timestampToTime(timestamp) {
        var date = new Date(timestamp * 1000); //时间戳为10位需*1000，时间戳为13位的话不需乘1000
        var Y = date.getFullYear() + '-';
        var M = (date.getMonth() + 1 < 10 ? '0' + (date.getMonth() + 1) : date.getMonth() + 1) + '-';
        var D = (date.getDate() < 10 ? '0' + date.getDate() : date.getDate()) + ' ';
        var h = (date.getHours() < 10 ? '0' + date.getHours() : date.getHours()) + ':';
        var m = (date.getMinutes() < 10 ? '0' + date.getMinutes() : date.getMinutes()) + ':';
        var s = date.getSeconds() < 10 ? '0' + date.getSeconds() : date.getSeconds();
        return Y + M + D + h + m + s;
    }

    function buyBook(obj) {
        var orderid = $(obj).attr('data-whatever');
        var cartids = $(obj).attr('data-cartids');
        var buybooks = $(obj).attr('data-url');
        console.log(cartids);
        if (cartids != 'null') {
            window.open(buybooks)
        } else {
            let token = sessionStorage.getItem('token')
            token = token.split(" ")
            window.open(baseUrl + "/api/pdf?order_id=" + orderid + '&token=' + token[1])
        }
    }
    $(document).on('click', '.layui-tr-del2', function() {
        var that = $(this),
            href = !that.attr('data-href') ? that.attr('href') : that.attr('data-href');
        layer.confirm('買取依頼キャンセルします？', {
            icon: 3,
            title: 'メッセージ',
            btn: ['確定', 'マイページ']
        }, function(index) {
            if (!href) {
                layer.msg('请设置data-href参数');
                return false;
            }
            $.ajax({
                type: 'get',
                url: baseUrl + "/api/delOrder?ids=" + href,
                headers: {
                    'Authorization': sessionStorage.getItem('token'),
                },
                success(data) {
                    if (data.code == 200) {
                        layer.msg(res.msg);
                        that.parents('tr').remove();
                    } else {
                        layer.msg(res.msg);
                    }
                }
            });
            layer.close(index);
        });
        return false;
    });

    layui.use(['util', 'laydate', 'layer', 'table'], function() {
        var table = layui.table;

        var util = layui.util,
            laydate = layui.laydate,
            layer = layui.layer
        // 待发货
        table.render({
            elem: '#rules',
            url: baseUrl + '/api/orderList' //数据接口
                ,
            where: {
                order_type: 1,
                status: 1
            } //如果无需传递额外参数，可不加该参数
            ,
            method: 'get',
            headers: {
                'Authorization': sessionStorage.getItem('token')
            },
            text: {
                none: 'オーダー0' //默认：无数据。注：该属性为 layui 2.2.5 开始新增
            },
            cols: [
                [ //表头
                    {
                        field: 'order_num',
                        title: 'オーダー番号',
                        width: 180
                    },
                    // {field: 'goodsname', title: '商品名称', width:80} ,
                    // {field: 'itemno', title: '货号', width:80} ,
                    // {field: 'size', title: 'サイズ', width:70, sort: true} ,
                    // {field: 'price', title: '価格', width:70, sort: true} ,
                    {
                        field: 'status',
                        title: '状態',
                        width: 80,
                        templet: function(d) {
                            if (d.status == 1) {
                                return '発送待ち'
                            } else if (d.status == 2) {
                                return '発送済み'
                            } else if (d.status == 3) {
                                return '鑑定中'
                            } else if (d.status == 4) {
                                return '送金待ち'
                            } else if (d.status == 5) {
                                return '鑑定失敗'
                            } else if (d.status == 6) {
                                return '引取完了'
                            } else if (d.status == 7) {
                                return '返品済み'
                            } else if (d.status == 8 && d.overdue == 1) {
                                return '来店予約時間切れ'
                            } else {
                                return '引き取り終了'
                            }
                        }
                    },
                    // {field: 'createtime', title: 'オーダー時間', width:120, sort: true} ,

                    {
                        field: 'createtime',
                        title: '残り時間',
                        width: 120,
                        templet: function(d) {
                            return `<div><dd title="请在有效时长内填写物流编号" style="cursor:pointer" id="timer${ d.id }" class="timer" data="${ d.createtime }" orderId = "${ d.id }"></dd></div>`
                        }
                    },
                    {
                        field: 'serialnum',
                        title: '追跡番号',
                        width: 160,
                        templet: function(d) {
                            let str = ''
                            str += `<div><dd title="点击修改物流编号" style="cursor:pointer">`
                            if (d.serialnum == "" || d.serialnum == null) {} else {
                                str += d.serialnum
                            }
                            str += `</dd></div>`
                            return str
                        }
                    },
                    {
                        field: 'number',
                        title: '詳細',
                        width: 120,
                        templet: function(d) {
                            let str = ''
                            str += `<a href="#" onclick="javascript:return false;" class="layui-btn layui-btn-xs layui-btn-normal" data-toggle="modal" data-target="#orderDetailModal" data-whatever="${ d.id }">詳細</a>`
                            if (d.status != 8) {
                                str += `<a href="#" onclick="javascript:return false;" class="layui-btn layui-btn-now1 layui-btn-xs"  data-toggle="modal" data-target="`
                                if (d.serialnum) {
                                    str += '#serialDetailModal'
                                } else {
                                    str += '#serialnumDetailModal'
                                }
                                str += `" data-whatever="${d.id}">追跡番号</a>`
                                str += `<a href="#" onclick="buyBook(this);" class="layui-btn layui-btn-xs layui-btn-warm" data-whatever="${ d.id }" data-cartids="${d.cartids}" data-url="${d.buy_books}">申込書</a>`
                            }
                            return str
                        }
                    },
                ]
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

        // 已发货
        table.render({
            elem: '#rules2',
            url: baseUrl + '/api/orderList' //数据接口
                ,
            where: {
                order_type: 1,
                status: 2
            } //如果无需传递额外参数，可不加该参数
            ,
            method: 'get',
            headers: {
                'Authorization': sessionStorage.getItem('token')
            },
            text: {
                none: 'オーダー0' //默认：无数据。注：该属性为 layui 2.2.5 开始新增
            },
            cols: [
                [ //表头
                    {
                        field: 'order_num',
                        title: 'オーダー番号',
                        width: 180
                    },
                    // {field: 'goodsname', title: '商品名称', width:80} ,
                    // {field: 'itemno', title: '货号', width:80} ,
                    // {field: 'size', title: 'サイズ', width:70, sort: true} ,
                    // {field: 'price', title: '価格', width:70, sort: true} ,
                    {
                        field: 'status',
                        title: '状態',
                        width: 80,
                        templet: function(d) {
                            if (d.status == 1) {
                                return '発送待ち'
                            } else if (d.status == 2) {
                                return '発送済み'
                            } else if (d.status == 3) {
                                return '鑑定中'
                            } else if (d.status == 4) {
                                return '送金待ち'
                            } else if (d.status == 5) {
                                return '鑑定失敗'
                            } else if (d.status == 6) {
                                return '引取完了'
                            } else if (d.status == 7) {
                                return '返品済み'
                            } else if (d.status == 8 && d.overdue == 1) {
                                return '来店予約時間切れ'
                            } else {
                                return '引き取り終了'
                            }
                        }
                    },
                    {
                        field: 'createtime',
                        title: 'オーダー時間',
                        width: 150,
                        sort: true,
                        templet: function(d) {
                            let date = timestampToTime(d.createtime)
                            return date
                        }
                    },
                    {
                        field: 'serialnum',
                        title: '追跡番号',
                        width: 160,
                        templet: function(d) {
                            let str = ''
                            str += `<div><dd title="点击修改物流编号" style="cursor:pointer">`
                            if (d.serialnum == "" || d.serialnum == null) {} else {
                                str += d.serialnum
                            }
                            str += `</dd></div>`
                            return str
                        }
                    },
                    {
                        field: 'number',
                        title: '詳細',
                        width: 120,
                        templet: function(d) {
                            let str = ''
                            str += `<a href="#" onclick="javascript:return false;" class="layui-btn layui-btn-xs layui-btn-normal" data-toggle="modal" data-target="#orderDetailModal" data-whatever="${ d.id }">詳細</a>`
                            if (d.status != 8) {
                                str += `<a href="#" onclick="javascript:return false;" class="layui-btn layui-btn-now1 layui-btn-xs"  data-toggle="modal" data-target="`
                                if (d.serialnum) {
                                    str += '#serialDetailModal'
                                } else {
                                    str += '#serialnumDetailModal'
                                }
                                str += `" data-whatever="${d.id}">引取状況</a>`
                                str += `<a href="#" onclick="buyBook(this);" class="layui-btn layui-btn-xs layui-btn-warm" data-whatever="${ d.id }" data-cartids="${d.cartids}" data-url="${d.buy_books}">申込書</a>`
                            }
                            return str
                        }
                    },
                ]
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
        // 已到货
        table.render({
            elem: '#rules3',
            url: baseUrl + '/api/orderList' //数据接口
                ,
            where: {
                order_type: 1,
                status: 3
            } //如果无需传递额外参数，可不加该参数
            ,
            method: 'get',
            headers: {
                'Authorization': sessionStorage.getItem('token')
            },
            text: {
                none: 'オーダー0' //默认：无数据。注：该属性为 layui 2.2.5 开始新增
            },
            cols: [
                [ //表头
                    {
                        field: 'order_num',
                        title: 'オーダー番号',
                        width: 180
                    },
                    // {field: 'goodsname', title: '商品名称', width:80} ,
                    // {field: 'itemno', title: '货号', width:80} ,
                    // {field: 'size', title: 'サイズ', width:70, sort: true} ,
                    // {field: 'price', title: '価格', width:70, sort: true} ,
                    {
                        field: 'status',
                        title: '状態',
                        width: 80,
                        templet: function(d) {
                            if (d.status == 1) {
                                return '発送待ち'
                            } else if (d.status == 2) {
                                return '発送済み'
                            } else if (d.status == 3) {
                                return '鑑定中'
                            } else if (d.status == 4) {
                                return '送金待ち'
                            } else if (d.status == 5) {
                                return '鑑定失敗'
                            } else if (d.status == 6) {
                                return '引取完了'
                            } else if (d.status == 7) {
                                return '返品済み'
                            } else if (d.status == 8 && d.overdue == 1) {
                                return '来店予約時間切れ'
                            } else {
                                return '引き取り終了'
                            }
                        }
                    },
                    {
                        field: 'createtime',
                        title: 'オーダー時間',
                        width: 150,
                        sort: true,
                        templet: function(d) {
                            let date = timestampToTime(d.createtime)
                            return date
                        }
                    },
                    {
                        field: 'serialnum',
                        title: '追跡番号',
                        width: 160,
                        templet: function(d) {
                            let str = ''
                            str += `<div><dd title="点击修改物流编号" style="cursor:pointer">`
                            if (d.serialnum == "" || d.serialnum == null) {} else {
                                str += d.serialnum
                            }
                            str += `</dd></div>`
                            return str
                        }
                    },
                    {
                        field: 'number',
                        title: '詳細',
                        width: 120,
                        templet: function(d) {
                            let str = ''
                            str += `<a href="#" onclick="javascript:return false;" class="layui-btn layui-btn-xs layui-btn-normal" data-toggle="modal" data-target="#orderDetailModal" data-whatever="${ d.id }">詳細</a>`
                            if (d.status != 8) {
                                str += `<a href="#" onclick="javascript:return false;" class="layui-btn layui-btn-now1 layui-btn-xs"  data-toggle="modal" data-target="`
                                if (d.serialnum) {
                                    str += '#serialDetailModal'
                                } else {
                                    str += '#serialnumDetailModal'
                                }
                                str += `" data-whatever="${d.id}">引取状況</a>`
                                str += `<a href="#" onclick="buyBook(this);" class="layui-btn layui-btn-xs layui-btn-warm" data-whatever="${ d.id }" data-cartids="${d.cartids}" data-url="${d.buy_books}">申込書</a>`
                            }
                            return str
                        }
                    },
                ]
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
        table.render({
            elem: '#rules4',
            url: baseUrl + '/api/orderList' //数据接口
                ,
            where: {
                order_type: 1,
                status: 4
            } //如果无需传递额外参数，可不加该参数
            ,
            method: 'get',
            headers: {
                'Authorization': sessionStorage.getItem('token')
            },
            text: {
                none: 'オーダー0' //默认：无数据。注：该属性为 layui 2.2.5 开始新增
            },
            cols: [
                [ //表头
                    {
                        field: 'order_num',
                        title: 'オーダー番号',
                        width: 180
                    },
                    // {field: 'goodsname', title: '商品名称', width:80} ,
                    // {field: 'itemno', title: '货号', width:80} ,
                    // {field: 'size', title: 'サイズ', width:70, sort: true} ,
                    // {field: 'price', title: '価格', width:70, sort: true} ,
                    {
                        field: 'status',
                        title: '状態',
                        width: 80,
                        templet: function(d) {
                            if (d.status == 1) {
                                return '発送待ち'
                            } else if (d.status == 2) {
                                return '発送済み'
                            } else if (d.status == 3) {
                                return '鑑定中'
                            } else if (d.status == 4) {
                                return '送金待ち'
                            } else if (d.status == 5) {
                                return '鑑定失敗'
                            } else if (d.status == 6) {
                                return '引取完了'
                            } else if (d.status == 7) {
                                return '返品済み'
                            } else if (d.status == 8 && d.overdue == 1) {
                                return '来店予約時間切れ'
                            } else {
                                return '引き取り終了'
                            }
                        }
                    },
                    {
                        field: 'createtime',
                        title: 'オーダー時間',
                        width: 150,
                        sort: true,
                        templet: function(d) {
                            let date = timestampToTime(d.createtime)
                            return date
                        }
                    },
                    {
                        field: 'serialnum',
                        title: '追跡番号',
                        width: 160,
                        templet: function(d) {
                            let str = ''
                            str += `<div><dd title="点击修改物流编号" style="cursor:pointer">`
                            if (d.serialnum == "" || d.serialnum == null) {} else {
                                str += d.serialnum
                            }
                            str += `</dd></div>`
                            return str
                        }
                    },
                    {
                        field: 'number',
                        title: '詳細',
                        width: 120,
                        templet: function(d) {
                            let str = ''
                            str += `<a href="#" onclick="javascript:return false;" class="layui-btn layui-btn-xs layui-btn-normal" data-toggle="modal" data-target="#orderDetailModal" data-whatever="${ d.id }">詳細</a>`
                            if (d.status != 8) {
                                str += `<a href="#" onclick="javascript:return false;" class="layui-btn layui-btn-now1 layui-btn-xs"  data-toggle="modal" data-target="`
                                if (d.serialnum) {
                                    str += '#serialDetailModal'
                                } else {
                                    str += '#serialnumDetailModal'
                                }
                                str += `" data-whatever="${d.id}">引取状況</a>`
                                str += `<a href="#" onclick="buyBook(this);" class="layui-btn layui-btn-xs layui-btn-warm" data-whatever="${ d.id }" data-cartids="${d.cartids}" data-url="${d.buy_books}">申込書</a>`
                            }
                            return str
                        }
                    },
                ]
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
        // 鉴定失败
        table.render({
            elem: '#rules5',
            url: baseUrl + '/api/orderList' //数据接口
                ,
            where: {
                order_type: 1,
                status: 5
            } //如果无需传递额外参数，可不加该参数
            ,
            method: 'get',
            headers: {
                'Authorization': sessionStorage.getItem('token')
            },
            text: {
                none: 'オーダー0' //默认：无数据。注：该属性为 layui 2.2.5 开始新增
            },
            cols: [
                [ //表头
                    {
                        field: 'order_num',
                        title: 'オーダー番号',
                        width: 180
                    },
                    // {field: 'goodsname', title: '商品名称', width:80} ,
                    // {field: 'itemno', title: '货号', width:80} ,
                    // {field: 'size', title: 'サイズ', width:70, sort: true} ,
                    // {field: 'price', title: '価格', width:70, sort: true} ,
                    {
                        field: 'status',
                        title: '状態',
                        width: 80,
                        templet: function(d) {
                            if (d.status == 1) {
                                return '発送待ち'
                            } else if (d.status == 2) {
                                return '発送済み'
                            } else if (d.status == 3) {
                                return '鑑定中'
                            } else if (d.status == 4) {
                                return '送金待ち'
                            } else if (d.status == 5) {
                                return '鑑定失敗'
                            } else if (d.status == 6) {
                                return '引取完了'
                            } else if (d.status == 7) {
                                return '返品済み'
                            } else if (d.status == 8 && d.overdue == 1) {
                                return '来店予約時間切れ'
                            } else {
                                return '引き取り終了'
                            }
                        }
                    },
                    {
                        field: 'createtime',
                        title: 'オーダー時間',
                        width: 150,
                        sort: true,
                        templet: function(d) {
                            let date = timestampToTime(d.createtime)
                            return date
                        }
                    },
                    {
                        field: 'serialnum',
                        title: '追跡番号',
                        width: 160,
                        templet: function(d) {
                            let str = ''
                            str += `<div><dd title="点击修改物流编号" style="cursor:pointer">`
                            if (d.serialnum == "" || d.serialnum == null) {} else {
                                str += d.serialnum
                            }
                            str += `</dd></div>`
                            return str
                        }
                    },
                    {
                        field: 'number',
                        title: '詳細',
                        width: 120,
                        templet: function(d) {
                            let str = ''
                            str += `<a href="#" onclick="javascript:return false;" class="layui-btn layui-btn-xs layui-btn-normal" data-toggle="modal" data-target="#orderDetailModal" data-whatever="${ d.id }">詳細</a>`
                            if (d.status != 8) {
                                str += `<a href="#" onclick="javascript:return false;" class="layui-btn layui-btn-now1 layui-btn-xs"  data-toggle="modal" data-target="`
                                if (d.serialnum) {
                                    str += '#serialDetailModal'
                                } else {
                                    str += '#serialnumDetailModal'
                                }
                                str += `" data-whatever="${d.id}">引取状況</a>`
                                str += `<a href="#" onclick="buyBook(this);" class="layui-btn layui-btn-xs layui-btn-warm" data-whatever="${ d.id }" data-cartids="${d.cartids}" data-url="${d.buy_books}">申込書</a>`
                            }
                            return str
                        }
                    },
                ]
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
        // 交易成功
        table.render({
            elem: '#rules6',
            url: baseUrl + '/api/orderList' //数据接口
                ,
            where: {
                order_type: 1,
                status: 6
            } //如果无需传递额外参数，可不加该参数
            ,
            method: 'get',
            headers: {
                'Authorization': sessionStorage.getItem('token')
            },
            text: {
                none: 'オーダー0' //默认：无数据。注：该属性为 layui 2.2.5 开始新增
            },
            cols: [
                [ //表头
                    {
                        field: 'order_num',
                        title: 'オーダー番号',
                        width: 180
                    },
                    // {field: 'goodsname', title: '商品名称', width:80} ,
                    // {field: 'itemno', title: '货号', width:80} ,
                    // {field: 'size', title: 'サイズ', width:70, sort: true} ,
                    // {field: 'price', title: '価格', width:70, sort: true} ,
                    {
                        field: 'status',
                        title: '状態',
                        width: 80,
                        templet: function(d) {
                            if (d.status == 1) {
                                return '発送待ち'
                            } else if (d.status == 2) {
                                return '発送済み'
                            } else if (d.status == 3) {
                                return '鑑定中'
                            } else if (d.status == 4) {
                                return '送金待ち'
                            } else if (d.status == 5) {
                                return '鑑定失敗'
                            } else if (d.status == 6) {
                                return '引取完了'
                            } else if (d.status == 7) {
                                return '返品済み'
                            } else if (d.status == 8 && d.overdue == 1) {
                                return '来店予約時間切れ'
                            } else {
                                return '引き取り終了'
                            }
                        }
                    },
                    {
                        field: 'createtime',
                        title: 'オーダー時間',
                        width: 150,
                        sort: true,
                        templet: function(d) {
                            let date = timestampToTime(d.createtime)
                            return date
                        }
                    },
                    {
                        field: 'serialnum',
                        title: '追跡番号',
                        width: 160,
                        templet: function(d) {
                            let str = ''
                            str += `<div><dd title="点击修改物流编号" style="cursor:pointer">`
                            if (d.serialnum == "" || d.serialnum == null) {} else {
                                str += d.serialnum
                            }
                            str += `</dd></div>`
                            return str
                        }
                    },
                    {
                        field: 'number',
                        title: '詳細',
                        width: 120,
                        templet: function(d) {
                            let str = ''
                            str += `<a href="#" onclick="javascript:return false;" class="layui-btn layui-btn-xs layui-btn-normal" data-toggle="modal" data-target="#orderDetailModal" data-whatever="${ d.id }">詳細</a>`
                            if (d.status != 8) {
                                str += `<a href="#" onclick="javascript:return false;" class="layui-btn layui-btn-now1 layui-btn-xs"  data-toggle="modal" data-target="`
                                if (d.serialnum) {
                                    str += '#serialDetailModal'
                                } else {
                                    str += '#serialnumDetailModal'
                                }
                                str += `" data-whatever="${d.id}">引取状況</a>`
                                str += `<a href="#" onclick="buyBook(this);" class="layui-btn layui-btn-xs layui-btn-warm" data-whatever="${ d.id }" data-cartids="${d.cartids}" data-url="${d.buy_books}">申込書</a>`
                            }
                            return str
                        }
                    },
                ]
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
        table.render({
            elem: '#rules7',
            url: baseUrl + '/api/orderList' //数据接口
                ,
            where: {
                order_type: 1,
                status: 7
            } //如果无需传递额外参数，可不加该参数
            ,
            method: 'get',
            headers: {
                'Authorization': sessionStorage.getItem('token')
            },
            text: {
                none: 'オーダー0' //默认：无数据。注：该属性为 layui 2.2.5 开始新增
            },
            cols: [
                [ //表头
                    {
                        field: 'order_num',
                        title: 'オーダー番号',
                        width: 180
                    },
                    // {field: 'goodsname', title: '商品名称', width:80} ,
                    // {field: 'itemno', title: '货号', width:80} ,
                    // {field: 'size', title: 'サイズ', width:70, sort: true} ,
                    // {field: 'price', title: '価格', width:70, sort: true} ,
                    {
                        field: 'status',
                        title: '状態',
                        width: 80,
                        templet: function(d) {
                            if (d.status == 1) {
                                return '発送待ち'
                            } else if (d.status == 2) {
                                return '発送済み'
                            } else if (d.status == 3) {
                                return '鑑定中'
                            } else if (d.status == 4) {
                                return '送金待ち'
                            } else if (d.status == 5) {
                                return '鑑定失敗'
                            } else if (d.status == 6) {
                                return '引取完了'
                            } else if (d.status == 7) {
                                return '返品済み'
                            } else if (d.status == 8 && d.overdue == 1) {
                                return '来店予約時間切れ'
                            } else {
                                return '引き取り終了'
                            }
                        }
                    },
                    {
                        field: 'createtime',
                        title: 'オーダー時間',
                        width: 150,
                        sort: true,
                        templet: function(d) {
                            let date = timestampToTime(d.createtime)
                            return date
                        }
                    },
                    {
                        field: 'serialnum2',
                        title: '退货单号',
                        width: 160,
                        templet: function(d) {
                            return `<div><dd style="cursor:pointer">${ d.serialnum2 }</dd></div>`
                        }
                    },
                    {
                        field: 'number',
                        title: '詳細',
                        width: 120,
                        templet: function(d) {
                            let str = ''
                            str += `<a href="#" onclick="javascript:return false;" class="layui-btn layui-btn-xs layui-btn-normal" data-toggle="modal" data-target="#orderDetailModal" data-whatever="${ d.id }">詳細</a>`
                            if (d.status != 8) {
                                str += `<a href="#" onclick="javascript:return false;" class="layui-btn layui-btn-now1 layui-btn-xs"  data-toggle="modal" data-target="`
                                if (d.serialnum) {
                                    str += '#serialDetailModal'
                                } else {
                                    str += '#serialnumDetailModal'
                                }
                                str += `" data-whatever="${d.id}">引取状況</a>`
                                str += `<a href="#" onclick="buyBook(this);" class="layui-btn layui-btn-xs layui-btn-warm" data-whatever="${ d.id }" data-cartids="${d.cartids}" data-url="${d.buy_books}">申込書</a>`
                            }
                            return str
                        }
                    },
                ]
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
        // 交易关闭
        table.render({
            elem: '#rules8',
            url: baseUrl + '/api/orderList' //数据接口
                ,
            where: {
                order_type: 1,
                status: 8
            } //如果无需传递额外参数，可不加该参数
            ,
            method: 'get',
            headers: {
                'Authorization': sessionStorage.getItem('token')
            },
            text: {
                none: 'オーダー0' //默认：无数据。注：该属性为 layui 2.2.5 开始新增
            },
            cols: [
                [ //表头
                    {
                        field: 'order_num',
                        title: 'オーダー番号',
                        width: 180
                    },
                    // {field: 'goodsname', title: '商品名称', width:80} ,
                    // {field: 'itemno', title: '货号', width:80} ,
                    // {field: 'size', title: 'サイズ', width:70, sort: true} ,
                    // {field: 'price', title: '価格', width:70, sort: true} ,
                    {
                        field: 'status',
                        title: '状態',
                        width: 80,
                        templet: function(d) {
                            if (d.status == 1) {
                                return '発送待ち'
                            } else if (d.status == 2) {
                                return '発送済み'
                            } else if (d.status == 3) {
                                return '鑑定中'
                            } else if (d.status == 4) {
                                return '送金待ち'
                            } else if (d.status == 5) {
                                return '鑑定失敗'
                            } else if (d.status == 6) {
                                return '引取完了'
                            } else if (d.status == 7) {
                                return '返品済み'
                            } else if (d.status == 8 && d.overdue == 1) {
                                return '来店予約時間切れ'
                            } else {
                                return '引き取り終了'
                            }
                        }
                    },
                    {
                        field: 'createtime',
                        title: 'オーダー時間',
                        width: 150,
                        sort: true,
                        templet: function(d) {
                            let date = timestampToTime(d.createtime)
                            return date
                        }
                    },
                    {
                        field: 'number',
                        title: '詳細',
                        width: 120,
                        templet: function(d) {
                            let str = ''
                            str += `<a href="#" onclick="javascript:return false;" class="layui-btn layui-btn-xs layui-btn-normal" data-toggle="modal" data-target="#orderDetailModal" data-whatever="${ d.id }">詳細</a>`
                            if (d.status != 8) {
                                str += `<a href="#" onclick="javascript:return false;" class="layui-btn layui-btn-now1 layui-btn-xs"  data-toggle="modal" data-target="`
                                if (d.serialnum) {
                                    str += '#serialDetailModal'
                                } else {
                                    str += '#serialnumDetailModal'
                                }
                                str += `" data-whatever="${d.id}">引取状況</a>`
                                str += `<a href="#" onclick="buyBook(this);" class="layui-btn layui-btn-xs layui-btn-warm" data-whatever="${ d.id }" data-cartids="${d.cartids}" data-url="${d.buy_books}">申込書</a>`
                            }
                            return str
                        }
                    },
                ]
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
        // 全部
        table.render({
            elem: '#rules9',
            url: baseUrl + '/api/orderList' //数据接口
                ,
            where: {
                order_type: 1
            } //如果无需传递额外参数，可不加该参数
            ,
            method: 'get',
            headers: {
                'Authorization': sessionStorage.getItem('token')
            },
            text: {
                none: 'オーダー0' //默认：无数据。注：该属性为 layui 2.2.5 开始新增
            },
            cols: [
                [ //表头
                    {
                        field: 'order_num',
                        title: 'オーダー番号',
                        width: 180
                    },
                    // {field: 'goodsname', title: '商品名称', width:80} ,
                    // {field: 'itemno', title: '货号', width:80} ,
                    // {field: 'size', title: 'サイズ', width:70, sort: true} ,
                    // {field: 'price', title: '価格', width:70, sort: true} ,
                    {
                        field: 'status',
                        title: '状態',
                        width: 80,
                        templet: function(d) {
                            if (d.status == 1) {
                                return '発送待ち'
                            } else if (d.status == 2) {
                                return '発送済み'
                            } else if (d.status == 3) {
                                return '鑑定中'
                            } else if (d.status == 4) {
                                return '送金待ち'
                            } else if (d.status == 5) {
                                return '鑑定失敗'
                            } else if (d.status == 6) {
                                return '引取完了'
                            } else if (d.status == 7) {
                                return '返品済み'
                            } else if (d.status == 8 && d.overdue == 1) {
                                return '来店予約時間切れ'
                            } else {
                                return '引き取り終了'
                            }
                        }
                    },
                    {
                        field: 'createtime',
                        title: 'オーダー時間',
                        width: 150,
                        sort: true,
                        templet: function(d) {
                            let date = timestampToTime(d.createtime)
                            return date
                        }
                    },
                    {
                        field: 'serialnum',
                        title: '追跡番号',
                        width: 160,
                        templet: function(d) {
                            let str = ''
                            str += `<div><dd title="点击修改物流编号" style="cursor:pointer">`
                            if (d.serialnum == "" || d.serialnum == null) {} else {
                                str += d.serialnum
                            }
                            str += `</dd></div>`
                            return str
                        }
                    },
                    {
                        field: 'number',
                        title: '詳細',
                        width: 120,
                        templet: function(d) {
                            let str = ''
                            str += `<a href="#" onclick="javascript:return false;" class="layui-btn layui-btn-xs layui-btn-normal" data-toggle="modal" data-target="#orderDetailModal" data-whatever="${ d.id }">詳細</a>`
                            if (d.status != 8) {
                                str += `<a href="#" onclick="javascript:return false;" class="layui-btn layui-btn-now1 layui-btn-xs"  data-toggle="modal" data-target="`
                                if (d.serialnum) {
                                    str += '#serialDetailModal'
                                    str += `" data-whatever="${d.id}">引取状況</a>`
                                } else {
                                    str += '#serialnumDetailModal'
                                    str += `" data-whatever="${d.id}">追踪番号</a>`
                                }
                                str += `<a href="#" onclick="buyBook(this);" class="layui-btn layui-btn-xs layui-btn-warm" data-whatever="${ d.id }" data-cartids="${d.cartids}" data-url="${d.buy_books}">申込書</a>`
                            }
                            return str
                        }
                    },
                ]
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
    $(function() {
        layui.use(['util', 'laydate', 'layer', 'table'], function() {
            layer = layui.layer
            var util = layui.util,
                laydate = layui.laydate,
                layer = layui.layer;
            // 获取订单详细信息
            // modal
            $('#orderDetailModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget)
                var orderId = button.data('whatever')
                var modal = $(this)
                modal.find('.modal-body').find('.order_detail').html('')
                modal.find('.modal-body').find('.order_list').find('table').find('tbody').html('')
                $.ajax({
                    type: 'get',
                    url: baseUrl + "/api/orderInfo?id=" + orderId,
                    headers: {
                        'Authorization': sessionStorage.getItem('token'),
                    },
                    success(data) {
                        console.log(data)
                        if (data.code == 200) {
                            let str = ''
                            let str2 = ''
                            let time = timestampToTime(data.data.createtime)
                            str += `<div class="form-group"><label for="message-text" class="control-label">オーダー番号:</label><strong>${data.data.order_num}</strong></div><div class="form-group"><label for="message-text" class="control-label">オーダー状態:</label><strong>${letstatus(data.data.status)}</strong></div><div class="form-group"><label for="message-text" class="control-label">オーダー時間:</label><strong>${time}</strong></div><div class="form-group"><label for="message-text" class="control-label">追跡番号:</label><strong>${data.data.serialnum?data.data.serialnum:''}</strong></div>`
                            $.each(data.data.list, function(index, item) {
                                let size = JSON.parse(item.size)[0].value
                                str2 += `<tr><td>${item.goodsname}</td><td>${item.itemno}</td><td>${size}</td><td>${'￥'+ item._price}</td><td>${item.num}</td></tr>`
                            })
                            modal.find('.modal-body').find('.order_detail').html(str);
                            modal.find('.modal-body').find('.order_list').find('table').find('tbody').html(str2);
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
            })
            // 提交运单编号
            $('#serialnumDetailModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget)
                var orderId = button.data('whatever')
                var modal = $(this)
                modal.find('.modal-body').find('#recipient-orderid').val(orderId)
            })
            $('.determine').click(function() {
                var serialnum = $('#recipient-name').val();
                var orderid = $('#recipient-orderid').val();
                $.ajax({
                    type: 'get',
                    url: baseUrl + "/api/editOrder?id=" + orderid + '&status=2&logistics_status=15&serialnum=' + serialnum,
                    headers: {
                        'Authorization': sessionStorage.getItem('token'),
                    },
                    success(data) {
                        if (data.code == 200) {
                            layer.alert(data.msg, {
                                icon: 6,
                                title: "メッセージ",
                                btn: "確定"
                            });
                            $('#serialnumDetailModal').modal('hide')
                            window.location = '/member_order';
                        } else {
                            layer.alert(data.msg, {
                                icon: 5,
                                title: "メッセージ",
                                btn: "確定"
                            });
                        }
                    }
                });
            });

            // 获取物流信息
            $('#serialDetailModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget)
                var orderId = button.data('whatever')
                var modal = $(this)
                $.ajax({
                    type: 'get',
                    url: baseUrl + '/api/orderInfo?id=' + orderId,
                    headers: {
                        'Authorization': sessionStorage.getItem('token'),
                    },
                    success(data) {
                        if (data.code == 200) {
                            console.log(data);
                            let str = ''
                            switch (data.data.logistics_status) {
                                case 15:
                                    str += '<div class="row" style="font-size:12px; text-align:center;font-weight:bold;"><div class="col-xs-12">●買取依頼</div><span class="col-xs-12">↓</span><div class="col-xs-12">●発送済み</div><span class="col-xs-12">↓</span><div class="col-xs-12" style="color:red">●運輸中</div>          <span class="col-xs-12">↓</span></div>'
                                    break;
                                case 25:
                                    str += `<div class="row" style="font-size:12px; text-align:center;font-weight:bold;"><div class="col-xs-12">●買取依頼</div>
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12">●発送済み</div>
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12" >●運輸中</div>
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12">●the1snakerに到着</div>
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12" style="color:red">●鑑定中</div>
                                            <span class="col-xs-12">↓</span></div>`
                                    break;
                                case 45:
                                    str += `<div class="row" style="font-size:12px; text-align:center;font-weight:bold;"><div class="col-xs-12">●買取依頼</div>
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12">●発送済み</div>
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12">●運輸中</div>
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12">●the1snakerに到着</div>
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12">●鑑定中</div>
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12">●鑑定完了</div>          
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12" style="color:red">●送金待ち</div>          
                                            <span class="col-xs-12">↓</span></div>`
                                    break;
                                case 40:
                                    str += `<div class="row" style="font-size:12px; text-align:center;font-weight:bold;"><div class="col-xs-12">●買取依頼</div>
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12">●発送済み</div>
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12">●運輸中</div>
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12">●the1snakerに到着</div>
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12">●鑑定中</div>
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12">●鉴定失败</div>          
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12" style="color:red">●返品中</div>          
                                            <span class="col-xs-12">↓</span></div>`
                                    break;
                                case 100:
                                    str += `<div class="row" style="font-size:12px; text-align:center;font-weight:bold;"><div class="col-xs-12">●買取依頼</div>
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12">●発送済み</div>
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12">●運輸中</div>
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12">●the1snakerに到着</div>
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12">●鑑定中</div>
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12">●鑑定完了</div>          
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12">●送金待ち</div>          
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12">●送金手続き完了</div>
                                            <span class="col-xs-12">↓</span>
                                            <div class="col-xs-12" style="color:red">●引き取り完了</div></div>`
                                    break;
                                case 55:
                                    str += `<div class="row" style="font-size:12px; text-align:center;font-weight:bold;"><div class="col-xs-12">●買取依頼</div>
                                        <span class="col-xs-12">↓</span>
                                        <div class="col-xs-12">●発送済み</div>
                                        <span class="col-xs-12">↓</span>
                                        <div class="col-xs-12">●運輸中</div>
                                        <span class="col-xs-12">↓</span>
                                        <div class="col-xs-12">●the1snakerに到着</div>
                                        <span class="col-xs-12">↓</span>
                                        <div class="col-xs-12">●鑑定中</div>
                                        <span class="col-xs-12">↓</span>
                                        <div class="col-xs-12">●鉴定失败</div>          
                                        <span class="col-xs-12">↓</span>
                                        <div class="col-xs-12" style="color:red">●返品中</div>          
                                        <span class="col-xs-12">↓</span></div>`
                                    break;
                            }
                            modal.find('.modal-body').html(str)
                        } else {
                            layer.alert(data.msg, {
                                icon: 5,
                                title: "メッセージ",
                                btn: "確定"
                            });
                        }
                    }
                })
            });
            // 倒计时
            // window.onload=function(){
            var getEleTimer = setInterval(function() {
                var eleNum = $(".timer").length;
                if (eleNum != 0) {
                    // clearTimeout(getEleTimer);
                    $(".timer").each(function(index, domEle) {
                        var createtime = $(this).attr('data');
                        createtime = timestampToTime(createtime)
                        createtime = createtime.replace(/-/g, '/')
                        var eleId = $(this).attr('id');
                        var endTime = new Date(createtime).getTime().valueOf() + 3 * 60 * 60 * 1000;
                        // var endTime = new Date(endTime);
                        var orderId = $(this).attr('orderid');
                        //结束时间
                        var endDate = new Date(endTime);
                        //当前时间
                        var nowDate = new Date();
                        //五分钟后取消预约按钮
                        var endTime_cancel = new Date(createtime).getTime().valueOf() + 5 * 60 * 1000;
                        //结束时间
                        var endDate_cancel = new Date(endTime_cancel);
                        //当前时间
                        var nowDate_cancel = new Date();
                        //相差的总秒数
                        var totalSeconds_cancel = parseInt((endDate_cancel - nowDate_cancel) / 1000);
                        //天数
                        var days_cancel = Math.floor(totalSeconds_cancel / (60 * 60 * 24));
                        //取模（余数）
                        var modulo_cancel = totalSeconds_cancel % (60 * 60 * 24);
                        //小时数
                        var hours_cancel = Math.floor(modulo_cancel / (60 * 60));
                        modulo_cancel = modulo_cancel % (60 * 60);
                        //分钟
                        var minutes_cancel = Math.floor(modulo_cancel / 60);
                        //秒
                        var seconds_cancel = modulo_cancel % 60;
                        if (hours_cancel <= 0 && minutes_cancel <= 0 && seconds_cancel <= 0) {
                            $('#' + eleId).parent().parent().parent('tr').find('td:eq(4)').find('div').find('.layui-tr-del2').remove();
                        }
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
                        //秒
                        var seconds = modulo % 60;
                        var str = hours + '時間' + minutes + '分' + seconds + '秒';
                        if (hours <= 0 && minutes <= 0 && seconds <= 0) {
                            lay('#' + eleId).html('時間切れ引き取り終了');
                            $.ajax({
                                type: 'get',
                                url: baseUrl + "/api/editOrder?id=" + orderId + '&status=8&overdue=2',
                                headers: {
                                    'Authorization': sessionStorage.getItem('token'),
                                },
                                success(res) {
                                    if (res.code == 200) {
                                        layer.msg(res.msg);
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
                        var serverTime = new Date(); //假设为当前服务器时间，这里采用的是本地时间，实际使用一般是取服务端的
                    });
                } else {
                    clearInterval(getEleTimer)
                }
            }, 1000);
        })
        $('.layui-input').attr("disabled", true);
        $('input[type="file"]').attr("disabled", "disabled");
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