# BMad Review — 评审修复计划 02-plan.md

> **Reviewer**：adversarial-review（愤世嫉俗视角）
> **日期**：2026-07-03
> **被评对象**：`_bmad/tasks/project-review/02-plan.md`
> **方法**：逐 Task 找漏洞、矛盾、遗漏、假假设

---

## §1 总体评价

计划结构清晰，Task 1 先建基线的顺序正确。但有 **7 个 finding**，其中 2 个 Important 会直接影响执行成功率。

---

## §2 Findings

### F-1 [Important] Task 1 Step 5 的 README 更新无法在 Step 4 完成前写

**问题**：Step 5 写"用真实数字更新 README"，但 Step 4 才跑测试输出 baseline。Step 5 的代码块写了 `{X}/{total}`，这只能在 Step 4 执行后才知道。

**影响**：如果用 subagent 执行，subagent 会在 Step 5 卡住——它不知道真实数字。

**修复**：Step 5 改为"读取 02-test-baseline.md 末尾的 `Tests: X passed, Y failed` 行，提取 X 和总数，替换 README 第 94 行"。明确数据来源。

---

### F-2 [Important] Task 2 的测试在当前环境下无法验证"真实 Stripe 格式"

**问题**：`StripeSignatureTest` 的 `test_webhook_with_invalid_signature_returns_401` 用 `v1=invalidhash`，当前错误代码也会返回 401——**测试会通过但没测到真正的 bug**。真正的 bug 是"真实 Stripe 签名格式不被接受"，但测试没有构造一个合法的 Stripe 签名来验证。

**影响**：RED 阶段形同虚设。修复后测试通过，但无法证明修复有效——因为修复前后测试都通过。

**修复**：加第 3 个测试 `test_webhook_with_valid_stripe_signature_is_accepted`，用真实 `hash_hmac` 构造合法签名。但这需要知道 Stripe 签名格式（`t=timestamp,v1=hex`，签名内容是 `timestamp.rawbody`），且需要 Stripe SDK 的 `constructEvent` 能解析。或者至少在测试注释里说明"此测试验证 401 路径，真实签名验证靠手动 Stripe CLI `stripe listen`"。

---

### F-3 [Medium] Task 3 的 PayMe fail-closed 会破坏现有 WebhookFlowTest

**问题**：`tests/Feature/Order/WebhookFlowTest.php` 可能包含 PayMe webhook 测试（REVIEW-REPORT §7.1 提到 WebhookFlowTest 有 3 用例）。Task 3 把 PayMe 改成返回 501，如果有测试期望 200，会失败。

**影响**：Task 3 Step 4"跑测试确认无回归"可能失败，但计划没说怎么处理。

**修复**：Step 4 加"如果有 PayMe webhook 测试失败，更新该测试期望为 501，或删除依赖 PayMe 200 的测试用例"。

---

### F-4 [Medium] Task 5 Step 2 的 migration 表数"12 是合理近似"是偷懒

**问题**：计划写"12 张表保留（实际表数需数 migration，但 12 是合理近似）"。这是 placeholder 性质的表述。

**影响**：README 的"12 张表"如果实际是 14 张或 10 张，仍是文档不符。

**修复**：Task 1 跑完 `migrate --seed` 后，`php artisan db:show --table` 或数 migration 里的 `Schema::create` 调用，用真实数字。

---

### F-5 [Medium] Task 2 改了 handle 方法签名语义但没更新调用方

**问题**：原 `handle` 用 `$request->all()` 拿 payload，新代码用 `$request->getContent()` 拿 raw body 再 `json_decode`。如果 payload 不是合法 JSON（如 Stripe 重试发 form-encoded），`json_decode` 返回 null，fallback 到 `$request->all()`——但这时 raw body 和 array 可能不一致。

**影响**：边缘情况下 payload 解析行为变化。

**修复**：可接受。`$request->all()` fallback 已覆盖非 JSON 场景。在代码注释里说明"优先 raw body 供验签，fallback 到 all() 供兼容"。

---

### F-6 [Low] Task 6 Step 1 改 docker-compose 的 STRIPE 变量名，但没验证 Laravel 代码里引用的是哪个

**问题**：计划把 docker-compose 的 `STRIPE_KEY` 改成 `STRIPE_PUBLISHABLE_KEY`，但没检查 `config/services.php` 或代码里 `config('services.stripe.key')` 引用的是哪个。如果 Laravel 配置用 `stripe.key` 读 `STRIPE_KEY`，改名后配置读不到。

**影响**：生产 Stripe 配置可能失效。

**修复**：Task 6 Step 1 前加一步"grep `config('services.stripe` 和 `env('STRIPE` 全项目，确认 Laravel 实际读的变量名"。

---

### F-7 [Low] 计划没提 git 分支策略

**问题**：Superpowers `using-git-worktrees` 要求在隔离工作区执行。计划直接在 main 分支 commit。

**影响**：违反 Superpowers 铁律"Never start implementation on main/master branch without explicit user consent"。

**修复**：执行前先开 `fix/review-findings` 分支，或用户明确同意在 main 上做。

---

## §3 修复建议汇总

| Finding | 严重度 | 修复方式 |
|---|---|---|
| F-1 | Important | Task 1 Step 5 明确数据提取方式 |
| F-2 | Important | Task 2 加真实签名测试或注明手动验证 |
| F-3 | Medium | Task 3 Step 4 加 PayMe 测试失败处理 |
| F-4 | Medium | Task 1 后数真实表数 |
| F-5 | Medium | Task 2 代码加注释（可接受） |
| F-6 | Low | Task 6 加 grep 验证步骤 |
| F-7 | Low | 执行前确认分支策略 |

---

## §4 判定

**Conditional Pass** — 计划可执行，但 F-1/F-2 建议在执行前修正，否则 Task 1/2 会卡住或假通过。
