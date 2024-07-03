<?php

namespace App\Models\Admin\V2;


use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;
use Illuminate\Support\Facades\DB;

class PurchaseStatements extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_purchase_order_statements'; //采购结算单

    protected $guarded = [];
    // protected $map = [
    //     'status' => [0=>'待结算',1=>'已结算'],
    // ];
    protected $map;

    // protected $casts = [
    //     'settled_time' => 'datetime:Y-m-d H:i:s',
    //     'order_at' => 'datetime:Y-m-d H:i:s',
    // ];
    protected $appends = ['status_txt', 'users_txt'];


    public function __construct($params=[])
    {
        parent::__construct($params);
        $this->map = [
            'status' => [0 => __('status.wait_statement'), 1 => __('status.statemented')],
        ];
    }

    public function searchUser(){
        return [
            'users_txt.settled_user' => 'settled_user',
            'users_txt.created_user' => 'created_user',
            'users_txt.updated_user' => 'updated_user',
            'users_txt.order_user' => 'order_user',
        ];
    }

    public function getUsersTxtAttribute($key)
    {
        $tenant_id = request()->header('tenant_id');
        $res['settled_user'] = $this->getAdminUser($this->settled_user, $tenant_id);
        $res['created_user'] = $this->getAdminUser($this->created_user, $tenant_id);
        $res['updated_user'] = $this->getAdminUser($this->updated_user, $tenant_id);
        $res['order_user'] = $this->getAdminUser($this->order_user, $tenant_id);
        return $res;
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'sup_id')->withDefault(['status' => 1]);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_code', 'warehouse_code');
    }
    public function withSearch($select)
    {
        $permission = ADMIN_INFO['data_permission'];
        $warehouse_codes = $permission['warehouse'] ?? [];
        $model = $this::with(['supplier', 'warehouse']);
        if ($warehouse_codes) {
            $model->whereIn('warehouse_code',$warehouse_codes);
        }
        $supplier2 = $permission['supplier2'] ?? [];
        if ($supplier2) {
            $model->whereIn('sup_id',$supplier2);
        }

        return $model->select($select);
    }

    public static function add($buy_code, $settle_amount)
    {
        $buy_order_item = PurchaseOrders::where('code', $buy_code)->first();
        if (empty($buy_order_item)) return [false, '采购单不存在'];
        $pay_status = $buy_order_item->pay_status;
        if($pay_status==1 && $settle_amount == 0)$settle_amount =  $buy_order_item->amount;
        $code = self::getErpCode('CGJSD');
        $create_data = [
            'code' => $code,
            'origin_code' => $buy_code,
            'order_user' => $buy_order_item->order_user,
            'order_at' => $buy_order_item->order_at,
            'sup_id' => $buy_order_item->sup_id,
            'warehouse_code' => $buy_order_item->warehouse_code,
            'num' => $buy_order_item->num,
            'amount' => $buy_order_item->amount,
            'settle_amount' => $settle_amount,
            'tenant_id' => $buy_order_item->tenant_id,
            'created_at' => date('Y-m-d H:i:s'),
            'created_user' => SYSTEM_ID,
        ];
        if ($pay_status) {
            $create_data['status'] = 1;
            $create_data['settle_amount'] = $buy_order_item->amount;
            $create_data['settled_time'] = date('Y-m-d H:i:s');
            $create_data['settled_user'] = request()->header('user_id');
        }
        $row = self::insert($create_data);
        return [$row, '新增失败'];
    }

    public  function settle($ids)
    {
        if(!is_array($ids))$ids = explode(',',$ids);
        $item = $this::whereIn('id',$ids )->where('status', 0);
        if ($item->doesntExist()) return [false, __('response.doc_status_settlement_err')];
        $purchase_code = $item->pluck('origin_code')->toArray() ;
        $update = [
            'status' => 1,
            'settled_amount' => DB::raw('settle_amount'),
            'settled_time' => date('Y-m-d H:i:s'),
            // 'settled_time' => time(),
            'settled_user' => request()->header('user_id'),
        ];
        $row = $item->update($update);
        //修改采购单的支付状态
        if($row)PurchaseOrders::whereIn('code',$purchase_code)->update(['pay_status'=>1]);
        return [$row, '结算失败'];
    }
}
