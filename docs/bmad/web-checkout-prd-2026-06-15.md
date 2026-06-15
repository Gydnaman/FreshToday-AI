# Web 端结算链路 PRD — 2026-06-15

> **作者**：Charlie (PM)
> **状态**：Draft v1.0（待 Alpha 评审 → Golf/Delta 接收）
> **目标读者**：Alpha（项目集）、Bravo（架构）、Golf（前端开发）、Delta（QA）
> **范围**：Web 端 `/catalog → /cart → /checkout → /orders/{id}` 闭环
> **关联 SSOT**：
> - 订单状态机：`docs/bmad/order-state-machine.md` §3（7 态：`pending / paid / processing / shipped / delivered / cancelled / refunded`）
> - API 契约：`docs/bmad/api-contract.md` §2.2-2.4
> - 数据模型：`docs/bmad/er-diagram.md` §2.3（products）、§2.6（orders）、§2.11（cart_items）
> - 触发背景：`docs/bmad/DAY5-GAP-REPORT-2026-06-15.md` §3（测试红屏）+ 用户观察"流程是断的"

---

## §1 目标 / 成功指标

### 1.1 业务目标
打通访客浏览 → 加购 → 登录合并 → 结算 → 支付跳转的完整链路，让 web 端不再依赖 mock/假交互。Sprint 1 收尾时端到端可演示。

### 1.2 核心 KPI（验收门槛）

| # | 指标 | 基线（当前） | 目标 | 度量方式 | 统计窗口 |
|---|---|---|---|---|---|
| **KPI-1** | **端到端下单成功率**（从打开商品页到拿到 `order_no`） | 0%（流程断） | **≥ 95%** | Delta E2E：S1 + S2 + S4 串联自动化 | Sprint 1 收尾 7 天 |
| **KPI-2** | **登录态购物车同步成功率**（匿名 cart 合并到登录账户） | 0% | **100%** | Delta E2E：US-3 合并场景通过率 | Sprint 1 收尾 7 天 |
| **KPI-3** | **结账完成时长**（点击"去结算"到拿到支付跳转 URL 的中位耗时） | N/A（断） | **< 60s** | 前端 `performance.mark` + RUM 埋点 | Sprint 1 收尾 7 天 |

> **注**：KPI-1 的分母 = 启动 checkout 的人数；分子 = 成功拿到 `order_no` 的人数。失败原因（库存不足、token 过期、支付网关超时）需写入 `funnels.md` 漏斗。

### 1.3 非功能性指标
- **NFR-1** 购物车 PATCH 响应 P95 < 300ms（参考 api-contract §1 性能预算）
- **NFR-2** 商品列表页 LCP < 2.5s（移动端 4G）
- **NFR-3** 所有"加购"操作在弱网（断网 30s）下不丢数据：本地保留 + 上线后自动同步

---

## §2 用户故事（INVEST）

### US-1 访客浏览商品目录
- **Actor**：未登录访客（Anonymous）
- **触发**：访问首页或点击导航"商品"
- **价值**：能在不上号的情况下挑选商品
- **优先级**：P0
- **Story Points**：3
- **依赖**：`GET /api/products` + `GET /api/categories` 必须可访问，DB 至少 1 个分类 + 3 个商品

**AC（验收标准）**：
- **AC-1.1** Given 访客未登录且商品/分类表至少有 1 个分类 + 3 个上架商品
      When 访问 `/catalog`
      Then 看到分类侧边栏（≥1 个分类）与商品网格（≥3 个商品卡片）
      And 每张卡片显示：商品图、名、价（HKD）、是否有机标签
- **AC-1.2** Given 访客在 `/catalog`
      When 点击某个分类
      Then 列表过滤为该分类商品，且 URL `?category_id=N` 同步更新（可分享/刷新）
- **AC-1.3** Given 商品已渲染
      When 网络中断
      Then 页面降级为"网络异常"占位，**不**抛白屏
      And 恢复网络后自动重试拉取

---

### US-2 访客加购（匿名）
- **Actor**：未登录访客
- **触发**：在商品详情或卡片点击"加入购物车"
- **价值**：在登录前先攒好购物车
- **优先级**：P0
- **Story Points**：5
- **依赖**：US-1；localStorage schema 定义（见 §6）

**AC**：
- **AC-2.1** Given 访客未登录且 `/api/cart` 在未登录态会返回 401
      When 在商品页点击"加入购物车"
      Then 商品以 `{ product_id, name, price, qty, added_at }` 形式写入 `localStorage.cart_items`
      And 顶部购物车角标 +1（仅前端，不调后端）
