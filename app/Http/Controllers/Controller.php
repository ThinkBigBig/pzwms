<?php

namespace App\Http\Controllers;

use App\Logics\BaseLogic;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    const SUCCESS = 200; //正常code
    const PERMISSION  = 403; //没有权限
    const TOKEN = 405; //token不存在
    const PARAMS_ERROR = 422; //参数错误
    const ERROR = 500; //异常
    const VAlID = 501; //验证不通过
    const ERROR_SPECIAL = 502; //特殊异常
    const PLAY_ERR = 700; //需要提示音的错误码
    public function __construct(Request $request)
    {
        // var_dump($request->header());exit;
        $header  = $request->header();
        #多语言处理
        if (!empty($request->lang)) {
            App::setLocale($request->lang);
        } else {
            $lang = ($header['lang'][0] ?? '') ?: 'zh';
            App::setLocale($lang);
        }

        $token = explode(" ", $request->header('authorization'))[1] ?? '';
        if (!$token) {
            $token = $request->get('token', '');
        }
        session(['admin_token' => $token]);
    }

    //跳转主页
    public function toIndex()
    {
        return view('cms.index');
        // $url = cdnurl('').'/cms/index.html';
        // // var_dump($url);exit;
        // Header("HTTP/1.1 303 See Other");
        // Header("Location:$url");exit;
        // return view()->file(public_path().'/cms/index.html');
    }

    public function output(BaseLogic $logic, $data = []): \Illuminate\Http\JsonResponse
    {
        if ($logic->success) {
            $code = self::SUCCESS;
            $msg = '成功';
            return response()->json([
                'code' => $code,
                'msg' => $msg,
                'time' => time(),
                'data' => $data,
            ]);
        } else {
            $code = self::ERROR;
            $msg = $logic->err_msg ?: __('base.error');
            return response()->json([
                'code' => $code,
                'msg' => $msg,
                'time' => time(),
            ]);
        }
    }

    //错误提示音
    public function playErr(BaseLogic $logic, $data = []): \Illuminate\Http\JsonResponse
    {
        if ($logic->success) {
            $code = self::SUCCESS;
            $msg = '成功';
            return response()->json([
                'code' => $code,
                'msg' => $msg,
                'time' => time(),
                'data' => $data,
            ]);
        } else {
            $code = self::PLAY_ERR;
            $msg = $logic->err_msg ?: __('base.error');
            return response()->json([
                'code' => $code,
                'msg' => $msg,
                'time' => time(),
            ]);
        }
    }

    public function validateParams($data, $rules)
    {
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            response()->json([
                'code' => self::PARAMS_ERROR,
                'msg' => $validator->errors()->first() ?: __('base.error'),
                'time' => time(),
                // 'data' => [],
            ])->send();
            exit();
        }
    }
}
