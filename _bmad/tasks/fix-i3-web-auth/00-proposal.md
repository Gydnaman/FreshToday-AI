# I-3 修复方案 — Web Checkout 认证模式重构

> **日期**：2026-07-03
> **问题**：CheckoutController 把 Sanctum PAT 放进 HTML hidden field（`<input name="gb_token">`），XSS 可窃取
> **约束**：不改 API 控制器签名；不破坏现有 PAT 模式的其他页面（catalog/cart/dashboard）

## 当前架构

```
用户 → /login 页面 → fetch /api/login → 拿 PAT token → 存 localStorage.gb_token
用户 → /checkout 页面 → 从 localStorage 读 token → 放进 hidden field → form POST /checkout/place
CheckoutController → 从 $request->input('gb_token') 取 token → Http::withToken() 调 /api/orders + /api/orders/{id}/pay
```

**风险点**：
1. PAT token 在 HTML 源码中可见（`<input name="gb_token" value="1|abc...">`）
2. XSS 可读 `localStorage.gb_token` 或 hidden field
3. PAT 是长期有效 token，泄露后可调全部 26 个 API

## 方案选项

### 方案 A：前端 AJAX 化（最小改动）

**思路**：把 `POST /checkout/place` 的服务端逻辑移到前端 AJAX。CheckoutController 只负责显示页面（GET），不处理 POST。

```
用户 → /checkout 页面 → JS 从 localStorage 读 token → AJAX POST /api/orders（带 Authorization header）→ AJAX POST /api/orders/{id}/pay → JS 拿到 redirect_url → window.location 跳转
```

**改动**：
- `checkout.blade.php`：form submit 改为 AJAX（e.preventDefault + fetch）
- `CheckoutController`：删除 `place()` 方法，保留 `show()`
- `routes/web.php`：移除 `POST /checkout/place` 路由
- 前端 JS：加 `gbFetch` 封装（已存在于其他页面）

**优点**：
- 改动最小（只动 checkout 前端 + 删 controller 方法）
- token 仍在 localStorage，但不再出现在 HTML 源码
- 不影响其他页面

**缺点**：
- token 仍在 localStorage（XSS 仍可读）——但这是 PAT 模式的固有问题，不是 checkout 独有
- 支付网关跳转从服务端 302 变成 JS `window.location`（用户体验略不同）

---

### 方案 B：Sanctum SPA Cookie 模式（官方推荐）

**思路**：从 PAT 模式切换到 Sanctum SPA cookie 模式。token 存 httpOnly cookie，JS 不可读。

```
用户 → /login → fetch /sanctum/csrf-cookie → fetch /api/login（带 withCredentials）→ Sanctum 写 cookie
用户 → /checkout → form POST /checkout/place → session middleware 认证 → CheckoutController 用 $request->user()
```

**改动**：
- `config/sanctum.php`：配置 stateful domains
- `config/cors.php`：配置 supports_credentials + allowed_origins
- 所有前端 fetch 加 `credentials: 'include'`
- `auth.blade.php`：登录流程改为先调 `/sanctum/csrf-cookie`
- `CheckoutController`：加 `auth` middleware，用 `$request->user()` 代替 token
- `routes/web.php`：checkout 路由加 `auth` middleware
- 其他所有页面（catalog/cart/dashboard）的 fetch 也要加 `withCredentials`

**优点**：
- 根治 XSS 窃取 token（httpOnly cookie）
- Sanctum 官方推荐模式
- CSRF 保护自带

**缺点**：
- 改动大（所有前端 fetch + 登录流程 + CORS 配置）
- 影响所有页面，不只 checkout
- 需要充分测试回归

---

### 方案 C：Web Session 双轨制（session + PAT 并行）

**思路**：Web 端加独立 session 登录路由，与 API PAT 并行。checkout 用 session 认证，其他页面继续用 PAT。

```
用户 → /login（web）→ POST /web-login（session 认证）→ 写 session cookie
用户 → /checkout → form POST /checkout/place → auth middleware（session）→ CheckoutController 用 $request->user()
用户 → /catalog, /cart → 继续用 PAT（localStorage）
```

**改动**：
- 新增 `WebAuthController`（login/logout 用 session）
- `routes/web.php`：加 `POST /web-login` + checkout 路由加 `auth` middleware
- `auth.blade.php`：登录改为 form POST 到 `/web-login`（不再 AJAX 调 /api/login）
- `CheckoutController`：用 `$request->user()` 代替 token；直接调 OrderService（不走 HTTP）
- `checkout.blade.php`：移除 hidden gb_token field

**优点**：
- checkout 不再暴露 token
- 不影响其他页面的 PAT 模式
- CheckoutController 直接调 Service（消除 BFF HTTP 自调用）

**缺点**：
- 双轨认证（session + PAT）增加复杂度
- 用户可能需要登录两次（web session + API PAT）——除非登录时同时创建两者
- 新增 controller + 路由

---

## 推荐

**方案 A（前端 AJAX 化）**——最小改动，风险可控，不引入新认证模式。PAT 在 localStorage 的风险是全项目固有问题，应单独评估（如未来切 Sanctum cookie），不阻塞 I-3 修复。

方案 B 是"正确"的长期方向，但改动太大，应作为独立 Sprint 任务。方案 C 引入双轨制，增加维护成本。
