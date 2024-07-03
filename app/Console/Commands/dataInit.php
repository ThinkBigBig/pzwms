<?php

namespace App\Console\Commands;

use App\Handlers\DwApi;
use App\Handlers\GoatApi;
use App\Logics\BidExecute;
use App\Logics\channel\DW;
use App\Logics\channel\GOAT;
use App\Logics\ChannelPurchaseLogic;
use App\Logics\FreeTaxLogic;
use App\Logics\RedisKey;
use App\Logics\Robot;
use App\Logics\wms\Init;
use App\Logics\wms\Order;
use App\Logics\wms\ShippingRequest;
use App\Models\Admin\V2\IbAndArr as V2IbAndArr;
use App\Models\Admin\V2\Inventory;
use App\Models\Admin\V2\ObOrder;
use App\Models\Admin\V2\Product;
use App\Models\Admin\V2\ShippingDetail;
use App\Models\Admin\V2\Supplier;
use App\Models\Admin\V2\WmsOrder as V2WmsOrder;
use App\Models\Admin\V2\WmsOrderDeliverStatement;
use App\Models\Admin\V2\WmsOrderItem;
use App\Models\Admin\V2\WmsStockLog;
use App\Models\CarryMeBidding;
use App\Models\CarryMeBiddingItem;
use App\Models\ChannelBidding;
use App\Models\ChannelBiddingItem;
use App\Models\ChannelProduct;
use App\Models\ChannelProductSku;
use App\Models\Shipment;
use App\Models\StockBiddingItem;
use Exception;
use IbAndArr;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Maatwebsite\Excel\Facades\Excel;
use Psy\Util\Json;
use stdClass;
use WmsOrder;

