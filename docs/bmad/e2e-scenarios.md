# GreenBite E2E 核心场景（5 个关键路径）

> 本文件定义 GreenBite（FreshToday-AI）**Sprint 0 必交付的 5 个核心 E2E 场景**，作为产品上线前的「关键路径 100% 覆盖」准出门槛。  
> 遵循 fdd-bmad-custom 框架 `bmad-qa-generate-e2e-tests` 输出的标准格式：**Given/When/Then + Playwright 伪代码**。  
> 详细测试策略见 `test-strategy.md`；边界情况见 `edge-cases.md`。

---

## 元信息（Metadata）

| 字段 | 值 |
| --- | --- |
| 文档名称 | GreenBite E2E 核心场景（E2E Scenarios） |
| 创建人 | qa-agent |
| 版本 | v1.0 |
| 创建日期 | 2026-06-12 |
| 适用范围 | Sprint 0 准入 / Sprint 3 上线准入 |
| 工具 | Playwright 1.4x（TypeScript，Node 20+） |
| 运行时机 | 每次 PR 合并 main、每日 Nightly、Sprint 末回归 |
| 关联 Skill | `bmad-qa-generate-e2e-tests`、`bmad-review-edge-case-hunter` |

---

## 通用约定（适用于全部 5 个场景）

### 0.1 测试夹具（Fixtures）

| 资源 | 准备方式 | 责任 |
| --- | --- | --- |
| 测试用户（邮箱 / 密码） | `database/factories/UserFactory` | QA |
| 真实商品 Seed | `SubscriptionPlanSeeder`、`ProductSeeder`（含 carbon_footprint） | QA |
| Stripe 测试卡 | `4242 4242 4242 4242`（成功）、`4000 0000 0000 0002`（拒付） | QA |
| Gemini API Mock | MSW / Playwright `route.fulfill` 拦截 `generativelanguage.googleapis.com` | QA |
| SMTP | Mailpit，断言 `mailpit.api.messages` 包含验证邮件 | QA |
| 时区 | `Asia/Hong_Kong` | 全局 |
| 浏览器 | Chromium（主）、Firefox（次）、WebKit（iOS 兼容抽样） | QA |

### 0.2 全局性能基线

| 路径 | P95 目标 | 监控工具 |
| --- | --- | --- |
| 注册 → 验证 | < 500 ms | Playwright `page.evaluate(performance.timing)` |
| 提交问卷 → 看到 AI 菜单 | < 1500 ms（含 Gemini Mock 返回 800ms） | 同上 |
| 加购 → 下单 → 订单详情 | < 800 ms | 同上 |
| Stripe Webhook 处理 | < 300 ms（服务端） | Laravel Telescope |
| i18n 切换首屏渲染 | < 200 ms | Playwright trace |

### 0.3 元素选择约定

所有 `data-testid` 由 dev 在 PR 中补齐；QA 严禁使用文本选择器（i18n 后会失效）。

---

## 场景 1：新用户首次体验（Onboarding Happy Path）

**业务价值**：决定首日激活率，是全链路最长的关键路径。

### 1.1 前置条件

| 类别 | 内容 |
| --- | --- |
| 账号 | 无（全新邮箱 `onboarding+{uuid}@greenbite.test`） |
| 数据库 | 干净（`migrate:fresh --seed`），存在 ≥ 8 个商品、3 个订阅计划 |
| Mock | Gemini API 返回 200 + 100 词菜单（Mock 响应 800ms）；SMTP Mailpit 启动 |
| 时区 | `Asia/Hong_Kong` |
| 浏览器 | Chromium Desktop 1280×800 |
| 货币 / 语言 | HKD / 英文 |

### 1.2 操作步骤与预期（Given / When / Then）

