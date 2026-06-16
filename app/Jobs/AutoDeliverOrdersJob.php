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
 * 定时任务：shipped 超 7 天无异常 → delivered
 */
class AutoDeliverOrdersJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function handle(OrderService $orderService): int
    {
        $count = 0;
        Order::where('status', OrderStatus::Shipped->value)
            ->where('updated_at', '<=', now()->subDays(7))
            ->chunkById(100, function ($orders) use ($orderService, &$count) {
                foreach ($orders as $order) {
                    try {
                        $orderService->transition(
                            $order,
                            OrderStatus::Delivered,
                            'auto_deliver_7d',
                            ['actor_type' => 'system'],
                        );
                        $count++;
                    } catch (\Throwable $e) {
                        Log::warning('Auto deliver failed', [
                            'order_id' => $order->id, 'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $count;
    }
}
