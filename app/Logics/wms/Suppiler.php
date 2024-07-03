<?php

namespace App\Logics\wms;

use App\Logics\BaseLogic;
use App\Logics\RedisKey;
use App\Models\Admin\V2\Supplier;
use App\Models\Admin\V2\WmsSupplierDocument;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use SebastianBergmann\Type\ObjectType;

class Suppiler extends BaseLogic
{
    function addDoc($params)
    {
        $suppiler = Supplier::where('sup_code', $params['sup_code'])->first();
        if (!$suppiler) {
            $this->setErrorMsg(__('tips.doc_not_exists'));
            return false;
        }
        WmsSupplierDocument::create([
            'sup_code' => $params['sup_code'],
            'type' => $params['type'],
            'name' => $params['name'],
            'personal_number' => $params['personal_number'] ?? '',
            'address' => $params['address'] ?? '',
            'passport_type' => $params['passport_type'] ?? 0,
            'passport_number' => $params['passport_number'] ?? '',
            'tenant_id' => ADMIN_INFO['tenant_id'],
            'created_user' => ADMIN_INFO['user_id'],
        ]);
        return true;
    }

    static function documents($params)
    {
        return WmsSupplierDocument::where('sup_code', $params['sup_code'])->orderBy('id', 'desc')->get()->toArray();
    }

    static function supplierKeyBy($key = 'id')
    {
        $tenant_id = request()->header('tenant_id');
        $data = DB::select("select * from wms_supplier where tenant_id=" . $tenant_id);
        $data = objectToArray($data);
        $keys = array_column($data, $key);
        return array_combine($keys, $data);
        // return Supplier::get()->keyBy($key)->toArray();
    }
}
