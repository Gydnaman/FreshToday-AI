# FreshToday-AI / GreenBite 项目评审 Findings 报告

> **日期**：2026-07-03
> **方法**：BMAD adversarial-review + edge-case-hunter + code-review + Superpowers verification-before-completion
> **评审基线**：实际代码静态审查（vendor 未安装，测试未实跑）
> **项目声明**：README.md + docs/bmad/REVIEW-REPORT-v1.2.md（自评 9.21/10 Pass）

---

## §0 评审结论（一页式）

| 维度 | 评审得分 | 项目自评 | 差距 |
|---|---|---|---|
| 文档代码一致性 | **3/10** | 9.21 | README 与实际严重不符 |
| 安全（webhook/支付） | **4/10** | 9.40 | PayMe 零验签，Stripe 验签非官方格式 |
| 配置完整性 | **5/10** | 9.25 | .env 与 docker-compose 变量错位 |
| 架构代码质量 | **7/10** | 9.10 | OrderService 状态机扎实，但有已知债务 |
| 测试可信度 | **2/10** | 8.95 | vendor 不存在，39/54 vs 2/54 矛盾未解 |
| **综合** | **4.2/10** | **9.21** | **不通过** |

**判定：❌ 不通过**。项目自评 9.21/10 与实际状态严重脱节。核心问题是"代码已写但从未真正跑通"，文档描述的是理想状态而非现实。

---

## §1 Critical（阻断级，必须立即修）

### C-1：项目无法运行 — vendor 目录不存在

**证据**：执行 `php artisan test` 报 `Failed to open stream: vendor/autoload.php`。README §Quick Start 说 `bash scripts/dev.sh install` 会装依赖，但实际从未执行。

**影响**：所有"测试通过率"声明（README 39/54、Day5 Gap Report 2/54、REVIEW-REPORT-v1.2 的 37 用例）**全部未经验证**。项目处于"代码已写但从未运行"状态。

**违反**：Superpowers `verification-before-completion` 铁律——"NO COMPLETION CLAIMS WITHOUT FRESH VERIFICATION EVIDENCE"。REVIEW-REPORT-v1.2 §7.4 自己也承认"v1.2 复评时仍未实际跑通，仅静态代码评审"，但综合评分仍给 8.95/10。

**修复**：跑 `composer install && npm install && php artisan migrate --seed && php artisan test`，用实际输出替换所有测试声明。

---

### C-2：PayMe Webhook 零验签 — 任何人可伪造支付

**证据**：`app/Http/Controllers/Api/PaymeWebhookController.php:15-28`

```php
public function handle(Request $request): JsonResponse
{
    $payload = $request->all();
    $signature = $request->header('X-Payme-Signature');  // 取了但没用
    // Sprint 2 接入：复用 StripeWebhookEvent 表
    try {
        $this->payments->handleWebhook('payme', $payload, $signature);
    } ...
}
```

`$signature` 取了但**从未校验**，直接传给 PaymentService。README §Features 宣称"PayMe webhook 签名验签"，API 表宣称"PayMe webhook（签名验签）"——**完全是虚假声明**。

**影响**：攻击者构造 `POST /api/payme/webhook` 带 `payment_intent.succeeded` payload 即可把任意订单标记为已付款。这是直接的财务损失漏洞。

**违反**：BMAD adversarial-review——宣称的安全契约不存在。

**修复**：实现 PayMe 官方签名校验，或在 README/API 表如实标注"PayMe Sprint 2 待接入，当前不验签"。

---

### C-3：Stripe Webhook 验签格式错误 — 真实 Stripe 永远验签失败

**证据**：`app/Http/Controllers/Api/StripeWebhookController.php:70-74`

```php
// 简化 HMAC-SHA256 校验（生产建议用 \Stripe\Webhook::constructEvent）
$signedPayload = ($payload['id'] ?? '').'.'.json_encode($payload);
$expected = hash_hmac('sha256', $signedPayload, $secret);
return hash_equals($expected, $signature) ? true : 'INVALID_SIGNATURE';
```

Stripe 官方签名格式是 `t=<timestamp>,v1=<hex>`，签名内容是 `<timestamp>.<raw_body>`，不是 `id.json_encode(payload)`。**即使配了正确 secret，真实 Stripe webhook 也会返回 401**。

注释自己写了"生产建议用 `\Stripe\Webhook::constructEvent`"，且 `stripe/stripe-php ^13.0` 已在 composer.json——SDK 都装了就是不用。

