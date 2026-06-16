<?php

namespace App\Jobs;

use App\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FulfillSubscriptionsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SubscriptionService $service): int
    {
        return $service->fulfillDueSubscriptions();
    }
}
