<?php

use App\Exceptions\GuardFailedException;
use App\Exceptions\InvalidTransitionException;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA cookie 模式：启用 stateful API（session + csrf 中间件链）
        $middleware->statefulApi();
        $middleware->throttleApi();
        // i18n：解析 ?lang= / cookie / Accept-Language，写入 app()->setLocale
        $middleware->append(SetLocale::class);
        // admin 别名（IsAdmin 中间件）
        $middleware->alias([
            'admin' => IsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API 鉴权失败：返 401 JSON，不重定向到 login 路由
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['error' => ['code' => 'UNAUTHENTICATED', 'message' => '未登录或令牌无效']], 401);
            }

            // Web 页面未登录 → 重定向到 /login?return=原始URL
            return redirect()->to('/login?return='.urlencode($request->fullUrl()));
        });

        // API 路径下 RouteNotFoundException 视为 404 JSON
        $exceptions->render(function (RouteNotFoundException $e, Request $request) {
            if ($request->is('api/*') && str_contains($e->getMessage(), 'Route [login] not defined')) {
                return response()->json(['error' => ['code' => 'UNAUTHENTICATED', 'message' => '未登录或令牌无效']], 401);
            }
        });

        $exceptions->render(function (InvalidTransitionException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['error' => $e->toApiPayload()], 422);
            }
        });

        $exceptions->render(function (GuardFailedException $e, Request $request) {
            if ($request->is('api/*')) {
                $payload = $e->toApiPayload();

                return response()->json(['error' => $payload], $payload['http']);
            }
        });
    })->create();
