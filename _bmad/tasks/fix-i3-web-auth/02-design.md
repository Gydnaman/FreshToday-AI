# I-3 设计文档 — Sanctum SPA Cookie 模式迁移

> **日期**：2026-07-03
> **方案**：B（用户选定）
> **目标**：从 PAT (localStorage) 切换到 Sanctum SPA cookie (httpOnly)，根治 XSS 窃取 token

## §1 当前架构 vs 目标架构

### 当前（PAT 模式）
```
登录：fetch /api/login → 返回 PAT token → 存 localStorage.gb_token
请求：fetch /api/* + Authorization: Bearer {token}
checkout：token 放 <input hidden> → form POST → CheckoutController 用 Http::withToken() 调内部 API
```

### 目标（Sanctum SPA cookie）
```
登录：fetch /sanctum/csrf-cookie → fetch /api/login (withCredentials) → Sanctum 写 session cookie
请求：fetch /api/* (withCredentials) → cookie 自动携带 → Sanctum 认证
checkout：form POST /checkout/place → session middleware 认证 → CheckoutController 用 $request->user() → 直接调 OrderService
```

## §2 改动范围

### 2.1 后端配置

| 文件 | 改动 |
|---|---|
| `bootstrap/app.php` | 启用 `$middleware->statefulApi()`（当前第 23-24 行注释禁用） |
| `bootstrap/app.php` | **F-1 修正**：AuthenticationException render 加 `$request->expectsJson() \|\| $request->is('api/*')` 判断，确保 stateful API session 认证失败仍返 401 JSON（非 302 重定向） |
| `config/sanctum.php` | 已配置好（stateful domains + guard=['web']），无需改 |
| `.env.example` | 加 `SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1:8000` |
| **CORS** | **F-3 修正**：当前同源（127.0.0.1:8000）不触发 CORS，不创建 config/cors.php。未来分离部署时需配 `supports_credentials=true`。本次不加。 |

### 2.2 后端控制器

| 文件 | 改动 |
|---|---|
| `app/Http/Controllers/Api/AuthController.php` | login 改为 session 认证（`Auth::attempt` + `$request->session()->regenerate()`）；不再创建 PAT；**响应格式改为 `{"user": {...}}`（无 token 字段）** |
| `app/Http/Controllers/Api/AuthController.php` | logout 改为 `$request->session()->invalidate()` + `$request->session()->regenerateToken()`；**F-2**：多 tab 下所有 session 失效是正确行为 |
| `app/Http/Controllers/Api/AuthController.php` | me 改为 `Auth::user()`；不再从 PAT 找 user |
| `app/Http/Controllers/Web/CheckoutController.php` | place() 改用 `$request->user()` + 直接调 OrderService（消除 Http::withToken 自调用）；**F-6**：调 createOrder 后自己调 `$request->user()->cartItems()->delete()` 清 cart |
| `routes/web.php` | checkout 路由加 `auth` middleware |

### 2.3 前端

| 文件 | 改动 |
|---|---|
| `resources/views/layouts/app.blade.php` | `gbFetch` 改为 `credentials: 'include'`；移除 `Authorization: Bearer` header；移除 localStorage 读写 |
| `resources/views/layouts/app.blade.php` | `renderAuthArea` 改为调 `/api/me` 判断登录态（不再读 localStorage.gb_user） |
| `resources/views/layouts/app.blade.php` | logout 改为 POST /api/logout (withCredentials)；不再清 localStorage |
| `resources/views/layouts/app.blade.php` | **F-4**：guest 模式 `addToCartAuth` 的 localStorage `greenbite_cart` 路径保持不变，本次只改 logged-in 路径 |
| `resources/views/auth.blade.php` | **F-5**：登录改为先 `fetch /sanctum/csrf-cookie` 再 `fetch /api/login (withCredentials)`；不再存 localStorage；不读 `body.token`（响应无此字段） |
| `resources/views/checkout.blade.php` | 移除 `<input name="gb_token">`；form 直接 POST /checkout/place（session 认证） |

### 2.4 测试

| 文件 | 改动 |
|---|---|
| `tests/Feature/Web/CartAuthGuardTest.php` | 改为 `$this->actingAs($user)` 代替 PAT header |
| `tests/Feature/Web/CheckoutFlowTest.php` | 同上 |
| `tests/Feature/Web/EndToEndCheckoutTest.php` | 同上 |
| `tests/Feature/Order/WebhookFlowTest.php` | webhook 不受影响（无 auth） |

## §3 关键设计决策

### 3.1 是否保留 PAT 模式？

**不保留**。彻底切换到 SPA cookie。理由：
- 双轨制增加复杂度（方案 C 已被否决）
- PAT 的 localStorage 存储是风险根源
- API 仍可通过 `auth:sanctum` 认证——Sanctum 同时支持 cookie 和 token，但本项目不再创建新 PAT

### 3.2 第三方 API 集成怎么办？

README 提到"第三方 API 集成"用 PAT。但当前项目没有第三方 API 消费者（demo 阶段）。Sprint 2 接第三方时再单独创建 PAT 端点。本次改动不影响未来 PAT 创建能力。

### 3.3 CheckoutController 是否还走 BFF（Http 自调用）？

**不走**。改为直接调 OrderService：
- 当前 BFF 模式是因为 web 端拿不到 user（只有 PAT），需要透传
- session 认证后 `$request->user()` 直接可用，无需 HTTP 自调用
- 消除了 `Http::withToken()` 内部请求开销

### 3.4 CSRF 保护

Sanctum SPA cookie 模式自带 CSRF：
- `/sanctum/csrf-cookie` 设置 XSRF-TOKEN cookie
- 前端 fetch 读 cookie 加到 `X-XSRF-TOKEN` header
- Laravel 自动验证

## §4 风险

| 风险 | 概率 | 影响 | 缓解 |
|---|---|---|---|
| 前端漏改 withCredentials 导致 401 | 高 | 中 | 全局 gbFetch 统一改，不逐页改 |
| 测试 actingAs 与 session 不兼容 | 中 | 中 | RefreshDatabase + $this->withSession() |
| CORS 配置缺失 | 中 | 高 | .env.example 加 SANCTUM_STATEFUL_DOMAINS |
| 现有 PAT token 失效（已登录用户被踢） | 高 | 低 | demo 阶段无真实用户，可接受 |

## §5 不在本次范围

- Stripe.js 真实支付流程（当前 mock 阶段，PaymentService::createIntent 返回 mock URL）
- 第三方 API PAT 端点（Sprint 2+）
- Admin 后台认证（已用 session + IsAdmin middleware，不受影响）