```gherkin
Given 用户访问首页
  And 未登录状态
When  点击 "Sign Up" 跳转到 /register
  And 填写 name="Test User"、email、password="GreenBite!2026"
  And 勾选 "I agree to terms" 并点击 [data-testid="register-submit"]
Then  收到 1 封验证邮件，主题 "Verify your GreenBite account"
  And 跳转 /verify-email 提示 "Check your inbox"

When  点击邮件中验证链接（或从 Mailpit 提取 token 拼 URL）
Then  跳转 /dashboard，顶部提示 "Email verified"
  And 用户记录 email_verified_at 不为 null

When  在 dashboard 点击 [data-testid="start-survey"]
Then  跳转 /survey，展示 6 道题

When  按顺序回答 6 题并点击 [data-testid="survey-submit"]
  | Q1 usage_purpose    | "Weight management"   |
  | Q2 dietary_habits   | "Vegetarian"          |
  | Q3 goals            | "Eat more greens"     |
  | Q4 cooking_skill    | "Beginner"            |
  | Q5 household_size   | "2"                   |
  | Q6 budget_hkd       | "300"                 |
Then  dashboard 显示 AI 菜单文本（≥ 50 字符，含至少 1 个推荐商品）
  And daily_menus 表新增 1 条记录
  And 响应 P95 < 1500ms

When  点击菜单中 [data-testid="add-to-cart-{productId}"] 至少 1 次
Then  cart 角标 +1，[data-testid="cart-count"]=1
  And 跳转 /cart 看到商品、单价、小计 = 单价

When  点击 [data-testid="checkout"]
  And 填写 Stripe 卡号 4242 4242 4242 4242、CVC 123、邮编 999077
  And 点击 [data-testid="pay-now"]
Then  跳转 /orders/{id}，订单状态 = "paid"
  And orders 表新增 1 条 status=paid
  And order_product 新增对应行
  And dashboard [data-testid="carbon-saved"] 增加该商品 carbon_footprint
  And 收到 1 封 "Order confirmed" 邮件
```

### 1.3 异常分支

| 场景 | 触发 | 预期 |
| --- | --- | --- |
| 邮箱已存在 | 重复注册 | 422 + 提示 "Email already taken" |
| 密码强度不足 | 密码 `123` | 422 + 实时校验提示 |
| 验证链接过期 | 等待 60 min 后点击 | 跳 /verify-email?expired=1，提示重发 |
| Gemini 超时 | Mock 500 | 显示 fallback 菜单，提示 "AI is busy, here is a healthy default" |
| 问卷漏答 | 跳过 Q3 | 422 + 高亮未答题 |
| 加购库存为 0 | 商品 stock=0 | 按钮 disabled，提示 "Out of stock" |
| 支付网络中断 | 断网 5s | 订单保持 pending，可重试 |

### 1.4 Playwright 伪代码

```typescript
import { test, expect } from '@playwright/test';
import { mockGemini, mockStripeSuccess, fetchVerificationLink } from '../fixtures';

test('S1: new user end-to-end onboarding', async ({ page, request }) => {
  // 1. 注册
  await page.goto('/');
  await page.getByTestId('nav-signup').click();
  const email = `onboarding+${Date.now()}@greenbite.test`;
  await page.getByTestId('register-name').fill('Test User');
  await page.getByTestId('register-email').fill(email);
  await page.getByTestId('register-password').fill('GreenBite!2026');
  await page.getByTestId('register-terms').check();
  await page.getByTestId('register-submit').click();

  // 2. 邮件验证
  await expect(page).toHaveURL(/\/verify-email/);
  const verifyUrl = await fetchVerificationLink(email);
  await page.goto(verifyUrl);
  await expect(page.getByTestId('verify-success')).toBeVisible();

  // 3. 6 题问卷
  await page.getByTestId('start-survey').click();
  await page.getByTestId('q1-weight').check();
  await page.getByTestId('q2-vegetarian').check();
  await page.getByTestId('q3-greens').check();
  await page.getByTestId('q4-beginner').check();
  await page.getByTestId('q5-size-2').check();
  await page.getByTestId('q6-budget').fill('300');
  const t0 = Date.now();
  await page.getByTestId('survey-submit').click();
  await expect(page.getByTestId('ai-menu-text')).toBeVisible();
  expect(Date.now() - t0).toBeLessThan(1500);

  // 4. 加购 + 下单
  await mockStripeSuccess(page);
  await page.getByTestId('add-to-cart-1').first().click();
  await expect(page.getByTestId('cart-count')).toHaveText('1');
  await page.goto('/cart');
  await page.getByTestId('checkout').click();
  await page.getByTestId('card-number').fill('4242424242424242');
  await page.getByTestId('card-cvc').fill('123');
  await page.getByTestId('card-zip').fill('999077');
  await page.getByTestId('pay-now').click();
  await expect(page).toHaveURL(/\/orders\/\d+/);
  await expect(page.getByTestId('order-status')).toHaveText('paid');
});
```

