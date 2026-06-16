<?php

namespace Tests\Unit\Services;

use App\Enums\OrderStatus;
use App\Exceptions\GuardFailedException;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * OrderService 守卫专项测试
 * 覆盖 GUARD-G0/G1/I1/P1/P3
 */
class OrderServiceGuardTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrderService::class);
        $this->user = User::factory()->create();
    }

    /** GUARD-I1 库存预占：库存不足抛 GuardFailedException */
    public function test_guard_i1_rejects_out_of_stock(): void
    {
        $product = Product::factory()->create(['stock' => 1, 'price' => 50]);

        $this->expectException(GuardFailedException::class);
        $this->expectExceptionMessageMatches('/库存不足|out of stock|quantity/i');

        $this->service->createOrder(
            user: $this->user,
            items: [['product_id' => $product->id, 'quantity' => 5]],
            shippingAddress: ['name' => 'Tester', 'currency' => 'HKD'],
        );
    }

    /** GUARD-I1 数量为 0 抛 GuardFailedException */
    public function test_guard_i1_rejects_zero_quantity(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 50]);

        $this->expectException(GuardFailedException::class);
        $this->service->createOrder(
            user: $this->user,
            items: [['product_id' => $product->id, 'quantity' => 0]],
            shippingAddress: ['name' => 'Tester', 'currency' => 'HKD'],
        );
    }

    /** GUARD-P1 支付金额不匹配：订单进入 paid 时校验 */
    public function test_guard_p1_rejects_amount_mismatch(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 100]);
        $order = $this->service->createOrder(
            user: $this->user,
            items: [['product_id' => $product->id, 'quantity' => 1]],
            shippingAddress: ['name' => 'Tester', 'currency' => 'HKD'],
        );

        // 故意造一笔金额不一致的支付单
        Payment::create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'provider_txn_id' => 'pi_wrong_amt',
            'amount' => $order->total_price - 1,
            'currency' => 'HKD',
            'status' => 'succeeded',
            'paid_at' => now(),
        ]);

        $this->expectException(GuardFailedException::class);
        $this->service->transition($order, OrderStatus::Paid, 'payment_succeeded');
    }

    /** GUARD-P1 无支付单：进入 paid 失败 */
    public function test_guard_p1_rejects_no_payment(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 100]);
        $order = $this->service->createOrder(
            user: $this->user,
            items: [['product_id' => $product->id, 'quantity' => 1]],
            shippingAddress: ['name' => 'Tester', 'currency' => 'HKD'],
        );

        $this->expectException(GuardFailedException::class);
        $this->service->transition($order, OrderStatus::Paid, 'payment_succeeded');
    }

    /** GUARD-G0 订单归属：admin 可代操作 */
    public function test_guard_g0_allows_admin(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 50]);
        $order = $this->service->createOrder(
            user: $this->user,
            items: [['product_id' => $product->id, 'quantity' => 1]],
            shippingAddress: ['name' => 'Tester', 'currency' => 'HKD'],
        );

        $admin = User::factory()->admin()->create();
        $cancelled = $this->service->transition(
            $order,
            OrderStatus::Cancelled,
            'admin_cancel',
            ['reason' => 'test', 'actor_type' => 'admin'],
            actor: $admin,
        );

        $this->assertEquals(OrderStatus::Cancelled, $cancelled->status);
    }
}
