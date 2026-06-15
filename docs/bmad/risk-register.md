# GreenBite Sprint 1 风险登记册 (Risk Register)

> **创建人**：pm-agent (charlie) — GreenBite 产品经理
> **版本**：v1.0
> **日期**：2026-06-12 (Sprint 1 Day 2)
> **框架**：fdd-bmad-custom · Risk = Probability × Impact（1-5 标度）
> **配套文档**：[`sprint-1-backlog.md`](./sprint-1-backlog.md) · [`STATUS-UPDATE-2026-06-12-v1.1.md`](./STATUS-UPDATE-2026-06-12-v1.1.md) · [`REVIEW-REPORT.md`](./REVIEW-REPORT.md)

---

## 0. 文档说明

本登记册是 Sprint 1（Week 1-2）的**活文档**（Living Document），每条风险按以下 9 元组结构化管理：

| 字段 | 含义 |
| --- | --- |
| **ID** | 风险唯一编号（`R-XXX`） |
| **风险描述** | 具体风险场景 |
| **类别** | 技术 / 业务 / 进度 / 安全 / 合规 |
| **概率 (P)** | 1=极低 2=低 3=中 4=高 5=极高 |
| **影响 (I)** | 1=可忽略 2=小 3=中 4=大 5=灾难 |
| **风险分 = P×I** | 1-25（≥ 12 需重点关注） |
| **缓解策略** | 主动 / 被动 / 转移 / 接受 |
| **责任人** | 跟踪该风险到关闭的 agent |
| **触发条件** | 何时升级 / 何时重新评估 |
| **状态** | Open / Mitigating / Monitoring / Closed / Accepted |

**风险分等级**：

| 等级 | 分值 | 颜色 | 行动 |
| --- | --- | --- | --- |
| 🔴 严重 | 15-25 | 红色 | **必须**有缓解 Owner，每 24h 跟踪 |
| 🟡 高 | 8-14 | 黄色 | 必须有缓解 Owner，每 3 天跟踪 |
| 🟢 中 | 4-7 | 绿色 | 监控 + 兜底方案 |
| ⚪ 低 | 1-3 | 灰色 | 接受，记录存档 |

---

## 1. 风险总览（风险热图）

| 风险 ID | 标题 | 类别 | P | I | 风险分 | 状态 | Owner |
| --- | --- | --- | --- | --- | --- | --- | --- |
| **R-001** | Redis 服务不可用（Cache 驱动降级） | 技术 | 4 | 2 | **8** 🟡 | Mitigating | echo |
| **R-002** | MySQL CHECK 约束并发竞争（库存超扣） | 技术 | 3 | 4 | **12** 🟡 | Mitigating | bravo |
| **R-003** | vendor 安装失败（composer / npm） | 进度 | 5 | 2 | **10** 🟡 | Open | echo |
| **R-004** | Stripe API 限速 / Webhook 延迟 | 技术 | 3 | 3 | **9** 🟡 | Mitigating | golf |
| **R-005** | AI 菜单命中率 < 60%（Gemini 幻觉） | 业务 | 3 | 3 | **9** 🟡 | Open | golf + hotel |
| **R-006** | 退款率 > 5%（食材质量 / 配送延迟） | 业务 | 3 | 4 | **12** 🟡 | Monitoring | charlie |
| **R-007** | i18n 实施延期（P0-I18N-01/02/03/04） | 进度 | 3 | 3 | **9** 🟡 | Mitigating | golf + bravo |
| **R-008** | HK 区域网络合规问题（ap-east-1 选型） | 合规 | 2 | 4 | **8** 🟡 | Monitoring | bravo + echo |
| **R-009** | Google OAuth 回调域名未配 | 技术 | 2 | 3 | **6** 🟢 | Open | echo |
| **R-010** | 订阅状态字段双轨制（NEW-P1-03） | 技术 | 2 | 2 | **4** 🟢 | Mitigating | bravo |
| **R-011** | Sentry DSN 泄露 / Prometheus exporter 故障 | 安全 | 1 | 3 | **3** ⚪ | Monitoring | echo |

