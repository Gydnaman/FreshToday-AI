<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\GuardFailedException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\UserCoupon;
use Illuminate\Support\Facades\DB;

/**
 * 订单状态机服务（Day 1-2 核心）
 *
 * 单一入口：transition()
 * - 守卫校验（GUARD-G0/G1/G2/P1/P3/I1/I2/I3）
 * - 状态转移（事务内）
 * - 审计日志
 * - 副作用：库存预占/释放、支付绑定、退款触发
 *
 * 详见 docs/bmad/order-state-machine.md §6
 */
class OrderService
{
    /**
     * 状态转移表：from → to (合法)
     * 与附录 A 跨文档状态对照表一致
     */
    private const TRANSITIONS = [
        'pending' => ['paid', 'cancelled'],
        'paid' => ['processing', 'refunded'],
        'processing' => ['shipped', 'refunded'],
        'shipped' => ['delivered', 'refunded'],
        'delivered' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
    ];

    public function transition(
        Order $order,
        OrderStatus $to,
        string $trigger,
        array $context = [],
        ?User $actor = null,
    ): Order {
        $from = $order->status;
        $order->refresh();

        // GUARD-G1 状态合法性
        $allowedTo = self::TRANSITIONS[$from->value] ?? [];
        if (! in_array($to->value, $allowedTo, true)) {
            throw new InvalidTransitionException($order, $from, $to, $trigger);
        }

        // GUARD-G0 订单归属
        if ($actor !== null && $order->user_id !== $actor->id && ! ($actor->is_admin ?? false)) {
            throw new GuardFailedException('GUARD-G0', '无权操作此订单', [
                'order_user_id' => $order->user_id,
                'actor_user_id' => $actor->id,
            ]);
        }

        // 终态不变量 #3
        if ($from->isTerminal() && ! ($from === OrderStatus::Delivered && $to === OrderStatus::Refunded)) {
            throw new InvalidTransitionException($order, $from, $to, $trigger);
        }

        return DB::transaction(function () use ($order, $from, $to, $trigger, $context, $actor) {
            // 进入 paid：检查是否有匹配支付单
            if ($to === OrderStatus::Paid) {
                $this->guardPaidTransition($order, $context);
            }

            // 进入 cancelled：释放库存
            if ($to === OrderStatus::Cancelled) {
                $this->releaseStock($order);
                $order->cancelled_at = now();
                $order->cancel_reason = $context['reason'] ?? null;
            }

            // 进入 shipped：仅写 tracking_no（库存预占 → 出库的语义已由 createOrder 时的
            // lockForUpdate + decrement('stock') 完成；无需二次扣减，避免与实物库存脱钩）
            if ($to === OrderStatus::Shipped) {
                $order->tracking_no = $context['tracking_no'] ?? $order->tracking_no;
            }

            // 进入 delivered
            if ($to === OrderStatus::Delivered) {
                $order->delivered_at = now();
            }

            // 进入 refunded：写 refund 审计字段 + 释放库存（终态，库存退还）
            //   ⚠️ 不再调 PaymentService::refund()，避免与 PaymentService 互相调用形成递归
            //   PaymentService::refund() 自己负责 payment.status + 调本 transition
            if ($to === OrderStatus::Refunded) {
                $order->refunded_at = now();
                $order->refund_reason = $context['reason'] ?? null;
                $this->releaseStock($order);
            }

            $order->status = $to;
            if ($to === OrderStatus::Paid && empty($order->paid_at)) {
                $order->paid_at = now();
            }
            $order->save();

            // 审计日志（不变量 #2）
            OrderStatusLog::create([
                'order_id' => $order->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'trigger' => $trigger,
                'actor_user_id' => $actor?->id,
                'actor_type' => $context['actor_type'] ?? 'system',
                'context' => $context,
                'created_at' => now(),
            ]);

            return $order->fresh();
        });
    }

    public function canTransition(Order $order, OrderStatus $to, string $trigger): bool
    {
        $allowed = self::TRANSITIONS[$order->status->value] ?? [];

        return in_array($to->value, $allowed, true) && ! $order->status->isTerminal();
    }

