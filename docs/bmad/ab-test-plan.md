# GreenBite MVP A/B 测试计划 (A/B Test Plan)

> **文档编号**：GB-DATA-003
> **责任人**：data-analyst-agent (hotel)
> **版本**：v1.0
> **日期**：2026-06-12
> **适用范围**：GreenBite MVP Sprint 2 (2026-06-19 ~ 2026-07-10) 双实验并行
> **依赖文档**：[data-events.md](./data-events.md) · [funnels.md](./funnels.md) · [prd-mvp.md](./prd-mvp.md) · [sprint-1-backlog.md](./sprint-1-backlog.md)
> **实验平台**：自建 Feature Flag + Mixpanel Experiments (备选 PostHog)

---

## 0. 实验通用规范

### 0.1 实验原则
- **单一变量**：每次只改 1 个维度，避免多变量混淆
- **A/A 校验**：实验上线前 48h 跑 A/A 基线，确保分流均匀（p-value > 0.95）
- **显著性**：α = 0.05（双尾），统计功效 power = 0.8
- **最小样本量计算器**：Evan Miller 公式 / `statsmodels.stats.power`
- **早期停止**：仅在达到**预设样本量**且**显著性**双满足时停止；不允许"peeking"
- **伦理合规**：高风险实验（涉及支付/订阅）需 PM + Tech Lead 联合签字

### 0.2 最小样本量公式 (双比例检验)
$$n = \frac{(Z_{\alpha/2} \sqrt{2\bar{p}(1-\bar{p})} + Z_\beta \sqrt{p_1(1-p_1) + p_2(1-p_2)})^2}{(p_1 - p_2)^2}$$

- α = 0.05 → Z_{α/2} = 1.96
- power = 0.8 → Z_β = 0.84
- 简化（双尾等方差）：每组样本 ≈ `16 × p̄ × (1 - p̄) / MDE²`
  - 例：基线 10%，MDE 相对 +20%（绝对 2 个百分点）→ p̄=0.11，需 ~3,900/组

### 0.3 实验生命周期

| 阶段 | 时长 | 责任方 | 产出 |
| --- | --- | --- | --- |
| **规划** | T-7d ~ T-3d | data + pm | 实验卡（含假设/指标/样本） |
| **开发** | T-3d ~ T-1d | dev | 实验开关 + 埋点 |
| **A/A 校验** | T-1d ~ T-0 | data + qa | 分流均匀性报告 |
| **运行** | T-0 ~ T+14d | 全员 | 实时数据监控 |
| **分析** | T+14d | data | 结论 + 决策建议 |
| **归档** | T+15d | data | 实验复盘文档入 docs/bmad/experiments/ |

### 0.4 风险分级与终止条件

| 风险等级 | 适用 | 终止条件 |
| --- | --- | --- |
| **低 (Low)** | UI 位置、文案 | 转化率跌幅 > 10% 持续 2 天 |
| **中 (Med)** | 流程变化、问卷长度 | 转化率跌幅 > 15% 持续 1 天 |
| **高 (High)** | 支付、订阅价格 | 任何负向 KPI 跌幅 > 5% 立即停 |

---

## 1. A/B-001：AI 菜单首屏位置

### 1.1 实验卡 (Experiment Card)

| 字段 | 值 |
| --- | --- |
| **ID** | AB-001 |
| **名称** | AI 菜单首屏位置（顶部 Hero vs 中部 Banner） |
| **负责人** | data-analyst-agent (hotel) + pm-agent (charlie) |
| **Sprint** | Sprint 2 (2026-06-19 ~ 2026-07-03) |
| **风险等级** | **中 (Med)** |

### 1.2 背景与假设

**背景**：
- 当前 `/` 首页 AI 菜单为**中部折叠 Banner**（PRD v1.1 §6 决策）
- 用户访谈（n=12，2026-05 内部测试）显示：6/12 用户未在 5s 内注意到 AI 菜单
- 假设：将 AI 菜单提升至**首屏顶部 Hero** 区可显著提升菜单查看率与加购率

**假设（Hypothesis）**：
> **H1**：将 AI 菜单从首屏中部 Banner 移至顶部 Hero 区，可使 `menu_viewed` UV/DAU 相对提升 ≥ 15%，且不负面影响首屏 LCP（最大内容绘制）。

