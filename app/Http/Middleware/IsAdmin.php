<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin 守卫中间件（最小版）
 *
 * 契约：
 * - 未登录 → 重定向到 /admin/login（带 return URL）
 * - 已登录但 is_admin !== true → 403
 * - 是 admin → 放行
 *
 * 配套：bootstrap/app.php 中间件别名 'admin' → 本类
 */
class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('admin.login', ['return' => $request->fullUrl()]);
        }

        if (! ($user->is_admin ?? false)) {
            abort(403, '需要管理员权限');
        }

        return $next($request);
    }
}