- **AC-2.2** Given localStorage 已有 3 件商品
      When 再加购同一件商品（`product_id` 相同）
      Then 数量累加（qty+1），**不**生成重复条目
- **AC-2.3** Given 加购成功
      When 用户在 24h 内关闭并重开浏览器
      Then 购物车内容仍然存在
- **AC-2.4** Given 访客尝试加购 0 库存商品
      When 点击"加入购物车"
      Then 前端 disabled 按钮 + 提示"暂时缺货"
      And **不**写 localStorage

---

### US-3 访客登录（合并购物车）
- **Actor**：未登录访客（已加购 N 件）
- **触发**：点击"登录/注册"或结算时引导登录
- **价值**：登录后购物车不丢，能继续结算
- **优先级**：P0
- **Story Points**：8
- **依赖**：`POST /api/auth/login` 已存在；合并策略见 §4.1

**AC**：
- **AC-3.1** Given 访客 localStorage 有购物车、且未登录
      When 登录成功
      Then 客户端用 localStorage 中每条 `product_id` 顺序调 `POST /api/cart`
      And 合并策略：相同 `product_id` 数量相加（不超过库存）
      And 完成后清空 localStorage.cart_items
- **AC-3.2** Given 合并过程中某件商品已下架（404 PRODUCT_NOT_FOUND）
      When 循环调 `POST /api/cart`
      Then 该条被跳过并写入 `localStorage.merge_failures` 数组
      And 合并结束后弹 toast："N 件商品已合并，1 件已下架被跳过"
- **AC-3.3** Given 访客登录后 `/api/cart` 已有 2 件
      When 用同一账号在另一台设备登录并把 localStorage 的 3 件合并
      Then 账户购物车为 5 件（或合并去重后正确数）
- **AC-3.4** Given 用户点"登录"但密码错误
      When 表单提交
      Then 显示表单错误，**不**触发合并流程，localStorage 保留

---

### US-4 登录用户看购物车（实时）
- **Actor**：已登录用户
- **触发**：访问 `/cart`
- **价值**：看到合并后的真实购物车（来自后端）
- **优先级**：P0
- **Story Points**：3
- **依赖**：`GET /api/cart`

**AC**：
- **AC-4.1** Given 已登录用户 cart_items 表 ≥1 条
      When 访问 `/cart`
      Then 看到购物车列表：商品图、名、单价、数量、小计、删除按钮
      And 顶部显示"合计 HKD $XXX"和"共 N 件"
- **AC-4.2** Given 用户在 `/cart`
      When 另一标签页改了数量
      Then 当前页 30s 内自动刷新（轮询或 SSE，二选一；MVP 用 30s 轮询）
- **AC-4.3** Given `/api/cart` 返回空
      When 渲染 `/cart`
      Then 显示空状态插画 + "去逛逛商品 →" 按钮

---

### US-5 登录用户修改购物车
- **Actor**：已登录用户
- **触发**：在 `/cart` 改数量或点删除
- **价值**：调整下单内容
- **优先级**：P0
- **Story Points**：5
- **依赖**：`PATCH /api/cart/{item}`、`DELETE /api/cart/{item}`

**AC**：
- **AC-5.1** Given 购物车某商品 qty=2
      When 点"+"号变成 qty=3
      Then 调 `PATCH /api/cart/{item}` quantity=3
      And 小计与合计实时更新
- **AC-5.2** Given 任意商品
      When 把 qty 改到 0
      Then 该条目被删除（API 支持 quantity=0 等同 DELETE，见 api-contract §2.3）
      And 角标 -1
- **AC-5.3** Given 任意商品
      When 点击删除按钮（垃圾桶）
      Then 弹二次确认 → 调 `DELETE /api/cart/{item}` → 行消失
- **AC-5.4** Given 后端返回 `409 OUT_OF_STOCK`
      When PATCH 失败
      Then 行内回滚到原数量 + 红色提示"库存不足，最多 N 件"
      And 数量输入框 max 设为剩余库存

---

### US-6 登录用户结算（创建订单 + 支付跳转）
- **Actor**：已登录用户（购物车非空）
- **触发**：点击"去结算"
- **价值**：完成下单
- **优先级**：P0
- **Story Points**：8
- **依赖**：`POST /api/orders`、`POST /api/orders/{id}/pay`

