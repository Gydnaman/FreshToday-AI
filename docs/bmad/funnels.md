# GreenBite MVP 关键漏斗定义 (Funnels)

> **文档编号**：GB-DATA-002
> **责任人**：data-analyst-agent (hotel)
> **版本**：v1.0
> **日期**：2026-06-12
> **适用范围**：GreenBite MVP Sprint 1-Day2 → Sprint 4 商业指标监控
> **依赖文档**：[data-events.md](./data-events.md) · [prd-mvp.md](./prd-mvp.md) · [order-state-machine.md](./order-state-machine.md)
> **下游消费**：BI 看板 (Metabase / Superset) · 周会复盘 · 增长决策

---

## 0. 漏斗通用约定

### 0.1 漏斗原则
- 同一漏斗同一会话窗口内（默认 7 天）追踪
- 每步去重：基于 `user_id`（已登录）或 `anonymous_id`（未登录）
- 漏斗可视化：横向漏斗 + 同环比变化（WoW / DoD）
- 异常阈值：单步转化率 WoW 跌幅 > 15% 自动告警

### 0.2 命名与字段
| 维度 | 字段来源 | 备注 |
| --- | --- | --- |
| 时间窗口 | `occurred_at` | 默认 UTC+0；BI 层按 `Asia/Hong_Kong` 重新切片 |
| 用户标识 | `user_id` 或 `anonymous_id` | 二者互斥优先级：登录态 > 匿名 |
| 漏斗 ID | `funnel_id` (本文件定义) | 不可变 |
| 漏斗版本 | `funnel_version` | 业务规则变更时 +1 |
| 设备平台 | `platform` | 单独看 web vs mobile 转化差 |

### 0.3 漏斗 ID 规范
- `F-ACT-001` Activation Funnel
- `F-CON-002` Conversion Funnel
- `F-RET-003` Retention Funnel
- `F-REF-004` Refund Funnel

---

## 1. 漏斗 F-ACT-001：激活漏斗 (Activation Funnel)

### 1.1 业务目标
衡量新用户从注册到首次完成"价值时刻"（看菜单）的转化率。**北极星指标** `MAU Subscribers` 的上游必读漏斗。

### 1.2 步骤定义

| 步骤 | 事件 | 触发条件 | 备注 |
| --- | --- | --- | --- |
| **S1** | `register` | 用户落库成功 | 起点 |
| **S2** | `login` | 首次登录（`is_first_login=true`） | 注册后 24h 内 |
| **S3** | `survey_completed` | 问卷提交成功 | 登录后 7d 内 |
| **S4** | `menu_viewed` (首次) | 首次查看 AI 菜单 | 问卷后 7d 内 |
| **S5** | `menu_helpful_voted` | 菜单评分 | 激活完成标志 |

### 1.3 SQL 模板 (ClickHouse)
```sql
WITH activation AS (
  SELECT
    user_id,
    MIN(occurred_at) AS s1_at
  FROM events_raw
  WHERE event_name = 'register'
    AND occurred_at >= now() - INTERVAL 7 DAY
  GROUP BY user_id
),
s2 AS (
  SELECT user_id, MIN(occurred_at) AS s2_at
  FROM events_raw
  WHERE event_name = 'login' AND is_first_login = 1
  GROUP BY user_id
),
s3 AS (
  SELECT user_id, MIN(occurred_at) AS s3_at
  FROM events_raw
  WHERE event_name = 'survey_completed'
  GROUP BY user_id
),
s4 AS (
  SELECT user_id, MIN(occurred_at) AS s4_at
  FROM events_raw
  WHERE event_name = 'menu_viewed'
  GROUP BY user_id
),
s5 AS (
  SELECT user_id, MIN(occurred_at) AS s5_at
  FROM events_raw
  WHERE event_name = 'menu_helpful_voted'
  GROUP BY user_id
)
SELECT
  count(DISTINCT a.user_id) AS s1,
  count(DISTINCT s2.user_id) AS s2,
  count(DISTINCT s3.user_id) AS s3,
  count(DISTINCT s4.user_id) AS s4,
  count(DISTINCT s5.user_id) AS s5,
  s2 / s1 AS s1_to_s2,
  s3 / s1 AS s1_to_s3,
  s4 / s1 AS s1_to_s4,
  s5 / s1 AS s1_to_s5
FROM activation a
LEFT JOIN s2 USING(user_id)
LEFT JOIN s3 USING(user_id)
LEFT JOIN s4 USING(user_id)
LEFT JOIN s5 USING(user_id);
```

