<?php

namespace Tests\Unit\Services;

use App\Enums\OrderStatus;
use App\Exceptions\GuardFailedException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * OrderService 状态机主测试
 * 覆盖 docs/bmad/order-state-machine.md 附录 A 的 7 状态 × happy path
 */
class OrderServiceTest extends TestCase
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

    /** 创建一个已支付成功的 pending 订单（含匹配支付单） */
    private function makeOrderWithPayment(string $trigger = 'admin'): Order
    {
        $order = $this->service->createOrder(
            user: $this->user,
            items: [['product_id' => $this->product->id, 'quantity' => 2]],
            shippingAddress: ['name' => 'Tester', 'currency' => 'HKD'],
        );
        // 注入一笔 succeeded 支付单，金额匹配
        Payment::create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'provider_txn_id' => 'pi_test_'.$order->id,
            'amount' => $order->total_price,
            'currency' => 'HKD',
            'status' => 'succeeded',
            'paid_at' => now(),
        ]);

        return $order->fresh();
    }

    /** 测试 pending → paid 的 happy path（含 GUARD-P1 支付守卫） */
    public function test_pending_to_paid_succeeds_with_valid_payment(): void
    {
        $order = $this->makeOrderWithPayment();

        $paid = $this->service->transition($order, OrderStatus::Paid, 'payment_succeeded', [
            'actor_type' => 'webhook',
        ]);

        $this->assertEquals(OrderStatus::Paid, $paid->status);
        $this->assertNotNull($paid->paid_at);
        $this->assertEquals('HKD', $paid->shipping_address['currency']);
    }

    /** 测试 paid → processing */
    public function test_paid_to_processing_succeeds(): void
    {
        $order = $this->service->transition($this->makeOrderWithPayment(), OrderStatus::Paid, 'payment_succeeded');

        $processing = $this->service->transition($order, OrderStatus::Processing, 'admin_pick');

        $this->assertEquals(OrderStatus::Processing, $processing->status);
    }

    /** 测试 processing → shipped（写入 tracking_no） */
    public function test_processing_to_shipped_writes_tracking_no(): void
    {
        $order = $this->service->transition($this->makeOrderWithPayment(), OrderStatus::Paid, 'payment_succeeded');
        $order = $this->service->transition($order, OrderStatus::Processing, 'admin_pick');

        $shipped = $this->service->transition($order, OrderStatus::Shipped, 'warehouse_ship', [
            'tracking_no' => 'SF'.random_int(100000, 999999),
        ]);

        $this->assertEquals(OrderStatus::Shipped, $shipped->status);
        $this->assertNotNull($shipped->tracking_no);
        $this->assertEquals(98, $this->product->fresh()->stock); // 库存预占已扣，shipped 不再扣
    }

    /** 测试 shipped → delivered */
    public function test_shipped_to_delivered_succeeds(): void
    {
        $order = $this->service->transition($this->makeOrderWithPayment(), OrderStatus::Paid, 'payment_succeeded');
        $order = $this->service->transition($order, OrderStatus::Processing, 'admin_pick');
        $order = $this->service->transition($order, OrderStatus::Shipped, 'warehouse_ship', ['tracking_no' => 'SF1']);

        $delivered = $this->service->transition($order, OrderStatus::Delivered, 'logistics_callback');

        $this->assertEquals(OrderStatus::Delivered, $delivered->status);
        $this->assertNotNull($delivered->delivered_at);
    }

    /** 测试非法转移 pending → shipped 直接跳级被拒 */
    public function test_pending_to_shipped_is_rejected(): void
    {
        $order = $this->service->createOrder(
            user: $this->user,
            items: [['product_id' => $this->product->id, 'quantity' => 1]],
            shippingAddress: ['name' => 'Tester', 'currency' => 'HKD'],
        );

        $this->expectException(InvalidTransitionException::class);
        $this->service->transition($order, OrderStatus::Shipped, 'illegal_skip');
    }

    /** 测试终态不变量：从 cancelled 不允许转移 */
    public function test_cancelled_is_terminal_no_further_transition(): void
    {
        $order = $this->service->createOrder(
            user: $this->user,
            items: [['product_id' => $this->product->id, 'quantity' => 1]],
            shippingAddress: ['name' => 'Tester', 'currency' => 'HKD'],
        );
        $cancelled = $this->service->transition($order, OrderStatus::Cancelled, 'user_cancel', ['reason' => 'change_mind']);

        $this->expectException(InvalidTransitionException::class);
        $this->service->transition($cancelled, OrderStatus::Paid, 'reopen_attempt');
    }

    /** 测试 delivered → refunded 是合法的（订单状态机附录 A 唯一终态回退） */
    public function test_delivered_to_refunded_succeeds(): void
    {
        $order = $this->service->transition($this->makeOrderWithPayment(), OrderStatus::Paid, 'payment_succeeded');
        $order = $this->service->transition($order, OrderStatus::Processing, 'admin_pick');
        $order = $this->service->transition($order, OrderStatus::Shipped, 'warehouse_ship', ['tracking_no' => 'SF1']);
        $order = $this->service->transition($order, OrderStatus::Delivered, 'logistics_callback');

        $refunded = $this->service->transition($order, OrderStatus::Refunded, 'admin_refund', [
            'reason' => 'damaged', 'actor_type' => 'admin',
        ]);

        $this->assertEquals(OrderStatus::Refunded, $refunded->status);
    }

    /** 审计日志：每个 transition 必须写一条 OrderStatusLog */
    public function test_transition_creates_audit_log(): void
    {
        $order = $this->service->transition(
            $this->makeOrderWithPayment(),
            OrderStatus::Paid,
            'payment_succeeded',
            ['actor_type' => 'webhook', 'source_ip' => '127.0.0.1'],
        );

        $log = OrderStatusLog::where('order_id', $order->id)->latest('created_at')->first();
        $this->assertNotNull($log);
        $this->assertEquals('pending', $log->from_status);
        $this->assertEquals('paid', $log->to_status);
        $this->assertEquals('payment_succeeded', $log->trigger);
        $this->assertEquals('webhook', $log->actor_type);
    }

    /** GUARD-G0 订单归属：他人不能取消 */
    public function test_guarded_other_user_cannot_transition(): void
    {
        $order = $this->makeOrderWithPayment();
        $other = User::factory()->create();

        $this->expectException(GuardFailedException::class);
        $this->service->transition($order, OrderStatus::Cancelled, 'user_cancel', [
            'reason' => 'not_owner', 'actor_type' => 'user',
        ], actor: $other);
    }
}