class dataInit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data-handle {type} {tenant_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '数据处理';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected $init = null;
    protected $tenant_id = 0;
    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        set_time_limit(0);
        $start = time();
        $type = $this->argument('type');
        $tenant_id = $this->argument('tenant_id');
        request()->headers->set('tenant_id', $tenant_id);
        define('ADMIN_INFO', [
            'tenant_id' => $tenant_id,
            'user_id' => 0,
        ]);
        $this->tenant_id = $tenant_id;
        $this->init = new Init($tenant_id);
        $init = $this->init;
        switch ($type) {
            case 1:
                $this->wmsBase();
                break;
            case 2:
                $this->wmsStockIn();
                break;
            case 3:
                $this->wmsStock();
                break;
            case 4: //出库
                $this->wmsStockOut();
                break;
            case 5: //唯一码库存
                $this->wmsInv();
                break;
            case 6: //出库需求单
                $this->init->shippingRequest(); //出库需求单
                break;
            case 7: //配货单
                $this->init->allocate();
                break;
            case 8: //配货任务
                $this->init->task();
                break;
            case 9: //发货单
                $this->init->shippment();
                break;
            case 10: //发货取消单
                $this->init->shippmentCancel();
                break;
            case 11: //调拨单
                $this->init->transfer();
                break;
            case 12: //其他出库单
                $this->init->obOther();
                break;
            case 13: //收货单
                $this->init->receive2();
                break;
            case 14: //收货单明细
                $this->init->recvDetailInit();
                break;
            case 15: //收获单状态更新
                $this->init->recvUpdate();
                break;
            case 16: //收获单确认入库单
                // $this->init->matchIb2();
                $this->init->matchIb3();
                break;
            case 17: //采购结算账单
                $this->init->purchaseBill();
                break;
            case 18: //质检单
                $this->init->qc();
                break;
            case 19: //质检确认
                $this->init->qcConfirm();
                break;
            case 20: //上架单
                $this->init->putaway();
                break;
            case 21: //移位单
                $this->init->move();
                break;
            case 22: //调拨收货详情
                // $this->init->recvDetailInit(11);
                break;
            case 23:
                $this->init->qcDetailInit(); //质检单明细
                break;
            case 25: //库存状态
                // $this->init->skuDetail3();
                $this->init->skuDetail4();
                // $this->init->syncSupInv();
                // $this->init->syncTotalInv();
                break;
            case 26: //重新确认入库并更新数据
                $this->init->recvUpdate(); //收获单状态更新
                $this->init->matchIb4(); //收获单确认入库单
                DB::statement("UPDATE wms_inv_goods_detail SET in_wh_status=0,inv_status=0,sale_status=0");
                $this->init->skuDetail3(); //唯一码出库状态更新
                $this->init->skuDetail4(); //唯一码入库状态更新
                $this->init->syncSupInv(); //供应商库存
                $this->init->syncTotalInv(); //总库存
                break;
            case 27:
                $this->init->buyPirce();
                $this->init->syncSupInv(); //供应商库存
                $this->init->syncTotalInv(); //总库存
                break;
            case 28:
                DB::statement("UPDATE wms_inv_goods_detail SET in_wh_status=0,inv_status=0,sale_status=0");
                $this->init->skuDetail3(); //唯一码出库状态更新
                $this->init->skuDetail4(); //唯一码入库状态更新
                $this->init->syncSupInv(); //供应商库存
                $this->init->syncTotalInv(); //总库存
                break;
            case 29:
                $this->init->qcDetailInit();
                $this->init->updateBarCode(); //更新条码和供应商id
                break;
            case 30:
                $this->init->recvDetailInit(); //收货单明细
                $this->init->recvUpdate(); //收获单状态更新
                $this->init->matchIb4(); //收获单确认入库单
                DB::statement("UPDATE wms_inv_goods_detail SET in_wh_status=0,inv_status=0,sale_status=0");
                $init->skuDetail(); //唯一码库存明细
                $this->init->skuDetail3(); //唯一码出库状态更新
                $this->init->skuDetail5(); //唯一码入库状态更新
                $init->shenduInv(); //慎独产品明细
                $init->stockInv();
                $this->init->buyPirce();
                $this->init->syncSupInv(); //供应商库存
                $this->init->syncTotalInv(); //总库存
                break;
            case 31:
                DB::statement("TRUNCATE wms_shendu_inv");
                $this->init->skuDetail5(); //唯一码入库状态更新
                $init->shenduInv(); //慎独产品明细
                $init->stockInv();
                $this->init->buyPirce();
                $this->init->syncSupInv(); //供应商库存
                $this->init->syncTotalInv(); //总库存
                break;
            case 32:
                // DB::statement("TRUNCATE wms_shipping_request");
                DB::statement("UPDATE wms_inv_goods_detail SET in_wh_status=0,inv_status=0,sale_status=0");
                // $this->init->shippingRequest(); //出库需求单
                $this->init->skuDetail3(); //唯一码出库状态更新
                $this->init->skuDetail5(); //唯一码入库状态更新
                $init->shenduInv(); //慎独产品明细
                $init->stockInv();
                $this->init->buyPirce();
                $this->init->syncSupInv(); //供应商库存
                $this->init->syncTotalInv(); //总库存
                break;
            case 33:
                // DB::statement("TRUNCATE wms_shipping_request");
                DB::statement("UPDATE wms_inv_goods_detail SET in_wh_status=0,inv_status=0,sale_status=0");
                DB::statement("UPDATE wms_inv_goods_detail SET in_wh_status=3 ,   sale_status=1 , inv_status=5 
                WHERE uniq_code IN (
                '230923115-85','230923115-57','230919019-327','230919019-328','230919019-324','230919019-325','231213018-454','230919019-1055','230919019-498','230919019-1058','230919019-495','230919019-497','230919019-469','230919019-461','230919019-410','230919019-412','231213018-505','231213018-508','231213018-519','231213018-528','231213018-549','230919019-580','230919019-539','230919019-537','231213018-198','230919019-503','230919019-568','230919019-550','230919019-548','230919019-549','230919019-612','230919019-613','230919019-615','231213018-253','230919019-610','230919019-611','230919019-608','230919019-602','230919019-609','231213018-207','230919019-630','230919019-629','230919019-733','230919019-734','230919019-732','230919019-726','230919019-724','230919019-725','231213018-395','231213018-396','231213018-313','231213018-316','231213018-835','231213018-913','231213018-915','231213018-919','231213018-924','231213018-960','231213018-969','231213018-980','230919019-227','230919019-228','230919019-229','230919019-293','230919019-271','231213018-729','231213018-779','231213018-1395','231213018-1305','231213018-1300','231213018-1326','231213018-1327','230408043-411','230714003-621','230714003-625','230916085-83','230916085-97','230714003-550','230714003-589','230714003-586','230714003-587','230714003-584','230714003-585','230714003-562','230916085-288','230916085-289','230916085-290','230714003-502','230916085-253','230916085-265','230916085-266','230916085-263','230916085-267','231213018-1004','230916085-205','230916085-203','230919029-132','230916085-211','230916085-214','230919029-130','230916085-213','230728003-919','230728003-914','230728003-935','230919029-108','230919029-107','230919029-109','230919029-127','230919029-129','230919029-110','230919029-112','230728003-941','230714003-438','230714003-425','230714003-423','230714003-411','230714003-406','230714003-471','230714003-453','230916085-173','230916085-174','230916085-171','230916085-172','230916085-177','230916085-178','230916085-179','230916085-180','230916085-138','230916085-150','230919029-263','230916085-102','230916085-112','230714003-393','230714003-363','230714003-361','231213018-128','230714003-367','230919029-225','230919029-227','230919029-226','230919029-228','231213018-139','230919029-230','230714003-315','230714003-307','230714003-346','230714003-342','230714003-335','230714003-336','230714003-324','230714003-322','230714003-323','230714003-320','230714003-321','230916085-489','230916085-488','230916085-499','230916085-457','230916085-468','230916085-474','230916085-472','230916085-414','230916085-410','230916085-436','230916085-435','230714003-271','230714003-272','230714003-273','230714003-278','230916085-502','230916085-501','230714003-249','230916085-513','230714003-248','230714003-241','230714003-245','230714003-246','230714003-298','230714003-282','230714003-285','230714003-286','230714003-283','230714003-238','230714003-227','230714003-208','230916085-365','230916085-372','230916085-374','230916085-381','230916085-389','230916085-390','230916085-397','230916085-396','230916085-339','230916085-337','230916085-336','230916085-349','230916085-340','230916085-359','230916085-352','230916085-306','230916085-308','230714003-153','230714003-156','230714003-146','230714003-145','230714003-135','230714003-133','230714003-123','230714003-183','230714003-180','230714003-185','230714003-171','230714003-170','230714003-179','230714003-178','230714003-169','231213018-1093','230728003-684','230728003-680','230923115-1116','230923115-1113','230923115-1112','230923115-1115','230728003-614','230923115-1111','230728003-638','230923115-1109','230923115-1106','230923115-1105','230923115-1107','230923115-1102','230923115-1101','230728003-625','230923115-1103','230728003-659','230728003-657','230916085-533','230916085-534','230916085-546','230916085-545','230916085-542','230728003-673','230728003-678','230728003-676','230728003-675','230916085-551','230728003-663','230728003-661','230923115-1176','230923115-1182','230923115-1181','230923115-1180','230923115-1193','230923115-1192','230923115-1195','231213018-88','231213018-87','230923115-1167','230923115-1164','230923115-1165','231213018-55','231213018-56','231213018-58','230923115-1134','230728003-739','230728003-730','230728003-722','230728003-721','230728003-758','230728003-755','230728003-754','230728003-798','230728003-797','230706002-1019','230706002-1008','230706002-1034','230728003-855','230728003-854','230728003-845','230728003-879','230728003-878','230728003-869','230728003-880','230728003-801','230728003-823','230728003-2','230728003-7','230706002-1132','230706002-1134','230728003-107','230728003-102','230728003-101','230728003-100','230728003-157','230728003-144','230728003-149','230728003-211','230728003-209','230728003-200','230728003-234','230728003-235','230728003-254','230728003-253','230728003-257','230728003-276','230728003-192','230728003-195','230728003-194','230728003-180','230728003-324','230728003-353','230728003-352','230728003-350','230728003-397','230728003-390','230728003-388','230728003-310','230728003-314','230728003-312','230728003-306','230728003-305','230728003-309','230728003-443','230728003-440','230728003-463','230623005-1270','230623005-1285','230623005-1286','230728003-487','230728003-486','230728003-482','230623005-726','230623005-1131','230623005-652','230623005-671','230623005-675','230923115-1699','230923115-1696','230923115-1698','230923115-1694','230923115-1693','230623005-881','230623005-882','230623005-883','230623005-859','230623005-877','230623005-870','230623005-873','230623005-875','230623005-866','230623005-868','230623005-863','230923115-1710','230923115-1719','230923115-1722','230923115-1704','230923115-1703','230923115-1705','230923115-1700','230923115-1702','230923115-1707','230623005-820','230923115-1709','230919019-775','230919019-759','230919019-756','230919019-749','230919019-798','230919019-791','230919019-817','230919019-812','230919019-818','230919019-806','230919019-858','230919019-859','230919019-855','230919019-856','230919019-857','230919019-851','230919019-853','230919019-840','230919019-839','230919019-834','230919019-826','230623005-233','230919019-877','230919019-879','230919019-872','230919019-875','230919019-862','230919019-864','230919019-860','230623005-160','230623005-161','230623005-162','230623005-194','230623005-197','230919019-903','230919019-977','230919019-978','230623005-132','230623005-133','230919019-968','230919019-969','230919019-964','230919019-965','230919019-959','230623005-159','230923115-1232','230923115-1250','230923115-1218','230923115-1213','230923115-1210','230919019-993','230919019-994','230919019-996','230919019-991','230923115-1226','230919019-988','230923115-1223','230923115-1224','230919019-982','230919019-984','230919019-985','230919019-980','230919019-981','230923115-1209','230923115-1205','230923115-1204','230923115-1296','230923115-1295','230923115-1298','230923115-1297','230923115-1288','230923115-1285','230923115-1286','230923115-1293','230923115-1261','230923115-1260','230923115-1269','230923115-1268','230923115-1263','230923115-1264','230923115-1270','230623005-452','230623005-417','230623005-418','230623005-420','230923115-1319','230923115-1318','230923115-1315','230923115-1317','230923115-1316','230923115-1310','230923115-1313','230923115-1312','230923115-1322','230923115-1321','230923115-1324','230923115-1323','230923115-1320','230923115-1308','230923115-1307','230923115-1309','230923115-1304','230923115-1303','230923115-1306','230923115-1300','230923115-1302','230923115-1301','230623005-74','230623005-39','230706002-967','230706002-952','230706002-953','230706002-956','230706002-958','230706002-931','230706002-923','230706002-914','230706002-917','230706002-903','230706002-995','230706002-732','230706002-790','230706002-786','230706002-789','230706002-761','230706002-760','230706002-763','230706002-762','230706002-765','230706002-764','230706002-752','230706002-753','230919029-10','230919029-12','230919029-20','230919029-22','230706002-839','230706002-838','230728003-89','230728003-88','230919029-60','230706002-891','230706002-890','230706002-895','230706002-881','230706002-887','230706002-889','230728003-35','230706002-863','230728003-59','230728003-22','230728003-10','230728003-12','230706002-6','230706002-91','230706002-92','230706002-24','230706002-49','230706002-60','230706002-697','230706002-683','230706002-670','230706002-668','230706002-652','230706002-655','230718007-93','230718007-89','230718007-97','230718007-98','230718007-80','230718007-73','230718007-76','230706002-331','230706002-325','230706002-329','230718007-58','230706002-328','230718007-59','230718007-56','230718007-57','230706002-313','230706002-317','230718007-24','230728003-1424','230706002-304','230706002-306','230706002-309','230718007-32','230728003-1455','230728003-1448','230728003-1492','230706002-280','231218006-1005','231218006-1028','230706002-230','230706002-227','230706002-229','231218006-1096','230706002-440','230706002-441','230706002-430','230706002-426','230728003-1333','230728003-1344','230728003-1347','230728003-1349','230728003-1365','230728003-1364','230728003-1383','230728003-1382','230728003-1384','230706002-350','230923001-1','230919019-49','唯一码','230706002-128','230706002-112','230706002-114','230706002-113','230728003-1079','230728003-1072','230728003-1092','230714003-50','230714003-82','230714003-76','230714003-78','230714003-72','230714003-75','230718007-474','230718007-473','230718007-498','230718007-484','230718007-483','230718007-481','230718007-411','230718007-409','230718007-430','230718007-420','230718007-322','230718007-356','230718007-345','230718007-341','230718007-369','230718007-390','230718007-388','230718007-384','230718007-302','230718007-292','230718007-212','230718007-215','230718007-234','230718007-223','230718007-227','230718007-226','230718007-225','230718007-243','230718007-249','230718007-276','230718007-275','230718007-261','230718007-260','230718007-115','230617005-375','230617005-411','230617005-413','230617005-414','230617005-655','230617005-638','230617005-639','230916085-9','230919029-1','230919029-2','230923115-208','230923115-207','230923115-206','230923115-218','230923115-217','230923115-216','230923115-215','230923115-193','230923115-105','230923115-103','230923115-129','230923115-128','230923115-125','230923115-124','230923115-149','230923115-148','230923115-318','230923115-329','230923115-328','230923115-326','230923115-325','230923115-335','230923115-334','230923115-333','230923115-332','230923115-331','230923115-229','230923115-238','230923115-237','230923115-236','230923115-235','230923115-234','230923115-233','230923115-231','230923115-259','230923115-269','230923115-289','230923115-284','230923115-297','230923115-290','230718007-8','230718007-2','230923115-600','231130007-1448','231130007-1443','230923115-599','230923115-597','230923115-596','230923115-595','230923115-593','230923115-592','230923115-591','230923115-590','230923115-409','230923115-407','230923115-404','230923115-403','230923115-402','230923115-401','230923115-419','230923115-417','230923115-412','230923115-410','230923115-429','230923115-423','230923115-422','230923115-421','230923115-436','230923115-434','230923115-432','230923115-346','230923115-344','230923115-342','230923115-340','230923115-365','230923115-379','230923115-378','230923115-377','230923115-389','230923115-388','230923115-387','230923115-382','230923115-380','230923115-399','230923115-398','230923115-397','230923115-396','230923115-394','230923115-393','230923115-392','230923115-391','230923115-390','230919019-1232','230919019-1235','230718007-536','230718007-533','230718007-542','230718007-541','230718007-540','230718007-545','230923115-469','230923115-464','230923115-477','230923115-480'
                )");
                $this->init->skuDetail3(); //唯一码出库状态更新
                $this->init->skuDetail5(); //唯一码入库状态更新
                $init->shenduInv(); //慎独产品明细
                $init->stockInv();
                $this->init->buyPirce();
                $this->init->syncSupInv(); //供应商库存
                $this->init->syncTotalInv(); //总库存
                break;
            case 34:
                $this->init->syncSupInv(); //供应商库存
                $this->init->syncTotalInv(); //总库存
                break;
            case 35:
                $this->init->transferDetail(); //调拨单数据
                break;
            case 36:
                $this->init->dataPermission(); //调拨单数据
                break;
            case 37:
                $this->init->saleDetail(); //销售发货明细
                break;
            case 38:
                $this->init->updateSupName();
                break;
            case 39:
                $this->init->orderDetailUniq();
                break;
            case 40:
                $this->init->orderDetailUniq(); //订单详情去重
                $this->init->updateBuyPrice(); //更新成本价
                break;
            case 41:
                $this->init->productStockLog();
                break;
            case 42:
                $this->init->stockLog2();
                break;
            case 43:
                $this->updateProductName();
                break;
            case 44:
                $this->updateIbAndArr();
                break;
            case 45:
                $this->updateInvBuyPrice();
                $this->modifyItem();
                $this->modifyOrderDeliverSettlement();
                break;
            case 46:
                $this->modifyOrderItemAndStatment();
                // $this->deleteRequestDetail();
                // $this->deleteRequestDetail2();
                break;
            case 47:
                $this->updateSubblierType();
                break;
            case 100: //基础
                // $init->company();
                // $init->companyProduct();
                // $init->dataPermission();
                // $init->user();
                // $init->role();
                // $init->warehouse();
                // $init->area();
                // $init->locationCode();
                // $init->suplier();
                // $init->suplier2();
                // $init->shop();
                // $init->category();
                // $init->brand();
                // $init->product();
                // $init->barcode();
                // $init->barcode2();

                // $init->stockLog();
                $init->productStockLog();
                // $init->printLog(); //唯一码打印记录
                break;
            case 101:
                $this->init->shippingRequest(); //出库需求单
                $init->ibOther(); //其他入库申请单
                $init->instock(); //入库单
                $init->regist(); //到货登记单
                $this->init->receive2(); //收货单
                $this->init->recvDetailInit(); //收货单明细
                $this->init->recvUpdate(); //收获单状态更新
                $this->init->matchIb4(); //收获单确认入库单
                $init->skuDetail(); //唯一码库存明细
                $this->init->skuDetail3(); //唯一码出库状态更新
                $this->init->skuDetail5(); //唯一码入库状态更新
                $init->shenduInv(); //慎独产品明细
                $init->stockInv();
                $this->init->buyPirce();
                $this->init->syncSupInv(); //供应商库存
                $this->init->syncTotalInv(); //总库存
                break;
            case 102:
                $init->order(); //销售
                $init->aftersale(); //售后工单
                $init->orderBill(); //销售结算单
                $init->buyOrder(); //采购单
                $this->init->purchaseBill(); //采购结算账单
                break;
            case 103:
                $this->init->qc(); //质检单
                $this->init->qcDetailInit(); //质检单明细
                $this->init->qcConfirm(); //质检确认
                $this->init->putaway(); //上架单
                $this->init->putawayDetail(); //上架详情
                break;
            case 104:
                $this->init->shippment(); //发货单
                $this->init->transfer(); //调拨单
                $this->init->obOther(); //其他出库单
                $this->init->allocate(); //配货单
                $this->init->task(); //配货任务
                $this->init->shippmentCancel(); //发货取消单
                $this->init->taskDetail(); //配货任务详情
                break;
            case 105:
                $init->consigment(); //寄卖单
                $init->consigmentBill(); //寄卖结算单
                $init->withdraw(); //提现申请单
                break;
            case 106:
                $init->checkRequest(); //盘点申请单
                $init->check(); //盘点单
                $init->difference(); //差异处理记录
                $init->checkBill(); //盘盈亏单
                $this->init->move(); //移位单
                $this->init->moveItem(); //移位单明细
                break;
        }

        dump("总耗时:" . (time() - $start));


        // $this->biddingSync();
        // FreeTaxLogic::fixShipment(1000000);
        // FreeTaxLogic::fixShipmentQty(1000000);

        //初始化所有商品预约单
        //   (new FreeTaxLogic())->reservationInit();
    }

