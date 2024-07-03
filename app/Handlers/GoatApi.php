<?php

/**
 * GOAT 基础类
 */

namespace App\Handlers;

use App\Logics\BaseLogic;
use App\Logics\Robot;
use App\Models\ErpLog;
use Psy\Util\Json;

class GoatApi
{

    protected $host = '';
    public function __construct($host = '')
    {
        $this->host = $host ?: env('GOAT_HOST', '');
    }

    /**
     * 获取sku信息
     *
     * @param [string] $sku
     */
    public function skuInfo($params)
    {
        $data['sku'] = $params['sku'];
        return $this->request('get', '/api/v1/product_templates/search', $data);
    }

    /**
     * 获取sku最低出售价
     *
     * @param [int] $product_template_id
     * @param string $country_code
     */
    public function getLowestPrice($product_template_id, $country_code = 'US')
    {
        $query = [
            'countryCode' => $country_code,
            'productTemplateId' => $product_template_id
        ];
        return $this->request('get', '/api/v1/product_variants/buy_bar_data', $query);
    }

    /**
     * 创建新商品
     *
     * @param int $qty
     * @param int $pt_id
     * @param string $size
     * @param int $price
     * @param string $ext_tag
     */
    public function createProduct($qty, $pt_id, $size, $price, $ext_tag)
    {
        $params = [
            'quantity' => $qty,
            'product' => [
                'productTemplateId' => $pt_id,
                'size' => $size,
                'shoeCondition' => 'new',
                'boxCondition' => 'good_condition',
                'priceCents' => $price,
                'extTag' => $ext_tag
            ]
        ];
        return $this->request('post', '/api/v1/products/create_multiple', $params);
    }

    /**
     * 查询商品信息
     *
     * @param [array] $params
     */
    public function productSearch($params)
    {
        $data = [
            // 'saleStatus' => 'active',
            'pageSize' => $params['pageSize'] ?? 100,
            // 'withDefects' => true,//查询有瑕疵的商品信息
        ];
        if ($params['sku'] ?? '') $data['sku'] = $params['sku'];
        if ($params['size'] ?? '') $data['size'] = $params['size'];
        if ($params['pt_id'] ?? '') $data['productTemplateId'] = $params['pt_id'];
        if ($params['ext_tag'] ?? '') $data['extTag'] = $params['ext_tag'];
        return $this->request('get', '/api/v1/partners/products/search', $data);
    }

    /**
     * 批量更新商品价格
     *
     * @param array $params
     */
    public function updateProductPrice($params)
    {
        $data = [
            'products' => $params,
        ];
        return $this->request('post', '/api/v1/partners/products/update_multiple', $data);
    }

    /**
     * 取消指定商品
     *
     * @param int $product_id
     */
    public function cancelProduct($product_id)
    {
        return $this->request('post', '/api/v1/products/' . $product_id . '/cancel', []);
    }

    /**
     * 批量下架商品
     *
     * @param array $ids
     */
    function deactivateProducts($ids)
    {
        $data = [
            'products' => $ids,
        ];
        return $this->request('post', '/api/v1/partners/products/deactivate_multiple', $data);
    }


    /**
     * 获取指定商品信息
     *
     * @param int $product_id
     */
    public function productInfo($product_id)
    {
        return $this->request('get', '/api/v1/products/' . $product_id, []);
    }

    /**
     * 获取尺码转换信息
     *
     */
    public function getSizeConversion()
    {
        $data = [
            'to_unit' => 'eu',
            'to_gender' => 'men',
            'adidas_original' => true,
        ];
        return $this->request('post', '/api/v1/size_conversions', $data);
    }

    /**
     * 全量获取订单信息
     *
     * @param array $params
     */
    public function getOrders($params)
    {
        /**
         * filter
         *  sell - 所有售出的订单信息 
         *  need_to_confirm - 需要确认的订单信息，订单状态包含 sold, goat_review, goat_review_risk_rejected, goat_review_skipped
         *  need_to_ship - 需要寄出的订单信息，订单状态包含 seller_confirmed, seller_packaging
         *  sell_with_issue - 所有正处于 goat_issue 状态的订单
         */
        $data = [
            'page' => $params['page'] ?? 1,
            'filter' => $params['status'] ?? 'sell', //
            'sortBy' => 'update_time'
        ];
        return $this->request('get', "/api/v1/orders", $data);
    }

    /**
     * 根据订单号获取商品信息
     *
     * @param string $order_no
     */
    public function getOrderInfo($order_no)
    {
        return $this->request('get', '/api/v1/orders/' . $order_no, []);
    }

    /**
     * 更新订单状态
     *
     * @param string $order_no
     * @param string $status
     */
    public function updateOrderStatus($order_no, $status)
    {
        // 卖家确认 (seller_confirm)
        // 卖家打包 (seller_packaging)
        // 订单已交予快递 (with_courier)
        // 卖家取消订单 (seller_cancel_review)

        $params = ['order' => ['statusAction' => $status]];
        if ($status == 'seller_packaging') {
            $params['order']['carrier'] = "drop_off";
        }

        return $this->request('put', '/api/v1/orders/' . $order_no . '/update_status', $params);
    }

    public $messages = [];
    private function request($method, $url, $params, $option = true)
    {
        $url = $this->host . $url;
        $options = [];
        if ($option) {
            $options = ['header' => [
                sprintf('Authorization: Token token="%s"', env('GOAT_TOKEN', '')),
                'Content-Type: application/json'
            ]];
        }

        if ($method == 'get') {
            $options['query'] = $params;
            $res = HttpService::request('get', $url, $options);
        }
        if ($method == 'post') {
            $options['data'] = $params;
            $res = HttpService::request('post', $url, $options);
        }
        if ($method == 'put') {
            $options['data'] = $params;
            $res = HttpService::request('put', $url, $options);
        }
        $res = $res ? json_decode($res, true) : [];
        
        BaseLogic::requestLog('【GOAT】', ['url' => $url, 'params' => $params, 'data' => $res]);
        $params['channel_code'] = 'GOAT';
        $success = $res && ($res['success'] ?? true);
        if (!$success) {
            Robot::sendApiRes($res, $url, $params);
        }
        $this->messages = $res['messages'] ?? '';
        return $res;
    }
}