### 1.4 目标基线 (Sprint 1-2)

| 步骤 | 目标转化率 | 备注 |
| --- | --- | --- |
| S1 → S2 | ≥ 95% | 自动登录态，可视为 100% |
| S2 → S3 | ≥ 70% | 问卷完成率，**A/B-002 重点** |
| S3 → S4 | ≥ 85% | 问卷后看菜单是产品主路径 |
| S4 → S5 | ≥ 25% | 评分是"激活"信号 |
| **S1 → S5（综合）** | **≥ 18%** | 激活漏斗核心 KPI |

### 1.5 告警规则
- 任一步转化率 WoW 跌幅 > 15% → Slack `#growth-alert`
- S1 → S5 综合转化率 < 12% 持续 3 天 → 升级 PagerDuty

---

## 2. 漏斗 F-CON-002：转化漏斗 (Conversion Funnel)

### 2.1 业务目标
衡量"看菜单 → 加购 → 下单 → 支付成功"的主商业漏斗，**核心收入漏斗**。

### 2.2 步骤定义

| 步骤 | 事件 | 触发条件 | 备注 |
| --- | --- | --- | --- |
| **S1** | `menu_viewed` | 任意一次菜单查看 | 流量入口 |
| **S2** | `cart_item_added` | 首次加购 | 7d 内 |
| **S3** | `order_created` | 下单 | 7d 内 |
| **S4** | `order_paid` | 支付成功 | 7d 内；`status='paid'` |
| **S5** | `order_delivered` | 送达 | 14d 内（物流时效） |

### 2.3 步骤间关键比值
- **菜单-加购率 (CVR1)** = S2 / S1
- **加购-下单率 (CVR2)** = S3 / S2
- **下单-支付率 (CVR3)** = S4 / S3
- **支付-送达率 (CVR4)** = S5 / S4
- **AOV** = sum(total_hkd) / S4
- **GMV (7d)** = sum(total_hkd where event=order_paid)

### 2.4 SQL 模板 (BI 看板)
```sql
SELECT
  toDate(occurred_at, 'Asia/Hong_Kong') AS biz_date,
  countIf(event_name = 'menu_viewed') AS s1,
  countIf(event_name = 'cart_item_added') AS s2,
  countIf(event_name = 'order_created') AS s3,
  countIf(event_name = 'order_paid') AS s4,
  countIf(event_name = 'order_delivered') AS s5,
  round(s2 / s1, 4) AS cvr1,
  round(s3 / s2, 4) AS cvr2,
  round(s4 / s3, 4) AS cvr3,
  round(s5 / s4, 4) AS cvr4,
  round(s4 / s1, 4) AS overall_cvr
FROM events_raw
WHERE occurred_at >= today() - 30
GROUP BY biz_date
ORDER BY biz_date DESC;
```

### 2.5 目标基线 (Sprint 2 末)

| 步骤 | 目标转化率 | 行业参考 (电商基准) |
| --- | --- | --- |
| S1 → S2 (CVR1) | ≥ 12% | 8-15% (内容电商) |
| S2 → S3 (CVR2) | ≥ 35% | 30-50% (加购→结算) |
| S3 → S4 (CVR3) | ≥ 80% | 75-90% (下单→支付) |
| S4 → S5 (CVR4) | ≥ 95% | 90-98% (支付→送达) |
| **S1 → S4 (综合)** | **≥ 3.2%** | 电商基线 1-3% |
| **AOV (HKD)** | **≥ 280** | HK 餐厨电商 220-350 |

### 2.6 漏斗切片维度
- 渠道：`utm_source`（自然搜索 / SEM / 社交 / 邮件）
- 设备：`platform` (web / ios / android)
- 用户分层：首次 vs 回访；订阅 vs 单买
- 问卷完整度：完成 6 题 vs 3 题精简版（**A/B-002 关联**）

### 2.7 异常告警
- CVR3 (下单→支付) < 70% 持续 2h → 支付通道告警
- CVR1 日环比跌幅 > 20% → 推荐/菜单异常告警

---

## 3. 漏斗 F-RET-003：留存漏斗 (Retention Funnel)

### 3.1 业务目标
衡量用户的生命周期留存能力，订阅模式关键。**MAU Subscribers** 的下游漏斗。

### 3.2 留存定义
- **D1 回访**：注册后第 1 天（含注册当天）有任意活跃事件
- **D7 回访**：注册后第 7 天有任意活跃事件（**D7 ± 1d 容忍窗口**）
- **D30 回访**：注册后第 30 天（**D30 ± 3d 容忍窗口**）
- **WAU/MAU**：自然周/月内活跃用户数

