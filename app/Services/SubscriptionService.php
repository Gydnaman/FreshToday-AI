<?php

namespace App\Services;

use App\Exceptions\GuardFailedException;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 订阅服务（Day 3-4）
 * 详见 docs/bmad/api-contract.md §2.7
 */
class SubscriptionService
{
    public function subscribe(
        User $user,
        SubscriptionPlan $plan,
        Carbon $startDate,
        bool $autoRenew = true,
    ): UserSubscription {
        $existing = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
        if ($existing) {
            throw new GuardFailedException('GUARD-SUB', '已有活跃订阅', [
                'existing_id' => $existing->id,
            ]);
        }

        return DB::transaction(function () use ($user, $plan, $startDate, $autoRenew) {
            return UserSubscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $startDate->copy()->addDays($plan->duration)->toDateString(),
                'next_fulfillment_at' => $startDate->toDateString(),
                'status' => 'active',
                'auto_renew' => $autoRenew,
            ]);
        });
    }

    public function cancel(UserSubscription $sub, string $reason): UserSubscription
    {
        if ($sub->status === 'cancelled') {
            throw new GuardFailedException('GUARD-SUB', '订阅已取消', ['id' => $sub->id]);
        }
        $sub->update([
            'status' => 'cancelled',
            'end_date' => $sub->next_fulfillment_at ?? $sub->end_date,
            'cancel_reason' => $reason,
        ]);

        // 审计：订阅状态变化只更新 user_subscriptions 表本身的字段
        // （ADR-0005 §2.4：订阅状态不在 7 态订单 SSOT 内；Sprint 2 引入 subscription_status_logs
        //  时再独立建表，本期不混用 order_status_logs 以避免 nullable order_id schema 改动）

        return $sub;
    }

    /**
     * 队列任务入口：为到期订阅生成履约订单
     *
     * @see docs/bmad/architecture.md ADR-005
     */
    public function fulfillDueSubscriptions(): int
    {
        $count = 0;
        UserSubscription::where('status', 'active')
            ->whereNotNull('next_fulfillment_at')
            ->whereDate('next_fulfillment_at', '<=', now())
            ->chunk(100, function ($subs) use (&$count) {
                foreach ($subs as $sub) {
                    try {
                        $this->fulfillOne($sub);
                        $count++;
                    } catch (\Throwable $e) {
                        \Log::error('Subscription fulfillment failed', [
                            'sub_id' => $sub->id, 'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $count;
    }

    private function fulfillOne(UserSubscription $sub): void
    {
        $plan = $sub->subscriptionPlan;
        $items = $plan->products->mapWithKeys(fn ($p) => [
            $p->id => ['quantity' => $p->pivot->quantity ?? 1, 'price' => $p->price],
        ])->toArray();

        if (empty($items)) {
            throw new \RuntimeException("Plan {$plan->id} has no products");
        }

        $order = app(OrderService::class)->createOrder(
            $sub->user,
            collect($items)->map(fn ($v, $k) => ['product_id' => $k, 'quantity' => $v['quantity']])->values()->toArray(),
            $sub->user->default_shipping_address ?? null,
            null,
            $sub->id,
        );

        // 滚动 next_fulfillment_at
        $cycleDays = match ($plan->cycle ?? 'weekly') {
            'weekly' => 7,
            'biweekly' => 14,
            'monthly' => 30,
            default => $plan->duration,
        };
        $sub->update([
            'next_fulfillment_at' => Carbon::parse($sub->next_fulfillment_at)->addDays($cycleDays)->toDateString(),
        ]);
    }
}