### 1.5 性能断言

- 问卷提交 → 看到菜单 P95 < 1500ms
- checkout → 订单详情 P95 < 800ms
- 整段旅程（注册到下单）总时长 < 30s（自动化）

---

## 场景 2：订阅续费（Subscription Renewal）

**业务价值**：LTV 核心，决定 MRR 留存。

### 2.1 前置条件

| 类别 | 内容 |
| --- | --- |
| 账号 | 已存在 `sub+{uuid}@greenbite.test`，已完成邮箱验证，已完成 6 题问卷 |
| 订阅计划 | Family Box（HKD 488 / 30 天）已 Seed |
| 支付方式 | 已绑定 Stripe Customer + 成功卡 |
| 状态 | 当前 `user_subscriptions.status = 'active'`，`end_date` = 今天 + 5 天 |
| Mock | Stripe `invoice.payment_succeeded` Webhook 模拟 |

### 2.2 操作步骤与预期

```gherkin
Given 用户已登录且订阅在 5 天内到期
When  访问 /subscriptions
Then  看到 3 个计划卡片，Family Box 显示 "Renewal in 5 days"

When  点击 Family Box [data-testid="plan-family"] 下的 [data-testid="choose-plan"]
  And 在确认弹窗点击 [data-testid="confirm-renewal"]
Then  Stripe 触发扣款（Mock），返回 invoice.payment_succeeded
  And 跳转 /subscriptions/success
  And user_subscriptions.end_date 延长 30 天
  And dashboard [data-testid="subscription-status"]="active"
  And dashboard [data-testid="next-billing-date"] 显示新到期日
  And 收到 "Subscription renewed" 邮件，含收据 PDF 链接

When  离开页面再回到 /dashboard
Then  状态保持一致（不丢 session）
```

### 2.3 异常分支

| 场景 | 触发 | 预期 |
| --- | --- | --- |
| 卡过期 | Stripe `card_expired` | 提示更新卡，订阅状态保持 active 至 end_date |
| 余额不足 | Stripe `insufficient_funds` | 弹窗提示，end_date 不变，发重试邮件 |
| 用户主动取消 | 点击 "Cancel" | 状态 → `canceled_at=now`，end_date 保留至原到期 |
| Webhook 重复 | 同一 event_id 投递 2 次 | 仅处理 1 次（幂等） |
| 续费时换计划 | 由 Solo 改 Family | 原订阅 canceled，新订阅按差额计费 |

### 2.4 Playwright 伪代码

```typescript
import { test, expect } from '@playwright/test';
import { loginAs, mockStripeWebhook } from '../fixtures';

test('S2: subscription renewal happy path', async ({ page, request }) => {
  await loginAs(page, 'sub-renewal@greenbite.test');
  await page.goto('/subscriptions');
  await expect(page.getByTestId('plan-family')).toContainText('Renewal in 5 days');

  await page.getByTestId('plan-family').getByTestId('choose-plan').click();
  await page.getByTestId('confirm-renewal').click();

  // Mock Stripe webhook asynchronous fire-and-forget
  await mockStripeWebhook('invoice.payment_succeeded', { amount: 48800 });

  await expect(page).toHaveURL(/\/subscriptions\/success/);
  await page.goto('/dashboard');
  await expect(page.getByTestId('subscription-status')).toHaveText('active');
});
```

