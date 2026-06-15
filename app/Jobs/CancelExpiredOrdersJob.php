<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 定时任务：30min 未支付订单自动取消
 * 详见 docs/bmad/order-state-machine.md §7
 */
class CancelExpiredOrdersJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 1;

    public function handle(OrderService $orderService): int
    {
        $count = 0;
        Order::where('status', OrderStatus::Pending->value)
            ->where('placed_at', '<=', now()->subMinutes(30))
            ->chunkById(100, function ($orders) use ($orderService, &$count) {
                foreach ($orders as $order) {
                    try {
                        $orderService->transition(
                            $order,
                            OrderStatus::Cancelled,
                            'expired_timeout',
                            ['reason' => 'payment_timeout_30min', 'actor_type' => 'system'],
                        );
                        $count++;
                    } catch (\Throwable $e) {
                        Log::warning('Cancel expired order failed', [
                            'order_id' => $order->id, 'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
        return $count;
    }
}
