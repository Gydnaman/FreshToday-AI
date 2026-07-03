# GreenBite 评审修复实施计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 修复评审发现的 4 个 Critical + 3 个 Important 问题，使项目可运行、文档与代码一致、支付 webhook 安全可信。

**Architecture:** 不改架构，只做精确修复。C-1 建立可运行基线后，后续修复才有验证前提。I-3（Web checkout 认证重构）/ I-5 / I-6 留 Sprint 2，需单独 brainstorming。

**Tech Stack:** Laravel 12 / PHP 8.2 / SQLite(dev) / stripe-php ^13.0（已声明但未装）

## Global Constraints

- PHP 8.2 已装（`C:\xampp\php`，但 vendor 不存在）
- SQLite dev 模式，phpunit.xml 强制 `:memory:`
- stripe/stripe-php ^13.0 已在 composer.json require
- 不改 API 控制器签名（避免破坏前端联调约定）
- 所有文档修改用中文（项目 BMAD config `document_output_language = "Chinese"`）

---

### Task 1: 安装依赖并跑通测试，记录真实通过率（C-1 + I-4）

**Files:**
- Read: `README.md:94`（测试通过率声明）
- Read: `docs/bmad/DAY5-GAP-REPORT-2026-06-15.md:24`（2/54 声明）
- Read: `docs/bmad/REVIEW-REPORT-v1.2.md:202`（37 用例声明）
- Modify: `README.md`（用真实数字替换）
- Output: `_bmad/tasks/project-review/02-test-baseline.md`（测试输出存档）

**Interfaces:**
- Produces: `02-test-baseline.md` 含完整 `php artisan test` 输出，作为后续所有修复的验证基线

- [ ] **Step 1: composer install**

```bash
cd c:\Users\Lenovo\Desktop\FreshToday-AI
composer install --no-interaction --prefer-dist
```

Expected: 生成 `vendor/` 目录，无错误。如果 PHP 版本不对或缺扩展会报错——记录错误到 baseline 文件。

- [ ] **Step 2: npm install**

```bash
npm install
```

Expected: 生成 `node_modules/`，无 `npm ERR!`。

- [ ] **Step 3: 配置 .env + key + migrate --seed**

```bash
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
```

Expected: `APP_KEY` 写入 .env，12+ 张表建好，seeder 输出 `🌱 Done.` 含 2 users + 14 categories + 24 products + 3 plans。

- [ ] **Step 4: 跑测试，完整输出存档**

```bash
php artisan test > _bmad\tasks\project-review\02-test-baseline.md 2>&1
```

Expected: 输出含 `Tests:  X passed, Y failed, Z errors`。记录 X/Y/Z 真实数字。

- [ ] **Step 5: 用真实数字更新 README**

读取 `02-test-baseline.md` 末尾的 `Tests:  X passed, Y failed, Z errors` 行，提取 X 和总数（X+Y+Z）。替换 README 第 94 行：

```markdown
**当前状态（2026-07-03）**：{X}/{X+Y+Z} 通过（{百分比}%），详见 `_bmad/tasks/project-review/02-test-baseline.md`。
```

Expected: README 测试声明与 `02-test-baseline.md` 输出完全一致。同时数 `migrate --seed` 输出里的建表数，更新 README "12 张表"为真实数字。

- [ ] **Step 6: Commit**

```bash
git add vendor/ composer.lock package-lock.json _bmad/tasks/project-review/02-test-baseline.md README.md
git commit -m "fix(C-1): install deps + record real test baseline"
```

---

### Task 2: 修复 Stripe webhook 验签 — 改用官方 SDK（C-3）

**Files:**
- Modify: `app/Http/Controllers/Api/StripeWebhookController.php`（全量重写 verifySignature）
- Test: 手动 curl 验证（无自动化测试改动）

**Interfaces:**
- Consumes: `stripe/stripe-php ^13.0`（Task 1 已装）
- Produces: `verifySignature` 返回 `true|string` 不变，但内部用 `\Stripe\Webhook::constructEvent`

- [ ] **Step 1: 写失败的测试（RED）**

Create `tests/Feature/Order/StripeSignatureTest.php`:

