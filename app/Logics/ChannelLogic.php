<?php

namespace App\Logics;

use App\Handlers\GoatApi;
use App\Handlers\StockxApi;
use App\Logics\bid\BidQueue;
use App\Logics\bid\Excute;
use App\Logics\channel\DW;
use App\Logics\channel\GOAT;
use App\Logics\channel\STOCKX;
use App\Logics\mock\stockxMock;
use App\Models\CarryMeBiddingItem;
use App\Models\ChannelBidding;
use App\Models\ChannelBiddingItem;
use App\Models\ChannelBiddingLog;
use App\Models\ChannelCallbackLog;
use App\Models\ChannelOrder;
use App\Models\StockxBatch;
use App\Models\StockxBidding;
use App\Models\StockxProduct;
use App\Models\StockxProductVariant;
use Exception;
use Illuminate\Support\Facades\DB;

use function PHPUnit\Framework\returnSelf;

class ChannelLogic
{
    private static $obj = null;
    static $code = '';

    public function __construct($code)
    {
        $code = strtoupper($code);
        $name = 'App\Logics\channel\\' . $code;
        self::$obj = new $name();
        self::$code = $code;
        return self::$obj;
    }

    public function __call($method, $params)
    {
        return self::$obj->$method(...$params);
    }

    //回调事件
    const CALLBACK_EVENT_ORDER_CREATED = 1; //订单创建
    const CALLBACK_EVENT_ORDER_REFUND = 2; //订单取消
    const CALLBACK_EVENT_BIDDING_CLOSE = 3; //出价关闭

    //回调处理
    public function callbackHandle($data): bool
    {
        $res = self::$obj->callbackFormat($data);
        $origin = $res['origin'];
        $origin['channel_code'] = self::$code;
        $callback = ChannelCallbackLog::create($origin);

        //订单创建
        if ($res['event'] == self::CALLBACK_EVENT_ORDER_CREATED) {
            if ($res['order_detail']) {
                //跨境
                if ($res['order_detail']['order_type'] == 3) {
                    //创建订单
                    OrderLogic::orderCreated($res['order_detail'], self::$code);
                }
            }
        }

        //订单取消
        if ($res['event'] == self::CALLBACK_EVENT_ORDER_REFUND) {
            if ($res['order_detail'] && $res['order_detail']['order_type'] == 3) {
                // 检查订单是否创建，未创建就先创建再取消
                $order = ChannelOrder::where(['channel_code' => self::$code, 'order_no' => $res['order_detail']['order_no']])->first();
                if (!$order) {
                    $order = OrderLogic::orderCreated($res['order_detail'], self::$code);
                }
                OrderLogic::orderRefund($order->channel_code, $res['refund']);
            }
        }

        //出价关闭
        if ($res['event'] == self::CALLBACK_EVENT_BIDDING_CLOSE) {

            if ($res['order_detail'] && $res['order_detail']['order_type'] == 3) {
                Robot::sendNotice(sprintf('DW出价关闭，出价单号%s', $res['bidding']['bidding_no']));

                $bidding = ChannelBidding::where(['bidding_no' => $res['bidding']['bidding_no'], 'channel_code' => 'DW'])->first();
                if ($bidding && $bidding->status == ChannelBidding::BID_SUCCESS) {
                    if ($bidding->source_format == 'app') {
                        // BidQueue::appSingleChanelCancel([
                        //     'bidding_no' => $res['bidding']['bidding_no'],
                        //     'remark' => '得物出价关闭',
                        //     'product_sn' => $bidding->product_sn,
                        // ]);
                        Excute::appCancel([
                            'bidding_no' => $res['bidding']['bidding_no'],
                            'remark' => '得物出价关闭',
                            'product_sn' => $bidding->product_sn,
                        ]);
                    }
                    if ($bidding->source_format == 'stock') {
                        // BidQueue::stockSingleChannelCancel([
                        //     'stock_bidding_item_id' => $bidding->stock_bidding_item_id,
                        //     'remark' => '得物出价关闭',
                        //     'product_sn' => $bidding->product_sn,
                        // ]);
                        Excute::stockCancel([
                            'stock_bidding_item_id' => $bidding->stock_bidding_item_id,
                            'remark' => '得物出价关闭',
                            'product_sn' => $bidding->product_sn,
                        ]);
                    }
                }
            }
        }

        $callback->update(['status' => ChannelCallbackLog::STATUS_DONE,]);
        return true;
    }

