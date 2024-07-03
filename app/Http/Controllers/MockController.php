<?php

namespace App\Http\Controllers;

use App\Console\Commands\order;
use App\Handlers\GoatApi;
use App\Models\ChannelBidding;
use App\Models\ChannelBiddingItem;
use App\Models\ChannelOrder;
use App\Models\ChannelProduct;
use App\Models\ChannelProductSku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MockController extends Controller
{
    public $param;
    public $host;
    public function __construct(Request $request)
    {
        $this->param = $request->all();
        $this->host = 'https://www.goat.com';
    }

    public function mock(Request $request)
    {
        $path = ltrim($request->path(), 'mock');
       
        //goat 商品下架
        if (preg_match("/^\/api\/v1\/products\/(.*?)\/cancel$/", $path, $matchs)) {
            return call_user_func([$this, 'goatCancelProduct'], $matchs[1]);
        }
        //goat更新订单状态
        if (preg_match("/^\/api\/v1\/orders\/(.*?)\/update_status$/", $path, $matchs)) {
            return call_user_func([$this, 'goatUpdateStatus'], $matchs[1]);
        }
        //goat查指定订单信息
        if (preg_match("/^\/api\/v1\/orders\/(.*?)$/", $path, $matchs)) {
            return call_user_func([$this, 'goatOrderInfo'], $matchs[1]);
        }
        //goat 获取指定商品信息
        if (preg_match("/^\/api\/v1\/products\/(\d+)$/", $path, $matchs)) {
            return call_user_func([$this, 'goatProductInfo'], $matchs[1]);
        }

        $maps = [
            '/api/v1/product_templates/search' => 'goatTmplatesSearch', //商品基础信息
            '/api/v1/products/create_multiple' => 'goatCreateProduct', //创建商品
            '/api/v1/partners/products/search' => 'goatProductSearch', //上架商品查询
            '/api/v1/partners/products/update_multiple' => 'goatUpdateProduct', //批量更新商品价格
            '/api/v1/product_variants/buy_bar_data' => 'goatLowestPrice', //获取最低价
            '/api/v1/orders' => 'goatOrders', //全量订单信息
            '/api/v1/size_conversions' => 'goatSize', //尺码转换

        ];
        $params = $request->all();
        return call_user_func([$this, $maps[$path] ?? ''], $params);
    }

    private function goatTmplatesSearch($params)
    {
        $api = new GoatApi($this->host);
        $res = $api->skuInfo($params);
        return $res;
    }

    //创建商品
    private function goatCreateProduct($params)
    {
        $data = '{
            "jobId": "1827207c72a7e9b9287ff7b36de3cd6b",
            "status": "queued",
            "createdAt": "2023-04-07T09:50:21+00:00"
        }';
        return json_decode($data, true);
    }
    //获取已出价信息
    private function goatProductSearch($params)
    {
        $data = '{
            "metadata": {
                "totalPages": 96,
                "currentPage": 1,
                "nextPage": 2,
                "pageItemCount": 10,
                "itemsPerPageCount": 10
            },
            "products": [
                {
                    "id": 259420550,
                    "slug": "dunk-low-year-of-the-rabbit-white-rabbit-candy-b688224d-2066-4db1-af0e-2269f57210f6",
                    "createdAt": "2023-03-20T06:07:54.431Z",
                    "updatedAt": "2023-03-20T06:07:54.770Z",
                    "priceCents": 70383,
                    "size": 9.5,
                    "shoeCondition": "new",
                    "boxCondition": "good_condition",
                    "productTemplateId": 1118894,
                    "localizedPriceCents": {
                        "currency": "USD",
                        "amount": 13200,
                        "amountUsdCents": 13200
                    },
                    "saleStatus": "active",
                    "instantShippable": false,
                    "isGoatClean": false,
                    "defectsString": "",
                    "extTag":"G4600711680918781",
                    "consigned": false
                }
            ]
        }';
        $data = json_decode($data, true);
        if($params['extTag']??''){
            $check = ChannelBidding::where(['bidding_no'=>$params['extTag']])->first();
            $spu_id = $check->spu_id;
        }
        if($params['productTemplateId']??0){
            $spu_id = $params['productTemplateId'];
        }
        //最近有效出价单
        $bidding = ChannelBidding::where(['status' => ChannelBidding::BID_DEFAULT, 'channel_code' => 'GOAT', 'spu_id' => $spu_id])->orderBy('id', 'desc')->first();
        $product_ids = ChannelBiddingItem::where(['status' => ChannelBiddingItem::STATUS_SHELF])->distinct(true)->pluck('product_id');
        $max =  ChannelBiddingItem::max('product_id');
        if ($bidding) {
            $pro = $data['products'][0];
            $num = $bidding->qty;
            $data['products'] = [];
            while ($num > 0) {
                $max++;
                $tmp = $pro;
                $tmp['id'] = $max;
                $tmp['extTag'] = $bidding->bidding_no;
                $tmp['priceCents'] = $bidding->price;
                $tmp['productTemplateId'] = $spu_id;
                $tmp['size'] = $bidding->properties['size_us'];
                $data['products'][] = $tmp;
                $num--;
            }
        }
        return $data;
    }

    //批量更新商品价格
    private function goatUpdateProduct($params)
    {
        $data = '{
            "jobId": "1827207c72a7e9b9287ff7b36de3cd6b",
            "status": "queued",
            "createdAt": "2023-04-07T09:50:21+00:00"
        }';
        return json_decode($data, true);
    }

    //下架指定商品
    private function goatCancelProduct($product_id)
    {
        $data = '{
            "id": 259464896,
            "slug": "wmns-dunk-low-black-white-60e8841b-6ed1-4255-833f-446c326b8a17",
            "createdAt": "2023-03-20T08:19:49.889Z",
            "size": 10,
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
            "messageRead": true,
            "extTag": "FIT_TEST_001",
            "sizeOption": {
                "presentation": "10",
                "value": 10
            },
            "variantShoeCondition": "new_no_defects",
            "priceCents": 19700,
            "localizedPriceCents": {
                "currency": "USD",
                "amount": 19700,
                "amountUsdCents": 19700
            },
            "saleStatus": "canceled",
            "canReturn": true,
            "status": "sale",
            "consigned": false,
            "isFungible": true,
            "directShipping": false,
            "mainPictureUrl": "https://image.goat.com/750/attachments/product_template_pictures/images/071/445/292/original/722965_00.png.png",
            "shortUrl": "https://www.goat.com/products/wmns-dunk-low-black-white-60e8841b-6ed1-4255-833f-446c326b8a17",
            "localizedProductHighestOfferCents": {
                "currency": "USD"
            },
            "productImages": [
                {
                    "url": "https://image.goat.com/750/attachments/product_template_pictures/images/071/445/292/original/722965_00.png.png",
                    "createdAt": "2022-04-29T19:00:39.023Z",
                    "pictureType": "ShoeTemplate"
                }
            ],
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
                    4,
                    4.5,
                    5,
                    5.5,
                    6,
                    6.5,
                    7,
                    7.5,
                    8,
                    8.5,
                    9,
                    9.5,
                    10,
                    10.5,
                    11,
                    11.5,
                    12,
                    12.5,
                    13,
                    13.5,
                    14,
                    14.5,
                    15,
                    15.5,
                    16,
                    16.5,
                    17,
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
                "lowestPriceCents": 0,
                "newLowestPriceCents": 0,
                "usedLowestPriceCents": 0,
                "productTaxonomy": [],
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
                "isOfferable": true,
                "isFashionProduct": false,
                "isRaffleProduct": false,
                "singleGender": "women",
                "storyHtml": "<p>The Nike women’s Dunk Low ‘Black White’ also known as ‘Panda’ highlights classic color blocking on a vintage silhouette originally released in 1985. The all-leather upper features a crisp white base with contrasting black overlays and a matching black Swoosh. Nike branding lands on the heel tab and woven tongue tag in keeping with the sneaker’s OG aesthetic. The low-top is supported by a durable rubber cupsole, equipped underfoot with a basketball-specific traction pattern.</p>\n",
                "pictureUrl": "https://image.goat.com/1000/attachments/product_template_pictures/images/071/445/292/original/722965_00.png.png",
                "mainGlowPictureUrl": "https://image.goat.com/glow-4-5-25/750/attachments/product_template_pictures/images/071/445/292/original/722965_00.png.png",
                "mainPictureUrl": "https://image.goat.com/750/attachments/product_template_pictures/images/071/445/292/original/722965_00.png.png",
                "gridGlowPictureUrl": "https://image.goat.com/glow-4-5-25/375/attachments/product_template_pictures/images/071/445/292/original/722965_00.png.png",
                "gridPictureUrl": "https://image.goat.com/375/attachments/product_template_pictures/images/071/445/292/original/722965_00.png.png",
                "sizeOptions": [
                    {
                        "presentation": "4",
                        "value": 4
                    },
                    {
                        "presentation": "4.5",
                        "value": 4.5
                    },
                    {
                        "presentation": "5",
                        "value": 5
                    },
                    {
                        "presentation": "5.5",
                        "value": 5.5
                    },
                    {
                        "presentation": "6",
                        "value": 6
                    },
                    {
                        "presentation": "6.5",
                        "value": 6.5
                    },
                    {
                        "presentation": "7",
                        "value": 7
                    },
                    {
                        "presentation": "7.5",
                        "value": 7.5
                    },
                    {
                        "presentation": "8",
                        "value": 8
                    },
                    {
                        "presentation": "8.5",
                        "value": 8.5
                    },
                    {
                        "presentation": "9",
                        "value": 9
                    },
                    {
                        "presentation": "9.5",
                        "value": 9.5
                    },
                    {
                        "presentation": "10",
                        "value": 10
                    },
                    {
                        "presentation": "10.5",
                        "value": 10.5
                    },
                    {
                        "presentation": "11",
                        "value": 11
                    },
                    {
                        "presentation": "11.5",
                        "value": 11.5
                    },
                    {
                        "presentation": "12",
                        "value": 12
                    },
                    {
                        "presentation": "12.5",
                        "value": 12.5
                    },
                    {
                        "presentation": "13",
                        "value": 13
                    },
                    {
                        "presentation": "13.5",
                        "value": 13.5
                    },
                    {
                        "presentation": "14",
                        "value": 14
                    },
                    {
                        "presentation": "14.5",
                        "value": 14.5
                    },
                    {
                        "presentation": "15",
                        "value": 15
                    },
                    {
                        "presentation": "15.5",
                        "value": 15.5
                    },
                    {
                        "presentation": "16",
                        "value": 16
                    },
                    {
                        "presentation": "16.5",
                        "value": 16.5
                    },
                    {
                        "presentation": "17",
                        "value": 17
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
            "cjId": "722965_10_0",
            "defectsString": ""
        }';
        // $data = '{
        //     "success": false,
        //     "messages": ["This product has already been purchased and cannot be updated."],
        //     "error_code": "unknown",
        //     "localized_messages": ["This product has already been purchased and cannot be updated."]
        // }';
        $data = json_decode($data, true);
        $data['id'] = $product_id;
        return $data;
    }

    //获取最低价
    private function goatLowestPrice($params)
    {
        // $com = [
        //     'shoeCondition' => 'new_no_defects',
        //     "boxCondition" => "good_condition",
        //     "lowestPriceCents" => [
        //         "currency" => "USD",
        //         "amount" => 20000,
        //         "amountUsdCents" => 20000,
        //     ],
        //     "stockStatus" => 'single_in_stock'
        // ];
        // $res = [];
        // $skus = ChannelProductSku::where(['spu_id' => $params['productTemplateId']])->get();
        // foreach ($skus as $sku) {
        //     $tmp = $com;
        //     $tmp['sizeOption'] = [
        //         "presentation" => $sku->properties['size_us'],
        //         "value" => $sku->properties['size_us']
        //     ];
        //     $res[] = $tmp;
        // }
        // return $res;

        $api = new GoatApi($this->host);
        $res = $api->getLowestPrice($params['productTemplateId'], $params['countryCode']);
        return $res;
    }

    //获取全量订单信息
    private function goatOrders($params)
    {
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
                "status": "goat_packaged",
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
        $data['orders'] = [];
        //所有有效出价单，模拟一个下单
        $where = ['channel_code' => 'GOAT', 'status' => ChannelBidding::BID_SUCCESS];
        $biddings = ChannelBidding::where($where)->orWhereExists(function ($query) {
            $query->where('channel_code', 'GOAT')->where('status', ChannelOrder::STATUS_DELIVER)->select(DB::raw(1))->from('channel_order')->whereRaw('channel_order.channel_bidding_id = channel_bidding.id');
        })->get();
        // $biddings = ChannelBidding::where($where)->orExists('channelOrders')->get();


        foreach ($biddings as $bidding) {
            $items = $bidding->channelBiddingItem;
            foreach ($items as $item) {
                if ($bidding->product_sn = '554724-058') {
                    $ord['product']['priceCents'] = $bidding->price - 15000;
                } else {
                    $ord['product']['priceCents'] = $bidding->price;
                }
                $order = ChannelOrder::where('channel_bidding_item_id', $item->id)->first();
                if($order) continue;
                $ord['product']['id'] = $item->product_id;
                $ord['product']['productTemplate']['id'] = $bidding->spu_id;
                $ord['product']['size'] = $bidding->properties['size_us'];
                $ord['product']['priceCents'] = $bidding->price;
                $ord['product']['extTag'] = $bidding->bidding_no;
                $ord['number'] = $order ? $order->order_no : 'XNOrder' . date('mdHis') . rand(100, 999);
                $ord['id'] = random_int(10000000, 99999999);
                if ($order) {
                    $ord['id'] = $order->tripartite_order_id;
                    switch ($order->status) {
                        case ChannelOrder::STATUS_CONFIRM:
                            $ord['status'] = 'seller_packaging';
                            break;
                        case ChannelOrder::STATUS_DELIVER:
                            if ($order->id % 2) { //奇数模拟订单完成
                                $ord['status'] = 'goat_verified';
                            } else { //偶数模拟订单取消
                                $ord['status'] = 'goat_issue_return_to_seller';
                            }
                            break;
                    }
                }else{
                    // "purchasedAt": "2023-03-16T06:03:23.787Z",
                    $ord['purchasedAt'] = gmdate('Y-m-d\TH:i:s\Z');
                }
                $ord['updatedAt'] = "2023-07-16T06:03:23.787Z";
                $data['orders'][] = $ord;
            }
        }
        return $data;
    }

    //获取指定订单信息
    private function goatOrderInfo($order_no)
    {
        $data = '{
            "id": 424366885,
            "productId": 257452618,
            "number": 309389083,
            "purchaseOrderNumber": 309389083,
            "shippingServiceLevel": "standard",
            "status": "sold",
            "updatedAt": "2023-03-16T05:04:32.722Z",
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
                "amount": 31489,
                "amountUsdCents": 31489
            },
            "sellerAmountMadeCents": 31489,
            "localizedPriceCents": {
                "currency": "USD",
                "amount": 35900,
                "amountUsdCents": 35900
            },
            "priceCents": 35900,
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
            "purchasedAt": "2023-03-16T05:04:31.700Z",
            "promoCodeLedgerId": 0,
            "progressSequence": "ProgressSequenceForSeller",
            "trackingToGoatCode": "0a41bcce6f2753be",
            "trackingToGoatCodeUrl": "https://sneakers-production-shippinglabelbucket-bucket-1cqhkx9lppzpb.s3.amazonaws.com/shipping_label/2023-03-17/31af6eba-a398-4d66-9f1e-a1522fbaf5d3.pdf",
            "carrierToGoat": "drop_off",
            "shippingLabelToGoat": "https://sneakers-production-shippinglabelbucket-bucket-1cqhkx9lppzpb.s3.amazonaws.com/shipping_label/2023-03-17/31af6eba-a398-4d66-9f1e-a1522fbaf5d3.pdf",
            "endState": false,
            "product": {
                "id": 257452618,
                "slug": "concepts-x-dunk-low-sb-orange-lobster-1caa0afb-d9b9-4e7a-8d5d-67a380b6f162",
                "createdAt": "2023-03-16T00:23:31.460Z",
                "priceCents": 35900,
                "size": 8.0,
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
                "messageRead": true,
                "sizeOption": {
                    "presentation": "8",
                    "value": 8.0
                },
                "variantShoeCondition": "new_no_defects",
                "localizedPriceCents": {
                    "currency": "USD",
                    "amount": 35900,
                    "amountUsdCents": 35900
                },
                "saleStatus": "completed",
                "canReturn": true,
                "status": "sale",
                "consigned": false,
                "mainPictureUrl": "https://image.goat.com/750/attachments/product_template_pictures/images/081/467/358/original/1004215_00.png",
                "shortUrl": "https://www.goat.com/products/concepts-x-dunk-low-sb-orange-lobster-1caa0afb-d9b9-4e7a-8d5d-67a380b6f162",
                "localizedProductHighestOfferCents": {
                    "currency": "USD"
                },
                "productImages": [
                    {
                        "url": "https://image.goat.com/750/attachments/product_template_pictures/images/081/467/358/original/1004215_00.png",
                        "createdAt": "2022-11-29T17:33:01.647Z",
                        "pictureType": "ShoeTemplate"
                    }
                ],
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
                    "color": "Orange",
                    "composition": "",
                    "designer": "",
                    "details": "Orange Frost/Electro Orange/White",
                    "fit": "",
                    "gender": [
                        "men"
                    ],
                    "id": 1004215,
                    "maximumOfferCents": 200000,
                    "midsole": "Zoom Air",
                    "minimumOfferCents": 2500,
                    "modelSizing": "",
                    "name": "Concepts x Dunk Low SB \'Orange Lobster\'",
                    "nickname": "Orange Lobster",
                    "productCategory": "shoes",
                    "productType": "sneakers",
                    "releaseDate": "2022-12-22T23:59:59.999Z",
                    "silhouette": "Dunk SB",
                    "sizeBrand": "nike",
                    "sizeRange": [
                        3.0,
                        3.5,
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
                        17.5,
                        18.0,
                        18.5,
                        19.0,
                        19.5,
                        20.0
                    ],
                    "sizeType": "numeric_sizes",
                    "sizeUnit": "us",
                    "sku": "FD8776 800",
                    "slug": "concepts-x-dunk-low-sb-orange-lobster-bv1310-orange",
                    "specialDisplayPriceCents": 12000,
                    "specialType": "standard",
                    "status": "active",
                    "upperMaterial": "Suede",
                    "availableSizesNew": [],
                    "availableSizesNewV2": [],
                    "availableSizesNewWithDefects": [],
                    "availableSizesUsed": [],
                    "lowestPriceCents": 0,
                    "newLowestPriceCents": 0,
                    "usedLowestPriceCents": 0,
                    "productTaxonomy": [],
                    "localizedSpecialDisplayPriceCents": {
                        "currency": "USD",
                        "amount": 12000,
                        "amountUsdCents": 12000
                    },
                    "category": [
                        "Skateboarding"
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
                    "applePayOnlyPromo": false,
                    "singleGender": "men",
                    "storyHtml": "<p>The Concepts x Nike Dunk Low SB ‘Orange Lobster’ continues a collaborative series that originally kicked off in 2008. In crafting the shoe’s design, the Boston-based shop looks to the rare orange lobster for inspiration. Varying shades of orange are applied to the nubuck upper, featuring lightly speckled overlays and a tonal Swoosh outlined in white. A woven Nike SB tag embellishes the white mesh tongue, while a bib-themed pattern decorates the interior lining. The sneaker sits atop a traditional cupsole, featuring black sidewalls and a grippy orange rubber outsole.</p>\n",
                    "pictureUrl": "https://image.goat.com/1000/attachments/product_template_pictures/images/081/467/358/original/1004215_00.png",
                    "mainGlowPictureUrl": "https://image.goat.com/glow-4-5-25/750/attachments/product_template_pictures/images/081/467/358/original/1004215_00.png",
                    "mainPictureUrl": "https://image.goat.com/750/attachments/product_template_pictures/images/081/467/358/original/1004215_00.png",
                    "gridGlowPictureUrl": "https://image.goat.com/glow-4-5-25/375/attachments/product_template_pictures/images/081/467/358/original/1004215_00.png",
                    "gridPictureUrl": "https://image.goat.com/375/attachments/product_template_pictures/images/081/467/358/original/1004215_00.png",
                    "sizeOptions": [
                        {
                            "presentation": "3",
                            "value": 3.0
                        },
                        {
                            "presentation": "3.5",
                            "value": 3.5
                        },
                        {
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
                        },
                        {
                            "presentation": "18",
                            "value": 18.0
                        },
                        {
                            "presentation": "18.5",
                            "value": 18.5
                        },
                        {
                            "presentation": "19",
                            "value": 19.0
                        },
                        {
                            "presentation": "19.5",
                            "value": 19.5
                        },
                        {
                            "presentation": "20",
                            "value": 20.0
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
                "cjId": "1004215_8_0"
            },
            "sellerTitle": "Sold",
            "sellerDescription": "Congrats. Please confirm within 24 hours of the order being placed. Failure to confirm will result in an auto cancellation and review of your seller account.",
            "sellerActions": [
                {
                    "buttonTitle": "Confirm",
                    "buttonAction": "seller_confirm",
                    "buttonType": "big_button",
                    "confirmTitle": "",
                    "confirmMessage": "Please confirm that the sneakers are the following: New, Size 8.0, SKU: FD8776 800.\n\nIf the sneakers are not received as described, they can be rejected during verification."
                },
                {
                    "buttonTitle": "Cancel Order",
                    "buttonAction": "seller_cancel_review",
                    "buttonType": "link",
                    "confirmTitle": "",
                    "confirmMessage": "Are you sure you want to cancel this order? Multiple cancellations will result in commission rate increases."
                }
            ],
            "buyerTitle": "",
            "buyerDescription": "",
            "buyerActions": [],
            "buyerIssuePictures": [],
            "buyerDiscountPercentage": 0,
            "buyerDiscountAmountFormatted": "",
            "finalSale": false
        }';
        $data = json_decode($data, true);
        $data['number'] = $order_no;
        $order = ChannelOrder::where(['order_no' => $order_no, 'channel_code' => 'GOAT'])->first();
        if (!$order) return [];
        $item = $order->channelBiddingItem;
        $bidding = $order->channelBidding;
        $data['product']['id'] = $item->product_id;
        $data['product']['productTemplate']['id'] = $item->spu_id;
        $data['product']['size'] = $bidding->properties['size_us'];
        $data['product']['priceCents'] = $order->price;
        $data['product']['extTag'] = $bidding->bidding_no;
        $data['number'] = $order ? $order->order_no : 'XNOrder' . date('mdHis') . rand(100, 999);
        $data['status'] = ($order && $order->status == ChannelOrder::STATUS_CONFIRM) ? 'seller_packaging' : $data['status'];
        $data['updatedAt'] = gmdate('Y-m-d\TH:i:s\Z');
        return $data;
    }

    //更新订单状态
    private function goatUpdateStatus($order_no)
    {
        $data = $this->goatOrderInfo($order_no);
        $data['status'] = $this->param['order']['statusAction'] ?? 'sold';
        return $data;
    }

    private function goatSize($params)
    {
        $api = new GoatApi($this->host);
        $res = $api->getSizeConversion($params);
        return $res;
    }

    public function goatProductInfo($product_id)
    {
        $str = '{
            "id": 289936716,
            "slug": "air-jordan-3-retro-washington-wizards-b981f961-656c-4ba7-ac52-c6af527c4693",
            "createdAt": "2023-07-15T06:03:03.801Z",
            "size": 9.5,
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
            "message": "We are currently attempting to process your sale for $184. You will be notified of the outcome through push notification and email.",
            "messageRead": false,
            "extTag": "GsJ0Dxr1689400978",
            "sizeOption": {
                "presentation": "9.5",
                "value": 9.5
            },
            "variantShoeCondition": "new_no_defects",
            "priceCents": 18400,
            "localizedPriceCents": {
                "currency": "USD",
                "amount": 18400,
                "amountUsdCents": 18400
            },
            "saleStatus": "canceled",
            "canReturn": true,
            "status": "sale",
            "consigned": false,
            "isFungible": true,
            "directShipping": false,
            "mainPictureUrl": "https://image.goat.com/750/attachments/product_template_pictures/images/085/551/715/original/1038216_00.png.png",
            "shortUrl": "https://www.goat.com/products/air-jordan-3-retro-washington-wizards-b981f961-656c-4ba7-ac52-c6af527c4693",
            "localizedProductHighestOfferCents": {
                "currency": "USD"
            },
            "productImages": [
                {
                    "url": "https://image.goat.com/750/attachments/product_template_pictures/images/085/551/715/original/1038216_00.png.png",
                    "createdAt": "2023-03-03T03:01:44.749Z",
                    "pictureType": "ShoeTemplate"
                }
            ],
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
                "brandName": "Air Jordan",
                "careInstructions": "",
                "color": "White",
                "composition": "",
                "designer": "Tinker Hatfield",
                "details": "White/Metallic Copper/True Blue/Cement Grey",
                "fit": "",
                "gender": [
                    "men"
                ],
                "id": 1038216,
                "maximumOfferCents": 200000,
                "midsole": "Air",
                "minimumOfferCents": 2500,
                "modelSizing": "",
                "name": "Air Jordan 3 Retro ",
                "nickname": "Washington Wizards",
                "productCategory": "shoes",
                "productType": "sneakers",
                "releaseDate": "2023-04-29T23:59:59.999Z",
                "silhouette": "Air Jordan 3",
                "sizeBrand": "air_jordan",
                "sizeRange": [
                    3.5,
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
                    14.0,
                    15.0,
                    16.0,
                    17.0,
                    18.0
                ],
                "sizeType": "numeric_sizes",
                "sizeUnit": "us",
                "sku": "CT8532 148",
                "slug": "air-jordan-3-retro-washington-wizards-ct8532-148",
                "specialDisplayPriceCents": 21000,
                "specialType": "standard",
                "status": "active",
                "upperMaterial": "Leather",
                "availableSizesNew": [],
                "availableSizesNewV2": [],
                "availableSizesNewWithDefects": [],
                "availableSizesUsed": [],
                "lowestPriceCents": 0,
                "newLowestPriceCents": 0,
                "usedLowestPriceCents": 0,
                "productTaxonomy": [],
                "localizedSpecialDisplayPriceCents": {
                    "currency": "USD",
                    "amount": 21000,
                    "amountUsdCents": 21000
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
                "isOfferable": true,
                "isFashionProduct": false,
                "isRaffleProduct": false,
                "singleGender": "men",
                "storyHtml": "<p>The Air Jordan 3 Retro ‘Washington Wizards’ showcases a familiar design based on a PE colorway that Michael Jordan wore during his time in D.C. Featuring color blocking reminiscent of the OG ‘True Blue’ edition from 1988, the classic silhouette makes use of a white tumbled leather upper with grey elephant-print overlays at the toe and heel. Contrasting blue accents appear on the interior lining, molded eyelets, and the raised Jumpman logo that decorates the heel tab. A second Jumpman graces the tongue in copper-colored embroidery, matching the circular eyelets that dot the forefoot. The mid-top is mounted on a two-tone polyurethane midsole, equipped with a visible Air-sole heel unit and supported by a durable grey rubber outsole.</p>\n",
                "pictureUrl": "https://image.goat.com/1000/attachments/product_template_pictures/images/085/551/715/original/1038216_00.png.png",
                "mainGlowPictureUrl": "https://image.goat.com/glow-4-5-25/750/attachments/product_template_pictures/images/085/551/715/original/1038216_00.png.png",
                "mainPictureUrl": "https://image.goat.com/750/attachments/product_template_pictures/images/085/551/715/original/1038216_00.png.png",
                "gridGlowPictureUrl": "https://image.goat.com/glow-4-5-25/375/attachments/product_template_pictures/images/085/551/715/original/1038216_00.png.png",
                "gridPictureUrl": "https://image.goat.com/375/attachments/product_template_pictures/images/085/551/715/original/1038216_00.png.png",
                "sizeOptions": [
                    {
                        "presentation": "3.5",
                        "value": 3.5
                    },
                    {
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
                        "presentation": "14",
                        "value": 14.0
                    },
                    {
                        "presentation": "15",
                        "value": 15.0
                    },
                    {
                        "presentation": "16",
                        "value": 16.0
                    },
                    {
                        "presentation": "17",
                        "value": 17.0
                    },
                    {
                        "presentation": "18",
                        "value": 18.0
                    }
                ],
                "userWant": false,
                "userOwn": false
            },
            "instantShippable": false,
            "cjId": "1038216_9_5",
            "defectsString": ""
        }';
        $data = json_decode($str,true);
        $data['id'] = $product_id;
        return $data;
    }
}
