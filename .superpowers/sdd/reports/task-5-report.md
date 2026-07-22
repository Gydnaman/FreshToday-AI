# Task 5 Report: 可观测性埋点

**Status:** DONE

## 实现内容

1. 创建 `MetricsRecorder`（静态类）：
   - `recordGeneration(string $provider, string $status, int $latencyMs, int $tokens): void`
   - `getFailureRate(string $provider, int $windowSeconds = 3600): float`
   - 存储：Cache（Redis）— `ai:last_success:{provider}` / `ai:last_failure:{provider}` / `ai:metrics:{provider}:success|failure`
2. `AiMenuService::generateDailyMenuForUser()` 埋点：`$status = $tokens > 0 ? 'success' : 'failure'`（latency 暂时传 0，Task 7 加 Stopwatch）
3. 创建 `HealthController::ai()` 返回 `{provider, configured, last_success_at, last_failure_at, failure_rate_1h}`
4. `routes/api.php` 加公开路由 `GET /api/health/ai`

## TDD 证据

### RED
```bash
$ php vendor/bin/phpunit tests/Unit/Services/Ai/MetricsRecorderTest.php --no-coverage
ERRORS! Tests: 4, Assertions: 0, Errors: 4.
Error: Class "App\Services\Ai\MetricsRecorder" not found
```

### GREEN
```bash
$ php vendor/bin/phpunit tests/Unit/Services/Ai/MetricsRecorderTest.php tests/Feature/HealthCheckTest.php --no-coverage
OK (5 tests, 10 assertions)
```

### 全量回归
```bash
$ php vendor/bin/phpunit --no-coverage
OK (121 tests, 450 assertions)  # 基线 94 → 121 (+27: T1=8, T2=5, T3=4, T4=5, T5=5)
```

## 文件清单

- 创建 `app/Services/Ai/MetricsRecorder.php`
- 创建 `app/Http/Controllers/HealthController.php`
- 创建 `tests/Unit/Services/Ai/MetricsRecorderTest.php`
- 创建 `tests/Feature/HealthCheckTest.php`
- 修改 `app/Services/AiMenuService.php`（+use MetricsRecorder，+2 行埋点）
- 修改 `routes/api.php`（+1 路由）

## Commit

`feat(ai): add observability metrics and /health/ai endpoint` (6 files)

## Self-Review

- **完整性**：recordGeneration 记录成功/失败时间戳 + 计数器；getFailureRate 计算比率；health endpoint 返回 5 字段
- **一致性**：埋点位置在 `upsertMenu` 前，确保所有路径（含 fallback）都被记录
- **YAGNI**：未加 brief 之外功能（如 Prometheus 导出、分布式追踪、latency 实测）

## 顾虑

1. **latency 传 0 是 placeholder**：brief 明确说"Task 7 加 Stopwatch"，但 Task 7 是 FailoverProvider 不是 Stopwatch——这是 plan 的笔误，实际应该是后续加 latency 测量（可在 final review 记录为技术债务）。
2. **TTL 设置逻辑的竞态**：`recordGeneration` 中 `if (Cache::get(...) === 1)` 判断首次写入再 `put` 设 TTL，高并发下两个请求可能同时看到 `=== 1` 都 `put`（无害但冗余），或都看不到（计数器永不过期）。Redis `INCR` 原子，但 `GET` + `PUT` 不是。当前为简单版可接受，生产建议用 Lua 脚本或 `SET key value EX ttl NX` 模式。
3. **失败率窗口**：计数器 TTL 1h，但 `last_success/failure` TTL 24h，时间窗口不一致。这是 brief 设计（`TTL_SECONDS * 24`），遵循 brief。

## 报告路径

`d:/FreshToday-AI/.superpowers/sdd/reports/task-5-report.md`

---

## Fix Round 1（reviewer 2 Important + 2 Minor）

### Important #1: `getFailureRate` 的 `$windowSeconds` 死参数

**Fix:** 删掉 `$windowSeconds` 参数（YAGNI，brief 要求 1h 就够），签名简化为 `getFailureRate(string $provider): float`。

**File:** `app/Services/Ai/MetricsRecorder.php:93`

### Important #2: TTL 续期逻辑缺陷（窗口漂移）

**问题：** 旧逻辑 `increment` + `if (get === 1) put(1, TTL)` 只在计数器恰好为 1 时设 TTL，之后不再续期。导致失败率窗口从"第一次记录开始 1h"而非严格的"最近 1h"。

**Fix:** 改用 `Cache::add($key, 0, TTL)` + `Cache::increment($key)` 组合：
- `Cache::add` 只在 key 不存在时设置（原子）且带 TTL
- `increment` 在已存在 key 上只增不重置 TTL
- 抽出私有方法 `incrementWithTtl(string $key): void` 供 success/failure 复用（DRY）

**File:** `app/Services/Ai/MetricsRecorder.php:113-118`

### Minor #3: `latencyMs` 参数未使用

**Fix:** 加注释 `// latencyMs 暂未使用，后续加 Stopwatch 后接入 latency 统计`。

### Minor #4: HealthController 直接读 Cache 绕过抽象

**Fix:** `MetricsRecorder` 新增 `getLastSuccessAt(string $provider): ?string` 和 `getLastFailureAt(string $provider): ?string`，Controller 改为只调 MetricsRecorder，移除 `use Illuminate\Support\Facades\Cache`。

**File:** `app/Http/Controllers/HealthController.php:37-38` → `MetricsRecorder::getLastSuccessAt / getLastFailureAt`

### 测试证据

**覆盖测试：**
- `tests/Unit/Services/Ai/MetricsRecorderTest.php`（4 tests）
- `tests/Feature/HealthCheckTest.php`（1 test）

**命令：** `php vendor/bin/phpunit tests/Unit/Services/Ai/MetricsRecorderTest.php tests/Feature/HealthCheckTest.php --no-coverage`

**输出：** `OK (5 tests, 10 assertions)`

**全量回归：** `php vendor/bin/phpunit --no-coverage` → `OK (121 tests, 450 assertions)`
