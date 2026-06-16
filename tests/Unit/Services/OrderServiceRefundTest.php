<?php

namespace Tests\Unit\Services;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * OrderService 退款专项测试
 * 覆盖 docs/bmad/order-state-machine.md §3 的 4 条 *→refunded 路径
 */
class OrderServiceRefundTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $service;

    private User $user;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrderService::class);
        $this->user = User::factory()->create();
        $this->product = Product::factory()->create(['stock' => 100, 'price' => 50]);
    }

    private function makeOrderWithSucceededPayment(): Order
    {
        $order = $this->service->createOrder(
            user: $this->user,
            items: [['product_id' => $this->product->id, 'quantity' => 1]],
            shippingAddress: ['name' => 'Tester', 'currency' => 'HKD'],
        );
        Payment::create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'provider_txn_id' => 'pi_'.uniqid(),
            'amount' => $order->total_price,
            'currency' => 'HKD',
            'status' => 'succeeded',
            'paid_at' => now(),
        ]);

        return $order->fresh();
    }

    /** 路径 1: paid → refunded（财务审核，未发货退款） */
    public function test_paid_to_refunded_succeeds(): void
    {
        $order = $this->service->transition(
            $this->makeOrderWithSucceededPayment(),
            OrderStatus::Paid,
            'payment_succeeded',
        );

        $refunded = $this->service->transition($order, OrderStatus::Refunded, 'admin_refund', [
            'reason' => 'audit_failed', 'actor_type' => 'admin',
        ]);

        $this->assertEquals(OrderStatus::Refunded, $refunded->status);
    }

    /** 路径 2: processing → refunded */
    public function test_processing_to_refunded_succeeds(): void
    {
        $order = $this->service->transition($this->makeOrderWithSucceededPayment(), OrderStatus::Paid, 'payment_succeeded');
        $order = $this->service->transition($order, OrderStatus::Processing, 'admin_pick');

        $refunded = $this->service->transition($order, OrderStatus::Refunded, 'user_refund_request', [
            'reason' => 'change_mind', 'actor_type' => 'admin',
        ]);

        $this->assertEquals(OrderStatus::Refunded, $refunded->status);
    }

    /** 路径 3: shipped → refunded（丢件/损坏） */
    public function test_shipped_to_refunded_succeeds(): void
    {
        $order = $this->service->transition($this->makeOrderWithSucceededPayment(), OrderStatus::Paid, 'payment_succeeded');
        $order = $this->service->transition($order, OrderStatus::Processing, 'admin_pick');
        $order = $this->service->transition($order, OrderStatus::Shipped, 'warehouse_ship', ['tracking_no' => 'SF1']);

        $refunded = $this->service->transition($order, OrderStatus::Refunded, 'logistics_lost', [
            'reason' => 'logistics_lost', 'actor_type' => 'admin',
        ]);

        $this->assertEquals(OrderStatus::Refunded, $refunded->status);
    }

    /** 路径 4: delivered → refunded（7 天售后） */
    public function test_delivered_to_refunded_succeeds(): void
    {
        $order = $this->service->transition($this->makeOrderWithSucceededPayment(), OrderStatus::Paid, 'payment_succeeded');
        $order = $this->service->transition($order, OrderStatus::Processing, 'admin_pick');
        $order = $this->service->transition($order, OrderStatus::Shipped, 'warehouse_ship', ['tracking_no' => 'SF1']);
        $order = $this->service->transition($order, OrderStatus::Delivered, 'logistics_callback');

        $refunded = $this->service->transition($order, OrderStatus::Refunded, 'customer_refund', [
            'reason' => 'damaged_on_arrival', 'actor_type' => 'admin',
        ]);

        $this->assertEquals(OrderStatus::Refunded, $refunded->status);
    }

    /** pending → refunded 非法（订单未付款不允许退款） */
    public function test_pending_to_refunded_is_rejected(): void
    {
        $order = $this->service->createOrder(
            user: $this->user,
            items: [['product_id' => $this->product->id, 'quantity' => 1]],
            shippingAddress: ['name' => 'Tester', 'currency' => 'HKD'],
        );

        $this->expectException(InvalidTransitionException::class);
        $this->service->transition($order, OrderStatus::Refunded, 'illegal_refund');
    }
}