    public static function callbackHandleTest($data): bool
    {
        if (env('ENV_NAME', 'dev') == 'prod') return true;

        $res = self::$obj->callbackFormatTest($data);
        $origin = $res['origin'];
        if ($data['refund_order_no'] ?? '') {
            $res['refund']['order_no'] = $data['refund_order_no'];
        }

        $origin['channel_code'] = self::$code;
        $callback = ChannelCallbackLog::create($origin);

        if ($data['channel_bidding_id'] ?? '') {
            $res['order_detail']['bidding_no'] = ChannelBidding::where(['id' => $data['channel_bidding_id']])->orderby('id', 'desc')->value('bidding_no');
        }
        if ($data['price'] ?? '') {
            $res['order_detail']['price_jpy'] = $data['price'];
        }
        if ($data['sellerBiddingNo'] ?? '') {
            $res['bidding']['bidding_no'] = $data['sellerBiddingNo'];
        }

        switch ($res['event']) {
            case self::CALLBACK_EVENT_ORDER_CREATED:
                if ($res['order_detail']) {
                    $res['order_detail']['paysuccess_time'] = date('Y-m-d H:i:s');
                    OrderLogic::orderCreated($res['order_detail'], self::$code);
                }
                break;
            case self::CALLBACK_EVENT_ORDER_REFUND:
                $order = ChannelOrder::where(['channel_code' => self::$code, 'order_no' => $res['order_detail']['order_no']])->first();

                if (!$order) {
                    OrderLogic::orderCreated($res['order_detail'], self::$code);
                }
                OrderLogic::orderRefund($order->channel_code, $res['refund']);
                break;
            case self::CALLBACK_EVENT_BIDDING_CLOSE:
                Robot::sendNotice(sprintf('DW出价关闭，出价单号%s', $res['bidding']['bidding_no']));
                $bidding = ChannelBidding::where(['bidding_no' => $res['bidding']['bidding_no'], 'channel_code' => 'DW'])->first();
                if ($bidding && $bidding->status == ChannelBidding::BID_SUCCESS) {
                    if ($bidding->source_format == 'app') {
                        // BidQueue::appSingleChanelCancel([
                        //     'bidding_no' => $res['bidding']['bidding_no'],
                        //     'remark' => '得物出价关闭',
                        //     'product_sn' => $bidding->product_sn,
                        // ]);
                        Excute::appCancel([
                            'bidding_no' => $res['bidding']['bidding_no'],
                            'remark' => '得物出价关闭',
                            'product_sn' => $bidding->product_sn,
                        ]);
                    }
                    if ($bidding->source_format == 'stock') {
                        // BidQueue::stockSingleChannelCancel([
                        //     'stock_bidding_item_id' => $bidding->stock_bidding_item_id,
                        //     'remark' => '得物出价关闭',
                        //     'product_sn' => $bidding->product_sn,
                        // ]);
                        Excute::stockCancel([
                            'stock_bidding_item_id' => $bidding->stock_bidding_item_id,
                            'remark' => '得物出价关闭',
                            'product_sn' => $bidding->product_sn,
                        ]);
                    }
                }
                break;
        }

        if ($data['type'] == 'order-completion') {
            $order = ChannelOrder::where(['order_no' => $data['order_no']])->first();
            $logic = new OrderLogic();
            $logic->orderSync(['order' => $order]);
        }


        $callback->update([
            'status' => ChannelCallbackLog::STATUS_DONE,
        ]);

        return true;
    }

