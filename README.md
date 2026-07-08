# GreenBite

> A green lifestyle e-commerce platform built with Laravel 12, Tailwind CSS, and MySQL/SQLite.

## Features

- Local organic agriculture products catalog
- AI-powered daily menu generation (Gemini)
- Shopping cart, multi-step checkout
- Stripe + PayMe payment integration（Alipay HK 计划 Sprint 2 接入；PayMe webhook 验签待 Sprint 2）
- Subscription plans
- Order tracking with 7-state machine
- Refund workflow
- i18n (zh-HK / en / zh-CN)

## Tech Stack

| Layer | Choice |
|---|---|
| Backend | Laravel 12 (PHP 8.2) |
| Database | SQLite (dev) / MySQL 8.0 (prod) |
| Frontend | Tailwind CSS 4 (Vite)（原生 JS，无 jQuery 依赖） |
| API Auth | Laravel Sanctum (SPA Cookie / httpOnly session) |
| Payment | Stripe + PayMe（Alipay HK 待 Sprint 2） |
| AI | Google Gemini 2.5 Flash（HTTP 直调，无 SDK；3-layer fallback per ADR-0006） |
| Cache | File / Database / Redis (configurable) |

## Quick Start (5 minutes, native PHP + SQLite on Windows 10+)

> 适合：本地开发、不想装 Docker。  
> 系统要求：Windows 10 22H2+ / PowerShell 5+ / 管理员权限（首次装包时）

### 1. Install prerequisites via `winget`

以 **PowerShell（管理员）** 运行：

```powershell
winget install --id=PHP.PHP.8.2 -e --accept-package-agreements --accept-source-agreements
winget install --id=SQLite.SQLite -e --accept-package-agreements --accept-source-agreements
```

> PHP 包已含 bcmath/openssl/intl/gd/zip/pdo_sqlite 等 Laravel 12 必需扩展。  
> Composer 2.x 由仓库自带（`C:\tools\composer` 脚本自动安装），无需单独装。

### 2. Clone & install

```bash
cd c:\Users\Lenovo\Desktop\FreshToday-AI
bash scripts/unix/dev.sh install     # composer install + npm install
```

### 3. Configure environment & database

```bash
bash scripts/unix/dev.sh setup       # cp .env + key:generate + migrate --seed
```

该命令会：
- 复制 `.env.example` → `.env`
- 生成 `APP_KEY`
- 创建 `database/database.sqlite`
- 跑所有 migrations（12 张表）
- 跑 seeders（1 个 admin test user: `test@example.com` / `password`）

### 4. Start the dev server

```bash
bash scripts/unix/dev.sh serve       # 后台启动 php artisan serve + npm run dev
```

打开 <http://127.0.0.1:8000>

- 健康检查：<http://127.0.0.1:8000/up>
- 默认登录：`test@example.com` / `password`

### 5. Stop the server

```bash
bash scripts/unix/dev.sh stop
```

---

## Dev script cheatsheet (`scripts/unix/dev.sh`)

| Command | 作用 |
|---|---|
| `bash scripts/unix/dev.sh doctor` | 体检（PHP 扩展 / Composer / SQLite / .env） |
| `bash scripts/unix/dev.sh install` | composer install + npm install |
| `bash scripts/unix/dev.sh setup` | 配 .env + key:generate + migrate --seed |
| `bash scripts/unix/dev.sh serve` | 后台启动 artisan serve + vite（PID 写到 storage/framework/dev-pids/） |
| `bash scripts/unix/dev.sh stop` | 停掉 serve + vite |
| `bash scripts/unix/dev.sh tinker` | 进 Laravel REPL |
| `bash scripts/unix/dev.sh test` | 跑 PHPUnit（当前 79 passed / 322 assertions / 0 failing） |
| `bash scripts/unix/dev.sh all` | install + setup + serve 一把梭 |

> `dev.sh` **不依赖** `php` 在 PATH 中（用 winget 安装的绝对路径调用），Git Bash 即可运行。
> Windows PowerShell 辅助脚本见 `scripts/windows/`。

---

## Architecture

### Directory map

