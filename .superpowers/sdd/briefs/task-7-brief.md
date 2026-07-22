### Task 7: FailoverProvider — 主备熔断（可选，P2）

**Note:** 本 Task 为 P2 增强，可在前 6 个 Task 上线后独立实施。提供多 Provider 运行时灾备 + Circuit Breaker 熔断。

**Files:**
- Create: `app/Services/Ai/Providers/FailoverProvider.php`
- Create: `app/Services/Ai/CircuitBreaker.php`
- Modify: `app/Services/Ai/AiProviderFactory.php::make()`
- Test: `tests/Unit/Services/Ai/Providers/FailoverProviderTest.php`
- Test: `tests/Unit/Services/Ai/CircuitBreakerTest.php`

**Interfaces:**
- Consumes: `AiProviderInterface` 实例列表
- Produces:
  - `FailoverProvider implements AiProviderInterface`，内部按顺序尝试多个 Provider
  - `CircuitBreaker::isOpen(string $provider): bool`、`recordFailure()`、`recordSuccess()`

- [ ] **Step 1: 写 CircuitBreaker 失败测试**

```php
<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_circuit_closed_initially(): void
    {
        $breaker = new CircuitBreaker;

        $this->assertFalse($breaker->isOpen('gemini'));
    }

    public function test_circuit_opens_after_threshold_failures(): void
    {
        $breaker = new CircuitBreaker(failureThreshold: 3, windowSeconds: 300);

        for ($i = 0; $i < 3; $i++) {
            $breaker->recordFailure('gemini');
        }

        $this->assertTrue($breaker->isOpen('gemini'));
    }

    public function test_circuit_closes_on_success(): void
    {
        $breaker = new CircuitBreaker(failureThreshold: 3, windowSeconds: 300);

        for ($i = 0; $i < 3; $i++) {
            $breaker->recordFailure('gemini');
        }
        $this->assertTrue($breaker->isOpen('gemini'));

        $breaker->recordSuccess('gemini');
        $this->assertFalse($breaker->isOpen('gemini'));
    }

    public function test_circuit_resets_after_timeout(): void
    {
        $breaker = new CircuitBreaker(failureThreshold: 2, windowSeconds: 1);

        $breaker->recordFailure('gemini');
        $breaker->recordFailure('gemini');
        $this->assertTrue($breaker->isOpen('gemini'));

        sleep(2); // 等待熔断窗口过期

        $this->assertFalse($breaker->isOpen('gemini'), '熔断器应在窗口过期后自动关闭');
    }
}
```

- [ ] **Step 2: 实现 CircuitBreaker**

创建 `app/Services/Ai/CircuitBreaker.php`：

```php
<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;

/**
 * AI Provider 熔断器
 *
 * 职责：某 Provider 连续失败达到阈值时，临时熔断（跳过调用），避免雪崩。
 *
 * 状态机：
 *  - Closed（正常）：失败计数 < 阈值
 *  - Open（熔断）：失败计数 >= 阈值，持续 windowSeconds 秒
 *  - Half-Open（试探）：窗口过期后下一次调用允许通过，成功则 Closed，失败则重新 Open
 *
 * 存储：Redis（Cache facade）
 *  - circuit:{provider}:failures  失败计数（带 TTL）
 *  - circuit:{provider}:opened_at  熔断开启时间戳
 */
class CircuitBreaker
{
    public function __construct(
        private readonly int $failureThreshold = 5,
        private readonly int $windowSeconds = 600, // 10 分钟
    ) {}

    public function isOpen(string $provider): bool
    {
        $failures = (int) Cache::get("circuit:{$provider}:failures", 0);

        if ($failures < $this->failureThreshold) {
            return false;
        }

        $openedAt = Cache::get("circuit:{$provider}:opened_at");
        if (! $openedAt) {
            // 达到阈值但未记录 opened_at，立即熔断
            Cache::put("circuit:{$provider}:opened_at", now()->timestamp, $this->windowSeconds);

            return true;
        }

        // 检查是否已过熔断窗口
        if (now()->timestamp - $openedAt > $this->windowSeconds) {
            $this->reset($provider);

            return false;
        }

        return true;
    }

    public function recordFailure(string $provider): void
    {
        $key = "circuit:{$provider}:failures";
        $failures = Cache::increment($key);

        if ($failures === 1) {
            Cache::put($key, 1, $this->windowSeconds);
        }

        if ($failures >= $this->failureThreshold) {
            Cache::put("circuit:{$provider}:opened_at", now()->timestamp, $this->windowSeconds);
        }
    }

    public function recordSuccess(string $provider): void
    {
        $this->reset($provider);
    }

    private function reset(string $provider): void
    {
        Cache::forget("circuit:{$provider}:failures");
        Cache::forget("circuit:{$provider}:opened_at");
    }
}
```

