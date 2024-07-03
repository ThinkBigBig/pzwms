
    <div class="contact w3l-3">
        <div class="container">
            <h2>無料鑑定</h2>

            <div class="notice">
                <div style="float: left;width: auto;line-height: 35px;"><i class="glyphicon glyphicon-glass"
                        style="color: #C5D0E1;font-size: 10px;"></i>&nbsp;最新鑑定:</div>
                <ul style="margin-left:2%;float: left;cursor:pointer">
                    <li>会員<b style="color: #C5D0E1">{$vo.submitter|func_substr_replace}</b>さんの{$vo.category}が鑑定出来ました.
                        【{$vo.createtime|date='Y-m-d H:i'}】</li>
                </ul>
            </div>
            <hr />
            <form class="layui-form" action="" enctype="multipart/form-data" method="post">
                <div class="layui-form-item">
                    <label class="layui-form-label">品名</label>
                    <div class="layui-input-block">
                        <input type="text" name="category" required lay-verify="required" placeholder="jordan1" autocomplete="off" class="layui-input">
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">品番</label>
                    <div class="layui-input-block">
                        <input type="text" name="brand" required lay-verify="required" placeholder="スタイルコード"
                            autocomplete="off" class="layui-input">
                    </div>
                </div>

                <div class="layui-form-item">
                    <label class="layui-form-label">商品状態</label>
                    <div class="layui-input-block">
                        <!-- <input type="text" name="shoe_style" required  lay-verify="required" placeholder="カテゴリー" autocomplete="off" class="layui-input"> -->

                        <select name="shoe_style" lay-filter="aihao">
                            <option value="新品未使用" selected="">新品未使用</option>
                            <option value="新古品">新古品</option>
                            <option value="中古品">中古品</option>
                        </select>
                    </div>
                </div>

                <div class="layui-form-item layui-form-text">
                    <label class="layui-form-label" style="padding-left: 0px;padding-right: 0px;">商品画像 <br />

                        <!-- <i href="/beginnersNote" data-target="#beginnersModal" data-toggle="modal">(新手必看)</i> -->
                        <a href="/beginnersNote" target="_blank" class="beginners"><em>(撮影マニュアル)</em></a>
                    </label>
                    <div class="layui-input-block">
                        <!-- <input type="hidden" name="image_ids" required="" placeholder="鉴定图片"> -->
                        <div class="layui-upload">
                            <!-- <div class="remark" id="appearance">外观</div> -->
                            <div class="layui-upload-list" style="z-index: 2" id="appearance">
                                <img class="layui-upload-img" id="appearance_img" src="static/img/appraisal/01.jpeg">
                                <p id="appearanceText"></p>
                                <input type="hidden" name="images[appearance]" required="" placeholder="鉴定图片详情">
                            </div>
                        </div>

                        <div class="layui-upload">
                            <!-- <div class="remark" id="shoeLabel">鞋标</div> -->
                            <div class="layui-upload-list" style="z-index: 2" id="shoeLabel">
                                <img class="layui-upload-img" id="shoeLabel_img" src="static/img/appraisal/02.jpeg">
                                <p id="shoeLabelText"></p>
                                <input type="hidden" name="images[shoeLabel]" required="" placeholder="鉴定图片详情">
                            </div>
                        </div>

                        <div class="layui-upload">
                            <!-- <div class="remark" id="routing">走线</div> -->
                            <div class="layui-upload-list" style="z-index: 2" id="routing">
                                <img class="layui-upload-img" id="routing_img" src="static/img/appraisal/03.jpeg">
                                <p id="routingText"></p>
                                <input type="hidden" name="images[routing]" required="" placeholder="鉴定图片详情">
                            </div>
                        </div>

                        <div class="layui-upload">
                            <!-- <div class="remark" id="insole">鞋垫</div> -->
                            <div class="layui-upload-list" style="z-index: 2" id="insole">
                                <img class="layui-upload-img" id="insole_img" src="static/img/appraisal/04.jpeg">
                                <p id="insoleText"></p>
                                <input type="hidden" name="images[insole]" required="" placeholder="鉴定图片详情">
                            </div>
                        </div>

                        <div class="layui-upload">
                            <!-- <div class="remark" id="sideMarker">侧标</div> -->
                            <div class="layui-upload-list" style="z-index: 2" id="sideMarker">
                                <img class="layui-upload-img" id="sideMarker_img" src="static/img/appraisal/05.jpeg">
                                <p id="sideMarkerText"></p>
                                <input type="hidden" name="images[sideMarker]" required="" placeholder="鉴定图片详情">
                            </div>
                        </div>

                        <div class="layui-upload">
                            <!-- <div class="remark" id="embossed">钢印</div> -->
                            <div class="layui-upload-list" style="z-index: 2" id="embossed">
                                <img class="layui-upload-img" id="embossed_img" src="static/img/appraisal/06.jpeg">
                                <p id="embossedText"></p>
                                <input type="hidden" name="images[embossed]" required="" placeholder="鉴定图片详情">
                            </div>
                        </div>

                        <div class="layui-upload">
                            <!-- <div class="remark" id="inscribed">内刻印</div> -->
                            <div class="layui-upload-list" style="z-index: 2" id="inscribed">
                                <img class="layui-upload-img" id="inscribed_img" src="static/img/appraisal/07.jpeg">
                                <p id="inscribedText"></p>
                                <input type="hidden" name="images[inscribed]" required="" placeholder="鉴定图片详情">
                            </div>
                        </div>

                        <div class="layui-upload">
                            <!-- <div class="remark" id="shoecase">箱</div> -->
                            <div class="layui-upload-list" style="z-index: 2" id="shoecase">
                                <img class="layui-upload-img" id="shoecase_img" src="static/img/appraisal/08.jpeg">
                                <p id="shoecaseText"></p>
                                <input type="hidden" name="images[shoecase]" required="" placeholder="鉴定图片详情">
                            </div>
                        </div>
                        <!-- <div class="layui-upload">
          <div class="remark" id="more_graph">更多</div>
          <div class="layui-upload-list" style="z-index: 2">
            <img class="layui-upload-img">
            <input type="hidden" name="more_graph" required="" placeholder="鉴定图片详情">
          </div>
        </div> -->

                    </div>


                </div>

                <!--  <div class="layui-form-item layui-form-text">
      <label class="layui-form-label">补图预览</label>
      <div class="layui-input-block">
        
        <blockquote class="layui-elem-quote layui-quote-nm" style="margin-top: 10px;">
          预览图：
          <div class="layui-upload-list" id="more_graph_img"></div>
        </blockquote>
        
      </div>

    </div> -->

                <div class="layui-form-item layui-form-text">
                    <label class="layui-form-label">備考</label>
                    <div class="layui-input-block">
                        <textarea name="remark" placeholder="備考..." class="layui-textarea"></textarea>
                    </div>
                </div>

                <div class="layui-form-item" pane="">
                    <!-- <label class="layui-form-label">提交即阅读并同意</label> -->
                    <div class="layui-input-block">
                        <p style="color: #333;font-size: 12px;">確認事項<br />
                            ・初めてご利用のお客様は撮影マニュアルをご覧になってから撮影をお願い致します。<br />
                            ・一部フラッシュ撮影が必要な箇所がありますので必ずフラッシュ撮影をお願い致します。<br />
                            ・鑑定精度や鑑定速度を上げるために画質が良い写真を選択お願い致します。<br />
                            ・被写体に必ずピントが合っているようにお願い致します。<br />
                            ・撮影した写真の加工、修正は行わないようにお願い致します。<br />
                            ・画像をアップする段階で異なった被写体を載せないようにお願い致します。<br />
                            ※当社が意図的または悪意のある行為とみなした場合、厳重な対処をさせて 頂きます。</p>
                        <input type="checkbox" name="is_agree" lay-skin="primary" title="確認事項同意" checked="">
                        <!-- <a>《鉴别须知》</a> -->
                    </div>
                </div>

                <div class="layui-form-item">
                    <div class="layui-input-block">
                        <button class="layui-btn" lay-submit lay-filter="formDemo">鑑定依頼</button>
                    </div>
                </div>
            </form>

        </div>
    </div>

    <!--弹出子页面 开始 -->
    <div class="modal fade" id="beginnersModal" tabindex="-1" role="dialog" aria-labelledby="modal">
        <div class="modal-dialog" style="width:90%;height:90%" role="document">
            <div class="modal-content">
                <!--//text.html内容会被加载到这里-->
            </div>
        </div>
    </div>
    <!--弹出子页面 结束 -->

    <!-- 上传图片进度条 -->
    <div id="win" style="display:none" class="layui-progress layui-progress-big" lay-showPercent="yes"
        lay-filter="progressBar">
        <div class="layui-progress-bar layui-bg-red" lay-percent="0%"></div>
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

        .layui-upload {
            position: relative;
            width: 200px;
            height: 100%;
            float: left;
            margin: 0 5px;
        }

        .remark {
            position: absolute;
            top: 50px;
            left: 36px;
            font-weight: 500;
            cursor: pointer;
        }

        .layui-upload-img {
            width: 200px;
            height: 200px;
            margin: 0;
        }

        .content p,
        .content ul,
        .content ol,
        .content blockquote {
            margin-top: 0px;
            margin-bottom: 0px;
        }

        .content img {
            margin-top: 0px;
        }

        .layui-form-label {
            width: auto;
        }

        .beginners {
            font-size: 12px;
            color: #C5D0E1;

        }

        .layui-upload-list {
            cursor: pointer;
        }

        a:hover {
            text-decoration: none;
        }

        div,
        ul,
        li {
            margin: 0;
            padding: 0
        }

        /*先初始化一下默认样式*/
        .notice {
            /*width: 300px;单行显示，超出隐藏*/
            height: 35px;
            /*固定公告栏显示区域的高度*/
            padding: 0 20px;
            background-color: WhiteSmoke;
            overflow: hidden;
            font-size: 10px;
        }

        .notice ul li {
            list-style: none;
            line-height: 35px;
            /*以下为了单行显示，超出隐藏*/
            display: block;
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
        }

        .layui-form-checked[lay-skin=primary] i {
            border-color: #C5D0E1;
            background-color: #C5D0E1;
            color: #000;
        }
    </style>
    <script  language="javascript" type="text/javascript">
        $('#searchhead').keydown(function (e) {
            var good_name = $("#searchhead").val()
            if (good_name == null || good_name == '') {
                return
            }
            if (e.keyCode == 13) {
                let parse = {
                    good_name: null,
                    productsn: null,
                    page: null,
                    limit: null
                }
                parse.good_name = good_name
                window.location.href = '/cms_market?good_name=' + parse.good_name + '&productsn=' + parse.good_name
            }
        })

        function noticeUp(obj, top, time) {
            $(obj).animate({
                marginTop: top
            }, time, function () {
                $(this).css({ marginTop: "0" }).find(":first").appendTo(this);
            })
        }

        $(function () {
            setInterval("noticeUp('.notice ul','-35px',500)", 2000);
        });

        layui.use('form', function () {
            var form = layui.form;

            //监听提交
            form.on('submit(formDemo)', function (data) {
                $.post("/appraisal", $(data.form).serialize(), function (data) {

                    if (data.code == 0) {
                        layer.msg(data.msg);
                        location.reload()
                    } else {

                        layer.msg(data.msg);
                        return false;
                    }
                }, "json");

                return false;
            });
        });


        // 上传图片压缩
        function canvasDataURL(file, callback) { //压缩转化为base64
            var reader = new FileReader()
            reader.readAsDataURL(file)
            reader.onload = function (e) {
                const img = new Image()
                const quality = 0.8 // 图像质量
                const canvas = document.createElement('canvas')
                const drawer = canvas.getContext('2d')
                img.src = this.result
                img.onload = function () {
                    canvas.width = img.width
                    canvas.height = img.height
                    drawer.drawImage(img, 0, 0, canvas.width, canvas.height)
                    convertBase64UrlToBlob(canvas.toDataURL(file.type, quality), callback);
                }
            }
        }

        function convertBase64UrlToBlob(urlData, callback) { //将base64转化为文件格式
            const arr = urlData.split(',')
            const mime = arr[0].match(/:(.*?);/)[1]
            const bstr = atob(arr[1])
            let n = bstr.length
            const u8arr = new Uint8Array(n)
            while (n--) {
                u8arr[n] = bstr.charCodeAt(n)
            }
            callback(new Blob([u8arr], {
                type: mime
            }));
        }

        var xhrOnProgress = function (fun) {
            xhrOnProgress.onprogress = fun; //绑定监听  
            //使用闭包实现监听绑  
            return function () {
                //通过$.ajaxSettings.xhr();获得XMLHttpRequest对象  
                var xhr = $.ajaxSettings.xhr();
                //判断监听函数是否为函数  
                if (typeof xhrOnProgress.onprogress !== 'function')
                    return xhr;
                //如果有监听函数并且xhr对象支持绑定时就把监听函数绑定上去  
                if (xhrOnProgress.onprogress && xhr.upload) {
                    xhr.upload.onprogress = xhrOnProgress.onprogress;
                }
                return xhr;
            }
        };

        var width = document.body.clientWidth * 0.8;

        layui.use(['upload', 'element'], function () {
            var $ = layui.jquery
                , upload = layui.upload;

            var element = layui.element;

            //外观图片上传
            var uploadInst1 = upload.render({
                elem: '#appearance'
                , url: '/attachment/upload/upload/dir/images/module/cms.html'
                , size: 0
                , accept: 'file'
                , acceptMime: 'image/*'
                , auto: false
                , xhr: xhrOnProgress
                , progress: function (value) {
                    element.progress('progressBar', value + '%');
                }
                , before: function (obj) {

                    layer.open({
                        type: 1,
                        title: 'アップロード中',
                        area: [width + 'px'],
                        skin: 'layui-layer-molv',
                        closeBtn: 1,
                        shadeClose: true,
                        content: $('#win') //这里content是一个普通的String
                    });
                    //预读本地文件示例，不支持ie8
                    obj.preview(function (index, file, result) {
                        $('#appearance_img').attr('src', result); //图片链接（base64）
                    });
                }
                , choose: function (obj) { //选择文件后的回调
                    var files = obj.pushFile();
                    var filesArry = [];
                    for (var key in files) { //将上传的文件转为数组形式
                        filesArry.push(files[key])
                    }
                    var index = filesArry.length - 1;
                    var file = filesArry[index]; //获取最后选择的图片,即处理多选情况

                    if (navigator.appName == "Microsoft Internet Explorer" && parseInt(navigator.appVersion.split(";")[1]
                        .replace(/[ ]/g, "").replace("MSIE", "")) < 9) {
                        return obj.upload(index, file)
                    }
                    canvasDataURL(file, function (blob) {
                        var aafile = new File([blob], file.name, {
                            type: file.type
                        })
                        var isLt1M;
                        if (file.size < aafile.size) {
                            isLt1M = file.size
                        } else {
                            isLt1M = aafile.size
                        }

                        if (isLt1M / 1024 / 1024 > 10) {
                            return layer.alert('上传图片过大！')
                        } else {
                            if (file.size < aafile.size) {
                                return obj.upload(index, file)
                            }
                            obj.upload(index, aafile)
                        }
                    })
                }
                , done: function (res) {
                    //如果アップロードエラー
                    if (res.code > 0) {
                        return layer.msg('アップロードエラー');
                    }
                    //上传成功
                    $("input[name='images[appearance]']").val(res.id);
                }
                , error: function () {
                    //演示失败状态，并实现重传
                    var appearanceText = $('#appearanceText');
                    appearanceText.html('<span style="color: #FF5722;">アップロードエラー</span> <a class="layui-btn layui-btn-xs appearance-reload">再度アップロードする</a>');
                    appearanceText.find('.appearance-reload').on('click', function () {
                        uploadInst1.upload();
                    });
                }
            });

            //鞋标图片上传
            var uploadInst2 = upload.render({
                elem: '#shoeLabel'
                , url: '/attachment/upload/upload/dir/images/module/cms.html'
                , size: 0
                , accept: 'file'
                , acceptMime: 'image/*'
                , auto: false
                , xhr: xhrOnProgress
                , progress: function (value) {
                    element.progress('progressBar', value + '%');
                }
                , before: function (obj) {

                    layer.open({
                        type: 1,
                        title: 'アップロード中',
                        area: [width + 'px'],
                        skin: 'layui-layer-molv',
                        closeBtn: 1,
                        shadeClose: true,
                        content: $('#win') //这里content是一个普通的String
                    });
                    //预读本地文件示例，不支持ie8
                    obj.preview(function (index, file, result) {
                        $('#shoeLabel_img').attr('src', result); //图片链接（base64）
                    });
                }
                , choose: function (obj) { //选择文件后的回调
                    var files = obj.pushFile();
                    var filesArry = [];
                    for (var key in files) { //将上传的文件转为数组形式
                        filesArry.push(files[key])
                    }
                    var index = filesArry.length - 1;
                    var file = filesArry[index]; //获取最后选择的图片,即处理多选情况

                    if (navigator.appName == "Microsoft Internet Explorer" && parseInt(navigator.appVersion.split(";")[1]
                        .replace(/[ ]/g, "").replace("MSIE", "")) < 9) {
                        return obj.upload(index, file)
                    }
                    canvasDataURL(file, function (blob) {
                        var aafile = new File([blob], file.name, {
                            type: file.type
                        })
                        var isLt1M;
                        if (file.size < aafile.size) {
                            isLt1M = file.size
                        } else {
                            isLt1M = aafile.size
                        }

                        if (isLt1M / 1024 / 1024 > 10) {
                            return layer.alert('上传图片过大！')
                        } else {
                            if (file.size < aafile.size) {
                                return obj.upload(index, file)
                            }
                            obj.upload(index, aafile)
                        }
                    })
                }
                , done: function (res) {
                    //如果アップロードエラー
                    if (res.code > 0) {
                        return layer.msg('アップロードエラー');
                    }
                    //上传成功
                    $("input[name='images[shoeLabel]']").val(res.id);
                }
                , error: function () {
                    //演示失败状态，并实现重传
                    var shoeLabelText = $('#shoeLabelText');
                    shoeLabelText.html('<span style="color: #FF5722;">アップロードエラー</span> <a class="layui-btn layui-btn-xs shoeLabel-reload">再度アップロードする</a>');
                    shoeLabelText.find('.shoeLabel-reload').on('click', function () {
                        uploadInst2.upload();
                    });
                }
            });


            //走线图片上传 
            var uploadInst3 = upload.render({
                elem: '#routing'
                , url: '/attachment/upload/upload/dir/images/module/cms.html'
                , size: 0
                , accept: 'file'
                , acceptMime: 'image/*'
                , auto: false
                , xhr: xhrOnProgress
                , progress: function (value) {
                    element.progress('progressBar', value + '%');
                }
                , before: function (obj) {

                    layer.open({
                        type: 1,
                        title: 'アップロード中',
                        area: [width + 'px'],
                        skin: 'layui-layer-molv',
                        closeBtn: 1,
                        shadeClose: true,
                        content: $('#win') //这里content是一个普通的String
                    });
                    //预读本地文件示例，不支持ie8
                    obj.preview(function (index, file, result) {
                        $('#routing_img').attr('src', result); //图片链接（base64）
                    });
                }
                , choose: function (obj) { //选择文件后的回调
                    var files = obj.pushFile();
                    var filesArry = [];
                    for (var key in files) { //将上传的文件转为数组形式
                        filesArry.push(files[key])
                    }
                    var index = filesArry.length - 1;
                    var file = filesArry[index]; //获取最后选择的图片,即处理多选情况

                    if (navigator.appName == "Microsoft Internet Explorer" && parseInt(navigator.appVersion.split(";")[1]
                        .replace(/[ ]/g, "").replace("MSIE", "")) < 9) {
                        return obj.upload(index, file)
                    }
                    canvasDataURL(file, function (blob) {
                        var aafile = new File([blob], file.name, {
                            type: file.type
                        })
                        var isLt1M;
                        if (file.size < aafile.size) {
                            isLt1M = file.size
                        } else {
                            isLt1M = aafile.size
                        }

                        if (isLt1M / 1024 / 1024 > 10) {
                            return layer.alert('上传图片过大！')
                        } else {
                            if (file.size < aafile.size) {
                                return obj.upload(index, file)
                            }
                            obj.upload(index, aafile)
                        }
                    })
                }
                , done: function (res) {
                    //如果アップロードエラー
                    if (res.code > 0) {
                        return layer.msg('アップロードエラー');
                    }
                    //上传成功
                    $("input[name='images[routing]']").val(res.id);
                }
                , error: function () {
                    //演示失败状态，并实现重传
                    var routingText = $('#routingText');
                    routingText.html('<span style="color: #FF5722;">アップロードエラー</span> <a class="layui-btn layui-btn-xs routing-reload">再度アップロードする</a>');
                    routingText.find('.routing-reload').on('click', function () {
                        uploadInst3.upload();
                    });
                }
            });

            //鞋垫图片上传 
            var uploadInst4 = upload.render({
                elem: '#insole'
                , url: '/attachment/upload/upload/dir/images/module/cms.html'
                , size: 0
                , accept: 'file'
                , acceptMime: 'image/*'
                , auto: false
                , xhr: xhrOnProgress
                , progress: function (value) {
                    element.progress('progressBar', value + '%');
                }
                , before: function (obj) {

                    layer.open({
                        type: 1,
                        title: 'アップロード中',
                        area: [width + 'px'],
                        skin: 'layui-layer-molv',
                        closeBtn: 1,
                        shadeClose: true,
                        content: $('#win') //这里content是一个普通的String
                    });
                    //预读本地文件示例，不支持ie8
                    obj.preview(function (index, file, result) {
                        $('#insole_img').attr('src', result); //图片链接（base64）
                    });
                }
                , choose: function (obj) { //选择文件后的回调
                    var files = obj.pushFile();
                    var filesArry = [];
                    for (var key in files) { //将上传的文件转为数组形式
                        filesArry.push(files[key])
                    }
                    var index = filesArry.length - 1;
                    var file = filesArry[index]; //获取最后选择的图片,即处理多选情况

                    if (navigator.appName == "Microsoft Internet Explorer" && parseInt(navigator.appVersion.split(";")[1]
                        .replace(/[ ]/g, "").replace("MSIE", "")) < 9) {
                        return obj.upload(index, file)
                    }
                    canvasDataURL(file, function (blob) {
                        var aafile = new File([blob], file.name, {
                            type: file.type
                        })
                        var isLt1M;
                        if (file.size < aafile.size) {
                            isLt1M = file.size
                        } else {
                            isLt1M = aafile.size
                        }

                        if (isLt1M / 1024 / 1024 > 10) {
                            return layer.alert('上传图片过大！')
                        } else {
                            if (file.size < aafile.size) {
                                return obj.upload(index, file)
                            }
                            obj.upload(index, aafile)
                        }
                    })
                }
                , done: function (res) {
                    //如果アップロードエラー
                    if (res.code > 0) {
                        return layer.msg('アップロードエラー');
                    }
                    //上传成功
                    $("input[name='images[insole]']").val(res.id);
                }
                , error: function () {
                    //演示失败状态，并实现重传
                    var insoleText = $('#insoleText');
                    insoleText.html('<span style="color: #FF5722;">アップロードエラー</span> <a class="layui-btn layui-btn-xs insole-reload">再度アップロードする</a>');
                    insoleText.find('.insole-reload').on('click', function () {
                        uploadInst4.upload();
                    });
                }
            });

            //侧标图片上传 
            var uploadInst5 = upload.render({
                elem: '#sideMarker'
                , url: '/attachment/upload/upload/dir/images/module/cms.html'
                , size: 0
                , accept: 'file'
                , acceptMime: 'image/*'
                , auto: false
                , xhr: xhrOnProgress
                , progress: function (value) {
                    element.progress('progressBar', value + '%');
                }
                , before: function (obj) {

                    layer.open({
                        type: 1,
                        title: 'アップロード中',
                        area: [width + 'px'],
                        skin: 'layui-layer-molv',
                        closeBtn: 1,
                        shadeClose: true,
                        content: $('#win') //这里content是一个普通的String
                    });
                    //预读本地文件示例，不支持ie8
                    obj.preview(function (index, file, result) {
                        $('#sideMarker_img').attr('src', result); //图片链接（base64）
                    });
                }
                , choose: function (obj) { //选择文件后的回调
                    var files = obj.pushFile();
                    var filesArry = [];
                    for (var key in files) { //将上传的文件转为数组形式
                        filesArry.push(files[key])
                    }
                    var index = filesArry.length - 1;
                    var file = filesArry[index]; //获取最后选择的图片,即处理多选情况

                    if (navigator.appName == "Microsoft Internet Explorer" && parseInt(navigator.appVersion.split(";")[1]
                        .replace(/[ ]/g, "").replace("MSIE", "")) < 9) {
                        return obj.upload(index, file)
                    }
                    canvasDataURL(file, function (blob) {
                        var aafile = new File([blob], file.name, {
                            type: file.type
                        })
                        var isLt1M;
                        if (file.size < aafile.size) {
                            isLt1M = file.size
                        } else {
                            isLt1M = aafile.size
                        }

                        if (isLt1M / 1024 / 1024 > 10) {
                            return layer.alert('上传图片过大！')
                        } else {
                            if (file.size < aafile.size) {
                                return obj.upload(index, file)
                            }
                            obj.upload(index, aafile)
                        }
                    })
                }
                , done: function (res) {
                    //如果アップロードエラー
                    if (res.code > 0) {
                        return layer.msg('アップロードエラー');
                    }
                    //上传成功
                    $("input[name='images[sideMarker]']").val(res.id);
                }
                , error: function () {
                    //演示失败状态，并实现重传
                    var sideMarkerText = $('#sideMarkerText');
                    sideMarkerText.html('<span style="color: #FF5722;">アップロードエラー</span> <a class="layui-btn layui-btn-xs sideMarker-reload">再度アップロードする</a>');
                    sideMarkerText.find('.sideMarker-reload').on('click', function () {
                        uploadInst5.upload();
                    });
                }
            });

            //钢印图片上传 
            var uploadInst6 = upload.render({
                elem: '#embossed'
                , url: '/attachment/upload/upload/dir/images/module/cms.html'
                , size: 0
                , accept: 'file'
                , acceptMime: 'image/*'
                , auto: false
                , xhr: xhrOnProgress
                , progress: function (value) {
                    element.progress('progressBar', value + '%');
                }
                , before: function (obj) {

                    layer.open({
                        type: 1,
                        title: 'アップロード中',
                        area: [width + 'px'],
                        skin: 'layui-layer-molv',
                        closeBtn: 1,
                        shadeClose: true,
                        content: $('#win') //这里content是一个普通的String
                    });
                    //预读本地文件示例，不支持ie8
                    obj.preview(function (index, file, result) {
                        $('#embossed_img').attr('src', result); //图片链接（base64）
                    });
                }
                , choose: function (obj) { //选择文件后的回调
                    var files = obj.pushFile();
                    var filesArry = [];
                    for (var key in files) { //将上传的文件转为数组形式
                        filesArry.push(files[key])
                    }
                    var index = filesArry.length - 1;
                    var file = filesArry[index]; //获取最后选择的图片,即处理多选情况

                    if (navigator.appName == "Microsoft Internet Explorer" && parseInt(navigator.appVersion.split(";")[1]
                        .replace(/[ ]/g, "").replace("MSIE", "")) < 9) {
                        return obj.upload(index, file)
                    }
                    canvasDataURL(file, function (blob) {
                        var aafile = new File([blob], file.name, {
                            type: file.type
                        })
                        var isLt1M;
                        if (file.size < aafile.size) {
                            isLt1M = file.size
                        } else {
                            isLt1M = aafile.size
                        }

                        if (isLt1M / 1024 / 1024 > 10) {
                            return layer.alert('上传图片过大！')
                        } else {
                            if (file.size < aafile.size) {
                                return obj.upload(index, file)
                            }
                            obj.upload(index, aafile)
                        }
                    })
                }
                , done: function (res) {
                    //如果アップロードエラー
                    if (res.code > 0) {
                        return layer.msg('アップロードエラー');
                    }
                    //上传成功
                    $("input[name='images[embossed]']").val(res.id);
                }
                , error: function () {
                    //演示失败状态，并实现重传
                    var embossedText = $('#embossedText');
                    embossedText.html('<span style="color: #FF5722;">アップロードエラー</span> <a class="layui-btn layui-btn-xs embossed-reload">再度アップロードする</a>');
                    embossedText.find('.embossed-reload').on('click', function () {
                        uploadInst6.upload();
                    });
                }
            });

            //内刻印图片上传 
            var uploadInst7 = upload.render({
                elem: '#inscribed'
                , url: '/attachment/upload/upload/dir/images/module/cms.html'
                , size: 0
                , accept: 'file'
                , acceptMime: 'image/*'
                , auto: false
                , xhr: xhrOnProgress
                , progress: function (value) {
                    element.progress('progressBar', value + '%');
                }
                , before: function (obj) {

                    layer.open({
                        type: 1,
                        title: 'アップロード中',
                        area: [width + 'px'],
                        skin: 'layui-layer-molv',
                        closeBtn: 1,
                        shadeClose: true,
                        content: $('#win') //这里content是一个普通的String
                    });
                    //预读本地文件示例，不支持ie8
                    obj.preview(function (index, file, result) {
                        $('#inscribed_img').attr('src', result); //图片链接（base64）
                    });
                }
                , choose: function (obj) { //选择文件后的回调
                    var files = obj.pushFile();
                    var filesArry = [];
                    for (var key in files) { //将上传的文件转为数组形式
                        filesArry.push(files[key])
                    }
                    var index = filesArry.length - 1;
                    var file = filesArry[index]; //获取最后选择的图片,即处理多选情况

                    if (navigator.appName == "Microsoft Internet Explorer" && parseInt(navigator.appVersion.split(";")[1]
                        .replace(/[ ]/g, "").replace("MSIE", "")) < 9) {
                        return obj.upload(index, file)
                    }
                    canvasDataURL(file, function (blob) {
                        var aafile = new File([blob], file.name, {
                            type: file.type
                        })
                        var isLt1M;
                        if (file.size < aafile.size) {
                            isLt1M = file.size
                        } else {
                            isLt1M = aafile.size
                        }

                        if (isLt1M / 1024 / 1024 > 10) {
                            return layer.alert('上传图片过大！')
                        } else {
                            if (file.size < aafile.size) {
                                return obj.upload(index, file)
                            }
                            obj.upload(index, aafile)
                        }
                    })
                }
                , done: function (res) {
                    //如果アップロードエラー
                    if (res.code > 0) {
                        return layer.msg('アップロードエラー');
                    }
                    //上传成功
                    $("input[name='images[inscribed]']").val(res.id);
                }
                , error: function () {
                    //演示失败状态，并实现重传
                    var inscribedText = $('#inscribedText');
                    inscribedText.html('<span style="color: #FF5722;">アップロードエラー</span> <a class="layui-btn layui-btn-xs inscribed-reload">再度アップロードする</a>');
                    inscribedText.find('.inscribed-reload').on('click', function () {
                        uploadInst7.upload();
                    });
                }
            });

            //箱图片上传 
            var uploadInst8 = upload.render({
                elem: '#shoecase'
                , url: '/attachment/upload/upload/dir/images/module/cms.html'
                , size: 0
                , accept: 'file'
                , acceptMime: 'image/*'
                , auto: false
                , xhr: xhrOnProgress
                , progress: function (value) {
                    element.progress('progressBar', value + '%');
                }
                , before: function (obj) {

                    layer.open({
                        type: 1,
                        title: 'アップロード中',
                        area: [width + 'px'],
                        skin: 'layui-layer-molv',
                        closeBtn: 1,
                        shadeClose: true,
                        content: $('#win') //这里content是一个普通的String
                    });
                    //预读本地文件示例，不支持ie8
                    obj.preview(function (index, file, result) {
                        $('#shoecase_img').attr('src', result); //图片链接（base64）
                    });
                }
                , choose: function (obj) { //选择文件后的回调
                    var files = obj.pushFile();
                    var filesArry = [];
                    for (var key in files) { //将上传的文件转为数组形式
                        filesArry.push(files[key])
                    }
                    var index = filesArry.length - 1;
                    var file = filesArry[index]; //获取最后选择的图片,即处理多选情况

                    if (navigator.appName == "Microsoft Internet Explorer" && parseInt(navigator.appVersion.split(";")[1]
                        .replace(/[ ]/g, "").replace("MSIE", "")) < 9) {
                        return obj.upload(index, file)
                    }
                    canvasDataURL(file, function (blob) {
                        var aafile = new File([blob], file.name, {
                            type: file.type
                        })
                        var isLt1M;
                        if (file.size < aafile.size) {
                            isLt1M = file.size
                        } else {
                            isLt1M = aafile.size
                        }

                        if (isLt1M / 1024 / 1024 > 10) {
                            return layer.alert('上传图片过大！')
                        } else {
                            if (file.size < aafile.size) {
                                return obj.upload(index, file)
                            }
                            obj.upload(index, aafile)
                        }
                    })
                }
                , done: function (res) {
                    //如果アップロードエラー
                    if (res.code > 0) {
                        return layer.msg('アップロードエラー');
                    }
                    //上传成功
                    $("input[name='images[shoecase]']").val(res.id);
                }
                , error: function () {
                    //演示失败状态，并实现重传
                    var shoecaseText = $('#shoecaseText');
                    shoecaseText.html('<span style="color: #FF5722;">アップロードエラー</span> <a class="layui-btn layui-btn-xs shoecase-reload">再度アップロードする</a>');
                    shoecaseText.find('.shoecase-reload').on('click', function () {
                        uploadInst8.upload();
                    });
                }
            });

            // 更多补图
            // upload.render({
            //   elem: '#more_graph'
            //   ,url: '/attachment/upload/upload/dir/images/module/cms.html'
            //   ,multiple: true
            //   ,before: function(obj){
            //     //预读本地文件示例，不支持ie8
            //     obj.preview(function(index, file, result){
            //       $('#more_graph_img').append('<img src="'+ result +'" alt="'+ file.name +'" class="layui-upload-img" style="margin-right: 10px;">')
            //     });
            //   }
            //   ,done: function(res){
            //     //上传完毕
            //     var original_val = $("input[name='more_graph']").val();
            //     $("input[name='more_graph']").val([original_val, res.id]);
            //   }
            // });

        })

