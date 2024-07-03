<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use  App\Models\Admin\V2\ArrivalRegist;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;


class ShippingDetail extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_shipping_detail'; //出库明细
    // protected $map = [
    //     'status' => [0=>'待发货',1=>'配货中' ,2=>'发货中', 3=>'已发货', 4=>'已取消'],
    // ];
    protected $map;
    protected $guarded = [];

    const ALLOCATING = 1; //配货中
    const SHIPPING = 2; //发货中
    const SHIPPED = 3; //已发货
    const CNACELED = 4; //已取消

    protected $appends = ['status_txt', 'users_txt', 'diff_num'];

    protected $with = ['product', 'supplier'];
    protected $fillable = ['request_code','sku','quality_type','quality_level','batch_no','uniq_code','status', 'ship_code', 'oversold_num', 'stockout_num', 'cancel_num', 'actual_num','third_no','tenant_id'];

    public function __construct($params=[])
    {
        parent::__construct($params);
        $this->map = [
            'status' => [0 => __('status.wait_send'), 1 => __('status.in_distribution'), 2 => __('status.shipping'), 3 => __('status.shipped'), 4 => __('status.canceled')],
        ];
    }

    public function getUsersTxtAttribute($key)
    {
        $tenant_id = request()->header('tenant_id');
        $res['shipper_user'] = $this->getAdminUser($this->shipper_id, $tenant_id);
        $res['admin_user_user'] = $this->getAdminUser($this->admin_user_id, $tenant_id);
        return $res;
    }

    function property()
    {
        return $this->hasOne(ProductSpecAndBar::class, 'bar_code', 'bar_code');
    }
    public  function product()
    {
        return $this->hasOne(ProductSpecAndBar::class, 'bar_code', 'bar_code')->withDefault([
            'sku' => '',
            'spec_one' => '',
            'product' => [
                'name' => '',
                'product_sn' => '',
                'img' => '',
            ]
        ]);
    }
    public function supplier()
    {
        return $this->hasOne(Supplier::class, 'id', 'sup_id')->withDefault();
    }

    public function getDiffNumAttribute($key)
    {
        return $this->re_total - $this->rd_total;
    }
}
