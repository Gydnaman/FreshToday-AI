<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Stripe Webhook 控制器（无 auth，签名校验 + fail-closed）
 *
 * 详见 docs/bmad/api-contract.md §2.8 + ADR-007 P0-2
 *
 * 关键安全契约：
 * - 必须配置 STRIPE_WEBHOOK_SECRET（AppServiceProvider::boot() 启动断言）
 * - HMAC-SHA256 签名验证失败 → 401 INVALID_SIGNATURE（不返回 200 避免事件丢失）
 * - 没有合法 signature header → 401 MISSING_SIGNATURE
 * - 在生产 / staging 环境**永不** fail-open
 */
class StripeWebhookController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('Stripe-Signature');

        $verifyResult = $this->verifySignature($payload, $signature);
        if ($verifyResult !== true) {
            return response()->json([
                'error' => ['code' => $verifyResult, 'message' => '签名校验失败'],
            ], 401);
        }

        try {
            $this->payments->handleWebhook('stripe', $payload, $signature);
        } catch (\Throwable $e) {
            // webhook 仍返回 200（避免 Stripe 无限重试）；错误已落库 stripe_webhook_events
            Log::error('Stripe webhook unhandled error', ['error' => $e->getMessage()]);
        }

        return response()->json(['received' => true]);
    }

    /**
     * HMAC 验签（fail-closed）
     *
     * @param  array<string, mixed>  $payload
     * @return true|string true 表示通过；字符串为错误 code
     */
    private function verifySignature(array $payload, ?string $signature): true|string
    {
        $secret = config('services.stripe.webhook_secret') ?: env('STRIPE_WEBHOOK_SECRET');

        if (! $secret) {
            // P0-2: 严禁静默放行。AppServiceProvider::boot() 已断言 secret 必须配置；
            // 此处仍 fail-closed 防启动期 race condition / 配置热更新失败。
            Log::error('Stripe webhook secret is not configured');

            return 'INVALID_SIGNATURE';
        }

        if (! $signature) {
            return 'MISSING_SIGNATURE';
        }

        // 简化 HMAC-SHA256 校验（生产建议用 \Stripe\Webhook::constructEvent）
        $signedPayload = ($payload['id'] ?? '').'.'.json_encode($payload);
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expected, $signature) ? true : 'INVALID_SIGNATURE';
    }
}
