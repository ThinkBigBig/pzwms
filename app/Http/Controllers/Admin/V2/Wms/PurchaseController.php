<?php

namespace App\Http\Controllers\Admin\V2\Wms;

use App\Http\Controllers\Admin\V1\BaseController;
use App\Logics\wms\Purchase;
use Illuminate\Http\Request;

/**
 * 采购
 */
class PurchaseController extends BaseController
{
    // 查询
    function summarySearch(Request $request)
    {
        $logic = new Purchase();
        $data = $logic->summarySearch($request->all());
        return $this->success($data);
    }

    // 导出
    function summaryExport(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new Purchase();
            $res = $logic->summarySearch($params, true);
            return $res;
        });
        // $params = $request->all();
        // $params['size'] = 10000;
        // $logic = new Purchase();
        // $res = $logic->summarySearch($params, true);
        // return $this->exportOutput($res,[]);
    }
}