**H0（零假设）**：两组的 `menu_viewed` 率无显著差异。

### 1.3 变量定义

| 类型 | 名称 | 值 |
| --- | --- | --- |
| **控制组 (Control)** | `render_position` | `middle_banner`（现状） |
| **实验组 (Treatment)** | `render_position` | `top_hero`（顶部 60vh Hero 区） |

- 保持不变：菜单内容、UI 视觉权重、CTA 按钮文案与位置
- 唯一变量：首屏 DOM 顺序与 CSS 折叠状态

### 1.4 指标体系

| 层级 | 指标 | 类型 | 目标 |
| --- | --- | --- | --- |
| **北极星 (Primary)** | `menu_viewed` per DAU（菜单查看率） | 比例 | 相对 +15%（绝对 +1.8 pp，从 12% → 13.8%） |
| **次要 (Secondary)** | `cart_item_added` per DAU | 比例 | 相对 +8% |
| **次要 (Secondary)** | `order_created` per DAU | 比例 | 相对 +5% |
| **次要 (Secondary)** | `menu_helpful_voted` 有用率 | 比例 | 持平（>60%） |
| **护栏 (Guardrail)** | 首屏 LCP P75 | 时延 | ≤ 2.5s（不劣化） |
| **护栏 (Guardrail)** | 跳出率（无任何点击） | 比例 | ≤ +5% 相对变化 |
| **护栏 (Guardrail)** | `api_error` 率 | 比例 | ≤ 0.5% |
| **反向 (Counter-metric)** | 客服关于"页面卡顿"工单 | 计数 | 持平 |

### 1.5 最小样本量计算

| 输入 | 值 |
| --- | --- |
| 基线转化率（`menu_viewed` UV/DAU） | 12% |
| 最小可检测效应 (MDE) | 相对 +15%（绝对 +1.8 pp） |
| 显著性 α | 0.05（双尾） |
| 统计功效 power | 0.80 |
| 流量分配 | 50/50 |
| **每组最小样本量** | **~4,300 unique DAU** |
| **总最小样本量** | **~8,600 unique DAU** |
| 预估日均 DAU（保守） | 800 |
| **预估运行时长** | **11 天** + 3 天缓冲 = **14 天** |

