<table>
    <thead>
        <tr>
            <th colspan="14" align="center" style="height: 30px;font-size: 20px;">质检单</th>
        </tr>
        <tr class="title" height="30px">
            <th width="80px" style="text-align: center;">仓库</th>
            <th width="80px" style="text-align: center;">单据类型</th>
            <th width="150px" style="text-align: center;">单据编号</th>
            <th width="80px" style="text-align: center;">单据状态</th>
            <th width="80px" style="text-align: center;">质检状态</th>
            <th width="80px" style="text-align: center;">质检方式</th>
            <th  style="text-align: center;">质检数量</th>
            <th width="80px" style="text-align: center;">疑似瑕疵数</th>
            <th  style="text-align: center;">正品数</th>
            <th  style="text-align: center;">瑕疵数</th>
            <th width="200px" style="text-align: center;">备注</th>
            <th width="100px" style="text-align: center;">质检人</th>
            <th width="140px" style="text-align: center;">质检开始时间</th>
            <th width="140px" style="text-align: center;">质检完成时间</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data as $item)
        <tr>
            <td>{{$item['warehouse_name']}}</td>
            <td>{{$item['type_txt']}}</td>
            <td>{{$item['qc_code']}}</td>
            <td>{{$item['status_txt']}}</td>
            <td>{{$item['qc_status_txt']}}</td>
            <td>{{$item['method_txt']}}</td>
            <td>{{$item['total_num']}}</td>
            <td>{{$item['probable_defect_num']}}</td>
            <td>{{$item['normal_num']}}</td>
            <td>{{$item['defect_num']}}</td>
            <td>{{$item['remark']}}</td>
            <td>{{$item['username']}}</td>
            <td>{{$item['created_at']}}</td>
            <td>{{$item['completed_at']}}</td>
        </tr>
        @endforeach
    </tbody>
</table>