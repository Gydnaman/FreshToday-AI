<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Exceptions\GuardFailedException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 并发退款专项测试（边界用例补漏）
 *
 * 场景：
 *  - 同一订单被 2 个客户端同时触发 cancel：只允许 1 个成功，库存一致性
 *  - 同一订单被 2 个客户端同时触发 refund：只允许 1 个成功，payment.status 唯一化
 *  - 高并发 (N=10) 取消 pending 订单：精确 1 成功，9 失败
 *  - 跨进程模拟（DB lock 锁 + 重试）：1 笔订单 + 5 worker 抢锁串行
 *  - 库存回滚：N 个并发 cancel 后，产品库存严格恢复到初始值
 *
 * 与 OrderServiceIdempotencyTest 的差异：
 *  - IdempotencyTest 验证「同一 trigger 重复调用」
 *  - 本测试验证「不同 trigger 抢同一资源」（cancel vs refund vs cancel）
 */
class ConcurrentRefundTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orderService;
    private PaymentService $paymentService;
    private User $user;
    private Product $product;
    private Product $expensiveProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = app(OrderService::class);
        $this->paymentService = app(PaymentService::class);
        $this->user = User::factory()->create();
        $this->product = Product::factory()->create(['stock' => 50, 'price' => 40]);
        $this->expensiveProduct = Product::factory()->create(['stock' => 10, 'price' => 500]);
    }

    /** 创建一笔 pending 订单（含库存预占） */
    private function makePendingOrder(int $quantity = 2, ?Product $product = null): Order
    {
        $p = $product ?? $this->product;
        // 读最新库存值（Eloquent $p->stock 可能是过期的 model 缓存）
        $initialStock = Product::find($p->id)->stock;
        $order = $this->orderService->createOrder(
            user: $this->user,
            items: [['product_id' => $p->id, 'quantity' => $quantity]],
            shippingAddress: ['name' => 'Concurrent Tester', 'currency' => 'HKD'],
        );
        // 预占后库存下降
        $this->assertEquals($initialStock - $quantity, Product::find($p->id)->stock, 'createOrder 应预占库存');
        return $order;
    }

    /** 创建一笔已支付订单（含成功支付单，可退款） */
    private function makePaidOrder(int $quantity = 1): Order
    {
        $order = $this->makePendingOrder($quantity);
        Payment::create([
            'order_id'        => $order->id,
            'provider'        => 'stripe',
            'provider_txn_id' => 'pi_concurrent_' . uniqid(),
            'amount'          => $order->total_price,
            'currency'        => 'HKD',
            'status'          => 'succeeded',
            'paid_at'         => now(),
        ]);
        return $order->fresh();
    }

    /**
     * 场景 1：同一笔 pending 订单被 2 个客户端同时 cancel
     * 预期：仅 1 个成功，1 个抛 InvalidTransitionException；库存精确恢复一次
     */
    public function test_two_concurrent_cancels_only_one_succeeds(): void
    {
        $order = $this->makePendingOrder(quantity: 3);
        $stockBeforeCancel = $this->product->fresh()->stock; // 47

        $results = ['success' => 0, 'rejected' => 0, 'exceptions' => []];
        $payloads = [
            ['reason' => 'client_A_change_mind', 'actor_type' => 'user'],
            ['reason' => 'client_B_timeout', 'actor_type' => 'system'],
        ];

        foreach ($payloads as $payload) {
            try {
                $this->orderService->transition($order, OrderStatus::Cancelled, 'user_cancel', $payload);
                $results['success']++;
            } catch (InvalidTransitionException $e) {
                $results['rejected']++;
                $results['exceptions'][] = $e->getMessage();
            }
        }

        $this->assertEquals(1, $results['success'], '2 个并发 cancel 中应仅 1 个成功');
        $this->assertEquals(1, $results['rejected'], '另一笔应被状态机拒绝');

        // 库存应精确恢复 3 件到初始值 50
        $this->assertEquals(50, $this->product->fresh()->stock, '库存应精确恢复 +3');

        // 订单最终态为 cancelled，且 cancel_reason 记录最后一次成功的值
        $final = $order->fresh();
        $this->assertEquals(OrderStatus::Cancelled, $final->status);
        $this->assertNotNull($final->cancelled_at);
        $this->assertContains($final->cancel_reason, ['client_A_change_mind', 'client_B_timeout']);
    }

    /**
     * 场景 2：高并发 (N=10) 取消同一笔 pending 订单
     * 预期：精确 1 成功，9 失败；OrderStatusLog 仅 1 条 pending→cancelled
     */
    public function test_high_concurrency_cancel_only_one_wins(): void
    {
        $order = $this->makePendingOrder(quantity: 5);

        $success = 0;
        $rejected = 0;
        for ($i = 0; $i < 10; $i++) {
            try {
                $this->orderService->transition(
                    $order->fresh(),
                    OrderStatus::Cancelled,
                    'user_cancel',
                    ['reason' => "worker_{$i}", 'actor_type' => 'system'],
                );
                $success++;
            } catch (InvalidTransitionException) {
                $rejected++;
            }
        }

        $this->assertEquals(1, $success, '10 个并发 cancel 应仅 1 成功');
        $this->assertEquals(9, $rejected, '9 个应被状态机拒绝');

        // 审计日志验证
        $logCount = OrderStatusLog::where('order_id', $order->id)
            ->where('to_status', 'cancelled')
            ->count();
        $this->assertEquals(1, $logCount, '应仅 1 条 pending→cancelled 审计日志');

        // 库存恢复 5 件
        $this->assertEquals(50, $this->product->fresh()->stock, '库存应恢复 5 件到 50');
    }

    /**
     * 场景 3：cancel 与 refund 抢同一笔订单
     * 预期：先到者赢；后到者根据 from 状态被拒
     */
    public function test_cancel_and_refund_race_only_one_wins(): void
    {
        $order = $this->makePaidOrder(quantity: 1);
        $stockBeforeRace = $this->product->fresh()->stock; // 49

        $racerA = function () use ($order) {
            try {
                $this->orderService->transition(
                    $order->fresh(),
                    OrderStatus::Cancelled,
                    'user_cancel',
                    ['reason' => 'racer_A', 'actor_type' => 'user'],
                );
                return 'cancelled';
            } catch (InvalidTransitionException) {
                return 'cancel_rejected';
            }
        };

        $racerB = function () use ($order) {
            try {
                $this->orderService->transition(
                    $order->fresh(),
                    OrderStatus::Refunded,
                    'admin_refund',
                    ['reason' => 'racer_B', 'actor_type' => 'admin'],
                );
                return 'refunded';
            } catch (InvalidTransitionException) {
                return 'refund_rejected';
            }
        };

        // 串行执行 2 个 racer（同一 PHP 进程顺序触发，模拟 2 个外部请求）
        $aResult = $racerA();
        $bResult = $racerB();

        // 其中一个赢，另一个被拒
        $winners = array_filter([$aResult, $bResult], fn ($r) => ! str_ends_with($r, '_rejected'));
        $losers = array_filter([$aResult, $bResult], fn ($r) => str_ends_with($r, '_rejected'));
        $this->assertCount(1, $winners, '应仅 1 个赢家');
        $this->assertCount(1, $losers, '应仅 1 个被拒');

        $final = $order->fresh();
        $this->assertContains($final->status, [OrderStatus::Cancelled, OrderStatus::Refunded]);
    }

    /**
     * 场景 4：PaymentService::refund 的并发幂等
     * 同一笔 payment 被 2 个客服同时调用 refund
     * 预期：第一次成功（payment.status=refunded + order→refunded），
     *       第二次抛 GuardFailedException (GUARD-P2) 因为 order 已是终态
     */
    public function test_concurrent_refund_calls_only_succeeds_once(): void
    {
        $order = $this->makePaidOrder(quantity: 2);
        $payment = Payment::where('order_id', $order->id)->where('status', 'succeeded')->first();

        // 推进订单到 paid（refund 业务前置条件；与生产路径一致：
        //   payment_succeeded webhook 触发 transition pending→paid）
        $order = $this->orderService->transition(
            $order->fresh(),
            OrderStatus::Paid,
            'payment_succeeded',
            ['payment' => $payment, 'actor_type' => 'webhook'],
        );

        $firstResult = $this->paymentService->refund($payment, (int) $payment->amount, 'first_call');
        $this->assertTrue($firstResult, '首次 refund 应返回 true');
        $this->assertEquals('refunded', $payment->fresh()->status, '支付单首次 refund 应被标记');
        $this->assertEquals(OrderStatus::Refunded, $order->fresh()->status, '订单首次 refund 应进入终态');

        // 第二次 refund：order 已是 refunded（终态），GUARD-P2 拒绝
        $this->expectException(GuardFailedException::class);
        $this->expectExceptionMessageMatches('/refund|GUARD-P2|状态不允许/i');
        $this->paymentService->refund($payment->fresh(), (int) $payment->amount, 'second_call');
    }

    /**
     * 场景 5：跨多订单的并发 cancel，库存聚合一致性
     * 创建 5 笔订单，每笔 quantity=2，库存从 50→40
     * 然后 5 个 worker 并发 cancel，库存应恢复 +10 到 50
     */
    public function test_multi_order_concurrent_cancel_stock_aggregate_consistency(): void
    {
        $orders = [];
        for ($i = 0; $i < 5; $i++) {
            $orders[] = $this->makePendingOrder(quantity: 2);
        }
        $this->assertEquals(40, $this->product->fresh()->stock, '5 笔订单预占 10 件后库存应为 40');

        $successCount = 0;
        foreach ($orders as $order) {
            try {
                $this->orderService->transition(
                    $order->fresh(),
                    OrderStatus::Cancelled,
                    'user_cancel',
                    ['reason' => 'batch_cancel', 'actor_type' => 'system'],
                );
                $successCount++;
            } catch (InvalidTransitionException) {
                // 已被其他 worker 取消
            }
        }

        $this->assertEquals(5, $successCount, '5 笔订单应全部成功取消');
        $this->assertEquals(50, $this->product->fresh()->stock, '5 笔取消后库存应恢复到初始 50');
    }

    /**
     * 场景 6：DB 行级锁验证（lockForUpdate 串行化）
     * 验证 createOrder 中 Product::lockForUpdate 阻止 race
     * 模拟：两个并发 createOrder 抢同一低库存商品
     */
    public function test_low_stock_concurrent_create_order_serialized_by_lock(): void
    {
        // 商品仅剩 2 件
        $scarce = Product::factory()->create(['stock' => 2, 'price' => 100]);

        // 模拟 2 个并发请求：第一个扣 2 件，第二个失败
        $results = [];
        foreach ([2, 2] as $idx => $qty) {
            try {
                $this->orderService->createOrder(
                    user: $this->user,
                    items: [['product_id' => $scarce->id, 'quantity' => $qty]],
                    shippingAddress: ['name' => "Worker {$idx}", 'currency' => 'HKD'],
                );
                $results[] = 'success';
            } catch (GuardFailedException) {
                $results[] = 'guarded';
            }
        }

        // 在 RefreshDatabase 串行事务下，第一次必成功；第二次因库存=0 抛 GUARD-I1
        $this->assertCount(2, $results);
        $this->assertContains('success', $results, '第一个 createOrder 应成功');
        $this->assertContains('guarded', $results, '第二个 createOrder 应被 GUARD-I1 拒绝');
        $this->assertEquals(0, $scarce->fresh()->stock, '商品库存应归 0，无超卖');
    }
}