    // 仓储基本信息初始化
    public function wmsBase()
    {
        $init = new Init($this->tenant_id);
        //      $init->OrderDetailByDjd();
        // die;
        $init->dataPermission();
        $init->user();
        $init->role();
        $init->warehouse();
        $init->area();
        $init->locationCode();
        $init->suplier();
        $init->suplier2();
        $init->shop();
        $init->category();
        $init->brand();
        $init->product();
        $init->barcode();
        $init->barcode2();

        // $init->stockLog();
        $init->productStockLog();
    }

    public function wmsStockIn()
    {
        $init = new Init($this->tenant_id);
        $init->buyOrder();
        $init->regist();
        $init->ibOther();
        $init->instock(); //入库单
        $init->printLog();
        // $init->purchaseBill();
        // $init->qc();
        // $init->qcConfirm();
        // $init->putaway();
        // $init->move();
    }

    public function wmsStock()
    {
        $init = new Init($this->tenant_id);
        $init->stockLog();
        $init->receiveOrderDetail();
        $init->checkRequest();
        $init->check();
        $init->difference();
        $init->checkBill();
    }

    function wmsInv()
    {
        $init = new Init($this->tenant_id);
        $init->receive();
        $init->skuDetail();
        // $init->skuDetail2();
        // $init->syncSupInv();
        // $init->syncTotalInv();
    }