**AC**：
- **AC-6.1** Given 购物车 ≥1 件、用户已登录
      When 点击"去结算"按钮
      Then 跳转 `/checkout`，展示订单摘要（商品 + 合计）+ 收货地址表单（默认从 user profile 带出）
      And 必填项：收件人、电话、地址（HKD 区域内）
- **AC-6.2** Given 表单填写完整
      When 点击"提交订单"
      Then 调 `POST /api/orders` 拿 `order_no`（**进入 pending 状态**，见 order-state-machine §3）
      And 立即自动调 `POST /api/orders/{id}/pay` provider=stripe
      And 跳转到返回的 `redirect_url`（mock URL 即可，见 §5）
- **AC-6.3** Given 提交时某商品超库存（`409 OUT_OF_STOCK`）
      When 提交
      Then 弹模态：哪几件超库存，提示用户回购物车调整
      And **不**创建订单
- **AC-6.4** Given 订单已创建
      When 30 分钟内未支付
      Then 后台 `CancelExpiredOrdersJob` 把订单置为 `cancelled`（order-state-machine §6）
      And 用户回访订单页看到"已取消"
- **AC-6.5** Given 整个 checkout 流程
      When 从点"去结算"到拿到 `redirect_url`
      Then **KPI-3：中位时长 < 60s**

---

### US-7 登录用户查订单详情
- **Actor**：已登录用户
- **触发**：下单成功跳转后 / 顶部"我的订单"列表点击
- **价值**：知道支付状态、物流状态
- **优先级**：P1
- **Story Points**：3
- **依赖**：`GET /api/orders/{id}`

**AC**：
- **AC-7.1** Given 订单存在且属于当前用户
      When 访问 `/orders/{id}`
      Then 看到订单号、下单时间、状态徽章（按 SSOT 7 态）、商品列表、合计、支付方式
- **AC-7.2** Given 订单状态为 `pending`
      When 渲染详情页
      Then 显示"去支付"按钮（再调一次 `POST /api/orders/{id}/pay`）
- **AC-7.3** Given 订单状态为 `cancelled` / `refunded`
      When 渲染详情页
      Then "去支付"按钮隐藏，显示"已取消/已退款"说明
- **AC-7.4** Given 订单不属于当前用户
      When 访问 `/orders/{id}`
      Then 返回 403 NOT_OWNER，前端跳 404 占位页

---

## §3 验收标准（Gherkin 汇编）

> 全部 AC 的 Gherkin 视图按 Story 列出。每条 AC 已在 §2 给出文字版，此处保留 mapping。

### US-1 Gherkin
```gherkin
Feature: 访客浏览商品目录
  Scenario: 首屏渲染分类与商品
    Given 访客未登录
    And products 表 ≥3 条、categories 表 ≥1 条
    When 访问 /catalog
    Then 页面 200 OK
    And 至少 1 个分类与 3 个商品卡片可见
    And 每卡片含 name/price(HKD)/is_organic 标签
```

### US-2 Gherkin
```gherkin
Feature: 访客加购（匿名）
  Scenario: 加购新商品
    Given 访客未登录、localStorage.cart_items 为空
    And 商品 P-1 stock=10
    When 在 P-1 详情页点击"加入购物车"
    Then localStorage.cart_items 出现 {product_id: 1, name: ..., price: ..., qty: 1, added_at: <ISO>}
    And 角标 +1
  Scenario: 同商品累加
    Given localStorage 已含 P-1 qty=1
    When 再加购 P-1
    Then 该条 qty=2（不新增条目）
  Scenario: 0 库存拦截
    Given 商品 P-2 stock=0
    When 在 P-2 卡片点"加入购物车"
    Then 按钮 disabled、提示"暂时缺货"
    And localStorage 无变化
```

### US-3 Gherkin
```gherkin
Feature: 登录合并购物车
  Scenario: 正常合并
    Given 访客 localStorage 有 3 件（P-1 qty2, P-2 qty1）
    And 后端 cart_items 为空
    When 用邮箱+密码登录成功
    Then 顺序调 2 次 POST /api/cart
    And 账户 cart_items 含 P-1 qty2 + P-2 qty1
    And localStorage.cart_items 被清空
  Scenario: 合并中遇下架
    Given 访客 localStorage 有 P-1、P-2、P-3
    And P-2 在后端已删除
    When 登录
    Then P-1、P-3 合并成功
    And P-2 写入 localStorage.merge_failures
    And toast: "2 件已合并，1 件已下架被跳过"
  Scenario: 登录失败不合并
    Given 访客 localStorage 有 1 件
    When 用错误密码登录
    Then 表单显示错误
    And localStorage 保持不变
```

