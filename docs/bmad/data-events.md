# GreenBite MVP 埋点事件字典 (Data Events Dictionary)

> **文档编号**：GB-DATA-001
> **责任人**：data-analyst-agent (hotel)
> **版本**：v1.0
> **日期**：2026-06-12
> **适用范围**：GreenBite MVP Sprint 1-Day2 → Sprint 4 全周期
> **下游兼容**：Google Analytics 4 (GA4) · Mixpanel · Amplitude · PostHog · 自建 ClickHouse
> **合规依据**：PDPO (HK) · GDPR 风格合规 · 公司《数据保留政策 v1.2》

---

## 0. 命名与公共规范

### 0.1 事件命名约定
- 全部使用 **snake_case**，全部小写，避免驼峰
- 动词优先（`created` / `viewed` / `updated`），名词作为修饰
- 用户主动行为用过去式（`survey_completed`）；系统行为用过去分词（`webhook_received`）
- 禁止使用 `event1` / `click_button` / `test_event` 这类占位名

### 0.2 公共字段 (Common Properties)
所有事件均带以下公共字段，由 SDK 自动注入，**业务代码不需要重复传递**：

| 字段 | 类型 | 必填 | 说明 | 隐私 |
| --- | --- | --- | --- | --- |
| `event_id` | UUID v7 | 是 | 事件唯一 ID（用于去重） | 否 |
| `event_name` | string | 是 | 事件名（与本字典一致） | 否 |
| `occurred_at` | ISO8601 datetime | 是 | 客户端发生时间，UTC+0 | 否 |
| `received_at` | ISO8601 datetime | 是 | 服务端接收时间，UTC+0 | 否 |
| `user_id` | string \| null | 条件 | 已登录用户的 UUID | SHA-256 匿名化可选 |
| `anonymous_id` | UUID | 是 | 未登录用户 cookie 标识 | 否 |
| `session_id` | string | 是 | 会话 ID（30 min 无活动过期） | 否 |
| `platform` | enum | 是 | `web` / `ios` / `android` / `server` | 否 |
| `app_version` | string | 是 | 形如 `1.0.0+sha.abc1234` | 否 |
| `locale` | string | 是 | `en` / `zh-HK` / `zh-CN` | 否 |
| `country` | string | 是 | ISO 3166-1 alpha-2，默认 `HK` | 否 |
| `tz_offset_minutes` | int | 是 | 客户端时区偏移，用于人时计算 | 否 |
| `user_agent` | string | 否 | 仅服务端记录 | 否 |
| `ip` | string | 否 | 服务端记录，**12 个月后截断** | 是 (PDPO) |

### 0.3 字段类型映射 (GA4 / Mixpanel)
| 字典类型 | GA4 维度 | Mixpanel 属性类型 | ClickHouse 类型 |
| --- | --- | --- | --- |
| `string` | Custom dimension | string | `LowCardinality(String)` |
| `int` | Custom metric | number | `Int64` |
| `float` | Custom metric | number | `Float64` |
| `bool` | Custom dimension | boolean | `UInt8` |
| `enum` | Custom dimension | string | `Enum8` |
| `timestamp` | Custom dimension | datetime | `DateTime64(3)` |
| `array<string>` | Custom dimension | list | `Array(String)` |
| `object` | Custom dimension | object | `Map(String, String)` (扁平化) |

### 0.4 隐私分级
- **L0 公开**：无个人信息（`event_name` / `platform` / `app_version`）
- **L1 弱标识**：`anonymous_id` / `session_id`（无法直接关联自然人）
- **L2 强标识**：`user_id` / `email` / `phone`（需登录态 + 加密存储）
- **L3 敏感**：`health` / `diet` / `allergy` / `address`（额外同意 + 字段级加密）

> 事件字段标注的「隐私」列指**该字段所属分级**。L2/L3 字段出报表前必须 SHA-256 + salt 脱敏。

### 0.5 必填与可空
- `*` 标记表示**强制**，缺失则事件拒绝入库（HTTP 422）
- 未标 `*` 字段可空，但 `null` 与缺失在漏斗分析中等价

---

## 1. 用户类事件 (User Events)