    // 出库
    public function wmsStockOut()
    {
        $init = new Init($this->tenant_id);
        $init->order();
        $init->aftersale();
        $init->orderBill();
        $init->consigment();
        $init->consigmentBill();
        $init->withdraw();
        // $init->shippingRequest();
        // $init->allocate();
        // $init->task();
        // $init->shippment();
        // $init->shippmentCancel();
        // $init->transfer();
        // $init->obOther();
    }

    public function biddingSync()
    {
        set_time_limit(0);
        // 当前有效出价
        $biddings = ChannelBidding::where(['status' => ChannelBidding::BID_SUCCESS, 'qty_sold' => 0])->get();
        $dw = new DW();
        $goat = new GoatApi();
        $sync_num = 0;
        $cancel_num = 0;
        $total = 0;
        foreach ($biddings as $bidding) {
            try {
                $total++;
                dump($bidding->bidding_no);
                $cancel = false;
                dump('获取出价单状态');
                if ($bidding->channel_code == 'DW') {
                    $detail = $dw->getBiddingDetail($bidding->bidding_no);
                    if (($detail['status'] ?? '') == 10) {
                        $cancel = true;
                    }
                }
                if ($bidding->channel_code == 'GOAT') {
                    $products = $bidding->channelBiddingItem;
                    foreach ($products as $product) {
                        $detail = $goat->productInfo($product->product_id);
                        if (($detail['saleStatus'] ?? '') == 'canceled') {
                            $cancel = true;
                            $product->update([
                                'status' => ChannelBiddingItem::STATUS_TAKEDOWN,
                                'takedown_at' => date('Y-m-d H:i:s'),
                            ]);
                        }
                    }
                }


                if ($cancel) {
                    dump('取消，同步出价状态');
                    $bidding->update([
                        'status' => ChannelBidding::BID_CANCEL,
                        'qty_cancel' => 1,
                        'qty_remain' => 0
                    ]);

                    // erp出价，stock_bidding_items同步
                    if ($bidding->stock_bidding_item_id) {
                        StockBiddingItem::where([
                            'id' => $bidding->stock_bidding_item_id,
                            'status' => StockBiddingItem::STATUS_SUCCESS
                        ])->update([
                            'status' => StockBiddingItem::STATUS_CANCEL,
                            'remark' => '同步出价状态',
                            'qty_cancel' => 1,
                            'qty_left' => 0,
                        ]);
                    }
                    // app出价，carryme_bidding_items同步
                    if ($bidding->carryme_bidding_item_id) {
                        CarryMeBiddingItem::where([
                            'id' => $bidding->carryme_bidding_item_id,
                            'status' => CarryMeBiddingItem::STATUS_BID
                        ])->update([
                            'status' => CarryMeBiddingItem::STATUS_CANCEL,
                            'qty_cancel' => 0,
                            'qty_left' => 0,
                            'remark' => '同步出价状态',
                        ]);
                    }
                    $sync_num++;
                } else {
                    dump('未取消');
                    if ($bidding->carryme_bidding_item_id) {
                        $carryme_bidding = $bidding->carrymeBidding;
                        if ($carryme_bidding && $carryme_bidding->created_at < '2023-07-21T00:00:00.000000Z') {
                            dump('执行取消，原始出价时间在2023-07-21之前');
                            BidExecute::cancel($bidding, '批量取消出价2');
                            $cancel_num++;
                        }
                    }
                }
            } catch (Exception $e) {
                Robot::sendException($e->__toString());
            }
        }
        $msg = sprintf('出价状态同步完成，查询数量%d 同步数量 %d 取消数量 %d', $total, $sync_num, $cancel_num);
        Robot::sendNotice($msg);
        dump($msg);
    }