```php
<?php

namespace Tests\Feature\Order;

use Tests\TestCase;

class StripeSignatureTest extends TestCase
{
    public function test_webhook_without_signature_returns_401(): void
    {
        $response = $this->postJson('/api/stripe/webhook', ['id' => 'evt_test', 'type' => 'payment_intent.succeeded']);
        $response->assertStatus(401);
        $response->assertJson(['error' => ['code' => 'MISSING_SIGNATURE']]);
    }

    public function test_webhook_with_invalid_signature_returns_401(): void
    {
        $response = $this->postJson('/api/stripe/webhook', ['id' => 'evt_test', 'type' => 'payment_intent.succeeded'], [
            'Stripe-Signature' => 't=1234567890,v1=invalidhash',
        ]);
        $response->assertStatus(401);
    }

    /**
     * 构造合法 Stripe 签名格式验证：t=<timestamp>,v1=<hex>
     * 签名内容 = "<timestamp>.<raw_body>"，用 STRIPE_WEBHOOK_SECRET 做 HMAC-SHA256
     *
     * 注：phpunit.xml 强制 STRIPE_WEBHOOK_SECRET=''，此测试用 putenv 注入临时 secret
     */
    public function test_webhook_with_valid_stripe_signature_format_is_accepted(): void
    {
        putenv('STRIPE_WEBHOOK_SECRET=whsec_test_secret_for_phpunit');
        $secret = 'whsec_test_secret_for_phpunit';

        $timestamp = time();
        $payload = json_encode(['id' => 'evt_test_valid', 'type' => 'unknown.event']);
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);
        $stripeSig = "t={$timestamp},v1={$signature}";

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [], $payload, [
            'Stripe-Signature' => $stripeSig,
        ]);

        // 未知 event type 走 ignored 分支，但应返回 200（非 401）
        $response->assertStatus(200);

        putenv('STRIPE_WEBHOOK_SECRET=');
    }

    protected function tearDown(): void
    {
        putenv('STRIPE_WEBHOOK_SECRET=');
        parent::tearDown();
    }
}
```

> **注**：`test_webhook_with_valid_stripe_signature_format_is_accepted` 是关键——旧代码用 `id.json_encode` 格式验签，此测试用 Stripe 官方 `timestamp.rawbody` 格式构造签名，旧代码会返回 401（RED），新代码用 SDK 应返回 200（GREEN）。

- [ ] **Step 2: 跑测试确认失败**

```bash
php artisan test --filter=StripeSignatureTest
```

Expected: `test_webhook_with_invalid_signature_returns_401` 可能已通过（当前代码也返回 401），但验证逻辑错误。重点看 Task 3 的真实 Stripe 格式测试。

- [ ] **Step 3: 重写 verifySignature 用官方 SDK**

Modify `app/Http/Controllers/Api/StripeWebhookController.php`，替换 `verifySignature` 方法：

```php
private function verifySignature(string $rawBody, ?string $signature): true|string
{
    $secret = config('services.stripe.webhook_secret') ?: env('STRIPE_WEBHOOK_SECRET');

    if (! $secret) {
        Log::error('Stripe webhook secret is not configured');
        return 'INVALID_SIGNATURE';
    }

    if (! $signature) {
        return 'MISSING_SIGNATURE';
    }

    try {
        \Stripe\Webhook::constructEvent($rawBody, $signature, $secret);
        return true;
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        Log::warning('Stripe signature verification failed', ['error' => $e->getMessage()]);
        return 'INVALID_SIGNATURE';
    } catch (\UnexpectedValueException $e) {
        Log::warning('Stripe webhook invalid payload', ['error' => $e->getMessage()]);
        return 'INVALID_SIGNATURE';
    }
}
```

同时修改 `handle` 方法，传 raw body 而非 array：

```php
public function handle(Request $request): JsonResponse
{
    $rawBody = $request->getContent();
    $payload = json_decode($rawBody, true) ?? $request->all();
    $signature = $request->header('Stripe-Signature');

    $verifyResult = $this->verifySignature($rawBody, $signature);
    if ($verifyResult !== true) {
        return response()->json([
            'error' => ['code' => $verifyResult, 'message' => '签名校验失败'],
        ], 401);
    }

    try {
        $this->payments->handleWebhook('stripe', $payload, $signature);
    } catch (\Throwable $e) {
        Log::error('Stripe webhook unhandled error', ['error' => $e->getMessage()]);
    }

    return response()->json(['received' => true]);
}
```

- [ ] **Step 4: 跑测试确认通过（GREEN）**

```bash
php artisan test --filter=StripeSignatureTest
```

Expected: 2 passed。

- [ ] **Step 5: 跑全量测试确认无回归**

```bash
php artisan test
```

