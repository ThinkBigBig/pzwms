<?php

namespace App\Models\Admin\V2;

use App\Logics\BaseLogic;
use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WmsLogisticsProduct extends wmsBaseModel
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];
    protected $table = 'wms_logistics_products';

    const ACTIVE = 1;
    const INACTIVE = 0;

    static function maps($attr)
    {
        $maps = [
            'status' => [
                self::ACTIVE => __('admin.wms.status.open'), //'开启',
                self::INACTIVE => __('admin.wms.status.close'), //'关闭',
            ],
            'payment' => [
                1 => __('admin.wms.pay.month'), //'月付',
                2 => __('admin.wms.pay.cash'), //'现结',
                3 => __('admin.wms.pay.collect'), //'到付',
                4 => __('admin.wms.pay.other'), //'其他',
            ],
            'pickup_method' => [
                1 => __('admin.wms.deliver.take_their'), //'自提',
                2 => __('admin.wms.deliver.third'), //'第三方物流',
                3 => __('admin.wms.deliver.express'), //'快递',
                4 => __('admin.wms.deliver.trunk'), //'干线物流',
                5 => __('admin.wms.deliver.other'), //'其他',
            ],
        ];
        return $maps[$attr];
    }

    protected $appends = ['status_txt', 'pickup_method_txt', 'payment_txt'];

    //赋值
    public function getStatusTxtAttribute(): string
    {
        return self::maps('status')[$this->status] ?? '';
    }
    public function getPickupMethodTxtAttribute(): string
    {
        return self::maps('pickup_method')[$this->pickup_method] ?? '';
    }
    public function getPaymentTxtAttribute(): string
    {
        return self::maps('payment')[$this->payment] ?? '';
    }

    function company()
    {
        return $this->hasOne(WmsLogisticsCompany::class, 'company_code', 'company_code');
    }


    function columnOptions()
    {
        return [
            'company_code' => BaseLogic::companyOptions(),
            'pickup_method' => self::cloumnOptions(self::maps('pickup_method')),
            'status' => self::cloumnOptions(self::maps('status')),
            'payment' => self::cloumnOptions(self::maps('payment')),
        ];
    }

    public $requiredColumns = [];

    function columns()
    {
        // export=true 导出时展示 ， search=true 搜索查询时展示
        return [
            ['value' => 'product_name', 'label' => '编码'],
            ['value' => 'product_code', 'label' => '名称'],
            ['value' => 'pickup_method', 'label' => '提货方式', 'export' => false],
            ['value' => 'pickup_method_txt', 'label' => '提货方式', 'search' => false],
            ['value' => 'company_code', 'label' => '物流公司'],
            ['value' => 'payment', 'label' => '结算方式', 'export' => false],
            ['value' => 'payment_txt', 'label' => '结算方式', 'search' => false],
            ['value' => 'status', 'label' => '是否启用', 'export' => false],
            ['value' => 'status_txt', 'label' => '状态', 'search' => false],
            ['value' => 'remark', 'label' => '备注'],
        ];
    }

    static function getProduct($pickup_method_txt, $product_name, $tenant_id = 0)
    {
        if(!$pickup_method_txt || !$product_name) return null;
        $map = array_flip(self::maps('pickup_method'));
        $where = ['product_name' => $product_name, 'pickup_method' => $map[$pickup_method_txt]];
        if ($tenant_id) $where['tenant_id'] = $tenant_id;
        return self::where($where)->first();
    }
}
