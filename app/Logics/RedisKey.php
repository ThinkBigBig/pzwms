<?php

namespace App\Logics;

class RedisKey
{

    const PRODUCT_LOWEST_PRICE_LOCK = 'erp:lowest_price_lock';
    const PRODUCT_LOWEST_PRICE_LOCK_DW = 'erp:lowest_price_lock_dw';
    const PRODUCT_LOWEST_LIMIT_BOLT = 'erp:lowest_price_blot';
    const PRODUCT_LOWEST_LIMIT_CROSS_BORDER = 'erp:lowest_price_cross_border';

    const CHANNEL_ORDER_LOCK = 'erp:channel_order_lock';
    const GOAT_ORDER_UPDATE_TIME = 'goat:order_updated_at';
    const GOAT_ORDERS_SELL = 'goat:sell_order_updated_at';
    const GOAT_ORDERS_ISSUE = 'goat:issue_order_updated_at';
    const GOAT_ORDERS_CONFIRM = 'goat:confirm_order_updated_at';
    const GOAT_ORDERS_SHIP = 'goat:ship_order_updated_at';

    const EXCHNAGE_LOCK = 'erp:exchange_lock';

    const STOCKX_AUTHORIZATION_CODE = 'erp:stockx_authorization_code';
    const STOCKX_TOKEN = 'erp:stockx_token';
    const STOCKX_REFRESH_TOKEN = 'erp:stockx_refresh_token';

    const RUN_TAG_BID = 'erp:run_tag_bid';
    const RUN_TAG_BID_RESULT = 'erp:run_tag_bid_result';
    const RUN_TAG_ORDER = 'erp:run_tag_order';
    const RUN_TAG_DISPATCH_NUM = 'erp:run_tag_dispatch_num';

    static function stockItemBidLock($item)
    {
        return 'erp:stock_item_bid_lock:' . $item->id;
    }
    static function appItemBidLock($item)
    {
        return 'erp:app_item_bid_lock:' . $item->id;
    }

    const BID_ORDER_LOCK = 'erp:bid_order_lock:';
    static function orderLock($carryme_bidding_id)
    {
        return self::BID_ORDER_LOCK . $carryme_bidding_id;
    }

    const ORDER_CONFIRM = 'erp:order_confirm';
    const ORDER_EXPORT_DELIVER_LOCK = 'erp:order_deliver_lock';

    const LOCK_CALLBACK = 'erp:lock_callback';
    const LOCK_REFRESH_STOCK_BID = 'erp:refresh_stock_bid';

    static function sotckBidLock($product_id)
    {
        return 'erp:lock_stock_bid:' . $product_id;
    }

    const BID_AFTER_CANCEL = 'erp:bid_after_cancel';
    const SKU_BID_AFTER_CANCEL = 'erp:sku_bid_after_cancel';

    const DW_DISPATCH_CURRENT = 'erp:dw_dispatch_current';

    // 慎独同步商品信息时使用
    const PRODUCT_QUEUE = 'erp:sync_product_queue';
    const PRODUCT_STOCK_QUEUE = 'erp:sync_product_stock_queue';
    const PRODUCT_STOCK_DETAIL_QUEUE = 'erp:sync_product_stock_detail_queue';

    const APP_TOKEN = 'erp:app_token';

    const LOCK_CHANNEL_PURCHASE = 'erp:lock_channel_purchase';
    const CHANNEL_PURCHASE_QUEUE = 'erp:channel_purchase_queue';

    const LOCK_CHANNEL_PURCHASE_CANCEL = 'erp:lock_channel_purchase_cancel';
    const PURCHASE_PRODUCT_QUEUE = 'erp:purchase_product_queue';

    const LOCK_CHANNEL_RETRY = 'erp:lock_channel_retry';
    const LOCK_ORDER_TRACKING = 'erp:lock_order_tracking';
    const LOCK_ORDER_CONFIRM = 'erp:lock_order_confirm';
    const LOCK_ORDER_SENDOUT = 'erp:lock_order_sendout';
    const LOCK_CONSUMER_KAFKA = 'erp:lock_consumer_kafka';

    const CM_BID_CONFIG = 'erp:cm_bid_config';
    const CHANNEL_CONFIG = 'erp:channel_config';



    static function receiveTaskLock($task_code)
    {
        return 'wms:receive_task:' . $task_code;
    }

    const LOCK_ASYNC_HADDLE = 'wms:lock_async_handle';
    const LOCK2_ASYNC_HADDLE = 'wms:lock2_async_handle';
    const QUEUE_AYSNC_HADNLE = 'wms:queue_async_handle';
    const QUEUE2_AYSNC_HADNLE = 'wms:queue2_async_handle';

    const WMS_WAVE_GROUP = 'wms:wave_group';
    const WMS_IMPORT = 'erp:lock_import';
    const WMS_IMPORT_QUEUE = 'erp:queue_import';
    static function wmsPermission($tenant_id = 0)
    {
        return 'wms:permission:' . $tenant_id;
    }

    const PDA_TOKEN = 'erp:pad_token';
    const WMS_SKIP = 'wms:skip';
    const WMS_CATEGORY = 'wms:category';

    static function lockArrConfirm($arr_id) {
        return 'wms:lock_arr_confirm:'.$arr_id;
    }
}
