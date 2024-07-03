<table>
    <thead>
        <tr>
            <th colspan="12" align="center" style="height: 30px;font-size: 20px;">质检单明细</th>
        </tr>
        <tr class="title" height="30px" style="text-align: center;">
            <th style="text-align: center;">供应商</th>
            <th width="80px" style="text-align: center;">货号</th>
            <th width="200px" style="text-align: center;">品名</th>
            <th style="text-align: center;">规格</th>
            <th width="80px" style="text-align: center;">质量类型</th>
            <th width="80px" style="text-align: center;">质量等级</th>
            <th width="100px" style="text-align: center;">批次号</th>
            <th width="100px" style="text-align: center;">唯一码</th>
            <th style="text-align: center;">数量</th>
            <th width="80px" style="text-align: center;">是否作废</th>
            <th style="text-align: center;">操作人</th>
            <th width="140px" style="text-align: center;">操作时间</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data as $item)
        <tr>
            <td>{{$item['merchant_name']}}</td>
            <td>{{$item['product_sn']}}</td>
            <td>{{$item['name']}}</td>
            <td>{{$item['spec_one']}}</td>
            <td>{{$item['quality_type_txt']}}</td>
            <td>{{$item['quality_level']}}</td>
            <td>{{$item['lot_num']}}</td>
            <td>{{$item['uniq_code']}}</td>
            <td>{{$item['num']}}</td>
            <td>{{$item['status']}}</td>
            <td>{{$item['admin_user']}}</td>
            <td>{{$item['created_at']}}</td>
        </tr>
        @endforeach
    </tbody>
</table>