```
├── app/                        Laravel 应用核心
│   ├── Enums/                    状态枚举（OrderStatus / GuardCode / Currency）
│   ├── Exceptions/               业务异常（GuardFailedException / InvalidTransitionException）
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/              10 个 REST 控制器（Auth/Cart/Menu/Order/...）
│   │   │   ├── Admin/            管理后台（产品 CRUD）
│   │   │   └── Web/              Web 页面控制器（Checkout）
│   │   │   ├── ProductController.php      公开产品目录页
│   │   │   └── SurveyController.php       问卷入口（302→SPA）
│   │   └── Middleware/           IsAdmin / SetLocale
│   ├── Jobs/                     4 个定时任务（自动发货/取消/订阅配送/生成菜单）
│   ├── Models/                   16 Eloquent 模型
│   ├── Providers/                AppServiceProvider / DomainServiceProvider
│   └── Services/                 5 个业务服务
│       ├── OrderService.php       7 态订单状态机（SSOT）
│       ├── PaymentService.php     Stripe / PayMe 支付
│       ├── AiMenuService.php      AI 菜单生成（3 层降级）
│       ├── Ai/                    AI Provider 抽象（Interface + 4 实现 + Factory）
│       ├── SubscriptionService.php
│       └── NotificationService.php
│
├── resources/views/             Blade 视图（按功能分目录）
│   ├── admin/                     管理后台视图
│   │   └── products/              产品 CRUD 表单
│   ├── auth/                      认证页面
│   │   └── login.blade.php        登录 / 注册
│   ├── shop/                      商城页面（需登录）
│   │   ├── cart.blade.php         购物车
│   │   ├── checkout.blade.php     结账
│   │   ├── orders.blade.php       订单历史
│   │   ├── catalog.blade.php      产品目录
│   │   ├── subscriptions.blade.php订阅方案
│   │   ├── survey.blade.php       饮食问卷
│   │   └── dashboard.blade.php    用户首页
│   ├── pages/                     公开页面
│   │   └── welcome.blade.php      Landing Page
│   ├── layouts/                   布局组件（app.blade.php / admin.blade.php）
│   └── partials/                  复用片段（nav / footer）
│
├── database/
│   ├── migrations/                23 张表迁移
│   ├── factories/                 7 个 Model Factory
│   └── seeders/                   DatabaseSeeder
│
├── docs/                         项目文档
│   ├── bmad/                      BMAD 方法论文档（ADR / 架构 / API / 复盘）
│   │   └── adr/                   3 份架构决策记录
│   ├── design/                    设计原型
│   │   ├── *.html                 9 个 HTML 原型
│   │   └── mock/                  模拟数据（JSON + CSS）
│   └── i18n/                      多语言资源
│
├── routes/
│   ├── api.php                    26 条 API 路由（Sanctum + throttle）
│   ├── web.php                    15 条 Web 路由（含 admin 子组）
│   └── console.php                定时任务调度
│
├── scripts/                      开发 / 部署脚本
│   ├── windows/                   Windows 平台（9 ps1 + 1 cmd）
│   ├── unix/                      macOS / Linux（3 sh，含 dev.sh）
│   └── db-counts.php              跨平台：数据库统计
│
├── tests/                        PHPUnit 测试
│   ├── Unit/                      单元测试（Product / AiMenu / OrderService）
│   ├── Feature/                   功能测试
│   │   ├── Order/                 订单 + 支付 webhook
│   │   └── Web/                   购物车 / 结账 / E2E
│   ├── TestCases/                 抽象基类（OrderServiceTestCase）
│   ├── Concerns/                  测试 Trait
│   └── TestCase.php               PHPUnit 基类
│
├── config/                       Laravel 配置（app/auth/cache/database/...）
├── public/                       Web 入口 + 编译后前端资源
├── storage/                      日志 / 缓存 / 编译视图 / 上传文件
├── bootstrap/                    Laravel 引导启动
│
├── _bmad/                        BMAD 框架（项目管理 / Agent 配置 / 任务文件）
├── superpowers/                  Superpowers 开发方法论
├── .github/                      GitHub CI/CD
├── .infra/                       基础设施配置（Prometheus + Grafana）
│   └── prometheus/
│
├── artisan                       Laravel CLI 入口
├── composer.json / package.json  依赖管理
├── docker-compose.yml / Dockerfile 容器化
├── vite.config.js                前端构建
└── README.md                     你正在看这个
```

### Key design decisions (ADRs)

