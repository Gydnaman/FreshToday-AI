# GreenBite MVP 产品需求文档 (PRD)

> **创建人**：pm-agent (GreenBite 产品经理)  
> **版本**：v1.1（P0 修订版，对应 REVIEW-REPORT.md FIX-05 / FIX-11）  
> **日期**：2026-06-12（v1.0）/ 2026-06-12（v1.1 P0 修订）  
> **框架**：fdd-bmad-custom · Epic / Story / AC / DoD 规范  
> **范围**：MVP (Sprint 1-4, Week 1-12)

---

## 0. 文档说明

本 PRD 基于 [`product-brief.md`](./product-brief.md) 展开，将产品拆分为 **8 个 Epic**，每个 Epic 包含：业务价值、用户故事概要、优先级、关键 AC、依赖关系。Story 级别的拆分与详细 AC 留待 Sprint Planning 时由 dev-agent 在 BMAD 工作流中进一步产出。

**优先级定义**：
- **P0**：MVP 必交付，缺失则产品无法上线
- **P1**：增强竞争力，建议 Sprint 3 前交付
- **P2**：差异化能力，Sprint 4 或后续版本

---

## Epic 总览表

| # | Epic 名称 | 优先级 | 主要交付 Sprint | 关键依赖 |
| --- | --- | --- | --- | --- |
| E1 | 账户认证 | P0 | Sprint 1 | 无 |
| E2 | 商品目录 | P0 | Sprint 1 | E1 |
| E3 | 购物车与订单 | P0 | Sprint 1-2 | E1, E2 |
| E4 | 支付集成 | P0 | Sprint 2 | E3 |
| E5 | 配送订阅 | P0 | Sprint 2-3 | E3, E4 |
| E6 | 问卷与 AI 菜单 | P0/P1 | Sprint 3-4 | E1, E2 |
| E7 | 碳足迹引擎 | P1 | Sprint 2-3 | E2, E5 |
| E8 | 运营后台 | P0/P1 | Sprint 1-3 | E1-E7 |

---

## E1. 账户认证 (Authentication & Account)

**业务价值**
用户可注册、登录、管理个人资料与配送地址；为后续订阅、问卷、ESG 报告提供身份基础。

**用户故事概要**
- 作为新用户，我希望用 Email / 手机号快速注册，并使用 Google 登录。
- 作为老用户，我希望下次自动登录，并可在多设备登出。
- 作为订阅用户，我希望管理多个配送地址并设置默认地址。

**优先级**：P0

**关键 AC (Given/When/Then)**
1. **Given** 用户填写有效 Email + 密码，**When** 点击注册，**Then** 收到验证邮件并可登录。
2. **Given** 用户使用 Google 登录，**When** 完成 OAuth 回调，**Then** 自动创建/匹配本地账户并登录。
3. **Given** 已登录用户，**When** 连续 30 分钟无操作，**Then** 会话过期需重新登录。
4. **Given** 用户在 Account 页，**When** 新增 / 编辑 / 删除地址，**Then** 数据持久化到 MySQL 且默认地址唯一。
5. **Given** 用户忘记密码，**When** 提交注册邮箱，**Then** 收到重置链接，1 小时内有效。

**依赖关系**
- 依赖：无
- 被依赖：E2, E3, E5, E6, E8

---

## E2. 商品目录 (Product Catalog)

**业务价值**
展示本地有机农产品的 SKU、产地、农户、价格与库存，是用户购买与菜单推荐的基础。

**用户故事概要**
- 作为消费者，我希望按"有机蔬菜 / 水果 / 谷物 / 套餐"分类浏览商品。
- 作为消费者，我希望看到每个商品的产地、农户照片、是否有有机认证。
- 作为运营，我希望能批量上下架、调整库存与价格。

**优先级**：P0

**关键 AC (Given/When/Then)**
1. **Given** 用户进入 /catalog，**When** 选择分类，**Then** 看到对应商品列表（含图片、价格、产地、有机徽章）。
2. **Given** 用户在搜索框输入关键词，**When** 点击搜索，**Then** 返回匹配商品（按名称 / 农户 / 标签模糊匹配）。
3. **Given** 商品库存为 0，**When** 用户浏览，**Then** 显示"缺货"并禁用"加入购物车"按钮。
4. **Given** 运营在后台编辑商品，**When** 保存，**Then** 前台 ≤ 60 秒内可见更新（带 Cache invalidation）。
5. **Given** 用户点击商品详情，**When** 加载详情页，**Then** 展示完整溯源信息（农场名、地址、证书号）。

**依赖关系**
- 依赖：E1（用户体系）
- 被依赖：E3, E5, E6, E7

---

## E3. 购物车与订单 (Cart & Order)

**业务价值**
将"浏览"转化为"购买"的核心交易链路，包含购物车、结算、单次订单与订单详情。