    function updateProductName()
    {
        $file = fopen(storage_path('/files/productNewName.csv'), 'r');
        while ($data = fgetcsv($file)) {
            dump($data);
            if (empty($data[1] ?? "")) {
                Log::info($data);
                continue;
            }
            Product::where(['product_sn' => trim($data[0])])->update(['name' => trim($data[1])]);
        }
    }

    // 更新登记单和入库单的关联关系
    function updateIbAndArr()
    {
        $file = fopen(storage_path('/files/登记单入库单匹配历史数据.csv'), 'r');
        while ($data = fgetcsv($file)) {
            dump($data);
            if (empty($data[1] ?? "")) {
                Log::info($data);
                continue;
            }
            $where = ['ib_type' => $data[1], 'warehouse_code' => $data[2], 'arr_code' => $data[3], 'ib_code' => $data[4], 'erp_no' => $data[5], 'third_no' => $data[6], 'source_code' => $data[7], 'rd_total' => $data[8], 'created_at' => date('Y-m-d H:i:s', strtotime($data[9])), 'updated_at' => date('Y-m-d H:i:s', strtotime($data[10])), 'tenant_id' => $data[11]];
            $find = V2IbAndArr::where($where)->first();
            if (!$find) V2IbAndArr::create($where);
        }
    }

