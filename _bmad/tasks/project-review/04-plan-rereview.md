# 再 Review — 修正后的计划 02-plan.md

> **Reviewer**：edge-case-hunter（边界条件视角）
> **日期**：2026-07-03
> **被评对象**：修正后的 `02-plan.md`（已应用 F-1/F-2/F-3/F-6 修复）

---

## §1 修正核对

| 原 Finding | 修正状态 | 验证 |
|---|---|---|
| F-1（Task 1 Step 5 数据来源） | ✅ 已修 | 明确"读取 baseline 末尾 Tests 行提取数字" |
| F-2（Task 2 真实签名测试） | ✅ 已修 | 加了 `test_webhook_with_valid_stripe_signature_format_is_accepted`，用 `timestamp.rawbody` 格式构造合法签名，旧代码会 401（RED） |
| F-3（Task 3 PayMe 测试失败处理） | ✅ 已修 | Step 4 明确"更新期望为 501 或删除假绿用例" |
| F-4（migration 表数） | ✅ 已修 | 合并到 Task 1 Step 5"数 migrate --seed 输出的建表数" |
| F-5（raw body fallback） | ✅ 可接受 | 代码已有 `$request->all()` fallback |
| F-6（Task 6 grep 验证） | ✅ 已修 | Step 1 前置 grep `config('services.stripe` |
| F-7（git 分支） | ⚠️ 未在计划内修 | 需用户在执行前决定 |

---

## §2 新发现

### NF-1 [Low] Task 2 的 `test_webhook_with_valid_stripe_signature_format_is_accepted` 用 `$this->call()` 而非 `$this->postJson()`

`$this->call()` 的第 7 参数是 `$content`（raw body），这在 Laravel TestCase 里是合法的。但需确认 `StripeWebhookController::handle` 改后用 `$request->getContent()` 能拿到这个值——`call()` 方法确实会把 content 设到 Request 里。✅ 可接受。

### NF-2 [Low] Task 2 的 `putenv` 在 tearDown 清空，但 AppServiceProvider 的断言只在非 testing 环境检查

phpunit.xml 设 `APP_ENV=testing`，所以 AppServiceProvider 的 `assertStripeWebhookSecretConfigured` 直接 return，putenv 注入的 secret 能被 `env('STRIPE_WEBHOOK_SECRET')` 读到。✅ 逻辑自洽。

### NF-3 [Medium] Task 3 把 PayMe 改 501 后，`PaymentService::handleWebhook('payme', ...)` 的测试路径断了

如果 `PaymentServiceTest` 或 `WebhookFlowTest` 直接调 `PaymentService::handleWebhook('payme', ...)`（不经过 controller），那 Task 3 改 controller 不影响这些测试——它们仍会通过。但这些测试测的是"PayMe 事件能被处理"，而 controller 层已 501 拒绝。**存在测试通过但功能不可用的矛盾**。

**修复**：可接受。Service 层测试测的是"如果 PayMe 事件到达，PaymentService 能处理"——这是正确的单元测试行为。Controller 层 501 是"PayMe 事件根本不该到达"——这是正确的集成行为。两层各自正确。

---

## §3 判定

**Pass** — 计划可执行。F-7（git 分支）需用户在执行前确认，但不阻塞计划本身。

---

## §4 执行建议

1. **先决定分支策略**：开 `fix/review-findings` 分支，还是在 main 上做？（Superpowers 建议 worktree/分支）
2. **Task 1 必须第一个执行**，因为后续 Task 都依赖 vendor 已装 + 测试基线已建立
3. **Task 2 是最高价值修复**（Stripe 验签），建议 Task 1 后立即做
4. **Task 3/4/5/6 可并行**，互不依赖
5. 执行方式选择：
   - **Subagent-driven**（推荐）：每个 Task 派 subagent，Task 间 review
   - **Inline 执行**：批量跑，检查点暂停
