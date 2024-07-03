<?php

namespace App\Http\Controllers\PDA;

use App\Logics\wms\Auth;
use Illuminate\Http\Request;

class AuthController extends BaseController
{
    // 登录
    function login(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'username' => 'required',
            'password' => 'required'
        ]);

        $logic = new Auth();
        $params['path'] = $request->path();
        $params['ip'] = $request->ip();
        $res = $logic->pdaLogin($params);
        return $this->output($logic, $res);
    }

    // 退出登录
    function logout(Request $request)
    {
        $logic = new Auth();
        $logic::pdaLogout($request->header('token'));
        return $this->output($logic, []);
    }
}
