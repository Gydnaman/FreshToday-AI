# BMad Review — I-3 实施计划 05-plan.md

> **Reviewer**：edge-case-hunter + adversarial-review
> **日期**：2026-07-03

---

## §1 Findings

### F-1 [Critical] Task 4 Step 5 的 auth.blade.php csrf-cookie 流程在测试环境会失败

**问题**：auth.blade.php 登录改为先 `fetch('/sanctum/csrf-cookie')` 再 `fetch('/api/login')`。但：
- phpunit.xml 设 `SESSION_DRIVER=array`，Sanctum csrf-cookie 需要 session middleware 工作
- 测试环境没有真实 HTTP 服务器，`fetch('/sanctum/csrf-cookie')` 在测试中不可用
- EndToEndCheckoutTest 如果测登录流程，会因 csrf-cookie 端点不可用而失败

**影响**：Task 5 的测试可能无法覆盖登录流程。

**修复**：测试中用 `$this->post('/api/login', ...)` 直接调（Laravel TestCase 自带 CSRF 禁用 + session），不走前端 fetch。前端 csrf-cookie 流程靠手动测试验证，不靠自动化测试。

---

### F-2 [Important] Task 3 的 CheckoutController::place 用了 PaymentService 但计划没注入

**问题**：Task 3 Step 2 的 CheckoutController 构造函数注入了 `PaymentService`，但当前 CheckoutController 没有构造函数。计划代码是对的，但需确认 PaymentService 可注入（它是无依赖的 service）。

**影响**：低风险——Laravel 自动解析，PaymentService 无构造参数。

**修复**：无需改。确认 PaymentService 构造函数无参数即可。

---

### F-3 [Important] Task 4 Step 4 的 addToCartAuth 改动破坏了 guest 模式

**问题**：原 addToCartAuth 有 `if (token) { API } else { localStorage }` 分支。新代码改为"先调 API，失败 fallback localStorage"。但：
- guest 用户调 `/api/cart` 会 401 → gbFetch 拦截跳 `/login?return=`
- guest 被跳到登录页，而不是走 localStorage fallback
- 这破坏了 guest 浏览加购的体验

**影响**：guest 用户不能加购（被强制跳登录）。

**修复**：gbFetch 的 401 拦截不应在所有页面触发。addToCartAuth 应先判断是否登录（调 /api/me 或检查 cookie），未登录直接走 localStorage。或者 gbFetch 加参数控制是否跳转。

更简单的方案：addToCartAuth 保留"先检查登录态"逻辑：

```javascript
window.addToCartAuth = function(productId, name, price, qty) {
    qty = qty || 1;
    // 先尝试 API；401 时不跳转，走 localStorage fallback
    fetch('/api/cart', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId, quantity: qty }),
    }).then(r => {
        if (r.status === 401) {
            // guest：走 localStorage
            fallbackLocalAdd(productId, name, price);
            return null;
        }
        return r.json();
    }).then(d => {
        if (d === null) return;
        if (d.error) { fallbackLocalAdd(productId, name, price); return; }
        return fetch('/api/cart', { credentials: 'include' }).then(r2 => r2.json()).then(d2 => {
            $('#cart-count').text(d2.item_count || 0);
        });
    }).catch(() => fallbackLocalAdd(productId, name, price));
    // 角标动画
    $('#cart-count').addClass('scale-150').delay(200).queue(function(next){
        $(this).removeClass('scale-150'); next();
    });
};
```

这样 guest 的 401 不会触发 gbFetch 的全局跳转（因为不用 gbFetch）。

---

### F-4 [Medium] Task 4 Step 2 的 renderAuthArea 改为调 /api/me，每次页面加载都发请求

**问题**：原代码从 localStorage 读 user 信息（无网络请求）。新代码调 `/api/me`，每个页面加载都发一个 API 请求。

**影响**：增加服务器负载 + 页面加载延迟（需等 /api/me 返回才能渲染右上角）。

**修复**：可接受。session cookie 模式下 /api/me 是轻量查询。如果延迟明显，可加 sessionStorage 缓存 user 信息。

---

### F-5 [Medium] Task 5 没提 StripeSignatureTest 和 WebhookFlowTest 的影响

**问题**：StripeSignatureTest 和 WebhookFlowTest 不用 auth（webhook 无 auth），不受影响。但计划应明确标注"webhook 测试不受影响"以避免执行时困惑。

**修复**：Task 5 加一句注释"webhook 测试（StripeSignatureTest/WebhookFlowTest）无 auth，不受影响"。

---

### F-6 [Low] Task 3 Step 1 的 routes/web.php 改动用 auth middleware，但 auth middleware 默认重定向 /login

**问题**：`auth` middleware 在 web guard 下，未认证会 302 重定向到 `route('login')`。但当前项目没有 named route `login`（routes/web.php 第 15 行是闭包 `Route::get('/login', function(){...})`）。

**影响**：未登录访问 /checkout 会触发 `RouteNotFoundException`。

**修复**：给 /login 路由加 `->name('login')`：

```php
Route::get('/login', function () {
    return view('auth');
})->name('login');
```

---

## §2 修正建议

| Finding | 严重度 | 修正 |
|---|---|---|
| F-1 | Critical | Task 5 标注"前端 csrf-cookie 流程靠手动测试，自动化测试用 post 直接调" |
| F-3 | Important | Task 4 Step 4 改 addToCartAuth 不用 gbFetch，直接 fetch + 401 走 localStorage |
| F-6 | Low | Task 3 Step 1 给 /login 加 ->name('login') |
| F-2 | Important | 无需改（确认 PaymentService 无构造参数） |
| F-4 | Medium | 可接受 |
| F-5 | Medium | Task 5 加注释 |

---

## §3 判定

**Conditional Pass** — F-1（测试环境 csrf-cookie）和 F-3（guest 模式破坏）需执行前修正。F-6（login route name）是必须修的隐藏 bug。