### US-4 Gherkin
```gherkin
Feature: 登录用户看购物车
  Scenario: 正常渲染
    Given 用户已登录、cart_items 2 条
    When 访问 /cart
    Then 显示 2 行商品 + 合计 + 件数
  Scenario: 空购物车
    Given /api/cart 返回 items=[]
    When 渲染 /cart
    Then 显示空状态 + "去逛逛商品" CTA
```

### US-5 Gherkin
```gherkin
Feature: 修改购物车
  Scenario: 数量+1
    Given 购物车某行 qty=2
    When 点"+"号
    Then PATCH /api/cart/{item} quantity=3
    And 合计刷新
  Scenario: 数量=0 触发删除
    Given 某行 qty=2
    When 把 qty 改 0
    Then 该行消失（API 等同 DELETE）
  Scenario: 库存不足回滚
    Given 后端 PATCH 返回 409 OUT_OF_STOCK
    When 行内提示出现
    Then 数量回滚到原值
    And max=剩余库存
```

### US-6 Gherkin
```gherkin
Feature: 结算 + 支付跳转
  Scenario: 正常下单
    Given 购物车 2 件、用户已登录、地址表单完整
    When 点"提交订单"
    Then POST /api/orders 返回 201 含 order_no（status=pending）
    And 自动 POST /api/orders/{id}/pay provider=stripe
    And 跳转 redirect_url
  Scenario: 库存不足拦截
    Given 提交时 P-1 已被别人抢光
    When POST /api/orders 返回 409
    Then 弹模态"P-1 库存不足"
    And 不创建订单
  Scenario: 超时未支付
    Given 订单 status=pending
    When 超过 30 分钟未支付
    Then 后台任务把订单置为 cancelled
    And 用户访问 /orders/{id} 看到"已取消"
```

### US-7 Gherkin
```gherkin
Feature: 订单详情
  Scenario: pending 订单显示支付按钮
    Given 订单 O-1 status=pending 且属于当前用户
    When 访问 /orders/O-1
    Then 显示"去支付"按钮
  Scenario: 终态隐藏支付
    Given 订单 O-1 status=cancelled
    When 渲染详情页
    Then 不显示"去支付"按钮
    And 显示"已取消"说明
  Scenario: 非本人订单
    Given 订单 O-1 属于用户 U-2
    When U-1 访问 /orders/O-1
    Then 跳 404 占位
```

---

## §4 边界 / 异常

### 4.1 localStorage ↔ 后端同步失败
| 场景 | 策略 | 用户感知 |
|---|---|---|
| 登录合并时，合并中网络断 | 中断循环，**保留** localStorage；下次登录再合并 | toast："部分商品未合并，下次登录重试" |
| 合并时 `POST /api/cart` 5xx | 整批回退，localStorage 不清空 | toast："合并失败，请重试" |
| 合并时某件 `409 OUT_OF_STOCK` | 该件跳过，写 `merge_failures` | toast 明示哪件被跳过 |
| PATCH 失败 | 数量回滚到上次的乐观值；前端维持 UI 旧值 | 行内红字 |
| DELETE 失败 | 行回滚；前端重新拉 `/api/cart` 校正 | toast："删除失败" |

### 4.2 库存不足回滚策略
- **下单瞬间**（POST /api/orders）：`409 OUT_OF_STOCK` → 整单不创建，购物车保留，前端弹模态告诉用户哪几件超库存，让用户回购物车调整。
- **支付后**（webhook 异步）：若发现订单里某商品已无库存（理论上被预留锁住了，不应发生），触发 `refund_required` sentinel（见 order-state-machine §3 末注），自动退款 + 告警财务。
- **前端预校验**：进入 `/checkout` 时再 `GET /api/cart` 一次，确保摘要与下单时一致。

### 4.3 Token 过期时购物车是否保留
- 后端 `cart_items` 表持久化，**不**依赖 token 有效期；用户重新登录（即使 token 刷新）购物车仍在。
- 仅当用户**显式登出且选择"清空"**时才删。
- 访客态 localStorage 24h TTL（`added_at` 字段），超期下次进入站点时清掉。