| ADR | Decision |
|---|---|
| **ADR-0004** | Webhook 幂等性：DB 去重（`stripe_webhook_events.provider_event_id` 唯一索引）+ Stripe-Signature HMAC 校验 |
| **ADR-0005** | 订单状态机：Service 层 `canTransition`（拒绝 DB CHECK 因并发不可重入） |
| **ADR-0006** | AI 菜单缓存与降级：Cache 抽象 + 三层降级（Cache→DB→Provider→本地模板）+ 限流 3 次/天 |
| **AI Provider 切换** | `AiProviderInterface` 抽象（`app/Services/Ai/Contracts/`）+ `AiProviderFactory` 自动探测；Gemini/OpenAI/DeepSeek 三家任选其一切换 |

详细见 `docs/bmad/adr/`。

---

## API Authentication

Web 端使用 **Sanctum SPA Cookie 认证**（httpOnly session cookie），API 直调仍支持 token。

### Web 端（SPA Cookie 模式）

浏览器访问 `/login` → 自动走 CSRF cookie + session 认证流程，登录后前端 `gbFetch` 自动带 `credentials: 'include'`。

### API 直调（Token 模式）

```bash
# 1. 注册 / 登录
curl -X POST http://127.0.0.1:8000/api/register \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"name":"Alice","email":"alice@x.test","password":"password","password_confirmation":"password"}'

# 2. 带 token 请求
curl -H "Authorization: Bearer YOUR_TOKEN" http://127.0.0.1:8000/api/me

# 3. 登出
curl -X POST -H "Authorization: Bearer YOUR_TOKEN" http://127.0.0.1:8000/api/logout
```

完整 API 26 端点见 `docs/bmad/openapi.yaml`（OpenAPI 3.0.3 规范）。

---

## 26 API Endpoints (summary)

| Method | Path | Auth | Purpose |
|---|---|---|---|
| POST | `/api/register` | — | 注册 + 拿 token |
| POST | `/api/login` | — | 登录 + 拿 token |
| POST | `/api/logout` | sanctum | 撤销 token |
| GET | `/api/me` | sanctum | 当前用户信息 |
| GET | `/api/products` | — | 产品列表 |
| GET | `/api/products/{id}` | — | 产品详情 |
| GET | `/api/categories` | — | 分类列表 |
| GET | `/api/cart` | sanctum | 购物车 |
| POST | `/api/cart` | sanctum | 加车 |
| PATCH | `/api/cart/{item}` | sanctum | 改数量 |
| DELETE | `/api/cart/{item}` | sanctum | 删项 |
| GET | `/api/orders` | sanctum | 订单列表 |
| POST | `/api/orders` | sanctum | 下单 |
| GET | `/api/orders/{id}` | sanctum | 订单详情 |
| POST | `/api/orders/{id}/pay` | sanctum | 发起支付 |
| GET | `/api/survey` | sanctum | 问卷 |
| POST | `/api/survey` | sanctum | 提交问卷 |
| GET | `/api/menu/today` | sanctum | 今日菜单 |
| POST | `/api/menu/regenerate` | sanctum | 重生菜单（3 次/天） |
| GET | `/api/subscriptions` | sanctum | 订阅列表 |
| POST | `/api/subscriptions` | sanctum | 创建订阅 |
| DELETE | `/api/subscriptions/{id}` | sanctum | 取消订阅 |
| POST | `/api/stripe/webhook` | — | Stripe webhook（HMAC 验签） |
| POST | `/api/payme/webhook` | — | PayMe webhook（**验签未实现，返回 501**） |
| GET | `/api/test/orders/{id}` | sanctum (testing/staging) | 调试 |
| POST | `/api/test/tick` | sanctum (testing/staging) | 调试：推进时间 |

---

## Web Pages (Blade)

| Path | View | 用途 | 需登录 |
|---|---|
| `/` | `pages.welcome` | 首页（hero + CTA） | — |
| `/catalog` | `shop.catalog` | 产品目录 | — |
| `/login` | `auth.login` | 登录 / 注册（SPA Cookie） | — |
| `/cart` | `shop.cart` | 购物车 | — |
| `/checkout` | `shop.checkout` | 结账 | ✅ |
| `/dashboard` | `shop.dashboard` | 用户首页（含 AI 菜单） | — |
| `/orders` | `shop.orders` | 订单历史 | — |
| `/subscriptions` | `shop.subscriptions` | 订阅方案 | — |
| `/survey` | → 302 SPA | 饮食问卷 | ✅ |
| `/admin/products` | `admin.products.*` | 管理后台（CRUD） | ✅ admin |

---

## Database

