# I-3 Sanctum SPA Cookie 迁移实施计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement. Steps use checkbox (`- [ ]`) syntax.

**Goal:** 从 PAT (localStorage) 切换到 Sanctum SPA cookie (httpOnly)，根治 XSS 窃取 token。

**Architecture:** 启用 statefulApi + AuthController 改 session 认证 + CheckoutController 直接调 OrderService + 前端 withCredentials + 测试 actingAs。设计文档见 `_bmad/tasks/fix-i3-web-auth/02-design.md`。

**Tech Stack:** Laravel 12 / PHP 8.2 / Sanctum 4.3 / SQLite(dev)

## Global Constraints

- 在 main 分支直接做
- 测试基线：75 passed / 317 assertions（2026-07-03）
- 不改 API 控制器签名（路由不变）
- guest localStorage cart 模式保持不变
- AuthController 注释原本就写"使用 Session Cookie"，实际被改成 PAT，本次是回归原设计

---

### Task 1: 启用 statefulApi + 修 AuthenticationException render

**Files:**
- Modify: `bootstrap/app.php:22-31`（withMiddleware 闭包）
- Modify: `bootstrap/app.php:34-38`（AuthenticationException render）
- Modify: `.env.example`（加 SANCTUM_STATEFUL_DOMAINS）

- [ ] **Step 1: 启用 statefulApi**

Modify `bootstrap/app.php`，在 `$middleware->throttleApi();` 前加：

```php
->withMiddleware(function (Middleware $middleware): void {
    // 启用 Sanctum stateful API（SPA cookie 模式）
    $middleware->statefulApi();
    $middleware->throttleApi();
    // i18n：解析 ?lang= / cookie / Accept-Language，写入 app()->setLocale
    $middleware->append(SetLocale::class);
    // admin 别名（IsAdmin 中间件）
    $middleware->alias([
        'admin' => IsAdmin::class,
    ]);
})
```

删除原注释 `// API 仅做 token 鉴权（auth:sanctum）+ 限流（throttle:api）` 和 `// 不启用 statefulApi()`。

- [ ] **Step 2: 修 AuthenticationException render 防 302**

Modify `bootstrap/app.php` 第 35-38 行，AuthenticationException render 加 `expectsJson` 判断：

```php
$exceptions->render(function (AuthenticationException $e, Request $request) {
    if ($request->expectsJson() || $request->is('api/*')) {
        return response()->json(['error' => ['code' => 'UNAUTHENTICATED', 'message' => '未登录或令牌无效']], 401);
    }
});
```

- [ ] **Step 3: .env.example 加 SANCTUM_STATEFUL_DOMAINS**

在 `.env.example` 的 `SESSION_DOMAIN=null` 后加：

```env
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,127.0.0.1:8000,::1
```

- [ ] **Step 4: 跑测试确认无回归**

```bash
php artisan test
```

Expected: 75 passed（statefulApi 启用后 Sanctum::actingAs 仍工作）。

- [ ] **Step 5: Commit**

```bash
git add bootstrap/app.php .env.example
git commit -m "feat(I-3): enable statefulApi + fix auth exception render for SPA cookie"
```

---

### Task 2: 改 AuthController 为 session 认证

**Files:**
- Modify: `app/Http/Controllers/Api/AuthController.php`（login/logout/register/me 全改）

- [ ] **Step 1: 改 register**

```php
public function register(Request $request): JsonResponse
{
    $data = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:8|confirmed',
        'locale' => ['nullable', 'string', Rule::in(['zh-HK', 'en', 'zh-CN'])],
    ]);

    $user = User::create([
        'name' => $data['name'],
        'email' => $data['email'],
        'password' => Hash::make($data['password']),
        'locale' => $data['locale'] ?? 'zh-HK',
    ]);

    // Sanctum SPA cookie 模式：登录 + regenerate session
    Auth::login($user);
    $request->session()->regenerate();

    return response()->json([
        'user' => $user,
    ], 201);
}
```

- [ ] **Step 2: 改 login**