**用户故事概要**
- 作为消费者，我希望把多个商品加入购物车并调整数量。
- 作为消费者，我希望结算时选择地址 + 配送时间窗。
- 作为消费者，我希望下单后看到订单详情和状态。

**优先级**：P0

**关键 AC (Given/When/Then)**
1. **Given** 已登录用户，**When** 点击"加入购物车"，**Then** 购物车数量 +1 并持久化到 MySQL（即使刷新仍在）。
2. **Given** 购物车有商品，**When** 进入 /checkout，**Then** 显示地址选择、时段选择、费用预览。
3. **Given** 用户确认下单，**When** 点击"提交订单"，**Then** 创建 Order + OrderItems，库存预扣减，返回订单号。
4. **Given** 订单已创建，**When** 用户访问 /orders/{id}，**Then** 看到状态（待支付 / 已支付 / 已发货 / 已签收 / 已取消）[^state-terms]。
5. **Given** 订单 30 分钟未支付，**When** 系统 Cron 运行，**Then** 自动取消并释放库存。

**依赖关系**
- 依赖：E1, E2
- 被依赖：E4, E5, E7, E8

---

## E4. 支付集成 (Payment Integration)

**业务价值**
完成"商品 → 现金"的最后一公里，支持香港本地主流支付方式，提升转化率。

**用户故事概要**
- 作为消费者，我希望用 Stripe (信用卡) / PayMe / FPS 付款。
- 作为消费者，我希望付款失败时收到明确提示并可重试。

**优先级**：P0

**关键 AC (Given/When/Then)**
1. **Given** 用户在结算页，**When** 选择支付方式，**Then** 显示对应支付按钮 (Stripe Checkout / PayMe QR / FPS 转账记录)。
2. **Given** Stripe Webhook 收到 `payment_intent.succeeded`，**When** 系统验证签名，**Then** 订单状态变为"已支付"并触发后续履约。
3. **Given** 用户支付失败，**When** 回调失败事件，**Then** 订单保持"待支付"并发送失败邮件。
4. **Given** 用户选择 PayMe / FPS，**When** 生成二维码或收款参考号，**Then** 用户有 15 分钟完成支付，超时自动取消订单。
5. **Given** 财务对账，**When** 运营导出订单，**Then** CSV 包含支付方式、流水号、金额、时间。

**依赖关系**
- 依赖：E3
- 被依赖：E5（订阅续费也走支付）

---

## E5. 配送订阅 (Delivery & Subscription)

**业务价值**
从"一次性购买"升级为"周期性订阅"，是 LTV 与北极星指标的核心引擎。

**用户故事概要**
- 作为订阅用户，我希望选"每周 / 每两周"配送频率并自定义跳过某一周。
- 作为订阅用户，我希望每次扣款前收到通知，并可随时暂停 / 取消。

**优先级**：P0

**关键 AC (Given/When/Then)**
1. **Given** 用户在商品详情，**When** 点击"订阅此商品"，**Then** 可选周期（Weekly / Bi-weekly / Monthly）并设置配送地址。
2. **Given** 订阅创建成功，**When** Cron 提前 24 小时触发，**Then** 自动生成下周订单并尝试扣款。
3. **Given** 扣款失败，**When** 重试 3 次均失败，**Then** 自动暂停订阅并通知用户。
4. **Given** 用户主动暂停，**When** 在订阅中心点击暂停，**Then** 后续周期不生成订单，恢复后可继续。
5. **Given** 用户取消订阅，**When** 确认取消，**Then** 订阅标记为 cancelled 但历史订单保留。

**依赖关系**
- 依赖：E3, E4
- 被依赖：E7（订阅订单也是碳足迹统计来源）

---

## E6. 问卷与 AI 菜单 (Survey & AI Menu)

**业务价值**
通过 Gemini 生成个性化周菜单，差异化于纯电商竞品；问卷数据反哺推荐系统。

**用户故事概要**
- 作为新用户，我希望完成一份 1-2 分钟问卷（口味、过敏、目标）。
- 作为订阅用户，我希望每周收到 AI 生成的"本周菜单"，可一键加入购物车。

**优先级**：P0（问卷）+ P1（AI 推荐核心算法）

**关键 AC (Given/When/Then)**
1. **Given** 新用户进入 /survey，**When** 提交 8-10 道题，**Then** 问卷答案保存到 `user_preferences` 表。
2. **Given** 用户问卷完成，**When** 进入 /menu，**Then** 调用 Gemini API 生成 5 菜 1 汤的本周菜单（含食材映射到商品 SKU）。
3. **Given** AI 调用失败，**When** Gemini 返回错误，**Then** 降级到"基于问卷标签的规则菜单"并提示用户。
4. **Given** 用户对某道菜点"不喜欢"，**When** 提交反馈，**Then** 该菜品从下期菜单排除并记录 negative signal。
5. **Given** 用户点击"加入购物车"，**When** 确认，**Then** 全部食材 SKU 一次性入车并按套餐价结算。