### 2.5 性能断言

- 订阅页加载 P95 < 500ms
- 续费扣款 → success 页 P95 < 800ms
- Webhook 端到端处理 P95 < 300ms（服务端日志）

---

## 场景 3：碳足迹累计（Carbon Footprint Accumulation）

**业务价值**：差异化卖点 + ESG 营销素材。

### 3.1 前置条件

| 类别 | 内容 |
| --- | --- |
| 账号 | 已存在 `carbon+{uuid}@greenbite.test` |
| 历史订单 | 已存在 2 单 `status=paid`（product A 1.200kg + product B 0.800kg） |
| 仪表盘 | 初始 carbon_saved = 2.000kg |
| 商品 | Seed 含 carbon_footprint（kg CO2e）字段 |

### 3.2 操作步骤与预期

```gherkin
Given 仪表盘显示 carbon_saved = 2.000kg
When  在 /catalog 选择 product C（carbon_footprint=0.500kg）并下单成功
Then  订单 status=paid，dashboard carbon_saved = 2.500kg
  And carbon 数字保留 3 位小数，附 "+0.5 kg this month" 提示

When  再下单 product D（1.250kg）
Then  carbon_saved = 3.750kg
  And dashboard 折线图（若有）新增 2 个数据点
  And 单位标签 "kg CO₂e saved" 显示正确
  And 累计 3 单后，触发 "Earth Champion" 徽章弹窗
```

### 3.3 异常分支

| 场景 | 触发 | 预期 |
| --- | --- | --- |
| 商品 carbon_footprint = null | 新商品未填 | 累计时跳过该商品，记 warning 日志 |
| 订单取消并退款 | status → refunded | 扣减对应 carbon_saved |
| 同一订单重复计算 | Webhook 重放 | 幂等，不重复加 |
| 数值溢出 | 1 万单 × 10kg = 10万kg | 仍能精确（DECIMAL(10,3)）；UI 用千分位 |
| 跨年累计 | 1 月下单 12 月看 | 仅累计 status=paid 的订单，跨年度不清零 |

### 3.4 Playwright 伪代码

```typescript
import { test, expect } from '@playwright/test';
import { loginAs, placeOrder } from '../fixtures';

test('S3: carbon footprint accumulates correctly', async ({ page }) => {
  await loginAs(page, 'carbon-test@greenbite.test');
  await page.goto('/dashboard');
  await expect(page.getByTestId('carbon-saved')).toHaveText('2.000 kg CO₂e saved');

  // 订单 3
  await placeOrder(page, { productId: 3, expectedCarbon: 0.500 });
  await page.goto('/dashboard');
  await expect(page.getByTestId('carbon-saved')).toHaveText('2.500 kg CO₂e saved');

  // 订单 4
  await placeOrder(page, { productId: 4, expectedCarbon: 1.250 });
  await page.goto('/dashboard');
  await expect(page.getByTestId('carbon-saved')).toHaveText('3.750 kg CO₂e saved');
  await expect(page.getByTestId('badge-earth-champion')).toBeVisible();
});
```

### 3.5 性能断言

- 仪表盘渲染 P95 < 500ms
- 单订单 carbon 累加在 service 层耗时 < 50ms（用 Benchmark 测）

---

## 场景 4：支付失败回滚（Payment Failure & Rollback）

**业务价值**：资金安全 + 库存一致性，**P0 场景**。

### 4.1 前置条件

| 类别 | 内容 |
| --- | --- |
| 账号 | 已存在 `payfail+{uuid}@greenbite.test` |
| 购物车 | 含 product X（stock=10），product Y（stock=1，最后一件） |
| 库存 | 初始 product Y.stock = 1 |
| Mock | Stripe 返回 `card_declined`（卡号 `4000 0000 0000 0002`） |

