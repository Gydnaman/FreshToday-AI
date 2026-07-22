### Task 5: 可观测性埋点（指标 + 健康检查）

**Files:**
- Modify: `app/Services/AiMenuService.php`（加 metric 埋点）
- Create: `app/Http/Controllers/HealthController.php`
- Modify: `routes/api.php`（加 `/health/ai` 路由）
- Create: `app/Services/Ai/MetricsRecorder.php`
- Test: `tests/Feature/HealthCheckTest.php`

**Interfaces:**
- Consumes: 无
- Produces:
  - `MetricsRecorder::recordGeneration(string $provider, string $status, int $latencyMs, int $tokens): void`
  - `MetricsRecorder::getFailureRate(string $provider, int $windowSeconds = 3600): float`
  - `GET /health/ai` 返回 `{provider, configured, last_success_at, last_failure_at, failure_rate_1h}`

- [ ] **Step 1: 写 MetricsRecorder 失败测试**

创建 `tests/Unit/Services/Ai/MetricsRecorderTest.php`：

```php
<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\MetricsRecorder;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MetricsRecorderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_record_generation_stores_success_timestamp(): void
    {
        MetricsRecorder::recordGeneration('gemini', 'success', 250, 100);

        $this->assertNotNull(Cache::get('ai:last_success:gemini'));
    }

    public function test_record_generation_stores_failure_timestamp(): void
    {
        MetricsRecorder::recordGeneration('gemini', 'failure', 0, 0);

        $this->assertNotNull(Cache::get('ai:last_failure:gemini'));
    }

    public function test_failure_rate_calculation(): void
    {
        // 10 次成功，2 次失败
        for ($i = 0; $i < 10; $i++) {
            MetricsRecorder::recordGeneration('openai', 'success', 200, 100);
        }
        for ($i = 0; $i < 2; $i++) {
            MetricsRecorder::recordGeneration('openai', 'failure', 0, 0);
        }

        $rate = MetricsRecorder::getFailureRate('openai');

        $this->assertEqualsWithDelta(2 / 12, $rate, 0.01);
    }

    public function test_failure_rate_zero_when_no_data(): void
    {
        $this->assertSame(0.0, MetricsRecorder::getFailureRate('deepseek'));
    }
}
```

- [ ] **Step 2: 实现 MetricsRecorder**

创建 `app/Services/Ai/MetricsRecorder.php`：

```php
<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;

/**
 * AI 指标记录器
 *
 * 职责：记录每次生成的成功/失败/延迟/tokens，供健康检查和告警用。
 *
 * 存储：Redis（Cache facade）
 *  - ai:last_success:{provider}  最后成功时间戳
 *  - ai:last_failure:{provider}  最后失败时间戳
 *  - ai:metrics:{provider}:success  成功计数（1h 滑动窗口）
 *  - ai:metrics:{provider}:failure  失败计数
 *
 * 生产建议：高峰期可换成 Prometheus pushgateway，这里先用 Cache 实现简单版。
 */
class MetricsRecorder
{
    private const TTL_SECONDS = 3600; // 1h 滑动窗口

    public static function recordGeneration(string $provider, string $status, int $latencyMs, int $tokens): void
    {
        $now = now()->toIso8601String();

        if ($status === 'success') {
            Cache::put("ai:last_success:{$provider}", $now, self::TTL_SECONDS * 24);
            Cache::increment("ai:metrics:{$provider}:success");
        } else {
            Cache::put("ai:last_failure:{$provider}", $now, self::TTL_SECONDS * 24);
            Cache::increment("ai:metrics:{$provider}:failure");
        }

        // 设置 TTL（首次写入时）
        if (Cache::get("ai:metrics:{$provider}:success") === 1) {
            Cache::put("ai:metrics:{$provider}:success", 1, self::TTL_SECONDS);
        }
        if (Cache::get("ai:metrics:{$provider}:failure") === 1) {
            Cache::put("ai:metrics:{$provider}:failure", 1, self::TTL_SECONDS);
        }
    }

    public static function getFailureRate(string $provider, int $windowSeconds = 3600): float
    {
        $success = (int) Cache::get("ai:metrics:{$provider}:success", 0);
        $failure = (int) Cache::get("ai:metrics:{$provider}:failure", 0);
        $total = $success + $failure;

        return $total > 0 ? $failure / $total : 0.0;
    }
}
```

- [ ] **Step 3: 在 AiMenuService 埋点**

`app/Services/AiMenuService.php::generateDailyMenuForUser()` 在 `upsertMenu` 前加：

```php
use App\Services\Ai\MetricsRecorder;

// 在 return $this->upsertMenu(...) 之前：
$status = $tokens > 0 ? 'success' : 'failure';
MetricsRecorder::recordGeneration($this->provider->name(), $status, 0, $tokens);
```

（latency 暂时传 0，Task 7 加 Stopwatch）

- [ ] **Step 4: 写 HealthController + 路由**

创建 `app/Http/Controllers/HealthController.php`：

```php
<?php

namespace App\Http\Controllers;

use App\Services\Ai\MetricsRecorder;
use App\Services\Ai\Contracts\AiProviderInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    public function __construct(private readonly AiProviderInterface $provider) {}

    public function ai(): JsonResponse
    {
        $providerName = $this->provider->name();

        return response()->json([
            'provider' => $providerName,
            'configured' => $this->provider->isConfigured(),
            'last_success_at' => Cache::get("ai:last_success:{$providerName}"),
            'last_failure_at' => Cache::get("ai:last_failure:{$providerName}"),
            'failure_rate_1h' => MetricsRecorder::getFailureRate($providerName),
        ]);
    }
}
```

`routes/api.php` 在公开路由组加：

```php
Route::get('/health/ai', [\App\Http\Controllers\HealthController::class, 'ai']);
```

- [ ] **Step 5: 写 HealthCheck Feature 测试**

创建 `tests/Feature/HealthCheckTest.php`：

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_ai_endpoint_returns_provider_status(): void
    {
        Cache::flush();

        $response = $this->getJson('/api/health/ai');

        $response->assertOk()
            ->assertJsonStructure([
                'provider',
                'configured',
                'last_success_at',
                'last_failure_at',
                'failure_rate_1h',
            ]);
    }
}
```

- [ ] **Step 6: 跑测试 + 回归**

```bash
php vendor/bin/phpunit tests/Unit/Services/Ai/MetricsRecorderTest.php tests/Feature/HealthCheckTest.php --no-coverage
php vendor/bin/phpunit tests/ --no-coverage
```

预期：全部通过

- [ ] **Step 7: Commit**

```bash
git add app/Services/Ai/MetricsRecorder.php app/Http/Controllers/HealthController.php routes/api.php app/Services/AiMenuService.php tests/
git commit -m "feat(ai): add observability metrics and /health/ai endpoint"
```

---