> **统计**：11 条风险 / 0 条 🔴 严重 / 8 条 🟡 高 / 2 条 🟢 中 / 1 条 ⚪ 低
> **最高风险**：R-002（MySQL CHECK 并发）、R-006（退款率）并列 12 分

---

## 2. 技术风险（4 条）

### R-001：Redis 服务不可用（Cache 驱动降级）

| 字段 | 内容 |
| --- | --- |
| **风险描述** | Sprint 1 staging 部署若使用 Redis 作为 `Cache` / `Session` 驱动，Redis 故障会导致：(1) `AiMenuService` 缓存失效（每秒增加 1 次 Gemini API 调用）；(2) Session 丢失，全部用户登出；(3) Cart/Order 写穿 DB 后响应变慢（P95 500ms → 2s） |
| **类别** | 技术 |
| **概率 (P)** | 4（高 — Redis 客户端未在 `composer.json` 锁定，运维常忘部署） |
| **影响 (I)** | 2（小 — v1.1 已修复：`AiMenuService` 改用 `Cache::` 抽象，自动 fallback 到 file 驱动） |
| **风险分** | **8** 🟡 |
| **缓解策略** | 主动 + 被动（Defense in Depth） |
| **缓解动作** | 1. `composer.json` 移除 `predis/predis` 强制依赖，保留可选<br>2. `.env.staging` 配 `CACHE_DRIVER=file`、`SESSION_DRIVER=file`<br>3. `config/cache.php` 默认 stores = `array`（单元测试）+ `file`（staging）<br>4. monitoring 告警：Cache 命中率 < 70% 触发 ALR-020 |
| **责任人** | echo（部署配置）/ bravo（架构） |
| **触发条件** | staging 部署后 24h 内 Cache 命中率 < 70% 或 Session 写入失败率 > 0.1% |
| **状态** | Mitigating（v1.1 已修） |

---

### R-002：MySQL CHECK 约束并发竞争（库存超扣）

| 字段 | 内容 |
| --- | --- |
| **风险描述** | `cart_items` / `order_items` 表用 `CHECK (quantity >= 0)` 约束库存，但**高并发下可能出现 TOCTOU（Time-of-Check to Time-of-Use）竞态**：用户 A 看到库存 1 并下单，用户 B 同样下单，两个事务都通过 CHECK，最终库存 -1；MySQL 8 默认 `READ-COMMITTED` 下不阻塞 |
| **类别** | 技术 |
| **概率 (P)** | 3（中 — 限量商品（鸡蛋 / 时令菜）首发日 P=5） |
| **影响 (I)** | 4（大 — 用户付款后缺货，触发退款 + 客诉，影响复购） |
| **风险分** | **12** 🟡 |
| **缓解策略** | 主动（应用层乐观锁）+ 被动（CHECK 兜底） |
| **缓解动作** | 1. `OrderService::createWithStockGuard()` 用 `SELECT ... FOR UPDATE` 行锁<br>2. `inventory_reservations` 预占表（15 分钟 TTL），避免直接扣 `products.stock`<br>3. DB 触发器：BEFORE INSERT 校验 `stock >= quantity` 抛 SIGNAL<br>4. Edge Case Hunter 写 `E-12 库存并发边界` 测试 |
| **责任人** | bravo（DB 设计）/ golf（Service 实现）/ delta（并发测试） |
| **触发条件** | staging 压测并发 ≥ 50 时出现 `stock < 0`；或线上退款单中"库存不足"占比 > 1% |
| **状态** | Mitigating（v1.1 `OrderService` 已加 guard） |

---

### R-004：Stripe API 限速 / Webhook 延迟

