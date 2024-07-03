<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SupInvView extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    /*
    public function up()
    {
        //
        DB::statement('
        CREATE VIEW wms_supinv AS SELECT
        id,
        bar_code,
        warehouse_code,
        lot_num,
        sup_id,
        buy_price,
        sale_status,
        quality_type,
        quality_level,
        count(*) AS wh_inv,
        count(
            IF
            ( sale_status = 1, TRUE, NULL )) AS sale_inv,
            count(
            IF
            ( sale_status = 2, TRUE, NULL )) AS lock_inv,
        IF
            ( quality_level <> "A", uniq_code, "" ) AS uniq_code_1,
            tenant_id
        FROM
            wms_inv_goods_detail
        WHERE
            sup_id <> 0
            AND in_wh_status NOT IN ( 0, 4, 7 )
        GROUP BY
            bar_code,
            warehouse_code,
            lot_num,
            quality_type,
            quality_level,
            uniq_code_1,
            sup_id,
            buy_price,
            tenant_id
        ');

        DB::statement('
        CREATE VIEW wms_totalinv  AS SELECT
        id,
        bar_code,
        quality_type,
        quality_level,
        warehouse_code,
        count(*) AS wh_inv,
        count(
            IF
            ( in_wh_status = 3, TRUE, NULL )) shelf_inv,
            count(
            IF
            ( sale_status = 1, TRUE, NULL )) sale_inv,
            count(
            IF
            ( inv_status = 5, TRUE, NULL )) shelf_sale_inv,
            count(
            IF
            ( inv_status = 6, TRUE, NULL )) shelf_lock_inv,
            count(
            IF
            ( inv_status = 7, TRUE, NULL )) wt_send_inv,
            count(
            IF
            ( inv_status = 3, TRUE, NULL )) wt_shelf_inv,
            count(
            IF
            ( in_wh_status = 6, TRUE, NULL )) freeze_inv,
            count(
            IF
            ( inv_status = 4, TRUE, NULL )) wt_shelf_cfm,
            count(
            IF
            ( in_wh_status = 5, TRUE, NULL )) trf_inv,
            tenant_id
        FROM
            wms_inv_goods_detail
        WHERE
            in_wh_status NOT IN ( 0, 4, 7 )
        GROUP BY
            bar_code,
            quality_type,
            quality_level,
            warehouse_code,
            tenant_id
        ');
    }
    */

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        DB::statement("DROP VIEW IF EXISTS wms_supinv");
        DB::statement("DROP VIEW IF EXISTS wms_totalinv");
    }
}
