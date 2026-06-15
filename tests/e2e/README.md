# GreenBite MVP — Playwright E2E 测试计划

> Sprint 1 · QA Lead: delta
> 关联文档：`docs/bmad/test-strategy.md` `docs/bmad/api-contract.md` `docs/bmad/e2e-scenarios.md` `docs/bmad/edge-cases.md`

本目录用于存放 GreenBite 端到端（E2E）测试脚本与运行环境配置。Sprint 1 阶段以 **冒烟 + 核心 6 场景** 为主，使用 Playwright 1.45+ 在 Chromium 上跑通；Sprint 2 再扩展到多浏览器矩阵与可访问性 (a11y) 校验。

---

## 1. 环境依赖

| 依赖 | 版本 | 用途 |
|------|------|------|
| Node.js | 20.x LTS (>= 20.10) | Playwright Test Runner |
| npm | 10.x | 包管理 |
| @playwright/test | ^1.45.0 | 测试运行器 |
| Playwright Browsers | chromium 120+ | 默认浏览器；Sprint 2 扩展 webkit/firefox |
| Laravel App | >= 11.x | 被测后端 |
| PHP | 8.3 | composer 跑后端 |
| SQLite / MySQL | 任一 | 测试库（CI 默认 sqlite :memory:） |
| MailHog (可选) | latest | 邮件断言（Sprint 2） |

> 注意：vendor 目录未在仓库内提供，CI runner 必须先 `composer install`。

---

## 2. 安装与启动

### 2.1 安装

```bash
# 1. 安装后端依赖
composer install --no-interaction

# 2. 安装前端依赖 + Playwright
npm ci
npx playwright install --with-deps chromium

# 3. 准备测试库
cp .env.testing.example .env.testing
php artisan key:generate --env=testing
touch database/database.sqlite

# 4. 迁移 + 种子
php artisan migrate:fresh --seed --env=testing
```

### 2.2 启动被测应用

```bash
# 终端 A：启动 Laravel（端口 8000）
php artisan serve --env=testing

# 终端 B：启动 Playwright
npx playwright test
```

或使用 `concurrently` 一键启动：

```bash
npm run e2e
```

### 2.3 报告

```bash
# HTML 报告
npx playwright show-report

# 单文件 HTML
npx playwright test --reporter=html
```

---

## 3. 6 条核心 E2E 场景（Sprint 1）

> 完整路径：注册 → 问卷 → 菜单 → 加车 → 下单 → 支付 webhook 注入

| # | 场景 | 入口 | 预期 | 优先级 |
|---|------|------|------|--------|
| 1 | 新用户注册 | `POST /api/register` | 201 + 写入 users 表 | P0 |
| 2 | 填写问卷 | `POST /api/survey` | 200 + userPreferences 落库 | P0 |
| 3 | 获取今日菜单 | `GET /api/menu/today` | 200 + 命中 AI/降级 | P0 |
| 4 | 加入购物车 | `POST /api/cart` | 201 + cart_items 落库 | P0 |
| 5 | 提交订单 | `POST /api/orders` | 201 + orders.status=pending | P0 |
| 6 | Webhook 注入（pending→paid） | `POST /api/stripe/webhook` | 200 + 订单进入 paid | P0 |

### 场景脚本框架

```ts
// tests/e2e/specs/01-registration.spec.ts
import { test, expect } from '@playwright/test';

test.describe.serial('Sprint 1 核心流程', () => {
  test('1. 新用户注册', async ({ request, page }) => {
    const res = await request.post('/api/register', {
      data: {
        name: 'E2E Tester',
        email: `e2e_${Date.now()}@greenbite.hk`,
        password: 'password',
        password_confirmation: 'password',
        locale: 'zh-HK',
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.user.email).toContain('e2e_');
  });

  test('2. 填写问卷', async ({ request }) => {
    // 依赖 1：使用同一 session cookie
    // ...
    const res = await request.post('/api/survey', {
      data: { /* ... */ },
    });
    expect(res.status()).toBe(200);
  });

  // 3-6 同模式
});
```

---

## 4. 场景矩阵