### 1.1 `register`
- **触发位置**：`app/Http/Controllers/Api/AuthController.php::register()`
- **触发时点**：用户提交注册表单通过验证、User 模型 created_at 落库后
- **频率上限**：每 `user_id` 仅 1 次
- **字段 schema**：
```json
{
  "event_name": "register",
  "user_id": "* (uuid)",
  "register_method": "* (enum: email | google | apple | wechat)",
  "referrer_source": "string (utm_source)",
  "referrer_campaign": "string (utm_campaign)",
  "locale": "* (string)",
  "is_newsletter_opted_in": "bool (default false)",
  "is_pdpo_consent_given": "* (bool, must be true)"
}
```
- **必填**：`user_id` / `register_method` / `locale` / `is_pdpo_consent_given`
- **隐私**：`user_id` 为 L2；其余 L0-L1
- **失败处理**：注册失败不发 `register`；改为发 `validation_failed`（见 §7.2）

### 1.2 `login`
- **触发位置**：`app/Http/Controllers/Api/AuthController.php::login()` / `socialLogin()`
- **触发时点**：成功签发 Sanctum token
- **字段 schema**：
```json
{
  "event_name": "login",
  "user_id": "* (uuid)",
  "login_method": "* (enum: email | google | apple | wechat | sso)",
  "is_first_login": "* (bool)",
  "device_fingerprint": "string (L1, hash)",
  "ip_country": "string (ISO 3166-1 alpha-2)",
  "is_suspicious": "bool (default false, 由 RiskService 标记)"
}
```
- **必填**：`user_id` / `login_method` / `is_first_login`
- **隐私**：`device_fingerprint` 走 L1 哈希；`is_suspicious=true` 时关联风控事件

### 1.3 `logout`
- **触发位置**：`AuthController::logout()` / 全局 session 失效中间件
- **字段 schema**：
```json
{
  "event_name": "logout",
  "user_id": "* (uuid)",
  "logout_reason": "* (enum: user_initiated | session_expired | password_reset | admin_force)",
  "session_duration_seconds": "int"
}
```

### 1.4 `survey_completed`
- **触发位置**：`app/Http/Controllers/Api/SurveyController.php::complete()`
- **触发时点**：用户提交问卷、UserPreference 落库后
- **字段 schema**：
```json
{
  "event_name": "survey_completed",
  "user_id": "* (uuid)",
  "survey_version": "* (string, e.g. v1-6q or v1-3q-ab)",
  "questions_answered": "* (int, 3-6)",
  "duration_seconds": "int",
  "skipped_count": "int (default 0)",
  "household_size": "int (1-10)",
  "cooking_skill": "enum: beginner | intermediate | advanced",
  "budget_hkd_per_week": "int (100-3000)",
  "dietary_habits": "array<enum: vegetarian | vegan | halal | gluten_free>",
  "allergies": "array<enum: peanut | shellfish | dairy | egg | soy>",
  "usage_purpose": "enum: daily_cook | family_meal | fitness | baby_food | party",
  "locale": "* (string)"
}
```
- **必填**：`user_id` / `survey_version` / `questions_answered` / `locale`
- **隐私**：L3 敏感；`dietary_habits` / `allergies` 不进明细表，仅聚合计数

### 1.5 `survey_skipped`
- **触发位置**：`SurveyController::skip()` 或前端"跳过问卷"按钮
- **字段 schema**：
```json
{
  "event_name": "survey_skipped",
  "user_id": "* (uuid)",
  "skip_stage": "* (enum: intro | mid_survey | final_review)",
  "questions_answered_before_skip": "int",
  "locale": "* (string)"
}
```

---

## 2. 菜单类事件 (Menu Events)

### 2.1 `menu_viewed`
- **触发位置**：`MenuController::show()` (web + api)
- **触发时点**：AI 菜单加载完成、首次绘制至首屏
- **字段 schema**：
```json
{
  "event_name": "menu_viewed",
  "user_id": "uuid (null for guest)",
  "menu_id": "* (uuid)",
  "menu_date": "* (date, YYYY-MM-DD, Asia/Hong_Kong)",
  "is_ai_generated": "* (bool)",
  "ai_model": "string (gemini-2.5-flash | gpt-4o-mini | rule-based-v1)",
  "render_position": "* (enum: top_hero | middle_banner | bottom_collapsed)",
  "recipes_count": "int",
  "total_carbon_kg": "float (kg CO2e)",
  "estimated_price_hkd": "float",
  "locale": "* (string)"
}
```
- **必填**：`menu_id` / `menu_date` / `is_ai_generated` / `render_position` / `locale`

