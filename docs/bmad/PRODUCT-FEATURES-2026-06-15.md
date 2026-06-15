# GreenBite / FreshToday-AI 产品功能清单

> **品牌**：GreenBite（HK）= FreshToday-AI
> **定位**：香港本地有机农产品 + AI 个性化菜单订阅电商
> **目标用户**：注重健康的家庭（30-45岁）、港九高净值白领（25-40岁）、精品餐厅（B2B 后期）
> **基线**：Laravel 12 + Sprint 1 Day 5 / Day 6 状态
> **梳理日期**：2026-06-15

---

## §0 整体健康度

| 维度 | 现状 | 说明 |
|---|---|---|
| API 端点 | **26 个**（其中鉴权 20、公开 6）| `routes/api.php` 已 100% 注册 |
| Service 层 | 5 个（Order/Payment/AiMenu/Subscription/Notification）| Order 状态机是 SSOT |
| Model 层 | 17 个实体 | 关系 + fillable + casts 完整 |
| Controller 层 | 13 个（API 10 + Web 3）| 2026-06-15 重写 web SurveyController 302→SPA |
| 定时任务 | 4 个 | 取消超时订单 / 自动确认收货 / 履约订阅 / 生成菜单 |
| PHPUnit | **43/54 = 79.6%** | Day 5 修复后 +7.4 个百分点 |
| 支付集成 | 3 通道枚举 | Stripe (Sprint 1 mock)、Payme (mock)、AlipayHK (待接入) |
| 多语种 | 3 语（zh-HK / en / zh-CN）| SetLocale Middleware 已就位 |
| 鉴权方式 | Sanctum PAT | 2026-06-15 切换（替换 session） |

---

## §1 MVP 核心闭环（5 个最关键功能）

> 一个新用户走完这 5 步，GreenBite 价值主张就跑通了。

1. **注册** → 拿到 token → 看到自己账户
2. **完成问卷**（饮食目标、过敏、烹饪技能、预算） → 触发 AI 菜单生成
3. **看今日菜单** → 决定下单
4. **加入购物车 → 下单 → 支付（Stripe/Payme/AlipayHK 3 选 1）** → 订单流转到已支付
5. **订阅周/双周/月套餐** → 自动续期 / 自动履约发货

---

## §2 模块功能清单

### 🟩 模块 A：用户与认证

| # | 功能点 | 端到端说明 | 端点 / 控制器 | 状态 |
|---|---|---|---|---|
| A1 | 邮箱注册 | 输入 name/email/password/可选 locale；自动登录；返 token | `POST /api/register` · `Api\AuthController@register` | ✅ 已实现 |
| A2 | 登录 | email+password → Sanctum PAT | `POST /api/login` · `Api\AuthController@login` | ✅ 已实现 |
| A3 | 登出 | 撤销当前 token | `POST /api/logout` · `Api\AuthController@logout` | ✅ 已实现 |
| A4 | 我的信息 | 返 user + preferences + notification pref | `GET /api/me` · `Api\AuthController@me` | ✅ 已实现 |
| A5 | 多语种偏好 | 3 语可选，注册时入 `users.locale` | `User.locale` 字段 | ✅ 已实现 |
| A6 | Sanctum PAT 鉴权 | Laravel Sanctum `HasApiTokens` | `personal_access_tokens` 表 | ✅ 已实现 |
| A7 | i18n 中间件 | 优先级：`?lang=` > cookie > `Accept-Language` > 默认 | `App\Http\Middleware\SetLocale` | ✅ 已实现 |
| A8 | Google OAuth 登录 | "使用 Google 一键登录" | — | ❌ 未实现（PRD E1 AC-1.2）|
| A9 | 邮箱验证 | 注册后发验证邮件，30min 内激活 | — | ❌ 未实现（`email_verified_at` 字段已落）|
| A10 | 忘记密码 | 邮箱重置链接，1h 有效 | — | ❌ 未实现 |
| A11 | 多地址管理 | 多个配送地址 + 默认地址 | — | ❌ 未实现（`default_shipping_address` JSON 已落）|
| A12 | Admin 后台 | `is_admin=1` + admin guard | — | 🟡 半成品（字段就绪，路由/中间件未做）|

