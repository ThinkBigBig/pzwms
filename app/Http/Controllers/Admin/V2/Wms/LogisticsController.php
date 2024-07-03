<?php

namespace App\Http\Controllers\Admin\V2\Wms;

use App\Exports\ExportSheets;
use App\Exports\ExportView;
use App\Http\Controllers\Admin\V1\BaseController;
use App\Logics\wms\Logistics;
use App\Models\Admin\V2\WmsLogisticsCompany;
use App\Models\Admin\V2\WmsLogisticsProduct;
use App\Models\Organization;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use stdClass;

/**
 * 物流
 * - 物流公司管理
 * - 物流产品管理
 */
class LogisticsController extends BaseController
{
    // 列表
    public function companyIndex(Request $request)
    {
        $logic = new Logistics();
        $data = $logic->companySearch($request->all());
        return $this->success($data);
    }

    // 保存
    public function companyStore(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'company_name' => 'required',
            'status' => 'required|in:' . implode(',', WmsLogisticsCompany::$status),
            'short_name' => 'required',
        ]);
        $logic = new Logistics();
        $logic->companySave($params);
        return $this->output($logic, []);
    }

    // 详情
    public function companyShow($id)
    {
        $logic = new Logistics();
        $data = $logic->companyDetail($id);
        return $this->output($logic, $data);
    }


    //删除
    function companyDelete(Request $request)
    {
        $this->validateParams($request->all(), [
            'ids' => 'required',
        ]);
        $ids = explode(',', $request->get('ids'));
        $logic = new Logistics();
        $logic->companyDel($ids);
        return $this->success([]);
    }

    // 导入
    function companyImport(Request $request)
    {
        $data = Excel::toArray(new stdClass(), $request->file('file'));
        $fail = [];
        $logic = new Logistics();
        foreach ($data[0] as $k => $item) {
            if ($k < 2) continue;
            $res = $logic->companySave([
                'company_code' => $item[0],
                'company_name' => $item[1],
                'short_name' => $item[2],
                'contact_name' => $item[3],
                'contact_phone' => $item[4],
                'address' => $item[5],
                'remark' => $item[6],
            ]);
            if (!$res) {
                $fail[$item[1]] = $logic->err_msg;
            }
        }
        $msg = '成功';
        if ($fail) {
            $msg = sprintf('失败%d条', count($fail));
        }
        return $this->success([], $msg);
    }

    // 导出
    function companyExport(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new Logistics();
            $res = $logic->companySearch($params, true);
            return $res;
        });
        // $params = $request->all();
        // $params['size'] = 1000;
        // $logic = new Logistics();
        // $res = $logic->companySearch($params, true);
        // return $this->exportOutput($res);
    }


    // 导入模板
    function companyTemplate(Request $request)
    {
        return $this->templateOutput(new WmsLogisticsCompany());
    }


    // 列表
    public function productIndex(Request $request)
    {
        $logic = new Logistics();
        $data = $logic->productSearch($request->all());
        return $this->success($data);
    }

    // 保存
    public function productStore(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'product_name' => 'required',
            'company_code' => 'required',
            'pickup_method' => 'required|in:1,2,3,4,5',
            'payment' => 'required|in:1,2,3,4',
        ]);
        $logic = new Logistics();
        $logic->productSave($params);
        return $this->output($logic, []);
    }

    // 详情
    public function productShow($id)
    {
        $logic = new Logistics();
        $data = $logic->productDetail($id);
        return $this->output($logic, $data);
    }


    //删除
    function productDelete(Request $request)
    {
        $this->validateParams($request->all(), [
            'ids' => 'required',
        ]);
        $ids = explode(',', $request->get('ids'));
        $logic = new Logistics();
        $logic->productDel($ids);
        return $this->success([]);
    }

    // 导入
    function productImport(Request $request)
    {
        $data = Excel::toArray(new stdClass(), $request->file('file'));
        $fail = [];

        $pickup_methods = array_flip(WmsLogisticsProduct::maps('pickup_method'));
        $payments = array_flip(WmsLogisticsProduct::maps('payment'));
        $companys = WmsLogisticsCompany::select(['id', 'company_code', 'company_name', 'short_name'])->where('status', WmsLogisticsCompany::ACTIVE)->get()->keyBy('company_name');
        $logic = new Logistics();
        foreach ($data[0] as $k => $item) {
            if ($k < 2) continue;
            $pickup_method = $pickup_methods[$item[2]] ?? '';
            if (!$pickup_method) {
                $fail[$k] = __('tips.pickup_not_exist');
                continue;
            }
            $payment = $payments[$item[4]] ?? '';
            if (!$payment) {
                $fail[$k] = __('tips.payment_not_exist');
                continue;
            }
            // $tenant_id = Organization::where('name', $item[7])->value('tenant_id');
            // if (!$tenant_id) {
            //     $fail[$k] = '所属租户不存在';
            //     continue;
            // }
            $status = $item[5] == '启用' ? WmsLogisticsProduct::ACTIVE : WmsLogisticsProduct::INACTIVE;
            $company_code = $companys[$item[3]]['company_code'] ?? '';
            if (!$company_code) {
                $fail[$k] = '物流公司不存在';
                continue;
            }
            $res = $logic->productSave([
                'product_code' => $item[0],
                'product_name' => $item[1],
                'pickup_method' => $pickup_method,
                'company_code' => $company_code,
                'payment' => $payment,
                'status' => $status,
                'remark' => $item[6],
                'tenant_id' => ADMIN_INFO['tenant_id'],
            ]);
            if (!$res) {
                $fail[$item[1]] = $logic->err_msg;
            }
        }
        $msg = '成功';
        if ($fail) {
            $msg = sprintf('失败%d条 %s', count($fail), json_encode($fail));
        }
        return $this->success([], $msg);
    }

    // 导出
    function productExport(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new Logistics();
            $res = $logic->productSearch($params, true);
            return $res;
        });
        // $params = $request->all();
        // $params['size'] = 1000;
        // $logic = new Logistics();
        // $res = $logic->productSearch($params, true);
        // return $this->exportOutput($res, []);
    }

    // 导入模板
    function productTemplate(Request $request)
    {
        return $this->templateOutput(new WmsLogisticsProduct());
    }

    // 所有数据
    public function companyAll(Request $request)
    {
        $logic = new Logistics();
        $data = $logic->companyAll();
        return $this->success($data);
    }
}