### 2.2 `menu_regenerated`
- **触发位置**：`MenuController::regenerate()` 或前端"换一组"按钮
- **字段 schema**：
```json
{
  "event_name": "menu_regenerated",
  "user_id": "uuid (null for guest)",
  "menu_id": "* (uuid)",
  "regenerate_count_for_day": "* (int, 1-based)",
  "trigger_reason": "* (enum: user_clicked | not_helpful | low_rating)",
  "previous_recipes_hashes": "array<string> (sha256 of recipe ids)",
  "new_recipes_hashes": "array<string>",
  "ai_latency_ms": "int",
  "fallback_used": "bool (true if rule-based instead of AI)"
}
```

### 2.3 `menu_helpful_voted`
- **触发位置**：菜单卡片"有用 👍 / 不有用 👎"按钮
- **字段 schema**：
```json
{
  "event_name": "menu_helpful_voted",
  "user_id": "uuid (null for guest)",
  "menu_id": "* (uuid)",
  "recipe_id": "uuid (null if voted on whole menu)",
  "vote": "* (enum: helpful | not_helpful)",
  "feedback_text": "string (L1, max 500 chars, optional)"
}
```
- **用途**：A/B-001 主指标

### 2.4 `recipe_viewed` *(扩展事件)*
- **触发位置**：`RecipeController::show()`
- **字段 schema**：
```json
{
  "event_name": "recipe_viewed",
  "user_id": "uuid",
  "recipe_id": "* (uuid)",
  "menu_id": "uuid",
  "cooking_time_minutes": "int",
  "difficulty": "enum: easy | medium | hard",
  "source": "enum: menu | search | share_link"
}
```

### 2.5 `ai_menu_error` *(扩展事件)*
- **触发位置**：`AiMenuService::generate()` 异常分支
- **字段 schema**：
```json
{
  "event_name": "ai_menu_error",
  "user_id": "uuid",
  "error_code": "* (enum: timeout | rate_limit | validation | upstream_5xx | unknown)",
  "error_message": "string (L0, generic)",
  "ai_model": "string",
  "prompt_tokens": "int",
  "completion_tokens": "int",
  "fallback_used": "bool",
  "latency_ms": "int"
}
```

---

## 3. 购物车类事件 (Cart Events)

### 3.1 `cart_item_added`
- **触发位置**：`CartController::addItem()` (POST `/api/cart/items`)
- **触发时点**：CartItem 落库后
- **字段 schema**：
```json
{
  "event_name": "cart_item_added",
  "user_id": "* (uuid)",
  "cart_id": "* (uuid)",
  "product_id": "* (uuid)",
  "sku": "* (string)",
  "quantity": "* (int, 1-99)",
  "unit_price_hkd": "* (float)",
  "subtotal_hkd": "* (float, =quantity * unit_price_hkd)",
  "is_organic": "bool",
  "carbon_footprint_kg": "float",
  "source": "* (enum: product_detail | menu_recipe | catalog_list | quick_reorder | subscription_cart)",
  "locale": "* (string)"
}
```

### 3.2 `cart_item_removed`
- **触发位置**：`CartController::removeItem()`
- **字段 schema**：
```json
{
  "event_name": "cart_item_removed",
  "user_id": "* (uuid)",
  "cart_id": "* (uuid)",
  "product_id": "* (uuid)",
  "quantity_removed": "* (int)",
  "removal_reason": "enum: user_clicked | out_of_stock | checkout_cleanup | system_expired",
  "cart_value_after_hkd": "float"
}
```

### 3.3 `cart_item_updated`
- **触发位置**：`CartController::updateItem()` (数量加减)
- **字段 schema**：
```json
{
  "event_name": "cart_item_updated",
  "user_id": "* (uuid)",
  "cart_id": "* (uuid)",
  "product_id": "* (uuid)",
  "old_quantity": "* (int)",
  "new_quantity": "* (int)",
  "delta_quantity": "* (int, =new - old)",
  "cart_value_after_hkd": "float"
}
```

### 3.4 `cart_cleared`
- **触发位置**：`CartController::clear()` 或下单成功后清理
- **字段 schema**：
```json
{
  "event_name": "cart_cleared",
  "user_id": "* (uuid)",
  "cart_id": "* (uuid)",
  "items_count": "* (int)",
  "cart_value_hkd": "* (float)",
  "trigger": "* (enum: user_clicked | order_placed | session_expired | admin_force)"
}
```