**Dev（默认）**：SQLite（`database/database.sqlite`）  
**Prod**：MySQL 8.0.16+（必启用 CHECK 约束，详见 ADR-0005）

切换到 MySQL：编辑 `.env` 改 `DB_CONNECTION=mysql` + 填 `DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD`，然后 `php artisan migrate:fresh --seed`。

---

## Environment Variables (`.env`)

| Var | 必填 | 说明 |
|---|---|---|
| `APP_KEY` | ✅ | `php artisan key:generate` 自动生成 |
| `DB_CONNECTION` | ✅ | `sqlite` (dev) / `mysql` (prod) |
| `DB_DATABASE` | ✅* | sqlite 时填 `database/database.sqlite` 的相对路径 |
| `STRIPE_SECRET_KEY` | ❌ | 留空则支付走 mock 分支（不阻塞本地起站） |
| `STRIPE_WEBHOOK_SECRET` | ❌ | 留空则 webhook 验签失败返回 401 |
| `GEMINI_API_KEY` | ❌ | 留空则 AI 菜单走 DB 模板兜底（仍可看到菜单） |
| `OPENAI_API_KEY` | ❌ | 同上，与 Gemini 二选一；同时设则按 ai.auto_detect_order 探测 |
| `DEEPSEEK_API_KEY` | ❌ | 同上（OpenAI 兼容协议，HK 出口稳定） |
| `AI_PROVIDER` | ❌ | 显式指定 gemini / openai / deepseek；留空走自动探测 |

### AI Provider 切换（"插 KEY 即用"）

你**只需要**告诉我"公司 + KEY"两件事：
- **公司**：是 Google Gemini / OpenAI / DeepSeek / 其它（告诉我是哪家即可，我会按该家官方默认 base_url + 协议接好）
- **API KEY**：直接贴给我
- 可选：要不要换模型（默认各家都是性价比最优的那个）
- 可选：要不要换 base_url（自有代理/Azure OpenAI 等）

我会负责的事（你不用动）：
- 写好 Provider 实现类（`app/Services/Ai/Providers/{Company}Provider.php`）
- 在 `config/ai.php` 注册进 `providers` 与 `auto_detect_order`
- 在 `.env.example` 加好注释化模板
- 在 `AiProviderFactory::build()` 加好 `match` 分支
- 默认协议/base_url/超时：按该家公开文档设好
- 测试 + 文档

例：
- "**用 DeepSeek，key 是 `sk-abcd1234`**" → 我接好 DeepSeek V3（`api.deepseek.com/v1`，OpenAI 兼容协议）
- "**用 OpenAI，key 是 `sk-...`，模型换 `gpt-4o`**" → 我接好 gpt-4o（`api.openai.com/v1`）
- "**用 Azure OpenAI，endpoint 是 `xxx.openai.azure.com`，deployment 是 `my-gpt4`，key 是 `xxx`**" → 我接好 Azure 协议

**关闭 AI**：注释掉所有 `*_API_KEY` → 走 `NullProvider` → 永远用本地模板（不报错、不影响其他功能）。
| `PAYME_MERCHANT_ID` / `PAYME_API_KEY` | ❌ | 留空则 PayMe webhook 返回 501（fail-closed） |

---

## Testing

```bash
bash scripts/unix/dev.sh test
# 或直接：
php artisan test
```

**当前状态（2026-07-04）**：**79 passed / 322 assertions / 0 failed**。

---

## Deployment (production)

```bash
# Dockerfile 多阶段构建（已落地）
docker build -t greenbite/app:latest .
docker run -d -p 8080:80 --env-file .env greenbite/app:latest
```

详见 `Dockerfile` + `docker-compose.yml` + `docs/bmad/deployment.md`。

---

## Documentation

- **产品**：`docs/bmad/prd-mvp.md` + `docs/bmad/product-brief.md`
- **架构**：`docs/bmad/architecture.md` + `docs/bmad/adr/`
- **API**：`docs/bmad/api-contract.md` + `docs/bmad/openapi.yaml`
- **状态机**：`docs/bmad/order-state-machine.md` + ADR-0005
- **部署**：`docs/bmad/deployment.md` + `Dockerfile` + `docker-compose.yml`
- **监控**：`docs/bmad/monitoring-and-runbooks.md` + `.infra/prometheus/`
- **Sprint 1 Day 5 复盘**：`docs/bmad/DAY5-GAP-REPORT-2026-06-15.md`

---

## License

MIT