| 字段 | 内容 |
| --- | --- |
| **风险描述** | (1) Stripe 测试模式有 100 req/s 限速，Sprint 1 压测 200 并发下单可能触发 429；(2) Webhook 端到端延迟 P95 > 5s，导致订单长时间停在 `pending`；(3) Stripe SDK 未安装（vendor 缺失）导致签名验证失败 |
| **类别** | 技术 |
| **概率 (P)** | 3（中 — 限速容易触发，但限流后用户可重试） |
| **影响 (I)** | 3（中 — 主链路阻塞但可恢复） |
| **风险分** | **9** 🟡 |
| **缓解策略** | 主动 + 转移（Stripe 兜底逻辑） |
| **缓解动作** | 1. Day 4 echo 跑 `composer require stripe/stripe-php`（v1.1 已识别）<br>2. Webhook 路由加 middleware 记录 `received_at`，`processed_at`，延迟 > 5s 告警 ALR-015<br>3. 客户端 retry：前端按钮置灰 + Loading 30s，捕获 429 后指数退避<br>4. 幂等性：`stripe_webhook_events.event_id` 唯一约束 + `processed_at` 标志 |
| **责任人** | echo（SDK 部署）/ golf（Web 集成）/ delta（幂等测试） |
| **触发条件** | 24h 内 webhook 失败 > 5 次 或 API 限速 429 > 10 次 |
| **状态** | Mitigating |

---

### R-009：Google OAuth 回调域名未配

| 字段 | 内容 |
| --- | --- |
| **风险描述** | Google OAuth Client ID 已申请，但 staging 域名（`staging.greenbite.hk`）未加入 Google Console 白名单，回调时会返回 `redirect_uri_mismatch` 错误，US-001 AC-1.2 直接 fail |
| **类别** | 技术 |
| **概率 (P)** | 2（低 — 域名配置即可解决） |
| **影响 (I)** | 3（中 — 仅阻塞 Google 登录，邮箱注册不受影响） |
| **风险分** | **6** 🟢 |
| **缓解策略** | 主动（配置）+ 被动（降级） |
| **缓解动作** | 1. Day 4 echo 在 Google Console 加 staging + localhost 回调 URL<br>2. 降级：Google 按钮失败提示"请用邮箱注册"，`flash` 友好提示<br>3. e2e 测试跳过 OAuth 场景，Day 5 真实测试 |
| **责任人** | echo（域名配置） |
| **触发条件** | Day 4 E2E `auth.spec.ts` Google 登录用例 fail |
| **状态** | Open |

---

## 3. 业务风险（2 条）

### R-005：AI 菜单命中率 < 60%（Gemini 幻觉）

| 字段 | 内容 |
| --- | --- |
| **风险描述** | US-002 Gemini API 生成的菜单可能：(1) 推荐用户过敏的食材（花生 / 海鲜）；(2) 推荐 `catalog` 中不存在的 SKU（幻觉）；(3) 营养搭配不均衡（全是蔬菜无蛋白）。导致"一鍵加購"失败、客诉、用户流失 |
| **类别** | 业务 |
| **概率 (P)** | 3（中 — Gemini 在 prompt 良好时命中率 ~75%，但缺乏反馈循环时可能 60%） |
| **影响 (I)** | 3（中 — 短期不阻塞交易，长期影响 AI 差异化） |
| **风险分** | **9** 🟡 |
| **缓解策略** | 主动（规则引擎降级）+ 反馈循环 |
| **缓解动作** | 1. **降级 AC-2.2**：Gemini 超时/错误 → `RuleBasedMenuBuilder` 按 dietary + allergies 过滤<br>2. **后处理守卫**：生成菜单后服务端二次校验 SKU 在 catalog + 用户无过敏<br>3. **埋点**：`menu_acceptance` 事件（点击"一鍵加購"率）目标 ≥ 60%<br>4. **A/B 测试**：Sprint 4 上线，对照组（规则）vs 实验组（Gemini） |
| **责任人** | golf（实现）/ hotel（埋点分析）/ charlie（业务验收） |
| **触发条件** | staging 灰度 100 用户时 `menu_acceptance < 60%` 或 过敏投诉 > 0 |
| **状态** | Open |

---

