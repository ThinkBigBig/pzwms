<?php

namespace App\Http\Controllers\Admin\V2\Wms;

use App\Exports\ExportView;
use App\Http\Controllers\Admin\V1\BaseController;
use App\Logics\wms\Warehouse;
use App\Models\Admin\V2\WmsWarehouseArea;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use stdClass;

/**
 * 库位信息
 */
class WarehouseController extends BaseController
{

    function __construct(Request $request)
    {
        parent::__construct($request);
        $this->model = new WmsWarehouseArea();
    }

    // 列表
    public function areaIndex(Request $request)
    {
        $logic = new Warehouse();
        $data = $logic->areaSearch($request->all());
        return $this->success($data);
    }

    // 保存
    public function areaStore(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'area_name' => 'required',
            'warehouse_code' => 'required',
            'status' => 'required|in:0,1',
            'type' => 'required|in:0,1,2,3',
            'purpose' => 'required|in:0,1,2,3',
        ]);
        $logic = new Warehouse();
        $logic->areaSave($params);
        return $this->output($logic, []);
    }

    // 详情
    public function areaShow($id)
    {
        $logic = new Warehouse();
        $data = $logic->areaDetail($id);
        return $this->output($logic, $data);
    }

    //删除
    function areaDelete(Request $request)
    {
        $this->validateParams($request->all(), [
            'ids' => 'required',
        ]);
        $ids = explode(',', $request->get('ids'));
        $logic = new Warehouse();
        $logic->areaDel($ids);
        return $this->success([]);
    }

    // 导入模板和导出使用的字段
    public $import_fileds = [
        'warehouse_code' => '仓库编码',
        'warehouse_name' => '所属仓库|required',
        'area_code' => '库区编码',
        'area_name' => '库区名称|required',
        'type_txt' => '库区类型|required',
        'purpose_txt' => '库区用途|required',
        'status_txt' => '启用状态',
        'remark' => '备注',
    ];
    // 导入导出文件名
    public $filename = '库区';

    // 导入
    function areaImport(Request $request)
    {
        $data = Excel::toArray(new stdClass(), $request->file('file'));
        $logic = new Warehouse();
        $res = $logic->areaImport($data);
        return $this->output($logic, $res);
    }

    // 导出
    function areaExport(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new Warehouse();
            $res = $logic->areaSearch($params, true);
            return $res;
        });
        // $params = $request->all();
        // $params['size'] = 1000;
        // $logic = new Warehouse();
        // $res = $logic->areaSearch($params, true);
        // return $this->exportOutput($res);
    }
}
