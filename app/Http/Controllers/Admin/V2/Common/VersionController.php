<?php

namespace App\Http\Controllers\Admin\V2\Common;

use App\Http\Controllers\Admin\V2\BaseController;
use App\Logics\BaseLogic;
use App\Logics\wms\Suppiler;
use App\Models\Admin\V2\WmsSupplierDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class VersionController extends BaseController
{
    protected $BaseModels = 'App\Models\Admin\V2\Version';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = []; //获取全部Where条件
    protected $BA  = ['*']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $BLWhere  = [
        'download_link' => ['like', ''],
        'WHERE' => ['WHERE', ''],
    ]; //获取全部分页Where条件
    protected $BL  = ['*']; //获取全部分页选取字段 *是全部
    protected $BLOrder  = [ ['created_at', 'desc'], ['id', 'desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序

    protected $BaseCreateVat = [
        'version_num' => 'required', //分类名称
    ]; //新增验证
    protected $BaseCreate = [
        'port' => '',
        'type' => '',
        'check' => '',
        'version_num' => '',
        'download_link' => '',
        'note_jp' => '',
        'note_zh' => '',
        'status' => '',
        'remark' => '',
        'version_at' => '',
        'created_at' => ['type', 'date'],
    ]; //新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
    ]; //新增验证
    protected $BaseUpdate = [
        'port' => '',
        'type' => '',
        'check' => '',
        'version_num' => '',
        'download_link' => '',
        'note_jp' => '',
        'note_zh' => '',
        'status' => '',
        'remark' => '',
        'version_at' => '',
        'updated_at' => ['type', 'date'],
    ]; //新增数据
    protected $BUWhere = ['id' => ['=', '']]; //新增数据
}
