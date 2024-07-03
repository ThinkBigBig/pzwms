<?php

namespace App\Logics\wms;

use App\Logics\BaseLogic;
use App\Models\Admin\V2\WmsDataPermission;
use App\Models\Admin\V2\WmsShop;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Redis;

/**
 * 店铺
 */
class Shop extends BaseLogic
{

    function save($params)
    {
        $shop = null;
        if ($params['id'] ?? 0) {
            $shop = WmsShop::find($params['id']);
            if (!$shop) {
                $this->setErrorMsg(__('admin.wms.data.exception'));
                return false;
            }
        }
        $code = WmsShop::code();
        if ($params['code'] ?? '') {
            $shop = WmsShop::where('code', $params['code'])->first();
            $code = $params['code'];
        }
        if (!$shop) {
            $shop = WmsShop::create([
                'code' => $code,
                'status' => WmsShop::ON,
                'tenant_id' => ADMIN_INFO['tenant_id'],
                'create_user_id' => ADMIN_INFO['user_id'],
            ]);
            WmsDataPermission::addShop($shop);
        }

        $update = ['admin_user_id' => ADMIN_INFO['user_id'],];
        if ($params['name'] ?? '') $update['name'] = $params['name'];
        if ($params['manager_id'] ?? '') $update['manager_id'] = $params['manager_id'];
        if ($params['product_code'] ?? '') $update['product_code'] = $params['product_code'];
        if ($params['sale_channel'] ?? '') $update['sale_channel'] = $params['sale_channel'];
        if ($params['remark'] ?? '') $update['remark'] = $params['remark'];
        if (isset($params['status'])) $update['status'] = $params['status'];
        $shop->update($update);
        Redis::del('shop_map');
        return true;
    }

    // 删除
    function delete($id)
    {
        $shop = WmsShop::find($id);
        if (!$shop) {
            $this->setErrorMsg(__('admin.wms.data.exception'));
            return false;
        }
        $shop->delete();
        return true;
    }

    // 搜索
    function search($params, $export = false)
    {
        $model = new WmsShop();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            $permission = ADMIN_INFO['data_permission'];
            $shops = $permission['shop']??[];
            if($shops){
                $model = $model->whereIn('code',$shops);
            }
            $model = $model->with('manager')->orderBy('id', 'desc');
            return $model;
        });
        return $list;
    }

    // 详情
    function info($params)
    {
        $info = [];
        $info = WmsShop::with(['manager'])->find($params['id']);
        if ($info) {
            $info = $info->toArray();
            self::infoFormat($info);
        }
        return compact('info');
    }

    // 下拉框时展示的店铺/渠道
    static function selectShopOptions()
    {
        $arr =  WmsShop::where('status', WmsShop::ON)->select(['name', 'code'])->get()->toArray();
        $code = array_column($arr, 'code');
        $name = array_column($arr, 'name');
        return array_combine($code, $name);
    }

    function import($params)
    {
        $users = BaseLogic::adminUsers('username');
        $fail = [];
        $arr = [];
        // 数据检查
        foreach ($params[0] as $k => $item) {
            if ($k < 2) continue;
            if (empty($item[0]) && empty($item[1])) {
                $fail[] = sprintf(__('admin.excel.shop.empty'), $k);
                continue;
            }
            $arr[] = [
                'code' => $item[0],
                'name' => $item[1],
                'sale_channel' => array_flip(WmsShop::maps('sale_channel'))[$item[2]],
                'manager_id' => $users[$item[3]] ?? 0, //店铺负责人
                'status' => array_flip(WmsShop::maps('status'))[$item[4]],
                'remark' => $item[5],
            ];
        }
        if ($fail) {
            $this->setErrorMsg(implode(PHP_EOL, $fail));
            return false;
        }
        $fail = [];
        // 更新数据
        foreach ($arr as $item) {
            $res = $this->save($item);
            if (!$res) {
                $fail[] = sprintf(__('admin.excel.shop.add_fail'), $item['name'], $this->err_msg);
            }
        }

        if ($fail) {
            $this->setErrorMsg(implode(PHP_EOL, $fail));
            return false;
        }
        return true;
    }


    static function selectOptions()
    {
        return WmsShop::selectRaw('code as value,name as label')->get()->toArray();
    }
}
