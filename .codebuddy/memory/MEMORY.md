# FreshToday-AI / GreenBite 项目长期记忆

## 项目基本信息
- **项目名**：GreenBite（仓库目录名 FreshToday-AI）
- **技术栈**：Laravel 12 / PHP 8.2 / SQLite(dev) / MySQL 8(prod) / Tailwind CSS 4 / Sanctum
- **业务**：香港本地有机农产品电商 + AI 每日菜单（Gemini/OpenAI/DeepSeek 三选一）
- **支付**：Stripe（已实现验签）+ PayMe（验签待 Sprint 2）+ Alipay HK（待 Sprint 2）

## 用户偏好
- 在 main 分支直接做，不用 worktree
- 用 Subagent-driven 方式执行（每 Task 派 subagent review）
- BMAD + Superpowers 方法论作为项目约束（已存入持久记忆）

## 测试基线（2026-07-04 五轮 Review 后）
- **79 passed / 322 assertions / 0 failed**（零回归）
- 新增：`tests/Unit/ProductImageUrlTest.php`（4 个测试）

## 测试基线（2026-07-09 i18n 补全后）
- **86 passed / 334 assertions / 0 failed**（零回归）
- i18n 覆盖：所有 Blade 视图 + admin 后台；JS 文案通过 `@json(i18n('key'))` 注入
- 三语言验证：默认 `zh` 显示中文，`?lang=en` 显示英文，`?lang=zhhk` 显示繁中（已修复 helper 映射 bug）

## 测试基线（2026-07-20 AI 菜单生产化加固后）
- **129 passed / 468 assertions / 0 failed**（零回归）
- 基线修正：实际起点 94 tests（非 86），缺 .env 导致 8 个 MissingAppKeyException
- 新增 35 个测试覆盖 7 个 Task

## AI 菜单生产化架构（2026-07-20 落地）
- **五道防线**：PromptBuilder 契约 → JSON Schema 强制 → MenuOutputValidator 校验 → 本地 fallback → FailoverProvider 灾备
- **新组件**（`app/Services/Ai/`）：MenuOutputValidator / PromptBuilder / MenuSchema / MenuRenderer / MetricsRecorder / CircuitBreaker / Providers/FailoverProvider
- **Provider 返回契约**：`array{0:string,1:int,2:?array}`（content, tokens, json_data）
- **数据层**：`daily_menus.menu_json` JSON 列（nullable）存结构化，`menu_content` 保留渲染文本
- **可观测**：`GET /api/health/ai` 返回 provider/configured/last_success_at/last_failure_at/failure_rate_1h
- **Failover 模式**：`AI_FAILOVER_ENABLED=true` 开启，按 `failover_order=['deepseek','openai','gemini']` 顺序尝试，CircuitBreaker 熔断（默认 5 次失败 / 600s 窗口）
- **已知语义边界**：FailoverProvider "content 非空即成功"，不感知业务校验层（跑题 JSON 会判成功直接返回，落本地 fallback）


## BMAD+Superpowers 五轮 Review 修复摘要

### R1 初始修复 — 图片 URL 统一
- `Product::image_url` 访问器 + `$appends`，所有视图改用 `image_url`

### R2 安全审查
- catalog.blade.php：`addslashes()` → `Js::from()` 防 XSS
- checkout.blade.php：`buildConfirm()` DOM-XSS 加 `escapeHtml()`
- `OrderService::releaseStock()` N+1 → 单次 `whereIn()` 查询
- `PaymentService`：移除死代码 `$orderService`
- `ProductController`：LIKE 通配符 `addcslashes` 转义

### R3 竞态条件
- `ProductController::index()` `/catalog` 加 `status=published` 过滤（之前泄露 draft）
- 所有 quantity 验证加 `max:999`（Cart ×2 + Order + Checkout）
- CheckoutController 加 `$qty > 999` 防御

### R4 性能/配置
- `Admin\ProductController`：product CRUD 后 `Cache::increment('products:cache_version')`
- API routes：login/register 加 `throttle:30,1`，auth 路由组加 `throttle:api`

### 已知未修复技术债务（R5 记录）
- 10 个控制器缺少独立 Feature Test
- `IsAdmin` / `SetLocale` 中间件未测试
- `daily_menus` 缺少 `[user_id, date]` 复合唯一索引
- `products.category_id` 缺少单独索引
- `cart_items` 级联删除（非质量问题，设计保留）

## 认证模式（2026-07-03 I-3 修复后）
- Sanctum SPA Cookie 模式（httpOnly session cookie）
- 不再使用 PAT (Personal Access Token) / localStorage 存 token
- bootstrap/app.php 启用 statefulApi()
- 前端 fetch 用 credentials: 'include'
- 登录流程：先 fetch /sanctum/csrf-cookie 再 fetch /api/login
- CheckoutController 直接调 OrderService（不再 BFF HTTP 自调用）

## 已知技术债务
- Minor-3：WebhookFlowTest sign() 与 postJson JSON 编码隐式耦合
- Minor-4：docker-compose version: '3.9' 已废弃
- Minor-5：PayMe webhook_secret 配置不一致（Controller 检查 api_key 非 webhook_secret）

## 图片 URL 统一方案（2026-07-04）
- `Product::image_url` 访问器（`getImageUrlAttribute`）：外部 URL 原样返回，本地路径拼接 `asset('storage/...')`
- `protected $appends = ['image_url']` — API 序列化始终包含该字段
- 所有 Blade 视图 + JS 前端统一使用 `image_url`（catalog / admin / cart / checkout）
