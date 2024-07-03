<table>
    <thead>
        <tr>
            <th colspan="24" align="center" style="height: 30px;font-size: 20px;">质检确认单</th>
        </tr>
        <tr class="title" height="30px">
            <th>序号</th>
            <th width="150px" style="text-align: center;">质检单据编码</th>
            <th width="150px" style="text-align: center;">源单单据编码</th>
            <th width="80px" style="text-align: center;">质检仓库</th>
            <th width="90px" style="text-align: center;">质检确认状态</th>
            <th width="80px" style="text-align: center;">质检图片</th>
            <th width="200px" style="text-align: center;">质检备注</th>
            <th width="80px" style="text-align: center;">商品唯一码</th>
            <th width="90px" style="text-align: center;">质检质量类型</th>
            <th width="80px" style="text-align: center;">质量等级</th>
            <th width="100px" style="text-align: center;">确认后质量类型</th>
            <th width="100px" style="text-align: center;">确认后质量等级</th>
            <th width="80px" style="text-align: center;">SKU编码</th>
            <th width="80px" style="text-align: center;">货号</th>
            <th width="150px" style="text-align: center;">品名</th>
            <th width="80px" style="text-align: center;">规格</th>
            <th width="90px" style="text-align: center;">质检单据类型</th>
            <th>质检库区</th>
            <th width="90px" style="text-align: center;">质检位置码</th>
            <th>质检人</th>
            <th width="140px" style="text-align: center;">质检时间</th>
            <th width="90px" style="text-align: center;">质检确认人</th>
            <th width="140px" style="text-align: center;">质检确认时间</th>
            <th width="200px" style="text-align: center;">质检确认备注</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data as $item)
        <tr>
            <td>{{$item['id']}}</td>
            <td>{{$item['qc_code']}}</td>
            <td>{{$item['arr_code']}}</td>
            <td>{{$item['warehouse_name']}}</td>
            <td>{{$item['status_txt']}}</td>
            <td>{{$item['pic_url']}}</td>
            <td>{{$item['notes']}}</td>
            <td>{{$item['uniq_code']}}</td>
            <td>{{$item['quality_type']}}</td>
            <td>{{$item['quality_level']}}</td>
            <td>{{$item['confirm_quality_type_txt']}}</td>
            <td>{{$item['confirm_quality_level']}}</td>
            <td>{{$item['sku']}}</td>
            <td>{{$item['product_sn']}}</td>
            <td>{{$item['name']}}</td>
            <td>{{$item['spec_one']}}</td>
            <td>{{$item['type_txt']}}</td>
            <td>{{$item['area_name']}}</td>
            <td>{{$item['location_code']}}</td>
            <td>{{$item['submitter']}}</td>
            <td>{{$item['submit_at']}}</td>
            <td>{{$item['comfirmor']}}</td>
            <td>{{$item['confirm_at']}}</td>
            <td>{{$item['confirm_remark']}}</td>
        </tr>
        @endforeach
    </tbody>
</table>