### 🛒 模块 B：商品与目录

| # | 功能点 | 端到端说明 | 端点 / 控制器 | 状态 |
|---|---|---|---|---|
| B1 | 商品列表 | 支持 category / is_organic / q（搜索）/ sort / 分页 | `GET /api/products` · `Api\ProductController@index` | ✅ 已实现 |
| B2 | 商品详情 | 价格、库存、碳足迹、产地、有机徽章 | `GET /api/products/{id}` · `Api\ProductController@show` | ✅ 已实现 |
| B3 | 商品缓存 | 列表 5min Redis/array 缓存（key 含 query 哈希）| `Cache::remember` | ✅ 已实现 |
| B4 | 分类树 | 二级嵌套分类（蔬菜→叶菜），1h 缓存 | `GET /api/categories` · `Api\CategoryController@index` | ✅ 已实现 |
| B5 | 溯源数据 | `is_organic` / `origin`（本地农场）/ `carbon_footprint`（kg CO2e）| `products` 表 | ✅ 已实现 |
| B6 | Web 目录页（/catalog）| Blade 渲染，与 API 共享 Product Eloquent | `app/Http/Controllers/ProductController@index` | ✅ **2026-06-15 重写**（消除硬编码 mock）|
| B7 | 后台商品 CRUD | 上下架 / 调价 / 改库存 / 批量导入 | — | ❌ 未实现（PRD E8 P0）|
| B8 | 全文搜索 | Meilisearch + 中文分词 | — | ❌ 未实现 |

### 🧺 模块 C：购物车

| # | 功能点 | 端到端说明 | 端点 / 控制器 | 状态 |
|---|---|---|---|---|
| C1 | 查看购物车 | items + total + item_count | `GET /api/cart` · `Api\CartController@index` | ✅ 已实现 |
| C2 | 加购 | product_id + quantity；库存校验 | `POST /api/cart` | ✅ 已实现 |
| C3 | 改数量 | quantity=0 等同删除 | `PATCH /api/cart/{item}` | ✅ 已实现 |
| C4 | 删除 | 单项删除 | `DELETE /api/cart/{item}` | ✅ 已实现 |
| C5 | 归属校验 | 改/删仅限本人 item | `authorizeOwner()` | ✅ 已实现 |

### 📦 模块 D：订单（核心业务，状态机 SSOT）

| # | 功能点 | 端到端说明 | 端点 / 控制器 | 状态 |
|---|---|---|---|---|
| D1 | 创建订单 | items + shipping + 可选 coupon/sub-id → 库存预占 → 总价计算 | `POST /api/orders` · `Api\OrderController@store` | ✅ 已实现 |
| D2 | 我的订单列表 | 支持按 status 过滤 + 分页 | `GET /api/orders` | ✅ 已实现 |
| D3 | 订单详情 | 含 products / payments / statusLogs | `GET /api/orders/{id}` | ✅ 已实现 |
| D4 | 发起支付 | provider: stripe/payme/alipay_hk；返 redirect_url | `POST /api/orders/{id}/pay` | ✅ 已实现 |
| D5 | 状态机 SSOT | **7 态**（pending→paid→processing→shipped→delivered, +cancelled, +refunded）| `app/Enums/OrderStatus` + `OrderService::transition()` | ✅ 已实现 |
| D6 | 守卫 G0–I3 | GUARD-G0 归属 / GUARD-G1 合法转移 / GUARD-I1 库存 / GUARD-P1/P2/P3 支付 / GUARD-COUPON 优惠 | `OrderService` 内部 | ✅ 已实现 |
| D7 | 自动取消 | 30min 未支付 → cancelled（每日 5min 轮询）| `CancelExpiredOrdersJob` + `routes/console.php:23` | ✅ 已实现 |
| D8 | 自动确认收货 | shipped 超 7 天 → delivered（每日 02:00）| `AutoDeliverOrdersJob` | ✅ 已实现 |
| D9 | 取消库存释放 | 状态转 cancelled → 库存 + 回滚 | `OrderService::releaseStock` | ✅ 已实现 |
| D10 | 审计日志 | 每次状态转移落 `order_status_logs`（不变量 #2）| `OrderStatusLog` | ✅ 已实现 |
| D11 | 退款触发 | 状态机转 refunded → PaymentService::refund → 实际调网关 | `OrderService::handleRefund` | 🟡 业务已通，**实际调网关**仍 mock |
| D12 | Admin 改价 | 用 `Order::recalculateTotal` 改总价 | — | 🟡 方法已标 `@deprecated`，**路由未实现** |

