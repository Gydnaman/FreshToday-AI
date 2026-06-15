# GreenBite Web 结算流程 — 代码质量检查清单 (Sprint 1 实施前)

> **文档编号**：GB-CHK-WEB-2026-06-15
> **评审人**：reviewer-agent (Foxtrot)
> **生成日期**：2026-06-15 (Asia/Hong_Kong)
> **适用对象**：dev-agent (Golf) — Sprint 1 web 端"商品→加购→购物车→结算"实施
> **文档性质**：**只读 SSOT 检查项**；不修改任何代码；不触发 commit
> **关联 SSOT**：
> - `docs/bmad/architecture.md`（前后端鉴权方案）
> - `docs/bmad/er-diagram.md`（products / users 字段定义）
> - `docs/bmad/api-contract.md`（/api/cart /api/checkout 契约）
> - `docs/bmad/order-state-machine.md`（订单状态机 + 附录 A 状态名 SSOT）
> - `docs/bmad/REVIEW-REPORT.md` v1.2（历史评审基线）

---

## 〇、背景与已知风险（来自 Alpha 简报）

| # | 风险点 | 严重度 | 影响文件 |
|---|---|---|---|
| R-01 | `catalog.blade` 字段名 `carbonFootprint` 错误，应为 `carbon_footprint` | **P0** | resources/views/catalog.blade.php |
| R-02 | `/cart` 路由错误指向 `welcome` 视图 | **P0** | routes/web.php |
| R-03 | `auth.blade` 是 `alert()` 假交互，未调后端 | **P0** | resources/views/auth.blade.php |
| R-04 | `checkout.blade` 是 `localStorage` 假结算 | **P0** | resources/views/checkout.blade.php |
| R-05 | 数据库 `products` / `categories` 表 0 条记录 | **P1** | database/seeders/DatabaseSeeder.php |
| R-06 | 前后端 token 鉴权方案未对齐（Sanctum SPA vs API token） | **P1** | 全局 ajax 层 + layouts/app.blade.php |

---

## §A 数据层（database/seeders/DatabaseSeeder.php）

幂等性 & 覆盖度检查（防止 CI 反复跑种子污染）

- [ ] **A-01** Seeder 全部使用 `updateOrCreate()` 或 `firstOrCreate()`，**严禁**直接 `Model::create()` 造成 UNIQUE 冲突
- [ ] **A-02** 至少 seed 1 个 `demo` 用户（`email = demo@greenbite.hk`，`password = password`）+ 1 个 `admin` 用户（`email = admin@greenbite.hk`）
- [ ] **A-03** 至少 **6 个分类**（叶菜 / 根茎 / 水果 / 菌菇 / 谷物 / 调味——与 `categories` 表设计一致）
- [ ] **A-04** 至少 **20 个商品**，覆盖全部分类
- [ ] **A-05** 商品字段全部填齐：`name` / `slug` / `description` / `price` / `currency='HKD'` / `stock` / `category_id` / `origin`（产地，字符串）/ `is_organic`（0/1）
- [ ] **A-06** 至少 **1 个商品 `stock=0`**（缺货场景用，验证 catalog 禁用按钮 + 角标逻辑）
- [ ] **A-07** 至少 **1 个商品 `is_organic=0`**（非有机场景用，验证徽章显示逻辑）
- [ ] **A-08** 至少 **1 个商品 `origin='香港本地'`**（港化指标）+ 至少 **1 个 `origin='日本'`**（进口场景）
- [ ] **A-09** 商品 `price` 全部为 HKD 整数（如 38 / 45 / 88），无小数无其他币种
- [ ] **A-10** Seeder 末尾输出 `php artisan tinker` 友好的统计：`echo "Seeded N categories, M products, K users"`

---

## §B 路由层（routes/web.php + 可选 CheckoutController）