### 4.2 操作步骤与预期

```gherkin
Given 购物车含 X(2 件) + Y(1 件)，库存 Y=1
When  在 /checkout 输入拒付卡 4000 0000 0000 0002 并提交
Then  Stripe 返回 charge.failed
  And 订单 status 保持 "pending"（未变成 paid）
  And product X.stock 保持 8（仍扣 2）、product Y.stock 保持 0
  And 页面提示 "Payment declined. Your items are reserved for 15 minutes."
  And 收到 1 封 "Payment failed, please retry" 邮件
  And dashboard 不出现该订单的 carbon_saved 增加

When  15 分钟后订单仍 pending
Then  自动任务把订单置为 "expired"
  And X.stock 回滚到 10、Y.stock 回滚到 1
  And 购物车可重新下单
```

### 4.3 异常分支

| 场景 | 触发 | 预期 |
| --- | --- | --- |
| 部分扣款成功 | Stripe 5xx 后半成功 | 订单 status=failed，发起自动 refund |
| 库存竞态 | 同时 2 用户抢最后 1 件 | DB 行锁 + 唯一约束，1 成功 1 失败 |
| 用户刷新页面 | 失败页 F5 | 看到相同 pending 订单，可换卡重试 |
| Webhook 延迟 5 min | Stripe 异步通知 | 等待回调，不在同步路径直接失败 |
| 自动 expired 期间用户重试成功 | 边界 | 重新创建订单，原 expired 订单保留审计 |

### 4.4 Playwright 伪代码

```typescript
import { test, expect } from '@playwright/test';
import { loginAs, mockStripeDecline, getStock } from '../fixtures';

test('S4: payment failure keeps order pending and rolls back stock on expiry', async ({ page, request }) => {
  await loginAs(page, 'payfail-test@greenbite.test');
  await mockStripeDecline(page);

  // 假设 X.stock=10, Y.stock=1
  const xBefore = await getStock('X');
  const yBefore = await getStock('Y');

  await page.goto('/cart');
  await page.getByTestId('checkout').click();
  await page.getByTestId('card-number').fill('4000000000000002');
  await page.getByTestId('pay-now').click();

  await expect(page.getByTestId('payment-declined')).toBeVisible();
  const orderId = await page.getByTestId('order-id').textContent();

  // DB 断言
  const order = await request.get(`/api/test/orders/${orderId}`);
  expect(order.status()).toBe(200);
  expect((await order.json()).data.status).toBe('pending');

  // 等 15 分钟后过期（测试中用 time travel）
  await request.post('/api/test/tick', { minutes: 16 });
  const yAfter = await getStock('Y');
  expect(yAfter).toBe(yBefore); // 回滚
});
```

### 4.5 性能断言

- 失败订单回滚 DB 事务 < 200ms
- 自动 expired 任务在 15min ± 1min 内执行

---

## 场景 5：i18n 切换（i18n Locale Switching）

**业务价值**：香港市场多语言（en / zh-HK / zh-CN）体验。

### 5.1 前置条件

| 类别 | 内容 |
| --- | --- |
| 账号 | 已存在 `i18n+{uuid}@greenbite.test`，登录态 |
| 语言文件 | `resources/lang/{en,zh-HK,zh-CN}/` 完整 |
| 默认语言 | en |
| 时区 | `Asia/Hong_Kong` |

### 5.2 操作步骤与预期

```gherkin
Given 用户当前语言 = en，访问 /dashboard
  And 看到 "Today's AI Menu"、"Carbon Saved"、"Subscription" 全部英文
When  点击右上角 [data-testid="lang-switch"] 选择 "繁體中文"
Then  页面无刷新切换 locale = zh-HK
  And 上述三个 key 变为繁中：「今日 AI 菜單」、「已減碳」、「訂閱」
  And 货币格式 HKD $488.00 → HK$488.00 或 NT$?（依 locale 决定）
  And 日期格式 06/12/2026 → 2026年6月12日
  And 数字千分位 1,000 → 1,000（不变）

When  再次切换到 "简体中文"
Then  全部文案变为简中，「今日 AI 菜单」、「已减碳」、「订阅」
  And Cookie / LocalStorage 中 locale=zh-CN
  And F5 刷新后语言保持

When  切换回 English
Then  文案恢复英文，货币、日期恢复

When  直接访问 /zh-HK/dashboard
Then  URL locale 优先级 > Cookie > Header
```

