@include('cms.header2')
</div>
<div class="contact w3l-3" id="problem">
    <div class="container">
        <h2 style="color: #C5D0E1;">よくある質問</h2>

        <div class="grid_3 grid_5">
            <div class="layui-tab" lay-filter="maintenanceOrder">
                <ul class="layui-tab-title">
                    <li class="layui-this">よくある質問</li>
                    <li>買取について</li>
                    <li>鑑定について</li>
                    <li>査定について</li>
                    <li>アカウントについて</li>
                </ul>
                <div class="layui-tab-content about">
                    <div class="layui-tab-item layui-show" id="contect">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    let arr = []
    var element = layui.element
    var index = 0
    $.ajax({
        type: 'get',
        url: baseUrl + '/api/article?catid=6&ascription=' + index,
        success(data) {
            arr = data.data
            let str = '<ul>'
            $.each(data.data, function (index, item) {
                str += `<li>
                        <div onclick="problem(${index})">${item.title}</div>
                        </li>`
            })
            str += '</ul>'
            $('#contect').html(str)
        }
    })
    element.on('tab(maintenanceOrder)', function (data) {
        $.ajax({
            type: 'get',
            url: baseUrl + '/api/article?catid=6&ascription=' + data.index,
            success(data) {
                arr = data.data
                let str = '<ul>'
                $.each(data.data, function (index, item) {
                    str += `<li>
                            <div onclick='problem(${index})'>${item.title}</div>
                        </li>`
                })
                str += '</ul>'
                $('#contect').html(str)
            }
        })
    });
    function msToDate(msec) {
        let datetime = new Date(msec);
        let year = datetime.getFullYear();
        let month = datetime.getMonth();
        let date = datetime.getDate();
        let hour = datetime.getHours();
        let minute = datetime.getMinutes();
        let second = datetime.getSeconds();

        let result1 = year +
            '-' +
            ((month + 1) >= 10 ? (month + 1) : '0' + (month + 1)) +
            '-' +
            ((date + 1) < 10 ? '0' + date : date) +
            ' ' +
            ((hour + 1) < 10 ? '0' + hour : hour) +
            ':' +
            ((minute + 1) < 10 ? '0' + minute : minute) +
            ':' +
            ((second + 1) < 10 ? '0' + second : second);

        let result2 = year +
            '-' +
            ((month + 1) >= 10 ? (month + 1) : '0' + (month + 1)) +
            '-' +
            ((date + 1) < 10 ? '0' + date : date);

        let result = {
            hasTime: result1,
            withoutTime: result2
        };

        return result;
    }
    function problem(index) {
        let problem = arr[index]
        let shijian = msToDate(problem.createtime)
        problem.content = problem.content.replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&amp;/g, "&").replace(/&quot;/g, '"').replace(/&apos;/g, "'");
        let str =
            `<div class="single w3l-5">
                <div class="container">
                    <div class="single-left1 w3ls-5">
                        <h2 style='text-align:left'>${problem.title}</h2>
                        <ul>
                            <li><span style="color: #C5D0E1;" class="glyphicon glyphicon-calendar" aria-hidden="true"></span>${shijian.hasTime}</li>
                        </ul>
                        ${problem.content}
                    </div>
                </div>
            </div>`
        $('#problem').html(str)
    }

</script>
@include('cms.footer')