### 3.5 `cart_viewed` *(扩展事件)*
- **触发位置**：`CartController::index()` (打开 /cart 页)
- **字段 schema**：
```json
{
  "event_name": "cart_viewed",
  "user_id": "* (uuid)",
  "cart_id": "* (uuid)",
  "items_count": "int",
  "cart_value_hkd": "float",
  "referrer": "enum: catalog | menu | direct | push_notification | email_link"
}
```

---

## 4. 订单类事件 (Order Events)

### 4.1 `order_created`
- **触发位置**：`OrderController::store()` (POST `/api/orders`)
- **触发时点**：Order 落库、`status='pending'`
- **字段 schema**：
```json
{
  "event_name": "order_created",
  "user_id": "* (uuid)",
  "order_id": "* (uuid)",
  "order_number": "* (string, e.g. GB-2026-000123)",
  "status": "* (enum: pending)",
  "items_count": "* (int)",
  "subtotal_hkd": "* (float)",
  "shipping_fee_hkd": "float (default 0)",
  "discount_hkd": "float (default 0)",
  "total_hkd": "* (float)",
  "coupon_code": "string (L1)",
  "is_subscription_order": "bool",
  "subscription_id": "uuid (nullable)",
  "delivery_address_id": "uuid",
  "delivery_date": "date (YYYY-MM-DD)",
  "delivery_window": "enum: 09_12 | 12_15 | 15_18 | 18_21",
  "estimated_carbon_saved_kg": "float",
  "locale": "* (string)"
}
```

### 4.2 `order_paid`
- **触发位置**：`OrderController::pay()` 调用后，由 `PaymentService` 触发
- **触发时点**：`payment_intent.succeeded` 异步确认，订单 `status='paid'`
- **字段 schema**：
```json
{
  "event_name": "order_paid",
  "user_id": "* (uuid)",
  "order_id": "* (uuid)",
  "payment_id": "* (uuid)",
  "payment_method": "* (enum: stripe_card | stripe_fps | stripe_apple_pay | payme | alipay_hk)",
  "amount_hkd": "* (float)",
  "paid_at": "* (timestamp)",
  "payment_latency_ms": "int (created_at → paid_at)",
  "coupon_code": "string",
  "is_first_paid_order": "* (bool)"
}
```

### 4.3 `order_shipped`
- **触发位置**：`OrderStatusService::markShipped()` 后台
- **字段 schema**：
```json
{
  "event_name": "order_shipped",
  "user_id": "* (uuid)",
  "order_id": "* (uuid)",
  "tracking_number": "string",
  "carrier": "enum: sf_express | jumpa | zeek | lalamove | pickup",
  "shipped_at": "* (timestamp)",
  "fulfillment_latency_hours": "float (paid_at → shipped_at)"
}
```

### 4.4 `order_delivered`
- **触发位置**：`OrderStatusService::markDelivered()` (webhook 或人工)
- **字段 schema**：
```json
{
  "event_name": "order_delivered",
  "user_id": "* (uuid)",
  "order_id": "* (uuid)",
  "delivered_at": "* (timestamp)",
  "delivery_latency_hours": "float (shipped_at → delivered_at)",
  "is_on_time": "bool (与 delivery_window 末尾比较)"
}
```

### 4.5 `order_cancelled`
- **触发位置**：`OrderController::cancel()` / 客服后台
- **字段 schema**：
```json
{
  "event_name": "order_cancelled",
  "user_id": "* (uuid)",
  "order_id": "* (uuid)",
  "cancelled_by": "* (enum: user | admin | system)",
  "cancel_stage": "* (enum: pre_paid | paid_pre_ship | in_transit | delivered_within_7d)",
  "reason_code": "enum: user_request | out_of_stock | address_wrong | payment_timeout | fraud_detected",
  "refund_required": "bool",
  "refund_amount_hkd": "float"
}
```

### 4.6 `order_refunded`
- **触发位置**：`RefundService::execute()` (Stripe refund + 状态机切到 `refunded`)
- **字段 schema**：
```json
{
  "event_name": "order_refunded",
  "user_id": "* (uuid)",
  "order_id": "* (uuid)",
  "refund_id": "* (uuid)",
  "refund_amount_hkd": "* (float)",
  "refund_reason": "* (enum: user_request | duplicate | fraud | partial_damage | not_received | goodwill)",
  "refund_method": "* (enum: original_method | store_credit)",
  "refunded_at": "* (timestamp)",
  "refund_latency_hours": "float (cancelled_at → refunded_at)"
}
```