### R-006：退款率 > 5%（食材质量 / 配送延迟）

| 字段 | 内容 |
| --- | --- |
| **风险描述** | 退款率（refund_count / order_count）是电商核心健康指标。GreenBite MVP 阶段可能因：(1) 配送延迟 > 24h 导致蔬菜不新鲜；(2) 包装破损；(3) SKU 与描述不符（农户上传图与实物偏差），导致退款率超 5% 警戒线 |
| **类别** | 业务 |
| **概率 (P)** | 3（中 — MVP 阶段供应链不稳定是常态） |
| **影响 (I)** | 4（大 — 财务损失 + 客诉 + Stripe 风控（> 1% 触发审核）） |
| **风险分** | **12** 🟡 |
| **缓解策略** | 主动（供应链管理）+ 被动（运营快速响应） |
| **缓解动作** | 1. 退款率看板：Grafana panel `refund_rate_7d` > 5% 触发 ALR-021<br>2. 运营 SLA：收到退款申请 2h 内处理<br>3. 农户合同：要求 95% 订单 SKU 与图片一致，违约扣点<br>4. 冷链预案：香港夏季高温，限量商品投保运输险<br>5. monitoring RB-006 Runbook：退款率飙升处置流程 |
| **责任人** | charlie（业务监控）/ echo（监控配置）/ ops（运营 SOP） |
| **触发条件** | 7 日滚动退款率 > 5% 或 Stripe 拒付（chargeback）率 > 0.5% |
| **状态** | Monitoring |

---

## 4. 进度风险（2 条）

### R-003：vendor 安装失败（composer / npm）

| 字段 | 内容 |
| --- | --- |
| **风险描述** | Sprint 1 当前环境 **vendor/ 目录未安装**（v1.1 §6.1 风险已识别），导致：(1) `php artisan test` 无法跑（35 用例全部"未执行"）；(2) Stripe SDK 缺失 → Webhook 验签失败 → US-004 AC-4.4 fail；(3) `composer install` 失败（PHP 版本 / 扩展缺失）会阻塞整个 CI |
| **类别** | 进度 |
| **概率 (P)** | 5（极高 — 确认当前 vendor 不存在） |
| **影响 (I)** | 2（小 — 一次性解决，不影响功能） |
| **风险分** | **10** 🟡 |
| **缓解策略** | 主动（Day 4 解决） |
| **缓解动作** | 1. **Day 4 必做项**：echo 跑 `composer install --no-dev --optimize-autoloader`<br>2. CI 步骤：先 `composer validate` 再 `install`<br>3. Dockerfile（多阶段构建）锁 PHP 8.3 + ext-pdo_mysql + ext-bcmath<br>4. 备用：`composer create-project` 全新环境验证<br>5. 文档：Day 4 完成前所有测试代码标"未执行" |
| **责任人** | echo（环境部署） |
| **触发条件** | Day 4 18:00 vendor 仍未安装 或 CI composer 步骤 fail |
| **状态** | Open（最高优先级） |

---

### R-007：i18n 实施延期（P0-I18N-01/02/03/04）

| 字段 | 内容 |
| --- | --- |
| **风险描述** | REVIEW-REPORT §10.4 指出 i18n v1.0 复评 **FAIL（5.85/10）**，4 项 P0 缺失：`i18n-loader.js` / `SetLocale.php` middleware / `resources/lang/*.json` / Blade `__()` 迁移。若 Sprint 1 Day 4 未完成，将：(1) 繁中用户全部看到英文硬编码；(2) Sprint 2 启动基线缺失；(3) 香港市场本地化承诺落空 |
| **类别** | 进度 |
| **概率 (P)** | 3（中 — 4 项 P0 工作量合计 16h，可行但紧） |
| **影响 (I)** | 3（中 — 不阻塞主链路，但影响用户验收） |
| **风险分** | **9** 🟡 |
| **缓解策略** | 主动（拆分任务 + 并行） |
| **缓解动作** | 1. Day 4 三人并行：golf 写 `i18n-loader.js` / `SetLocale.php` / Blade 迁移；bravo 同步 JSON；echo 写 CSP<br>2. MVP 降级：先 zh-HK + en，zh-CN 推迟到 Sprint 2<br>3. 端到端验证：Playwright 切语言截图回归<br>4. 风险升级：若 Day 4 EOD 未完成，i18n v1.1 复评延后到 Day 5 |
| **责任人** | golf（前端 + middleware）/ bravo（JSON）/ charlie（验收） |
| **触发条件** | Day 4 18:00 4 项 P0 完成 < 2 项 |
| **状态** | Mitigating（任务已派发） |

