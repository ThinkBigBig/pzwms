<?php

namespace App\Http\Controllers\Admin\V2\Wms;

use App\Exports\ExportSheets;
use App\Exports\ExportView;
use App\Http\Controllers\Admin\V1\BaseController;
use App\Logics\wms\Stock;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * 库存
 */
class StockController extends BaseController
{

    // 总库存流水查询
    function logSearch(Request $request)
    {
        $logic = new Stock();
        $data = $logic->logSearch($request->all());
        return $this->success($data);
    }

    // 总库存流水导出
    function logExport(Request $request)
    {
        $this->_export($request,function($params){
            $logic = new Stock();
            return $logic->logSearch($params, true);
        });
    }

    //总流水类型
    function logType(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'code' => 'required',
        ]);
        $logic = new Stock();
        $data = $logic->logType($params);
        return $this->success($data);
    }

}
