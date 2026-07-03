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
        $rawBody = $request->getContent();
        $payload = json_decode($rawBody, true) ?? $request->all();
        $signature = $request->header('Stripe-Signature');

        $verifyResult = $this->verifySignature($rawBody, $signature);
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
     * HMAC 验签（fail-closed）— 使用 Stripe 官方 SDK
     *
     * @param  string  $rawBody  原始请求体（供 constructEvent 验签）
     * @param  string|null  $signature  Stripe-Signature header（格式：t=<ts>,v1=<hex>）
     * @return true|string true 表示通过；字符串为错误 code
     */
    private function verifySignature(string $rawBody, ?string $signature): true|string
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

        // 使用 Stripe 官方 SDK 验签（Stripe-Signature 格式：t=<timestamp>,v1=<hex>）
        // 签名内容 = "<timestamp>.<raw_body>"，HMAC-SHA256
        try {
            \Stripe\Webhook::constructEvent($rawBody, $signature, $secret);

            return true;
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Stripe signature verification failed', ['error' => $e->getMessage()]);

            return 'INVALID_SIGNATURE';
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe webhook invalid payload', ['error' => $e->getMessage()]);

            return 'INVALID_SIGNATURE';
        }
    }
}
