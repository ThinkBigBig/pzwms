<?php

namespace App\Http\Controllers\Admin\V2\Inbound;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Models\Admin\V2\UniqCodePrintLog;
use App\Models\Admin\V2\WmsOptionLog;


class UniqCodeController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\UniqCodePrintLog';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = []; //获取全部Where条件
    protected $BA  = ['*']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $BLWhere  = [
        'WHERE' => ['WHERE', ''],
        'NUMBER' => [['arr_code', 'uniq_code'], '',],
        'ORDER' => ['ORDER', ''],
    ]; //获取全部分页Where条件
    protected $BL  = ['*']; //获取全部分页选取字段 *是全部
    protected $BLOrder  = [['created_at', 'desc'],['id', 'desc'],]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序

    protected $NAME;
    protected $exportField;

    public function setExcelField()
    {
        $this->NAME = __('excel.uniq_record.title');
        $this->exportField = [
            'warehouse_name' => __('excel.warehouse_name'),
            'arr_code' => __('excel.uniq_record.arr_code'),
            'bar_code' => __('excel.uniq_record.bar_code'),
            'uniq_code' => __('excel.uniq_record.uniq_code'),
            'print_count' => __('excel.uniq_record.print_count'),
            'cre_user_name' => __('excel.uniq_record.cre_user_name'),
            'created_at' => __('excel.uniq_record.created_at'),
            'cre_user_name' => __('excel.uniq_record.cre_user_name'),
            'created_at' => __('excel.uniq_record.created_at'),
            'upd_user_name' => __('excel.uniq_record.upd_user_name'),
            'updated_at' => __('excel.uniq_record.updated_at'),
        ];
    }

    public function printOne(Request $request)
    {
        $arr_id = $request->get('arr_id');
        $uniq_code = $request->get('uniq_code');
        if (empty($arr_id) || empty($uniq_code)) return $this->vdtError();
        list($res, $msg) = $this->model->rePrint($arr_id, $uniq_code);
        if (!$res) return $this->error($msg);
        return $this->success($msg);
    }
}
