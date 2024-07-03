<?php
// 总列数
$count = count($data['headers'][0] ?? []);
$keys = array_keys($data['headers'][0]);

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
<table width="auto">
    <thead>
        <tr>
            <th colspan="{{$count}}" align="center" style="height: 30px;font-size: 20px;">{{$data['title']}}</th>
        </tr>
        <tr class="title" height="30px">
            @foreach($data['headers'][0] as $key=>$item)
            <?php
            $tmp = explode('|', $item);
            $head = $tmp[0];
            if (is_array($item)) {
                $width = $item[1] ?? $width;
                $head = $item[0];
            } else {
                if (strpos($item, '编码') !== false) {
                    $width = '150px';
                } elseif (strpos($item, '时间') !== false) {
                    $width = '140px';
                } else {
                    $width = $widths[$key] ?? '80px';
                }
            }
            $style = 'text-align: center;';
            if (in_array('required', $tmp)) {
                $style = 'color: red;';
            }
            ?>
            <th width="{{$width}}" style="{{$style}}">{{$head}}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
    </tbody>
</table>