- [ ] **Step 3: 实现 FailoverProvider**

创建 `app/Services/Ai/Providers/FailoverProvider.php`：

```php
<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\CircuitBreaker;
use App\Services\Ai\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Log;

/**
 * 多 Provider 灾备装饰器
 *
 * 职责：按优先级顺序尝试多个 Provider，任一成功即返回；全部失败返回空。
 *       配合 CircuitBreaker 跳过熔断的 Provider。
 *
 * 使用：
 *   $failover = new FailoverProvider([
 *       new DeepseekProvider($config),
 *       new OpenAiProvider($config),
 *       new GeminiProvider($config),
 *   ], new CircuitBreaker);
 */
class FailoverProvider implements AiProviderInterface
{
    /**
     * @param  array<int,AiProviderInterface>  $providers
     */
    public function __construct(
        private readonly array $providers,
        private readonly CircuitBreaker $breaker = new CircuitBreaker,
    ) {}

    public function name(): string
    {
        // 返回当前生效的 Provider 名（第一个非熔断的）
        foreach ($this->providers as $provider) {
            if (! $this->breaker->isOpen($provider->name())) {
                return $provider->name();
            }
        }

        return 'failover'; // 全部熔断
    }

    public function isConfigured(): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->isConfigured()) {
                return true;
            }
        }

        return false;
    }

    public function generate(array $preferences, array $products): array
    {
        foreach ($this->providers as $provider) {
            $name = $provider->name();

            // 跳过熔断的 Provider
            if ($this->breaker->isOpen($name)) {
                Log::info("FailoverProvider: skip {$name} (circuit open)");

                continue;
            }

            // 跳过未配置的 Provider
            if (! $provider->isConfigured()) {
                continue;
            }

            try {
                [$content, $tokens, $json] = $provider->generate($preferences, $products);

                if ($content !== '') {
                    $this->breaker->recordSuccess($name);

                    return [$content, $tokens, $json];
                }

                // Provider 返回空 = 失败
                $this->breaker->recordFailure($name);
            } catch (\Throwable $e) {
                Log::warning("FailoverProvider: {$name} exception", ['error' => $e->getMessage()]);
                $this->breaker->recordFailure($name);
            }
        }

        // 全部失败
        return ['', 0, null];
    }
}
```

- [ ] **Step 4: 修改 AiProviderFactory 支持 Failover 模式**

`app/Services/Ai/AiProviderFactory.php::make()` 在文件末尾改为：

```php
use App\Services\Ai\CircuitBreaker;
use App\Services\Ai\Providers\FailoverProvider;

    public static function make(): AiProviderInterface
    {
        $config = config('ai');

        // 新增：Failover 模式开关
        if ($config['failover_enabled'] ?? false) {
            return self::buildFailover($config);
        }

        // 原有逻辑：显式指定
        $explicit = $config['default'] ?? null;
        if ($explicit) {
            $provider = self::build($explicit);
            if ($provider !== null) {
                return $provider;
            }
        }

        // 原有逻辑：按 auto_detect_order 探测
        foreach ($config['auto_detect_order'] ?? [] as $name) {
            $provider = self::build($name);
            if ($provider !== null) {
                return $provider;
            }
        }

        return new NullProvider;
    }

    private static function buildFailover(array $config): AiProviderInterface
    {
        $providers = [];
        foreach ($config['failover_order'] ?? ['deepseek', 'openai', 'gemini'] as $name) {
            $provider = self::build($name);
            if ($provider !== null) {
                $providers[] = $provider;
            }
        }

        if (empty($providers)) {
            return new NullProvider;
        }

        return new FailoverProvider($providers, new CircuitBreaker(
            failureThreshold: $config['circuit_breaker']['failure_threshold'] ?? 5,
            windowSeconds: $config['circuit_breaker']['window_seconds'] ?? 600,
        ));
    }
```

