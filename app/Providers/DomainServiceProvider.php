<?php

namespace App\Providers;

use App\Services\AiMenuService;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Support\ServiceProvider;

/**
 * 领域 Service 注入（Sprint 1）
 * 全部使用单例；带状态的服务（PaymentService）除外
 */
class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OrderService::class);
        $this->app->singleton(PaymentService::class);
        $this->app->singleton(SubscriptionService::class);
        $this->app->singleton(NotificationService::class);
        // AiMenuService 内部无状态，但保留单例以复用 HTTP client
        $this->app->singleton(AiMenuService::class);
    }

    public function boot(): void {}
}
