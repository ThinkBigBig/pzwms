<table>
    <thead>
        <tr>
            <th colspan="7" align="center" style="height: 30px;font-size: 20px;">物流产品</th>
        </tr>
        <tr class="title" height="30px">
            <th width="150px" style="text-align: center;">公司编码</th>
            <th width="200px" style="color: red;text-align: center;">公司名称</th>
            <th style="color: red;text-align: center;">简称</th>
            <th style="text-align: center;">联系人</th>
            <th width="100px" style="text-align: center;">手机号</th>
            <th width="200px" style="text-align: center;">地址</th>
            <th width="200px" style="text-align: center;">备注</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data as $item)
        <tr>
            <td>{{$item['company_code']}}</td>
            <td>{{$item['company_name']}}</td>
            <td>{{$item['short_name']}}</td>
            <td>{{$item['contact_name']}}</td>
            <td>{{$item['contact_phone']}}</td>
            <td>{{$item['address']}}</td>
            <td>{{$item['remark']}}</td>
        </tr>
        @endforeach
    </tbody>
</table>