    function shenduInv()
    {
        $arr = Excel::toArray(new stdClass, storage_path('/files/慎独业务库存明细导出数据.xlsx'));
        dd($arr);
        $file = fopen(storage_path('/files/慎独业务库存明细导出数据.csv'), 'r');
        while ($data = fgetcsv($file)) {
            dump($data);
            if (empty($data[1] ?? "")) {
                Log::info($data);
                continue;
            }
            $where = ['ib_type' => $data[1], 'warehouse_code' => $data[2], 'arr_code' => $data[3], 'ib_code' => $data[4], 'erp_no' => $data[5], 'third_no' => $data[6], 'source_code' => $data[7], 'rd_total' => $data[8], 'created_at' => date('Y-m-d H:i:s', strtotime($data[9])), 'updated_at' => date('Y-m-d H:i:s', strtotime($data[10])), 'tenant_id' => $data[11]];
            $find = V2IbAndArr::where($where)->first();
            if (!$find) V2IbAndArr::create($where);
        }
    }

    function modifyItem()
    {
        dump('修复 orderItems数据');
        $file = fopen(storage_path('/files/orderItems订单编码.csv'), 'r');
        while ($data = fgetcsv($file)) {
            dump($data);
            $code = $data[0];
            WmsOrderItem::where(['origin_code' => $code])->delete();
            $order = V2WmsOrder::where(['code' => $code])->first();
            if (!$order) continue;
            Order::addItems($order);
        }
    }

