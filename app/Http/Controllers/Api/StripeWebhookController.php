<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Stripe Webhook 控制器（无 auth，签名校验）
 * 详见 docs/bmad/api-contract.md §2.8
 */
class StripeWebhookController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('Stripe-Signature');

        // 验签（实际生产用 Stripe SDK）
        if (! $this->verifySignature($payload, $signature)) {
            return response()->json([
                'error' => ['code' => 'INVALID_SIGNATURE', 'message' => '签名校验失败'],
            ], 401);
        }

        try {
            $this->payments->handleWebhook('stripe', $payload, $signature);
        } catch (\Throwable $e) {
            // webhook 仍返回 200，避免 Stripe 无限重试；错误已落库 stripe_webhook_events
            Log::error('Stripe webhook unhandled error', ['error' => $e->getMessage()]);
        }

        return response()->json(['received' => true]);
    }

    private function verifySignature(array $payload, ?string $signature): bool
    {
        $secret = env('STRIPE_WEBHOOK_SECRET');
        if (! $secret) {
            // 开发环境：未配置 secret 时放行
            return app()->environment(['local', 'testing']);
        }
        if (! $signature) {
            return false;
        }

        // 简化 HMAC-SHA256 校验（生产建议用 \Stripe\Webhook::constructEvent）
        $signedPayload = $payload['id'].'.'.json_encode($payload);
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expected, $signature);
    }
}
