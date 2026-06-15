> **元信息**：创建人 architect-agent | 版本 v0.1 (Sprint 0) | 日期 2026-06-12
> **框架**：fdd-bmad-custom（Architect 阶段产物：API Contract）
> **基础约定**：所有响应统一 JSON；鉴权采用 Laravel Sanctum SPA 模式（Session Cookie + CSRF）；前缀 `/api` 标记纯 API 路由

# GreenBite 核心 API 契约（api-contract.md）

## 1. 通用约定

### 1.1 统一响应格式

```json
{
  "data": { ... },
  "meta": { "request_id": "uuid", "timestamp": "2026-06-12T11:40:00+08:00" }
}
```

错误响应：

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "The given data was invalid.",
    "details": { "field": ["..."] }
  }
}
```

### 1.2 错误码字典

| HTTP | code | 含义 |
|---|---|---|
| 400 | `VALIDATION_FAILED` | 入参校验失败 |
| 401 | `UNAUTHENTICATED` | 未登录 |
| 401 | `INVALID_SIGNATURE` | Webhook 签名校验失败（Stripe-Signature / X-Payme-Signature） |
| 401 | `INVALID_CREDENTIALS` | 邮箱或密码错误 |
| 403 | `FORBIDDEN` | 无权限 |
| 403 | `NOT_OWNER` | 资源归属错（订单/购物车/订阅） |
| 404 | `NOT_FOUND` | 资源不存在 |
| 409 | `CONFLICT` | 业务冲突（库存不足/状态非法） |
| 409 | `OUT_OF_STOCK` | 库存不足（GUARD-I1） |
| 422 | `BUSINESS_RULE` | 业务规则不满足（状态机非法转移） |
| 422 | `NO_PREFERENCES` | 用户未完成问卷 |
| 429 | `RATE_LIMITED` | 限流 |
| 500 | `INTERNAL_ERROR` | 服务器异常 |
| 503 | `AI_UNAVAILABLE` | Gemini 不可用且无缓存 |
| 503 | `QUEUE_UNAVAILABLE` | 队列消费者不可用（罕见） |

### 1.3 权限说明

- `public` — 无需登录
- `auth` — 需要登录
- `verified` — 需要已验证邮箱
- `admin` — 需要 `is_admin=1`（Sprint 3 启用）

## 2. API 端点清单

### 2.0 端点总览（v1.1）

Sprint 1 共 **26 个端点**，按域分组如下：

| 域 | 端点数 | 路由示例 |
|---|---|---|
| **§2.1 认证** | 2 公开 + 2 鉴权 = **4** | `POST /api/register` · `POST /api/login` · `POST /api/logout` · `GET /api/me` |
| **§2.2 商品 & 分类** | 2 商品 + 1 分类 = **3** | `GET /api/products` · `GET /api/products/{id}` · `GET /api/categories` |
| **§2.3 购物车** | 4 | `GET/POST/PATCH/DELETE /api/cart[/{item}]` |
| **§2.4 订单** | 4 | `GET/POST /api/orders` · `GET /api/orders/{id}` · `POST /api/orders/{id}/pay` |
| **§2.5 问卷** | 2 | `GET/POST /api/survey` |
| **§2.6 AI 菜单** | 2 | `GET /api/menu/today` · `POST /api/menu/regenerate` |
| **§2.7 订阅** | 3 | `GET/POST /api/subscriptions` · `DELETE /api/subscriptions/{id}` |
| **§2.8 Webhook** | 2 | `POST /api/stripe/webhook` · `POST /api/payme/webhook` |
| **§2.9 调试（staging）** | 2 | `GET /api/test/orders/{orderId}` · `POST /api/test/tick` |
| **合计** | **26** | — |

所有路由通过 `routes/api.php` 声明，**`bootstrap/app.php` 的 `apiPrefix: 'api'` 自动加 `/api` 前缀**。

### 2.1 认证（Auth）

#### `POST /register`

- **权限**：public
- **入参**：`name`, `email`, `password`, `password_confirmation`, `locale?`
- **返回**：`201 { user, access_token? }`
- **错误**：`400 VALIDATION_FAILED`、`409 EMAIL_TAKEN`
- **说明**：注册成功自动登录，返回 Sanctum token（如启用）

#### `POST /login`

- **权限**：public
- **入参**：`email`, `password`, `remember?`
- **返回**：`200 { user }`（Session Cookie 自动 set）
- **错误**：`401 INVALID_CREDENTIALS`

#### `POST /logout`

- **权限**：auth
- **入参**：无
- **返回**：`204 No Content`
- **错误**：`401 UNAUTHENTICATED`

#### `GET /me`

- **权限**：auth
- **入参**：无
- **返回**：`200 { user: { id, name, email, phone, locale, preferences, notification_preferences } }`
- **错误**：`401`

### 2.2 商品与分类（Product & Category）

#### `GET /api/products`

- **权限**：public
- **查询参数**：`category_id?`, `is_organic?`, `q?`（搜索）, `sort?`(`price_asc|price_desc|newest`), `page?`, `per_page?`(默认 20)
- **返回**：`200 { data: [Product], meta: { pagination } }`
- **Product 字段**：`id, name, description, price, image, carbon_footprint, stock, is_organic, origin, category{id,name}`
- **缓存**：Redis 5min 标签 `products:list`

#### `GET /api/products/{id}`

- **权限**：public
- **返回**：`200 { data: Product }`
- **错误**：`404`

#### `GET /api/categories`

- **权限**：public
- **返回**：`200 { data: [Category] }`（含 `children` 二级嵌套）
- **缓存**：Redis 1h 标签 `categories:tree`

### 2.3 购物车（Cart）

> 购物车对登录用户持久化；未登录用户用 Session 临时存储。

#### `GET /api/cart`

- **权限**：auth（或 public+session）
- **返回**：`200 { items: [{ id, product, quantity, subtotal }], total, item_count }`

#### `POST /api/cart`

- **权限**：auth
- **入参**：`product_id`, `quantity`(>=1)
- **返回**：`201 { item }`
- **错误**：`404 PRODUCT_NOT_FOUND`、`409 OUT_OF_STOCK`

#### `PATCH /api/cart/{item}`

- **权限**：auth
- **入参**：`quantity`(>=0，0 表示删除)
- **返回**：`200 { item }`
- **错误**：`403 NOT_OWNER`、`404`

#### `DELETE /api/cart/{item}`

- **权限**：auth
- **返回**：`204`
- **错误**：`403 NOT_OWNER`、`404`

### 2.4 订单（Order）

#### `POST /api/orders`

- **权限**：auth
- **入参**：`items: [{product_id, quantity}]`, `shipping_address`, `coupon_code?`, `user_subscription_id?`
- **返回**：`201 { order: { id, order_no, status, total_price, items } }`
- **错误**：`400 VALIDATION_FAILED`、`409 OUT_OF_STOCK`、`422 MIN_ORDER_AMOUNT`
- **副作用**：扣库存（事务）、生成 `order_no`、清空购物车

#### `GET /api/orders`

- **权限**：auth
- **查询参数**：`status?`, `page?`, `per_page?`
- **返回**：`200 { data: [Order], meta }`

#### `GET /api/orders/{id}`

- **权限**：auth（仅本人）/ admin
- **返回**：`200 { data: Order }`
- **错误**：`403 NOT_OWNER`、`404`

#### `POST /api/orders/{id}/pay`

- **权限**：auth（仅本人）
- **入参**：`provider`(`stripe|payme`), `return_url`
- **返回**：`200 { payment: { id, provider, amount, status }, redirect_url? }`
- **错误**：`403 NOT_OWNER`、`409 ORDER_ALREADY_PAID`、`422 INVALID_STATUS`
- **说明**：写 `payments` 表 pending，调支付网关，返回跳转 URL；支付完成由 webhook 更新

### 2.5 健康问卷（Survey）

#### `GET /api/survey`

- **权限**：auth
- **返回**：`200 { data: UserPreference | null }`（未填写时为 null）

#### `POST /api/survey`

- **权限**：auth
- **入参**：`usage_purpose`, `dietary_habits`, `goals`, `allergies?`(array), `household_size?`, `cooking_skill?`（`Beginner`/`Intermediate`/`Advanced`，与 er-diagram §2.4 ENUM 一致）, `budget_hkd?`（DECIMAL(8,2)）
- **返回**：`200 { data: UserPreference }`
- **错误**：`400 VALIDATION_FAILED`
- **副作用**：upsert `user_preferences`；触发（异步）`AiMenuService::generateDailyMenu` 并写 `daily_menus`

### 2.6 AI 菜单（AI Menu）

#### `GET /api/menu/today`

- **权限**：auth
- **返回**：`200 { data: { date, content, source, cached } }`
- **错误**：`404 NO_PREFERENCES`（未填问卷）、`503 AI_UNAVAILABLE`
- **逻辑**：查询 `daily_menus(user_id, today)`；命中直接返回 `cached=true`；未命中调 Gemini 并落库

#### `POST /api/menu/regenerate`

- **权限**：auth
- **入参**：`override_preferences?`(本次使用的偏好覆盖)
- **返回**：`200 { data: { date, content, source, tokens_used } }`
- **错误**：`429 RATE_LIMITED`（每日最多 3 次）、`503 AI_UNAVAILABLE`
- **限流**：单用户 3 次/天（Redis 计数器）

### 2.7 订阅（Subscription）

#### `GET /api/subscriptions`

- **权限**：auth
- **返回**：`200 { plans: [SubscriptionPlan], user_subscriptions: [UserSubscription] }`

#### `POST /api/subscriptions`

- **权限**：auth
- **入参**：`subscription_plan_id`, `start_date`, `auto_renew?`
- **返回**：`201 { data: UserSubscription }`
- **错误**：`404 PLAN_NOT_FOUND`、`409 ALREADY_ACTIVE`
- **副作用**：计算 `next_fulfillment_at`；第一次履约由 `SubscriptionFulfillJob` 排队

#### `DELETE /api/subscriptions/{id}`

- **权限**：auth（仅本人）
- **入参**：无
- **返回**：`200 { data: UserSubscription }`（状态变为 `cancelled`，`end_date` 设为当前周期末）
- **错误**：`403 NOT_OWNER`、`409 ALREADY_CANCELLED`

### 2.8 Webhook（支付网关回调，P0 #4 修复）

> 设计目标：解决 edge-cases D-05 事件去重 + 重放保护；确保支付成功事件可幂等触发 `OrderService::transition(pending → paid)`。
> **重要**：webhook 端点**不要求 auth**（网关无法携带 Sanctum cookie），改用「签名校验 + provider_event_id 去重」鉴权。

#### `POST /api/stripe/webhook`

- **权限**：public（仅 Stripe 网关 IP + 签名校验）
- **签名校验**：通过 `Stripe-Signature` 头 + `STRIPE_WEBHOOK_SECRET`（env）做 HMAC-SHA256 校验
- **入参**（application/json）：Stripe 原始事件 payload
- **处理流程**：
  1. 验签失败 → `401 INVALID_SIGNATURE`
  2. 写入 `stripe_webhook_events`（`provider_event_id` UQ 去重；已存在则直接返回 200 幂等）
  3. 派发 `ProcessStripeWebhookJob` 队列任务
  4. 任务内根据 `event_type` 路由：
     - `payment_intent.succeeded` → 查 `payments` → 触发 `OrderService::transition(pending → paid)`
     - `payment_intent.payment_failed` → 写 `payments.status = failed`，订单保持 `pending`，触发超时取消逻辑
     - `charge.refunded` → 触发 `OrderService::transition(* → refunded)`
     - 未知事件类型 → `status = ignored`，记录 audit
- **返回**：`200 { received: true }`（必须 2xx，否则 Stripe 会重试 3 天）
- **错误**：`401 INVALID_SIGNATURE`、`503 QUEUE_UNAVAILABLE`（罕见）
- **去重保证**：`stripe_webhook_events.provider_event_id` UQ 约束 + 处理前 SELECT 一次
- **关联查询**：`README/webhook.md`（待 devops 补充 Stripe Dashboard 配置说明）

#### `POST /api/payme/webhook`（P0 #4 预留）

- **权限**：public（仅 PayMe 网关 + 签名校验）
- **入参**：PayMe 回调 JSON（结构与 Stripe 不同）
- **处理流程**：同 Stripe 入口，复用 `stripe_webhook_events` 表，`provider = 'payme'`
- **Sprint 范围**：MVP 仅占位签名校验 + 落库去重，业务路由在 Sprint 2 接入

### 2.9 调试端点（仅 staging/test，P1 #3 修复）

> 与 qa-agent 协调：S4 Playwright 用例中的"DB 断言"与"time travel"通过 Laravel Factories + 容器时钟注入实现，**不暴露调试端点到生产**。

#### `GET /api/test/orders/{orderId}`（staging-only）

- **权限**：auth + `APP_ENV !== 'production'`
- **用途**：QA 用例 S4 直接断言 DB 状态，绕过 Eloquent
- **生产环境**：路由不注册（`routes/api.php` 用 `if (app()->environment('testing', 'staging'))` 包裹）

#### `POST /api/test/tick`（staging-only）

- **入参**：`{ "advance_seconds": 1800 }`
- **用途**：推进 Carbon 容器时钟，加速超时取消等用例
- **生产环境**：同上，不暴露

## 3. 鉴权与限流矩阵

| 端点 | 鉴权 | 限流（IP/user） |
|---|---|---|
| `POST /register` | public | 10/h |
| `POST /login` | public | 20/h |
| `GET /api/products` | public | 60/min |
| `GET /api/products/{id}` | public | 120/min |
| `GET /api/categories` | public | 60/min |
| 购物车 4 个端点 | auth | 120/min |
| `POST /api/orders` | auth | 30/min |
| `POST /api/orders/{id}/pay` | auth | 10/min |
| 问卷 2 个端点 | auth | 30/min |
| `GET /api/menu/today` | auth | 60/min |
| `POST /api/menu/regenerate` | auth | 3/day |
| 订阅 3 个端点 | auth | 30/min |
| `POST /api/stripe/webhook` | public + 签名 | 无限（靠去重表） |
| `POST /api/payme/webhook` | public + 签名 | 无限（靠去重表） |
| 调试端点 `/api/test/*` | auth + non-prod | 仅 staging |

## 4. 路由文件结构（建议）

```php
// routes/api.php
// 注：Laravel 12 通过 bootstrap/app.php 的 apiPrefix:'api' 自动为所有路由加 /api 前缀
// 所以 Route::post('/register') 实际暴露为 POST /api/register
Route::post('/register', [AuthController::class, 'register']); // → POST /api/register
Route::post('/login',    [AuthController::class, 'login']);    // → POST /api/login

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    Route::get  ('/cart',          [CartController::class, 'index']);
    Route::post ('/cart',          [CartController::class, 'store']);
    Route::patch('/cart/{item}',   [CartController::class, 'update']);
    Route::delete('/cart/{item}',  [CartController::class, 'destroy']);

    Route::get   ('/orders',                 [OrderController::class, 'index']);
    Route::post  ('/orders',                 [OrderController::class, 'store']);
    Route::get   ('/orders/{order}',         [OrderController::class, 'show']);
    Route::post  ('/orders/{order}/pay',     [PaymentController::class, 'pay']);

    Route::get ('/survey', [SurveyController::class, 'show']);
    Route::post('/survey', [SurveyController::class, 'store']);

    Route::get ('/menu/today',         [MenuController::class, 'today']);
    Route::post('/menu/regenerate',    [MenuController::class, 'regenerate']);

    Route::get   ('/subscriptions',                 [SubscriptionController::class, 'index']);
    Route::post  ('/subscriptions',                 [SubscriptionController::class, 'store']);
    Route::delete('/subscriptions/{subscription}',  [SubscriptionController::class, 'destroy']);
});

// webhook（无 auth，签名校验）
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);
Route::post('/payme/webhook',  [PaymeWebhookController::class, 'handle']);

// staging-only 调试端点
if (app()->environment('testing', 'staging')) {
    Route::middleware('auth:sanctum')->prefix('test')->group(function () {
        Route::get('orders/{order}',  [TestController::class, 'showOrder']);
        Route::post('tick',           [TestController::class, 'tickClock']);
    });
}

// public
Route::get('/products',         [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/categories',       [CategoryController::class, 'index']);
```

## 5. 版本与演进

- 当前：`v1`（无前缀，URL 路径隐含）
- 后续：如出现破坏性变更，引入 `/api/v2/...`；旧版本至少保留 6 个月
- OpenAPI 规范文件位置（待生成）：`docs/bmad/openapi.yaml`（Sprint 1 由 reviewer-agent 输出）