### 💳 模块 E：支付与退款

| # | 功能点 | 端到端说明 | 端点 / 控制器 | 状态 |
|---|---|---|---|---|
| E1 | 创建支付意图 | provider + return_url → DB 写 payment pending → mock 重定向 URL | `PaymentService::createIntent` | ✅ 已实现（Sprint 1 mock 阶段）|
| E2 | Stripe Webhook | 验签 → 落库去重 → 路由到具体业务 | `POST /api/stripe/webhook` · `StripeWebhookController` | ✅ 已实现（HMAC 简化版，生产建议 `Stripe\Webhook::constructEvent`）|
| E3 | Payme Webhook | 同 Stripe 流程，复用 `StripeWebhookEvent` 表（provider='payme'）| `POST /api/payme/webhook` | ✅ 已实现（验签未做）|
| E4 | AlipayHK 支付 | — | — | 🟡 yaml 枚举已含 `'alipay_hk'`，**网关未接入** |
| E5 | 幂等去重 | 同 event_id 重放不重复处理 | `StripeWebhookEvent` 表 UQ | ✅ 已实现 |
| E6 | Webhook 失败重试 | status='failed' + last_error 落库 | `stripe_webhook_events` 表 | ✅ 已实现 |
| E7 | 退款 | amount + reason → payment.status='refunded' → 触发状态机 | `PaymentService::refund` | 🟡 业务已通，**实际退款**仍 mock |
| E8 | 退款并发 | 两个 webhook 同时 refund 一个订单 → 唯一性保证 | — | 🟡 ConcurrentRefundTest 2 fail 中，**Sprint 2 修** |

### 🗓️ 模块 F：订阅与套餐

| # | 功能点 | 端到端说明 | 端点 / 控制器 | 状态 |
|---|---|---|---|---|
| F1 | 套餐列表 | 所有 active 套餐 + 当前用户订阅 | `GET /api/subscriptions` · `Api\SubscriptionController@index` | ✅ 已实现 |
| F2 | 订阅套餐 | plan_id + start_date + auto_renew → 写 user_subscriptions | `POST /api/subscriptions` | ✅ 已实现 |
| F3 | 取消订阅 | reason → status='cancelled' + cancel_reason | `DELETE /api/subscriptions/{id}` | ✅ **2026-06-15 修复**（cancel_reason 写库）|
| F4 | 履约订单生成 | 到期订阅 → 自动建订单 + 滚动 next_fulfillment_at | `SubscriptionService::fulfillDueSubscriptions` | ✅ 已实现 |
| F5 | 4 态状态机 | active / paused / cancelled / expired（**不在订单 SSOT 内**）| ER + ADR-0005 §2.4 | 🟡 字段已有，**`subscription_state_machine.md` 文档待建**（NEW-P2-04）|
| F6 | 防重复订阅 | 已有 active 订阅 → GUARD-SUB 拒绝 | `SubscriptionService::subscribe` | ✅ 已实现 |
| F7 | 定时履约 | 每日 03:00 扫表 → 为到期订阅生成订单 | `FulfillSubscriptionsJob` + `routes/console.php:33` | ✅ 已实现 |

### 🧠 模块 G：AI 智能（菜单生成）

