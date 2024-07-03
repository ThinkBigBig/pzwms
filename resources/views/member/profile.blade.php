@include('member.header')
<div class="layui-tab layui-tab-brief" lay-filter="user">
    <ul class="layui-tab-title" id="LAY_mine">
        <li class="layui-this" lay-id="examine">会員申請</li>
        <li lay-id="examine2">情報修正</li>
        <li lay-id="info">会員情報</li>
        <li lay-id="avatar">写真</li>
        <li lay-id="pass">パスワード</li>
    </ul>
    <div class="layui-tab-content" style="padding: 20px 0;">

        <div class="layui-form-pane layui-tab-item layui-show">
            <div class="layui-form-mid layui-word-aux">
                ※注意　提出して頂いたお客様情報を確認し36時間～48時間以内に承認させて頂きます。また弊社からご連絡ある場合がございますのでご了承下さい。
            </div>
            <form class="layui-form" id="addExamine">
                <div class="layui-form-item"> <label class="layui-form-label">お名前</label>
                    <div class="layui-input-block"> <input type="text" name="name" required="" lay-verify="required" autocomplete="off" value="" class="layui-input"> </div>
                </div>
                <div class="layui-form-item"> <label class="layui-form-label">性別</label>
                    <div class="layui-input-block">
                        <input type="radio" name="sex" value="1" title="男">
                        <input type="radio" name="sex" value="2" title="女">
                    </div>
                </div>
                <div class="layui-form-item"> <label class="layui-form-label">生年月日</label>
                    <div class="layui-input-block">
                        <input type="text" id='birth' name="birth" required="" lay-verify="required" autocomplete="off" value="" class="layui-input test-item" readonly="readonly">
                    </div>
                </div>
                <div class="layui-form-item"> <label class="layui-form-label">年齢</label>
                    <div class="layui-input-block"> <input type="text" id='age' readonly style="background:#CCCCCC" name="age" required="" lay-verify="required" autocomplete="off" value="" class="layui-input">
                    </div>
                </div>

                <div class="layui-form-item"> <label class="layui-form-label">職業</label>
                    <div class="layui-input-block">
                        <select name="occupation" value="">
                            <option value="会社員">会社員</option>
                            <option value="自営業">自営業</option>
                            <option value="公務員">公務員</option>
                            <option value="アルバイト">アルバイト</option>
                            <option value="学生">学生</option>
                            <option value="その他">その他</option>
                        </select>
                    </div>
                </div>
                <div class="layui-form-item"> <label class="layui-form-label">銀行名</label>
                    <div class="layui-input-block"> <input type="text" name="bank_name" required="" lay-verify="required" autocomplete="off" value="" class="layui-input"> </div>
                </div>

                <div class="layui-form-item"> <label class="layui-form-label">支店名</label>
                    <div class="layui-input-block"> <input type="text" name="branch_name" required="" lay-verify="required" autocomplete="off" value="" class="layui-input"> </div>
                </div>
                <div class="layui-form-item"> <label class="layui-form-label">口座種別</label>
                    <div class="layui-input-block">
                        <select name="occupation2" value="">
                            <option value="普通">普通</option>
                            <option value="当座">当座</option>
                            <option value="貯蓄">貯蓄</option>
                        </select>
                    </div>
                </div>

                <div class="layui-form-item"> <label class="layui-form-label">口座番号</label>
                    <div class="layui-input-block"> <input type="text" name="slogans" required="" lay-verify="required" autocomplete="off" value="" class="layui-input"> </div>
                </div>

                <div class="layui-form-item"> <label class="layui-form-label">口座名義</label>
                    <div class="layui-input-block"> <input type="text" name="name_mouth" required="" lay-verify="required" autocomplete="off" value="" class="layui-input"> </div>
                </div>

                <div class="layui-form-item"> <label class="layui-form-label">ご住所</label>
                    <div class="layui-input-block"> <input type="text" name="addr" required="" lay-verify="required" autocomplete="off" value="" class="layui-input"> </div>
                </div>
                <div class="layui-form-item"> <label class="layui-form-label">連絡先電話番号</label>
                    <div class="layui-input-block"> <input type="text" name="phone_number" required="" lay-verify="required|jp_mobile" autocomplete="off" value="" class="layui-input"> </div>
                </div>
                <div class="layui-form-item"> <label class="layui-form-label">ご署名</label>
                    <div class="layui-input-block"> <input type="text" name="signature" required="" lay-verify="required" autocomplete="off" value="" class="layui-input"> </div>
                </div>

                <div class="layui-form-item"> <label class="layui-form-label" style="width: 80%">顔付き身分証をアップロードする</label>
                    <div class="layui-input-block"> </div>
                </div>
                <div class="layui-upload">
                    <button type="button" class="layui-btn" id="positive">正面</button>
                    <div class="layui-upload-list">
                        <img class="layui-upload-img" id="positiveImg" src="" style="width: 220px;height:220px;">
                        <p id="positiveText"></p>
                        <input type="hidden" name="positive" required="" placeholder="证件图片详情" value="0">
                    </div>
                </div>
                <div class="layui-upload">
                    <button type="button" class="layui-btn" id="otherSide">裏側</button>
                    <div class="layui-upload-list">
                        <img class="layui-upload-img" id="otherSideImg" src="" style="width: 220px;height:220px;">
                        <p id="otherSideText"></p>
                        <input type="hidden" name="other_side" required="" placeholder="证件图片详情" value="0">
                    </div>
                </div>

                <div class="layui-form-item">
                    <input type="hidden" name="id" value="">
                    <button class="layui-btn" key="set-mine" lay-filter="formSubmit" type="button" onclick="addExamine()" lay-submit="">申請依頼</button>
                    <!-- <input type="submit">提交审核</button> -->
                </div>
            </form>
        </div>

        <!-- 修改情报信息 -->
        <div class="layui-form-pane layui-tab-item">
            <form class="layui-form" id="exidEamine">
                <div class="layui-form-item"> <label class="layui-form-label">お名前</label>
                    <div class="layui-input-block"> <input type="text" name="name" required="" lay-verify="required" autocomplete="off" value="" class="layui-input"> </div>
                </div>
                <div class="layui-form-item"> <label class="layui-form-label">性別</label>
                    <div class="layui-input-block">
                        <input type="radio" name="sex" value="1" title="男">
                        <input type="radio" name="sex" value="2" title="女">
                    </div>
                </div>
                <div class="layui-form-item"> <label class="layui-form-label">生年月日</label>
                    <div class="layui-input-block">
                        <input type="text" id='birth2' name="birth" required="" lay-verify="required" autocomplete="off" value="" class="layui-input test-item2" readonly="readonly">
                    </div>
                </div>
                <div class="layui-form-item"> <label class="layui-form-label">年齢</label>
                    <div class="layui-input-block"> <input type="text" readonly style="background:#CCCCCC" id="age2" name="age" required="" lay-verify="required" autocomplete="off" value="" class="layui-input">
                    </div>
                </div>

                <div class="layui-form-item"> <label class="layui-form-label">職業</label>
                    <div class="layui-input-block">
                        <select name="occupation" lay-verify="">
                            <option value="会社員">会社員
                            </option>
                            <option value="自営業">自営業
                            </option>
                            <option value="公務員">公務員
                            </option>
                            <option value="アルバイト">
                                アルバイト</option>
                            <option value="学生">学生
                            </option>
                            <option value="その他">その他
                            </option>
                        </select>
                    </div>

                </div>

                <div class="layui-form-item"> <label class="layui-form-label">銀行名</label>
                    <div class="layui-input-block"> <input type="text" name="bank_name" required="" lay-verify="required" autocomplete="off" value="" class="layui-input"> </div>
                </div>

                <div class="layui-form-item"> <label class="layui-form-label">支店名</label>
                    <div class="layui-input-block"> <input type="text" name="branch_name" required="" lay-verify="required" autocomplete="off" value="" class="layui-input"> </div>
                </div>

                <div class="layui-form-item"> <label class="layui-form-label">口座種別</label>
                    <div class="layui-input-block">
                        <select name="port_category" lay-verify="">
                            <option value="普通">普通
                            </option>
                            <option value="当座">当座
                            </option>
                            <option value="貯蓄">貯蓄
                            </option>
                        </select>
                    </div>
                </div>

                <div class="layui-form-item"> <label class="layui-form-label">口座番号</label>
                    <div class="layui-input-block"> <input type="text" name="slogans" required="" lay-verify="required" autocomplete="off" value="" class="layui-input"> </div>
                </div>

                <div class="layui-form-item"> <label class="layui-form-label">口座名義</label>
                    <div class="layui-input-block"> <input type="text" name="name_mouth" required="" lay-verify="required" autocomplete="off" value="" class="layui-input"> </div>
                </div>

                <div class="layui-form-item"> <label class="layui-form-label">ご住所</label>
                    <div class="layui-input-block"> <input type="text" name="addr" required="" lay-verify="required" autocomplete="off" value="" class="layui-input"> </div>
                </div>
                <div class="layui-form-item"> <label class="layui-form-label">連絡先電話番号</label>
                    <div class="layui-input-block"> <input type="text" name="phone_number" required="" lay-verify="required|jp_mobile" autocomplete="off" value="" class="layui-input"> </div>
                </div>
                <div class="layui-form-item"> <label class="layui-form-label">ご署名</label>
                    <div class="layui-input-block"> <input type="text" name="signature" required="" lay-verify="required" autocomplete="off" value="" class="layui-input"> </div>
                </div>

                <div class="layui-form-item">
                    <input type="hidden" name="id" value="">
                    <button class="layui-btn" key="set-mine" lay-filter="formSubmit" onclick="exidExamine()" lay-submit type="button">修正</button>
                </div>
            </form>
        </div>



        <div class="layui-form layui-form-pane layui-tab-item">
            <form class="layui-form" id="setmine">
                <div class="layui-form-item"> <label class="layui-form-label">アカウント</label>
                    <div class="layui-input-block">
                        <input type="text" name="username" required="" lay-verify="required" autocomplete="off" value="" class="layui-input" disabled style="cursor: not-allowed !important;">
                    </div>
                </div>
                <div class="layui-form-item"> <label class="layui-form-label">お名前</label>
                    <div class="layui-input-block">
                        <input type="text" name="nickname" required="" lay-verify="required" autocomplete="off" value="" class="layui-input">
                    </div>
                </div>
                <div class="layui-form-item"> <label class="layui-form-label">連絡先電話番号</label>
                    <div class="layui-input-block"> <input type="text" name="mobile" required="" lay-verify="jp_mobile" autocomplete="off" value="" class="layui-input"></div>
                    <!-- <button class="layui-btn layui-iframe" type="button" href="{:url('changemobile')}"
                                    lay-data="{width:'440px',height:'250px',title:'修改手机'}">修改</button> -->
                </div>
                <div class="layui-form-item"> <label class="layui-form-label">メールアドレス</label>
                    <div class="layui-input-block"> <input type="text" name="email" required="" lay-verify="email" autocomplete="off" value="" class="layui-input"> </div>
                    <!-- <button class="layui-btn layui-iframe" type="button" href="{:url('changeemail')}"
                                    lay-data="{width:'440px',height:'250px',title:'修改邮箱'}">修改</button> -->
                </div>
                <div class="layui-form-item"><button class="layui-btn" key="set-mine" lay-filter="formSubmit" lay-submit="" type="button" onclick="setmine()">登録する</button>
                </div>
            </form>
        </div>
        <div class="layui-form layui-form-pane layui-tab-item" id="headimg">
            <div class="layui-form-item">
                <div class="avatar-add">
                    <p>サイズ168 * 168、サポートjpg、png、gif、最大サイズは50KBを超えることはできません</p> <button type="button" class="layui-btn upload-img" id="test1">
                        <i class="layui-icon"></i>写真アップロード </button><input type="hidden" name="avatar" required="" placeholder="头像详情" value="0">
                    <img id="acatarIMG" src=""> <span class="loading"></span>
                </div>
            </div>
        </div>
        <div class="layui-form-pane layui-tab-item">
            <form class="layui-form" id="mpass">
                <div class="layui-form-item"> <label class="layui-form-label">パスワード</label>
                    <div class="layui-input-block"> <input type="password" id="oldpassword" name="oldpassword" required="" lay-verify="required" autocomplete="off" class="layui-input"> </div>
                </div>
                <div class="layui-form-item"> <label class="layui-form-label">新しいパスワード</label>
                    <div class="layui-input-block"> <input type="password" id="newpassword" name="newpassword" required="" lay-verify="required" autocomplete="off" class="layui-input"> </div>
                </div>
                <div class="layui-form-item"> <label class="layui-form-label">新しいパスワード確認</label>
                    <div class="layui-input-block"> <input type="password" name="renewpassword" id="renewpassword" required="" lay-verify="confirmPass" autocomplete="off" class="layui-input"> </div>
                </div>
                <div class="layui-form-item"> <button type="button" class="layui-btn" key="set-mine" lay-filter="formSubmit" lay-submit="" onclick="changepass()">登録する</button> </div>
            </form>
        </div>


    </div>