```php
public function login(Request $request): JsonResponse
{
    $data = $request->validate([
        'email' => 'required|email',
        'password' => 'required|string',
    ]);

    if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
        return response()->json([
            'error' => ['code' => 'INVALID_CREDENTIALS', 'message' => '邮箱或密码错误'],
        ], 401);
    }

    $request->session()->regenerate();
    $user = Auth::user();

    return response()->json([
        'user' => $user,
    ]);
}
```

- [ ] **Step 3: 改 logout**

```php
public function logout(Request $request): JsonResponse
{
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return response()->json(null, 204);
}
```

- [ ] **Step 4: 改 me（已正确，确认用 Auth::user）**

```php
public function me(Request $request): JsonResponse
{
    $user = $request->user()->load(['userPreferences', 'notificationPreference']);

    return response()->json(['user' => $user]);
}
```

- [ ] **Step 5: 加 Auth facade import**

文件头 use 块加：

```php
use Illuminate\Support\Facades\Auth;
```

- [ ] **Step 6: 跑测试**

```bash
php artisan test
```

Expected: 可能部分失败（前端测试用 PAT header），Task 5 修复。

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/AuthController.php
git commit -m "feat(I-3): AuthController session auth (login/logout/register)"
```

---

### Task 3: 改 CheckoutController 直接调 OrderService

**Files:**
- Modify: `app/Http/Controllers/Web/CheckoutController.php`（place 方法重写）
- Modify: `routes/web.php`（checkout 路由加 auth middleware）

- [ ] **Step 1: 改 routes/web.php**

checkout 路由加 auth middleware。同时给 /login 加 name('login')（auth middleware 未认证时重定向到 route('login')）：

```php
Route::get('/login', function () {
    return view('auth');
})->name('login');

// Checkout（session 认证，不再用 PAT hidden field）
Route::middleware('auth')->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('web.checkout.show');
    Route::post('/checkout/place', [CheckoutController::class, 'place'])->name('web.checkout.place');
});
```

- [ ] **Step 2: 重写 CheckoutController::place**

```php
<?php

namespace App\Http\Controllers\Web;

use App\Enums\Currency;
use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly PaymentService $paymentService,
    ) {}

    public function show(Request $request): View
    {
        return view('checkout');
    }

    public function place(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shipping_address.name' => 'required|string|max:120',
            'shipping_address.phone' => 'required|string|max:32',
            'shipping_address.address' => 'required|string|max:255',
            'shipping_address.district' => 'required|string|max:64',
            'shipping_address.date' => 'nullable|date',
            'shipping_address.notes' => 'nullable|string|max:255',
            'items' => 'required|json',
            'coupon_code' => 'nullable|string|max:32',
        ]);

        $items = json_decode($data['items'], true);
        if (! is_array($items) || count($items) === 0) {
            return $this->errorRedirect('购物车为空', '/cart');
        }

        $normalized = [];
        foreach ($items as $row) {
            if (! isset($row['product_id'], $row['quantity'])) {
                continue;
            }
            $qty = (int) $row['quantity'];
            if ($qty <= 0) {
                continue;
            }
            $normalized[] = [
                'product_id' => (int) $row['product_id'],
                'quantity' => $qty,
            ];
        }
        if (count($normalized) === 0) {
            return $this->errorRedirect('购物车为空', '/cart');
        }

        $shippingAddress = [
            'name' => $data['shipping_address']['name'],
            'phone' => $data['shipping_address']['phone'],
            'address' => $data['shipping_address']['address'],
            'district' => $data['shipping_address']['district'],
            'date' => $data['shipping_address']['date'] ?? null,
            'notes' => $data['shipping_address']['notes'] ?? null,
            'currency' => Currency::HKD->value,
        ];

        try {
            $order = $this->orderService->createOrder(
                user: $request->user(),
                items: $normalized,
                shippingAddress: $shippingAddress,
                couponCode: $data['coupon_code'] ?? null,
            );

            // 清空购物车
            $request->user()->cartItems()->delete();

            // 创建支付意图（直接调 PaymentService，不走 HTTP）
            $returnUrl = rtrim(config('app.url') ?: $request->getSchemeAndHttpHost(), '/').'/orders';
            $payment = $this->paymentService->createIntent($order, 'stripe', $returnUrl);

            return redirect()->away($returnUrl.'?payment_id='.$payment->id);
        } catch (\Throwable $e) {
            $msg = method_exists($e, 'toApiPayload') ? ($e->toApiPayload()['message'] ?? '订单创建失败') : '订单创建失败：'.$e->getMessage();
            return $this->errorRedirect($msg, '/cart');
        }
    }

    private function errorRedirect(string $message, string $to): RedirectResponse
    {
        session()->flash('checkout_error', $message);
        return redirect()->to($to);
    }
}
```

- [ ] **Step 3: 跑测试**

```bash
php artisan test --filter=Checkout
```

Expected: CheckoutFlowTest 可能失败（用 PAT header），Task 5 修复。

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Web/CheckoutController.php routes/web.php
git commit -m "feat(I-3): CheckoutController direct OrderService call (no BFF HTTP)"
```