// var u = navigator.userAgent, app = navigator.appVersion;
// var isAndroid = u.indexOf('Android') > -1 || u.indexOf('Linux') > -1; //android终端或者uc浏览器
// if(isAndroid){
//     $(":file").attr('capture','camera');
// }


    </script>
    <div class="footer">
        <div class="container">
            <div class="footer-grids">
                <div class="col-md-2 footer-grid wow fadeInLeft animated" data-wow-delay=".5s">
                    <ul>
                        <li><a href="/cms_single?catid=1&index=0">利用規約</a></li>
                        <li><a href="/cms_single?catid=1&index=1">プライバシーポリシー</a></li>
                        <li><a href="/cms_single?catid=1&index=2">個人情報保護法</a></li>
                        <li><a href="/cms_single?catid=1&index=3">ソーシャルメディアポリシー</a></li>
                        <li><a href="/cms_single?catid=1&index=4">特定商取引法に基づく表記</a></li>
                    </ul>
                </div>
                <div class="col-md-2 footer-grid animated wow fadeInUp animated animated" data-wow-duration="1200ms"
                    data-wow-delay="500ms">
                    <ul>
                        <li>
                            <a href="../cms_market.html">商品価格</span></a>
                        </li>
                        <li>
                            <a href="../cms/member_enquiry">無料査定</span></a>
                        </li>
                        <li>
                            <a href="/cms_step">利用ガイド</span></a>
                        </li>
                        <!-- <li>
                            <a href="/cms_index.html">無料鑑定</span></a>
                        </li> -->
                        <li>
                            <a href="../cms_problem.html">よくある質問</span></a>
                        </li>
                    </ul>
                </div>
                <div id="shop">
                </div>
                <div class="clearfix"> </div>
            </div>
            <div class="foot-info">
                <div class="info">
                    <ul>
                        <li><a href="/cms/index"><img src="static/img/footlogo.png" alt=""></a></li>
                        <li><a href="/singlepage2/128">会社紹介</a></li>
                        <li><a href="/singlepage2/129">お問い合わせ</a></li>
                        <li><a href="/singlepage2/130">採用情報</a></li>
                        <li><a href="/singlepage2/42">利用規約</a></li>
                    </ul>
                </div>
                <div class="col-md-2 footer-grid animated wow fadeInUp animated animated" data-wow-duration="1200ms"
                    data-wow-delay="500ms">
                    <ul class="social-icons1">
                        <li><a href="https://twitter.com/the1sneaker"><img src="static/img/twitter.png"
                                    style="height:30px ;width:30px;"></img></a></li>
                        <li><a href="https://twitter.com/the1sneaker"><img src="static/img/ins.png"
                                    style="height:30px ;width:30px;"></img></a></li>
                        <li><a href="https://twitter.com/the1sneaker"><img src="static/img/line.png"
                                    style="height:30px ;width:30px;"></img></a></li>
                        <li><a href="https://twitter.com/the1sneaker"><img src="static/img/mail.png"
                                    style="height:30px ;width:30px;"></img></a></li>
                    </ul>
                </div>
            </div>
            <div class="copy-right wow fadeInUp animated animated" data-wow-duration="1200ms" data-wow-delay="500ms">
                <div class="earth">
                    <img src="static/img/earth.png"><span>日本/日本語</span>
                </div>
                <p>東京都公安委員会 第305502007435号</p>
                <p>FIT株式会社 <a href="http://www.beian.miit.gov.cn/" target="_blank">豫ICP备19030271号-1</a> <a href="/"
                        target="_blank" title="the1sneaker">the1sneaker</a></p>
            </div>
        </div>
    </div>
    <style>
        .footer {
            background: #333;
            margin-top: 40px;
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
        let token = sessionStorage.getItem('token')
        if (token) {
            $('.token').css('display', 'none')
            $.ajax({
                type: 'get',
                url: baseUrl + "/api/shopping",
                headers: {
                    'Authorization': sessionStorage.getItem('token'),
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                success(data) {
                    console.log(data);
                    if (data.code == 200) {
                        if (data.data == 0) {
                            $('#shopcar').css('display', 'none')
                        } else {
                            $('#shopcar').html(data.data.total)
                        }
                    } else {
                        layui.msg(data.msg)
                    }
                }
            })
        } else {
            window.location.href = '/member_login'
            $('.tokenin').css('display', 'none')
        }
        $("#searchicon").click(function () {
            $(".searchmain").slideDown();
        });
        $("#cha").click(function () {
            $(".searchmain").slideUp();
        });
        $('#caricon').click(function () {
            $.post("member/content/cartsList", function (data) {
                if (data.code == 0) {
                    console.log(data.data);
                } else {
                    layer.msg('网络错误!');
                }
            })
        })
        $(function () {
            var _nava = $('.navbar-nav a');
            var _url = window.location.href;
            var _host = window.location.host;
            for (var i = 0; i < _nava.length; i++) {
                var _astr = _nava.eq(i).attr('href');
                _astr = _astr.split('./')[1]
                if (_url.indexOf(_astr) != -1) {
                    _nava.eq(i).parent().addClass('active').siblings().removeClass('active');
                } else if (_url == ('http://' + _host + '/')) {
                    _nava.eq(0).addClass('active').siblings().removeClass('active');
                }
            }
        })
    </script>
    <script>
        var _hmt = _hmt || [];
        (function () {
            var hm = document.createElement("script");
            hm.src = "https://hm.baidu.com/hm.js?9fd62443f08c83cfd1fb5c01f92a3448";
            var s = document.getElementsByTagName("script")[0];
            s.parentNode.insertBefore(hm, s);
        })();

        //刷新底部栏
        $(function () {
            layui.use(['layer'], function () {
                $.get(baseUrl + "/api/shop", {}, function (data) {
                    if (data.code == 200) {
                        var h5 = ''
                        $.each(data.data, function (index, item) {
                            if (index >= 2) {
                                return false;
                            } else {
                                h5 += '<div class="col-md-2 footer-grid wow fadeInRight animated" data-wow-delay=".5s">'
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