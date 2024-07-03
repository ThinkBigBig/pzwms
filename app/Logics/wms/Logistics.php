<?php

namespace App\Logics\wms;

use App\Logics\BaseLogic;
use App\Models\Admin\V2\WmsLogisticsCompany;
use App\Models\Admin\V2\WmsLogisticsProduct;

class Logistics extends BaseLogic
{

    function companySearch($params, $export = false)
    {
        $model = new WmsLogisticsCompany();
        return $this->_search($params, $model, $export, function ($model, $params) {
            $model = $model->orderBy('id', 'desc');
            return $model;
        });
    }

    function companySave($params)
    {
        $this->success = true;
        if (!in_array($params['short_name'], WmsLogisticsCompany::$short_names)) {
            $this->setErrorMsg(__('tips.simple_error'));
            return false;
        }
        $company_code = $params['company_code'] ?? '';
        if ($params['id'] ?? 0) {
            $company = WmsLogisticsCompany::where('id', $params['id'])->first();
            if (!$company) {
                $this->setErrorMsg(__('tips.doc_not_exists'));
                return false;
            }
            if ($company && ($params['company_code'] ?? '') && $company->company_code != $params['company_code']) {
                $this->setErrorMsg(__('tips.company_code_deny_edit'));
                return false;
            }
            $company_code = $company->company_code;
        } else {
            // 编码查重
            $company_code = $params['company_code'] ?? self::generateCompanyCode();
            $find = WmsLogisticsCompany::where('company_code', $company_code)->first();
            if ($find) {
                $this->setErrorMsg(__('tips.company_code_exist'));
                return false;
            }
        }

        $update = [
            'company_name' => $params['company_name'],
            'short_name' => $params['short_name'],
            'status' => $params['status'] ?? WmsLogisticsCompany::ACTIVE,
            'tenant_id' => ADMIN_INFO['tenant_id'],
            'admin_user_id' => ADMIN_INFO['user_id'],
        ];
        if ($params['contact_name'] ?? '') $update['contact_name'] = $params['contact_name'];
        if ($params['contact_phone'] ?? '') $update['contact_phone'] = $params['contact_phone'];
        if ($params['address'] ?? '') $update['address'] = $params['address'];
        if ($params['remark'] ?? '') $update['remark'] = $params['remark'];
        WmsLogisticsCompany::updateOrCreate(['company_code' => $company_code], $update);
        return true;
    }

    // 生成公司编码
    static function generateCompanyCode()
    {
        $code = WmsLogisticsCompany::withTrashed()->where('company_code', 'like', 'TS%')->orderBy('id', 'desc')->value('company_code');
        if (!$code) return 'TS001';
        $num = intval(substr($code, -3)) + 1;
        return 'TS' . str_pad($num, 3, "0", STR_PAD_LEFT);
    }

    function companyDetail($id)
    {
        $info = WmsLogisticsCompany::where('id', $id)->where('deleted_at', null)->first();
        return [
            'info' => $info ?: [],
            'short_names' => WmsLogisticsCompany::$short_names,
        ];
    }