| # | 功能点 | 端到端说明 | 端点 / 控制器 | 状态 |
|---|---|---|---|---|
| G1 | 完成问卷 | 7 字段（usage_purpose / dietary_habits / goals / allergies / household_size / cooking_skill / budget_hkd） | `POST /api/survey` · `Api\SurveyController@store` | ✅ 已实现 |
| G2 | 异步生成菜单 | 问卷提交 → 派发 `GenerateDailyMenuJob` → 调 Gemini | `GenerateDailyMenuJob` | ✅ 已实现（**Gemini 无 key 时降级**到模板）|
| G3 | 今日菜单 | 返 date + content + source（gemini/fallback）+ cached | `GET /api/menu/today` · `Api\MenuController@today` | 🟡 **路径有 bug**，实为 404（**2026-06-15 待修**）|
| G4 | 重新生成 | override_preferences 可选；GUARD-AI-1 限流 3/24h | `POST /api/menu/regenerate` | ✅ 已实现 |
| G5 | 三层降级 | L1 Gemini → L2 模板 → L3 占位 | `AiMenuService::generateDailyMenuForUser` | ✅ 已实现（ADR-0006）|
| G6 | 菜单 token 用量 | `daily_menus.tokens_used` 字段记录 | 2026-06-15 migration | ✅ 已实现 |
| G7 | 每日批量生成 | 每日 04:00 遍历有 pref 的用户派发生成任务 | `routes/console.php:37-43` | ✅ 已实现 |

### 🎫 模块 H：优惠与营销

| # | 功能点 | 端到端说明 | 端点 / 控制器 | 状态 |
|---|---|---|---|---|
| H1 | 优惠券 | code → 校验 + 折扣 + 标记已用 | `Coupon` + `UserCoupon` 表 | ✅ 已实现 |
| H2 | 最低消费 | `min_order_amount` 校验 | `Coupon::isValidForAmount` | ✅ 已实现 |
| H3 | 用户领券 | 领/用状态（claimed → used）| `UserCoupon` | ✅ 已实现 |
| H4 | 积分 | 消费/活动返积分；`points_transactions` 表 | — | ❌ 字段已落，**CRUD 未做**（Sprint 3）|

### 🔔 模块 I：通知

| # | 功能点 | 端到端说明 | 端点 / 控制器 | 状态 |
|---|---|---|---|---|
| I1 | 订单更新通知 | 邮件/站内信，统一入口 | `NotificationService::sendOrderUpdate` | 🟡 **仅 Log::info**（无 Mail 接入）|
| I2 | 每日菜单提醒 | 邮件给已开启 `email_menu` 的用户 | `sendMenuReminder` | 🟡 同上 |
| I3 | 订阅续费提醒 | 到期前 N 天提醒 | `sendSubscriptionRenewal` | 🟡 同上 |
| I4 | 用户偏好 | 邮箱/推送开关、安静时段 | `NotificationPreference` 表 | ✅ 已实现 |
| I5 | 安静时段 | quiet_hours 内不发送 | `isQuietHours()` | ✅ 已实现 |
| I6 | 自动建默认偏好 | 首次访问时 firstOrCreate 默认开启 | `getPrefs()` | ✅ 已实现 |

### 🛠️ 模块 J：系统与平台

| # | 功能点 | 端到端说明 | 端点 / 控制器 | 状态 |
|---|---|---|---|---|
| J1 | 限流 | 60 req/min/IP，user 优先 | `RateLimiter::for('api', ...)` | ✅ **2026-06-15 修复** |
| J2 | API 错误格式 | `{"error": {"code": "...", "message": "..."}}` 统一 | `bootstrap/app.php` 异常处理器 | ✅ 已实现 |
| J3 | 401 鉴权 | 鉴权失败返 JSON 401，**不重定向** login 路由 | `bootstrap/app.php` | ✅ **2026-06-15 修复** |
| J4 | 422 业务错误 | GUARD-P2、InvalidTransition 返 422 | 同上 | ✅ 已实现 |
| J5 | 健康检查 | `/up` | `bootstrap/app.php:17` | ✅ Laravel 默认 |
| J6 | Web 端 | `/`、`/catalog`、`/login`、`/subscriptions`、`/orders`、`/cart`、`/dashboard` | `routes/web.php` | ✅ 已实现 |
| J7 | SPA 重定向 | `/survey` 浏览器访问 → 302 → `/dashboard#survey` | `app/Http/Controllers\SurveyController` | ✅ **2026-06-15 重写** |
| J8 | 调试端点 | `/api/test/tick` 时间穿越（仅 testing/staging）| `routes/api.php:35-40` | ✅ 已实现（环境守卫）|

