<?php

namespace App\Logics\mock;

use App\Handlers\HttpService;
use App\Models\StockxBidding;
use App\Models\StockxOrder;

/**
 * sotckx的mock数据
 */
class stockxMock
{
    static function prodEnv()
    {
        return in_array(env('ENV_NAME','dev'),['prod','uat']);
    }
    
    static function apiMock($url,$method,$params=[]){

        if (self::prodEnv()) {
            return [];
        }

        $header = [];
        if ($method == 'get') {
            $request_data = ['query' => $params, 'header' => $header];
        }

        if ($method == 'post') {
            $header[] = 'Content-Type: application/json';
            $request_data = ['data' => $params, 'header' => $header];
        }

        if ($method == 'delete') {
            $request_data = ['data' => $params, 'header' => $header];
        }

        // 加上调用mock接口使用的token
        $request_data['query']['apifoxToken'] = 'HBEPtlNwLt6QNcSEfalym3OrvXHtOcKZ';
        $res = HttpService::request($method, 'https://mock.apifox.cn/m1/3043629-0-default' . $url, $request_data, true);
        return $res ? json_decode($res, true) : [];
    }

    static function marketData()
    {
        if (self::prodEnv()) {
            return [];
        }
        $str = '{
            "productId": "1",
            "variantId": "1",
            "currencyCode": "JPY",
            "highestBidAmount": "18300",
            "sellFasterAmount": "21900",
            "earnMoreAmount": "27400",
            "lowestAskAmount": "7900"
        }';
        return json_decode($str,true);
    }

    static function getList($listing_id)
    {
        if (self::prodEnv()) {
            return [];
        }
        return self::getListSuccess($listing_id);
    }

    static function getListSuccess($listing_id) {
        $str = '{
            "listingId": "b73b6efe-6c57-4d23-9afe-88cfbfb25c6e",
            "status": "ACTIVE",
            "amount": "76",
            "currencyCode": "USD",
            "inventoryType": "STANDARD",
            "createdAt": "2023-07-27T08:23:25.481Z",
            "updatedAt": "2023-07-27T08:23:25.481Z",
            "product": {
                "productId": "7d4aa25d-3233-4627-9d3f-63002826e7e9",
                "productName": "Nike Dunk Low Industrial Blue Sashiko"
            },
            "variant": {
                "variantId": "be707b3d-eb4f-4016-a917-aff3bee9eccd",
                "variantName": "Nike-Dunk-Low-Industrial-Blue-Sashiko:8",
                "variantValue": "10"
            },
            "batch": null,
            "ask": {
                "askId": "14180490556756398238",
                "askCreatedAt": "2023-07-27T08:23:25.000Z",
                "askUpdatedAt": "2023-07-27T08:23:25.000Z",
                "askExpiresAt": "2024-07-26T08:22:33.000Z"
            },
            "order": null,
            "payout": {
                "totalPayout": "59.45",
                "salePrice": "76",
                "totalAdjustments": "-16.55",
                "currencyCode": "USD",
                "adjustments": [
                    {
                        "adjustmentType": "Minimum Transaction Fee",
                        "amount": "-5.71",
                        "percentage": "0.05"
                    },
                    {
                        "adjustmentType": "Payment Proc. (3%)",
                        "amount": "-2.28",
                        "percentage": "0.03"
                    },
                    {
                        "adjustmentType": "Shipping",
                        "amount": "-8.56",
                        "percentage": "0"
                    }
                ]
            },
            "lastOperation": {
                "operationId": "f61ddf33-7304-4954-809b-8580d6cdf344",
                "operationType": "CREATE",
                "operationStatus": "SUCCEEDED",
                "operationInitiatedBy": "USER",
                "operationInitiatedVia": "STOCKX-PRO",
                "operationCreatedAt": "2023-07-27T08:23:25.510Z",
                "operationUpdatedAt": "2023-07-27T08:23:25.510Z",
                "error": null,
                "changes": {
                    "additions": {
                        "amount": "76.0000",
                        "currencyCode": "USD",
                        "ask": {
                            "askId": "14180490556756398238",
                            "askExpiresAt": "2024-07-26T08:22:33.000Z"
                        },
                        "createdAt": "2023-07-27T08:23:25.481Z",
                        "listingId": "b73b6efe-6c57-4d23-9afe-88cfbfb25c6e",
                        "status": "ACTIVE",
                        "updatedAt": "2023-07-27T08:23:25.481Z"
                    },
                    "updates": {},
                    "removals": {}
                }
            }
        }';
        $arr = json_decode($str, true);
        $arr['listingId'] = $listing_id;
        $bidding = StockxBidding::where(['listingId' => $listing_id])->first();
        $arr['status'] = !$bidding->status ? 'ACTIVE' : 'DELETED';
        $arr['currencyCode'] = 'JPY';
        $arr['amount'] = '10000';
        $arr['product']['productId'] = $bidding->productId;
        $arr['variant']['variantId'] = $bidding->variantId;
        return $arr;
    }

    static function getListFail($listing_id) {
        $str = '{
            "ask": null,
            "order": null,
            "payout": null,
            "variant": {
                "productId": "570127ef-75ce-4ec1-8c19-d387bf3fbf88",
                "variantId": "8f582a70-fa42-4055-b640-ed2c3f6c3d53",
                "variantName": "Air-Jordan-13-Retro-Wheat-2023:10",
                "variantValue": "9"
            },
            "product": {
                "productId": "570127ef-75ce-4ec1-8c19-d387bf3fbf88",
                "productName": "Jordan 13 Retro Wheat (2023)"
            },
            "currencyCode": "JPY",
            "amount": "23500.0000",
            "listingId": "b6d33baf-7639-478b-84e2-59967653ac26",
            "status": "INACTIVE",
            "inventoryType": "STANDARD",
            "createdAt": "2023-10-11T05:05:12.229Z",
            "updatedAt": "2023-10-11T05:05:12.964Z",
            "batch": null,
            "lastOperation": {
                "operationId": "461c3afd-c4e5-4f7e-b63e-ca59d35027c1",
                "operationType": "ACTIVATE",
                "operationStatus": "FAILED",
                "operationInitiatedBy": "USER",
                "operationInitiatedVia": "PUBLIC-API",
                "operationCreatedAt": "2023-10-11T05:05:12.333Z",
                "operationUpdatedAt": "2023-10-11T05:05:12.957Z",
                "error": "该商品不可售",
                "changes": {
                    "additions": [],
                    "updates": {
                        "updatedAt": "2023-10-11T05:05:12.333Z"
                    },
                    "removals": []
                }
            }
        }';
        $arr = json_decode($str, true);
        $arr['listingId'] = $listing_id;
        $bidding = StockxBidding::where(['listingId' => $listing_id])->first();
        $arr['currencyCode'] = 'JPY';
        $arr['amount'] = '10000';
        $arr['product']['productId'] = $bidding->productId;
        $arr['variant']['variantId'] = $bidding->variantId;
        $arr['lastOperation']['operationId'] = $bidding->createOperationId;
        return $arr;
    }

    static function getListDelete($listing_id) {
        $str = '{
            "ask": null,
            "order": null,
            "payout": null,
            "variant": {
                "productId": "570127ef-75ce-4ec1-8c19-d387bf3fbf88",
                "variantId": "8f582a70-fa42-4055-b640-ed2c3f6c3d53",
                "variantName": "Air-Jordan-13-Retro-Wheat-2023:10",
                "variantValue": "9"
            },
            "product": {
                "productId": "570127ef-75ce-4ec1-8c19-d387bf3fbf88",
                "productName": "Jordan 13 Retro Wheat (2023)"
            },
            "currencyCode": "JPY",
            "amount": "23500.0000",
            "listingId": "b6d33baf-7639-478b-84e2-59967653ac26",
            "status": "DELETED",
            "inventoryType": "STANDARD",
            "createdAt": "2023-10-11T05:05:12.229Z",
            "updatedAt": "2023-10-11T05:05:12.964Z",
            "batch": null,
            "lastOperation": {
                "operationId": "461c3afd-c4e5-4f7e-b63e-ca59d35027c1",
                "operationType": "ACTIVATE",
                "operationStatus": "FAILED",
                "operationInitiatedBy": "USER",
                "operationInitiatedVia": "PUBLIC-API",
                "operationCreatedAt": "2023-10-11T05:05:12.333Z",
                "operationUpdatedAt": "2023-10-11T05:05:12.957Z",
                "error": "该商品不可售",
                "changes": {
                    "additions": [],
                    "updates": {
                        "updatedAt": "2023-10-11T05:05:12.333Z"
                    },
                    "removals": []
                }
            }
        }';
        $arr = json_decode($str, true);
        $arr['listingId'] = $listing_id;
        $bidding = StockxBidding::where(['listingId' => $listing_id])->first();
        $arr['currencyCode'] = 'JPY';
        $arr['amount'] = '10000';
        $arr['product']['productId'] = $bidding->productId;
        $arr['variant']['variantId'] = $bidding->variantId;
        $arr['lastOperation']['operationId'] = $bidding->createOperationId;
        return $arr;
    }

    static function createList($params)
    {
        if (self::prodEnv()) {
            return [];
        }
        $str = '{
            "listingId": "98e2e748-8000-45bf-a624-5531d6a68318",
            "operationId": "98e2e748-8000-45bf-a624-5531d6a68318",
            "operationType": "CREATE",
            "operationStatus": "PENDING",
            "operationUrl": "https://api.stockx.com/v2/selling/listings/c0a635ce-322f-49e1-9bfc-f954fc46f6bd/operations/d0a635ce-322f-49e1-9bfc-f954fc46f6be",
            "operationInitiatedBy": "USER",
            "operationInitiatedVia": "IOS",
            "createdAt": "2021-11-09T12:44:31.000Z",
            "updatedAt": "2021-11-09T12:44:31.000Z",
            "changes": {
                "additions": {
                    "active": true,
                    "askData": {
                        "amount": "100",
                        "currency": "USD",
                        "expiresAt": "2022-08-24T18:06:43.600Z"
                    }
                },
                "updates": {
                    "updatedAt": "2021-11-09T12:44:31.000Z"
                },
                "removals": {}
            },
            "error": null
        }';
        $res = json_decode($str,true);
        $res['listingId'] = 'listing-'.genRandomString(10).'-'.date('YmdHis');
        $res['operationId'] = 'operation-'.genRandomString(10).'-'.date('YmdHis');
        return $res;
    }

    static function deleteList($listing_id)
    {
        if (self::prodEnv()) {
            return [];
        }
        $str = '{
            "listingId": "98e2e748-8000-45bf-a624-5531d6a68318",
            "operationId": "98e2e748-8000-45bf-a624-5531d6a68318",
            "operationType": "CREATE",
            "operationStatus": "PENDING",
            "operationUrl": "https://api.stockx.com/v2/selling/listings/c0a635ce-322f-49e1-9bfc-f954fc46f6bd/operations/d0a635ce-322f-49e1-9bfc-f954fc46f6be",
            "operationInitiatedBy": "USER",
            "operationInitiatedVia": "IOS",
            "createdAt": "2021-11-09T12:44:31.000Z",
            "updatedAt": "2021-11-09T12:44:31.000Z",
            "changes": {
                "additions": {
                    "active": true,
                    "askData": {
                        "amount": "100",
                        "currency": "USD",
                        "expiresAt": "2022-08-24T18:06:43.600Z"
                    }
                },
                "updates": {
                    "updatedAt": "2021-11-09T12:44:31.000Z"
                },
                "removals": {}
            },
            "error": null
        }';
        $res = json_decode($str,true);
        $res['listingId'] = $listing_id;
        $res['operationId'] = 'operation-'.genRandomString(10).'-'.date('YmdHis');
        return $res;
    }

    static function orderMockData()
    {
        $str = '{
            "orderNumber": "49332873-49232632",
            "listingId": "b930b412-4898-4c9d-ae6b-0d6c14e298bd",
            "askId": "14090373734943054941",
            "amount": "117",
            "currencyCode": "USD",
            "status": "COMPLETED",
            "createdAt": "2023-03-25T05:32:10.000Z",
            "updatedAt": "2023-03-28T04:15:02.000Z",
            "product": {
                "productId": "5e6a1e57-1c7d-435a-82bd-5666a13560fe",
                "productName": "Nike Dunk Low Retro White Black Panda (2021)"
            },
            "variant": {
                "variantId": "a2ea632e-b87a-46a1-b35d-739b8050fdf2",
                "variantName": "Nike-Dunk-Low-Retro-White-Black-2021:11",
                "variantValue": "9"
            },
            "shipment": {
                "carrierCode": "YAMATO",
                "shipByDate": "2023-03-28T23:59:59.000Z",
                "shippingLabelUrl": "https://logistics-assets.stockx.com/406860204226304220230325053213_f79fcbf3-356a-49e6-8f90-588b16298018.png?Expires=1698370366&Key-Pair-Id=K20H1PZTQSUKX8&Signature=nWtqu~4DeQ0JY7mnPwJhi7l2lRyi46iWoUk~YZNgtTT6p9yMQFLDC1rr9u3qRrvxcChn0G~LLLQRDd27w2KZe1PRnJWlM2dPQ836gFAmQiTyAirhCMrMDvXIZ1~ZRf77NTipnKlBY-q0E4uUbEvFhrTllUBVZRfdsfepPCnPOpGCQa-bhayTWoeSE6UFA1bKtLdFb8zxkTMcvXNQ1HArwdjRAnzDGt0ZtvReWKCsLXC0Plp6fNhYkNJ9yvpnup7MkSuwm4hK47O50cpCyuAHDq6SbvDOnr1cjk6nVzNaCgxrclsba3g3j0RrCL3A4UDdHzI8Em5vnZCW879jrviwjQ__",
                "trackingNumber": "406860204226304220230325053213",
                "trackingUrl": "http://track.kuronekoyamato.co.jp/english/tracking",
                "shippingDocumentUrl": "https://api.stockx.com/v2/selling/orders/49332873-49232632/shipping-document/S-657138089"
            },
            "payout": {
                "salePrice": "117",
                "totalAdjustments": "-17.38",
                "totalPayout": "99.62",
                "currencyCode": "USD",
                "adjustments": [
                    {
                        "amount": "-13.87",
                        "percentage": "0.06",
                        "adjustmentType": "MinTransactionFee"
                    },
                    {
                        "amount": "-3.51",
                        "percentage": "0.03",
                        "adjustmentType": "Payment Proc. (3%)"
                    },
                    {
                        "amount": "-6.17",
                        "percentage": "0",
                        "adjustmentType": "Shipping"
                    },
                    {
                        "amount": "6.17",
                        "percentage": "0",
                        "adjustmentType": "InPersonDropOff"
                    }
                ]
            }
        }';

        $arr = json_decode($str,true);
        $arr['orderNumber'] = 'stockxTest'.date('YmdHis');
        $arr['currencyCode'] = 'JPY';
        $arr['updatedAt'] = date('Y-m-d H:i:s');
        return $arr;
    }

    static function orderDetail($order_no)
    {
        if (self::prodEnv()) {
            return [];
        }
        $data = self::orderMockData();
        $data['orderNumber'] = $order_no;
        $bidding = StockxOrder::where(['orderNumber'=>$order_no])->first();
        $data['listingId'] = $bidding->listingId;
        $data['askId'] = $bidding->askId;
        $data['amount'] = $bidding->amount;
        $data['currencyCode'] = $bidding->currencyCode;
        $data['status'] = $bidding->status;
        $data['product'] = $bidding->product;
        $data['variant'] = $bidding->variant;
        return $data;
    }

}
