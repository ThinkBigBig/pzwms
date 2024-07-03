<?php

namespace App\Logics\channel;

interface ThirdInterface
{

    /**
     * 将出价金额转成渠道对应的币种
     *
     * @param array $params
     */
    public function bidPriceHandle($params, $lowest);

    /**
     * 根据货号获取商品sku原始信息
     *
     * @param string $product_sn
     */
    public function getProductInfo($product_sn);


    //格式化输出商品信息，包括所有规格
    public function productDetailFormat($data);

    /**
     * 新增出价
     *
     * @param string $sku_id
     * @param int $price
     * @param int $qty
     * @param int $type
     * @param array $params
     */
    public function bid($sku_id, $price, $qty, $type, $params = []);

    /**
     * 取消出价
     *
     * @param ChannelBidding $bidding_no 出价编号
     * @param int $type 业务类型，当前只有跨境出价
     */
    public function bidCancel($bidding_no, $type);


    /**
     * 获取订单详情并格式化输出内容
     *
     * @param [string] $order_no 订单编号
     * @return void
     */
    public function getOrderDetail($order_no);

    /**
     * 获取平台最低价
     *
     * @param [string] $sku_id
     */
    function getLowestPrice($sku_id);

    /**
     * 同步商品最低价
     *
     * @param array $params
     */
    public function syncLowestPrice($params);
}
