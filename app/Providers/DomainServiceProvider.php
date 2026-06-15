<?php

namespace App\Providers;

use App\Services\Ai\AiProviderFactory;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\AiMenuService;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Support\ServiceProvider;

/**
 * 领域 Service 注入（Sprint 2）
 *
 * 变更：
 *  - 新增 AiProviderInterface 单例绑定（由 AiProviderFactory 解析）
 *  - AiMenuService 现在依赖 AiProviderInterface，自动获得构造注入
 */
class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OrderService::class);
        $this->app->singleton(PaymentService::class);
        $this->app->singleton(SubscriptionService::class);
        $this->app->singleton(NotificationService::class);

        // AI Provider 工厂：单例绑定接口
        $this->app->singleton(AiProviderInterface::class, function () {
            return AiProviderFactory::make();
        });

        // AiMenuService 由 Laravel 容器自动注入 AiProviderInterface
        $this->app->singleton(AiMenuService::class);
    }

    public function boot(): void {}
}