---

## 5. 合规 / 安全 / 其他（3 条）

### R-008：HK 区域网络合规问题（ap-east-1 选型）

| 字段 | 内容 |
| --- | --- |
| **风险描述** | architecture.md 提到 staging 在 HK-1，但未明确 AWS `ap-east-1`（HK） / Aliyun HK / Hetzner FSN1 哪家。涉及：(1) HK《個人資料（私隱）條例》（PDPO）合规要求数据不出境；(2) Stripe 香港商户账号区域匹配；(3) 网络 RTT 影响支付回调 |
| **类别** | 合规 |
| **概率 (P)** | 2（低 — 内部决策可控） |
| **影响 (I)** | 4（大 — 合规问题可导致下架 / 罚款） |
| **风险分** | **8** 🟡 |
| **缓解策略** | 主动（决策 + 文档） |
| **缓解动作** | 1. Day 3 站会议决：默认 AWS `ap-east-1`（HK 区域，符合 PDPO）<br>2. architecture.md §2 NFR 表加 "数据驻留：HK 区域" 行<br>3. deployment.md 加 PDPO 合规检查清单<br>4. 法务 review：隐私政策 / 退款政策双语（PRD 已规划 Sprint 3） |
| **责任人** | bravo（架构决策）/ echo（部署实施） |
| **触发条件** | Day 3 站会未决出区域 / 法务 review 拒绝 |
| **状态** | Monitoring |

---

### R-010：订阅状态字段双轨制（NEW-P1-03）

| 字段 | 内容 |
| --- | --- |
| **风险描述** | ER 中 `user_subscriptions.status` 含 `active/paused/cancelled/expired` 4 态；api-contract 仅声明 `cancelled` 1 态。代码层用 `isActive()` 而 API 返回 `status` 字符串不一致，导致 US-006 AC-6.6 fail |
| **类别** | 技术 |
| **概率 (P)** | 2（低 — v1.1 已识别 NEW-P1-03，Day 2 站会议决） |
| **影响 (I)** | 2（小 — 仅 API 文档补齐） |
| **风险分** | **4** 🟢 |
| **缓解策略** | 主动（补 api-contract 附录 A） |
| **缓解动作** | 1. Day 2 站会 bravo 决议：api-contract 附录 A 加"订阅状态对照表"<br>2. 测试用例 `SubscriptionServiceTest` 覆盖 4 态转移<br>3. e2e 场景 3 验证状态返回 |
| **责任人** | bravo（api-contract 更新）/ delta（测试） |
| **触发条件** | api-contract v1.2 仍缺订阅状态表 |
| **状态** | Mitigating |

---

### R-011：Sentry DSN 泄露 / Prometheus exporter 故障

| 字段 | 内容 |
| --- | --- |
| **风险描述** | (1) Sentry DSN 写入 `.env` 但 git 误提交 → 攻击者灌入垃圾事件耗尽配额；(2) Prometheus `/metrics` 端点未鉴权 → 业务指标泄露给竞品；(3) exporter 进程崩溃 → 告警 ALR-016 永远 silent |
| **类别** | 安全 |
| **概率 (P)** | 1（极低 — DSN 非敏感凭证） |
| **影响 (I)** | 3（中 — 监控盲区 + 配额耗尽） |
| **风险分** | **3** ⚪ |
| **缓解策略** | 被动（接受 + 监控） |
| **缓解动作** | 1. `.env` 加 `.gitignore`（已存在）<br>2. Prometheus metrics 端点配 `basic auth`<br>3. exporter 进程由 systemd 守护，崩自动重启<br>4. ALR-016 告警：`/metrics` 5 分钟无请求触发 |
| **责任人** | echo（部署加固） |
| **触发条件** | Prometheus 上线后 1 周内有 0 请求 |
| **状态** | Monitoring（接受） |

