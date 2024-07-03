<?php

namespace App\Models\Admin\V2;


use App\Models\Admin\wmsBaseModel;
use App\Models\AdminUsers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Redis;

class Supplier extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_supplier';
    public $unique_field = ['sup_code'];
    // protected $map = [
    //     'status'=>[1=>'启用' ,0=>'禁用' ],
    //     'sup_status'=>[1=>'暂存' ,2=>'已通过' ],
    //     'type'=>[1=>'个人',2=>'公司'],
    // ];
    protected $map;
    protected $appends = ['status_txt', 'sup_status_txt', 'type_txt', 'approver_txt'];

    protected $adminUserIns = null;


    public function __construct($params=[])
    {
        parent::__construct($params);
        $this->map = [
            'status' => [1 => __('status.enable'), 0 => __('status.disabled')],
            'sup_status' => [1 => __('status.stash'), 2 => __('status.pass')],
            'type' => [1 => __('status.person'), 2 => __('status.company'), 3 => __('status.aolai'), 4 => __('status.warehouse'), 5 => __('status.company_offline'), 6 => __('status.puyang'), 7 => __('status.yuansu1'), 8 => __('status.yuansu2'), 9 => __('status.yuansu3'), 10 => __('status.zwt1'), 11 => __('status.jimai'), 12 => __('status.shewai'),],
        ];
    }

    public function searchUser(){
        return [
            'approver' => 'approver',
        ];
    }


    public function getApproverTxtAttribute()
    {
        return $this->getAdminUser($this->approver);
    }

    public function getIdCardFrontAttribute($value)
    {
        // if($value)return request()->root().$value;
        if ($value) {
            if (strpos($value, 'https://') !== false || strpos($value, 'http://') !== false) return $value;
            return config('ext.aliyun_oss_host') . $value;
        }
        return '';
    }

    public function getIdCardReverseAttribute($value)
    {
        // if($value)return request()->root().$value;
        if ($value) {
            if (strpos($value, 'https://') !== false || strpos($value, 'http://') !== false) return $value;
            return config('ext.aliyun_oss_host') . $value;
        }
        return '';
    }

    public function setApprover($value)
    {
        if (!$this->adminUserIns) $this->adminUserIns = new AdminUsers();
        $item = $this->adminUserIns->where('username', $value)->first();
        $id = empty($item) ?? $item->id;
        return ['approver' => $id];
    }

    public static function getName($id)
    {
        if($id==0)return'';
        $item = self::find($id);
        if (empty($item)) return '';
        return $item->name;
    }

    public static function getID($name)
    {
        $item = self::where('name', $name)->orderBy('id', 'desc')->where('status',1)->orderBy('created_at', 'desc')->first();
        if (empty($item)) return 0;
        return $item->id;
    }
    public static function getIDByNameCard($name,$card,$arr_code='')
    {
        $sup_ids = [];
        if($arr_code){
            $arr = ArrivalRegist::where('arr_code',$arr_code)->first();
            if($arr){
                $sup_ids = RecvDetail::where('arr_id',$arr->id)->where('sup_id','<>',0)->pluck('sup_id')->toArray();
            }
        }
        if($sup_ids) $item = self::where('name', $name)->where('id_card',$card)->whereIn('id',$sup_ids)->orderBy('id', 'desc')->where('status',1)->orderBy('created_at', 'desc')->first();
        else $item = self::where('name', $name)->where('id_card',$card)->orderBy('id', 'desc')->where('status',1)->orderBy('created_at', 'desc')->first();
        if (empty($item)) return 0;
        return $item->id;
    }

    public function approved($id, $sup_status, $approver)
    {
        $item = $this::find($id);
        if (empty($item)) return [false, __('response.sup_not_exists')];
        if ($item->status == 0) return  [false, __('response.please_on')];
        if (!in_array($sup_status, array_keys($this->map['sup_status']))) return [false, __('response.status_not_exists')];
        $item->sup_status = $sup_status;
        $item->approver = $approver;
        $item->approved_at = date('Y-m-d H:i:s');
        $row = $item->save();
        return [$row, __('base.fail')];
    }

    public function searchAll()
    {
        $data = $this::where('status', 1)->where('sup_status', 2)->select('id', 'name', 'status', 'sup_status', 'type')->get();
        return [true, $data->toArray()];
    }

    function documents()
    {
        return $this->hasMany(WmsSupplierDocument::class, 'sup_code', 'sup_code')->orderBy('id', 'desc');
    }

    public  function BaseCreate($CreateData = [])
    {
        if (empty($CreateData)) return false;
        $id = $this::insertGetId($CreateData);
        if (empty($id)) return false;
        $suplier = $this::find($id);
        WmsDataPermission::addSupplier($suplier);
        Redis::del('sup_map');
        return $id;
    }

}
