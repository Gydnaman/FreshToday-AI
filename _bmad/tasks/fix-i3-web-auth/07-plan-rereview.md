# 再 Review — 修正后的 I-3 计划 05-plan.md

> **Reviewer**：adversarial-review
> **日期**：2026-07-03

## §1 修正核对

| Finding | 修正状态 |
|---|---|
| F-1（测试环境 csrf-cookie） | ✅ Task 5 Step 4 加注释：测试用 post 直接调，前端靠手动验证 |
| F-2（PaymentService 注入） | ✅ 确认无构造参数，无需改 |
| F-3（guest 模式破坏） | ✅ Task 4 Step 4 改 addToCartAuth 不用 gbFetch，401 走 localStorage |
| F-4（renderAuthArea 每页请求） | ✅ 可接受，轻量查询 |
| F-5（webhook 测试） | ✅ Task 5 Step 4 加注释标注不受影响 |
| F-6（login route name） | ✅ Task 3 Step 1 给 /login 加 ->name('login') |

## §2 新发现

无重大问题。

## §3 判定

**Pass** — 计划可执行。6 个 review finding 全部修正或确认可接受。

## §4 执行建议

5 个 Task 串行执行（每步有测试检查点），预估 3-4 小时：
- Task 1（配置）：20min
- Task 2（AuthController）：30min
- Task 3（CheckoutController）：30min
- Task 4（前端）：1.5h（最大改动量）
- Task 5（测试）：1h