- [ ] **B-01** `GET /cart` → 返回 `cart.blade` 视图（**不再**指向 `welcome`）
- [ ] **B-02** `GET /checkout` → 返回 `checkout.blade` 视图（**不存在则新建** CheckoutController@show）
- [ ] **B-03** `POST /checkout` → CheckoutController@place（提交订单，**走 auth:sanctum 中间件**）
- [ ] **B-04** 已有 `/login` 路由：若用户已登录则 302 重定向到 `/dashboard`（不显示登录页）
- [ ] **B-05** 已有 `/register` 路由：同上，已登录跳 `/dashboard`
- [ ] **B-06** 新增 `/logout` POST 路由（**不依赖 Sanctum SPA 的 CSRF 自动机制**，因为是 web form）：清 token + redirect `/`
- [ ] **B-07** 路由命名空间统一：`/` `/catalog` `/cart` `/checkout` `/login` `/register` `/logout` `/dashboard` 八个 web 路由
- [ ] **B-08** 所有 web 路由**严禁**裸暴露 `admin/*` 入口（见 §I-04）

---

## §C 视图层 — catalog（resources/views/catalog.blade.php）

- [ ] **C-01** 字段名使用 `carbon_footprint`（snake_case），**严禁** `carbonFootPrint` / `carbonFootprint` 等驼峰
- [ ] **C-02** 加购按钮 `onclick` 调用**新函数** `addToCart(productId)`（不再是占位 alert）
- [ ] **C-03** 缺货商品（`stock === 0`）显示"已售罄"标签 + `disabled` 按钮 + 灰度样式
- [ ] **C-04** 有机商品（`is_organic === 1`）显示 🌱 / "Organic" 徽章
- [ ] **C-05** 非有机商品（`is_organic === 0`）**不**显示徽章（避免误导）
- [ ] **C-06** 价格统一前缀 `HK$`（与 api-contract.md 货币规范一致）
- [ ] **C-07** 列表数据从后端 **Controller 注入**（`@foreach($products as $p)`），**不再** hardcode 假数据数组
- [ ] **C-08** 商品卡片含 `<a href="/product/{slug}">` 链接（v1.1 详情页可 404 占位，但 href 必须存在）

---

## §D 视图层 — cart（resources/views/cart.blade.php）

数据源双轨制（登录 / 未登录两条路径必须并存且互斥）

- [ ] **D-01** 页面加载时分支判断：`localStorage.getItem('gb_token')` 存在 → `fetch /api/cart`；否则 → 读 `localStorage.gb_cart`
- [ ] **D-02** 数量 `+` / `-` 按钮：登录态 → `PUT /api/cart/{itemId}` 带 `quantity`；未登录 → 更新 `localStorage.gb_cart`
- [ ] **D-03** 删除按钮：登录态 → `DELETE /api/cart/{itemId}`；未登录 → 过滤 `localStorage.gb_cart`
- [ ] **D-04** "去结算" 按钮 → 跳 `/checkout`，**带 cart 数据**（登录态从 API 拉，未登录从 localStorage 读后通过 `?` query 或 `sessionStorage` 传）
- [ ] **D-05** 登录态切换时（如用户在 cart 页登录）：**触发合并购物车**逻辑（localStorage → 后端 cart），合并成功后清 localStorage
- [ ] **D-06** 总价计算放在前端（**仅展示用**），**不**作为下单依据——下单时由后端重算（防篡改）
- [ ] **D-07** 货币前缀 `HK$` 全页统一

---

## §E 视图层 — auth（resources/views/auth.blade.php）

把假交互换成真后端调用

- [ ] **E-01** 登录表单 submit → `POST /api/login`（不再是 `alert('登录')`）
- [ ] **E-02** 注册表单 submit → `POST /api/register`，**含** `password_confirmation` 字段（与 api-contract.md 对齐）
- [ ] **E-03** 登录/注册成功后：`localStorage.setItem('gb_token', data.token)` + `localStorage.setItem('gb_user', JSON.stringify(data.user))`
- [ ] **E-04** 失败时显示后端 `error.message`（如 "邮箱或密码错误"），**不**用通用 "登录失败"
- [ ] **E-05** 登录 / 注册 tab 切换时**保留**已填字段（仅 `display: none` 切换 div，**不**销毁 DOM）
- [ ] **E-06** 注册成功后自动登录（直接调 `/api/login`，**不**让用户再输一次密码）
- [ ] **E-07** 表单前端校验：email 格式、password ≥8 位、password === confirmation（仅客户端预校验，**不**替代后端）

---

## §F 视图层 — 通用（resources/views/layouts/app.blade.php）

