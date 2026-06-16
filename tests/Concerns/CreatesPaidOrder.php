<?php

namespace Tests\Concerns;

use App\Enums\Currency;
use App\Models\Order;
use App\Models\Payment;

/**
 * 订单 + 匹配支付单工厂 trait（ADR-007 P1-1 模式 B）
 *
 * 共享 4 Service/Feature 测试的"创建已支付订单"逻辑：
 * 1. createOrder 创建 pending 订单
 * 2. 注入一笔 succeeded 支付单（amount 匹配、currency HKD）
 * 3. 返回 fresh order（已 attached products）
 *
 * 与 OrderServiceTestCase 抽象基类配合使用（trait 需要 $this->user / $this->product）。
 */
trait CreatesPaidOrder
{
    protected function makeOrderWithPayment(): Order
    {
        $order = $this->service->createOrder(
            user: $this->user,
            items: [['product_id' => $this->product->id, 'quantity' => 2]],
            shippingAddress: ['name' => 'Tester', 'currency' => Currency::HKD->value],
        );

        Payment::create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'provider_txn_id' => 'pi_test_'.$order->id,
            'amount' => $order->total_price,
            'currency' => Currency::HKD->value,
            'status' => 'succeeded',
            'paid_at' => now(),
        ]);

        return $order->fresh();
    }
}