    //模拟生成GOAT订单
    public function gaotOrderTest($params)
    {
        if (env('ENV_NAME', 'dev') == 'prod') return true;
        $data = '{
            "metadata": {
                "totalPages": 1,
                "currentPage": 1,
                "nextPage": 2,
                "pageItemCount": 25,
                "itemsPerPageCount": 25,
                "totalCount": 17227
            },
            "orders": [{
                "id": 424376694,
                "productId": 256219656,
                "number": 312420574,
                "purchaseOrderNumber": 312420574,
                "shippingServiceLevel": "standard",
                "status": "sold",
                "updatedAt": "2023-03-20T07:27:54.190Z",
                "addVerification": true,
                "verificationFeeCents": 0,
                "additionalTaxCents": 0,
                "localizedShippingCents": {
                    "currency": "USD",
                    "amount": 0,
                    "amountUsdCents": 0
                },
                "shippingCents": 0,
                "localizedAvailableCreditCents": {
                    "currency": "USD",
                    "amount": 0,
                    "amountUsdCents": 0
                },
                "localizedCreditCents": {
                    "currency": "USD"
                },
                "localizedCreditsToUseCents": {
                    "currency": "USD",
                    "amount": 0,
                    "amountUsdCents": 0
                },
                "creditsToUseCents": 0,
                "localizedCreditsUsedCents": {
                    "currency": "USD",
                    "amount": 0,
                    "amountUsdCents": 0
                },
                "creditsUsedCents": 0,
                "localizedSellerAmountMadeCents": {
                    "currency": "USD",
                    "amount": 10993,
                    "amountUsdCents": 10993
                },
                "sellerAmountMadeCents": 10993,
                "localizedPriceCents": {
                    "currency": "USD",
                    "amount": 12700,
                    "amountUsdCents": 12700
                },
                "priceCents": 12700,
                "localizedListPriceCents": {
                    "currency": "USD",
                    "amount": null,
                    "amount_usd_cents": null,
                    "amount_in_subunits": null
                },
                "localizedPromoCodeValueCents": {
                    "currency": "USD",
                    "amount": 0,
                    "amountUsdCents": 0
                },
                "promoCodeValueCents": 0,
                "localizedFinalPriceCents": {
                    "currency": "USD",
                    "amount": 0,
                    "amountUsdCents": 0
                },
                "finalPriceCents": 0,
                "localizedInstantShipMarkupFeeCents": {
                    "currency": "USD",
                    "amount": 0,
                    "amountUsdCents": 0
                },
                "instantShipMarkupFeeCents": 0,
                "localizedTaxCents": {
                    "currency": "USD"
                },
                "localizedProcessingFeeCents": {
                    "currency": "USD",
                    "amount": 0,
                    "amountUsdCents": 0
                },
                "userId": 0,
                "addressId": 0,
                "billingInfoId": 0,
                "wantId": 0,
                "shippingServiceLevelOptions": [],
                "purchasedAt": "2023-03-16T06:03:23.787Z",
                "promoCodeLedgerId": 0,
                "progressSequence": "ProgressSequenceForSeller",
                "trackingToGoatCode": "0a41bcce6f2753be",
                "trackingToGoatCodeUrl": "https://sneakers-production-shippinglabelbucket-bucket-1cqhkx9lppzpb.s3.amazonaws.com/shipping_label/2023-03-17/31af6eba-a398-4d66-9f1e-a1522fbaf5d3.pdf",
                "carrierToGoat": "drop_off",
                "shippingLabelToGoat": "https://sneakers-production-shippinglabelbucket-bucket-1cqhkx9lppzpb.s3.amazonaws.com/shipping_label/2023-03-17/31af6eba-a398-4d66-9f1e-a1522fbaf5d3.pdf",
                "endState": false,
                "product": {
                    "id": 256219656,
                    "slug": "wmns-dunk-low-black-white-24aa3618-b41f-4c8f-b1dd-6acd5952ffd2",
                    "createdAt": "2023-03-13T08:18:24.175Z",
                    "priceCents": 12700,
                    "size": 6.5,
                    "condition": 1,
                    "shoeCondition": "new",
                    "boxCondition": "good_condition",
                    "hasTears": false,
                    "hasOdor": false,
                    "hasDiscoloration": false,
                    "hasDefects": false,
                    "hasMissingInsoles": false,
                    "hasScuffs": false,
                    "quantity": 1,
                    "message": "You no longer have the lowest price for the size 6.5 Wmns Dunk Low \'Black White\'.",
                    "messageRead": false,
                    "sizeOption": {
                        "presentation": "6.5",
                        "value": 6.5
                    },
                    "variantShoeCondition": "new_no_defects",
                    "localizedPriceCents": {
                        "currency": "USD",
                        "amount": 12700,
                        "amountUsdCents": 12700
                    },
                    "saleStatus": "completed",
                    "canReturn": true,
                    "status": "sale",
                    "consigned": false,
                    "mainPictureUrl": "https://image.goat.com/750/attachments/product_template_pictures/images/071/445/292/original/722965_00.png.png",
                    "shortUrl": "https://www.goat.com/products/wmns-dunk-low-black-white-24aa3618-b41f-4c8f-b1dd-6acd5952ffd2",
                    "localizedProductHighestOfferCents": {
                        "currency": "USD"
                    },
                    "productImages": [],
                    "lowestPriceCents": 0,
                    "previousLowestPriceCents": 0,
                    "localizedLowestPriceCents": {
                        "currency": "USD"
                    },
                    "localizedPreviousLowestPriceCents": {
                        "currency": "USD"
                    },
                    "highestOfferCents": 0,
                    "previousHighestOfferCents": 0,
                    "localizedHighestOfferCents": {
                        "currency": "USD",
                        "amount": 0,
                        "amountUsdCents": 0
                    },
                    "localizedPreviousHighestOfferCents": {
                        "currency": "USD",
                        "amount": 0,
                        "amountUsdCents": 0
                    },
                    "lastSoldPriceCents": 0,
                    "previousSoldPriceCents": 0,
                    "localizedLastSoldPriceCents": {
                        "currency": "USD",
                        "amount": 0,
                        "amountUsdCents": 0
                    },
                    "localizedPreviousSoldPriceCents": {
                        "currency": "USD",
                        "amount": 0,
                        "amountUsdCents": 0
                    },
                    "productTemplate": {
                        "brandName": "Nike",
                        "careInstructions": "",
                        "color": "Black",
                        "composition": "",
                        "designer": "Peter Moore",
                        "details": "White/Black/White",
                        "fit": "",
                        "gender": [
                            "women"
                        ],
                        "id": 722965,
                        "maximumOfferCents": 200000,
                        "midsole": "",
                        "minimumOfferCents": 2500,
                        "modelSizing": "",
                        "name": "Wmns Dunk Low \'Black White\'",
                        "nickname": "Black White",
                        "productCategory": "shoes",
                        "productType": "sneakers",
                        "releaseDate": "2021-03-10T23:59:59.999Z",
                        "silhouette": "Dunk",
                        "sizeBrand": "nike",
                        "sizeRange": [
                            4.0,
                            4.5,
                            5.0,
                            5.5,
                            6.0,
                            6.5,
                            7.0,
                            7.5,
                            8.0,
                            8.5,
                            9.0,
                            9.5,
                            10.0,
                            10.5,
                            11.0,
                            11.5,
                            12.0,
                            12.5,
                            13.0,
                            13.5,
                            14.0,
                            14.5,
                            15.0,
                            15.5,
                            16.0,
                            16.5,
                            17.0,
                            17.5
                        ],
                        "sizeType": "numeric_sizes",
                        "sizeUnit": "us",
                        "sku": "DD1503 101",
                        "slug": "wmns-dunk-low-black-white-dd1503-101",
                        "specialDisplayPriceCents": 10000,
                        "specialType": "standard",
                        "status": "active",
                        "upperMaterial": "Leather",
                        "availableSizesNew": [],
                        "availableSizesNewV2": [],
                        "availableSizesNewWithDefects": [],
                        "availableSizesUsed": [],
                        "productTaxonomy": [],
                        "lowestPriceCents": 0,
                        "usedLowestPriceCents": 0,
                        "newLowestPriceCents": 0,
                        "localizedSpecialDisplayPriceCents": {
                            "currency": "USD",
                            "amount": 10000,
                            "amountUsdCents": 10000
                        },
                        "category": [
                            "Lifestyle"
                        ],
                        "micropostsCount": 0,
                        "sellingCount": 0,
                        "usedForSaleCount": 0,
                        "withDefectForSaleCount": 0,
                        "isWantable": true,
                        "isOwnable": true,
                        "isResellable": true,
                        "isFashionProduct": false,
                        "isRaffleProduct": false,
                        "singleGender": "women",
                        "storyHtml": "<p>The Nike women’s Dunk Low ‘Black White’ also known as ‘Panda’ highlights classic color blocking on a vintage silhouette originally released in 1985. The all-leather upper features a crisp white base with contrasting black overlays and a matching black Swoosh. Nike branding lands on the heel tab and woven tongue tag in keeping with the sneaker’s OG aesthetic. The low-top is supported by a durable rubber cupsole, equipped underfoot with a basketball-specific traction pattern.</p>\n",
                        "pictureUrl": "https://image.goat.com/1000/attachments/product_template_pictures/images/071/445/292/original/722965_00.png.png",
                        "mainGlowPictureUrl": "https://image.goat.com/glow-4-5-25/750/attachments/product_template_pictures/images/071/445/292/original/722965_00.png.png",
                        "mainPictureUrl": "https://image.goat.com/750/attachments/product_template_pictures/images/071/445/292/original/722965_00.png.png",
                        "gridGlowPictureUrl": "https://image.goat.com/glow-4-5-25/375/attachments/product_template_pictures/images/071/445/292/original/722965_00.png.png",
                        "gridPictureUrl": "https://image.goat.com/375/attachments/product_template_pictures/images/071/445/292/original/722965_00.png.png",
                        "sizeOptions": [{
                                "presentation": "4",
                                "value": 4.0
                            },
                            {
                                "presentation": "4.5",
                                "value": 4.5
                            },
                            {
                                "presentation": "5",
                                "value": 5.0
                            },
                            {
                                "presentation": "5.5",
                                "value": 5.5
                            },
                            {
                                "presentation": "6",
                                "value": 6.0
                            },
                            {
                                "presentation": "6.5",
                                "value": 6.5
                            },
                            {
                                "presentation": "7",
                                "value": 7.0
                            },
                            {
                                "presentation": "7.5",
                                "value": 7.5
                            },
                            {
                                "presentation": "8",
                                "value": 8.0
                            },
                            {
                                "presentation": "8.5",
                                "value": 8.5
                            },
                            {
                                "presentation": "9",
                                "value": 9.0
                            },
                            {
                                "presentation": "9.5",
                                "value": 9.5
                            },
                            {
                                "presentation": "10",
                                "value": 10.0
                            },
                            {
                                "presentation": "10.5",
                                "value": 10.5
                            },
                            {
                                "presentation": "11",
                                "value": 11.0
                            },
                            {
                                "presentation": "11.5",
                                "value": 11.5
                            },
                            {
                                "presentation": "12",
                                "value": 12.0
                            },
                            {
                                "presentation": "12.5",
                                "value": 12.5
                            },
                            {
                                "presentation": "13",
                                "value": 13.0
                            },
                            {
                                "presentation": "13.5",
                                "value": 13.5
                            },
                            {
                                "presentation": "14",
                                "value": 14.0
                            },
                            {
                                "presentation": "14.5",
                                "value": 14.5
                            },
                            {
                                "presentation": "15",
                                "value": 15.0
                            },
                            {
                                "presentation": "15.5",
                                "value": 15.5
                            },
                            {
                                "presentation": "16",
                                "value": 16.0
                            },
                            {
                                "presentation": "16.5",
                                "value": 16.5
                            },
                            {
                                "presentation": "17",
                                "value": 17.0
                            },
                            {
                                "presentation": "17.5",
                                "value": 17.5
                            }
                        ],
                        "userWant": false,
                        "userOwn": false
                    },
                    "instantShippable": false,
                    "user": {
                        "id": 49258347,
                        "slug": "fitgoat",
                        "username": "fitgoat",
                        "ownCount": 0,
                        "wantCount": 0,
                        "sellingCount": 0
                    },
                    "cjId": "722965_6_5"
                },
                "sellerTitle": "Authenticated",
                "sellerDescription": "Your sneakers were authenticated by our specialist and will be shipped to the buyer shortly. You now have $109.93 available to cash out.",
                "sellerActions": [],
                "buyerTitle": "",
                "buyerDescription": "",
                "buyerActions": [],
                "buyerIssuePictures": [],
                "buyerDiscountPercentage": 0,
                "buyerDiscountAmountFormatted": "",
                "finalSale": false
            }]
        }';

