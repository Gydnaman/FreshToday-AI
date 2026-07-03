<?php

namespace Tests\Feature\Order;

use Tests\TestCase;

class StripeSignatureTest extends TestCase
{
    private const TEST_SECRET = 'whsec_test_secret_for_phpunit';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.webhook_secret' => self::TEST_SECRET]);
    }

    public function test_webhook_without_signature_returns_401(): void
    {
        $response = $this->postJson('/api/stripe/webhook', ['id' => 'evt_test', 'type' => 'payment_intent.succeeded']);

        $response->assertStatus(401);
        $response->assertJson(['error' => ['code' => 'MISSING_SIGNATURE']]);
    }

    public function test_webhook_with_invalid_signature_returns_401(): void
    {
        $response = $this->postJson('/api/stripe/webhook', ['id' => 'evt_test', 'type' => 'payment_intent.succeeded'], [
            'Stripe-Signature' => 't=1234567890,v1=invalidhash',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => ['code' => 'INVALID_SIGNATURE']]);
    }

    /**
     * 构造合法 Stripe 签名格式验证：t=<timestamp>,v1=<hex>
     * 签名内容 = "<timestamp>.<raw_body>"
     * 用 STRIPE_WEBHOOK_SECRET 做 HMAC-SHA256
     *
     * 旧代码用 id.json_encode(payload) 格式 → 此测试会 RED
     * 新代码用 \Stripe\Webhook::constructEvent → 此测试应 GREEN
     */
    public function test_webhook_with_valid_stripe_signature_is_accepted(): void
    {
        $timestamp = time();
        $payload = json_encode(['id' => 'evt_test_valid', 'type' => 'unknown.event']);
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, self::TEST_SECRET);
        $stripeSig = "t={$timestamp},v1={$signature}";

        $response = $this->call(
            'POST',
            '/api/stripe/webhook',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $stripeSig, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        // 未知 event type 走 ignored 分支，但应返回 200（非 401）
        $response->assertStatus(200);
    }
}
