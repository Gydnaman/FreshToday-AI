# GreenBite 产品 Roadmap (Sprint 1-4)

> **创建人**：pm-agent (GreenBite 产品经理)  
> **版本**：v1.0  
> **日期**：2026-06-12  
> **框架**：fdd-bmad-custom · 4 个 Sprint (Week 1-12)  
> **配套文档**：[`product-brief.md`](./product-brief.md) · [`prd-mvp.md`](./prd-mvp.md)

---

## 0. 路线图总览 (At a Glance)

| Sprint | 时间 | 核心主题 | 价值主张关键词 |
| --- | --- | --- | --- |
| Sprint 1 | Week 1-2 | 让产品"**能用**" | 真实 Auth、DB 真实读写、订单真实持久化 |
| Sprint 2 | Week 3-6 | 让产品"**可信**" | 支付集成、邮件、碳足迹公式、i18n 繁中、测试 80% |
| Sprint 3 | Week 7-9 | 让产品"**好卖**" | 搜索、优惠券、会员、SEO、部署上线 |
| Sprint 4 | Week 10-12 | 让产品"**差异化**" | iAM Smart、推荐、B2B、ESG 报告 |

> **节奏说明**：Sprint 1 短 (2 周) 验证骨架；Sprint 2 长 (4 周) 是核心交付窗口；Sprint 3 短 (3 周) 补齐上线要素；Sprint 4 短 (3 周) 押注差异化。每个 Sprint 结束必须有 **可演示的增量 (Demoable Increment)**。

---

## Sprint 1 — 让产品"能用" (Week 1-2)

### 目标 (Sprint Goal)
> 用真实数据跑通"注册 → 浏览 → 加购 → 下单 → 查订单"主链路，去掉所有 Mock / 假数据。

### Epic 清单
- **E1 账户认证** (P0) — 完整功能
- **E2 商品目录** (P0) — 分类、详情、搜索 (基础 LIKE)
- **E3 购物车与订单** (P0) — 不含真实支付（先打"货到付款"占位）
- **E8 运营后台** (P0 基础) — 商品 CRUD + 订单列表

### 交付物 (Deliverables)
- [ ] Laravel 12 项目脚手架 + MySQL 8 Migration 全套
- [ ] 用户注册 / 登录 / Google OAuth
- [ ] 商品目录页 (含 8 个种子商品 / 2 个本地农场)
- [ ] 购物车 + 订单创建 + 订单详情页
- [ ] 运营后台商品管理 + 订单查看
- [ ] CI (GitHub Actions) + Staging 部署
- [ ] 单元测试覆盖 Auth / Order 模块 ≥ 60%

### DoD (Definition of Done)
- 任何新用户从注册到下单全流程 ≤ 5 分钟
- 所有表单有服务端校验
- 订单数据真实落库 `orders` + `order_items`
- 测试覆盖率 ≥ 60%，CI 绿灯
- 部署到 staging 并完成 1 次内部 demo

---

## Sprint 2 — 让产品"可信" (Week 3-6)

### 目标 (Sprint Goal)
> 接入真实支付 + 邮件 + 碳足迹公式 + 繁中 i18n + 订阅闭环，把产品从"能跑"变成"能交付给真实付费用户"。

### Epic 清单
- **E4 支付集成** (P0) — Stripe + PayMe + FPS
- **E5 配送订阅** (P0 基础) — 周期 / 暂停 / 取消
- **E7 碳足迹引擎** (P1 基础公式)
- **E1 / E2 / E3 增强** — i18n 繁中、Email 通知、错误页
- **测试** — 覆盖率提升到 ≥ 80%

### 交付物
- [ ] Stripe Checkout 集成 + Webhook 验签
- [ ] PayMe 二维码 + FPS 转账参考号生成
- [ ] 邮件服务 (订单确认 / 发货通知 / 支付失败) — Mailgun 或 SES
- [ ] 碳足迹公式 + 用户减排看板 `/account/sustainability`
- [ ] 订阅创建 / Cron 续单 / 暂停 / 取消
- [ ] 繁中 (zh-HK) 全站文案 + 英文 (en) 关键页
- [ ] Playwright / Laravel Dusk E2E 至少 5 条主流程

### DoD
- 真实支付 1 分钱测试单成功流转
- 碳足迹公式经 PM + 农场主签字确认
- 订阅 Cron 在 staging 跑 7 天零异常
- 测试覆盖率 ≥ 80%，E2E 主流程全绿
- 繁中翻译完成率 100% (UI 文案)

---

## Sprint 3 — 让产品"好卖" (Week 7-9)

### 目标 (Sprint Goal)
> 通过搜索 / 优惠 / 会员 / SEO / 公网部署，让第一批真实 HK 用户能"找到我们 → 试用 → 付费"。