    function modifyOrderDeliverSettlement()
    {
        dump('修复 OrderDeliverSettlement数据');
        $orders = V2WmsOrder::whereRaw("created_at>'2024-05-22' AND `status`=2 AND deliver_status IN (2,3)")->get();

        foreach ($orders as $order) {
            dump($order->code);
            WmsOrderDeliverStatement::where(['origin_code' => $order->code])->delete();
            Order::summaryUpdate($order);
        }
    }

    function modifyOrderItemAndStatment()
    {
        $codes = WmsOrderDeliverStatement::whereRaw("created_at>'2024-05-22' AND sup_id=0")->distinct()->pluck('origin_code')->toArray();
        foreach ($codes as $code) {
            dump($code);
            WmsOrderItem::where(['origin_code' => $code])->delete();
            $order = V2WmsOrder::where(['code' => $code])->first();
            if (!$order) continue;

            Order::addItems($order);
            WmsOrderDeliverStatement::where(['origin_code' => $order->code])->delete();
            Order::summaryUpdate($order);
        }
    }


    function updateInvBuyPrice()
    {
        dump('补充库存数据的成本价数据');
        $file = fopen(storage_path('/files/部分唯一码成本价.csv'), 'r');
        while ($data = fgetcsv($file)) {
            dump($data);
            $uniq_code = $data[1];
            $buy_price = $data[2];
            Inventory::where(['uniq_code' => $uniq_code, 'buy_price' => 0])->update(['buy_price' => $buy_price]);
        }
    }

