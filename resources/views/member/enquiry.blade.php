@include('member.header')
<div class="layui-tab layui-tab-brief">
    <ul class="layui-tab-title">
        <li class="layui-this">查定履歴</li>
    </ul>
    <div class="layui-tab-content" style="padding: 20px 0;">
        <div class="layui-tab-item layui-show">
            <table class="layui-hide" id="dataTable" lay-filter="dataTable"></table>
        </div>
    </div>
</div>
<!-- 查定明细详情modal -->
<div class="modal fade" id="enquiryDetailModal" tabindex="-1" role="dialog" aria-labelledby="enquiryDetailModalLabel" style="z-index: 10000;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="enquiryDetailModalLabel">查定詳細</h4>
            </div>
            <div class="modal-body">
                <!-- 鉴定明细数据 -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">閉じ</button>
                <!-- <button type="button" class="btn btn-primary">确定</button> -->
            </div>
        </div>
    </div>
</div>

<!-- 鉴定补图modal -->
<div class="modal fade" id="appraisalgraphModal" tabindex="-1" role="dialog" aria-labelledby="appraisalgraphModalLabel" style="z-index: 10000;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="appraisalgraphModalLabel">鉴定补图</h4>
            </div>
            <div class="modal-body">
                <!-- 补图 -->

                <div class="layui-upload">
                    <button type="button" class="layui-btn" id="more_graph">补图上传</button>
                    <input type="hidden" name="more_graph" required="" placeholder="鉴定图片详情">
                    <blockquote class="layui-elem-quote layui-quote-nm" style="margin-top: 10px;">
                        补图预览：
                        <div class="layui-upload-list" id="more_graph_img"></div>
                    </blockquote>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">閉じ</button>
                <button type="button" class="btn btn-primary" id="save_appraisalgraph">登録する</button>
            </div>
        </div>
    </div>