### 4.7 `order_status_changed` *(扩展事件，状态机通用)*
- **触发位置**：`OrderStatusService::transition()` 通用入口
- **字段 schema**：
```json
{
  "event_name": "order_status_changed",
  "user_id": "* (uuid)",
  "order_id": "* (uuid)",
  "from_status": "* (enum: 7 states)",
  "to_status": "* (enum: 7 states)",
  "trigger_source": "* (enum: user | admin | webhook | scheduler | api)",
  "is_revert": "bool (回滚)",
  "notes": "string (L1, max 500 chars)"
}
```
- **状态枚举**（与 `order-state-machine.md` 7 态对齐）：`pending` / `paid` / `processing` / `shipped` / `delivered` / `cancelled` / `refunded`

---

## 5. 支付类事件 (Payment Events)

### 5.1 `payment_initiated`
- **触发位置**：`PaymentController::initiate()` 或 `OrderController::pay()`
- **字段 schema**：
```json
{
  "event_name": "payment_initiated",
  "user_id": "* (uuid)",
  "order_id": "* (uuid)",
  "payment_id": "* (uuid)",
  "payment_method": "* (enum: stripe_card | stripe_fps | stripe_apple_pay | payme | alipay_hk)",
  "amount_hkd": "* (float)",
  "client": "enum: web | mobile_app | admin_panel",
  "is_retry": "bool (default false, 重试计数 1-based)"
}
```

### 5.2 `payment_succeeded`
- **触发位置**：`PaymentService::handleStripeWebhook()` `payment_intent.succeeded`
- **字段 schema**：
```json
{
  "event_name": "payment_succeeded",
  "user_id": "* (uuid)",
  "order_id": "* (uuid)",
  "payment_id": "* (uuid)",
  "payment_intent_id": "* (string, e.g. pi_3Nxxx)",
  "amount_hkd": "* (float)",
  "currency": "string (default HKD)",
  "succeeded_at": "* (timestamp)",
  "stripe_fee_hkd": "float",
  "net_amount_hkd": "float"
}
```

### 5.3 `payment_failed`
- **触发位置**：`payment_intent.payment_failed` webhook
- **字段 schema**：
```json
{
  "event_name": "payment_failed",
  "user_id": "* (uuid)",
  "order_id": "* (uuid)",
  "payment_id": "* (uuid)",
  "payment_intent_id": "* (string)",
  "failure_code": "* (enum: card_declined | insufficient_funds | expired_card | 3ds_failed | fraud_suspected | network_error | other)",
  "failure_message": "string (L0, generic)",
  "amount_hkd": "float",
  "attempt_count": "int"
}
```

### 5.4 `stripe_webhook_received`
- **触发位置**：`POST /api/stripe/webhook` 入口
- **字段 schema**：
```json
{
  "event_name": "stripe_webhook_received",
  "user_id": "uuid (null if not yet mapped)",
  "stripe_event_id": "* (string, evt_xxx)",
  "stripe_event_type": "* (string, e.g. payment_intent.succeeded)",
  "stripe_api_version": "string",
  "livemode": "bool",
  "received_at": "* (timestamp)",
  "signature_valid": "* (bool)",
  "payload_size_bytes": "int"
}
```

### 5.5 `stripe_webhook_failed`
- **触发位置**：webhook handler 抛异常或签名校验失败
- **字段 schema**：
```json
{
  "event_name": "stripe_webhook_failed",
  "stripe_event_id": "* (string)",
  "stripe_event_type": "string",
  "error_code": "* (enum: signature_invalid | payload_malformed | idempotency_conflict | handler_exception | db_error | unknown)",
  "error_message": "string (L0)",
  "http_status": "int",
  "retry_count": "int"
}
```

### 5.6 `refund_initiated` / `refund_succeeded` *(扩展事件)*
- 与 §4.6 配对。`refund_initiated` 触发位置：`RefundService::create()`；`refund_succeeded` 触发位置：`charge.refunded` webhook。

---

## 6. 订阅类事件 (Subscription Events)