### 5.3 异常分支

| 场景 | 触发 | 预期 |
| --- | --- | --- |
| 缺失 key | 新加的 en key 未翻译 | 后端 fallback 到 en，前端标黄警告 |
| 货币溢出 | 价格 100,000,000 | 千分位 + locale 正确显示 |
| RTL 浏览器 | 测试 ar | 暂不在 v1 范围，仅记录 |
| emoji 渲染 | AI 菜单含 🥬🌱 | Win / Mac / iOS 全部能渲染 |
| 翻译长度差异 | 德语比英语长 3 倍 | UI 不破版（自动省略号） |
| 用户自定义 locale | URL `?lang=fr` | 落到 fallback en，不报错 |

### 5.4 Playwright 伪代码

```typescript
import { test, expect } from '@playwright/test';
import { loginAs } from '../fixtures';

test('S5: i18n switching covers all UI + currency + date', async ({ page }) => {
  await loginAs(page, 'i18n-test@greenbite.test');
  await page.goto('/dashboard');

  // 英文
  await expect(page.getByTestId('menu-title')).toHaveText("Today's AI Menu");

  // 切到繁中
  await page.getByTestId('lang-switch').selectOption('zh-HK');
  await expect(page.getByTestId('menu-title')).toHaveText('今日 AI 菜單');
  await expect(page.locator('.price').first()).toContainText('HK$');

  // 切到简中
  await page.getByTestId('lang-switch').selectOption('zh-CN');
  await expect(page.getByTestId('menu-title')).toHaveText('今日 AI 菜单');

  // 刷新保持
  await page.reload();
  await expect(page.getByTestId('menu-title')).toHaveText('今日 AI 菜单');

  // 切回英文
  await page.getByTestId('lang-switch').selectOption('en');
  await expect(page.getByTestId('menu-title')).toHaveText("Today's AI Menu");
});
```

### 5.5 性能断言

- 语言切换首屏渲染 P95 < 200ms（本地资源切换，无网络）
- 翻译 JSON 资源大小 < 50KB / locale（gzip 前）

---

## 跨场景回归清单（Regressions to Cover After Any Change）

| 模块 | 触发 | 必须回归的 E2E 场景 |
| --- | --- | --- |
| `AiMenuService` 改动 | Gemini 提示词、fallback 逻辑 | S1 |
| `Orders` / `Checkout` 改动 | 支付、库存、状态机 | S1, S4 |
| `Subscription` 改动 | 续费、状态、Webhook | S2 |
| `CarbonCalculator` 改动 | 累计逻辑、徽章 | S3 |
| `lang/` 资源改动 | 新增 key、改文案 | S5 |
| `User` / Auth 改动 | 登录态、验证 | S1, S2, S3, S5 |

---

## Playwright 配置建议（节选）

```typescript
// playwright.config.ts
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/E2E/specs',
  timeout: 30_000,
  expect: { timeout: 5_000 },
  reporter: [['html', { open: 'never' }], ['junit', { outputFile: 'reports/junit.xml' }]],
  use: {
    baseURL: process.env.BASE_URL ?? 'http://localhost:8000',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    timezoneId: 'Asia/Hong_Kong',
    locale: 'en-HK',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
    { name: 'webkit',  use: { ...devices['Desktop Safari'] } },
  ],
});
```

---

*文档结束。共 5 个 E2E 场景，均需在 Sprint 0 末前绿，作为 Sprint 3 上线准出门槛。*
