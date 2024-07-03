function login() {
    let params = {
        username: null,
        password: null,
        captcha: null,
        key: null
    }
    params.username = $('#username').val()
    params.password = $('#password').val()
    params.captcha = $('#captcha').val()
    params.key = sessionStorage.getItem('key')
    $.post(baseUrl + '/api/login', params, function (data) {
        if (data.code == 200) {
            sessionStorage.setItem('token', data.data.token.token_type + ' ' + data.data.token.token)
            window.location.href = '/cms_index'
        } else {
            console.log(data);
            layer.msg(data.msg)
            getagain()
        }
    })
}
function getagain() {
    $.ajax({
        url: baseUrl+"/api/slide",
        type: 'get',
        data: {},
        headers: {
            'Authorization': sessionStorage.getItem('token'),
        },
        success: function (res) {
            console.log(res);
        }
    })
    $.get(baseUrl + "/api/codeImg", {}, function (data) {
        $('#verify').attr('src', data.data.code.img)
        let key = data.data.code.key
        sessionStorage.setItem('key', key)
    }, "json")
}

layui.use('form', function () {
    var form = layui.form;
    // 表单验证
    form.verify({
        jp_mobile: function (value, item) {
            if (!new RegExp("^(070|080|090)[0-9]{8}$").test(value)) {
                return '正しい携帯電話番号を入力ください!';
            }
        }
    });

    //监听提交
    form.on('submit(formDemo)', function (data) {
        return false;
    });
});
getagain()