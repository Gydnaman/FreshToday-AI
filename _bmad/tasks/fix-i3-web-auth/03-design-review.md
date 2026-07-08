# BMad Review — I-3 设计文档 02-design.md

> **Reviewer**：edge-case-hunter + adversarial-review
> **日期**：2026-07-03

---

## §1 Findings

### F-1 [Critical] 设计漏改了 CartController 的 auth:sanctum middleware 语义变化

**问题**：`routes/api.php` 所有 auth:sanctum 端点（cart/orders/menu/subscriptions/survey）当前用 PAT 认证。切到 SPA cookie 后：
- Sanctum 的 `auth:sanctum` **同时支持 cookie 和 PAT**——这没问题
- 但 `bootstrap/app.php` 启用 `statefulApi()` 后，API 请求会走 session middleware 链
- **当前 API 异常处理**（bootstrap/app.php 第 35-38 行）只在 `$request->is('api/*')` 时返 401 JSON——session 认证失败时 Laravel 默认重定向到 login 路由
- 如果 API 请求 session 认证失败，可能触发 302 重定向而非 401 JSON

**影响**：API 端点认证失败行为可能从 401 JSON 变成 302 重定向，破坏前端 gbFetch 的 401 处理逻辑。

**修复**：设计文档应加一步——验证 `bootstrap/app.php` 的 `AuthenticationException` render 是否覆盖 stateful API 的 session 认证失败。可能需要在 render 里加 `$request->expectsJson() || $request->is('api/*')` 判断。

---

### F-2 [Important] AuthController 改为 session 认证后，API 端 /api/me 和 /api/logout 的响应格式可能变

**问题**：当前 `/api/me` 返回 `Auth::user()`（从 PAT 找 user）。切到 session 后：
- `Auth::user()` 在 session 认证下也能用——但前提是 session middleware 已认证
- `/api/logout` 当前删 PAT（`$request->user()->currentAccessToken()->delete()`）。切到 session 后改为 `$request->session()->invalidate()`
- **但**：如果用户有多个 tab 打开，invalidate session 会让所有 tab 的 session 失效——这是正确行为，但需确认

**影响**：API 响应格式应不变（仍返 user JSON），但 logout 行为变化。

**修复**：设计文档 §2.2 应明确 logout 的新实现 + 确认多 tab 行为。

---

### F-3 [Important] 前端 gbFetch 改 withCredentials 后，CORS 配置缺失会导致跨域失败

**问题**：设计文档 §2.1 提到 .env.example 加 `SANCTUM_STATEFUL_DOMAINS`，但没提 CORS 配置。Laravel 12 没有 `config/cors.php`（已确认不存在），默认 CORS 在 `bootstrap/app.php` 或中间件层配置。

当前前端从 `http://127.0.0.1:8000` 访问同源 API——**同源不触发 CORS**。但如果未来前后端分离部署（不同端口/域名），`withCredentials: 'include'` 要求 CORS 响应头含 `Access-Control-Allow-Credentials: true` + `Access-Control-Allow-Origin` 不能是 `*`。

**影响**：当前同源没问题，但未来分离部署会踩坑。

**修复**：设计文档加一句"CORS 在同源下不触发；未来分离部署时需配 config/cors.php（supports_credentials=true）"。本次不创建 cors.php。

---

### F-4 [Medium] 设计未提 guest 购物车（localStorage）的处理

**问题**：`layouts/app.blade.php` 的 `addToCartAuth` 和 `updateCartCount` 有 guest 模式（localStorage `greenbite_cart`）。切到 cookie 模式后：
- guest 仍可用 localStorage（不登录）
- 登录后 cart 从 API 拉
- **但**：如果用户先 guest 加购（localStorage），再登录，需要合并 guest cart 到 API cart——当前代码没这个逻辑

**影响**：这不是 I-3 引入的问题（当前就有），但 I-3 改动前端时不该破坏 guest 模式。

**修复**：设计文档 §2.3 应标注"guest localStorage cart 模式保持不变，本次只改 logged-in 路径"。

---

### F-5 [Medium] 设计未提 AuthController 当前返回 token 字段的影响

**问题**：当前 `/api/login` 返回 `{"user": {...}, "token": "1|abc..."}`。切到 session 后不返 token。前端 `auth.blade.php` 第 139 行 `if (body.token) localStorage.setItem('gb_token', body.token)` 会拿不到 token。

**影响**：这是预期行为（不再用 token），但前端需同步移除 token 存储逻辑。设计文档 §2.3 已提到"不再存 localStorage"，但应明确 `/api/login` 的新响应格式（只返 `{"user": {...}}`，无 token 字段）。

**修复**：设计文档 §2.2 AuthController 改动应明确响应格式。

---

### F-6 [Low] 设计 §3.3 "直接调 OrderService" 需确认 cart clear 逻辑

**问题**：当前 CheckoutController::place 调 `/api/orders` 后，OrderController::store 第 42 行调 `$request->user()->cartItems()->delete()` 清空购物车。如果 CheckoutController 直接调 OrderService::createOrder，谁负责清 cart？

**修复**：设计文档应明确——CheckoutController::place 调 OrderService::createOrder 后，自己调 `$request->user()->cartItems()->delete()`。或把 cart clear 逻辑移到 OrderService（但增加耦合）。建议前者。

---

## §2 修正建议

| Finding | 严重度 | 修正 |
|---|---|---|
| F-1 | Critical | 设计加一步：验证 API 异常处理在 statefulApi 下仍返 401 JSON |
| F-2 | Important | 明确 logout 新实现 + 多 tab 行为 |
| F-3 | Important | 加 CORS 同源说明 + 未来分离部署注释 |
| F-4 | Medium | 标注 guest localStorage cart 不变 |
| F-5 | Medium | 明确 /api/login 新响应格式 |
| F-6 | Low | 明确 cart clear 责任在 CheckoutController |

---

## §3 判定

**Conditional Pass** — 设计方向正确，但 F-1（API 认证失败行为变化）需执行前确认，否则会引入 302 重定向 bug。