### 3.3 步骤定义

| 步骤 | 业务含义 | 事件触发 | 容忍窗口 |
| --- | --- | --- | --- |
| **S0** | 注册用户 | `register` | 起点 |
| **S1** | D1 回访 | 任意 `event_name IN (login, menu_viewed, cart_item_added, order_*)` | 0-24h |
| **S2** | D7 回访 | 同上 | 6-8d |
| **S3** | D14 回访 | 同上 | 13-15d |
| **S4** | D30 回访 | 同上 | 27-33d |
| **S5** | 订阅转化 | `subscription_created` | 0-30d |
| **S6** | 二次购买 | `order_paid` 计数 ≥ 2 | 0-60d |

### 3.4 队列分析 (Cohort) 模板
```sql
WITH cohorts AS (
  SELECT user_id, toDate(min(occurred_at)) AS cohort_date
  FROM events_raw
  WHERE event_name = 'register'
  GROUP BY user_id
),
activity AS (
  SELECT user_id, toDate(occurred_at) AS active_date
  FROM events_raw
  WHERE event_name IN ('login', 'menu_viewed', 'cart_item_added',
    'order_created', 'order_paid', 'subscription_created')
  GROUP BY user_id, active_date
)
SELECT
  c.cohort_date,
  count(DISTINCT c.user_id) AS cohort_size,
  count(DISTINCT IF(dateDiff('day', c.cohort_date, a.active_date) BETWEEN 0 AND 1, c.user_id, NULL)) AS d1,
  count(DISTINCT IF(dateDiff('day', c.cohort_date, a.active_date) BETWEEN 6 AND 8, c.user_id, NULL)) AS d7,
  count(DISTINCT IF(dateDiff('day', c.cohort_date, a.active_date) BETWEEN 13 AND 15, c.user_id, NULL)) AS d14,
  count(DISTINCT IF(dateDiff('day', c.cohort_date, a.active_date) BETWEEN 27 AND 33, c.user_id, NULL)) AS d30,
  count(DISTINCT IF(date_name = 'subscription_created' AND dateDiff('day', c.cohort_date, a.active_date) <= 30, c.user_id, NULL)) AS sub30
FROM cohorts c
LEFT JOIN activity a USING(user_id)
WHERE c.cohort_date >= today() - 60
GROUP BY c.cohort_date
ORDER BY c.cohort_date DESC;
```

### 3.5 目标基线 (Sprint 4 末)

| 指标 | 目标 | 行业参考 |
| --- | --- | --- |
| D1 留存 | ≥ 50% | 35-55% (电商) |
| D7 留存 | ≥ 30% | 20-35% |
| D30 留存 | ≥ 18% | 10-22% |
| D7→D14 衰减 | ≤ 25% | 健康基线 |
| 30 天订阅转化 | ≥ 8% | 4-10% (订阅电商) |
| 60 天二次购买 | ≥ 22% | 15-30% |

### 3.6 留存监控
- 周报：每 Cohort 周维度 D1/D7/D30
- 月报：滚动 12 周趋势
- 风险：D7 < 20% 持续 2 周 → 启动产品调研
- 群组对比：新版本 vs 老版本 / 不同注册渠道 / 不同问卷长度

---

## 4. 漏斗 F-REF-004：退款漏斗 (Refund Funnel)

### 4.1 业务目标
监控退款流程的健康度，识别运营/物流/商品质量风险。**财务合规** 必读。

### 4.2 步骤定义

| 步骤 | 事件 | 触发条件 | 备注 |
| --- | --- | --- | --- |
| **S1** | `order_cancelled` (refund_required=true) | 用户/客服发起退款 | 起点 |
| **S2** | 财务审核通过 | 内部事件 `refund_reviewed` (状态 review→approved) | 内部表 |
| **S3** | `refund_initiated` | 调用 Stripe Refund API | 1-3 工作日 |
| **S4** | `refund_succeeded` / `order_refunded` | Stripe `charge.refunded` webhook | 实际到账 |
| **S5** | 财务对账完成 | 内部 ETL 标记 `reconciled_at` | 月度对账 |

### 4.3 步骤间关键指标
- **退款率** = S1 / (order_paid 总量) —— 健康线 < 5%
- **审核通过率** = S2 / S1 —— 健康线 ≥ 90%
- **退款成功率** = S4 / S3 —— 健康线 ≥ 95%
- **退款时效中位数** = median(S3.at - S1.at) 小时数
- **实际到账时效** = median(S4.at - S3.at) 小时数
- **退款金额占比** = sum(refund_amount_hkd) / sum(order_paid total_hkd)

