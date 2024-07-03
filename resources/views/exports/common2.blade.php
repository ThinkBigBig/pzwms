<?php
// 总列数
$count = array_sum(array_column($data['headers'][0] ?? [],'colspan'));
$keys = [];
// 对应的列设置相应的宽度
$widths = [
    'source_code' => '150px',
    'code' => '150px',
    'origin_code' => '150px',
    'erp_no' => '150px',
    'company_code' => '150px',
    'company_name' => '150px',
    'third_no' => '150px',
    'request_code' => '150px',
    'bar_code' => '100px',
    'lot_num' => '100px',
    'name' => '200px',
    'remark' => '200px',
];
?>
<table>
    <thead>
        <tr>
            <th colspan="{{$count}}" align="center" valign="center" style="height: 30px;font-size: 20px;">{{$data['title']}}</th>
        </tr>

        @foreach($data['headers'] as $header)
        <tr>
            @foreach($header as $item)
            <?php
            $name = $item['label'];
            $key = $item['value'];
            $rowspan = $item['rowspan'] ?? 1;
            $colspan = $item['colspan'] ?? 1;
            $required = $item['required'] ?? false;
            if ($colspan == 1) $keys[] = $key;
            if (strpos($name, '编码') !== false) {
                $width = '150px';
            } elseif (strpos($name, '时间') !== false) {
                $width = '140px';
            } else {
                $width = $widths[$key] ?? '100px';
            }
            $style = 'height: 40px;text-align:center;vertical-align: middle;word-wrap:break-word;';
            if ($required) {
                $style .= 'color: red;';
            }
            ?>
            <th width="{{$width}}" align="center" valign="center" style="{{$style}}" rowspan="{{$rowspan}}" colspan="{{$colspan}}">{{$name}}</th>
            @endforeach
        </tr>
        @endforeach

    </thead>
    
    <tbody>
        @foreach($data['data'] as $item)
        <tr>
            @foreach($keys as $key)
            <td style="height: 30px;text-align: center;"><?php echo $item[$key] ?? ''; ?></td>
            @endforeach
        </tr>
        @endforeach
    </tbody>
</table>