    public function getAllowedTransitions(Order $order): array
    {
        return self::TRANSITIONS[$order->status->value] ?? [];
    }

    /**
     * 创建订单（含库存预占 GUARD-I1）
     *
     * @throws GuardFailedException
     */
    public function createOrder(
        User $user,
        array $items,                  // [['product_id' => 1, 'quantity' => 2], ...]
        ?array $shippingAddress = null,
        ?string $couponCode = null,
        ?int $userSubscriptionId = null,
    ): Order {
        return DB::transaction(function () use ($user, $items, $shippingAddress, $couponCode, $userSubscriptionId) {
            // 预占库存
            $orderProducts = [];
            $total = 0;
            foreach ($items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);
                $qty = (int) $item['quantity'];
                if ($qty <= 0) {
                    throw new GuardFailedException('GUARD-I1', '数量必须为正', ['product_id' => $product->id]);
                }
                if (! $product->hasStock($qty)) {
                    throw new GuardFailedException('GUARD-I1', '库存不足', [
                        'product_id' => $product->id,
                        'requested' => $qty,
                        'available' => $product->stock,
                    ]);
                }
                $product->decrement('stock', $qty);
                $orderProducts[$product->id] = ['quantity' => $qty, 'price' => $product->price];
                $total += (float) $product->price * $qty;
            }

            // 优惠券
            $discount = 0;
            $userCoupon = null;
            if ($couponCode) {
                $coupon = Coupon::where('code', $couponCode)
                    ->where('is_active', 1)->first();
                if (! $coupon) {
                    throw new GuardFailedException('GUARD-COUPON', '优惠券无效', ['code' => $couponCode]);
                }
                if (! $coupon->isValidForAmount($total)) {
                    throw new GuardFailedException('GUARD-COUPON', '优惠券不满足最低金额', [
                        'min_order_amount' => $coupon->min_order_amount,
                    ]);
                }
                $discount = $coupon->discountAmount($total);
                $total -= $discount;
                $userCoupon = UserCoupon::where('user_id', $user->id)
                    ->where('coupon_id', $coupon->id)
                    ->where('status', 'claimed')->first();
            }

            $order = Order::create([
                'user_id' => $user->id,
                'order_no' => $this->generateOrderNo(),
                'status' => OrderStatus::Pending,
                'total_price' => $total,
                'discount_amount' => $discount,
                'shipping_address' => $shippingAddress,
                'user_subscription_id' => $userSubscriptionId,
                'placed_at' => now(),
            ]);
            $order->products()->sync($orderProducts);

            if ($userCoupon) {
                $userCoupon->update(['order_id' => $order->id, 'status' => 'used', 'used_at' => now()]);
            }

            return $order->fresh(['products', 'user']);
        });
    }

    /** GUARD-P1 / GUARD-P3 */
    private function guardPaidTransition(Order $order, array $context): void
    {
        $payment = $context['payment'] ?? Payment::where('order_id', $order->id)
            ->where('status', 'succeeded')
            ->latest()->first();

        if (! $payment) {
            throw new GuardFailedException('GUARD-P1', '订单未找到匹配的成功支付单', [
                'order_id' => $order->id,
            ]);
        }
        if ((float) $payment->amount !== (float) $order->total_price) {
            throw new GuardFailedException('GUARD-P1', '支付金额与订单金额不一致', [
                'payment_amount' => $payment->amount,
                'order_total' => $order->total_price,
            ]);
        }
        if ($payment->currency !== 'HKD' || ($order->shipping_address['currency'] ?? 'HKD') !== 'HKD') {
            throw new GuardFailedException('GUARD-P3', '币种不一致（仅支持 HKD）');
        }
    }

    private function releaseStock(Order $order): void
    {
        foreach ($order->products as $product) {
            $product->increment('stock', $product->pivot->quantity);
        }
    }

    private function generateOrderNo(): string
    {
        return 'GB'.now()->format('Ymd').str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    }
}