### 4.4 网络中断时未完成的订单如何恢复
- **下单成功、支付跳转前断网**：订单已在后端 `status=pending`；用户重连后从"我的订单"看到 pending 状态 + "去支付"按钮（US-7.2）。
- **支付跳转中断网**：依赖 `ReconcilePaymentJob`（5min 延迟，见 order-state-machine §6）轮询支付网关，主动对齐状态。
- **前端草稿保护**：`/checkout` 表单值写 sessionStorage，断网/刷新不丢；恢复后用户可继续提交。

### 4.5 并发与幂等
- 同一商品连续点"加入购物车"导致 5 个并发请求 → 后端按 `cart_items(user_id, product_id)` 唯一约束（见 er-diagram §3）做 upsert，前端去抖 300ms。
- `POST /api/orders` 加 idempotency-key（请求头 `Idempotency-Key`，UUID v4）防止用户双击导致两单。SSOT：`api-contract.md` 待补；本 PRD 提为 P0 依赖（Bravo/Golf 协同）。

---

## §5 不做（Out of Scope）

| 项 | 原因 | 计划 |
|---|---|---|
| 优惠券 / 折扣码 | 增加 checkout 复杂度 | **Sprint 2** |
| 多地址管理 / 地址簿 | 当前 1 收件地址够用 | **Sprint 2** |
| 实际 Stripe/PayMe 真实支付 | 仍走 mock `redirect_url`（api-contract §2.4 注明） | Sprint 2 接入真沙箱 |
| 完整订单历史 / 列表分页 | MVP 只做单订单详情 + 跳转 | Sprint 2 |
| 库存实时倒计时 / SSE | MVP 用 30s 轮询（US-4.2） | Sprint 2 可升 SSE |
| 推荐位 / 相关商品 | 不在 checkout 链路 | Sprint 3 |
| 团购 / 拼单 | 与购物车模型正交 | 待评估 |
| 移动端 App | web-only | 待评估 |
| 国际化（zh-HK / en） | MVP 锁 zh-HK + HKD | Sprint 3 |
| 取消订单用户自助 | 仅"超时系统自动取消" | Sprint 2 |

---

## §6 字段映射：localStorage item ↔ 后端 Product

### 6.1 localStorage schema（前端）
```json
// key: "cart_items"
// value: [
//   {
//     "product_id": 1,             // 必填，与后端 Product.id 对齐
//     "name": "本地菠菜",            // 仅为展示用，**不参与结算**（以防商品改名/调价）
//     "price": 12.5,                // 同上，仅展示
//     "qty": 2,                     // 用户加购数量
//     "added_at": "2026-06-15T10:00:00+08:00"  // ISO 8601，TTL 判定用
//   }
// ]
```

### 6.2 后端 Product schema（DB + API 响应）
| 字段 | 类型 | 来源 | 用途 |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | products.id PK | 唯一标识，前端用 |
| `name` | VARCHAR | products.name | 展示 |
| `description` | TEXT | products.description | 详情页 |
| `price` | DECIMAL(10,2) | products.price | **结算价以这个为准** |
| `image` | VARCHAR | products.image | 缩略图 |
| `carbon_footprint` | DECIMAL(8,3) | products.carbon_footprint | 碳足迹标签 |
| `stock` | INT | products.stock | 库存判定 |
| `is_organic` | BOOLEAN | products.is_organic | 有机标签 |
| `origin` | VARCHAR | products.origin | 产地 |
| `category` | Object{id,name} | products.category_id FK | 分类 |

### 6.3 关键对齐规则（**重点**）
1. **localStorage 只能存"用户加过"的最简信息**：`product_id` 是唯一可信字段；`name/price` 仅用于离线展示（断网时前端不至于空白）。
2. **真正 checkout 必须用 `product_id` 从后端拉**：`POST /api/orders` 只传 `items: [{product_id, quantity}]`，**不传** name/price。后端用最新的 `products.price` 算 `total_price`（防前端篡改）。
3. **合并登录购物车时**：以 `product_id` 对账；同 `product_id` 数量相加（不超过后端 `stock`），冲突时跳过 + 提示。
4. **登出/换号**：清空 localStorage.cart_items；后端 cart_items 不动。
5. **TTL 清理**：localStorage 条目若 `added_at` 距今 > 24h，丢弃。

