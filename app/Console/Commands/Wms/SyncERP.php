<?php

namespace App\Console\Commands\Wms;

use App\Handlers\ERPApi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncERP extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wms:sync-erp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步商品和库存信息到ERP';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->product();
        $this->stock();
        $this->stockDetail();
        return 0;
    }

    private $tenant_id = '489274';
    function product($params = [])
    {
        dump('开始同步商品信息....');
        $api = new ERPApi();
        $tenant_id = $this->tenant_id;
        $start = 0;
        while (true) {
            dump($start);
            $sql = "SELECT sku.id as id,p.`product_sn` as goodsCode,p.`name` as itemName,'' as shortName,'' as englishName,b.`name` as brandName,'' as seasonName,c.`name` 	as categoryName,sku.sku as itemCode,sku.bar_code as barCode,sku.spec_one as skuProperty,'' as stockUnit,'' as length,'' as width,''as height,'' as volume,'' AS grossWeight,'' as netWeight,'' as color,'' as size,sku.tag_price as tagPrice,sku.retails_price as retailPrice,sku.const_price as costPrice,'' as purchasePrice
         FROM wms_spec_and_bar sku 
         LEFT JOIN wms_product p ON sku.product_id = p.id
         left JOIN wms_product_brands b ON p.brand_id=b.id
         left JOIN wms_product_category c ON p.category_id=c.id
         WHERE sku.tenant_id=$tenant_id AND p.tenant_id=$tenant_id AND b.tenant_id=$tenant_id AND c.tenant_id=$tenant_id and sku.id> $start order by sku.id limit 500  ";
            $data = DB::select($sql);
            if (!$data) break;
            $data = objectToArray($data);
            $start = $data[count($data) - 1]['id'];
            $res = $api->products($data, $tenant_id);
            dump($res);
        }
    }

    function stock($params = [])
    {
        dump('开始同步库存信息....');
        $api = new ERPApi();
        $tenant_id = $this->tenant_id;
        $start = 0;
        while (true) {
            dump($start);
            $sql = " SELECT inv.id,'' as requestId,'' as ownerCode,sku.sku as itemCode,p.product_sn as goodsCode,p.`name` as itemName,inv.bar_code as barCode,sku.spec_one as skuProperty,inv.buy_price as weightCostPrice,
            inv.uniq_code_1 as uniqueCode,inv.wh_inv as  storeGoodsNum,inv.lock_inv AS lockGoodsNum,inv.wt_send_inv AS waiteGoodsNum,inv.freeze_inv AS freezeGoodsNum,
            IF(inv.inv_type=0,'自营','寄卖') AS inventoryType,
            IF(inv.quality_type=1,'正品','瑕疵') AS qualityType,
            IF(inv.quality_level='A','优','一级') AS qualityLevel,
            inv.lot_num as batchCode,inv.warehouse_code AS storeHouseCode,inv.warehouse_name as storeHouseName,inv.sup_name as supplierName,s.sup_code AS supplierCode
            from wms_sup_inv inv 
            left join wms_spec_and_bar sku ON inv.bar_code=sku.bar_code
            left JOIN wms_product p ON sku.product_id=p.id
            LEFT JOIN wms_supplier s ON inv.sup_id=s.id
            WHERE inv.tenant_id=$tenant_id AND sku.tenant_id=$tenant_id  AND p.tenant_id=$tenant_id AND s.tenant_id=$tenant_id AND inv.id>$start order by inv.id limit 500";
            $data = DB::select($sql);
            if (!$data) break;
            $data = objectToArray($data);
            $start = $data[count($data) - 1]['id'];
            $res = $api->stock($data, $tenant_id);
            dump($res);
        }
    }

    function stockDetail($params = [])
    {
        dump('开始同步出入库明细....');
        $api = new ERPApi();
        $tenant_id = $this->tenant_id;
        $start = 0;
        $begin = date('Y-m-d 00:00:00',strtotime('-1 day'));
        $end = date('Y-m-d 00:00:00');
        while (true) {
            dump($start);
            $sql = "SELECT l.id as id,'' as requestId,l.updated_at AS changeTime,l.origin_code AS srcOrderCode,l.origin_type AS srcOrderType,l.erp_no AS threeOrderCode,'' AS platformItemCode,sku.sku as itemCode,p.product_sn as goodsCode,p.`name` as itemName,sku.spec_one as skuProperty,'' as salePrice,'' AS realDealAmount,'' AS costPrice,'' AS weightCostPrice,'' as uniqueCode,l.type AS orderType,l.source_code AS orderCode,
        IF(l.operation=9,CONCAT('-',l.num),l.num) AS changeNum,
        l.warehouse_code AS storeHouseCode,w.warehouse_name as storeHouseName,s.sup_code AS supplierCode,s.`name` as supplierName,l.batch_no AS batchCode,'' as inventoryType,
        IF(l.quality_level='A','正品','瑕疵') AS qualityType,
        IF(l.quality_level='A','优','一级') AS qualityLevel
        from wms_stock_logs l 
        left JOIN wms_spec_and_bar sku ON l.bar_code=sku.bar_code
        left JOIN wms_product p ON sku.product_id=p.id
        LEFT JOIN wms_warehouse w ON l.warehouse_code=w.warehouse_code
        LEFT JOIN wms_supplier s ON l.sup_id=s.id
        WHERE l.tenant_id=$tenant_id and sku.tenant_id=$tenant_id and p.tenant_id=$tenant_id and w.tenant_id=$tenant_id and s.tenant_id=$tenant_id AND l.operation IN(5,9) AND l.updated_at>'$begin' AND l.updated_at<'$end' AND l.id>$start ORDER BY l.id LIMIT 500";
            $data = DB::select($sql);
            if (!$data) break;
            $data = objectToArray($data);
            $start = $data[count($data) - 1]['id'];
            $res = $api->stockDetail($data, $tenant_id);
            dump($res);
        }
    }
}
