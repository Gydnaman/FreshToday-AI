<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PaymentService 测试
 * 覆盖：webhook 落库去重 + 路由事件 + 退款
 */
class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;
    private User $user;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PaymentService::class);
        $this->user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 100, 'price' => 100]);
        $this->order = app(OrderService::class)->createOrder(
            user: $this->user,
            items: [['product_id' => $product->id, 'quantity' => 1]],
            shippingAddress: ['name' => 'Test', 'currency' => 'HKD'],
        );
    }

    /** 重复 event_id：仅入库 1 条 StripeWebhookEvent */
    public function test_duplicate_event_id_is_deduplicated(): void
    {
        $payload = ['id' => 'evt_dup_test', 'type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_dup']]];

        $this->service->handleWebhook('stripe', $payload);
        $this->service->handleWebhook('stripe', $payload);
        $this->service->handleWebhook('stripe', $payload);

        $this->assertEquals(1, StripeWebhookEvent::where('provider_event_id', 'evt_dup_test')->count());
    }

    /** payment_intent.succeeded：成功时 update payment status → succeeded */
    public function test_payment_succeeded_updates_payment_record(): void
    {
        $payment = Payment::create([
            'order_id'        => $this->order->id,
            'provider'        => 'stripe',
            'provider_txn_id' => 'pi_paid_1',
            'amount'          => $this->order->total_price,
            'currency'        => 'HKD',
            'status'          => 'pending',
        ]);

        $payload = [
            'id'   => 'evt_paid_1',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_paid_1']],
        ];
        $this->service->handleWebhook('stripe', $payload);

        $this->assertEquals('succeeded', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
    }

    /** 退款：成功 transfer 订单 → refunded */
    public function test_refund_transitions_order_to_refunded(): void
    {
        $payment = Payment::create([
            'order_id'        => $this->order->id,
            'provider'        => 'stripe',
            'provider_txn_id' => 'pi_refund_1',
            'amount'          => $this->order->total_price,
            'currency'        => 'HKD',
            'status'          => 'succeeded',
            'paid_at'         => now(),
        ]);

        $this->service->refund($payment, (int) $payment->amount, 'customer_refund');

        $this->assertEquals('refunded', $payment->fresh()->status);
        $this->assertEquals(\App\Enums\OrderStatus::Refunded, $this->order->fresh()->status);
    }
}
