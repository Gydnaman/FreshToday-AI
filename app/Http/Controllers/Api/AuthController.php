<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Auth API 控制器
 * 详见 docs/bmad/api-contract.md §2.1
 *
 * Sanctum SPA Cookie 模式（2026-07-03 I-3 修复）：
 * - login/register 用 Auth::attempt/login + session()->regenerate()
 * - logout 用 Auth::logout + session()->invalidate()
 * - 不再创建 Personal Access Token
 * - 前端用 withCredentials: 'include' 携带 cookie
 */
class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'locale' => ['nullable', 'string', Rule::in(['zh-HK', 'en', 'zh-CN'])],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'locale' => $data['locale'] ?? 'zh-HK',
        ]);

        // Sanctum SPA cookie 模式：登录 + regenerate session
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'user' => $user,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            return response()->json([
                'error' => ['code' => 'INVALID_CREDENTIALS', 'message' => '邮箱或密码错误'],
            ], 401);
        }

        $request->session()->regenerate();
        $user = Auth::user();

        return response()->json([
            'user' => $user,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['userPreferences', 'notificationPreference']);

        return response()->json(['user' => $user]);
    }
}