    function deleteRequestDetail()
    {

        while (1) {
            $sql = "SELECT d.* FROM wms_shipping_request req,(
                SELECT request_code,SUM(payable_num) as num,MIN(id) as id,GROUP_CONCAT(id) as ids,MAx(ship_code) as ship_code,MAx(sup_id) AS sup_id,max(uniq_code) as uniq_code,max(shipped_at) as shipped_at,max(shipper_id) AS shipper_id FROM wms_shipping_detail WHERE tenant_id='489274' AND created_at<'2024-05-22' GROUP BY request_code) d WHERE req.request_code=d.request_code AND req.payable_num<d.num AND req.`status`=4 AND req.request_status=4 AND req.payable_num=1 AND req.tenant_id='489274' limit 5000";
            $data = DB::select($sql);
            if (!$data) break;
            // Log::info($data);
            foreach ($data as $item) {
                dump($item->request_code);
                $where = ['request_code' => $item->request_code];

                $find = ShippingDetail::where($where)->where('id', $item->id)->first();
                $update = ['ship_code' => $item->ship_code, 'uniq_code' => $item->uniq_code, 'sup_id' => $item->sup_id, 'shipper_id' => $item->shipper_id, 'shipped_at' => $item->shipped_at,];
                if ($find->uniq_code == '' && $find->batch_no != $item->uniq_code) {
                    $update['uniq_code'] = $item->uniq_code;
                }
                $find->update($update);
                ShippingDetail::where($where)->whereIn('id', explode(',', $item->ids))->where('id', '<>', $item->id)->delete();
            }
        }
    }

    function deleteRequestDetail2()
    {

        while (1) {
            $sql = "SELECT d.* FROM wms_shipping_request req,(
                SELECT request_code,SUM(payable_num) as num,MIN(id) as id,GROUP_CONCAT(id) as ids,MAx(ship_code) as ship_code,MAx(sup_id) AS sup_id,max(uniq_code) as uniq_code,max(shipped_at) as shipped_at,max(shipper_id) AS shipper_id,COUNT(DISTINCT IF(ship_code='',NULL,ship_code)) as ship_num FROM wms_shipping_detail WHERE tenant_id='489274' AND created_at<'2024-05-22' GROUP BY request_code HAVING ship_num=1
								) d WHERE req.request_code=d.request_code AND req.payable_num<d.num AND req.`status`=4 AND req.request_status=4 AND req.tenant_id='489274' LIMIT 5000";
            $data = DB::select($sql);
            if (!$data) break;
            // Log::info($data);
            foreach ($data as $item) {
                dump($item->request_code);
                $where = ['request_code' => $item->request_code];
                $ids = explode(',', $item->ids);
                $use_ids = array_chunk($ids, count($ids) / 3)[0];
                ShippingDetail::where($where)->whereIn('id', $use_ids)->update([
                    'ship_code' => $item->ship_code, 'uniq_code' => $item->uniq_code, 'sup_id' => $item->sup_id, 'shipper_id' => $item->shipper_id, 'shipped_at' => $item->shipped_at,
                    'uniq_code' => $item->uniq_code,
                ]);
                ShippingDetail::where($where)->whereIn('id', $ids)->whereNotIn('id', $use_ids)->delete();
            }
        }
    }

    function updateSubblierType()
    {
        dump('更新供应商类别');
        $file = storage_path('/files/供应商类别修改.txt');
        $type = array_flip([1 => '个人', 2 => '公司', 3 => '奥莱', 4 => '仓库', 5 => '公司线下', 6 => '濮阳', 7 => '原宿ネット購入', 8 => '原宿店舗購入', 9 => '原宿買取', 10 => '中丸町网购',]);
        $status = ['启用' => 1, '禁用' => 0];
        $fp = fopen($file, 'r');
        $k = 0;
        while (($data = fgets($fp)) !== false) {
            $k++;
            if ($k==1) continue;
            $data = explode(',', $data);
            dump($data);
            Supplier::where(['sup_code' => $data[0]])->update([
                'type' => $type[$data[4]],
                'status' => $status[$data[5]],
            ]);
            
        }
        fclose($fp);
    }
}