    function companyDel(array $ids)
    {
        WmsLogisticsCompany::whereIn('id', $ids)->where('deleted_at', null)->update([
            'admin_user_id' => ADMIN_INFO['user_id'],
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);
        return true;
    }


    // 生成物流产品编码
    static function generateProductCode()
    {
        $code = WmsLogisticsProduct::withTrashed()->where('product_code', 'like', 'EP%')->orderBy('id', 'desc')->value('product_code');
        if (!$code) return 'EP001';
        $num = intval(substr($code, -3)) + 1;
        return 'EP' . str_pad($num, 3, "0", STR_PAD_LEFT);
    }

    function productSearch($params, $export = false)
    {
        $model = new WmsLogisticsProduct();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            $model = $model->with('company')->orderBy('id', 'desc');
            return $model;
        });
        foreach ($list['data'] as &$item) {
            $item['company_name'] = $item['company']['company_name'];
            $item['tender_name'] = '';
        }
        return $list;
    }

    function productSave($params)
    {
        $this->success = true;
        $product_code = $params['product_code'] ?? '';
        $status = $params['status'] ?? WmsLogisticsProduct::ACTIVE;
        $company = WmsLogisticsCompany::where('company_code', $params['company_code'])->first();
        if (!$company) {
            $this->setErrorMsg(__('tips.company_not_exist'));
            return false;
        }
        if ($status == WmsLogisticsProduct::ACTIVE) {
            if ($company->status != WmsLogisticsCompany::ACTIVE) {
                $this->setErrorMsg(__('tips.company_disable'));
                return false;
            }
        }

        if (!(WmsLogisticsProduct::maps('payment')[$params['payment']] ?? '')) {
            $this->setErrorMsg(__('tips.payment_not_exist'));
            return false;
        }
        if (!(WmsLogisticsProduct::maps('pickup_method')[$params['pickup_method']] ?? '')) {
            $this->setErrorMsg(__('tips.pickup_not_exist'));
            return false;
        }

        if ($params['id'] ?? 0) {
            $product = WmsLogisticsProduct::where('id', $params['id'])->first();
            if ((!$product) || $product->deleted_at) {
                $this->setErrorMsg(__('tips.product_not_exist'));
                return false;
            }
            if ($product && ($params['product_code'] ?? '') && $product->product_code != $params['product_code']) {
                $this->setErrorMsg(__('tips.product_code_deny_edit'));
                return false;
            }
            $product_code = $product->product_code;
        } else {
            // 编码查重
            $product_code = ($params['product_code'] ?? '') ? $params['product_code'] : self::generateProductCode();
            $find = WmsLogisticsProduct::where('product_code', $product_code)->first();
            if ($find) {
                $this->setErrorMsg(__('tips.product_code_exist'));
                return false;
            }
        }

        WmsLogisticsProduct::updateOrCreate([
            'product_code' => $product_code,
        ], [
            'product_name' => $params['product_name'],
            'company_code' => $params['company_code'],
            'status' => $status,
            'pickup_method' => $params['pickup_method'],
            'payment' => $params['payment'],
            'remark' => $params['remark'],
            'tenant_id' => ADMIN_INFO['tenant_id'],
            'admin_user_id' => ADMIN_INFO['user_id'],
        ]);
        return true;
    }

    function productDetail($id)
    {
        $info = WmsLogisticsProduct::where('id', $id)->first();
        return [
            'info' => $info ?: [],
            'pickup_methods' => WmsLogisticsProduct::maps('pickup_method'),
            'payments' => WmsLogisticsProduct::maps('payment'),
            'status_maps' => WmsLogisticsProduct::maps('status'),
        ];
    }

    function productDel(array $ids)
    {
        WmsLogisticsProduct::whereIn('id', $ids)->update([
            'admin_user_id' => ADMIN_INFO['user_id'],
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    function companyAll()
    {
        $select = ['id', 'company_name', 'company_code', 'short_name', 'status'];
        $data = [];
        $model = new WmsLogisticsCompany();

        $list = $model::with(['product' => function ($qurey) {
            $qurey->where('status', 1);
        }])->select($select)->orderBy('created_at', 'desc')->where('status', 1)->get()->toArray();
        foreach ($list as $company) {
            if ($company['product']) {
                foreach ($company['product'] as $product) {
                    $temp = [
                        'company_name' => $company['company_name'],
                        'company_code' => $company['company_code'],
                        'short_name' => $company['short_name'],
                        'product_code' => $product['product_code'],
                        'product_name' => $product['product_name'],
                        'payment' => $product['payment'],
                        'payment_txt' => $product['payment_txt'],
                        'pickup_method' => $product['pickup_method'],
                        'pickup_method_txt' => $product['pickup_method_txt'],
                    ];
                    $data[] = $temp;
                }
            }
        }
        return $data;
    }
}
