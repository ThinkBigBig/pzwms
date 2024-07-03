<?php
    $style = "text-align: center;";
?>
<table>
    <thead>
        <tr>
            <th colspan="24" align="center" style="height: 30px;font-size: 20px;">销售订单</th>
        </tr>
        <tr class="title" height="30px">
            <th style="{{$style}}">序号</th>
            <th style="{{$style}}">标记</th>
            <th style="{{$style}}">卖家留言</th>
            <th width="130px" style="{{$style}}">下单时间</th>
            <th width="150px" style="{{$style}}">电商单号</th>
            <th style="{{$style}}">单据状态</th>
            <th width="150px" style="{{$style}}">单据编码</th>
            <th style="{{$style}}">发货状态</th>
            <th style="{{$style}}">付款状态</th>
            <th style="{{$style}}">商品数量</th>
            <th style="{{$style}}">订单总额</th>
            <th style="{{$style}}">实际支付总额</th>
            <th style="{{$style}}">优惠总额</th>
            <th style="{{$style}}">买家账号</th>
            <th style="{{$style}}">买家留言</th>
            <th style="{{$style}}">来源平台</th>
            <th style="{{$style}}">店铺</th>
            <th style="{{$style}}">仓库</th>
            <th style="{{$style}}">单据来源</th>
            <th style="{{$style}}">物流</th>
            <th width="100px" style="{{$style}}">物流单号</th>
            <th style="{{$style}}">备注</th>
            <th style="{{$style}}">创建人</th>
            <th width="130px" style="{{$style}}">创建时间</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data as $item)
        <tr>
            <td>{{$item['id']}}</td>
            <td>{{$item['tag_txt']}}</td>
            <td>{{$item['seller_message']}}</td>
            <td>{{$item['order_at']}}</td>
            <td>{{(string)$item['third_no']}}</td>
            <td>{{$item['status_txt']}}</td>
            <td>{{$item['code']}}</td>
            <td>{{$item['deliver_status_txt']}}</td>
            <td>{{$item['payment_status_txt']}}</td>
            <td>{{$item['num']}}</td>
            <td>{{$item['total_amount']}}</td>
            <td>{{$item['payment_amount']}}</td>
            <td>{{$item['discount_amount']}}</td>
            <td>{{$item['buyer_account']}}</td>
            <td>{{$item['buyer_message']}}</td>
            <td>{{$item['order_platform_txt']}}</td>
            <td>{{$item['shop_name']}}</td>
            <td>{{$item['warehouse_name']}}</td>
            <td>{{$item['source_type_txt']}}</td>
            <td>{{$item['product_name']}}</td>
            <td>{{$item['deliver_no']}}</td>
            <td>{{$item['remark']}}</td>
            <td>{{$item['create_user']}}</td>
            <td>{{$item['created_at']}}</td>
        </tr>
        @endforeach
    </tbody>
</table>