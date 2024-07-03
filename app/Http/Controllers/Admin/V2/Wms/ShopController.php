<?php

namespace App\Http\Controllers\Admin\V2\Wms;

use App\Exports\ExportSheets;
use App\Exports\ExportView;
use App\Http\Controllers\Admin\V1\BaseController;
use App\Logics\wms\Shop;
use App\Models\Admin\V2\WmsShop;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use stdClass;

/**
 * 店铺
 */
class ShopController extends BaseController
{

    function __construct(Request $request)
    {
        parent::__construct($request);
        $this->model = new WmsShop();
    }

    // 新增/保存
    function save(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'name' => 'required',
            'sale_channel' => 'required|in:1,2',
        ]);
        $logic = new Shop();
        $data = $logic->save($params);
        return $this->output($logic, $data);
    }

    // 状态修改
    function status(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
            'status' => 'required|in:0,1',
        ]);
        $logic = new Shop();
        $data = $logic->save($params);
        return $this->output($logic, $data);
    }

    // 详情
    function info(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
        ]);
        $logic = new Shop();
        $data = $logic->info($params);
        return $this->output($logic, $data);
    }

    // 删除
    function delete($id)
    {
        $logic = new Shop();
        $data = $logic->delete($id);
        return $this->output($logic, $data);
    }

    // 查询
    function search(Request $request)
    {
        $logic = new Shop();
        $data = $logic->search($request->all());
        return $this->success($data);
    }

    // 导入
    function import(Request $request)
    {
        $data = Excel::toArray(new stdClass(), $request->file('file'));

        $logic = new Shop();
        $logic->import($data);
        return $this->output($logic, []);
    }

    // 导出
    function export(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new Shop();
            $res = $logic->search($params, true);
            return $res;
        });
        // $params = $request->all();
        // $params['size'] = 1000;
        // $logic = new Shop();
        // $res = $logic->search($params, true);
        // return $this->exportOutput($res);
    }
}