### 6.1 `subscription_created`
- **触发位置**：`SubscriptionController::store()` (POST `/api/subscriptions`)
- **字段 schema**：
```json
{
  "event_name": "subscription_created",
  "user_id": "* (uuid)",
  "subscription_id": "* (uuid)",
  "plan_id": "* (uuid)",
  "plan_code": "* (string, e.g. weekly_family | monthly_couple)",
  "price_hkd": "* (float, per billing period)",
  "billing_period": "* (enum: weekly | biweekly | monthly | quarterly)",
  "delivery_frequency": "* (enum: weekly | biweekly)",
  "start_date": "* (date)",
  "trial_days": "int (default 0)",
  "coupon_code": "string",
  "is_pdpo_consent_given": "* (bool)"
}
```

### 6.2 `subscription_cancelled`
- **触发位置**：`SubscriptionController::cancel()` 或 Stripe `customer.subscription.deleted`
- **字段 schema**：
```json
{
  "event_name": "subscription_cancelled",
  "user_id": "* (uuid)",
  "subscription_id": "* (uuid)",
  "cancelled_by": "* (enum: user | admin | payment_failed | system)",
  "cancellation_reason": "enum: too_expensive | not_using | bad_quality | switch_competitor | moving | other",
  "cancellation_feedback": "string (L1, max 1000 chars, optional)",
  "active_days": "int (subscription created_at → cancelled_at)",
  "deliveries_completed": "int",
  "refund_required": "bool",
  "cancel_at_period_end": "bool"
}
```

### 6.3 `subscription_renewed`
- **触发位置**：Stripe `invoice.payment_succeeded` (周期扣款) 或后台 scheduler
- **字段 schema**：
```json
{
  "event_name": "subscription_renewed",
  "user_id": "* (uuid)",
  "subscription_id": "* (uuid)",
  "invoice_id": "* (string)",
  "billing_period_start": "* (date)",
  "billing_period_end": "* (date)",
  "amount_hkd": "* (float)",
  "is_auto_renew": "* (bool)",
  "renewal_number": "* (int, 1-based)"
}
```

### 6.4 `subscription_paused` / `subscription_resumed` *(扩展事件)*
- 字段集参考 6.1 / 6.2，必填：`user_id` / `subscription_id` / `event_specific_action`。

### 6.5 `subscription_payment_failed` *(扩展事件)*
- Stripe `invoice.payment_failed` 触发，用于重试与告警。

---

## 7. 系统类事件 (System Events)

### 7.1 `api_error`
- **触发位置**：全局异常处理中间件 `app/Exceptions/Handler.php::render()`
- **字段 schema**：
```json
{
  "event_name": "api_error",
  "request_id": "* (uuid)",
  "http_status": "* (int, 4xx or 5xx)",
  "error_code": "* (string, e.g. ORDER_NOT_FOUND)",
  "error_message": "string (L0, generic, 长度 ≤ 200)",
  "endpoint": "* (string, /api/orders/{id})",
  "method": "* (enum: GET | POST | PUT | DELETE | PATCH)",
  "user_id": "uuid (null for unauthenticated)",
  "latency_ms": "int",
  "exception_class": "string (L0, 类名)",
  "is_handled": "bool (true = 业务已知错误)"
}
```

### 7.2 `validation_failed`
- **触发位置**：FormRequest `failedValidation()` 或 Validator 失败分支
- **字段 schema**：
```json
{
  "event_name": "validation_failed",
  "request_id": "* (uuid)",
  "endpoint": "* (string)",
  "user_id": "uuid",
  "field_errors": "* (object, {field: [error_codes]})",
  "locale": "* (string)"
}
```

### 7.3 `web_vital` *(扩展事件，前端性能)*
- 字段 schema：
```json
{
  "event_name": "web_vital",
  "page_url": "* (string)",
  "metric_name": "* (enum: LCP | FID | CLS | INP | TTFB | FCP)",
  "metric_value": "* (float)",
  "metric_rating": "enum: good | needs_improvement | poor",
  "device_type": "enum: mobile | tablet | desktop",
  "connection_type": "string (4g | 5g | wifi)"
}
```

### 7.4 `experiment_exposed` *(A/B 必含)*
- **触发位置**：前端加载 A/B 实验时
- **字段 schema**：
```json
{
  "event_name": "experiment_exposed",
  "user_id": "uuid (null for guest)",
  "anonymous_id": "* (uuid)",
  "experiment_id": "* (string, e.g. AB-001)",
  "experiment_version": "* (string, e.g. v1)",
  "variant": "* (string, e.g. control | treatment_a | treatment_b)",
  "exposed_at": "* (timestamp)"
}
```

