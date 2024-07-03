<table>
    <thead>
        <tr>
            <th colspan="8" align="center" style="height: 30px;font-size: 20px;">物流产品</th>
        </tr>
        <tr class="title" height="30px">
            <th width="150px" style="text-align: center;">编码</th>
            <th width="200px" style="text-align: center;">产品名称</th>
            <th width="80px" style="text-align: center;">提货方式</th>
            <th width="150px" style="text-align: center;">物流公司</th>
            <th width="80px" style="text-align: center;">结算方式</th>
            <th width="80px" style="text-align: center;">是否启用</th>
            <th width="200px" style="text-align: center;">备注</th>
            <!-- <th width="200px">所属租户</th> -->
        </tr>
    </thead>
    <tbody>
        @foreach($data as $item)
        <tr>
            <td>{{$item['product_code']}}</td>
            <td>{{$item['product_name']}}</td>
            <td>{{$item['pickup_method_txt']}}</td>
            <td>{{$item['company_name']}}</td>
            <td>{{$item['payment_txt']}}</td>
            <td>{{$item['status_txt']}}</td>
            <td>{{$item['remark']}}</td>
            <!-- <td>{{$item['tender_name']}}</td> -->
        </tr>
        @endforeach
    </tbody>
</table>