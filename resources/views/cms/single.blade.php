@include('cms.header')
<div id="problem">
</div>
<script>
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
    let search = window.location.search
    var searchObj = function searchObj(search) {
        return JSON.parse("{\"".concat(decodeURIComponent(search.substring(1)).replace(/"/g, '\\"').replace(/&/g, '","').replace(/=/g, '":"'), "\"}"));
    };
    let pramas = searchObj(search)

    $.ajax({
        type: 'get',
        url: baseUrl + '/api/article?catid=' + pramas.catid,
        success(data) {
            let agress = data.data[pramas.index]
            let shijian = msToDate(agress.createtime)
            agress.content = agress.content.replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&amp;/g, "&").replace(/&quot;/g, '"').replace(/&apos;/g, "'");
            let str =
                `<div class="single w3l-5">
                <div class="container">
                    <div class="single-left1 w3ls-5">
                        <h2 style='text-align:left'>${agress.title}</h2>
                        <ul>
                            <li><span style="color: #C5D0E1;" class="glyphicon glyphicon-calendar" aria-hidden="true"></span>${shijian.hasTime}</li>
                        </ul>
                        ${agress.content}
                    </div>
                </div>
            </div>`
            $('#problem').html(str)
        }
    })
</script>
@include('cms.footer')