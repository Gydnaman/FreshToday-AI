<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 定义 'api' 限流器（Laravel 12 default throttle:api 调用）
        // 见 docs/bmad/sprint-1-backlog.md NFR §2.3 + docs/bmad/monitoring-and-runbooks.md §11
        // 默认 60 req/min/IP（生产可按 IP 维度调高到 600）
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Webhook 专属限流器（备用，路由当前直接用 throttle:10000,1 数字形式）
        // 见 ADR-0004（webhook 幂等性）
        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute(10000);
        });
    }
}