        $data = json_decode($data, true);
        $ord = $data['orders'][0];
        $ord['updatedAt'] = date('Y-m-d H:i:s');
        $ord['purchasedAt'] = gmdate('Y-m-d\TH:i:s');
        $data['orders'] = [];
        //所有有效出价单，模拟一个下单
        $where = ['channel_code' => 'GOAT', 'id' => $params['channel_bidding_id']];
        $biddings = ChannelBidding::where($where)->get();
        $ord['status'] = $params['status'];
        foreach ($biddings as $bidding) {
            $items = $bidding->channelBiddingItem;
            foreach ($items as $item) {
                $ord['priceCents'] = $params['price'];
                $ord['product']['priceCents'] = $params['price'];
                $order = ChannelOrder::where('channel_bidding_item_id', $item->id)->first();
                if (in_array($ord['status'], ['sold', 'goat_review_skipped'])) {
                    $ord['id'] = ChannelOrder::where(['channel_code' => 'GOAT'])->max('tripartite_order_id') + 1;
                } else {
                    $tripartite_order_id = $order ? $order->tripartite_order_id : ChannelOrder::where(['channel_code' => 'GOAT'])->max('tripartite_order_id') + 1;
                    $ord['id'] = $tripartite_order_id;
                }
                $ord['product']['id'] = $item->product_id;
                $ord['product']['productTemplate']['id'] = $bidding->spu_id;
                $ord['product']['size'] = $bidding->properties['size_us'];
                $ord['product']['extTag'] = $bidding->bidding_no;
                $ord['number'] = $order ? $order->order_no : 'XNOrder' . date('mdHis') . rand(100, 999);
                $ord['purchaseOrderNumber'] = $ord['number'];

                dump($ord['number']);
                if ($order && $ord['status'] != 'delivered') {
                    switch ($order->status) {
                        case ChannelOrder::STATUS_CONFIRM:
                            $ord['status'] = 'seller_packaging';
                            break;
                        case ChannelOrder::STATUS_DELIVER:
                            $ord['status'] = 'goat_issue_return_to_seller';
                            break;
                    }
                }
                $data['orders'][] = $ord;
            }
        }


