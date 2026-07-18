<?php

namespace App\Providers;

use App\Models\Product;
use App\Policies\ProductPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        // Product Policy 注册
        Gate::policy(Product::class, ProductPolicy::class);

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

        // P0-2 启动断言：Stripe webhook secret 必须在所有非 local 环境配置
        // 防止 StripeWebhookController 静默放行（fail-closed）
        // 注意：跳过 `package:discover` 命令（此时 .env 还没创建，env() 取 config 默认值 production）
        if (! $this->isPackageDiscover()) {
            $this->assertStripeWebhookSecretConfigured();
        }
    }

    /**
     * 检测当前是否在 package:discover 命令执行中
     * （避免 composer install 触发的 autoload dump 阶段报错）
     */
    private function isPackageDiscover(): bool
    {
        if (! $this->app->runningInConsole()) {
            return false;
        }
        $argv = $_SERVER['argv'] ?? [];
        // 例如: ['artisan', 'package:discover']
        if (in_array('package:discover', $argv, true)) {
            return true;
        }
        return false;
    }

    /**
     * 启动期断言 Stripe webhook secret 已配置
     *
     * - local 环境允许空（开发者本地测试）
     * - testing 环境允许空（CI 跑测试用，签名已 mock）
     * - 其他所有环境（staging/production）必须配置，否则启动即崩
     */
    private function assertStripeWebhookSecretConfigured(): void
    {
        $appEnv = $this->app->environment();

        if (in_array($appEnv, ['local', 'testing'], true)) {
            return;
        }

        $secret = config('services.stripe.webhook_secret') ?: env('STRIPE_WEBHOOK_SECRET');
        if (! $secret) {
            throw new \RuntimeException(
                "Stripe webhook secret is not configured for environment [{$appEnv}]. "
                .'Set STRIPE_WEBHOOK_SECRET in your .env file. '
                .'Refusing to start to prevent insecure webhook handling (fail-closed).'
            );
        }
    }
}