---

### Task 4: 改前端（gbFetch + auth + checkout）

**Files:**
- Modify: `resources/views/layouts/app.blade.php`（gbFetch + renderAuthArea + logout）
- Modify: `resources/views/auth.blade.php`（登录流程）
- Modify: `resources/views/checkout.blade.php`（移除 hidden gb_token）

- [ ] **Step 1: 改 gbFetch（layouts/app.blade.php）**

把 `window.gbFetch` 改为：

```javascript
window.gbFetch = function(url, opts) {
    opts = opts || {};
    opts.credentials = 'include';  // Sanctum SPA cookie
    opts.headers = opts.headers || {};
    opts.headers['Accept'] = 'application/json';
    // XSRF-TOKEN cookie 自动被 Laravel 读取；POST/PUT/DELETE 需加 X-XSRF-TOKEN header
    const xsrf = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    if (xsrf && ['POST','PUT','PATCH','DELETE'].includes((opts.method||'GET').toUpperCase())) {
        opts.headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf[1]);
    }
    return fetch(url, opts).then(function(resp) {
        if (resp.status === 401) {
            if (location.pathname !== '/login') {
                location.href = '/login?return=' + encodeURIComponent(location.pathname + location.search);
            }
            throw new Error('UNAUTHORIZED');
        }
        return resp;
    });
};
```

- [ ] **Step 2: 改 renderAuthArea + logout**

```javascript
function renderAuthArea() {
    // session 模式：调 /api/me 判断登录态
    gbFetch('/api/me').then(r => r.json()).then(d => {
        const user = d.user;
        if (user && (user.id || user.email)) {
            $('#signin-btn').addClass('hidden');
            $('#user-area').removeClass('hidden').addClass('flex');
            $('#user-name').text(user.name || user.email);
        }
    }).catch(() => {
        $('#signin-btn').removeClass('hidden');
        $('#user-area').addClass('hidden').removeClass('flex');
    });
}

$(document).on('click', '#logout-btn', function() {
    gbFetch('/api/logout', { method: 'POST' })
        .finally(function() {
            localStorage.removeItem('greenbite_cart');
            location.href = '/';
        });
});
```

- [ ] **Step 3: 改 updateCartCount（移除 token 判断）**

```javascript
function updateCartCount() {
    gbFetch('/api/cart')
        .then(r => r.json())
        .then(d => $('#cart-count').text(d.item_count || 0))
        .catch(() => fallbackLocalCount());
}
```

- [ ] **Step 4: 改 addToCartAuth（保留 guest fallback，不用 gbFetch 避免 401 跳转）**

**F-3 修正**：guest 用户调 API 会 401，但 gbFetch 会全局跳登录。addToCartAuth 改为直接用 fetch，401 时不跳转走 localStorage：

