<?php

namespace App\Logics\wms;

use App\Imports\AdminUsers;
use App\Imports\StockLogImport;
use App\Logics\BaseLogic;
use App\Logics\Robot;
use App\Models\Admin\V2\AfterSaleOrder;
use App\Models\Admin\V2\ArrivalRegist;
use App\Models\Admin\V2\Consignment;
use App\Models\Admin\V2\ConsignmentDetails;
use App\Models\Admin\V2\IbDetail;
use App\Models\Admin\V2\IbOrder;
use App\Models\Admin\V2\ImportTemporary;
use App\Models\Admin\V2\Inventory;
use App\Models\Admin\V2\ObOrder;
use App\Models\Admin\V2\OIbDetails;
use App\Models\Admin\V2\OObDetails;
use App\Models\Admin\V2\OtherIbOrder;
use App\Models\Admin\V2\OtherObOrder;
use App\Models\Admin\V2\preAllocationDetail;
use App\Models\Admin\V2\preAllocationLists;
use App\Models\Admin\V2\Product;
use App\Models\Admin\V2\ProductBrands;
use App\Models\Admin\V2\ProductCategory;
use App\Models\Admin\V2\ProductSpecAndBar;
use App\Models\Admin\V2\PurchaseDetails;
use App\Models\Admin\V2\PurchaseOrders;
use App\Models\Admin\V2\PurchaseStatements;
use App\Models\Admin\V2\RecvDetail;
use App\Models\Admin\V2\RecvOrder;
use App\Models\Admin\V2\ShippingDetail;
use App\Models\Admin\V2\ShippingOrders;
use App\Models\Admin\V2\SupInv;
use App\Models\Admin\V2\Supplier;
use App\Models\Admin\V2\TransferDetails;
use App\Models\Admin\V2\TransferOrder;
use App\Models\Admin\V2\UniqCodePrintLog;
use App\Models\Admin\V2\Warehouse;
use App\Models\Admin\V2\WarehouseLocation;
use App\Models\Admin\V2\WithdrawUniqLog;
use App\Models\Admin\V2\WmsAfterSaleOrderDetail;
use App\Models\Admin\V2\WmsAllocationTask;
use App\Models\Admin\V2\WmsConsigmentSettlement;
use App\Models\Admin\V2\WmsDataPermission;
use App\Models\Admin\V2\WmsLogisticsCompany;
use App\Models\Admin\V2\WmsLogisticsProduct;
use App\Models\Admin\V2\WmsOrder as V2WmsOrder;
use App\Models\Admin\V2\WmsOrderDeliverStatement;
use App\Models\Admin\V2\WmsOrderDetail;
use App\Models\Admin\V2\WmsOrderItem;
use App\Models\Admin\V2\WmsOrderStatement;
use App\Models\Admin\V2\WmsPreAllocationDetail;
use App\Models\Admin\V2\WmsProductStockLog;
use App\Models\Admin\V2\WmsPurchaseStatement;
use App\Models\Admin\V2\WmsPutawayDetail;
use App\Models\Admin\V2\WmsPutawayList;
use App\Models\Admin\V2\WmsQualityConfirmList;
use App\Models\Admin\V2\WmsQualityDetail;
use App\Models\Admin\V2\WmsQualityList;
use App\Models\Admin\V2\WmsShenduInv;
use App\Models\Admin\V2\WmsShippingCancel;
use App\Models\Admin\V2\WmsShippingCancelDetail;
use App\Models\Admin\V2\WmsShop;
use App\Models\Admin\V2\WmsStockCheckBill;
use App\Models\Admin\V2\WmsStockCheckDetail;
use App\Models\Admin\V2\WmsStockCheckDifference;
use App\Models\Admin\V2\WmsStockCheckList;
use App\Models\Admin\V2\WmsStockCheckRequest;
use App\Models\Admin\V2\WmsStockCheckRequestDetail;
use App\Models\Admin\V2\WmsStockDifference;
use App\Models\Admin\V2\WmsStockLog;
use App\Models\Admin\V2\WmsStockMoveDetail;
use App\Models\Admin\V2\WmsStockMoveItem;
use App\Models\Admin\V2\WmsStockMoveList;
use App\Models\Admin\V2\WmsWarehouseArea;
use App\Models\Admin\V2\WmsWithdrawRequest;
use App\Models\AdminUser;
use App\Models\AdminUsers as ModelsAdminUsers;
use App\Models\Organization;
use App\Models\ProductBrand;
use App\Models\Roles;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use stdClass;
use WmsOrder;

/**
 * 数据初始化
 */
class Init extends BaseLogic
{
    protected $tenant_id;
    protected $stockLogMax = 4763349; //总库存流水的上限id
    protected $uniqLogMax = 914463; //唯一码打印记录的流水上限id
    protected $productStockLogMax = 3079907; //产品库存流水的上限id

    public function __construct($tenant_id)
    {
        $this->tenant_id = $tenant_id;
        // $this->stockLogMax = WmsStockLog::max('id');
    }

    function _init($type, $callback)
    {
        while (true) {
            dump(sprintf('%s  | memory:  %s M', date('Y-m-d H:i:s'), memory_get_usage() / 1024 / 1024));
            $find = ImportTemporary::where('type', $type)->first();
            if (!$find) break;
            // $find->tenant_id = 550960;
            call_user_func($callback, $find);
        }
    }
    function _init2($type, $callback)
    {
        while (true) {
            dump(sprintf('%s  | memory:  %s M', date('Y-m-d H:i:s'), memory_get_usage() / 1024 / 1024));
            $find = ImportTemporary::where('type', $type)->orderBy('id', 'desc')->first();
            if (!$find) break;
            // $find->tenant_id = 550960;
            call_user_func($callback, $find);
        }
    }
    function user()
    {
        $tenant_id = $this->tenant_id;
        $user = ModelsAdminUsers::where('tenant_id', $tenant_id)->first();
        dump('用户初始化......');
        $this->_init(2, function ($find) use ($tenant_id, $user) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                foreach ($data as $item) {
                    ModelsAdminUsers::updateOrCreate([
                        'user_code' => $item[0],
                        'tenant_id' => $tenant_id,
                    ], [
                        'username' => $item[2],
                        'name' => $item[3],
                        'email' => $item[7] ?: $item[8] . '@carryme.com',
                        'mobile' => $item[8],
                        'status' => $item[9] == '启用' ? 1 : 2,
                        'roles_id' => 0,
                        'p_id' => $user->id,
                        'org_code' => $user->org_code,
                        'password' => Hash::make(substr($item[8], -6)),
                    ]);
                }
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
        });
    }

    function role()
    {
        $tenant_id = $this->tenant_id;
        dump('角色初始化......');
        $this->_init(3, function ($find) use ($tenant_id) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                foreach ($data as $item) {
                    Roles::updateOrCreate([
                        'role_code' => $item[0],
                        'tenant_id' => $tenant_id,
                    ], [
                        'name' => $item[2],
                        'display_name' => $item[2],
                        'type' => 3,
                    ]);
                }
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
        });
    }

    function warehouse()
    {
        $tenant_id = $this->tenant_id;
        $users = ModelsAdminUsers::where('tenant_id', $tenant_id)->get()->keyBy('username')->toArray();
        dump('仓库初始化......');
        $this->_init(4, function ($find) use ($users, $tenant_id) {
            $type = ['销售仓' => 0, '退货仓' => 1, '换季仓' => 2, '虚拟仓' => 3,];
            $attribute = ['自有仓' => 0, '云仓' => 1];
            $data = $find->data;
            try {
                DB::beginTransaction();
                foreach ($data as $item) {
                    $warehouse = Warehouse::updateOrCreate([
                        'warehouse_code' => $item[1],
                        'tenant_id' => $tenant_id,
                    ], [
                        'warehouse_name' => $item[2],
                        'type' => $type[$item[4]],
                        'attribute' => $attribute[$item[0]],
                        'status' => $item[3] == '启用' ? 1 : 0,
                        'admin_user_id' => $users[$item[11]]['id'] ?? 0,
                    ]);
                    WmsDataPermission::addWarehouse($warehouse);
                }
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
        });
    }

    function area()
    {
        $tenant_id = $this->tenant_id;
        $users = ModelsAdminUsers::where('tenant_id', $tenant_id)->get()->keyBy('username')->toArray();
        dump('库区初始化......');
        $this->_init(5, function ($find) use ($users, $tenant_id) {
            $map = ['架上库区' => 0, '收货暂存区' => 1, '质检暂存区' => 2, '下架暂存区' => 3,];
            $purpose = ['暂存' => 0, '拣选' => 1, '爆品' => 2, '备货' => 3,];
            $data = $find->data;

            try {
                DB::beginTransaction();
                foreach ($data as $item) {
                    WmsWarehouseArea::updateOrCreate([
                        'tenant_id' => $tenant_id,
                        'area_code' => $item[2],
                        'warehouse_code' => $item[0],
                    ], [
                        'area_name' => $item[3],
                        'type' => $map[$item[4]],
                        'purpose' => $purpose[$item[5]],
                        'status' => $item[7] == '启用' ? 1 : 0,
                        'remark' => $item[8],
                        'admin_user_id' => $users[$item[11]]['id'] ?? 0,
                        'created_at' => $item[10],
                        'updated_at' => $item[12],
                    ]);
                }
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
        });
    }

    function locationCode()
    {
        $tenant_id = $this->tenant_id;
        dump('位置码初始化......');
        $this->_init(6, function ($find) use ($tenant_id) {
            $type = ['混合货位' => 0, '整箱货位' => 1, '拆零货位' => 2,];
            $data = $find->data;
            try {
                DB::beginTransaction();
                foreach ($data as $item) {
                    WarehouseLocation::updateOrCreate([
                        'area_code' => $item[2],
                        'warehouse_code' => $item[0],
                        'location_code' => $item[4],
                        'tenant_id' => $tenant_id,
                    ], [
                        'pick_number' => $item[5],
                        'type' => $type[$item[8]] ?? 0,
                        'volume' => $item[11] ?: 0,
                        'status' => $item[6] == '是' ? 1 : 0,
                        'is_able' => $item[7] == '否' ? 0 : 1,
                        'remark' => $item[13],
                    ]);
                }
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
        });
    }

    function suplier()
    {
        $tenant_id = $this->tenant_id;
        $users = ModelsAdminUsers::where('tenant_id', $tenant_id)->get()->keyBy('username')->toArray();

        dump('供应商初始化......');
        $this->_init(7, function ($find) use ($tenant_id, $users) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                foreach ($data as $item) {
                    $supplier = Supplier::updateOrCreate([
                        'sup_code' => $item[0],
                        'tenant_id' => $tenant_id,
                    ], [
                        'name' => $item[1],
                        'sup_status' => $item[2] == '已通过' ? 2 : 1,
                        'type' => $item[4] == '个人' ? 1 : 2,
                        'created_at' => $item[3],
                        'status' => $item[7] == '启用' ? 1 : 0,
                        'approver' => $users[$item[8]]['id'] ?? 0,
                        'approved_at' => $item[9],
                    ]);
                    WmsDataPermission::addSupplier($supplier);
                }
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
        });
    }

    function suplier2()
    {
        $tenant_id = $this->tenant_id;
        dump('供应商信息补充......');
        $this->_init(22, function ($find) use ($tenant_id) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                foreach ($data as $item) {
                    Supplier::where(['sup_code' => $item[0], 'tenant_id' => $tenant_id,])->update([
                        'contact_name' => $item[8],
                        'contact_phone' => $item[9],
                        'contact_landline' => $item[10],
                        'contact_addr' => $item[15],
                    ]);
                }
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
        });
    }


    function shop()
    {
        $tenant_id = $this->tenant_id;
        $users = ModelsAdminUsers::where('tenant_id', $tenant_id)->get()->keyBy('username')->toArray();

        dump('店铺初始化......');
        $this->_init(8, function ($find) use ($users, $tenant_id) {
            $channels = ['其他' => 1, '得物跨境' => 2,];
            $data = $find->data;

            try {
                DB::beginTransaction();
                foreach ($data as $item) {
                    $shop = WmsShop::updateOrCreate([
                        'code' => $item[0],
                        'tenant_id' => $tenant_id,
                    ], [
                        'name' => $item[1],
                        'sale_channel' => $channels[$item[2]] ?? 0,
                        'manager_id' => $users[$item[4]]['id'] ?? 0,
                        'status' => $item[7] == '启用' ? 1 : 0,
                        'remark' => $item[8],
                    ]);
                    WmsDataPermission::addShop($shop);
                }
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
        });
    }

    function userAddPermission()
    {
        dump('给用户进行数据授权......');
        $user_codes = AdminUser::where('tenant_id', $this->tenant_id)->pluck('user_code');
        if ($user_codes->count() == 0) return;

        $root = WmsDataPermission::where(['tenant_id' => $this->tenant_id, 'parent_code' => ''])->first();
        if (!$root) return;

        $logic = new DataPermission();
        $logic->authorize(['org_codes' => [$root->code], 'user_codes' => $user_codes->toArray(), 'tenant_id' => $this->tenant_id, 'user_id' => 0]);
    }

    function category()
    {
        $tenant_id = $this->tenant_id;
        dump('商品分类初始化......');
        $this->_init(9, function ($find) use ($tenant_id) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                foreach ($data as $item) {
                    if (in_array($item[1], ['根分类/', 'FIT株式会社'])) continue;
                    ProductCategory::updateOrCreate([
                        'code' => $item[0],
                        'tenant_id' => $tenant_id,
                    ], [
                        'name' => $item[1],
                        'sort' => $item[2],
                        'status' => $item[3] == '启用' ? 1 : 0,
                        'note' => $item[5],
                    ]);
                }
                $category = ProductCategory::where('tenant_id', $tenant_id)->get()->keyBy('name')->toArray();
                foreach ($data as $item) {
                    if (in_array($item[1], ['根分类/', 'FIT株式会社'])) continue;
                    $cate = ProductCategory::where('tenant_id', $tenant_id)->where('code', $item[0])->first();
                    $pid = $category[$item[4]]['id'] ?? 0;
                    $parent = null;
                    if ($pid) $parent = ProductCategory::where('tenant_id', $tenant_id)->where('id', $pid)->first();
                    $path = $parent ? sprintf('%s-%d', $parent->path, $cate->id) : $cate->id;
                    $cate->update([
                        'pid' => $pid,
                        'path' => $path,
                        'level' => $pid ? 2 : 1,
                    ]);
                }

                foreach ($data as $item) {
                    if (in_array($item[1], ['根分类/', 'FIT株式会社'])) continue;
                    $cate = ProductCategory::where('tenant_id', $tenant_id)->where('code', $item[0])->first();
                    $pid = $category[$item[4]]['id'] ?? 0;
                    $parent = null;
                    if ($pid) $parent = ProductCategory::where('tenant_id', $tenant_id)->where('id', $pid)->first();
                    $path = $parent ? sprintf('%s-%d', $parent->path, $cate->id) : $cate->id;
                    $cate->update([
                        'pid' => $pid,
                        'path' => $path,
                        'level' => $pid ? 2 : 1,
                    ]);
                }
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
        });
    }

    function brand()
    {
        $tenant_id = $this->tenant_id;
        dump('商品品牌初始化......');
        $this->_init(10, function ($find) use ($tenant_id) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                foreach ($data as $item) {
                    ProductBrands::updateOrCreate([
                        'code' => $item[0],
                        'tenant_id' => $tenant_id,
                    ], [
                        'name' => $item[1],
                        'status' => $item[2] == '启用' ? 1 : 0,
                        'note' => $item[3],
                    ]);
                }
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
        });
    }
    function product()
    {
        $tenant_id = $this->tenant_id;
        $category = ProductCategory::where('tenant_id', $tenant_id)->get()->keyBy('name')->toArray();
        $brand = ProductBrands::where('tenant_id', $tenant_id)->get()->keyBy('name')->toArray();
        dump('商品初始化......');
        $this->_init(11, function ($find) use ($tenant_id, $category, $brand) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                $arr = [];
                foreach ($data as $item) {
                    // Product::updateOrCreate([
                    //     'product_sn' => $item[0],
                    //     'tenant_id' => $tenant_id,
                    // ], [
                    //     'name' => $item[1],
                    //     'category_id' => $category[$item[2]]['id'] ?? 0,
                    //     'type' => $item[3],
                    //     'brand_id' => $brand[$item[7]]['id'] ?? 0,
                    //     'status' => $item[9] == '启用' ? 1 : 0,
                    //     'note' => $item[11],
                    // ]);
                    $arr[] = [
                        'product_sn' => $item[0],
                        'tenant_id' => $tenant_id,
                        'name' => $item[1],
                        'category_id' => $category[$item[2]]['id'] ?? 0,
                        'type' => $item[3],
                        'brand_id' => $brand[$item[7]]['id'] ?? 0,
                        'status' => $item[9] == '启用' ? 1 : 0,
                        'note' => $item[11],
                    ];
                    if (count($arr) > 500) {
                        DB::table('wms_product')->insert($arr);
                        $arr = [];
                    }
                }
                if ($arr) DB::table('wms_product')->insert($arr);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
        });
    }
    function barcode()
    {
        $tenant_id = $this->tenant_id;
        $product = Product::where('tenant_id', $tenant_id)->get()->keyBy('product_sn')->toArray();

        dump('商品条码初始化......');
        $this->_init(12, function ($find) use ($tenant_id, $product) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                $arr = [];
                foreach ($data as $item) {
                    // ProductSpecAndBar::updateOrCreate([
                    //     'product_id' => $product[$item[1]]['id'] ?? 0,
                    //     'bar_code' => $item[0],
                    //     'tenant_id' => $tenant_id,
                    // ], [
                    //     'sku' => $item[3],
                    //     'code' => $item[3],
                    //     'spec_one' => $item[4],
                    //     'type' => 1,
                    // ]);
                    $arr[] = [
                        'product_id' => $product[$item[1]]['id'] ?? 0,
                        'bar_code' => $item[0],
                        'tenant_id' => $tenant_id,
                        'sku' => $item[3],
                        'code' => $item[3],
                        'spec_one' => $item[4],
                        'type' => 1,
                    ];
                    if (count($arr) > 500) {
                        DB::table('wms_spec_and_bar')->insert($arr);
                        $arr = [];
                    }
                }
                if ($arr) DB::table('wms_spec_and_bar')->insert($arr);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
        });
    }
    function barcode2()
    {
        $tenant_id = $this->tenant_id;
        // $product = Product::where('tenant_id', $tenant_id)->get()->keyBy('product_sn')->toArray();
        $category = ProductCategory::where('tenant_id', $tenant_id)->get()->keyBy('code')->toArray();
        $brand = ProductBrands::where('tenant_id', $tenant_id)->get()->keyBy('code')->toArray();
        dump('商品条码第二规格初始化......');
        $this->_init(17, function ($find) use ($tenant_id, $category, $brand) {
            $data = $find->data;
            $product_sn = '';

            // 0:实物 1：虚拟 2：赠品 3：附属品 4：其他
            $type = ['实物产品' => 0, '虚拟产品' => 1, '分销商品' => 4, '赠品' => 2, '包材' => 4, '耗材' => 4, '辅料' => 4, '附属品' => 3, '组合商品' => 4, '残次品' => 4, '其它' => 4,];
            $parent = [];
            try {
                DB::beginTransaction();
                $product = null;
                foreach ($data as $item) {
                    if ($item[0]) {
                        $product_sn = $item[0];
                        $parent = array_slice($item, 0, 15);
                        $product = Product::where('tenant_id', $tenant_id)->where('product_sn', $product_sn)->first();
                        // 补充商品信息
                        if (!$product) {
                            $product = Product::create([
                                'product_sn' => $product_sn,
                                'name' => $parent[1],
                                'category_id' => $category[$parent[3]]['id'] ?? 0,
                                'type' => $type[$parent[4]],
                                'brand_id' => $brand[$parent[5]]['id'] ?? 0,
                                'tenant_id' => $tenant_id,
                            ]);
                        }
                    }
                    $arr = explode(',', $item[17]);
                    $sku = sprintf('%s#%s', $product_sn, $item[15]);
                    foreach ($arr as $barcode) {
                        $spec = ProductSpecAndBar::where([
                            'product_id' => $product->id,
                            'sku' => $sku,
                            'tenant_id' => $tenant_id,
                        ])->first();
                        if (!$spec) {
                            ProductSpecAndBar::create([
                                'product_id' => $product->id,
                                'bar_code' => $barcode,
                                'tenant_id' => $tenant_id,
                                'spec_one' => $item[15],
                                'code' => $item[16],
                                'spec_two' => $item[16],
                                'type' => 1,
                            ]);
                        } else {
                            $update = ['code' => $item[16], 'spec_two' => $item[16]];
                            if (!$spec->sku) $update['sku'] = $sku;
                            if (!$spec->spec_one) $update['spec_one'] = $item[15];
                            $spec->update($update);
                        }
                    }
                }
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
        });
        ProductSpecAndBar::whereRaw('sku=spec_two')->update(['spec_two' => ""]);
    }

    function buyOrder()
    {
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $tenant_id];
        $users = ModelsAdminUsers::where($where)->get()->keyBy('username')->toArray();
        $warehouse = Warehouse::where($where)->get()->keyBy('warehouse_name')->toArray();
        $supplier = Supplier::where($where)->get()->keyBy('name')->toArray();

        dump('采购单初始化......');
        $this->_init(13, function ($find) use ($tenant_id, $users, $warehouse, $supplier) {
            $data = $find->data;
            $where = ['tenant_id' => $tenant_id];

            $status = ['暂存' => 0, '审核中' => 1, '已审核' => 2,  '已确认' => 4, '已取消' => 5, '已驳回' => 6];
            $receive_status = ['待收货' => 0, '已收货' => 1, '部分收货' => 2];



            try {
                $order  = null;
                DB::beginTransaction();
                $arr1 = [];
                $arr2 = [];
                $parent = [];
                foreach ($data as $item) {
                    if ($item[2]) {
                        $parent = [
                            'code' => $item[2],
                            'status' => $status[$item[0]],
                            'receive_status' => $receive_status[$item[1]],
                            'sup_id' => self::getSupId($item[3], $supplier),
                            'warehouse_code' => $warehouse[$item[4]]['warehouse_code'] ?? '',
                            'source_type' => $item[5] == '手工创建' ? 1 : 0,
                            'third_code' => $item[6],
                            'order_at' => $item[7],
                            'num' => $item[8],
                            'amount' => $item[9],
                            'received_num' => $item[11],
                            'estimate_receive_at' => $item[13],
                            'order_user' => $users[$item[14]]['id'] ?? 0,
                            'remark' => $item[15],
                            'created_user' => $users[$item[16]]['id'] ?? 0,
                            'created_at' => $item[17],
                            'updated_user' => $users[$item[18]]['id'] ?? 0,
                            'updated_at' => $item[19],
                            'pay_status' => 1,
                            'tenant_id' => $tenant_id,
                        ];
                        $arr1[] = $parent;
                        if (count($arr1) > 500) {
                            DB::table('wms_purchase_orders')->insert($arr1);
                            $arr1 = [];
                        }
                        // $order = PurchaseOrders::create([
                        //     'code' => $item[2],
                        //     'status' => $status[$item[0]],
                        //     'receive_status' => $receive_status[$item[1]],
                        //     'sup_id' => $supplier[$item[3]]['id'] ?? 0,
                        //     'warehouse_code' => $warehouse[$item[4]]['warehouse_code'] ?? '',
                        //     'source_type' => $item[5] == '手工创建' ? 1 : 0,
                        //     'third_code' => $item[6],
                        //     'order_at' => $item[7],
                        //     'num' => $item[8],
                        //     'amount' => $item[9],
                        //     'received_num' => $item[11],
                        //     'estimate_receive_at' => $item[13],
                        //     'order_user' => $users[$item[14]]['id'] ?? 0,
                        //     'remark' => $item[15],
                        //     'created_user' => $users[$item[16]]['id'] ?? 0,
                        //     'created_at' => $item[17],
                        //     'updated_user' => $users[$item[18]]['id'] ?? 0,
                        //     'updated_at' => $item[19],
                        //     'pay_status' => 1,
                        //     'tenant_id' => $tenant_id,
                        // ]);
                    }

                    // PurchaseDetails::create([
                    //     'buy_code' => $order->code,
                    //     'tenant_id' => $tenant_id,
                    //     'sku' => $item[20],
                    //     'buy_price' => $item[25],
                    //     'num' => $item[24],
                    //     'recv_num' => $item[27],
                    //     'normal_count' => $item[28],
                    //     'flaw_count' => $item[29],
                    //     'remark' => $item[32],
                    //     'tenant_id' => $tenant_id,
                    // ]);
                    $arr2[] = [
                        'buy_code' => $parent['code'],
                        'tenant_id' => $tenant_id,
                        'sku' => $item[20],
                        'buy_price' => $item[25],
                        'num' => $item[24],
                        'recv_num' => $item[27],
                        'normal_count' => $item[28],
                        'flaw_count' => $item[29],
                        'remark' => $item[32],
                        'tenant_id' => $tenant_id,
                    ];
                    if (count($arr2) > 500) {
                        DB::table('wms_purchase_details')->insert($arr2);
                        $arr2 = [];
                    }
                }
                if ($arr1) DB::table('wms_purchase_orders')->insert($arr1);
                if ($arr2) DB::table('wms_purchase_details')->insert($arr2);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
        });
        // 根据sku更新商品条形码
        DB::statement("UPDATE wms_purchase_details d ,wms_spec_and_bar sku SET d.bar_code=sku.bar_code WHERE d.sku = sku.sku AND d.bar_code='' AND d.tenant_id=$tenant_id AND sku.tenant_id=$tenant_id");
    }

    function regist()
    {
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $tenant_id];
        $users = ModelsAdminUsers::where($where)->get()->keyBy('username')->toArray();
        $warehouse = Warehouse::where($where)->select(['warehouse_name', 'warehouse_code'])->get()->keyBy('warehouse_name')->toArray();

        dump('到货登记单初始化......');
        $this->_init(14, function ($find) use ($tenant_id, $users, $warehouse) {
            $data = $find->data;

            $arr_type = ['采购到货登记' => 1, '调拨到货登记' => 2, '退货到货登记' => 3, '其它到货登记' => 4];
            $doc_status = ['已审核' => 1, '已取消' => 2, '已作废' => 3, '已确认' => 4];
            $arr_status = ['待匹配' => 1, '待收货' => 2, '收货中' => 3, '已完成' => 4,];
            try {
                DB::beginTransaction();
                $arr = [];
                foreach ($data as $item) {
                    if (!$item[0]) continue;
                    // ArrivalRegist::create([
                    //     'arr_code' => $item[10],
                    //     'lot_num' => $item[11],
                    //     'arr_name' => $item[4],
                    //     'warehouse_code' => $warehouse[$item[0]]['warehouse_code'],
                    //     'ib_code' => $item[2],
                    //     'third_doc_code' => $item[3],
                    //     'log_number' => $item[4],
                    //     'arr_num' => $item[5],
                    //     'recv_num' => $item[7],
                    //     'confirm_num' => $item[8],
                    //     'arr_type' => $item[9] ? $arr_type[$item[9]] : 0,
                    //     'arr_code' => $item[10],
                    //     'doc_status' => $doc_status[$item[12]],
                    //     'arr_status' => $arr_status[$item[13]],
                    //     'uni_num_count' => $item[14],
                    //     'remark' => $item[21],
                    //     'created_user' => $users[$item[34]]['id'] ?? 0,
                    //     'created_at' => $item[35],
                    //     'updated_user' => $users[$item[36]]['id'] ?? 0,
                    //     'updated_at' => $item[37],
                    //     'tenant_id' => $tenant_id,
                    // ]);
                    $arr[] = [
                        'arr_code' => $item[10],
                        'lot_num' => $item[11],
                        'arr_name' => $item[4],
                        'warehouse_code' => $warehouse[$item[0]]['warehouse_code'],
                        'ib_code' => $item[2],
                        'third_doc_code' => $item[3],
                        'log_number' => $item[4],
                        'arr_num' => $item[5],
                        'recv_num' => $item[7],
                        'confirm_num' => $item[8],
                        'arr_type' => $item[9] ? $arr_type[$item[9]] : 0,
                        'arr_code' => $item[10],
                        'doc_status' => $doc_status[$item[12]],
                        'arr_status' => $arr_status[$item[13]],
                        'uni_num_count' => $item[14],
                        'remark' => $item[21],
                        'created_user' => $users[$item[34]]['id'] ?? 0,
                        'created_at' => $item[35],
                        'updated_user' => $users[$item[36]]['id'] ?? 0,
                        'updated_at' => $item[37],
                        'tenant_id' => $tenant_id,
                    ];
                    if (count($arr) > 500) {
                        DB::table('wms_arrival_regist')->insert($arr);
                        $arr = [];
                    }
                }
                if ($arr) DB::table('wms_arrival_regist')->insert($arr);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
        });
    }

    function receive()
    {
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $tenant_id];
        $users = ModelsAdminUsers::where($where)->selectRaw('id,username')->get()->keyBy('username')->toArray();
        $warehouse = Warehouse::where($where)->selectRaw('warehouse_code,warehouse_name')->get()->keyBy('warehouse_name')->toArray();

        dump('收货单初始化......');
        $this->_init(19, function ($find) use ($tenant_id, $users, $warehouse) {
            $data = $find->data;
            $where = ['tenant_id' => $tenant_id];
            $quality_level = ['优' => 'A', '良' => 'B', '一级' => 'C', '二级' => 'D', '三级' => 'E',];
            $recv_type = ['采购收货' => 1, '调拨收货' => 2, '退货收货' => 3, '其他收货' => 4];
            $doc_status = ['暂存' => 1, '已审核' => 2, '已作废' => 3];
            $recv_status = ['收货中' => 0, '已完成' => 1, '已收货' => 1];
            $recv_methods = ['逐件收货' => 1, '其他' => 2];
            $arr_code = '';
            $warehouse_name = '';
            $recv_id = 0;
            try {
                DB::beginTransaction();
                foreach ($data as $item) {
                    if ($item[0]) {
                        // if ($item[3] == '已收货') dump($item);
                        $arr_code = $item[4];
                        $warehouse_name = $item[5];
                        $regist = ArrivalRegist::where($where)->where('arr_code', $arr_code)->first();
                        $receive = RecvOrder::updateOrCreate([
                            'recv_code' => $item[1],
                            'tenant_id' => $tenant_id,
                            'warehouse_code' => $warehouse[$warehouse_name]['warehouse_code'] ?? '',
                        ], [
                            'recv_type' => $recv_type[$item[0]],
                            'source_code' => $item[4],
                            'doc_status' => $doc_status[$item[2]],
                            'recv_status' => $recv_status[$item[3]],
                            'arr_id' => $regist ? $regist->id : 0,
                            'recv_num' => $item[6],
                            'created_user' => $users[$item[8]]['id'] ?? 0,
                            'created_at' => $item[9],
                            'done_at' => $item[10],
                            'recv_methods' => $recv_methods[$item[11]],
                            'updated_user' => $users[$item[15]]['id'] ?? 0,
                            'updated_at' => $item[16],
                        ]);
                        $recv_id = $receive->id;
                    }

                    $quality_type = $this->quality_type($item[21]);
                    $quality_level = $this->quality_level($item[22]);

                    $logs = WmsStockLog::where([
                        'operation' => 1, 'source_code' => $receive->recv_code, 'sku' => $item[17], 'quality_type' => $quality_type, 'quality_level' => $quality_level, 'warehouse_code' => $receive->warehouse_code, 'tenant_id' => $tenant_id,
                    ])->get();
                    foreach ($logs as $log) {

                        // 确认供应商记录
                        $sup_log = WmsStockLog::where([
                            'operation' => 4, 'origin_code' => $regist->arr_code, 'sku' => $item[17], 'uniq_code' => $log->uniq_code,  'warehouse_code' => $receive->warehouse_code,
                        ])->first();

                        $p_where = ['tenant_id' => $tenant_id, 'third_no' => $regist->arr_code, 'batch_no' => $log->batch_no, 'sku' => $item[17], 'quality_type' => $log->quality_type, 'node' => 1];
                        if ($log->quality_type == 2) $p_where['uniq_code'] = $log->uniq_code;
                        // 确认入库记录
                        $product_log = WmsProductStockLog::where($p_where)->first();
                        // 入库单
                        $ib  = $product_log ? IbOrder::where(['ib_code' => $product_log->source_code, 'tenant_id' => $tenant_id])->first() : null;
                        RecvDetail::create([
                            'arr_id' => $receive->arr_id, //登记单id
                            'recv_id' => $recv_id, //收货单id
                            'ib_id' => $ib ? $ib->id : 0, //入库单id
                            'sku' => $item[17],
                            'bar_code' => $item[23],
                            'uniq_code' => $log->uniq_code,
                            'lot_num' => $log->batch_no,
                            'warehouse_code' => $log->warehouse_code,
                            'location_code' => $log->location_code,
                            'quality_level' => $log->quality_level,
                            'quality_type' => $log->quality_level == 'A' ? 1 : 2,
                            'created_user' => $log->create_user_id,
                            'sup_id' => $sup_log ? $sup_log->sup_id : 0,
                            'tenant_id' => $tenant_id,
                            'created_at' => $log->created_at,
                            'done_at' => $log->created_at,
                            'updated_user' => $log->create_user_id,
                            'updated_at' => $log->created_at,
                            'inv_type' => $product_log ? $product_log->inv_type : 0,
                            'buy_price' => $product_log ? $product_log->cost_price : 0,
                            'ib_confirm' => $ib ? 1 : 0,
                            'sup_confirm' => $sup_log ? 1 : 0,
                            'is_qc' => ($regist->doc_status == 4 && $regist->arr_status == 4) ? 1 : 0,
                            'is_putway' => ($regist->doc_status == 4 && $regist->arr_status == 4) ? 1 : 0,
                        ]);
                    }
                }
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
        });
        DB::statement("UPDATE wms_recv_detail d ,wms_spec_and_bar sku SET d.bar_code=sku.bar_code WHERE  d.sku=sku.sku AND  d.tenant_id=$tenant_id AND sku.tenant_id=$tenant_id");
    }

    function instock()
    {
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $tenant_id];
        $users = ModelsAdminUsers::where($where)->selectRaw('id,username')->get()->keyBy('username')->toArray();
        $warehouse = Warehouse::where($where)->selectRaw('warehouse_code,warehouse_name')->get()->keyBy('warehouse_name')->toArray();
        $supplier = Supplier::where($where)->selectRaw('id,name')->get()->keyBy('name')->toArray();

        dump('入库单初始化......');
        $this->_init(15, function ($find) use ($tenant_id, $users, $warehouse, $supplier) {
            $data = $find->data;


            $ib_type = ['采购入库' => 1, '调拨入库' => 2, '退货入库' => 3, '其他入库' => 4];
            $doc_status = ['已审核' => 1, '已取消' => 2, '已确认' => 3];
            $recv_status = ['待收货' => 1, '部分收货' => 2, '已收货' => 3,];
            $ib_code = '';
            $bar_code = '';
            $arr1 = [];
            $arr2 = [];
            try {
                DB::beginTransaction();
                foreach ($data as $item) {
                    // 更新入库单
                    if ($item[1]) {
                        $ib_code = $item[1];
                        $regist = null;
                        if ($item[4]) {
                            if ($item['0'] == '采购入库')
                                $regist = ArrivalRegist::where(['arr_code' => $item[4]])->first();
                            else
                                $regist = ArrivalRegist::where(['ib_code' => $ib_code])->first();
                        }
                        $arr1[] = [
                            'ib_code' => $ib_code,
                            'tenant_id' => $tenant_id,
                            'warehouse_code' => $warehouse[$item[7]]['warehouse_code'],
                            'ib_type' => $ib_type[$item[0]],
                            'source_code' => $item[2],
                            'erp_no' => $item[3],
                            'third_no' => $item[4],
                            'doc_status' => $doc_status[$item[5]],
                            'recv_status' => $recv_status[$item[6]],
                            're_total' => $item[8],
                            'rd_total' => $item[9],
                            'normal_count' => $item[10],
                            'flaw_count' => $item[11],
                            'deliver_no' => $item[13],
                            'paysuccess_time' => $item[27],
                            'remark' => $item[32],
                            'created_user' => $users[$item[35]]['id'] ?? 0,
                            'created_at' => $item[36],
                            'updated_user' => $users[$item[37]]['id'] ?? 0,
                            'updated_at' => $item[38],
                            'arr_id' => $regist ? $regist->id : 0,
                        ];
                        if (count($arr1) > 500) {
                            DB::table('wms_ib_order')->insert($arr1);
                            $arr1 = [];
                        }
                    }

                    // 更新入库单详情
                    $quality_type = $item[49] == '正品' ? 1 : 2;
                    $quality_level = $item[49] == '正品' ? 'A' : "B";
                    $arr2[] = [
                        'ib_code' => $ib_code,
                        'tenant_id' => $tenant_id,
                        'sku' => $item[40],
                        'sup_id' => self::getSupId($item[51], $supplier),
                        'quality_level' => $quality_level,
                        'bar_code' => $bar_code,
                        'buy_price' => $item[47],
                        'quality_type' => $quality_type,
                        're_total' => $item[52],
                        'rd_total' => $item[53],
                        'normal_count' => $item[54],
                        'flaw_count' => $item[55],
                        'remark' => $item[58],
                    ];
                    if (count($arr2) > 500) {
                        DB::table('wms_ib_detail')->insert($arr2);
                        $arr2 = [];
                    }
                }
                if ($arr1) DB::table('wms_ib_order')->insert($arr1);
                if ($arr2) DB::table('wms_ib_detail')->insert($arr2);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
        });
        DB::statement('UPDATE wms_ib_detail ib ,wms_spec_and_bar sb  SET ib.bar_code=sb.bar_code WHERE ib.sku = sb.sku and ib.bar_code=""');
    }


    function printLog()
    {
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $tenant_id];
        $users = ModelsAdminUsers::where($where)->get()->keyBy('username')->toArray();

        dump('唯一码打印记录初始化......');
        $this->_init(16, function ($find) use ($users, $tenant_id) {
            // $id = UniqCodePrintLog::where('tenant_id', $tenant_id)->min('id');
            // $id = (!$id) ? $this->uniqLogMax : $id - 1;
            $data = $find->data;
            try {
                DB::beginTransaction();
                $arr = [];
                foreach ($data as $item) {
                    // $log = UniqCodePrintLog::where(['uniq_code' => $item[3], 'tenant_id' => $tenant_id,])->first();
                    // if ($log) continue;
                    // UniqCodePrintLog::create([
                    //     'id' => $id--,
                    //     'warehouse_name' => $item[0],
                    //     'arr_code' => $item[1],
                    //     'bar_code' => $item[2],
                    //     'uniq_code' => $item[3],
                    //     'print_count' => $item[4],
                    //     'created_user' => $users[$item[5]]['id'] ?? 0,
                    //     'cre_user_name' => $item[5],
                    //     'created_at' => $item[6],
                    //     'tenant_id' => $tenant_id,
                    //     'updated_user' => $users[$item[11]]['id'] ?? 0,
                    //     'upd_user_name' => $item[11],
                    //     'updated_at' => $item[12],
                    // ]);
                    $arr[] = [
                        // 'id' => $id--,
                        'warehouse_name' => $item[0],
                        'arr_code' => $item[1],
                        'bar_code' => $item[2],
                        'uniq_code' => $item[3],
                        'print_count' => $item[4],
                        'created_user' => $users[$item[5]]['id'] ?? 0,
                        'cre_user_name' => $item[5],
                        'created_at' => $item[6],
                        'tenant_id' => $tenant_id,
                        'updated_user' => $users[$item[11]]['id'] ?? 0,
                        'upd_user_name' => $item[11],
                        'updated_at' => $item[12],
                    ];
                    if (count($arr) > 500) {
                        DB::table('wms_unicode_print_log')->insert($arr);
                        $arr = [];
                    }
                }
                if ($arr) DB::table('wms_unicode_print_log')->insert($arr);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
            unset($data);
        });
        // 更新登记单id
        DB::getPdo()->exec('UPDATE wms_unicode_print_log  log,wms_arrival_regist reg SET log.arr_id=reg.id WHERE log.arr_code = reg.arr_code  AND log.tenant_id=' . $this->tenant_id . ' AND reg.tenant_id=' . $this->tenant_id . ' AND log.arr_id=0');
    }


    function skuDetail()
    {
        $where = ['tenant_id' => $this->tenant_id];
        $supplier = Supplier::where($where)->get()->keyBy('name')->toArray();
        $areas = WmsWarehouseArea::where($where)->with('warehouse')->get()->map(function ($item) {
            // dump($item->toArray());
            return [
                'warehouse_name' => $item->warehouse ? $item->warehouse->warehouse_name : '',
                'warehouse_code' => $item->warehouse_code,
                'area_name' => $item->area_name,
                'area_code' => $item->area_code,
                'skey' => sprintf('%s_%s', $item->warehouse ? $item->warehouse->warehouse_name : '', $item->area_name)
            ];
        })->keyBy('skey')->toArray();
        $tenant_id = $this->tenant_id;

        dump('唯一码库存明细初始化......');
        $this->_init(1, function ($find) use ($supplier, $areas, $tenant_id) {
            $notice = [];
            $data = $find->data;
            $where = ['tenant_id' => $tenant_id];

            try {
                DB::beginTransaction();
                foreach ($data as $item) {

                    $area_name = $item[11] == '宁波出库区' ? '抖音库存' : $item[11];
                    $area = $areas[sprintf('%s_%s', $item[10], $area_name)] ?? [];
                    $area_code = $area ? $area['area_code'] : '';
                    $warehouse_code = $area ? $area['warehouse_code'] : '';
                    if (!$area_code) {
                        $notice[] = ['warehouse_name' => $item[10], 'area_name' => $item[11]];
                        continue;
                    }

                    $sup_id = self::getSupId($item[14], $supplier);
                    // 条形码为多个时，条形码找唯一码打印记录对应的条形码
                    // $bar_codes = explode(',', $item[5]);
                    $bar_code = $item[5];
                    // if (count($bar_codes) > 1 && $log) $bar_code = $log->bar_code;

                    // $tmp = [
                    //     'lot_num' => $item[6],
                    //     'bar_code' => $bar_code,
                    //     'location_code' => $item[12],
                    //     'quality_type' => $this->quality_type($item[7]),
                    //     'quality_level' => $this->quality_level($item[8]),
                    //     'recv_num' => $item[9],
                    //     'sup_id' => $sup_id,
                    //     'created_at' => $item[15],
                    // ];
                    // Inventory::updateOrCreate([
                    //     'warehouse_code' => $warehouse_code,
                    //     'area_code' => $area_code,
                    //     'uniq_code' => $item[0],
                    //     'tenant_id' => $tenant_id,
                    //     'sku' => $item[1],
                    //     'lot_num' => $item[6],
                    //     'bar_code' => $bar_code,
                    //     'location_code' => $item[12],
                    //     'quality_type' => $this->quality_type($item[7]),
                    //     'quality_level' => $this->quality_level($item[8]),
                    //     'recv_num' => $item[9],
                    //     'sup_id' => $sup_id,
                    //     'created_at' => $item[15],
                    // ], $tmp);
                    $arr[] = [
                        'warehouse_code' => $warehouse_code,
                        'area_code' => $area_code,
                        'uniq_code' => $item[0],
                        'tenant_id' => $tenant_id,
                        'sku' => $item[1],
                        'lot_num' => $item[6],
                        'bar_code' => $bar_code,
                        'location_code' => $item[12],
                        'quality_type' => $this->quality_type($item[7]),
                        'quality_level' => $this->quality_level($item[8]),
                        'recv_num' => $item[9],
                        'sup_id' => $sup_id,
                        'created_at' => $item[15],
                    ];
                    if (count($arr) > 500) {
                        DB::table('wms_inv_goods_detail')->insert($arr);
                        $arr = [];
                    }
                }
                if ($arr) DB::table('wms_inv_goods_detail')->insert($arr);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
            if ($notice) Log::channel('daily2')->info('仓库/库区不存在', $notice);
        });
    }

    function receiveOrderDetail()
    {
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $tenant_id];
        $users = ModelsAdminUsers::where($where)->selectRaw('id,username')->get()->keyBy('username')->toArray();
        $warehouse = Warehouse::where($where)->selectRaw('warehouse_code,warehouse_name')->get()->keyBy('warehouse_name')->toArray();

        dump('收货单明细初始化......');
        $this->_init(20, function ($find) use ($users, $warehouse, $tenant_id) {
            $data = $find->data;
            $where = ['tenant_id' => $tenant_id];

            $quality_level = ['优' => 'A', '良' => 'B', '一级' => 'C', '二级' => 'D', '三级' => 'E',];
            $arr_code = '';
            $warehouse_name = '';
            $recv_id = 0;
            $parent = [];
            $buy_id = 0;
            $ib_id = 0;
            $regist = null;
            $warehouse_code = '';
            $arr_id = 0;
            try {
                DB::beginTransaction();
                foreach ($data as $item) {

                    if ($item[0]) {
                        $arr_code = $item[4];
                        $warehouse_name = $item[5];
                        $warehouse_code = $warehouse[$warehouse_name]['warehouse_code'] ?? '';

                        $regist = ArrivalRegist::where($where)->where('warehouse_code', $warehouse_code)->where('arr_code', $arr_code)->first();
                        if (!$regist) {
                            Log::channel('daily2')->info('未找到登记单', compact('warehouse_name', 'arr_code'));
                            continue;
                        }
                        $arr_id = $regist ? $regist->id : 0;
                        $ib = IbOrder::where($where)->where('warehouse_code', $warehouse_code)->where('arr_id', $arr_id)->first();
                        $ib_id = $ib ? $ib->id : 0;
                        if ($regist->arr_type == 1 && $ib) {
                            $buy = PurchaseOrders::where($where)->where('warehouse_code', $warehouse_code)->where('code', $ib->source_code)->first();
                            $buy_id = $buy ? $buy->id : 0;
                        }

                        $receive = RecvOrder::where(['recv_code' => $item[1], 'tenant_id' => $tenant_id,])->where('warehouse_code', $warehouse_code)->first();
                        $recv_id = $receive->id;
                        $parent = array_slice($item, 0, 17);
                    }

                    $stocks = Inventory::where($where)->where([
                        'arr_id' => $arr_id,
                        'recv_id' => $recv_id,
                        'sku' => $item[17],
                        'warehouse_code' => $warehouse_code,
                        'quality_level' => $this->quality_level($item[22]),
                    ])->limit($item[26])->get();
                    if ($stocks->count() == 0) Log::channel('daily2')->info('未找到唯一码', [
                        'arr_id' => $arr_id,
                        'recv_id' => $recv_id,
                        'sku' => $item[17],
                        'warehouse_code' => $warehouse_code,
                        'quality_level' => $this->quality_level($item[22]),
                    ]);

                    foreach ($stocks as $unique) {
                        // 更新收货单单详情
                        RecvDetail::create([
                            'arr_id' => $arr_id, //登记单id
                            'recv_id' => $recv_id, //收货单id
                            'ib_id' => $ib_id, //入库单id
                            'buy_id' => $buy_id, //采购单id
                            'sku' => $item[17],
                            'bar_code' => $item[23],
                            'uniq_code' => $unique->uniq_code,
                            'lot_num' => $regist ? $regist->lot_num : '',
                            'warehouse_code' => $warehouse[$warehouse_name]['warehouse_code'] ?? '',
                            'area_code' => $unique->area_code,
                            'location_code' => $unique->location_code,
                            'quality_level' => $quality_level[$item[22]],
                            'quality_type' => $item[21] == '正品' ? 1 : 2,
                            'created_user' => $users[$parent[8]]['id'] ?? 0,
                            'sup_id' => $unique->sup_id,
                            'buy_price' => $unique->buy_price,
                            'sup_confirm' => $unique->sup_id > 0 ? 1 : 0,
                            'ib_confirm' => $unique->buy_price > 0 ? 1 : 0,
                            'is_qc' => $unique->is_qc,
                            'is_putway' => $unique->is_putway,
                            'tenant_id' => $tenant_id,
                            'created_at' => $parent[9],
                            'done_at' => $parent[10],
                            'updated_user' => $parent[15],
                            'updated_at' => $parent[16],
                        ]);
                    }
                }
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
        });
    }

    // 

    private function quality_type($origin)
    {
        if (!$origin) return 1;
        $map = ['正品' => 1, '正常' => 1, '疑似瑕疵' => 2, '疑似瑕疵品' => 2, '瑕疵' => 2, '瑕疵品' => 2,];
        return $map[$origin];
    }

    private function quality_level($origin, $type_name = '')
    {
        if ($type_name && $type_name == '疑似瑕疵品') return 'B';
        // if (empty($origin) && in_array($type_name ,['正品','优']) ) return 'A';
        if (empty($origin) && $type_name) {
            $type = $this->quality_type($type_name);
            return $type == 1 ? 'A' : "B";
        }
        if (!$origin && !$type_name) return 'A';

        $map = ['优' => 'A', '良' => 'A', '一级' => 'B', '二级' => 'C', '三级' => 'D',];
        return $map[$origin];
    }



    function stockLog()
    {
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        // $supplier = Supplier::where($where)->select('id', 'name')->get()->keyBy('name')->toArray();
        $supplier = null;
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();

        dump('总库存流水初始化......');
        $this->_init(0, function ($find) use ($tenant_id, &$supplier, $warehouse, $users) {
            // $id = WmsStockLog::where('tenant_id', $tenant_id)->min('id');
            // $id = (!$id) ? $this->stockLogMax : $id - 1;
            $map = [
                '收货' => 1, '质量类型调整' => 2, '质检' => 3, '供应商调整' => 4, '上架' => 5, '下架' => 6, '出库锁定' => 7, '配货' => 8, '发货' => 9, '移位上架' => 10, '移位下架' => 11, '少货寻回' => 12, '取消出库' => 13, '少货冻结' => 14, '快速移位' => 15, '产品调整' => 16, '重配释放' => 17, '入库作废' => 18, '释放库存' => 19,
            ];

            $map2 = [
                '采购收货' => 1, '出库配货订单' => 5, '调拨收货' => 11, '发货单' => 6, '供应商调整单' => 8, '入库上架单' => 4, '入库质检单' => 2,
                '一般交易出库单' => 10, '质量类型调整单' => 9, '移位上架单' => 12, '移位配货订单' => 13, '调拨出库' => 14, '动盘申请单' => 15, '退货收货' => 16, '其他出库' => 17, '动态盘点单' => 18, '快速移位单' => 19, '中转移位单' => 20, '采购到货登记' => 21, '调拨到货登记' => 22, '退货到货登记' => 23, '一般交易出库取消单' => 24, '其他收货' => 25, '取消单上架单' => 26, '调拨出库取消单' => 27, '其他出库取消单' => 28,
            ];

            $data = $find->data;
            $arr = [];
            try {
                DB::beginTransaction();
                foreach ($data as $item) {
                    $arr[] = [
                        // 'id' => $id--,
                        'operation' => $map[$item[0]],
                        'origin_value' => $item[1],
                        'type' => $map2[$item[3]],
                        'source_code' => $item[4],
                        'origin_type' => $item[5],
                        'origin_code' => $item[6],
                        'erp_no' => $item[7],
                        'sup_id' => 0,
                        'sup_name' => $item[8],
                        'sku' => $item[9],
                        'bar_code' => $item[13],
                        'location_code' => $item[18],
                        'uniq_code' => $item[15],
                        'batch_no' => $item[14],
                        'num' => $item[20],
                        'warehouse_code' => $warehouse[$item[21]]['warehouse_code'] ?? '',
                        'quality_type' => $this->quality_type($item[16]),
                        'quality_level' => $this->quality_level($item[17]),
                        'remark' => $item[23],
                        'tenant_id' => $tenant_id,
                        'create_user_id' => $users[$item[26]]['id'] ?? 0,
                        'admin_user_id' => $users[$item[28]]['id'] ?? 0,
                        'created_at' => $item[27],
                        'updated_at' => $item[29],
                        // 'dateline' => date('Y-m-d', strtotime($item[27])),
                    ];
                    if (count($arr) > 500) {
                        DB::table('wms_stock_logs')->insert($arr);
                        $arr = [];
                    }
                }
                if ($arr) DB::table('wms_stock_logs')->insert($arr);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
        });
    }

    function stockLog2()
    {
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        // $supplier = Supplier::where($where)->select('id', 'name')->get()->keyBy('name')->toArray();
        $supplier = null;
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();

        dump('总库存流水初始化......');
        $this->_init2(0, function ($find) use ($tenant_id, &$supplier, $warehouse, $users) {
            // $id = WmsStockLog::where('tenant_id', $tenant_id)->min('id');
            // $id = (!$id) ? $this->stockLogMax : $id - 1;
            $map = [
                '收货' => 1, '质量类型调整' => 2, '质检' => 3, '供应商调整' => 4, '上架' => 5, '下架' => 6, '出库锁定' => 7, '配货' => 8, '发货' => 9, '移位上架' => 10, '移位下架' => 11, '少货寻回' => 12, '取消出库' => 13, '少货冻结' => 14, '快速移位' => 15, '产品调整' => 16, '重配释放' => 17, '入库作废' => 18, '释放库存' => 19,
            ];

            $map2 = [
                '采购收货' => 1, '出库配货订单' => 5, '调拨收货' => 11, '发货单' => 6, '供应商调整单' => 8, '入库上架单' => 4, '入库质检单' => 2,
                '一般交易出库单' => 10, '质量类型调整单' => 9, '移位上架单' => 12, '移位配货订单' => 13, '调拨出库' => 14, '动盘申请单' => 15, '退货收货' => 16, '其他出库' => 17, '动态盘点单' => 18, '快速移位单' => 19, '中转移位单' => 20, '采购到货登记' => 21, '调拨到货登记' => 22, '退货到货登记' => 23, '一般交易出库取消单' => 24, '其他收货' => 25, '取消单上架单' => 26, '调拨出库取消单' => 27, '其他出库取消单' => 28,
            ];

            $data = $find->data;
            $arr = [];
            try {
                DB::beginTransaction();
                foreach ($data as $item) {
                    $arr[] = [
                        // 'id' => $id--,
                        'operation' => $map[$item[0]],
                        'origin_value' => $item[1],
                        'type' => $map2[$item[3]],
                        'source_code' => $item[4],
                        'origin_type' => $item[5],
                        'origin_code' => $item[6],
                        'erp_no' => $item[7],
                        'sup_id' => 0,
                        'sup_name' => $item[8],
                        'sku' => $item[9],
                        'bar_code' => $item[13],
                        'location_code' => $item[18],
                        'uniq_code' => $item[15],
                        'batch_no' => $item[14],
                        'num' => $item[20],
                        'warehouse_code' => $warehouse[$item[21]]['warehouse_code'] ?? '',
                        'quality_type' => $this->quality_type($item[16]),
                        'quality_level' => $this->quality_level($item[17]),
                        'remark' => $item[23],
                        'tenant_id' => $tenant_id,
                        'create_user_id' => $users[$item[26]]['id'] ?? 0,
                        'admin_user_id' => $users[$item[28]]['id'] ?? 0,
                        'created_at' => $item[27],
                        'updated_at' => $item[29],
                        // 'dateline' => date('Y-m-d', strtotime($item[27])),
                    ];
                    if (count($arr) > 500) {
                        DB::table('wms_stock_logs')->insert($arr);
                        $arr = [];
                    }
                }
                if ($arr) DB::table('wms_stock_logs')->insert($arr);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
        });
    }

    function dataPermission()
    {
        dump('组织权限导出......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        WmsDataPermission::orgInit($tenant_id, 'FIT株式会社');

        $this->_init(21, function ($find) use ($tenant_id, $warehouse) {
            $type = ['供应商' => 1, '仓库' => 2, '店铺' => 3,];
            $data = $find->data;
            try {
                DB::beginTransaction();
                foreach ($data as $item) {
                    if (empty($type[$item[3]] ?? 0)) continue;

                    if ($item[3] == '供应商') {
                        $supplier = Supplier::updateOrCreate([
                            'sup_code' => $item[0],
                            'tenant_id' => $tenant_id,
                        ], [
                            'name' => $item[1],
                            'sup_status' => 2,
                            'type' => 1,
                            'status' => $item[4] == '启用' ? 1 : 0,
                        ]);
                        WmsDataPermission::addSupplier($supplier);
                    }
                    if ($item[3] == '仓库') {
                        $warehouse = Warehouse::where(['warehouse_code' => $item[0], 'tenant_id' => $tenant_id,])->first();
                        if (!$warehouse) {
                            $warehouse = Warehouse::create([
                                'warehouse_code' => $item[0],
                                'tenant_id' => $tenant_id,
                                'warehouse_name' => $item[1],
                                'status' => $item[4] == '启用' ? 1 : 0,
                            ]);
                        }
                        WmsDataPermission::addWarehouse($warehouse);
                    }
                    if ($item[3] == '店铺') {
                        $shop = WmsShop::updateOrCreate([
                            'code' => $item[0],
                            'tenant_id' => $tenant_id,
                        ], [
                            'name' => $item[1],
                            'sale_channel' => 0,
                            'manager_id' => 0,
                            'status' => $item[4] == '启用' ? 1 : 0,
                        ]);
                        WmsDataPermission::addShop($shop);
                    }
                }
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
    }

    // 同步供应商库存
    function syncSupInv()
    {
        dump('同步供应商库存......');
        DB::getPdo()->exec("TRUNCATE TABLE wms_sup_inv;
        INSERT INTO wms_sup_inv (
            warehouse_code,
            warehouse_name,
            bar_code,
            lot_num,
            uniq_code_1,
            quality_type,
            quality_level,
            sup_id,
            sup_name,
            buy_price,
            wh_inv,
            sale_inv,
            lock_inv,
            wt_send_inv,
            freeze_inv,
            inv_type,
            tenant_id 
        ) SELECT
        inv.warehouse_code,
        IF
            ( w.warehouse_name IS NULL, '', w.warehouse_name ) AS warehouse_name,
            bar_code,
            lot_num,
        IF
            ( quality_level <> 'A', uniq_code, '' ) AS uniq_code_1,
            quality_type,
            quality_level,
            inv.sup_id,
        IF
            ( s.NAME IS NULL, '', s.NAME ) AS sup_name,
            buy_price,
            count(*) AS wh_inv,
            count(
            IF
            ( sale_status = 1, TRUE, NULL )) AS sale_inv,
            count(
            IF
            ( sale_status = 2, TRUE, NULL )) AS lock_inv,
            count(
            IF
            ( inv_status = 7, TRUE, NULL )) AS wt_send_inv,
            count(
            IF
            ( in_wh_status = 6, TRUE, NULL )) AS freeze_inv,
            inv_type,
            inv.tenant_id 
        FROM
            wms_inv_goods_detail inv
            LEFT JOIN wms_warehouse w ON w.warehouse_code = inv.warehouse_code 
            AND w.tenant_id = inv.tenant_id
            LEFT JOIN wms_supplier s ON s.id = inv.sup_id 
            AND s.tenant_id = inv.tenant_id 
        WHERE
            sup_id <> 0 
            AND in_wh_status NOT IN ( 0, 4, 7 ) 
            AND w.deleted_at IS NULL 
        GROUP BY
            bar_code,
            warehouse_code,
            lot_num,
            quality_type,
            quality_level,
            uniq_code_1,
            s.`name`,
            buy_price,
            inv_type,
            tenant_id;");
    }

    // 同步总库存
    function syncTotalInv()
    {
        dump('同步总库存......');
        DB::getPdo()->exec("
        TRUNCATE TABLE wms_total_inv;	
        insert into  wms_total_inv ( bar_code,
quality_type,
quality_level,
warehouse_code,warehouse_name,wh_inv,shelf_inv,sale_inv,shelf_sale_inv,shelf_lock_inv,wt_send_inv,wt_shelf_inv,freeze_inv,wt_shelf_cfm,trf_inv,tenant_id) SELECT
bar_code,
quality_type,
quality_level,
inv.warehouse_code,
        if(w.warehouse_name is null,'',w.warehouse_name)as warehouse_name,
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
    inv.tenant_id
FROM
    wms_inv_goods_detail inv
        join wms_warehouse w
        on w.warehouse_code = inv.warehouse_code and w.tenant_id = inv.tenant_id
WHERE
    in_wh_status NOT IN ( 0, 4, 7 ) and w.deleted_at is null
GROUP BY
    bar_code,
    quality_type,
    quality_level,
    warehouse_code,
    tenant_id;
        ");
    }

    function  order()
    {
        dump('销售订单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        $shops = WmsShop::where($where)->select('code', 'name')->get()->keyBy('name')->toArray();
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();
        $product = $this->deliverProduct();

        $order_platform = array_flip(V2WmsOrder::$order_platform_map);
        $this->_init(23, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users, $product) {
            $data = $find->data;
            try {
                $payment_status = ['已付款' => 1];
                $deliver_status = ['已取消' => 4, '已发货' => 3, '部分发货' => 2, '发货中' => 1, '待发货' => 0, '未发货' => 0];
                $status = ['暂存' => 0, '审核中' => 1, '已审核' => 2, '已驳回' => 3, '已撤回' => 4, '暂停' => 5, '已取消' => 6, '已确认' => 7];
                DB::beginTransaction();
                $order = null;
                $arr1 = [];
                $arr2 = [];
                $parent = [];
                foreach ($data as $item) {
                    if ($item[2] ?? '') {
                        // $order = V2WmsOrder::create([
                        //     'seller_message' => $item[0],
                        //     'order_at' => $item[1],
                        //     'third_no' => $item[2],
                        //     'status' => $status[$item[3]],
                        //     'code' => $item[4],
                        //     'deliver_status' => $deliver_status[$item[5]],
                        //     'payment_status' => $payment_status[$item[7]] ?? 0,
                        //     'buyer_account' => $item[8],
                        //     'order_platform' => $order_platform[$item[9]] ?? $item[9],
                        //     'shop_name' => $item[10],
                        //     'shop_code' => $shops[$item[10]]['code'],
                        //     'warehouse_name' => $item[11],
                        //     'warehouse_code' => $item[11] ? $warehouse[$item[11]]['warehouse_code'] : '',
                        //     'type' => 0,
                        //     'source_type' => 1,
                        //     'product_code' => $product ? $product->product_code : '',
                        //     'product_name' => $item[15],
                        //     'deliver_no' => $item[16],
                        //     'deliver_fee' => $item[17],
                        //     'num' => $item[18],
                        //     'total_amount' => $item[19],
                        //     'payment_amount' => $item[20],
                        //     'discount_amount' => $item[23],
                        //     'estimate_sendout_time' => $item[30],
                        //     'paysuccess_time' => $item[31],
                        //     'buyer_message' => $item[33],
                        //     'create_user_id' => $users[$item[34]]['id'] ?? 0,
                        //     'created_at' => $item[35],
                        //     'admin_user_id' => $users[$item[36]]['id'] ?? 0,
                        //     'updated_at' => $item[37],
                        //     'tenant_id' => $tenant_id,
                        // ]);
                        $parent = [
                            'seller_message' => $item[0],
                            'order_at' => $item[1],
                            'third_no' => $item[2],
                            'status' => $status[$item[3]],
                            'code' => $item[4],
                            'deliver_status' => $deliver_status[$item[5]],
                            'payment_status' => $payment_status[$item[7]] ?? 0,
                            'buyer_account' => $item[8],
                            'order_platform' => $order_platform[$item[9]] ?? $item[9],
                            'shop_name' => $item[10],
                            'shop_code' => $shops[$item[10]]['code'] ?? '',
                            'warehouse_name' => $item[11],
                            'warehouse_code' => $item[11] ? $warehouse[$item[11]]['warehouse_code'] : '',
                            'type' => 0,
                            'source_type' => 1,
                            'product_code' => $product[sprintf('%s_%s', $item[15], $item[14])]['product_code'] ?? '',
                            'product_name' => $item[15],
                            'deliver_no' => $item[16],
                            'deliver_fee' => $item[17],
                            'num' => $item[18],
                            'total_amount' => $item[19],
                            'payment_amount' => $item[20],
                            'discount_amount' => $item[23],
                            'estimate_sendout_time' => $item[30],
                            'paysuccess_time' => $item[31],
                            'buyer_message' => $item[33],
                            'create_user_id' => $users[$item[34]]['id'] ?? 0,
                            'created_at' => $item[35],
                            'admin_user_id' => $users[$item[36]]['id'] ?? 0,
                            'updated_at' => $item[37],
                            'tenant_id' => $tenant_id,
                        ];
                        $arr1[] = $parent;
                        if (count($arr1) > 500) {
                            DB::table('wms_orders')->insert($arr1);
                            $arr1 = [];
                        }
                    }

                    // WmsOrderDetail::create([
                    //     'origin_code' => $parent['code'],
                    //     'third_no' => $parent['third_no'],
                    //     'sku' => $item[38],
                    //     'num' => $item[43],
                    //     'cost_price' => $item[44] ? ($item[44] / $item[43]) : 0,
                    //     'retails_price' => $item[45],
                    //     'price' => $item[46],
                    //     'amount' => $item[47],
                    //     'payment_amount' => $item[48],
                    //     'discount_amount' => $item[49],
                    //     'amount' => $item[50],
                    //     'quality_type' => $this->quality_type($item[53]),
                    //     'quality_level' => $this->quality_level($item[54]),
                    //     'batch_no' => $item[55],
                    //     'uniq_code' => $item[56],
                    //     'oversold_num' => $item[57],
                    //     'refund_num' => $item[58],
                    //     'sendout_num' => $item[59],
                    //     'sendout_refund_num' => $item[60],
                    //     'return_num' => $item[61],
                    //     'tenant_id' => $tenant_id,
                    //     'warehouse_code' => $parent['warehouse_code'],
                    //     'status' => 1,
                    // ]);
                    $arr2[] = [
                        'origin_code' => $parent['code'],
                        'third_no' => $parent['third_no'],
                        'sku' => $item[38],
                        'num' => $item[43],
                        'cost_price' => $item[44] ? ($item[44] / $item[43]) : 0,
                        'retails_price' => $item[45],
                        'price' => $item[46],
                        'amount' => $item[47],
                        'payment_amount' => $item[48],
                        'discount_amount' => $item[49],
                        'amount' => $item[50],
                        'quality_type' => $this->quality_type($item[53]),
                        'quality_level' => $this->quality_level($item[54], $item[53]),
                        'batch_no' => $item[55],
                        'uniq_code' => $item[56],
                        'oversold_num' => $item[57],
                        'refund_num' => $item[58],
                        'sendout_num' => $item[59],
                        'sendout_refund_num' => $item[60],
                        'return_num' => $item[61],
                        'tenant_id' => $tenant_id,
                        'warehouse_code' => $parent['warehouse_code'],
                        'status' => 1,
                    ];
                    if (count($arr2) > 500) {
                        DB::table('wms_order_details')->insert($arr2);
                        $arr2 = [];
                    }
                }
                if ($arr1) DB::table('wms_orders')->insert($arr1);
                if ($arr2) DB::table('wms_order_details')->insert($arr2);
                $find->delete();

                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
        DB::statement("UPDATE wms_order_details d ,wms_spec_and_bar sku SET d.bar_code=sku.bar_code WHERE  d.sku=sku.sku AND  d.tenant_id=$tenant_id AND sku.tenant_id=$tenant_id");
    }

    function aftersale()
    {
        dump('售后工单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        $shops = null;
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();

        $order_platform = null;
        $this->_init(24, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users) {
            $data = $find->data;
            try {
                $refund_reason = array_flip([
                    0 => '默认退货原因',
                    1 => '效果不好/不喜欢',
                    2 => '缺货',
                    3 => '不想要了',
                    4 => '尺码不合适',
                    5 => '大小尺寸与商品描述不符',
                    6 => '卖家发错货',
                    7 => '拍多了',
                    8 => '材质、面料与商品描述不符',
                    9 => '颜色、款式、图案与描述不符',
                    10 => '质量问题',
                    11 => '地址/电话信息填写错误',
                    12 => '商品信息拍错(规格/尺码/颜色等)',
                    13 => '未按约定时间发货',
                    14 => '快递一直未送到',
                    15 => '协商一致退款',
                    16 => '其他',
                    17 => '多拍/拍错/不想要',
                    18 => '发货速度不满意',
                    19 => '没用/少用优惠',
                    20 => '空包裹/少货',
                    21 => '付款之时起365天内,卖家未点击发货,自动退款给您',
                ]);
                DB::beginTransaction();
                $status = ['暂存' => 0, '审核中' => 1, '已审核' => 2, '已确认' => 4, '已取消' => 5, '已驳回' => 6];
                $return_status = ['未收货' => 1, '已收货' => 2];
                $refund_status = ['已退款' => 1];
                $type = ['仅退款' => 1, '退款退货' => 2];

                $aftersale = null;
                foreach ($data as $item) {
                    if ($item[4] ?? '') {
                        $aftersale = AfterSaleOrder::create([
                            'created_at' => $item[0],
                            'deadline' => $item[1],
                            'code' => $item[4],
                            'status' => $status[$item[5]],
                            'return_status' => $return_status[$item[6]] ?? 0,
                            'refund_status' => $refund_status[$item[7]] ?? 0,
                            'type' => $type[$item[9]] ?? 0,
                            'refund_time' => $item[12],
                            'apply_no' => $item[13],
                            'warehouse_code' => $item[16] ? $warehouse[$item[16]]['warehouse_code'] : '',
                            'source_type' => 1,
                            'origin_code' => $item[14],
                            'deliver_no' => $item[20],
                            'return_num' => $item[21],
                            'refund_reason' => $item[22] ? $refund_reason[$item[22]] ?? 0 : 0,
                            'refund_amount' => $item[23],
                            'created_user' => $users[$item[29]]['id'] ?? 0,
                            'refund_user_id' => $users[$item[28]]['id'] ?? 0,
                            'audit_user_id' => $users[$item[27]]['id'] ?? 0,
                            'tenant_id' => $tenant_id,
                        ]);
                    }
                    $detail = WmsOrderDetail::where(['origin_code' => $aftersale->apply_no, 'sku' => $item[30]])->whereRaw('(refund_num>0 or sendout_refund_num>0 or return_num>0)')->first();
                    WmsAfterSaleOrderDetail::create([
                        'origin_code' => $aftersale->code,
                        'sku' => $item[30],
                        'num' => $item[35],
                        'return_num' => $item[36],
                        'refund_num' => $item[37],
                        'retails_price' => $item[38],
                        'price' => $item[39],
                        'amount' => $item[40],
                        'refund_amount' => $item[42],
                        'remark' => $item[46],
                        'tenant_id' => $tenant_id,
                        'order_detail_id' => $detail ? $detail->id : 0,
                    ]);
                }

                // 更新apply_num 申请数量
                $aftersales = AfterSaleOrder::where('tenant_id', $tenant_id)->where('apply_num', 0)->with('details')->get();
                foreach ($aftersales as $aftersale) {
                    $aftersale->update(['apply_num' => $aftersale->details->sum('num')]);
                }

                $find->delete();

                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
        DB::statement("UPDATE wms_after_sale_order_details d ,wms_spec_and_bar sku SET d.bar_code=sku.bar_code WHERE  d.sku=sku.sku AND  d.tenant_id=$tenant_id AND sku.tenant_id=$tenant_id");
    }

    function orderBill()
    {
        dump('销售结算账单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = null;
        $shops = null;
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();

        $order_platform = null;
        $this->_init(25, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                $status = ['已结算' => 1];
                $type = ['销售订单' => 1, '售后订单' => 2, '8' => 2];

                $arr = [];
                foreach ($data as $item) {
                    // WmsOrderStatement::create([
                    //     'status' => $status[$item[0]] ?? 0,
                    //     'code' => $item[1],
                    //     'origin_code' => $item[2],
                    //     'order_at' => $item[3],
                    //     'type' => $type[$item[4]],
                    //     'amount_time' => $item[5],
                    //     'third_no' => $item[6],
                    //     'shop_name' => $item[7],
                    //     'buyer_account' => $item[8],
                    //     'amount' => $item[10],
                    //     'settle_amount' => $item[11],
                    //     'settled_amount' => $item[12],
                    //     'settled_user_id' => $item[13] ? ($users[$item[13]]['id'] ?? 0) : 0,
                    //     'create_user_id' => $item[13] ? ($users[$item[13]]['id'] ?? 0) : 0,
                    //     'admin_user_id' => $item[13] ? ($users[$item[13]]['id'] ?? 0) : 0,
                    //     'settled_time' => $item[14],
                    //     'tenant_id' => $tenant_id,
                    // ]);
                    $arr[] = [
                        'status' => $status[$item[0]] ?? 0,
                        'code' => $item[1],
                        'origin_code' => $item[2],
                        'order_at' => $item[3],
                        'type' => $type[$item[4]],
                        'amount_time' => $item[5],
                        'third_no' => $item[6],
                        'shop_name' => $item[7],
                        'buyer_account' => $item[8],
                        'amount' => $item[10],
                        'settle_amount' => $item[11],
                        'settled_amount' => $item[12],
                        'settled_user_id' => $item[13] ? ($users[$item[13]]['id'] ?? 0) : 0,
                        'create_user_id' => $item[13] ? ($users[$item[13]]['id'] ?? 0) : 0,
                        'admin_user_id' => $item[13] ? ($users[$item[13]]['id'] ?? 0) : 0,
                        'settled_time' => $item[14],
                        'tenant_id' => $tenant_id,
                    ];
                    if (count($arr) > 500) {
                        DB::table('wms_order_statements')->insert($arr);
                        $arr = [];
                    }
                }
                if ($arr) DB::table('wms_order_statements')->insert($arr);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
    }


    function consigment()
    {
        dump('寄卖单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        $shops = null;
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();
        $supplier = Supplier::where($where)->select('id', 'name')->get()->keyBy('name')->toArray();

        $order_platform = null;
        $this->_init(26, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users, $supplier) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                $status = ['审核中' => 1, '已审核' => 2, '已确认' => 4, '已取消' => 5, '已驳回' => 6,];
                $receive_status = ['已收货' => 1];
                $source_type = ['手工创建' => 1];

                $order = null;
                $arr1 = [];
                $arr2 = [];
                $parent = [];
                foreach ($data as $item) {
                    if ($item[0] ?? '') {
                        // $order = Consignment::create([
                        //     'status' => $status[$item[0]] ?? 0,
                        //     'receive_status' => $receive_status[$item[1]] ?? 0,
                        //     'code' => $item[2],
                        //     'sup_id' => $supplier[$item[3]]['id'],
                        //     'warehouse_code' => $warehouse[$item[4]]['warehouse_code'],
                        //     'order_at' => $item[5],
                        //     'third_code' => $item[6],
                        //     'num' => $item[7],
                        //     'received_num' => $item[9],
                        //     'amount' => $item[11],
                        //     'estimate_receive_at' => $item[12],
                        //     'order_user' => $item[13] ? $users[$item[13]]['id'] ?? 0 : 0,
                        //     'remark' => $item[14],
                        //     'created_user' => $users[$item[15]]['id'] ?? 0,
                        //     'created_at' => $item[16],
                        //     'updated_user' => $users[$item[17]]['id'] ?? 0,
                        //     'updated_at' => $item[18],
                        //     'tenant_id' => $tenant_id,
                        // ]);
                        $parent = [
                            'status' => $status[$item[0]] ?? 0,
                            'receive_status' => $receive_status[$item[1]] ?? 0,
                            'code' => $item[2],
                            'sup_id' => self::getSupId($item[3], $supplier),
                            'warehouse_code' => $warehouse[$item[4]]['warehouse_code'],
                            'order_at' => $item[5],
                            'third_code' => $item[6],
                            'num' => $item[7],
                            'received_num' => $item[9],
                            'amount' => $item[11],
                            'estimate_receive_at' => $item[12],
                            'order_user' => $item[13] ? $users[$item[13]]['id'] ?? 0 : 0,
                            'remark' => $item[14],
                            'created_user' => $users[$item[15]]['id'] ?? 0,
                            'created_at' => $item[16],
                            'updated_user' => $users[$item[17]]['id'] ?? 0,
                            'updated_at' => $item[18],
                            'source_type' => $source_type[$item[19]],
                            'tenant_id' => $tenant_id,
                        ];
                        $arr1[] = $parent;
                        if (count($arr1) > 500) {
                            DB::table('wms_consignment_orders')->insert($arr1);
                            $arr1 = [];
                        }
                    }

                    // ConsignmentDetails::create([
                    //     'origin_code' => $order->code,
                    //     'sku' => $item[20],
                    //     'num' => $item[25],
                    //     'buy_price' => $item[26],
                    //     'recv_num' => $item[28],
                    //     'normal_count' => $item[29],
                    //     'flaw_count' => $item[30],
                    //     'remark' => $item[32],
                    //     'tenant_id' => $tenant_id,
                    // ]);
                    $arr2 = [
                        'origin_code' => $parent['code'],
                        'sku' => $item[20],
                        'num' => $item[25],
                        'buy_price' => $item[26],
                        'recv_num' => $item[28],
                        'normal_count' => $item[29],
                        'flaw_count' => $item[30],
                        'remark' => $item[32],
                        'tenant_id' => $tenant_id,
                    ];
                    if ($arr2) {
                        DB::table('wms_consignment_details')->insert($arr2);
                        $arr2 = [];
                    }
                }
                if ($arr1) DB::table('wms_consignment_orders')->insert($arr1);
                if ($arr2) DB::table('wms_consignment_details')->insert($arr2);

                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
        DB::statement("UPDATE wms_consignment_details d ,wms_spec_and_bar sku SET d.bar_code=sku.bar_code WHERE  d.sku=sku.sku AND  d.tenant_id=$tenant_id AND sku.tenant_id=$tenant_id");
    }

    function consigmentBill()
    {
        dump('寄卖结算账单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = null;
        $shops = null;
        $users = null;
        $supplier = Supplier::where($where)->select('id', 'name')->get()->keyBy('name')->toArray();

        $order_platform = null;
        $this->_init(27, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users, $supplier) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                $stattlement_status = ['待结算' => 0, '待提现' => 1, '提现中' => 2, '已提现' => 3,];
                $type = ['电商订单' => 0, '手工订单' => 1, '退货退款' => 2, '仅退款' => 3, '退换货' => 4];
                $status = ['已确认' => 1, '已审核' => 2];

                $arr = [];
                foreach ($data as $item) {
                    // WmsConsigmentSettlement::create([
                    //     'stattlement_status' => $stattlement_status[$item[0]],
                    //     'sup_name' => $item[1],
                    //     'sup_id' => $supplier[$item[1]]['id'] ?? 0,
                    //     'type' => $type[$item[2]],
                    //     'origin_code' => $item[3],
                    //     'status' => $status[$item[4]],
                    //     'confirm_at' => $item[5],
                    //     'third_code' => $item[6],
                    //     'order_at' => $item[7],
                    //     'sku' => $item[9],
                    //     'product_sn' => $item[10],
                    //     'product_name' => $item[12],
                    //     'spec_one' => $item[13],
                    //     'bid_price' => $item[14],
                    //     'actual_deal_price' => $item[15],
                    //     'deal_price' => $item[16],
                    //     'payment_amount' => $item[17],
                    //     'num' => $item[18],
                    //     'quality_type' => $item[19] == '正品' ? 1 : 2,
                    //     'quality_level' => $item[19] == '正品' ? 'A' : "B",
                    //     'subsidy_amount' => $item[21],
                    //     'actual_deal_amount' => $item[23],
                    //     'deal_amount' => $item[24],
                    //     'rule_name' => $item[25],
                    //     'stattlement_amount' => $item[26],
                    //     'send_warehouse_name' => $item[27],
                    //     'return_warehouse_name' => $item[28],
                    //     'shop_name' => $item[29],
                    //     'order_channel' => $item[30],
                    //     'action_at' => $item[31],
                    //     'settlement_at' => $item[32],
                    //     'apply_code' => $item[33],
                    //     'apply_at' => $item[34],
                    //     'remark' => $item[35],
                    //     'tenant_id' => $tenant_id,
                    // ]);
                    $arr[] = [
                        'stattlement_status' => $stattlement_status[$item[0]],
                        'sup_name' => $item[1],
                        'sup_id' => self::getSupId($item[1], $supplier),
                        'type' => $type[$item[2]],
                        'origin_code' => $item[3],
                        'status' => $status[$item[4]],
                        'confirm_at' => $item[5],
                        'third_code' => $item[6],
                        'order_at' => $item[7],
                        'sku' => $item[9],
                        'product_sn' => $item[10],
                        'product_name' => $item[12],
                        'spec_one' => $item[13],
                        'bid_price' => $item[14],
                        'actual_deal_price' => $item[15],
                        'deal_price' => $item[16],
                        'payment_amount' => $item[17],
                        'num' => $item[18],
                        'quality_type' => $item[19] == '正品' ? 1 : 2,
                        'quality_level' => $item[19] == '正品' ? 'A' : "B",
                        'subsidy_amount' => $item[21],
                        'actual_deal_amount' => $item[23],
                        'deal_amount' => $item[24],
                        'rule_name' => $item[25],
                        'stattlement_amount' => $item[26],
                        'send_warehouse_name' => $item[27],
                        'return_warehouse_name' => $item[28],
                        'shop_name' => $item[29],
                        'order_channel' => $item[30],
                        'action_at' => $item[31],
                        'settlement_at' => $item[32],
                        'apply_code' => $item[33],
                        'apply_at' => $item[34],
                        'remark' => $item[35],
                        'tenant_id' => $tenant_id,
                    ];
                    if (count($arr) > 500) {
                        DB::table('wms_consignment_settlement')->insert($arr);
                        $arr = [];
                    }
                }
                if ($arr) DB::table('wms_consignment_settlement')->insert($arr);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
    }

    function withdraw()
    {
        dump('提现申请单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = null;
        $shops = null;
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();
        $supplier = null;
        $order_platform = null;
        $this->_init(28, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users, $supplier) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                $type = ['提现申请单' => 1,];
                $status = ['待审核' => 0, '已审核' => 1];
                $arr = [];
                foreach ($data as $item) {
                    if ($item[0]) {
                        // WmsWithdrawRequest::create([
                        //     'type' => $type[$item[0]],
                        //     'code' => $item[1],
                        //     'apply_at' => $item[2],
                        //     'status' => $status[$item[3]],
                        //     'source' => 1,
                        //     'sup_name' => $item[5],
                        //     'total' => $item[6],
                        //     'amount' => $item[7],
                        //     'order_user' => $users[$item[8]]['id'] ?? 0,
                        //     'remark' => $item[9],
                        //     'created_user' => $users[$item[10]]['id'] ?? 0,
                        //     'created_at' => $item[11],
                        //     'updated_user' => $users[$item[12]]['id'] ?? 0,
                        //     'updated_at' => $item[13],
                        //     'tenant_id' => $tenant_id,
                        // ]);
                        $arr[] = [
                            'type' => $type[$item[0]],
                            'code' => $item[1],
                            'apply_at' => $item[2],
                            'status' => $status[$item[3]],
                            'source' => 1,
                            'sup_name' => $item[5],
                            'total' => $item[6],
                            'amount' => $item[7],
                            'order_user' => $users[$item[8]]['id'] ?? 0,
                            'remark' => $item[9],
                            'created_user' => $users[$item[10]]['id'] ?? 0,
                            'created_at' => $item[11],
                            'updated_user' => $users[$item[12]]['id'] ?? 0,
                            'updated_at' => $item[13],
                            'tenant_id' => $tenant_id,
                        ];
                        if (count($arr) > 500) {
                            DB::table('wms_withdraw_request')->insert($arr);
                            $arr = [];
                        }
                    }
                }
                if ($arr) DB::table('wms_withdraw_request')->insert($arr);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
    }

    function purchaseBill()
    {
        dump('采购结算账单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        $shops = null;
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();
        $supplier = Supplier::where($where)->select('id', 'name')->get()->keyBy('name')->toArray();

        $order_platform = null;
        $this->_init(29, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users, $supplier) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                $status = ['待结算' => 0, '已结算' => 1];
                $arr = [];
                foreach ($data as $item) {
                    if ($item[0]) {
                        // PurchaseStatements::create([
                        //     'status' => $status[$item[0]],
                        //     'code' => $item[1],
                        //     'order_user' => $users[$item[2]]['id'] ?? 0,
                        //     'order_at' => $item[3],
                        //     'origin_code' => $item[4],
                        //     'sup_id' => $supplier[$item[5]]['id'] ?? 0,
                        //     'warehouse_code' => $warehouse[$item[6]]['warehouse_code'],
                        //     'num' => $item[7],
                        //     'amount' => $item[9],
                        //     'settle_amount' => $item[10],
                        //     'settled_amount' => $item[11],
                        //     'settled_user' => $item[12] ? $users[$item[12]]['id'] ?? 0 : 0,
                        //     'settled_time' => $item[13],
                        //     'remark' => $item[14],
                        //     'tenant_id' => $tenant_id,
                        // ]);
                        $arr[] = [
                            'status' => $status[$item[0]],
                            'code' => $item[1],
                            'order_user' => $users[$item[2]]['id'] ?? 0,
                            'order_at' => $item[3],
                            'origin_code' => $item[4],
                            'sup_id' => self::getSupId($item[5], $supplier),
                            'warehouse_code' => $warehouse[$item[6]]['warehouse_code'],
                            'num' => $item[7],
                            'amount' => $item[9],
                            'settle_amount' => $item[10],
                            'settled_amount' => $item[11],
                            'settled_user' => $item[12] ? $users[$item[12]]['id'] ?? 0 : 0,
                            'settled_time' => $item[13],
                            'remark' => $item[14],
                            'tenant_id' => $tenant_id,
                        ];
                        if (count($arr) > 500) {
                            DB::table('wms_purchase_order_statements')->insert($arr);
                            $arr = [];
                        }
                    }
                }
                if ($arr) DB::table('wms_purchase_order_statements')->insert($arr);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
    }

    function shippingRequest()
    {
        dump('出库需求单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        $shops = WmsShop::where($where)->select('code', 'name')->get()->keyBy('name')->toArray();
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();
        $supplier = null;

        $order_platform = array_flip(V2WmsOrder::$order_platform_map);
        $this->_init(30, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users, $supplier) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                $status2 = ['待发货' => 0, '配货中' => 1, '发货中' => 2, '已发货' => 3, '已取消' => 4];
                $type = ['销售出库' => 1, '一般交易出库单' => 1, '调拨出库' => 2, '其他出库' => 3];
                $status = ['审核中' => 1, '已审核' => 2, '暂停' => 3, '已确认' => 4, '已取消' => 5];
                $request_status = ['待发货' => 1, '配货中' => 2, '发货中' => 3, '部分发货' => 3, '已发货' => 4, '已取消' => 5];

                $request = null;
                $arr1 = [];
                $arr2 = [];
                $parent = [];
                foreach ($data as $item) {
                    if ($item[1]) {
                        // $request = ObOrder::create([
                        //     'third_no' => $item[1],
                        //     'type' => $type[$item[2]] ?? $item[2],
                        //     'request_code' => $item[3],
                        //     'erp_no' => $item[4],
                        //     'paysuccess_time' => $item[5],
                        //     'status' => $status[$item[6]],
                        //     'request_status' => $request_status[$item[7]] ?? $item[7],
                        //     'payable_num' => $item[10],
                        //     'oversold_num' => $item[11],
                        //     'stockout_num' => $item[12],
                        //     'cancel_num' => $item[13],
                        //     'actual_num' => $item[14],
                        //     'warehouse_name' => $item[16],
                        //     'warehouse_code' => $warehouse[$item[16]]['warehouse_code'] ?? '',
                        //     'delivery_deadline' => $item[18],
                        //     'order_platform' => $order_platform[$item[19]] ?? $item[19],
                        //     'order_channel' => $shops[$item[20]]['id'] ?? 0,
                        //     'deliver_type' => $item[25],
                        //     'deliver_no' => $item[26],
                        //     'suspender_id' => $item[34] ? $users[$item[34]]['id'] ?? 0 : 0,
                        //     'paused_at' => $item[35],
                        //     'paused_reason' => $item[36],
                        //     'recovery_operator_id' => $item[37] ? $users[$item[37]]['id'] ?? 0 : 0,
                        //     'recovery_at' => $item[38],
                        //     'recovery_reason' => $item[39],
                        //     'created_at' => $item[43],
                        //     'admin_user_id' => $users[$item[44]]['id'] ?? 0,
                        //     'updated_at' => $item[45],
                        //     'tenant_id' => $tenant_id,
                        //     'buyer_account' => $item[22],
                        //     'seller_message' => $item[0],
                        //     'buyer_message' => $item[23],
                        // ]);
                        $parent = [
                            'third_no' => $item[1],
                            'type' => $type[$item[2]] ?? $item[2],
                            'request_code' => $item[3],
                            'erp_no' => $item[4],
                            'paysuccess_time' => $item[5],
                            'status' => $status[$item[6]],
                            'request_status' => $request_status[$item[7]] ?? $item[7],
                            'payable_num' => $item[10],
                            'oversold_num' => $item[11],
                            'stockout_num' => $item[12],
                            'cancel_num' => $item[13],
                            'actual_num' => $item[14],
                            'warehouse_name' => $item[16],
                            'warehouse_code' => $warehouse[$item[16]]['warehouse_code'] ?? '',
                            'delivery_deadline' => $item[18],
                            'order_platform' => $order_platform[$item[19]] ?? $item[19],
                            'order_channel' => $shops[$item[20]]['id'] ?? 0,
                            'deliver_type' => $item[25],
                            'deliver_no' => $item[26],
                            'suspender_id' => $item[34] ? $users[$item[34]]['id'] ?? 0 : 0,
                            'paused_at' => $item[35],
                            'paused_reason' => $item[36],
                            'recovery_operator_id' => $item[37] ? $users[$item[37]]['id'] ?? 0 : 0,
                            'recovery_at' => $item[38],
                            'recovery_reason' => $item[39],
                            'created_at' => $item[43],
                            'admin_user_id' => $users[$item[44]]['id'] ?? 0,
                            'updated_at' => $item[45],
                            'tenant_id' => $tenant_id,
                            'buyer_account' => $item[22],
                            'seller_message' => $item[0],
                            'buyer_message' => $item[23],
                        ];
                        $arr1[] = $parent;
                        if (count($arr1) > 500) {
                            DB::table('wms_shipping_request')->insert($arr1);
                            $arr1 = [];
                        }
                    }

                    // ShippingDetail::create([
                    //     'request_code' => $request->request_code,
                    //     'sku' => $item[47],
                    //     'quality_type' => $this->quality_type($item[51]),
                    //     'quality_level' => $item[51] == '正品' ? 'A' : "B",
                    //     'batch_no' => $item[52],
                    //     'uniq_code' => $item[53],
                    //     'payable_num' => $item[54],
                    //     'cancel_num' => $item[55],
                    //     'actual_num' => $item[56],
                    //     'oversold_num' => $item[58],
                    //     'stockout_num' => $item[59],
                    //     'status' => $status2[$item[60]] ?? $item[60],
                    //     'third_no' => $item[61],
                    //     'tenant_id' => $tenant_id,
                    // ]);
                    $arr2[] = [
                        'request_code' => $parent['request_code'],
                        'sku' => $item[47],
                        'quality_type' => $this->quality_type($item[51]),
                        'quality_level' => $item[51] == '正品' ? 'A' : "B",
                        'batch_no' => $item[52],
                        'uniq_code' => $item[53],
                        'payable_num' => $item[54],
                        'cancel_num' => $item[55],
                        'actual_num' => $item[56],
                        'oversold_num' => $item[58],
                        'stockout_num' => $item[59],
                        'status' => $status2[$item[60]] ?? $item[60],
                        'third_no' => $item[61],
                        'tenant_id' => $tenant_id,
                    ];
                    if (count($arr2) > 500) {
                        DB::table('wms_shipping_detail')->insert($arr2);
                        $arr2 = [];
                    }
                }
                if ($arr1) DB::table('wms_shipping_request')->insert($arr1);
                if ($arr2) DB::table('wms_shipping_detail')->insert($arr2);
                $find->delete();

                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
        DB::statement("UPDATE wms_shipping_detail d ,wms_spec_and_bar sku SET d.bar_code=sku.bar_code WHERE  d.sku=sku.sku AND  d.tenant_id=$tenant_id AND sku.tenant_id=$tenant_id");
    }

    function allocate()
    {
        dump('配货订单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        $shops = null;
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();
        $supplier = null;

        $order_platform = null;
        $this->_init(31, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users, $supplier) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                $type = ['出库配货订单' => 1, '移位配货订单' => 2, '爆品配货订单' => 3,];
                $origin_type = ['销售出库单' => 1, '调拨出库单' => 2, '调拨出库' => 2, '其他出库单' => 3, '其他出库' => 3, '中转移位单' => 4, '快速移位单' => 5, '一般交易出库单' => 1, '计划移位单' => 7];
                $status = ['已审核' => 1, '已取消' => 2, '暂停' => 3];
                $allocation_status2 = ['待配货' => 1, '配货中' => 2, '已配货' => 3];
                $map1 = ['待配货' => 1, '配货中' => 4, '已配货' => 5];
                $map2 = ['发货中' => 1, '待发货' => 4, '待揽收' => 5, '部分发货' => 5, '已发货' => 5];
                $pre = null;
                $arr1 = [];
                $arr2 = [];
                $parent = [];
                foreach ($data as $item) {
                    $cancel_status = 0;
                    if ($item[1] ?? '') {
                        if (!$item[3]) $item[3] = $item[1] == '移位配货订单' ? '快速移位单' : '一般交易出库单';
                        // $pre = preAllocationLists::create([
                        //     'remark' => $item[0],
                        //     'type' => $type[$item[1]],
                        //     'pre_alloction_code' => $item[2],
                        //     'origin_type' => $origin_type[$item[3]] ?? $item[3],
                        //     'request_code' => $item[4],
                        //     'status' => $status[$item[8]] ?? $item[8],
                        //     'allocation_status' => $allocation_status2[$item[9]] ?? $item[9],
                        //     'warehouse_code' => $warehouse[$item[12]]['warehouse_code'],
                        //     'sku_num' => $item[13],
                        //     'pre_num' => $item[14],
                        //     'cancel_num' => $item[15],
                        //     'actual_num' => $item[16],
                        //     'create_user_id' => $users[$item[32]]['id'] ?? 0,
                        //     'created_at' => $item[33],
                        //     'admin_user_id' => $users[$item[34]]['id'] ?? 0,
                        //     'updated_at' => $item[35],
                        //     'tenant_id' => $tenant_id,
                        // ]);
                        $status2 = $status[$item[8]] ?? $item[8];
                        // 1-待配货 2-配货中 3-已配货
                        $a_status = $allocation_status2[$item[9]] ?? $item[9];
                        // 已取消
                        if ($status2 == 3) {
                            // 取消状态 1-已取消待释放库存 2-库存释放完成 3-待重新上架 4-已扫描待上架  5-上架完成
                            $mp = ['1' => 2, '2' => 5, '3' => 5];
                            $cancel_status = $mp[$a_status];
                        }
                        $parent = [
                            'remark' => $item[0],
                            'type' => $type[$item[1]],
                            'pre_alloction_code' => $item[2],
                            'origin_type' => $origin_type[$item[3]] ?? $item[3],
                            'request_code' => $item[4],
                            'status' => $status2,
                            'allocation_status' => $a_status,
                            'warehouse_code' => $warehouse[$item[12]]['warehouse_code'],
                            'sku_num' => $item[13],
                            'pre_num' => $item[14],
                            'cancel_num' => $item[15],
                            'actual_num' => $item[16],
                            'create_user_id' => $users[$item[32]]['id'] ?? 0,
                            'created_at' => $item[33],
                            'admin_user_id' => $users[$item[34]]['id'] ?? 0,
                            'updated_at' => $item[35],
                            'tenant_id' => $tenant_id,
                            'state' => 0,
                        ];
                        $arr1[] = $parent;
                        if (count($arr1) > 500) {
                            DB::table('wms_pre_allocation_lists')->insert($arr1);
                            $arr1 = [];
                        }
                    }

                    // 1-已预配待分组 2-已分组待领取 3-已领取待配货 4-配货中 5-已配货待复核  6-已复核待发货 7-已发货
                    if ($item[10] == '已发货') $alloction_status = 7;
                    else $alloction_status = $map1[$item[9]] ?? 9;

                    $batch_no = strpos($item[46], '-') === false ? $item[46] : '';
                    $uniq_code = strpos($item[46], '-') !== false ? $item[46] : '';
                    // preAllocationDetail::create([
                    //     'pre_alloction_code' => $pre->pre_alloction_code,
                    //     'warehouse_code' => $pre->warehouse_code,
                    //     'request_code' => $pre->request_code,
                    //     'sku' => $item[36],
                    //     'sup_id' => $supplier[$item[40]]['id'] ?? 0,
                    //     'pre_num' => $item[41],
                    //     'actual_num' => $item[42],
                    //     'cancel_num' => $item[43],
                    //     'quality_type' => $this->quality_type($item[44]),
                    //     'quality_level' => $this->quality_level($item[45]),
                    //     'batch_no' => $batch_no,
                    //     'uniq_code' => $uniq_code,
                    //     'location_code' => $item[47],
                    //     'allocated_at' => $item[48],
                    //     'remark' => $item[49],
                    //     'alloction_status' => $alloction_status,
                    //     'tenant_id' => $tenant_id,
                    // ]);
                    $arr2[] = [
                        'pre_alloction_code' => $parent['pre_alloction_code'],
                        'warehouse_code' => $parent['warehouse_code'],
                        'request_code' => $parent['request_code'],
                        'sku' => $item[36],
                        // 'sup_id' => self::getSupId($item[40], $supplier),
                        'sup_name' => $item[40],
                        'pre_num' => $item[41],
                        'actual_num' => $item[42],
                        'cancel_num' => $item[43],
                        'quality_type' => $this->quality_type($item[44]),
                        'quality_level' => $this->quality_level($item[45]),
                        'batch_no' => $batch_no,
                        'uniq_code' => $uniq_code,
                        'location_code' => $item[47],
                        'allocated_at' => $item[48],
                        'remark' => $item[49],
                        'alloction_status' => $alloction_status,
                        'tenant_id' => $tenant_id,
                        'cancel_status' => $cancel_status,
                    ];
                    if (count($arr2) > 500) {
                        DB::table('wms_pre_allocation_detail')->insert($arr2);
                        $arr2 = [];
                    }
                }
                if ($arr1) DB::table('wms_pre_allocation_lists')->insert($arr1);
                if ($arr2) DB::table('wms_pre_allocation_detail')->insert($arr2);
                $find->delete();

                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
        // DB::statement("UPDATE wms_pre_allocation_detail d ,wms_spec_and_bar sku SET d.bar_code=sku.bar_code WHERE  d.sku=sku.sku AND  d.tenant_id=$tenant_id AND sku.tenant_id=$tenant_id");
    }

    function task()
    {
        dump('配货任务单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        $shops = null;
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();
        $supplier = null;
        $order_platform = null;
        $this->_init(32, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users, $supplier) {
            $data = $find->data;
            try {
                DB::beginTransaction();

                $status = ['暂存' => 1, '已审核' => 2, '已取消' => 3,];
                $mode = ['分拣配货' => 1, '按单配货' => 2,];
                $alloction_status = ['待配货' => 1, '配货中' => 2, '已配货' => 3];

                $task = [];
                $arr = [];
                $arr2 = [];
                foreach ($data as $item) {
                    if ($item[0] ?? '') {
                        $task = [
                            'type' => $item[0],
                            'mode' => $mode[$item[1]] ?? $item[1],
                            'code' => $item[2],
                            'status' => $status[$item[4]] ?? $item[4],
                            'alloction_status' => $alloction_status[$item[5]] ?? $item[5],
                            'group_no' => $item[6],
                            'order_num' => $item[7],
                            'warehouse_code' => $warehouse[$item[8]]['warehouse_code'] ?? '',
                            'start_at' => $item[10],
                            'confirm_at' => $item[11],
                            'print_at' => $item[12],
                            'receiver_id' => $users[$item[13]]['id'] ?? 0,
                            'create_user_id' => $users[$item[14]]['id'] ?? 0,
                            'created_at' => $item[15],
                            'admin_user_id' => $users[$item[16]]['id'] ?? 0,
                            'updated_at' => $item[17],
                            'tenant_id' => $tenant_id,
                        ];
                        $arr[] = $task;
                        if (count($arr) > 500) {
                            DB::table('wms_allocation_tasks')->insert($arr);
                            $arr = [];
                        }
                    }

                    $arr2[] = [
                        'origin_code' => $task['code'],
                        'warehouse_code' => $task['warehouse_code'],
                        'pre_alloction_code' => $item[31],
                        'request_code' => $item[33],
                        'location_code' => $item[19],
                        'batch_no' => $item[20],
                        'bar_code' => $item[21],
                        'num' => $item[22],
                        'actual_num' => $item[23],
                        'sku' => $item[25] . '#' . $item[26],
                        'quality_type' => $this->quality_type($item[28]),
                        'quality_level' => $this->quality_level($item[29], $item[28]),
                        'receiver_id' => $user[$item[30]]['id'] ?? 0,
                        'allocated_at' => $task['updated_at'],
                        'tenant_id' => $tenant_id,
                        'admin_user_id' => $user[$item[30]]['id'] ?? 0,
                        'created_at' => $task['created_at'],
                        'updated_at' => $task['updated_at'],

                    ];
                    if (count($arr2) > 500) {
                        DB::table('wms_allocation_task_detail')->insert($arr2);
                        $arr2 = [];
                    }
                }
                if ($arr) DB::table('wms_allocation_tasks')->insert($arr);
                if ($arr2) DB::table('wms_allocation_task_detail')->insert($arr2);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
    }

    function taskDetail()
    {
        $tenant_id = $this->tenant_id;
        dump("更新配货任务单编码......");
        $max = WmsPreAllocationDetail::where(['task_code' => '', 'tenant_id' => ADMIN_INFO['tenant_id']])->orderBy('id', 'desc')->limit(1)->value('id');
        if ($max) {
            $begin = 0;
            while (1) {
                dump($begin);
                if ($begin > $max) break;
                $end = $begin + 1000;
                DB::statement("UPDATE  wms_allocation_task_detail td,wms_pre_allocation_detail pre 
                SET pre.task_code=td.origin_code,pre.receiver_id=td.receiver_id,pre.allocated_at=td.allocated_at
                WHERE td.request_code=pre.request_code AND pre.pre_alloction_code=td.pre_alloction_code AND td.warehouse_code=pre.warehouse_code AND td.sku=pre.sku  AND td.batch_no=pre.batch_no AND pre.task_code='' AND pre.id>$begin AND pre.id<=$end and pre.tenant_id=$tenant_id and td.tenant_id=$tenant_id");
                $begin = $end;
            }
        }


        dump("更新具体的配货信息......");
        $max = WmsPreAllocationDetail::where(['uniq_code' => '', 'tenant_id' => ADMIN_INFO['tenant_id']])->orderBy('id', 'desc')->limit(1)->value('id');
        if ($max) {
            $begin = 0;
            while (1) {
                dump($begin);
                if ($begin > $max) break;
                $end = $begin + 1000;
                DB::statement("UPDATE wms_pre_allocation_detail pre,wms_stock_logs log
            SET pre.uniq_code=log.uniq_code,pre.bar_code=pre.bar_code,pre.sup_id=log.sup_id,pre.receiver_id=log.admin_user_id
            WHERE pre.pre_alloction_code=log.source_code AND pre.sku=log.sku AND pre.batch_no=log.batch_no AND pre.warehouse_code=log.warehouse_code AND pre.id>$begin AND pre.id<=$end AND log.operation=8 and pre.tenant_id=$tenant_id and log.tenant_id=$tenant_id");
                $begin = $end;
            }
        }

        dump("更新配货状态......");
        $max = preAllocationLists::where(['tenant_id' => ADMIN_INFO['tenant_id'], 'status' => 1, 'allocation_status' => 3])->orderBy('id', 'desc')->limit(1)->value('id');
        if ($max) {
            $begin = 0;
            while (1) {
                dump($begin);
                if ($begin > $max) break;
                $end = $begin + 1000;
                DB::statement("UPDATE wms_pre_allocation_lists l,wms_pre_allocation_detail d 
        SET d.alloction_status=7 
        WHERE l.pre_alloction_code=d.pre_alloction_code AND l.status=1 AND l.allocation_status=3 AND d.alloction_status=9 AND l.id>$begin AND l.id<$end and l.tenant_id=$tenant_id and d.tenant_id=$tenant_id");
                $begin = $end;
            }
        }

        dump("出库取消释放库存.....");
        DB::statement("UPDATE  wms_pre_allocation_lists l ,wms_shipping_cancel c,wms_pre_allocation_detail d
        SET d.cancel_status=2,d.alloction_status=1
        WHERE l.request_code=c.request_code AND l.pre_alloction_code=d.pre_alloction_code AND l.`status`=2 AND d.alloction_status=9 AND c.method IN (1,2) AND c.cancel_status=1 and l.tenant_id=$tenant_id and  d.tenant_id=$tenant_id");

        dump("出库取消发货拦截.....");
        DB::statement("UPDATE  wms_pre_allocation_lists l ,wms_shipping_cancel c,wms_pre_allocation_detail d
        SET d.cancel_status=5,d.alloction_status=6
        WHERE l.request_code=c.request_code AND l.pre_alloction_code=d.pre_alloction_code AND l.`status`=2 AND d.alloction_status=9 AND c.method=3 AND c.cancel_status=2 and l.tenant_id=$tenant_id and c.tenant_id=$tenant_id and d.tenant_id=$tenant_id");

        dump("已发货.....");
        $max = preAllocationLists::where(['tenant_id' => ADMIN_INFO['tenant_id'], 'status' => 1, 'allocation_status' => 3])->orderBy('id', 'desc')->limit(1)->value('id');
        if ($max) {
            $begin = 0;
            while (1) {
                dump($begin);
                if ($begin > $max) break;
                $end = $begin + 1000;
                DB::statement("UPDATE wms_pre_allocation_lists l ,wms_shipping_orders o,wms_pre_allocation_detail d
        SET d.alloction_status=7
        WHERE  l.request_code=o.request_code AND l.pre_alloction_code=d.pre_alloction_code AND l.`status`=1
        AND l.id>$begin AND l.id<=$end and l.tenant_id=$tenant_id and o.tenant_id=$tenant_id and d.tenant_id=$tenant_id");
                $begin = $end;
            }
        }
    }

    function updateBarCode()
    {


        $tenant_id = $this->tenant_id;
        dump("配货单详情条码.....");
        $max = preAllocationDetail::where('tenant_id', $tenant_id)->max('id');
        if ($max) {
            $begin = 0;
            while (1) {
                dump($begin);
                if ($begin > $max) break;
                $end = $begin + 1000;
                DB::statement("UPDATE wms_pre_allocation_detail d ,wms_spec_and_bar sku 
        SET d.bar_code=sku.bar_code
        WHERE d.sku=sku.sku AND d.id>$begin AND d.id<=$end and d.bar_code='' and d.tenant_id=$tenant_id and sku.tenant_id=$tenant_id");
                $begin = $end;
            }
        }

        dump("配货单详情供应商.....");
        if ($max) {
            $begin = 0;
            while (1) {
                dump($begin);
                if ($begin > $max) break;
                $end = $begin + 1000;
                DB::statement("UPDATE wms_pre_allocation_detail d ,wms_supplier sup 
                SET d.sup_id=sup.id
                WHERE d.sup_name=sup_name AND d.sup_id=0 AND d.id>$begin AND d.id<=$end and d.tenant_id=$tenant_id and sup.tenant_id=$tenant_id");
                $begin = $end;
            }
        }

        dump("收货单详情库区编码.....");
        $max = RecvDetail::where('tenant_id', $tenant_id)->max('id');
        if ($max) {
            $begin = 0;
            while (1) {
                dump($begin);
                if ($begin > $max) break;
                $end = $begin + 1000;
                DB::statement("UPDATE wms_recv_detail d ,wms_area_location l 
                SET d.area_code=l.location_code
                WHERE d.warehouse_code=l.warehouse_code AND d.location_code=l.location_code AND d.area_code='' AND d.id>$begin AND d.id<=$end and d.tenant_id=$tenant_id and l.tenant_id=$tenant_id");
                $begin = $end;
            }
        }
    }

    function shippment()
    {
        dump('发货单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        $shops = null;
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();
        $supplier = Supplier::where($where)->select('id', 'name')->get()->keyBy('name')->toArray();

        $order_platform = null;
        $this->_init(33, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users, $supplier) {
            $data = $find->data;
            try {
                DB::beginTransaction();

                $type = ['发货单' => 0];
                $status = ['已审核' => 0];
                $request_status = ['已发货' => 0];

                $order = null;
                $arr1 = [];
                $arr2 = [];
                $parent = [];
                foreach ($data as $item) {
                    if ($item[0]) {
                        // $order = ShippingOrders::create([
                        //     'type' => $type[$item[0]] ?? $item[0],
                        //     'ship_code' => $item[1],
                        //     'status' => $status[$item[2]] ?? $item[2],
                        //     'request_status' => $request_status[$item[3]] ?? $item[3],
                        //     'request_code' => $item[5],
                        //     'sku_num' => $item[8],
                        //     'actual_num' => $item[9],
                        //     'quality_num' => $item[10],
                        //     'defects_num' => $item[11],
                        //     'shipper_id' => $users[$item[12]]['id'] ?? 0,
                        //     'shipped_at' => $item[13],
                        //     'warehouse_name' => $item[15],
                        //     'warehouse_code' => $warehouse[$item[15]]['warehouse_code'] ?? '',
                        //     'tenant_id' => $tenant_id,
                        //     'created_at' => $item[34],
                        //     'updated_at' => $item[36],
                        // ]);
                        $parent = [
                            'type' => $type[$item[0]] ?? $item[0],
                            'ship_code' => $item[1],
                            'status' => $status[$item[2]] ?? $item[2],
                            'request_status' => $request_status[$item[3]] ?? $item[3],
                            'request_code' => $item[5],
                            'sku_num' => $item[8],
                            'actual_num' => $item[9],
                            'quality_num' => $item[10],
                            'defects_num' => $item[11],
                            'shipper_id' => $users[$item[12]]['id'] ?? 0,
                            'shipped_at' => $item[13],
                            'warehouse_name' => $item[15],
                            'warehouse_code' => $warehouse[$item[15]]['warehouse_code'] ?? '',
                            'tenant_id' => $tenant_id,
                            'created_at' => $item[34],
                            'updated_at' => $item[36],
                        ];
                        $arr1[] = $parent;
                        if (count($arr1) > 500) {
                            DB::table('wms_shipping_orders')->insert($arr1);
                            $arr1 = [];
                        }
                    }

                    // ShippingDetail::updateOrcreate([
                    //     'request_code' => $order->request_code,
                    //     'sku' => $item[40],
                    //     'quality_type' => $this->quality_type($item[48]),
                    //     'quality_level' => $this->quality_level($item[49], $item[48]),
                    //     'batch_no' => $item[45],
                    //     'tenant_id' => $tenant_id,
                    //     'sup_id' => self::getSupId($item[45], $supplier),
                    //     'uniq_code' => $item[50],
                    // ], [
                    //     'ship_code' => $order->ship_code,
                    //     'third_no' => $item[38],
                    //     'actual_num' => $item[42],
                    //     'remark' => $item[51],
                    //     'shipper_id' => $order->shipper_id,
                    //     'shipped_at' => $order->shipped_at,
                    //     'admin_user_id' => $users[$item[35]]['id'] ?? 0,
                    //     'updated_at' => $order->updated_at,
                    // ]);
                    $arr2[] = [
                        'request_code' => $parent['request_code'],
                        'sku' => $item[40],
                        'quality_type' => $this->quality_type($item[48]),
                        'quality_level' => $this->quality_level($item[49], $item[48]),
                        'batch_no' => $item[45],
                        'tenant_id' => $tenant_id,
                        'sup_id' => self::getSupId($item[45], $supplier),
                        'uniq_code' => $item[50],
                        'ship_code' => $parent['ship_code'],
                        'third_no' => $item[38],
                        'actual_num' => $item[42],
                        'remark' => $item[51],
                        'shipper_id' => $parent['shipper_id'],
                        'shipped_at' => $parent['shipped_at'],
                        'admin_user_id' => $users[$item[35]]['id'] ?? 0,
                        'updated_at' => $parent['updated_at'],
                    ];
                    if (count($arr2) > 500) {
                        DB::table('wms_shipping_detail')->insert($arr2);
                        $arr2 = [];
                    }
                }
                if ($arr1) DB::table('wms_shipping_orders')->insert($arr1);
                if ($arr2) DB::table('wms_shipping_detail')->insert($arr2);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
    }

    function shippmentCancel()
    {
        dump('出库取消单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = null;
        $shops = null;
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();
        $supplier = null;

        $order_platform = null;
        $this->_init(34, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users, $supplier) {
            $data = $find->data;
            try {
                DB::beginTransaction();

                $type = ['一般交易出库取消单' => 1, '其他出库取消单' => 3, '调拨出库取消单' => 2,];
                $status = ['已确认' => 1];
                $cancel_status = ['已完成' => 1, '已上架' => 2, '待上架' => 3, '部分上架' => 4,];
                $method = ['取消库存' => 1, '取消出库' => 1, '发货拦截' => 3, '释放库存' => 2,];

                $cancel = null;
                $arr1 = [];
                $arr2 = [];
                $parent = [];
                foreach ($data as $item) {
                    if ($item[0]) {
                        // $cancel = WmsShippingCancel::create([
                        //     'type' => $type[$item[0]] ?? $item[0],
                        //     'code' => $item[1],
                        //     'status' => $status[$item[2]] ?? $item[2],
                        //     'cancel_status' => $cancel_status[$item[3]] ?? $item[3],
                        //     'request_code' => $item[4],
                        //     'method' => $method[trim($item[5])] ?? $item[5],
                        //     'third_no' => $item[6],
                        //     'cancel_num' => $item[9],
                        //     'canceled_num' => $item[10],
                        //     'wait_putaway_num' => $item[11],
                        //     'putaway_num' => $item[12],
                        //     'remark' => $item[13],
                        //     'create_user_id' => $users[$item[14]]['id'] ?? 0,
                        //     'created_at' => $item[15],
                        //     'admin_user_id' => $users[$item[16]]['id'] ?? 0,
                        //     'updated_at' => $item[17],
                        //     'tenant_id' => $tenant_id,
                        // ]);
                        $parent = [
                            'type' => $type[$item[0]] ?? $item[0],
                            'code' => $item[1],
                            'status' => $status[$item[2]] ?? $item[2],
                            'cancel_status' => $cancel_status[$item[3]] ?? $item[3],
                            'request_code' => $item[4],
                            'method' => $method[trim($item[5])] ?? $item[5],
                            'third_no' => $item[6],
                            'cancel_num' => $item[9],
                            'canceled_num' => $item[10],
                            'wait_putaway_num' => $item[11],
                            'putaway_num' => $item[12],
                            'remark' => $item[13],
                            'create_user_id' => $users[$item[14]]['id'] ?? 0,
                            'created_at' => $item[15],
                            'admin_user_id' => $users[$item[16]]['id'] ?? 0,
                            'updated_at' => $item[17],
                            'tenant_id' => $tenant_id,
                        ];
                        $arr1[] = $parent;
                        if (count($arr1) > 500) {
                            DB::table('wms_shipping_cancel')->insert($arr1);
                            $arr1 = [];
                        }
                    }

                    $quality_type = $this->quality_type($item[25]);
                    $quality_level = $item[25] == '正品' ? 'A' : "B";
                    // WmsShippingCancelDetail::create([
                    //     'origin_code' => $cancel->code,
                    //     'sku' => $item[21],
                    //     'quality_type' => $quality_type,
                    //     'quality_level' => $quality_level,
                    //     'retail_price' => $item[26],
                    //     'actual_deal_price' => $item[27],
                    //     'discount_amount' => $item[28],
                    //     'cancel_num' => $item[29],
                    //     'canceled_num' => $item[30],
                    //     'putaway_num' => $item[31],
                    //     'wait_putaway_num' => $item[32],
                    //     'remark' => $item[33],
                    //     'tenant_id' => $tenant_id,
                    //     'created_at' => $cancel->created_at,
                    //     'admin_user_id' => $cancel->admin_user_id,
                    //     'updated_at' => $cancel->updated_at,
                    // ]);
                    $arr2[] = [
                        'origin_code' => $parent['code'],
                        'sku' => $item[21],
                        'quality_type' => $quality_type,
                        'quality_level' => $quality_level,
                        'retail_price' => $item[26],
                        'actual_deal_price' => $item[27],
                        'discount_amount' => $item[28],
                        'cancel_num' => $item[29],
                        'canceled_num' => $item[30],
                        'putaway_num' => $item[31],
                        'wait_putaway_num' => $item[32],
                        'remark' => $item[33],
                        'tenant_id' => $tenant_id,
                        'created_at' => $parent['created_at'],
                        'admin_user_id' => $parent['admin_user_id'],
                        'updated_at' => $parent['updated_at'],
                    ];
                    if (count($arr2) > 500) {
                        DB::table('wms_shipping_cancel_detail')->insert($arr2);
                        $arr2 = [];
                    }

                    // $pres = preAllocationDetail::where([
                    //     'sku'=>$item[21],'quality_type'=>$quality_type,'request_code'=>$cancel->request_code,'tenant_id' => $tenant_id,
                    // ])->get();
                    // foreach($pres as $pre){
                    //     // 取消状态 1-已取消待释放库存 2-库存释放完成 3-待重新上架 4-已扫描待上架  5-上架完成
                    //     $pre->update(['cancel_status'=>$cancel_status,'canceled_at'=>$cancel->updated_at,]);
                    // }
                }
                if ($arr1) DB::table('wms_shipping_cancel')->insert($arr1);
                if ($arr2) DB::table('wms_shipping_cancel_detail')->insert($arr2);
                $find->delete();
                // TODO 将取消状态同步到pre_acclocation_details表
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
    }

    // 获取供应商id
    static function getSupId($name, &$supplier)
    {
        // $supplier = Redis::get('sup');
        // if($supplier){
        //     $supplier = json_decode($supplier,true);
        // }else{
        //     $supplier = Supplier::where(['tenant_id'=>ADMIN_INFO['tenant_id']])->select('id', 'name')->get()->keyBy('name')->toArray();
        //     Redis::set('sup',json_encode($supplier));
        // }

        // Log::info(['name' => $name, 'num' => count($supplier)]);
        // -原宿買取
        $sup_id = $supplier[$name]['id'] ?? 0;
        if ($sup_id) return $sup_id;

        $name2 = $name . '-原宿買取';
        $sup_id = $supplier[$name2]['id'] ?? 0;
        if ($sup_id) return $sup_id;

        $find = Supplier::where(['name' => $name, 'tenant_id' => ADMIN_INFO['tenant_id']])->value('id') ?: 0;
        if ($find) return $find;

        $find = Supplier::where(['name' => $name2, 'tenant_id' => ADMIN_INFO['tenant_id']])->value('id') ?: 0;
        if ($find) return $find;

        // 不存在则创建
        $code = Supplier::getErpCode('G');
        $tenant_id = ADMIN_INFO['tenant_id'];
        DB::insert("INSERT INTO `wms_supplier` (`sup_code`, `name`, `sup_status`, `type`, `id_card`, `email`, `contact_name`, `contact_phone`, `contact_landline`, `contact_addr`, `bank_number`, `account_name`, `bank_card`, `bank_name`, `id_card_front`, `id_card_reverse`, `remark`, `status`, `approver`, `approved_at`, `sort`, `tenant_id`, `updated_user`, `created_user`, `created_at`, `updated_at`, `id_card_date`) VALUES ('$code', '$name', 2, 1, '', '', '', '', '', '', '', '', '', '', '', '', '', 1, 324, '2024-05-16 00:15:02', 0, $tenant_id, 0, 0, '2024-05-16 00:14:58', '2024-05-16 00:15:02', '2024-05-16')");
        $supplier = Supplier::where(['tenant_id' => ADMIN_INFO['tenant_id']])->select('id', 'name')->get()->keyBy('name')->toArray();
        // return Supplier::where(['sup_code' => $code, 'tenant_id' => ADMIN_INFO['tenant_id']])->value('id') ?: 0;
        return self::getSupId($name, $supplier);
    }

    function deliverProduct()
    {
        $p = WmsLogisticsProduct::where(['tenant_id' => ADMIN_INFO['tenant_id']])->selectRaw("product_name,pickup_method,product_code")->get();
        $product = [];
        foreach ($p as $item) {
            $product[sprintf('%s_%s', $item->product_name, $item->pickup_method_txt)] = $item;
        }
        return $p;
    }

    function transfer()
    {
        dump('调拨申请单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();
        $supplier = Supplier::where($where)->select('id', 'name')->get()->keyBy('name')->toArray();

        $product = $this->deliverProduct();
        $order_platform = null;
        $this->_init(35, function ($find) use ($tenant_id, $order_platform, $product, $warehouse, $users, $supplier) {
            $data = $find->data;
            try {
                DB::beginTransaction();

                $type = ['普通调拨单' => 2, '直接调拨单' => 4,];
                $doc_status = ['暂存' => 0, '审核中' => 1, '已审核' => 2, '暂停' => 3, '已确认' => 4, '已取消' => 5, '已驳回' => 6,];
                $send_status = ['待发货' => 1, '发货中' => 2, '11' => 2, '部分发货' => 3, '已发货' => 4,];
                $recv_status = ['待收货' => 1, '部分收货' => 2, '已收货' => 3,];

                $order = null;
                $arr1 = [];
                $arr2 = [];
                $parent = [];
                foreach ($data as $item) {
                    if ($item[0]) {

                        // $order = TransferOrder::create([
                        //     'type' => $type[$item[0]] ?? $item[0],
                        //     'doc_status' => $doc_status[$item[1]] ?? $item[1],
                        //     'tr_code' => $item[2],
                        //     'paysuccess_time' => $item[3],
                        //     'send_status' => $send_status[$item[4]] ?? $item[4],
                        //     'recv_status' => $recv_status[$item[5]] ?? $item[5],
                        //     'out_warehouse_code' => $warehouse[$item[6]]['warehouse_code'],
                        //     'in_warehouse_code' => $warehouse[$item[7]]['warehouse_code'],
                        //     'source_code' => $item[8],
                        //     'total' => $item[9],
                        //     'send_num' => $item[10],
                        //     'recv_num' => $item[11],
                        //     'delivery_deadline' => $item[13],
                        //     'log_prod_code' => $product ? $product->product_code : '',
                        //     'deliver_no' => $item[16],
                        //     'paysuccess_user' => $users[$item[17]]['id'] ?? 0,
                        //     'remark' => $item[18],
                        //     'suspender_id' => $users[$item[19]]['id'] ?? 0,
                        //     'paused_at' => $item[20],
                        //     'recovery_at' => $item[21],
                        //     'created_user' => $users[$item[22]]['id'] ?? 0,
                        //     'created_at' => $item[23],
                        //     'updated_user' => $users[$item[24]]['id'] ?? 0,
                        //     'updated_at' => $item[25],
                        //     'tenant_id' => $tenant_id,
                        // ]);
                        $parent = [
                            'type' => $type[$item[0]] ?? $item[0],
                            'doc_status' => $doc_status[$item[1]] ?? $item[1],
                            'tr_code' => $item[2],
                            'paysuccess_time' => $item[3],
                            'send_status' => $send_status[$item[4]] ?? $item[4],
                            'recv_status' => $recv_status[$item[5]] ?? $item[5],
                            'out_warehouse_code' => $warehouse[$item[6]]['warehouse_code'],
                            'in_warehouse_code' => $warehouse[$item[7]]['warehouse_code'],
                            'source_code' => $item[8],
                            'total' => $item[9],
                            'send_num' => $item[10],
                            'recv_num' => $item[11],
                            'delivery_deadline' => $item[13],
                            'log_prod_code' => $product[sprintf('%s_%s', $item[15], $item[14])]['product_code'] ?? '',
                            'deliver_no' => $item[16],
                            'paysuccess_user' => $users[$item[17]]['id'] ?? 0,
                            'remark' => $item[18],
                            'suspender_id' => $users[$item[19]]['id'] ?? 0,
                            'paused_at' => $item[20],
                            'recovery_at' => $item[21],
                            'created_user' => $users[$item[22]]['id'] ?? 0,
                            'created_at' => $item[23],
                            'updated_user' => $users[$item[24]]['id'] ?? 0,
                            'updated_at' => $item[25],
                            'tenant_id' => $tenant_id,
                        ];
                        $arr1[] = $parent;
                        if (count($arr1) > 500) {
                            DB::table('wms_transfer_order')->insert($arr1);
                            $arr1 = [];
                        }
                    }

                    $sup_id = self::getSupId($item[36], $supplier);
                    // $detail = TransferDetails::create([
                    //     'tr_code' => $order->tr_code,
                    //     'sku' => $item[26],
                    //     'num' => $item[30],
                    //     'send_num' => $item[31],
                    //     'recv_num' => $item[32],
                    //     'buy_price' => $item[33],
                    //     'quality_type' => $item[34] == '正品' ? 1 : 2,
                    //     'uniq_code' => $item[35],
                    //     'sup_id' => $sup_id,
                    //     'batch_no' => $item[39],
                    //     'remark' => $item[40],
                    //     'tenant_id' => $tenant_id,
                    // ]);
                    $arr2[] = [
                        'tr_code' => $parent['tr_code'],
                        'sku' => $item[26],
                        'num' => $item[30],
                        'send_num' => $item[31],
                        'recv_num' => $item[32],
                        'buy_price' => $item[33],
                        'quality_type' => $item[34] == '正品' ? 1 : 2,
                        'uniq_code' => $item[35],
                        'sup_id' => $sup_id,
                        'batch_no' => $item[39],
                        'remark' => $item[40],
                        'tenant_id' => $tenant_id,
                    ];
                    if (count($arr2) > 500) {
                        DB::table('wms_transfer_details')->insert($arr2);
                        $arr2 = [];
                    }
                }
                if ($arr1) DB::table('wms_transfer_order')->insert($arr1);
                if ($arr2) DB::table('wms_transfer_details')->insert($arr2);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
        DB::statement("UPDATE wms_transfer_details d ,wms_spec_and_bar sku SET d.bar_code=sku.bar_code WHERE  d.sku=sku.sku AND  d.tenant_id=$tenant_id AND sku.tenant_id=$tenant_id");
    }

    // 初始化调拨单明细
    function transferDetail()
    {
        $tenant_id = $this->tenant_id;
        $max = WmsStockLog::whereRaw("operation=9 AND origin_type='调拨出库'")->where(['tenant_id' => $tenant_id])->orderBy('id', 'desc')->limit(1)->value('id');
        $begin = 0;
        while (1) {
            if ($begin > $max) break;
            dump($begin);
            $end = $begin + 1000;
            $res = DB::select("SELECT log.sku,req.third_no,log.bar_code,log.sup_id,log.quality_type,log.quality_level,log.batch_no,log.admin_user_id,log.uniq_code,log.created_at,log.updated_at 
            FROM wms_stock_logs log 
            left JOIN wms_shipping_request req ON log.origin_code=req.request_code and req.tenant_id=$tenant_id
            WHERE log.operation=9 AND log.origin_type='调拨出库' AND log.created_at<'2024-05-22 00:00:00' and log.tenant_id=$tenant_id and log.id>$begin and log.id<=$end");
            $arr = [];
            foreach ($res as $item) {
                $arr[] = [
                    'type' => 1, 'sku' => $item->sku,
                    'source_code' => $item->third_no ?: '',
                    'sup_id' => $item->sup_id,
                    'bar_code' => $item->bar_code,
                    'quality_type' => $item->quality_type,
                    'quality_level' => $item->quality_level,
                    'batch_no' => $item->batch_no,
                    'admin_user_id' => $item->admin_user_id,
                    'uniq_code' => $item->uniq_code,
                    'is_scan' => 0, 'tenant_id' => $tenant_id, 'inv_type' => 0,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ];
            }
            if ($arr) DB::table('wms_withdraw_uniq_log')->insert($arr);
            $begin = $end;
        }
    }

    function ibOther()
    {
        dump('其他入库申请单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        // $shops = WmsShop::where($where)->select('code', 'name')->get()->keyBy('name')->toArray();
        $shops = null;
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();
        $supplier = Supplier::where($where)->select('id', 'name')->get()->keyBy('name')->toArray();

        $order_platform = null;
        $this->_init(36, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users, $supplier) {
            $data = $find->data;
            try {
                DB::beginTransaction();

                $type = ['其他入库申请单' => 4];
                $doc_status = ['暂存' => 0, '审核中' => 1, '已审核' => 2, '已确认' => 4, '已取消' => 5, '已驳回' => 6,];
                $recv_status = ['待收货' => 1, '部分收货' => 2, '已收货' => 3,];
                $inv_type = ['自营' => 0, '寄卖' => 1,];

                $order = null;
                $arr1 = [];
                $arr2 = [];
                $parent = [];
                foreach ($data as $item) {

                    if ($item[0]) {
                        $parent = [
                            'type' => $type[$item[0]],
                            'oib_code' => $item[1],
                            'paysuccess_time' => $item[2],
                            'doc_status' => $doc_status[$item[3]],
                            'recv_status' => $recv_status[$item[4]],
                            'warehouse_code' => $warehouse[$item[5]]['warehouse_code'],
                            'sum_buy_price' => $item[7],
                            'total' => $item[8],
                            'recv_num' => $item[9],
                            'paysuccess_user' => $users[$item[11]]['id'] ?? 0,
                            'remark' => $item[12],
                            'tenant_id' => $tenant_id,
                        ];
                        $arr1[] = $parent;
                        if (count($arr1) > 500) {
                            DB::table('wms_other_ib_order')->insert($arr1);
                            $arr1 = [];
                        }
                        // $order = OtherIbOrder::create([
                        //     'type' => $type[$item[0]],
                        //     'oib_code' => $item[1],
                        //     'paysuccess_time' => $item[2],
                        //     'doc_status' => $doc_status[$item[3]],
                        //     'recv_status' => $recv_status[$item[4]],
                        //     'warehouse_code' => $warehouse[$item[5]]['warehouse_code'],
                        //     'sum_buy_price' => $item[7],
                        //     'total' => $item[8],
                        //     'recv_num' => $item[9],
                        //     'paysuccess_user' => $users[$item[11]]['id'] ?? 0,
                        //     'remark' => $item[12],
                        //     'tenant_id' => $tenant_id,
                        // ]);
                    }
                    // OIbDetails::create([
                    //     'oib_code' => $order->oib_code,
                    //     'sku' => $item[14],
                    //     'sup_id' => self::getSupId($item[15],$supplier),
                    //     'num' => $item[19],
                    //     'recv_num' => $item[20],
                    //     'buy_price' => $item[21],
                    //     'inv_type' => $inv_type[$item[23]],
                    //     'remark' => $item[24],
                    //     'tenant_id' => $tenant_id,
                    // ]);
                    $arr2[] = [
                        'oib_code' => $parent['oib_code'],
                        'sku' => $item[14],
                        'sup_id' => self::getSupId($item[15], $supplier),
                        'num' => $item[19],
                        'recv_num' => $item[20],
                        'buy_price' => $item[21],
                        'inv_type' => $inv_type[$item[23]],
                        'remark' => $item[24],
                        'tenant_id' => $tenant_id,
                    ];
                    if (count($arr2) > 500) {
                        DB::table('wms_other_ib_details')->insert($arr2);
                        $arr2 = [];
                    }
                }
                if ($arr1) DB::table('wms_other_ib_order')->insert($arr1);
                if ($arr2) DB::table('wms_other_ib_details')->insert($arr2);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
        DB::statement("UPDATE wms_other_ib_details d ,wms_spec_and_bar sku SET d.bar_code=sku.bar_code WHERE  d.sku=sku.sku AND  d.tenant_id=$tenant_id AND sku.tenant_id=$tenant_id");
    }

    function shenduInv()
    {
        dump('慎独产品库存明细......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        $supplier = Supplier::where($where)->select('id', 'name')->get()->keyBy('name')->toArray();

        $this->_init(51, function ($find) use ($tenant_id, $warehouse, &$supplier) {
            $data = $find->data;
            try {
                DB::beginTransaction();

                $inv_type = ['自营' => 0, '寄卖' => 1,];

                $arr1 = [];
                foreach ($data as $item) {
                    $arr1[] = [
                        'sku' => $item[0],
                        'product_sn' => $item[1],
                        'spec_one' => $item[3],
                        'cost_amount' => $item[4],
                        'weight_cost_price' => $item[5],
                        'uniq_code' => $item[6],
                        'wh_inv' => $item[7],
                        'sale_inv' => $item[8],
                        'lock_inv' => $item[9],
                        'wt_send_inv' => $item[10],
                        'freeze_inv' => $item[11],
                        'warehouse_code' => $warehouse[$item[12]]['warehouse_code'] ?? '',
                        'warehouse_name' => $item[12],
                        'sup_name' => $item[13],
                        'sup_id' => self::getSupId($item[13], $supplier),
                        'inv_type' => $inv_type[$item[14]],
                        'quality_type' => $this->quality_type($item[15]),
                        'quality_level' => $this->quality_level($item[16]),
                        'batch_no' => $item[17],
                        'tenant_id' => $tenant_id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ];
                    if (count($arr1) > 500) {
                        // Log::info($arr1);
                        DB::table('wms_shendu_inv')->insert($arr1);
                        $arr1 = [];
                    }
                }
                if ($arr1) DB::table('wms_shendu_inv')->insert($arr1);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
    }

    function stockInv()
    {
        $tenant_id = $this->tenant_id;
        // 瑕疵品 - 采购价
        DB::statement("UPDATE wms_shendu_inv inv1,wms_inv_goods_detail inv2
        SET inv2.inv_type=inv1.inv_type,inv2.buy_price=inv1.cost_amount,inv2.in_wh_status=3,inv2.sale_status=1,inv2.inv_status=5,inv2.is_qc=1,inv2.is_putway=1
        WHERE inv1.uniq_code=inv2.uniq_code AND inv1.uniq_code>'' AND inv1.sale_inv=1 and inv1.warehouse_code=inv2.warehouse_code and inv2.in_wh_status>0 and inv1.tenant_id=$tenant_id and inv2.tenant_id=$tenant_id");

        // 瑕疵品架上可售
        DB::statement("UPDATE wms_shendu_inv inv1,wms_inv_goods_detail inv2
        SET inv2.inv_type=inv1.inv_type,inv2.buy_price=inv1.cost_amount,inv2.in_wh_status=3,inv2.sale_status=1,inv2.inv_status=5,inv2.is_qc=1,inv2.is_putway=1
        WHERE inv1.uniq_code=inv2.uniq_code AND inv1.uniq_code>'' AND inv1.sale_inv=1 AND inv2.in_wh_status=0 AND inv2.sale_status=0 AND inv2.inv_status=0 and inv1.tenant_id=$tenant_id and inv2.tenant_id=$tenant_id");

        // 已经有状态的，修改采购价
        // DB::statement("UPDATE wms_shendu_inv inv1,wms_inv_goods_detail inv2
        // SET inv2.buy_price=inv1.cost_amount
        // WHERE inv1.quality_type=inv2.quality_type AND inv1.batch_no=inv2.lot_num AND inv1.sku=inv2.sku AND inv1.sup_id=inv2.sup_id AND inv2.sale_status>0");

        // 正品1个可售
        // DB::statement("UPDATE wms_shendu_inv inv1,wms_inv_goods_detail inv2
        // SET inv2.inv_type=inv1.inv_type,inv2.buy_price=inv1.cost_amount,inv2.in_wh_status=3,inv2.sale_status=1,inv2.inv_status=5,inv2.is_qc=1,inv2.is_putway=1
        // WHERE  inv1.batch_no=inv2.lot_num AND inv1.sku=inv2.sku AND inv1.sup_id=inv2.sup_id AND  inv1.uniq_code='' AND inv1.sale_inv=1  and inv1.warehouse_code=inv2.warehouse_code AND inv1.quality_type=1 AND inv2.quality_type=1 AND inv2.in_wh_status=0 AND inv2.sale_status=0 AND inv2.inv_status=0");

        // 正品可售-质量类型相同
        $list = DB::select("SELECT inv1.* FROM wms_shendu_inv inv1
        WHERE inv1.uniq_code='' AND inv1.sale_inv>=1 AND EXISTS(
        SELECT 1 FROM wms_inv_goods_detail inv2 WHERE inv1.batch_no=inv2.lot_num AND inv1.sku=inv2.sku AND inv1.sup_id=inv2.sup_id
        AND inv2.sale_status=0 AND inv2.in_wh_status=0 AND inv2.inv_status=0) ");
        foreach ($list as $inv) {
            $where1 = ['sku' => $inv->sku, 'lot_num' => $inv->batch_no, 'sup_id' => $inv->sup_id, 'quality_type' => $inv->quality_type, 'warehouse_code' => $inv->warehouse_code];
            $where2 = ['sku' => $inv->sku, 'lot_num' => $inv->batch_no, 'sup_id' => $inv->sup_id, 'quality_type' => $inv->quality_type];
            $where3 = ['sku' => $inv->sku, 'lot_num' => $inv->batch_no, 'sup_id' => $inv->sup_id,];
            $res = $this->_up($where1, $inv);
            if (!$res) {
                $res = $this->_up($where2, $inv);
                // if ($res) {
                //     $res = $this->_up($where3, $inv);
                // }
            }
        }

        // 仓库不同

    }

    function _stockLogMax()
    {
        return WmsStockLog::max('id');
    }

    private function _up($where, $inv)
    {
        $invs2 = Inventory::whereRaw('inv_status=0 and sale_status=0 and in_wh_status=0')->where($where)->limit($inv->sale_inv)->pluck('uniq_code');
        if (!$invs2) return false;
        Inventory::whereRaw('inv_status=0 and sale_status=0 and in_wh_status=0')->where($where)->whereIn('uniq_code', $invs2->toArray())->update([
            'inv_type' => $inv->inv_type, 'buy_price' => $inv->weight_cost_price, 'in_wh_status' => 3, 'sale_status' => 1, 'inv_status' => 5, 'is_qc' => 1, 'is_putway' => 1
        ]);
        return true;
    }

    function obOther()
    {
        dump('其他出库申请单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        $shops = null;
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();
        $supplier = Supplier::where($where)->select('id', 'name')->get()->keyBy('name')->toArray();
        $product = $this->deliverProduct();

        $order_platform = null;
        $this->_init(37, function ($find) use ($tenant_id, $order_platform, $product, $warehouse, $users, $supplier) {
            $data = $find->data;
            try {
                DB::beginTransaction();

                $type = ['其他出库申请单' => 3];
                $doc_status = ['暂存' => 0, '审核中' => 1, '已审核' => 2, '暂停' => 3, '已确认' => 4, '已取消' => 5, '已驳回' => 6,];
                $send_status = ['待发货' => 1, '发货中' => 2, '部分发货' => 3, '已发货' => 4,];

                $order = null;
                $arr1 = [];
                $arr2 = [];
                $parent = [];
                foreach ($data as $item) {
                    if ($item[0]) {
                        $product = WmsLogisticsProduct::getProduct($item[8], $item[9], $tenant_id);
                        // $order = OtherObOrder::create([
                        //     'type' => $type[$item[0]],
                        //     'oob_code' => $item[1],
                        //     'paysuccess_time' => $item[2],
                        //     'doc_status' => $doc_status[$item[3]],
                        //     'send_status' => $send_status[$item[4]],
                        //     'delivery_deadline' => $item[5],
                        //     'warehouse_code' => $warehouse[$item[6]]['warehouse_code'],
                        //     'log_prod_code' => $product ? $product->product_code : '',
                        //     'deliver_no' => $item[10],
                        //     'total' => $item[12],
                        //     'send_num' => $item[13],
                        //     'paysuccess_user' => $users[$item[14]]['id'] ?? 0,
                        //     'remark' => $item[15],
                        //     'suspender_id' => $users[$item[16]]['id'] ?? 0,
                        //     'paused_at' => $item[17],
                        //     'recovery_at' => $item[18],
                        //     'tenant_id' => $tenant_id,
                        // ]);
                        $parent = [
                            'type' => $type[$item[0]],
                            'oob_code' => $item[1],
                            'paysuccess_time' => $item[2],
                            'doc_status' => $doc_status[$item[3]],
                            'send_status' => $send_status[$item[4]],
                            'delivery_deadline' => $item[5],
                            'warehouse_code' => $warehouse[$item[6]]['warehouse_code'],
                            'log_prod_code' => $product[sprintf('%s_%s', $item[9], $item[8])]['product_code'] ?? '',
                            'deliver_no' => $item[10],
                            'total' => $item[12],
                            'send_num' => $item[13],
                            'paysuccess_user' => $users[$item[14]]['id'] ?? 0,
                            'remark' => $item[15],
                            'suspender_id' => $users[$item[16]]['id'] ?? 0,
                            'paused_at' => $item[17],
                            'recovery_at' => $item[18],
                            'tenant_id' => $tenant_id,
                        ];
                        $arr1[] = $parent;
                        if (count($arr1) > 500) {
                            DB::table('wms_other_ob_order')->insert($arr1);
                            $arr1 = [];
                        }
                    }

                    // OObDetails::create([
                    //     'oob_code' => $order->oob_code,
                    //     'sku' => $item[20],
                    //     'sup_id' => $supplier[$item[21]]['id'] ?? 0,
                    //     'num' => $item[25],
                    //     'send_num' => $item[26],
                    //     'buy_price' => $item[27],
                    //     'quality_type' => $this->quality_type($item[31]),
                    //     'quality_level' => $this->quality_level($item[32]),
                    //     'batch_no' => $item[33],
                    //     'uniq_code' => $item[34],
                    //     'remark' => $item[35],
                    //     'tenant_id' => $tenant_id,
                    // ]);
                    $arr2[] = [
                        'oob_code' => $parent['oob_code'],
                        'sku' => $item[20],
                        'sup_id' => self::getSupId($item[21], $supplier),
                        'num' => $item[25],
                        'send_num' => $item[26],
                        'buy_price' => $item[27],
                        'quality_type' => $this->quality_type($item[31]),
                        'quality_level' => $this->quality_level($item[32]),
                        'batch_no' => $item[33],
                        'uniq_code' => $item[34],
                        'remark' => $item[35],
                        'tenant_id' => $tenant_id,
                    ];
                    if (count($arr2) > 500) {
                        DB::table('wms_other_ob_details')->insert($arr2);
                        $arr2 = [];
                    }
                }
                if ($arr1) DB::table('wms_other_ob_order')->insert($arr1);
                if ($arr2) DB::table('wms_other_ob_details')->insert($arr2);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
        DB::statement("UPDATE wms_other_ob_details d ,wms_spec_and_bar sku SET d.bar_code=sku.bar_code WHERE  d.sku=sku.sku AND  d.tenant_id=$tenant_id AND sku.tenant_id=$tenant_id");
    }

    function putaway()
    {
        dump('上架单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        // $shops = WmsShop::where($where)->select('code', 'name')->get()->keyBy('name')->toArray();
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();
        // $supplier = Supplier::where($where)->select('id', 'name')->get()->keyBy('name')->toArray();
        $shops = null;
        $supplier = null;

        $order_platform = null;
        $this->_init(38, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users, $supplier) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                $type = ['入库上架单' => 1, '移位上架单' => 2, '取消单上架单' => 3];
                $status = ['暂存' => 0, '已审核' => 1];
                $putaway_status = ['上架中' => 0, '已上架' => 1];
                $order = null;
                $arr1 = [];
                $arr2 = [];
                $parent = [];
                foreach ($data as $item) {

                    if ($item[0]) {
                        // $order = WmsPutawayList::create([
                        //     'type' => $type[$item[0]],
                        //     'putaway_code' => $item[1],
                        //     'status' => $status[$item[2]],
                        //     'putaway_status' => $putaway_status[$item[3]],
                        //     'total_num' => $item[4],
                        //     'warehouse_name' => $item[5],
                        //     'warehouse_code' => $warehouse[$item[5]]['warehouse_code'],
                        //     'submitter_id' => $users[$item[6]]['id'] ?? 0,
                        //     'completed_at' => $item[8],
                        //     'remark' => $item[9],
                        //     'create_user_id' => $users[$item[11]]['id'] ?? 0,
                        //     'created_at' => $item[12],
                        //     'admin_user_id' => $users[$item[13]]['id'] ?? 0,
                        //     'updated_at' => $item[14],
                        //     'tenant_id' => $tenant_id,
                        // ]);
                        $parent = [
                            'type' => $type[$item[0]],
                            'putaway_code' => $item[1],
                            'status' => $status[$item[2]],
                            'putaway_status' => $putaway_status[$item[3]],
                            'total_num' => $item[4],
                            'warehouse_name' => $item[5],
                            'warehouse_code' => $warehouse[$item[5]]['warehouse_code'],
                            'submitter_id' => $users[$item[6]]['id'] ?? 0,
                            'completed_at' => $item[8],
                            'remark' => $item[9],
                            'create_user_id' => $users[$item[11]]['id'] ?? 0,
                            'created_at' => $item[12],
                            'admin_user_id' => $users[$item[13]]['id'] ?? 0,
                            'updated_at' => $item[14],
                            'tenant_id' => $tenant_id,
                        ];
                        $arr1[] = $parent;
                        if (count($arr1) > 500) {
                            DB::table('wms_putaway_list')->insert($arr1);
                            $arr1 = [];
                        }
                    }

                    // $quality_type = $this->quality_type($item[20]);
                    // $logs = WmsStockLog::where([
                    //     'operation' => 5, 'source_code' => $parent['putaway_code'], 'sku' => $item[15], 'location_code' => $item[22], 'quality_type' => $quality_type, 'warehouse_code' => $parent['warehouse_code'], 'tenant_id' => $tenant_id,
                    // ])->get();
                    // if (!$logs) {
                    //     Log::channel('daily2')->info('未获取到上架单明细', [
                    //         'operation' => 5, 'source_code' => $parent['putaway_code'], 'sku' => $item[15], 'location_code' => $item[22], 'quality_type' => $quality_type, 'warehouse_code' => $parent['warehouse_code'], 'tenant_id' => $tenant_id,
                    //     ]);
                    //     continue;
                    // }
                    // foreach ($logs as $log) {
                    //     // WmsPutawayDetail::create([
                    //     //     'putaway_code' => $parent['putaway_code'],
                    //     //     'type' => $parent['type'],
                    //     //     'sku' => $item[15],
                    //     //     'bar_code' => $item[19],
                    //     //     'quality_type' => $quality_type,
                    //     //     'quality_level' => $this->quality_level($item[21]),
                    //     //     'location_code' => $item[22],
                    //     //     'admin_user_id' => $parent['admin_user_id'],
                    //     //     'created_at' => $parent['created_at'],
                    //     //     'updated_at' => $parent['updated_at'],
                    //     //     'tenant_id' => $tenant_id,
                    //     //     'uniq_code' => $log ? $log->uniq_code : '',
                    //     // ]);
                    //     $arr2[] = [
                    //         'putaway_code' => $parent['putaway_code'],
                    //         'type' => $parent['type'],
                    //         'sku' => $item[15],
                    //         'bar_code' => $item[19],
                    //         'quality_type' => $quality_type,
                    //         'quality_level' => $this->quality_level($item[21]),
                    //         'location_code' => $item[22],
                    //         'admin_user_id' => $parent['admin_user_id'],
                    //         'created_at' => $parent['created_at'],
                    //         'updated_at' => $parent['updated_at'],
                    //         'tenant_id' => $tenant_id,
                    //         'uniq_code' => $log ? $log->uniq_code : '',
                    //     ];
                    //     if (count($arr2) > 500) {
                    //         DB::table('wms_putaway_detail')->insert($arr2);
                    //         $arr2 = [];
                    //     }
                    // }
                }
                if ($arr1) DB::table('wms_putaway_list')->insert($arr1);
                // if ($arr2) DB::table('wms_putaway_detail')->insert($arr2);

                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
    }

    function putawayDetail()
    {
        dump("上架单详情......");
        $tenant_id = ADMIN_INFO['tenant_id'];
        $max = WmsStockLog::where(['operation' => 5, 'tenant_id' => ADMIN_INFO['tenant_id']])->orderBy('id', 'desc')->limit(1)->value('id');
        if (!$max) return;
        $begin = 0;
        DB::statement("TRUNCATE wms_putaway_detail");
        while (1) {
            dump($begin);
            if ($begin > $max) break;
            $end = $begin + 1000;
            $logs = WmsStockLog::where(['operation' => 5, 'tenant_id' => ADMIN_INFO['tenant_id']])->whereRaw("id>$begin and id<=$end")->get();
            // 单据类型 1-入库上架单 2-移位上架单  3-取消上架单
            $map = ['4' => 1, '12' => 2, '26' => 3];
            $arr2 = [];
            foreach ($logs as $log) {
                $arr2[] = [
                    'putaway_code' => $log->source_code,
                    'type' => $map[$log->type],
                    'sku' => $log->sku,
                    'bar_code' => $log->bar_code,
                    'quality_type' => $log->quality_type,
                    'quality_level' => $log->quality_level,
                    'location_code' => $log->location_code,
                    'admin_user_id' => $log->admin_user_id,
                    'created_at' => $log->created_at,
                    'updated_at' => $log->updated_at,
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                    'uniq_code' => $log->uniq_code,
                ];
                if (count($arr2) > 500) {
                    DB::table('wms_putaway_detail')->insert($arr2);
                    $arr2 = [];
                }
            }
            if ($arr2) DB::table('wms_putaway_detail')->insert($arr2);
            $begin = $end;
        }

        // 更新area_code
        // DB::statement("UPDATE wms_putaway_detail d ,wms_putaway_list l,wms_area_location loc SET d.area_code = loc.area_code
        // WHERE d.putaway_code=l.putaway_code AND l.warehouse_code=loc.warehouse_code AND d.location_code = loc.location_code AND d.area_code='' AND d.tenant_id=$tenant_id AND l.tenant_id=$tenant_id  AND loc.tenant_id=$tenant_id ");
    }

    function qc()
    {
        dump('质检单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        // $shops = WmsShop::where($where)->select('code', 'name')->get()->keyBy('name')->toArray();
        $shops = null;
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();
        // $supplier = Supplier::where($where)->select('id', 'name')->get()->keyBy('name')->toArray();
        $supplier = null;

        $order_platform = null;
        $this->_init(39, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users, $supplier) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                $type = ['入库质检单' => 1, '仓内质检单' => 2,];
                $status = ['暂存' => 0, '已审核' => 1];
                $qc_status = ['质检中' => 0, '已完成' => 1];
                $method = ['一键质检' => 1, '逐件质检' => 2, '收货即质检' => 3,];
                $arr1 = [];
                $parent = [];
                foreach ($data as $item) {
                    if ($item[0]) {
                        $parent = [
                            'warehouse_name' => $item[0],
                            'warehouse_code' => $warehouse[$item[0]]['warehouse_code'],
                            'type' => $type[$item[1]],
                            'qc_code' => $item[2],
                            'status' => $status[$item[3]],
                            'qc_status' => $qc_status[$item[4]],
                            'method' => $method[$item[5]],
                            'total_num' => $item[6],
                            'probable_defect_num' => $item[7],
                            'normal_num' => $item[8],
                            'defect_num' => $item[9],
                            'submit_user_id' => $users[$item[10]]['id'] ?? 0,
                            'completed_at' => $item[12],
                            'remark' => $item[13],
                            'create_user_id' => $users[$item[15]]['id'] ?? 0,
                            'created_at' => $item[16],
                            'admin_user_id' => $users[$item[17]]['id'] ?? 0,
                            'updated_at' => $item[18],
                            'tenant_id' => $tenant_id,
                        ];
                        $arr1[] = $parent;
                        if (count($arr1) > 500) {
                            DB::table('wms_quality_list')->insert($arr1);
                            $arr1 = [];
                        }
                    }
                }
                if ($arr1) DB::table('wms_quality_list')->insert($arr1);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
    }


    function qcDetailInit()
    {
        dump('质检单明细......');
        $tenant_id = $this->tenant_id;
        DB::statement("TRUNCATE wms_quality_detail");
        $min = 0;
        $max = 10000;
        while (1) {
            $max = $min + 10000;
            if ($max > $this->_stockLogMax()) break;
            dump($max);
            $count = WmsStockLog::whereRaw("operation=3 AND tenant_id=$tenant_id and id>$min and id<=$max")->count();
            if ($count) {
                DB::statement("INSERT INTO wms_quality_detail(qc_code,sku,bar_code,uniq_code,quality_type,quality_level,status,remark,location_code,area_name,tenant_id,admin_user_id,created_at,updated_at)(SELECT source_code,sku,bar_code,uniq_code,quality_type,quality_level,1 as `status`,remark,location_code,origin_value,tenant_id,admin_user_id,created_at,updated_at FROM wms_stock_logs WHERE  operation=3 AND tenant_id=$tenant_id and id>$min and id<=$max)");
            }
            $min = $max;
        }
    }

    function qcConfirm()
    {
        dump('质检确认单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        // $shops = WmsShop::where($where)->select('code', 'name')->get()->keyBy('name')->toArray();
        $shops = null;
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();
        // $supplier = Supplier::where($where)->select('id', 'name')->get()->keyBy('name')->toArray();
        $supplier = null;

        $order_platform = null;
        $this->_init(40, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users, $supplier) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                $type = ['入库质检单' => 1, '仓内质检单' => 2,];
                $status = ['待确认' => 0, '已确认' => 1, '已作废' => 2, '确认失败' => 3];
                $order = null;
                $arr = [];
                foreach ($data as $item) {
                    // WmsQualityConfirmList::create([
                    //     'qc_code' => $item[0],
                    //     'arr_code' => $item[1],
                    //     'warehouse_name' => $item[2],
                    //     'warehouse_code' => $warehouse[$item[2]]['warehouse_code'],
                    //     'status' => $status[$item[3]],
                    //     'remark' => $item[5],
                    //     'uniq_code' => $item[6],
                    //     'old_quality_type' => $this->quality_type($item[8]),
                    //     'old_quality_level' => $this->quality_level($item[9], $item[8]),
                    //     'confirm_quality_type' => $this->quality_type($item[10]),
                    //     'confirm_quality_level' => $this->quality_level($item[11]),
                    //     'sku' => $item[12],
                    //     'type' => $type[$item[16]],
                    //     'area_name' => $item[17],
                    //     'location_code' => $item[18],
                    //     'submitter_id' => $users[$item[19]]['id'] ?? 0,
                    //     'created_at' => $item[20],
                    //     'comfirmor_id' => $users[$item[21]]['id'] ?? 0,
                    //     'confirm_at' => $item[22],
                    //     'confirm_remark' => $item[23],
                    //     'tenant_id' => $tenant_id,
                    // ]);
                    $arr[] = [
                        'qc_code' => $item[0],
                        'arr_code' => $item[1],
                        'warehouse_name' => $item[2],
                        'warehouse_code' => $warehouse[$item[2]]['warehouse_code'],
                        'status' => $status[$item[3]],
                        'remark' => $item[5],
                        'uniq_code' => $item[6],
                        'old_quality_type' => $this->quality_type($item[8]),
                        'old_quality_level' => $this->quality_level($item[9], $item[8]),
                        'confirm_quality_type' => $this->quality_type($item[10]),
                        'confirm_quality_level' => $this->quality_level($item[11]),
                        'sku' => $item[12],
                        'type' => $type[$item[16]],
                        'area_name' => $item[17],
                        'location_code' => $item[18],
                        'submitter_id' => $users[$item[19]]['id'] ?? 0,
                        'created_at' => $item[20],
                        'comfirmor_id' => $users[$item[21]]['id'] ?? 0,
                        'confirm_at' => $item[22],
                        'confirm_remark' => $item[23],
                        'tenant_id' => $tenant_id,
                    ];
                    if (count($arr) > 500) {
                        DB::table('wms_quality_confirm_list')->insert($arr);
                        $arr = [];
                    }
                }
                if ($arr) DB::table('wms_quality_confirm_list')->insert($arr);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
        DB::statement("UPDATE wms_quality_confirm_list d ,wms_spec_and_bar sku SET d.bar_code=sku.bar_code WHERE  d.sku=sku.sku AND  d.tenant_id=$tenant_id AND sku.tenant_id=$tenant_id");
    }

    function move()
    {
        dump('移位单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        // $shops = WmsShop::where($where)->select('code', 'name')->get()->keyBy('name')->toArray();
        $shops = null;
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();
        $supplier = Supplier::where($where)->select('id', 'name')->get()->keyBy('name')->toArray();

        $order_platform = null;
        $this->_init(41, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users, $supplier) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                $status = ['暂存' => 0, '审核中' => 1, '已审核' => 2, '已驳回' => 3, '已取消' => 4];
                $down_status = ['待下架' => 0, '下架中' => 1, '已下架' => 2,];
                $shelf_status = ['待上架' => 0, '上架中' => 1, '已上架' => 2,];
                $type = ['计划移位单' => 1, '中转移位单' => 2, '快速移位单' => 3,];

                $order = null;
                $arr1 = [];
                $arr2 = [];
                $parent = [];
                foreach ($data as $item) {

                    if ($item[0]) {
                        // $order = WmsStockMoveList::create([
                        //     'type' => $type[$item[0]],
                        //     'code' => $item[1],
                        //     'status' => $status[$item[2]],
                        //     'down_status' => $down_status[$item[3]],
                        //     'shelf_status' => $shelf_status[$item[4]],
                        //     'warehouse_code' => $warehouse[$item[5]]['warehouse_code'],
                        //     'num' => $item[6],
                        //     'down_num' => $item[7],
                        //     'down_diff' => $item[8],
                        //     'shelf_num' => $item[9],
                        //     'shelf_diff' => $item[10],
                        //     'order_user' => $users[$item[11]]['id'] ?? 0,
                        //     'down_user_id' => $users[$item[11]]['id'] ?? 0,
                        //     'remark' => $item[12],
                        //     'start_at' => $item[13],
                        //     'end_at' => $item[14],
                        //     'created_user' => $users[$item[15]]['id'] ?? 0,
                        //     'created_at' => $item[16],
                        //     'updated_user' => $users[$item[17]]['id'] ?? 0,
                        //     'updated_at' => $item[18],
                        //     'tenant_id' => $tenant_id,
                        // ]);
                        $parent = [
                            'type' => $type[$item[0]],
                            'code' => $item[1],
                            'status' => $status[$item[2]],
                            'down_status' => $down_status[$item[3]],
                            'shelf_status' => $shelf_status[$item[4]],
                            'warehouse_code' => $warehouse[$item[5]]['warehouse_code'],
                            'num' => $item[6],
                            'down_num' => $item[7],
                            'down_diff' => $item[8],
                            'shelf_num' => $item[9],
                            'shelf_diff' => $item[10],
                            'order_user' => $users[$item[11]]['id'] ?? 0,
                            'down_user_id' => $users[$item[11]]['id'] ?? 0,
                            'remark' => $item[12],
                            'start_at' => $item[13],
                            'end_at' => $item[14],
                            'created_user' => $users[$item[15]]['id'] ?? 0,
                            'created_at' => $item[16],
                            'updated_user' => $users[$item[17]]['id'] ?? 0,
                            'updated_at' => $item[18],
                            'tenant_id' => $tenant_id,
                        ];
                        $arr1[] = $parent;
                        if (count($arr1) > 500) {
                            DB::table('wms_stock_move_list')->insert($arr1);
                            $arr1 = [];
                        }
                    }

                    // $detail = WmsStockMoveDetail::create([
                    //     'origin_code' => $order->code,
                    //     'sku' => $item[20],
                    //     'bar_code' => $item[24],
                    //     // 'sup_id' => $supplier[$item[25]]['id'] ?? 0,
                    //     'total' => $item[26],
                    //     'location_code' => $item[27],
                    //     'target_location_code' => $item[31],
                    //     'down_num' => $item[33],
                    //     'shelf_num' => $item[35],
                    //     'quality_type' => $this->quality_type($item[37]),
                    //     'quality_level' => $this->quality_level($item[38]),
                    //     'remark' => $item[39],
                    //     'tenant_id' => $tenant_id,
                    // ]);
                    $arr2[] = [
                        'origin_code' => $parent['code'],
                        'sku' => $item[20],
                        'bar_code' => $item[24],
                        'total' => $item[26],
                        'location_code' => $item[27],
                        'target_location_code' => $item[31],
                        'down_num' => $item[33],
                        'shelf_num' => $item[35],
                        'quality_type' => $this->quality_type($item[37]),
                        'quality_level' => $this->quality_level($item[38]),
                        'remark' => $item[39],
                        'tenant_id' => $tenant_id,
                    ];
                    if (count($arr2) > 500) {
                        DB::table('wms_stock_move_details')->insert($arr2);
                        $arr2 = [];
                    }

                    // $sup_id = self::getSupId($item[25], $supplier);
                    // $where = [
                    //     'operation' => 11, 'origin_code' => $order->code, 'sku' => $item[20], 'sup_id' => $sup_id, 'warehouse_code' => $order->warehouse_code, 'bar_code' => $detail->bar_code, 'quality_type' => $detail->quality_type, 'quality_level' => $detail->quality_level, 'tenant_id' => $tenant_id,
                    // ];
                    // // operation 11移位下架 10移位上架 15快速移位
                    // if (in_array($order->type, [1, 2])) {
                    //     $logs = WmsStockLog::where($where)->get();
                    //     foreach ($logs as $log) {
                    //         $item = WmsStockMoveItem::create([
                    //             'origin_code' => $order->code, 'uniq_code' => $log->uniq_code, 'bar_code' => $detail->bar_code, 'sup_id' => $sup_id, 'quality_type' => $detail->quality_type, 'quality_level' => $detail->quality_level,
                    //             'batch_no' => $log->batch_no, 'location_code' => $detail->location_code, 'target_location_code' => $detail->target_location_code, 'target_area_code' => $detail->target_area_code, 'status' => 2, 'down_at' => $log->created_at, 'tenant_id' => $tenant_id, 'down_user_id' => $log->create_user_id, 'admin_user_id' => $log->admin_user_id, 'updated_at' => $log->updated_at,
                    //         ]);

                    //         // 更新上架状态
                    //         $where['operation'] = 12;
                    //         $log = WmsStockLog::where($where)->where('uniq_code', $item->uniq_code)->first();
                    //         if ($log) {
                    //             $item->update(['status' => 4, 'new_location_code' => $log->location_code, 'shelf_at' => $log->created_at, 'shelf_user_id' => $log->create_user_id, 'admin_user_id' => $log->admin_user_id, 'updated_at' => $log->updated_at,]);
                    //         }
                    //     }
                    // }
                    // if ($order->type == 3) {
                    //     $where['operation'] = 15;
                    //     $logs = WmsStockLog::where($where)->where('origin_value', $detail->location_code)->get();
                    //     foreach ($logs as $log) {
                    //         $item = WmsStockMoveItem::create([
                    //             'origin_code' => $order->code, 'uniq_code' => $log->uniq_code, 'bar_code' => $detail->bar_code, 'sup_id' => $sup_id, 'quality_type' => $detail->quality_type, 'quality_level' => $detail->quality_level,
                    //             'batch_no' => $log->batch_no, 'location_code' => $detail->location_code, 'target_location_code' => $detail->target_location_code, 'target_area_code' => $detail->target_area_code, 'status' => 4, 'down_at' => $log->created_at, 'tenant_id' => $tenant_id, 'down_user_id' => $log->create_user_id, 'new_location_code' => $log->location_code, 'shelf_at' => $log->created_at, 'shelf_user_id' => $log->create_user_id, 'admin_user_id' => $log->admin_user_id, 'updated_at' => $log->updated_at,
                    //         ]);
                    //     }
                    // }
                    // if (!$logs) {
                    //     Log::channel('daily2')->info('移位单未获取到唯一码信息', $where);
                    // }
                }
                if ($arr1) DB::table('wms_stock_move_list')->insert($arr1);
                if ($arr2) DB::table('wms_stock_move_details')->insert($arr2);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });

        // // 更新 wms_stock_move_details 的area_code 和 target_area_code
        // DB::statement("UPDATE wms_stock_move_details d ,wms_stock_move_list l,wms_area_location loc SET d.area_code = loc.area_code
        // WHERE d.origin_code=l.`code` AND l.warehouse_code=loc.warehouse_code AND d.location_code = loc.location_code AND d.area_code='' AND d.tenant_id=$tenant_id AND l.tenant_id=$tenant_id  AND loc.tenant_id=$tenant_id");

        // DB::statement("UPDATE wms_stock_move_details d ,wms_stock_move_list l,wms_area_location loc SET d.target_area_code = loc.area_code
        // WHERE d.origin_code=l.`code` AND l.warehouse_code=loc.warehouse_code AND d.target_location_code = loc.location_code AND d.target_area_code='' AND d.tenant_id=$tenant_id AND l.tenant_id=$tenant_id  AND loc.tenant_id=$tenant_id");

        // //更新 wms_stock_move_items 的 area_code target_area_code new_area_code

        // DB::statement("UPDATE wms_stock_move_items d ,wms_stock_move_list l,wms_area_location loc SET d.area_code = loc.area_code
        // WHERE d.origin_code=l.`code` AND l.warehouse_code=loc.warehouse_code AND d.location_code = loc.location_code AND d.area_code='' AND d.tenant_id=$tenant_id AND l.tenant_id=$tenant_id  AND loc.tenant_id=$tenant_id ");


        // DB::statement("UPDATE wms_stock_move_items d ,wms_stock_move_list l,wms_area_location loc SET d.target_area_code = loc.area_code
        // WHERE d.origin_code=l.`code` AND l.warehouse_code=loc.warehouse_code AND d.target_location_code = loc.location_code AND d.target_area_code='' AND d.tenant_id=$tenant_id AND l.tenant_id=$tenant_id  AND loc.tenant_id=$tenant_id");

        // DB::statement("UPDATE wms_stock_move_items d ,wms_stock_move_list l,wms_area_location loc SET d.new_area_code = loc.area_code WHERE d.origin_code=l.`code` AND l.warehouse_code=loc.warehouse_code AND d.new_location_code = loc.location_code AND d.new_area_code='' AND d.tenant_id=$tenant_id AND l.tenant_id=$tenant_id  AND loc.tenant_id=$tenant_id");
    }

    function moveItem()
    {
        dump("移位单明细......");
        $tenant_id = ADMIN_INFO['tenant_id'];
        dump('移位下架');
        $max = WmsStockLog::where(['operation' => 11, 'tenant_id' => ADMIN_INFO['tenant_id']])->orderBy('id', 'desc')->limit(1)->value('id');
        if (!$max) return;
        $begin = 0;
        DB::statement("TRUNCATE wms_stock_move_items");
        while (1) {
            dump($begin);
            if ($begin > $max) break;
            $end = $begin + 1000;
            $logs = WmsStockLog::where(['operation' => 11, 'tenant_id' => ADMIN_INFO['tenant_id']])->whereRaw("id>$begin and id<=$end")->get();

            $arr2 = [];
            foreach ($logs as $log) {
                $arr2[] = [
                    'origin_code' => $log->origin_code,
                    'uniq_code' => $log->uniq_code,
                    'sku' => $log->sku,
                    'bar_code' => $log->bar_code,
                    'sup_id' => $log->sup_id,
                    'quality_type' => $log->quality_level == 'A' ? 1 : 2,
                    'quality_level' => $log->quality_level,
                    'batch_no' => $log->batch_no,
                    'location_code' => $log->origin_value,
                    'status' => 2,
                    'down_at' => $log->created_at,
                    'tenant_id' => $tenant_id,
                    'down_user_id' => $log->create_user_id,
                    'new_location_code' => $log->location_code,
                    'admin_user_id' => $log->admin_user_id,
                    'updated_at' => $log->updated_at,
                ];
                if (count($arr2) > 500) {
                    DB::table('wms_stock_move_items')->insert($arr2);
                    $arr2 = [];
                }
            }
            if ($arr2) DB::table('wms_stock_move_items')->insert($arr2);
            $begin = $end;
        }

        dump('移位上架');
        $max = WmsStockMoveItem::where(['status' => 2, 'tenant_id' => ADMIN_INFO['tenant_id']])->orderBy('id', 'desc')->limit(1)->value('id');
        if (!$max) return;
        $begin = 0;
        while (1) {
            dump($begin);
            if ($begin > $max) break;
            $end = $begin + 1000;
            DB::statement("UPDATE wms_stock_move_items i,wms_stock_logs log
            SET i.status=4,i.new_location_code=log.location_code,i.target_location_code=log.location_code,i.shelf_at=log.created_at,i.shelf_user_id=log.admin_user_id,i.updated_at=log.created_at,i.admin_user_id=log.admin_user_id
            WHERE i.origin_code=log.origin_code AND log.operation=10 AND i.status=2 AND i.id>$begin AND i.id<=$end");
            $begin = $end;
        }
    }

    function checkRequest()
    {
        dump('盘点申请单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        // $shops = WmsShop::where($where)->select('code', 'name')->get()->keyBy('name')->toArray();
        // $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();
        // $supplier = Supplier::where($where)->select('id', 'name')->get()->keyBy('name')->toArray();
        $shops = null;
        $users = null;
        $supplier = null;

        $order_platform = null;
        $this->_init(42, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users, $supplier) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                $check_status = ['待盘点' => 0, '盘点中' => 1, '已盘点' => 2,];
                $type = ['动盘申请单' => 1];
                $status = ['暂存' => 0, '审核中' => 1, '已审核' => 2, '已驳回' => 3, '已取消' => 4];
                $source = ['手工创建' => 0, '系统创建' => 1,];

                $order = null;
                $arr1 = [];
                $arr2 = [];
                $parent = [];
                foreach ($data as $item) {

                    if ($item[0]) {
                        $parent = [
                            'type' => $type[$item[0]],
                            'code' => $item[1],
                            'source' => $source[$item[2]],
                            'status' => $status[$item[3]],
                            'check_status' => $check_status[$item[4]],
                            'total_num' => $item[5],
                            'total_diff' => $item[6],
                            'report_num' => $item[7],
                            'recover_num' => $item[8],
                            'current_diff' => $item[9],
                            'order_user' => $item[10],
                            'order_at' => $item[11],
                            'warehouse_code' => $warehouse[$item[12]]['warehouse_code'],
                            'remark' => $item[13],
                            'tenant_id' => $tenant_id,
                        ];
                        $arr1[] = $parent;
                        if (count($arr1) > 500) {
                            DB::table('wms_stock_check_request')->insert($arr1);
                            $arr1 = [];
                        }
                        // $order = WmsStockCheckRequest::create([
                        //     'type' => $type[$item[0]],
                        //     'code' => $item[1],
                        //     'source' => $source[$item[2]],
                        //     'status' => $status[$item[3]],
                        //     'check_status' => $check_status[$item[4]],
                        //     'total_num' => $item[5],
                        //     'total_diff' => $item[6],
                        //     'report_num' => $item[7],
                        //     'recover_num' => $item[8],
                        //     'current_diff' => $item[9],
                        //     'order_user' => $item[10],
                        //     'order_at' => strtotime($item[11]),
                        //     'warehouse_code' => $warehouse[$item[12]]['warehouse_code'],
                        //     'remark' => $item[13],
                        //     'tenant_id' => $tenant_id,
                        // ]);
                    }


                    // WmsStockCheckRequestDetail::create([
                    //     'origin_code' => $order->code,
                    //     'sku' => $item[15],
                    //     'quality_type' => $item[19] == '正品' ? 1 : 2,
                    //     'quality_level' => $item[19] == '正品' ? 'A' : "B",
                    //     'location_code' => $item[21],
                    //     'stock_num' => $item[23],
                    //     'check_num' => $item[24],
                    //     'check_time' => $item[26],
                    //     'last_code' => $item[27],
                    //     'remark' => $item[28],
                    //     'status' => 1,
                    //     'tenant_id' => $tenant_id,
                    // ]);

                    $arr2[] = [
                        'origin_code' => $parent['code'],
                        'sku' => $item[15],
                        'quality_type' => $item[19] == '正品' ? 1 : 2,
                        'quality_level' => $item[19] == '正品' ? 'A' : "B",
                        'location_code' => $item[21],
                        'stock_num' => $item[23],
                        'check_num' => $item[24],
                        'check_time' => $item[26],
                        'last_code' => $item[27],
                        'remark' => $item[28],
                        'status' => 1,
                        'tenant_id' => $tenant_id,
                    ];
                    if (count($arr2) > 500) {
                        DB::table('wms_stock_check_request_details')->insert($arr2);
                        $arr2 = [];
                    }
                }
                if ($arr1) DB::table('wms_stock_check_request')->insert($arr1);
                if ($arr2) DB::table('wms_stock_check_request_details')->insert($arr2);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
        DB::statement("UPDATE wms_stock_check_request_details d ,wms_spec_and_bar sku SET d.bar_code=sku.bar_code WHERE  d.sku=sku.sku AND  d.tenant_id=$tenant_id AND sku.tenant_id=$tenant_id");
    }

    function check()
    {
        dump('盘点单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        // $shops = WmsShop::where($where)->select('code', 'name')->get()->keyBy('name')->toArray();
        // $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();
        // $supplier = Supplier::where($where)->select('id', 'name')->get()->keyBy('name')->toArray();

        $shops = null;
        $users = null;
        $supplier = null;

        $model = new V2WmsOrder();
        $order_platform = null;
        $this->_init(43, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users, $supplier) {
            $data = $find->data;
            try {
                DB::beginTransaction();

                $check_status = ['待盘点' => 0, '盘点中' => 1, '已盘点' => 2,];
                $type = ['动态盘点单' => 1];
                $status = ['暂存' => 0, '审核中' => 1, '已审核' => 2, '已驳回' => 3, '已取消' => 4];
                $source = ['手工创建' => 0, '系统创建' => 1,];
                $check_type = ['明盘' => 0, '盲盘' => 1,];
                $map = ['优' => 'A', '一级' => 'B', '二级' => 'C', '三级' => 'D', '四级' => 'E',];
                $order = null;
                $arr1 = [];
                $arr2 = [];
                foreach ($data as $item) {
                    if ($item[0]) {
                        // $order = WmsStockCheckList::create([
                        //     'type' => $type[$item[0]],
                        //     'code' => $item[1],
                        //     'created_at' => $item[2],
                        //     'status' => $status[$item[3]],
                        //     'check_status' => $check_status[$item[4]],
                        //     'check_type' => $check_type[$item[5]],
                        //     'warehouse_code' => $warehouse[$item[7]]['warehouse_code'],
                        //     'start_at' => $item[8],
                        //     'end_at' => $item[9],
                        //     'remark' => $item[11],
                        //     'tenant_id' => $tenant_id,
                        //     'created_at' => $item[8],
                        // ]);
                        $parent = [
                            'type' => $type[$item[0]],
                            'code' => $item[1],
                            'created_at' => $item[2],
                            'status' => $status[$item[3]],
                            'check_status' => $check_status[$item[4]],
                            'check_type' => $check_type[$item[5]],
                            'warehouse_code' => $warehouse[$item[7]]['warehouse_code'],
                            'start_at' => $item[8],
                            'end_at' => $item[9],
                            'remark' => $item[11],
                            'tenant_id' => $tenant_id,
                            'created_at' => $item[8],
                        ];
                        $arr1[] = $parent;
                        if (count($arr1) > 500) {
                            DB::table('wms_stock_check_list')->insert($arr1);
                            $arr1 = [];
                        }
                    }
                    // WmsStockCheckDetail::create([
                    //     'origin_code' => $order->code,
                    //     'sku' => $item[13],
                    //     'location_code' => $item[17],
                    //     'stock_num' => $item[19],
                    //     'check_num' => $item[20],
                    //     'quality_type' => $this->quality_type($item[22]),
                    //     'quality_level' => $this->quality_level($item[23]),
                    //     'remark' => $item[24],
                    //     'tenant_id' => $tenant_id,
                    // ]);
                    $arr2[] = [
                        'origin_code' => $parent['code'],
                        'sku' => $item[13],
                        'location_code' => $item[17],
                        'stock_num' => $item[19],
                        'check_num' => $item[20],
                        'quality_type' => $this->quality_type($item[22]),
                        'quality_level' => $this->quality_level($item[23]),
                        'remark' => $item[24],
                        'tenant_id' => $tenant_id,
                    ];
                    if (count($arr2) > 500) {
                        DB::table('wms_stock_check_details')->insert($arr2);
                        $arr2 = [];
                    }
                }
                if ($arr1) DB::table('wms_stock_check_list')->insert($arr1);
                if ($arr2) DB::table('wms_stock_check_details')->insert($arr2);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });

        DB::statement("UPDATE wms_stock_check_details d ,wms_spec_and_bar sku SET d.bar_code=sku.bar_code WHERE  d.sku=sku.sku AND  d.tenant_id=$tenant_id AND sku.tenant_id=$tenant_id");
    }

    function difference()
    {
        dump('差异处理记录......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        // $shops = WmsShop::where($where)->select('code', 'name')->get()->keyBy('name')->toArray();
        $shops = null;
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();
        $supplier = Supplier::where($where)->select('id', 'name')->get()->keyBy('name')->toArray();

        $model = new V2WmsOrder();
        $order_platform = null;
        $this->_init(44, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users, $supplier) {
            $data = $find->data;
            try {
                DB::beginTransaction();

                $type = ['少货' => 1, '多货' => 2];
                $origin_type = ['盘点' => 1,];
                $status = ['审核中' => 0, '已审核' => 1, '已驳回' => 2,];
                $order = null;
                $arr1 = [];
                $arr2 = [];
                $parent = [];
                foreach ($data as $item) {
                    if ($item[2]) {
                        // $order = WmsStockDifference::create([
                        //     'remark' => $item[0],
                        //     'created_at' => $item[1],
                        //     'type' => $type[$item[2]],
                        //     'origin_type' => $origin_type[$item[3]],
                        //     'origin_code' => $item[4],
                        //     'diff_num' => $item[5],
                        //     'status' => $status[$item[6]],
                        //     'code' => $item[7],
                        //     'warehouse_code' => $warehouse[$item[8]]['warehouse_code'],
                        //     'order_user' => $users[$item[9]]['id'] ?? 0,
                        //     'create_user_id' => $users[$item[9]]['id'] ?? 0,
                        //     'admin_user_id' => $users[$item[10]]['id'] ?? 0,
                        //     'updated_at' => $item[11],
                        //     'tenant_id' => $tenant_id,
                        // ]);
                        $parent = [
                            'remark' => $item[0],
                            'created_at' => $item[1],
                            'type' => $type[$item[2]],
                            'origin_type' => $origin_type[$item[3]],
                            'origin_code' => $item[4],
                            'diff_num' => $item[5],
                            'status' => $status[$item[6]],
                            'code' => $item[7],
                            'warehouse_code' => $warehouse[$item[8]]['warehouse_code'],
                            'order_user' => $users[$item[9]]['id'] ?? 0,
                            'create_user_id' => $users[$item[9]]['id'] ?? 0,
                            'admin_user_id' => $users[$item[10]]['id'] ?? 0,
                            'updated_at' => $item[11],
                            'tenant_id' => $tenant_id,
                        ];
                        $arr1[] = $parent;
                        if (count($arr1) > 500) {
                            DB::table('wms_stock_differences')->insert($arr1);
                            $arr1 = [];
                        }
                    }
                    // WmsStockCheckDifference::create([
                    //     'origin_code' => $order->code,
                    //     'sku' => $item[13],
                    //     'batch_no' => $item[17],
                    //     'uniq_code' => $item[18],
                    //     'location_code' => $item[19],
                    //     'num' => $item[21],
                    //     'sup_id' => self::getSupId($item[22], $supplier),
                    //     'quality_type' => $this->quality_type($item[23]),
                    //     'quality_level' => $this->quality_level($item[24]),
                    //     'remark' => $item[25],
                    //     'tenant_id' => $tenant_id,
                    //     'admin_user_id' => $order->admin_user_id,
                    //     'created_at' => $order->created_at,
                    //     'updated_at' => $order->updated_at,
                    //     'status' => $order->status,
                    //     'request_code' => $order->origin_code,
                    // ]);
                    $arr2[] = [
                        'origin_code' => $parent['code'],
                        'sku' => $item[13],
                        'batch_no' => $item[17],
                        'uniq_code' => $item[18],
                        'location_code' => $item[19],
                        'num' => $item[21],
                        'sup_id' => self::getSupId($item[22], $supplier),
                        'quality_type' => $this->quality_type($item[23]),
                        'quality_level' => $this->quality_level($item[24]),
                        'remark' => $item[25],
                        'tenant_id' => $tenant_id,
                        'admin_user_id' => $parent['admin_user_id'],
                        'created_at' => $parent['created_at'],
                        'updated_at' => $parent['updated_at'],
                        'status' => $parent['status'],
                        'request_code' => $parent['origin_code'],
                    ];
                    if (count($arr2) > 500) {
                        DB::table('wms_stock_check_differences')->insert($arr2);
                        $arr2 = [];
                    }
                }
                if ($arr1) DB::table('wms_stock_differences')->insert($arr1);
                if ($arr2) DB::table('wms_stock_check_differences')->insert($arr2);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
        DB::statement("UPDATE wms_stock_check_differences d ,wms_spec_and_bar sku SET d.bar_code=sku.bar_code WHERE  d.sku=sku.sku AND  d.tenant_id=$tenant_id AND sku.tenant_id=$tenant_id");
    }

    function checkBill()
    {
        dump('盘盈亏单......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        // $shops = WmsShop::where($where)->select('code', 'name')->get()->keyBy('name')->toArray();
        $shops = null;
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();
        $supplier = Supplier::where($where)->select('id', 'name')->get()->keyBy('name')->toArray();

        $order_platform = null;
        $this->_init(45, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users, $supplier) {
            $data = $find->data;
            try {
                DB::beginTransaction();

                $type = ['盘盈单' => 1, '盘亏单' => 2];
                $status = ['已暂存' => 0, '已审核' => 1,];
                $order = null;
                foreach ($data as $item) {
                    if ($item[2]) {
                        $diff_code = $item[7];
                        $order = WmsStockCheckBill::create([
                            'remark' => $item[0],
                            'order_at' => $item[1],
                            'type' => $type[$item[2]],
                            'code' => $item[3],
                            'origin_code' => $item[4],
                            'diff_num' => $item[5],
                            'status' => $status[$item[6]],
                            'warehouse_code' => $warehouse[$item[8]]['warehouse_code'],
                            'order_user' => $users[$item[9]]['id'] ?? 0,
                            'tenant_id' => $tenant_id,
                        ]);
                    }
                    $where = [
                        'origin_code' => $diff_code,
                        'sup_id' => self::getSupId($item[11], $supplier),
                        'sku' => $item[12], 'batch_no' => $item[16], 'uniq_code' => $item[17], 'num' => $item[18], 'quality_type' => $item[19] == '正品' ? 1 : 2, 'tenant_id' => $tenant_id,
                    ];
                    WmsStockCheckDifference::where($where)->update(['bill_code' => $order->code]);
                }
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
    }

    private function _recvDetail($log)
    {
        $recv =  RecvDetail::where(['sku' => $log->sku, 'uniq_code' => $log->uniq_code, 'lot_num' => $log->batch_no, 'warehouse_code' => $log->warehouse_code])->first();

        $sale_status = $recv && $recv->ib_confirm ? 1 : 0;
        return [
            'update' => !$recv ? [] : [
                'buy_price' => $recv->buy_price, 'inv_type' => $recv->inv_type, 'arr_id' => $recv->arr_id, 'recv_id' => $recv->recv_id,
            ],
            'sale_status' => $sale_status,
            'recv' => $recv

        ];
    }
    // 已发货
    private function _sendout($log, $order)
    {
        $where = ['tenant_id' => $this->tenant_id];
        // lock_type 0-未锁定 1-销售 2-调拨 3-其他出库
        $inv_status = $order->type == 2 ? 8 : 7;
        $update = ['in_wh_status' => 4, 'sale_status' => 4, 'inv_status' => $inv_status, 'lock_type' => $order->type, 'lock_code' => $order->source_code];
        $update2 = $this->_recvDetail($log)['update'];
        Inventory::where(['in_wh_status' => 0, 'sale_status' => 0, 'inv_status' => 0])->where($where)
            ->where(['uniq_code' => $log->uniq_code, 'warehouse_code' => $log->warehouse_code])
            ->update(array_merge($update, $update2));
    }
    // 架上可售
    private function _cansale($log, $order)
    {
        $where = ['tenant_id' => $this->tenant_id];
        $update = ['in_wh_status' => 3, 'sale_status' => 1, 'inv_status' => 5, 'is_qc' => 1, 'is_putway' => 1,];
        $update2 = $this->_recvDetail($log)['update'];
        Inventory::where(['in_wh_status' => 0, 'sale_status' => 0, 'inv_status' => 0])->where($where)
            ->where(['uniq_code' => $log->uniq_code, 'warehouse_code' => $log->warehouse_code])
            ->update(array_merge($update, $update2));
    }

    function skuDetail2()
    {
        $where = ['tenant_id' => $this->tenant_id];
        dump('唯一码明细状态更新......');
        // 已出库
        $orders = ObOrder::where(['status' => 4, 'request_status' => 4])->where($where)->get();
        foreach ($orders as $order) {
            dump('已出库 ' . $order->request_code);
            // type 1-销售出库 2-调拨出库 3-其他出库
            $logs = WmsStockLog::where(['operation' => 9, 'origin_code' => $order->request_code])->where($where)->get();
            foreach ($logs as $log) {
                $new_log = WmsStockLog::where(['uniq_code' => $log->uniq_code])->orderBy('created_at', 'desc')->first();
                // 最后一个操作记录是发货
                if ($new_log->operation == 9) $this->_sendout($new_log, $order);
                // 最新的操作记录是供应商调整
                if ($new_log->operation == 4) $this->_cansale($log, $order);
            }
        }

        // 发货中
        $orders = ObOrder::where(['status' => 2, 'request_status' => 3])->where($where)->get();
        foreach ($orders as $order) {
            dump('发货中 ' . $order->request_code);
            $logs = WmsStockLog::where(['operation' => 8, 'origin_code' => $order->request_code])->where($where)->get();
            foreach ($logs as $log) {
                $update = ['in_wh_status' => 9, 'sale_status' => 3, 'inv_status' => 6, 'lock_type' => $order->type, 'lock_code' => $order->source_code,];
                $update2 = $this->_recvDetail($log)['update'];
                Inventory::where(['in_wh_status' => 0, 'sale_status' => 0, 'inv_status' => 0])->where($where)
                    ->where(['uniq_code' => $log->uniq_code, 'warehouse_code' => $log->warehouse_code])
                    ->update(array_merge($update, $update2));
            }
        }

        // 待发货
        $orders = ObOrder::where(['status' => 2, 'request_status' => 1])->where($where)->get();
        foreach ($orders as $order) {
            dump('待发货 ' . $order->request_code);
            $logs = WmsStockLog::where(['operation' => 7, 'origin_code' => $order->request_code])->where($where)->get();
            foreach ($logs as $log) {
                $update = ['in_wh_status' => 3, 'sale_status' => 2, 'inv_status' => 6, 'lock_type' => $order->type, 'lock_code' => $order->source_code,];
                $update2 = $this->_recvDetail($log)['update'];
                Inventory::where(['in_wh_status' => 0, 'sale_status' => 0, 'inv_status' => 0])->where($where)
                    ->where(['uniq_code' => $log->uniq_code, 'warehouse_code' => $log->warehouse_code])
                    ->update(array_merge($update, $update2));
            }
        }

        // 架上可售
        $orders = ArrivalRegist::where(['doc_status' => 4])->whereIn('arr_status', [3, 4])->where($where)->get();
        foreach ($orders as $order) {
            dump('架上可售 ' . $order->arr_code);
            $logs = WmsStockLog::where(['operation' => 3, 'origin_code' => $order->arr_code])->where($where)->get();
            foreach ($logs as $log) {
                $this->_cansale($log, $order);
            }
        }


        $orders = ArrivalRegist::where(['doc_status' => 1, 'arr_status' => 3])->where($where)->get();
        foreach ($orders as $order) {
            dump('待上架/待质检 ' . $order->arr_code);
            // 待上架
            $logs = WmsStockLog::where(['operation' => 3, 'origin_code' => $order->arr_code])->where($where)->get();
            foreach ($logs as $log) {
                $res = $this->_recvDetail($log);
                $update2 = $res['update'];
                $sale_status = $res['sale_status'];
                $update = ['in_wh_status' => 2, 'sale_status' => $sale_status, 'inv_status' => 1, 'is_qc' => 1,];
                Inventory::where(['in_wh_status' => 0, 'sale_status' => 0, 'inv_status' => 0])
                    ->where(['uniq_code' => $log->uniq_code, 'warehouse_code' => $log->warehouse_code])->where($where)
                    ->update(array_merge($update, $update2));
                if ($res['recv']) $res['recv']->update(['is_qc' => 1]);
            }

            // 待质检
            $logs = WmsStockLog::where(['operation' => 1, 'origin_code' => $order->arr_code])->where($where)->get();
            foreach ($logs as $log) {
                $res = $this->_recvDetail($log);
                $update2 = $res['update'];
                $sale_status = $res['sale_status'];
                $update = ['in_wh_status' => 1, 'sale_status' => $sale_status, 'inv_status' => 0, 'is_qc' => 0,];
                Inventory::where(['in_wh_status' => 0, 'sale_status' => 0, 'inv_status' => 0])->where($where)
                    ->where(['uniq_code' => $log->uniq_code, 'warehouse_code' => $log->warehouse_code])
                    ->update(array_merge($update, $update2));
            }
        }
    }

    function skuDetail3()
    {
        $tenant_id = $this->tenant_id;
        dump('唯一码明细状态更新......');
        $max = $this->_stockLogMax();
        while (1) {
            if ($max <= 0) break;
            dump($max);
            $min = $max - 1000;
            $res = DB::select("select b.request_code,a.uniq_code,a.warehouse_code,a.operation,b.type,a.origin_type,a.created_at,b.status,b.request_status,b.source_code,rd.ib_confirm,rd.buy_price,rd.inv_type,rd.arr_id,rd.recv_id
            from wms_stock_logs as a 
            left join wms_shipping_request as b on a.origin_code = b.request_code AND b.tenant_id=$tenant_id
            left JOIN wms_recv_detail rd ON a.uniq_code=rd.uniq_code AND a.sku=rd.sku AND a.warehouse_code=rd.warehouse_code AND rd.tenant_id=$tenant_id
            WHERE a.operation in (4,9,8,7,5,10) AND a.id >$min AND a.id<=$max AND a.tenant_id=$tenant_id  AND EXISTS (SELECT 1 FROM wms_inv_goods_detail inv WHERE a.uniq_code=inv.uniq_code AND inv.tenant_id=$tenant_id AND inv.in_wh_status=0 AND inv.sale_status=0 AND inv.inv_status=0)
            order by  a.created_at DESC");
            $max = $min;
            if (!$res) continue;
            foreach ($res as $item) {

                $update = [];
                $update2 = [];
                if ($item->recv_id) $update2 = ['buy_price' => $item->buy_price, 'inv_type' => $item->inv_type, 'arr_id' => $item->arr_id, 'recv_id' => $item->recv_id,];
                $sale_status = $item->recv_id && $item->ib_confirm ? 1 : 0;

                // 已发货
                if ($item->operation == 9 && $item->status = 4 and $item->request_status == 4) {
                    $inv_status = $item->type == 2 ? 8 : 7;
                    $update = ['in_wh_status' => 4, 'sale_status' => 4, 'inv_status' => $inv_status, 'lock_type' => $item->type, 'lock_code' => $item->source_code,];
                }
                // 发货中
                if ($item->operation == 8 && $item->status = 2 and $item->request_status == 3) {
                    $update = ['in_wh_status' => 9, 'sale_status' => 3, 'inv_status' => 6, 'lock_type' => $item->type, 'lock_code' => $item->source_code,];
                }
                // 待发货
                if ($item->operation == 7 && $item->status = 2 and $item->request_status == 1) {
                    $update = ['in_wh_status' => 3, 'sale_status' => 2, 'inv_status' => 6, 'lock_type' => $item->type, 'lock_code' => $item->source_code,];
                }

                if ($update) {
                    Inventory::where(['in_wh_status' => 0, 'sale_status' => 0, 'inv_status' => 0, 'tenant_id' => $tenant_id, 'uniq_code' => $item->uniq_code, 'warehouse_code' => $item->warehouse_code])
                        ->update(array_merge($update, $update2));
                }
            }
        }
    }

    function skuDetail4()
    {
        $start = time();
        $tenant_id = $this->tenant_id;
        dump('唯一码明细状态更新......');
        $max = $this->_stockLogMax();
        while (1) {
            if ($max <= 0) break;
            dump($max);
            $min = $max - 1000;
            $res = DB::select("select a.operation,a.origin_code,a.uniq_code,a.warehouse_code,arr.doc_status,arr.arr_status,rd.ib_confirm,rd.buy_price,rd.inv_type,rd.arr_id,rd.recv_id,rd.id as rd_id
        from wms_stock_logs as a 
        left JOIN wms_arrival_regist arr ON a.origin_code=arr.arr_code AND arr.tenant_id=$tenant_id
        left JOIN wms_recv_detail rd ON a.uniq_code=rd.uniq_code AND a.sku=rd.sku AND a.warehouse_code=rd.warehouse_code  AND rd.tenant_id=$tenant_id
        WHERE a.operation in (1,3) AND a.id >$min AND a.id<=$max AND a.tenant_id=$tenant_id  AND EXISTS (SELECT 1 FROM wms_inv_goods_detail inv WHERE a.uniq_code=inv.uniq_code AND inv.tenant_id=$tenant_id AND inv.in_wh_status=0 AND inv.sale_status=0 AND inv.inv_status=0)
        order by  a.created_at DESC");
            $max = $min;
            if (!$res) continue;
            foreach ($res as $item) {

                $update = [];
                $update2 = [];
                if ($item->recv_id) $update2 = ['buy_price' => $item->buy_price, 'inv_type' => $item->inv_type, 'arr_id' => $item->arr_id, 'recv_id' => $item->recv_id,];
                $sale_status = $item->recv_id && $item->ib_confirm ? 1 : 0;
                // 架上可售
                if ($item->operation == 3 && $item->doc_status == 4 && in_array($item->arr_status, [3, 4])) {
                    $update = ['in_wh_status' => 3, 'sale_status' => 1, 'inv_status' => 5, 'is_qc' => 1, 'is_putway' => 1,];
                }
                // 待上架
                if ($item->operation == 3 && $item->doc_status == 1 && $item->arr_status == 3) {
                    $update = ['in_wh_status' => 2, 'sale_status' => $sale_status, 'inv_status' => 1, 'is_qc' => 1,];
                    if ($item->rd_id) RecvDetail::where(['id' => $item->rd_id, 'tenant_id' => $tenant_id])->update(['is_qc' => 1]);
                }

                // 待质检
                if ($item->operation == 1 && $item->doc_status == 1 && $item->arr_status == 3) {
                    $update = ['in_wh_status' => 1, 'sale_status' => $sale_status, 'inv_status' => 0, 'is_qc' => 0,];
                }

                if ($update) {
                    Inventory::where(['in_wh_status' => 0, 'sale_status' => 0, 'inv_status' => 0, 'tenant_id' => $tenant_id, 'uniq_code' => $item->uniq_code, 'warehouse_code' => $item->warehouse_code])->update(array_merge($update, $update2));
                }
            }
        }
        dump('耗时 ' . (time() - $start));
    }



    function skuDetail5()
    {
        $start = time();
        $tenant_id = $this->tenant_id;
        dump('唯一码明细状态更新......');
        $max = $this->_stockLogMax();
        while (1) {
            if ($max <= 0) break;
            dump($max);
            $min = $max - 1000;
            $res = DB::select("select a.operation,a.type,a.origin_code,a.uniq_code,a.warehouse_code,arr.doc_status,arr.arr_status,rd.ib_confirm,rd.buy_price,rd.inv_type,rd.arr_id,rd.recv_id,rd.id as rd_id,rd.is_qc,rd.is_putway
        from wms_stock_logs as a 
        left JOIN wms_arrival_regist arr ON a.origin_code=arr.arr_code AND arr.tenant_id=$tenant_id
        left JOIN wms_recv_detail rd ON a.uniq_code=rd.uniq_code AND a.sku=rd.sku AND a.warehouse_code=rd.warehouse_code  AND rd.tenant_id=$tenant_id
        WHERE a.operation IN (1,10) AND a.id >$min AND a.id<=$max AND a.tenant_id=$tenant_id  AND EXISTS (SELECT 1 FROM wms_inv_goods_detail inv WHERE a.uniq_code=inv.uniq_code AND inv.tenant_id=$tenant_id AND inv.in_wh_status=0 AND inv.sale_status=0 AND inv.inv_status=0)
        order by  a.created_at DESC");
            $max = $min;
            if (!$res) continue;
            foreach ($res as $item) {

                $update = [];
                $update2 = [];
                if ($item->recv_id) $update2 = ['buy_price' => $item->buy_price, 'inv_type' => $item->inv_type, 'arr_id' => $item->arr_id, 'recv_id' => $item->recv_id,];
                $sale_status = $item->recv_id && $item->ib_confirm ? 1 : 0;
                //                 `in_wh_status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '在仓状态 0-暂存 1-已收货 ,2-已质检 3-已上架 4-已出库 5-调拨中 6-冻结 7-作废 8-移位中 9-已下架',
                //   `sale_status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '销售 0-不可售 1-待售 ,2-已匹配销售单/调拨单 3-已配货 4-已发货 5-冻结',
                //   `inv_status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '库存状态 0-在仓 1-架上 2-可售 3-待上架 4-架上待确认 5-架上可售 6-架上锁定 7-待发 8-调拨 9-冻结',
                $in_wh_status = 0;
                $inv_status = 0;
                if ($item->is_putway) {
                    $in_wh_status = 3;
                    $inv_status = $sale_status == 1 ? 5 : 4;
                } elseif ($item->is_qc) {
                    $in_wh_status = 2;
                    $inv_status = $sale_status == 1 ? 3 : 2;
                } elseif ($item->recv_id) {
                    $in_wh_status = 1;
                    $inv_status = $sale_status == 1 ? 2 : 0;
                }
                if ($item->operation == 10 && $item->type == 12) {
                    $in_wh_status = 3;
                    $sale_status = 1;
                    $inv_status = 5;
                }

                if ($item->doc_status == 4 && $item->arr_status == 4) {
                    $in_wh_status = 3;
                    $sale_status = 1;
                    $inv_status = 5;
                }


                $update2['in_wh_status'] = $in_wh_status;
                $update2['sale_status'] = $sale_status;
                $update2['inv_status'] = $inv_status;
                $update2['is_qc'] = $item->is_qc;
                $update2['is_qc'] = $item->is_putway;

                Inventory::where(['in_wh_status' => 0, 'sale_status' => 0, 'inv_status' => 0, 'tenant_id' => $tenant_id, 'uniq_code' => $item->uniq_code, 'warehouse_code' => $item->warehouse_code])->update(array_merge($update, $update2));
            }
        }
        dump('耗时 ' . (time() - $start));
    }



    function productStockLog()
    {
        dump('产品库存流水......');
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $this->tenant_id];
        $warehouse = Warehouse::where($where)->select('warehouse_name', 'warehouse_code')->get()->keyBy('warehouse_name')->toArray();
        $shops = null;
        $users = ModelsAdminUsers::where($where)->select('id', 'username')->get()->keyBy('username')->toArray();
        // $supplier = Supplier::where($where)->select('id', 'name')->get()->keyBy('name')->toArray();
        $supplier = null;

        $order_platform = null;
        $this->_init(46, function ($find) use ($tenant_id, $order_platform, $shops, $warehouse, $users, &$supplier) {
            // $id = WmsProductStockLog::where('tenant_id', $tenant_id)->min('id');
            // $id = (!$id) ? $this->productStockLogMax : $id - 1;
            $data = $find->data;
            try {
                DB::beginTransaction();

                $node = array_flip(WmsProductStockLog::maps('node'));
                $origin_type = array_flip(WmsProductStockLog::maps('origin_type'));
                $inv_category = array_flip(WmsProductStockLog::maps('inv_category'));
                $inv_type = array_flip(WmsProductStockLog::maps('inv_type'));
                $order = null;
                $arr1 = [];
                foreach ($data as $item) {

                    $arr1[] = [
                        // 'id' => $id--,
                        'node' => $node[$item[0]],
                        'type' => $origin_type[$item[1]],
                        'source_code' => $item[2],
                        // 'sup_id' => self::getSupId(trim($item[3]), $supplier),
                        'sup_id' => 0,
                        'sup_name' => trim($item[3]),
                        'inv_type' => $inv_type[$item[4]],
                        'sku' => $item[5],
                        'quality_type' => $this->quality_type($item[9]),
                        'quality_level' => $this->quality_level($item[10]),
                        'batch_no' => $item[11],
                        'uniq_code' => $item[12],
                        'inv_category' => $inv_category[trim($item[13])],
                        'old_num' => $item[14],
                        'change_num' => $item[15],
                        'new_num' => $item[16],
                        'cost_amount' => $item[17],
                        'cost_price' => $item[18],
                        'weighted_cost_price' => $item[19],
                        'warehouse_code' => $warehouse[$item[20]]['warehouse_code'],
                        'warehouse_name' => $item[20],
                        'origin_type' => $origin_type[$item[21]],
                        'origin_code' => $item[22],
                        'third_no' => $item[23],
                        'remark' => $item[24],
                        'ip' => $item[25],
                        'created_at' => $item[27],
                        'admin_user_id' => $users[$item[28]]['id'] ?? 0,
                        'updated_at' => $item[29],
                        'tenant_id' => $tenant_id,
                        'dateline' => date('Y-m-d', strtotime($item[27])),
                    ];
                    if (count($arr1) > 500) {
                        DB::table('wms_product_stock_logs')->insert($arr1);
                        $arr1 = [];
                    }

                    // WmsProductStockLog::create([
                    //     'id' => $id--,
                    //     'node' => $node[$item[0]],
                    //     'type' => $origin_type[$item[1]],
                    //     'source_code' => $item[2],
                    //     'sup_id' => $supplier[trim($item[3])]['id'] ?? 0,
                    //     'sup_name' => trim($item[3]),
                    //     'inv_type' => $inv_type[$item[4]],
                    //     'sku' => $item[5],
                    //     'quality_type' => $this->quality_type($item[9]),
                    //     'quality_level' => $this->quality_level($item[10]),
                    //     'batch_no' => $item[11],
                    //     'uniq_code' => $item[12],
                    //     'inv_category' => $inv_category[trim($item[13])],
                    //     'old_num' => $item[14],
                    //     'change_num' => $item[15],
                    //     'new_num' => $item[16],
                    //     'cost_amount' => $item[17],
                    //     'cost_price' => $item[18],
                    //     'weighted_cost_price' => $item[19],
                    //     'warehouse_code' => $warehouse[$item[20]]['warehouse_code'],
                    //     'warehouse_name' => $item[20],
                    //     'origin_type' => $origin_type[$item[21]],
                    //     'origin_code' => $item[22],
                    //     'third_no' => $item[23],
                    //     'remark' => $item[24],
                    //     'ip' => $item[25],
                    //     'created_at' => $item[27],
                    //     'admin_user_id' => $users[$item[28]]['id'] ?? 0,
                    //     'updated_at' => $item[29],
                    //     'tenant_id' => $tenant_id,
                    //     'dateline' => date('Y-m-d', strtotime($item[27])),
                    // ]);
                }
                if ($arr1) DB::table('wms_product_stock_logs')->insert($arr1);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                // Log::channel('daily2')->info($e->__toString());
                throw $e;
                // $this->setErrorMsg($e->getMessage());
            }
        });
    }

    function receive2()
    {
        $tenant_id = $this->tenant_id;
        $where = ['tenant_id' => $tenant_id];
        $users = ModelsAdminUsers::where($where)->selectRaw('id,username')->get()->keyBy('username')->toArray();
        $warehouse = Warehouse::where($where)->selectRaw('warehouse_code,warehouse_name')->get()->keyBy('warehouse_name')->toArray();

        dump('收货单初始化......');
        $this->_init(19, function ($find) use ($tenant_id, $users, $warehouse) {
            $data = $find->data;
            $recv_type = ['采购收货' => 1, '调拨收货' => 2, '退货收货' => 3, '其他收货' => 4];
            $doc_status = ['暂存' => 1, '已审核' => 2, '已作废' => 3];
            $recv_status = ['收货中' => 0, '已完成' => 1, '已收货' => 1];
            $recv_methods = ['逐件收货' => 1, '其他' => 2];
            try {
                DB::beginTransaction();
                $arr = [];
                foreach ($data as $item) {
                    if (!$item[0]) continue;
                    $arr[] = [
                        'recv_code' => $item[1],
                        'tenant_id' => $tenant_id,
                        'warehouse_code' => $warehouse[$item[5]]['warehouse_code'] ?? '',
                        'recv_type' => $recv_type[$item[0]],
                        'source_code' => $item[4],
                        'doc_status' => $doc_status[$item[2]],
                        'recv_status' => $recv_status[$item[3]],
                        'arr_code' => $item[4],
                        'recv_num' => $item[6],
                        'created_user' => $users[$item[8]]['id'] ?? 0,
                        'created_at' => $item[9],
                        'done_at' => $item[10],
                        'recv_methods' => $recv_methods[$item[11]],
                        'updated_user' => $users[$item[15]]['id'] ?? 0,
                        'updated_at' => $item[16],
                    ];
                    if (count($arr) > 500) {
                        DB::table('wms_recv_order')->insert($arr);
                        $arr = [];
                    }
                }
                if ($arr) DB::table('wms_recv_order')->insert($arr);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
            return;
        });
    }

    function recvDetailInit()
    {
        $tenant_id = $this->tenant_id;
        $max = WmsStockLog::max('id');
        dump('添加收货单详情');
        DB::statement("TRUNCATE wms_recv_detail");
        $begin = 0;
        while (1) {
            if ($begin > $max) break;
            $end = $begin + 1000;
            dump($begin);
            $count = WmsStockLog::where('tenant_id', $tenant_id)->whereIn('type', [1, 11])->whereRaw("id>$begin and id<=$end")->count();
            if ($count) {
                DB::statement("insert into wms_recv_detail(
                    recv_code,sku,bar_code,uniq_code,lot_num,warehouse_code,location_code,quality_type,quality_level,created_user,tenant_id,created_at,done_at,updated_user,updated_at
                    )
                    (SELECT source_code,sku,bar_code,uniq_code,batch_no,warehouse_code,location_code,quality_type,quality_level,create_user_id,tenant_id,created_at,created_at,create_user_id,created_at  from wms_stock_logs WHERE type IN (1,11) and tenant_id=$tenant_id and id>$begin and id<=$end)");
            }
            $begin = $end;
        }
        return;
    }

    function recvUpdate()
    {
        $tenant_id = $this->tenant_id;

        dump('更新收货单登记单id');
        $max = RecvOrder::whereRaw("arr_code>''  AND doc_status=2 and recv_status=1 and  arr_id=0")->where('tenant_id', $tenant_id)->orderBy('id', 'desc')->limit(1)->value('id');
        $begin = 0;

        while (true) {
            dump($begin);
            if ($max < $begin) break;
            $end = $begin + 100;
            $res = DB::select("SELECT ro.id,ro.recv_code,arr.id as arr_id FROM wms_recv_order ro 
            left JOIN wms_arrival_regist arr ON ro.arr_code = arr.arr_code
            WHERE ro.arr_code>''  AND ro.doc_status=2 and ro.recv_status=1 and  ro.arr_id=0 and ro.tenant_id=$tenant_id  AND ro.id>$begin AND ro.id<=$end");
            $sql = '';
            foreach ($res as $item) {
                $sql .= sprintf("UPDATE wms_recv_order SET arr_id=%d WHERE id=%d;UPDATE wms_recv_detail SET arr_id=%d,recv_id=%d WHERE recv_code='%s';", $item->arr_id, $item->id, $item->arr_id, $item->id, $item->recv_code);
            }
            if ($sql) DB::getPdo()->exec($sql);
            $begin = $end;
        }

        $max = WmsStockLog::where(['operation' => 5])->max('id');
        dump('更新上架状态');
        $begin = 0;
        while (1) {
            if ($begin > $max) break;
            dump($begin);
            $end = $begin + 1000;

            DB::statement("UPDATE wms_stock_logs log ,wms_arrival_regist arr ,wms_recv_detail rd  
            SET rd.is_qc=1,rd.is_putway=1
            WHERE log.origin_code=arr.arr_code AND arr.id=rd.arr_id AND log.uniq_code=rd.uniq_code AND log.operation=5 AND rd.is_putway=0 AND  log.id>$begin AND log.id<=$end");
            $begin = $end;
        }

        $max = WmsStockLog::where(['operation' => 3])->max('id');
        dump('更新质检状态');
        $begin = 0;
        while (1) {
            if ($begin > $max) break;
            dump($begin);
            $end = $begin + 1000;

            DB::statement("UPDATE wms_stock_logs log ,wms_arrival_regist arr ,wms_recv_detail rd  
            SET rd.is_qc=1,rd.is_putway=1
            WHERE log.origin_code=arr.arr_code AND arr.id=rd.arr_id AND log.uniq_code=rd.uniq_code AND log.operation=3 AND rd.is_qc=0 AND  log.id>$begin AND log.id<=$end");
            $begin = $end;
        }

        // DB::statement("UPDATE wms_recv_order ro ,wms_recv_detail arr SET arr.arr_id = ro.arr_id,arr.recv_id=ro.id WHERE ro.recv_code = arr.recv_code and (arr.arr_id=0 or arr.recv_id=0) AND ro.tenant_id=$tenant_id AND arr.tenant_id=$tenant_id");

        // dump('确认供应商');
        // $max = 0;
        // while (true) {
        //     $ids = RecvDetail::whereRaw('arr_id=0 or recv_id=0')->where('tenant_id', $tenant_id)->where('id', '>', $max)->limit(100)->pluck('id');
        //     $res = DB::select("SELECT rd.id,ro.id as recv_id,ro.arr_id FROM wms_recv_detail rd
        //     left JOIN wms_recv_order ro ON rd.recv_code=ro.recv_code and ro.tenant_id=$tenant_id
        //     WHERE (rd.arr_id=0 or rd.recv_id=0) AND ro.id>$max and rd.tenant_id=$tenant_id LIMIT 100");
        //     if (!$ids->count()) break;

        //     if (!$res) break;
        //     $ids = array_column($res,'id');

        //     DB::statement("UPDATE wms_recv_detail rd ,(
        //         SELECT rd.id,ro.id as recv_id,ro.arr_id FROM wms_recv_detail rd
        //         left JOIN wms_recv_order ro ON rd.recv_code=ro.recv_code and ro.tenant_id=$tenant_id
        //         WHERE (rd.arr_id=0 or rd.recv_id=0) AND ro.id>$max and rd.tenant_id=$tenant_id LIMIT 100
        //         ) a 
        //         SET rd.arr_id=a.arr_id,rd.recv_id=a.recv_id
        //         WHERE rd.id=a.id");
        //     $max = max($ids);
        // }

        // DB::statement("UPDATE wms_recv_order o,wms_recv_detail d ,wms_stock_logs l SET d.sup_id=l.sup_id,d.sup_confirm=1 
        // WHERE o.id=d.recv_id AND o.arr_code = l.origin_code AND l.operation=4 AND  d.uniq_code=l.uniq_code AND d.sup_id=0 AND o.tenant_id=$tenant_id AND d.tenant_id=$tenant_id AND l.tenant_id=$tenant_id");

        // dump('更新质检和上架状态');
        // DB::statement("UPDATE wms_recv_detail ro ,wms_arrival_regist arr SET ro.is_putway = 1,ro.is_qc=1 WHERE ro.arr_id = arr.id and arr.doc_status=4 and arr.arr_status=4 AND (ro.is_putway=0 or ro.is_qc=0) and  ro.tenant_id=$tenant_id AND arr.tenant_id=$tenant_id");

        // dump('更新条形码信息');
        // DB::statement("UPDATE wms_recv_detail d ,wms_spec_and_bar sku SET d.bar_code=sku.bar_code WHERE  d.sku=sku.sku AND  d.tenant_id=$tenant_id AND sku.tenant_id=$tenant_id");
    }

    function matchIb()
    {
        $tenant_id = $this->tenant_id;
        // dump('匹配入库单');
        // $max = 0;
        // while (1) {
        //     dump($max);
        //     $details = RecvDetail::where(['ib_id' => 0])->with('recvOrder')->where('id', '>', $max)->limit(500)->get();
        //     if ($details->count() == 0) break;
        //     foreach ($details as $detail) {
        //         $max = $detail->id;
        //         $recv = $detail->recvOrder;
        //         if (!$recv) continue;
        //         // 确认到货
        //         $product_log = WmsProductStockLog::where([
        //             'node' => 1, 'third_no' => $recv->arr_code, 'batch_no' => $detail->lot_num, 'sku' => $detail->sku, 'quality_type' => $detail->quality_type, 'tenant_id' => $tenant_id,'inv_category'=>1,
        //         ])->first();
        //         if (!$product_log) continue;
        //         $ib = IbOrder::where(['ib_code' => $product_log->source_code, 'tenant_id' => $tenant_id])->first();
        //         if (!$ib) continue;
        //         $detail->update(['ib_id' => $ib->id, 'ib_confirm' => 1, 'inv_type' => $product_log->inv_type, 'buy_price' => $product_log->cost_price]);
        //     }
        // }

        // 临时查询数据表


        dump('更新需求单登记单确认信息');
        DB::statement("UPDATE wms_product_stock_logs log ,wms_ib_order ib,wms_arrival_regist arr
        SET ib.arr_id=arr.id,arr.ib_code=ib.ib_code WHERE log.node=1 AND log.origin_type=3 AND log.source_code=ib.ib_code AND log.third_no=arr.arr_code AND (ib.arr_id=0 OR arr.ib_code='') and log.inv_category=1 AND log.tenant_id=$tenant_id AND ib.tenant_id=$tenant_id AND arr.tenant_id=$tenant_id");

        dump('更新收获单状态');
        DB::statement("UPDATE wms_recv_detail as rd,wms_product_stock_logs as  log,wms_arrival_regist as arr ,wms_ib_order ib
        SET rd.ib_id=ib.id,ib_confirm=1,rd.inv_type=log.inv_type,buy_price=log.weighted_cost_price
        WHERE rd.arr_id = arr.id and arr.arr_code=log.third_no AND arr.ib_code=ib.ib_code AND rd.sku=log.sku AND rd.lot_num = log.batch_no AND rd.quality_type=log.quality_type  AND rd.quality_level = log.quality_level AND rd.tenant_id=$tenant_id AND log.tenant_id=$tenant_id AND arr.tenant_id=$tenant_id AND ib.tenant_id=$tenant_id AND rd.ib_id=0 AND log.node=1 AND log.origin_type=3");
    }

    function matchIb2()
    {
        $tenant_id = $this->tenant_id;
        $max = 0;
        dump('更新需求单登记单确认信息');
        $start = time();
        while (1) {
            dump($max);

            $logs = DB::select("SELECT log.id,log.source_code,log.third_no,log.inv_type,log.cost_price,log.weighted_cost_price,log.sku,log.batch_no,log.quality_level FROM wms_product_stock_logs as log WHERE  log.node=1 AND log.source_code>'' AND log.third_no >'' AND log.origin_type=3 AND id>$max and log.tenant_id=$tenant_id LIMIT 100");
            if (count($logs) == 0) break;

            $ibs = IbOrder::whereIn('ib_code', array_unique(array_column($logs, 'source_code')))->get()->keyBy('ib_code');
            $arrs = ArrivalRegist::whereIn('arr_code', array_unique(array_column($logs, 'third_no')))->get()->keyBy('arr_code');

            foreach ($logs as $log) {
                $ib = $ibs[$log->source_code] ?? null;
                $arr = $arrs[$log->third_no] ?? null;
                if (!$ib || !$arr) continue;
                $ib->update(['arr_id' => $arr->id]);
                $arr->update(['ib_code' => $ib->ib_code]);
            }

            $max = max(array_column($logs, 'id'));
        }
        dump('耗时:' . (time() - $start));

        $max = 0;
        dump('更新收货明细信息 - 瑕疵');
        $start = time();
        while (1) {
            dump($max);

            $logs = DB::select("SELECT log.id,log.sku,log.batch_no,log.uniq_code,log.cost_price,log.quality_level,log.quality_type,log.inv_type,ib.arr_id,ib.id as ib_id FROM wms_product_stock_logs log , wms_ib_order ib WHERE log.source_code=ib.ib_code AND  log.node=1 AND log.origin_type=3  AND ib.arr_id>0 AND log.quality_type=2 and log.inv_category=1 AND log.id>$max and log.tenant_id=$tenant_id LIMIT 100");
            if (count($logs) == 0) break;


            DB::statement("UPDATE (
                SELECT rd.id,a.cost_price,a.ib_id,a.inv_type FROM (
                           SELECT log.id,log.sku,log.batch_no,log.uniq_code,log.cost_price,log.quality_level,log.quality_type,log.inv_type,ib.arr_id,ib.id as ib_id FROM wms_product_stock_logs log , wms_ib_order ib WHERE log.source_code=ib.ib_code AND  log.node=1 AND log.origin_type=3  AND ib.arr_id>0 AND log.quality_type=2 and log.inv_category=1 AND log.id>$max and log.tenant_id=$tenant_id LIMIT 100 
                 ) a 
                 left JOIN wms_recv_detail rd ON a.uniq_code=rd.uniq_code AND a.batch_no=rd.lot_num AND a.arr_id=rd.arr_id AND rd.tenant_id=$tenant_id
                 WHERE rd.ib_id=0 
           ) as b,wms_recv_detail rd 
           SET rd.ib_id=b.ib_id,rd.inv_type=b.inv_type,rd.buy_price=b.cost_price
           WHERE b.id=rd.id AND rd.tenant_id=$tenant_id");

            $max = max(array_column($logs, 'id'));
        }
        dump('耗时:' . (time() - $start));

        $max = 0;
        dump('更新收货明细信息 - 正品');
        $start = time();
        while (1) {

            dump($max);
            $logs = DB::select("SELECT log.id,log.sku,log.batch_no,log.uniq_code,log.cost_price,log.quality_level,log.quality_type,log.inv_type,ib.arr_id,ib.id as ib_id FROM wms_product_stock_logs log , wms_ib_order ib WHERE log.source_code=ib.ib_code AND  log.node=1 AND log.origin_type=3  AND ib.arr_id>0 AND log.quality_type=1  and log.inv_category=1 AND log.id>$max and log.tenant_id=$tenant_id LIMIT 100");
            if (count($logs) == 0) break;

            DB::statement("UPDATE (
                SELECT rd.id,a.cost_price,a.ib_id,a.inv_type FROM (
                           SELECT log.id,log.sku,log.batch_no,log.uniq_code,log.cost_price,log.quality_level,log.quality_type,log.inv_type,ib.arr_id,ib.id as ib_id FROM wms_product_stock_logs log , wms_ib_order ib WHERE log.source_code=ib.ib_code AND  log.node=1 AND log.origin_type=3  AND ib.arr_id>0 AND log.quality_type=1 and log.inv_category=1 AND log.id>$max and log.tenant_id=$tenant_id LIMIT 100 
                 ) a 
                 left JOIN wms_recv_detail rd ON a.sku=rd.sku AND a.batch_no=rd.lot_num AND a.arr_id=rd.arr_id AND rd.tenant_id=$tenant_id
                 WHERE rd.ib_id=0 
           ) as b,wms_recv_detail rd 
           SET rd.ib_id=b.ib_id,rd.inv_type=b.inv_type,rd.buy_price=b.cost_price
           WHERE b.id=rd.id AND rd.tenant_id=$tenant_id");
            $max = max(array_column($logs, 'id'));
        }
    }

    function matchIb3()
    {
        dump("确认入库单.....");
        $tenant_id = $this->tenant_id;
        // $max = ArrivalRegist::where(['tenant_id' => $this->tenant_id, 'doc_status' => 4])->orderBy('id', 'desc')->value('id');
        $max = WmsStockLog::where(['tenant_id' => $this->tenant_id, 'operation' => 4])->orderBy('id', 'desc')->value('id');

        $start = time();
        $begin = 0;
        while (1) {
            $end = $begin + 1000;
            if ($begin > $max) break;
            dump($begin);
            $res = DB::select("SELECT arr.id as arr_id,ib.arr_id as ib_arr_id,arr.arr_code,arr.ib_code as arr_ib_code,ib.id as ib_id,ib.ib_code FROM (
                SELECT DISTINCT erp_no,origin_code FROM wms_stock_logs WHERE operation=4  and id>$begin AND id<=$end and tenant_id=$tenant_id 
                ) a 
                left JOIN wms_arrival_regist arr ON a.origin_code=arr.arr_code and arr.tenant_id=$tenant_id
                left JOIN wms_ib_order ib ON a.erp_no=ib.ib_code and ib.tenant_id=$tenant_id");
            $sql = '';
            foreach ($res as $item) {
                $sql .= sprintf("UPDATE wms_arrival_regist SET ib_code='%s' WHERE ib_code='' AND id=%d;UPDATE wms_ib_order set arr_id=%d WHERE arr_id=0 AND id=%d;", $item->ib_code, $item->arr_id, $item->arr_id, $item->ib_id);
            }
            if ($sql) DB::getPdo()->exec($sql);
            $begin = $end;
        }
        dump('耗时 ' . (time() - $start));

        $max = 0;
        dump('更新收货明细信息 - 瑕疵');
        $start = time();
        while (1) {
            dump($max);

            $logs = DB::select("SELECT log.id,log.sku,log.batch_no,log.uniq_code,log.cost_price,log.quality_level,log.quality_type,log.inv_type,ib.arr_id,ib.id as ib_id FROM wms_product_stock_logs log , wms_ib_order ib WHERE log.source_code=ib.ib_code AND  log.node=1 AND log.origin_type=3  AND ib.arr_id>0 AND log.quality_type=2 and log.inv_category=1 AND log.id>$max and log.tenant_id=$tenant_id LIMIT 100");
            if (count($logs) == 0) break;


            DB::statement("UPDATE (
                SELECT rd.id,a.cost_price,a.ib_id,a.inv_type FROM (
                           SELECT log.id,log.sku,log.batch_no,log.uniq_code,log.cost_price,log.quality_level,log.quality_type,log.inv_type,ib.arr_id,ib.id as ib_id FROM wms_product_stock_logs log , wms_ib_order ib WHERE log.source_code=ib.ib_code AND  log.node=1 AND log.origin_type=3  AND ib.arr_id>0 AND log.quality_type=2 and log.inv_category=1 AND log.id>$max and log.tenant_id=$tenant_id LIMIT 100 
                 ) a 
                 left JOIN wms_recv_detail rd ON a.uniq_code=rd.uniq_code AND a.batch_no=rd.lot_num AND a.arr_id=rd.arr_id AND rd.tenant_id=$tenant_id
                 WHERE rd.ib_id=0 
           ) as b,wms_recv_detail rd 
           SET rd.ib_id=b.ib_id,rd.inv_type=b.inv_type,rd.buy_price=b.cost_price
           WHERE b.id=rd.id AND rd.tenant_id=$tenant_id");

            $max = max(array_column($logs, 'id'));
        }
        dump('耗时:' . (time() - $start));

        $max = 0;
        dump('更新收货明细信息 - 正品');
        $start = time();
        while (1) {

            dump($max);
            $logs = DB::select("SELECT log.id,log.sku,log.batch_no,log.uniq_code,log.cost_price,log.quality_level,log.quality_type,log.inv_type,ib.arr_id,ib.id as ib_id FROM wms_product_stock_logs log , wms_ib_order ib WHERE log.source_code=ib.ib_code AND  log.node=1 AND log.origin_type=3  AND ib.arr_id>0 AND log.quality_type=1  and log.inv_category=1 AND log.id>$max and log.tenant_id=$tenant_id LIMIT 100");
            if (count($logs) == 0) break;

            DB::statement("UPDATE (
                SELECT rd.id,a.cost_price,a.ib_id,a.inv_type FROM (
                           SELECT log.id,log.sku,log.batch_no,log.uniq_code,log.cost_price,log.quality_level,log.quality_type,log.inv_type,ib.arr_id,ib.id as ib_id FROM wms_product_stock_logs log , wms_ib_order ib WHERE log.source_code=ib.ib_code AND  log.node=1 AND log.origin_type=3  AND ib.arr_id>0 AND log.quality_type=1 and log.inv_category=1 AND log.id>$max and log.tenant_id=$tenant_id LIMIT 100 
                 ) a 
                 left JOIN wms_recv_detail rd ON a.sku=rd.sku AND a.batch_no=rd.lot_num AND a.arr_id=rd.arr_id AND rd.tenant_id=$tenant_id
                 WHERE rd.ib_id=0 
           ) as b,wms_recv_detail rd 
           SET rd.ib_id=b.ib_id,rd.inv_type=b.inv_type,rd.buy_price=b.cost_price
           WHERE b.id=rd.id AND rd.tenant_id=$tenant_id");
            $max = max(array_column($logs, 'id'));
        }
    }

    function matchIb4()
    {
        dump("确认入库单.....");
        $tenant_id = $this->tenant_id;
        // $max = ArrivalRegist::where(['tenant_id' => $this->tenant_id, 'doc_status' => 4])->orderBy('id', 'desc')->value('id');
        $max = WmsStockLog::where(['tenant_id' => $this->tenant_id, 'operation' => 4])->orderBy('id', 'desc')->value('id');

        $start = time();
        $begin = 0;
        while (1) {
            $end = $begin + 1000;
            if ($begin > $max) break;
            dump($begin);
            $res = DB::select("SELECT arr.id as arr_id,ib.arr_id as ib_arr_id,arr.arr_code,arr.ib_code as arr_ib_code,ib.id as ib_id,ib.ib_code FROM (
                SELECT DISTINCT erp_no,origin_code FROM wms_stock_logs WHERE operation=4  and id>$begin AND id<=$end and tenant_id=$tenant_id 
                ) a 
                left JOIN wms_arrival_regist arr ON a.origin_code=arr.arr_code and arr.tenant_id=$tenant_id
                left JOIN wms_ib_order ib ON a.erp_no=ib.ib_code and ib.tenant_id=$tenant_id");
            $sql = '';
            foreach ($res as $item) {
                $sql .= sprintf("UPDATE wms_arrival_regist SET ib_code='%s' WHERE ib_code='' AND id=%d;UPDATE wms_ib_order set arr_id=%d WHERE arr_id=0 AND id=%d;", $item->ib_code, $item->arr_id, $item->arr_id, $item->ib_id);
            }
            if ($sql) DB::getPdo()->exec($sql);
            $begin = $end;
        }
        dump('耗时 ' . (time() - $start));

        $max = WmsProductStockLog::where(['node' => 1, 'origin_type' => 3, 'quality_type' => 2])->max('id');
        dump('更新收货明细信息 - 瑕疵');
        $begin = 0;
        while (1) {
            if ($begin > $max) break;
            dump($begin);
            $end = $begin + 1000;

            DB::statement("UPDATE wms_product_stock_logs log , wms_ib_order ib,wms_recv_detail rd
            SET rd.ib_id=ib.id,rd.ib_confirm=1,rd.inv_type=log.inv_type,rd.buy_price=log.cost_price
            WHERE log.source_code=ib.ib_code AND log.uniq_code=rd.uniq_code AND ib.arr_id=rd.arr_id  AND  log.node=1  AND log.origin_type=3  AND ib.arr_id>0 AND log.quality_type=2 and log.inv_category=1 AND log.id>$begin AND log.id<=$end");
            $begin = $end;
        }

        $max = WmsProductStockLog::where(['node' => 1, 'origin_type' => 3, 'quality_type' => 1])->max('id');
        dump('更新收货明细信息 - 正品');
        $begin = 0;
        while (1) {
            if ($begin > $max) break;
            dump($begin);
            $end = $begin + 1000;

            DB::statement("UPDATE wms_product_stock_logs log , wms_ib_order ib,wms_recv_detail rd
            SET rd.ib_id=ib.id,rd.ib_confirm=1,rd.inv_type=log.inv_type,rd.buy_price=log.cost_price
            WHERE log.source_code=ib.ib_code AND  log.sku=rd.sku AND log.batch_no=rd.lot_num AND ib.arr_id=rd.arr_id  AND  log.node=1  AND log.origin_type=3  AND ib.arr_id>0 AND log.quality_type=1 and log.inv_category=1 AND log.id>$begin AND log.id<=$end");
            $begin = $end;
        }
    }

    function buyPirce()
    {
        $max = WmsProductStockLog::where(['node' => 1, 'origin_type' => 3, 'quality_type' => 2])->max('id');

        dump('更新成本价-瑕疵品');
        $begin = 0;
        while (1) {
            if ($begin > $max) break;
            dump($begin);
            $end = $begin + 1000;

            DB::statement("UPDATE wms_inv_goods_detail inv ,wms_product_stock_logs log 
            SET inv.buy_price=log.cost_price,inv.inv_type=log.inv_type
            WHERE log.batch_no=inv.lot_num AND inv.uniq_code=log.uniq_code AND log.quality_type=2 AND inv.sup_id=log.sup_id AND log.node=1 AND log.origin_type IN (2,3) AND log.id>$begin AND log.id<=$end");
            $begin = $end;
        }

        $max = WmsProductStockLog::where(['node' => 1, 'origin_type' => 3, 'quality_type' => 1])->max('id');
        dump('更新成本价-正品');
        $begin = 0;
        while (1) {
            if ($begin > $max) break;
            dump($begin);
            $end = $begin + 1000;

            DB::statement("UPDATE wms_inv_goods_detail inv ,wms_product_stock_logs log 
            SET inv.buy_price=log.cost_price,inv.inv_type=log.inv_type
            WHERE log.batch_no=inv.lot_num AND inv.sku=log.sku AND log.quality_type=1 AND inv.sup_id=log.sup_id AND log.node=1 AND log.origin_type IN (2,3) AND log.id>$begin AND log.id<=$end");
            $begin = $end;
        }
    }


    function company()
    {
        $tenant_id = $this->tenant_id;

        dump('物流公司......');
        $this->_init(49, function ($find) use ($tenant_id) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                $arr = [];
                foreach ($data as $item) {
                    $arr[] = [
                        'company_code' => $item[0],
                        'company_name' => $item[1],
                        'short_name' => $item[2],
                        'status' => $item[4] == '启用' ? 1 : 0,
                        'remark' => $item[5],
                        'tenant_id' => $tenant_id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ];
                }
                if ($arr) DB::table('wms_logistics_company')->insert($arr);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
            return;
        });
    }

    function companyProduct()
    {
        $tenant_id = $this->tenant_id;
        $company = WmsLogisticsCompany::where('tenant_id', $tenant_id)->select('company_code', 'company_name')->get()->keyBy('company_name');
        dump('物流产品......');
        $this->_init(50, function ($find) use ($tenant_id, $company) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                $arr = [];
                $payment = ['月付' => 1, '现结' => 2, '到付' => 3, '其他' => 4,];
                foreach ($data as $item) {
                    $arr[] = [
                        'product_code' => $item[0],
                        'product_name' => $item[1],
                        'company_code' => $company[$item[3]]['company_code'],
                        'pickup_method' => $item[3] == '自提' ? 1 : 3,
                        'payment' => $payment[$item[8]],
                        'remark' => $item[10],
                        'tenant_id' => $tenant_id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'status' => 1,
                    ];
                }
                if ($arr) DB::table('wms_logistics_products')->insert($arr);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
            return;
        });
    }

    // 销售发货明细
    function saleDetail()
    {
        $tenant_id = $this->tenant_id;
        dump('销售发货明细......');
        $shop = WmsShop::where(['tenant_id' => $tenant_id])->select(['name', 'code'])->get()->keyBy('name')->toArray();
        $brand = ProductBrands::where(['tenant_id' => $tenant_id])->select(['name', 'code'])->get()->keyBy('name')->toArray();
        $sup = Supplier::where(['tenant_id' => $tenant_id])->select(['name', 'id'])->get()->keyBy('name')->toArray();
        $category = ProductCategory::where(['tenant_id' => $tenant_id])->select(['name', 'code'])->get()->keyBy('name')->toArray();
        $this->_init(52, function ($find) use ($tenant_id, $shop, $brand, $sup, $category) {
            $data = $find->data;
            try {
                DB::beginTransaction();
                $arr = [];
                $inventory_type = ['自营' => 1, '寄卖' => 2];
                $product_type = ['实物产品' => 0, '虚拟' => 1, '赠品' => 2, '附属品' => 3, '其他' => 4];
                foreach ($data as $item) {
                    $arr[] = [
                        'origin_code' => $item[0],
                        'third_no' => $item[1],
                        'shop_name' => $item[2],
                        'shop_code' => $shop[$item[2]]['code'] ?? '',
                        'order_at' => $item[3],
                        'payment_at' => $item[4],
                        'shipped_at' => $item[5],
                        'name' => $item[12],
                        'category_name' => $item[8],
                        'category_code' => $category[$item[8]]['code'] ?? '',
                        'brand_name' => $item[9],
                        'brand_code' => $brand[$item[9]]['code'] ?? '',
                        'sku' => $item[10],
                        'product_sn' => $item[11],
                        'spec_one' => $item[13],
                        'num' => $item[14],
                        'retails_price' => $item[15],
                        'price' => $item[16],
                        'amount' => $item[17],
                        'payment_amount' => $item[18],
                        'discount_amount' => $item[19],
                        'cost_amount' => $item[22],
                        'gross_profit' => $item[23],
                        'gross_profit_rate' => $item[24],
                        'freight' => $item[25],
                        'product_type' => $product_type[$item[26]],
                        'sup_name' => $item[27],
                        'sup_id' => $sup[$item[27]]['id'] ?? 0,
                        'inventory_type' => $inventory_type[$item[28]],
                        'quality_type' => $this->quality_type($item[29]),
                        'quality_level' => $this->quality_level($item[30], $item[29]),
                        'batch_no' => $item[31],
                        'uniq_code' => $item[32],
                        'company_name' => $item[37],
                        'deliver_no' => $item[38],
                        'remark' => $item[39],
                        'tenant_id' => $tenant_id,
                        'created_at' => $item[5],
                        'updated_at' => $item[5],
                    ];
                    // 更新 shop_code warehouse_code warehouse_name category_code sup_id
                    if (count($arr) > 500) {
                        DB::table('wms_order_deliver_statements')->insert($arr);
                        $arr = [];
                    }
                }
                if ($arr) DB::table('wms_order_deliver_statements')->insert($arr);
                $find->delete();
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Robot::sendException($e->__toString());
                throw $e;
            }
            return;
        });
    }

    function _supArr()
    {
        $arr = ["G06160900_藤永 優-原宿買取", "G06160816_石嵜　清久-原宿買取", "G2301069484248600_松窪　駿-原宿買取", "G06161310_大久保 裕希-原宿買取", "G2303262662789100_HA VAN THUYEN-原宿買取", "G06161152_伊東　貴史-原宿買取", "G06160716_岡野　薫-原宿買取", "G06160164_鹿又　南都生-原宿買取", "G06161041_小野濑 勝-原宿買取", "G2303166088964300_LE MINH KIEN-原宿買取", "G06160877_中林　亞樹臣-原宿買取", "G2212133044982500_花岡　駿-原宿買取", "G2209188334973000_藤城　優典-原宿買取", "G06160775_深尾 文博-原宿買取", "G06160904_安原　悠驱-原宿買取", "G2210211986010700_揚妻　篤史-原宿買取", "G2303120063753200_TRAN DUC MINH-原宿買取", "G2303041896387100_太田　雅人-原宿買取", "G2210090218227100_弟子丸　拓海-原宿買取", "G06161185_長井　　拓真-原宿買取", "G06161405_園木　勇二-原宿買取", "G2304091635747700_NGUYEN THANH CONG-原宿買取", "G06161253_大北 英典-原宿買取", "G06161235_尾形 勇紀-原宿買取", "G06160302_金丸　暢人-原宿買取", "G06160828_横川　俊樹-原宿買取", "G06160841_角龍　俊介-原宿買取", "G2303077995537400_石田　健太郎-原宿買取", "G2207271061532700_星　優輝人-原宿買取", "G2304215509387100_HOANG THI HUONG GIANG-原宿買取", "G06160745_小杉　泰雅-原宿買取", "G06161019_前川 健太-原宿買取", "G06160966_荻野 純一-原宿買取", "G2208172846606400_松浦　力也-原宿買取", "G2211203486190000_四郎丸　正規-原宿買取", "G06160633_杉澤　涼大-原宿買取", "G06160212_相宮-原宿買取", "G2304296461231800_若原　裕之-原宿買取", "G2208235177878400_谷岡　真人-原宿買取", "G06160980_野笹　翔-原宿買取", "G06160363_江原 久人-原宿買取", "G06160188_座間　恭平-原宿買取", "G2303219441960800_NGUYEN VAN LONG-原宿買取", "G06160058_近藤　巧実-原宿買取", "G06160544_長谷川　大生-原宿買取", "G06161082_清水 大輝-原宿買取", "G06160613_昇平　森本-原宿買取", "G06160939_藤原 隆太-原宿買取", "G2209240216938400_吉田　明弘-原宿買取", "G06160219_富永　裕樹-原宿買取", "G06160202_友木　達也-原宿買取", "G06161390_黑川 阳平-原宿買取", "G2209214819342400_今村　真幸-原宿買取", "G2302102346208800_YKK（CM）-原宿CM", "G2302182186829600_上河　和之-原宿買取", "G06160859_若山 昂晖-原宿買取", "G06160710_中岛 一浩-原宿買取", "G2302170679206500_朱 双煒-原宿買取", "G2209127184217700_LE VAN GIANG-原宿買取", "G06160351_市川　暢一-原宿買取", "G06160268_森澤　勇太-原宿買取", "G06160902_小倉 一辉-原宿買取", "G2210028738513700_PAING KHANT KYAW-原宿買取", "G06161324_岡本　祐介-原宿買取", "G2303041952432800_NGUYEN VAN TIEN-原宿買取", "G06160258_樋岡　聡-原宿買取", "G2209206086522300_大本　翼-原宿買取", "G06161165_鈴木　海斗-原宿買取", "G06160286_前田 晋一-原宿買取", "G2209056147328800_小林　伸矢-原宿買取", "G06161211_山本　慎太郎-原宿買取", "G2209274729366600_NGUYEN DUY LONG-原宿買取", "G2208269516913900_水井　裕之-原宿買取", "G06160309_今成 康輔-原宿買取", "G06160274_鎌田　英祐-原宿買取", "G2303245540120400_藤井　裕雅-原宿買取", "G2211036151799200_LE THI THUY PHUONG-原宿買取", "G2303024322772300_中村　則昭-原宿買取", "G2211203902724800_岩船　隼人-原宿買取", "G06160321_今井 礼央-原宿買取", "G06160279_村上-原宿買取", "G2208227099593300_川井　陸-原宿買取", "G06160722_山本 暁-原宿買取", "G06160936_藪田　洸平-原宿買取", "G2303103975424800_辻村　拓巳-原宿買取", "G06160560_梅木 骏太郎-原宿買取", "G06160235_島谷　竜俊-原宿買取", "G2208235129074400_荒木　慎一-原宿買取", "G06160461_東恩納　優一-原宿買取", "G2303112603264400_TRAN VAN DUNG-原宿買取", "G2302059223118500_WU JILAN-原宿買取", "G06161366_岡田　剛正-原宿買取", "G2208172620774600_刘冬-原宿買取", "G06161216_宫城-原宿買取", "G2209170882425000_三村　峻央-原宿買取", "G06161208_高野 祐輔-原宿買取", "G2208209581425600_竹本　龍之介-原宿買取", "G06160967_小倉 邦彦-原宿買取", "G06161306_河石 結女-原宿買取", "G06161260_赤坂 賢英-原宿買取", "G2302243347424500_立川　慧-原宿買取", "G06160029_原田 勇樹-原宿買取", "G06161443_田渕　重貴-原宿買取", "G06160239_田所　慶也-原宿買取", "G06161222_平山 裕太-原宿買取", "G2209100221322200_籏山　裕太-原宿買取", "G06161236_浜辺　かなう-原宿買取", "G06161149_山中　祐太郎-原宿買取", "G2209064251538700_関　隼玄-原宿買取", "G06160462_大竹 　亮平-原宿買取", "G2212176199881800_NGUYEN TIEN DUNG-原宿買取", "G06160099_吉江　良翔-原宿買取", "G06161101_前村　和希-原宿買取", "G2302251525956900_宮澤　伽苑-原宿買取", "G2210019948403300_中里　優風-原宿買取", "G06161417_梅野 紘輔-原宿買取", "G2303148334707300_VU TRUNG NAN-原宿買取", "G06160886_永井 竜星-原宿買取", "G06161097_渡邊　嗣也-原宿買取", "G06160021_田中 利季-原宿買取", "G06160359_森　太作-原宿買取", "G06161218_铃木  海斗-原宿買取", "G06160007_清水　勇貴-原宿買取", "G06160433_緒方　祐介-原宿買取", "G06161265_田代 秀明-原宿買取", "G2208190498834600_岩城　匠-原宿買取", "G06160394_宇都宮　昌和-原宿買取", "G06161472_增田 良哉-原宿買取", "G2210028848097200_長谷川　柾-原宿買取", "G2208227001623000_呉屋　健太-原宿買取", "G06160557_森山　敬人-原宿買取", "G06160717_贵美岛-原宿買取", "G2209039560041600_川津　晃平-原宿買取", "G06160757_長岡　達志-原宿買取", "G06160291_石川　諒-原宿買取", "G2209222329064000_有路　勝志-原宿買取", "G06161379_正木 慎吾-原宿買取", "G06160422_渡邉　康弘-原宿買取", "G2208226708787200_尾崎　太洋-原宿買取", "G2207279001962500_山本　健太-原宿買取", "G2302252134267000_久住麻里子-原宿買取", "G2302059138143000_NGUYEN VAN NGHIEP-原宿買取", "G2210010559529100_小倉　和政-原宿買取", "G06161483_齊藤 晃太郎-原宿買取", "G2208269720448100_澤村　建造-原宿買取", "G06160708_上間　文贵-原宿買取", "G2208225862535700_長島　旭-原宿買取", "G2209257700117700_DINH DUY HUYNH-原宿買取", "G06160813_今野 優宇-原宿買取", "G06160684_都甲　誠司-原宿買取", "G06161134_大山　修-原宿買取", "G2209178685425700_NEW BALANCE-原宿買取", "G2211194332710700_PHAM TUAN VU-原宿買取", "G2209213777761100_野崎　健太-原宿買取", "G06161484_栗原 あゆみ-原宿買取", "G2210213248238000_林　虎之介-原宿買取", "G06161009_菅野　英-原宿買取", "G2209170239220700_儘田　泰幸-原宿買取", "G06161156_八木　拓郎-原宿買取", "G2208226866777300_橋本　渓汰-原宿買取", "G06160679_露木　秀仁-原宿買取", "G06161364_鈴木　将太-原宿買取", "G2209214853007000_横山　佳佑-原宿買取", "G06160882_高桥 和也-原宿買取", "G2301087084037200_岡野　裕一-原宿買取", "G2301236090372400_林原　弘典-原宿買取", "G06160747_岡村　勇-原宿買取", "G2207254585230000_新山　基一-原宿買取", "G06160387_石川 憲一-原宿買取", "G06160469_丸谷　真-原宿買取", "G2210036714496200_市川　侑-原宿買取", "G06161143_濱本　将吾-原宿買取", "G06160781_伊原 魁一-原宿買取", "G2302250879467200_NGUYEN DANG PHUONG-原宿買取", "G2305084498771800_三尾　光平-原宿買取", "G06160728_小飯塚光生-原宿買取", "G2208180864912600_上遠　野雄貴-原宿買取", "G06161360_知久　生-原宿買取", "G2209189253998900_达达吖-原宿買取", "G06161448_大河内　昭哉-原宿買取", "G2211150327537800_小松　征弘-原宿買取", "G2208056857728400_佐々木　豊-原宿買取", "G06160733_山下雅治-原宿買取", "G2212036017075100_大高　伸一-原宿買取", "G06160626_櫻井　宗平-原宿買取", "G06160655_相田 温史-原宿買取", "G06161332_菊地原　竜也-原宿買取", "G06161342_樋口　俊介-原宿買取", "G2209205454912000_NGUYEN THI THU LINH-原宿買取", "G06161372_山下　遼太-原宿買取", "G2208085641432700_西川 大智-原宿買取", "G2302251016288400_NGUYEN THANH TRUNG-原宿買取", "G2210011721339400_羽田野　将吾-原宿買取", "G06160661_菊地 海渡-原宿買取", "G2301316375966700_金子　将也-原宿買取", "G2211281837696200_NGUYEN TRUNG KIEN-原宿買取", "G06160349_箱石　剛直-原宿買取", "G2212185355601000_福永　亮-原宿買取", "G06161363_石井　隆史-原宿買取", "G2301201203835700_鈴木　充也-原宿買取", "G06160020_吉田 武史-原宿買取", "G2303183972806700_江　伟东-原宿買取", "G2208170629628000_前畑　公一-原宿買取", "G2304251025028900_PHAM VAN QUOC-原宿買取", "G2208172456385600_杉山 由佳-原宿買取", "G06160977_藤原 聖己-原宿買取", "G2208172367720200_麻生 豪-原宿買取", "G06160423_畑中　優輝-原宿買取", "G06160221_大橋　将-原宿買取", "G06160665_八田直之-原宿買取", "G2209274724158900_TRAN THI THUY TRANG-原宿買取", "G06160144_高坂　祐翔-原宿買取", "G06160417_山内　雅貴-原宿買取", "G2208172950718400_PHAM VI ANH-原宿買取", "G2301077911289900_馬場　政德-原宿買取", "G06160974_座嘉比 若都-原宿買取", "G2207296506880600_森崎-原宿買取", "G06160048_石川　和彦-原宿買取", "G06161188_山田　　和誠-原宿買取", "G2211265415605700_村田　祐次郎-原宿買取", "G2211291508285100_張　思奥-原宿買取", "G06160496_鳴海　唯人-原宿買取", "G06160236_住吉　舞依-原宿買取", "G06160620_山瀬　俊也-原宿買取", "G2301104421988500_VU MINH TU-原宿買取", "G2209021092086700_NGUYEN THI THU TRANG-原宿買取", "G06160257_今西　崇之-原宿買取", "G06161000_松本 直也-原宿買取", "G2211116074775700_岩井 寿樹-原宿買取", "G06160762_村松　儁-原宿買取", "G06160919_幸前 博行-原宿買取", "G06160581_三上 数昭-原宿買取", "G2207279358773900_渡邊　恵介-原宿買取", "G06160229_関口　晴天-原宿買取", "G2208242852017600_野口 鷹哉-原宿買取", "G06160493_浅木　星哉-原宿買取", "G06160876_喜屋武　政志-原宿買取", "G06160452_大嶋　敏弘-原宿買取", "G2304199722571800_萩村　勇人-原宿買取", "G06160608_田中 聖人-原宿買取", "G06160603_藤原　一秀-原宿買取", "G2303217124498600_NGUYEN HUY CONG-原宿買取", "G06160699_王侃-原宿買取", "G06160796_義原　涼平-原宿買取", "G06161120_菅谷　純-原宿買取", "G2209021051698600_中村　翔輝-原宿買取", "G06160692_水木 碧南-原宿買取", "G06160044_新居-原宿買取", "G2208242271094100_藤本　良平-原宿買取", "G06160913_西田 涼哉-原宿買取", "G06160691_坂田 翔-原宿買取", "G06160776_尾川　裕太-原宿買取", "G2301078305906000_永田　尚樹-原宿買取", "G06161303_池田　篤希-原宿買取", "G06160283_久保　裕太-原宿買取", "G06160944_片山 枫人-原宿買取", "G06160104_園部　新一-原宿買取", "G06160659_秋山 龍や-原宿買取", "G06161048_上島　晴正-原宿買取", "G2210028549347000_DONG THI DIEP ANH-原宿買取", "G06160242_黒川　浩一-原宿買取", "G06160278_板橋　京祐-原宿買取", "G2302182090660700_VU THI MIHN HA-原宿買取", "G2301130258605200_長谷川　勇-原宿買取", "G06160688_三吉野　晃一郎-原宿買取", "G06160168_酒井　一貴-原宿買取", "G06160926_兼松　徳良-原宿買取", "G06161394_山崎 祐人-原宿買取", "G2208226803834100_村田　和紀-原宿買取", "G06160460_桑井 天至-原宿買取", "G06161416_佐佐木 周太-原宿買取", "G06160984_中鉢　凌-原宿買取", "G2303200553747900_直井　嘉槻-原宿買取", "G06160250_小浜　徹-原宿買取", "G2301243758654100_田辺　和希-原宿買取", "G2209257607232400_齊 憲太郎-原宿買取", "G2304031532162100_林　靖之-原宿買取", "G2209222896035900_NGUYEN THI THU HOAI-原宿買取", "G2208172801930300_野木 康太-原宿買取", "G06161353_村田　裕弥-原宿買取", "G06160890_横溝　知久-原宿買取", "G06160725_伊藤　琉晟-原宿買取", "G2207262143652200_NGUYEN SI PHU-原宿買取", "G2208207107298300_三浦　勇輔-原宿買取", "G2303227711931700_丸井　健吾-原宿買取", "G06160996_荒井 佑太-原宿買取", "G06161321_杉山　幸正", "G2209268281889500_横山　雅伸-原宿買取", "G2302251322529800_NGUYEN CHI CUONG-原宿買取", "G06160367_香川 伸一-原宿買取", "G2209100092943500_大見　彩人-原宿買取", "G06160534_高桥 泰辉-原宿買取", "G06160284_小崎　敏一-原宿買取", "G2208216810838200_BUI VAN HUU-原宿買取", "G06160085_山下 大輔-原宿買取", "G06161187_堀-原宿買取", "G06160857_仲西 直人-原宿買取", "G2208277144221700_佐伯　拓哉-原宿買取", "G2303033806936700_NGUYEN THI THANH HUONG-原宿買取", "G2303217263367600_谷　典晃-原宿買取", "G2301079102596500_WANG JUNXING-原宿買取", "G06160987_嶺　尚弥-原宿買取", "G06161278_丸山　知弘-原宿買取", "G06161177_高田恭平-原宿買取", "G06160994_吉藤 佑生-原宿買取", "G2208083535624900_NGUYEN QUOC TIEN-原宿買取", "G2208226305357700_NGUYEN　TRUNG　NGUYEN-原宿買取", "G06160049_大田　一貴-原宿買取", "G2209285296232700_ネット-原宿買取", "G06160880_大山 知洋-原宿買取", "G06161249_大石 龟也-原宿買取", "G2209021321046800_高木　将吾-原宿買取", "G06161161_笠谷　樹石-原宿買取", "G06161408_津田 英辉-原宿買取", "G2303199853897900_NGUYEN THI HOA-原宿買取", "G06160621_望月　春那-原宿買取", "G2303253502934700_LY　THI　HANG-原宿買取", "G2207262150353300_遠藤　陽平-原宿買取", "G2211291555085700_法崎　剛-原宿買取", "G2303103962479600_鎌田　智行-原宿買取", "G06160129_鈴木　大輔-原宿買取", "G2303016081369200_YU PINFU-原宿買取", "G06161325_福本　恭大-原宿買取", "G06161106_寺門　岳雄-原宿買取", "G06160527_田中　聡-原宿買取", "G06161207_宫田  贵浩-原宿買取", "G06160901_渡部 雄太-原宿買取", "G06160514_鈴木　亨-原宿買取", "G2301262658807000_JIANG YUCHAO-原宿買取", "G06160645_松本-原宿買取", "G2303148345015000_児島　広樹-原宿買取", "G06160594_立野 貴寬-原宿買取", "G06160773_平岡　陽祐-原宿買取", "G06160117_高石 江利子-原宿買取", "G2210117783890400_LUU MANH DUY-原宿買取", "G06161209_田卷 真宙-原宿買取", "G06160211_町田　和俊-原宿買取", "G06160384_菊地　良太-原宿買取", "G06161111_福田　佑也-原宿買取", "G2207252225367900_水谷　泰良-原宿買取", "G2209231849983000_坂東　凌平-原宿買取", "G06161086_木場　瞬介-原宿買取", "G2303243781094900_NGUYEN HOANG VIET-原宿買取", "G06161247_古山 健司-原宿買取", "G2208110764913100_藤原　海斗-原宿買取", "G06161411_杉本 直道-原宿買取", "G06161421_甲斐雄一朗-原宿買取", "G06160790_保原　正敬-原宿買取", "G2208287774709300_樋口　綾太-原宿買取", "G06160445_小川 凉史-原宿買取", "G06160541_片村 圭佑-原宿買取", "G2208259653889400_藤田　和男-原宿買取", "G06160450_小林 光孝-原宿買取", "G06160005_菊地 真仁-原宿買取", "G06160871_福岡 一挥-原宿買取", "G06161400_森 光洋-原宿買取", "G2208083012853900_松　永東-原宿買取", "G06160837_池田 省吾-原宿買取", "G2211131979291300_田渕　重贵-原宿買取", "G2209057004982200_山賀　健太郎-原宿買取", "G06160753_吉原　涼平-原宿買取", "G2212035886111201_TA DUY CHUAN-原宿買取", "G06160368_清水 孝晃-原宿買取", "G2304199123860000_NGUYEN HONG ANH-原宿買取", "G2208225921902700_中津川　誠人-原宿買取", "G06161219_铃木  康介-原宿買取", "G06160995_廣尾 達成-原宿買取", "G06160112_朴宏宇-原宿買取", "G06160718_宮越　陽大-原宿買取", "G06160858_大坪 真士-原宿買取", "G06161449_水町　清志郎-原宿買取", "G2303280125627500_小林　陽-原宿買取", "中田　勇樹_中田　勇樹-原宿買取", "G2208171326200800_畔上　謙生-原宿買取", "G06161330_川本　秀作-原宿買取", "G06160715_田中 貴博-原宿買取", "G2301113344904000_伊沢　真心-原宿買取", "G06160281_大村 貴志-原宿買取", "G06161245_山下 佳 祐-原宿買取", "G2303120030621800_NGUYEN VAN LINH-原宿買取", "G2207252259670800_NGO THANH LANH-原宿買取", "G06160537_中倉　健太-原宿買取", "G06161153_上原　一貴-原宿買取", "G2208171710715000_木本　翔太-原宿買取", "G2208111205382600_井坂　高大-原宿買取", "G06161433_鈴木　悠平-原宿買取", "G06160180_須藤　昭人-原宿買取", "G06160518_成田　嵩哉-原宿買取", "G2208215390064000_MYO　KHANT　KO-原宿買取", "G06160934_吾孫子　豊-原宿買取", "G2208171461253700_谷ッ田　昌寛-原宿買取", "G06160925_奥田 元樹-原宿買取", "G2302023427843500_宮本　晃好-原宿買取", "G06160301_本間　邦明-原宿買取", "G2303173532368200_蒲谷　卓矢-原宿買取", "G2212035354364000_LE DUC MANH-原宿買取", "G06160111_佐山　経-原宿買取", "G2212291750520800_LI  YANG-原宿買取", "G06161262_岡本　晋作-原宿買取", "G2211282955292000_吉澤　幸乃-原宿買取", "G06161413_前田 涼太-原宿買取", "G2212089800035100_NGUYEN THI NGOC BICH-原宿買取", "G06161104_尾上　歓恵-原宿買取", "G2208209170775100_東　寛-原宿買取", "G06160426_川添　孝大-原宿買取", "G06161072_福田 勇樹-原宿買取", "G2212019202394200_MOHAMAD ZULHILMI BIN MOHA-原宿買取", "G2211088687757500_LUYEN HOAI MY-原宿買取", "G2209100346554700_前田　智贵-原宿買取", "G06161224_田中　申-原宿買取", "G06160821_武田　祐嗣-原宿買取", "G2209267784105200_NGUYEN TUAN KIET-原宿買取", "G2212273821695200_松野　亮-原宿買取", "G06160233_斉藤　滋-原宿買取", "G06160360_平田 雄大-原宿買取", "G06160158_木下  英亮-原宿買取", "G06160290_河端 辰哉-原宿買取", "G2301200403344200_PHAM MINH CUONG-原宿買取", "G06160348_杉野 计-原宿買取", "G06161335_清水　政仁-原宿買取", "G06161194_中平  ゆうき-原宿買取", "G2305073776099400_菅岩 明美-原宿買取", "G06161264_伊藤 慎也-原宿買取", "G2303262122111200_VU VAN HAU-原宿買取", "G2301050656019300_LE VAN CHUC-原宿買取", "G06160073_相宮　啓志-原宿買取", "G06160388_瀬戸　敏秀-原宿買取", "G06160456_本多　舞生-原宿買取", "G2210214523400600_LUU TIENHAI-原宿買取", "G06161202_佐々木　利明-原宿買取", "G06161367_高橋 漱也-原宿買取", "G2304074277694800_柳井　弘行-原宿買取", "G06160780_户田 飛向-原宿買取", "G06160001_at-池袋大货", "G06161225_宮澤　響-原宿買取", "G06160809_上田 大輔-原宿買取", "G06160545_葛西　真伍-原宿買取", "G06160299_矢野 晋作-原宿買取", "G06161345_山本 健一-原宿買取", "G06160671_丸橋　理央-原宿買取", "G2208172287355000_HO ANH QUAN-原宿買取", "G06160060_松田　大輝-原宿買取", "G2301315997466800_奥垣　士門-原宿買取", "G06160214_高橋　裕-原宿買取", "G06160416_安倍　昴-原宿買取", "G06160457_山口 尊史-原宿買取", "G06161193_石橋　義一-原宿買取", "G2304224509498000_TRAN QUANG LINH-原宿買取", "G06160206_佐々木　周大-原宿買取", "G2303218529066600_TRAN THI MAI-原宿買取", "G2209162374307600_NGUYEN VIET ANH-原宿買取", "G2305066678110100_TRAN VAN TRUNG-原宿買取", "G06161013_水代 健太-原宿買取", "G2211019387550600_kicks lab-原宿一楼", "G06160078_髙田　恭平-原宿買取", "G06161334_川辺　尚弘-原宿買取", "G06160805_堀口　奎佑-原宿買取", "G2208031050645600_松本　裕貴-原宿買取", "G2303298735591300_沖田　暁-原宿買取", "G06160401_田中 謙祐-原宿買取", "G06160892_小路 友也-原宿買取", "G06160840_尹藤 宗幸-原宿買取", "G06161453_閑野　翔太-原宿買取", "G2303261040389300_ZHANG WEI-原宿買取", "G06160574_森 俊哉-原宿買取", "G06161429_田中　登-原宿買取", "G2303289493776100_HA VAN TRUONG-原宿買取", "G2209276913552100_NGUYEN HUYEN ANH-原宿買取", "G06160918_米田 永辉-原宿買取", "G2210301431980500_DINH VAN LUAN-原宿買取", "G2208065690789500_塩田　晃夫-原宿買取", "G06161410_岩間　諒也-原宿買取", "G2208171428549400_HUANG TIANNAN-原宿買取", "G06160334_野島 岳士-原宿買取", "G2302138080917700_大西　智也-原宿買取", "G06160981_别府 大輔-原宿買取", "G06160146_星　拓海-原宿買取", "G06160315_尾形  亮太郎-原宿買取", "G06160785_荒井 美穂-原宿買取", "G06160670_廣田　崇親-原宿買取", "G2209100035765300_新井　央-原宿買取", "G06161485_堀口　翔太郎-原宿買取", "G2212168911527100_林　紘世-原宿買取", "G2209108984769100_川本　大悟-原宿買取", "G06160089_高橋　諒-原宿買取", "G06160332_今井 隆浩-原宿買取", "G2209222256288200_宮本　尚輝-原宿買取", "G2210029013506000_魚野 步夢-原宿買取", "G06161203_蓮沼  大介-原宿買取", "G06161148_ワンタイム（株）-原宿買取", "G06160232_大川 太郎-原宿買取", "G2210037046261700_秋本　晃輔-原宿買取", "G2209056653442800_佐々木　浩明-原宿買取", "G2304013568794300_稲葉　勇大-原宿買取", "G06160664_曾木 佑太-原宿買取", "G06160253_川井　賢太-原宿買取", "G2303183862916300_新井　肇-原宿買取", "G06160342_岩渕　亮-原宿買取", "G06161270_翔吾薊-原宿買取", "G06160263_原田 正宣-原宿買取", "G2304058775920100_祝 大輝-原宿買取", "G2211291099774200_HAN DUC TRUONG-原宿買取", "G06161370_片岡　真弓-原宿買取", "G06160889_細見　昌史-原宿買取", "G06160130_金丸　翔-原宿買取", "G06160169_元木　康太郎-原宿買取", "G06161385_ひが　カズオ-原宿買取", "G2209215619106600_SIY　WAI　LIM-原宿買取", "G06160491_藤山　裕-原宿買取", "G2212308717532900_右手　健一郎-原宿買取", "G2211185844272600_山田　庸宏-原宿買取", "G06160523_宇田川 海飛-原宿買取", "G06160273_佐藤　秀吉-原宿買取", "G06161136_黒川　あかり-原宿買取", "G2208241754570000_蓮沼　大介-原宿買取", "G06160740_永松 太郎-原宿買取", "G06161139_福田　傑-原宿買取", "G2304091382552600_長澤　天徒-原宿買取", "G06161132_田沼　克之-原宿買取", "G06161060_奥川 諭志-原宿買取", "G06160431_土居　隆馬-原宿買取", "G2303015974171200_原宿　店頭購入-原宿店頭購入", "G2208040155066400_糸井　義孝-原宿買取", "G06160322_藤本 慶-原宿買取", "G06160343_石原 海-原宿買取", "G2302093514340100_巣山　晋平-原宿買取", "G2208170789728500_金言-原宿買取", "G2304233284605900_PHAM VAN TUAN-原宿買取", "G06160823_石川 龍太-原宿買取", "G06160100_三上　亮-原宿買取", "G06160988_森岡　謙-原宿買取", "G06161042_島田　笃-原宿買取", "G06161083_金子 晃太郎-原宿買取", "G06160893_岡崎　湧-原宿買取", "G06160086_岩下 大-原宿買取", "G06160531_廣瀬　駿-原宿買取", "G06160082_長谷川　翔大-原宿買取", "G06161064_宫田 彩香-原宿買取", "G2210179353873600_野口　佑泰-原宿買取", "G06161206_小澤　亮-原宿買取", "G2208172649784500_檜垣 大祐-原宿買取", "G2208276934327400_田島　礼音-原宿買取", "G06160845_村下 信一-原宿買取", "G06160065_西村 雄貴-原宿買取", "G06160619_河本　紘幸-原宿買取", "G06161004_岡田　侑也-原宿買取", "G06160970_森岡　あゆみ-原宿買取", "G06160018_脇本　嵐史-原宿買取", "G06160466_津村　慶二郎-原宿買取", "G06161181_高瀬　大嗣-原宿買取", "G2304208013894100_田上　大輔-原宿買取", "G06161426_清水 豊-原宿買取", "G2209038660570900_大岡　玲治-原宿買取", "G2301313883420700_NGUYEN THI NHAT LINH-原宿買取", "G2209259367594500_遠藤　一輝-原宿買取", "G2208242368098700_直井　嘉偉-原宿買取", "G2209222976057000_NGO QUANG VIET-原宿買取", "G06160272_馬田　貴慶-原宿買取", "G2210310519757100_JIANG WENXUAN-原宿買取", "G06161380_衛藤　三四郎-原宿買取", "G2207262138564200_古川　雄一-原宿買取", "G06161210_伊達　一馬-原宿買取", "G2212106321570800_友岡　航-原宿買取", "G2208190230164200_池上　幸宏-原宿買取", "G06161079_ヨヨナ　ヤロキ-原宿買取", "G06161302_柴田 祐希-原宿買取", "G2209223782591700_竹石 弘平-原宿買取", "G2305118064648700_BUI HAI LONG-原宿買取", "G06161356_黑田 勝雄-原宿買取", "G06161291_長屋　宏-原宿買取", "G2211149634596600_CM-原宿一楼", "G2208172614183700_吉田 将義-原宿買取", "G06160125_伊藤　翼-原宿買取", "G2304233923328600_DO NANG DUNG-原宿買取", "G2210274096736200_水月　尚孝-原宿買取", "G2210027991984400_志岐 宏-原宿買取", "G06160595_内藤 喜仁-原宿買取", "G06161172_藤井　仁宣-原宿買取", "G2209012516398600_玉野神之介-原宿買取", "G06161491_宫澤 拓也-原宿買取", "G06161383_櫻井　寮-原宿買取", "G2212124081987600_NGUYEN TRUNG NGUYEN-原宿買取", "G06160969_柴田 悠吾-原宿買取", "G2303270940032800_PHAM QUANG SON-原宿買取", "G2211265712896200_山崎　彰太-原宿買取", "G2301297875959200_榛原　涼-原宿買取", "G06160674_山下　翔太-原宿買取", "G06160991_松田 裕贵-原宿買取", "G2212026582857700_TRAN DINH VU-原宿買取", "G2209214569218100_吉田　一輝-原宿買取", "G06160993_筆保　翔-原宿買取", "G06160361_沼崎　一輝-原宿買取", "G06161096_大村　昇司-原宿買取", "G06160706_高橋　清一-原宿買取", "G2303262779886700_LE NGOC HUNG-原宿買取", "G06160685_塚本　智之-原宿買取", "G2208208941557100_奥田　和明-原宿買取", "G06160598_吉田 智幸-原宿買取", "G2207217628619500_田村　日爽-原宿買取", "G2210204755920000_丹治　功-原宿買取", "G2209030418583100_仲　亮-原宿買取", "G2212178084346200_堀田　彪太-原宿買取", "G06160517_原田 健-原宿買取", "G2303130499454900_NGUYEN VAN TRA-原宿買取", "G2208207555109300_猪飼　大翔-原宿買取", "G2210011102061600_尹　国東-原宿買取", "G2210223445290800_中村　太一-原宿買取", "G06160741_平田 赳夫-原宿買取", "G06161144_遠藤　佑太-原宿買取", "G2303183529357900_高橋　拓也-原宿買取", "G06160874_斎藤　颯-原宿買取", "G2303112692296400_CHU VAN DAT-原宿買取", "G06160843_上田 楽人-原宿買取", "G06160096_凩　忍-原宿買取", "G2211265775748700_橋本　直樹-原宿買取", "G2304296441745600_下村　正翔-原宿買取", "G2301139323102900_HO VAN BINH-原宿買取", "G06161393_三沢　裕司-原宿買取", "G06161118_高塚　俊-原宿買取", "G06160259_高橋　敏幸-原宿買取", "G06160515_柴崎　理玖-原宿買取", "G2304126802079100_ZHOU JIAO-原宿買取", "G06161402_铃木 啓文-原宿買取", "G06160443_手嶋 久美-原宿買取", "G2209118020089500_宫崎　有司-原宿買取", "G06160375_花立　夢奈-原宿買取", "G2303174536062800_DO DUY HAO-原宿買取", "G06160851_野村 悠太-原宿買取", "G2208040006838100_宮崎　有司-原宿買取", "G06161022_保手浜 康輔-原宿買取", "G06160769_坂口 寛人-原宿買取", "G06160191_上田 亮-原宿買取", "G06160057_水谷　公彦-原宿買取", "G06160218_吉田 萌-原宿買取", "G2211309679896600_相川　贵昭-原宿買取", "G06160407_ゆユウゼン-原宿買取", "G2207262147812600_古市　将大-原宿買取", "G06160430_腰高　壮-原宿買取", "G06160097_平田　千聖-原宿買取", "G06161256_加藤　一世-原宿買取", "G2212042712897300_DAO QUANG HAO-原宿買取", "G2302164027336200_NGUYEN THI NGOC ANH-原宿買取", "G2209109810005100_江花　光太-原宿買取", "G2302110905692400_NGUYEN THI DUNG-原宿買取", "G2209214004082400_沼田　誠-原宿買取", "G06160077_小林　茂丸-原宿買取", "G06160473_久保 奨-原宿買取", "G06160432_早川　義树-原宿買取", "G06161355_小柳 順-原宿買取", "G2211036064395200_戸崎　聖也-原宿買取", "G06161246_比嘉 贵勇-原宿買取", "G06160014_高橋　弘太-原宿買取", "G06160686_屋田　日翔-原宿買取", "G2211291538469600_田中　亮太-原宿買取", "G2211178198693600_地藏　由馬-原宿買取", "G06160408_山口 勝巳-原宿買取", "G06160019_大島　宏海-原宿買取", "G2209293485556300_別府　大輔-原宿買取", "G2212308796928900_NGUYEN MINH DUC-原宿買取", "G06160975_高橋　知-原宿買取", "G06160320_坂谷 隆史-原宿買取", "G2211300102275100_谷ツ田　昌寛-原宿買取", "G2302147248911300_郑 文闻-原宿買取", "G06160884_深堀　太志-原宿買取", "G06160205_深堀　直也-原宿買取", "G2301174932963700_大塚　勝彦-原宿買取", "G06160492_淀縄　宥人-原宿買取", "G06160978_迫田　大地-原宿買取", "G2303236421944600_後藤　一星-原宿買取", "G06160267_安江 大輔-原宿買取", "G2210081304981900_NGUYEN THI CHAM-原宿買取", "G2209145111248300_鶴巻　美智雄-原宿買取", "G06161398_植田　浩文-原宿買取", "G2208226966494300_高橋　太賀-原宿買取", "G06160383_久保田　陽太-原宿買取", "G06161237_彬山 幸正-原宿買取", "G2303129990025900_CAN VAN DUC-原宿買取", "G06161343_秋山 司-原宿買取", "G06161061_佐佐木 悠二-原宿買取", "G2304279151232500_坂本　雄基-原宿買取", "G06160700_原田 陸久-原宿買取", "G06161261_高橋 知-原宿買取", "G06160118_柴山　友俊-原宿買取", "G2208171400095500_五十嵐　司-原宿買取", "G06160331_三四郎-原宿買取", "G06161183_加藤　裕司-原宿買取", "G2210301658805300_NGUYEN THI MINH NGOC-原宿買取", "G06161168_西里　祐貴-原宿買取", "G2207262772422500_中鉢 凌-原宿買取", "G06161003_岡元　祥太-原宿買取", "G06161088_金水　正一-原宿買取", "G06160119_原屋敷 悠-原宿買取", "G06160834_相川 貴昭-原宿買取", "G2211291258261300_DO TIEN CONG-原宿買取", "G06160170_牧　智康-原宿買取", "G06161066_嶺尚弥-原宿買取", "G2302111001427900_DANG MANH KIEN-原宿買取", "G2208225894351600_辻本 祐希-原宿買取", "G2208066444491300_斎藤　駿-原宿買取", "G2211123909850000_広井　俊-原宿買取", "G06160381_伊藤　恭子-原宿買取", "G06161469_上田 崇一朗-原宿買取", "G06160709_町田　和暉-原宿買取", "G06160101_森岡　純也-原宿買取", "G2302288367074300_NA  VAN TRUONG-原宿買取", "G06160815_迫田竜一郎-原宿買取", "G06161242_铃木 啟太-原宿買取", "G06160228_大庭　勇紀-原宿買取", "G06160943_坂入 弘哲-原宿買取", "G2208235027238800_谷口　怜哉-原宿買取", "G2301252925298600_super sports-原宿買取", "G06161298_角田 淳-原宿買取", "G2305049258667800_小島　俊介-原宿買取", "G06160215_遠藤　睦実-原宿買取", "G06160075_土谷　雅彦-原宿買取", "G2209215543982400_田中　隼-原宿買取", "G2208171510085500_石田　優貴-原宿買取", "G06161140_西島　亮-原宿買取", "G2210301374651400_DANG VAN DUONG-原宿買取", "G06160335_藤井 仁宣-原宿買取", "G06160079_中野　達徳-原宿買取", "G06160023_窪田 聖哉-原宿買取", "G2304091789355000_TRINH VIET BUY-原宿買取", "G06160584_前澤　星次郎-原宿買取", "G06161076_山下 僚太-原宿買取", "G06160739_加藤 和人-原宿買取", "G06161215_飯野　良幸-原宿買取", "G2208102404775200_QUE SHUPENG-原宿買取", "G06160520_上岡　健太-原宿買取", "G2212178190892300_LE DO KHANH LY-原宿買取", "G06160866_岡田　恭平-原宿買取", "G2209240374350400_藤村　亮汰-原宿買取", "G06160964_栗本　平-原宿買取", "G2301156737731200_TRAN MINH DUC-原宿買取", "G2303270932687700_VU QUANG MINH-原宿買取", "G2209215671158200_鹤巻　美智雄-原宿買取", "G2209283359972500_浦口　準-原宿買取", "G2209222520258200_NGUYEN HUU TRUONG-原宿買取", "G06160266_近藤　卓也-原宿買取", "G06160485_桜井　寮-原宿買取", "G06161382_阪上　兼太郎-原宿買取", "G06160444_福本　剛士-原宿買取", "G06160010_松上　拓馬-原宿買取", "G06160326_反田 明那-原宿買取", "G06161259_友田 悠太-原宿買取", "G06160124_石井　英哲-原宿買取", "G2211071875074000_亀山　大介-原宿買取", "G06160245_藤川　晋輔-原宿買取", "G06160983_網　裕介-原宿買取", "G2210107679152900_長谷川　啓-原宿買取", "G06161274_松藤　貴大-原宿買取", "G06160428_伊代野　淳也-原宿買取", "G2301078921753700_有澤　太雅-原宿買取", "G06160912_山口 真生-原宿買取", "G06160577_佐佐木 啓吾-原宿買取", "G06160789_島村　俊介-原宿買取", "G2211265355449300_NGUYEN LUONG NGOC LINH-原宿買取", "G2208312633546500_NGUYEN　KHANH　LAM-原宿買取", "G06161138_小野　和馬-原宿買取", "G06160116_岩間　徹-原宿買取", "G06160282_岩村泰成-原宿買取", "G2209160946352100_後藤　崇光-原宿買取", "G06161406_大野 正広-原宿買取", "G06160865_川井 鷹介-原宿買取", "G06160915_小林 嵩大-原宿買取", "G2210239935423800_杉山　知弘-原宿買取", "G2211043254889300_林　怡辰-原宿買取", "G06161480_店舗購入-原宿店舗購入", "G2207288692891900_小林　優仁-原宿買取", "G06160748_林 風子-原宿買取", "G2208206475675500_菅岩　明美-原宿買取", "G06160529_細田　一希-原宿買取", "G06160197_浅见 由典-原宿買取", "G06160145_新田　悦司-原宿買取", "G06160133_武田　和廣-原宿買取", "G06160338_成相 建豪-原宿買取", "G06160941_高井 英朗-原宿買取", "G2211054234540700_黎堂保-原宿買取", "G06160393_北田 圭亮-原宿買取", "G06161290_渡邊　康弘-原宿買取", "G2209205113477200_斉藤　愛斗-原宿買取", "G2209215721447300_垣田　莲-原宿買取", "G06161108_成田　隼大-原宿買取", "G2209249134118400_森山　登将馬-原宿買取", "G06161473_尾崎 桂裕-原宿買取", "G06161092_斉藤　和磨-原宿買取", "G2303243751575500_NGUYEN QUOC BAO-原宿買取", "G2211228911216200_藤村　悠-原宿買取", "G2212202872370900_廣川　慎吾-原宿買取", "G06160483_大平 開土-原宿買取", "G06161457_渡邊　浩太-原宿買取", "G06161099_石渡　俊-原宿買取", "G2212036129489300_NGUYEN VAN DUY-原宿買取", "G06161346_関口-原宿買取", "G06161304_松村 康太郎-原宿買取", "G06160872_藤澤　優人-原宿買取", "G2301236528164500_吴 吉籣-原宿買取", "G06160571_高桥 義治-原宿買取", "G2212113878647100_黑田　涼太-原宿買取", "G06160047_工藤　恭仁-原宿買取", "G06161078_熊谷 謙輔-原宿買取", "G2304059194138600_キンピオビ　ジョナタン幸太-原宿買取", "G2210080014797100_JI RUI-原宿買取", "G06161314_二瓶　裹辰-原宿買取", "G06160241_土屋　拓巳-原宿買取", "G06160244_藤原　直人-原宿買取", "G06160624_伊予野　淳也-原宿買取", "G06160306_神農 和雄-原宿買取", "G2209240450154800_DONG QUANG SON-原宿買取", "G06160453_副本 剛士-原宿買取", "G06160392_稲木　裕俊-原宿買取", "G06160778_阿部 公一-原宿買取", "G2210117445339300_LE TRONG HUY-原宿買取", "G06161337_尾山　達也-原宿買取", "G2209063857782900_張　静-原宿買取", "G06161300_牧 良祐-原宿買取", "G2209189060807400_高田　伶-原宿買取", "G06160293_東　直人-原宿買取", "G06160927_佐佐木 新-原宿買取", "G2303031582955300_AUNG SOE HTET-原宿買取", "G06160794_齋藤　翔-原宿買取", "G06161407_石原 省吾-原宿買取", "G2210283452519800_TA VAN DONG-原宿買取", "G2208172897451900_LIU JIANGSHUAI-原宿買取", "G2209170359755000_田村　龍-原宿買取", "G06160041_古田　武史-原宿買取", "G06160862_今野　隼人-原宿買取", "G2209232307980200_佐藤　卓-原宿買取", "G2208180851132400_松原 政治-原宿買取", "G06160081_中島　一浩-原宿買取", "G2301201226880900_菊池　悠斗-原宿買取", "G2212255191676000_猪野　有輝-原宿買取", "G06160853_贵美岛  聡-原宿買取", "G06161424_石原 晃輝-原宿買取", "G06161150_岡本　祥平-原宿買取", "G2211203117314100_藤澤　晟-原宿買取", "G06160105_鈴木　順也-原宿買取", "G06160225_高崎　裕司-原宿買取", "G06161277_工藤　崇恭-原宿買取", "G06161073_金川 裕史-原宿買取", "G2303130529894000_增田　郷-原宿買取", "G06160556_岩井 青樹-原宿買取", "G2208084495142100_鈴木　正樹-原宿買取", "G06160045_山下　翼-原宿買取", "G06160437_藤沢　慎介-原宿買取", "G06160314_藤田 啓吾-原宿買取", "G2209213281521900_新井　慶竜-原宿買取", "G2301243400901300_adidas-原宿网购", "G2301226745017800_澤田　大輝-原宿買取", "G2303033649127100_邱　梓軒-原宿買取", "G06161422_帯刀　恭一郎-原宿買取", "G2209065622714800_増田　郷-原宿買取", "G2303138352557800_星野　博昭-原宿買取", "G06160767_大倉　瑞貴-原宿買取", "G06160418_張勝來-原宿買取", "G06160835_高橋　大-原宿買取", "G2208013776870900_平山　大成-原宿買取", "G2210081395866600_NGHEIM VAN QUAN-原宿買取", "G06161036_宅万 勇太-原宿買取", "G2303087195657600_TRAN THANH TUNG-原宿買取", "G2211195513969000_星野　章浩-原宿買取", "G2302023338854500_TRAN  VAN DOAN-原宿買取", "G06161044_未永 優友-原宿買取", "G06161095_中西　輝太-原宿買取", "G2209302232321300_荒木　康平-原宿買取", "G2208172657605700_NGUYEN  TRUNG HIEU-原宿買取", "G2207306285951900_岩佐  裕斗-原宿買取", "G2208013909114700_鈴木　健介-原宿買取", "G06161213_亀井　一希-原宿買取", "G06160151_森下　克訓-原宿買取", "G06160669_高関　陸-原宿買取", "G2210300914847400_伊東　幹太-原宿買取", "G2212178011312100_VU THI MINH HA-原宿買取", "G06161476_店頭買取-原宿買取", "G2207243362497200_今野　健人-原宿買取", "G06160269_阿部 桂大-原宿買取", "G2209100024213200_中山　貴拓-原宿買取", "G06160870_田中 優孝-原宿買取", "G06160066_向江 祐樹-原宿買取", "G06160271_蓼沼　司-原宿買取", "G06160495_水川 贵雄-原宿買取", "G06160772_原　かや子-原宿買取", "G06160774_村瀬　たくと-原宿買取", "G06160102_西田　岳司-原宿買取", "G06160488_水町 清志郎-原宿買取", "G06161056_久間　進吾-原宿買取", "G2209083156800700_高橋　泰輝-原宿買取", "G06160009_薮崎　知哉-原宿買取", "G06160327_生駒 透-原宿買取", "G2208216954163400_高木　幹英-原宿買取", "G06161463_井関　亮佑-原宿買取", "G06161122_三浦　友嗣-原宿買取", "G06160163_有場　健太-原宿買取", "G2209258041154000_池島　大志-原宿買取", "G2304279392893600_DANG VAN THUY-原宿買取", "G06161316_玉村　嶺-原宿買取", "G06160115_岡野　舜也-原宿買取", "G06161387_柿内　宏-原宿買取", "G06160435_後藤 かゆう-原宿買取", "G06160917_泉 昌利-原宿買取", "G2208092284445200_田邉　和希-原宿買取", "G2302250584151100_NGUYEN HUU HIEP-原宿買取", "G06160627_山路　竜也-原宿買取", "G2302076771680200_文違　竜也-原宿買取", "G06160204_岩倉　将耶-原宿買取", "G06160771_铃木　拓也-原宿買取", "G06160732_立野　肇-原宿買取", "G06161049_中村 まゆみ-原宿買取", "G2209162408076700_NGUYEN　TIEN　DUNG-原宿買取", "G06161032_浜田　雅之-原宿買取", "G2210099686417000_渡邊　大介-原宿買取", "G06160956_横川　大輔-原宿買取", "G06161167_米田　直人-原宿買取", "G2209108725941000_佐々木　悠二-原宿買取", "G06160323_铃木 彰-原宿買取", "G06160234_福島　拓人-原宿買取", "G2304091877985600_THI VAN CUONG-原宿買取", "G06160656_洞口 正明-原宿買取", "G06160800_冨井 信隆-原宿買取", "G06161432_渡邊　敦也-原宿買取", "G2210081289318200_NGUYEN THI HUYEN-原宿買取", "G06160825_高橋　優-原宿買取", "G06160797_森岡　あゆや-原宿買取", "G2304136519930600_YANG HAOWEI-原宿買取", "G06160875_小谷地 裕希-原宿買取", "G2209118046334600_野田　歩夢-原宿買取", "G2208226636222500_石井　裕真-原宿買取", "G06160606_園部　一貴-原宿買取", "G2303101956690600_犬塚　龍生-原宿買取", "G2209162482099100_NGUYEN TRONG KHANH-原宿買取", "G06160924_中村 栄希-原宿買取", "G2210134604376500_横溝　千敏-原宿買取", "G2208110683405400_辻本　祐希-原宿買取", "G06161419_谷澤　健-原宿買取", "G06161050_北條　俊裕-原宿買取", "G06160398_石原　朝子-原宿買取", "G06160372_米井 茂之-原宿買取", "G2208172559225400_畑　慈人-原宿買取", "G2302040893486000_渋谷　勇樹-原宿買取", "G06161067_伊藤　甲相-原宿買取", "G2210188705737800_PHAM THI THUY-原宿買取", "G2209127315940900_韓 林呈-原宿買取", "G06161486_加藤 正彦-原宿買取", "G06161002_宫里 大作-原宿買取", "G2303016650160200_MAI THI THUY HUONG-原宿買取", "G06160292_森本 旭-原宿買取", "G06160849_増田　俊祐-原宿買取", "G2211248190468000_LE DUC HAI-原宿買取", "G2209267538490400_鈴木　とう子-原宿買取", "G06160356_濱田-原宿買取", "G06161312_鳥居　優紀-原宿買取", "G06160782_山下 弘平-原宿買取", "G06161281_野口 直希-原宿買取", "G06160062_藤崎　淳也-原宿買取", "G06160153_nike-池袋网购", "G06160593_赤塚　武継-原宿買取", "G06160379_實石　航介-原宿買取", "G2210284484539700_郭子洋-原宿買取", "G2303236304844600_NGUYEN SY HUNG-原宿買取", "G2208313051050400_NGUYEN　DANG　DUC-原宿買取", "G06160756_佐久間　優-原宿買取", "G06161110_関口　大地-原宿買取", "G2301245630202500_ZHU PENG-原宿買取", "G2208303205862000_魚野　歩夢-原宿買取", "G2209221965726800_MICHIYA TOYOOKA-原宿買取", "G2209232912095000_長田　恭平-原宿買取", "G06160667_湯澤　大和-原宿買取", "G06161286_市川 勇太-原宿買取", "G2304199884829000_VU ANH TUAN-原宿買取", "G2301147310134300_UPTOWN-原宿网购", "G2211080005341900_TRAN THANG TUNG-原宿買取", "G06160140_竹村　直人-原宿買取", "G2209170341765300_黒澤　玲奈-原宿買取", "G06160051_久保　喜敬-原宿買取", "G2207226823332200_三矢　弘志-原宿買取", "G06160224_鹿又 翼-原宿買取", "G06161014_内藤 裕介-原宿買取", "G06161418_桑名 太一-原宿買取", "G2302234871894200_大宮　拓也-原宿買取", "G06160037_吉野　桂一-原宿買取", "G2304091839983200_小林　征司-原宿買取", "G2302172228951200_池田　健一-原宿買取", "G06161470_田中 健太-原宿買取", "G06160958_森永 亮-原宿買取", "G06160329_堀真 樹-原宿買取", "G2302242924330100_小谷　洸人-原宿買取", "G2302250560406300_TRUONG NGOC ANH-原宿買取", "G2303218601004100_NGUYEN DOAN PHE-原宿買取", "G06161326_鈴木　達哉-原宿買取", "G06160035_渋谷　知寛-原宿買取", "G2212034023828600_DO THI QUYNH-原宿買取", "G06160126_細谷　大祐-原宿買取", "G06160222_菊田 浩典-原宿買取", "G06160261_小林　玲央奈-原宿買取", "G2301245343340000_藤澤　秀武-原宿買取", "G06161384_平井 展人-原宿買取", "G06160596_永倉　健裕-原宿買取", "G06160668_岡本　裕翔-原宿買取", "G06160850_今田 沫雪-原宿買取", "G06161087_三原 裕一朗-原宿買取", "G06160337_道脇 翼-原宿買取", "G2303086856353000_村上　謙優-原宿買取", "G2301157489865300_SHAO ZHIMING-原宿買取", "G06160521_陶山 翔太郎-原宿買取", "G2301217467906400_一宮　淳行-原宿買取", "G06161233_玉木 宇-原宿買取", "G06160414_森 一生-原宿買取", "G2211018849829300_内藤　晃央-原宿買取", "G2212035210247500_VU LE HUY-原宿買取", "G06160358_村田　誠徳-原宿買取", "G2208084715486300_山口　勝巳-原宿買取", "G06160808_松田 瑛子-原宿買取", "G2210064931931800_LIU GUICHUN-原宿買取", "G2212211746255400_澤田　憲人-原宿買取", "G06161455_広田　裕也-原宿買取", "G06161103_久徳　一期-原宿買取", "G06160801_菅野　貴之-原宿買取", "G06161329_白土 竜志-原宿買取", "G06160822_佐藤 亮-原宿買取", "G06161094_Billys-池袋网购", "G2208227043241800_石川　和男-原宿買取", "G06160189_柳瀬　雄司-原宿買取", "G06161169_砂川　響-原宿買取", "G2302031422241900_高畑　克斗-原宿買取", "G2210011739123600_堀之内　位-原宿買取", "G06160330_近藤 匠-原宿買取", "G2210134547912000_NGUYEN DUC SANG-原宿買取", "G06160509_矢島　健-原宿買取", "G2303069221529000_LE VAN TRUNG HIEU-原宿買取", "G2212255237288900_山本　慶-原宿買取", "G06161365_伴仲　雄太-原宿買取", "G06160838_得津 陽平-原宿買取", "G06161431_熊倉　勇太-原宿買取", "G2208172099612600_松原 佑-原宿買取", "G2302031410603600_NGUYEN NGOC LONG-原宿買取", "G2302234888854400_八木原　拓哉-原宿買取", "G06161280_水户 田晓-原宿買取", "G2209066048856100_簱山　裕太-原宿買取", "G06161029_宫田 润-原宿買取", "G06161162_遠藤　大輔-原宿買取", "G2210161241033900_NGUYEN KHANH DUY-原宿買取", "G2211089712585600_桒山　尚樹-原宿買取", "G06160916_嘉数　直人-原宿買取", "G06160605_尾崎　広太-原宿買取", "G06160559_绪行 友希-原宿買取", "G06160038_國分　浩之-原宿買取", "G06160377_鶴見　翔太-原宿買取", "G2301121884715200_stock x-原宿一楼", "G2208226731779500_AUNG　SOE　HTET-原宿買取", "G06160848_久保田 哲朗-原宿買取", "G2208172873583600_LING YU-原宿買取", "G06161488_松ノ井　祥昌-原宿買取", "G06161255_連沼 大介-原宿買取", "G2209039257151500_藤掛　周作-原宿買取", "G2208154426759100_廣瀬　裕一-原宿買取", "G06160059_藤田　信也-原宿買取", "G2209267570186200_稲葉　悠介-原宿買取", "G06160091_須賀　幸一-原宿買取", "G2212053752243500_瀧口　慶大-原宿買取", "G06161128_相田　龍逸-原宿買取", "G06160554_浜田　亮-原宿買取", "G2211291832987600_奥山　ゆらら-原宿買取", "G06160308_梅林 裕一-原宿買取", "G06160386_竹添　元-原宿買取", "G2208172591966800_漆澴 隆二-原宿買取", "G06161487_有场 健太-原宿買取", "G06160682_松原　宏紀-原宿買取", "G06160513_平山 大成-原宿買取", "G2208209446433800_秦野　拓也-原宿買取", "G06161435_吉永　浩人-原宿買取", "G2302190085823000_吉田　多郎-原宿買取", "G06161241_長塚  涼-原宿買取", "G2212018936103700_林　小輝-原宿買取", "G06160174_三木　海人-原宿買取", "G06160929_根本 银士-原宿買取", "G06161160_中村　迅希-原宿買取", "G06160027_松原 比呂樹-原宿買取", "G06160179_高橋　真人-原宿買取", "G06160749_板谷隆史-原宿買取", "G06160183_松浦　有真-原宿買取", "G2208208330984000_玉井　優也-原宿買取", "G06160173_東海林　裕也-原宿買取", "G06160254_金田　圭市-原宿買取", "G06161201_西　隆一-原宿買取", "G2303077989556400_佐藤　泰地-原宿買取", "G06161012_石澤　史明-原宿買取", "G2210240527979400_田村　陸人-原宿買取", "G06160641_山下 弘-原宿買取", "G2302093596213800_山田　庸弘-原宿買取", "G2304155575215400_QUANG THI TU VI-原宿買取", "G06160067_松田 宗騎-原宿買取", "G06160128_鎌田　一輝-原宿買取", "G2209108530129800_WANG　YUXIN-原宿買取", "G2212308834038000_内藤　潤-原宿買取", "G06160861_松本　龍也-原宿買取", "G06160787_池田 聖一-原宿買取", "G2209091639462700_加藤　詩音-原宿買取", "G2303023159456900_原宿　ネット購入-原宿ネット購入", "G06160742_手島　亜寿斗-原宿買取", "G2303059141963900_町田　賢斗-原宿買取", "G06160955_信原 味生-原宿買取", "G2211098227123500_久保田　大輝-原宿買取", "G2208207397051300_長谷　観来依-原宿買取", "G2209039275767700_金田　啓夢-原宿買取", "G2301280037757900_嶋村　裕司-原宿買取", "G06160583_庄司 義将-原宿買取", "G06161109_金山　峻己-原宿買取", "G06161341_畠　開人-原宿買取", "G2301315965805900_佐久間　優毅-原宿買取", "G2208226869726700_VU　HOANG　ANH-原宿買取", "G06160421_森 貴哉-原宿買取", "G2301298582035300_竹田　和彦-原宿買取", "G2208209222835000_井上　雄一朗-原宿買取", "G06160318_黑田 爱美-原宿買取", "G06160465_中村 賢明-原宿買取", "G06161436_北村　聡士-原宿買取", "G06161359_荒居 俊太-原宿買取", "G2302243462388400_村上　一志-原宿買取", "G06160362_今井　友生-原宿買取", "G2210028945688300_矢鳩　元気-原宿買取", "G2302251235713700_HUYNH VU TUAN ANH-原宿買取", "G06160759_石川　貴也-原宿買取", "G2208101344339600_高梨　浩司-原宿買取", "G2207182801922000_真野  一馬-原宿買取", "G06160149_田中　秀憲-原宿買取", "G06160240_長浦　雅武-原宿買取", "G06160999_中倉-原宿買取", "G2209011914317400_安彦　大路-原宿買取", "G06160910_石川 祐介-原宿買取", "G2209223597125500_NGUYEN VAN DAT-原宿買取", "G06160687_葉桐　遼-原宿買取", "G06160734_宮越 陽大-原宿買取", "G06160899_鈴木　康介-原宿買取", "G2207166019918300_鈴木　慎弥-原宿買取", "G06160846_小林　大祐-原宿買取", "G06160072_加茂　幸将-原宿買取", "G06160365_伊藤 善博-原宿買取", "G2303050754481600_LIU DONG-原宿買取", "G06160063_土屋 和輝-原宿買取", "G06161052_田中 義郎-原宿買取", "G06161403_高桥 知-原宿買取", "G06161323_りん　たかほ-原宿買取", "G06160965_谷合 航太-原宿買取", "G06160264_村井 貴文-原宿買取", "G2208153603126100_大江　稔-原宿買取", "G06161292_松木 淳-原宿買取", "G2301078668838900_福島　菜七-原宿買取", "G2303228309750400_NGUYEN BAO CHAU-原宿買取", "G2304013634873500_LE NGOC TRONG HIEU-原宿買取", "G2210293510030300_盧天瀅-原宿買取", "G2209206009326100_HUYNH QUOC BAO-原宿買取", "G2211274087231200_竹志　優作-原宿買取", "G06160208_小菅　彰仁-原宿買取", "G06160424_白根　淳平-原宿買取", "G06160474_加瀬　龍一-原宿買取", "G06160026_荒居 恭隆-原宿買取", "G06160369_中村 健-原宿買取", "G06161375_大関　薫-原宿買取", "G06160986_山本　遼平-原宿買取", "G06160506_佐藤 大綺-原宿買取", "G2304146473910700_TRINH VIET DUY-原宿買取", "G2304277133921600_NGUYEN DANG DUC DUY-原宿買取", "G2304188812859100_林　さち子-原宿買取", "G06160276_五十嵐 孔德-原宿買取", "G2210274521075400_RINKAN 渋谷店-原宿買取", "G06161437_市川　健人-原宿買取", "G2301078436190300_平戸　智基-原宿買取", "G06160533_宫地 宏季-原宿買取", "G2304040540133700_五味　建一-原宿買取", "G06160959_桃井 悠也-原宿買取", "G06161377_柳田 徹-原宿買取", "G06160317_今井　啓輔-原宿買取", "G2303191466962000_伊藤　恭平-原宿買取", "G06160385_木谷　典史-原宿買取", "G06160382_鲍 蟠権-原宿買取", "G06160364_中尾唯月月-原宿買取", "G2207167242350600_玉城　広之-原宿買取", "G06161423_大桥 勇辉-原宿買取", "G2211035081860400_今野　雄貴-原宿買取", "G06161289_中村-原宿買取", "G06160588_金 硬洙-原宿買取", "G06160646_佐藤　佳辉-原宿買取", "G2304224690390300_QUACH DAI DUONG-原宿買取", "G2303307215516400_山本　瞭太郎-原宿買取", "G2208312049962000_清水　竜翔-原宿買取", "G06160542_松田 一秀-原宿買取", "G2212113810021700_TRAN THANH TU-原宿買取", "G06160490_茅野　健司-原宿買取", "G2304013076051000_BACH DUY THAN-原宿買取", "G06160251_相原 啓吾-原宿買取", "G06160137_SALVANDOU SANTOSO-原宿買取", "G2301078465444500_町田　拓哉-原宿買取", "G06161070_飯村　崇史-原宿買取", "G2210011383102700_中角　仁哉-原宿買取", "G06161328_荘司 政紀-原宿買取", "G2302181357364500_進上　怜-原宿買取", "G06161055_鈴木　理恵-原宿買取", "G2210080926775100_NGUYEN HUU THINH-原宿買取", "G2303051254855400_永田　健太郎-原宿買取", "G2302155165250100_大瀬良　智行-原宿買取", "G2212053773607100_小林 陽三-原宿買取", "G06161336_遠藤　暁彦-原宿買取", "G2304092585026600_VU THANH LONG-原宿買取", "G06160695_河本　響-原宿買取", "G06160162_岡村　藤輝-原宿買取", "G06161020_赤坂 成哉-原宿買取", "G2212034041956200_遠藤　豊-原宿買取", "G2209222378326900_NGUYEN QUANG HUY-原宿買取", "G06160246_菅沼　直樹-原宿買取", "G2209188149090900_WIN　MAUNG-原宿買取", "G06161338_土田 拓海-原宿買取", "G06161033_来栖 勇希-原宿買取", "G06160888_真田 春来-原宿買取", "G2208277847410800_薄田　大空-原宿買取", "G2209116478308100_HUYNH　VAN　LINH-原宿買取", "G2209145276885000_池崎　英樹-原宿買取", "G06160374_斎藤　聡-原宿買取", "G2211080235965700_鄭　尊委-原宿買取", "G2209250062146100_ZHANG DUO-原宿買取", "G06160185_奥特莱斯禁用", "G2302260108170100_中山　礼偉-原宿買取", "G06160161_児玉　和樹-原宿買取", "G2209284122915000_NUYEN VAN NGUYEN-原宿買取", "G06160217_岩井　颯真-原宿買取", "G06160223_櫛田　昌輝-原宿買取", "G2208171009064600_HA　THI　OANH-原宿買取", "G2208287916581900_森俊哉-原宿買取", "G06160555_三吉野晃太郎-原宿買取", "G2303156931918000_NGUYEN VAN TU ANH-原宿買取", "G06160597_酒井 健太郎-原宿買取", "G06160352_劉　帥-原宿買取", "G06160134_長谷川　礼拓-原宿買取", "G06160113_小菅　修一-原宿買取", "G06160303_中山 雄大-原宿買取", "G2303209916093100_柏木　弘治-原宿買取", "G2210090183490800_高橋　琉盛-原宿買取", "G2208226703830900_NGUYEN TRAN PHI KHANH-原宿買取", "G06161085_荻野 祐一-原宿買取", "G2305056392957300_奈良　幸一-原宿買取", "G2209215565377100_西村　優太郎-原宿買取", "G06160194_小西 基裕-原宿買取", "G06160210_塚越　弘起-原宿買取", "G06160260_高畑　洋介-原宿買取", "G06160680_西田　勇介-原宿買取", "G06160997_米田 勇辉-原宿買取", "G06160478_川崎 庸右-原宿買取", "G06160812_櫻井　雄太-原宿買取", "G06160735_鈴木啓文-原宿買取", "G06160887_鳥谷　哲也-原宿買取", "G2211034658680000_正澤　勇太-原宿買取", "G06161296_井上 裕成-原宿買取", "G06160313_米倉 健太-原宿買取", "G06160237_岡　博之-原宿買取", "G2211117009873800_川元 陵-原宿買取", "G06160783_長浜朋也-原宿買取", "G06160028_出井 克彦-原宿買取", "G2208225982085400_ZHOU　KEYUFENG-原宿買取", "G06161028_杉山　司-原宿買取", "G06160160_稲田　信一郎-原宿買取", "G06161350_上杉 健一郎-原宿買取", "G2304091699110500_NGUYEN HUYNH DUC-原宿買取", "G06161001_三好 純平-原宿買取", "G2304049804691100_玉置　来夢-原宿買取", "G06160579_高階　悠介-原宿買取", "G2303069617927900_青木　亮-原宿買取", "G2304146134111200_三浦　哲平-原宿買取", "G06160540_鈴木　雄輔-原宿買取", "G2210196750646800_TA DINH QUAN-原宿買取", "G06161102_吉野　彩華-原宿買取", "G2303298135360500_QUANG MAI NHAT TIEN-原宿買取", "G06161459_作田 武俊-原宿買取", "G06161250_齐藤 晃太郎-原宿買取", "G06161174_鈴木　淳-原宿買取", "G06160524_青木　健俵-原宿買取", "G2208208861373000_松井　啓太郎-原宿買取", "G06160582_高杉　尚辉-原宿買取", "G2208056596471800_HU　XIN-原宿買取", "G06161065_片岡　孝太-原宿買取", "G2301078528977000_飯島　健正-原宿買取", "G2209303073357500_金澤　涼雅-原宿買取", "G2209109394731200_滑川　誠哉-原宿買取", "G06160906_原田　笑美子-原宿買取", "G06160015_海神 空也-原宿買取", "G06161357_岩川 雅纪-原宿買取", "G06160922_田地 雄作-原宿買取", "G06161339_高野 健-原宿買取", "G06160213_牧 亮介-原宿買取", "G06160248_宮尾　和茂-原宿買取", "G06161279_都留 俊介-原宿買取", "G06161200_茨城-原宿買取", "G2209038869829300_猪俣　雅英-原宿買取", "G06160333_兼田 惠梨子-原宿買取", "G2301183494756200_小山　菜実-原宿買取", "G06160690_竹井　翔一-原宿買取", "G2209109219787800_古田　雄次-原宿買取", "G2209303159900900_小叶-原宿買取", "G06160907_橫川 優樹-原宿買取", "G2212238347360100_佐藤　正章-原宿買取", "G2210027881947800_NGUYEN SIPHU-原宿買取", "G2301244476020500_alpen-原宿网购", "G06161391_藤本恭有-原宿買取", "G06161069_大橋　勇輝-原宿買取", "G06160350_青山　和治-原宿買取", "G2208250383160000_三浦　 勇輔-原宿買取", "G2208216086385600_櫻井　雄基-原宿買取", "G2303236488778600_村田　日爽-原宿買取", "G2210065038018200_秋山　海-原宿買取", "G2209030085501600_佐藤　北斗-原宿買取", "G2212124459949200_吉野　怜-原宿買取", "G06161294_増田　和樹-原宿買取", "G06161395_铃木 秋土-原宿買取", "G2211159123782200_林　真司-原宿買取", "G06161031_川口 陽導-原宿買取", "G06160093_保坂　淳一-原宿買取", "G2212194804435800_VU HONG DICH-原宿買取", "G06160933_関　哲平-原宿買取", "G2209091132747100_水谷　拓樹-原宿買取", "G2301271275987800_NGUYEN DANG DUC-原宿買取", "G06160295_杉山　幸正-原宿買取", "G06160347_柳井 剛-原宿買取", "G06160220_岡本　学-原宿買取", "G06160678_上田　知典-原宿買取", "G2301078722086500_飯塚　翔大-原宿買取", "G2208207504080600_大塚　直樹-原宿買取", "G2301077731215500_LIU RONG-原宿買取", "G06161282_久保翔马-原宿買取", "G06161186_大田和　　孝宏-原宿買取", "G2211053922504100_松永　太郎-原宿買取", "G06160989_世古口 英大-原宿買取", "G06160819_吉田　透-原宿買取", "G06161489_関口 繁-原宿買取", "G06161084_佐佐木 聡晃-原宿買取", "G06160528_赤塚　岳-原宿買取", "G06161182_山口 昌一-原宿買取", "G06160196_樋口　绫太-原宿買取", "G2301243768058800_後藤　和也-原宿買取", "G2208172393984500_清水 竜翔-原宿買取", "G06161121_磯部　隆政-原宿買取", "G2302076751086000_文明-原宿買取", "G06160481_坂 润一-原宿買取", "G2211143422401000_入仓退货-原宿買取", "G06160535_长谷川 啓-原宿買取", "G06160779_大前 元気-原宿買取", "G06161059_荻原 比吕司-原宿買取", "G06160643_中西 海斗-原宿買取", "G2210150905096600_池田　翔太-原宿買取", "G2211071735239500_NGUYEN MINH DUY-原宿買取", "G2303103179028400_榎戸 宏-原宿買取", "G06161396_岸田 贵佳-原宿買取", "G06161189_清水　健太-原宿買取", "G2209047824830100_橋本　溪汰-原宿買取", "G06161116_村上　遼-原宿買取", "G2209039007680600_伊藤　俊一-原宿買取", "G06160650_小平 達也-原宿買取", "G06160310_中村　克久-原宿買取", "G06160635_佐久间 大梦-原宿買取", "G06161467_吉田 正幸-原宿買取", "G2302059187906300_DAI CONG-原宿買取", "G06161142_反町　大雅-原宿買取", "G06160025_増古　高士-原宿買取", "G2209240620523000_NGUYEN TUAN ANH-原宿買取", "G06160950_舘　藤代-原宿買取", "G2305066337186200_原　健太-原宿買取", "G2302076736945700_TRAN VAN THIEU-原宿買取", "G06160441_矢沢　剛-原宿買取", "G06160578_芝田 悠大-原宿買取", "G06160467_石井　　英哲-原宿買取", "G06161071_三好 杏典-原宿買取", "G06161173_高橋　宏規-原宿買取", "G06161151_鎌倉　敦-原宿買取", "G06161058_加藤 修司-原宿買取", "G06161465_大塚　匠-原宿買取", "G06161344_真木内 貴之-原宿買取", "G06160504_中村 盛一-原宿買取", "G2208209277265300_HA THI OANH-原宿買取", "G06160885_小泉 欣也-原宿買取", "G06160847_金城　義仁-原宿買取", "G2207313448215300_松嶋　浩史-原宿買取", "G06161010_松本 耕井-原宿買取", "G2208119632654500_米井　聡-原宿買取", "G06161420_野田 洋之-原宿買取", "G2208286067625800_由村　龍之介-原宿買取", "G06160339_铃木 康広-原宿買取", "G06160992_早坂 永-原宿買取", "G2208075319525600_滝沢　雄-原宿買取", "G2305013105128600_木村　正男-原宿買取", "G2301243080532700_田　明-原宿買取", "G06160763_新村　嘉人-原宿買取", "G06161133_小川　隆二-原宿買取", "G06160110_松本　拓巳-原宿買取", "G06161477_郵送買取-原宿買取", "G06160636_中村 祥雄", "G06161175_廣島　佑輔-原宿買取", "G06161401_荒井 悠-原宿買取", "G06160719_岡村　篤慶-原宿買取", "G2301078504360300_松永　東-原宿買取", "G06161351_高桥 昂希-原宿買取", "G06160894_安陵 達也-原宿買取", "G2302182029617600_NGUYEN CONG THANH-原宿買取", "G2208277715245200_GONG　NAN-原宿買取", "G06161293_大西 祐哉-原宿買取", "G2211158414473400_藤木　良平-原宿買取", "G2212104435755900_中山　翔平-原宿買取", "G06161146_福島　学-原宿買取", "G06160543_永住 翔庸-原宿買取", "G06160971_官下 摩衣子-原宿買取", "G06161354_小見　憂太-原宿買取", "G2209266327686100_濱多　登夢-原宿買取", "G2302269973947200_NGUYEN TIEN THANH-原宿買取", "G06160697_铃木 雄輔-原宿買取", "G06160016_ABC-池袋网购", "G2208172631286700_菅野 雅人-原宿買取", "G06161439_宇田　紗彩-原宿買取", "G2305084355859000_BUI LE MY NGAN-原宿買取", "G06160954_栄谷 郁弥-原宿買取", "G06160340_井上 紘一-原宿買取", "G2211271722092700_朱 帰航-原宿買取", "G06160558_先崎駿太-原宿買取", "G2209038595064500_川村　政之-原宿買取", "G2302251221625600_NGUYEN QUANG VU-原宿買取", "G2303236298690900_NGUYEN KHANH HANG-原宿買取", "G06160536_桑原　章-原宿買取", "G06160754_岩下　弘河-原宿買取", "G2209081210470000_志田　智也-原宿買取", "G06161234_加瀬　龍一-", "G2303104221961700_板倉　優希-原宿買取", "G06161462_三井 隆雄-原宿買取", "G2208172149873400_服部　啓吾-原宿買取", "G2303042759863100_XU DANYANG-原宿買取", "G2209215742926600_XU HAIRUI-原宿買取", "G06160420_有馬 高智-原宿買取", "G06160702_森本 昇平-原宿買取", "G06161034_中川　拓海-原宿買取", "G2209107830492200_林　進宝-原宿買取", "G2208242231254500_伊東　明海-原宿買取", "G2304293488672600_TRAN DONG SON-原宿買取", "G2304215354093000_TRUONG THI QUYNH-原宿買取", "G06161217_宫城県-原宿買取", "G06160946_松川　達哉-原宿買取", "G06161295_池田 椋亮-原宿買取", "G06160937_竹内　啓太-原宿買取", "G2208170826163400_佐々木　千那-原宿買取", "G06160216_船津　良太-原宿買取", "G2301121850525000_PHAM THI THU TRANG-原宿買取", "G2208217885037700_青柳　雄大-原宿買取", "G06160127_藤井　陽介-原宿買取", "G2303156904647000_榎本　宏-原宿買取", "G2304091417511500_TRAN MINH TIEN-原宿買取", "G2208305245873200_野口　祐介-原宿買取", "G2208066387979400_岩崎　塔哉-原宿買取", "G06160640_田中　純也-原宿買取", "G2211248324447000_北島　友裕-原宿買取", "G06161227_村上 昇一-原宿買取", "G06161053_松尾 達也-原宿買取", "G06161389_田中　大地-原宿買取", "G06160600_斉藤　昂大-原宿買取", "G06161180_石林　拓人-原宿買取", "G06160403_竹下 勇星-原宿買取", "G2211167931285700_鈴木　大樹-原宿買取", "G2302076685892900_長尾　龍-原宿買取", "G06160510_鈴木 智之-原宿買取", "G2303120131842500_竹内　脩人-原宿買取", "G06161163_大森　弘武-原宿買取", "G06160839_太田 賢-原宿買取", "G2208172912516500_岩田 維久磨-原宿買取", "G06161458_山下　豊-原宿買取", "G2302251226941300_NGUYEN THI MINH QUYEN-原宿買取", "G2210187243172000_NGUYEN TRAN VIET ANH-原宿買取", "G06160951_白川 莉帆-原宿買取", "G2208153634614500_安井　一輝-原宿買取", "G06161425_平松　和磨-原宿買取", "G2208153469163200_八木　沢瀬名-原宿買取", "G06160344_加藤 克己-原宿買取", "G06160399_浦上 恒佑-原宿買取", "G06160548_久保田　光纪-原宿買取", "G06160031_松本　航大-原宿買取", "G2211167072156800_三浦　快斗-原宿買取", "G06161444_川村　伊織-原宿買取", "G06160963_家城　翔-原宿買取", "G06160074_卯山　大樹-原宿買取", "G2208154280390000_冨吉　洸貴-原宿買取", "G06160094_佐藤　譲-原宿買取", "G06160642_岩井 克樹-原宿買取", "G06160842_山田　夏辉-原宿買取", "G2208172640236300_金 長元-原宿買取", "G2208082463302000_永松　太郎-原宿買取", "G06160390_山尾　和也-原宿買取", "G2210223288188800_張　新宇-原宿買取", "G06160176_林　将勝-原宿買取", "G2211140501438800_渡辺　和茶-原宿買取", "G2209187569650700_登 智記-原宿買取", "G06161239_高居 優記-原宿買取", "G2302182013545600_大坪　道成-原宿買取", "G06161040_大川 航平-原宿買取", "G06160896_寺田 裕翔-原宿買取", "G2303031107723200_SAUNG YAW-原宿買取", "G2212265062513100_森　一男-原宿買取", "G06160765_小林　崇大-原宿買取", "G2210301781177100_小林 美優-原宿買取", "G2212095496285700_長谷川　徹-原宿買取", "G2210310873846200_上柿　良輔-原宿買取", "G2302067189118500_SON CHEOLIM-原宿買取", "G06160195_池田 馨-原宿買取", "G2208172821933000_NGUYEN  THANH LONG-原宿買取", "G2302102349802300_入札専用（CM）-原宿CM", "G06160799_村上 翔太-原宿買取", "G06160657_丹保 辉-原宿買取", "G2212255534085400_PHAM THI THUY VAN-原宿買取", "G06160434_营原 昭彦-原宿買取", "G06160103_長野　大樹-原宿買取", "G06160108_吉田 一弥-原宿買取", "G2209045763536900_野崎　秀佑-原宿買取", "G2208153559012000_道城　憲一-原宿買取", "G06161016_白井 慧大-原宿買取", "G06160616_菅沼　輝-原宿買取", "G06160721_斉藤　晶德-原宿買取", "G06161397_久留 納-原宿買取", "G2208031146772100_鈴木　秋土-原宿買取", "G2209240492306400_吉田　健一-原宿買取", "G06161347_若城 良敏-原宿買取", "G2208172885966300_姚 凌枫-原宿買取", "G06160378_山口　大輔-原宿買取", "G06161258_黑田 彩夏-原宿買取", "G2209223965994000_HOANG　TRUNG　AN-原宿買取", "G06160458_渋谷　清人-原宿買取", "G2208119610786700_DUONG THI HUYEN-原宿買取", "G06161026_家形　大毅-原宿買取", "G2304022199679400_野村　昌敬-原宿買取", "G06160905_宮澤　隼人-原宿買取", "G2303218637280100_NGUYEN THI AI NHU-原宿買取", "G06160476_铃木 真生-原宿買取", "G06161043_西 龍一-原宿買取", "G2208235196747800_柿　内宏-原宿買取", "G06161243_上里 俊介-原宿買取", "G2302093417584300_遠山　健太-原宿買取", "G06160768_铃木 志一那-原宿買取", "G06161191_川上  一明-原宿買取", "G2303112900601900_VU HUU TIN-原宿買取", "G06160803_齊藤 秀男-原宿買取", "G06161047_早川 竜平-原宿買取", "G06160012_菊田　浩典-原宿買取", "G06160178_織田　隼人-原宿買取", "G2303014629941300_YU　PINFU-原宿買取", "G2210302158887700_NGUYEN THI VAN-原宿買取", "G2301237004883300_渡辺　陸人-原宿買取", "G06161358_山本 友彦-原宿買取", "G06160689_荒井 俊太-原宿買取", "G06161254_鈴鹿 大嗣-原宿買取", "G06161063_松藤 雄希-原宿買取", "G06160968_関川　瑞穂-原宿買取", "G06160502_峯田　卓-原宿買取", "G2304224316926800_LE THI NGOC HAN-原宿買取", "G2211248172197700_槇井　啓人-原宿買取", "G06160400_宮澤　拓也-原宿買取", "G2303182385139000_長野　斗馬-原宿買取", "G06161221_裕也-原宿買取", "G06161179_大林　直輝-原宿買取", "G2209153576972000_斉欣-原宿買取", "G2209143144481700_加藤　紀幸-原宿買取", "G2211265561060900_吉田　秀太-原宿買取", "G2209100091123200_三木　清士郎-原宿買取", "G06160198_横山 幸泰-原宿買取", "G2209039400313900_YU　LIANG-原宿買取", "G06160061_沼田　隼-原宿買取", "G2303252203981700_岩淵　大-原宿買取", "G06160004_粂井　天至-原宿買取", "G06161039_森川 慶祐-原宿買取", "G06160319_三枝　大真-原宿買取", "G2304215455437600_滝田　恭介-原宿買取", "G06161114_高橋　宗栄-原宿買取", "G2303138141047200_今村　一矢-原宿買取", "G06161025_渡邊　嘉人-原宿買取", "G06160743_川上　望-原宿買取", "G06160270_岡田　絹子-原宿買取", "G06160071_佐野　海人-原宿買取", "G2211193304102400_鄭 秉周-原宿買取", "G06160013_下田 知令-原宿買取", "G06160726_田中 健一-原宿買取", "G2301095246734600_松本　天霸-原宿買取", "G06161199_鈴木　利哉-原宿買取", "G06161228_飯野　吉幸-原宿買取", "G06160427_桜井　純-原宿買取", "G06161447_小野田　直洋-原宿買取", "G2301298548071000_田原　栄作-原宿買取", "G2212176517708500_NGUYEN THANH QUAN-原宿買取", "G2210196517133700_NGHIEM VAN QUAN-原宿買取", "G06161176_田中 敬人-原宿買取", "G06161137_濱辺　良-原宿買取", "G06160138_毛利　健児-原宿買取", "G2208172762462300_但馬　祐人-原宿買取", "G06160532_池上 充辉-原宿買取", "G06160580_梅田 学-原宿買取", "G06160562_百代 秀一郎-原宿買取", "G2208172924433200_吉川 友明-原宿買取", "G2207191996043100_土屋　友樹-原宿買取", "G2209045911553100_埴田　唯人-原宿買取", "G2212053809133300_渡邊 寿宗-原宿買取", "G2212018971285900_ZHUANG LING-原宿買取", "G06161451_松浦　史也-原宿買取", "G06160611_村上 史弥-原宿買取", "G06160817_藤原　裕也-原宿買取", "G2209259693046300_NGO  MINH TRI-原宿買取", "G06160522_倉橋　宏史-原宿買取", "G2208172314759300_SU JUNHUA-原宿買取", "G06160766_日比野 佑樹-原宿買取", "G2209039979205000_LE　QUANG　TRUNG-原宿買取", "G06161054_三好 雄作-原宿買取", "G2303200400319500_LUONG VAN TU-原宿買取", "G2304296474573000_乙黒　昭-原宿買取", "G2209030237388900_野田　直明-原宿買取", "G2208287808845800_SU　VAN　SON-原宿買取", "G2211271742807500_及川　将史-原宿買取", "G06160022_上村 史乃-原宿買取", "G06160106_佐々木　亮平-原宿買取", "G2211195735517000_LE CONG TUYEN-原宿買取", "G06160860_武井 雄博-原宿買取", "G06160639_佐佐木 亮平-原宿買取", "G06160713_長浜　大雅-原宿買取", "G2303227380315000_八木 郁弥-原宿買取", "G06160209_中山 茂大-原宿買取", "G2210301482808500_PHAM VAN HOANG-原宿買取", "G2210301762750000_NGUYEN DINH DONG-原宿買取", "G2208276967443200_石橋　幹大-原宿買取", "G2209213902706600_鈴木　孝夫-原宿買取", "G06161197_渡辺　兼次-原宿買取", "G06161362_岩田　匡史-原宿買取", "G06161192_島津　直弥-原宿買取", "G2302287563120700_坂東　修平-原宿買取", "G2210028607567900_相馬　大輝-原宿買取", "G06161171_小川 耕平-原宿買取", "G06160172_河　清志-原宿買取", "G06161466_門田 武朗-原宿買取", "G06160909_水野 英弘-原宿買取", "G2302217587047000_隼田　将吾-原宿買取", "G06160673_敷島　章人-原宿買取", "G06160755_古山 和哉-原宿買取", "G2209249664702700_TRAN VAN THINH-原宿買取", "G06160171_丸山　達也-原宿買取", "G2303191274485100_NGUYEN TIEN HUONG-原宿買取", "G2211063196709200_池田　拓未-原宿買取", "G2302250777990300_NGO THUY TRANG-原宿買取", "G06160415_関 智雅-原宿買取", "G06160788_永元 康平-原宿買取", "G06160654_铃木 達也-原宿買取", "G06160484_大櫛　良祐-原宿買取", "G2210080020402800_加藤　千博-原宿買取", "G2208164626322000_黒田　涼太-原宿買取", "G2208083627358600_平郡　泰司-原宿買取", "G06161244_迫田 大地-原宿買取", "G2212053362803200_DAO HUYNH ANH VY-原宿買取", "G2209188905472100_田中　功司-原宿買取", "G06160867_平木 康太郎-原宿買取", "G06161374_今池　学-原宿買取", "G2301139899161600_西村 珠馬-原宿買取", "G06160935_奥脇　正贵-原宿買取", "G2209293302139500_LI YIMING-原宿買取", "G06160346_山濑 俊也-原宿買取", "G2210169888666200_ZHU GUIHANG-原宿買取", "G06160652_堀越 智哉-原宿買取", "G2301121217412000_野口　大樹-原宿買取", "G06161135_木村　優介-原宿買取", "G2208225925561400_笹子　慎平-原宿買取", "G06161154_大方　敏-原宿買取", "G2210081449546500_DAO TUAN ANH-原宿買取", "G2209109122771900_黒崎　隼矢-原宿買取", "G06160324_李江俊-原宿買取", "G06161125_林　風子-原宿買取", "G2210151849011600_五十嵐　翔太-原宿買取", "G06160724_太田和 孝宏-原宿買取", "G2302084761241900_NGUYEN CONG HUNG-原宿買取", "G06160810_久保 雄司-原宿買取", "G2302091792181100_DINH THANH DAT-原宿買取", "G2209109045470600_山内　昌也-原宿買取", "G2209187231730100_小野　孝-原宿買取", "G06161046_宫沢 洋平-原宿買取", "G06161037_副島　幸佑-原宿買取", "G06161388_阿部 しょういち-原宿買取", "G06161378_茂吕 隆弘-原宿買取", "G2304091780741900_門田　莉久-原宿買取", "G2209178497387400_BUI QUANG PHUC SON-原宿買取", "G2209109865508700_藤田　秀太-原宿買取", "G06160207_古澤　晃史-原宿買取", "G2303120952241400_LE MINH TIEN-原宿買取", "G2210011824655800_蒋　勝華-原宿買取", "G06160177_佐藤　光悟-原宿買取", "G06160120_大橋　建太-原宿買取", "G2208199918615900_比嘉　美空-原宿買取", "G2210170176802800_清水　良明-原宿買取", "G2303129872054700_VO QUOC CUONG-原宿買取", "G06160761_黑澤　賢斗-原宿買取", "G2210302328360100_古屋 宽-原宿買取", "G06161074_齋藤　賢次-原宿買取", "G06160405_福西 佑亮-原宿買取", "G06161285_鲁德兵-原宿買取", "G2210194678559800_小川　晴也-原宿買取", "G2302067440614600_野口　直希-原宿買取", "G06161392_寺田　弘幸-原宿買取", "G2212265057350200_PHAM VAN BIEN-原宿買取", "G06160500_末廣　和也-原宿買取", "G2209030046134200_NGO QUOC THANH-原宿買取", "G06160083_南前　茂利-原宿買取", "G06161062_萩原　俊一郎-原宿買取", "G06160175_永江 海人-原宿買取", "G06160928_田巻　真宙-原宿買取", "G2304180727375600_森谷 朋樹-原宿買取", "G2210099232200700_BUI DUC DUONG-原宿買取", "G06160150_高橋　芳人-原宿買取", "G2209109345517500_細木　悠全-原宿買取", "G06161030_奥田 翔-原宿買取", "G2207262433601300_山本　晋太郎-原宿買取", "G06161430_奥山　祐佳-原宿買取", "G2209162590664800_榎本　廉-原宿買取", "G06160487_竹田 匡宏-原宿買取", "G2208156053137000_檜垣　大佑-原宿買取", "G2212177848477300_NGUYEN THI HONG NHUNG-原宿買取", "G2208154314783700_長田　勇人-原宿買取", "G06160121_姜 博元-原宿買取", "G2301078727636000_高田　雄世-原宿買取", "G2208101100512000_son cheolmin-原宿買取", "G2209127191749500_広瀬　翼-原宿買取", "G2303086744849600_長濵　駿太-原宿買取", "G06160575_福田　裕也-原宿買取", "G06160070_松岡　大輔-原宿買取", "G2211194407820200_杉崎　翼-原宿買取", "G06160932_大植　郁斗-原宿買取", "G06160084_秋元　孝康-原宿買取", "G06160806_中澤　美裕-原宿買取", "G06160032_小野澤　将人-原宿買取", "G06160973_廣瀬　吉総-原宿買取", "G2209284891113100_石海　勇次-原宿買取", "G06161005_小田 敬土-原宿買取", "G2209108865600800_TRAN NGOC THACH-原宿買取", "G2208206686750600_上池　知史-原宿買取", "G2301078165075300_保木本　尚輝-原宿買取", "G06160114_上本　祥人-原宿買取", "G2304233640151400_PHAM HONG HAM-原宿買取", "G2209081560165200_秋山　蒼天-原宿買取", "G06160829_新井　美華-原宿買取", "G2212069412782600_NGUYEN MINH HIEU-原宿買取", "G06161276_川上 和也-原宿買取", "G06160712_合澤　健二-原宿買取", "G06160553_門村　拓実-原宿買取", "G2303190053605500_VU TRUNG NAM-原宿買取", "G06161409_有馬 高志-原宿買取", "G2208206419336700_林　竜希-原宿買取", "G2207315033417700_柏崎　慈恩-原宿買取", "G2302234867880500_石田　礼也-原宿買取", "G2304146201627900_加藤　雄太-原宿買取", "G06160660_村上　皓乙-原宿買取", "G06160744_村田　裕次郎-原宿買取", "G06160940_朱磊-原宿買取", "G06161198_网上下单-原宿買取", "G2210125888741100_LE DANH HUY-原宿買取", "G2208190430064700_沖田　賢信-原宿買取", "G06160406_土亀　エテ-原宿買取", "G2304076033261300_TRAN DANG QUANG-原宿買取", "G2302068030543500_津谷  宗汰-原宿買取", "G2211291052444100_PHAN MINH NHAT-原宿買取", "G2210081419558200_久野　郎大-原宿買取", "G2209221843927300_清水　寛史-原宿買取", "G2302102353212100_amama（CM）-原宿CM", "G2211264550590300_BUI BAO KHOA-原宿買取", "G06161023_塩屋　可奈-原宿買取", "G2208242836997600_金子　翔一-原宿買取", "G2212097940092100_稲葉　聖-原宿買取", "G06161361_高田　淳平-原宿買取", "G2209223861634500_久松　晃-原宿買取", "G06161322_久保　宏大-原宿買取", "G06161021_相川 竜輝-原宿買取", "G2208278936827600_佐藤　太地-原宿買取", "G06160285_坂上 兼太郎-原宿買取", "G06161077_竹本 勇太-原宿買取", "G2208190005687500_森村　太渡-原宿買取", "G06161446_井関　　慶人-原宿買取", "G2208102489669600_亀山　優-原宿買取", "G06160618_鈴木　智之-原宿買取", "G06160591_大福 陽平-原宿買取", "G2211053327357200_伊東　丈大-原宿買取", "G06160662_我那覇翔-原宿買取", "G2211291591068700_DUONG DUC SON-原宿買取", "G2210107509837700_白藤　靖人-原宿買取", "G06160852_宫尾 和茂-原宿買取", "G06160011_atmos", "G06160707_佐藤 嘉洋-原宿買取", "G06160998_田村 智亮-原宿買取", "G2303113147492000_河野　翔-原宿買取", "G2209108672803200_藤田　貴之-原宿買取", "G2208172607232900_小林　由悠-原宿買取", "G2211291639155700_太田　昂希-原宿買取", "G06160832_山手 涉-原宿買取", "G06160152_NGUYEN THI XEN-原宿買取", "G2303251634262300_新貝　記明-原宿買取", "G2208278218780000_江原　祐弥-原宿買取", "G06160325_宫島 隆佳-原宿買取", "G2208172601410000_鈴木 太一-原宿買取", "G06161223_原島　大-原宿買取", "G06161195_須藤　幹夫-原宿買取", "G2301077329221500_助川　颯希-原宿買取", "G2209039069424200_遠藤　健太-原宿買取", "G06160727_佐佐木 悠太-原宿買取", "G2303139952785800_小玉慎太郎-原宿買取", "G06161051_大森 徹也-原宿買取", "G06160147_附田　雅樹-原宿買取", "G2209178656403700_TRAN VAN CHUC-原宿買取", "G06160723_铃木  渉-原宿買取", "G06160109_加島　海斗-原宿買取", "G06161442_川津　優-原宿買取", "G06160505_佐佐木 浩明-原宿買取", "G06160297_波多野　妙美-原宿買取", "G2301314328731700_松本　輝-原宿買取", "G2209277182626300_LIN XIAOHUI-原宿買取", "G06160930_麻田 孝明-原宿買取", "G2211203639977500_原　卓也-原宿買取", "G06161024_寺本 泰洋-原宿買取", "G2207236374595800_佐々木　数馬-原宿買取", "G06161404_大橋　征吾-原宿買取", "G06160139_川村　拓哉-原宿買取", "G2209048410918900_河野　優介-原宿買取", "G06160256_宮地　弘季-原宿買取", "G06160017_牛腸　和彦-原宿買取", "G06161130_辻　裕貴-原宿買取", "G06160538_吉野 淳-原宿買取", "G06160098_森　智美-原宿買取", "G2211193336265400_キンピオビジョナタン幸太-原宿買取", "G06161166_永田　大樹-原宿買取", "G2209109684356700_伊藤　凛玖-原宿買取", "G06161412_福田 佑也-原宿買取", "G06161445_矢野　裕大-原宿買取", "G06160879_米田　誠-原宿買取", "G06160396_篠崎　滉太-原宿買取", "G06160030_本園　晋作-原宿買取", "G2301104658757300_DAO ANH CHUONG-原宿買取", "G2302155506531700_TRAN THANH LOI-原宿買取", "G2210223217320000_関根　秀幸-原宿買取", "G06161238_大手-原宿買取", "G06161038_島田　俊典-原宿買取", "G06160898_山本 佑輔-原宿買取", "G06161349_岩田 雄一郎-原宿買取", "G06161471_戸塚　裕一-原宿買取", "G2303120181306200_VO HAI TUNG-原宿買取", "G06161368_橋本　了嗣-原宿買取", "G2208180884491700_刘鹏-原宿買取", "G06161271_金九 暢人-原宿買取", "G06160777_加藤　夏輝-原宿買取", "G06160570_西鳥羽　真まし-原宿買取", "G2208227050553500_高橋　一成-原宿買取", "G06160945_小松 学-原宿買取", "G2302217492005100_PHAM TUAN ANH-原宿買取", "G06160731_林直利-原宿買取", "G06161131_高頭　佳道-原宿買取", "G06161352_久保　英生-原宿買取", "G06160280_中村 祥雄-原宿買取", "G06160895_和田 紘樹-原宿買取", "G06160312_工藤 達也-原宿買取", "G2208172840506700_小笠原 健一-原宿買取", "G2211283008722100_巢山　晋平-原宿買取", "G2208233158971100_倉島 　大輔-原宿買取", "G06161456_村尾 真行-原宿買取", "G06160447_堀　亀王-原宿買取", "G06160854_田中 大将-原宿買取", "G2212053480244000_DINH SON DIEN-原宿買取", "G06161288_川野 豪-原宿買取", "G06160948_星野 智史-原宿買取", "G06161081_辻谷　卓巳-原宿買取", "G06160911_林 海斗-原宿買取", "G06160135_星川　祐介-原宿買取", "G2209214537806500_星野　明大-原宿買取", "G2210081396706100_NGUYEN NGOC QUYNH TIEN-原宿買取", "G06160136_江藤　聖-原宿買取", "G06161251_寿原 陽-原宿買取", "G06160786_日比野 瑞樹-原宿買取", "G06160751_送川 達也-原宿買取", "G06161307_中出 俊-原宿買取", "G2210259167508900_高井　優輔-原宿買取", "G06160609_桜井　僚-原宿買取", "G06160187_石田　大毅-原宿買取", "G06160549_横尾 祥太郎-原宿買取", "G2209081190674000_山本　崇喜-原宿買取", "G06161369_冨山　裕章-原宿買取", "G2303157713657300_NGUYEN XUAN THANG-原宿買取", "G2209232536591000_秋元　雅宇-原宿買取", "G2302137931968500_平田　脩太-原宿買取", "G06160143_永井　博之-原宿買取", "G2211131403497800_HOANG  VAN HIEU-原宿買取", "G2302234812109600_曽木　佑太-原宿買取", "G06160730_永井 隆太郎-原宿買取", "G06160942_上田 尚志-原宿買取", "G06161090_有泉　隆司-原宿買取", "G06161474_日野 陸斗-原宿買取", "G06160711_長ナマ　朋也-原宿買取", "G06160863_四之宫 慎一-原宿買取", "G06160696_四元 清大-原宿買取", "G2208288582702000_修正-原宿修正", "G2207297900022100_根岸　一真-原宿買取", "G06161440_佐藤　亮太-原宿買取", "G2209223629006100_染谷　優太-原宿買取", "G06160914_後藤　裕紀-原宿買取", "G06160923_大和内 昭哉-原宿買取", "G06161170_川部　勇樹-原宿買取", "G06160897_木村 涼介-原宿買取", "G06160395_原屋敷 良子-原宿買取", "G06160131_田邉　修也-原宿買取", "G2211018893222400_PHAM THANG TRUNG-原宿買取", "G2303191567594000_漆澤　隆二-原宿買取", "G2304066746246600_NGUYEN VAN NGUYEN-原宿買取", "G06160055_寺田　博人-原宿買取", "G06160494_斎藤　晃太郎-原宿買取", "G2208075290590400_稻田 匠-原宿買取", "G06160503_宮田　貴浩-原宿買取", "G06160807_飛田　悠貴-原宿買取", "G2209283662938100_志村　直樹-原宿買取", "G06160714_東野　利香-原宿買取", "G06161284_岡野　瞬や-原宿買取", "G06160610_藤永　日向-原宿買取", "G06160024_伊藤　直也-原宿買取", "G2209127917552200_原 健太-原宿買取", "G06160804_高崎 海樹-原宿買取", "G2304189610650500_山下 征納-原宿買取", "G06160784_石田　祐樹-原宿買取", "G2211089681654500_村岡　史隆-原宿買取", "G06161327_大木 賢太-原宿買取", "G2209056528009100_入川 嘉彦-原宿買取", "G06160475_萬福　大介-原宿買取", "G2209187259421600_志岐 怜音-原宿買取", "G06160141_伊藤　秀平-原宿買取", "G2210081254255300_NGUYEN QUYNH NGA-原宿買取", "G06160878_今井 誠二-原宿買取", "G06160370_阳光整体-原宿買取", "G06161190_川原　茉莉-原宿買取", "G06161126_興那 大樹-原宿買取", "G06161333_森 聡志-原宿買取", "G06161414_佐藤 大悟-原宿買取", "G06160795_瀬島　真波-原宿買取", "G06160864_永田 真也-原宿買取", "G06160539_田辺　浩基-原宿買取", "G06160738_内野 アンデレ-原宿買取", "G2211131907673700_NGUYEN VAN YEN-原宿買取", "G06160464_若狭　心-原宿買取", "G06161301_大滝　正樹-原宿買取", "G06160648_富田 進吾-原宿買取", "G2210045927256600_TRAN XUAN KIEN-原宿買取", "G2208305025448800_伴　大輝-原宿買取", "G06161415_橋本　理沙-原宿買取", "G06161340_山口 裕毅-原宿買取", "G06160311_丸山 亮一-原宿買取", "G2210160336362000_松原　大和-原宿買取", "G06161475_野口 宏行-原宿買取", "G2209109162844400_矢ヶ部　知介-原宿買取", "G2210045815956600_TRAN CHINH TRIEU-原宿買取", "G06161035_藤川 晋輔-原宿買取", "G2303112927096200_HOANG ANH TUAN-原宿買取", "G06160148_青木 望-原宿買取", "G2210133198396400_加藤　大貴-原宿買取", "G06160181_庄野　雄二郎-原宿買取", "G06160623_西川　竜馬-原宿買取", "G06160050_高木　寛斗-原宿買取", "G06161348_長浜　結華-原宿買取", "G06160672_宮地　健之輔-原宿買取", "G2206263671269400_NGUYEN VAN KHANH-原宿買取", "G06160203_池田 凯-原宿買取", "G2208172902195000_大江 稔-原宿買取", "G06161119_木本　康一-原宿買取", "G06160637_西谷　完太-原宿買取", "G06161115_渡辺　眞佐子-原宿買取", "G2208171489659200_BUI QUOC HUNG-原宿買取", "G2301077976510800_泰野　拓也-原宿買取", "G06160516_岩川 嘉雅-原宿買取", "G06160982_中澤 徹-原宿買取", "G06161008_小坂部 隆寛-原宿買取", "G2208075095180200_木本 翔太-原宿買取", "G06161155_河合　義広-原宿買取", "G2211051778399600_今井　麻央-原宿買取", "G06160289_小泉 栄-原宿買取", "G2208206750064100_村松　雅貴-原宿買取", "G06160760_G2-池袋大货", "G2208173124436500_柏木　翔太-原宿買取", "G06160068_高木　宏和-原宿買取", "G2301227939781500_BAIT-原宿店购", "G06160165_大坪　優希-原宿買取", "G2209178592752700_TRAN VAN QUAN-原宿買取", "G2303025504383000_安達　大裕-原宿買取", "G2208206634751400_有路　將司-原宿買取", "G06161129_末永　優友-原宿買取", "G2209107858653700_髙橋　博英-原宿買取", "G2209010508714900_野邉　将広-原宿買取", "G06161266_田代秀明-原宿買取", "G2209065776893900_細矢　政寛-原宿買取", "G06160519_行村 勇斗-原宿買取", "G2209109935341000_TRAN　VAN　HUAN-原宿買取", "G06160459_付玉琴-原宿買取", "G2303095080319200_PHUNG NGOC TUAN-原宿買取", "G06160615_磯　達希-原宿買取", "G06160658_古賀 昇-原宿買取", "G06161371_小畠-原宿買取", "G06161117_高良　潤-原宿買取", "G06161112_行木　稔幸-原宿買取", "G2208154352516300_大谷　翔悟-原宿買取", "G06160573_三浦 拓夢-原宿買取", "G2304031840084000_ZHANG JIALIANG-原宿買取", "G06160589_豊岡 道也-原宿買取", "G2303227489589800_鍬田　健斗-原宿買取", "G06161164_西澤　亮-原宿買取", "G2302110895077100_ZHAO YINGLUO-原宿買取", "G2209038965062200_阿部　航平-原宿買取", "G06160193_川邉 真吾-原宿買取", "G06161311_松永 龍二-原宿買取", "G2301227560578400_NGUYEN HA MY-原宿買取", "G2208190060419300_寿々木　樹-原宿買取", "G2209160956331900_小川　啓吾-原宿買取", "G2208235097908500_加藤　優樹-原宿買取", "G06160694_金欣宇-原宿買取", "G06160586_渡边 幸信-原宿買取", "G06161427_山城　翔-原宿買取", "G06160952_奥原 保彦-原宿買取", "G06161263_大田 聖-原宿買取", "G06161464_尾上　紗和子-原宿買取", "G06160826_久保田　珠央-原宿買取", "G2212193263935600_森位　悠真-原宿買取", "G06160931_斎藤　昂太-原宿買取", "G06160446_奥田 亜纪-原宿買取", "G2208304287563800_渡辺　大翔-原宿買取", "G06160046_植木　希-原宿買取", "G06160585_物部哲也-原宿買取", "G2209267332695200_伊能　一穗-原宿買取", "G06160226_森崎　健太郎-原宿買取", "G2208277683493600_有賀　庸介-原宿買取", "G2208171201215900_ZHAO BEIXIAN-原宿買取", "G2208243086667100_佐藤　貴満-原宿買取", "G2211159400384300_SD-原宿一楼", "G2208207095455000_NGUYEN VAN THINH-原宿買取", "G2302190190199200_PHAN DINH DUC-原宿買取", "G06160499_山口 真-原宿買取", "G2210204784806000_須和　恒太-原宿買取", "G06160471_出浦 志羽-原宿買取", "G2208209196800800_新田　虎太郎-原宿買取", "G06160262_村田 可朗-原宿買取", "G2210169745976700_菅谷　賢史-原宿買取", "G06160764_鳥居　卓也-原宿買取", "G06161145_辻野　律-原宿買取", "G06160746_鮎澤 耕太-原宿買取", "G2302199749907900_石垣　淳-原宿買取", "G06160410_吉田　周平-原宿買取", "G2302023312675200_森　一央-原宿買取", "G06160008_富岡　亮佑-原宿買取", "G2302252217531800_WANG  YANLI-原宿買取", "G2208172237203700_LE MINH THAI-原宿買取", "G06160052_水越　祐太-原宿買取", "G2209268183515100_小堀　真理亜-原宿買取", "G06161124_猿川　稜悟-原宿買取", "G2301164097863700_高橋　空良-原宿買取", "G2301041945800700_青島　光弥-原宿買取", "G06160683_西野　健太-原宿買取", "G2208286347890700_石戸　柊-原宿買取", "G2209020606939700_樋口　育生-原宿買取", "G06160039_二瓶　裕章-原宿買取", "G06160736_高木　雅由-原宿買取", "G2208172194032900_山本 晋太郎-原宿買取", "G06161381_田中　良太-原宿買取", "G2208242415400000_葉 聖-原宿買取", "G06160455_神山 英亮-原宿買取", "G06160920_保原 正啓-原宿買取", "G2304161791899000_NGUYEN CAO PHONG-原宿買取", "G2207289888489400_大野　雅明-原宿買取", "G2209222993814500_PHAM MINH THINH-原宿買取", "G06161147_梶原　遼太郎-原宿買取", "G06160792_大久保 邦昭-原宿買取", "G06160565_佐藤　正-原宿買取", "G06161452_猿川　晃平-原宿買取", "G2212035330942900_NGUYEN DAC TUONG-原宿買取", "G06161157_徳田　薫-原宿買取", "G06160985_田中 伸-原宿買取", "G06160391_大村  優記-原宿買取", "G06160275_岸本 美穗-原宿買取", "G2305163105184700_ATMOS-原宿店购", "G06160373_見米 一馬-原宿買取", "G06161017_廣田　巧-原宿買取", "G06160043_高橋　博英-原宿買取", "G2208207099289400_GEIGULUGEQI-原宿買取", "G2212291482760400_高井　良則-原宿買取", "G06161100_北村　克利-原宿買取", "G06161331_池内太-原宿買取", "G06160526_稲葉　愛実-原宿買取", "G06160088_木立 皓也-原宿買取", "G2301095267080100_岡本　浩辉-原宿買取", "G06160166_嶌村　俊介-原宿買取", "G06160192_金子 伸彦-原宿買取", "G06161159_三上　英之-原宿買取", "G06160592_陣ヶ尾 雅人-原宿買取", "G06160277_斉藤　秀男-原宿買取", "G06160758_白松 大典-原宿買取", "G06160947_松山 拳大-原宿買取", "G06161178_磯田　武蔵-原宿買取", "G2302288308140200_小川　駈-原宿買取", "G2302181996497800_NGUYEN NAM-原宿買取", "G2211132084409900_PHAM NGUYEN ANH TUAN-原宿買取", "G06161287_伴野　託斗-原宿買取", "G2209232322417100_岡本　凌-原宿買取", "G2303261223466300_PHAM THU UYEN-原宿買取", "G06160132_早瀬　怜弥-原宿買取", "G06160949_石川 寛章-原宿買取", "G2303069467470600_TRUONG LE HONG KHA-原宿買取", "G06160649_小妻　竜太-原宿買取", "G06160908_山本　真也-原宿買取", "G06160824_千家 康弘-原宿買取", "G2208171443334100_工藤　琉聖-原宿買取", "G06160666_菅谷　崇裕-原宿買取", "G2209047975942900_薄田　優空-原宿買取", "G06161068_澁谷　知寛-原宿買取", "G06160345_久保 和樹-原宿買取", "G2208067416983000_岡部-原宿買取", "G2302128878743100_戴聡-原宿買取", "G2210293306225700_王子晴-原宿買取", "G06160227_北村　直道-原宿買取", "G2207191636997800_島津 直弥-原宿買取", "G2210028294616200_NGUYEN MANH TIEN-原宿買取", "G2208119649876100_佐々木　洋平-原宿買取", "G06161269_鈴木　　健-原宿買取", "G06160080_畠山　亮平-原宿買取", "G2211272193404400_樹下　資範-原宿買取", "G2304259001564400_TRAN THI THUY-原宿買取", "G06160793_菊地　敦也-原宿買取", "G06160307_CHOI JUNG HOON-原宿買取", "G2210134324905700_新井　陸-原宿買取", "G2208172865580800_野澤　大樹-原宿買取", "G2211026935099800_佐々木　楓-原宿買取", "G06160961_坂本 裕室-原宿買取", "G06161438_竹内　悠貴-原宿買取", "G06161007_矢島　健次-原宿買取", "G06160190_池田　有梨沙-原宿買取", "G06161057_藤本　恭有-原宿買取", "G06160962_PARK JAEHYUN-原宿買取", "G06160979_大六野　正利-原宿買取", "G2302023276632000_TRUONG VAN HUNG-原宿買取", "G06160316_寺内 弘幸-原宿買取", "G2303022721098300_大熊（ABC）-大熊（ABC）", "G06160836_川津　悠-原宿買取", "G2210276305143900_藤田　雄大-原宿買取", "G2210267737445500_NGUYEN DINH PHUC-原宿買取", "G2209038726493800_DO VIET HOANG-原宿買取", "G2210170099367300_安井　京太郎-原宿買取", "G2209267362363300_NGUYEN ANH TU-原宿買取", "G06160054_大塚　雄貴-原宿買取", "G06161309_陣ケ尾　雅人-原宿買取", "G06161450_吉崎　彰恒-原宿買取", "G2304091741308500_TRAN QUANG HOA-原宿買取", "G2208260884850500_undefeated-原宿店购", "G06160482_希田 一也-原宿買取", "G2303191017976500_DONG VAN TOAN-原宿買取", "G2208226794586500_前田　大輝-原宿買取", "G06160569_今井　詳貴-原宿買取", "G2210223388698200_HUYNH LONG CIN-原宿買取", "G06160006_石川 雅敏-原宿買取", "G06161441_増永　寿郁-原宿買取", "G06160802_福士 芳朗-原宿買取", "G06160341_和井 内輝-原宿買取", "G2210099668278100_長谷川　照久-原宿買取", "G06160252_安藤　寛朗-原宿買取", "G2209109341305000_折戸　友助-原宿買取", "G2302084381137500_萬谷　直大-原宿買取", "G2211131985313900_LE THI KIEU OANH-原宿買取", "G06160617_藤井　理気-原宿買取", "G2212220669301700_CHEN  YAXIU-原宿買取", "G2304181107521600_福山　拓真-原宿買取", "G2208225043201700_橋本　幸太-原宿買取", "G06160305_松田　瑛子-原宿買取", "G06160512_吉本 觉-原宿買取", "G2209249327517900_近藤　祐未-原宿買取", "G06160095_瀧本　純-原宿買取", "G06160677_高桥 一成-原宿買取", "G06160530_若林 翔-原宿買取", "G2209038403145300_佐藤　匠-原宿買取", "G06161399_冠野 裕大-原宿買取", "G2208093325195200_村岡　敬佑-原宿買取", "G2208172856218400_PHAN HUU PHUOC-原宿買取", "G06160300_榮谷　郁弥-原宿買取", "G2209240588286600_戸田　航太-原宿買取", "G2208242465707600_米津　天-原宿買取", "G06160604_金子 司-原宿買取", "G06160380_LUCKHAM PAKAPOL-原宿買取", "G06160036_今井　星哉-原宿買取", "G2303032273126200_惠飛須　凌-原宿買取", "G06160429_小澤　有斐-原宿買取", "G06161299_山田 しょうじ-原宿買取", "G2303050863651300_PHAN HOANG QUAN-原宿買取", "G2208286147862100_野口　泰道-原宿買取", "G2303218649893200_NGUYEN SONG NGUYEN-原宿買取", "G2304217579360900_林 亮太-原宿買取", "G2208092056096200_梶谷　雅史-原宿買取", "G06160705_岡村 泰良-原宿買取", "G06160590_石田 宏稀-原宿買取", "G06160087_坂本　龍哉-原宿買取", "G2208172830697100_TRAN VAN SU-原宿買取", "G06160599_浅原　巧-原宿買取", "G06160653_田岛 礼音-原宿買取", "G06161105_小林　圭子-原宿買取", "G2303078059726700_星優　輝人-原宿買取", "G06161141_寺田　桐人-原宿買取", "G06160142_方 日升-原宿買取", "G06160402_畠山　貴行-原宿買取", "G2301174713137100_大塚　亜連-原宿買取", "G2208215826470200_内田　光-原宿買取", "G06161045_久保よしたか-原宿買取", "G2212124512493200_NGUYEN DINH THUONG-原宿買取", "G2302127915558100_LE VAN DUNG-原宿買取", "G2212273227641500_PHAN MINH KHANG-原宿買取", "G2209012768054500_PHAM　VAN　CUONG-原宿買取", "G2208171049571500_田代　雅彦-原宿買取", "G2207306297684600_皆川　直樹-原宿買取", "G2301096139451300_NGUYEN TUAN DAT-原宿買取", "G2208171003904600_PHAN THI HUONG-原宿買取", "G06160389_鮎澤　耕太-原宿買取", "G06160265_中島　功人-原宿買取", "G06160972_関口　繁-原宿買取", "G06161454_長山　元洋-原宿買取", "G06161107_辻本　栄-原宿買取", "G2209048167267600_岩田　維久磨-原宿買取", "G06160818_横山　裕也-原宿買取", "G2303181517670900_平塚　匠-原宿買取", "G2210267996117800_広瀬　勝也-原宿買取", "G2209039593358900_秋山　克仁-原宿買取", "G06160304_谷口 莲-原宿買取", "G06160247_伊藤　拓海-原宿買取", "G2304083689992800_市川　涼-原宿買取", "G2207245779211700_荒牧　慶亮-原宿買取", "G2301262694016400_NGUYENVAN VIET-原宿買取", "G06160820_神田　悠多-原宿買取", "G06160501_渋谷　晃司-原宿買取", "G2212220656303100_竹石　弘平-原宿買取", "G06160182_塚本　峻也-原宿買取", "G2208172892907200_大瀬 良智行-原宿買取", "G2208225447683800_池本 優樹-原宿買取", "G06160881_織田　和哉-原宿買取", "G2207279243503900_谷津　陽介-原宿買取", "G06160830_山中　政代-原宿買取", "G06160201_小林　匠-原宿買取", "G06161075_铃木 桂輔-原宿買取", "G06160090_佐々木　達也-原宿買取", "G06161240_藤木 祥真-原宿買取", "G2303129822694300_PHAM ANH  DUNG-原宿買取", "G06160855_奥野　陽介-原宿買取", "G06161376_黒崎　路睦-原宿買取", "G06161315_遠山　洋中-原宿買取", "G2211096920805100_NGUYEN WAN DUY-原宿買取", "G06161018_平岩 憲一郎-原宿買取", "G2301262642480300_長谷川　誠-原宿買取", "G2211281812798800_森谷　淳史-原宿買取", "G06160921_石桥 佳秀-原宿買取", "G06161015_平松 和磨-原宿買取", "G06161113_北橋　花織-原宿買取", "G2207315943542700_高橋　佑輔-原宿買取", "G06160957_梅林 将大-原宿買取", "G2208170926071700_上村　勇介-原宿買取", "G06161204_増田　良哉-原宿買取", "G06160525_廣野　達行-原宿買取", "G06160354_佐土原　亘-原宿買取", "G2302092824370200_横山　誉幸-原宿買取", "G2208172942305300_湯川　冬悟-原宿買取", "G06161089_森　光洋-原宿買取", "G06160814_塩田将己-原宿買取", "G06160831_森本　颯-原宿買取", "G2211271992567200_NGUYEN VAN GIAP-原宿買取", "G2211291776609400_NGUYEN THI KIM OANH-原宿買取", "G06160486_横田　竜-原宿買取", "G06161275_川俣　美由紀-原宿買取", "G06161257_林 括海-原宿買取", "G2210081530233100_HUYNH MINH KHAI-原宿買取", "G06161461_佐久間　大夢-原宿買取", "G06161229_青木-原宿買取", "G06160976_山田 怜-原宿買取", "G06161482_久保 智彦-原宿買取", "G2211177335816000_MAI HUY HOANG-原宿買取", "G2208209412130000_橋口　昌央-原宿買取", "G06160056_武田　厚-原宿買取", "G06160355_山本　聖太-原宿買取", "G2301261742813800_翠　優介-原宿買取", "G2208156130559600_カ　コシ-原宿買取", "G06160844_青木　俊樹-原宿買取", "G06160409_林嘉誠-原宿買取", "G2211098233308100_VU　MINH  TU-原宿買取", "G06160891_小原 康一-原宿買取", "G06161158_佐々木　幸太-原宿買取", "G2208172812320200_中西 陽輔-原宿買取", "G2209100275653300_依田　顕作-原宿買取", "G06160042_松浦　彩乃-原宿買取", "G06160720_柴田 佳那-原宿買取", "G06160622_岩渕　勝-原宿買取", "G2209214502606100_榎本　大樹-原宿買取", "G2207242925041800_畦上　謙生-原宿買取", "G06160567_土屋　凱-原宿買取", "G06160092_出井　克彦-原宿買取", "G06160034_島津　佐和子-原宿買取", "G2209039183132000_武石　朋也-原宿買取", "G06160107_草留 芹加-原宿買取", "G06160856_古林 明雄-原宿買取", "G2302110789299100_NGUYEN HAI LONG-原宿買取", "G2301078708145000_加藤　嵐-原宿買取", "G06160566_柴田　風磨-原宿買取", "G2305074561323700_NGUYEN TRUC LINH-原宿買取", "G06161098_渡辺　健太郎-原宿買取", "G2208110739585700_内藤　琢也-原宿買取", "G2207254603747100_北本　嵩太郎-原宿買取", "G2209274736067000_増田　哲知-原宿買取", "G06161428_井口　基水-原宿買取", "G06160156_待续-原宿買取", "G06160938_中島 凌佑-原宿買取", "G06160371_谭-原宿買取", "G06160122_无味　建一-原宿買取", "G06160607_磯谷　亮平-原宿買取", "G2212194833208200_木村　勇祐-原宿買取", "G06160903_PEREZ NOLI FERNAN JR-原宿買取", "G06160953_手嶋　和貴-原宿買取", "G06160200_白井 敦史-原宿買取", "G06160827_池田 和希-原宿買取", "G06160328_田中 雅也-原宿買取", "G06160336_铃木 淳-原宿買取", "G2208313113699400_池田　蒼-原宿買取", "G2208279153489600_谷村　大輔-原宿買取", "G06160960_宫地 健之輔-原宿買取", "G06160064_ZOZO-池袋网购", "G2301306721306900_金本　政勝-原宿買取", "G06160238_水谷　俊介-原宿買取", "G2210311046160100_劉海涛-原宿買取", "G06160298_倉中　誠司-原宿買取", "G06161027_四元　誠大-原宿買取", "G2208226681542300_LIU　JIANGSHUAI-原宿買取", "G06160033_伊藤　弘樹-原宿買取", "G06160397_川口 良平-原宿買取", "G2303261139640600_VU QUANG VIET-原宿買取", "G06160630_小潘 贵章-原宿買取", "G06161321_杉山　幸正", "G06160636_中村 祥雄", "G2208277715245200_GONG　NAN-原宿買取", "G06160011_atmos", "G2305163105184700_ATMOS-原宿店购"];
        return $arr;
    }

    function updateSupName()
    {
        // 名字有问题的供应商
        $arr = $this->_supArr();
        dump('更新供应商名称....');
        foreach ($arr as $item) {
            dump($item);
            list($sup_code, $name) = explode('_', $item);
            $arr[] = $sup_code;
            Supplier::where(['sup_code' => $sup_code])->where('name', '<>', $name)->update(['name' => $name]);
        }
        // dd(123);
        $sups = Supplier::where('tenant_id', '489274')->select(['id', 'sup_code', 'name'])->get()->keyBy('sup_code');
        $arr2 = [];
        $del = [];
        $list = DB::select("SELECT `name`,GROUP_CONCAT(sup_code) as codes,COUNT(id) as num FROM wms_supplier WHERE tenant_id=489274 GROUP BY `name` HAVING num>1");
        $right_arr = $this->_rightArr();
        foreach ($list as $item) {
            $tmp = explode(',', $item->codes);
            dump($tmp);
            $right = 0;
            $wrong = [];
            foreach ($tmp as $code) {
                dump($code);
                if (in_array($code, $right_arr)) {
                    $right = $sups[$code]['id'];
                } else {
                    $wrong[] = $sups[$code]['id'];
                }
            }
            $del = array_merge($del, $wrong);
            foreach ($wrong as $item) {
                $arr2[] = ['old' => $right, 'new' => $item];
            }
            dump($wrong);
        }
        dump('替换供应商id....');
        // $tmp = [
        //     ['old' => 1449, 'new' => 6202, 'name' => '修正-原宿修正'],
        //     ['old' => 1032, 'new' => 6195, 'name' => 'NGUYEN THANH QUAN-原宿買取'],
        //     ['old' => 3165, 'new' => 6193, 'name' => 'ABC-池袋网购'],
        // ];
        $tmp = [
            ['new' => 3170, 'old' => 6221],
            ['new' => 648, 'old' => 217],
            ['new' => 1462, 'old' => 555],
            ['new' => 6167, 'old' => 555],
            ['new' => 6170, 'old' => 431],
        ];
        $arr2 = array_merge($arr2, $tmp);
        self::log('被替换的供应商',$arr2);
        foreach ($arr2 as $item) {
            $this->_supUpdate($item);
        }
        dump('删除多余的供应商');
        Supplier::whereIn('id', $del)->delete();
        self::log('被删除的供应商',$del);
        $msg = "删除的id1:" . implode(',', $del);
        Robot::sendNotice($msg);

        $arr4 = $this->_modifyName();
        foreach ($arr4 as $item) {
            Supplier::where('sup_code', $item[0])->update(['name' => $item[1]]);
        }


        // 删除名称重复的id
        $arr6 = [];
        $del = [];
        $sups = Supplier::where('tenant_id', '489274')->selectRaw("id,sup_code,`name`,sup_status,`status`")->get()->keyBy('sup_code');
        $list = DB::select("SELECT `name`,GROUP_CONCAT(sup_code) as codes,COUNT(id) as num,GROUP_CONCAT(id) as ids FROM wms_supplier WHERE tenant_id=489274 AND `status`=1 AND sup_status=2 GROUP BY `name` HAVING num>1");
        foreach ($list as $item) {
            $codes = explode(',', $item->codes);
            $right = 0;
            foreach ($codes as $code) {
                $tmp = $sups[$code];
                // 如果有大空格的名称，保留
                if (strpos(json_encode($tmp->name), '\u3000') !== false) {
                    $right = $tmp->id;
                }
            }
            $ids = explode(',', $item->ids);
            if (!$right) $right = min($ids);
            if (!$right) continue;

            foreach ($ids as $id) {
                if ($id != $right) {
                    $del[] = $id;
                    $arr6[] = ['old' => $right, 'new' => $id, 'name' => $item->name];
                }
            }
        }

        self::log('被替换的供应商',$arr6);
        // dd($arr6);
        foreach ($arr6 as $item) {
            $this->_supUpdate($item);
        }

        dump('删除多余的供应商');
        Supplier::whereIn('id', $del)->delete();
        self::log('被删除的供应商',$del);
        $msg = "删除的id2:" . implode(',', $del);
        Robot::sendNotice($msg);

        $this->syncSupInv();
    }

    function _modifyName()
    {
        return [["G2305162324876100", "ABC-原宿店舗購入"], ["G2305137353850000", "ABC-原宿ネット購入"], ["G2305198181566400", "ADIDAS-原宿店舗購入"], ["G2307318288864900", "ARROWS-原宿ネット購入"], ["G2402156697371600", "ASICS-原宿店舗購入"], ["G2309297958511800", "askate-原宿店舗購入"], ["G2306240184079200", "ATMOSPINK-原宿店舗購入"], ["G2307213026906700", "atmos-原宿ネット購入"], ["G2306062987305300", "BEAMS-原宿店舗購入"], ["G2404244960716600", "CHROMEHEARTS-原宿店舗購入"], ["G2405205982049291", "DAYZ-原宿店舗購入"], ["G2307071806644000", "emmi-原宿ネット購入"], ["G2308088763869300", "Hbeautyandyouth-原宿店舗購入"], ["G2403050288492400", "homegame-原宿店舗購入"], ["G2307318338292800", "Kemari87-原宿ネット購入"], ["G2305162321318800", "KITH-原宿店舗購入"], ["G2403106445101700", "LETUANNGHIA-原宿買取"], ["G2308026823145700", "MAGIKICKS-原宿店舗購入"], ["G2307275156530500", "matsuspo-原宿ネット購入"], ["G2306231100307000", "MEDICOMTOYPLUS-原宿店舗購入"], ["G2305162378370700", "NEWBALANCE-原宿店舗購入"], ["G2403264769429700", "PALACE-原宿店舗購入"], ["G2306248121766600", "RINKANLAB-原宿店舗購入"], ["G2210274521075400", "RINKAN渋谷店-原宿店舗購入"], ["G06161478", "RKM-原宿店舗購入"], ["G2305162335906700", "SALOMON-原宿店舗購入"], ["G2306143468667100", "SNS-原宿店舗購入"], ["G2306231342515200", "SPOPIA-原宿ネット購入"], ["G2305207542546800", "STUSSY-原宿店舗購入"]];
    }

    // 更新各表中的供应商id
    private function _supUpdate($item)
    {
        dump($item);
        $old_id = $item['old'];
        $new_id = $item['new'];
        $name = $item['name'] ?? '';
        dump('Consignment');
        Consignment::where(['sup_id' => $new_id])->update(['sup_id' => $old_id]);
        dump('WmsConsigmentSettlement');
        WmsConsigmentSettlement::where(['sup_id' => $new_id])->update(['sup_id' => $old_id]);
        dump('IbDetail');
        IbDetail::where(['sup_id' => $new_id])->update(['sup_id' => $old_id]);
        dump('IbOrder');
        IbOrder::where(['sup_id' => $new_id])->update(['sup_id' => $old_id]);
        dump('Inventory');
        Inventory::where(['sup_id' => $new_id])->update(['sup_id' => $old_id]);
        dump('WmsOrderDeliverStatement');
        WmsOrderDeliverStatement::where(['sup_id' => $new_id])->update(['sup_id' => $old_id]);
        dump('WmsOrderDetail');
        WmsOrderDetail::where(['sup_id' => $new_id])->update(['sup_id' => $old_id]);
        dump('WmsOrderItem');
        WmsOrderItem::where(['sup_id' => $new_id])->update(['sup_id' => $old_id]);
        dump('OIbDetails');
        OIbDetails::where(['sup_id' => $new_id])->update(['sup_id' => $old_id]);
        dump('OObDetails');
        OObDetails::where(['sup_id' => $new_id])->update(['sup_id' => $old_id]);
        dump('preAllocationDetail');
        preAllocationDetail::where(['sup_id' => $new_id])->update(['sup_id' => $old_id]);
        dump('WmsProductStockLog');
        $max = WmsProductStockLog::max('id');
        $begin = 0;
        while ($begin <= $max) {
            dump($begin);
            $end = $begin + 10000;
            WmsProductStockLog::where(['sup_id' => $new_id])->where('id', '>', $begin)->where('id', '<', $end)->update(['sup_id' => $old_id]);
            $begin = $end;
        }
        dump('PurchaseStatements');
        PurchaseStatements::where(['sup_id' => $new_id])->update(['sup_id' => $old_id]);
        dump('PurchaseOrders');
        PurchaseOrders::where(['sup_id' => $new_id])->update(['sup_id' => $old_id]);
        dump('WmsPurchaseStatement');
        WmsPurchaseStatement::where(['sup_id' => $new_id])->update(['sup_id' => $old_id]);
        dump('RecvDetail');
        RecvDetail::where(['sup_id' => $new_id])->update(['sup_id' => $old_id]);
        dump('ShippingDetail');
        ShippingDetail::where(['sup_id' => $new_id])->update(['sup_id' => $old_id]);
        dump('WmsStockCheckDifference');
        WmsStockCheckDifference::where(['sup_id' => $new_id])->update(['sup_id' => $old_id]);
        dump('WmsStockLog');
        $max = WmsStockLog::max('id');
        $begin = 0;
        while ($begin <= $max) {
            dump($begin);
            $end = $begin + 10000;
            WmsStockLog::where(['sup_id' => $new_id])->where('id', '>', $begin)->where('id', '<', $end)->update(['sup_id' => $old_id]);
            $begin = $end;
        }
        dump('WmsStockMoveItem');
        WmsStockMoveItem::where(['sup_id' => $new_id])->update(['sup_id' => $old_id]);
        // dump('SupInv');
        // SupInv::where(['sup_id' => $new_id])->update(['sup_id' => $old_id, 'sup_name' => $name]);
        dump('WmsWithdrawRequest');
        WmsWithdrawRequest::where(['sup_id' => $new_id])->update(['sup_id' => $old_id, 'sup_name' => $name]);
        dump('WithdrawUniqLog');
        WithdrawUniqLog::where(['sup_id' => $new_id])->update(['sup_id' => $old_id]);
    }
    // 供应商去重时要保留的code
    private function _rightArr()
    {
        return ["G2405209661443400", "G2405191181173200", "G2405190570217400", "G2405145762977300", "G2405139235255000", "G2405138820792100", "G2405138679964600", "G2405138618468600", "G2405138540521200", "G2405137751759300", "G2405136709419100", "G2405102523843300", "G2405094595073300", "G2405094565095000", "G2405092945411500", "G2405086436488500", "G2405086282204900", "G2405086046335100", "G2405077669033400", "G2405075854749900", "G2405065926038900", "G2405050327265200", "G2405059972263100", "G2405033083810100", "G2405033067931600", "G2405033047659800", "G2405032001057100", "G2405024147242500", "G2405013358391700", "G2405013227006700", "G2404305407962500", "G2404304448736300", "G2404304349203400", "G2404304212666300", "G2404303941316300", "G2404303914115300", "G2404270528747500", "G2404253429604200", "G2404244960716600", "G2404236399286200", "G2404233687067900", "G2404227918425600", "G2404227904997300", "G2404227494916100", "G2404225398184800", "G2404217861624000", "G2404209390413200", "G2404209350956600", "G2404191144089400", "G2404181300595800", "G2404180793484700", "G2404173989774400", "G2404172145391400", "G2404138611264800", "G2404128994158600", "G2404103555111300", "G2404101715458200", "G2404086206127800", "G2404084810872600", "G2404068624438300", "G2404051245619000", "G2404032681177800", "G2404016691804200", "G2403298252685300", "G2403270798843200", "G2403270295600800", "G2403264769429700", "G2403262871539800", "G2403253305814100", "G2403238270944200", "G2403229915182800", "G2403229813661700", "G2403227461293700", "G2403211187112800", "G2403203096392500", "G2403202804839100", "G2403192066528800", "G2403184906503800", "G2403184788489700", "G2403183744715200", "G2403183051725500", "G2403167229435500", "G2403131900703800", "G2403122992853900", "G2403106460762300", "G2403106456345200", "G2403106447258600", "G2403106445101700", "G2403106153101800", "G2403104668931500", "G2403089212850200", "G2403060222723300", "G2403050288492400", "G2403044518473500", "G2403044474475700", "G2403044350188500", "G2403043128432500", "G2403043058624700", "G2403041926526600", "G2403041561689900", "G2403027819117700", "G2403027524093900", "G2403025679793000", "G2403018629815300", "G2403018505992400", "G2403018488461300", "G2403017742359700", "G2402298133090600", "G2402281158353400", "G2402281152824800", "G2402281148496700", "G2402281143644200", "G2402281137936200", "G2402281107616600", "G2402281101551400", "G2402281054817500", "G2402281002938400", "G2402280996659800", "G2402280990941200", "G2402280317884500", "G2402272440407500", "G2402271189725900", "G2402264462206700", "G2402262247578800", "G2402254976358500", "G2402253976989700", "G2402252809609800", "G2402252766003400", "G2402245796821400", "G2402244141300100", "G2402235936200300", "G2402226920612500", "G2402210476018800", "G2402210084582900", "G2402210066862100", "G2402209703837600", "G2402209677049500", "G2402193520745200", "G2402193087585400", "G2402184783186700", "G2402184652097700", "G2402184553334400", "G2402184009620300", "G2402183917766700", "G2402182821266100", "G2402182561253100", "G2402182397837800", "G2402175987589000", "G2402175980526000", "G2402175711603000", "G2402175061707800", "G2402174853980500", "G2402174342872600", "G2402156697371600", "G2402140195374000", "G2402149868417400", "G2402113556993000", "G2402104746016800", "G2402103740426400", "G2402095926292900", "G2402095008898500", "G2402085949534100", "G2402079234211100", "G2402079179047200", "G2402079162723000", "G2402069733371500", "G2402043372402100", "G2402032853718600", "G2402032842296500", "G2402032842219500", "G2402025705033000", "G2402025692034000", "G2401319188602100", "G2401300067495800", "G2401292586654500", "G2401291875028900", "G2401274468243600", "G2401273010580900", "G2401272545314500", "G2401266295581600", "G2401255454790100", "G2401255451060800", "G2401248695333500", "G2401246304474900", "G2401220728958800", "G2401193530278000", "G2401185972677100", "G2401177903282000", "G2401177758487500", "G2401177730752400", "G2401177664124700", "G2401167753293500", "G2401132053689300", "G2401132049605500", "G2401132023533900", "G2401124749832800", "G2401106245513000", "G2401106238049000", "G2401098941568300", "G2401098442208400", "G2401089708614300", "G2401071990675600", "G2401062914686300", "G2401062420847100", "G2401062303671600", "G2312285199679000", "G2312284044460500", "G2312277962151800", "G2312277745791400", "G2312277308529700", "G2312276917948900", "G2312276913631700", "G2312276008817100", "G2312274704455300", "G2312268218043100", "G2312259756678100", "G2312249794685300", "G2312232042977900", "G2312231340262800", "G2312230548231800", "G2312205941281100", "G2312179733813400", "G2312179023409100", "G2312178919561400", "G2312178904301600", "G2312178507435100", "G2312178502151900", "G2312161632932700", "G2312160752102000", "G2312160735645800", "G2312153678303300", "G2312134919806900", "G2312127150309300", "G2312126360249800", "G2312100067354500", "G2312081929096200", "G2312065404334000", "G2312055510265600", "G2312039219445400", "G2312029955929200", "G2312028675394600", "G2312028408248900", "G2312012479816200", "G2312012378596500", "G2311301229843300", "G2311293505605600", "G2311286469737400", "G2311275308414200", "G2311259898759500", "G2311257877266600", "G2311231015307000", "G2311230254649400", "G2311224641712700", "G2311224444257000", "G2311223651280800", "G2311223403091900", "G2311223277234600", "G2311222836191400", "G2311206278525100", "G2311206237950200", "G2311196562650200", "G2311188574275300", "G2311171576867300", "G2311170278874100", "G2311170128060700", "G2311170101715600", "G2311162586011000", "G2311161446668600", "G2311160350826600", "G2311150714551300", "G2311145671290700", "G2311142955773700", "G2311136440761600", "G2311108864022300", "G2311090764630600", "G2311075029143500", "G2311074894047300", "G2311065182045400", "G2311065137800000", "G2311064206700100", "G2311058084162600", "G2311049697458000", "G2311049081310500", "G2311048945696400", "G2311048587556000", "G2311048038037100", "G2311047794004500", "G2311047624449300", "G2311047545494400", "G2311047485836000", "G2311047192225600", "G2311047062469600", "G2311039907680500", "G2311021853471700", "G2311020007610600", "G2310313091920600", "G2310313082804600", "G2310287812308300", "G2310287129529600", "G2310277803863600", "G2310269270767100", "G2310252621226400", "G2310251048108900", "G2310244499617000", "G2310244470395700", "G2310242642624600", "G2310234656629800", "G2310234631648100", "G2310233742056500", "G2310226189996300", "G2310217324262800", "G2310216979621400", "G2310216969701300", "G2310216718962900", "G2310216566074400", "G2310215725402800", "G2310215643744500", "G2310199899576400", "G2310181088089500", "G2310172110068000", "G2310155493626700", "G2310146760390700", "G2310138253377800", "G2310110021700600", "G2310102845247800", "G2310093186306600", "G2310084807772900", "G2310084254823700", "G2310076159753600", "G2310068691192300", "G2310059892988400", "G2310058988588400", "G2310057937975800", "G2310040658121800", "G2310040253701600", "G2310032387312300", "G2310039735786800", "G2310013938951600", "G2309306809372700", "G2309306453841100", "G2309305504384100", "G2309304904182900", "G2309297958511800", "G2309297050200900", "G2309296982163400", "G2309296908908100", "G2309295848052700", "G2309288660389400", "G2309288394503400", "G2309288353195100", "G2309251532871500", "G2309243883573500", "G2309243848845900", "G2309243522399500", "G2309233893042600", "G2309227630071600", "G2309216274416000", "G2309209420887400", "G2309208246444400", "G2309192140777200", "G2309190899905700", "G2309199677899500", "G2309183063193000", "G2309181945406700", "G2309181175099800", "G2309174418163100", "G2309171709871700", "G2309171629696000", "G2309165282755700", "G2309164314010100", "G2309155692716200", "G2309148649079500", "G2309145937742000", "G2309137772767800", "G2309110232763100", "G2309103486834900", "G2309103476298700", "G2309094696343000", "G2309094540270000", "G2309094530406500", "G2309094310220400", "G2309085626417800", "G2309069646211000", "G2309068884063500", "G2309041516306900", "G2309041011344200", "G2309049999852900", "G2309033775381900", "G2309033488418900", "G2309025715827800", "G2309025697308400", "G2309025210654500", "G2309025103045100", "G2309024390420800", "G2309024384039500", "G2309023150119500", "G2309023140308100", "G2309022684327200", "G2308316650406800", "G2308316568059900", "G2308315980452100", "G2308308783335400", "G2308308763800000", "G2308307020204600", "G2308306611787600", "G2308299742137500", "G2308299692288700", "G2308298447606400", "G2308297226722300", "G2308297202821200", "G2308280115127200", "G2308289206077600", "G2308273406396800", "G2308273382301300", "G2308263358874000", "G2308255567539600", "G2308255261953600", "G2308246734436900", "G2308245432109200", "G2308238050654000", "G2308236331567400", "G2308235256208200", "G2308229396316700", "G2308210715606200", "G2308202413961300", "G2308201380353900", "G2308201360792200", "G2308193015161800", "G2308192656462000", "G2308183476652800", "G2308183240460500", "G2308182895427300", "G2308182828886900", "G2308176579697000", "G2308176373300700", "G2308174788586700", "G2308174544628000", "G2308168704726900", "G2308167154426000", "G2308166109487700", "G2308158909518600", "G2308156751285100", "G2308156450585000", "G2308140522156000", "G2308140519738700", "G2308140516781400", "G2308148092380000", "G2308131979730200", "G2308130714455000", "G2308114764421300", "G2308113668266800", "G2308105837377800", "G2308104462622800", "G2308097498569500", "G2308095603833300", "G2308095407129000", "G2308095190391900", "G2308095170814100", "G2308088763869300", "G2308087239298900", "G2308070565667300", "G2308062174322500", "G2308052767384900", "G2308052180029600", "G2308050870799000", "G2308050698066900", "G2308044385287300", "G2308035786015600", "G2308027262239400", "G2308026823145700", "G2308025361389800", "G2308017739971800", "G2307310068527900", "G2307319495396200", "G2307318975174700", "G2307318338292800", "G2307318288864900", "G2307301525754500", "G2307292286250700", "G2307292092004000", "G2307290265705300", "G2307290242709400", "G2307275156530500", "G2307274349506300", "G2307266491709500", "G2307266111158200", "G2307257442221800", "G2307249080349800", "G2307247397181900", "G2307239720674700", "G2307213026906700", "G2307212333911300", "G2307212331083900", "G2307212328528300", "G2307204656090700", "G2307202704034400", "G2307195681720100", "G2307195665208400", "G2307193877326700", "G2307177704827100", "G2307169694894100", "G2307150856731300", "G2307130805646700", "G2307117061254300", "G2307117042682100", "G2307108351518000", "G2307108297907900", "G2307107807428700", "G2307106330533800", "G2307072344546100", "G2307072147304300", "G2307071806644000", "G2307046488408600", "G2307046006648900", "G2307037440365300", "G2307035980030100", "G2307018358526800", "G2307018262683700", "G2307018191503300", "G2306302077308400", "G2306301873603200", "G2306301369102300", "G2306293487265600", "G2306283290739600", "G2306276161772400", "G2306273712125000", "G2306273702066300", "G2306267890041600", "G2306267048385600", "G2306258628155000", "G2306256672900500", "G2306240184079200", "G2306248265244700", "G2306248246640600", "G2306248121766600", "G2306248113727700", "G2306231771625600", "G2306231375185500", "G2306231342515200", "G2306231100307000", "G2306239975954400", "G2306202886818100", "G2306188797416900", "G2306187793867600", "G2306170301174000", "G2306170287184800", "G2306178224191800", "G2306177783332800", "G2306177147855200", "G2306161344600500", "G2306161244287200", "G2306161196989100", "G2306160020698700", "G2306169823013300", "G2306169780901300", "G2306169645607100", "G2306169293076100", "G2306151278287800", "G2306143468667100", "G2306141875942200", "G2306141592322100", "G2306133728142600", "G2306126438883300", "G2306126343151200", "G2306126256887900", "G2306126016707200", "G2306125622275100", "G2306124569278200", "G2306118154209600", "G2306117492252800", "G2306117035339500", "G2306117005016500", "G2306109143175300", "G2306109055329300", "G2306108405114100", "G2306108372898400", "G2306107533550800", "G2306090517094400", "G2306090500781500", "G2306090485363500", "G2306099577306400", "G2306099539490800", "G2306081906279700", "G2306081697251900", "G2306081677809200", "G2306081064818800", "G2306089870756600", "G2306073165151200", "G2306072296845100", "G2306070883735900", "G2306070859152600", "G2306070219224600", "G2306064845145500", "G2306064823137200", "G2306062987305300", "G2306056156960600", "G2306055535250700", "G2306054988549500", "G2306054188710900", "G2306053431808500", "G2306047032136600", "G2306046869804900", "G2306045847371000", "G2306045684511200", "G2306036314228900", "G2306018949229400", "G2306018911859300", "G2305310561787600", "G2305304494604300", "G2305304491936300", "G2305304081709300", "G2305304068636600", "G2305302337248800", "G2305294832960300", "G2305286857250100", "G2305286220603300", "G2305278403853100", "G2305278007915000", "G2305276200532400", "G2305260088231400", "G2305269197993600", "G2305269173034300", "G2305250670435100", "G2305258887324900", "G2305258715326600", "G2305241839675500", "G2305233034580900", "G2305225622277700", "G2305225484947100", "G2305223562163800", "G2305214145228800", "G2305207542546800", "G2305207114515500", "G2305206994012400", "G2305206893783000", "G2305206805516800", "G2305205701201100", "G2305205640800700", "G2305199076491000", "G2305198598282300", "G2305198387082200", "G2305198181566400", "G2305197812219500", "G2305197089618000", "G2305180330743800", "G2305188354773400", "G2305188014466200", "G2305171306118400", "G2305163656799600", "G2305163360985100", "G2305163105184700", "G2305162765873200", "G2305162378370700", "G2305162335906700", "G2305162324876100", "G2305162321318800", "G2305161010896400", "G2305153572284000", "G2305144889332200", "G2305144415716000", "G2305137457321500", "G2305137353850000", "G2305137108976200", "G2305136840572400", "G2305136369784500", "G2305136365637000", "G2305136153082900", "G2305136133188200", "G2305129021180600", "G2305127885038000", "G2305126719436200", "G2305118064648700", "G2305084498771800", "G2305084355859000", "G2305083138463000", "G2305083126952800", "G2305074561323700", "G2305073776099400", "G2305066678110100", "G2305066337186200", "G2305056392957300", "G2305049258667800", "G2305013105128600", "G2304296474573000", "G2304296461231800", "G2304296441745600", "G2304293488672600", "G2304279392893600", "G2304279151232500", "G2304277133921600", "G2304251025028900", "G2304259001564400", "G2304233923328600", "G2304233640151400", "G2304233284605900", "G2304224690390300", "G2304224509498000", "G2304224316926800", "G2304217579360900", "G2304215509387100", "G2304215455437600", "G2304215354093000", "G2304208013894100", "G2304199884829000", "G2304199722571800", "G2304199123860000", "G2304181107521600", "G2304180727375600", "G2304189610650500", "G2304188812859100", "G2304162717971100", "G2304161791899000", "G2304155575215400", "G2304146473910700", "G2304146201627900", "G2304146134111200", "G2304136519930600", "G2304126802079100", "G2304092585026600", "G2304091877985600", "G2304091839983200", "G2304091789355000", "G2304091780741900", "G2304091741308500", "G2304091699110500", "G2304091635747700", "G2304091417511500", "G2304091382552600", "G2304083689992800", "G2304076033261300", "G2304075342015400", "G2304074277694800", "G2304066746246600", "G2304059194138600", "G2304058775920100", "G2304040540133700", "G2304049804691100", "G2304048433621800", "G2304031840084000", "G2304031532162100", "G2304022199679400", "G2304013634873500", "G2304013568794300", "G2304013076051000", "G2303307215516400", "G2303298735591300", "G2303298158860900", "G2303298135360500", "G2303280125627500", "G2303289493776100", "G2303270940032800", "G2303270932687700", "G2303262779886700", "G2303262662789100", "G2303262122111200", "G2303261223466300", "G2303261139640600", "G2303261040389300", "G2303253502934700", "G2303252203981700", "G2303251634262300", "G2303245540120400", "G2303243781094900", "G2303243751575500", "G2303236488778600", "G2303236421944600", "G2303236304844600", "G2303236298690900", "G2303228309750400", "G2303227711931700", "G2303227489589800", "G2303227380315000", "G2303219441960800", "G2303218649893200", "G2303218637280100", "G2303218601004100", "G2303218529066600", "G2303217263367600", "G2303217124498600", "G2303200553747900", "G2303200400319500", "G2303209916093100", "G2303191567594000", "G2303191466962000", "G2303191274485100", "G2303191017976500", "G2303190053605500", "G2303199853897900", "G2303183972806700", "G2303183862916300", "G2303183529357900", "G2303182385139000", "G2303181517670900", "G2303174715692800", "G2303174536062800", "G2303173532368200", "G2303166088964300", "G2303157713657300", "G2303156931918000", "G2303156904647000", "G2303148345015000", "G2303148334707300", "G2303130529894000", "G2303130499454900", "G2303139952785800", "G2303138352557800", "G2303138141047200", "G2303120952241400", "G2303120181306200", "G2303120131842500", "G2303120063753200", "G2303120030621800", "G2303129990025900", "G2303129872054700", "G2303129822694300", "G2303113147492000", "G2303112927096200", "G2303112900601900", "G2303112692296400", "G2303112603264400", "G2303104221961700", "G2303103975424800", "G2303103962479600", "G2303103179028400", "G2303101956690600", "G2303095080319200", "G2303087195657600", "G2303086856353000", "G2303086744849600", "G2303078059726700", "G2303077995537400", "G2303077989556400", "G2303069617927900", "G2303069467470600", "G2303069221529000", "G2303051254855400", "G2303050863651300", "G2303050754481600", "G2303059141963900", "G2303043225787900", "G2303042759863100", "G2303041952432800", "G2303041896387100", "G2303033806936700", "G2303033649127100", "G2303032273126200", "G2303031582955300", "G2303031107723200", "G2303025504383000", "G2303024322772300", "G2303023159456900", "G2303022721098300", "G2303016650160200", "G2303016081369200", "G2303015974171200", "G2303014678185800", "G2303014629941300", "G2302288367074300", "G2302288308140200", "G2302287563120700", "G2302260108170100", "G2302269973947200", "G2302252217531800", "G2302252134267000", "G2302251525956900", "G2302251322529800", "G2302251235713700", "G2302251226941300", "G2302251221625600", "G2302251016288400", "G2302250879467200", "G2302250777990300", "G2302250584151100", "G2302250560406300", "G2302243462388400", "G2302243347424500", "G2302242924330100", "G2302234888854400", "G2302234871894200", "G2302234867880500", "G2302234812109600", "G2302225729656600", "G2302217587047000", "G2302217492005100", "G2302208781562800", "G2302190190199200", "G2302190085823000", "G2302199749907900", "G2302182186829600", "G2302182090660700", "G2302182029617600", "G2302182013545600", "G2302181996497800", "G2302181357364500", "G2302172228951200", "G2302170679206500", "G2302164027336200", "G2302155506531700", "G2302155165250100", "G2302147248911300", "G2302138410673500", "G2302138080917700", "G2302137931968500", "G2302128878743100", "G2302127915558100", "G2302111001427900", "G2302110905692400", "G2302110895077100", "G2302110789299100", "G2302102353212100", "G2302102349802300", "G2302102346208800", "G2302093596213800", "G2302093514340100", "G2302093417584300", "G2302092824370200", "G2302091792181100", "G2302084761241900", "G2302084381137500", "G2302076771680200", "G2302076751086000", "G2302076736945700", "G2302076685892900", "G2302073574788900", "G2302068030543500", "G2302067440614600", "G2302067189118500", "G2302059223118500", "G2302059187906300", "G2302059138143000", "G2302040893486000", "G2302031422241900", "G2302031410603600", "G2302023427843500", "G2302023338854500", "G2302023312675200", "G2302023276632000", "G2301316375966700", "G2301315997466800", "G2301315965805900", "G2301314328731700", "G2301313883420700", "G2301306721306900", "G2301298582035300", "G2301298548071000", "G2301297875959200", "G2301280037757900", "G2301271275987800", "G2301262694016400", "G2301262658807000", "G2301262642480300", "G2301261742813800", "G2301252925298600", "G2301245630202500", "G2301245343340000", "G2301244476020500", "G2301243768058800", "G2301243758654100", "G2301243400901300", "G2301243080532700", "G2301237004883300", "G2301236528164500", "G2301236090372400", "G2301227939781500", "G2301227560578400", "G2301226745017800", "G2301217467906400", "G2301201226880900", "G2301201203835700", "G2301200403344200", "G2301183494756200", "G2301174932963700", "G2301174713137100", "G2301164097863700", "G2301157489865300", "G2301156737731200", "G2301147310134300", "G2301130258605200", "G2301139899161600", "G2301139323102900", "G2301121884715200", "G2301121850525000", "G2301121217412000", "G2301113344904000", "G2301104658757300", "G2301104421988500", "G2301096139451300", "G2301095267080100", "G2301095246734600", "G2301087084037200", "G2301079102596500", "G2301078921753700", "G2301078727636000", "G2301078722086500", "G2301078708145000", "G2301078668838900", "G2301078528977000", "G2301078504360300", "G2301078465444500", "G2301078436190300", "G2301078305906000", "G2301078165075300", "G2301077976510800", "G2301077911289900", "G2301077731215500", "G2301077329221500", "G2301069484248600", "G2301050656019300", "G2301041945800700", "G2212308834038000", "G2212308796928900", "G2212308717532900", "G2212291750520800", "G2212291482760400", "G2212273821695200", "G2212273227641500", "G2212265062513100", "G2212265057350200", "G2212255534085400", "G2212255237288900", "G2212255191676000", "G2212238347360100", "G2212220669301700", "G2212220656303100", "G2212211746255400", "G2212202872370900", "G2212194833208200", "G2212194804435800", "G2212193263935600", "G2212185355601000", "G2212178190892300", "G2212178084346200", "G2212178011312100", "G2212177848477300", "G2212176517708500", "G2212176199881800", "G2212168911527100", "G2212133044982500", "G2212124512493200", "G2212124459949200", "G2212124081987600", "G2212113878647100", "G2212113810021700", "G2212106321570800", "G2212104435755900", "G2212097940092100", "G2212095496285700", "G2212089800035100", "G2212069412782600", "G2212053809133300", "G2212053773607100", "G2212053752243500", "G2212053480244000", "G2212053362803200", "G2212042712897300", "G2212036129489300", "G2212036017075100", "G2212035886111201", "G2212035354364000", "G2212035330942900", "G2212035210247500", "G2212034041956200", "G2212034023828600", "G2212026582857700", "G2212019202394200", "G2212018971285900", "G2212018936103700", "G2211300102275100", "G2211309944146600", "G2211309679896600", "G2211291832987600", "G2211291776609400", "G2211291639155700", "G2211291591068700", "G2211291555085700", "G2211291538469600", "G2211291508285100", "G2211291279794400", "G2211291258261300", "G2211291099774200", "G2211291052444100", "G2211283008722100", "G2211282955292000", "G2211281837696200", "G2211281812798800", "G2211274087231200", "G2211272193404400", "G2211271992567200", "G2211271742807500", "G2211271722092700", "G2211265775748700", "G2211265712896200", "G2211265561060900", "G2211265415605700", "G2211265355449300", "G2211264550590300", "G2211248324447000", "G2211248190468000", "G2211248172197700", "G2211245998745000", "G2211228911216200", "G2211203902724800", "G2211203639977500", "G2211203486190000", "G2211203117314100", "G2211195735517000", "G2211195513969000", "G2211194407820200", "G2211194332710700", "G2211193336265400", "G2211193304102400", "G2211185844272600", "G2211178198693600", "G2211177335816000", "G2211167931285700", "G2211167072156800", "G2211150327537800", "G2211159400384300", "G2211159123782200", "G2211158414473400", "G2211143422401000", "G2211140501438800", "G2211149634596600", "G2211132084409900", "G2211131985313900", "G2211131979291300", "G2211131907673700", "G2211131403497800", "G2211124559823100", "G2211123909850000", "G2211117009873800", "G2211116974555300", "G2211116074775700", "G2211116059528700", "G2211098233308100", "G2211098227123500", "G2211096920805100", "G2211080235965700", "G2211080005341900", "G2211089712585600", "G2211089681654500", "G2211088687757500", "G2211071875074000", "G2211071735239500", "G2211063196709200", "G2211054234540700", "G2211053922504100", "G2211053327357200", "G2211051778399600", "G2211043254889300", "G2211036151799200", "G2211036064395200", "G2211035081860400", "G2211034658680000", "G2211026935099800", "G2211019387550600", "G2211018893222400", "G2211018849829300", "G2210311046160100", "G2210310873846200", "G2210310519757100", "G2210302328360100", "G2210302158887700", "G2210301781177100", "G2210301762750000", "G2210301658805300", "G2210301482808500", "G2210301431980500", "G2210301374651400", "G2210300914847400", "G2210293510030300", "G2210293306225700", "G2210284484539700", "G2210283452519800", "G2210276305143900", "G2210274521075400", "G2210274096736200", "G2210267996117800", "G2210267737445500", "G2210259167508900", "G2210240527979400", "G2210239935423800", "G2210223445290800", "G2210223388698200", "G2210223288188800", "G2210223217320000", "G2210214523400600", "G2210213248238000", "G2210211986010700", "G2210204784806000", "G2210204755920000", "G2210196750646800", "G2210196517133700", "G2210194678559800", "G2210188705737800", "G2210187243172000", "G2210170176802800", "G2210170099367300", "G2210179353873600", "G2210161241033900", "G2210160336362000", "G2210160019146000", "G2210169888666200", "G2210169745976700", "G2210151849011600", "G2210150905096600", "G2210134604376500", "G2210134547912000", "G2210134324905700", "G2210133198396400", "G2210125888741100", "G2210117783890400", "G2210117445339300", "G2210107679152900", "G2210107509837700", "G2210090218227100", "G2210090183490800", "G2210099686417000", "G2210099668278100", "G2210099232200700", "G2210081530233100", "G2210081449546500", "G2210081419558200", "G2210081396706100", "G2210081395866600", "G2210081304981900", "G2210081289318200", "G2210081254255300", "G2210081113241300", "G2210080926775100", "G2210080020402800", "G2210080014797100", "G2210065038018200", "G2210064931931800", "G2210045927256600", "G2210045815956600", "G2210038034376500", "G2210037046261700", "G2210036714496200", "G2210029013506000", "G2210028945688300", "G2210028848097200", "G2210028754544800", "G2210028738513700", "G2210028607567900", "G2210028549347000", "G2210028396182300", "G2210028294616200", "G2210027991984400", "G2210027881947800", "G2210011824655800", "G2210011739123600", "G2210011721339400", "G2210011383102700", "G2210011102061600", "G2210010559529100", "G2210019948403300", "G2209303159900900", "G2209303073357500", "G2209302232321300", "G2209293485556300", "G2209293302139500", "G2209285296232700", "G2209284891113100", "G2209284122915000", "G2209283662938100", "G2209283359972500", "G2209277182626300", "G2209276913552100", "G2209274736067000", "G2209274729366600", "G2209274724158900", "G2209268281889500", "G2209268183515100", "G2209267784105200", "G2209267570186200", "G2209267538490400", "G2209267362363300", "G2209267332695200", "G2209266327686100", "G2209250062146100", "G2209259693046300", "G2209259367594500", "G2209258041154000", "G2209257700117700", "G2209257607232400", "G2209240620523000", "G2209240588286600", "G2209240492306400", "G2209240450154800", "G2209240374350400", "G2209240216938400", "G2209249664702700", "G2209249327517900", "G2209249134118400", "G2209232912095000", "G2209232536591000", "G2209232322417100", "G2209232307980200", "G2209231849983000", "G2209223965994000", "G2209223861634500", "G2209223782591700", "G2209223629006100", "G2209223597125500", "G2209222993814500", "G2209222976057000", "G2209222896035900", "G2209222520258200", "G2209222378326900", "G2209222329064000", "G2209222256288200", "G2209221965726800", "G2209221843927300", "G2209215742926600", "G2209215721447300", "G2209215671158200", "G2209215619106600", "G2209215565377100", "G2209215543982400", "G2209214853007000", "G2209214819342400", "G2209214569218100", "G2209214537806500", "G2209214502606100", "G2209214004082400", "G2209213902706600", "G2209213777761100", "G2209213281521900", "G2209206086522300", "G2209206009326100", "G2209205454912000", "G2209205113477200", "G2209189253998900", "G2209189060807400", "G2209188905472100", "G2209188491531000", "G2209188334973000", "G2209188149090900", "G2209187569650700", "G2209187259421600", "G2209187231730100", "G2209170882425000", "G2209170359755000", "G2209170341765300", "G2209170239220700", "G2209178685425700", "G2209178656403700", "G2209178616646400", "G2209178592752700", "G2209178497387400", "G2209162590664800", "G2209162482099100", "G2209162408076700", "G2209162374307600", "G2209160956331900", "G2209160946352100", "G2209153576972000", "G2209145276885000", "G2209145111248300", "G2209143144481700", "G2209127917552200", "G2209127315940900", "G2209127191749500", "G2209127184217700", "G2209118046334600", "G2209118020089500", "G2209116478308100", "G2209100346554700", "G2209100275653300", "G2209100221322200", "G2209100092943500", "G2209100091123200", "G2209100035765300", "G2209100024213200", "G2209109935341000", "G2209109865508700", "G2209109810005100", "G2209109684356700", "G2209109465827200", "G2209109394731200", "G2209109345517500", "G2209109341305000", "G2209109219787800", "G2209109162844400", "G2209109122771900", "G2209109045470600", "G2209108984769100", "G2209108865600800", "G2209108725941000", "G2209108672803200", "G2209108530129800", "G2209107858653700", "G2209107830492200", "G2209091639462700", "G2209091132747100", "G2209083156800700", "G2209081560165200", "G2209081210470000", "G2209081190674000", "G2209066048856100", "G2209065776893900", "G2209065622714800", "G2209064251538700", "G2209063857782900", "G2209057004982200", "G2209056653442800", "G2209056528009100", "G2209056147328800", "G2209048410918900", "G2209048167267600", "G2209047975942900", "G2209047824830100", "G2209045911553100", "G2209045763536900", "G2209030418583100", "G2209030237388900", "G2209030085501600", "G2209030046134200", "G2209039979205000", "G2209039593358900", "G2209039560041600", "G2209039486986000", "G2209039400313900", "G2209039275767700", "G2209039257151500", "G2209039183132000", "G2209039069424200", "G2209039007680600", "G2209038965062200", "G2209038869829300", "G2209038726493800", "G2209038660570900", "G2209038595064500", "G2209038403145300", "G2209021321046800", "G2209021092086700", "G2209021051698600", "G2209020606939700", "G2209012768054500", "G2209012516398600", "G2209011914317400", "G2209010508714900", "G2208313113699400", "G2208313051050400", "G2208312633546500", "G2208312049962000", "G2208305245873200", "G2208305025448800", "G2208304287563800", "G2208303205862000", "G2208288582702000", "G2208287916581900", "G2208287808845800", "G2208287774709300", "G2208286347890700", "G2208286147862100", "G2208286067625800", "G2208286001882600", "G2208279153489600", "G2208278936827600", "G2208278863235400", "G2208278218780000", "G2208277847410800", "G2208277715245200", "G2208277683493600", "G2208277144221700", "G2208276967443200", "G2208276934327400", "G2208260884850500", "G2208269720448100", "G2208269516913900", "G2208250383160000", "G2208259653889400", "G2208243657925500", "G2208243086667100", "中田　勇樹", "G2208242852017600", "G2208242836997600", "G2208242465707600", "G2208242415400000", "G2208242368098700", "G2208242271094100", "G2208242231254500", "G2208241754570000", "G2208235196747800", "G2208235177878400", "G2208235129074400", "G2208235097908500", "G2208235027238800", "G2208233158971100", "G2208227099593300", "G2208227050553500", "G2208227043241800", "G2208227001623000", "G2208226966494300", "G2208226869726700", "G2208226866777300", "G2208226803834100", "G2208226794586500", "G2208226731779500", "G2208226708787200", "G2208226703830900", "G2208226681542300", "G2208226636222500", "G2208226305357700", "G2208225982085400", "G2208225925561400", "G2208225921902700", "G2208225894351600", "G2208225862535700", "G2208225447683800", "G2208225043201700", "G2208217885037700", "G2208216954163400", "G2208216810838200", "G2208216086385600", "G2208215826470200", "G2208215390064000", "G2208209581425600", "G2208209446433800", "G2208209412130000", "G2208209277265300", "G2208209222835000", "G2208209196800800", "G2208209170775100", "G2208208941557100", "G2208208861373000", "G2208208330984000", "G2208207555109300", "G2208207504080600", "G2208207397051300", "G2208207213696300", "G2208207107298300", "G2208207099289400", "G2208207095455000", "G2208206750064100", "G2208206686750600", "G2208206634751400", "G2208206475675500", "G2208206419336700", "G2208190498834600", "G2208190430064700", "G2208190230164200", "G2208190060419300", "G2208190005687500", "G2208199918615900", "G2208180884491700", "G2208180864912600", "G2208180851132400", "G2208173124436500", "G2208172950718400", "G2208172942305300", "G2208172924433200", "G2208172912516500", "G2208172902195000", "G2208172897451900", "G2208172892907200", "G2208172885966300", "G2208172873583600", "G2208172865580800", "G2208172856218400", "G2208172846606400", "G2208172840506700", "G2208172830697100", "G2208172821933000", "G2208172812320200", "G2208172801930300", "G2208172762462300", "G2208172657605700", "G2208172649784500", "G2208172640236300", "G2208172631286700", "G2208172620774600", "G2208172614183700", "G2208172607232900", "G2208172601410000", "G2208172591966800", "G2208172559225400", "G2208172456385600", "G2208172393984500", "G2208172367720200", "G2208172314759300", "G2208172287355000", "G2208172237203700", "G2208172194032900", "G2208172149873400", "G2208172099612600", "G2208171710715000", "G2208171510085500", "G2208171489659200", "G2208171461253700", "G2208171443334100", "G2208171428549400", "G2208171400095500", "G2208171326200800", "G2208171201215900", "G2208171049571500", "G2208171009064600", "G2208171003904600", "G2208170926071700", "G2208170826163400", "G2208170789728500", "G2208170740345500", "G2208170629628000", "G2208164626322000", "G2208156130559600", "G2208156053137000", "G2208154426759100", "G2208154352516300", "G2208154314783700", "G2208154280390000", "G2208153634614500", "G2208153603126100", "G2208153559012000", "G2208153469163200", "G2208111205382600", "G2208110764913100", "G2208110739585700", "G2208110683405400", "G2208119649876100", "G2208119632654500", "G2208119610786700", "G2208102489669600", "G2208102404775200", "G2208101344339600", "G2208101100512000", "G2208093325195200", "G2208092284445200", "G2208092056096200", "G2208085641432700", "G2208084715486300", "G2208084495142100", "G2208083627358600", "G2208083535624900", "G2208083012853900", "G2208082463302000", "G2208075319525600", "G2208075290590400", "G2208075095180200", "G2208067416983000", "G2208066444491300", "G2208066387979400", "G2208065690789500", "G2208056857728400", "G2208056596471800", "G2208040155066400", "G2208040006838100", "G2208031146772100", "G2208031050645600", "G2208013909114700", "G2208013776870900", "G2207315943542700", "G2207315033417700", "G2207313448215300", "G2207306297684600", "G2207306285951900", "G2207297900022100", "G2207296506880600", "G2207296298951600", "G2207289888489400", "G2207288692891900", "G2207271061532700", "G2207279358773900", "G2207279243503900", "G2207279001962500", "G2207262772422500", "G2207262433601300", "G2207262150353300", "G2207262147812600", "G2207262143652200", "G2207262138564200", "G2207254603747100", "G2207254585230000", "G2207252259670800", "G2207252225367900", "G2207245779211700", "G2207243362497200", "G2207242925041800", "G2207236374595800", "G2207226823332200", "G2207217628619500", "G2207191996043100", "G2207191636997800", "G2207183365119800", "G2207182801922000", "G2207167242350600", "G2207166696588500", "G2207166019918300", "G2206309973010700", "G2206272079655500", "G2206263671269400", "G2206253682842800", "G22061801", "G06160760", "G06160761", "G06160757", "G06160758", "G06160754", "G06160755", "G06160753", "G06160759", "G06160756", "G06160752", "G06160749", "G06160747", "G06160748", "G06160750", "G06160751", "G06160745", "G06160742", "G06160744", "G06160746", "G06160739", "G06160743", "G06160741", "G06160740", "G06160732", "G06160734", "G06160735", "G06160737", "G06160736", "G06160738", "G06160733", "G06160726", "G06160729", "G06160727", "G06160728", "G06160730", "G06160731", "G06160721", "G06160722", "G06160718", "G06160719", "G06160725", "G06160723", "G06160724", "G06160720", "G06160712", "G06160717", "G06160715", "G06160713", "G06160716", "G06160714", "G06160710", "G06160709", "G06160706", "G06160707", "G06160708", "G06160711", "G06160700", "G06160702", "G06160701", "G06160704", "G06160705", "G06160703", "G06160698", "G06160694", "G06160695", "G06160696", "G06160699", "G06160697", "G06160691", "G06160690", "G06160692", "G06160693", "G06160689", "G06160688", "G06160682", "G06160683", "G06160686", "G06160685", "G06160684", "G06160687", "G06160680", "G06160676", "G06160678", "G06160675", "G06160677", "G06160679", "G06160681", "G06160674", "G06160670", "G06160669", "G06160672", "G06160673", "G06160671", "G06160663", "G06160664", "G06160665", "G06160667", "G06160666", "G06160668", "G06160662", "G06160658", "G06160661", "G06160656", "G06160659", "G06160660", "G06160655", "G06160657", "G06160653", "G06160651", "G06160654", "G06160649", "G06160650", "G06160652", "G06160645", "G06160647", "G06160648", "G06160643", "G06160646", "G06160644", "G06160636", "G06160638", "G06160639", "G06160640", "G06160641", "G06160642", "G06160637", "G06160632", "G06160634", "G06160635", "G06160631", "G06160633", "G06160629", "G06160628", "G06160630", "G06160623", "G06160624", "G06160626", "G06160627", "G06160625", "G06160619", "G06160620", "G06160616", "G06160617", "G06160618", "G06160621", "G06160622", "G06160611", "G06160612", "G06160615", "G06160610", "G06160614", "G06160613", "G06160604", "G06160605", "G06160609", "G06160603", "G06160606", "G06160608", "G06160607", "G06160597", "G06160601", "G06160598", "G06160599", "G06160600", "G06160602", "G06160596", "G06160589", "G06160595", "G06160591", "G06160593", "G06160592", "G06160590", "G06160594", "G06160583", "G06160584", "G06160585", "G06160588", "G06160587", "G06160582", "G06160586", "G06160575", "G06160580", "G06160581", "G06160577", "G06160578", "G06160576", "G06160579", "G06160574", "G06160570", "G06160571", "G06160568", "G06160569", "G06160572", "G06160573", "G06160565", "G06160562", "G06160561", "G06160564", "G06160566", "G06160563", "G06160567", "G06160560", "G06160554", "G06160558", "G06160555", "G06160556", "G06160557", "G06160559", "G06160549", "G06160550", "G06160552", "G06160548", "G06160551", "G06160553", "G06160547", "G06160540", "G06160543", "G06160545", "G06160542", "G06160544", "G06160541", "G06160546", "G06160535", "G06160539", "G06160537", "G06160536", "G06160538", "G06160534", "G06161486", "G06161489", "G06161491", "G06161487", "G06161488", "G06161490", "G06161480", "G06161481", "G06161484", "G06161478", "G06161479", "G06161485", "G06161482", "G06161483", "G06161474", "G06161471", "G06161475", "G06161476", "G06161472", "G06161473", "G06161477", "G06161464", "G06161468", "G06161466", "G06161462", "G06161463", "G06161467", "G06161465", "G06161469", "G06161470", "G06161457", "G06161456", "G06161460", "G06161458", "G06161459", "G06161461", "G06161455", "G06161451", "G06161447", "G06161448", "G06161449", "G06161452", "G06161450", "G06161454", "G06161453", "G06161440", "G06161443", "G06161442", "G06161445", "G06161441", "G06161444", "G06161446", "G06161439", "G06161438", "G06161431", "G06161432", "G06161435", "G06161430", "G06161434", "G06161433", "G06161437", "G06161436", "G06161425", "G06161422", "G06161428", "G06161429", "G06161423", "G06161424", "G06161426", "G06161427", "G06161415", "G06161420", "G06161417", "G06161419", "G06161421", "G06161416", "G06161418", "G06161414", "G06161409", "G06161411", "G06161413", "G06161412", "G06161410", "G06161408", "G06161402", "G06161399", "G06161401", "G06161403", "G06161405", "G06161404", "G06161406", "G06161407", "G06161400", "G06161391", "G06161390", "G06161392", "G06161394", "G06161395", "G06161393", "G06161397", "G06161396", "G06161398", "G06161386", "G06161387", "G06161383", "G06161384", "G06161385", "G06161388", "G06161382", "G06161389", "G06161374", "G06161380", "G06161375", "G06161377", "G06161381", "G06161378", "G06161376", "G06161379", "G06161367", "G06161368", "G06161371", "G06161372", "G06161373", "G06161370", "G06161365", "G06161369", "G06161366", "G06161363", "G06161362", "G06161364", "G06161356", "G06161359", "G06161357", "G06161361", "G06161358", "G06161360", "G06161348", "G06161350", "G06161354", "G06161353", "G06161347", "G06161349", "G06161351", "G06161352", "G06161355", "G06161346", "G06161341", "G06161344", "G06161338", "G06161339", "G06161342", "G06161345", "G06161340", "G06161343", "G06161331", "G06161336", "G06161334", "G06161329", "G06161330", "G06161333", "G06161335", "G06161337", "G06161332", "G06161321", "G06161322", "G06161325", "G06161328", "G06161326", "G06161320", "G06161327", "G06161323", "G06161324", "G06161317", "G06161315", "G06161319", "G06161312", "G06161313", "G06161314", "G06161316", "G06161318", "G06161305", "G06161310", "G06161311", "G06161306", "G06161309", "G06161303", "G06161304", "G06161308", "G06161307", "G06161302", "G06161297", "G06161296", "G06161300", "G06161301", "G06161298", "G06161299", "G06161295", "G06161291", "G06161292", "G06161287", "G06161293", "G06161288", "G06161290", "G06161294", "G06161286", "G06161289", "G06161280", "G06161281", "G06161279", "G06161282", "G06161284", "G06161285", "G06161277", "G06161283", "G06161278", "G06161267", "G06161271", "G06161275", "G06161276", "G06161268", "G06161270", "G06161269", "G06161272", "G06161273", "G06161274", "G06161259", "G06161260", "G06161263", "G06161264", "G06161261", "G06161262", "G06161265", "G06161266", "G06161258", "G06161250", "G06161254", "G06161255", "G06161251", "G06161256", "G06161257", "G06161253", "G06161252", "G06161246", "G06161243", "G06161248", "G06161249", "G06161242", "G06161244", "G06161245", "G06161247", "G06161239", "G06161240", "G06161241", "G06161236", "G06161238", "G06161237", "G06161235", "G06161229", "G06161230", "G06161233", "G06161232", "G06161234", "G06161231", "G06161221", "G06161223", "G06161225", "G06161227", "G06161222", "G06161226", "G06161224", "G06161228", "G06161219", "G06161216", "G06161217", "G06161218", "G06161220", "G06161215", "G06161214", "G06161208", "G06161209", "G06161213", "G06161211", "G06161212", "G06161210", "G06161204", "G06161206", "G06161203", "G06161207", "G06161205", "G06161201", "G06161200", "G06161202", "G06161198", "G06161199", "G06161197", "G06161192", "G06161193", "G06161194", "G06161196", "G06161191", "G06161195", "G06161189", "G06161190", "G06161186", "G06161184", "G06161185", "G06161187", "G06161183", "G06161188", "G06161179", "G06161181", "G06161180", "G06161182", "G06161174", "G06161176", "G06161178", "G06161173", "G06161175", "G06161177", "G06161167", "G06161170", "G06161168", "G06161172", "G06161169", "G06161171", "G06161166", "G06160891", "G06160893", "G06160892", "G06160889", "G06160886", "G06160890", "G06160888", "G06160885", "G06160887", "G06160878", "G06160881", "G06160884", "G06160880", "G06160883", "G06160877", "G06160879", "G06160882", "G06160870", "G06160874", "G06160876", "G06160873", "G06160871", "G06160875", "G06160872", "G06160865", "G06160867", "G06160868", "G06160869", "G06160863", "G06160864", "G06160866", "G06160858", "G06160861", "G06160860", "G06160862", "G06160859", "G06160857", "G06160851", "G06160856", "G06160850", "G06160852", "G06160853", "G06160855", "G06160854", "G06160849", "G06160848", "G06160845", "G06160844", "G06160846", "G06160847", "G06160837", "G06160839", "G06160841", "G06160842", "G06160843", "G06160838", "G06160840", "G06160836", "G06160830", "G06160832", "G06160834", "G06160835", "G06160831", "G06160829", "G06160833", "G06160828", "G06160825", "G06160826", "G06160824", "G06160827", "G06160822", "G06160819", "G06160823", "G06160821", "G06160820", "G06160818", "G06160815", "G06160817", "G06160816", "G06160810", "G06160812", "G06160813", "G06160811", "G06160814", "G06160808", "G06160807", "G06160805", "G06160806", "G06160809", "G06160802", "G06160804", "G06160803", "G06160801", "G06160800", "G06160798", "G06160799", "G06160795", "G06160794", "G06160796", "G06160797", "G06160791", "G06160793", "G06160792", "G06160788", "G06160789", "G06160790", "G06160787", "G06160786", "G06160783", "G06160784", "G06160781", "G06160785", "G06160782", "G06160779", "G06160780", "G06160778", "G06160776", "G06160775", "G06160777", "G06160774", "G06160773", "G06160771", "G06160770", "G06160772", "G06160765", "G06160768", "G06160766", "G06160767", "G06160769", "G06160764", "G06160762", "G06160763", "G06161164", "G06161165", "G06161157", "G06161160", "G06161162", "G06161156", "G06161158", "G06161159", "G06161161", "G06161163", "G06161150", "G06161155", "G06161153", "G06161154", "G06161148", "G06161151", "G06161152", "G06161149", "G06161143", "G06161142", "G06161145", "G06161144", "G06161147", "G06161146", "G06161134", "G06161135", "G06161138", "G06161139", "G06161133", "G06161136", "G06161137", "G06161141", "G06161140", "G06161129", "G06161131", "G06161127", "G06161128", "G06161130", "G06161132", "G06161126", "G06161121", "G06161123", "G06161124", "G06161125", "G06161120", "G06161122", "G06161119", "G06161115", "G06161116", "G06161118", "G06161113", "G06161114", "G06161117", "G06161112", "G06161107", "G06161110", "G06161106", "G06161108", "G06161109", "G06161111", "G06161101", "G06161102", "G06161103", "G06161104", "G06161105", "G06161100", "G06161096", "G06161099", "G06161097", "G06161098", "G06161095", "G06161094", "G06161091", "G06161092", "G06161093", "G06161090", "G06161086", "G06161089", "G06161088", "G06161087", "G06161085", "G06161084", "G06161082", "G06161083", "G06161077", "G06161079", "G06161080", "G06161081", "G06161078", "G06161076", "G06161073", "G06161072", "G06161074", "G06161075", "G06161070", "G06161071", "G06161069", "G06161067", "G06161068", "G06161065", "G06161061", "G06161064", "G06161066", "G06161063", "G06161062", "G06161056", "G06161058", "G06161059", "G06161060", "G06161057", "G06161054", "G06161055", "G06161052", "G06161050", "G06161051", "G06161053", "G06161048", "G06161047", "G06161045", "G06161046", "G06161049", "G06161043", "G06161044", "G06161040", "G06161042", "G06161041", "G06161037", "G06161038", "G06161035", "G06161036", "G06161039", "G06161033", "G06161030", "G06161031", "G06161032", "G06161034", "G06161027", "G06161024", "G06161028", "G06161029", "G06161025", "G06161023", "G06161026", "G06161021", "G06161014", "G06161015", "G06161016", "G06161019", "G06161017", "G06161018", "G06161020", "G06161022", "G06161006", "G06161008", "G06161009", "G06161010", "G06161011", "G06161012", "G06161007", "G06161013", "G06161005", "G06161001", "G06161000", "G06160997", "G06160999", "G06161002", "G06161003", "G06161004", "G06160996", "G06160998", "G06160993", "G06160989", "G06160990", "G06160994", "G06160995", "G06160986", "G06160991", "G06160992", "G06160985", "G06160987", "G06160988", "G06160979", "G06160981", "G06160983", "G06160978", "G06160980", "G06160982", "G06160984", "G06160977", "G06160971", "G06160974", "G06160976", "G06160973", "G06160975", "G06160966", "G06160967", "G06160968", "G06160969", "G06160970", "G06160972", "G06160961", "G06160963", "G06160964", "G06160965", "G06160958", "G06160959", "G06160960", "G06160962", "G06160957", "G06160951", "G06160947", "G06160949", "G06160950", "G06160956", "G06160948", "G06160955", "G06160952", "G06160953", "G06160954", "G06160942", "G06160943", "G06160945", "G06160946", "G06160940", "G06160944", "G06160939", "G06160937", "G06160941", "G06160938", "G06160933", "G06160929", "G06160930", "G06160936", "G06160931", "G06160932", "G06160934", "G06160935", "G06160927", "G06160920", "G06160928", "G06160922", "G06160923", "G06160925", "G06160921", "G06160926", "G06160924", "G06160911", "G06160913", "G06160917", "G06160915", "G06160919", "G06160912", "G06160914", "G06160916", "G06160918", "G06160910", "G06160908", "G06160904", "G06160907", "G06160905", "G06160909", "G06160906", "G06160901", "G06160900", "G06160902", "G06160903", "G06160897", "G06160895", "G06160899", "G06160896", "G06160898", "G06160894", "G06160533", "G06160532", "G06160531", "G06160526", "G06160522", "G06160524", "G06160529", "G06160528", "G06160525", "G06160523", "G06160527", "G06160530", "G06160514", "G06160515", "G06160521", "G06160516", "G06160518", "G06160517", "G06160519", "G06160520", "G06160511", "G06160513", "G06160506", "G06160507", "G06160509", "G06160512", "G06160510", "G06160508", "G06160499", "G06160500", "G06160502", "G06160503", "G06160504", "G06160501", "G06160498", "G06160505", "G06160496", "G06160492", "G06160493", "G06160497", "G06160491", "G06160494", "G06160495", "G06160483", "G06160485", "G06160482", "G06160484", "G06160487", "G06160488", "G06160489", "G06160490", "G06160486", "G06160474", "G06160477", "G06160478", "G06160479", "G06160480", "G06160475", "G06160476", "G06160481", "G06160470", "G06160473", "G06160465", "G06160466", "G06160471", "G06160468", "G06160469", "G06160472", "G06160467", "G06160457", "G06160458", "G06160462", "G06160459", "G06160461", "G06160463", "G06160464", "G06160460", "G06160452", "G06160454", "G06160453", "G06160448", "G06160451", "G06160455", "G06160449", "G06160450", "G06160456", "G06160446", "G06160447", "G06160442", "G06160443", "G06160445", "G06160441", "G06160444", "G06160435", "G06160436", "G06160437", "G06160438", "G06160440", "G06160439", "G06160434", "G06160433", "G06160431", "G06160425", "G06160432", "G06160427", "G06160428", "G06160430", "G06160426", "G06160429", "G06160424", "G06160418", "G06160421", "G06160422", "G06160419", "G06160420", "G06160423", "G06160417", "G06160411", "G06160413", "G06160414", "G06160416", "G06160410", "G06160412", "G06160415", "G06160402", "G06160406", "G06160407", "G06160408", "G06160409", "G06160403", "G06160404", "G06160405", "G06160393", "G06160394", "G06160399", "G06160395", "G06160400", "G06160401", "G06160398", "G06160396", "G06160397", "G06160386", "G06160388", "G06160390", "G06160391", "G06160389", "G06160392", "G06160387", "G06160384", "G06160385", "G06160381", "G06160382", "G06160380", "G06160383", "G06160378", "G06160379", "G06160377", "G06160374", "G06160372", "G06160373", "G06160375", "G06160371", "G06160370", "G06160369", "G06160365", "G06160366", "G06160368", "G06160367", "G06160362", "G06160360", "G06160361", "G06160364", "G06160359", "G06160363", "G06160358", "G06160357", "G06160353", "G06160355", "G06160356", "G06160354", "G06160352", "G06160350", "G06160346", "G06160351", "G06160348", "G06160349", "G06160347", "G06160344", "G06160345", "G06160340", "G06160342", "G06160341", "G06160343", "G06160337", "G06160339", "G06160333", "G06160338", "G06160334", "G06160336", "G06160335", "G06160328", "G06160329", "G06160330", "G06160332", "G06160331", "G06160327", "G06160321", "G06160325", "G06160324", "G06160322", "G06160323", "G06160326", "G06160316", "G06160318", "G06160320", "G06160315", "G06160314", "G06160319", "G06160317", "G06160312", "G06160310", "G06160313", "G06160309", "G06160311", "G06160308", "G06160303", "G06160306", "G06160307", "G06160304", "G06160305", "G06160299", "G06160301", "G06160298", "G06160302", "G06160297", "G06160300", "G06160293", "G06160295", "G06160294", "G06160296", "G06160292", "G06160289", "G06160290", "G06160291", "G06160286", "G06160287", "G06160288", "G06160280", "G06160282", "G06160284", "G06160281", "G06160285", "G06160283", "G06160277", "G06160278", "G06160273", "G06160274", "G06160276", "G06160275", "G06160279", "G06160268", "G06160272", "G06160271", "G06160269", "G06160270", "G06160264", "G06160265", "G06160267", "G06160263", "G06160266", "G06160259", "G06160258", "G06160262", "G06160260", "G06160261", "G06160254", "G06160255", "G06160256", "G06160257", "G06160249", "G06160253", "G06160252", "G06160250", "G06160251", "G06160247", "G06160248", "G06160241", "G06160242", "G06160244", "G06160246", "G06160245", "G06160243", "G06160237", "G06160238", "G06160233", "G06160239", "G06160234", "G06160240", "G06160235", "G06160236", "G06160227", "G06160228", "G06160230", "G06160226", "G06160231", "G06160232", "G06160229", "G06160219", "G06160222", "G06160224", "G06160225", "G06160218", "G06160223", "G06160220", "G06160221", "G06160215", "G06160212", "G06160216", "G06160217", "G06160214", "G06160213", "G06160209", "G06160211", "G06160207", "G06160205", "G06160208", "G06160206", "G06160210", "G06160201", "G06160202", "G06160203", "G06160197", "G06160199", "G06160204", "G06160200", "G06160198", "G06160193", "G06160194", "G06160195", "G06160190", "G06160196", "G06160191", "G06160192", "G06160186", "G06160188", "G06160187", "G06160183", "G06160189", "G06160184", "G06160185", "G06160179", "G06160180", "G06160182", "G06160176", "G06160177", "G06160178", "G06160181", "G06160175", "G06160174", "G06160170", "G06160171", "G06160168", "G06160169", "G06160172", "G06160173", "G06160163", "G06160166", "G06160162", "G06160164", "G06160165", "G06160167", "G06160158", "G06160159", "G06160160", "G06160161", "G06160154", "G06160155", "G06160156", "G06160152", "G06160148", "G06160153", "G06160149", "G06160151", "G06160150", "G06160142", "G06160145", "G06160146", "G06160147", "G06160143", "G06160144", "G06160141", "G06160137", "G06160138", "G06160140", "G06160139", "G06160132", "G06160136", "G06160131", "G06160133", "G06160135", "G06160134", "G06160130", "G06160125", "G06160129", "G06160128", "G06160126", "G06160127", "G06160122", "G06160123", "G06160124", "G06160119", "G06160118", "G06160121", "G06160120", "G06160117", "G06160112", "G06160113", "G06160115", "G06160116", "G06160114", "G06160111", "G06160107", "G06160109", "G06160105", "G06160110", "G06160106", "G06160108", "G06160104", "G06160100", "G06160102", "G06160103", "G06160099", "G06160101", "G06160094", "G06160096", "G06160097", "G06160098", "G06160095", "G06160089", "G06160088", "G06160093", "G06160090", "G06160091", "G06160092", "G06160083", "G06160085", "G06160082", "G06160084", "G06160086", "G06160087", "G06160080", "G06160081", "G06160077", "G06160078", "G06160079", "G06160076", "G06160075", "G06160070", "G06160072", "G06160073", "G06160074", "G06160069", "G06160071", "G06160068", "G06160066", "G06160067", "G06160064", "G06160063", "G06160065", "G06160060", "G06160062", "G06160061", "G06160057", "G06160058", "G06160059", "G06160055", "G06160053", "G06160056", "G06160052", "G06160054", "G06160051", "G06160050", "G06160046", "G06160045", "G06160047", "G06160048", "G06160049", "G06160039", "G06160042", "G06160044", "G06160038", "G06160043", "G06160040", "G06160041", "G06160037", "G06160032", "G06160033", "G06160034", "G06160035", "G06160031", "G06160036", "G06160026", "G06160025", "G06160028", "G06160027", "G06160029", "G06160030", "G06160024", "G06160022", "G06160023", "G06160019", "G06160020", "G06160021", "G06160016", "G06160015", "G06160017", "G06160014", "G06160013", "G06160018", "G06160006", "G06160007", "G06160011", "G06160012", "G06160008", "G06160010", "G06160005", "G06160009", "G06160004", "G06160002", "G06160003", "G06160001", "G22060901", "G22060801"];
    }

    // 订单详情去重
    function orderDetailUniq()
    {
        $max = V2WmsOrder::max('id');
        $begin = 0;
        $total = 0;
        while ($begin <= $max) {
            dump($begin);
            $end = $begin + 100;
            $res = DB::select("SELECT `code`,num FROM wms_orders WHERE tenant_id='489274' AND id>$begin AND id<=$end");
            $begin = $end;
            $res = collect($res)->keyBy('code')->toArray();
            $codes = array_keys($res);
            $list = WmsOrderDetail::whereIn('origin_code', $codes)->selectRaw("origin_code,SUM(num) as num,GROUP_CONCAT(id) as ids")->groupBy("origin_code")->get();
            $del_ids = [];
            foreach ($list as $item) {
                $order = $res[$item->origin_code];
                if ($order->num * 2 == $item->num) {
                    $total++;
                    Log::info($item->origin_code);
                    $ids = explode(',', $item->ids);
                    $len = count($ids);
                    $del_ids = array_merge($del_ids, array_slice($ids, $len / 2, $len - 1));
                }
            }
            WmsOrderDetail::whereIn('id', $del_ids)->delete();
            $begin = $end;
        }
        dump(sprintf('共去重%d条', $total));
    }


    // 更新成本价
    function updateBuyPrice()
    {
        dump("根据找回记录修正成本价");
        $arr = $this->_updateArr();
        foreach ($arr as $item) {
            $find = WmsOrderDeliverStatement::where(['id' => $item['id'], 'cost_amount' => 0])->first();
            if (!$find) continue;
            $find->update(['cost_amount' => $item['amount'], 'remark' => $find->remark . '6.6成本价找回']);
        }

        dump("修正订单的商品数量");
        DB::statement("UPDATE wms_orders o ,(
            SELECT origin_code,SUM(num) as num FROM wms_order_details GROUP BY origin_code
            ) d SET o.num=d.num WHERE o.`code`=d.origin_code AND o.num!=d.num");

        dump("更新order_item的成本价");
        DB::statement("UPDATE wms_order_items oi ,wms_inv_goods_detail inv SET oi.cost_price=inv.buy_price WHERE  oi.uniq_code=inv.uniq_code AND oi.tenant_id=489274 AND inv.tenant_id=489274 AND oi.cost_price=0");

        dump("更新订单详情的成本价");
        DB::statement("UPDATE (
            SELECT detail_id,SUM(cost_price) as amount,COUNT(id) as num FROM wms_order_items oi WHERE oi.tenant_id=489274 GROUP BY detail_id
            ) as a ,wms_order_details d  SET d.cost_price=a.amount/a.num 
            WHERE a.detail_id=d.id and d.cost_price=0");

        dump("更新报表中的商品条码");
        $max = WmsOrderDeliverStatement::max('id');
        $begin = 0;
        while ($begin <= $max) {
            dump($begin);
            $end = $begin + 1000;
            DB::statement("UPDATE wms_order_deliver_statements s,wms_spec_and_bar bar
            SET s.bar_code=bar.bar_code
            WHERE s.sku=bar.sku AND s.tenant_id=bar.tenant_id  AND s.bar_code='' and s.tenant_id=489274 and bar.tenant_id=489274 AND s.id>$begin AND s.id<=$end");
            $begin = $end;
        }

        dump("根据订单详情更新成本价");
        $max = WmsOrderDeliverStatement::max('id');
        $begin = 0;
        while ($begin <= $max) {
            dump($begin);
            $end = $begin + 100;
            $list = WmsOrderDeliverStatement::whereRaw("cost_amount=0 and id>$begin and id<=$end")->get();
            foreach ($list as $item) {
                $where = ['origin_code' => $item->origin_code, 'quality_level' => $item->quality_level, 'num' => $item->num];
                if ($item->bar_code) {
                    $where['bar_code'] = $item->bar_code;
                } else {
                    $where['sku'] = $item->sku;
                }
                if ($item->uniq_code) $where['uniq_code'] = $item->uniq_code;
                if (count($where) < 4) {
                    dump($item);
                    dump('条件不足，不能更新');
                    continue;
                }
                $find = WmsOrderDetail::where($where)->first();
                if (!$find) {
                    dump($item->toArray());
                    dump('未获取到订单详情');
                    continue;
                }
                if (!$find->cost_price) {
                    dump($item);
                    dump('成本价为空');
                    continue;
                }
                $item->update([
                    'cost_amount' => bcmul($item->num, $find->cost_price, 2),
                    'remark' => $item->remark . ' 6.6成本价找回',
                ]);
            }
            $begin = $end;
        }

        dump("有唯一码，根据唯一码找到采购收货详情，更新成本价");
        DB::statement("UPDATE wms_order_deliver_statements s,wms_recv_detail rd ,wms_arrival_regist arr SET s.cost_amount=rd.buy_price
        WHERE s.uniq_code=rd.uniq_code AND rd.arr_id=arr.id AND arr.arr_type=1 AND s.cost_amount=0 AND s.uniq_code>'' AND rd.buy_price>0 and s.tenant_id=489274 and rd.tenant_id=489274 and arr.tenant_id=489274");

        // 订单明细中有成本价，直接更新
        // dump("有唯一码，根据唯一码找到采购收货详情，更新成本价");
        // DB::statement("UPDATE wms_order_deliver_statements s,wms_order_details d 
        // SET s.cost_amount=s.num*d.cost_price,s.remark='6.5成本价找回'
        // WHERE s.origin_code=d.origin_code AND s.bar_code=d.bar_code AND s.quality_level=d.quality_level AND  s.cost_amount=0 AND s.uniq_code='' AND s.num=d.num AND d.cost_price>0 AND d.tenant_id=489274 AND s.tenant_id=489274");

        dump("根据发货明细，更新成本价");
        $list = DB::select("SELECT s.id,s.bar_code,s.origin_code,pd.request_code,s.num,pd.bar_code,pd.batch_no,pd.quality_level,SUM(pd.buy_price) as buy_price,COUNT(pd.id) as num FROM wms_order_deliver_statements s,wms_shipping_request req,wms_pre_allocation_detail pd   
        WHERE s.third_no=req.third_no AND req.request_code=pd.request_code AND s.bar_code=pd.bar_code AND s.quality_level=pd.quality_level AND pd.alloction_status IN(6,7) AND s.cost_amount=0 AND s.uniq_code='' AND pd.buy_price>0 GROUP BY s.id,pd.request_code,pd.bar_code,pd.batch_no,pd.quality_level  AND s.tenant_id=489274 AND req.tenant_id=489274 AND pd.tenant_id=489274 HAVING s.num=num ");
        foreach ($list as $item) {
            WmsOrderDeliverStatement::where('id', $item->id)->update(['cost_amount' => $item->buy_price, 'remark' => '6.6成本价找回']);
        }

        dump("根据配货订单明细，更新成本价");
        $list = DB::select("SELECT s.id,s.bar_code,s.origin_code,pd.request_code,s.num,pd.bar_code,pd.quality_level,SUM(pd.buy_price) as buy_price,COUNT(pd.id) as num FROM wms_order_deliver_statements s,wms_shipping_request req,wms_pre_allocation_detail pd   
        WHERE s.third_no=req.third_no AND req.request_code=pd.request_code AND s.bar_code=pd.bar_code AND s.quality_level=pd.quality_level AND pd.alloction_status IN(6,7) AND s.cost_amount=0 AND s.uniq_code='' AND pd.buy_price>0 GROUP BY s.id,pd.request_code,pd.bar_code,pd.quality_level  AND s.tenant_id=489274 AND req.tenant_id=489274 AND pd.tenant_id=489274 AND s.batch_no=0 HAVING s.num=num");
        foreach ($list as $item) {
            WmsOrderDeliverStatement::where('id', $item->id)->update(['cost_amount' => $item->buy_price, 'remark' => '6.6成本价找回']);
        }
    }

    function _updateArr()
    {
        return [
            ["id" =>    1, "amount" =>    7739],
            ["id" =>    2, "amount" =>    4899],
            ["id" =>    3, "amount" =>    5549],
            ["id" =>    4, "amount" =>    4799.2],
            ["id" =>    5, "amount" =>    5390],
            ["id" =>    6, "amount" =>    8119.91],
            ["id" =>    7, "amount" =>    8119.77],
            ["id" =>    8, "amount" =>    9719],
            ["id" =>    9, "amount" =>    8119.77],
            ["id" =>    10, "amount" =>    8119.77],
            ["id" =>    11, "amount" =>    8119.91],
            ["id" =>    12, "amount" =>    10241],
            ["id" =>    13, "amount" =>    10241],
            ["id" =>    14, "amount" =>    8119.84],
            ["id" =>    15, "amount" =>    8119.84],
            ["id" =>    16, "amount" =>    8119.84],
            ["id" =>    17, "amount" =>    13584],
            ["id" =>    18, "amount" =>    11440],
            ["id" =>    19, "amount" =>    31900],
            ["id" =>    20, "amount" =>    7623],
            ["id" =>    21, "amount" =>    5929],
            ["id" =>    22, "amount" =>    7623],
            ["id" =>    23, "amount" =>    7623],
            ["id" =>    24, "amount" =>    7623],
            ["id" =>    25, "amount" =>    7623],
            ["id" =>    26, "amount" =>    5929],
            ["id" =>    27, "amount" =>    3956],
            ["id" =>    28, "amount" =>    4616],
            ["id" =>    29, "amount" =>    3956],
            ["id" =>    30, "amount" =>    4616],
            ["id" =>    31, "amount" =>    5929],
            ["id" =>    32, "amount" =>    4616],
            ["id" =>    33, "amount" =>    4616],
            ["id" =>    34, "amount" =>    3956],
            ["id" =>    35, "amount" =>    4616],
            ["id" =>    36, "amount" =>    4270],
            ["id" =>    37, "amount" =>    9999],
            ["id" =>    38, "amount" =>    5983],
            ["id" =>    39, "amount" =>    7800],
            ["id" =>    40, "amount" =>    7623],
            ["id" =>    41, "amount" =>    7623],
            ["id" =>    42, "amount" =>    13188],
            ["id" =>    43, "amount" =>    30500],
            ["id" =>    44, "amount" =>    24800],
            ["id" =>    45, "amount" =>    45298],
            ["id" =>    46, "amount" =>    17000],
            ["id" =>    56, "amount" =>    18700],
            ["id" =>    57, "amount" =>    20900],
            ["id" =>    58, "amount" =>    23320],
            ["id" =>    59, "amount" =>    25080],
            ["id" =>    60, "amount" =>    18700],
            ["id" =>    61, "amount" =>    21900],
            ["id" =>    62, "amount" =>    18700],
            ["id" =>    63, "amount" =>    29150],
            ["id" =>    91, "amount" =>    119000],
            ["id" =>    92, "amount" =>    32000],
            ["id" =>    94, "amount" =>    31000],
            ["id" =>    95, "amount" =>    16000],
            ["id" =>    96, "amount" =>    32500],
            ["id" =>    97, "amount" =>    33000],
            ["id" =>    98, "amount" =>    14050],
            ["id" =>    99, "amount" =>    16500],
            ["id" =>    100, "amount" =>    18500],
            ["id" =>    101, "amount" =>    41500],
            ["id" =>    102, "amount" =>    32500],
            ["id" =>    103, "amount" =>    627],
            ["id" =>    104, "amount" =>    19800],
            ["id" =>    105, "amount" =>    19800],
            ["id" =>    106, "amount" =>    17000],
            ["id" =>    107, "amount" =>    15400],
            ["id" =>    108, "amount" =>    22671],
            ["id" =>    109, "amount" =>    19000],
            ["id" =>    110, "amount" =>    15500],
            ["id" =>    111, "amount" =>    15325],
            ["id" =>    112, "amount" =>    29090],
            ["id" =>    113, "amount" =>    21000],
            ["id" =>    114, "amount" =>    17998],
            ["id" =>    115, "amount" =>    17002],
            ["id" =>    116, "amount" =>    29500],
            ["id" =>    117, "amount" =>    27000],
            ["id" =>    118, "amount" =>    10010],
            ["id" =>    119, "amount" =>    9100],
            ["id" =>    120, "amount" =>    10241],
            ["id" =>    121, "amount" =>    47000],
            ["id" =>    122, "amount" =>    8119.91],
            ["id" =>    123, "amount" =>    10241],
            ["id" =>    124, "amount" =>    10241],
            ["id" =>    125, "amount" =>    21000],
            ["id" =>    126, "amount" =>    21000],
            ["id" =>    127, "amount" =>    21000],
            ["id" =>    128, "amount" =>    21000],
            ["id" =>    129, "amount" =>    21000],
            ["id" =>    130, "amount" =>    27000],
            ["id" =>    134, "amount" =>    14438],
            ["id" =>    135, "amount" =>    14438],
            ["id" =>    136, "amount" =>    14438],
            ["id" =>    137, "amount" =>    14438],
            ["id" =>    138, "amount" =>    14025],
            ["id" =>    139, "amount" =>    17400],
            ["id" =>    140, "amount" =>    17400],
            ["id" =>    141, "amount" =>    4616],
            ["id" =>    142, "amount" =>    6600],
            ["id" =>    143, "amount" =>    4616],
            ["id" =>    144, "amount" =>    8119.91],
            ["id" =>    145, "amount" =>    5929],
            ["id" =>    146, "amount" =>    9029],
            ["id" =>    147, "amount" =>    7191],
            ["id" =>    148, "amount" =>    7854],
            ["id" =>    149, "amount" =>    7920],
            ["id" =>    150, "amount" =>    12600],
            ["id" =>    151, "amount" =>    22000],
            ["id" =>    152, "amount" =>    12000],
            ["id" =>    153, "amount" =>    55000],
            ["id" =>    154, "amount" =>    37950],
            ["id" =>    155, "amount" =>    31900],
            ["id" =>    156, "amount" =>    14000],
            ["id" =>    157, "amount" =>    16000],
            ["id" =>    158, "amount" =>    60000],
            ["id" =>    159, "amount" =>    5929],
            ["id" =>    160, "amount" =>    55000],
            ["id" =>    161, "amount" =>    12500],
            ["id" =>    162, "amount" =>    5800],
            ["id" =>    163, "amount" =>    16000],
            ["id" =>    164, "amount" =>    14900],
            ["id" =>    165, "amount" =>    63700],
            ["id" =>    166, "amount" =>    4616],
            ["id" =>    167, "amount" =>    980],
            ["id" =>    168, "amount" =>    4616],
            ["id" =>    169, "amount" =>    31900],
            ["id" =>    170, "amount" =>    28600],
            ["id" =>    171, "amount" =>    8099],
            ["id" =>    172, "amount" =>    38000],
            ["id" =>    173, "amount" =>    27500],
            ["id" =>    174, "amount" =>    28500],
            ["id" =>    175, "amount" =>    20000],
            ["id" =>    176, "amount" =>    38000],
            ["id" =>    177, "amount" =>    5599],
            ["id" =>    178, "amount" =>    40000],
            ["id" =>    179, "amount" =>    9099.62],
            ["id" =>    180, "amount" =>    75000],
            ["id" =>    181, "amount" =>    17002],
            ["id" =>    182, "amount" =>    36000],
            ["id" =>    183, "amount" =>    22000],
            ["id" =>    184, "amount" =>    20450],
            ["id" =>    185, "amount" =>    20000],
            ["id" =>    186, "amount" =>    12500],
            ["id" =>    187, "amount" =>    18500],
            ["id" =>    188, "amount" =>    25597],
            ["id" =>    189, "amount" =>    18700],
            ["id" =>    190, "amount" =>    21000],
            ["id" =>    191, "amount" =>    50000],
            ["id" =>    192, "amount" =>    11000],
            ["id" =>    193, "amount" =>    55900],
            ["id" =>    194, "amount" =>    29000],
            ["id" =>    195, "amount" =>    19000],
            ["id" =>    196, "amount" =>    18500],
            ["id" =>    197, "amount" =>    22000],
            ["id" =>    198, "amount" =>    20000],
            ["id" =>    199, "amount" =>    19000],
            ["id" =>    200, "amount" =>    34000],
            ["id" =>    201, "amount" =>    18957],
            ["id" =>    202, "amount" =>    8120],
            ["id" =>    203, "amount" =>    12320],
            ["id" =>    204, "amount" =>    14000],
            ["id" =>    205, "amount" =>    13590],
            ["id" =>    206, "amount" =>    18000],
            ["id" =>    207, "amount" =>    26000],
            ["id" =>    208, "amount" =>    94000],
            ["id" =>    209, "amount" =>    7860],
            ["id" =>    210, "amount" =>    42000],
            ["id" =>    211, "amount" =>    15599],
            ["id" =>    212, "amount" =>    46000],
            ["id" =>    213, "amount" =>    11000],
            ["id" =>    214, "amount" =>    56430],
            ["id" =>    215, "amount" =>    21465],
            ["id" =>    216, "amount" =>    42500],
            ["id" =>    217, "amount" =>    9839],
            ["id" =>    218, "amount" =>    95000],
            ["id" =>    219, "amount" =>    36500],
            ["id" =>    220, "amount" =>    15950],
            ["id" =>    221, "amount" =>    27000],
            ["id" =>    222, "amount" =>    18500],
            ["id" =>    232, "amount" =>    13900],
            ["id" =>    233, "amount" =>    18000],
            ["id" =>    234, "amount" =>    7623],
            ["id" =>    235, "amount" =>    174000],
            ["id" =>    236, "amount" =>    20515],
            ["id" =>    237, "amount" =>    9500],
            ["id" =>    238, "amount" =>    39845],
            ["id" =>    239, "amount" =>    13200],
            ["id" =>    240, "amount" =>    17489],
            ["id" =>    241, "amount" =>    68888],
            ["id" =>    242, "amount" =>    13860],
            ["id" =>    243, "amount" =>    4616],
            ["id" =>    244, "amount" =>    5039],
            ["id" =>    245, "amount" =>    3977],
            ["id" =>    246, "amount" =>    4616],
            ["id" =>    247, "amount" =>    4319],
            ["id" =>    248, "amount" =>    8119.91],
            ["id" =>    249, "amount" =>    4401],
            ["id" =>    250, "amount" =>    6279],
            ["id" =>    251, "amount" =>    8119.84],
            ["id" =>    252, "amount" =>    11550],
            ["id" =>    253, "amount" =>    5983],
            ["id" =>    254, "amount" =>    8119.84],
            ["id" =>    255, "amount" =>    6622],
            ["id" =>    256, "amount" =>    3956],
            ["id" =>    257, "amount" =>    11550],
            ["id" =>    258, "amount" =>    5717],
            ["id" =>    259, "amount" =>    4616],
            ["id" =>    260, "amount" =>    6352.5],
            ["id" =>    261, "amount" =>    5039],
            ["id" =>    262, "amount" =>    8712],
            ["id" =>    263, "amount" =>    3834],
            ["id" =>    264, "amount" =>    3958.2],
            ["id" =>    265, "amount" =>    4616],
            ["id" =>    266, "amount" =>    6352.5],
            ["id" =>    267, "amount" =>    4616],
            ["id" =>    268, "amount" =>    5605],
            ["id" =>    269, "amount" =>    5605],
            ["id" =>    270, "amount" =>    8712],
            ["id" =>    271, "amount" =>    3958.2],
            ["id" =>    272, "amount" =>    3958.2],
            ["id" =>    273, "amount" =>    6352.5],
            ["id" =>    274, "amount" =>    3956],
            ["id" =>    275, "amount" =>    3956],
            ["id" =>    276, "amount" =>    11339],
            ["id" =>    277, "amount" =>    6267],
            ["id" =>    278, "amount" =>    32958],
            ["id" =>    279, "amount" =>    17580],
            ["id" =>    280, "amount" =>    31299],
            ["id" =>    281, "amount" =>    42000],
            ["id" =>    282, "amount" =>    15950],
            ["id" =>    283, "amount" =>    20700],
            ["id" =>    284, "amount" =>    38000],
            ["id" =>    285, "amount" =>    32000],
            ["id" =>    286, "amount" =>    6352.5],
            ["id" =>    287, "amount" =>    20130],
            ["id" =>    288, "amount" =>    12000],
            ["id" =>    289, "amount" =>    13200],
            ["id" =>    290, "amount" =>    20000],
            ["id" =>    291, "amount" =>    99800],
            ["id" =>    292, "amount" =>    21000],
            ["id" =>    293, "amount" =>    28290],
            ["id" =>    294, "amount" =>    706],
            ["id" =>    295, "amount" =>    20000],
            ["id" =>    296, "amount" =>    18500],
            ["id" =>    297, "amount" =>    183032],
            ["id" =>    298, "amount" =>    18000],
            ["id" =>    299, "amount" =>    17500],
            ["id" =>    300, "amount" =>    27500],
            ["id" =>    301, "amount" =>    24000],
            ["id" =>    302, "amount" =>    78000],
            ["id" =>    303, "amount" =>    40500],
            ["id" =>    304, "amount" =>    37635],
            ["id" =>    305, "amount" =>    15000],
            ["id" =>    306, "amount" =>    13090],
            ["id" =>    307, "amount" =>    14679],
            ["id" =>    308, "amount" =>    17050],
            ["id" =>    309, "amount" =>    40000],
            ["id" =>    310, "amount" =>    8250],
            ["id" =>    311, "amount" =>    8119.77],
            ["id" =>    312, "amount" =>    8119.91],
            ["id" =>    313, "amount" =>    8119.84],
            ["id" =>    314, "amount" =>    8119.84],
            ["id" =>    315, "amount" =>    3958.2],
            ["id" =>    316, "amount" =>    4615],
            ["id" =>    317, "amount" =>    6600],
            ["id" =>    318, "amount" =>    46260],
            ["id" =>    319, "amount" =>    19000],
            ["id" =>    320, "amount" =>    18000],
            ["id" =>    321, "amount" =>    19800],
            ["id" =>    322, "amount" =>    19800],
            ["id" =>    323, "amount" =>    21000],
            ["id" =>    324, "amount" =>    14500],
            ["id" =>    325, "amount" =>    51285],
            ["id" =>    326, "amount" =>    40000],
            ["id" =>    327, "amount" =>    21465],
            ["id" =>    328, "amount" =>    14001],
            ["id" =>    329, "amount" =>    22000],
            ["id" =>    330, "amount" =>    63780],
            ["id" =>    331, "amount" =>    18000],
            ["id" =>    332, "amount" =>    54500],
            ["id" =>    333, "amount" =>    18000],
            ["id" =>    334, "amount" =>    18500],
            ["id" =>    335, "amount" =>    19500],
            ["id" =>    336, "amount" =>    17500],
            ["id" =>    337, "amount" =>    27000],
            ["id" =>    338, "amount" =>    24886],
            ["id" =>    339, "amount" =>    34500],
            ["id" =>    340, "amount" =>    9245],
            ["id" =>    342, "amount" =>    18500],
            ["id" =>    344, "amount" =>    12535],
            ["id" =>    345, "amount" =>    21800],
            ["id" =>    346, "amount" =>    35000],
            ["id" =>    347, "amount" =>    16000],
            ["id" =>    348, "amount" =>    27000],
            ["id" =>    349, "amount" =>    30900],
            ["id" =>    368, "amount" =>    15500],
            ["id" =>    369, "amount" =>    105500],
            ["id" =>    370, "amount" =>    15400],
            ["id" =>    371, "amount" =>    20501],
            ["id" =>    372, "amount" =>    149100],
            ["id" =>    373, "amount" =>    16500],
            ["id" =>    374, "amount" =>    13090],
            ["id" =>    375, "amount" =>    25630],
            ["id" =>    376, "amount" =>    19000],
            ["id" =>    377, "amount" =>    19000],
            ["id" =>    378, "amount" =>    35000],
            ["id" =>    379, "amount" =>    33540],
            ["id" =>    380, "amount" =>    23000],
            ["id" =>    381, "amount" =>    38000],
            ["id" =>    382, "amount" =>    65000],
            ["id" =>    383, "amount" =>    99800],
            ["id" =>    384, "amount" =>    7419.4],
            ["id" =>    385, "amount" =>    116900],
            ["id" =>    387, "amount" =>    57396],
            ["id" =>    388, "amount" =>    16500],
            ["id" =>    389, "amount" =>    13018],
            ["id" =>    390, "amount" =>    28500],
            ["id" =>    391, "amount" =>    19000],
            ["id" =>    392, "amount" =>    19000],
            ["id" =>    393, "amount" =>    20969],
            ["id" =>    394, "amount" =>    61000],
            ["id" =>    395, "amount" =>    53384],
            ["id" =>    396, "amount" =>    15931],
            ["id" =>    397, "amount" =>    55000],
            ["id" =>    398, "amount" =>    114597],
            ["id" =>    399, "amount" =>    34065],
            ["id" =>    400, "amount" =>    20130],
            ["id" =>    401, "amount" =>    168900],
            ["id" =>    402, "amount" =>    24996],
            ["id" =>    403, "amount" =>    28000],
            ["id" =>    404, "amount" =>    56800.8],
            ["id" =>    405, "amount" =>    14640],
            ["id" =>    406, "amount" =>    18303],
            ["id" =>    407, "amount" =>    53000],
            ["id" =>    408, "amount" =>    17050],
            ["id" =>    409, "amount" =>    50400],
            ["id" =>    410, "amount" =>    28000],
            ["id" =>    411, "amount" =>    16000],
            ["id" =>    412, "amount" =>    35500],
            ["id" =>    413, "amount" =>    10700],
            ["id" =>    414, "amount" =>    33000],
            ["id" =>    415, "amount" =>    17487],
            ["id" =>    416, "amount" =>    32000],
            ["id" =>    417, "amount" =>    34000],
            ["id" =>    418, "amount" =>    14865],
            ["id" =>    419, "amount" =>    32500],
            ["id" =>    420, "amount" =>    20000],
            ["id" =>    421, "amount" =>    39000],
            ["id" =>    422, "amount" =>    59790],
            ["id" =>    423, "amount" =>    28600],
            ["id" =>    424, "amount" =>    15142],
            ["id" =>    425, "amount" =>    24530],
            ["id" =>    426, "amount" =>    17800],
            ["id" =>    427, "amount" =>    67200],
            ["id" =>    428, "amount" =>    48500],
            ["id" =>    429, "amount" =>    17600],
            ["id" =>    430, "amount" =>    45000],
            ["id" =>    431, "amount" =>    118000],
            ["id" =>    432, "amount" =>    75000],
            ["id" =>    433, "amount" =>    22500],
            ["id" =>    434, "amount" =>    50000],
            ["id" =>    435, "amount" =>    12650],
            ["id" =>    436, "amount" =>    12000],
            ["id" =>    438, "amount" =>    64000],
            ["id" =>    439, "amount" =>    18500],
            ["id" =>    440, "amount" =>    5410],
            ["id" =>    442, "amount" =>    37199],
            ["id" =>    443, "amount" =>    23696],
            ["id" =>    444, "amount" =>    33000],
            ["id" =>    445, "amount" =>    33500],
            ["id" =>    446, "amount" =>    29000],
            ["id" =>    447, "amount" =>    30000],
            ["id" =>    455, "amount" =>    22000],
            ["id" =>    456, "amount" =>    33500],
            ["id" =>    457, "amount" =>    33500],
            ["id" =>    458, "amount" =>    33000],
            ["id" =>    459, "amount" =>    34000],
            ["id" =>    460, "amount" =>    6299.35],
            ["id" =>    461, "amount" =>    6299.35],
            ["id" =>    462, "amount" =>    6299.35],
            ["id" =>    463, "amount" =>    3499],
            ["id" =>    464, "amount" =>    3499],
            ["id" =>    465, "amount" =>    6998],
            ["id" =>    466, "amount" =>    3499],
            ["id" =>    467, "amount" =>    7710.99],
            ["id" =>    468, "amount" =>    7710.99],
            ["id" =>    469, "amount" =>    11000],
            ["id" =>    470, "amount" =>    21000],
            ["id" =>    471, "amount" =>    21000],
            ["id" =>    472, "amount" =>    18000],
            ["id" =>    473, "amount" =>    18000],
            ["id" =>    474, "amount" =>    36000],
            ["id" =>    475, "amount" =>    36000],
            ["id" =>    476, "amount" =>    36000],
            ["id" =>    477, "amount" =>    36000],
            ["id" =>    478, "amount" =>    30800],
            ["id" =>    479, "amount" =>    30800],
            ["id" =>    480, "amount" =>    26180],
            ["id" =>    481, "amount" =>    26180],
            ["id" =>    482, "amount" =>    13090],
            ["id" =>    483, "amount" =>    13800],
            ["id" =>    484, "amount" =>    13800],
            ["id" =>    485, "amount" =>    13200],
            ["id" =>    486, "amount" =>    6600],
            ["id" =>    487, "amount" =>    6600],
            ["id" =>    488, "amount" =>    10479],
            ["id" =>    489, "amount" =>    26479],
            ["id" =>    490, "amount" =>    10479],
            ["id" =>    491, "amount" =>    10479],
            ["id" =>    492, "amount" =>    10479],
            ["id" =>    493, "amount" =>    10479],
            ["id" =>    494, "amount" =>    10479],
            ["id" =>    495, "amount" =>    9440],
            ["id" =>    496, "amount" =>    9440],
            ["id" =>    497, "amount" =>    9440],
            ["id" =>    498, "amount" =>    4620],
            ["id" =>    507, "amount" =>    18700],
            ["id" =>    508, "amount" =>    19000],
            ["id" =>    509, "amount" =>    11935],
            ["id" =>    510, "amount" =>    11339],
            ["id" =>    549, "amount" =>    1489],
            ["id" =>    613, "amount" =>    33500],
            ["id" =>    631, "amount" =>    13860],
            ["id" =>    648, "amount" =>    33000],
            ["id" =>    649, "amount" =>    18900],
            ["id" =>    650, "amount" =>    34500],
            ["id" =>    651, "amount" =>    21500],
            ["id" =>    652, "amount" =>    40500],
            ["id" =>    653, "amount" =>    48800],
            ["id" =>    654, "amount" =>    41999],
            ["id" =>    655, "amount" =>    52500],
            ["id" =>    656, "amount" =>    20000],
            ["id" =>    657, "amount" =>    42000],
            ["id" =>    658, "amount" =>    16500],
            ["id" =>    659, "amount" =>    32500],
            ["id" =>    660, "amount" =>    149100],
            ["id" =>    661, "amount" =>    8119.91],
            ["id" =>    662, "amount" =>    8119.91],
            ["id" =>    663, "amount" =>    8119.77],
            ["id" =>    664, "amount" =>    18700],
            ["id" =>    665, "amount" =>    230000],
            ["id" =>    666, "amount" =>    37000],
            ["id" =>    667, "amount" =>    34900],
            ["id" =>    668, "amount" =>    42000],
            ["id" =>    669, "amount" =>    59000],
            ["id" =>    670, "amount" =>    18700],
            ["id" =>    671, "amount" =>    32500],
            ["id" =>    672, "amount" =>    21000],
            ["id" =>    673, "amount" =>    114800],
            ["id" =>    674, "amount" =>    20500],
            ["id" =>    675, "amount" =>    10000],
            ["id" =>    676, "amount" =>    38000],
            ["id" =>    677, "amount" =>    144989],
            ["id" =>    678, "amount" =>    33500],
            ["id" =>    679, "amount" =>    15290],
            ["id" =>    680, "amount" =>    42000],
            ["id" =>    681, "amount" =>    14000],
            ["id" =>    682, "amount" =>    63990],
            ["id" =>    683, "amount" =>    16536],
            ["id" =>    684, "amount" =>    18500],
            ["id" =>    685, "amount" =>    50000],
            ["id" =>    686, "amount" =>    20350],
            ["id" =>    687, "amount" =>    21000],
            ["id" =>    688, "amount" =>    18700],
            ["id" =>    691, "amount" =>    31500],
            ["id" =>    692, "amount" =>    84000],
            ["id" =>    693, "amount" =>    105000],
            ["id" =>    694, "amount" =>    168000],
            ["id" =>    695, "amount" =>    105000],
            ["id" =>    696, "amount" =>    73500],
            ["id" =>    697, "amount" =>    42000],
            ["id" =>    698, "amount" =>    21000],
            ["id" =>    699, "amount" =>    10500],
            ["id" =>    700, "amount" =>    14000],
            ["id" =>    701, "amount" =>    35000],
            ["id" =>    702, "amount" =>    35000],
            ["id" =>    703, "amount" =>    119000],
            ["id" =>    704, "amount" =>    28000],
            ["id" =>    705, "amount" =>    70000],
            ["id" =>    706, "amount" =>    14000],
            ["id" =>    707, "amount" =>    21000],
            ["id" =>    708, "amount" =>    84000],
            ["id" =>    709, "amount" =>    60000],
            ["id" =>    710, "amount" =>    144000],
            ["id" =>    711, "amount" =>    132000],
            ["id" =>    712, "amount" =>    96000],
            ["id" =>    713, "amount" =>    96000],
            ["id" =>    714, "amount" =>    36000],
            ["id" =>    715, "amount" =>    6000],
            ["id" =>    716, "amount" =>    24000],
            ["id" =>    717, "amount" =>    61890],
            ["id" =>    718, "amount" =>    21670],
            ["id" =>    719, "amount" =>    16136.7],
            ["id" =>    720, "amount" =>    20082],
            ["id" =>    721, "amount" =>    43440],
            ["id" =>    722, "amount" =>    15179],
            ["id" =>    723, "amount" =>    14399],
            ["id" =>    724, "amount" =>    238000],
            ["id" =>    725, "amount" =>    19134],
            ["id" =>    726, "amount" =>    31900],
            ["id" =>    727, "amount" =>    19000],
            ["id" =>    728, "amount" =>    70000],
            ["id" =>    729, "amount" =>    21800],
            ["id" =>    730, "amount" =>    12650],
            ["id" =>    731, "amount" =>    15400],
            ["id" =>    732, "amount" =>    18500],
            ["id" =>    733, "amount" =>    17593],
            ["id" =>    734, "amount" =>    16652],
            ["id" =>    735, "amount" =>    14150],
            ["id" =>    736, "amount" =>    7690],
            ["id" =>    737, "amount" =>    7419.12],
            ["id" =>    738, "amount" =>    10429],
            ["id" =>    739, "amount" =>    16500],
            ["id" =>    740, "amount" =>    17050],
            ["id" =>    741, "amount" =>    709],
            ["id" =>    742, "amount" =>    22000],
            ["id" =>    743, "amount" =>    36650],
            ["id" =>    744, "amount" =>    17587],
            ["id" =>    745, "amount" =>    12936],
            ["id" =>    746, "amount" =>    31898],
            ["id" =>    747, "amount" =>    15937],
            ["id" =>    748, "amount" =>    15000],
            ["id" =>    749, "amount" =>    15554],
            ["id" =>    750, "amount" =>    36000],
            ["id" =>    751, "amount" =>    7000],
            ["id" =>    752, "amount" =>    63000],
            ["id" =>    753, "amount" =>    18500],
            ["id" =>    754, "amount" =>    21000],
            ["id" =>    755, "amount" =>    19000],
            ["id" =>    756, "amount" =>    15883],
            ["id" =>    757, "amount" =>    33500],
            ["id" =>    758, "amount" =>    21670],
            ["id" =>    759, "amount" =>    6299],
            ["id" =>    760, "amount" =>    23000],
            ["id" =>    761, "amount" =>    116900],
            ["id" =>    762, "amount" =>    36000],
            ["id" =>    763, "amount" =>    21598],
            ["id" =>    764, "amount" =>    110000],
            ["id" =>    765, "amount" =>    44500],
            ["id" =>    766, "amount" =>    62500],
            ["id" =>    767, "amount" =>    50000],
            ["id" =>    768, "amount" =>    12600],
            ["id" =>    769, "amount" =>    12600],
            ["id" =>    770, "amount" =>    151200],
            ["id" =>    771, "amount" =>    138600],
            ["id" =>    772, "amount" =>    189000],
            ["id" =>    773, "amount" =>    176400],
            ["id" =>    774, "amount" =>    25200],
            ["id" =>    775, "amount" =>    162788],
            ["id" =>    776, "amount" =>    80000],
            ["id" =>    777, "amount" =>    45000],
            ["id" =>    778, "amount" =>    39000],
            ["id" =>    779, "amount" =>    21454],
            ["id" =>    787, "amount" =>    145890],
            ["id" =>    788, "amount" =>    25000],
            ["id" =>    789, "amount" =>    15500],
            ["id" =>    790, "amount" =>    10558],
            ["id" =>    791, "amount" =>    36222.97],
            ["id" =>    792, "amount" =>    31074],
            ["id" =>    793, "amount" =>    57000],
            ["id" =>    794, "amount" =>    52800],
            ["id" =>    795, "amount" =>    21000],
            ["id" =>    796, "amount" =>    62940],
            ["id" =>    797, "amount" =>    9798],
            ["id" =>    798, "amount" =>    34293],
            ["id" =>    799, "amount" =>    78384],
            ["id" =>    800, "amount" =>    146970],
            ["id" =>    801, "amount" =>    48990],
            ["id" =>    802, "amount" =>    39192],
            ["id" =>    803, "amount" =>    29394],
            ["id" =>    804, "amount" =>    19000],
            ["id" =>    805, "amount" =>    29000],
            ["id" =>    806, "amount" =>    18997],
            ["id" =>    807, "amount" =>    33500],
            ["id" =>    808, "amount" =>    33000],
            ["id" =>    809, "amount" =>    31900],
            ["id" =>    810, "amount" =>    39000],
            ["id" =>    811, "amount" =>    21500],
            ["id" =>    812, "amount" =>    27500],
            ["id" =>    813, "amount" =>    20350],
            ["id" =>    814, "amount" =>    18898],
            ["id" =>    815, "amount" =>    43880],
            ["id" =>    816, "amount" =>    21400],
            ["id" =>    817, "amount" =>    20350],
            ["id" =>    818, "amount" =>    19300],
            ["id" =>    819, "amount" =>    21900],
            ["id" =>    820, "amount" =>    16392],
            ["id" =>    821, "amount" =>    22000],
            ["id" =>    822, "amount" =>    15000],
            ["id" =>    823, "amount" =>    12000],
            ["id" =>    824, "amount" =>    12000],
            ["id" =>    825, "amount" =>    15000],
            ["id" =>    826, "amount" =>    19000],
            ["id" =>    827, "amount" =>    30500],
            ["id" =>    828, "amount" =>    13667],
            ["id" =>    829, "amount" =>    56697.12],
            ["id" =>    830, "amount" =>    25199.28],
            ["id" =>    831, "amount" =>    62997.2],
            ["id" =>    832, "amount" =>    46189],
            ["id" =>    833, "amount" =>    58786],
            ["id" =>    834, "amount" =>    16796],
            ["id" =>    835, "amount" =>    8398],
            ["id" =>    836, "amount" =>    33592],
            ["id" =>    837, "amount" =>    23500],
            ["id" =>    838, "amount" =>    479061.12],
            ["id" =>    839, "amount" =>    389744.16],
            ["id" =>    840, "amount" =>    3499.3],
            ["id" =>    841, "amount" =>    18898.74],
            ["id" =>    842, "amount" =>    37797.78],
            ["id" =>    843, "amount" =>    6300],
            ["id" =>    844, "amount" =>    31498.15],
            ["id" =>    845, "amount" =>    56695.59],
            ["id" =>    846, "amount" =>    37797.78],
            ["id" =>    847, "amount" =>    18898.74],
            ["id" =>    848, "amount" =>    278460],
            ["id" =>    849, "amount" =>    158340],
            ["id" =>    850, "amount" =>    16796],
            ["id" =>    851, "amount" =>    20995],
            ["id" =>    852, "amount" =>    50388],
            ["id" =>    853, "amount" =>    54587],
            ["id" =>    854, "amount" =>    16796],
            ["id" =>    855, "amount" =>    20995],
            ["id" =>    856, "amount" =>    54587],
            ["id" =>    857, "amount" =>    33592],
            ["id" =>    858, "amount" =>    20995],
            ["id" =>    859, "amount" =>    73078.56],
            ["id" =>    860, "amount" =>    105558.57],
            ["id" =>    861, "amount" =>    105558.57],
            ["id" =>    862, "amount" =>    138038.47],
            ["id" =>    863, "amount" =>    211118.44],
            ["id" =>    864, "amount" =>    154278.48],
            ["id" =>    865, "amount" =>    89318.57],
            ["id" =>    866, "amount" =>    40598.85],
            ["id" =>    867, "amount" =>    73078.56],
            ["id" =>    868, "amount" =>    33282.97],
            ["id" =>    869, "amount" =>    11658],
            ["id" =>    870, "amount" =>    17487],
            ["id" =>    871, "amount" =>    17487],
            ["id" =>    872, "amount" =>    4199],
            ["id" =>    873, "amount" =>    4199],
            ["id" =>    874, "amount" =>    20995],
            ["id" =>    875, "amount" =>    71383],
            ["id" =>    876, "amount" =>    54587],
            ["id" =>    877, "amount" =>    3499],
            ["id" =>    878, "amount" =>    17495],
            ["id" =>    879, "amount" =>    20994],
            ["id" =>    880, "amount" =>    31491],
            ["id" =>    881, "amount" =>    5271.2],
            ["id" =>    882, "amount" =>    5271.2],
            ["id" =>    883, "amount" =>    5271.2],
            ["id" =>    884, "amount" =>    10542.4],
            ["id" =>    885, "amount" =>    10542.4],
            ["id" =>    886, "amount" =>    5271.2],
            ["id" =>    887, "amount" =>    5271.2],
            ["id" =>    888, "amount" =>    10542.4],
            ["id" =>    889, "amount" =>    5271.2],
            ["id" =>    890, "amount" =>    5271.2],
            ["id" =>    891, "amount" =>    35000],
            ["id" =>    892, "amount" =>    112000],
            ["id" =>    893, "amount" =>    27995],
            ["id" =>    894, "amount" =>    39193],
            ["id" =>    895, "amount" =>    11198],
            ["id" =>    896, "amount" =>    5599],
            ["id" =>    897, "amount" =>    5599],
            ["id" =>    898, "amount" =>    11198],
            ["id" =>    899, "amount" =>    5599],
            ["id" =>    900, "amount" =>    22396],
            ["id" =>    901, "amount" =>    39193],
            ["id" =>    902, "amount" =>    39193],
            ["id" =>    903, "amount" =>    11198],
            ["id" =>    904, "amount" =>    4899],
            ["id" =>    905, "amount" =>    9798],
            ["id" =>    906, "amount" =>    53889],
            ["id" =>    907, "amount" =>    34293],
            ["id" =>    908, "amount" =>    9798],
            ["id" =>    909, "amount" =>    115000],
            ["id" =>    910, "amount" =>    39295],
            ["id" =>    911, "amount" =>    39295],
            ["id" =>    912, "amount" =>    157180],
            ["id" =>    913, "amount" =>    78590],
            ["id" =>    914, "amount" =>    5967],
            ["id" =>    915, "amount" =>    5967],
            ["id" =>    916, "amount" =>    5967],
            ["id" =>    917, "amount" =>    5967],
            ["id" =>    918, "amount" =>    141888.12],
            ["id" =>    919, "amount" =>    42800],
            ["id" =>    920, "amount" =>    138454],
            ["id" =>    926, "amount" =>    63000],
            ["id" =>    927, "amount" =>    105000],
            ["id" =>    928, "amount" =>    28000],
            ["id" =>    929, "amount" =>    26406.12],
            ["id" =>    930, "amount" =>    12598.6],
            ["id" =>    931, "amount" =>    6299.43],
            ["id" =>    932, "amount" =>    37795.26],
            ["id" =>    933, "amount" =>    88189.5],
            ["id" =>    939, "amount" =>    41573],
            ["id" =>    940, "amount" =>    47512],
            ["id" =>    941, "amount" =>    89085],
            ["id" =>    942, "amount" =>    7419],
            ["id" =>    943, "amount" =>    14838],
            ["id" =>    944, "amount" =>    22257],
            ["id" =>    945, "amount" =>    51933],
            ["id" =>    946, "amount" =>    59352],
            ["id" =>    947, "amount" =>    51933],
            ["id" =>    948, "amount" =>    44514],
            ["id" =>    949, "amount" =>    44514],
            ["id" =>    950, "amount" =>    22257],
            ["id" =>    951, "amount" =>    24578.4],
            ["id" =>    952, "amount" =>    14044.8],
            ["id" =>    953, "amount" =>    7022.4],
            ["id" =>    954, "amount" =>    14044.8],
            ["id" =>    955, "amount" =>    10533.6],
            ["id" =>    956, "amount" =>    14044.8],
            ["id" =>    957, "amount" =>    10533.6],
            ["id" =>    958, "amount" =>    22396],
            ["id" =>    959, "amount" =>    44792],
            ["id" =>    960, "amount" =>    44792],
            ["id" =>    961, "amount" =>    16797],
            ["id" =>    962, "amount" =>    16797],
            ["id" =>    963, "amount" =>    13995],
            ["id" =>    964, "amount" =>    36387],
            ["id" =>    965, "amount" =>    13995],
            ["id" =>    966, "amount" =>    5598],
            ["id" =>    967, "amount" =>    19593],
            ["id" =>    968, "amount" =>    11196],
            ["id" =>    969, "amount" =>    184485],
            ["id" =>    970, "amount" =>    61495],
            ["id" =>    971, "amount" =>    73794],
            ["id" =>    972, "amount" =>    122990],
            ["id" =>    973, "amount" =>    234240],
            ["id" =>    974, "amount" =>    312320],
            ["id" =>    975, "amount" =>    117120],
            ["id" =>    976, "amount" =>    195200],
            ["id" =>    977, "amount" =>    18897.54],
            ["id" =>    978, "amount" =>    37794.84],
            ["id" =>    979, "amount" =>    6299.37],
            ["id" =>    980, "amount" =>    69291.42],
            ["id" =>    981, "amount" =>    151181.52],
            ["id" =>    982, "amount" =>    170079.21],
            ["id" =>    983, "amount" =>    188976.9],
            ["id" =>    984, "amount" =>    188976.9],
            ["id" =>    985, "amount" =>    94488],
            ["id" =>    986, "amount" =>    30000],
            ["id" =>    987, "amount" =>    79560],
            ["id" =>    988, "amount" =>    79560],
            ["id" =>    989, "amount" =>    119340],
            ["id" =>    990, "amount" =>    119340],
            ["id" =>    991, "amount" =>    12597],
            ["id" =>    992, "amount" =>    8398],
            ["id" =>    993, "amount" =>    33592],
            ["id" =>    994, "amount" =>    75582],
            ["id" =>    995, "amount" =>    79781],
            ["id" =>    996, "amount" =>    50388],
            ["id" =>    997, "amount" =>    25194],
            ["id" =>    998, "amount" =>    46189],
            ["id" =>    999, "amount" =>    8398],
            ["id" =>    1096, "amount" =>    25200],
            ["id" =>    1097, "amount" =>    25200],
            ["id" =>    1098, "amount" =>    4616],
            ["id" =>    1099, "amount" =>    4319],
            ["id" =>    1101, "amount" =>    20000],
            ["id" =>    1102, "amount" =>    33540],
            ["id" =>    1103, "amount" =>    90000],
            ["id" =>    1104, "amount" =>    15000],
            ["id" =>    1114, "amount" =>    48000],
            ["id" =>    1115, "amount" =>    51500],
            ["id" =>    1116, "amount" =>    50500],
            ["id" =>    1117, "amount" =>    35000],
            ["id" =>    1118, "amount" =>    20130],
            ["id" =>    1119, "amount" =>    40000],
            ["id" =>    1120, "amount" =>    15000],
            ["id" =>    1121, "amount" =>    22000],
            ["id" =>    1122, "amount" =>    244000],
            ["id" =>    1123, "amount" =>    18700],
            ["id" =>    1124, "amount" =>    240000],
            ["id" =>    1125, "amount" =>    210000],
            ["id" =>    1126, "amount" =>    27500],
            ["id" =>    1127, "amount" =>    18500],
            ["id" =>    1128, "amount" =>    22500],
            ["id" =>    1129, "amount" =>    16500],
            ["id" =>    1130, "amount" =>    23500],
            ["id" =>    1131, "amount" =>    123000],
            ["id" =>    1132, "amount" =>    147500],
            ["id" =>    1133, "amount" =>    47000],
            ["id" =>    1134, "amount" =>    4620],
            ["id" =>    1135, "amount" =>    23320],
            ["id" =>    1136, "amount" =>    25080],
            ["id" =>    1137, "amount" =>    17875],
            ["id" =>    1138, "amount" =>    23320],
            ["id" =>    1139, "amount" =>    29150],
            ["id" =>    1140, "amount" =>    29150],
            ["id" =>    1141, "amount" =>    18700],
            ["id" =>    1142, "amount" =>    18050],
            ["id" =>    1143, "amount" =>    12705],
            ["id" =>    1144, "amount" =>    14000],
            ["id" =>    1145, "amount" =>    29150],
            ["id" =>    1146, "amount" =>    17490],
            ["id" =>    1147, "amount" =>    21900],
            ["id" =>    1148, "amount" =>    18700],
            ["id" =>    1149, "amount" =>    47000],
            ["id" =>    1150, "amount" =>    47000],
            ["id" =>    1151, "amount" =>    23320],
            ["id" =>    1152, "amount" =>    47000],
            ["id" =>    1153, "amount" =>    20405],
            ["id" =>    1154, "amount" =>    17400],
            ["id" =>    1155, "amount" =>    29150],
            ["id" =>    1156, "amount" =>    30800],
            ["id" =>    1157, "amount" =>    30800],
            ["id" =>    1158, "amount" =>    23320],
            ["id" =>    1159, "amount" =>    22000],
            ["id" =>    1160, "amount" =>    18700],
            ["id" =>    1161, "amount" =>    47000],
            ["id" =>    1162, "amount" =>    21900],
            ["id" =>    1163, "amount" =>    20900],
            ["id" =>    1164, "amount" =>    27500],
            ["id" =>    1165, "amount" =>    57480],
            ["id" =>    1166, "amount" =>    30000],
            ["id" =>    1167, "amount" =>    30000],
            ["id" =>    1168, "amount" =>    53000],
            ["id" =>    1169, "amount" =>    126600],
            ["id" =>    1170, "amount" =>    31000],
            ["id" =>    1171, "amount" =>    19000],
            ["id" =>    1172, "amount" =>    20800],
            ["id" =>    1173, "amount" =>    18900],
            ["id" =>    1174, "amount" =>    19099],
            ["id" =>    1175, "amount" =>    16700],
            ["id" =>    1176, "amount" =>    139999],
            ["id" =>    1177, "amount" =>    145000],
            ["id" =>    1178, "amount" =>    33500],
            ["id" =>    1179, "amount" =>    220000],
            ["id" =>    1180, "amount" =>    19500],
            ["id" =>    1181, "amount" =>    24500],
            ["id" =>    1182, "amount" =>    24000],
            ["id" =>    1183, "amount" =>    19000],
            ["id" =>    1184, "amount" =>    20500],
            ["id" =>    1185, "amount" =>    22500],
            ["id" =>    1186, "amount" =>    130000],
            ["id" =>    1187, "amount" =>    19000],
            ["id" =>    1188, "amount" =>    20900],
            ["id" =>    1189, "amount" =>    15948],
            ["id" =>    1190, "amount" =>    19000],
            ["id" =>    1191, "amount" =>    16000],
            ["id" =>    1192, "amount" =>    46000],
            ["id" =>    1193, "amount" =>    192600],
            ["id" =>    1194, "amount" =>    59900],
            ["id" =>    1195, "amount" =>    104100],
            ["id" =>    1196, "amount" =>    20900],
            ["id" =>    1197, "amount" =>    38500],
            ["id" =>    1198, "amount" =>    36000],
            ["id" =>    1199, "amount" =>    55000],
            ["id" =>    1200, "amount" =>    48100],
            ["id" =>    1201, "amount" =>    21000],
            ["id" =>    1202, "amount" =>    17664],
            ["id" =>    1203, "amount" =>    14200],
            ["id" =>    1204, "amount" =>    13900],
            ["id" =>    1205, "amount" =>    23500],
            ["id" =>    1206, "amount" =>    17800],
            ["id" =>    1207, "amount" =>    54000],
            ["id" =>    1209, "amount" =>    24886],
            ["id" =>    1210, "amount" =>    25471],
            ["id" =>    1211, "amount" =>    157259],
            ["id" =>    1212, "amount" =>    25875],
            ["id" =>    1213, "amount" =>    19900],
            ["id" =>    1214, "amount" =>    33100],
            ["id" =>    1215, "amount" =>    18700],
            ["id" =>    1216, "amount" =>    19800],
            ["id" =>    1217, "amount" =>    13090],
            ["id" =>    1218, "amount" =>    21106],
            ["id" =>    1219, "amount" =>    28800],
            ["id" =>    1220, "amount" =>    14438],
            ["id" =>    1221, "amount" =>    17400],
            ["id" =>    1222, "amount" =>    10241],
            ["id" =>    1223, "amount" =>    8119.91],
            ["id" =>    1224, "amount" =>    8119.91],
            ["id" =>    1225, "amount" =>    8119.91],
            ["id" =>    1226, "amount" =>    6799],
            ["id" =>    1227, "amount" =>    10010],
            ["id" =>    1228, "amount" =>    7799],
            ["id" =>    1229, "amount" =>    9841],
            ["id" =>    1230, "amount" =>    11550],
            ["id" =>    1231, "amount" =>    8119.68],
            ["id" =>    1232, "amount" =>    12455],
            ["id" =>    1233, "amount" =>    6799],
            ["id" =>    1234, "amount" =>    6799],
            ["id" =>    1235, "amount" =>    8316],
            ["id" =>    1237, "amount" =>    90240],
            ["id" =>    1238, "amount" =>    16500],
            ["id" =>    1239, "amount" =>    12500],
            ["id" =>    1240, "amount" =>    15163],
            ["id" =>    1241, "amount" =>    22250],
            ["id" =>    1242, "amount" =>    21000],
            ["id" =>    1243, "amount" =>    9840],
            ["id" =>    1244, "amount" =>    42300],
            ["id" =>    1245, "amount" =>    18898],
            ["id" =>    1246, "amount" =>    17300],
            ["id" =>    1247, "amount" =>    15400],
            ["id" =>    1248, "amount" =>    24000],
            ["id" =>    1249, "amount" =>    52500],
            ["id" =>    1250, "amount" =>    14492],
            ["id" =>    1251, "amount" =>    17900],
            ["id" =>    1252, "amount" =>    37375],
            ["id" =>    1253, "amount" =>    19998],
            ["id" =>    1254, "amount" =>    14399],
            ["id" =>    1255, "amount" =>    13000],
            ["id" =>    1256, "amount" =>    6299.43],
            ["id" =>    1257, "amount" =>    37404],
            ["id" =>    1258, "amount" =>    48129],
            ["id" =>    1259, "amount" =>    18000],
            ["id" =>    1260, "amount" =>    11051],
            ["id" =>    1261, "amount" =>    15000],
            ["id" =>    1262, "amount" =>    20400],
            ["id" =>    1263, "amount" =>    50000],
            ["id" =>    1264, "amount" =>    20000],
            ["id" =>    1265, "amount" =>    248500],
            ["id" =>    1266, "amount" =>    34000],
            ["id" =>    1267, "amount" =>    45999],
            ["id" =>    1268, "amount" =>    550000],
            ["id" =>    1269, "amount" =>    27000],
            ["id" =>    1270, "amount" =>    19000],
            ["id" =>    1271, "amount" =>    19000],
            ["id" =>    1272, "amount" =>    20900],
            ["id" =>    1273, "amount" =>    16500],
            ["id" =>    1274, "amount" =>    24024],
            ["id" =>    1275, "amount" =>    30030],
            ["id" =>    1276, "amount" =>    30030],
            ["id" =>    1277, "amount" =>    30030],
            ["id" =>    1278, "amount" =>    30030],
            ["id" =>    1283, "amount" =>    158385.6],
            ["id" =>    1284, "amount" =>    43996],
            ["id" =>    1285, "amount" =>    13200],
            ["id" =>    1286, "amount" =>    12792],
            ["id" =>    1287, "amount" =>    19000],
            ["id" =>    1288, "amount" =>    14000],
            ["id" =>    1289, "amount" =>    71138],
            ["id" =>    1290, "amount" =>    63977.04],
            ["id" =>    1291, "amount" =>    6999],
            ["id" =>    1292, "amount" =>    14005],
            ["id" =>    1293, "amount" =>    59900],
            ["id" =>    1294, "amount" =>    89850],
            ["id" =>    1295, "amount" =>    53910],
            ["id" =>    1296, "amount" =>    59900],
            ["id" =>    1297, "amount" =>    29950],
            ["id" =>    1298, "amount" =>    29950],
            ["id" =>    1299, "amount" =>    59900],
            ["id" =>    1300, "amount" =>    59900],
            ["id" =>    1301, "amount" =>    6998],
            ["id" =>    1302, "amount" =>    10497],
            ["id" =>    1303, "amount" =>    17495],
            ["id" =>    1304, "amount" =>    17495],
            ["id" =>    1305, "amount" =>    17495],
            ["id" =>    1306, "amount" =>    6998],
            ["id" =>    1307, "amount" =>    6998],
            ["id" =>    1308, "amount" =>    12787.5],
            ["id" =>    1309, "amount" =>    12787.5],
            ["id" =>    1310, "amount" =>    18000],
            ["id" =>    1311, "amount" =>    9099],
            ["id" =>    1312, "amount" =>    3000],
            ["id" =>    1313, "amount" =>    53889],
            ["id" =>    1314, "amount" =>    48990],
            ["id" =>    1315, "amount" =>    4899],
            ["id" =>    1316, "amount" =>    14697],
            ["id" =>    1317, "amount" =>    9798],
            ["id" =>    1318, "amount" =>    24495],
            ["id" =>    1319, "amount" =>    83283],
            ["id" =>    1320, "amount" =>    18018],
            ["id" =>    1321, "amount" =>    18018],
            ["id" =>    1322, "amount" =>    30030],
            ["id" =>    1323, "amount" =>    30030],
            ["id" =>    1324, "amount" =>    76994.5],
            ["id" =>    1325, "amount" =>    55440],
            ["id" =>    1326, "amount" =>    15840],
            ["id" =>    1327, "amount" =>    4899],
            ["id" =>    1328, "amount" =>    4899],
            ["id" =>    1329, "amount" =>    4899],
            ["id" =>    1330, "amount" =>    19596],
            ["id" =>    1331, "amount" =>    12596],
            ["id" =>    1332, "amount" =>    18545.01],
            ["id" =>    1333, "amount" =>    18545.01],
            ["id" =>    1334, "amount" =>    34980],
            ["id" =>    1335, "amount" =>    34980],
            ["id" =>    1336, "amount" =>    17490],
            ["id" =>    1337, "amount" =>    17490],
            ["id" =>    1338, "amount" =>    34980],
            ["id" =>    1339, "amount" =>    51480],
            ["id" =>    1340, "amount" =>    4919.2],
            ["id" =>    1341, "amount" =>    9838.4],
            ["id" =>    1342, "amount" =>    4919.2],
            ["id" =>    1343, "amount" =>    9798],
            ["id" =>    1344, "amount" =>    24495],
            ["id" =>    1345, "amount" =>    24495],
            ["id" =>    1346, "amount" =>    14697],
            ["id" =>    1347, "amount" =>    32967],
            ["id" =>    1348, "amount" =>    10989],
            ["id" =>    1349, "amount" =>    32967],
            ["id" =>    1350, "amount" =>    10989],
            ["id" =>    1351, "amount" =>    7911],
            ["id" =>    1352, "amount" =>    7911],
            ["id" =>    1353, "amount" =>    7911],
            ["id" =>    1354, "amount" =>    7911],
            ["id" =>    1355, "amount" =>    16794],
            ["id" =>    1356, "amount" =>    8397],
            ["id" =>    1357, "amount" =>    19593],
            ["id" =>    1358, "amount" =>    5598],
            ["id" =>    1359, "amount" =>    7911],
            ["id" =>    1360, "amount" =>    7911],
            ["id" =>    1361, "amount" =>    11600],
            ["id" =>    1362, "amount" =>    5000],
            ["id" =>    1363, "amount" =>    5000],
            ["id" =>    1364, "amount" =>    39438],
            ["id" =>    1365, "amount" =>    51500.01],
            ["id" =>    1366, "amount" =>    34500],
            ["id" =>    1367, "amount" =>    17000],
            ["id" =>    1368, "amount" =>    34500],
            ["id" =>    1369, "amount" =>    9099],
            ["id" =>    1370, "amount" =>    9099],
            ["id" =>    1371, "amount" =>    18198],
            ["id" =>    1372, "amount" =>    54594],
            ["id" =>    1373, "amount" =>    9099],
            ["id" =>    1374, "amount" =>    36396],
            ["id" =>    1375, "amount" =>    83985.45],
            ["id" =>    1376, "amount" =>    167970.6],
            ["id" =>    1377, "amount" =>    263153.94],
            ["id" =>    1378, "amount" =>    335941.2],
            ["id" =>    1379, "amount" =>    335941.2],
            ["id" =>    1380, "amount" =>    235158.84],
            ["id" =>    1381, "amount" =>    139975.5],
            ["id" =>    1382, "amount" =>    8078.4],
            ["id" =>    1383, "amount" =>    16156.8],
            ["id" =>    1384, "amount" =>    20547.8],
            ["id" =>    1385, "amount" =>    20547.8],
            ["id" =>    1386, "amount" =>    20547.8],
            ["id" =>    1387, "amount" =>    16156.8],
            ["id" =>    1388, "amount" =>    18898.86],
            ["id" =>    1389, "amount" =>    6299.63],
            ["id" =>    1390, "amount" =>    18898.86],
            ["id" =>    1391, "amount" =>    50397.04],
            ["id" =>    1392, "amount" =>    31498.15],
            ["id" =>    1393, "amount" =>    6299.63],
            ["id" =>    1394, "amount" =>    17000],
            ["id" =>    1395, "amount" =>    8500],
            ["id" =>    1396, "amount" =>    17000],
            ["id" =>    1397, "amount" =>    8500],
            ["id" =>    1398, "amount" =>    36999],
            ["id" =>    1399, "amount" =>    42500],
            ["id" =>    1400, "amount" =>    42500],
            ["id" =>    1401, "amount" =>    51000],
            ["id" =>    1402, "amount" =>    51000],
            ["id" =>    1403, "amount" =>    33594],
            ["id" =>    1404, "amount" =>    50391],
            ["id" =>    1405, "amount" =>    61589],
            ["id" =>    1406, "amount" =>    83985],
            ["id" =>    1407, "amount" =>    83985],
            ["id" =>    1408, "amount" =>    1],
            ["id" =>    1409, "amount" =>    5341.22],
            ["id" =>    1410, "amount" =>    17175.48],
            ["id" =>    1411, "amount" =>    51999],
            ["id" =>    1412, "amount" =>    14025],
            ["id" =>    1413, "amount" =>    25200],
            ["id" =>    1414, "amount" =>    25162.5],
            ["id" =>    1415, "amount" =>    14300],
            ["id" =>    1416, "amount" =>    11440],
            ["id" =>    1417, "amount" =>    14025],
            ["id" =>    1418, "amount" =>    13860],
            ["id" =>    1419, "amount" =>    10241],
            ["id" =>    1420, "amount" =>    5082],
            ["id" =>    1421, "amount" =>    4319],
            ["id" =>    1422, "amount" =>    5717],
            ["id" =>    1423, "amount" =>    5717],
            ["id" =>    1424, "amount" =>    8712],
            ["id" =>    1426, "amount" =>    23300],
            ["id" =>    1427, "amount" =>    7675.82],
            ["id" =>    1428, "amount" =>    34800],
            ["id" =>    1429, "amount" =>    32000],
            ["id" =>    1430, "amount" =>    41100],
            ["id" =>    1431, "amount" =>    58000],
            ["id" =>    1432, "amount" =>    17500],
            ["id" =>    1433, "amount" =>    12000],
            ["id" =>    1434, "amount" =>    19250],
            ["id" =>    1435, "amount" =>    31000],
            ["id" =>    1436, "amount" =>    39000],
            ["id" =>    1437, "amount" =>    4299],
            ["id" =>    1438, "amount" =>    9500],
            ["id" =>    1439, "amount" =>    19890],
            ["id" =>    1440, "amount" =>    15962],
            ["id" =>    1441, "amount" =>    18000],
            ["id" =>    1442, "amount" =>    13475],
            ["id" =>    1443, "amount" =>    57900],
            ["id" =>    1444, "amount" =>    17000],
            ["id" =>    1445, "amount" =>    5929],
            ["id" =>    1446, "amount" =>    3958.2],
            ["id" =>    1447, "amount" =>    3958.2],
            ["id" =>    1448, "amount" =>    4270],
            ["id" =>    1449, "amount" =>    7623],
            ["id" =>    1450, "amount" =>    5499],
            ["id" =>    1451, "amount" =>    4270],
            ["id" =>    1452, "amount" =>    4401],
            ["id" =>    1453, "amount" =>    4401],
            ["id" =>    1454, "amount" =>    4616],
            ["id" =>    1455, "amount" =>    5929],
            ["id" =>    1456, "amount" =>    8712],
            ["id" =>    1457, "amount" =>    7623],
            ["id" =>    1458, "amount" =>    4616],
            ["id" =>    1459, "amount" =>    6999.34],
            ["id" =>    1460, "amount" =>    7623],
            ["id" =>    1461, "amount" =>    4616],
            ["id" =>    1462, "amount" =>    10998],
            ["id" =>    1463, "amount" =>    5929],
            ["id" =>    1464, "amount" =>    5929],
            ["id" =>    1465, "amount" =>    4616],
            ["id" =>    1467, "amount" =>    7623],
            ["id" =>    1468, "amount" =>    7623],
            ["id" =>    1469, "amount" =>    33500],
            ["id" =>    1470, "amount" =>    15500],
            ["id" =>    1471, "amount" =>    35000],
            ["id" =>    1472, "amount" =>    31999],
            ["id" =>    1473, "amount" =>    14025],
            ["id" =>    1474, "amount" =>    18700],
            ["id" =>    1475, "amount" =>    16500],
            ["id" =>    1476, "amount" =>    31900],
            ["id" =>    1477, "amount" =>    31900],
            ["id" =>    1478, "amount" =>    16500],
            ["id" =>    1479, "amount" =>    26999],
            ["id" =>    1480, "amount" =>    42480],
            ["id" =>    1481, "amount" =>    19000],
            ["id" =>    1482, "amount" =>    19000],
            ["id" =>    1483, "amount" =>    20500],
            ["id" =>    1484, "amount" =>    25000],
            ["id" =>    1485, "amount" =>    19000],
            ["id" =>    1486, "amount" =>    19000],
            ["id" =>    1487, "amount" =>    16999],
            ["id" =>    1488, "amount" =>    14371],
            ["id" =>    1489, "amount" =>    68190],
            ["id" =>    1490, "amount" =>    24000],
            ["id" =>    1491, "amount" =>    76590],
            ["id" =>    1492, "amount" =>    14500],
            ["id" =>    1493, "amount" =>    42979],
            ["id" =>    1494, "amount" =>    14115],
            ["id" =>    1495, "amount" =>    16500],
            ["id" =>    1496, "amount" =>    27500],
            ["id" =>    1497, "amount" =>    105199],
            ["id" =>    1498, "amount" =>    16500],
            ["id" =>    1499, "amount" =>    20000],
            ["id" =>    1500, "amount" =>    18500],
            ["id" =>    1501, "amount" =>    14000],
            ["id" =>    1502, "amount" =>    9839],
            ["id" =>    1503, "amount" =>    18200],
            ["id" =>    1504, "amount" =>    18700],
            ["id" =>    1505, "amount" =>    32000],
            ["id" =>    1506, "amount" =>    20350],
            ["id" =>    1507, "amount" =>    28000],
            ["id" =>    1508, "amount" =>    7650],
            ["id" =>    1509, "amount" =>    19000],
            ["id" =>    1510, "amount" =>    19900],
            ["id" =>    1511, "amount" =>    13500],
            ["id" =>    1512, "amount" =>    23999],
            ["id" =>    1513, "amount" =>    44000],
            ["id" =>    1514, "amount" =>    38560],
            ["id" =>    1515, "amount" =>    19001],
            ["id" =>    1516, "amount" =>    182000],
            ["id" =>    1517, "amount" =>    18000],
            ["id" =>    1518, "amount" =>    17389],
            ["id" =>    1519, "amount" =>    265000],
            ["id" =>    1520, "amount" =>    18500],
            ["id" =>    1521, "amount" =>    18500],
            ["id" =>    1522, "amount" =>    18500],
            ["id" =>    1523, "amount" =>    170000],
            ["id" =>    1524, "amount" =>    24828],
            ["id" =>    1525, "amount" =>    133000],
            ["id" =>    1526, "amount" =>    765],
            ["id" =>    1527, "amount" =>    99800],
            ["id" =>    1528, "amount" =>    60500],
            ["id" =>    1529, "amount" =>    36000],
            ["id" =>    1530, "amount" =>    17046],
            ["id" =>    1531, "amount" =>    15699],
            ["id" =>    1532, "amount" =>    189010],
            ["id" =>    1533, "amount" =>    14500],
            ["id" =>    1534, "amount" =>    65000],
            ["id" =>    1535, "amount" =>    29000],
            ["id" =>    1536, "amount" =>    19000],
            ["id" =>    1537, "amount" =>    26000],
            ["id" =>    1538, "amount" =>    18000],
            ["id" =>    1539, "amount" =>    21563],
            ["id" =>    1540, "amount" =>    39840],
            ["id" =>    1541, "amount" =>    50000],
            ["id" =>    1542, "amount" =>    20900],
            ["id" =>    1543, "amount" =>    14000],
            ["id" =>    1544, "amount" =>    22999],
            ["id" =>    1545, "amount" =>    19890],
            ["id" =>    1546, "amount" =>    12460],
            ["id" =>    1547, "amount" =>    12000],
            ["id" =>    1548, "amount" =>    19000],
            ["id" =>    1549, "amount" =>    24500],
            ["id" =>    1550, "amount" =>    18000],
            ["id" =>    1551, "amount" =>    17000],
            ["id" =>    1552, "amount" =>    243000],
            ["id" =>    1553, "amount" =>    84000],
            ["id" =>    1554, "amount" =>    16000],
            ["id" =>    1555, "amount" =>    25630],
            ["id" =>    1556, "amount" =>    35500],
            ["id" =>    1557, "amount" =>    12000],
            ["id" =>    1558, "amount" =>    22000],
            ["id" =>    1559, "amount" =>    35000],
            ["id" =>    1560, "amount" =>    20500],
            ["id" =>    1561, "amount" =>    59000],
            ["id" =>    1562, "amount" =>    39000],
            ["id" =>    1563, "amount" =>    168000],
            ["id" =>    1564, "amount" =>    148000],
            ["id" =>    1565, "amount" =>    215000],
            ["id" =>    1566, "amount" =>    120000],
            ["id" =>    1567, "amount" =>    26000],
            ["id" =>    1568, "amount" =>    15500],
            ["id" =>    1569, "amount" =>    35500],
            ["id" =>    1570, "amount" =>    133000],
            ["id" =>    1571, "amount" =>    62500],
            ["id" =>    1572, "amount" =>    56001],
            ["id" =>    1573, "amount" =>    16000],
            ["id" =>    1574, "amount" =>    29500],
            ["id" =>    1575, "amount" =>    18500],
            ["id" =>    1576, "amount" =>    19000],
            ["id" =>    1577, "amount" =>    19800],
            ["id" =>    1578, "amount" =>    20000],
            ["id" =>    1579, "amount" =>    34590],
            ["id" =>    1580, "amount" =>    20500],
            ["id" =>    1581, "amount" =>    19500],
            ["id" =>    1582, "amount" =>    40000],
            ["id" =>    1583, "amount" =>    28600],
            ["id" =>    1584, "amount" =>    195000],
            ["id" =>    1585, "amount" =>    40500],
            ["id" =>    1586, "amount" =>    24000],
            ["id" =>    1587, "amount" =>    30000],
            ["id" =>    1588, "amount" =>    22000],
            ["id" =>    1589, "amount" =>    35000],
            ["id" =>    1590, "amount" =>    43800],
            ["id" =>    1591, "amount" =>    26000],
            ["id" =>    1592, "amount" =>    10000],
            ["id" =>    1593, "amount" =>    49000],
            ["id" =>    1594, "amount" =>    43000],
            ["id" =>    1595, "amount" =>    33500],
            ["id" =>    1596, "amount" =>    34000],
            ["id" =>    1597, "amount" =>    15000],
            ["id" =>    1598, "amount" =>    29900],
            ["id" =>    1599, "amount" =>    17000],
            ["id" =>    1600, "amount" =>    8793],
            ["id" =>    1601, "amount" =>    8793],
            ["id" =>    1602, "amount" =>    4616],
            ["id" =>    1603, "amount" =>    7623],
            ["id" =>    1604, "amount" =>    8712],
            ["id" =>    1605, "amount" =>    5717],
            ["id" =>    1606, "amount" =>    4616],
            ["id" =>    1607, "amount" =>    7623],
            ["id" =>    1608, "amount" =>    7623],
            ["id" =>    1609, "amount" =>    3919],
            ["id" =>    1610, "amount" =>    4199],
            ["id" =>    1611, "amount" =>    11550],
            ["id" =>    1612, "amount" =>    7623],
            ["id" =>    1613, "amount" =>    7623],
            ["id" =>    1614, "amount" =>    5717],
            ["id" =>    1615, "amount" =>    4616],
            ["id" =>    1616, "amount" =>    7623],
            ["id" =>    1617, "amount" =>    5082],
            ["id" =>    1618, "amount" =>    4616],
            ["id" =>    1619, "amount" =>    3958.2],
            ["id" =>    1620, "amount" =>    3958.2],
            ["id" =>    1621, "amount" =>    3958.2],
            ["id" =>    1622, "amount" =>    10919],
            ["id" =>    1623, "amount" =>    10919],
            ["id" =>    1624, "amount" =>    10919],
            ["id" =>    1625, "amount" =>    3958.2],
            ["id" =>    1626, "amount" =>    10280],
            ["id" =>    1627, "amount" =>    10280],
            ["id" =>    1628, "amount" =>    10280],
            ["id" =>    1629, "amount" =>    10280],
            ["id" =>    1630, "amount" =>    10280],
            ["id" =>    1631, "amount" =>    10280],
            ["id" =>    1632, "amount" =>    10280],
            ["id" =>    1633, "amount" =>    3958.2],
            ["id" =>    1634, "amount" =>    7623],
            ["id" =>    1635, "amount" =>    8539],
            ["id" =>    1636, "amount" =>    8539],
            ["id" =>    1637, "amount" =>    8539],
            ["id" =>    1638, "amount" =>    8539],
            ["id" =>    1639, "amount" =>    8539],
            ["id" =>    1640, "amount" =>    8539],
            ["id" =>    1641, "amount" =>    18000],
            ["id" =>    1642, "amount" =>    8712],
            ["id" =>    1643, "amount" =>    3406.5],
            ["id" =>    1644, "amount" =>    3406.5],
            ["id" =>    1645, "amount" =>    3406.5],
            ["id" =>    1646, "amount" =>    3406.5],
            ["id" =>    1647, "amount" =>    8712],
            ["id" =>    1648, "amount" =>    4616],
            ["id" =>    1649, "amount" =>    8712],
            ["id" =>    1650, "amount" =>    7623],
            ["id" =>    1651, "amount" =>    5717],
            ["id" =>    1652, "amount" =>    6352.5],
            ["id" =>    1653, "amount" =>    6352.5],
            ["id" =>    1654, "amount" =>    5082],
            ["id" =>    1655, "amount" =>    4616],
            ["id" =>    1656, "amount" =>    3956],
            ["id" =>    1657, "amount" =>    6600],
            ["id" =>    1658, "amount" =>    3956],
            ["id" =>    1659, "amount" =>    2980],
            ["id" =>    1660, "amount" =>    5983],
            ["id" =>    1661, "amount" =>    8398],
            ["id" =>    1663, "amount" =>    3958.2],
            ["id" =>    1664, "amount" =>    5040],
            ["id" =>    1665, "amount" =>    8712],
            ["id" =>    1666, "amount" =>    4616],
            ["id" =>    1667, "amount" =>    8712],
            ["id" =>    1668, "amount" =>    4616],
            ["id" =>    1669, "amount" =>    8712],
            ["id" =>    1670, "amount" =>    33000],
            ["id" =>    1671, "amount" =>    16278],
            ["id" =>    1672, "amount" =>    18500],
            ["id" =>    1673, "amount" =>    244700],
            ["id" =>    1674, "amount" =>    13900],
            ["id" =>    1675, "amount" =>    7000],
            ["id" =>    1676, "amount" =>    4400],
            ["id" =>    1677, "amount" =>    3545.12],
            ["id" =>    1678, "amount" =>    3545.12],
            ["id" =>    1679, "amount" =>    3545.12],
            ["id" =>    1680, "amount" =>    5806.63],
            ["id" =>    1681, "amount" =>    5806.63],
            ["id" =>    1682, "amount" =>    5806.63],
            ["id" =>    1683, "amount" =>    5806.63],
            ["id" =>    1684, "amount" =>    5806.63],
            ["id" =>    1685, "amount" =>    46200],
            ["id" =>    1686, "amount" =>    11550],
            ["id" =>    1687, "amount" =>    231000],
            ["id" =>    1688, "amount" =>    8500],
            ["id" =>    1689, "amount" =>    8500],
            ["id" =>    1690, "amount" =>    8500],
            ["id" =>    1691, "amount" =>    6299],
            ["id" =>    1692, "amount" =>    6299],
            ["id" =>    1693, "amount" =>    6299],
            ["id" =>    1694, "amount" =>    23500],
            ["id" =>    1695, "amount" =>    6999.4],
            ["id" =>    1696, "amount" =>    6999.4],
            ["id" =>    1697, "amount" =>    10989],
            ["id" =>    1698, "amount" =>    10989],
            ["id" =>    1699, "amount" =>    10989],
            ["id" =>    1700, "amount" =>    6299],
            ["id" =>    1701, "amount" =>    6299],
            ["id" =>    1702, "amount" =>    8949.5],
            ["id" =>    1703, "amount" =>    8949.5],
            ["id" =>    1704, "amount" =>    8949.5],
            ["id" =>    1705, "amount" =>    8949.5],
            ["id" =>    1706, "amount" =>    8949.5],
            ["id" =>    1707, "amount" =>    8949.5],
            ["id" =>    1708, "amount" =>    8949.5],
            ["id" =>    1709, "amount" =>    8949.5],
            ["id" =>    1710, "amount" =>    8949.5],
            ["id" =>    1711, "amount" =>    8949.5],
            ["id" =>    1712, "amount" =>    62370],
            ["id" =>    1713, "amount" =>    139600],
            ["id" =>    1714, "amount" =>    62991.2],
            ["id" =>    1715, "amount" =>    62991.2],
            ["id" =>    1716, "amount" =>    62991.2],
            ["id" =>    1717, "amount" =>    94486.2],
            ["id" =>    1718, "amount" =>    94486.2],
            ["id" =>    1719, "amount" =>    94486.2],
            ["id" =>    1720, "amount" =>    81888.17],
            ["id" =>    1721, "amount" =>    62991.2],
            ["id" =>    1722, "amount" =>    14840],
            ["id" =>    1723, "amount" =>    22260],
            ["id" =>    1724, "amount" =>    37100],
            ["id" =>    1725, "amount" =>    66780],
            ["id" =>    1726, "amount" =>    51940],
            ["id" =>    1727, "amount" =>    74200],
            ["id" =>    1728, "amount" =>    66780],
            ["id" =>    1729, "amount" =>    29680],
            ["id" =>    1730, "amount" =>    22260],
            ["id" =>    1731, "amount" =>    14840],
            ["id" =>    1732, "amount" =>    22260],
            ["id" =>    1733, "amount" =>    4899],
            ["id" =>    1734, "amount" =>    4899],
            ["id" =>    1735, "amount" =>    4899],
            ["id" =>    1736, "amount" =>    4899],
            ["id" =>    1737, "amount" =>    4899],
            ["id" =>    1738, "amount" =>    4899],
            ["id" =>    1739, "amount" =>    4899],
            ["id" =>    1740, "amount" =>    4899],
            ["id" =>    1741, "amount" =>    59900],
            ["id" =>    1742, "amount" =>    59900],
            ["id" =>    1743, "amount" =>    59900],
            ["id" =>    1744, "amount" =>    59900],
            ["id" =>    1745, "amount" =>    29950],
            ["id" =>    1746, "amount" =>    29950],
            ["id" =>    1747, "amount" =>    59900],
            ["id" =>    1748, "amount" =>    59900],
            ["id" =>    1749, "amount" =>    254100.08],
            ["id" =>    1750, "amount" =>    44095.8],
            ["id" =>    1751, "amount" =>    138585.7],
            ["id" =>    1752, "amount" =>    3499],
            ["id" =>    1753, "amount" =>    3499],
            ["id" =>    1754, "amount" =>    3499],
            ["id" =>    1755, "amount" =>    3499],
            ["id" =>    1756, "amount" =>    3499],
            ["id" =>    1757, "amount" =>    3499],
            ["id" =>    1758, "amount" =>    3499],
            ["id" =>    1759, "amount" =>    6799],
            ["id" =>    1760, "amount" =>    6932.33],
            ["id" =>    1762, "amount" =>    8537],
            ["id" =>    1763, "amount" =>    8499.33],
            ["id" =>    1764, "amount" =>    8570.86],
            ["id" =>    1765, "amount" =>    117599.64],
            ["id" =>    1766, "amount" =>    117599.64],
            ["id" =>    1767, "amount" =>    78399.76],
            ["id" =>    1768, "amount" =>    186199.24],
            ["id" =>    1769, "amount" =>    19599.94],
            ["id" =>    1770, "amount" =>    9799.97],
            ["id" =>    1771, "amount" =>    9799.97],
            ["id" =>    1772, "amount" =>    4094.5],
            ["id" =>    1773, "amount" =>    9100],
            ["id" =>    1774, "amount" =>    9100],
            ["id" =>    1775, "amount" =>    9100],
            ["id" =>    1776, "amount" =>    9100],
            ["id" =>    1777, "amount" =>    5355],
            ["id" =>    1778, "amount" =>    5355],
            ["id" =>    1779, "amount" =>    5355],
            ["id" =>    1780, "amount" =>    5355],
            ["id" =>    1781, "amount" =>    5355],
            ["id" =>    1782, "amount" =>    5355],
            ["id" =>    1783, "amount" =>    5355],
            ["id" =>    1784, "amount" =>    5355],
            ["id" =>    1785, "amount" =>    3824.5],
            ["id" =>    1786, "amount" =>    7686.5],
            ["id" =>    1787, "amount" =>    7686.5],
            ["id" =>    1788, "amount" =>    7686.5],
            ["id" =>    1789, "amount" =>    7686.5],
            ["id" =>    1790, "amount" =>    7686.5],
            ["id" =>    1791, "amount" =>    7686.5],
            ["id" =>    1792, "amount" =>    7686.5],
            ["id" =>    1793, "amount" =>    4061.39],
            ["id" =>    1794, "amount" =>    4061.39],
            ["id" =>    1795, "amount" =>    4061.39],
            ["id" =>    1796, "amount" =>    3518],
            ["id" =>    1797, "amount" =>    3518],
            ["id" =>    1798, "amount" =>    3518],
            ["id" =>    1799, "amount" =>    3518],
            ["id" =>    1800, "amount" =>    3518],
            ["id" =>    1801, "amount" =>    3518],
            ["id" =>    1802, "amount" =>    3518],
            ["id" =>    1803, "amount" =>    3518],
            ["id" =>    1804, "amount" =>    3518],
            ["id" =>    1805, "amount" =>    11568.92],
            ["id" =>    1806, "amount" =>    11568.92],
            ["id" =>    1807, "amount" =>    11568.92],
            ["id" =>    1808, "amount" =>    11568.92],
            ["id" =>    1809, "amount" =>    11568.92],
            ["id" =>    1810, "amount" =>    11568.92],
            ["id" =>    1811, "amount" =>    11568.92],
            ["id" =>    1812, "amount" =>    11568.92],
            ["id" =>    1813, "amount" =>    11568.92],
            ["id" =>    1814, "amount" =>    11568.92],
            ["id" =>    1815, "amount" =>    217437],
            ["id" =>    1816, "amount" =>    105424],
            ["id" =>    1817, "amount" =>    79068],
            ["id" =>    1818, "amount" =>    2599.5],
            ["id" =>    1819, "amount" =>    111980],
            ["id" =>    1820, "amount" =>    52220],
            ["id" =>    1821, "amount" =>    44760],
            ["id" =>    1822, "amount" =>    7460],
            ["id" =>    1823, "amount" =>    7460],
            ["id" =>    1824, "amount" =>    14920],
            ["id" =>    1825, "amount" =>    7460],
            ["id" =>    1826, "amount" =>    7460],
            ["id" =>    1827, "amount" =>    20735],
            ["id" =>    1828, "amount" =>    20735],
            ["id" =>    1829, "amount" =>    20735],
            ["id" =>    1830, "amount" =>    20735],
            ["id" =>    1831, "amount" =>    20735],
            ["id" =>    1832, "amount" =>    20735],
            ["id" =>    1833, "amount" =>    20735],
            ["id" =>    1834, "amount" =>    20735],
            ["id" =>    1835, "amount" =>    20735],
            ["id" =>    1836, "amount" =>    12000],
            ["id" =>    1837, "amount" =>    12000],
            ["id" =>    1838, "amount" =>    12000],
            ["id" =>    1839, "amount" =>    12000],
            ["id" =>    1840, "amount" =>    12000],
            ["id" =>    1841, "amount" =>    12000],
            ["id" =>    1842, "amount" =>    12000],
            ["id" =>    1843, "amount" =>    17966.67],
            ["id" =>    1844, "amount" =>    17966.67],
            ["id" =>    1845, "amount" =>    4785],
            ["id" =>    1846, "amount" =>    4785],
            ["id" =>    1847, "amount" =>    4785],
            ["id" =>    1848, "amount" =>    4785],
            ["id" =>    1849, "amount" =>    4785],
            ["id" =>    1850, "amount" =>    13822],
            ["id" =>    1851, "amount" =>    13822],
            ["id" =>    1852, "amount" =>    13822],
            ["id" =>    1853, "amount" =>    13822],
            ["id" =>    1854, "amount" =>    3552],
            ["id" =>    1855, "amount" =>    207856],
            ["id" =>    1856, "amount" =>    41990],
            ["id" =>    1857, "amount" =>    33592],
            ["id" =>    1858, "amount" =>    33592],
            ["id" =>    1859, "amount" =>    29393],
            ["id" =>    1860, "amount" =>    33592],
            ["id" =>    1861, "amount" =>    33592],
            ["id" =>    1862, "amount" =>    31498],
            ["id" =>    1863, "amount" =>    75594.72],
            ["id" =>    1864, "amount" =>    34072.64],
            ["id" =>    1865, "amount" =>    16508.6],
            ["id" =>    1866, "amount" =>    16508.6],
            ["id" =>    1867, "amount" =>    132226.05],
            ["id" =>    1868, "amount" =>    35697],
            ["id" =>    1869, "amount" =>    59686],
            ["id" =>    1870, "amount" =>    6856.43],
            ["id" =>    1871, "amount" =>    6856.43],
            ["id" =>    1872, "amount" =>    6856.43],
            ["id" =>    1873, "amount" =>    10780],
            ["id" =>    1874, "amount" =>    10780],
            ["id" =>    1875, "amount" =>    10780],
            ["id" =>    1876, "amount" =>    10780],
            ["id" =>    1877, "amount" =>    10780],
            ["id" =>    1878, "amount" =>    5210],
            ["id" =>    1879, "amount" =>    5210],
            ["id" =>    1880, "amount" =>    5210],
            ["id" =>    1881, "amount" =>    7419.57],
            ["id" =>    1882, "amount" =>    7419.57],
            ["id" =>    1883, "amount" =>    7419.57],
            ["id" =>    1884, "amount" =>    4199.28],
            ["id" =>    1885, "amount" =>    4199.28],
            ["id" =>    1886, "amount" =>    4199.28],
            ["id" =>    1887, "amount" =>    4199.28],
            ["id" =>    1888, "amount" =>    55990],
            ["id" =>    1889, "amount" =>    83985],
            ["id" =>    1890, "amount" =>    166035.9],
            ["id" =>    1891, "amount" =>    138547],
            ["id" =>    1892, "amount" =>    55990],
            ["id" =>    1893, "amount" =>    10850],
            ["id" =>    1894, "amount" =>    10850],
            ["id" =>    1895, "amount" =>    10850],
            ["id" =>    1896, "amount" =>    10850],
            ["id" =>    1897, "amount" =>    10850],
            ["id" =>    1898, "amount" =>    10850],
            ["id" =>    1899, "amount" =>    10850],
            ["id" =>    1900, "amount" =>    7860],
            ["id" =>    1901, "amount" =>    7860],
            ["id" =>    1902, "amount" =>    7860],
            ["id" =>    1903, "amount" =>    7860],
            ["id" =>    1904, "amount" =>    7860],
            ["id" =>    1905, "amount" =>    7860],
            ["id" =>    1906, "amount" =>    5592],
            ["id" =>    1907, "amount" =>    5592],
            ["id" =>    1908, "amount" =>    20000],
            ["id" =>    1909, "amount" =>    20000],
            ["id" =>    1910, "amount" =>    20000],
            ["id" =>    1911, "amount" =>    11935],
            ["id" =>    1912, "amount" =>    11935],
            ["id" =>    1913, "amount" =>    11935],
            ["id" =>    1914, "amount" =>    6804.65],
            ["id" =>    1915, "amount" =>    6804.65],
            ["id" =>    1916, "amount" =>    6804.65],
            ["id" =>    1917, "amount" =>    6804.65],
            ["id" =>    1918, "amount" =>    6804.65],
            ["id" =>    1919, "amount" =>    6804.65],
            ["id" =>    1920, "amount" =>    4274],
            ["id" =>    1921, "amount" =>    4274],
            ["id" =>    1922, "amount" =>    4274],
            ["id" =>    1923, "amount" =>    4274],
            ["id" =>    1924, "amount" =>    4274],
            ["id" =>    1925, "amount" =>    6159],
            ["id" =>    1926, "amount" =>    6159],
            ["id" =>    1927, "amount" =>    6159],
            ["id" =>    1928, "amount" =>    100800],
            ["id" =>    1929, "amount" =>    113400],
            ["id" =>    1930, "amount" =>    81200],
            ["id" =>    1931, "amount" =>    162399.8],
            ["id" =>    1932, "amount" =>    162399.8],
            ["id" =>    1933, "amount" =>    81200],
            ["id" =>    1934, "amount" =>    81200],
            ["id" =>    1935, "amount" =>    8120],
            ["id" =>    1936, "amount" =>    11179],
            ["id" =>    1937, "amount" =>    11179],
            ["id" =>    1938, "amount" =>    11179],
            ["id" =>    1939, "amount" =>    127851.56],
            ["id" =>    1940, "amount" =>    105593.46],
            ["id" =>    1941, "amount" =>    150109.8],
            ["id" =>    1942, "amount" =>    8100],
            ["id" =>    1943, "amount" =>    8100],
            ["id" =>    1944, "amount" =>    8100],
            ["id" =>    1945, "amount" =>    8100],
            ["id" =>    1946, "amount" =>    8100],
            ["id" =>    1947, "amount" =>    8100],
            ["id" =>    1948, "amount" =>    70200],
            ["id" =>    1949, "amount" =>    7419.6],
            ["id" =>    1950, "amount" =>    14839.2],
            ["id" =>    1951, "amount" =>    74196],
            ["id" =>    1952, "amount" =>    14839.2],
            ["id" =>    1953, "amount" =>    22258.8],
            ["id" =>    1954, "amount" =>    14839.2],
            ["id" =>    1955, "amount" =>    250270.08],
            ["id" =>    1956, "amount" =>    47385],
            ["id" =>    1957, "amount" =>    187680],
            ["id" =>    1958, "amount" =>    77434.95],
            ["id" =>    1959, "amount" =>    87982.99],
            ["id" =>    1960, "amount" =>    23556],
            ["id" =>    1961, "amount" =>    6039],
            ["id" =>    1962, "amount" =>    4332],
            ["id" =>    1963, "amount" =>    4332],
            ["id" =>    1964, "amount" =>    4332],
            ["id" =>    1965, "amount" =>    2631.2],
            ["id" =>    1966, "amount" =>    2631.2],
            ["id" =>    1967, "amount" =>    2631.2],
            ["id" =>    1968, "amount" =>    21049.6],
            ["id" =>    1969, "amount" =>    2631.2],
            ["id" =>    1970, "amount" =>    20796.8],
            ["id" =>    1971, "amount" =>    31195.2],
            ["id" =>    1972, "amount" =>    20796.8],
            ["id" =>    1973, "amount" =>    5199.2],
            ["id" =>    1974, "amount" =>    160960],
            ["id" =>    1975, "amount" =>    26928],
            ["id" =>    1976, "amount" =>    26928],
            ["id" =>    1977, "amount" =>    26928],
            ["id" =>    1978, "amount" =>    26928],
            ["id" =>    1979, "amount" =>    3599],
            ["id" =>    1980, "amount" =>    3599],
            ["id" =>    1981, "amount" =>    5599],
            ["id" =>    1982, "amount" =>    5599],
            ["id" =>    1983, "amount" =>    16797],
            ["id" =>    1984, "amount" =>    9779],
            ["id" =>    1985, "amount" =>    27334.98],
            ["id" =>    1986, "amount" =>    5390],
            ["id" =>    1987, "amount" =>    13998],
            ["id" =>    1988, "amount" =>    34995],
            ["id" =>    1989, "amount" =>    59010],
            ["id" =>    1990, "amount" =>    10500],
            ["id" =>    1991, "amount" =>    27300],
            ["id" =>    1992, "amount" =>    125987.6],
            ["id" =>    1993, "amount" =>    35420],
            ["id" =>    1994, "amount" =>    17710],
            ["id" =>    1995, "amount" =>    4391.2],
            ["id" =>    1996, "amount" =>    13173.6],
            ["id" =>    1997, "amount" =>    8782.4],
            ["id" =>    1998, "amount" =>    8782.4],
            ["id" =>    1999, "amount" =>    13173.6],
            ["id" =>    2000, "amount" =>    8782.4],
            ["id" =>    2001, "amount" =>    4391.2],
            ["id" =>    2002, "amount" =>    30492],
            ["id" =>    2003, "amount" =>    6299],
            ["id" =>    2004, "amount" =>    6299],
            ["id" =>    2005, "amount" =>    6299],
            ["id" =>    2006, "amount" =>    6299],
            ["id" =>    2007, "amount" =>    17245],
            ["id" =>    2008, "amount" =>    17245],
            ["id" =>    2009, "amount" =>    74844],
            ["id" =>    2010, "amount" =>    16632],
            ["id" =>    2011, "amount" =>    16632],
            ["id" =>    2012, "amount" =>    63590],
            ["id" =>    2013, "amount" =>    62990.9],
            ["id" =>    2014, "amount" =>    41395],
            ["id" =>    2015, "amount" =>    19599],
            ["id" =>    2016, "amount" =>    39198.24],
            ["id" =>    2017, "amount" =>    68596.99],
            ["id" =>    2018, "amount" =>    12748],
            ["id" =>    2019, "amount" =>    44618],
            ["id" =>    2021, "amount" =>    136790],
            ["id" =>    2022, "amount" =>    8398],
            ["id" =>    2023, "amount" =>    25194],
            ["id" =>    2024, "amount" =>    4049],
            ["id" =>    2025, "amount" =>    8098],
            ["id" =>    2026, "amount" =>    20000],
            ["id" =>    2027, "amount" =>    20000],
            ["id" =>    2028, "amount" =>    20000],
            ["id" =>    2029, "amount" =>    6799],
            ["id" =>    2030, "amount" =>    49195.02],
            ["id" =>    2031, "amount" =>    42129],
            ["id" =>    2032, "amount" =>    6799],
            ["id" =>    2033, "amount" =>    8782.4],
            ["id" =>    2034, "amount" =>    4391.2],
            ["id" =>    2035, "amount" =>    4391.2],
            ["id" =>    2036, "amount" =>    83990],
            ["id" =>    2037, "amount" =>    9598.4],
            ["id" =>    2038, "amount" =>    14397.6],
            ["id" =>    2039, "amount" =>    4799.2],
            ["id" =>    2040, "amount" =>    14397.6],
            ["id" =>    2041, "amount" =>    103525],
            ["id" =>    2042, "amount" =>    20705],
            ["id" =>    2043, "amount" =>    20705],
            ["id" =>    2044, "amount" =>    32696],
            ["id" =>    2045, "amount" =>    32696],
            ["id" =>    2046, "amount" =>    8791.2],
            ["id" =>    2047, "amount" =>    8791.2],
            ["id" =>    2048, "amount" =>    8791.2],
            ["id" =>    2049, "amount" =>    8791.2],
            ["id" =>    2050, "amount" =>    8791.2],
            ["id" =>    2051, "amount" =>    70400],
            ["id" =>    2052, "amount" =>    26400],
            ["id" =>    2053, "amount" =>    52800],
            ["id" =>    2054, "amount" =>    4899],
            ["id" =>    2055, "amount" =>    23727.99],
            ["id" =>    2056, "amount" =>    4899],
            ["id" =>    2057, "amount" =>    4899],
            ["id" =>    2058, "amount" =>    5599],
            ["id" =>    2059, "amount" =>    16797],
            ["id" =>    2060, "amount" =>    12059],
            ["id" =>    2061, "amount" =>    24118],
            ["id" =>    2062, "amount" =>    107559.99],
            ["id" =>    2063, "amount" =>    12059],
            ["id" =>    2064, "amount" =>    12059],
            ["id" =>    2065, "amount" =>    3511.2],
            ["id" =>    2066, "amount" =>    3511.2],
            ["id" =>    2067, "amount" =>    3511.2],
            ["id" =>    2068, "amount" =>    5949],
            ["id" =>    2069, "amount" =>    5949],
            ["id" =>    2071, "amount" =>    14696],
            ["id" =>    2072, "amount" =>    19000],
            ["id" =>    2073, "amount" =>    55899],
            ["id" =>    2074, "amount" =>    16650],
            ["id" =>    2075, "amount" =>    21920],
            ["id" =>    2076, "amount" =>    19800],
            ["id" =>    2077, "amount" =>    20500],
            ["id" =>    2078, "amount" =>    14696],
            ["id" =>    2079, "amount" =>    19500],
            ["id" =>    2080, "amount" =>    57750],
            ["id" =>    2081, "amount" =>    34650],
            ["id" =>    2082, "amount" =>    12500],
            ["id" =>    2083, "amount" =>    43956],
            ["id" =>    2084, "amount" =>    36960],
            ["id" =>    2085, "amount" =>    61600],
            ["id" =>    2086, "amount" =>    8119.63],
            ["id" =>    2087, "amount" =>    8119.91],
            ["id" =>    2088, "amount" =>    17000],
            ["id" =>    2089, "amount" =>    10296],
            ["id" =>    2090, "amount" =>    17000],
            ["id" =>    2091, "amount" =>    17000],
            ["id" =>    2092, "amount" =>    53039.03],
            ["id" =>    2093, "amount" =>    11049],
            ["id" =>    2094, "amount" =>    20405],
            ["id" =>    2095, "amount" =>    89568],
            ["id" =>    2096, "amount" =>    131553],
            ["id" =>    2097, "amount" =>    35000],
            ["id" =>    2098, "amount" =>    17500],
            ["id" =>    2099, "amount" =>    16797],
            ["id" =>    2100, "amount" =>    16797],
            ["id" =>    2101, "amount" =>    57000],
            ["id" =>    2102, "amount" =>    28500],
            ["id" =>    2103, "amount" =>    28500],
            ["id" =>    2104, "amount" =>    13398],
            ["id" =>    2105, "amount" =>    20097],
            ["id" =>    2106, "amount" =>    91000],
            ["id" =>    2107, "amount" =>    127400],
            ["id" =>    2108, "amount" =>    19490],
            ["id" =>    2109, "amount" =>    19490],
            ["id" =>    2110, "amount" =>    19490],
            ["id" =>    2111, "amount" =>    19490],
            ["id" =>    2113, "amount" =>    5599],
            ["id" =>    2114, "amount" =>    5599],
            ["id" =>    2115, "amount" =>    5599],
            ["id" =>    2117, "amount" =>    46276],
            ["id" =>    2118, "amount" =>    10241],
            ["id" =>    2119, "amount" =>    17788],
            ["id" =>    2120, "amount" =>    37500],
            ["id" =>    2121, "amount" =>    17000],
            ["id" =>    2122, "amount" =>    41500],
            ["id" =>    2123, "amount" =>    19000],
            ["id" =>    2124, "amount" =>    75000],
            ["id" =>    2125, "amount" =>    16500],
            ["id" =>    2126, "amount" =>    48000],
            ["id" =>    2127, "amount" =>    26950],
            ["id" =>    2128, "amount" =>    23500],
            ["id" =>    2129, "amount" =>    31890],
            ["id" =>    2130, "amount" =>    163000],
            ["id" =>    2131, "amount" =>    222700],
            ["id" =>    2132, "amount" =>    35000],
            ["id" =>    2133, "amount" =>    16799],
            ["id" =>    2134, "amount" =>    44101],
            ["id" =>    2135, "amount" =>    1428],
            ["id" =>    2136, "amount" =>    28000],
            ["id" =>    2137, "amount" =>    35000],
            ["id" =>    2138, "amount" =>    11000],
            ["id" =>    2139, "amount" =>    17500],
            ["id" =>    2140, "amount" =>    9500],
            ["id" =>    2141, "amount" =>    40800],
            ["id" =>    2142, "amount" =>    19000],
            ["id" =>    2143, "amount" =>    22000],
            ["id" =>    2144, "amount" =>    49100],
            ["id" =>    2145, "amount" =>    345000],
            ["id" =>    2146, "amount" =>    54699],
            ["id" =>    2147, "amount" =>    184997],
            ["id" =>    2148, "amount" =>    134899],
            ["id" =>    2149, "amount" =>    197000],
            ["id" =>    2150, "amount" =>    219000],
            ["id" =>    2151, "amount" =>    35000],
            ["id" =>    2152, "amount" =>    35500],
            ["id" =>    2153, "amount" =>    4199],
            ["id" =>    2154, "amount" =>    12597],
            ["id" =>    2155, "amount" =>    4199],
            ["id" =>    2156, "amount" =>    260320.06],
            ["id" =>    2157, "amount" =>    184320],
            ["id" =>    2158, "amount" =>    222320.04],
            ["id" =>    2159, "amount" =>    411819.98],
            ["id" =>    2160, "amount" =>    146970],
            ["id" =>    2161, "amount" =>    48990],
            ["id" =>    2162, "amount" =>    19596],
            ["id" =>    2163, "amount" =>    151869],
            ["id" =>    2164, "amount" =>    48990],
            ["id" =>    2165, "amount" =>    14697],
            ["id" =>    2166, "amount" =>    111982.4],
            ["id" =>    2167, "amount" =>    106383.28],
            ["id" =>    2168, "amount" =>    79781],
            ["id" =>    2169, "amount" =>    83980],
            ["id" =>    2170, "amount" =>    419900],
            ["id" =>    2171, "amount" =>    125970],
            ["id" =>    2172, "amount" =>    92378],
            ["id" =>    2173, "amount" =>    113373],
            ["id" =>    2174, "amount" =>    62999],
            ["id" =>    2175, "amount" =>    18500],
            ["id" =>    2176, "amount" =>    15400],
            ["id" =>    2177, "amount" =>    37500],
            ["id" =>    2178, "amount" =>    20750],
            ["id" =>    2179, "amount" =>    32399],
            ["id" =>    2180, "amount" =>    89000],
            ["id" =>    2181, "amount" =>    42000],
            ["id" =>    2182, "amount" =>    34000],
            ["id" =>    2183, "amount" =>    24405],
            ["id" =>    2184, "amount" =>    50000],
            ["id" =>    2185, "amount" =>    18500],
            ["id" =>    2186, "amount" =>    19000],
            ["id" =>    2187, "amount" =>    19000],
            ["id" =>    2188, "amount" =>    27132],
            ["id" =>    2189, "amount" =>    13200],
            ["id" =>    2190, "amount" =>    13750],
            ["id" =>    2191, "amount" =>    24000],
            ["id" =>    2192, "amount" =>    15697],
            ["id" =>    2193, "amount" =>    42500],
            ["id" =>    2194, "amount" =>    53488],
            ["id" =>    2195, "amount" =>    58578],
            ["id" =>    2196, "amount" =>    31479],
            ["id" =>    2197, "amount" =>    23050],
            ["id" =>    2198, "amount" =>    15480],
            ["id" =>    2199, "amount" =>    9839],
            ["id" =>    2200, "amount" =>    13200],
            ["id" =>    2201, "amount" =>    42000],
            ["id" =>    2202, "amount" =>    21450],
            ["id" =>    2203, "amount" =>    21496],
            ["id" =>    2204, "amount" =>    26100],
            ["id" =>    2205, "amount" =>    40488],
            ["id" =>    2206, "amount" =>    16536],
            ["id" =>    2207, "amount" =>    44500],
            ["id" =>    2208, "amount" =>    12900],
            ["id" =>    2209, "amount" =>    17778],
            ["id" =>    2210, "amount" =>    23301],
            ["id" =>    2211, "amount" =>    6299],
            ["id" =>    2212, "amount" =>    7689],
            ["id" =>    2213, "amount" =>    7689],
            ["id" =>    2214, "amount" =>    18500],
            ["id" =>    2215, "amount" =>    20020],
            ["id" =>    2216, "amount" =>    20020],
            ["id" =>    2217, "amount" =>    42001],
            ["id" =>    2218, "amount" =>    45000],
            ["id" =>    2219, "amount" =>    24500],
            ["id" =>    2220, "amount" =>    25000],
            ["id" =>    2221, "amount" =>    19000],
            ["id" =>    2222, "amount" =>    19800],
            ["id" =>    2223, "amount" =>    33500],
            ["id" =>    2224, "amount" =>    18500],
            ["id" =>    2225, "amount" =>    33500],
            ["id" =>    2226, "amount" =>    43597],
            ["id" =>    2227, "amount" =>    33500],
            ["id" =>    2228, "amount" =>    19000],
            ["id" =>    2229, "amount" =>    30290],
            ["id" =>    2230, "amount" =>    31000],
            ["id" =>    2231, "amount" =>    33000],
            ["id" =>    2232, "amount" =>    18700],
            ["id" =>    2233, "amount" =>    98500],
            ["id" =>    2234, "amount" =>    42000],
            ["id" =>    2235, "amount" =>    26000],
            ["id" =>    2236, "amount" =>    20001],
            ["id" =>    2237, "amount" =>    60500],
            ["id" =>    2238, "amount" =>    56000],
            ["id" =>    2239, "amount" =>    113800],
            ["id" =>    2240, "amount" =>    206000],
            ["id" =>    2241, "amount" =>    249896],
            ["id" =>    2242, "amount" =>    90500],
            ["id" =>    2243, "amount" =>    90500],
            ["id" =>    2244, "amount" =>    90500],
            ["id" =>    2245, "amount" =>    90500],
            ["id" =>    2246, "amount" =>    177000],
            ["id" =>    2247, "amount" =>    33000],
            ["id" =>    2248, "amount" =>    48129],
            ["id" =>    2249, "amount" =>    1],
            ["id" =>    2250, "amount" =>    15749.25],
            ["id" =>    2251, "amount" =>    6999.24],
            ["id" =>    2252, "amount" =>    4616],
            ["id" =>    2253, "amount" =>    4616],
            ["id" =>    2254, "amount" =>    7623],
            ["id" =>    2255, "amount" =>    5082],
            ["id" =>    2256, "amount" =>    7623],
            ["id" =>    2257, "amount" =>    7623],
            ["id" =>    2258, "amount" =>    7623],
            ["id" =>    2259, "amount" =>    7623],
            ["id" =>    2260, "amount" =>    3958.2],
            ["id" =>    2261, "amount" =>    4615],
            ["id" =>    2262, "amount" =>    12600],
            ["id" =>    2263, "amount" =>    12600],
            ["id" =>    2264, "amount" =>    4299],
            ["id" =>    2265, "amount" =>    9597],
            ["id" =>    2266, "amount" =>    23336],
            ["id" =>    2267, "amount" =>    2980],
            ["id" =>    2269, "amount" =>    4616],
            ["id" =>    2270, "amount" =>    5717],
            ["id" =>    2271, "amount" =>    3958.2],
            ["id" =>    2272, "amount" =>    8398],
            ["id" =>    2273, "amount" =>    8398],
            ["id" =>    2274, "amount" =>    8712],
            ["id" =>    2275, "amount" =>    4616],
            ["id" =>    2276, "amount" =>    8712],
            ["id" =>    2278, "amount" =>    6999.34],
            ["id" =>    2279, "amount" =>    4616],
            ["id" =>    2280, "amount" =>    4616],
            ["id" =>    2281, "amount" =>    5717],
            ["id" =>    2282, "amount" =>    14300],
            ["id" =>    2283, "amount" =>    20500],
            ["id" =>    2284, "amount" =>    16500],
            ["id" =>    2285, "amount" =>    42000],
            ["id" =>    2286, "amount" =>    31900],
            ["id" =>    2287, "amount" =>    20000],
            ["id" =>    2288, "amount" =>    5599],
            ["id" =>    2289, "amount" =>    9099.57],
            ["id" =>    2290, "amount" =>    3958.2],
            ["id" =>    2291, "amount" =>    43000],
            ["id" =>    2292, "amount" =>    43600],
            ["id" =>    2300, "amount" =>    60000],
            ["id" =>    2301, "amount" =>    24000],
            ["id" =>    2302, "amount" =>    155764],
            ["id" =>    2303, "amount" =>    249220],
            ["id" =>    2304, "amount" =>    139075],
            ["id" =>    2305, "amount" =>    127949],
            ["id" =>    2306, "amount" =>    295947.9],
            ["id" =>    2310, "amount" =>    47000],
            ["id" =>    2311, "amount" =>    18810],
            ["id" =>    2312, "amount" =>    20405],
            ["id" =>    2313, "amount" =>    29150],
            ["id" =>    2314, "amount" =>    20900],
            ["id" =>    2315, "amount" =>    47000],
            ["id" =>    2316, "amount" =>    12320],
            ["id" =>    2317, "amount" =>    16500],
            ["id" =>    2318, "amount" =>    13999],
            ["id" =>    2319, "amount" =>    18810],
            ["id" =>    2320, "amount" =>    18400],
            ["id" =>    2321, "amount" =>    18400],
            ["id" =>    2322, "amount" =>    14300],
            ["id" =>    2323, "amount" =>    18400],
            ["id" =>    2324, "amount" =>    20405],
            ["id" =>    2325, "amount" =>    29150],
            ["id" =>    2326, "amount" =>    47000],
            ["id" =>    2327, "amount" =>    13999],
            ["id" =>    2328, "amount" =>    22000],
            ["id" =>    2329, "amount" =>    21900],
            ["id" =>    2330, "amount" =>    21900],
            ["id" =>    2331, "amount" =>    23650],
            ["id" =>    2332, "amount" =>    27500],
            ["id" =>    2333, "amount" =>    20900],
            ["id" =>    2334, "amount" =>    20975],
            ["id" =>    2335, "amount" =>    29150],
            ["id" =>    2336, "amount" =>    23320],
            ["id" =>    2337, "amount" =>    23320],
            ["id" =>    2338, "amount" =>    23320],
            ["id" =>    2339, "amount" =>    23320],
            ["id" =>    2340, "amount" =>    20900],
            ["id" =>    2341, "amount" =>    20900],
            ["id" =>    2342, "amount" =>    18700],
            ["id" =>    2343, "amount" =>    47000],
            ["id" =>    2344, "amount" =>    20900],
            ["id" =>    2345, "amount" =>    23320],
            ["id" =>    2346, "amount" =>    22000],
            ["id" =>    2347, "amount" =>    20900],
            ["id" =>    2348, "amount" =>    23000],
            ["id" =>    2349, "amount" =>    5000],
            ["id" =>    2350, "amount" =>    17490],
            ["id" =>    2351, "amount" =>    4620],
            ["id" =>    2353, "amount" =>    205335],
            ["id" =>    2354, "amount" =>    1007939.84],
            ["id" =>    2355, "amount" =>    784440],
            ["id" =>    2356, "amount" =>    221130],
            ["id" =>    2357, "amount" =>    1167939.78],
            ["id" =>    2358, "amount" =>    56697.39],
            ["id" =>    2359, "amount" =>    188993.4],
            ["id" =>    2360, "amount" =>    144893.56],
            ["id" =>    2361, "amount" =>    176393.56],
            ["id" =>    2362, "amount" =>    170093.52],
            ["id" =>    2363, "amount" =>    154926],
            ["id" =>    2364, "amount" =>    151164],
            ["id" =>    2365, "amount" =>    205588.06],
            ["id" =>    2366, "amount" =>    107406.04],
            ["id" =>    2367, "amount" =>    64274.96],
            ["id" =>    2368, "amount" =>    60506.08],
            ["id" =>    2369, "amount" =>    56737.05],
            ["id" =>    2370, "amount" =>    11440],
            ["id" =>    2371, "amount" =>    23336],
            ["id" =>    2372, "amount" =>    2958],
            ["id" =>    2373, "amount" =>    2958],
            ["id" =>    2374, "amount" =>    2958],
            ["id" =>    2375, "amount" =>    2618],
            ["id" =>    2376, "amount" =>    2618],
            ["id" =>    2377, "amount" =>    2618],
            ["id" =>    2378, "amount" =>    2618],
            ["id" =>    2379, "amount" =>    2618],
            ["id" =>    2380, "amount" =>    2618],
            ["id" =>    2381, "amount" =>    2618],
            ["id" =>    2382, "amount" =>    2618],
            ["id" =>    2383, "amount" =>    63000],
            ["id" =>    2384, "amount" =>    21000],
            ["id" =>    2385, "amount" =>    28500],
            ["id" =>    2386, "amount" =>    47000],
            ["id" =>    2387, "amount" =>    23500],
            ["id" =>    2388, "amount" =>    20000],
            ["id" =>    2389, "amount" =>    14600],
            ["id" =>    2390, "amount" =>    20500],
            ["id" =>    2391, "amount" =>    19000],
            ["id" =>    2392, "amount" =>    42500],
            ["id" =>    2393, "amount" =>    112000],
            ["id" =>    2394, "amount" =>    19800],
            ["id" =>    2395, "amount" =>    30500],
            ["id" =>    2396, "amount" =>    175000],
            ["id" =>    2397, "amount" =>    250000],
            ["id" =>    2398, "amount" =>    180500],
            ["id" =>    2399, "amount" =>    41000],
            ["id" =>    2400, "amount" =>    174000],
            ["id" =>    2401, "amount" =>    20000],
            ["id" =>    2402, "amount" =>    40500],
            ["id" =>    2403, "amount" =>    18500],
            ["id" =>    2404, "amount" =>    31000],
            ["id" =>    2405, "amount" =>    60000],
            ["id" =>    2406, "amount" =>    14795],
            ["id" =>    2407, "amount" =>    26000],
            ["id" =>    2408, "amount" =>    59800],
            ["id" =>    2409, "amount" =>    40000],
            ["id" =>    2410, "amount" =>    15400],
            ["id" =>    2411, "amount" =>    29000],
            ["id" =>    2412, "amount" =>    30000],
            ["id" =>    2413, "amount" =>    14000],
            ["id" =>    2414, "amount" =>    33013],
            ["id" =>    2415, "amount" =>    35000],
            ["id" =>    2416, "amount" =>    25063],
            ["id" =>    2417, "amount" =>    56000],
            ["id" =>    2418, "amount" =>    17500],
            ["id" =>    2419, "amount" =>    18899],
            ["id" =>    2420, "amount" =>    165000],
            ["id" =>    2421, "amount" =>    228590],
            ["id" =>    2422, "amount" =>    90500],
            ["id" =>    2423, "amount" =>    90500],
            ["id" =>    2424, "amount" =>    19000],
            ["id" =>    2425, "amount" =>    40000],
            ["id" =>    2426, "amount" =>    37800],
            ["id" =>    2427, "amount" =>    19250],
            ["id" =>    2428, "amount" =>    20500],
            ["id" =>    2429, "amount" =>    7200],
            ["id" =>    2430, "amount" =>    14500],
            ["id" =>    2431, "amount" =>    13860],
            ["id" =>    2432, "amount" =>    4616],
            ["id" =>    2433, "amount" =>    4616],
            ["id" =>    2434, "amount" =>    6880.38],
            ["id" =>    2435, "amount" =>    6880.38],
            ["id" =>    2436, "amount" =>    6880.38],
            ["id" =>    2437, "amount" =>    6880.38],
            ["id" =>    2438, "amount" =>    6880.38],
            ["id" =>    2439, "amount" =>    7150],
            ["id" =>    2440, "amount" =>    7150],
            ["id" =>    2441, "amount" =>    7150],
            ["id" =>    2442, "amount" =>    7420],
            ["id" =>    2443, "amount" =>    7420],
            ["id" =>    2444, "amount" =>    7420],
            ["id" =>    2445, "amount" =>    7420],
            ["id" =>    2446, "amount" =>    7420],
            ["id" =>    2447, "amount" =>    14696],
            ["id" =>    2448, "amount" =>    15400],
            ["id" =>    2449, "amount" =>    15400],
            ["id" =>    2450, "amount" =>    51390],
            ["id" =>    2451, "amount" =>    54960],
            ["id" =>    2452, "amount" =>    27396],
            ["id" =>    2453, "amount" =>    16197],
            ["id" =>    2454, "amount" =>    17000],
            ["id" =>    2455, "amount" =>    19000],
            ["id" =>    2456, "amount" =>    20900],
            ["id" =>    2457, "amount" =>    34500],
            ["id" =>    2458, "amount" =>    216199],
            ["id" =>    2459, "amount" =>    48000],
            ["id" =>    2460, "amount" =>    23000],
            ["id" =>    2461, "amount" =>    20000],
            ["id" =>    2462, "amount" =>    24000],
            ["id" =>    2463, "amount" =>    21563],
            ["id" =>    2464, "amount" =>    21200],
            ["id" =>    2465, "amount" =>    54330],
            ["id" =>    2466, "amount" =>    188500],
            ["id" =>    2467, "amount" =>    49250],
            ["id" =>    2468, "amount" =>    33000],
            ["id" =>    2469, "amount" =>    28290],
            ["id" =>    2470, "amount" =>    24900],
            ["id" =>    2471, "amount" =>    17800],
            ["id" =>    2472, "amount" =>    8119.8],
            ["id" =>    2473, "amount" =>    149980],
            ["id" =>    2474, "amount" =>    45088],
            ["id" =>    2475, "amount" =>    20000],
            ["id" =>    2476, "amount" =>    42045],
            ["id" =>    2477, "amount" =>    42500],
            ["id" =>    2478, "amount" =>    32000],
            ["id" =>    2479, "amount" =>    59786],
            ["id" =>    2480, "amount" =>    44000],
            ["id" =>    2481, "amount" =>    47000],
            ["id" =>    2482, "amount" =>    14800],
            ["id" =>    2483, "amount" =>    14500],
            ["id" =>    2484, "amount" =>    2300],
            ["id" =>    2485, "amount" =>    51000],
            ["id" =>    2486, "amount" =>    155000],
            ["id" =>    2487, "amount" =>    40500],
            ["id" =>    2488, "amount" =>    21000],
            ["id" =>    2489, "amount" =>    33500],
            ["id" =>    2490, "amount" =>    23000],
            ["id" =>    2491, "amount" =>    9099.78],
            ["id" =>    2492, "amount" =>    4327],
            ["id" =>    2493, "amount" =>    188979],
            ["id" =>    2494, "amount" =>    314964],
            ["id" =>    2495, "amount" =>    314964],
            ["id" =>    2496, "amount" =>    377956.2],
            ["id" =>    2497, "amount" =>    390554.74],
            ["id" =>    2498, "amount" =>    62992.8],
            ["id" =>    2499, "amount" =>    24000],
            ["id" =>    2500, "amount" =>    54000],
            ["id" =>    2501, "amount" =>    6000],
            ["id" =>    2502, "amount" =>    223960.4],
            ["id" =>    2503, "amount" =>    335940.6],
            ["id" =>    2504, "amount" =>    279950.5],
            ["id" =>    2505, "amount" =>    55990],
            ["id" =>    2506, "amount" =>    29678.4],
            ["id" =>    2507, "amount" =>    37098],
            ["id" =>    2508, "amount" =>    51937.2],
            ["id" =>    2509, "amount" =>    37098],
            ["id" =>    2510, "amount" =>    318304],
            ["id" =>    2511, "amount" =>    296352],
            ["id" =>    2512, "amount" =>    54880],
            ["id" =>    2513, "amount" =>    10976],
            ["id" =>    2514, "amount" =>    11000],
            ["id" =>    2515, "amount" =>    14025],
            ["id" =>    2516, "amount" =>    20405],
            ["id" =>    2517, "amount" =>    13100],
            ["id" =>    2518, "amount" =>    8119.91],
            ["id" =>    2519, "amount" =>    4616],
            ["id" =>    2520, "amount" =>    35000],
            ["id" =>    2521, "amount" =>    8712],
            ["id" =>    2522, "amount" =>    9099.73],
            ["id" =>    2523, "amount" =>    5039.65],
            ["id" =>    2524, "amount" =>    20597],
            ["id" =>    2525, "amount" =>    15950],
            ["id" =>    2526, "amount" =>    16773],
            ["id" =>    2527, "amount" =>    60000],
            ["id" =>    2528, "amount" =>    16500],
            ["id" =>    2529, "amount" =>    21249],
            ["id" =>    2530, "amount" =>    5599.33],
            ["id" =>    2531, "amount" =>    10010],
            ["id" =>    2532, "amount" =>    40000],
            ["id" =>    2533, "amount" =>    88888],
            ["id" =>    2534, "amount" =>    35880],
            ["id" =>    2535, "amount" =>    33500],
            ["id" =>    2536, "amount" =>    14300],
            ["id" =>    2537, "amount" =>    18693],
            ["id" =>    2538, "amount" =>    22000],
            ["id" =>    2539, "amount" =>    9839],
            ["id" =>    2540, "amount" =>    115000],
            ["id" =>    2541, "amount" =>    5599],
            ["id" =>    2542, "amount" =>    13188],
            ["id" =>    2543, "amount" =>    14644],
            ["id" =>    2544, "amount" =>    17490],
            ["id" =>    2545, "amount" =>    14999],
            ["id" =>    2546, "amount" =>    90500],
            ["id" =>    2547, "amount" =>    24000],
            ["id" =>    2548, "amount" =>    17175.48],
            ["id" =>    2549, "amount" =>    20020],
            ["id" =>    2550, "amount" =>    95000],
            ["id" =>    2551, "amount" =>    25400],
            ["id" =>    2552, "amount" =>    28800],
            ["id" =>    2553, "amount" =>    19795],
            ["id" =>    2554, "amount" =>    12000],
            ["id" =>    2555, "amount" =>    29788],
            ["id" =>    2556, "amount" =>    15400],
            ["id" =>    2557, "amount" =>    50340],
            ["id" =>    2558, "amount" =>    20000],
            ["id" =>    2559, "amount" =>    42100],
            ["id" =>    2560, "amount" =>    2300],
            ["id" =>    2561, "amount" =>    14000],
            ["id" =>    2562, "amount" =>    19400],
            ["id" =>    2563, "amount" =>    228490],
            ["id" =>    2564, "amount" =>    194788],
            ["id" =>    2565, "amount" =>    21000],
            ["id" =>    2566, "amount" =>    55000],
            ["id" =>    2567, "amount" =>    24000],
            ["id" =>    2568, "amount" =>    68000],
            ["id" =>    2569, "amount" =>    185000],
            ["id" =>    2570, "amount" =>    160000],
            ["id" =>    2571, "amount" =>    205000],
            ["id" =>    2572, "amount" =>    27400],
            ["id" =>    2573, "amount" =>    29890],
            ["id" =>    2574, "amount" =>    22500],
            ["id" =>    2575, "amount" =>    55500],
            ["id" =>    2576, "amount" =>    820],
            ["id" =>    2577, "amount" =>    36500],
            ["id" =>    2578, "amount" =>    35000],
            ["id" =>    2579, "amount" =>    34065],
            ["id" =>    2580, "amount" =>    17000],
            ["id" =>    2581, "amount" =>    23500],
            ["id" =>    2582, "amount" =>    23500],
            ["id" =>    2583, "amount" =>    33000],
            ["id" =>    2584, "amount" =>    18281],
            ["id" =>    2585, "amount" =>    23500],
            ["id" =>    2586, "amount" =>    90500],
            ["id" =>    2587, "amount" =>    90500],
            ["id" =>    2588, "amount" =>    70000],
            ["id" =>    2589, "amount" =>    45501],
            ["id" =>    2590, "amount" =>    23999],
            ["id" =>    2591, "amount" =>    97380],
            ["id" =>    2592, "amount" =>    199000],
            ["id" =>    2593, "amount" =>    190000],
            ["id" =>    2594, "amount" =>    134000],
            ["id" =>    2595, "amount" =>    265198],
            ["id" =>    2596, "amount" =>    16760],
            ["id" =>    2597, "amount" =>    770],
            ["id" =>    2598, "amount" =>    42201],
            ["id" =>    2599, "amount" =>    80000],
            ["id" =>    2600, "amount" =>    30500],
            ["id" =>    2601, "amount" =>    18700],
            ["id" =>    2602, "amount" =>    15001],
            ["id" =>    2603, "amount" =>    15400],
            ["id" =>    2604, "amount" =>    32000],
            ["id" =>    13317, "amount" =>    18150],
            ["id" =>    14068, "amount" =>    18150],
            ["id" =>    14108, "amount" =>    18150],
            ["id" =>    14223, "amount" =>    18150],
            ["id" =>    15423, "amount" =>    18150],
            ["id" =>    15828, "amount" =>    18295],
            ["id" =>    16281, "amount" =>    16000],
            ["id" =>    19591, "amount" =>    21333.33],
            ["id" =>    20739, "amount" =>    4033.33],
            ["id" =>    20749, "amount" =>    21333.33],
            ["id" =>    20852, "amount" =>    21333.33],
            ["id" =>    20859, "amount" =>    21333.33],
            ["id" =>    20864, "amount" =>    21333.33],
            ["id" =>    29047, "amount" =>    21333.33],
            ["id" =>    29511, "amount" =>    3544.44],
            ["id" =>    29513, "amount" =>    3544.44],
            ["id" =>    29562, "amount" =>    21333.33],
            ["id" =>    29578, "amount" =>    21333.33],
            ["id" =>    30592, "amount" =>    21269.82],
            ["id" =>    31379, "amount" =>    2571.43],
            ["id" =>    31462, "amount" =>    21333.33],
            ["id" =>    31469, "amount" =>    21333.33],
            ["id" =>    32673, "amount" =>    21684.31],
            ["id" =>    32774, "amount" =>    21333.33],
            ["id" =>    33160, "amount" =>    21589.2],
            ["id" =>    33163, "amount" =>    21269.82],
            ["id" =>    33177, "amount" =>    21684.31],
            ["id" =>    33178, "amount" =>    21684.31],
            ["id" =>    33547, "amount" =>    21333.33],
            ["id" =>    34848, "amount" =>    3544.44],
            ["id" =>    35190, "amount" =>    3544.44],
            ["id" =>    36631, "amount" =>    21333.33],
            ["id" =>    36647, "amount" =>    21333.33],
            ["id" =>    36648, "amount" =>    21333.33],
            ["id" =>    36690, "amount" =>    19702.94],
            ["id" =>    37289, "amount" =>    43368.62],
            ["id" =>    37327, "amount" =>    4333.33],
            ["id" =>    37359, "amount" =>    16000],
            ["id" =>    37405, "amount" =>    21589.2],
            ["id" =>    38380, "amount" =>    21589.2],
            ["id" =>    38483, "amount" =>    4333.33],
            ["id" =>    38484, "amount" =>    4333.33],
            ["id" =>    38485, "amount" =>    4333.33],
            ["id" =>    38513, "amount" =>    4333.33],
            ["id" =>    40492, "amount" =>    4033.33],
            ["id" =>    40553, "amount" =>    21333.33],
            ["id" =>    41253, "amount" =>    3544.44],
            ["id" =>    41258, "amount" =>    3544.44],
            ["id" =>    41814, "amount" =>    3544.44],
            ["id" =>    43133, "amount" =>    4333.33],
            ["id" =>    43195, "amount" =>    4333.33],
            ["id" =>    45733, "amount" =>    21333.33],
            ["id" =>    45791, "amount" =>    4333.33],
            ["id" =>    45838, "amount" =>    4333.33],
            ["id" =>    46485, "amount" =>    21333.33],
            ["id" =>    46521, "amount" =>    21333.33],
            ["id" =>    46564, "amount" =>    4333.33],
            ["id" =>    47676, "amount" =>    4333.33],
            ["id" =>    47678, "amount" =>    4333.33],
            ["id" =>    47689, "amount" =>    21333.33],
            ["id" =>    49778, "amount" =>    4033.33],
            ["id" =>    49797, "amount" =>    16000],
            ["id" =>    49810, "amount" =>    4333.33],
            ["id" =>    49811, "amount" =>    4333.33],
            ["id" =>    49819, "amount" =>    21333.33],
            ["id" =>    51222, "amount" =>    21333.33],
            ["id" =>    51245, "amount" =>    21333.33],
            ["id" =>    52164, "amount" =>    4333.33],
            ["id" =>    52556, "amount" =>    25200],
            ["id" =>    53192, "amount" =>    2493.33],
            ["id" =>    53218, "amount" =>    4033.33],
            ["id" =>    54169, "amount" =>    4333.33],
            ["id" =>    54172, "amount" =>    4333.33],
            ["id" =>    55043, "amount" =>    2493.33],
            ["id" =>    55059, "amount" =>    21333.33],
            ["id" =>    56997, "amount" =>    2493.33],
            ["id" =>    57001, "amount" =>    16000],
            ["id" =>    57006, "amount" =>    16000],
            ["id" =>    57007, "amount" =>    16000],
            ["id" =>    57034, "amount" =>    2493.33],
            ["id" =>    57047, "amount" =>    4333.33],
            ["id" =>    59002, "amount" =>    4333.33],
            ["id" =>    59045, "amount" =>    21333.33],
            ["id" =>    59158, "amount" =>    1750],
            ["id" =>    59958, "amount" =>    3544.44],
            ["id" =>    60753, "amount" =>    4333.33],
            ["id" =>    60818, "amount" =>    2493.33],
            ["id" =>    61728, "amount" =>    3544.44],
            ["id" =>    62789, "amount" =>    2493.33],
            ["id" =>    62799, "amount" =>    16000],
            ["id" =>    62812, "amount" =>    16000],
            ["id" =>    62885, "amount" =>    2493.33],
            ["id" =>    62938, "amount" =>    2493.33],
            ["id" =>    62949, "amount" =>    16000],
            ["id" =>    62958, "amount" =>    16000],
            ["id" =>    62961, "amount" =>    16000],
            ["id" =>    62962, "amount" =>    16000],
            ["id" =>    62990, "amount" =>    16000],
            ["id" =>    63067, "amount" =>    4333.33],
            ["id" =>    63069, "amount" =>    2493.33],
            ["id" =>    64045, "amount" =>    5571.74],
            ["id" =>    64337, "amount" =>    2493.33],
            ["id" =>    64369, "amount" =>    2493.33],
            ["id" =>    64394, "amount" =>    21333.33],
            ["id" =>    64965, "amount" =>    2493.33],
            ["id" =>    65107, "amount" =>    5571.74],
            ["id" =>    65568, "amount" =>    2493.33],
            ["id" =>    65579, "amount" =>    2493.33],
            ["id" =>    65616, "amount" =>    5571.74],
            ["id" =>    66625, "amount" =>    7257.94],
            ["id" =>    66941, "amount" =>    21333.33],
            ["id" =>    66986, "amount" =>    3544.44],
            ["id" =>    67005, "amount" =>    3544.44],
            ["id" =>    67055, "amount" =>    16000],
            ["id" =>    67068, "amount" =>    16000],
            ["id" =>    67071, "amount" =>    16000],
            ["id" =>    67112, "amount" =>    16000],
            ["id" =>    67150, "amount" =>    2493.33],
            ["id" =>    67324, "amount" =>    4319.46],
            ["id" =>    67933, "amount" =>    16000],
            ["id" =>    67945, "amount" =>    21333.33],
            ["id" =>    67946, "amount" =>    16000],
            ["id" =>    67951, "amount" =>    16000],
            ["id" =>    68331, "amount" =>    3544.44],
            ["id" =>    68351, "amount" =>    3544.44],
            ["id" =>    68375, "amount" =>    3544.44],
            ["id" =>    68651, "amount" =>    21333.33],
            ["id" =>    68741, "amount" =>    3544.44],
            ["id" =>    68777, "amount" =>    16000],
            ["id" =>    68799, "amount" =>    21333.33],
            ["id" =>    68802, "amount" =>    16000],
            ["id" =>    70058, "amount" =>    21333.33],
            ["id" =>    70109, "amount" =>    16000],
            ["id" =>    70139, "amount" =>    21333.33],
            ["id" =>    70141, "amount" =>    16000],
            ["id" =>    70175, "amount" =>    2493.33],
            ["id" =>    70222, "amount" =>    4033.33],
            ["id" =>    70963, "amount" =>    3544.44],
            ["id" =>    71440, "amount" =>    3544.44],
            ["id" =>    71661, "amount" =>    3544.44],
            ["id" =>    72069, "amount" =>    4319.46],
            ["id" =>    72906, "amount" =>    3544.44],
            ["id" =>    73003, "amount" =>    16000],
            ["id" =>    73137, "amount" =>    21333.33],
            ["id" =>    74112, "amount" =>    3544.44],
            ["id" =>    74118, "amount" =>    3544.44],
            ["id" =>    74119, "amount" =>    3544.44],
            ["id" =>    74125, "amount" =>    3544.44],
            ["id" =>    74127, "amount" =>    3544.44],
            ["id" =>    74145, "amount" =>    3544.44],
            ["id" =>    74147, "amount" =>    3544.44],
            ["id" =>    74150, "amount" =>    3544.44],
            ["id" =>    74282, "amount" =>    2493.33],
            ["id" =>    74376, "amount" =>    14600],
            ["id" =>    74377, "amount" =>    26226.13],
            ["id" =>    74378, "amount" =>    14300],
            ["id" =>    74379, "amount" =>    38767],
            ["id" =>    74380, "amount" =>    26331.98],
            ["id" =>    74381, "amount" =>    26331.98],
            ["id" =>    74382, "amount" =>    30527.66],
            ["id" =>    74383, "amount" =>    22330],
            ["id" =>    74384, "amount" =>    30527.66],
            ["id" =>    74385, "amount" =>    27115],
            ["id" =>    74386, "amount" =>    14300],
            ["id" =>    74387, "amount" =>    22330],
            ["id" =>    74388, "amount" =>    14300],
            ["id" =>    74389, "amount" =>    22330],
            ["id" =>    74390, "amount" =>    27414.56],
            ["id" =>    74391, "amount" =>    27414.56],
            ["id" =>    74392, "amount" =>    9500],
            ["id" =>    74393, "amount" =>    9240],
            ["id" =>    74394, "amount" =>    26352],
            ["id" =>    74395, "amount" =>    18590],
            ["id" =>    74396, "amount" =>    22330],
            ["id" =>    74397, "amount" =>    27115],
            ["id" =>    74398, "amount" =>    18900],
            ["id" =>    74399, "amount" =>    18592.52],
            ["id" =>    74400, "amount" =>    27115],
            ["id" =>    74401, "amount" =>    27115],
            ["id" =>    74402, "amount" =>    14300],
            ["id" =>    74403, "amount" =>    20735],
            ["id" =>    74404, "amount" =>    20735],
            ["id" =>    74405, "amount" =>    14300],
            ["id" =>    74406, "amount" =>    27115],
            ["id" =>    74407, "amount" =>    27115],
            ["id" =>    74408, "amount" =>    18300],
            ["id" =>    74409, "amount" =>    25948.55],
            ["id" =>    74410, "amount" =>    28303],
            ["id" =>    74411, "amount" =>    30527.66],
            ["id" =>    74412, "amount" =>    26352],
            ["id" =>    74413, "amount" =>    18592.52],
            ["id" =>    74414, "amount" =>    24401],
            ["id" =>    74415, "amount" =>    23000],
            ["id" =>    74416, "amount" =>    17710],
            ["id" =>    74417, "amount" =>    40700],
            ["id" =>    74418, "amount" =>    10480],
            ["id" =>    74419, "amount" =>    18500],
            ["id" =>    74420, "amount" =>    24401],
            ["id" =>    74421, "amount" =>    21000],
            ["id" =>    74422, "amount" =>    20700],
            ["id" =>    74423, "amount" =>    16774],
            ["id" =>    74424, "amount" =>    17710],
            ["id" =>    74425, "amount" =>    17710],
            ["id" =>    74426, "amount" =>    18319],
            ["id" =>    74427, "amount" =>    17710],
            ["id" =>    74428, "amount" =>    8970],
            ["id" =>    74429, "amount" =>    26174],
            ["id" =>    74430, "amount" =>    8970],
            ["id" =>    74431, "amount" =>    14000],
            ["id" =>    74432, "amount" =>    7012],
            ["id" =>    74433, "amount" =>    22330],
            ["id" =>    74434, "amount" =>    30527.66],
            ["id" =>    74435, "amount" =>    10000],
            ["id" =>    74436, "amount" =>    30527.66],
            ["id" =>    74437, "amount" =>    22330],
            ["id" =>    74438, "amount" =>    17710],
            ["id" =>    74439, "amount" =>    17710],
            ["id" =>    74440, "amount" =>    30527.66],
            ["id" =>    74441, "amount" =>    27115],
            ["id" =>    74442, "amount" =>    22330],
            ["id" =>    74443, "amount" =>    22330],
            ["id" =>    74444, "amount" =>    17129],
            ["id" =>    74445, "amount" =>    27115],
            ["id" =>    74446, "amount" =>    30527.66],
            ["id" =>    74447, "amount" =>    22330],
            ["id" =>    74448, "amount" =>    22330],
            ["id" =>    74449, "amount" =>    27115],
            ["id" =>    74450, "amount" =>    27115],
            ["id" =>    74451, "amount" =>    30527.66],
            ["id" =>    74452, "amount" =>    22330],
            ["id" =>    74453, "amount" =>    30527.66],
            ["id" =>    74454, "amount" =>    27115],
            ["id" =>    74455, "amount" =>    27414.56],
            ["id" =>    74456, "amount" =>    27115],
            ["id" =>    74457, "amount" =>    16500],
            ["id" =>    74458, "amount" =>    10099.5],
            ["id" =>    74459, "amount" =>    21560],
            ["id" =>    74460, "amount" =>    17710],
            ["id" =>    74461, "amount" =>    27280],
            ["id" =>    74462, "amount" =>    9240],
            ["id" =>    74463, "amount" =>    27280],
            ["id" =>    74464, "amount" =>    10000],
            ["id" =>    74465, "amount" =>    24693],
            ["id" =>    74466, "amount" =>    22330],
            ["id" =>    74467, "amount" =>    30431],
            ["id" =>    74468, "amount" =>    18900],
            ["id" =>    74469, "amount" =>    24702],
            ["id" =>    74470, "amount" =>    18400],
            ["id" =>    74471, "amount" =>    25706.11],
            ["id" =>    74472, "amount" =>    20735],
            ["id" =>    74473, "amount" =>    18900],
            ["id" =>    74474, "amount" =>    24674],
            ["id" =>    74475, "amount" =>    18500],
            ["id" =>    74476, "amount" =>    22330],
            ["id" =>    74477, "amount" =>    25948.55],
            ["id" =>    74478, "amount" =>    17000],
            ["id" =>    74479, "amount" =>    25948.55],
            ["id" =>    74480, "amount" =>    24702],
            ["id" =>    74481, "amount" =>    25948.55],
            ["id" =>    74482, "amount" =>    24702],
            ["id" =>    74483, "amount" =>    24702],
            ["id" =>    74484, "amount" =>    25948.55],
            ["id" =>    74485, "amount" =>    9850],
            ["id" =>    74487, "amount" =>    19720],
            ["id" =>    74488, "amount" =>    22330],
            ["id" =>    74489, "amount" =>    22330],
            ["id" =>    74492, "amount" =>    22330],
            ["id" =>    74493, "amount" =>    18480],
            ["id" =>    74499, "amount" =>    10430],
            ["id" =>    74500, "amount" =>    18900],
            ["id" =>    74501, "amount" =>    25706.11],
            ["id" =>    74502, "amount" =>    10900],
            ["id" =>    74506, "amount" =>    17500],
            ["id" =>    74507, "amount" =>    16000],
            ["id" =>    74508, "amount" =>    16500],
            ["id" =>    74509, "amount" =>    19900],
            ["id" =>    74510, "amount" =>    18480],
            ["id" =>    74512, "amount" =>    18480],
            ["id" =>    74513, "amount" =>    40540],
            ["id" =>    74514, "amount" =>    18500],
            ["id" =>    74516, "amount" =>    18500],
            ["id" =>    74517, "amount" =>    22330],
            ["id" =>    74519, "amount" =>    13500],
            ["id" =>    74520, "amount" =>    18900],
            ["id" =>    74521, "amount" =>    22770],
            ["id" =>    74523, "amount" =>    27280],
            ["id" =>    74525, "amount" =>    18500],
            ["id" =>    74526, "amount" =>    18500],
            ["id" =>    74537, "amount" =>    14000],
            ["id" =>    74539, "amount" =>    22330],
            ["id" =>    74544, "amount" =>    9900],
            ["id" =>    74546, "amount" =>    22330],
            ["id" =>    74547, "amount" =>    9900],
            ["id" =>    74548, "amount" =>    22330],
            ["id" =>    74550, "amount" =>    15000],
            ["id" =>    74551, "amount" =>    24184],
            ["id" =>    74552, "amount" =>    18480],
            ["id" =>    74553, "amount" =>    23000],
            ["id" =>    74554, "amount" =>    17500],
            ["id" =>    74555, "amount" =>    18350],
            ["id" =>    74556, "amount" =>    21450],
            ["id" =>    74557, "amount" =>    27280],
            ["id" =>    74558, "amount" =>    19720],
            ["id" =>    74559, "amount" =>    27280],
            ["id" =>    74560, "amount" =>    27280],
            ["id" =>    74561, "amount" =>    25465],
            ["id" =>    74563, "amount" =>    27280],
            ["id" =>    74564, "amount" =>    27115],
            ["id" =>    74565, "amount" =>    20735],
            ["id" =>    74566, "amount" =>    33990],
            ["id" =>    74567, "amount" =>    14800],
            ["id" =>    74571, "amount" =>    27280],
            ["id" =>    74573, "amount" =>    10000],
            ["id" =>    74574, "amount" =>    26331.98],
            ["id" =>    74577, "amount" =>    26331.98],
            ["id" =>    74581, "amount" =>    33500],
            ["id" =>    74583, "amount" =>    27414.56],
            ["id" =>    74584, "amount" =>    27414.56],
            ["id" =>    74585, "amount" =>    21500],
            ["id" =>    74587, "amount" =>    24000],
            ["id" =>    74588, "amount" =>    26331.98],
            ["id" =>    74589, "amount" =>    21450],
            ["id" =>    74590, "amount" =>    21450],
            ["id" =>    74592, "amount" =>    10500],
            ["id" =>    74593, "amount" =>    19720],
            ["id" =>    74594, "amount" =>    26331.98],
            ["id" =>    74596, "amount" =>    21450],
            ["id" =>    74598, "amount" =>    18500],
            ["id" =>    74600, "amount" =>    14000],
            ["id" =>    74602, "amount" =>    24694],
            ["id" =>    74603, "amount" =>    24694],
            ["id" =>    74604, "amount" =>    20735],
            ["id" =>    74605, "amount" =>    20735],
            ["id" =>    74606, "amount" =>    18480],
            ["id" =>    74607, "amount" =>    18840],
            ["id" =>    74608, "amount" =>    18480],
            ["id" =>    74609, "amount" =>    19635],
            ["id" =>    74611, "amount" =>    13090],
            ["id" =>    74612, "amount" =>    13090],
            ["id" =>    74652, "amount" =>    17000],
            ["id" =>    74658, "amount" =>    27115],
            ["id" =>    74659, "amount" =>    27115],
            ["id" =>    74661, "amount" =>    24702],
            ["id" =>    74669, "amount" =>    27115],
            ["id" =>    74671, "amount" =>    24702],
            ["id" =>    74673, "amount" =>    27115],
            ["id" =>    74674, "amount" =>    27115],
            ["id" =>    74675, "amount" =>    24702],
            ["id" =>    74677, "amount" =>    24702],
            ["id" =>    74854, "amount" =>    10480],
            ["id" =>    74855, "amount" =>    10725],
            ["id" =>    74856, "amount" =>    9240],
            ["id" =>    74857, "amount" =>    13580],
            ["id" =>    74858, "amount" =>    8000],
            ["id" =>    74859, "amount" =>    14630],
            ["id" =>    74861, "amount" =>    7266],
            ["id" =>    74862, "amount" =>    8970],
            ["id" =>    74864, "amount" =>    16479],
            ["id" =>    74865, "amount" =>    16479],
            ["id" =>    74866, "amount" =>    19600],
            ["id" =>    74867, "amount" =>    18480],
            ["id" =>    74868, "amount" =>    18480],
            ["id" =>    74869, "amount" =>    18480],
            ["id" =>    74871, "amount" =>    16479],
            ["id" =>    74872, "amount" =>    18480],
            ["id" =>    74873, "amount" =>    18480],
            ["id" =>    74874, "amount" =>    18480],
            ["id" =>    74875, "amount" =>    18480],
            ["id" =>    74876, "amount" =>    18480],
            ["id" =>    74877, "amount" =>    16170],
            ["id" =>    74878, "amount" =>    16479],
            ["id" =>    74879, "amount" =>    16479],
            ["id" =>    74880, "amount" =>    10725],
            ["id" =>    74881, "amount" =>    10725],
            ["id" =>    74882, "amount" =>    19600],
            ["id" =>    74883, "amount" =>    20735],
            ["id" =>    74884, "amount" =>    20735],
            ["id" =>    74886, "amount" =>    18480],
            ["id" =>    74887, "amount" =>    10480],
            ["id" =>    74889, "amount" =>    13500],
            ["id" =>    74890, "amount" =>    20500],
            ["id" =>    77447, "amount" =>    4033.33],
            ["id" =>    79099, "amount" =>    2493.33],
            ["id" =>    79285, "amount" =>    2571.43],
            ["id" =>    80393, "amount" =>    2493.33],
            ["id" =>    80396, "amount" =>    2493.33],
            ["id" =>    81578, "amount" =>    1199],
            ["id" =>    81579, "amount" =>    1199],
            ["id" =>    81584, "amount" =>    5450],
            ["id" =>    81585, "amount" =>    3786.32],
            ["id" =>    81587, "amount" =>    5450],
            ["id" =>    81612, "amount" =>    4419.08],
            ["id" =>    81685, "amount" =>    5450],
            ["id" =>    81690, "amount" =>    4419.08],
            ["id" =>    81701, "amount" =>    2493.33],
            ["id" =>    81716, "amount" =>    4419.08],
            ["id" =>    81718, "amount" =>    5450],
            ["id" =>    81719, "amount" =>    3786.32],
            ["id" =>    81724, "amount" =>    2493.33],
            ["id" =>    81727, "amount" =>    5450],
            ["id" =>    81728, "amount" =>    4419.08],
            ["id" =>    81745, "amount" =>    3786.32],
            ["id" =>    81750, "amount" =>    4419.08],
            ["id" =>    81781, "amount" =>    7921.96],
            ["id" =>    81782, "amount" =>    1199],
            ["id" =>    82699, "amount" =>    5450],
            ["id" =>    82701, "amount" =>    2493.33],
            ["id" =>    82706, "amount" =>    4333.33],
            ["id" =>    82708, "amount" =>    1998.33],
            ["id" =>    82711, "amount" =>    5450],
            ["id" =>    82723, "amount" =>    1199],
            ["id" =>    82728, "amount" =>    3786.32],
            ["id" =>    82729, "amount" =>    7921.96],
            ["id" =>    82731, "amount" =>    3786.32],
            ["id" =>    82733, "amount" =>    5450],
            ["id" =>    82735, "amount" =>    5450],
            ["id" =>    82736, "amount" =>    3786.32],
            ["id" =>    82737, "amount" =>    4419.08],
            ["id" =>    82738, "amount" =>    4419.08],
            ["id" =>    82785, "amount" =>    3786.32],
            ["id" =>    82786, "amount" =>    4419.08],
            ["id" =>    82787, "amount" =>    3786.32],
            ["id" =>    82789, "amount" =>    4419.08],
            ["id" =>    82790, "amount" =>    5450],
            ["id" =>    82793, "amount" =>    1998.33],
            ["id" =>    82809, "amount" =>    2493.33],
            ["id" =>    82812, "amount" =>    1998.33],
            ["id" =>    82813, "amount" =>    1998.33],
            ["id" =>    83880, "amount" =>    5450],
            ["id" =>    83884, "amount" =>    4419.08],
            ["id" =>    83885, "amount" =>    4419.08],
            ["id" =>    83887, "amount" =>    4419.08],
            ["id" =>    83889, "amount" =>    2493.33],
            ["id" =>    83891, "amount" =>    5450],
            ["id" =>    83893, "amount" =>    7921.96],
            ["id" =>    83894, "amount" =>    3786.32],
            ["id" =>    83895, "amount" =>    4419.08],
            ["id" =>    83900, "amount" =>    5709.52],
            ["id" =>    83902, "amount" =>    3786.32],
            ["id" =>    83941, "amount" =>    4419.08],
            ["id" =>    83945, "amount" =>    5450],
            ["id" =>    83946, "amount" =>    5709.52],
            ["id" =>    83947, "amount" =>    5450],
            ["id" =>    83948, "amount" =>    4419.08],
            ["id" =>    83949, "amount" =>    5450],
            ["id" =>    83964, "amount" =>    2493.33],
            ["id" =>    83965, "amount" =>    1199],
            ["id" =>    83966, "amount" =>    3786.32],
            ["id" =>    83968, "amount" =>    7921.96],
            ["id" =>    83969, "amount" =>    4419.08],
            ["id" =>    83970, "amount" =>    3786.32],
            ["id" =>    83971, "amount" =>    5709.52],
            ["id" =>    83972, "amount" =>    3786.32],
            ["id" =>    83973, "amount" =>    4419.08],
            ["id" =>    83979, "amount" =>    3786.32],
            ["id" =>    83980, "amount" =>    5450],
            ["id" =>    83992, "amount" =>    1998.33],
            ["id" =>    83993, "amount" =>    7921.96],
            ["id" =>    84008, "amount" =>    7921.96],
            ["id" =>    84016, "amount" =>    5450],
            ["id" =>    84017, "amount" =>    5709.52],
            ["id" =>    84021, "amount" =>    4419.08],
            ["id" =>    84022, "amount" =>    3786.32],
            ["id" =>    84023, "amount" =>    5450],
            ["id" =>    84024, "amount" =>    5450],
            ["id" =>    84026, "amount" =>    5450],
            ["id" =>    84027, "amount" =>    5450],
            ["id" =>    85466, "amount" =>    1199],
            ["id" =>    85468, "amount" =>    5450],
            ["id" =>    85469, "amount" =>    1998.33],
            ["id" =>    85487, "amount" =>    5450],
            ["id" =>    85489, "amount" =>    4419.08],
            ["id" =>    85490, "amount" =>    4419.08],
            ["id" =>    85492, "amount" =>    1199],
            ["id" =>    85495, "amount" =>    4419.08],
            ["id" =>    85498, "amount" =>    5450],
            ["id" =>    85499, "amount" =>    5450],
            ["id" =>    85500, "amount" =>    4419.08],
            ["id" =>    85501, "amount" =>    1199],
            ["id" =>    85502, "amount" =>    1998.33],
            ["id" =>    85503, "amount" =>    4419.08],
            ["id" =>    85506, "amount" =>    7921.96],
            ["id" =>    85508, "amount" =>    5450],
            ["id" =>    85510, "amount" =>    3786.32],
            ["id" =>    85512, "amount" =>    3786.32],
            ["id" =>    85513, "amount" =>    1199],
            ["id" =>    85523, "amount" =>    1199],
            ["id" =>    85529, "amount" =>    3786.32],
            ["id" =>    85531, "amount" =>    3786.32],
            ["id" =>    85566, "amount" =>    2493.33],
            ["id" =>    85567, "amount" =>    3786.32],
            ["id" =>    85568, "amount" =>    4419.08],
            ["id" =>    85569, "amount" =>    3786.32],
            ["id" =>    85604, "amount" =>    4419.08],
            ["id" =>    85608, "amount" =>    4419.08],
            ["id" =>    85609, "amount" =>    5450],
            ["id" =>    85610, "amount" =>    4419.08],
            ["id" =>    85611, "amount" =>    1998.33],
            ["id" =>    85614, "amount" =>    4419.08],
            ["id" =>    85618, "amount" =>    5450],
            ["id" =>    85625, "amount" =>    3786.32],
            ["id" =>    85628, "amount" =>    3786.32],
            ["id" =>    85633, "amount" =>    5450],
            ["id" =>    85639, "amount" =>    5450],
            ["id" =>    85653, "amount" =>    1998.33],
            ["id" =>    85657, "amount" =>    5450],
            ["id" =>    85660, "amount" =>    3786.32],
            ["id" =>    85663, "amount" =>    2493.33],
            ["id" =>    85664, "amount" =>    1199],
            ["id" =>    85670, "amount" =>    5450],
            ["id" =>    85675, "amount" =>    7921.96],
            ["id" =>    85694, "amount" =>    7921.96],
            ["id" =>    85697, "amount" =>    3786.32],
            ["id" =>    85700, "amount" =>    1199],
            ["id" =>    85701, "amount" =>    4419.08],
            ["id" =>    85702, "amount" =>    7921.96],
            ["id" =>    85713, "amount" =>    5450],
            ["id" =>    85718, "amount" =>    4419.08],
            ["id" =>    85722, "amount" =>    2493.33],
            ["id" =>    85730, "amount" =>    1199],
            ["id" =>    85731, "amount" =>    1199],
            ["id" =>    85732, "amount" =>    3786.32],
            ["id" =>    85733, "amount" =>    3786.32],
            ["id" =>    85737, "amount" =>    5450],
            ["id" =>    85740, "amount" =>    5450],
            ["id" =>    85743, "amount" =>    3786.32],
            ["id" =>    85744, "amount" =>    5450],
            ["id" =>    85770, "amount" =>    22880],
            ["id" =>    85792, "amount" =>    5709.52],
            ["id" =>    85793, "amount" =>    3786.32],
            ["id" =>    85810, "amount" =>    1199],
            ["id" =>    85811, "amount" =>    1199],
            ["id" =>    85812, "amount" =>    1199],
            ["id" =>    85828, "amount" =>    1199],
            ["id" =>    85829, "amount" =>    1199],
            ["id" =>    85830, "amount" =>    1199],
            ["id" =>    86413, "amount" =>    2493.33],
            ["id" =>    86423, "amount" =>    2493.33],
            ["id" =>    86426, "amount" =>    3786.32],
            ["id" =>    86429, "amount" =>    3786.32],
            ["id" =>    86435, "amount" =>    5450],
            ["id" =>    86440, "amount" =>    5450],
            ["id" =>    86451, "amount" =>    4419.08],
            ["id" =>    86529, "amount" =>    1199],
            ["id" =>    86543, "amount" =>    1199],
            ["id" =>    86546, "amount" =>    4419.08],
            ["id" =>    86552, "amount" =>    4419.08],
            ["id" =>    87441, "amount" =>    4419.08],
            ["id" =>    87442, "amount" =>    5450],
            ["id" =>    87451, "amount" =>    3786.32],
            ["id" =>    87468, "amount" =>    4419.08],
            ["id" =>    87476, "amount" =>    3786.32],
            ["id" =>    87748, "amount" =>    17700],
            ["id" =>    88525, "amount" =>    3786.32],
            ["id" =>    88534, "amount" =>    3786.32],
            ["id" =>    88539, "amount" =>    3786.32],
            ["id" =>    88541, "amount" =>    5450],
            ["id" =>    88551, "amount" =>    5450],
            ["id" =>    88552, "amount" =>    1199],
            ["id" =>    88553, "amount" =>    4419.08],
            ["id" =>    88603, "amount" =>    5450],
            ["id" =>    88607, "amount" =>    5450],
            ["id" =>    88609, "amount" =>    5450],
            ["id" =>    88611, "amount" =>    1199],
            ["id" =>    88613, "amount" =>    3786.32],
            ["id" =>    88617, "amount" =>    4419.08],
            ["id" =>    88621, "amount" =>    4419.08],
            ["id" =>    88625, "amount" =>    3786.32],
            ["id" =>    88632, "amount" =>    5450],
            ["id" =>    88636, "amount" =>    3786.32],
            ["id" =>    88646, "amount" =>    2493.33],
            ["id" =>    88670, "amount" =>    21333.33],
            ["id" =>    88673, "amount" =>    2493.33],
            ["id" =>    89069, "amount" =>    10480],
            ["id" =>    91289, "amount" =>    2493.33],
            ["id" =>    91291, "amount" =>    7921.96],
            ["id" =>    91307, "amount" =>    1199],
            ["id" =>    91350, "amount" =>    7921.96],
            ["id" =>    91372, "amount" =>    5450],
            ["id" =>    91958, "amount" =>    17500],
            ["id" =>    92838, "amount" =>    3544.44],
            ["id" =>    93168, "amount" =>    17500],
            ["id" =>    97340, "amount" =>    9680],
            ["id" =>    98184, "amount" =>    4962.22],
            ["id" =>    114285, "amount" =>    16000],
            ["id" =>    114307, "amount" =>    23250],
            ["id" =>    114559, "amount" =>    14000],
            ["id" =>    114651, "amount" =>    10780],
            ["id" =>    114749, "amount" =>    14300],
            ["id" =>    114784, "amount" =>    12237],
            ["id" =>    115704, "amount" =>    1750],
            ["id" =>    115772, "amount" =>    1750],
            ["id" =>    115785, "amount" =>    1750],
            ["id" =>    115793, "amount" =>    1750],
            ["id" =>    115821, "amount" =>    31318],
            ["id" =>    117618, "amount" =>    9900],
            ["id" =>    119382, "amount" =>    16200],
            ["id" =>    119402, "amount" =>    9680],
            ["id" =>    122102, "amount" =>    8470],
            ["id" =>    126154, "amount" =>    32500],
            ["id" =>    131386, "amount" =>    18300],
            ["id" =>    132466, "amount" =>    18288],
            ["id" =>    133067, "amount" =>    20100],
            ["id" =>    136706, "amount" =>    17500],
            ["id" =>    141846, "amount" =>    2493.33],
            ["id" =>    144035, "amount" =>    10500],
            ["id" =>    145426, "amount" =>    20000],
            ["id" =>    145896, "amount" =>    4466],
            ["id" =>    145897, "amount" =>    4466],
            ["id" =>    148348, "amount" =>    2493.33],
            ["id" =>    155632, "amount" =>    12556],
            ["id" =>    157945, "amount" =>    27115],
            ["id" =>    157956, "amount" =>    16720],
            ["id" =>    159209, "amount" =>    39500],
            ["id" =>    159211, "amount" =>    18000],
            ["id" =>    172042, "amount" =>    2493.33],
            ["id" =>    174297, "amount" =>    8069],
            ["id" =>    174681, "amount" =>    6589],
            ["id" =>    183846, "amount" =>    30750],
            ["id" =>    184602, "amount" =>    2493.33],
            ["id" =>    186801, "amount" =>    16500],
            ["id" =>    189918, "amount" =>    42108],
            ["id" =>    189920, "amount" =>    21764.68],
            ["id" =>    189928, "amount" =>    65294.04],
            ["id" =>    189944, "amount" =>    85079.28],
            ["id" =>    189945, "amount" =>    43529.36],
            ["id" =>    189946, "amount" =>    43529.36],
            ["id" =>    202884, "amount" =>    2493.33],
            ["id" =>    218727, "amount" =>    4199],
            ["id" =>    219730, "amount" =>    5707.53],
            ["id" =>    220340, "amount" =>    6799.05],
            ["id" =>    222904, "amount" =>    20990],
            ["id" =>    222908, "amount" =>    14800],
            ["id" =>    222910, "amount" =>    20600],
            ["id" =>    222913, "amount" =>    18500],
            ["id" =>    223050, "amount" =>    19720],
            ["id" =>    223651, "amount" =>    10084.72],
            ["id" =>    223657, "amount" =>    8120],
            ["id" =>    223658, "amount" =>    10084.72],
            ["id" =>    223659, "amount" =>    10084.72],
            ["id" =>    235146, "amount" =>    7479.99],
            ["id" =>    235148, "amount" =>    7479.99],
            ["id" =>    235155, "amount" =>    7479.99],
            ["id" =>    237304, "amount" =>    19974],
            ["id" =>    237306, "amount" =>    19974],
            ["id" =>    237368, "amount" =>    19974],
            ["id" =>    237421, "amount" =>    4000],
            ["id" =>    237422, "amount" =>    4000],
            ["id" =>    241962, "amount" =>    17700],
            ["id" =>    241972, "amount" =>    23100],
            ["id" =>    241973, "amount" =>    10780],
            ["id" =>    242182, "amount" =>    12100],
            ["id" =>    248092, "amount" =>    35000],
            ["id" =>    264732, "amount" =>    18500],
            ["id" =>    268171, "amount" =>    49898],
            ["id" =>    268592, "amount" =>    28000],
            ["id" =>    268738, "amount" =>    61999],
            ["id" =>    268742, "amount" =>    14500],
            ["id" =>    268863, "amount" =>    28000],
            ["id" =>    270168, "amount" =>    28000],
            ["id" =>    270278, "amount" =>    4199],
            ["id" =>    270281, "amount" =>    16680.36],
            ["id" =>    270285, "amount" =>    20000],
            ["id" =>    270286, "amount" =>    16760],
            ["id" =>    270289, "amount" =>    18000],
            ["id" =>    270293, "amount" =>    20000],
            ["id" =>    270302, "amount" =>    16000],
            ["id" =>    270315, "amount" =>    10681.09],
            ["id" =>    270316, "amount" =>    10444.45],
            ["id" =>    270317, "amount" =>    12000],
            ["id" =>    270320, "amount" =>    10310.77],
            ["id" =>    270634, "amount" =>    28000],
            ["id" =>    271096, "amount" =>    59002],
            ["id" =>    271508, "amount" =>    20900],
            ["id" =>    273799, "amount" =>    4199],
            ["id" =>    273800, "amount" =>    4544.83],
            ["id" =>    273802, "amount" =>    7084.71],
            ["id" =>    273803, "amount" =>    5599.23],
            ["id" =>    273804, "amount" =>    5159],
            ["id" =>    273805, "amount" =>    6913.45],
            ["id" =>    273806, "amount" =>    20000],
            ["id" =>    273811, "amount" =>    16000],
            ["id" =>    273812, "amount" =>    16000],
            ["id" =>    273818, "amount" =>    4500.56],
            ["id" =>    273823, "amount" =>    5210],
            ["id" =>    273833, "amount" =>    9029],
            ["id" =>    273835, "amount" =>    10420.96],
            ["id" =>    273839, "amount" =>    10393.94],
            ["id" =>    273984, "amount" =>    18500],
            ["id" =>    274520, "amount" =>    25890],
            ["id" =>    275501, "amount" =>    23604],
            ["id" =>    275502, "amount" =>    6959],
            ["id" =>    275504, "amount" =>    4199],
            ["id" =>    275508, "amount" =>    5599.38],
            ["id" =>    276100, "amount" =>    32000],
            ["id" =>    277126, "amount" =>    17250],
            ["id" =>    277350, "amount" =>    22867],
            ["id" =>    277365, "amount" =>    28000],
            ["id" =>    278543, "amount" =>    30500],
            ["id" =>    282063, "amount" =>    7084.71],
            ["id" =>    282073, "amount" =>    4199],
            ["id" =>    282077, "amount" =>    6899],
            ["id" =>    282078, "amount" =>    5210],
            ["id" =>    282081, "amount" =>    5099],
            ["id" =>    282677, "amount" =>    3510.11],
            ["id" =>    282685, "amount" =>    5249],
            ["id" =>    282686, "amount" =>    5599.38],
            ["id" =>    282687, "amount" =>    5599],
            ["id" =>    282689, "amount" =>    6899],
            ["id" =>    282690, "amount" =>    7156.25],
            ["id" =>    282691, "amount" =>    6799],
            ["id" =>    282692, "amount" =>    6858.33],
            ["id" =>    282693, "amount" =>    6858.33],
            ["id" =>    282694, "amount" =>    4332],
            ["id" =>    282695, "amount" =>    4899],
            ["id" =>    282696, "amount" =>    4609.23],
            ["id" =>    282697, "amount" =>    4653.81],
            ["id" =>    282698, "amount" =>    4633.38],
            ["id" =>    282699, "amount" =>    4671.32],
            ["id" =>    282700, "amount" =>    4580.25],
            ["id" =>    282709, "amount" =>    16500],
            ["id" =>    282710, "amount" =>    17500],
            ["id" =>    282711, "amount" =>    16716.67],
            ["id" =>    282714, "amount" =>    10310.77],
            ["id" =>    282715, "amount" =>    10310.77],
            ["id" =>    282742, "amount" =>    10748.53],
            ["id" =>    282743, "amount" =>    10431.8],
            ["id" =>    282744, "amount" =>    10808.87],
            ["id" =>    282745, "amount" =>    9680],
            ["id" =>    282746, "amount" =>    10519.23],
            ["id" =>    284877, "amount" =>    34000],
            ["id" =>    287489, "amount" =>    28000],
            ["id" =>    287887, "amount" =>    15400],
            ["id" =>    287893, "amount" =>    39000],
            ["id" =>    287944, "amount" =>    53000],
            ["id" =>    289684, "amount" =>    7142],
            ["id" =>    289700, "amount" =>    4294.45],
            ["id" =>    289705, "amount" =>    6854.66],
            ["id" =>    289710, "amount" =>    6799],
            ["id" =>    289711, "amount" =>    14025],
            ["id" =>    289734, "amount" =>    6374],
            ["id" =>    289783, "amount" =>    18000],
            ["id" =>    289897, "amount" =>    19000],
            ["id" =>    289945, "amount" =>    26000],
            ["id" =>    290103, "amount" =>    18000],
            ["id" =>    290412, "amount" =>    11000],
            ["id" =>    290623, "amount" =>    18000],
            ["id" =>    290663, "amount" =>    18000],
            ["id" =>    290720, "amount" =>    37000],
            ["id" =>    290819, "amount" =>    19000],
            ["id" =>    291066, "amount" =>    13500],
            ["id" =>    291185, "amount" =>    65000],
            ["id" =>    291442, "amount" =>    3499],
            ["id" =>    291443, "amount" =>    5565.67],
            ["id" =>    291449, "amount" =>    6959],
            ["id" =>    291450, "amount" =>    5210],
            ["id" =>    291472, "amount" =>    30800],
            ["id" =>    291507, "amount" =>    11000],
            ["id" =>    291846, "amount" =>    211000],
            ["id" =>    292101, "amount" =>    13900],
            ["id" =>    292121, "amount" =>    11880],
            ["id" =>    292137, "amount" =>    20900],
            ["id" =>    292543, "amount" =>    13900],
            ["id" =>    292553, "amount" =>    14300],
            ["id" =>    292563, "amount" =>    14300],
            ["id" =>    292670, "amount" =>    6074],
            ["id" =>    292921, "amount" =>    15400],
            ["id" =>    293511, "amount" =>    43500],
            ["id" =>    293512, "amount" =>    43500],
            ["id" =>    293918, "amount" =>    6959],
            ["id" =>    293923, "amount" =>    3510.11],
            ["id" =>    293928, "amount" =>    6799],
            ["id" =>    293929, "amount" =>    6799],
            ["id" =>    293931, "amount" =>    5210],
            ["id" =>    294096, "amount" =>    17600],
            ["id" =>    294124, "amount" =>    41000],
            ["id" =>    294125, "amount" =>    15400],
            ["id" =>    294352, "amount" =>    14300],
            ["id" =>    294356, "amount" =>    15400],
            ["id" =>    294357, "amount" =>    15400],
            ["id" =>    296712, "amount" =>    20000],
            ["id" =>    296938, "amount" =>    48000],
            ["id" =>    296972, "amount" =>    26000],
            ["id" =>    296985, "amount" =>    15000],
            ["id" =>    296995, "amount" =>    14300],
            ["id" =>    297160, "amount" =>    53000],
            ["id" =>    299389, "amount" =>    15400],
            ["id" =>    299402, "amount" =>    18000],
            ["id" =>    299412, "amount" =>    60000],
            ["id" =>    299429, "amount" =>    14000],
            ["id" =>    300436, "amount" =>    5210],
            ["id" =>    300447, "amount" =>    6799],
            ["id" =>    300451, "amount" =>    20350],
            ["id" =>    300452, "amount" =>    21070],
            ["id" =>    300455, "amount" =>    7419.64],
            ["id" =>    300576, "amount" =>    18245],
            ["id" =>    300667, "amount" =>    18000],
            ["id" =>    300687, "amount" =>    26000],
            ["id" =>    300691, "amount" =>    28000],
            ["id" =>    300830, "amount" =>    14500],
            ["id" =>    300972, "amount" =>    16500],
            ["id" =>    300987, "amount" =>    28000],
            ["id" =>    301571, "amount" =>    18000],
            ["id" =>    301583, "amount" =>    47000],
            ["id" =>    301609, "amount" =>    16000],
            ["id" =>    301626, "amount" =>    28000],
            ["id" =>    301645, "amount" =>    12320],
            ["id" =>    301658, "amount" =>    60000],
            ["id" =>    302113, "amount" =>    34905],
            ["id" =>    302118, "amount" =>    20919],
            ["id" =>    302241, "amount" =>    5210],
            ["id" =>    303093, "amount" =>    60000],
            ["id" =>    303099, "amount" =>    34905],
            ["id" =>    303103, "amount" =>    15000],
            ["id" =>    303186, "amount" =>    5210],
            ["id" =>    303307, "amount" =>    14774],
            ["id" =>    303583, "amount" =>    49056],
            ["id" =>    303926, "amount" =>    15400],
            ["id" =>    303979, "amount" =>    26000],
            ["id" =>    303991, "amount" =>    33000],
            ["id" =>    303999, "amount" =>    255000],
            ["id" =>    304070, "amount" =>    1],
            ["id" =>    304259, "amount" =>    6799],
            ["id" =>    304313, "amount" =>    10890],
            ["id" =>    304532, "amount" =>    60000],
            ["id" =>    304982, "amount" =>    60000],
            ["id" =>    305284, "amount" =>    51000],
            ["id" =>    305288, "amount" =>    14000],
            ["id" =>    305796, "amount" =>    25000],
            ["id" =>    305918, "amount" =>    18480],
            ["id" =>    305950, "amount" =>    11000],
            ["id" =>    306013, "amount" =>    23000],
            ["id" =>    306105, "amount" =>    16500],
            ["id" =>    306119, "amount" =>    28000],
            ["id" =>    306158, "amount" =>    1],
            ["id" =>    306159, "amount" =>    60000],
            ["id" =>    306161, "amount" =>    60000],
            ["id" =>    306180, "amount" =>    79000],
            ["id" =>    306287, "amount" =>    11500],
            ["id" =>    306303, "amount" =>    4199],
            ["id" =>    306304, "amount" =>    4199],
            ["id" =>    306308, "amount" =>    14300],
            ["id" =>    306551, "amount" =>    21942],
            ["id" =>    306598, "amount" =>    29500],
            ["id" =>    306744, "amount" =>    68000],
            ["id" =>    307182, "amount" =>    27500],
            ["id" =>    307272, "amount" =>    21000],
            ["id" =>    307528, "amount" =>    4839.68],
            ["id" =>    307916, "amount" =>    9179],
            ["id" =>    308462, "amount" =>    23000],
            ["id" =>    308643, "amount" =>    23500],
            ["id" =>    308815, "amount" =>    35500],
            ["id" =>    309053, "amount" =>    33000],
            ["id" =>    309449, "amount" =>    11550],
            ["id" =>    309483, "amount" =>    17000],
            ["id" =>    310107, "amount" =>    14000],
            ["id" =>    310412, "amount" =>    33000],
            ["id" =>    310443, "amount" =>    23000],
            ["id" =>    310492, "amount" =>    5210],
            ["id" =>    310493, "amount" =>    5210],
            ["id" =>    310495, "amount" =>    14025],
            ["id" =>    310501, "amount" =>    6799],
            ["id" =>    310510, "amount" =>    6959],
            ["id" =>    310517, "amount" =>    23859],
            ["id" =>    310546, "amount" =>    9029],
            ["id" =>    310547, "amount" =>    9029],
            ["id" =>    310548, "amount" =>    9029],
            ["id" =>    310549, "amount" =>    9029],
            ["id" =>    310563, "amount" =>    5919.31],
            ["id" =>    310593, "amount" =>    4280.25],
            ["id" =>    310612, "amount" =>    7084.71],
            ["id" =>    310613, "amount" =>    6999],
            ["id" =>    310614, "amount" =>    7084.71],
            ["id" =>    310615, "amount" =>    6879.15],
            ["id" =>    310616, "amount" =>    6899],
            ["id" =>    310617, "amount" =>    6932.33],
            ["id" =>    310618, "amount" =>    5159],
            ["id" =>    310619, "amount" =>    5099],
            ["id" =>    310627, "amount" =>    5565.67],
            ["id" =>    310629, "amount" =>    5565.67],
            ["id" =>    310762, "amount" =>    4199],
            ["id" =>    310786, "amount" =>    5159],
            ["id" =>    310788, "amount" =>    5099],
            ["id" =>    310824, "amount" =>    6888.11],
            ["id" =>    310825, "amount" =>    6888.11],
            ["id" =>    310848, "amount" =>    7834.98],
            ["id" =>    310849, "amount" =>    12000],
            ["id" =>    310850, "amount" =>    10605.22],
            ["id" =>    310872, "amount" =>    23604],
            ["id" =>    310873, "amount" =>    23604],
            ["id" =>    310877, "amount" =>    6028.55],
            ["id" =>    310893, "amount" =>    6959],
            ["id" =>    310894, "amount" =>    6799],
            ["id" =>    310896, "amount" =>    14025],
            ["id" =>    310918, "amount" =>    5210],
            ["id" =>    310920, "amount" =>    5210],
            ["id" =>    310921, "amount" =>    5210],
            ["id" =>    310922, "amount" =>    5210],
            ["id" =>    310923, "amount" =>    5210],
            ["id" =>    310924, "amount" =>    5210],
            ["id" =>    310925, "amount" =>    5210],
            ["id" =>    310990, "amount" =>    5210],
            ["id" =>    310991, "amount" =>    5210],
            ["id" =>    310992, "amount" =>    5210],
            ["id" =>    310993, "amount" =>    5210],
            ["id" =>    310995, "amount" =>    5210],
            ["id" =>    311000, "amount" =>    6799],
            ["id" =>    311008, "amount" =>    18000],
            ["id" =>    311009, "amount" =>    18000],
            ["id" =>    311019, "amount" =>    4899],
            ["id" =>    311038, "amount" =>    5159],
            ["id" =>    311212, "amount" =>    25000],
            ["id" =>    311259, "amount" =>    14300],
            ["id" =>    311283, "amount" =>    13200],
            ["id" =>    311402, "amount" =>    28000],
            ["id" =>    311414, "amount" =>    16500],
            ["id" =>    311440, "amount" =>    98000],
            ["id" =>    312495, "amount" =>    22800],
            ["id" =>    312496, "amount" =>    15500],
            ["id" =>    312638, "amount" =>    30500],
            ["id" =>    313002, "amount" =>    33000],
            ["id" =>    313014, "amount" =>    60000],
            ["id" =>    313143, "amount" =>    15400],
            ["id" =>    313222, "amount" =>    60000],
            ["id" =>    313267, "amount" =>    16500],
            ["id" =>    313298, "amount" =>    30800],
            ["id" =>    313332, "amount" =>    230000],
            ["id" =>    313411, "amount" =>    88000],
            ["id" =>    313508, "amount" =>    33000],
            ["id" =>    313773, "amount" =>    115000],
            ["id" =>    313982, "amount" =>    13750],
            ["id" =>    313984, "amount" =>    60000],
            ["id" =>    314031, "amount" =>    9850],
            ["id" =>    314100, "amount" =>    17600],
            ["id" =>    314531, "amount" =>    30800],
            ["id" =>    314537, "amount" =>    105000],
            ["id" =>    315051, "amount" =>    20000],
            ["id" =>    315095, "amount" =>    215000],
            ["id" =>    315110, "amount" =>    30800],
            ["id" =>    315268, "amount" =>    21000],
            ["id" =>    315311, "amount" =>    22000],
            ["id" =>    315320, "amount" =>    40000],
            ["id" =>    315348, "amount" =>    14300],
            ["id" =>    315773, "amount" =>    23000],
            ["id" =>    315836, "amount" =>    23000],
            ["id" =>    315894, "amount" =>    14300],
            ["id" =>    315911, "amount" =>    16501],
            ["id" =>    316074, "amount" =>    88000],
            ["id" =>    316080, "amount" =>    20000],
            ["id" =>    316091, "amount" =>    25000],
            ["id" =>    316139, "amount" =>    13000],
            ["id" =>    316369, "amount" =>    16500],
            ["id" =>    316613, "amount" =>    37000],
            ["id" =>    317040, "amount" =>    20000],
            ["id" =>    317052, "amount" =>    30800],
            ["id" =>    317079, "amount" =>    16500],
            ["id" =>    317139, "amount" =>    10074],
            ["id" =>    317159, "amount" =>    43000],
            ["id" =>    317316, "amount" =>    3399.1],
            ["id" =>    317540, "amount" =>    43000],
            ["id" =>    317547, "amount" =>    17600],
            ["id" =>    317666, "amount" =>    25850],
            ["id" =>    317724, "amount" =>    20735],
            ["id" =>    318424, "amount" =>    23000],
            ["id" =>    318490, "amount" =>    17600],
            ["id" =>    318620, "amount" =>    30800],
            ["id" =>    318694, "amount" =>    16500],
            ["id" =>    318703, "amount" =>    14300],
            ["id" =>    318774, "amount" =>    38000],
            ["id" =>    318781, "amount" =>    25000],
            ["id" =>    318829, "amount" =>    15400],
            ["id" =>    318977, "amount" =>    30800],
            ["id" =>    319399, "amount" =>    23000],
            ["id" =>    319442, "amount" =>    40000],
            ["id" =>    319497, "amount" =>    17600],
            ["id" =>    319528, "amount" =>    16500],
            ["id" =>    319557, "amount" =>    4199],
            ["id" =>    319558, "amount" =>    4199],
            ["id" =>    319560, "amount" =>    4199],
            ["id" =>    319689, "amount" =>    5565.67],
            ["id" =>    319690, "amount" =>    5417],
            ["id" =>    319691, "amount" =>    5417],
            ["id" =>    319762, "amount" =>    3839],
            ["id" =>    319900, "amount" =>    12500],
            ["id" =>    319901, "amount" =>    12000],
            ["id" =>    319934, "amount" =>    18000],
            ["id" =>    319969, "amount" =>    23604],
            ["id" =>    319970, "amount" =>    16501],
            ["id" =>    319972, "amount" =>    4839.68],
            ["id" =>    319973, "amount" =>    4839.68],
            ["id" =>    320051, "amount" =>    5739],
            ["id" =>    320063, "amount" =>    5210],
            ["id" =>    320227, "amount" =>    27500],
            ["id" =>    320345, "amount" =>    25850],
            ["id" =>    320866, "amount" =>    14300],
            ["id" =>    321272, "amount" =>    26000],
            ["id" =>    321632, "amount" =>    20000],
            ["id" =>    322020, "amount" =>    21000],
            ["id" =>    322041, "amount" =>    14300],
            ["id" =>    322068, "amount" =>    13200],
            ["id" =>    322234, "amount" =>    7881.97],
            ["id" =>    322441, "amount" =>    33500],
            ["id" =>    322883, "amount" =>    10890],
            ["id" =>    322885, "amount" =>    60000],
            ["id" =>    322967, "amount" =>    13200],
            ["id" =>    323021, "amount" =>    25850],
            ["id" =>    323147, "amount" =>    13200],
            ["id" =>    323215, "amount" =>    17250],
            ["id" =>    323381, "amount" =>    14300],
            ["id" =>    323793, "amount" =>    929],
            ["id" =>    323863, "amount" =>    800],
            ["id" =>    323866, "amount" =>    16500],
            ["id" =>    323926, "amount" =>    25850],
            ["id" =>    323943, "amount" =>    14300],
            ["id" =>    324068, "amount" =>    704],
            ["id" =>    324070, "amount" =>    4720],
            ["id" =>    324195, "amount" =>    16000],
            ["id" =>    324254, "amount" =>    20919],
            ["id" =>    324284, "amount" =>    2680],
            ["id" =>    324288, "amount" =>    15400],
            ["id" =>    324358, "amount" =>    23500],
            ["id" =>    324785, "amount" =>    40000],
            ["id" =>    324814, "amount" =>    2500],
            ["id" =>    325192, "amount" =>    13200],
            ["id" =>    325246, "amount" =>    15400],
            ["id" =>    325254, "amount" =>    1],
            ["id" =>    325459, "amount" =>    16000],
            ["id" =>    325480, "amount" =>    21000],
            ["id" =>    325490, "amount" =>    16500],
            ["id" =>    325501, "amount" =>    26000],
            ["id" =>    325530, "amount" =>    21000],
            ["id" =>    325832, "amount" =>    15400],
            ["id" =>    325950, "amount" =>    57000],
            ["id" =>    326063, "amount" =>    211000],
            ["id" =>    326127, "amount" =>    13200],
            ["id" =>    326143, "amount" =>    12255],
            ["id" =>    326146, "amount" =>    20000],
            ["id" =>    326373, "amount" =>    16500],
            ["id" =>    326408, "amount" =>    1975],
            ["id" =>    326523, "amount" =>    20500],
            ["id" =>    326721, "amount" =>    17820],
            ["id" =>    327191, "amount" =>    23000],
            ["id" =>    327452, "amount" =>    30800],
            ["id" =>    327651, "amount" =>    23000],
            ["id" =>    327653, "amount" =>    23000],
            ["id" =>    327957, "amount" =>    26000],
            ["id" =>    328086, "amount" =>    25850],
            ["id" =>    328185, "amount" =>    2020],
            ["id" =>    328270, "amount" =>    16500],
            ["id" =>    328271, "amount" =>    27500],
            ["id" =>    328490, "amount" =>    16500],
            ["id" =>    328785, "amount" =>    21500],
            ["id" =>    328901, "amount" =>    16500],
            ["id" =>    328967, "amount" =>    69000],
            ["id" =>    328979, "amount" =>    15500],
            ["id" =>    328983, "amount" =>    20919],
            ["id" =>    328990, "amount" =>    14300],
            ["id" =>    328997, "amount" =>    33000],
            ["id" =>    329457, "amount" =>    17000],
            ["id" =>    329464, "amount" =>    13750],
            ["id" =>    329561, "amount" =>    12500],
            ["id" =>    329576, "amount" =>    33000],
            ["id" =>    330084, "amount" =>    23000],
            ["id" =>    330181, "amount" =>    30500],
            ["id" =>    330194, "amount" =>    16500],
            ["id" =>    330374, "amount" =>    23000],
            ["id" =>    330456, "amount" =>    13750],
            ["id" =>    330457, "amount" =>    19000],
            ["id" =>    330698, "amount" =>    13200],
            ["id" =>    330731, "amount" =>    3409.07],
            ["id" =>    330777, "amount" =>    3839],
            ["id" =>    330780, "amount" =>    3839],
            ["id" =>    330781, "amount" =>    3839],
            ["id" =>    330782, "amount" =>    3839],
            ["id" =>    330825, "amount" =>    7920],
            ["id" =>    330830, "amount" =>    5210],
            ["id" =>    330831, "amount" =>    5210],
            ["id" =>    330832, "amount" =>    5210],
            ["id" =>    330992, "amount" =>    12500],
            ["id" =>    331017, "amount" =>    4839.68],
            ["id" =>    331019, "amount" =>    7920],
            ["id" =>    331037, "amount" =>    5739],
            ["id" =>    331038, "amount" =>    5739],
            ["id" =>    331039, "amount" =>    5739],
            ["id" =>    331040, "amount" =>    5739],
            ["id" =>    331299, "amount" =>    33000],
            ["id" =>    331349, "amount" =>    17820],
            ["id" =>    331350, "amount" =>    11550],
            ["id" =>    331354, "amount" =>    13200],
            ["id" =>    331384, "amount" =>    16500],
            ["id" =>    331429, "amount" =>    27500],
            ["id" =>    331432, "amount" =>    60000],
            ["id" =>    331648, "amount" =>    16500],
            ["id" =>    331652, "amount" =>    53490],
            ["id" =>    331808, "amount" =>    647],
            ["id" =>    331980, "amount" =>    155000],
            ["id" =>    332090, "amount" =>    27500],
            ["id" =>    332153, "amount" =>    60000],
            ["id" =>    332174, "amount" =>    16500],
            ["id" =>    332222, "amount" =>    120000],
            ["id" =>    332361, "amount" =>    29000],
            ["id" =>    333172, "amount" =>    12000],
            ["id" =>    333610, "amount" =>    16500],
            ["id" =>    333657, "amount" =>    20000],
            ["id" =>    333737, "amount" =>    14300],
            ["id" =>    333806, "amount" =>    16500],
            ["id" =>    334567, "amount" =>    17000],
            ["id" =>    334568, "amount" =>    15500],
            ["id" =>    334624, "amount" =>    60000],
            ["id" =>    335177, "amount" =>    14300],
            ["id" =>    336433, "amount" =>    16500],
            ["id" =>    336444, "amount" =>    30800],
            ["id" =>    336458, "amount" =>    16500],
            ["id" =>    336728, "amount" =>    23000],
            ["id" =>    336731, "amount" =>    20000],
            ["id" =>    337994, "amount" =>    74000],
            ["id" =>    338589, "amount" =>    21000],
            ["id" =>    338912, "amount" =>    30800],
            ["id" =>    339097, "amount" =>    6449],
            ["id" =>    339228, "amount" =>    25850],
            ["id" =>    339371, "amount" =>    28000],
            ["id" =>    339410, "amount" =>    30800],
            ["id" =>    340393, "amount" =>    1527],
            ["id" =>    340405, "amount" =>    26000],
            ["id" =>    340447, "amount" =>    16500],
            ["id" =>    340483, "amount" =>    7012],
            ["id" =>    340489, "amount" =>    6660.54],
            ["id" =>    340491, "amount" =>    4899],
            ["id" =>    340497, "amount" =>    14025],
            ["id" =>    340499, "amount" =>    4899.13],
            ["id" =>    340503, "amount" =>    14025],
            ["id" =>    340505, "amount" =>    21070],
            ["id" =>    340506, "amount" =>    14025],
            ["id" =>    340510, "amount" =>    14025],
            ["id" =>    340511, "amount" =>    7012],
            ["id" =>    340514, "amount" =>    6799],
            ["id" =>    340532, "amount" =>    4899.13],
            ["id" =>    340535, "amount" =>    14025],
            ["id" =>    340537, "amount" =>    14025],
            ["id" =>    340542, "amount" =>    16999],
            ["id" =>    340543, "amount" =>    14190],
            ["id" =>    340550, "amount" =>    3510.11],
            ["id" =>    340553, "amount" =>    5949],
            ["id" =>    340555, "amount" =>    5949],
            ["id" =>    340559, "amount" =>    4899.13],
            ["id" =>    340565, "amount" =>    14025],
            ["id" =>    340566, "amount" =>    16501],
            ["id" =>    340572, "amount" =>    6799],
            ["id" =>    340574, "amount" =>    4199],
            ["id" =>    340576, "amount" =>    3427.58],
            ["id" =>    340581, "amount" =>    5949],
            ["id" =>    340589, "amount" =>    14025],
            ["id" =>    340591, "amount" =>    14190],
            ["id" =>    340592, "amount" =>    4199],
            ["id" =>    340599, "amount" =>    8120],
            ["id" =>    340606, "amount" =>    6149],
            ["id" =>    340612, "amount" =>    7012],
            ["id" =>    340625, "amount" =>    3420.57],
            ["id" =>    340633, "amount" =>    4900],
            ["id" =>    340635, "amount" =>    14025],
            ["id" =>    340638, "amount" =>    7715.7],
            ["id" =>    340639, "amount" =>    4199],
            ["id" =>    340652, "amount" =>    6066],
            ["id" =>    340659, "amount" =>    7012],
            ["id" =>    340661, "amount" =>    16501],
            ["id" =>    340662, "amount" =>    21070],
            ["id" =>    340675, "amount" =>    4899],
            ["id" =>    340685, "amount" =>    10149.5],
            ["id" =>    340689, "amount" =>    14190],
            ["id" =>    340690, "amount" =>    17000],
            ["id" =>    340692, "amount" =>    4199],
            ["id" =>    340696, "amount" =>    6799],
            ["id" =>    340701, "amount" =>    7419.64],
            ["id" =>    340707, "amount" =>    6660.54],
            ["id" =>    340708, "amount" =>    8120],
            ["id" =>    340720, "amount" =>    6149],
            ["id" =>    340729, "amount" =>    7012],
            ["id" =>    340749, "amount" =>    7012],
            ["id" =>    340755, "amount" =>    7012],
            ["id" =>    340760, "amount" =>    14190],
            ["id" =>    340761, "amount" =>    4199],
            ["id" =>    340778, "amount" =>    6149],
            ["id" =>    340784, "amount" =>    10212],
            ["id" =>    340792, "amount" =>    7419.64],
            ["id" =>    340793, "amount" =>    8120],
            ["id" =>    340805, "amount" =>    9911],
            ["id" =>    340807, "amount" =>    7012],
            ["id" =>    340810, "amount" =>    6799],
            ["id" =>    340811, "amount" =>    6799],
            ["id" =>    340812, "amount" =>    6799],
            ["id" =>    340818, "amount" =>    6660.54],
            ["id" =>    340819, "amount" =>    4899],
            ["id" =>    340836, "amount" =>    10212],
            ["id" =>    340841, "amount" =>    7419.64],
            ["id" =>    340849, "amount" =>    4899],
            ["id" =>    340861, "amount" =>    14025],
            ["id" =>    340863, "amount" =>    4199],
            ["id" =>    340870, "amount" =>    4899.13],
            ["id" =>    340872, "amount" =>    6660.54],
            ["id" =>    340879, "amount" =>    9911],
            ["id" =>    340889, "amount" =>    9911],
            ["id" =>    340894, "amount" =>    6149],
            ["id" =>    340903, "amount" =>    7392],
            ["id" =>    340906, "amount" =>    3427.58],
            ["id" =>    340916, "amount" =>    6660.54],
            ["id" =>    340919, "amount" =>    7012],
            ["id" =>    340928, "amount" =>    16999],
            ["id" =>    340929, "amount" =>    10212],
            ["id" =>    340930, "amount" =>    7012],
            ["id" =>    340932, "amount" =>    4199],
            ["id" =>    340943, "amount" =>    4900],
            ["id" =>    340944, "amount" =>    4899.13],
            ["id" =>    340947, "amount" =>    9911],
            ["id" =>    340948, "amount" =>    14025],
            ["id" =>    340949, "amount" =>    14025],
            ["id" =>    340953, "amount" =>    7012],
            ["id" =>    340965, "amount" =>    10149.5],
            ["id" =>    340967, "amount" =>    4899],
            ["id" =>    340968, "amount" =>    9859],
            ["id" =>    340977, "amount" =>    21070],
            ["id" =>    340981, "amount" =>    10212],
            ["id" =>    340988, "amount" =>    3420.57],
            ["id" =>    340997, "amount" =>    10149.5],
            ["id" =>    341002, "amount" =>    16999],
            ["id" =>    341003, "amount" =>    7012],
            ["id" =>    341004, "amount" =>    3404.8],
            ["id" =>    341005, "amount" =>    3399.1],
            ["id" =>    341006, "amount" =>    3399.1],
            ["id" =>    341010, "amount" =>    7419.64],
            ["id" =>    341028, "amount" =>    14025],
            ["id" =>    341029, "amount" =>    14025],
            ["id" =>    341033, "amount" =>    7012],
            ["id" =>    341035, "amount" =>    4199],
            ["id" =>    341036, "amount" =>    3399.1],
            ["id" =>    341049, "amount" =>    6660.54],
            ["id" =>    341051, "amount" =>    9911],
            ["id" =>    341062, "amount" =>    4900],
            ["id" =>    341065, "amount" =>    14025],
            ["id" =>    341070, "amount" =>    10212],
            ["id" =>    341076, "amount" =>    7419.64],
            ["id" =>    341079, "amount" =>    9859],
            ["id" =>    341081, "amount" =>    3427.58],
            ["id" =>    341090, "amount" =>    9911],
            ["id" =>    341093, "amount" =>    14025],
            ["id" =>    341097, "amount" =>    16999],
            ["id" =>    341098, "amount" =>    17000],
            ["id" =>    341099, "amount" =>    7775.04],
            ["id" =>    341105, "amount" =>    7419.64],
            ["id" =>    341110, "amount" =>    6660.54],
            ["id" =>    341117, "amount" =>    4899],
            ["id" =>    341125, "amount" =>    21070],
            ["id" =>    341129, "amount" =>    21070],
            ["id" =>    341131, "amount" =>    14025],
            ["id" =>    341132, "amount" =>    14025],
            ["id" =>    341137, "amount" =>    7012],
            ["id" =>    341138, "amount" =>    7715.7],
            ["id" =>    341141, "amount" =>    4199],
            ["id" =>    341151, "amount" =>    4899],
            ["id" =>    341153, "amount" =>    6149],
            ["id" =>    341155, "amount" =>    14025],
            ["id" =>    341156, "amount" =>    14025],
            ["id" =>    341162, "amount" =>    14190],
            ["id" =>    341165, "amount" =>    9911],
            ["id" =>    341176, "amount" =>    10149.5],
            ["id" =>    341182, "amount" =>    9911],
            ["id" =>    341185, "amount" =>    14025],
            ["id" =>    341204, "amount" =>    4899.13],
            ["id" =>    341205, "amount" =>    14025],
            ["id" =>    341206, "amount" =>    7012],
            ["id" =>    341212, "amount" =>    14025],
            ["id" =>    341215, "amount" =>    10212],
            ["id" =>    341221, "amount" =>    3427.58],
            ["id" =>    341234, "amount" =>    4899],
            ["id" =>    341246, "amount" =>    7012],
            ["id" =>    341247, "amount" =>    4199],
            ["id" =>    341253, "amount" =>    6660.54],
            ["id" =>    341255, "amount" =>    4899],
            ["id" =>    341261, "amount" =>    4899.13],
            ["id" =>    341262, "amount" =>    4899.13],
            ["id" =>    341267, "amount" =>    14025],
            ["id" =>    341269, "amount" =>    13200.8],
            ["id" =>    341283, "amount" =>    9911],
            ["id" =>    341291, "amount" =>    14025],
            ["id" =>    341293, "amount" =>    14025],
            ["id" =>    341296, "amount" =>    10212],
            ["id" =>    341300, "amount" =>    4199],
            ["id" =>    341311, "amount" =>    6029.77],
            ["id" =>    341318, "amount" =>    10149.5],
            ["id" =>    341322, "amount" =>    14025],
            ["id" =>    341328, "amount" =>    14025],
            ["id" =>    341331, "amount" =>    16999],
            ["id" =>    341332, "amount" =>    16999],
            ["id" =>    341333, "amount" =>    4199],
            ["id" =>    341339, "amount" =>    3427.58],
            ["id" =>    341346, "amount" =>    4899],
            ["id" =>    341348, "amount" =>    14025],
            ["id" =>    341352, "amount" =>    16501],
            ["id" =>    341354, "amount" =>    14190],
            ["id" =>    341355, "amount" =>    7012],
            ["id" =>    341356, "amount" =>    4199],
            ["id" =>    341794, "amount" =>    16500],
            ["id" =>    342204, "amount" =>    115000],
            ["id" =>    342258, "amount" =>    23500],
            ["id" =>    342285, "amount" =>    23000],
            ["id" =>    342523, "amount" =>    17000],
            ["id" =>    342545, "amount" =>    29000],
            ["id" =>    342570, "amount" =>    13750],
            ["id" =>    342627, "amount" =>    16500],
            ["id" =>    342757, "amount" =>    23000],
            ["id" =>    343047, "amount" =>    16500],
            ["id" =>    343115, "amount" =>    1907],
            ["id" =>    343208, "amount" =>    17600],
            ["id" =>    343226, "amount" =>    15400],
            ["id" =>    343230, "amount" =>    20000],
            ["id" =>    343420, "amount" =>    3633.5],
            ["id" =>    343423, "amount" =>    4881],
            ["id" =>    343428, "amount" =>    4199],
            ["id" =>    343625, "amount" =>    60000],
            ["id" =>    343651, "amount" =>    13750],
            ["id" =>    343837, "amount" =>    38000],
            ["id" =>    343945, "amount" =>    23000],
            ["id" =>    344123, "amount" =>    16500],
            ["id" =>    344362, "amount" =>    5210],
            ["id" =>    344363, "amount" =>    5210],
            ["id" =>    344365, "amount" =>    5210],
            ["id" =>    344372, "amount" =>    5210],
            ["id" =>    344400, "amount" =>    23604],
            ["id" =>    344454, "amount" =>    7775.04],
            ["id" =>    344490, "amount" =>    5529],
            ["id" =>    344706, "amount" =>    23000],
            ["id" =>    344767, "amount" =>    16500],
            ["id" =>    344792, "amount" =>    110000],
            ["id" =>    344825, "amount" =>    16500],
            ["id" =>    344971, "amount" =>    13750],
            ["id" =>    345119, "amount" =>    30800],
            ["id" =>    345785, "amount" =>    50000],
            ["id" =>    345837, "amount" =>    6799],
            ["id" =>    345943, "amount" =>    6799],
            ["id" =>    346045, "amount" =>    8120],
            ["id" =>    346087, "amount" =>    8120],
            ["id" =>    346183, "amount" =>    5019.87],
            ["id" =>    346203, "amount" =>    4199],
            ["id" =>    346225, "amount" =>    5155],
            ["id" =>    346226, "amount" =>    5155],
            ["id" =>    346244, "amount" =>    4199],
            ["id" =>    346245, "amount" =>    4199],
            ["id" =>    346252, "amount" =>    5019.87],
            ["id" =>    346253, "amount" =>    4199],
            ["id" =>    346277, "amount" =>    4199],
            ["id" =>    346281, "amount" =>    4199],
            ["id" =>    346320, "amount" =>    5417],
            ["id" =>    346322, "amount" =>    5099],
            ["id" =>    346323, "amount" =>    4199],
            ["id" =>    346324, "amount" =>    5155],
            ["id" =>    346341, "amount" =>    4199],
            ["id" =>    346352, "amount" =>    4199],
            ["id" =>    346353, "amount" =>    4199],
            ["id" =>    346364, "amount" =>    5099],
            ["id" =>    346366, "amount" =>    5155],
            ["id" =>    346410, "amount" =>    5155],
            ["id" =>    346432, "amount" =>    4199],
            ["id" =>    346515, "amount" =>    18500],
            ["id" =>    346528, "amount" =>    10560],
            ["id" =>    346561, "amount" =>    10560],
            ["id" =>    346562, "amount" =>    23859],
            ["id" =>    346615, "amount" =>    23859],
            ["id" =>    346656, "amount" =>    23859],
            ["id" =>    346676, "amount" =>    7920],
            ["id" =>    346679, "amount" =>    23859],
            ["id" =>    346905, "amount" =>    7392],
            ["id" =>    347084, "amount" =>    16500],
            ["id" =>    347126, "amount" =>    25850],
            ["id" =>    348238, "amount" =>    16500],
            ["id" =>    348936, "amount" =>    20000],
            ["id" =>    350037, "amount" =>    27500],
            ["id" =>    350249, "amount" =>    30800],
            ["id" =>    350807, "amount" =>    25850],
            ["id" =>    351181, "amount" =>    20000],
            ["id" =>    351469, "amount" =>    289000],
            ["id" =>    351470, "amount" =>    42500],
            ["id" =>    351471, "amount" =>    212500],
            ["id" =>    351472, "amount" =>    375590.88],
            ["id" =>    351473, "amount" =>    197670],
            ["id" =>    351474, "amount" =>    131780],
            ["id" =>    351475, "amount" =>    197670],
            ["id" =>    351476, "amount" =>    570060.48],
            ["id" =>    351477, "amount" =>    148386.6],
            ["id" =>    351478, "amount" =>    18898.14],
            ["id" =>    351479, "amount" =>    88189.08],
            ["id" =>    351480, "amount" =>    6299.13],
            ["id" =>    351481, "amount" =>    151180.56],
            ["id" =>    351482, "amount" =>    132282.99],
            ["id" =>    351483, "amount" =>    125984],
            ["id" =>    351484, "amount" =>    98252],
            ["id" =>    351485, "amount" =>    107932],
            ["id" =>    351486, "amount" =>    59532],
            ["id" =>    351487, "amount" =>    78892],
            ["id" =>    351488, "amount" =>    157485],
            ["id" =>    351489, "amount" =>    214180.28],
            ["id" =>    351490, "amount" =>    151185.36],
            ["id" =>    351491, "amount" =>    81891.03],
            ["id" =>    351492, "amount" =>    81891.03],
            ["id" =>    351493, "amount" =>    88190.9],
            ["id" =>    351494, "amount" =>    6299.35],
            ["id" =>    351495, "amount" =>    56694.15],
            ["id" =>    351496, "amount" =>    18898.05],
            ["id" =>    351497, "amount" =>    62993.5],
            ["id" =>    351498, "amount" =>    182680.86],
            ["id" =>    351499, "amount" =>    125987],
            ["id" =>    351500, "amount" =>    6299.35],
            ["id" =>    351501, "amount" =>    14025],
            ["id" =>    351502, "amount" =>    12500],
            ["id" =>    351503, "amount" =>    4192.65],
            ["id" =>    351504, "amount" =>    10725],
            ["id" =>    351505, "amount" =>    10241],
            ["id" =>    351506, "amount" =>    4616],
            ["id" =>    351507, "amount" =>    10241],
            ["id" =>    351508, "amount" =>    4616],
            ["id" =>    351509, "amount" =>    5929],
            ["id" =>    351510, "amount" =>    27170],
            ["id" =>    351511, "amount" =>    27170],
            ["id" =>    351512, "amount" =>    27170],
            ["id" =>    351513, "amount" =>    17500],
            ["id" =>    351514, "amount" =>    17000],
            ["id" =>    351515, "amount" =>    10010],
            ["id" =>    351516, "amount" =>    27500],
            ["id" =>    351517, "amount" =>    23512.5],
            ["id" =>    351518, "amount" =>    27170],
            ["id" =>    351519, "amount" =>    6600],
            ["id" =>    351520, "amount" =>    7623],
            ["id" =>    351521, "amount" =>    5717],
            ["id" =>    351522, "amount" =>    5505],
            ["id" =>    351523, "amount" =>    3956.7],
            ["id" =>    351524, "amount" =>    5039.65],
            ["id" =>    351525, "amount" =>    4616],
            ["id" =>    351526, "amount" =>    5929],
            ["id" =>    351527, "amount" =>    27170],
            ["id" =>    351528, "amount" =>    4616],
            ["id" =>    351529, "amount" =>    6267],
            ["id" =>    351530, "amount" =>    4616],
            ["id" =>    351531, "amount" =>    7623],
            ["id" =>    351532, "amount" =>    5039.65],
            ["id" =>    351533, "amount" =>    3956.7],
            ["id" =>    351534, "amount" =>    5717],
            ["id" =>    351535, "amount" =>    6267],
            ["id" =>    351536, "amount" =>    6267.8],
            ["id" =>    351537, "amount" =>    4616.15],
            ["id" =>    351538, "amount" =>    3956.7],
            ["id" =>    351539, "amount" =>    3956.7],
            ["id" =>    351540, "amount" =>    5040],
            ["id" =>    351541, "amount" =>    5717],
            ["id" =>    351542, "amount" =>    5039.65],
            ["id" =>    351543, "amount" =>    5717],
            ["id" =>    351544, "amount" =>    7800],
            ["id" =>    351545, "amount" =>    7623],
            ["id" =>    351546, "amount" =>    6267],
            ["id" =>    351547, "amount" =>    5039.65],
            ["id" =>    351548, "amount" =>    7800],
            ["id" =>    351549, "amount" =>    7800],
            ["id" =>    351550, "amount" =>    7800],
            ["id" =>    351551, "amount" =>    7800],
            ["id" =>    351552, "amount" =>    8712],
            ["id" =>    351553, "amount" =>    5717],
            ["id" =>    351554, "amount" =>    5039.65],
            ["id" =>    351555, "amount" =>    5040],
            ["id" =>    351556, "amount" =>    4616],
            ["id" =>    351557, "amount" =>    4616],
            ["id" =>    351558, "amount" =>    5039.65],
            ["id" =>    351559, "amount" =>    5039.65],
            ["id" =>    351560, "amount" =>    5039.65],
            ["id" =>    351561, "amount" =>    5039.65],
            ["id" =>    351562, "amount" =>    5717],
            ["id" =>    351563, "amount" =>    5082],
            ["id" =>    351564, "amount" =>    5039],
            ["id" =>    351565, "amount" =>    5039.65],
            ["id" =>    351566, "amount" =>    3956.7],
            ["id" =>    351567, "amount" =>    8813],
            ["id" =>    351568, "amount" =>    5039],
            ["id" =>    351569, "amount" =>    5039],
            ["id" =>    351570, "amount" =>    7623],
            ["id" =>    351571, "amount" =>    5505],
            ["id" =>    351572, "amount" =>    4720],
            ["id" =>    351573, "amount" =>    4720],
            ["id" =>    351574, "amount" =>    2980],
            ["id" =>    351575, "amount" =>    2980],
            ["id" =>    351576, "amount" =>    4720],
            ["id" =>    351577, "amount" =>    4616],
            ["id" =>    351578, "amount" =>    5040],
            ["id" =>    351579, "amount" =>    6243.6],
            ["id" =>    351580, "amount" =>    6243.6],
            ["id" =>    351581, "amount" =>    6243.6],
            ["id" =>    351582, "amount" =>    6243.6],
            ["id" =>    351583, "amount" =>    6243.6],
            ["id" =>    351584, "amount" =>    5040],
            ["id" =>    351585, "amount" =>    5040],
            ["id" =>    351586, "amount" =>    6243.6],
            ["id" =>    351587, "amount" =>    6243.6],
            ["id" =>    351588, "amount" =>    5040],
            ["id" =>    351589, "amount" =>    10900],
            ["id" =>    351590, "amount" =>    5039.65],
            ["id" =>    351591, "amount" =>    5039.65],
            ["id" =>    351592, "amount" =>    23925],
            ["id" =>    351593, "amount" =>    5040],
            ["id" =>    351594, "amount" =>    12600],
            ["id" =>    351595, "amount" =>    4319],
            ["id" =>    351596, "amount" =>    5039.65],
            ["id" =>    351597, "amount" =>    5040],
            ["id" =>    351598, "amount" =>    10998],
            ["id" =>    351599, "amount" =>    5040],
            ["id" =>    351600, "amount" =>    5039.65],
            ["id" =>    351601, "amount" =>    6267],
            ["id" =>    351602, "amount" =>    6267.8],
            ["id" =>    351603, "amount" =>    4616],
            ["id" =>    351604, "amount" =>    5445],
            ["id" =>    351605, "amount" =>    4616],
            ["id" =>    351606, "amount" =>    4616],
            ["id" =>    351607, "amount" =>    4616],
            ["id" =>    351608, "amount" =>    3000],
            ["id" =>    351609, "amount" =>    3500],
            ["id" =>    351610, "amount" =>    5082],
            ["id" =>    351611, "amount" =>    5040],
            ["id" =>    351612, "amount" =>    7623],
            ["id" =>    351613, "amount" =>    5040],
            ["id" =>    351614, "amount" =>    5929],
            ["id" =>    351615, "amount" =>    7199.5],
            ["id" =>    351616, "amount" =>    4616],
            ["id" =>    351617, "amount" =>    4616],
            ["id" =>    351618, "amount" =>    3834],
            ["id" =>    351619, "amount" =>    5463.15],
            ["id" =>    351620, "amount" =>    7993],
            ["id" =>    351621, "amount" =>    5039.65],
            ["id" =>    351622, "amount" =>    5039.65],
            ["id" =>    351623, "amount" =>    5040],
            ["id" =>    351624, "amount" =>    12600],
            ["id" =>    351625, "amount" =>    4616],
            ["id" =>    351626, "amount" =>    4616],
            ["id" =>    351627, "amount" =>    4616],
            ["id" =>    351628, "amount" =>    15400],
            ["id" =>    351629, "amount" =>    11550],
            ["id" =>    351630, "amount" =>    7150],
            ["id" =>    351631, "amount" =>    7150],
            ["id" =>    351633, "amount" =>    5490],
            ["id" =>    351634, "amount" =>    4616],
            ["id" =>    351635, "amount" =>    8712],
            ["id" =>    351636, "amount" =>    4192.65],
            ["id" =>    351637, "amount" =>    5082],
            ["id" =>    351638, "amount" =>    4616],
            ["id" =>    351639, "amount" =>    4616],
            ["id" =>    351640, "amount" =>    5929],
            ["id" =>    351641, "amount" =>    6267],
            ["id" =>    351642, "amount" =>    8712],
            ["id" =>    351643, "amount" =>    6267],
            ["id" =>    351644, "amount" =>    3956.7],
            ["id" =>    351645, "amount" =>    5039],
            ["id" =>    351646, "amount" =>    6119.3],
            ["id" =>    351647, "amount" =>    5505],
            ["id" =>    351648, "amount" =>    6267],
            ["id" =>    351649, "amount" =>    6267],
            ["id" =>    351650, "amount" =>    4616],
            ["id" =>    351651, "amount" =>    4319.7],
            ["id" =>    351652, "amount" =>    7623],
            ["id" =>    351653, "amount" =>    17500],
            ["id" =>    351654, "amount" =>    4699],
            ["id" =>    351655, "amount" =>    23925],
            ["id" =>    351656, "amount" =>    5463.15],
            ["id" =>    351657, "amount" =>    4616],
            ["id" =>    351658, "amount" =>    5039.65],
            ["id" =>    351659, "amount" =>    5039.65],
            ["id" =>    351660, "amount" =>    5039.65],
            ["id" =>    351661, "amount" =>    5039.65],
            ["id" =>    351662, "amount" =>    4616.15],
            ["id" =>    351663, "amount" =>    5759.6],
            ["id" =>    351664, "amount" =>    6243.6],
            ["id" =>    351665, "amount" =>    5929],
            ["id" =>    351666, "amount" =>    3956.7],
            ["id" =>    351667, "amount" =>    4616],
            ["id" =>    351668, "amount" =>    30000],
            ["id" =>    351669, "amount" =>    21000],
            ["id" =>    351670, "amount" =>    31900],
            ["id" =>    351671, "amount" =>    20130],
            ["id" =>    351672, "amount" =>    42000],
            ["id" =>    351673, "amount" =>    13640],
            ["id" =>    351674, "amount" =>    30000],
            ["id" =>    351675, "amount" =>    19800],
            ["id" =>    351676, "amount" =>    20370],
            ["id" =>    351677, "amount" =>    18700],
            ["id" =>    351678, "amount" =>    23320],
            ["id" =>    351679, "amount" =>    17400],
            ["id" =>    351680, "amount" =>    29150],
            ["id" =>    351681, "amount" =>    15400],
            ["id" =>    351682, "amount" =>    29150],
            ["id" =>    351683, "amount" =>    13999],
            ["id" =>    351684, "amount" =>    31350],
            ["id" =>    351685, "amount" =>    31350],
            ["id" =>    351686, "amount" =>    23650],
            ["id" =>    351687, "amount" =>    15500],
            ["id" =>    351688, "amount" =>    29150],
            ["id" =>    351689, "amount" =>    31350],
            ["id" =>    351690, "amount" =>    20900],
            ["id" =>    351691, "amount" =>    17490],
            ["id" =>    351694, "amount" =>    23320],
            ["id" =>    351696, "amount" =>    14000],
            ["id" =>    351697, "amount" =>    31350],
            ["id" =>    351698, "amount" =>    20405],
            ["id" =>    351699, "amount" =>    20405],
            ["id" =>    351700, "amount" =>    18400],
            ["id" =>    351701, "amount" =>    22000],
            ["id" =>    351702, "amount" =>    17400],
            ["id" =>    351703, "amount" =>    18810],
            ["id" =>    351704, "amount" =>    28499],
            ["id" =>    351705, "amount" =>    18400],
            ["id" =>    351706, "amount" =>    13999],
            ["id" =>    351707, "amount" =>    16500],
            ["id" =>    351708, "amount" =>    23650],
            ["id" =>    351709, "amount" =>    18400],
            ["id" =>    351710, "amount" =>    17050],
            ["id" =>    351711, "amount" =>    29150],
            ["id" =>    351712, "amount" =>    21900],
            ["id" =>    351713, "amount" =>    18700],
            ["id" =>    351714, "amount" =>    18700],
            ["id" =>    351715, "amount" =>    20900],
            ["id" =>    351716, "amount" =>    15400],
            ["id" =>    351724, "amount" =>    7150],
            ["id" =>    351725, "amount" =>    15000],
            ["id" =>    351726, "amount" =>    23336],
            ["id" =>    351727, "amount" =>    13860],
            ["id" =>    351728, "amount" =>    11440],
            ["id" =>    351729, "amount" =>    14300],
            ["id" =>    351730, "amount" =>    5929],
            ["id" =>    351731, "amount" =>    26490],
            ["id" =>    351732, "amount" =>    29500],
            ["id" =>    351733, "amount" =>    12000],
            ["id" =>    351734, "amount" =>    29000],
            ["id" =>    351735, "amount" =>    14999],
            ["id" =>    351736, "amount" =>    30000],
            ["id" =>    351737, "amount" =>    25000],
            ["id" =>    351738, "amount" =>    22500],
            ["id" =>    351739, "amount" =>    39900],
            ["id" =>    351740, "amount" =>    40500],
            ["id" =>    351741, "amount" =>    18499],
            ["id" =>    351742, "amount" =>    20000],
            ["id" =>    351743, "amount" =>    13090],
            ["id" =>    351744, "amount" =>    40500],
            ["id" =>    351745, "amount" =>    38500],
            ["id" =>    351746, "amount" =>    20130],
            ["id" =>    351747, "amount" =>    260000],
            ["id" =>    351748, "amount" =>    90500],
            ["id" =>    351749, "amount" =>    121000],
            ["id" =>    351750, "amount" =>    160000],
            ["id" =>    351751, "amount" =>    90500],
            ["id" =>    351752, "amount" =>    248000],
            ["id" =>    351753, "amount" =>    19800],
            ["id" =>    351754, "amount" =>    16700],
            ["id" =>    351755, "amount" =>    14000],
            ["id" =>    351756, "amount" =>    130169],
            ["id" =>    351757, "amount" =>    29393],
            ["id" =>    351758, "amount" =>    9100],
            ["id" =>    351759, "amount" =>    18200],
            ["id" =>    351760, "amount" =>    81900],
            ["id" =>    351761, "amount" =>    45500],
            ["id" =>    351762, "amount" =>    195200],
            ["id" =>    351763, "amount" =>    58748],
            ["id" =>    351764, "amount" =>    15142],
            ["id" =>    351765, "amount" =>    34000],
            ["id" =>    351766, "amount" =>    20000],
            ["id" =>    351767, "amount" =>    14500],
            ["id" =>    351768, "amount" =>    14300],
            ["id" =>    351769, "amount" =>    19000],
            ["id" =>    351770, "amount" =>    15000],
            ["id" =>    351771, "amount" =>    150990],
            ["id" =>    351772, "amount" =>    245000],
            ["id" =>    351773, "amount" =>    95000],
            ["id" =>    351774, "amount" =>    255000],
            ["id" =>    351775, "amount" =>    33000],
            ["id" =>    351776, "amount" =>    12460],
            ["id" =>    351777, "amount" =>    33300],
            ["id" =>    351778, "amount" =>    24826],
            ["id" =>    351779, "amount" =>    15559],
            ["id" =>    351780, "amount" =>    28000],
            ["id" =>    351781, "amount" =>    19000],
            ["id" =>    351782, "amount" =>    14800],
            ["id" =>    351783, "amount" =>    16500],
            ["id" =>    351784, "amount" =>    16500],
            ["id" =>    351785, "amount" =>    40000],
            ["id" =>    351786, "amount" =>    63000],
            ["id" =>    351787, "amount" =>    111981.4],
            ["id" =>    351788, "amount" =>    67189.08],
            ["id" =>    351789, "amount" =>    78387.12],
            ["id" =>    351790, "amount" =>    44792.72],
            ["id" =>    351791, "amount" =>    29400],
            ["id" =>    351792, "amount" =>    195998.6],
            ["id" =>    351793, "amount" =>    284198.26],
            ["id" =>    351794, "amount" =>    23834],
            ["id" =>    351795, "amount" =>    14025],
            ["id" =>    351796, "amount" =>    37000],
            ["id" =>    351797, "amount" =>    19500],
            ["id" =>    351798, "amount" =>    31680],
            ["id" =>    351799, "amount" =>    31900],
            ["id" =>    351800, "amount" =>    48000],
            ["id" =>    351801, "amount" =>    83000],
            ["id" =>    351802, "amount" =>    52000],
            ["id" =>    351803, "amount" =>    19000],
            ["id" =>    351804, "amount" =>    19000],
            ["id" =>    351805, "amount" =>    20000],
            ["id" =>    351806, "amount" =>    19000],
            ["id" =>    351807, "amount" =>    20000],
            ["id" =>    351808, "amount" =>    18500],
            ["id" =>    351809, "amount" =>    10849],
            ["id" =>    351810, "amount" =>    15899],
            ["id" =>    351811, "amount" =>    15900],
            ["id" =>    351812, "amount" =>    47735],
            ["id" =>    351813, "amount" =>    14925],
            ["id" =>    351814, "amount" =>    180000],
            ["id" =>    351815, "amount" =>    56075],
            ["id" =>    351816, "amount" =>    48000],
            ["id" =>    351817, "amount" =>    15800],
            ["id" =>    351818, "amount" =>    115499],
            ["id" =>    351819, "amount" =>    90500],
            ["id" =>    351820, "amount" =>    90500],
            ["id" =>    351821, "amount" =>    57999],
            ["id" =>    351822, "amount" =>    23800],
            ["id" =>    351823, "amount" =>    19400],
            ["id" =>    351824, "amount" =>    21255],
            ["id" =>    351825, "amount" =>    7860],
            ["id" =>    351826, "amount" =>    24046],
            ["id" =>    351827, "amount" =>    19102],
            ["id" =>    351828, "amount" =>    52963],
            ["id" =>    351829, "amount" =>    36500],
            ["id" =>    351830, "amount" =>    35599],
            ["id" =>    351831, "amount" =>    42300],
            ["id" =>    351832, "amount" =>    36000],
            ["id" =>    351833, "amount" =>    36500],
            ["id" =>    351834, "amount" =>    37404],
            ["id" =>    351835, "amount" =>    17000],
            ["id" =>    351836, "amount" =>    33000],
            ["id" =>    351837, "amount" =>    20500],
            ["id" =>    351838, "amount" =>    81890.12],
            ["id" =>    351839, "amount" =>    31496.4],
            ["id" =>    351840, "amount" =>    18897.87],
            ["id" =>    351841, "amount" =>    9501.2],
            ["id" =>    351842, "amount" =>    147271.25],
            ["id" =>    351843, "amount" =>    5990],
            ["id" =>    351844, "amount" =>    62995.5],
            ["id" =>    351845, "amount" =>    41997],
            ["id" =>    351846, "amount" =>    69995],
            ["id" =>    351847, "amount" =>    34997.5],
            ["id" =>    351848, "amount" =>    13999],
            ["id" =>    351849, "amount" =>    92265],
            ["id" =>    351850, "amount" =>    62991.3],
            ["id" =>    351851, "amount" =>    125984],
            ["id" =>    351852, "amount" =>    144881.14],
            ["id" =>    351853, "amount" =>    209975],
            ["id" =>    351854, "amount" =>    119000.04],
            ["id" =>    351855, "amount" =>    10500],
            ["id" =>    351856, "amount" =>    10500],
            ["id" =>    351857, "amount" =>    21000],
            ["id" =>    351858, "amount" =>    7623],
            ["id" =>    351859, "amount" =>    38115],
            ["id" =>    351860, "amount" =>    15246],
            ["id" =>    351861, "amount" =>    73765.3],
            ["id" =>    351862, "amount" =>    42827.76],
            ["id" =>    351863, "amount" =>    11098],
            ["id" =>    351864, "amount" =>    16240],
            ["id" =>    351865, "amount" =>    146155.14],
            ["id" =>    351866, "amount" =>    243591.6],
            ["id" =>    351867, "amount" =>    32479.28],
            ["id" =>    351868, "amount" =>    16797],
            ["id" =>    351869, "amount" =>    11198],
            ["id" =>    351870, "amount" =>    33594],
            ["id" =>    351871, "amount" =>    16797],
            ["id" =>    351872, "amount" =>    5599],
            ["id" =>    351873, "amount" =>    16797],
            ["id" =>    351874, "amount" =>    5599],
            ["id" =>    351875, "amount" =>    22396],
            ["id" =>    351876, "amount" =>    25015],
            ["id" =>    351877, "amount" =>    4199],
            ["id" =>    351878, "amount" =>    4199],
            ["id" =>    351879, "amount" =>    16796],
            ["id" =>    351880, "amount" =>    53279.03],
            ["id" =>    351881, "amount" =>    39419],
            ["id" =>    351882, "amount" =>    132282.57],
            ["id" =>    351883, "amount" =>    163778.42],
            ["id" =>    351884, "amount" =>    243599.4],
            ["id" =>    351885, "amount" =>    85988.07],
            ["id" =>    351886, "amount" =>    45943.98],
            ["id" =>    351887, "amount" =>    6299],
            ["id" =>    351888, "amount" =>    118711.98],
            ["id" =>    351889, "amount" =>    24495],
            ["id" =>    351890, "amount" =>    48990],
            ["id" =>    351891, "amount" =>    68586],
            ["id" =>    351892, "amount" =>    53889],
            ["id" =>    351893, "amount" =>    19596],
            ["id" =>    351894, "amount" =>    4899],
            ["id" =>    351895, "amount" =>    14697],
            ["id" =>    351896, "amount" =>    24495],
            ["id" =>    351897, "amount" =>    215521.74],
            ["id" =>    351898, "amount" =>    123155.28],
            ["id" =>    351899, "amount" =>    723537.27],
            ["id" =>    351900, "amount" =>    369465.84],
            ["id" =>    351901, "amount" =>    892875.78],
            ["id" =>    351902, "amount" =>    708142.86],
            ["id" =>    351903, "amount" =>    677354.04],
            ["id" =>    351904, "amount" =>    61577.64],
            ["id" =>    351905, "amount" =>    63687],
            ["id" =>    351906, "amount" =>    34293],
            ["id" =>    351907, "amount" =>    78384],
            ["id" =>    351908, "amount" =>    186162],
            ["id" =>    351909, "amount" =>    171465],
            ["id" =>    351910, "amount" =>    166566],
            ["id" =>    351911, "amount" =>    107778],
            ["id" =>    351912, "amount" =>    93081],
            ["id" =>    351913, "amount" =>    7034.56],
            ["id" =>    351914, "amount" =>    210719.7],
            ["id" =>    351915, "amount" =>    119285.77],
            ["id" =>    351916, "amount" =>    14197.48],
            ["id" =>    351917, "amount" =>    238833.34],
            ["id" =>    351918, "amount" =>    462116.16],
            ["id" =>    351919, "amount" =>    307915.08],
            ["id" =>    351920, "amount" =>    105216.6],
            ["id" =>    351921, "amount" =>    119285.77],
            ["id" =>    351922, "amount" =>    6998],
            ["id" =>    351923, "amount" =>    13996],
            ["id" =>    351924, "amount" =>    76978],
            ["id" =>    351925, "amount" =>    62982],
            ["id" =>    351926, "amount" =>    122465],
            ["id" =>    351927, "amount" =>    111968],
            ["id" =>    351928, "amount" =>    108469],
            ["id" =>    351929, "amount" =>    52485],
            ["id" =>    351930, "amount" =>    34990],
            ["id" =>    351931, "amount" =>    34990],
            ["id" =>    351932, "amount" =>    48986],
            ["id" =>    351933, "amount" =>    115467],
            ["id" =>    351934, "amount" =>    73479],
            ["id" =>    351935, "amount" =>    45487],
            ["id" =>    351936, "amount" =>    27963.12],
            ["id" =>    351937, "amount" =>    63019.08],
            ["id" =>    351938, "amount" =>    59433.53],
            ["id" =>    351939, "amount" =>    80482.98],
            ["id" =>    351940, "amount" =>    52467.3],
            ["id" =>    351941, "amount" =>    21059.58],
            ["id" =>    351942, "amount" =>    83369],
            ["id" =>    351943, "amount" =>    7579],
            ["id" =>    351944, "amount" =>    7579],
            ["id" =>    351945, "amount" =>    189475],
            ["id" =>    351946, "amount" =>    113685],
            ["id" =>    351947, "amount" =>    13480],
            ["id" =>    351948, "amount" =>    13800],
            ["id" =>    351949, "amount" =>    11000],
            ["id" =>    351950, "amount" =>    13500],
            ["id" =>    351951, "amount" =>    2219],
            ["id" =>    351952, "amount" =>    22000],
            ["id" =>    351953, "amount" =>    49990],
            ["id" =>    351954, "amount" =>    20999],
            ["id" =>    351955, "amount" =>    25000],
            ["id" =>    351956, "amount" =>    21000],
            ["id" =>    351957, "amount" =>    49500],
            ["id" =>    351958, "amount" =>    26000],
            ["id" =>    351959, "amount" =>    44200],
            ["id" =>    351960, "amount" =>    69800],
            ["id" =>    351961, "amount" =>    11500],
            ["id" =>    351962, "amount" =>    28000],
            ["id" =>    351963, "amount" =>    6999.09],
            ["id" =>    351964, "amount" =>    20997.24],
            ["id" =>    351965, "amount" =>    97989.22],
            ["id" =>    351966, "amount" =>    76991.31],
            ["id" =>    351967, "amount" =>    90989.73],
            ["id" =>    351968, "amount" =>    90989.73],
            ["id" =>    351969, "amount" =>    55993.36],
            ["id" =>    351970, "amount" =>    41994.72],
            ["id" =>    351971, "amount" =>    16900],
            ["id" =>    351972, "amount" =>    9900],
            ["id" =>    351973, "amount" =>    11000],
            ["id" =>    351974, "amount" =>    24393],
            ["id" =>    351975, "amount" =>    34191.01],
            ["id" =>    351976, "amount" =>    24393],
            ["id" =>    351977, "amount" =>    68483.94],
            ["id" =>    351978, "amount" =>    83181],
            ["id" =>    351979, "amount" =>    88079.94],
            ["id" =>    351980, "amount" =>    43989.03],
            ["id" =>    351981, "amount" =>    48888],
            ["id" =>    351982, "amount" =>    39090],
            ["id" =>    351983, "amount" =>    10600],
            ["id" =>    351984, "amount" =>    153700],
            ["id" =>    351985, "amount" =>    5300],
            ["id" =>    351986, "amount" =>    5300],
            ["id" =>    351987, "amount" =>    5300],
            ["id" =>    351988, "amount" =>    159000],
            ["id" =>    351989, "amount" =>    164300],
            ["id" =>    351990, "amount" =>    137800],
            ["id" =>    351991, "amount" =>    148400],
            ["id" =>    351992, "amount" =>    132500],
            ["id" =>    351993, "amount" =>    132500],
            ["id" =>    351994, "amount" =>    29393],
            ["id" =>    351995, "amount" =>    104975.25],
            ["id" =>    351996, "amount" =>    130169.93],
            ["id" =>    351997, "amount" =>    104975.25],
            ["id" =>    351998, "amount" =>    146966.4],
            ["id" =>    351999, "amount" =>    104975.25],
            ["id" =>    352000, "amount" =>    29393],
            ["id" =>    352014, "amount" =>    17900],
            ["id" =>    352015, "amount" =>    19500],
            ["id" =>    352017, "amount" =>    5040],
            ["id" =>    352018, "amount" =>    12500],
            ["id" =>    352019, "amount" =>    8712],
            ["id" =>    352020, "amount" =>    5463.15],
            ["id" =>    352021, "amount" =>    4616.15],
            ["id" =>    352022, "amount" =>    5755],
            ["id" =>    352023, "amount" =>    4401],
            ["id" =>    352024, "amount" =>    4401],
            ["id" =>    352025, "amount" =>    11550],
            ["id" =>    352026, "amount" =>    7519],
            ["id" =>    352027, "amount" =>    5039.65],
            ["id" =>    352028, "amount" =>    5717],
            ["id" =>    352029, "amount" =>    16083],
            ["id" =>    352030, "amount" =>    5717.25],
            ["id" =>    352031, "amount" =>    5039.65],
            ["id" =>    352032, "amount" =>    23925],
            ["id" =>    352033, "amount" =>    4616],
            ["id" =>    352034, "amount" =>    4616],
            ["id" =>    352035, "amount" =>    7623],
            ["id" =>    352036, "amount" =>    7623],
            ["id" =>    352037, "amount" =>    10998],
            ["id" =>    352038, "amount" =>    5039.65],
            ["id" =>    352039, "amount" =>    5929],
            ["id" =>    352040, "amount" =>    7800],
            ["id" =>    352041, "amount" =>    7800],
            ["id" =>    352042, "amount" =>    7800],
            ["id" =>    352043, "amount" =>    5039.65],
            ["id" =>    352044, "amount" =>    4616],
            ["id" =>    352045, "amount" =>    8712],
            ["id" =>    352046, "amount" =>    6243],
            ["id" =>    352047, "amount" =>    5717],
            ["id" =>    352048, "amount" =>    5039.65],
            ["id" =>    352049, "amount" =>    3958.2],
            ["id" =>    352050, "amount" =>    5039.65],
            ["id" =>    352051, "amount" =>    5039.65],
            ["id" =>    352052, "amount" =>    3958.2],
            ["id" =>    352053, "amount" =>    10998],
            ["id" =>    352054, "amount" =>    10998],
            ["id" =>    352055, "amount" =>    6999],
            ["id" =>    352056, "amount" =>    6999],
            ["id" =>    352057, "amount" =>    6999],
            ["id" =>    352058, "amount" =>    5039.65],
            ["id" =>    352059, "amount" =>    4616],
            ["id" =>    352060, "amount" =>    6267],
            ["id" =>    352061, "amount" =>    6856],
            ["id" =>    352062, "amount" =>    6799],
            ["id" =>    352063, "amount" =>    4616],
            ["id" =>    352064, "amount" =>    10241],
            ["id" =>    352065, "amount" =>    8712],
            ["id" =>    352066, "amount" =>    4616],
            ["id" =>    352067, "amount" =>    10500],
            ["id" =>    352068, "amount" =>    5929],
            ["id" =>    352069, "amount" =>    8712],
            ["id" =>    352070, "amount" =>    8712],
            ["id" =>    352071, "amount" =>    4270],
            ["id" =>    352072, "amount" =>    6267],
            ["id" =>    352073, "amount" =>    5717],
            ["id" =>    352074, "amount" =>    6267],
            ["id" =>    352075, "amount" =>    5096],
            ["id" =>    352076, "amount" =>    5099.5],
            ["id" =>    352077, "amount" =>    5099.5],
            ["id" =>    352078, "amount" =>    5099.5],
            ["id" =>    352079, "amount" =>    15299.01],
            ["id" =>    352080, "amount" =>    5099.5],
            ["id" =>    352081, "amount" =>    5929],
            ["id" =>    352082, "amount" =>    5039.65],
            ["id" =>    352083, "amount" =>    13860],
            ["id" =>    352084, "amount" =>    8712],
            ["id" =>    352085, "amount" =>    5505],
            ["id" =>    352086, "amount" =>    7623],
            ["id" =>    352087, "amount" =>    5717],
            ["id" =>    352088, "amount" =>    4249],
            ["id" =>    352089, "amount" =>    4249],
            ["id" =>    352090, "amount" =>    25494],
            ["id" =>    352091, "amount" =>    12747],
            ["id" =>    352092, "amount" =>    10348],
            ["id" =>    352093, "amount" =>    15546.99],
            ["id" =>    352094, "amount" =>    5249],
            ["id" =>    352095, "amount" =>    5249],
            ["id" =>    352096, "amount" =>    19700],
            ["id" =>    352097, "amount" =>    164262],
            ["id" =>    352098, "amount" =>    265698],
            ["id" =>    352099, "amount" =>    16000],
            ["id" =>    352100, "amount" =>    19615],
            ["id" =>    352101, "amount" =>    14850],
            ["id" =>    352102, "amount" =>    32000],
            ["id" =>    352103, "amount" =>    14500],
            ["id" =>    352104, "amount" =>    175000],
            ["id" =>    352105, "amount" =>    17500],
            ["id" =>    352106, "amount" =>    255000],
            ["id" =>    352107, "amount" =>    15000],
            ["id" =>    352108, "amount" =>    66997],
            ["id" =>    352109, "amount" =>    57000],
            ["id" =>    352110, "amount" =>    17500],
            ["id" =>    352111, "amount" =>    35000],
            ["id" =>    352112, "amount" =>    56000],
            ["id" =>    352113, "amount" =>    16000],
            ["id" =>    352114, "amount" =>    33000],
            ["id" =>    352115, "amount" =>    23500],
            ["id" =>    352116, "amount" =>    23500],
            ["id" =>    352117, "amount" =>    23500],
            ["id" =>    352118, "amount" =>    30500],
            ["id" =>    352119, "amount" =>    30000],
            ["id" =>    352120, "amount" =>    35000],
            ["id" =>    352121, "amount" =>    12460],
            ["id" =>    352122, "amount" =>    6299.1],
            ["id" =>    352123, "amount" =>    62990.8],
            ["id" =>    352124, "amount" =>    75589.08],
            ["id" =>    352125, "amount" =>    188972.4],
            ["id" =>    352126, "amount" =>    163776.34],
            ["id" =>    352127, "amount" =>    100785.6],
            ["id" =>    352128, "amount" =>    6299.1],
            ["id" =>    352129, "amount" =>    37794.54],
            ["id" =>    352130, "amount" =>    104940],
            ["id" =>    352131, "amount" =>    104940],
            ["id" =>    352132, "amount" =>    104940],
            ["id" =>    352133, "amount" =>    104940],
            ["id" =>    352134, "amount" =>    4199],
            ["id" =>    352135, "amount" =>    41990],
            ["id" =>    352136, "amount" =>    67184],
            ["id" =>    352137, "amount" =>    41990],
            ["id" =>    352138, "amount" =>    16796],
            ["id" =>    352139, "amount" =>    69420.48],
            ["id" =>    352140, "amount" =>    60748.38],
            ["id" =>    352141, "amount" =>    39044.61],
            ["id" =>    352142, "amount" =>    69420.48],
            ["id" =>    352143, "amount" =>    43404.7],
            ["id" =>    352144, "amount" =>    34733.12],
            ["id" =>    352145, "amount" =>    8398],
            ["id" =>    352146, "amount" =>    12597],
            ["id" =>    352147, "amount" =>    25194],
            ["id" =>    352148, "amount" =>    41990],
            ["id" =>    352149, "amount" =>    16796],
            ["id" =>    352150, "amount" =>    5099],
            ["id" =>    352151, "amount" =>    5099],
            ["id" =>    352152, "amount" =>    55990],
            ["id" =>    352153, "amount" =>    83985],
            ["id" =>    352154, "amount" =>    330259.8],
            ["id" =>    352155, "amount" =>    302669.95],
            ["id" =>    352156, "amount" =>    27995],
            ["id" =>    352157, "amount" =>    215556],
            ["id" =>    352158, "amount" =>    244950],
            ["id" =>    352159, "amount" =>    264546],
            ["id" =>    352160, "amount" =>    146970],
            ["id" =>    352161, "amount" =>    230253],
            ["id" =>    352169, "amount" =>    1],
            ["id" =>    352196, "amount" =>    13018],
            ["id" =>    352197, "amount" =>    14479],
            ["id" =>    352198, "amount" =>    15500],
            ["id" =>    352199, "amount" =>    32000],
            ["id" =>    352200, "amount" =>    16830],
            ["id" =>    352201, "amount" =>    19800],
            ["id" =>    352202, "amount" =>    35000],
            ["id" =>    352203, "amount" =>    23000],
            ["id" =>    352204, "amount" =>    70000],
            ["id" =>    352205, "amount" =>    53490],
            ["id" =>    352206, "amount" =>    41000],
            ["id" =>    352207, "amount" =>    24499],
            ["id" =>    352208, "amount" =>    18000],
            ["id" =>    352209, "amount" =>    234880],
            ["id" =>    352210, "amount" =>    20000],
            ["id" =>    352211, "amount" =>    20000],
            ["id" =>    352212, "amount" =>    655],
            ["id" =>    352213, "amount" =>    17050],
            ["id" =>    352214, "amount" =>    31000],
            ["id" =>    352215, "amount" =>    53000],
            ["id" =>    352216, "amount" =>    16000],
            ["id" =>    352217, "amount" =>    14500],
            ["id" =>    352218, "amount" =>    98000],
            ["id" =>    352219, "amount" =>    110000],
            ["id" =>    352220, "amount" =>    160000],
            ["id" =>    352221, "amount" =>    51800],
            ["id" =>    352222, "amount" =>    32000],
            ["id" =>    352223, "amount" =>    255000],
            ["id" =>    352224, "amount" =>    54000],
            ["id" =>    352225, "amount" =>    20500],
            ["id" =>    352226, "amount" =>    36900],
            ["id" =>    352227, "amount" =>    35800],
            ["id" =>    352228, "amount" =>    49500],
            ["id" =>    352229, "amount" =>    36000],
            ["id" =>    352230, "amount" =>    22000],
            ["id" =>    352231, "amount" =>    23500],
            ["id" =>    352232, "amount" =>    14000],
            ["id" =>    352233, "amount" =>    21000],
            ["id" =>    352234, "amount" =>    17656],
            ["id" =>    352235, "amount" =>    23500],
            ["id" =>    352236, "amount" =>    25850],
            ["id" =>    352237, "amount" =>    65000],
            ["id" =>    352238, "amount" =>    39000],
            ["id" =>    352239, "amount" =>    30000],
            ["id" =>    352240, "amount" =>    50000],
            ["id" =>    352241, "amount" =>    43450],
            ["id" =>    352242, "amount" =>    18578],
            ["id" =>    352243, "amount" =>    115000],
            ["id" =>    352244, "amount" =>    30000],
            ["id" =>    352245, "amount" =>    119400],
            ["id" =>    352246, "amount" =>    40000],
            ["id" =>    352247, "amount" =>    44000],
            ["id" =>    352248, "amount" =>    25900],
            ["id" =>    352249, "amount" =>    18700],
            ["id" =>    352250, "amount" =>    19000],
            ["id" =>    352251, "amount" =>    16500],
            ["id" =>    352252, "amount" =>    16940],
            ["id" =>    352253, "amount" =>    18700],
            ["id" =>    352254, "amount" =>    48000],
            ["id" =>    352255, "amount" =>    32000],
            ["id" =>    352256, "amount" =>    52750],
            ["id" =>    352257, "amount" =>    18696],
            ["id" =>    352258, "amount" =>    68000],
            ["id" =>    352259, "amount" =>    17788],
            ["id" =>    352260, "amount" =>    26000],
            ["id" =>    352261, "amount" =>    36500],
            ["id" =>    352262, "amount" =>    31900],
            ["id" =>    352263, "amount" =>    24001],
            ["id" =>    352264, "amount" =>    18500],
            ["id" =>    352303, "amount" =>    73465],
            ["id" =>    352304, "amount" =>    136435],
            ["id" =>    352305, "amount" =>    65069],
            ["id" =>    352306, "amount" =>    27287],
            ["id" =>    352307, "amount" =>    20990],
            ["id" =>    352308, "amount" =>    17520],
            ["id" =>    352309, "amount" =>    7008],
            ["id" =>    352310, "amount" =>    24528],
            ["id" =>    352311, "amount" =>    17520],
            ["id" =>    352312, "amount" =>    17520],
            ["id" =>    352313, "amount" =>    17520],
            ["id" =>    352314, "amount" =>    133544.7],
            ["id" =>    352315, "amount" =>    304185.15],
            ["id" =>    352316, "amount" =>    296765.6],
            ["id" =>    352317, "amount" =>    148382.8],
            ["id" =>    352318, "amount" =>    51934.12],
            ["id" =>    352319, "amount" =>    96448.69],
            ["id" =>    352320, "amount" =>    5040],
            ["id" =>    352321, "amount" =>    5929],
            ["id" =>    352322, "amount" =>    4616],
            ["id" =>    352323, "amount" =>    4616],
            ["id" =>    352324, "amount" =>    5040],
            ["id" =>    352325, "amount" =>    4616],
            ["id" =>    352326, "amount" =>    7623],
            ["id" =>    352327, "amount" =>    8712],
            ["id" =>    352328, "amount" =>    6268],
            ["id" =>    352329, "amount" =>    5040],
            ["id" =>    352330, "amount" =>    5040],
            ["id" =>    352331, "amount" =>    4697],
            ["id" =>    352332, "amount" =>    5465],
            ["id" =>    352333, "amount" =>    14025],
            ["id" =>    352334, "amount" =>    25200],
            ["id" =>    352392, "amount" =>    29700],
            ["id" =>    352393, "amount" =>    21000],
            ["id" =>    352394, "amount" =>    33500],
            ["id" =>    352395, "amount" =>    13750],
            ["id" =>    352396, "amount" =>    20000],
            ["id" =>    352397, "amount" =>    14000],
            ["id" =>    352398, "amount" =>    30599],
            ["id" =>    352399, "amount" =>    16500],
            ["id" =>    352400, "amount" =>    50500],
            ["id" =>    352401, "amount" =>    50000],
            ["id" =>    352402, "amount" =>    34800],
            ["id" =>    352403, "amount" =>    18150],
            ["id" =>    352405, "amount" =>    46000],
            ["id" =>    352406, "amount" =>    38949],
            ["id" =>    352407, "amount" =>    31900],
            ["id" =>    352408, "amount" =>    24000],
            ["id" =>    352409, "amount" =>    24999],
            ["id" =>    352410, "amount" =>    95000],
            ["id" =>    352411, "amount" =>    20130],
            ["id" =>    352412, "amount" =>    21780],
            ["id" =>    352413, "amount" =>    15339.5],
            ["id" =>    352414, "amount" =>    50000],
            ["id" =>    352415, "amount" =>    18000],
            ["id" =>    352416, "amount" =>    19000],
            ["id" =>    352417, "amount" =>    20000],
            ["id" =>    352418, "amount" =>    21000],
            ["id" =>    352419, "amount" =>    19000],
            ["id" =>    352420, "amount" =>    19000],
            ["id" =>    352421, "amount" =>    19000],
            ["id" =>    352422, "amount" =>    19800],
            ["id" =>    352423, "amount" =>    20500],
            ["id" =>    352424, "amount" =>    19000],
            ["id" =>    352425, "amount" =>    14000],
            ["id" =>    352426, "amount" =>    22000],
            ["id" =>    352427, "amount" =>    16830],
            ["id" =>    352430, "amount" =>    42000],
            ["id" =>    352431, "amount" =>    27000],
            ["id" =>    352432, "amount" =>    20940],
            ["id" =>    352433, "amount" =>    22900],
            ["id" =>    352435, "amount" =>    48500],
            ["id" =>    352436, "amount" =>    17500],
            ["id" =>    352437, "amount" =>    18500],
            ["id" =>    352438, "amount" =>    16339],
            ["id" =>    352439, "amount" =>    97000],
            ["id" =>    352440, "amount" =>    60000],
            ["id" =>    352441, "amount" =>    20000],
            ["id" =>    352442, "amount" =>    29880],
            ["id" =>    352444, "amount" =>    9099.89],
            ["id" =>    352445, "amount" =>    7649],
            ["id" =>    352469, "amount" =>    4199],
            ["id" =>    352470, "amount" =>    15749.25],
            ["id" =>    352471, "amount" =>    7762],
            ["id" =>    352472, "amount" =>    4799.2],
            ["id" =>    352473, "amount" =>    2499],
            ["id" =>    352474, "amount" =>    4199],
            ["id" =>    352475, "amount" =>    9099.86],
            ["id" =>    352476, "amount" =>    12787.5],
            ["id" =>    352477, "amount" =>    5599],
            ["id" =>    352504, "amount" =>    24000],
            ["id" =>    352505, "amount" =>    30500],
            ["id" =>    352506, "amount" =>    23500],
            ["id" =>    352507, "amount" =>    108000],
            ["id" =>    352508, "amount" =>    25000],
            ["id" =>    352509, "amount" =>    41900],
            ["id" =>    352510, "amount" =>    38100],
            ["id" =>    352511, "amount" =>    48000],
            ["id" =>    352512, "amount" =>    18500],
            ["id" =>    352513, "amount" =>    23500],
            ["id" =>    352514, "amount" =>    42700],
            ["id" =>    352515, "amount" =>    16622],
            ["id" =>    352516, "amount" =>    18500],
            ["id" =>    352517, "amount" =>    52000],
            ["id" =>    352518, "amount" =>    15400],
            ["id" =>    352519, "amount" =>    40500],
            ["id" =>    352520, "amount" =>    74499],
            ["id" =>    352521, "amount" =>    90500],
            ["id" =>    352522, "amount" =>    3209],
            ["id" =>    352523, "amount" =>    23900],
            ["id" =>    352524, "amount" =>    16383],
            ["id" =>    352525, "amount" =>    90500],
            ["id" =>    352526, "amount" =>    236838],
            ["id" =>    352527, "amount" =>    213536],
            ["id" =>    352528, "amount" =>    40500],
            ["id" =>    352529, "amount" =>    7012],
            ["id" =>    352530, "amount" =>    34899],
            ["id" =>    352531, "amount" =>    14496],
            ["id" =>    352532, "amount" =>    194123],
            ["id" =>    352533, "amount" =>    278158],
            ["id" =>    352534, "amount" =>    102171],
            ["id" =>    352535, "amount" =>    30000],
            ["id" =>    352536, "amount" =>    33000],
            ["id" =>    352537, "amount" =>    44500],
            ["id" =>    352538, "amount" =>    17000],
            ["id" =>    352539, "amount" =>    39900],
            ["id" =>    352540, "amount" =>    21563],
            ["id" =>    352541, "amount" =>    26000],
            ["id" =>    352542, "amount" =>    20500],
            ["id" =>    352543, "amount" =>    19800],
            ["id" =>    352578, "amount" =>    12000],
            ["id" =>    352579, "amount" =>    3958.2],
            ["id" =>    352580, "amount" =>    10725],
            ["id" =>    352581, "amount" =>    37000],
            ["id" =>    352582, "amount" =>    38000],
            ["id" =>    352583, "amount" =>    23512.5],
            ["id" =>    352584, "amount" =>    12455],
            ["id" =>    352585, "amount" =>    5999],
            ["id" =>    352586, "amount" =>    10010],
            ["id" =>    352587, "amount" =>    7836],
            ["id" =>    352588, "amount" =>    12455],
            ["id" =>    352589, "amount" =>    27170],
            ["id" =>    352590, "amount" =>    27170],
            ["id" =>    352591, "amount" =>    27170],
            ["id" =>    352599, "amount" =>    14438],
            ["id" =>    352600, "amount" =>    14438],
            ["id" =>    352601, "amount" =>    14025],
            ["id" =>    352602, "amount" =>    14025],
            ["id" =>    352603, "amount" =>    10725],
            ["id" =>    352604, "amount" =>    5599],
            ["id" =>    352605, "amount" =>    11500],
            ["id" =>    352606, "amount" =>    12500],
            ["id" =>    352616, "amount" =>    5040],
            ["id" =>    352617, "amount" =>    23925],
            ["id" =>    352618, "amount" =>    5039],
            ["id" =>    352619, "amount" =>    4616],
            ["id" =>    352620, "amount" =>    8712],
            ["id" =>    352621, "amount" =>    4616.15],
            ["id" =>    352622, "amount" =>    5463.15],
            ["id" =>    352623, "amount" =>    7623],
            ["id" =>    352624, "amount" =>    6243.6],
            ["id" =>    352625, "amount" =>    45500],
            ["id" =>    352626, "amount" =>    7946],
            ["id" =>    352627, "amount" =>    4401],
            ["id" =>    352628, "amount" =>    4270],
            ["id" =>    352629, "amount" =>    9719],
            ["id" =>    352630, "amount" =>    10998],
            ["id" =>    352631, "amount" =>    7623],
            ["id" =>    352632, "amount" =>    4616.15],
            ["id" =>    352633, "amount" =>    23336],
            ["id" =>    352634, "amount" =>    8813],
            ["id" =>    352635, "amount" =>    6600],
            ["id" =>    352636, "amount" =>    6160],
            ["id" =>    352637, "amount" =>    8712],
            ["id" =>    352638, "amount" =>    3958.2],
            ["id" =>    352639, "amount" =>    5039.65],
            ["id" =>    352640, "amount" =>    23925],
            ["id" =>    352641, "amount" =>    5717.25],
            ["id" =>    352642, "amount" =>    5039.65],
            ["id" =>    352643, "amount" =>    5929],
            ["id" =>    352644, "amount" =>    5717],
            ["id" =>    352645, "amount" =>    7623],
            ["id" =>    352646, "amount" =>    6243],
            ["id" =>    352647, "amount" =>    25200],
            ["id" =>    352648, "amount" =>    13100],
            ["id" =>    352649, "amount" =>    13100],
            ["id" =>    352650, "amount" =>    18400],
            ["id" =>    352651, "amount" =>    4620],
            ["id" =>    352652, "amount" =>    23650],
            ["id" =>    352653, "amount" =>    23650],
            ["id" =>    352654, "amount" =>    33550],
            ["id" =>    352655, "amount" =>    18700],
            ["id" =>    352656, "amount" =>    18700],
            ["id" =>    352657, "amount" =>    23320],
            ["id" =>    352658, "amount" =>    28499],
            ["id" =>    352659, "amount" =>    23320],
            ["id" =>    352670, "amount" =>    16000],
            ["id" =>    352671, "amount" =>    12000],
            ["id" =>    352672, "amount" =>    14520],
            ["id" =>    352673, "amount" =>    28629],
            ["id" =>    352674, "amount" =>    19000],
            ["id" =>    352675, "amount" =>    58000],
            ["id" =>    352676, "amount" =>    23500],
            ["id" =>    352677, "amount" =>    23600],
            ["id" =>    352678, "amount" =>    12000],
            ["id" =>    352679, "amount" =>    28000],
            ["id" =>    352680, "amount" =>    27482],
            ["id" =>    352681, "amount" =>    7774],
            ["id" =>    352682, "amount" =>    26000],
            ["id" =>    352683, "amount" =>    25900],
            ["id" =>    352684, "amount" =>    20550],
            ["id" =>    352685, "amount" =>    8119.71],
            ["id" =>    352686, "amount" =>    13860],
            ["id" =>    352687, "amount" =>    5039.65],
            ["id" =>    352688, "amount" =>    10241],
            ["id" =>    352689, "amount" =>    7099],
            ["id" =>    352690, "amount" =>    55900],
            ["id" =>    352691, "amount" =>    39500],
            ["id" =>    352692, "amount" =>    75500],
            ["id" =>    352693, "amount" =>    20000],
            ["id" =>    352694, "amount" =>    15400],
            ["id" =>    352695, "amount" =>    19000],
            ["id" =>    352696, "amount" =>    102000],
            ["id" =>    352697, "amount" =>    16000],
            ["id" =>    352698, "amount" =>    37500],
            ["id" =>    352699, "amount" =>    19699],
            ["id" =>    352700, "amount" =>    297765],
            ["id" =>    352701, "amount" =>    274402],
            ["id" =>    352702, "amount" =>    224799],
            ["id" =>    352703, "amount" =>    246912],
            ["id" =>    352704, "amount" =>    15000],
            ["id" =>    352705, "amount" =>    18000],
            ["id" =>    352706, "amount" =>    16000],
            ["id" =>    352707, "amount" =>    20000],
            ["id" =>    352708, "amount" =>    14300],
            ["id" =>    352709, "amount" =>    23500],
            ["id" =>    352710, "amount" =>    33000],
            ["id" =>    352711, "amount" =>    58500],
            ["id" =>    352712, "amount" =>    28000],
            ["id" =>    352713, "amount" =>    10424],
            ["id" =>    352714, "amount" =>    38000],
            ["id" =>    352715, "amount" =>    35500],
            ["id" =>    352716, "amount" =>    34500],
            ["id" =>    352717, "amount" =>    36000],
            ["id" =>    352718, "amount" =>    18803],
            ["id" =>    352719, "amount" =>    18500],
            ["id" =>    352720, "amount" =>    15000],
            ["id" =>    352721, "amount" =>    14000],
            ["id" =>    352722, "amount" =>    37500],
            ["id" =>    352723, "amount" =>    15950],
            ["id" =>    352724, "amount" =>    33000],
            ["id" =>    352725, "amount" =>    30000],
            ["id" =>    352726, "amount" =>    19000],
            ["id" =>    352727, "amount" =>    32000],
            ["id" =>    352728, "amount" =>    31900],
            ["id" =>    352729, "amount" =>    12919],
            ["id" =>    352730, "amount" =>    88000],
            ["id" =>    352731, "amount" =>    180001],
            ["id" =>    352732, "amount" =>    196000],
            ["id" =>    352733, "amount" =>    260000],
            ["id" =>    352734, "amount" =>    121000],
            ["id" =>    352735, "amount" =>    18700],
            ["id" =>    352736, "amount" =>    48500],
            ["id" =>    352737, "amount" =>    26000],
            ["id" =>    352738, "amount" =>    28050],
            ["id" =>    352739, "amount" =>    36500],
            ["id" =>    352740, "amount" =>    29400],
            ["id" =>    352741, "amount" =>    28000],
            ["id" =>    352742, "amount" =>    17800],
            ["id" =>    352743, "amount" =>    14500],
            ["id" =>    352744, "amount" =>    15000],
            ["id" =>    352745, "amount" =>    66208],
            ["id" =>    352746, "amount" =>    250000],
            ["id" =>    352747, "amount" =>    8119.77],
            ["id" =>    352748, "amount" =>    37000],
            ["id" =>    352749, "amount" =>    86000],
            ["id" =>    352750, "amount" =>    19800],
            ["id" =>    352751, "amount" =>    19800],
            ["id" =>    352752, "amount" =>    19800],
            ["id" =>    352753, "amount" =>    49290],
            ["id" =>    352754, "amount" =>    18700],
            ["id" =>    352755, "amount" =>    27000],
            ["id" =>    352756, "amount" =>    45000],
            ["id" =>    352757, "amount" =>    90000],
            ["id" =>    352758, "amount" =>    16500],
            ["id" =>    352759, "amount" =>    15094],
            ["id" =>    352760, "amount" =>    18000],
            ["id" =>    352761, "amount" =>    10010],
            ["id" =>    352762, "amount" =>    62264],
            ["id" =>    352763, "amount" =>    20000],
            ["id" =>    352764, "amount" =>    19000],
            ["id" =>    352765, "amount" =>    19000],
            ["id" =>    352766, "amount" =>    19000],
            ["id" =>    352767, "amount" =>    40500],
            ["id" =>    352768, "amount" =>    12320],
            ["id" =>    352769, "amount" =>    15500],
            ["id" =>    352770, "amount" =>    73000],
            ["id" =>    352771, "amount" =>    68000],
            ["id" =>    352772, "amount" =>    27000],
            ["id" =>    352773, "amount" =>    45000],
            ["id" =>    352774, "amount" =>    35640],
            ["id" =>    352775, "amount" =>    40000],
            ["id" =>    352776, "amount" =>    25000],
            ["id" =>    352777, "amount" =>    7370.54],
            ["id" =>    352778, "amount" =>    56000],
            ["id" =>    352779, "amount" =>    53000],
            ["id" =>    352780, "amount" =>    27900],
            ["id" =>    352781, "amount" =>    24977],
            ["id" =>    352782, "amount" =>    31000],
            ["id" =>    352783, "amount" =>    43010],
            ["id" =>    352784, "amount" =>    17365],
            ["id" =>    352785, "amount" =>    18000],
            ["id" =>    352786, "amount" =>    20441],
            ["id" =>    352787, "amount" =>    14492],
            ["id" =>    352788, "amount" =>    9100],
            ["id" =>    352789, "amount" =>    28000],
            ["id" =>    352790, "amount" =>    18800],
            ["id" =>    352791, "amount" =>    99000],
            ["id" =>    352792, "amount" =>    46900],
            ["id" =>    352793, "amount" =>    17473],
            ["id" =>    352794, "amount" =>    22300],
            ["id" =>    352795, "amount" =>    12500],
            ["id" =>    352796, "amount" =>    18999.5],
            ["id" =>    352797, "amount" =>    50800],
            ["id" =>    352798, "amount" =>    12500],
            ["id" =>    352799, "amount" =>    36000],
            ["id" =>    352800, "amount" =>    11000],
            ["id" =>    352801, "amount" =>    10999],
            ["id" =>    352802, "amount" =>    14000],
            ["id" =>    352803, "amount" =>    23500],
            ["id" =>    352804, "amount" =>    31500],
            ["id" =>    352805, "amount" =>    18000],
            ["id" =>    352806, "amount" =>    28050],
            ["id" =>    352807, "amount" =>    26500],
            ["id" =>    352808, "amount" =>    48900],
            ["id" =>    352809, "amount" =>    27000],
            ["id" =>    352810, "amount" =>    31000],
            ["id" =>    352811, "amount" =>    13640],
            ["id" =>    352812, "amount" =>    56900],
            ["id" =>    352813, "amount" =>    7419.08],
            ["id" =>    352814, "amount" =>    7419.08],
            ["id" =>    352815, "amount" =>    7419.08],
            ["id" =>    352816, "amount" =>    7419.08],
            ["id" =>    352817, "amount" =>    7419.08],
            ["id" =>    352818, "amount" =>    7419.08],
            ["id" =>    352819, "amount" =>    7419.08],
            ["id" =>    352820, "amount" =>    7419.08],
            ["id" =>    352821, "amount" =>    7419.08],
            ["id" =>    352822, "amount" =>    7419.08],
            ["id" =>    352823, "amount" =>    7419.08],
            ["id" =>    352824, "amount" =>    7419.08],
            ["id" =>    352825, "amount" =>    8119.91],
            ["id" =>    352826, "amount" =>    32000],
            ["id" =>    352827, "amount" =>    9910],
            ["id" =>    352828, "amount" =>    6879],
            ["id" =>    352829, "amount" =>    13584],
            ["id" =>    352830, "amount" =>    13100],
            ["id" =>    352831, "amount" =>    11440],
            ["id" =>    352832, "amount" =>    5929],
            ["id" =>    352833, "amount" =>    151173],
            ["id" =>    352834, "amount" =>    29700],
            ["id" =>    352835, "amount" =>    38000],
            ["id" =>    352836, "amount" =>    9500],
            ["id" =>    352837, "amount" =>    41415],
            ["id" =>    352838, "amount" =>    23500],
            ["id" =>    352839, "amount" =>    30500],
            ["id" =>    352840, "amount" =>    16940],
            ["id" =>    352841, "amount" =>    20900],
            ["id" =>    352842, "amount" =>    208894],
            ["id" =>    352843, "amount" =>    280500],
            ["id" =>    352844, "amount" =>    42000],
            ["id" =>    352845, "amount" =>    301402],
            ["id" =>    352846, "amount" =>    18700],
            ["id" =>    352847, "amount" =>    56586],
            ["id" =>    352848, "amount" =>    54500],
            ["id" =>    352849, "amount" =>    20362],
            ["id" =>    352850, "amount" =>    23500],
            ["id" =>    352851, "amount" =>    139000],
            ["id" =>    352852, "amount" =>    23000],
            ["id" =>    352853, "amount" =>    20000],
            ["id" =>    352854, "amount" =>    28000],
            ["id" =>    352855, "amount" =>    60500],
            ["id" =>    352856, "amount" =>    104290],
            ["id" =>    352857, "amount" =>    38500],
            ["id" =>    352858, "amount" =>    33000],
            ["id" =>    352863, "amount" =>    41000],
            ["id" =>    352864, "amount" =>    19998],
            ["id" =>    352865, "amount" =>    33500],
            ["id" =>    352866, "amount" =>    20000],
            ["id" =>    352867, "amount" =>    15000],
            ["id" =>    352868, "amount" =>    79000],
            ["id" =>    352869, "amount" =>    63900],
            ["id" =>    352870, "amount" =>    7817.75],
            ["id" =>    352871, "amount" =>    50000],
            ["id" =>    352872, "amount" =>    32800],
            ["id" =>    352873, "amount" =>    15000],
            ["id" =>    352874, "amount" =>    123000],
            ["id" =>    352875, "amount" =>    31900],
            ["id" =>    352876, "amount" =>    6299],
            ["id" =>    352877, "amount" =>    50392],
            ["id" =>    352878, "amount" =>    62990],
            ["id" =>    352879, "amount" =>    62990],
            ["id" =>    352880, "amount" =>    125983.2],
            ["id" =>    352881, "amount" =>    125983.2],
            ["id" =>    352882, "amount" =>    125983.2],
            ["id" =>    352883, "amount" =>    94485],
            ["id" =>    352884, "amount" =>    18897],
            ["id" =>    352894, "amount" =>    28600],
            ["id" =>    352895, "amount" =>    8119.69],
            ["id" =>    352896, "amount" =>    22000],
            ["id" =>    352897, "amount" =>    20597],
            ["id" =>    352898, "amount" =>    56998],
            ["id" =>    352899, "amount" =>    31000],
            ["id" =>    352900, "amount" =>    28333],
            ["id" =>    352901, "amount" =>    20130],
            ["id" =>    352902, "amount" =>    17000],
            ["id" =>    352903, "amount" =>    62000],
            ["id" =>    352904, "amount" =>    58500],
            ["id" =>    352905, "amount" =>    36688],
            ["id" =>    352906, "amount" =>    18974],
            ["id" =>    352907, "amount" =>    21000],
            ["id" =>    352908, "amount" =>    23000],
            ["id" =>    352909, "amount" =>    22000],
            ["id" =>    352910, "amount" =>    19000],
            ["id" =>    352911, "amount" =>    40500],
            ["id" =>    352912, "amount" =>    16500],
            ["id" =>    352913, "amount" =>    21000],
            ["id" =>    352914, "amount" =>    15000],
            ["id" =>    352915, "amount" =>    9839],
            ["id" =>    352916, "amount" =>    25140],
            ["id" =>    352917, "amount" =>    17700],
            ["id" =>    352918, "amount" =>    20130],
            ["id" =>    352919, "amount" =>    74000],
            ["id" =>    352920, "amount" =>    25900],
            ["id" =>    352921, "amount" =>    22151],
            ["id" =>    352922, "amount" =>    20500],
            ["id" =>    352923, "amount" =>    95000],
            ["id" =>    352924, "amount" =>    13200],
            ["id" =>    352925, "amount" =>    46999],
            ["id" =>    352926, "amount" =>    33000],
            ["id" =>    352928, "amount" =>    28000],
            ["id" =>    352929, "amount" =>    12500],
            ["id" =>    352930, "amount" =>    14500],
            ["id" =>    352931, "amount" =>    21500],
            ["id" =>    352932, "amount" =>    25959],
            ["id" =>    352933, "amount" =>    20130],
            ["id" =>    352934, "amount" =>    44565],
            ["id" =>    352935, "amount" =>    105990],
            ["id" =>    352936, "amount" =>    20000],
            ["id" =>    352937, "amount" =>    53488],
            ["id" =>    352938, "amount" =>    13860],
            ["id" =>    352939, "amount" =>    56000],
            ["id" =>    352940, "amount" =>    40600],
            ["id" =>    352941, "amount" =>    23500],
            ["id" =>    352942, "amount" =>    21800],
            ["id" =>    352943, "amount" =>    265698],
            ["id" =>    352944, "amount" =>    215409],
            ["id" =>    352945, "amount" =>    268355],
            ["id" =>    352946, "amount" =>    206043],
            ["id" =>    352947, "amount" =>    44000],
            ["id" =>    352948, "amount" =>    31900],
            ["id" =>    352949, "amount" =>    22000],
            ["id" =>    352950, "amount" =>    25000],
            ["id" =>    352951, "amount" =>    23800],
            ["id" =>    352952, "amount" =>    23500],
            ["id" =>    352953, "amount" =>    18000],
            ["id" =>    352954, "amount" =>    17500],
            ["id" =>    352955, "amount" =>    19000],
            ["id" =>    352956, "amount" =>    20000],
            ["id" =>    352957, "amount" =>    12000],
            ["id" =>    352958, "amount" =>    19000],
            ["id" =>    352959, "amount" =>    40500],
            ["id" =>    352960, "amount" =>    18555],
            ["id" =>    352961, "amount" =>    41500],
            ["id" =>    352962, "amount" =>    19000],
            ["id" =>    352963, "amount" =>    182679],
            ["id" =>    352964, "amount" =>    191378],
            ["id" =>    352965, "amount" =>    73485],
            ["id" =>    352972, "amount" =>    114091.41],
            ["id" =>    352973, "amount" =>    170849.28],
            ["id" =>    352974, "amount" =>    64346.03],
            ["id" =>    352975, "amount" =>    75790],
            ["id" =>    352976, "amount" =>    166200],
            ["id" =>    352977, "amount" =>    55400],
            ["id" =>    352991, "amount" =>    8120],
            ["id" =>    352992, "amount" =>    207903.72],
            ["id" =>    352993, "amount" =>    138038.02],
            ["id" =>    352994, "amount" =>    14000],
            ["id" =>    352995, "amount" =>    42000],
            ["id" =>    352996, "amount" =>    6999],
            ["id" =>    352997, "amount" =>    56693.25],
            ["id" =>    352998, "amount" =>    69291.09],
            ["id" =>    352999, "amount" =>    144886.6],
            ["id" =>    353000, "amount" =>    6300],
            ["id" =>    353001, "amount" =>    150651],
            ["id" =>    353002, "amount" =>    158580],
        ];
    }
}