</div>

<!-- 上传图片进度条 -->
<div id="win" style="display:none" class="layui-progress layui-progress-big" lay-showPercent="yes" lay-filter="progressBar">
    <div class="layui-progress-bar layui-bg-red" lay-percent="0%"></div>
</div>

<style>
    .layui-upload-img {
        width: 120px;
        height: 120px;
        margin: 0 10px 10px 0;
    }

    .layui-form-radio>i:hover,
    .layui-form-radioed>i {
        color: #a8b6cb;
    }

    .layui-btn {
        background-color: #a8b6cb;
    }
</style>
<script type="text/javascript">
    function name(data) {
        if (data.data.member.status != 0) {
            $("input[name='name']")[0].value = data.data.name
            $("input[name='name']")[1].value = data.data.name
            if (data.data.sex == 1) {
                $("input[name='sex']").eq(0).next().click()
                $("input[name='sex']").eq(2).next().click()
            } else {
                $("input[name='sex']").eq(1).next().click()
                $("input[name='sex']").eq(3).next().click()
            }
            $("input[name='birth']")[0].value = data.data.birth
            $("input[name='birth']")[1].value = data.data.birth
            $("input[name='age']")[0].value = data.data.age
            $("input[name='age']")[1].value = data.data.age
            $("select[name='occupation']")[0].value = data.data.occupation
            $("select[name='occupation']")[1].value = data.data.occupation
            $("input[name='bank_name']")[0].value = data.data.bank_name
            $("input[name='bank_name']")[1].value = data.data.bank_name
            $("input[name='branch_name']")[0].value = data.data.branch_name
            $("input[name='branch_name']")[1].value = data.data.branch_name
            $("select[name='occupation2']")[0].value = data.data.port_category
            $("select[name='occupation']")[1].value = data.data.port_category
            $("input[name='slogans']")[0].value = data.data.slogans
            $("input[name='slogans']")[1].value = data.data.slogans
            $("input[name='name_mouth']")[0].value = data.data.name_mouth
            $("input[name='name_mouth']")[1].value = data.data.name_mouth
            $("input[name='addr']")[0].value = data.data.addr
            $("input[name='addr']")[1].value = data.data.addr
            $("input[name='id']")[0].value = data.data.id
            $("input[name='id']")[1].value = data.data.id
            $("input[name='phone_number']")[0].value = data.data.phone_number
            $("input[name='phone_number']")[1].value = data.data.phone_number
            $("input[name='signature']")[0].value = data.data.signature
            $("input[name='signature']")[1].value = data.data.signature
            $("#positiveImg").attr('src', data.data.positive_url)
            $("#otherSideImg").attr('src', data.data.otherSideImg)
            $("input[name='username']")[0].value = data.data.member.username
            $("input[name='nickname']")[0].value = data.data.member.nickname
            $("input[name='email']")[0].value = data.data.member.email
            $("input[name='mobile']")[0].value = data.data.member.mobile
            $('#avatar').attr('src', data.data.member.avatar)
            $('#avatarIMG').attr('src', data.data.member.avatar)
            $('#username').html(data.data.member.username)
            $('#positiveImg').attr('src', data.data.positive)
            $('#otherSideImg').attr('src', data.data.other_side)
        } else {
            $("input[name='username']")[0].value = data.data.member.username
            $("input[name='nickname']")[0].value = data.data.member.nickname
            $("input[name='email']")[0].value = data.data.member.email
            $("input[name='mobile']")[0].value = data.data.member.mobile
            $('#avatar').attr('src', data.data.member.avatar)
            $('#avatarIMG').attr('src', data.data.member.avatar)
            $('#username').html(data.data.member.username)
            $('#positiveImg').attr('src', data.data.positive)
            $('#otherSideImg').attr('src', data.data.other_side)
        }
    }

    function addExamine() {
        var d = {};
        var t = $('#addExamine [name]').serializeArray();
        let flag = true
        $.each(t, function() {
            d[this.name] = this.value;
            if (this.name != 'id') {
                if (this.value == 0 || this.value == null || this.value == undefined || this.value == '' || this.value == '0') {
                    flag = false
                }
            }
        });
        console.log(flag);
        let data = d;
        if (flag) {
            $.ajax({
                type: 'post',
                url: baseUrl + "/api/examine/add",
                data: data,
                headers: {
                    'Authorization': sessionStorage.getItem('token'),
                },
                success(data) {
                    if (data.code == 200) {
                        layer.msg(data.msg)
                        window.location.reload()
                        name(data)
                    } else {
                        layer.msg(data.msg)
                    }
                }
            })
        }
    }

    function changepass() {
        let data = {
            password: null,
            old_password: null
        }
        if ($('#newpassword').val() != $('#renewpassword').val()) {
            layer.msg('两次密码输入不一致！')
            return false
        }
        data.password = $('#newpassword').val()
        data.old_password = $('#oldpassword').val()
        $.ajax({
            type: 'post',
            url: baseUrl + "/api/member/edit",
            data: data,
            headers: {
                'Authorization': sessionStorage.getItem('token'),
            },
            success(data) {
                if (data.code == 200) {
                    layer.msg(data.msg)
                    location.reload()
                } else {
                    layer.msg(data.msg)
                    window.location.reload()
                }
            }
        })
    }

    function setmine() {
        var d = {};
        var t = $('#setmine [name]').serializeArray();
        $.each(t, function() {
            d[this.name] = this.value;
        });
        let data = d;
        $.ajax({
            type: 'post',
            url: baseUrl + "/api/member/edit",
            data: data,
            headers: {
                'Authorization': sessionStorage.getItem('token'),
            },
            success(data) {
                if (data.code == 200) {
                    layer.msg(data.msg)
                    $.ajax({
                        type: 'get',
                        url: baseUrl + "/api/examine/info",
                        headers: {
                            'Authorization': sessionStorage.getItem('token'),
                        },
                        success(data) {
                            if (data.code == 200) {
                                name(data)
                            } else {
                                layui.msg(data.msg)
                            }
                        }
                    })
                } else {
                    layer.msg(data.msg)
                }
            }
        })
    }

    function exidExamine() {
        var d = {};
        var flag = true
        var t = $('#exidEamine [name]').serializeArray();
        $.each(t, function() {
            d[this.name] = this.value;
            if (this.value == '' || this.value == null || this.value == undefined || this.value == 0 || this.value == '0') {
                flag = false
            }
        });
        if (!flag) {
            return
        } else {
            let data = d;
            $.ajax({
                type: 'post',
                url: baseUrl + "/api/examine/edit",
                data: data,
                headers: {
                    'Authorization': sessionStorage.getItem('token'),
                },
                success(data) {
                    if (data.code == 200) {
                        layer.msg(data.msg)
                        name(data)
                    } else {
                        layer.msg(data.msg)
                    }
                }
            })
        }

    }
    layui.use('form', function() {
        var form = layui.form;
        var $ = layui.$
        // 表单验证
        form.verify({
            // jp_mobile: function(value, item) {
            //     if (!new RegExp("^(070|080|090)[0-9]{8}$").test(value)) {
            //         return '正しい携帯電話番号を入力ください!';
            //     }
            // },
            confirmPass: function(value) {
                if ($('input[name=password]').val() !== value)
                    return '两次密码输入不一致！';
            }
        });
        $.ajax({
            type: 'get',
            url: baseUrl + "/api/examine/info",
            headers: {
                'Authorization': sessionStorage.getItem('token'),
            },
            success(data) {
                if (data.code == 200) {
                    name(data)
                } else {
                    layui.msg(data.msg)
                }
            }
        })
    });
    layui.use(['laydate', 'form'], function() {
        var laydate = layui.laydate;
        var form = layui.form
        lay('.test-item').each(function() {
            var time = new Date();
            var max_data = time.getFullYear();
            max_data += '-'
            max_data += time.getMonth()
            max_data += '-'
            max_data += time.getHours()
            console.log(max_data);
            laydate.render({
                elem: this,
                lang: 'en',
                trigger: 'click',
                type: 'date',
                max: max_data,
                value: max_data,
                done: function(value, date, endDate) {
                    var birth = document.getElementById("birth").value;
                    var re_age = self.GetAge(value)
                    $('#age').val(re_age)
                }
            });
        });
        lay('.test-item2').each(function() {
            laydate.render({
                elem: this,
                lang: 'en',
                trigger: 'click',
                type: 'date',
                done: function(value, date, endDate) {
                    console.log(value);
                    var birth = document.getElementById("birth2").value;
                    var re_age = self.GetAge(value)
                    $('#age2').val(re_age)
                }
            });
        });
    });
    //计算周岁
    function GetAge(strBirthday) {
        var returnAge,
            strBirthdayArr = strBirthday.split("-"),
            birthYear = strBirthdayArr[0],
            birthMonth = strBirthdayArr[1],
            birthDay = strBirthdayArr[2],
            d = new Date(),
            nowYear = d.getFullYear(),
            nowMonth = d.getMonth() + 1,
            nowDay = d.getDate();
        if (nowYear == birthYear) {
            returnAge = 0; //同年 则为0周岁
        } else {
            var ageDiff = nowYear - birthYear; //年之差
            if (ageDiff > 0) {
                if (nowMonth == birthMonth) {
                    var dayDiff = nowDay - birthDay; //日之差
                    if (dayDiff < 0) {
                        returnAge = ageDiff - 1;
                    } else {
                        returnAge = ageDiff;
                    }
                } else {
                    var monthDiff = nowMonth - birthMonth; //月之差
                    if (monthDiff < 0) {
                        returnAge = ageDiff - 1;
                    } else {
                        returnAge = ageDiff;
                    }
                }
            } else {
                returnAge = -1; //返回-1 表示出生日期输入错误 晚于今天
            }
        }
        return returnAge; // 返回周岁年龄
    }

    // 上传图片压缩
    function canvasDataURL(file, callback) { //压缩转化为base64
        var reader = new FileReader()
        reader.readAsDataURL(file)
        reader.onload = function(e) {
            const img = new Image()
            const quality = 0.8 // 图像质量
            const canvas = document.createElement('canvas')
            const drawer = canvas.getContext('2d')
            img.src = this.result
            img.onload = function() {
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

    var width = document.body.clientWidth * 0.8;

    var xhrOnProgress = function(fun) {
        xhrOnProgress.onprogress = fun; //绑定监听  
        //使用闭包实现监听绑  
        return function() {
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

    // 上传证件
    layui.use(['upload', 'element'], function() {
        var $ = layui.jquery,
            upload = layui.upload;
        var element = layui.element;
        var uploadInst = upload.render({
            elem: '#test1',
            url: baseUrl + '/api/attachment/add',
            headers: {
                'Authorization': sessionStorage.getItem('token')
            },
            size: 0,
            accept: 'file',
            acceptMime: 'image/*',
            auto: false,
            xhr: xhrOnProgress,
            progress: function(value) {
                element.progress('progressBar', value + '%');
            },
            before: function(obj) {
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
                obj.preview(function(index, file, result) {
                    $('#acatarIMG').attr('src', result); //图片链接（base64）
                });
            },
            choose: function(obj) { //选择文件后的回调
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
                canvasDataURL(file, function(blob) {
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
            },
            done: function(res) {
                if (res.code == 200) {
                    layer.msg(res.msg)
                    console.log(res.data[0].path);
                    console.log($("input[name='avatar']"));
                    $("input[name='avatar']").val(res.data[0].path);
                    var d = {};
                    var t = $('#headimg [name]').serializeArray();
                    $.each(t, function() {
                        d[this.name] = this.value;
                    });
                    let data = d;
                    $.ajax({
                        type: 'post',
                        url: baseUrl + "/api/member/edit",
                        data: data,
                        headers: {
                            'Authorization': sessionStorage.getItem('token'),
                        },
                        success(data) {
                            if (data.code == 200) {
                                layer.msg(data.msg)
                                location.reload()
                            } else {
                                layer.msg(data.msg)
                            }
                        }
                    })
                } else {
                    return layer.msg('アップロードエラー');
                }
            },
            error: function() {
                //演示失败状态，并实现重传
                var demoText1 = $('#positiveText');
                demoText1.html('<span style="color: #FF5722;">アップロードエラー</span> <a class="layui-btn layui-btn-now layui-btn-xs demo-reload1">再度アップロード</a>');
                demoText1.find('.demo-reload1').on('click', function() {
                    uploadPositive.upload();
                });
            }
        });
        //证件正面上传
        var uploadPositive = upload.render({
            elem: '#positive',
            url: baseUrl + '/api/attachment/add',
            headers: {
                'Authorization': sessionStorage.getItem('token')
            },
            size: 0,
            accept: 'file',
            acceptMime: 'image/*',
            auto: false,
            xhr: xhrOnProgress,
            progress: function(value) {
                element.progress('progressBar', value + '%');
            },
            before: function(obj) {

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
                obj.preview(function(index, file, result) {
                    $('#positiveImg').attr('src', result); //图片链接（base64）
                });
            },
            choose: function(obj) { //选择文件后的回调
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
                canvasDataURL(file, function(blob) {
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
            },
            done: function(res) {
                if (res.code == 200) {
                    layer.msg(res.msg)
                    $("input[name='positive']").val(res.data[0].path);
                } else {
                    return layer.msg('アップロードエラー');
                }
            },
            error: function() {
                //演示失败状态，并实现重传
                var demoText1 = $('#positiveText');
                demoText1.html('<span style="color: #FF5722;">アップロードエラー</span> <a class="layui-btn layui-btn-now layui-btn-xs demo-reload1">再度アップロード</a>');
                demoText1.find('.demo-reload1').on('click', function() {
                    uploadPositive.upload();
                });
            }
        });

        //证件反面上传
        var uploadOtherSide = upload.render({
            elem: '#otherSide',
            url: baseUrl + '/api/attachment/add',
            headers: {
                'Authorization': sessionStorage.getItem('token')
            },
            size: 0,
            accept: 'file',
            acceptMime: 'image/*',
            auto: false,
            xhr: xhrOnProgress,
            progress: function(value) {
                element.progress('progressBar', value + '%');
            },
            before: function(obj) {

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
                obj.preview(function(index, file, result) {
                    $('#otherSideImg').attr('src', result); //图片链接（base64）

                });
            },
            choose: function(obj) { //选择文件后的回调
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
                canvasDataURL(file, function(blob) {
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
            },
            done: function(res) {
                if (res.code == 200) {
                    layer.msg(res.msg)
                    $("input[name='other_side']").val(res.data[0].path);
                } else {
                    return layer.msg('アップロードエラー');
                }
            },
            error: function() {
                //演示失败状态，并实现重传
                var demoText2 = $('#otherSideText');
                demoText2.html('<span style="color: #FF5722;">アップロードエラー</span> <a class="layui-btn layui-btn-now layui-btn-xs demo-reload2">再度アップロード</a>');
                demoText2.find('.demo-reload2').on('click', function() {
                    uploadOtherSide.upload();
                });
            }
        });
    });
</script>
</div>
</div>
<div class="fly-footer">
    <p>古物営業許可第305502007435号 </p>
    <p><a href="http://www.beian.miit.gov.cn/" target="_blank">豫ICP备19030271号-1</a> <a href="/cms_index" target="_blank">the1sneaker</a></p>
</div>
</body>

</html>