- [ ] **Step 5: 更新 config/ai.php**

在文件末尾 `return` 数组中加：

```php
    /*
     * Failover 模式：启用后按 failover_order 顺序尝试多个 Provider
     * 配合 CircuitBreaker 跳过熔断的 Provider
     */
    'failover_enabled' => (bool) env('AI_FAILOVER_ENABLED', false),

    'failover_order' => ['deepseek', 'openai', 'gemini'],

    'circuit_breaker' => [
        'failure_threshold' => (int) env('AI_CB_FAILURE_THRESHOLD', 5),
        'window_seconds' => (int) env('AI_CB_WINDOW_SECONDS', 600),
    ],
```

- [ ] **Step 6: 写 FailoverProvider 测试**

```php
<?php

namespace Tests\Unit\Services\Ai\Providers;

use App\Services\Ai\CircuitBreaker;
use App\Services\Ai\Providers\FailoverProvider;
use App\Services\Ai\Providers\NullProvider;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FailoverProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_failover_returns_first_successful_provider(): void
    {
        $primary = new class extends NullProvider {
            public function name(): string { return 'primary'; }
            public function isConfigured(): bool { return true; }
            public function generate(array $p, array $pr): array { return ['', 0, null]; } // 失败
        };

        $secondary = new class extends NullProvider {
            public function name(): string { return 'secondary'; }
            public function isConfigured(): bool { return true; }
            public function generate(array $p, array $pr): array { return ['menu', 100, null]; } // 成功
        };

        $failover = new FailoverProvider([$primary, $secondary], new CircuitBreaker);
        [$content, $tokens] = $failover->generate([], []);

        $this->assertSame('menu', $content);
        $this->assertSame(100, $tokens);
    }

    public function test_failover_skips_circuit_open_provider(): void
    {
        $breaker = new CircuitBreaker(failureThreshold: 1, windowSeconds: 600);

        $primary = new class extends NullProvider {
            public function name(): string { return 'primary'; }
            public function isConfigured(): bool { return true; }
            public function generate(array $p, array $pr): array { return ['primary_menu', 50, null]; }
        };

        // 手动熔断 primary
        $breaker->recordFailure('primary');

        $secondary = new class extends NullProvider {
            public function name(): string { return 'secondary'; }
            public function isConfigured(): bool { return true; }
            public function generate(array $p, array $pr): array { return ['secondary_menu', 100, null]; }
        };

        $failover = new FailoverProvider([$primary, $secondary], $breaker);
        [$content] = $failover->generate([], []);

        $this->assertSame('secondary_menu', $content, '应跳过熔断的 primary');
    }

    public function test_failover_returns_empty_when_all_fail(): void
    {
        $failover = new FailoverProvider([new NullProvider, new NullProvider]);
        [$content, $tokens] = $failover->generate([], []);

        $this->assertSame('', $content);
        $this->assertSame(0, $tokens);
    }
}
```

- [ ] **Step 7: 跑测试 + 全量回归**

```bash
php vendor/bin/phpunit tests/Unit/Services/Ai/ --no-coverage
php vendor/bin/phpunit tests/ --no-coverage
```

预期：全部通过

- [ ] **Step 8: Commit**

```bash
git add app/Services/Ai/CircuitBreaker.php app/Services/Ai/Providers/FailoverProvider.php app/Services/Ai/AiProviderFactory.php config/ai.php tests/Unit/Services/Ai/
git commit -m "feat(ai): add FailoverProvider with circuit breaker for multi-provider resilience"
```

---

