<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Auth API 控制器 — 双模式 Sanctum 认证（2026-07-18 修复）
 *
 * ## 问题根因
 * AuthController 直接调用 Auth::login() / Auth::attempt() +
 * $request->session()->regenerate()，但 API 路由默认不加载 Session 中间件。
 * Sanctum 的 statefulApi() 仅在 EnsureFrontendRequestsAreStateful::fromFrontend()
 * 判断为 true 时（请求头含 Referer/Origin 且匹配 stateful domains）才会注入 Session。
 * 非前端请求（curl / PHPUnit getJson）没有 Referer/Origin → Session 不可用 → 500。
 *
 * ## 双模式方案
 * - 前端浏览器请求（有 Origin/Referer + statefulApi 注入 Session）：
 *   Auth::login() + session()->regenerate() → SPA cookie 模式
 * - 非前端请求（curl / 测试 / 移动端）：
 *   createToken() → 返回 Sanctum Personal Access Token（纯 API 模式）
 *
 * 两种模式共享同一个 auth:sanctum guard，后端无需区分。
 *
 * ## 安全性
 * - 密码用 bcrypt（User::$casts['password'=>'hashed']）哈希存储
 * - Token 由 Sanctum createToken() 生成（SHA-256 哈希，可撤销）
 * - SPA 模式：httpOnly session cookie + CSRF 保护（statefulApi 自动注入）
 * - 前端 fetch 用 credentials:'include'，登录前先调 /sanctum/csrf-cookie
 */
class AuthController extends Controller
{
    /**
     * 判断当前请求是否是前端 SPA 请求（statefulApi 已注入 Session）
     */
    private function isStateful(Request $request): bool
    {
        return $request->hasSession()
            && $request->session()->isStarted()
            && EnsureFrontendRequestsAreStateful::fromFrontend($request);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'locale' => ['nullable', 'string', Rule::in(['zh', 'en', 'zhhk'])],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'locale' => $data['locale'] ?? 'zh',
        ]);

        $token = null;
        if ($this->isStateful($request)) {
            // SPA cookie 模式：登录 + regenerate session
            Auth::login($user);
            $request->session()->regenerate();
        } else {
            // 纯 API 模式：创建 Sanctum token
            $token = $user->createToken('api-register')->plainTextToken;
        }

        $response = response()->json([
            'user' => $user,
        ], 201);

        if ($token) {
            $response->withHeaders(['X-Auth-Token' => $token]);
        }

        return $response;
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::guard('web')->attempt(['email' => $data['email'], 'password' => $data['password']])) {
            return response()->json([
                'error' => ['code' => 'INVALID_CREDENTIALS', 'message' => '邮箱或密码错误'],
            ], 401);
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        $token = null;
        if ($this->isStateful($request)) {
            // SPA cookie 模式
            $request->session()->regenerate();
        } else {
            // 纯 API 模式
            $token = $user->createToken('api-login')->plainTextToken;
        }

        $response = response()->json([
            'user' => $user,
        ]);

        if ($token) {
            $response->withHeaders(['X-Auth-Token' => $token]);
        }

        return $response;
    }

    public function logout(Request $request): JsonResponse
    {
        if ($this->isStateful($request)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } else {
            // 纯 API 模式：从 Authorization header 解析 token ID 并撤销
            // 不使用 request->user()（Session 不可用）也不使用 auth('sanctum')->user()（需要完整上下文）
            $header = $request->header('Authorization', '');
            if (str_starts_with($header, 'Bearer ')) {
                $plainText = substr($header, 7);
                $tokenId = (int) explode('|', $plainText, 2)[0];
                if ($tokenId > 0) {
                    PersonalAccessToken::find($tokenId)?->delete();
                }
            }
        }

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['userPreferences', 'notificationPreference']);
        $user->makeVisible('is_admin');

        return response()->json(['user' => $user]);
    }
}