导航 / 鉴权 UI / cart 角标

- [ ] **F-01** 导航右上角：未登录 → "Sign In" 链 `/login`；已登录 → 显示 `user.name` + "Logout" 按钮
- [ ] **F-02** 渲染时检查 `localStorage.getItem('gb_token')`：存在 → 已登录态；不存在 → 未登录态
- [ ] **F-03** "Logout" 点击：`localStorage.removeItem('gb_token')` + `removeItem('gb_user')` + `location.href = '/'`
- [ ] **F-04** Cart 角标：优先 `GET /api/cart` 返回的 `items.length`；失败 / 未登录 fallback 到 `localStorage.gb_cart.length`
- [ ] **F-05** 角标为 0 时**隐藏**红点（避免视觉噪音）
- [ ] **F-06** 角标数字 > 99 显示 `99+`
- [ ] **F-07** 全局 `meta[name="csrf-token"]` 在 layout 头部（web form 走 CSRF 必需）
- [ ] **F-08** 全局引入 `app.js`（封装 §G 的 ajax 拦截器）

---

## §G 鉴权 + 安全（resources/js/app.js + 全局 ajax）

- [ ] **G-01** 全局 `fetch` / `axios` 拦截器自动注入 `Authorization: Bearer {localStorage.gb_token}`
- [ ] **G-02** 拦截 401 响应：清 `gb_token` + `gb_user` + `alert('登录已过期')` + `location.href = '/login'`
- [ ] **G-03** **区分 CSRF**：web form (`<form method="POST">`) 走 `csrf_token()`；AJAX 调 `/api/*` **不需要** CSRF（Sanctum token 模式）
- [ ] **G-04** **不**在 `console.log` 打印 token / `Authorization` header
- [ ] **G-05** **不**在网络面板可见的位置（如 HTML 属性、URL query）传 token
- [ ] **G-06** 密码字段 `type="password"`（默认） + autocomplete=`"current-password"` / `"new-password"`
- [ ] **G-07** 注册成功后**不**在 localStorage 明文存密码（实际只存 token + user，不存 password，但代码 review 需确认）
- [ ] **G-08** 所有用户输入字段**不**直接 `innerHTML` 注入（用 `textContent` / 转义防 XSS）

---

## §H 测试 + 验证（Alpha 跑通后，dev-agent 自验 + reviewer 复验）

### H.1 端到端 6 流程
- [ ] **H-01** 流程 1：未登录访问 `/catalog` → 看到 20+ 商品列表
- [ ] **H-02** 流程 2：点加购 → 角标 +1（未登录态走 localStorage）
- [ ] **H-03** 流程 3：点 "Sign In" → 登录 demo 账号 → token 写入 localStorage
- [ ] **H-04** 流程 4：登录后 cart 页 → 触发合并购物车（localStorage → API） → 角标数字与后端一致
- [ ] **H-05** 流程 5：在 cart 页改数量 → `PUT /api/cart/{id}` 成功 → 总价刷新
- [ ] **H-06** 流程 6：点 "去结算" → `/checkout` 填地址 + 支付 → `POST /checkout` → 跳 `/orders/{id}` 成功页

### H.2 自动化
- [ ] **H-07** `php artisan test` 通过率**不下降**（基线 v1.2: 假设 N 个用例全绿，本轮必须 ≥ N）
- [ ] **H-08** 浏览器 Console **无 JS 报错**（F12 → Console 0 errors / 0 unhandled promise rejection）
- [ ] **H-09** 浏览器 Network 面板：所有 `/api/*` 响应 2xx，无 5xx 漏出
- [ ] **H-10** 新增 1 个 Feature test：`WebCheckoutFlowTest` 覆盖 §H-01 ~ H-06 至少 1 个 happy path

---

## §I 不变量（**必须严格保留**，违反任一项即 P0 阻断）