### 7.5 `feature_flag_evaluated` *(扩展)*
- 与 `experiment_exposed` 类似，记录 SDK 决策。

---

## 8. 事件 → 业务实体映射总览

| 实体 | 关键事件 | 文档章节 |
| --- | --- | --- |
| User | register / login / logout | §1.1-1.3 |
| Survey | survey_completed / survey_skipped | §1.4-1.5 |
| DailyMenu | menu_viewed / menu_regenerated / menu_helpful_voted | §2.1-2.3 |
| Cart | cart_item_added / cart_item_removed / cart_item_updated / cart_cleared | §3.1-3.4 |
| Order | order_created / order_paid / order_shipped / order_delivered / order_cancelled / order_refunded | §4.1-4.6 |
| Payment | payment_initiated / payment_succeeded / payment_failed | §5.1-5.3 |
| Webhook | stripe_webhook_received / stripe_webhook_failed | §5.4-5.5 |
| Subscription | subscription_created / subscription_cancelled / subscription_renewed | §6.1-6.3 |
| System | api_error / validation_failed / experiment_exposed | §7.1-7.4 |

> 合计 **30 个事件**（含 8 个扩展事件），超出任务要求的 25+ 阈值。

---

## 9. SDK 落地建议

### 9.1 客户端 (Web)
- **首选**：自建轻量 SDK `analytics-js`（≤ 12KB gzip），封装 fetch 批上报
- **后端接收**：`POST /api/v1/events` 接受 JSON 数组，单次 ≤ 50 条，单条 ≤ 16KB
- **降级**：`localStorage` 暂存失败事件，48h 内重试
- **采样**：调试环境 100%，生产 100%，AI 调试可临时降采样

### 9.2 服务端 (Laravel)
- **中间件**：`TrackEvent` 中间件，自动注入公共字段
- **Job 异步**：`ProcessEventJob` 写入 ClickHouse `events_raw` 表
- **降级**：ClickHouse 不可用时回写 MySQL `events_fallback` 表 + 告警

### 9.3 GA4 映射示例
```javascript
gtag('event', 'menu_viewed', {
  menu_id: 'uuid',
  menu_date: '2026-06-13',
  is_ai_generated: true,
  render_position: 'top_hero'
});
// GA4 自动附加 user_id / session_id / app_version
```

### 9.4 Mixpanel 映射示例
```javascript
mixpanel.track('Menu Viewed', {
  distinct_id: userId,
  menu_id: 'uuid',
  menu_date: '2026-06-13',
  is_ai_generated: true,
  render_position: 'top_hero'
});
// Mixpanel People Profile 关联 user_id
```

---

## 10. 数据保留与归档

| 事件分组 | 在线保留 | 归档保留 | 脱敏 |
| --- | --- | --- | --- |
| 用户类 (§1) | 24 个月 | 7 年 | L2 字段 SHA-256 + salt |
| 菜单/购物车 (§2-3) | 13 个月 | 2 年 | 无需脱敏 |
| 订单/支付 (§4-5) | 24 个月 | 7 年（合规） | L2 用户级聚合 |
| 订阅 (§6) | 24 个月 | 7 年 | L2 |
| 系统 (§7) | 6 个月 | 1 年 | 移除 IP / UA |

> 与 `deployment.md` 数据保留策略 §4 对齐；超期事件 ETL 至 S3 IA，归档不删除。

---

## 11. 验收标准 (DoD)

- [ ] 30 个事件全部在 `analytics-js` SDK 中实现并通过单测
- [ ] 30 个事件 schema 在 GA4 DebugView 验证 1 次成功上报
- [ ] 30 个事件 schema 在 Mixpanel Project View 验证 1 次成功上报
- [ ] ClickHouse `events_raw` 表 DDL 与本字典 §0.3 类型映射一致
- [ ] L2/L3 字段出报表前已脱敏（QA 抽样 100 条）
- [ ] 数据保留策略已写入 deployment.md §4

---

## 12. 修订记录

| 版本 | 日期 | 修订人 | 说明 |
| --- | --- | --- | --- |
| v1.0 | 2026-06-12 | data-analyst-agent (hotel) | 初版，覆盖 Sprint 1-Day2 任务 P0-3 |

---

> **下一步**：与 dev-agent (golf) 对接 SDK 落地细节；与 architect-agent (bravo) 确认 §7 系统事件与异常中间件集成。
