<?php

namespace App\Models\Admin\V2;


use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsPurchaseStatement extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $appends =['inv_type_txt'];
    protected $table = 'wms_purchase_statements';

    function columnOptions()
    {
        return [];
    }
    protected $map;
    public function __construct($params = [])
    {
        parent::__construct($params);
        $this->map = [
            'inv_type' => [
                0 => __('status.proprietary'),
                1 => __('status.consignment'),
            ],
        ];
    }
    function columns()
    {
        // statusOPtion
        // export=true 导出时展示 ，search=true 搜索查询时展示
        return [
            ['value' => 'buy_code', 'label' => '采购单据编码'],
            ['value' => 'audit_at', 'label' => '审核时间'],
            ['value' => 'sup_name', 'label' => '供应商'],
            ['value' => 'warehouse_name', 'label' => '仓库'],
            ['value' => 'sku', 'label' => 'SKU编码'],
            ['value' => 'product_sn', 'label' => '货号'],
            ['value' => 'name', 'label' => '品名'],
            ['value' => 'spec_one', 'label' => '规格'],
            ['value' => 'num', 'label' => '总数量'],
            ['value' => 'wait_num', 'label' => '待收总数'],
            ['value' => 'recv_num', 'label' => '已收总数'],
            ['value' => 'recv_rate', 'label' => '收货率'],
            ['value' => 'normal_num', 'label' => '正品数'],
            ['value' => 'flaw_num', 'label' => '瑕疵数'],
            ['value' => 'flaw_rate', 'label' => '瑕疵率'],
            ['value' => 'purchase_price', 'label' => '采购价'],
            ['value' => 'purchase_amount', 'label' => '采购总额'],
            ['value' => 'inv_type_txt', 'label' => '采购总额'],
        ];
    }
}