| ID | 场景 | 前置 | 步骤 | 断言 | 失败时 P 等级 |
|----|------|------|------|------|---------------|
| E-01 | 注册 happy path | 无 | POST /api/register | 201 + body.user.id | P0 |
| E-02 | 注册失败（重复邮箱） | E-01 | POST /api/register (same email) | 422 + error.code=VALIDATION | P1 |
| E-03 | 登录 + 错误密码 | 用户存在 | POST /api/login (wrong pwd) | 401 + INVALID_CREDENTIALS | P0 |
| E-04 | 登录成功 | E-01 | POST /api/login | 200 + cookie | P0 |
| E-05 | /api/me 鉴权 | E-04 | GET /api/me | 200 + body.user.id | P0 |
| E-06 | 限流 60 req/min | — | 61 次 GET /api/products | 第 61 次 429 | P1 |
| E-07 | 问卷提交 | E-04 | POST /api/survey | 200 + userPreferences 落库 | P0 |
| E-08 | 今日菜单（降级） | E-07 | GET /api/menu/today | 200 + 非空 content | P0 |
| E-09 | 菜单 regenerate 限流 | E-08 | 4× POST /api/menu/regenerate | 第 4 次 422 GUARD-AI-RATE | P1 |
| E-10 | 加入购物车 | E-04 | POST /api/cart | 201 + cart_items 行 | P0 |
| E-11 | 提交订单（库存不足） | 库存=1，请求 qty=5 | POST /api/orders | 422 + GUARD-I1 | P0 |
| E-12 | 提交订单（happy） | E-10 | POST /api/orders | 201 + status=pending | P0 |
| E-13 | 支付 webhook（pending→paid） | E-12 | POST /api/stripe/webhook | 200 + status=paid | P0 |
| E-14 | 重复 webhook 幂等 | E-13 | 同 event_id × 3 | 仅 1 次 StripeWebhookEvent 落库 | P0 |
| E-15 | 取消订单（pending） | E-12 | POST .../cancel | 200 + status=cancelled | P0 |
| E-16 | 错误码格式 | — | 任意 4xx | body.error.code 存在 | P1 |

---

## 5. CI 集成位

### GitHub Actions 示例（`.github/workflows/e2e.yml`）

```yaml
name: e2e
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: 20 }
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }

      - name: Install backend
        run: composer install --no-interaction --prefer-dist

      - name: Install frontend + Playwright
        run: |
          npm ci
          npx playwright install --with-deps chromium

      - name: Prepare DB
        run: |
          touch database/database.sqlite
          php artisan key:generate --env=testing
          php artisan migrate:fresh --seed --env=testing

      - name: Serve Laravel (background)
        run: php artisan serve --env=testing &

      - name: Wait for app
        run: npx wait-on http://localhost:8000/up

      - name: Run E2E
        run: npx playwright test

      - name: Upload report
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: playwright-report
          path: playwright-report/
```

### 触发条件
- 每次 `push` 到 `main` / `develop`
- 每个 PR（合并前必经）
- 每日 UTC 00:00 cron 跑回归（避免静默退化）

---

## 6. 数据隔离

- **每 spec 独立用户**：使用 `Date.now()` / `randomUUID()` 生成唯一 email
- **每 spec 独立购物车**：避免跨用例污染
- **测试库独立**：`--env=testing` + `:memory:` SQLite
- **Webhook 注入**：Sprint 1 通过 `POST /api/stripe/webhook` 直送；Sprint 2 改为 Stripe CLI `stripe trigger`

---

## 7. 风险与边界

| 风险 | 缓解 |
|------|------|
| Gemini 限流 | 默认无 key 走 fallback；E2E 不依赖 AI 输出 |
| 真实 Stripe | 使用 mock provider（Sprint 1 占位） |
| 时间相关用例 | 注入 `Carbon::setTestNow` |
| 跨日 cron | E2E 不覆盖（见 `SubscriptionServiceTest`） |
| Webhook 重放 | `StripeWebhookEvent` UQ 已保证 100× 幂等 |

---

## 8. 后续 Sprint 路线

- Sprint 2：webhook 改为 Stripe CLI / Payme sandbox；加入 webkit + firefox 矩阵
- Sprint 2：a11y 校验（axe-core）
- Sprint 3：性能基线（k6 压测 100 RPS）
- Sprint 3：跨日 cron 走 E2E（用 `setTestNow` 跳日）
