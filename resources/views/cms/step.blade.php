@include('cms.header')
<div class="container">
    <div class="layui-tab layui-tab-brief" lay-filter="docDemoTabBrief">
        <ul class="layui-tab-title">
            <li class="layui-this">郵送買取</li>
            <li>店頭買取</li>
        </ul>
        <div class="layui-tab-content">
            <div class="layui-tab-item layui-show" id="content">
            </div>
            <div class="layui-tab-item" id="content2"></div>
        </div>
    </div>
</div>
<style type="text/css">
    .layui-tab-title li {
        background-color: rgba(230, 230, 230, 1);
        padding: 0 30px;
        margin-right: 10px;
        border-radius: 10px;
    }

    .layui-tab-title .layui-this {
        color: #fff;
        background-color: #000000;
        border-bottom: none;
    }

    .layui-tab-brief>.layui-tab-title .layui-this {
        color: #fff;
    }

    .layui-tab-brief>.layui-tab-more li.layui-this:after,
    .layui-tab-brief>.layui-tab-title .layui-this:after {
        border-bottom: none;
    }
</style>
<!--about-->
<script>
    $.ajax({
        type: 'get',
        url: baseUrl + '/api/article?catid=5&ascription=1',
        success(data) {
            $.each(data.data, function (index, item) {
                item.content = item.content.replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&amp;/g, "&").replace(/&quot;/g, '"').replace(/&apos;/g, "'");
            })
            let str = ''
            $.each(data.data, function (index, item) {
                str += item.content
            })
            $('#content').html(str)
        }
    })
    $.ajax({
        type: 'get',
        url: baseUrl + '/api/article?catid=5&ascription=2',
        success(data) {
            $.each(data.data, function (index, item) {
                item.content = item.content.replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&amp;/g, "&").replace(/&quot;/g, '"').replace(/&apos;/g, "'");
            })
            let str = ''
            $.each(data.data, function (index, item) {
                str += item.content
            })
            $('#content2').html(str)
        }
    })
</script>
<script>
    layui.use('element', function () {
        var $ = layui.jquery
            , element = layui.element; //Tab的切换功能，切换事件监听等，需要依赖element模块
    });
</script>
@include('cms.footer')