```javascript
window.addToCartAuth = function(productId, name, price, qty) {
    qty = qty || 1;
    // 直接 fetch（不用 gbFetch），401 时不跳转走 localStorage fallback
    fetch('/api/cart', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId, quantity: qty }),
    }).then(r => {
        if (r.status === 401) {
            // guest：走 localStorage，不跳登录
            fallbackLocalAdd(productId, name, price);
            return null;
        }
        return r.json();
    }).then(d => {
        if (d === null) return;  // 已走 fallback
        if (d.error) { fallbackLocalAdd(productId, name, price); return; }
        // 成功：刷新角标
        return fetch('/api/cart', { credentials: 'include' })
            .then(r2 => r2.json())
            .then(d2 => { $('#cart-count').text(d2.item_count || 0); });
    }).catch(() => fallbackLocalAdd(productId, name, price));
    // 角标动画
    $('#cart-count').addClass('scale-150').delay(200).queue(function(next){
        $(this).removeClass('scale-150');
        next();
    });
};

function fallbackLocalAdd(productId, name, price) {
    const cart = JSON.parse(localStorage.getItem('greenbite_cart') || '[]');
    cart.push({ name: name, price: parseFloat(price), product_id: productId });
    localStorage.setItem('greenbite_cart', JSON.stringify(cart));
    $('#cart-count').text(cart.length);
}
```

- [ ] **Step 5: 改 auth.blade.php 登录流程**

把 `$('#auth-form').on('submit', ...)` 改为：

```javascript
$('#auth-form').on('submit', function(e) {
    e.preventDefault();
    const email = $('#auth-email').val().trim();
    const password = $('#auth-password').val();
    if (!email || !password) return;

    const $err = $('#auth-err');
    $err.addClass('hidden').text('');

    const $btn = $('#auth-submit');
    $btn.prop('disabled', true).html('<i data-lucide="loader" class="animate-spin mr-2 w-5 h-5"></i> ' + (isLogin ? i18n.signIn : i18n.signUp));
    lucide.createIcons();

    const url = isLogin ? '/api/login' : '/api/register';
    const body = isLogin
        ? { email: email, password: password }
        : {
            email: email,
            password: password,
            password_confirmation: $('#auth-password-confirmation').val(),
            name: $('#auth-name').val().trim() || email.split('@')[0],
            locale: (document.documentElement.lang || 'zh-HK'),
        };

    // Sanctum SPA：先拿 csrf-cookie，再登录
    fetch('/sanctum/csrf-cookie', { credentials: 'include' })
        .then(() => fetch(url, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [,''])[1]),
            },
            body: JSON.stringify(body),
        }))
        .then(r => r.json().then(j => ({ status: r.status, body: j })))
        .then(({ status, body }) => {
            if (status === 200 || status === 201) {
                if (typeof renderAuthArea === 'function') renderAuthArea();
                location.href = returnTo;
            } else {
                const msg = (body.error && body.error.message) || (body.message) || '请求失败';
                $err.removeClass('hidden').text(msg);
            }
        })
        .catch(err => {
            $err.removeClass('hidden').text('网络错误：' + (err.message || ''));
        })
        .finally(() => {
            $btn.prop('disabled', false);
            applyMode();
        });
});
```

移除文件顶部的 `if (localStorage.getItem('gb_token'))` 跳转——改为调 `/api/me` 判断：

```javascript
// 已登录则直接跳
fetch('/api/me', { credentials: 'include' })
    .then(r => { if (r.ok) location.href = returnTo; })
    .catch(() => {});
```

- [ ] **Step 6: 改 checkout.blade.php（移除 hidden gb_token）**

删除 `<input type="hidden" name="gb_token" id="gb_token_field">`。

把 JS 里的登录态判断改为调 API：

```javascript
// ── 登录态判断 ─────────────────────────────────────────────────
fetch('/api/me', { credentials: 'include' })
    .then(r => {
        if (!r.ok) throw new Error('UNAUTHORIZED');
        return r.json();
    })
    .then(() => {
        // 已登录，拉购物车
        return fetchItems();
    })
    .catch(() => {
        $('#not-logged-in').removeClass('hidden');
        $('.auth-required').addClass('hidden');
        return [];
    })
    .then(items => {
        $('#items-field').val(JSON.stringify(items.map(i => ({ product_id: i.product_id, quantity: i.qty }))));
        buildSummary(items);
        lucide.createIcons();
    });
```

