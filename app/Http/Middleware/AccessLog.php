<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AccessLog
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $traceId = md5(time() . mt_rand(1, 1000000));
        // 记录请求信息
        $requestMessage = [
            'trace_id' => $traceId,
            'url' => $request->url(),
            'method' => $request->method(),
            'ip' => $request->ips(),
            'headers' => $request->header('Authorization'),
            'params' => $request->all()
        ];
        // Log::channel('daily2')->info("请求信息：", $requestMessage);

        $respone = $next($request);
        $responeData = [
            'trace_id' => $traceId,
            'response' => json_decode($respone->getContent(), true) ?? ""
        ];
        // Log::channel('daily2')->info("返回信息：", $responeData);

        return $respone;
    }
}
