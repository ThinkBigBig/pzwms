<?php

namespace App\Http\Middleware;

use App\Logics\wms\DataPermission as WmsDataPermission;
use App\Models\Admin\V2\WmsUserDataPermission;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Dingo\Api\Routing\Helpers;
use Illuminate\Contracts\Auth\Factory as Auth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\JsonResponse;
use App\Models\AdminUser;
use App\Models\AdminAuthorization;
use DataPermission;
use Tymon\JWTAuth\Facades\JWTAuth;

class Authenticate extends Middleware
{
    use Helpers;
    /**
     * The authentication factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @return void
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string[]  ...$guards
     * @return mixed
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function handle($request, Closure $next, ...$guards)
    {

        $this->authenticate($request, $guards);
        $user = $this->auth->user();
        if (!empty($user)) {
            //过滤那api token请求admin
            if (!empty($guards[0]) && $guards[0] == 'admin' && isset($user->u_token)) {
                return response()->json([
                    'code' => 401,
                    'msg' => __('base.errorToken'),
                    'time' => time(),
                    'data' => []
                ]);
            }
            //过滤那admin token请求api
            if (!empty($guards[0]) && $guards[0] == 'user' && !isset($user->u_token)) {
                // var_dump($guards[0]);exit;
                return response()->json([
                    'code' => 401,
                    'msg' => __('base.errorToken'),
                    'time' => time(),
                    'data' => []
                ]);
            }
            //同过u_token字段区分admin 和api 环境
            if (!empty($guards[0]) && $guards[0] == 'user' && isset($user->u_token)) {
                //单点登录
            }
            if (!empty($guards[0]) && $guards[0] == 'admin' && !isset($user->u_token)) {
                //单点登录
                //admin不做单点
                $loginToken = AdminUser::where('username', $user->username)->select('username', 'remember_token')->first();
                if ($loginToken->remember_token != JWTAuth::getToken()) {
                    return response()->json([
                        'code' => 401,
                        'msg' => __('base.errorToken2'),
                        'time' => time(),
                        'data' => []
                    ]);
                }
            }
        } else {
            return response()->json([
                'code' => 401,
                'msg' => __('base.errorToken'),
                'time' => time(),
                'data' => []
            ]);
        }

        $request->headers->set('user_code', $user->user_code);
        $request->headers->set('user_id', $user->id);
        $request->headers->set('tenant_id', $user->tenant_id);
        $request->headers->set('pid', $user->p_id);
        $request->headers->set('username', $user->username);
        $permission = $user->tenant_id ? WmsDataPermission::userPermissionList($user) : [];
        define('ADMIN_INFO', [
            'tenant_id' => $user->tenant_id,
            'pid' => $user->p_id,
            'user_id' => $user->id,
            'roles_id' => $user->roles_id,
            'org_code' => $user->org_code,
            'user_code' => $user->user_code,
            'username' => $user->username,
            'data_permission' => $permission,
        ]);
        $request->merge(array_map(function ($item) {
            if (is_string($item)) return trim($item);
            if ($item === null) return '';
            return $item;
        }, $request->all()));
        return $next($request);
    }

    /**
     * Determine if the user is logged in to any of the given guards.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $guards
     * @return void
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function authenticate($request, array $guards)
    {
        if (empty($guards)) {
            $guards = [null];
        }

        foreach ($guards as $guard) {
            if ($this->auth->guard($guard)->check()) {
                return $this->auth->shouldUse($guard);
            }
        }

        return $this->unauthenticated($request, $guards);
    }

    /**
     * Handle an unauthenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $guards
     * @return void
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function unauthenticated($request, array $guards)
    {
        return 1;
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {

        if (!$request->expectsJson()) {
            // exit;
        }
    }
}
