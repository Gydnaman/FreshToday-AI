<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

/**
 * Webhook 端到端测试（Sprint 1 关键集成）
 * 验证：Stripe webhook 100 次重复投递，仅 1 次状态变为 paid（幂等）
 *
 * 100 次连续请求会触发默认 throttle:api 60/min 限流，但 webhook 路由配置了
 * throttle:10000,1 不应触发 429。本测试套用 WithoutMiddleware 是双保险：
 * 100 次是为了验证「webhook 业务级幂等」而不是「限流器是否生效」。
 */
class WebhookFlowTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    private OrderService $orderService;
    private User $user;
    private Order $order;
    private string $eventId;
    private string $txnId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = app(OrderService::class);
        $this->user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 100, 'price' => 80]);

        $this->order = $this->orderService->createOrder(
            user: $this->user,
            items: [['product_id' => $product->id, 'quantity' => 1]],
            shippingAddress: ['name' => 'Webhook Tester', 'currency' => 'HKD'],
        );
        $this->eventId = 'evt_test_' . uniqid();
        $this->txnId = 'pi_test_' . uniqid();

        Payment::create([
            'order_id'        => $this->order->id,
            'provider'        => 'stripe',
            'provider_txn_id' => $this->txnId,
            'amount'          => $this->order->total_price,
            'currency'        => 'HKD',
            'status'          => 'pending',
        ]);
    }

    /** 一次 webhook 成功：pending → paid */
    public function test_single_webhook_transitions_order_to_paid(): void
    {
        $payload = $this->buildPayload();

        $this->postJson('/api/stripe/webhook', $payload, [
            'Stripe-Signature' => 'sig_test',
        ])->assertOk()->assertJson(['received' => true]);

        $this->order->refresh();
        $this->assertEquals(OrderStatus::Paid, $this->order->status);

        // StripeWebhookEvent 应被记录
        $event = StripeWebhookEvent::where('provider_event_id', $this->eventId)->first();
        $this->assertNotNull($event);
        $this->assertEquals('processed', $event->status);
    }

    /** 100 次重复 webhook：仅 1 次进入 paid（幂等） */
    public function test_repeated_webhook_only_processes_once(): void
    {
        $payload = $this->buildPayload();

        for ($i = 0; $i < 100; $i++) {
            $this->postJson('/api/stripe/webhook', $payload, [
                'Stripe-Signature' => 'sig_test',
            ])->assertOk();
        }

        $this->order->refresh();
        $this->assertEquals(OrderStatus::Paid, $this->order->status);

        // StripeWebhookEvent 应仅 1 条
        $eventCount = StripeWebhookEvent::where('provider_event_id', $this->eventId)->count();
        $this->assertEquals(1, $eventCount, '100 次重复 webhook 应仅入库 1 条');

        // 支付单应仅 1 次被标记为 succeeded
        $payment = Payment::where('provider_txn_id', $this->txnId)->first();
        $this->assertEquals('succeeded', $payment->status);
    }

    /** payment_intent.payment_failed：更新 Payment.status=failed，不转移订单 */
    public function test_payment_failed_event_marks_payment_as_failed(): void
    {
        $payload = [
            'id'   => 'evt_failed_' . uniqid(),
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => $this->txnId,
                ],
            ],
        ];

        $this->postJson('/api/stripe/webhook', $payload, ['Stripe-Signature' => 'sig_test'])
            ->assertOk();

        $payment = Payment::where('provider_txn_id', $this->txnId)->first();
        $this->assertEquals('failed', $payment->status);
        $this->assertEquals(OrderStatus::Pending, $this->order->fresh()->status);
    }

    private function buildPayload(): array
    {
        return [
            'id'   => $this->eventId,
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id'     => $this->txnId,
                    'amount' => (int) ($this->order->total_price * 100),
                    'currency' => 'hkd',
                ],
            ],
        ];
    }
}
