# BMad Review — I-3 修复方案 00-proposal.md

> **Reviewer**：adversarial-review + edge-case-hunter
> **日期**：2026-07-03

---

## §1 Findings

### F-1 [Critical] 方案 A 的"token 不再出现在 HTML 源码"是假安全感

**问题**：方案 A 说"token 仍在 localStorage，但不再出现在 HTML 源码"。但：
- XSS 攻击者读 `localStorage.gb_token` 和读 `<input value="...">` 难度一样——都是一行 JS
- 方案 A 的真实改善只是"token 不在 HTML 源码里"（view-source 看不到），但 XSS 场景下安全性**零提升**
- 评审 findings I-3 的原文是"XSS 可窃取 token"——方案 A 没有解决这个核心风险

**影响**：方案 A 可能给人"已修复"的假象，实际风险仍在。

**修复**：方案 A 的描述应诚实标注"只消除 HTML 源码暴露，不解决 XSS 窃取 localStorage 的风险"。如果 I-3 的目标是防 XSS 窃取 token，方案 A 不够。

---

### F-2 [Important] 方案 A 忽略了支付网关跳转的根本约束

**问题**：方案 A 说"支付网关跳转从服务端 302 变成 JS `window.location`"。但：
- 当前 `PaymentService::createIntent` 返回 mock URL（`return_url?payment_id=X`）
- 生产 Stripe 的支付流程是：前端调 `/api/orders/{id}/pay` → 后端创建 PaymentIntent → 返回 `client_secret` → 前端用 Stripe.js `confirmPayment` 跳转到 Stripe 托管页
- 方案 A 的 AJAX 化意味着前端需要处理 Stripe SDK 的 `confirmPayment` 回调——这不是简单的 `window.location`
- 当前 checkout.blade.php 是多步表单（Delivery → Payment → Confirm），AJAX 化需要重构整个前端交互

**影响**：方案 A 低估了前端改动复杂度。"最小改动"的判断有误。

**修复**：方案 A 应标注"如果未来接真实 Stripe，需进一步重构为 Stripe.js 流程"。当前 mock 阶段 AJAX 化可行，但不是最终形态。

---

### F-3 [Important] 方案 C 的"双轨登录"问题被低估

**问题**：方案 C 说"用户可能需要登录两次"。但：
- 如果 web session 和 API PAT 是独立的，用户在 /login 登录后只有 session，没有 PAT
- catalog/cart 页面用 PAT（localStorage），用户没 PAT 就调不了 API
- 这意味着用户登录后不能浏览商品/加购物车——除非登录时同时创建 PAT + session

**修复**：方案 C 必须在登录时同时创建 session + PAT：
```php
// WebAuthController::login
Auth::login($user); // session
$token = $user->createToken('web')->plainTextToken; // PAT
return response()->json(['token' => $token])->withCookie(cookie('session', ...));
```
但这引入了"一个登录端点创建两种凭证"的耦合，增加了攻击面。

---

### F-4 [Medium] 三个方案都没提 CSRF 保护

**问题**：
- 方案 A：AJAX 请求需要 CSRF token（Laravel 默认要求）
- 方案 B：Sanctum SPA 自带 CSRF
- 方案 C：session 认证需要 CSRF 保护

当前 checkout.blade.php 的 form 有 `@csrf`，但方案 A 改为 AJAX 后，需要在前端 JS 里带 CSRF header。方案没提这个。

**修复**：方案 A 应加一步"前端 AJAX 请求加 X-CSRF-TOKEN header（从 meta tag 读）"。

---

### F-5 [Medium] 方案 A 删除 POST /checkout/place 会破坏现有测试

**问题**：`tests/Feature/Web/CheckoutFlowTest.php` 和 `EndToEndCheckoutTest.php` 测试的是 `POST /checkout/place` 流程。方案 A 删除这个路由后，这些测试会全部失败。

**修复**：方案 A 应包含"重写 CheckoutFlowTest 为 API 端点测试"的步骤。

---

### F-6 [Low] 方案推荐理由偏保守

**问题**：推荐方案 A 的理由是"最小改动"。但 I-3 是安全 finding，安全修复应该以"彻底解决"为目标，不是"最小改动"。方案 B 虽然改动大，但才是正确方向。

**修复**：推荐应分两层：
- 短期（当前 Sprint）：方案 A（快速止血，消除 HTML 源码暴露）
- 长期（Sprint 2+）：方案 B（彻底解决，切 Sanctum cookie）

---

## §2 修正后的推荐

| 时间 | 方案 | 目标 |
|---|---|---|
| 现在 | 方案 A（修正版） | 消除 HTML 源码暴露 + AJAX 化（mock 阶段可行） |
| Sprint 2+ | 方案 B | 切 Sanctum SPA cookie，根治 XSS 窃取 |

**方案 A 修正**：
1. 诚实标注"不解决 XSS 窃取 localStorage 风险"
2. 加 CSRF header 步骤
3. 加测试重写步骤
4. 标注"Stripe 真实接入时需进一步重构"

---

## §3 判定

**Conditional Pass** — 方案 A 方向可行但需修正 F-1（诚实标注）+ F-4（CSRF）+ F-5（测试）。F-2/F-3 说明方案 A/C 各有问题，方案 B 是长期方向。