- [ ] **I-01** **订单状态机 SSOT**：web checkout 提交订单**必须**调 `OrderService::place()`，**严禁** 直接 `Order::create(['status' => ...])` 绕过守卫
- [ ] **I-02** **货币 HKD 统一**：全站 `price` 字段后端 → 前端 → DB → log 全部 HKD，**不**出现 CNY / USD / TWD
- [ ] **I-03** **Sanctum token 模式**：web 端用 `localStorage.gb_token` + `Authorization: Bearer`，**不**与 Laravel session 混用
- [ ] **I-04** **不暴露 admin 入口**：web 路由表**严禁**出现 `Route::get('/admin', ...)` 等面向用户的 admin 入口（admin 后台走 `/api/admin/*` 独立鉴权）
- [ ] **I-05** **不破坏既有 7 态 9 转移**：web checkout 触发的状态变化是 `PENDING_PAYMENT` → `PAID`（Webhook）或 `PENDING_PAYMENT` → `CANCELLED`，**不**引入新状态
- [ ] **I-06** **不绕过审计日志**：每次订单状态变更必须写 `order_status_logs`（由 OrderService 保证；web 端**不**直接写）
- [ ] **I-07** **不破坏 ER 图字段命名**：web 端使用的字段 `carbon_footprint` / `is_organic` / `origin` 必须与 `er-diagram.md` 一致

---

## §J 复评签到栏（reviewer-agent 留档）

| 轮次 | 日期 | 范围 | 综合分 | 判定 | 签字 |
|---|---|---|---|---|---|
| v1.0 | 2026-06-15 | 清单发布（实施前 SSOT） | — | 清单**冻结**，待 Golf 实施 | Foxtrot ✅ |
| v1.1 | _TBD_ | 实施后代码评审（routes + views + seeder + CheckoutController） | _/10_ | _Pass / Conditional / Fail_ | _Foxtrot_ |
| v1.2 | _TBD_ | 复评（如 v1.1 出 P0/P1） | _/10_ | _Pass / Conditional / Fail_ | _Foxtrot_ |

---

## §K 评分规则（v1.1 实施后评审用）

| 维度 | 权重 | 评分要点 |
|---|---|---|
| 数据层完整性 | 15% | §A 全勾 = 9-10 分；缺 A-06/A-07 扣 1 分；用 `create()` 扣 2 分 |
| 路由正确性 | 10% | §B 全勾 = 9-10 分；`/cart` 还指 welcome 直接 0 分 |
| catalog 视图 | 10% | 字段名错 = 0 分起步；其余按 §C 扣分 |
| cart 视图双轨 | 15% | 缺登录/未登录任一路径 = 0 分起步 |
| auth 视图真交互 | 10% | 还有 `alert` 假实现 = 0 分起步 |
| layout 通用 | 10% | 角标 / 鉴权 UI 任一缺失 = -3 分 |
| 鉴权安全 | 15% | §G 任何一条违反 = 0 分起步（P0 阻断） |
| 不变量遵守 | 10% | §I 任一违反 = 直接 Fail |
| 测试覆盖 | 5% | §H 测试任一缺失 = -1 分 |
| **合计阈值** | — | **≥ 9.0 = Pass** / **8.0-8.9 = Conditional** / **< 8.0 = Fail** |

---

## §L 与 SSOT 交叉引用

| 清单章节 | 对应 SSOT | 不一致时谁优先 |
|---|---|---|
| §A 商品字段 | `er-diagram.md` §products 表 | er-diagram.md |
| §B 路由 | `architecture.md` §前后端鉴权 | architecture.md |
| §C 货币 | `architecture.md` §货币规范 | architecture.md |
| §D /api/cart 路径 | `api-contract.md` §Cart | api-contract.md |
| §E /api/login /api/register 路径 | `api-contract.md` §Auth | api-contract.md |
| §I 状态名 | `order-state-machine.md` 附录 A | order-state-machine.md（**最高优先级**） |
| §I 审计日志 | `order-state-machine.md` §审计 | order-state-machine.md |
| §I 守卫 | `order-state-machine.md` §GUARD-* | order-state-machine.md |

---

*清单结束。共 **82 项**检查（§A: 10 / §B: 8 / §C: 8 / §D: 7 / §E: 7 / §F: 8 / §G: 8 / §H: 10 / §I: 7 / §J: 3 / §K: 1 / §L: 8 / 含 §〇 风险 R-01~R-06 6 项）。*

*— reviewer-agent (Foxtrot) · 2026-06-15 · 质量门禁*