        $logic = new OrderLogic();
        $goat = new GOAT();
        foreach ($data['orders'] as $order) {
            $goat::syncOrder($order);
            $detail = $goat->orderDetailFormat($order);
            if (!$detail['bidding_no']) {
                dump("非API出价订单");
                continue;
            }

            if ($detail['order_status'] == ChannelOrder::STATUS_DEFAULT) {
                dump(sprintf('风控订单暂不处理 订单号:%s 订单id:%s', $detail['order_no'], $detail['order_id']));
                continue;
            }

            $where = ['tripartite_order_id' => $detail['order_id'], 'channel_code' => 'GOAT'];
            $channel_order = ChannelOrder::where($where)->first();

            // dump($channel_order);
            if (!$channel_order) {
                dump('订单创建');
                $goat->orderPirceHandle($detail);
                $channel_order = OrderLogic::orderCreated($detail, 'GOAT');
            }

            $logic = new OrderLogic();
            $logic->orderSync(['order' => $channel_order], $detail);
        }
    }

    // 模拟生成Stockx订单
    public function stockxOrderTest($params)
    {
        if (env('ENV_NAME', 'dev') == 'prod') return true;
        $bid = ChannelBidding::where(['id' => $params['channel_bidding_id']])->first();
        $data = stockxMock::orderMockData();

        $bidding = StockxBidding::where(['bidding_no' => $bid->bidding_no])->first();
        $product = StockxProduct::where(['productId' => $bidding->productId])->first();
        $variant = StockxProductVariant::where(['variantId' => $bidding->variantId])->first();


        $data['amount'] = $params['price'];
        $data['status'] = $params['status']; //CREATED SHIPPED CANCELED RETURNED COMPLETED
        $data['listingId'] = $bidding->listingId;
        $data['createdAt'] = date('Y-m-d H:i:s');
        $data['product'] = [
            'productId' => $product->productId,
            'productName' => $product->title,
        ];
        $data['variant'] = [
            'variantId' => $variant->variantId,
            'variantName' => $variant->variantName,
            'variantValue' => $variant->variantValue,
        ];

        $api = new STOCKX();
        if ($params['status'] == 'CREATED') {
            $detail = $api->syncOrder($data);
            OrderLogic::orderCreated($detail, 'STOCKX');
        } else {
            $order = ChannelOrder::where(['channel_bidding_id' => $params['channel_bidding_id']])->first();
            $data['orderNumber'] = $order->order_no;
            $detail = $api->orderDetailFormat($data);
            $logic = new OrderLogic();
            $logic->orderSync(['order' => $order], $detail);
        }

        return $detail;
    }

    public function goatOrderUpdate($params)
    {
        $goat = new GOAT();
        $detail = $goat->getOrderDetail($params['order_no']);
        if (!$detail['bidding_no']) {
            return $detail;
        }

        $where = ['tripartite_order_id' => $detail['order_id'], 'channel_code' => 'GOAT'];
        $channel_order = ChannelOrder::where($where)->first();
        if (!$channel_order) {
            Robot::sendNotice('订单创建' . $detail['order_no']);
            $goat->orderPirceHandle($detail);
            $channel_order = OrderLogic::orderCreated($detail, 'GOAT');
        }

        if ($channel_order && $detail['order_status'] == ChannelOrder::STATUS_CLOSE) {
            Robot::sendNotice('订单关闭：' . $detail['order_no']);
            OrderLogic::orderClose($channel_order, $detail);
        }

        if ($detail['order_status'] == ChannelOrder::STATUS_CANCEL && $channel_order && $channel_order->status != ChannelOrder::STATUS_CANCEL) {
            Robot::sendNotice('订单取消:' . $detail['refund']['order_no']);
            OrderLogic::orderRefund($channel_order->channel_code, $detail['refund']);
        }

        //更新虚拟物流单
        if ($channel_order->status == ChannelOrder::STATUS_CONFIRM && !$channel_order->dispatch_num) {
            Robot::sendNotice('虚拟物流更新:' . $detail['order_no']);
            $goat->updateDispatchNum($channel_order, $detail);
        }

        return $detail;
    }
}
