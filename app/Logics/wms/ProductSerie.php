<?php

namespace App\Logics\wms;

use App\Logics\BaseLogic;
use App\Models\Admin\V2\ProductBrands;
use App\Models\Admin\V2\WmsProductSerie;
use App\Models\Admin\V2\WmsShop;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Log;

/**
 * 商品系列
 */
class ProductSerie extends BaseLogic
{

    function save($params)
    {
        $serie = null;
        $brand = ProductBrands::where('code', $params['brand_code'])->first();
        if (!$brand) {
            $this->setErrorMsg(__('tips.brand_not_exist'));
            return false;
        }

        $model = WmsProductSerie::where('name', $params['name'])->where('brand_code', $params['brand_code']);
        if ($params['id'] ?? 0) {
            $model->where('id', '<>', $params['id']);
        }
        $find = $model->first();
        if ($find) {
            $this->setErrorMsg(__('tips.serie_repeat'));
            return false;
        }


        if ($params['id'] ?? 0) {
            $serie = WmsProductSerie::find($params['id']);
            if (!$serie) {
                $this->setErrorMsg(__('tips.doc_not_exists'));
                return false;
            }
        }

        // if ($params['name'] ?? '') {
        //     $serie = WmsProductSerie::where('name', $params['name'])->where('brand_code', $params['brand_code'])->first();
        // }
        if (!$serie) {
            $serie = WmsProductSerie::create([
                'code' => WmsProductSerie::code(),
                'name' => $params['name'],
                'brand_code' => $params['brand_code'],
                'status' => WmsProductSerie::ON,
                'tenant_id' => ADMIN_INFO['tenant_id'],
                'created_user' => ADMIN_INFO['user_id'],
            ]);
        }

        $update = [
            'updated_user' => ADMIN_INFO['user_id'],
            'sort' => $params['sort'] ?? 0,
        ];
        if ($params['name'] ?? '') $update['name'] = $params['name'];
        if ($params['remark'] ?? '') $update['remark'] = $params['remark'];
        if (isset($params['status'])) $update['status'] = $params['status'];
        $serie->update($update);
        return true;
    }

    function updateStatus($params)
    {
        WmsProductSerie::where('id', $params['id'])->update([
            'status' => $params['status'],
            'updated_user' => ADMIN_INFO['user_id'],
        ]);
        return true;
    }

    // 删除
    function delete($params)
    {
        if(is_string($params['ids']??'')) $params['ids'] = explode(',',$params['ids']);
        WmsProductSerie::whereIn('id', $params['ids'])->delete();
        return true;
    }

    // 搜索
    function search($params, $export = false)
    {
        $model = new WmsProductSerie();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            return $model->with('brand')->orderBy('sort', 'desc')->orderBy('id', 'desc');
        });
        return $list;
    }

    // 详情
    function info($params)
    {
        $info = [];
        $info = WmsProductSerie::find($params['id']);
        if ($info) {
            $info = $info->toArray();
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
        $brands = ProductBrands::get()->keyBy('name')->toArray();
        $fail = [];
        // 数据检查
        foreach ($params[0] as $k => $item) {
            if ($k < 2) continue;
            if (empty($item[0]) && empty($item[1])) {
                $fail[] = sprintf(__('tips.serie_brand_name_empty'), $k);
                continue;
            }
            $brand_code = $brands[$item[1]]['code'] ?? '';
            if (!$brand_code) {
                $fail[] = sprintf(__('tips.brand_not_exist_num'), $k);
                continue;
            }
            $arr[] = [
                'name' => $item[0],
                'brand_code' => $brands[$item[1]]['code'],
                'status' => array_flip(WmsProductSerie::maps('status'))[$item[2]],
                'remark' => $item[3],
            ];
        }
        if ($fail) {
            $this->setErrorMsg(implode('<br>', $fail));
            return false;
        }
        Log::info($arr);
        $fail = [];
        // 更新数据
        foreach ($arr as $item) {
            $res = $this->save($item);
            if (!$res) {
                $fail[] = sprintf(__('tips.add_fail_reason'), $item['name'], $this->err_msg);
            }
        }

        if ($fail) {
            $this->setErrorMsg(implode('<br>', $fail));
            return false;
        }
        return true;
    }
}