**影响**：生产环境 Stripe 支付回调全部失败，订单永远卡在 pending。AppServiceProvider 的启动断言只检查 secret 是否配置，不检查验签逻辑是否正确。

**修复**：改用 `\Stripe\Webhook::constructEvent($rawBody, $signature, $secret)`，并读取 raw body（不是 `$request->all()`）。

---

### C-4：alipay_hk 支付是死代码 — 接受参数但无实现

**证据**：`app/Http/Controllers/Api/OrderController.php:92`

```php
'provider' => 'required|in:stripe,payme,alipay_hk',
```

但 `PaymentService::callGatewayCreate` 只返回 mock URL，无 alipay 分支。搜索全 app 目录，`alipay` 仅此一处出现。

README §Features 宣称"Stripe + PayMe + Alipay HK 支付集成"，docs/bmad/OPENAPI-AUDIT §5 D1 标记为"🔴 静默生产风险"——**已知 15 天未修**。

**影响**：用户选 alipay_hk 会创建一条 `provider='alipay_hk'` 的 Payment 记录，但永远不会有回调把它推进到 succeeded。

**修复**：要么实现 alipay_hk，要么从 validate 移除，同步修 README。

---

## §2 Important（重要，Sprint 1 内必修）

### I-1：README 与实际代码 11 处不符

| # | README 声明 | 实际 | 证据 |
|---|---|---|---|
| 1 | 17 Eloquent models | 16 个 | `app/Models/` 目录 |
| 2 | 17 migrations（12 张表） | 22 个 migration 文件 | `database/migrations/` |
| 3 | 5 business services | 5 个 + Ai/ 子目录（含 Factory + 3 Provider） | `app/Services/` |
| 4 | Tailwind CSS 3 | Tailwind 4.2.4 | `package.json` |
| 5 | jQuery 3.7 | package.json 无 jQuery 依赖 | `package.json` |
| 6 | 39/54 测试通过 | Day5 Gap Report 说 2/54；vendor 不存在无法验证 | 矛盾 |
| 7 | `cd d:/FreshToday-AI` | 实际在 `c:\Users\Lenovo\Desktop\FreshToday-AI` | README §Quick Start |
| 8 | docker APP_URL `freshbite.hk` | 项目名 GreenBite / FreshToday-AI | `docker-compose.yml:39` |
| 9 | "PayMe webhook 签名验签" | 零验签（见 C-2） | `PaymeWebhookController` |
| 10 | "Alipay HK 支付集成" | 死代码（见 C-4） | `OrderController` |
| 11 | Google Gemini 2.5 Flash | composer.json 无 Gemini SDK，靠 HTTP 直调 | `composer.json` |

> 注：README 说 26 API 端点，实际确实是 26（含 2 个仅 testing/staging 的调试端点），此项数字一致但未说明调试端点性质，归为 M-2。

**违反**：BMAD code-review 清单——文档与代码一致性是基础要求。

---

### I-2：.env.example 与 docker-compose.yml 变量名错位

**证据**：
- docker-compose 引用 `STRIPE_KEY`、`STRIPE_SECRET`、`SENTRY_LARAVEL_DSN`、`HORIZON_DOMAIN`
- .env.example 只有 `STRIPE_SECRET_KEY`、`STRIPE_PUBLISHABLE_KEY`、`STRIPE_WEBHOOK_SECRET`，无 Sentry/Horizon

**影响**：按 docker-compose 部署时，Stripe key 名不匹配导致支付配置丢失；Sentry/Horizon 环境变量未在 .env.example 模板化，新人部署会漏配。

**修复**：统一变量名，.env.example 补全 docker-compose 引用的所有变量。

---

### I-3：Web CheckoutController 把 Sanctum token 放进 HTML hidden field

**证据**：`app/Http/Controllers/Web/CheckoutController.php:25-26,68`

```php
// web 用户必须把 token 放进 form hidden field（gb_token）
$token = (string) $request->input('gb_token', '');
```

注释自己说"Sanctum SPA 模式会写 cookie，但纯 PAT 模式下没有"。把长期有效的 Personal Access Token 放进 HTML hidden input 暴露在页面源码中，XSS 可窃取。

**影响**：任何 XSS 漏洞可窃取用户 token，冒充用户调全部 26 个 API。