**依赖关系**
- 依赖：E1, E2
- 被依赖：无（独立模块）

---

## E7. 碳足迹引擎 (Carbon Footprint Engine)

**业务价值**
为每份订单 / 每个订阅生成碳排数据，是 ESG 报告与 B2B 客户买单的"硬通货"。

**用户故事概要**
- 作为消费者，我希望看到"这一单我减排了多少 kg CO₂"。
- 作为 B2B 客户，我希望导出"本店年度 ESG 报告"PDF。

**优先级**：P1

**关键 AC (Given/When/Then)**
1. **Given** 订单完成，**When** 系统结算，**Then** 按公式 `Σ(商品碳系数 × 数量) - 本地直送减排` 计算 `co2_saved_kg`。
2. **Given** 用户访问 /account/sustainability，**When** 加载页面，**Then** 看到累计减排、本月减排、本月订单数。
3. **Given** B2B 账户在 /admin/esg，**When** 选择时间范围并点击导出，**Then** 生成含图表的 PDF 报告。
4. **Given** 商品新增产地，**When** 运营填写"产地距离市区 (km)"，**Then** 碳系数自动按距离加成。
5. **Given** 公式更新，**When** 管理员在后台调整系数表，**Then** 历史订单按新公式重新计算（保留旧值快照）。

**依赖关系**
- 依赖：E2（商品碳系数表）, E5（订阅订单数据）
- 被依赖：E8（ESG 报告在运营后台）

---

## E8. 运营后台 (Admin Console)

**业务价值**
让运营 / 客服 / 财务 / 农场管理员可独立工作，减少对开发的依赖。

**用户故事概要**
- 作为运营，我能管理商品上下架、库存、价格、查看订单。
- 作为客服，我能查询订单并修改状态（如部分退款）。
- 作为财务，我能导出每日订单 + 支付流水 CSV。
- 作为农场管理员，我能维护本地农场信息与有机证书。

**优先级**：P0（基础 CRUD）+ P1（高级报表 / 退款）

**关键 AC (Given/When/Then)**
1. **Given** 运营登录 /admin，**When** 进入"商品管理"，**Then** 可 CRUD 商品、批量导入 CSV、调整库存。
2. **Given** 客服查询订单，**When** 输入订单号 / 用户手机，**Then** 看到完整订单 + 支付 + 履约时间线。
3. **Given** 财务导出，**When** 选择日期范围，**Then** 下载 `orders_YYYYMMDD.csv` 与 `payments_YYYYMMDD.csv`。
4. **Given** 农场管理员编辑农场，**When** 上传证书 PDF，**Then** 证书在商品溯源页对用户可见。
5. **Given** RBAC 配置，**When** 客服账号访问商品编辑，**Then** 403 拒绝（仅运营可编辑）。

**依赖关系**
- 依赖：E1-E7（基于全量业务数据）
- 被依赖：无

---

## 附录 A：Epic 优先级矩阵 (MoSCoW)

| Must Have (P0) | Should Have (P1) | Could Have (P2) | Won't Have (本期) |
| --- | --- | --- | --- |
| E1 账户认证 / E2 商品目录 / E3 购物车订单 / E4 支付 / E5 订阅 / E6 问卷 / E8 后台基础 | E7 碳足迹 / E6 AI 推荐算法 / E8 高级报表 | E6 多语言菜单 / E5 B2B 订阅 | 移动 App / 自营冷链 / 跨境 |

## 附录 B：AC 验收与 DoD (Definition of Done)

每个 Story 必须满足以下 DoD 才能合并到 `main`：

- [ ] 实现所有声明的 AC（Given/When/Then 全部可演示）
- [ ] 单元测试覆盖率 ≥ 80%（核心业务 ≥ 90%）
- [ ] 通过 `phpunit` 与浏览器 E2E（Playwright / Laravel Dusk）
- [ ] 通过 `php-cs-fixer` 与 `phpstan` level 6
- [ ] UI 走 Tailwind 4 设计系统，三语言 (zh-HK / zh-CN / en) 至少 zh-HK + en
- [ ] 部署到 staging 环境并由 QA 签字
- [ ] 文档同步更新（README / API 文档 / 运维 Runbook）

---

*本 PRD 为 MVP 范围最终事实来源；如需变更请提交 RFC 并经 PM + Tech Lead + QA 三方评审。*

[^state-terms]: 订单状态术语 SSOT（Single Source of Truth）：`docs/bmad/order-state-machine.md` 附录 A。中文术语使用「待支付 / 已支付 / 已发货 / 已签收 / 已取消 / 已退款 / 处理中」，与状态枚举 7 态完全对齐。