### 6.4 字段映射矩阵
| 场景 | 前端读 | 前端写 | 后端权威 |
|---|---|---|---|
| 商品列表 | — | — | `GET /api/products` 返回全字段 |
| 加购（匿名） | 无（无 token） | localStorage | — |
| 加购（登录） | localStorage 或空 | `POST /api/cart { product_id, quantity }` | `cart_items` |
| 看购物车 | — | — | `GET /api/cart` 返回 `{id, product, quantity, subtotal}` |
| 改数量 | — | — | `PATCH /api/cart/{item} { quantity }` |
| 结算 | `GET /api/cart` 取最新摘要 | `POST /api/orders` 仅传 `items[{product_id, quantity}]` + 地址 | 后端用 `products.price` 重算 `total_price` |
| 支付跳转 | `order_no` | `POST /api/orders/{id}/pay { provider, return_url }` | 返回 `redirect_url` |

---

## §7 风险与依赖

| 风险/依赖 | 影响 | Owner | 缓解 |
|---|---|---|---|
| Day 5 测试 52/54 红（缺 `Product::factory` + `ProductFactory`） | 阻塞 E2E 写用例 | Golf | Day 6 必修，详见 `DAY5-GAP-REPORT-2026-06-15.md` §3 |
| DB 当前 products/categories 0 条 | 阻塞 US-1 AC-1.1 | Golf | 写 seed（参考 e2e-scenarios.md S1 需要的 fixture） |
| api-contract §2.3 写"未登录用 Session 临时存储" | 与本 PRD "localStorage" 冲突 | Bravo + Charlie | **需在 api-contract 追加 ADR / 附录**：MVP 改 localStorage 简化前端（无 Session 依赖），后续可迁 Session |
| OpenAPI 8 处 schema 漂移（`OPENAPI-AUDIT-2026-06-15.md`） | 影响前端 schema 校验 | Bravo | Day 6 必修 D1（alipay_hk）+ 其他 |
| `POST /api/orders` 无 idempotency-key | 双击会下两单（§4.5） | Bravo + Golf | P0 加在 Sprint 1 内 |
| 支付仍走 mock URL | Sprint 1 验收看"是否拿到 redirect_url"即可 | Delta | US-6 AC-6.2/6.5 不强求真实扣款 |

---

## §8 排期建议（Sprint 1 收尾 + Sprint 2 起点）

| Day | 任务 | Owner | 工时 |
|---|---|---|---|
| Day 6 上午 | 修 `Product::factory` + `ProductFactory` + seed 6 个商品 | Golf | 0.5d |
| Day 6 下午 | api-contract 追加 localStorage 决策附录 + idempotency-key 设计 | Bravo | 0.5d |
| Day 6 | 修 OpenAPI 8 处漂移 | Bravo | 0.5d |
| Day 7 | 前端：US-1、US-2、US-4、US-5、US-6、US-7 落地（路由/页面/API 调用） | Golf | 2d |
| Day 8 | 前端：US-3 登录合并流 + 库存回滚 + idempotency-key 接入 | Golf | 1d |
| Day 9-10 | Delta 写 E2E：S1+S2+S3+S4（参考 e2e-scenarios.md）+ 性能/异常用例 | Delta | 1.5d |
| Day 10 末 | Sprint 1 收尾：跑 7 天窗口统计 KPI-1/2/3 | Delta + Charlie | — |

---

## §9 附录

### 附录 A：状态术语对照（避免与产品文案冲突）
| 后端 SSOT（api-contract / state machine） | 用户可见文案 | 备注 |
|---|---|---|
| `pending` | 待支付 | 创建订单后默认 |
| `paid` | 已支付 | webhook 成功后 |
| `processing` | 处理中 | 仓库接单 |
| `shipped` | 已发货 | 物流揽收 |
| `delivered` | 已签收 | 物流签收 |
| `cancelled` | 已取消 | 超时/用户取消 |
| `refunded` | 已退款 | 任意阶段 |

> 严禁在产品文案里写"配送中"（对应 `shipped`）、"已完成"（歧义，可能是 `delivered` 也可能是 `paid`）。本 PRD 与 order-state-machine 保持一致。

### 附录 B：相关文档索引
- `docs/bmad/prd-mvp.md` §4.3 订单生命周期
- `docs/bmad/order-state-machine.md` §3 七态 SSOT
- `docs/bmad/api-contract.md` §2.2-2.4
- `docs/bmad/er-diagram.md` §2.3 / §2.6 / §2.11
- `docs/bmad/e2e-scenarios.md` S1-S4 购物车与结算场景
- `docs/bmad/edge-cases.md` 库存/支付异常用例
- `docs/bmad/DAY5-GAP-REPORT-2026-06-15.md` §3 当前阻塞

---

*文档结束。如对 KPI 门槛、AC 边界或字段映射有异议，请用 `send_message` 召集 Alpha + Bravo 评审。*
