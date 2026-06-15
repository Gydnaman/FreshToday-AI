<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Auth API 控制器
 * 详见 docs/bmad/api-contract.md §2.1
 *
 * Sprint 1 使用 Session Cookie（SPA 模式）
 * Sprint 2 可叠加 Sanctum Personal Access Token
 */
class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'locale'   => ['nullable', 'string', Rule::in(['zh-HK', 'en', 'zh-CN'])],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'locale'   => $data['locale'] ?? 'zh-HK',
        ]);

        // 2026-06-15 改造：纯 token 鉴权（不再用 session）
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'error' => ['code' => 'INVALID_CREDENTIALS', 'message' => '邮箱或密码错误'],
            ], 401);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // 撤销当前 token
        $request->user()->currentAccessToken()->delete();
        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['userPreferences', 'notificationPreference']);
        return response()->json(['user' => $user]);
    }
}
