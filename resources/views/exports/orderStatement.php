<table>
    <thead>
        <tr>
            <th colspan="19" align="center" style="height: 30px;font-size: 20px;">销售结算账单</th>
        </tr>
        <tr class="title">
            <th style="height: 50px;">序号</th>
            <th style="text-align: center;">结算状态</th>
            <th width="150px" style="text-align: center;">单据编码</th>
            <th width="150px" style="text-align: center;">业务单据编码</th>
            <th width="130px" style="text-align: center;">下单时间</th>
            <th style="text-align: center;">单据类型</th>
            <th width="130px" style="text-align: center;">收退款时间</th>
            <th width="150px" style="text-align: center;">电商单号</th>
            <th style="text-align: center;">店铺</th>
            <th style="text-align: center;">买家账号</th>
            <th style="text-align: center;">总金额</th>
            <th style="text-align: center;">应结金额</th>
            <th style="text-align: center;">结算金额</th>
            <th style="text-align: center;">结算人</th>
            <th style="text-align: center;">结算时间</th>
            <th style="text-align: center;">创建人</th>
            <th style="text-align: center;">创建时间</th>
            <th style="text-align: center;">最后更新人</th>
            <th width="130px" style="text-align: center;">最后更新时间</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($data as $item) : ?>
            <tr>
                <td><?php echo $item['id']; ?></td>
                <td><?php echo $item['status_txt']; ?></td>
                <td><?php echo $item['code']; ?></td>
                <td><?php echo $item['origin_code']; ?></td>
                <td><?php echo $item['order_at']; ?></td>
                <td><?php echo $item['type_txt']; ?></td>
                <td><?php echo $item['amount_time']; ?></td>
                <td><?php echo $item['third_no']; ?></td>
                <td><?php echo $item['shop_name']; ?></td>
                <td><?php echo $item['buyer_account']; ?></td>
                <td><?php echo $item['amount']; ?></td>
                <td><?php echo $item['settle_amount']; ?></td>
                <td><?php echo $item['settled_amount']; ?></td>
                <td><?php echo $item['settled_user']; ?></td>
                <td><?php echo $item['settled_time']; ?></td>
                <td><?php echo $item['create_user']; ?></td>
                <td><?php echo $item['created_at']; ?></td>
                <td><?php echo $item['admin_user']; ?></td>
                <td><?php echo $item['updated_at']; ?></td>
            </tr>

        <?php endforeach ?>
    </tbody>
</table>