**修复**：Web 端用 session 认证，或用 Sanctum SPA cookie 模式，不要把 PAT 透传到前端。

---

### I-4：测试自评与实际严重矛盾，且已知 15 天未修

**证据**：
- `docs/bmad/REVIEW-REPORT-v1.2.md`（2026-06-12）自评测试覆盖 8.95/10，37 用例
- `docs/bmad/DAY5-GAP-REPORT-2026-06-15.md`（3 天后）说 2/54 通过，根因 Product::factory() 缺失
- 现在 Product.php **已加 HasFactory trait**（line 12），ProductFactory.php **已存在**——但没人重跑测试确认通过率
- README 写 39/54——既不是 2 也不是 37，来源不明

**影响**：项目状态不可知。所有基于"测试通过"的决策都不可信。

**违反**：Superpowers `verification-before-completion`——"NO COMPLETION CLAIMS WITHOUT FRESH VERIFICATION EVIDENCE"。

**修复**：装 vendor 跑一次测试，用真实数字更新所有文档。

---

### I-5：OrderService 已知债务 NEW-P2-10 未修 — refund 异常时状态已写

**证据**：`docs/bmad/REVIEW-REPORT-v1.2.md` §3 NEW-P2-10：

> `OrderService::handleRefund` GUARD-P2 抛 `GuardFailedException` 后状态已变 refunded（§97-99），实际无法回滚

REVIEW-REPORT 标"Sprint 2（需 DB 事务调整）"。当前代码 `OrderService.php:128-132` 在事务内先写 refunded_at + 释放库存，但 guardPaidTransition 的校验在事务前——如果 refund 路径有类似前置校验缺失，会留下脏数据。

**影响**：refund 失败时订单已变 refunded 但支付未退，财务对账偏差。

---

### I-6：AiMenuService 限流计数器 TTL 续期问题（NEW-P2-08 未修）

**证据**：`app/Services/AiMenuService.php:96-99`

```php
$count = (int) Cache::increment($regenKey);
if ($count === 1) {
    Cache::put($regenKey, 1, self::CACHE_TTL_SECONDS);  // 只第 1 次设 TTL
}
```

第 2/3 次 increment 不续期 TTL。如果 key 在第 2 次调用前过期，计数器归零，用户可无限重新生成。

**影响**：AI 成本失控（绕过 3 次/天限制）。

---

## §3 Minor（次要，Sprint 2+）

### M-1：README 未提 Admin 功能

`app/Http/Controllers/Admin/ProductController.php` 存在（列表+创建+图片上传），`routes/web.php` 有 `/admin/products` 路由组挂 `admin` middleware，但 README §Features 和 §Web Pages 都没提 admin 后台。

### M-2：README 未提 tests/e2e/ 和 tests/TestCases/

`tests/e2e/` 目录存在（REVIEW-REPORT 提到 `sprint-1-smoke.spec.ts`），`tests/TestCases/` 也存在，但 README §Architecture 只说 `tests/ 54 tests`。

### M-3：phpunit.xml 设 GEMINI_API_KEY 和 STRIPE_WEBHOOK_SECRET 为空

`phpunit.xml:36-37` 强制测试环境 key 为空。结合 AppServiceProvider 的断言只在非 local/testing 检查——测试环境 webhook 完全不验签，测试通过不代表生产安全。

### M-4：docker-compose version: '3.9' 已废弃

`docker-compose.yml:8` 用 `version: '3.9'`，Docker Compose v2+ 已忽略 version 字段并会警告。

### M-5：composer.json dev script 假设 concurrently 已装

`composer.json:50` 的 dev script 用 `npx concurrently`，虽然 package.json devDependencies 有 concurrently，但 `npx` 会尝试下载如果本地没装，首次 `composer dev` 可能卡住。

### M-6：docs/bmad 两份评审报告状态冲突

`REVIEW-REPORT-v1.2.md`（06-12）给 9.21/10 Pass，`DAY5-GAP-REPORT`（06-15）暴露测试 2/54。三份文档（加 README）对项目状态描述互相矛盾，无单一真相源（SSOT）。

### M-7：StripeWebhookEvent 表名误导

`PaymentService::insertOrFetchEvent` 把 PayMe 的事件也存进 `StripeWebhookEvent` 表（`PaymeWebhookController` 注释说"复用 StripeWebhookEvent 表 provider='payme'"）。表名带 Stripe 但存多 provider 数据，命名误导。

---