### 4.4 SQL 模板
```sql
SELECT
  toDate(o.occurred_at, 'Asia/Hong_Kong') AS biz_date,
  countIf(o.event_name = 'order_cancelled' AND o.refund_required = 1) AS s1,
  countIf(r.event_name = 'refund_reviewed' AND r.decision = 'approved') AS s2,
  countIf(r.event_name = 'refund_initiated') AS s3,
  countIf(r.event_name = 'refund_succeeded' OR o.event_name = 'order_refunded') AS s4,
  countIf(r.event_name = 'refund_reconciled') AS s5,
  round(s4 / nullIf(s1, 0), 4) AS success_rate,
  quantile(0.5)(dateDiff('hour', s1_at, s3_at)) AS p50_review_hours,
  quantile(0.5)(dateDiff('hour', s3_at, s4_at)) AS p50_refund_hours
FROM events_raw o
LEFT JOIN events_raw r ON o.order_id = r.order_id
WHERE o.event_name IN ('order_cancelled', 'order_refunded')
  AND o.occurred_at >= today() - 30
GROUP BY biz_date;
```

### 4.5 目标基线

| 指标 | 目标 | 警戒线 | 行动线 |
| --- | --- | --- | --- |
| 退款率 | < 3% | 3-5% | > 5% |
| 审核通过率 | ≥ 95% | 85-95% | < 85% |
| 退款成功率 | ≥ 98% | 95-98% | < 95% |
| 审核时效 P50 | ≤ 24h | 24-48h | > 48h |
| 实际到账 P50 | ≤ 72h | 72-120h | > 120h |
| 退款金额占比 | < 2.5% GMV | 2.5-4% | > 4% |

### 4.6 告警规则
- 单日退款率 > 5% → Slack `#ops-alert`
- 单 SKU 7d 退款率 > 8% → 运营下架告警
- 退款成功率 < 95% 持续 4h → Stripe 通道告警
- 审核时效 P50 > 48h → 客服 backlog 告警

---

## 5. 漏斗间的依赖与切片

### 5.1 主漏斗链路
```
F-ACT-001 (激活) ──> F-CON-002 (转化) ──> F-RET-003 (留存) ──> F-REF-004 (退款，负向)
```

- 激活漏斗的 S4/S5 是转化漏斗的潜在 S1（**不同时间窗**：7d vs 即时）
- 留存漏斗的二次购买率是 GMV 复购的核心
- 退款漏斗与转化漏斗 **互斥但相关**：高退款率 = 隐性低质量激活

### 5.2 跨漏斗衍生指标

| 指标 | 公式 | 用途 |
| --- | --- | --- |
| **LTV/CAC** | LTV 90d / (CAC = 营销 spend / 新增 register) | 商业健康度 |
| **净推荐 NPS** | (推荐者 % - 贬损者 %) × 100 | 留存补充 |
| **碳减排人均** | sum(carbon_saved_kg) / active_users_30d | ESG 报告 |
| **AI 菜单采纳率** | menu_helpful_voted.helpful / menu_viewed (按 user 去重) | AI 价值 |

### 5.3 BI 看板布局
- **首页 (Executive)**：4 漏斗总体转化率 + 异常高亮
- **激活页**：F-ACT-001 分步 + 渠道/问卷版本切片
- **转化页**：F-CON-002 每日趋势 + CVR 漏斗 + AOV
- **留存页**：F-RET-003 Cohort 矩阵 + 衰减曲线
- **退款页**：F-REF-004 流程时效 + Top 退款 SKU

---

## 6. 验收标准 (DoD)

- [ ] 4 个漏斗的 SQL 模板在 ClickHouse 跑通并出数
- [ ] Metabase / Superset 看板 v1 上线，每周自动更新
- [ ] 4 个漏斗的告警规则在 Alertmanager 配置
- [ ] 漏斗字段与 `data-events.md` v1.0 完全对齐
- [ ] Sprint 1 Week 2 第一次复盘会用此漏斗出数

---

## 7. 修订记录

| 版本 | 日期 | 修订人 | 说明 |
| --- | --- | --- | --- |
| v1.0 | 2026-06-12 | data-analyst-agent (hotel) | 初版，覆盖 Sprint 1-Day2 任务 P0-3 |

---

> **下一步**：与 devops-agent (echo) 对接 ClickHouse 表 DDL 与告警通道；与 dev-agent (golf) 确认 `refund_reviewed` 等内部事件是否需前端埋点。