---

## 6. 风险跟踪矩阵（RAG 状态）

| ID | 风险分 | 状态 | 缓解完成度 | 跟踪频率 |
| --- | --- | --- | --- | --- |
| R-001 | 8 | 🟢 Mitigating | 80% | 每日 standup |
| R-002 | 12 | 🟢 Mitigating | 60% | 每日 standup |
| R-003 | 10 | 🟡 Open | 0% | **每日 18:00 check-in** |
| R-004 | 9 | 🟢 Mitigating | 70% | 每日 standup |
| R-005 | 9 | 🟡 Open | 30% | 每 2 日 |
| R-006 | 12 | 🟢 Monitoring | 50% | 每日 standup |
| R-007 | 9 | 🟢 Mitigating | 40% | 每日 standup |
| R-008 | 8 | 🟢 Monitoring | 70% | 每周 |
| R-009 | 6 | 🟡 Open | 0% | Day 4 check-in |
| R-010 | 4 | 🟢 Mitigating | 80% | Day 2 站会 |
| R-011 | 3 | ⚪ Monitoring | 50% | 每周 |

---

## 7. Top 3 风险与建议措施

> 按风险分排序，最高 3 项：

### 🥇 R-002（12 分）：MySQL CHECK 约束并发竞争
- **影响**：高并发下库存超扣 → 缺货退款 + 客诉
- **建议**：
  1. Day 3 golf 完成 `inventory_reservations` 预占表（TTL 15min）
  2. Day 4 delta 写并发测试 `StockConcurrencyTest`（50 并发下单同 SKU）
  3. DB 触发器 + `SELECT ... FOR UPDATE` 双重保险

### 🥇 R-006（12 分）：退款率 > 5%
- **影响**：Stripe 风控审核 + 财务损失 + 品牌口碑
- **建议**：
  1. Day 5 staging 上线前先做 100 单灰度，监控 7 日退款率
  2. 农户合同 + 冷链保险
  3. Grafana panel `refund_rate_7d` > 5% 告警 ALR-021

### 🥉 R-003（10 分）：vendor 安装失败
- **影响**：35 用例全部"未执行" + Stripe 验签失败 + CI 红
- **建议**：
  1. **Day 4 上午** echo 必跑 `composer install`，18:00 检查
  2. CI 加 `composer validate` 步骤
  3. 多阶段 Dockerfile 锁 PHP 8.3 + 扩展

---

## 8. 风险升级流程

```
风险触发（监控告警 / E2E fail / 进度偏差）
  ↓
24h 内 agent 在 standup 提出
  ↓
风险分 ≥ 12 → 升级到 team-lead + 24h 内决策
风险分 8-11 → agent + bravo 协商
风险分 ≤ 7 → 接受并记录
  ↓
更新本登记册状态 + 风险回顾（Day 3 / Day 5）
```

---

## 9. 文档元信息

- **创建人**：pm-agent (charlie) — Sprint 1 Day 2 任务
- **创建时间**：2026-06-12 16:25 HKT
- **版本**：v1.0
- **下次更新**：Day 5 Sprint Review 后或风险状态变化时
- **关联文件**：`docs/bmad/sprint-1-backlog.md` / `docs/bmad/STATUS-UPDATE-2026-06-12-v1.1.md` / `docs/bmad/REVIEW-REPORT.md` §10

---

*— Sprint 1 风险登记册 v1.0 结束 —*
*charlie · 2026-06-12 16:25 HKT · fdd-bmad-custom PM*