### Epic 清单
- **E2 商品目录** (P1) — 全文搜索 (MySQL FULLTEXT 或 Meilisearch)
- **E5 配送订阅** (P1) — 优惠券 / 会员折扣
- **E8 运营后台** (P1) — 优惠券 / 会员等级 / 财务报表导出
- **上线** — HTTPS / 域名 / CDN / 监控 (Sentry)
- **SEO** — Meta / Open Graph / sitemap / 结构化数据

### 交付物
- [ ] 商品搜索（支持中文 / 英文 / 拼音）≤ 300ms
- [ ] 优惠券系统（百分比 / 满减 / 首单）
- [ ] 会员等级（普通 / 银 / 金）按累计订单自动升级
- [ ] 公网 HTTPS 部署 (含 HSTS / Let's Encrypt)
- [ ] SEO 基础：sitemap.xml / robots.txt / OG 图 / JSON-LD Product
- [ ] Sentry 错误监控 + UptimeRobot 健康检查
- [ ] 隐私政策 / 服务条款 / 退款政策 (zh-HK / en 双语)

### DoD
- 真实公网域名可访问，HTTPS 评分 A
- 优惠券全链路 E2E 通过（创建 → 领取 → 使用 → 核销）
- 监控告警配置完成（错误率 > 1% 触发 Slack）
- Lighthouse 性能分 ≥ 80，移动端可用
- 隐私政策 / 退款政策经法务 review

---

## Sprint 4 — 让产品"差异化" (Week 10-12)

### 目标 (Sprint Goal)
> 落地 iAM Smart 香港身份认证、AI 推荐核心算法、B2B 餐厅版订单流、ESG 报告生成，确立相对于"普通有机电商"的竞争壁垒。

### Epic 清单
- **E6 问卷与 AI 菜单** (P1 核心算法) — Gemini 推荐闭环
- **E5 配送订阅** (P2) — B2B 餐厅版订单流（按需 + 月结）
- **E7 碳足迹引擎** (P1) — ESG 报告 PDF 导出
- **E1 账户认证** (P2) — iAM Smart 集成
- **运营** — A/B 测试 + 数据看板 (GA4 + Metabase)

### 交付物
- [ ] iAM Smart 登录（B2C 试点 1000 用户）
- [ ] Gemini 菜单推荐（含降级策略与 negative signal 学习）
- [ ] B2B 餐厅版：企业账户 / 月结 / 批量下单 / 发票
- [ ] ESG 报告 PDF（家庭版 + 餐厅版，含图表与碳排明细）
- [ ] GA4 + Metabase 看板（北极星指标 + 3 个次要指标实时可视化）
- [ ] A/B 测试框架（按钮 / 文案 / 价格）

### DoD
- iAM Smart 在沙盒环境完整跑通
- AI 推荐 1 周 A/B 测试 CTR 提升 ≥ 10%
- B2B 第一家餐厅客户完成 1 次端到端订单
- ESG 报告 PDF 排版经设计 / 品牌签字
- 北极星指标看板日活活跃（团队每日查看）

---

## 跨 Sprint 节奏 (Cross-Sprint Cadence)

| 活动 | 频率 | 参与者 |
| --- | --- | --- |
| Daily Standup | 每日 09:30 (15 min) | 全员 |
| Sprint Planning | Sprint 启动第 1 天 (2h) | PM + Tech Lead + 全员 |
| Backlog Refinement | Sprint 中段 (1.5h) | PM + Dev + QA |
| Sprint Review / Demo | Sprint 末 (1h) | 全员 + Stakeholders |
| Retrospective | Sprint 末 (1h) | 全员 |
| RFC 评审 | 按需 | PM + Tech Lead + QA |

## 风险与依赖 (Risks & Dependencies)

| 风险 | 等级 | 缓解措施 |
| --- | --- | --- |
| Gemini API 配额 / 延迟 | 中 | 准备规则引擎降级方案，缓存常见问卷结果 |
| Stripe 香港商户审核 | 中 | Sprint 1 提前申请，Sprint 2 留 buffer |
| iAM Smart 集成复杂度 | 高 | Sprint 4 试点，失败则推迟到 v1.1 |
| 本地农场产能不稳定 | 中 | 后台支持临时下架 + 替代品推荐 |
| 团队 jQuery + Blade 经验 | 低 | 早 Sprint 内技术分享 + code review |

## 关键里程碑 (Milestones)

- **M1 (Week 2 end)**：Sprint 1 Demo — 内部可下单
- **M2 (Week 6 end)**：Sprint 2 Demo — 真实支付 + 订阅 + 碳足迹
- **M3 (Week 9 end)**：Sprint 3 Demo — 公网上线 + SEO
- **M4 (Week 12 end)**：Sprint 4 Demo — 差异化功能全部就绪 + 复盘
- **GA (Week 13)**：正式对外发布 GreenBite v1.0

---

*本 Roadmap 为方向性规划，每个 Sprint 启动前会通过 BMAD Sprint Planning 工作流重新确认范围与优先级。*
