<?php

namespace App\Http\Controllers\Admin\V2\Wms;

use App\Http\Controllers\Admin\V1\BaseController;
use App\Imports\BigDataImport;
use App\Logics\RedisKey;
use App\Logics\wms\Warehouse;
use App\Models\Admin\V2\Inventory;
use App\Models\Admin\V2\Supplier;
use App\Models\Admin\V2\WmsWarehouseArea;
use App\Models\AdminUser;
use App\Models\AdminUsers;
use App\Models\Roles;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Maatwebsite\Excel\Facades\Excel;
use stdClass;

/**
 * 
 */
class CommonController extends BaseController
{
    // 产品唯一码明细库存导入
    function skuDetailmport(Request $request)
    {
        $data = Excel::toArray(new stdClass(), $request->file('file'))[0] ?? [];
        // 仓库
        $warehouse = Warehouse::warehouseKeyByName();
        // 库区
        $area = WmsWarehouseArea::selectRaw('area_code,area_name,warehouse_code,concat(warehouse_code,"_",area_name) as cname')->get()->keyBy('cname')->toArray();

        $supplier = Supplier::get()->keyBy('name')->toArray();

        $quality_type_map = [
            '正品' => 1,
            '疑似瑕疵' => 2,
            '瑕疵' => 2,
        ];
        $quality_level_map = [
            '优' => 'A',
            '良' => 'B',
            '一级' => 'B',
            '二级' => 'C',
            '三级' => 'D',
        ];

        try {
            DB::beginTransaction();
            foreach ($data as $k => $item) {
                if ($k < 2) continue;
                $warehouse_code = $warehouse[$item[10]]['warehouse_code'];
                $area_code = $area[$warehouse_code . '_' . $item[11]]['area_code'];
                $sup_id = $supplier[$item[14]]['id'] ?? 0;

                $tmp = [
                    'in_wh_status' => 3, //0-暂存 1-已收货 ,2-已质检 3-已上架 4-已出库 5-调拨中 6-冻结
                    'sale_status' => 1, //0-不可售 1-待售 ,2-已匹配销售单 3-已配货 4-已发货
                    'inv_status' => 5, //0-在仓 1-架上 2-可售 3-待上架 4-架上待确认 5-架上可售 6-架上锁定 7-待发 8-调拨 9-冻结
                    'lot_num' => $item[6],
                    'bar_code' => $item[5],
                    'location_code' => $item[12],
                    'quality_type' => $quality_type_map[$item[7]],
                    'quality_level' => $quality_level_map[$item[8]],
                    'recv_num' => $item[9],
                    'sup_id' => $sup_id,
                ];
                Inventory::updateOrCreate([
                    'warehouse_code' => $warehouse_code,
                    'area_code' => $area_code,
                    'uniq_code' => $item[0],
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                ], $tmp);
            }
            DB::commit();
            return $this->success();
        } catch (Exception $e) {
            DB::rollBack();
            // throw $e;
            Log::info($e->__toString());
            return $this->error($e->getMessage());
        }
    }

    // 用户导入
    function userImport(Request $request)
    {
        $data = Excel::toArray(new stdClass(), $request->file('file'))[0] ?? [];
        try {
            DB::beginTransaction();
            foreach ($data as $k => $item) {
                if ($k < 2) continue;
                AdminUsers::updateOrCreate([
                    'user_code' => $item[0],
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                ], [
                    'username' => $item[2],
                    'name' => $item[3],
                    'email' => $item[7] ?: $item[8] . '@carryme.com',
                    'mobile' => $item[8],
                    'status' => $item[9] == '启用' ? 1 : 2,
                    'roles_id' => 30,
                    'p_id' => ADMIN_INFO['user_id'],
                    'org_code' => ADMIN_INFO['org_code'],
                    'password' => Hash::make('carryme123456'),
                ]);
            }
            DB::commit();
            return $this->success();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 权限导入
    function roleImport(Request $request)
    {
        $data = Excel::toArray(new stdClass(), $request->file('file'))[0] ?? [];
        try {
            DB::beginTransaction();
            foreach ($data as $k => $item) {
                if ($k < 2) continue;
                Roles::updateOrCreate([
                    'role_code' => $item[0],
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                ], [
                    'name' => $item[2],
                    'display_name' => $item[2],
                    'org_code' => ADMIN_INFO['org_code'],
                    'type' => 3,
                ]);
            }
            DB::commit();
            return $this->success();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    // 总库存流水导入
    function stockLogImport(Request $request)
    {
        $data = Excel::toArray(new stdClass(), $request->file('file'))[0] ?? [];
    }
}
