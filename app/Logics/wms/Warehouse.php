<?php

namespace App\Logics\wms;

use App\Logics\BaseLogic;
use App\Models\Admin\V2\Warehouse as V2Warehouse;
use App\Models\Admin\V2\WmsWarehouseArea;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Warehouse extends BaseLogic
{
    function areaSearch($params, $export = false)
    {
        $model = new WmsWarehouseArea();
        return  $this->_search($params, $model, $export, function ($model, $params) {
            $permission = ADMIN_INFO['data_permission'];
            $warehouse_codes = $permission['warehouse'] ?? [];
            if ($warehouse_codes) {
                $model = $model->whereIn('warehouse_code', $warehouse_codes);
            }
            $model = $model->with('warehouse')->orderBy('id', 'desc');
            return $model;
        });
    }

    function areaSave($params)
    {
        $this->success = true;
        $area_code = $params['area_code'] ?? '';
        $status = $params['status'] ?? WmsWarehouseArea::ACTIVE;
        $check_warehouse = $params['check_warehouse'] ?? true;
        if ($check_warehouse) {
            $warehouse = V2Warehouse::where('warehouse_code', $params['warehouse_code'])->where('status', 1)->first();
            if (!$warehouse) {
                $this->setErrorMsg(__('admin.wms.warehouse.exception'));
                return false;
            }
        }


        if ($params['id'] ?? 0) {
            $area = WmsWarehouseArea::where('id', $params['id'])->first();
            if ((!$area) || $area->deleted_at) {
                $this->setErrorMsg(__('admin.wms.warehouse_area.exception'));
                return false;
            }
            if ($area && ($params['area_code'] ?? '') && $area->area_code != $params['area_code']) {
                $this->setErrorMsg(__('admin.wms.warehouse_area.can_not_change'));
                return false;
            }
        } else {
            // 编码查重
            $area_code = $params['area_code'] ?? WmsWarehouseArea::code();
            $find = WmsWarehouseArea::where('area_code', $area_code)->where('warehouse_code', $params['warehouse_code'])->first();
            if ($find) {
                $this->setErrorMsg(__('admin.wms.warehouse_area.repeat'));
                return false;
            }
        }

        WmsWarehouseArea::updateOrCreate([
            'warehouse_code' => $params['warehouse_code'],
            'area_code' => $area_code,
        ], [
            'area_name' => $params['area_name'],
            'type' => $params['type'],
            'purpose' => $params['purpose'],
            'status' => $status,
            'notes' => $params['notes'] ?? '',
            'remark' => $params['remark'] ?? '',
            'tenant_id' => ADMIN_INFO['tenant_id'],
            'admin_user_id' => ADMIN_INFO['user_id'],
        ]);
        return true;
    }

    // 生成库区编码
    static function generateAreaCode($name)
    {
        $code = getWholeCharter($name) . rand(100, 999);
        $find = WmsWarehouseArea::where('area_code', $code)->first();
        if ($find) {
            return self::generateAreaCode($name);
        }
        return $code;
    }

    function areaDetail($id)
    {
        $info = WmsWarehouseArea::where('id', $id)->where('deleted_at', null)->first();
        $warehouse = V2Warehouse::where('deleted_at', null)->get();
        $purposes = WmsWarehouseArea::maps('purpose');
        $pro = [];
        foreach ($purposes as $va => $name) {
            $pro[] = [
                'value' => $va,
                'name' => $name,
                'selectable' => in_array($va, WmsWarehouseArea::$unselect_purpoese) ? 0 : 1,
            ];
        }
        $types = WmsWarehouseArea::maps('type');
        $ty = [];
        foreach ($types as $va => $name) {
            $ty[] = [
                'value' => $va,
                'name' => $name,
                'selectable' => in_array($va, WmsWarehouseArea::$unselect_types) ? 0 : 1,
            ];
        }
        return [
            'info' => $info ?: [],
            'types' => $ty,
            'purposes' => $pro,
            'tags' => [],
            'warehouse' => $warehouse
        ];
    }

    function areaDel(array $ids)
    {
        WmsWarehouseArea::whereIn('id', $ids)->where('deleted_at', null)->update([
            'admin_user_id' => ADMIN_INFO['user_id'],
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    // 库区导入
    function areaImport($data)
    {
        $warhouse = self::warehouseKeyByName();
        $fail = [];
        // 数据检查
        foreach ($data[0] as $k => $item) {
            if ($k < 2) continue;
            if (empty($item[0]) && empty($item[1])) {
                $fail[] = sprintf(__('admin.excel.warehouse_area.warehouse_empty'), $k);
                continue;
            }
            $warehouse_code = $item[0];
            if (!$item[0]) {
                $warehouse_code = $warhouse[$item[1]] ?? '';
            }
            if (!$warehouse_code) {
                $fail[] = sprintf(__('admin.excel.warehouse_area.warehouse_not_found'), $k);
                continue;
            }
            $arr[] = [
                'warehouse_code' => $warehouse_code,
                'warehouse_name' => $item[1],
                'area_code' => $item[2],
                'area_name' => $item[3],
                'type' => array_flip(WmsWarehouseArea::maps('type'))[$item[4]],
                'purpose' => array_flip(WmsWarehouseArea::maps('purpose'))[$item[5]],
                'status' => array_flip(WmsWarehouseArea::maps('status'))[$item[6]],
                'remark' => $item[7],
                'check_warehouse' => false,
            ];
        }
        if ($fail) {
            $this->setErrorMsg(implode(PHP_EOL, $fail));
            return false;
        }
        $fail = [];
        // 更新数据
        foreach ($arr as $item) {
            $res = $this->areaSave($item);
            if (!$res) {
                $fail[] = sprintf(__('admin.excel.warehouse_area.add_fail'), $item['area_name'], $this->err_msg);
            }
        }

        if ($fail) {
            $this->setErrorMsg(implode(PHP_EOL, $fail));
            return false;
        }
        return true;
    }

    // 根据仓库编码获取仓库名称
    static function name($code)
    {
        $name = V2Warehouse::where('warehouse_code', $code)->value('warehouse_name');
        return $name ?: '';
    }

    static function areaName($code, $warehouse_code)
    {
        $name = WmsWarehouseArea::where(['warehouse_code' => $warehouse_code, 'area_code' => $code])->value('area_name');
        return $name ?: '';
    }

    // 获取仓库列表，key为仓库名称
    static function warehouseKeyByName()
    {
        return V2Warehouse::get()->keyBy('warehouse_name')->toArray();
    }

    static function warehouseKeyBy($name = 'warehouse_code')
    {
        $tenant_id = request()->header('tenant_id');
        $data = DB::select("select * from wms_warehouse where tenant_id=" . $tenant_id);
        $data = objectToArray($data);
        $keys = array_column($data, $name);
        return array_combine($keys, $data);

        // return V2Warehouse::get()->keyBy($name)->toArray();
    }

    function warehouseSearch($params = [])
    {
        $where = ['status' => 1];
        if ($params['warehouse_name'] ?? '') {
            $where['warehouse_name'] = $params['warehouse_name'];
        }
        if ($params['warehouse_code'] ?? '') {
            $where['warehouse_code'] = $params['warehouse_code'];
        }
        return V2Warehouse::where($where)->orderBy('id', 'desc')->select(['id', 'warehouse_code', 'warehouse_name'])->get()->toArray();
    }
}
