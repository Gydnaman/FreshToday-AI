<?php

namespace Tests\Unit\Services;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * OrderService 幂等性专项测试
 *
 * 验证：相同 trigger 在 OrderStatusLog 中不应产生重复转移行
 * 实现机制：转移矩阵（pending → [paid, cancelled]）+ 终态不变量
 * 第一次 transition 成功后，order.status 已变更；
 * 重复 transition 时因 from 不在 TRANSITIONS 中立即被拒。
 */
class OrderServiceIdempotencyTest extends TestCase
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
            'order_id'        => $order->id,
            'provider'        => 'stripe',
            'provider_txn_id' => 'pi_' . uniqid(),
            'amount'          => $order->total_price,
            'currency'        => 'HKD',
            'status'          => 'succeeded',
            'paid_at'         => now(),
        ]);
        return $order->fresh();
    }

    /** 同一 trigger 调用 100 次，仅首次成功；99 次因 from 状态已变更被拒 */
    public function test_repeated_transition_only_succeeds_once(): void
    {
        $order = $this->makeOrderWithSucceededPayment();

        // 首次 pending → paid 成功
        $paid = $this->service->transition($order, OrderStatus::Paid, 'payment_succeeded');
        $this->assertEquals(OrderStatus::Paid, $paid->status);

        // 接下来 99 次重复调用全部抛 InvalidTransitionException（from=paid，to=paid 不在转移矩阵中）
        $rejected = 0;
        for ($i = 0; $i < 99; $i++) {
            try {
                $this->service->transition($paid, OrderStatus::Paid, 'payment_succeeded');
            } catch (InvalidTransitionException $e) {
                $rejected++;
            }
        }

        $this->assertEquals(99, $rejected, '99 次重复 transition 应全部被拒');
        $this->assertEquals(OrderStatus::Paid, $paid->fresh()->status, '订单状态保持 paid');

        // 审计日志仅写 1 条
        $logCount = OrderStatusLog::where('order_id', $paid->id)
            ->where('trigger', 'payment_succeeded')
            ->where('to_status', 'paid')
            ->count();
        $this->assertEquals(1, $logCount, '应仅 1 条 from=pending→to=paid 的审计日志');
    }

    /** 并发场景：DB 锁保证转移矩阵的串行执行（单进程模拟） */
    public function test_concurrent_transitions_under_db_lock(): void
    {
        $order = $this->makeOrderWithSucceededPayment();

        // 同一笔订单，5 个并发 transition pending → paid；只有 1 个成功
        $success = 0;
        $failed = 0;
        for ($i = 0; $i < 5; $i++) {
            try {
                $this->service->transition($order, OrderStatus::Paid, 'payment_succeeded');
                $success++;
            } catch (InvalidTransitionException $e) {
                $failed++;
            }
        }

        $this->assertEquals(1, $success, '5 个并发 transition 中应仅 1 个成功');
        $this->assertEquals(4, $failed, '4 个应被拒（from 状态已被前序占用）');
    }
}