---

## §3 数据模型（17 实体）

```
User ─┬─ orders
      ├─ userPreferences
      ├─ dailyMenus
      ├─ userSubscriptions ── subscriptionPlan ─┐
      │                              │         products
      │                              └───────── products (pivot: quantity, price)
      ├─ cartItems ── product
      ├─ userCoupons ── coupon
      ├─ pointsTransactions   (Sprint 3)
      └─ notificationPreference

Product ── category
Order ─┬─ products (pivot: quantity, price)
       ├─ payments
       ├─ statusLogs
       └─ userSubscription

Payment ── order
StripeWebhookEvent ── (payment, order) related_* nullable
```

---

## §4 营收相关功能（已加粗标黄）

- **A1/A2 鉴权**：注册/登录是所有营收的入口
- **D1 创建订单 + D4 发起支付**：单次购买营收
- **E1 创建支付意图 + E2/E3 Webhook**：3 通道支付（Stripe / Payme / AlipayHK）
- **E7 退款**：售后保障，影响留存
- **F2 订阅套餐 + F4 自动履约**：**持续营收**（LTV 核心）
- **H1 优惠券**：促活转化
- **H4 积分**（Sprint 3）：复购激励

---

## §5 当前可演示 URL（本地启动后）

```bash
bash scripts/dev.sh serve   # http://127.0.0.1:8000
```

### Web 端
| URL | 页面 | 数据 |
|---|---|---|
| `/` | 首页 / 欢迎页 | 静态 |
| `/catalog` | 产品目录 | 走 Eloquent 真实数据 |
| `/login` | 登录页 | 静态 |
| `/survey` | 问卷入口 | 302 → SPA |
| `/dashboard` | 仪表盘 | 读 session |
| `/subscriptions` | 订阅页 | 静态 |
| `/orders` | 订单页 | 静态（占位）|
| `/cart` | 购物车 | 静态（占位）|

### API 端（共 26 个，详见 README.md §API 端点表）
- 公开：`GET /api/products`、`GET /api/products/{id}`、`GET /api/categories`、`POST /api/register`、`POST /api/login`
- 鉴权：`/api/me`、`/api/logout`、`/api/cart/*`、`/api/orders/*`、`/api/survey/*`、`/api/menu/*`、`/api/subscriptions/*`
- Webhook：`/api/stripe/webhook`、`/api/payme/webhook`

---

## §6 仍待实现（Sprint 2 优先级）

| P | 功能 | 影响 |
|---|---|---|
| 🔴 | `D1` 路径修复：`/api/menu/today` 路由 404 | 阻塞产品演示 |
| 🔴 | 邮件真实发送（NotificationService → Mail::send）| 用户体验 |
| 🔴 | AlipayHK 真实接入 | 港九核心市场 |
| 🟠 | 真实 Stripe SDK 验签（`\Stripe\Webhook::constructEvent`）| 安全合规 |
| 🟠 | 后台 Admin（商品 CRUD、订单管理、退款审核）| 运营效率 |
| 🟠 | Google OAuth / 邮箱验证 / 找回密码 | 注册转化率 |
| 🟡 | Meilisearch 全文搜索 | 搜索体验 |
| 🟡 | B2B 餐厅版（Sprint 4 范围）| 业务扩张 |
| 🟡 | 积分系统（Sprint 3）| 复购激励 |

---

## §7 维护说明

- **新功能** 加到对应模块表，状态用 ✅/🟡/❌
- **状态机变更** 必须同步 `docs/bmad/order-state-machine.md` 附录 A + ADR-0005 §2.4
- **新增 API 端点** 必须在 `docs/bmad/openapi.yaml` 注册（NEW-P2-03）
- **新增支付通道** 必须更新 `OrderController::pay` 验证规则 + `PaymentService::callGatewayCreate` 分发
