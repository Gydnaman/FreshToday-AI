<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PayMe Webhook 控制器（Sprint 2 待实现验签）
 *
 * ⚠️ 当前状态：签名校验未实现。fail-closed 模式——
 *    - 未配 PAYME_WEBHOOK_SECRET → 返回 501（不处理）
 *    - 配了 secret → 返回 501 + 日志（验签逻辑待 Sprint 2 接入）
 *
 * 安全契约：永不静默放行。即使配置了 secret，也不处理 payload，
 *           直到 Sprint 2 实现 PayMe 官方签名校验。
 */
class PaymeWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.payme.api_key') ?: env('PAYME_API_KEY');

        if (! $secret) {
            Log::info('PayMe webhook received but PAYME_API_KEY not configured, rejecting');

            return response()->json([
                'error' => ['code' => 'PAYME_NOT_CONFIGURED', 'message' => 'PayMe webhook handler not implemented'],
            ], 501);
        }

        // Sprint 2 TODO: 实现 PayMe 官方签名校验
        // 当前即使配了 secret 也不处理，避免假安全感
        Log::warning('PayMe webhook received but signature verification not implemented (Sprint 2)');

        return response()->json([
            'error' => ['code' => 'PAYME_VERIFICATION_TODO', 'message' => 'PayMe signature verification not yet implemented'],
        ], 501);
    }
}