## §4 优点（客观记录）

1. **OrderService 状态机设计扎实**：7 态 TRANSITIONS 数组 + lockForUpdate + 事务 + 审计日志 + GUARD 分层，SSOT 原则贯彻。`assertOwnerOrAdmin` 静态方法提供双层防护。
2. **PaymentService webhook 幂等设计**：INSERT + catch UQ + 重读模式，比 firstOrCreate 更可靠，解决了并发业务事件丢失问题。GUARD-P4 防止 succeeded 被 failed 覆盖。
3. **AiProvider 抽象层**：`AiProviderInterface` + Factory + NullProvider 兜底，"注释掉 KEY 即关闭 AI"的配置层操作很优雅。
4. **AppServiceProvider 启动断言**：非 local/testing 环境缺 STRIPE_WEBHOOK_SECRET 直接崩，fail-closed 思路正确（虽然验签逻辑本身有 C-3 的 bug）。
5. **Dockerfile 多阶段构建**：4 阶段（vendor/frontend/php-base/runtime），层缓存优化，tini 作 PID 1，healthcheck 完整。

---

## §5 按 BMAD 三维度评审汇总

### 5.1 Adversarial Review（愤世嫉俗审查）

核心问题：**项目宣称的状态与实际状态系统性脱节**。这不是个别 bug，而是"文档描述理想、代码停在半路、测试从未验证"的模式。自评 9.21/10 Pass 的 REVIEW-REPORT-v1.2 是在没有跑通任何测试的情况下给出的——这本身就是评审失职。

### 5.2 Edge Case Hunter（边界条件）

- webhook 无 signature header → Stripe 返回 MISSING_SIGNATURE（✓），PayMe 直接放行（✗ C-2）
- Stripe 真实签名格式 → 永远 401（C-3）
- 用户选 alipay_hk → 死代码（C-4）
- AiMenu regenerate 第 4 次 → 抛异常（✓），但 TTL 过期后绕过（I-6）
- SQLite 不支持 lockForUpdate → phpunit 用 :memory: sqlite，行锁测试不真实（M-3）

### 5.3 Code Review（代码审查）

- Service 分层清晰：Controller → Service → Model，无越层
- 异常体系：GuardFailedException + GuardCode enum + InvalidTransitionException，结构化
- 但 PaymeWebhookController 是空壳，StripeWebhookController 验签错误

---

## §6 修复优先级

| 优先级 | 编号 | 内容 | 工时 |
|---|---|---|---|
| 🔴 立即 | C-1 | 装 vendor 跑测试，用真实数字更新所有文档 | 1h |
| 🔴 立即 | C-2 | PayMe webhook 实现验签 或 README 如实标注未实现 | 2h |
| 🔴 立即 | C-3 | Stripe 改用 `\Stripe\Webhook::constructEvent` | 1h |
| 🔴 立即 | C-4 | 移除 alipay_hk 或实现 | 0.5h |
| 🟠 Sprint 1 | I-1 | README 12 处不符全修 | 1h |
| 🟠 Sprint 1 | I-2 | .env.example 与 docker-compose 对齐 | 0.5h |
| 🟠 Sprint 1 | I-3 | Web checkout 改 session 认证 | 2h |
| 🟠 Sprint 1 | I-4 | 跑通测试，消除三份文档矛盾 | 含 C-1 |
| 🟡 Sprint 2 | I-5, I-6 | OrderService refund + AiMenu TTL | 3h |
| 🟢 Sprint 2+ | M-1~M-7 | 文档/命名清理 | 2h |

---

## §7 评审方法说明

本次评审遵循：
- **BMAD** `bmad-review-adversarial-general`：愤世嫉俗视角，找宣称与现实的差距
- **BMAD** `bmad-review-edge-case-hunter`：穷举边界条件
- **BMAD** `bmad-code-review`：代码分层/安全/一致性
- **Superpowers** `verification-before-completion`：所有声明必须有证据，跑了 `php artisan test` 验证 vendor 状态
- **Superpowers** `systematic-debugging` Phase 1：根因调查——不只列症状，追到"项目从未跑通"这个根因

未做：
- 未装 vendor 跑完整测试（环境准备耗时，且 C-1 已是结论）
- 未读全部 22 个 migration（抽样足够）
- 未读 routes/web.php 的 admin middleware 实现（IsAdmin middleware）

---

*评审完成。等待用户确认后进入修复阶段。*