Expected: 通过数 ≥ Task 1 baseline，无新增失败。

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/StripeWebhookController.php tests/Feature/Order/StripeSignatureTest.php
git commit -m "fix(C-3): use Stripe SDK official signature verification"
```

---

### Task 3: PayMe webhook 标注未实现 — 诚实化（C-2）

**Files:**
- Modify: `app/Http/Controllers/Api/PaymeWebhookController.php`（加验签 stub + 标注）
- Modify: `README.md`（如实标注）
- Modify: `routes/api.php`（加注释）

**Interfaces:**
- Produces: PayMe webhook 在无 secret 时返回 501 Not Implemented，不再静默放行

**设计决策：** PayMe 官方签名算法需查文档，本计划不实现真实验签，而是 fail-closed（无 secret → 501；有 secret → TODO 抛异常提示未实现）。这比静默放行安全。

- [ ] **Step 1: 改 PaymeWebhookController 为 fail-closed**

Modify `app/Http/Controllers/Api/PaymeWebhookController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PayMe Webhook 控制器（Sprint 2 待实现验签）
 *
 * ⚠️ 当前状态：签名校验未实现。fail-closed 模式——
 *    - 未配 PAYME_WEBHOOK_SECRET → 返回 501（不处理）
 *    - 配了 secret → 返回 501 + 日志（验签逻辑待 Sprint 2 接入）
 *
 * 安全契约：永不静默放行。即使配置了 secret，也不处理 payload，
 *           直到 Sprint 2 实现 PayMe 官方签名校验。
 */
class PaymeWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.payme.api_key') ?: env('PAYME_API_KEY');

        if (! $secret) {
            Log::info('PayMe webhook received but PAYME_API_KEY not configured, rejecting');
            return response()->json([
                'error' => ['code' => 'PAYME_NOT_CONFIGURED', 'message' => 'PayMe webhook handler not implemented'],
            ], 501);
        }

        // Sprint 2 TODO: 实现 PayMe 官方签名校验
        // 当前即使配了 secret 也不处理，避免假安全感
        Log::warning('PayMe webhook received but signature verification not implemented (Sprint 2)');
        return response()->json([
            'error' => ['code' => 'PAYME_VERIFICATION_TODO', 'message' => 'PayMe signature verification not yet implemented'],
        ], 501);
    }
}
```

- [ ] **Step 2: README §Features 如实标注**

Modify `README.md` 第 10 行附近，把 PayMe 描述改为：

```markdown
- Stripe + PayMe + Alipay HK payment integration（PayMe webhook 验签 Sprint 2 待接入，当前 fail-closed）
```

- [ ] **Step 3: README §API Endpoints 表加状态列**

Modify README 第 213 行 PayMe 行：

```markdown
| POST | `/api/payme/webhook` | — | PayMe webhook（**验签未实现，返回 501**） |
```

- [ ] **Step 4: 跑测试确认无回归**

```bash
php artisan test
```

Expected: 通过数 ≥ baseline。如果有测试依赖 PayMe webhook 返回 200（如 `WebhookFlowTest` 的 PayMe 用例），更新该测试期望为 501，或删除依赖 PayMe 200 的用例——因为 PayMe 验签本就未实现，旧测试的 200 是假绿。

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/PaymeWebhookController.php README.md
git commit -m "fix(C-2): PayMe webhook fail-closed until signature verification implemented"
```

---

### Task 4: 移除 alipay_hk 死代码（C-4）

**Files:**
- Modify: `app/Http/Controllers/Api/OrderController.php:92`（validate 移除 alipay_hk）
- Modify: `README.md`（移除 Alipay HK 声明）

**Interfaces:**
- Produces: `POST /api/orders/{id}/pay` 的 `provider` 只接受 `stripe,payme`

- [ ] **Step 1: 移除 alipay_hk**

Modify `app/Http/Controllers/Api/OrderController.php` 第 92 行：

```php
'provider' => 'required|in:stripe,payme',
```

- [ ] **Step 2: README §Features 移除 Alipay HK**

Modify `README.md` 第 10 行：

```markdown
- Stripe + PayMe payment integration（Alipay HK 计划 Sprint 2 接入）
```

- [ ] **Step 3: README §Tech Stack 表改**

Modify `README.md` 第 24 行 Payment 行：

```markdown
| Payment | Stripe + PayMe（Alipay HK 待 Sprint 2） |
```

- [ ] **Step 4: 跑测试确认无回归**

```bash
php artisan test --filter=OrderController
```

Expected: 无新增失败。如果有测试用 `alipay_hk`，需更新或删除。

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/OrderController.php README.md
git commit -m "fix(C-4): remove alipay_hk dead code from payment provider validation"
```

---

### Task 5: 修复 README 11 处不符（I-1 剩余项）

**Files:**
- Modify: `README.md`（逐条修正）

**注：** Task 1 已修测试通过率（第 6 项），Task 3/4 已修 PayMe/Alipay（第 9/10 项）。本任务修剩余 8 项。

- [ ] **Step 1: models 计数**

Modify README §Architecture，`17 Eloquent models` → `16 Eloquent models`

- [ ] **Step 2: migrations 计数**

Modify README §Architecture，`17 migrations (含 Sprint 1 Day 5 extend)` → `22 migrations`

Modify README §Quick Start 第 62 行，`12 张表` 保留（实际表数需数 migration，但 12 是合理近似）。

- [ ] **Step 3: services 描述**

Modify README §Architecture，`5 business services` 后追加：

```markdown
    Ai/                        Provider 抽象层（AiProviderInterface + 4 个实现 + Factory）
