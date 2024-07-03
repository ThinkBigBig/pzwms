<?php

namespace App\Logics\wms;

use App\Logics\BaseLogic;
use App\Logics\RedisKey;
use App\Models\Admin\V2\preAllocationDetail;
use App\Models\Admin\V2\RecvDetail;
use App\Models\Admin\V2\WmsQualityList;
use App\Models\AdminUser;
use App\Models\UserLoginLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;

class Auth extends BaseLogic
{
    // 登录
    function pdaLogin($params)
    {
        $user = AdminUser::where('username', $params['username'])->where('status', 1)->first();
        if (!$user) {
            $this->setErrorMsg(__('tips.no_user'));
            return [];
        }

        $password = $params['password'];
        if (Hash::check($password, $user->password)) {
            $token = base64_encode(time() . genRandomString(40) . time());

            UserLoginLog::create([
                'type' => 1,
                'ip' => $params['ip'],
                'path' => $params['path'],
                'user_id' => $user->id,
                'info' => ['pda_token' => $token]
            ]);

            $key = RedisKey::PDA_TOKEN . ":" . $token;
            Redis::set($key, $user->id);
            Redis::expire($key, 15 * 24 * 3600);

            return [
                'token' => $token,
                'user' => $this->userInfo(['id' => $user->id])
            ];
        }

        $this->setErrorMsg(__('tips.login_info_error'));
        return [];
    }

    // token验证
    static function padAuth($token)
    {
        $key = RedisKey::PDA_TOKEN . ":" . $token;
        $id = Redis::get($key);
        if (!$id) return null;

        $info = UserLoginLog::where('user_id', $id)->where('type', 1)->orderBy('id', 'desc')->value('info');
        if ($info['pda_token'] != $token) {
            Redis::del($key);
            return null;
        }
        return AdminUser::where('id', $id)->where('status', 1)->first();
    }

    // 退出登录
    static function pdaLogout($token)
    {
        $key = RedisKey::PDA_TOKEN . ":" . $token;
        Redis::del($key);
        return;
    }

    // 用户信息
    function userInfo($params)
    {
        $user = AdminUser::find($params['id']);
        return [
            'avatar' => '',
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'current_warehouse_code' => $user->current_warehouse,
            'current_warehouse_name' => $user->current_warehouse ? Warehouse::name($user->current_warehouse) : '',
            'no' => '123',
        ];
    }

    // 修改所在仓库信息
    function changeWarehouse($params)
    {
        $warehouse = (new Warehouse())->warehouseSearch($params);
        if (!count($warehouse)) {
            $this->setErrorMsg(__('tips.warehouse_error'));
            return false;
        }

        $user = AdminUser::find(ADMIN_INFO['user_id']);
        $user->current_warehouse = $params['warehouse_code'];
        $user->save();
        return $this->userInfo(['id' => $user->id]);
    }

    // pda主页展示的信息
    function padInfo($params)
    {
        // 待质检数
        $wait_qc = RecvDetail::where(['warehouse_code' => ADMIN_INFO['current_warehouse'], 'is_qc' => 0, 'is_cancel' => 0,])->count();
        // 待上架数
        $wait_putaway = RecvDetail::where(['warehouse_code' => ADMIN_INFO['current_warehouse'], 'is_qc' => 1, 'is_putway' => 0, 'is_cancel' => 0,])->count();

        $where = ['warehouse_code' => ADMIN_INFO['current_warehouse'], 'cancel_status' => 0,];
        // 待配单数
        $wait_allocate = preAllocationDetail::where($where)->whereIn('alloction_status', [preAllocationDetail::WAIT_ALLOCATE, preAllocationDetail::WAIT_RECEIVER])->count();
        // 待发单量
        $wait_deliver = preAllocationDetail::where($where)->whereIn('alloction_status', [preAllocationDetail::WAIT_REVIEW, preAllocationDetail::WAIT_DELIVER])->distinct('request_code')->count();
        return [
            'count' => compact('wait_qc', 'wait_putaway', 'wait_allocate', 'wait_deliver'),
        ];
    }
}