把 `fetchItems` 改为用 gbFetch（withCredentials）：

```javascript
function fetchItems() {
    return gbFetch('/api/cart').then(r => r.json()).then(d => {
        return (d.items || []).map(it => ({
            product_id: it.product_id,
            name: it.product.name,
            price: parseFloat(it.product.price),
            qty: it.quantity,
            image: it.product.image,
        }));
    }).catch(() => []);
}
```

把 form submit 的 token 检查改为 session 检查：

```javascript
$('#checkout-form').on('submit', function(e) {
    // session 认证由后端 auth middleware 拦截，前端不需额外检查
    const btn = $('#place-order-btn');
    btn.prop('disabled', true).html('<i data-lucide="loader" class="animate-spin w-5 h-5 mr-2"></i> Processing...');
    lucide.createIcons();
});
```

移除 `const token = localStorage.getItem('gb_token')` 和所有 `if (!token)` 判断。

- [ ] **Step 7: Commit**

```bash
git add resources/views/layouts/app.blade.php resources/views/auth.blade.php resources/views/checkout.blade.php
git commit -m "feat(I-3): frontend SPA cookie auth (withCredentials + csrf-cookie)"
```

---

### Task 5: 改测试（actingAs 代替 PAT header）

**Files:**
- Modify: `tests/Feature/Web/CartAuthGuardTest.php`
- Modify: `tests/Feature/Web/CheckoutFlowTest.php`
- Modify: `tests/Feature/Web/EndToEndCheckoutTest.php`

- [ ] **Step 1: CartAuthGuardTest 改 actingAs**

把 `Sanctum::actingAs($this->alice)` 改为 `$this->actingAs($this->alice)`（web guard）。

`test_invalid_token_returns_401` 改为 `test_unauthenticated_api_returns_401`（不再测 Bearer header，测无 session 时 401）：

```php
public function test_unauthenticated_api_returns_401(): void
{
    $response = $this->getJson('/api/cart');
    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
}
```

- [ ] **Step 2: CheckoutFlowTest 改 actingAs**

读文件确认当前用 PAT header 的地方，改为 `$this->actingAs($user)`。

- [ ] **Step 3: EndToEndCheckoutTest 改 actingAs**

同上。

- [ ] **Step 4: 跑全量测试**

```bash
php artisan test
```

Expected: 75 passed（或更多），0 failed。如果有失败，逐个修。

> **F-1 注**：前端 csrf-cookie 流程（`fetch('/sanctum/csrf-cookie')`）在 PHPUnit 测试环境中不可用（无真实 HTTP 服务器）。测试中用 `$this->post('/api/login', ...)` 直接调（Laravel TestCase 自带 CSRF 禁用 + session）。前端 csrf-cookie 流程靠手动浏览器测试验证。

> **F-5 注**：webhook 测试（StripeSignatureTest / WebhookFlowTest）无 auth，不受本次改动影响。

- [ ] **Step 5: Commit**

```bash
git add tests/
git commit -m "test(I-3): switch from PAT header to actingAs (session auth)"
```

---

## Self-Review

**1. Spec coverage:** 设计文档 6 个 finding 全部有对应 Task（F-1→Task1 Step2, F-2→Task2 Step3, F-3→Task1 Step3 注释, F-4→Task4 Step4, F-5→Task2 Step1/2, F-6→Task3 Step2）。

**2. Placeholder scan:** 无 TBD/TODO。代码块完整。

**3. Type consistency:** AuthController 返回 JsonResponse 不变。CheckoutController 返回 RedirectResponse 不变。

**4. 依赖顺序:** Task 1（配置）→ Task 2（Auth）→ Task 3（Checkout）→ Task 4（前端）→ Task 5（测试）。串行，每步有测试检查点。