</div>
<script>
    layui.use(['table'], function() {
        var table = layui.table,
            $ = layui.$,
            form = layui.form;
        table.render({
            elem: '#dataTable',
            url: baseUrl + '/api/materiel/list' //数据接口
                ,
            method: 'get',
            headers: {
                'Authorization': sessionStorage.getItem('token')
            },
            text: {
                none: 'オーダー0' //默认：无数据。注：该属性为 layui 2.2.5 开始新增
            },
            cols: [
                [{
                        field: 'name',
                        width: 80,
                        title: 'お名前',
                        align: "center"
                    },
                    {
                        field: 'commodity_name',
                        width: 80,
                        title: '商品名',
                        align: "center"
                    },
                    {
                        field: 'createtime',
                        width: 120,
                        title: '依頼時間',
                        align: "center",
                        sort: true
                    },
                    // { field: 'examinetime', width: 180, title: '审核时间' },
                    {
                        field: 'mater_price',
                        width: 120,
                        title: '査定参考価格',
                        align: "center"
                    },
                    {
                        field: 'iscart',
                        title: 'カートに追加',
                        width: 130,
                        align: "center",
                        templet: function(d) {
                            let str = `<div id="time${d.id}" class="time" data-time="${ d.examinetime }" data-cart="${d.iscart}"></div>`
                            return str
                        }
                    },
                    {
                        field: 'number',
                        width: 220,
                        title: '詳細',
                        align: "center",
                        templet: function(d) {
                            let str = ''
                            let now = Date.parse(new Date()) / 1000;
                            let endTime = d.examinetime + 60 * 24 * 60
                            if (d.mater_price > 0 && d.iscart === 0 && d.examinetime && d.policy == 1 && endTime > now) {
                                str += `<a href="#" onclick="addCarts(this)" class="layui-btn layui-btn-sm layui-btn-normal" data-whatever="${d.id}">カードに追加</a>`
                            }
                            str += `<a href="#" onclick="javascript:return false;" class="layui-btn layui-btn-now1 layui-btn-xs" data-toggle="modal" data-target="#enquiryDetailModal" data-whatever="${ d.id }">詳細</a>`
                            str += `<a href='${ d.id }' class="layui-btn layui-btn-danger layui-btn-xs layui-tr-del2">削除</a>`
                            return str
                        }
                    }
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

    function addCarts(obj) {
        var enquiryid = $(obj).attr('data-whatever');
        layui.use('layer', function() {
            var layer = layui.layer;
            layer.confirm('カートに追加しますか?', {
                icon: 3,
                title: 'メッセージ',
                btn: ['確定', 'マイベージ']
            }, function(index) {
                var serialnum = $('#recipient-name2').val();
                var orderid = $('#recipient-orderid2').val();
                $.ajax({
                    type: 'post',
                    url: baseUrl + '/api/materiel_addShopping?id=' + enquiryid,
                    headers: {
                        'Authorization': sessionStorage.getItem('token'),
                    },
                    success(data) {
                        if (data.code == 200) {
                            layer.confirm(data.msg, {
                                icon: 3,
                                title: 'メッセージ',
                                btn: ['確定', 'カート']
                            }, function(index) {
                                location.reload();
                                layer.close(index);
                            }, function(index) {
                                window.location.href = '/member_car'
                                layer.close(index);
                            });
                        } else {
                            layer.alert(data.msg, {
                                icon: 5,
                                title: "メッセージ",
                                btn: "確定"
                            });
                        }
                    }
                })
                layer.close(index);
            }, function(index) {
                layer.close(index);
            });
        });
    }
    $(function() {
        var getEleTimer = setInterval(function() {
            let str = ''
            var eleNum = $(".time").length;
            if (eleNum != 0) {
                $(".time").each(function(index, domEle) {
                    var examinetime = $(this).attr('data-time');
                    var cart = $(this).attr('data-cart');
                    var eleId = $(this).attr('id');
                    let now = Date.parse(new Date());
                    let endTime = examinetime + 24 * 60 * 60 * 1000
                    if (examinetime != 'null'&& examinetime) {
                        if (cart == 1) {
                            str = '追加済'
                        } else if (cart == 0 && now < endTime) {
                            var usedTime = endTime - now;
                            var days = Math.floor(usedTime / (24 * 3600 * 1000));
                            var leave1 = usedTime % (24 * 3600 * 1000);
                            var hours = Math.floor(leave1 / (3600 * 1000));
                            var leave2 = leave1 % (3600 * 1000);
                            var minutes = Math.floor(leave2 / (60 * 1000));
                            var leave3 = leave2 % (60 * 1000)
                            var seconds = Math.round(leave3 / 1000)
                            var time = hours + ":" + minutes + ":" + seconds;
                            str = time
                        } else {
                            str = '時間オーバー'
                        }
                    } else {
                        str = '查定中'
                    }
                    lay('#' + eleId).html(str);
                });
            } else {
                clearInterval(getEleTimer)
            }
        }, 1000);
        layui.use(['layer', 'upload'], function() {
            layer = layui.layer
            var $ = layui.jquery,
                upload = layui.upload;
            $('#enquiryDetailModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget)
                var enquiryId = button.data('whatever')
                var modal = $(this)
                $.ajax({
                    type: 'get',
                    url: baseUrl + "/api/materiel/info?id=" + enquiryId,
                    headers: {
                        'Authorization': sessionStorage.getItem('token'),
                    },
                    success(data) {
                        if (data.code == 200) {
                            let str = ''
                            str += `<div class="form-group"><label for="message-text" class="control-label">お名前:</label><strong>${data.data.name}</strong></div><div class="form-group"><label for="message-text" class="control-label">商品名:</label><strong>${data.data.commodity_name}</strong></div><div class="form-group"><label for="message-text" class="control-label">品番:</label><strong>${data.data.pinfan}</strong></div><div class="form-group"><label for="message-text" class="control-label">サイズ:</label><strong>${data.data.rules}</strong></div><div class="form-group"><label for="message-text" class="control-label">個数:</label><strong>${data.data.num}</strong></div><div class="form-group"><label for="message-text" class="control-label">依頼時間:</label><strong>${data.data.createtime}</strong></div><div class="form-group"><label for="message-text" class="control-label">現時点査定価格:</label><strong>${ data.data.mater_price!=null?data.data.mater_price:'查定中'}</strong></div>`
                            modal.find('.modal-body').html(str)
                        } else if (data.code == 401) {
                            window.location.href = '/member_login'
                        } else {
                            layer.alert(data.msg, {
                                icon: 5,
                                title: "信息"
                            });
                        }
                    }
                }, "json");
            })
            upload.render({
                elem: '#more_graph',
                url: baseUrl + '/api/attachment/add',
                multiple: true,
                before: function(obj) {
                    //预读本地文件示例，不支持ie8
                    obj.preview(function(index, file, result) {
                        $('#more_graph_img').append('<img src="' + result + '" alt="' + file.name + '" class="layui-upload-img" style="margin-right: 10px;">')
                    });
                },
                done: function(res) {
                    var original_val = $("input[name='more_graph']").val();
                    $("input[name='more_graph']").val([original_val, res.id]);
                }
            });
            $('#appraisalgraphModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget)
                var appraisalId = button.data('whatever')
                $('#save_appraisalgraph').click(function() {
                    var original_val = $("input[name='more_graph']").val();
                    if (original_val == '') {
                        layer.msg('请上传补图');
                        return false;
                    }
                    $.post(baseUrl + "/api/materiel/add", {
                        "appraisalgraph": original_val,
                        "appraisalid": appraisalId
                    }, function(data) {
                        if (data.code == 0) {
                            $('#appraisalgraphModal').modal('hide');
                        }
                        layer.msg(data.msg);
                    }, "json");
                });
            });
        })
    })
    $(document).on('click', '.layui-tr-del2', function() {
        var that = $(this),
            href = !that.attr('data-href') ? that.attr('href') : that.attr('data-href');
        layer.confirm('商品がカードから削除します？', {
            icon: 3,
            title: 'メッセージ',
            btn: ['確定', 'マイページ']
        }, function(index) {
            $.ajax({
                type: 'get',
                url: baseUrl + '/api/materiel/del?ids=' + href,
                headers: {
                    'Authorization': sessionStorage.getItem('token'),
                },
                success(res) {
                    if (res.code == 200) {
                        layer.msg(res.msg);
                        that.parents('tr').remove();
                    } else {
                        layer.msg(res.msg);
                        //window.location.reload()
                    }
                }
            })
            layer.close(index);
        });
        return false;
    });
</script>
</div>
</div>
<div class="fly-footer">
    <p>古物営業許可第305502007435号 </p>
    <p><a href="http://www.beian.miit.gov.cn/" target="_blank">豫ICP备19030271号-1</a> <a href="/cms_index" target="_blank">the1sneaker</a></p>
</div>
</body>