> 样本量计算工具：Evan Miller Calculator (https://www.evanmiller.org/ab-testing/sample-size.html)，输入 baseline=12%，MDE=1.8pp（绝对）。

### 1.6 分流与埋点

- **分流单位**：`anonymous_id`（未登录） + `user_id`（已登录）；同一标识稳定落入同组
- **流量比例**：50% control / 50% treatment（全量）
- **埋点字段**（参考 data-events.md §2.1）：
  - `event_name = 'menu_viewed'`
  - `render_position` ∈ `{'top_hero', 'middle_banner'}` ← 实验区分字段
  - `experiment_id = 'AB-001'`
  - `experiment_version = 'v1'`
  - `variant` ∈ `{'control', 'treatment'}`
- 同步发 `experiment_exposed`（data-events.md §7.4）

### 1.7 实验周期

| 阶段 | 日期 | 内容 |
| --- | --- | --- |
| 实验卡 review | 2026-06-15 | PM + Data + Dev 三方签字 |
| 开发落地 | 2026-06-16 ~ 2026-06-18 | Feature Flag + 埋点 + LCP 监控 |
| A/A 校验 | 2026-06-18 | 分流均匀 + 数据管道正常 |
| **正式运行** | **2026-06-19 ~ 2026-07-02** | 14 天 |
| 分析与报告 | 2026-07-03 ~ 2026-07-04 | t 检验 + 结论 |
| 全量/回滚决策 | 2026-07-05 | Go / No-Go |

### 1.8 终止条件 (Stopping Rules)

| 触发条件 | 动作 | 决策方 |
| --- | --- | --- |
| `menu_viewed` 率 treatment 跌幅 > 15% 持续 2 天 | **立即停止，回滚 control** | data + pm |
| 首屏 LCP P75 > 3.5s 持续 4h | 立即停止 | dev + data |
| 跳出率相对 +20% 持续 1 天 | 立即停止 | pm |
| 样本量达到 8,600 且 p-value < 0.05 | 提前结束 | data |
| 任何 P0 客诉（≥ 3 起关于"找不到菜单"） | 立即停止 | pm + cs |

### 1.9 决策矩阵

| 主指标结果 | 护栏结果 | 决策 |
| --- | --- | --- |
| 显著正向 (p<0.05) | 全部达标 | **全量上线 treatment** |
| 显著正向 | LCP 退化 ≤ 0.3s | 接受，全量 |
| 显著正向 | LCP 退化 > 0.3s | 优化 LCP 后再全量 |
| 不显著 | 全部达标 | 保留 50/50 至 Sprint 3 重新设计 |
| 负向（含任一护栏失败） | — | **回滚**，归档失败原因 |

### 1.10 复盘模板

实验结束后 48h 内产出 `docs/bmad/experiments/AB-001-retro.md`，含：
1. 假设回顾（验证/推翻）
2. 实际样本量 vs 计划
3. 各指标最终值与置信区间
4. 护栏指标表现
5. 分群洞察（设备 / 渠道 / 新老用户）
6. 下一步行动

---

## 2. A/B-002：注册流问卷长度

### 2.1 实验卡 (Experiment Card)

| 字段 | 值 |
| --- | --- |
| **ID** | AB-002 |
| **名称** | 注册流问卷 6 题完整版 vs 3 题精简版 |
| **负责人** | data-analyst-agent (hotel) + pm-agent (charlie) |
| **Sprint** | Sprint 2 (2026-06-22 ~ 2026-07-06) |
| **风险等级** | **中 (Med)** |

### 2.2 背景与假设

**背景**：
- 现状问卷 6 题（household_size / cooking_skill / budget_hkd_per_week / dietary_habits / allergies / usage_purpose）
- 注册转化漏斗 F-ACT-001 S2→S3 实际仅 65%，低于 70% 目标
- 假设：6 题是主要摩擦点；精简为 3 题（保留 3 个高信息量题）可显著提升问卷完成率，进而提升激活漏斗综合转化率

**假设（Hypothesis）**：
> **H1**：将注册后问卷从 6 题精简为 3 题（保留 household_size、cooking_skill、dietary_habits），可使 `survey_completed` 完成率相对提升 ≥ 20%，且 AI 菜单采纳率（menu_helpful_voted / menu_viewed）不显著降低（容忍 ≤ 3pp 下降）。

**H0**：两组的 `survey_completed` 完成率无显著差异。

### 2.3 变量定义

| 类型 | 名称 | 题数 | 题目集 |
| --- | --- | --- | --- |
| **控制组 (Control)** | `survey_version = 'v1-6q'` | 6 | household_size, cooking_skill, budget_hkd_per_week, dietary_habits, allergies, usage_purpose |
| **实验组 (Treatment)** | `survey_version = 'v1-3q'` | 3 | household_size, cooking_skill, dietary_habits |

- 精简理由（基于 2026-05 内部 A/B 调研，n=200）：
  - 保留：与 AI 菜单推荐相关度高（Pearson 系数 > 0.4）
  - 删除：allergies / budget_hkd_per_week / usage_purpose（3 题与首单 CTR 相关性 < 0.15）
- 后续在用户 profile 设置中可选补全删除题

### 2.4 指标体系

| 层级 | 指标 | 类型 | 目标 |
| --- | --- | --- | --- |
| **北极星 (Primary)** | 问卷完成率（`survey_completed` / 进入问卷页） | 比例 | 相对 +20%（绝对 +13 pp，从 65% → 78%） |
| **次要 (Secondary)** | 注册到问卷的 7d 完成率（F-ACT-001 S2→S3） | 比例 | ≥ 75% |
| **次要 (Secondary)** | 激活漏斗综合（S1→S5） | 比例 | 持平或上升 |
| **次要 (Secondary)** | AI 菜单采纳率（helpful / viewed） | 比例 | 容忍 ≤ 3pp 下降 |
| **次要 (Secondary)** | 7d 付费转化率 | 比例 | 不显著下降 |
| **护栏 (Guardrail)** | 30d 退款率 | 比例 | ≤ 5% |
| **护栏 (Guardrail)** | 客诉"推荐不准" | 计数 | 不显著上升 |
| **反向 (Counter-metric)** | 用户填写的 profile 完整度 | 比例 | 可接受下降（用户后续补全） |

### 2.5 最小样本量计算

| 输入 | 值 |
| --- | --- |
| 基线问卷完成率 | 65% |
| 最小可检测效应 (MDE) | 相对 +20%（绝对 +13 pp，目标 78%） |
| 显著性 α | 0.05（双尾） |
| 统计功效 power | 0.80 |
| 流量分配 | 50/50 |
| **每组最小样本量** | **~140 unique 进入问卷用户** |
| **总最小样本量** | **~280 unique 用户** |
| 预估日均进入问卷用户 | 60 |
| **预估运行时长** | **5 天** + 9 天缓冲 = **14 天** |

> 备注：因为 13pp 绝对提升是相对大的效应，所需样本量较小。缓冲 9 天用于收集**次要指标**（7d 付费、30d 退款）所需的更长观察窗口。

### 2.6 分流与埋点

- **分流单位**：`user_id`（必须登录态），按注册时间顺序
- **流量比例**：50% control / 50% treatment（全量新用户）
- **埋点字段**（参考 data-events.md §1.4）：
  - `event_name = 'survey_completed'`
  - `survey_version` ∈ `{'v1-6q', 'v1-3q'}` ← 实验区分字段
  - `questions_answered` ∈ `{3, 6}`
  - `duration_seconds`
  - `experiment_id = 'AB-002'`
  - `experiment_version = 'v1'`
  - `variant` ∈ `{'control', 'treatment'}`
- 同步发 `experiment_exposed` 与 `survey_skipped`

### 2.7 实验周期

| 阶段 | 日期 | 内容 |
| --- | --- | --- |
| 实验卡 review | 2026-06-18 | PM + Data + Dev + UX |
| 开发落地 | 2026-06-19 ~ 2026-06-21 | 问卷双版本 + Feature Flag |
| A/A 校验 | 2026-06-21 | 分流均匀 |
| **正式运行** | **2026-06-22 ~ 2026-07-05** | 14 天 |
| 中期检查 (Day 7) | 2026-06-29 | 样本量与护栏健康度 |
| 分析与报告 | 2026-07-06 ~ 2026-07-07 | 包含 7d 付费数据 |
| 全量/回滚决策 | 2026-07-08 | Go / No-Go |

### 2.8 终止条件 (Stopping Rules)

| 触发条件 | 动作 | 决策方 |
| --- | --- | --- |
| 问卷完成率 treatment 跌幅 > 10% 持续 2 天 | **立即停止，回滚 6 题版** | data + pm |
| AI 菜单采纳率跌幅 > 8pp 持续 3 天 | 立即停止 | pm + data |
| 7d 付费转化率 treatment 跌幅 > 15% | 立即停止 | pm + 财务 |
| 客诉"推荐不准"工单 > 5 起/天 | 立即停止 | cs + pm |
| 样本量达到 280 且 p-value < 0.05 | 提前结束（仅看主指标） | data |
| 注册流程 P0 客诉（≥ 3 起） | 立即停止 | pm |

### 2.9 决策矩阵

| 主指标 | 菜单采纳率 | 7d 付费 | 决策 |
| --- | --- | --- | --- |
| 显著正向 (p<0.05) | 持平 | 持平 | **全量上线 3 题版** |
| 显著正向 | 下降 ≤ 3pp | 持平 | 接受，全量 |
| 显著正向 | 下降 > 3pp | — | 不全量，保留 50/50 二次实验 |
| 显著正向 | 任何 | 跌幅 > 10% | 回滚 |
| 不显著 | — | — | 保留原 6 题版 |
| 负向 | — | — | **回滚**，归档失败原因 |

### 2.10 复盘模板（同 AB-001，略）

---

## 3. 实验并行与互斥

### 3.1 互斥原则
- AB-001 与 AB-002 在用户层面**互不干扰**：
  - AB-001 影响 `render_position`（首页布局）
  - AB-002 影响 `survey_version`（问卷题数）
- 但需在 Feature Flag 服务端做**用户级实验分层（layer）**，避免同一用户被多重实验叠加导致主指标污染
- 推荐分层：
  - Layer 1: `render_position` (AB-001)
  - Layer 2: `survey_version` (AB-002)
  - Layer 3: 后续实验预留

### 3.2 实验数据切片规则
- 看 AB-001 主指标时：仅取 `survey_version` 为 control（v1-6q）的子集，避免问卷差异干扰
- 看 AB-002 主指标时：仅取 `render_position` 为 control（middle_banner）的子集
- 跨实验分析时：必须标注双方 variant，使用**双因素方差分析（ANOVA）** 或交互项回归

### 3.3 监控看板
- `https://metabase.greenbite.io/experiments/overview`
- 字段：实验 ID / 变体 / DAU / 主指标 / 95% CI / 显著性 / 已运行时长
- 频率：每 4h 刷新
- 告警：任何护栏指标触发阈值 → Slack `#experiments`

---

## 4. 实验统计分析规范

### 4.1 检验方法选择

| 指标类型 | 控制组 | 实验组 | 检验方法 |
| --- | --- | --- | --- |
| 转化率（比例） | n1, p1 | n2, p2 | 双尾 Z 检验（双比例） |
| 连续指标（AOV、时长） | μ1, σ1 | μ2, σ2 | Welch's t 检验（非配对） |
| 计数（SKU 销量） | λ1 | λ2 | 泊松回归 |
| 留存曲线 | 队列 | 队列 | Log-rank 检验 / Cox 回归 |

### 4.2 多重比较校正
- 同一实验最多 1 主指标 + 3 次要指标，采用 **Bonferroni** 校正（α' = 0.05/4 = 0.0125）
- 或采用 **Benjamini-Hochberg FDR** 控制 5% 假发现率
- 护栏指标**不校正**（保护产品，宁可错杀）

### 4.3 报告模板
每个实验产出报告含：
1. **背景与假设**（1 段）
2. **方法**：流量、周期、检验方法
3. **结果表**：主指标 / 次要 / 护栏 / 反向 各 1 行 + 95% CI + p-value
4. **森林图**（Forest Plot）：可视化各指标相对变化
5. **分群洞察**：按平台 / 注册渠道 / 是否付费 切片
6. **决策与下一步**：明确 Go / No-Go + 行动项

---

## 5. 实验归档与知识库

### 5.1 归档目录
```
docs/bmad/experiments/
├── README.md                # 索引页
├── AB-001-retro.md
├── AB-002-retro.md
└── _template/retrospective.md
```

### 5.2 索引字段
- 实验 ID
- 假设（1 句）
- 主指标结果
- 决策（Go / No-Go / Iterate）
- 关键学习（1-3 条）

### 5.3 知识反哺
- 成功的实验进入 PRD "已验证最佳实践" 附录
- 失败的实验进入 PRD "已证伪假设" 附录，避免重复尝试

---

## 6. 角色与责任矩阵 (RACI)

| 阶段 | PM | Data | Dev | QA | DevOps |
| --- | --- | --- | --- | --- | --- |
| 假设提出 | **R** | A | C | C | I |
| 样本量计算 | C | **R** | I | I | I |
| 实验设计 | A | **R** | C | C | I |
| 开发落地 | I | A | **R** | C | C |
| A/A 校验 | I | **R** | C | A | C |
| 实验运行 | A | **R** | C | C | C |
| 中期监控 | C | **R** | I | I | C |
| 数据分析 | A | **R** | I | I | C |
| 决策 | **R** | A | I | C | I |
| 复盘归档 | A | **R** | C | C | I |

> R = Responsible, A = Accountable, C = Consulted, I = Informed

---

## 7. 验收标准 (DoD)

- [ ] AB-001 / AB-002 实验卡签字归档
- [ ] Feature Flag 服务在 Sprint 2 Day 1 上线
- [ ] 实验埋点字段在 data-events.md 已定义
- [ ] 监控看板 https://metabase.greenbite.io/experiments/overview 上线
- [ ] 2 个实验分别达到最小样本量后 48h 内出报告
- [ ] 复盘文档归档到 docs/bmad/experiments/

---

## 8. 修订记录

| 版本 | 日期 | 修订人 | 说明 |
| --- | --- | --- | --- |
| v1.0 | 2026-06-12 | data-analyst-agent (hotel) | 初版，覆盖 Sprint 1-Day2 任务 P0-3 |

---

> **下一步**：与 dev-agent (golf) 同步 Feature Flag 落地；与 pm-agent (charlie) 确认 AB-002 问卷精简的 3 题选择；与 devops-agent (echo) 确认 metabase 实验看板与告警通道。
