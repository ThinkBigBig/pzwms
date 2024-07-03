<?php

namespace App\Models\Admin\V2;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsProductStockLog extends Model
{
    use HasFactory;
    protected $guarded = [];
    public $table = 'wms_product_stock_logs';

    protected $appends = [];

    const ON = 1;
    const OFF = 0;

    static function maps($attr)
    {
        $maps = [
            'node' => [
                '1' => '确认到货',
                '2' => '提交出库',
                '3' => '撤销出库',
                '4' => '出库预配',
                '5' => '取消出库',
                '6' => '确认发货',
                '7' => '缺货冻结',
                '8' => '库存调入',
                '9' => '库存调出',
                '10' => '提交调整单',
                '11' => '撤销调整单',
                '12' => '供应商调入',
                '13' => '供应商调出',
                '14' => '质量类型调入',
                '15' => '质量类型调出',
                '16' => '取消出库',
                '17' => '核销出库',
                '18' => '撤回调整单',
            ],
            'origin_type' => [
                '1' => '采购入库单',
                '2' => '寄卖单',
                '3' => '采购单',
                '4' => '寄卖入库单',
                '5' => '普通调拨入库单',
                '6' => '直接调拨入库单',
                '7' => '退货入库',
                '8' => '其他入库单',
                '9' => '普通调拨出库单',
                '10' => '直接调拨出库单',
                '11' => 'B2B出库单',
                '12' => '其他出库单',
                '13' => '采购退货出库单',
                '14' => '一般交易出库单',
                '15' => '换货出库单',
                '16' => '普通出库单',
                '17' => '寄卖召回单',
                '18' => '采购退货单',
                '19' => '销售订单',
                '20' => '售后工单',
                '21' => '普通调拨单',
                '22' => '库存类型调整单',
                '23' => '供应商调整单',
                '24' => '质量类型调整单',
                '25' => '寄卖代发单',
                '26' => '其它出库单',
                '27' => '其他入库申请单',
                '28' => '其他出库申请单',
                '29' => '直接调拨单',
                '30' => '调拨申请单',
                '31' => '其它入库',
                '32' => '其他入库',
                '33' => '其它入库单',
                '34' => '其他出库单',
                '35' => '其它出库单',
                '36' => '其它入库申请单',
                '37' => '其它出库申请单',
            ],
            'inv_category' => [
                '1' => '可售库存',
                '2' => '锁定库存',
                '3' => '待发库存',
                '4' => '冻结库存',
                '5' => '在仓库存',
            ],
            'inv_type'=>[
                '0'=>'自营',
                '1'=>'寄卖',
            ],
        ];
        return $maps[$attr];
    }


    public $requiredColumns = [];
    function columns()
    {
        // export=true 导出时展示 ， search=true 搜索查询时展示
        return [
            ['value' => 'name', 'label' => '系列名称'],
            ['value' => 'brand_code', 'label' => '品牌', 'export' => false,],
            ['value' => 'brand_name', 'label' => '品牌', 'search' => false,],
            ['value' => 'status', 'label' => '状态', 'export' => false,],
            ['value' => 'status_txt', 'label' => '状态', 'search' => false,],
            ['value' => 'sort', 'label' => '排序'],
            ['value' => 'remark', 'label' => '备注'],
        ];
    }
}
