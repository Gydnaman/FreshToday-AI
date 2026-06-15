# GreenBite

> A green lifestyle e-commerce platform built with Laravel 12, jQuery, Tailwind CSS, and MySQL/SQLite.

## Features

- Local organic agriculture products catalog
- AI-powered daily menu generation (Gemini)
- Shopping cart, multi-step checkout
- Stripe + PayMe + Alipay HK payment integration
- Subscription plans
- Order tracking with 7-state machine
- Refund workflow
- i18n (zh-HK / en / zh-CN)

## Tech Stack

| Layer | Choice |
|---|---|
| Backend | Laravel 12 (PHP 8.2) |
| Database | SQLite (dev) / MySQL 8.0 (prod) |
| Frontend | jQuery 3.7 + Tailwind CSS 3 (Vite) |
| API Auth | Laravel Sanctum (token) |
| Payment | Stripe + PayMe + Alipay HK |
| AI | Google Gemini 2.5 Flash (3-layer fallback per ADR-0006) |
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
cd d:/FreshToday-AI
bash scripts/dev.sh install     # composer install + npm install
```

### 3. Configure environment & database

```bash
bash scripts/dev.sh setup       # cp .env + key:generate + migrate --seed
```

该命令会：
- 复制 `.env.example` → `.env`
- 生成 `APP_KEY`
- 创建 `database/database.sqlite`
- 跑所有 migrations（12 张表）
- 跑 seeders（1 个 admin test user: `test@example.com` / `password`）

### 4. Start the dev server

```bash
bash scripts/dev.sh serve       # 后台启动 php artisan serve + npm run dev
```

打开 <http://127.0.0.1:8000>

- 健康检查：<http://127.0.0.1:8000/up>
- 默认登录：`test@example.com` / `password`

### 5. Stop the server

```bash
bash scripts/dev.sh stop
```

---

## Dev script cheatsheet (`scripts/dev.sh`)

| Command | 作用 |
|---|---|
| `bash scripts/dev.sh doctor` | 体检（PHP 扩展 / Composer / SQLite / .env） |
| `bash scripts/dev.sh install` | composer install + npm install |
| `bash scripts/dev.sh setup` | 配 .env + key:generate + migrate --seed |
| `bash scripts/dev.sh serve` | 后台启动 artisan serve + vite（PID 写到 storage/framework/dev-pids/） |
| `bash scripts/dev.sh stop` | 停掉 serve + vite |
| `bash scripts/dev.sh tinker` | 进 Laravel REPL |
| `bash scripts/dev.sh test` | 跑 PHPUnit（当前 39/54 通过，详见 `docs/bmad/DAY5-GAP-REPORT-2026-06-15.md`） |
| `bash scripts/dev.sh all` | install + setup + serve 一把梭 |

> `dev.sh` **不依赖** `php` 在 PATH 中（用 winget 安装的绝对路径调用），Git Bash 即可运行。

---

## Architecture

### Directory map

```
app/
  Enums/                 PHP 8.1 backed enums (OrderStatus, ...)
  Exceptions/            GuardFailedException, InvalidTransitionException
  Http/
    Controllers/Api/     10 REST controllers (Auth/Cart/Menu/Order/...)
    Middleware/          SetLocale
  Models/                17 Eloquent models
  Services/              5 business services
    OrderService.php           7-state machine (SSOT)
    PaymentService.php         Stripe/PayMe/Alipay HK
    AiMenuService.php          3-layer fallback (cache → DB → Gemini)
    SubscriptionService.php
    NotificationService.php
database/
  migrations/            17 migrations (含 Sprint 1 Day 5 extend)
  factories/             7 factories (User/Product/Order/Category/...)
  seeders/               DatabaseSeeder
docs/bmad/               Architecture docs / ADRs / Sprint reports
  adr/                   3 ADRs (webhook idempotency / state machine / AI cache)
  DAY5-GAP-REPORT-2026-06-15.md
  OPENAPI-AUDIT-2026-06-15.md
  SSOT-IMPACT-ASSESSMENT-2026-06-15.md
public/
  js/i18n-loader.js      Client-side i18n (zh-HK / en / zh-CN)
routes/
  api.php                26 endpoints (sanctum auth)
  web.php                8 web pages (welcome/login/catalog/...)
scripts/                 dev.sh + Windows setup scripts
tests/                   54 tests (39 passing, 15 known-failing — see Day 5 report)
```

### Key design decisions (ADRs)

| ADR | Decision |
|---|---|
| **ADR-0004** | Webhook 幂等性：DB 去重（`stripe_webhook_events.provider_event_id` 唯一索引）+ Stripe-Signature HMAC 校验 |
| **ADR-0005** | 订单状态机：Service 层 `canTransition`（拒绝 DB CHECK 因并发不可重入） |
| **ADR-0006** | AI 菜单缓存与降级：Cache 抽象 + 三层降级（Cache→DB→Gemini→503）+ 限流 3 次/天 |

详细见 `docs/bmad/adr/`。

---

## API Authentication

所有 `/api/*` 端点使用 **Laravel Sanctum Personal Access Token**（不是 session cookie）。

### 1. Register
```bash
curl -X POST http://127.0.0.1:8000/api/register \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"name":"Alice","email":"alice@x.test","password":"password","password_confirmation":"password"}'
```
Response: `{"user": {...}, "token": "1|abc..."}`

### 2. Login
```bash
curl -X POST http://127.0.0.1:8000/api/login \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"email":"alice@x.test","password":"password"}'
```

### 3. Authenticated request
```bash
curl -H "Authorization: Bearer 1|abc..." -H "Accept: application/json" \
  http://127.0.0.1:8000/api/me
```

### 4. Logout
```bash
curl -X POST -H "Authorization: Bearer 1|abc..." -H "Accept: application/json" \
  http://127.0.0.1:8000/api/logout
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
| POST | `/api/payme/webhook` | — | PayMe webhook（签名验签） |
| GET | `/api/test/orders/{id}` | sanctum (testing/staging) | 调试 |
| POST | `/api/test/tick` | sanctum (testing/staging) | 调试：推进时间 |

---

## Web Pages (Blade)

| Path | 用途 |
|---|---|
| `/` | 首页（hero + 特性 + CTA） |
| `/catalog` | 产品目录 |
| `/subscriptions` | 订阅方案 |
| `/survey` | 用户问卷（影响 AI 菜单） |
| `/login` | 登录页（用 SPA 模式鉴权） |

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
| `PAYPEMERCHANT_ID` / `PAYPEMAPI_KEY` | ❌ | 留空则 PayMe 走 mock |

---

## Testing

```bash
bash scripts/dev.sh test
```

**当前状态（2026-06-15）**：39/54 通过（72%），剩 15 fail 详见 `docs/bmad/DAY5-GAP-REPORT-2026-06-15.md` §5。

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
- **监控**：`docs/bmad/monitoring-and-runbooks.md` + `ops/prometheus/`
- **Sprint 1 Day 5 复盘**：`docs/bmad/DAY5-GAP-REPORT-2026-06-15.md`

---

## License

MIT