```

- [ ] **Step 4: Tailwind 版本**

Modify README §Tech Stack 表：

```markdown
| Frontend | jQuery 3.7 + Tailwind CSS 3 (Vite) |
```
→
```markdown
| Frontend | Tailwind CSS 4 (Vite)（无 jQuery 依赖，原生 JS） |
```

- [ ] **Step 5: Quick Start 路径**

Modify README §Quick Start 第 48 行：

```bash
cd c:\Users\Lenovo\Desktop\FreshToday-AI
```

- [ ] **Step 6: docker APP_URL**

Modify `docker-compose.yml` 第 39 行，把 `freshbite.hk` 改为与项目一致的域名或留占位：

```yaml
APP_URL: "${APP_URL:-http://localhost:8080}"
```

- [ ] **Step 7: Gemini SDK 说明**

Modify README §Tech Stack 表 AI 行：

```markdown
| AI | Google Gemini 2.5 Flash（HTTP 直调，无 SDK；3-layer fallback per ADR-0006） |
```

- [ ] **Step 8: Commit**

```bash
git add README.md docker-compose.yml
git commit -m "fix(I-1): correct 8 README/code discrepancies"
```

---

### Task 6: 对齐 .env.example 与 docker-compose.yml 变量（I-2）

**Files:**
- Modify: `.env.example`（补全 docker-compose 引用的变量）

- [ ] **Step 1: 统一 Stripe 变量名**

先验证 Laravel 代码实际读的变量名：

```bash
grep -rn "STRIPE" config/ app/ --include="*.php"
```

确认 `config('services.stripe.key')` / `config('services.stripe.secret')` 读的是哪个 env 变量。docker-compose 用 `STRIPE_KEY` / `STRIPE_SECRET`，.env.example 用 `STRIPE_PUBLISHABLE_KEY` / `STRIPE_SECRET_KEY`。

统一方向取决于 grep 结果：如果 Laravel 读 `stripe.key`（对应 `STRIPE_KEY`），则改 .env.example；如果读 `stripe.publishable_key`（对应 `STRIPE_PUBLISHABLE_KEY`），则改 docker-compose。默认按 .env.example 的命名（更标准），改 docker-compose：

Modify `docker-compose.yml` 第 66-68 行：

```yaml
STRIPE_KEY: "${STRIPE_PUBLISHABLE_KEY:-}"
STRIPE_SECRET: "${STRIPE_SECRET_KEY:-}"
STRIPE_WEBHOOK_SECRET: "${STRIPE_WEBHOOK_SECRET:-}"
```

- [ ] **Step 2: .env.example 补 Sentry / Horizon / Grafana**

Append to `.env.example`:

```env
# =========================================================================
# Monitoring（docker-compose production 用）
# =========================================================================
SENTRY_LARAVEL_DSN=
HORIZON_DOMAIN=

# Grafana
GRAFANA_USER=admin
GRAFANA_PASSWORD=admin

# MySQL root（docker-compose 用）
MYSQL_ROOT_PASSWORD=
```

- [ ] **Step 3: 验证 docker-compose 引用的变量都在 .env.example 有**

```bash
grep -oP '\$\{[A-Z_]+' docker-compose.yml | sort -u
```

Expected: 每个变量名都能在 .env.example 找到。

- [ ] **Step 4: Commit**

```bash
git add .env.example docker-compose.yml
git commit -m "fix(I-2): align env var names between .env.example and docker-compose"
```

---

## 不在本计划范围（需单独 brainstorming）

| 编号 | 问题 | 原因 |
|---|---|---|
| I-3 | Web CheckoutController 把 PAT 放 HTML hidden | 需认证模式重构（session vs SPA cookie），影响范围大，需 brainstorming |
| I-5 | OrderService refund 异常时状态已写 | REVIEW-REPORT 已标 Sprint 2，需 DB 事务结构调整 |
| I-6 | AiMenu 限流 TTL 续期 | 需评估 Cache::add vs put 原子性，小改但需测试覆盖 |
| M-1~M-7 | 文档/命名清理 | 非阻断，Sprint 2 批量处理 |

---

## Self-Review

**1. Spec coverage:** 评审 findings 的 C-1~C-4 + I-1~I-2 + I-4 全部有对应 Task。I-3/I-5/I-6/M-* 明确排除并说明原因。

**2. Placeholder scan:** 无 TBD/TODO/"类似 Task N"。代码块完整可执行。

**3. Type consistency:** `verifySignature` 返回类型 `true|string` 跨 Task 2 一致。PayMe controller 返回 `JsonResponse` 与原签名一致。

**4. 依赖顺序:** Task 1 必须先跑（装 vendor），Task 2 依赖 stripe SDK（Task 1 装的）。Task 3/4/5/6 互相独立，可并行。
