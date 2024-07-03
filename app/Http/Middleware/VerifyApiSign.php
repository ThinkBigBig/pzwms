<?php

namespace App\Http\Middleware;

use App\Logics\ErrorCode;
use App\Logics\wms\Auth;
use App\Models\Admin\V2\WmsUserDataPermission;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Psy\Util\Json;

class VerifyApiSign
{
    // 忽略列表
    protected $except = [
        //
        'channels',
    ];

    // 时间误差
    protected $timeError = 600;

    protected $secretKeys = [
        'carry-me-api' => 'X0Km5kRdo3SwhE8RDeU3qTA2xzvDONIEwXN9XEsw2FlY0dnkm2Xi4hSia5kRFxt2',
        'ihad' => 'pS2qn5Z8HDhYtYSRkjH8HyG5JLHzt8sbCpgxfy52gQtWe5bDPkeyWARrLzpZcteM',
        'wms' => 'C7cmazX2sKKLQnWKOHb3REXZc47n6x8FymT4qM6DqzrQnCtpjTdtIUyv0EhrlpEA',
    ];


    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->method() == 'GET') {
            $data = $request->query();
        } else {
            $data = $request->json()->all();
            if (empty($data)) $data = $request->post();
        }
        // Log::info($request->all());
        // Log::info($data);
        if (isset($data['s'])) {
            unset($data['s']);
        }
        if ($request->header('source', '') == 'pda') {
            $data['source'] = $request->header('source', '');
            $data['timestamp'] = $request->header('timestamp', '');
            $data['sign'] = $request->header('sign', '');
            if ($request->header('token', '')) $data['token'] = $request->header('token', '');
            if ($request->header('lang', '')) $data['lang'] = $request->header('lang', '');
        }
        if ($this->inExceptArray($request) || ($this->allowTimestamp($data) && $this->signMatch($data))) {
            if (($data['source'] ?? '') == 'pda') {

                // 非登录接口，验证token是否过期
                if ($request->path() != 'pda/login') {
                    $user = Auth::padAuth($request->header('token', ''));
                    if (!$user) {
                        return response()->json([
                            'code' => ErrorCode::TOKEN_EXPIRE,
                            'msg' => '登录已过期',
                            'time' => time(),
                            // 'data' => []
                        ]);
                    }

                    // $user->tenant_id = 489063;
                    $request->headers->set('user_id', $user->id);
                    $request->headers->set('tenant_id', $user->tenant_id);
                    $request->headers->set('pid', $user->p_id);
                    $request->headers->set('username', $user->username);
                    $permission = $user->tenant_id ? WmsUserDataPermission::userPermission($user) : [];
                    define('ADMIN_INFO', [
                        'tenant_id' => $user->tenant_id,
                        'pid' => $user->p_id,
                        'user_id' => $user->id,
                        'current_warehouse' => $user->current_warehouse,
                        'data_permission' => $permission,
                    ]);
                }
            }

            return $next($request);
        }
        return response()->json([
            'code' => 401,
            'msg' => __('base.Signature_error'),
            'time' => time(),
            // 'data' => []
        ]);
    }

    /**
     * 判断当前请求是否在忽略列表中
     *
     * @param Request $request
     * @return bool
     *
     */
    protected function inExceptArray($request): bool
    {
        foreach ($this->except as $except) {
            if ($except !== '/') {
                $except = trim($except, '/');
            }
            if ($request->fullUrlIs($except) || $request->is($except)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 判断用户请求是否在对应时间范围
     *
     * @param $data
     * @return boolean
     */
    protected function allowTimestamp($data): bool
    {
        $tim = millisecond2second($data['timestamp']);
        $queryTime = Carbon::createFromTimestamp($tim);
        $lfTime = Carbon::now()->subSeconds($this->timeError);
        $rfTime = Carbon::now()->addSeconds($this->timeError);
        if ($queryTime->between($lfTime, $rfTime, true)) {
            return true;
        }
        return false;
    }

    /**
     * 签名验证
     *
     * @param $data
     * @return bool
     */
    protected function signMatch($data): bool
    {
        $sign = $data['sign'] ?? '';
        // 移除sign字段
        if (isset($data['sign'])) {
            unset($data['sign']);
        }
        // $secret_key = $this->secretKeys[$data['source']];
        $secret_key = config('ext.partners')[$data['source']];

        ksort($data);

        $arr = [];
        foreach ($data as $k => $v) {
            if ("sign" !== $k) {
                $v = is_array($v) ? Json::encode($v) : $v;
                $arr[] = $k . '=' . $v;
            }
        }

        $str = implode('&', $arr);
        // Log::info($str . $secret_key);
        // Log::channel('daily2')->info($str . $secret_key);
        // Log::channel('daily2')->info(md5($str . $secret_key));
        if (md5($str . $secret_key) === $sign) {
            return true;
        }
        return false;
    }
}
