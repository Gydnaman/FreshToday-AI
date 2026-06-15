# GreenBite 评审日历 (Review Cadence)

> **文档编号**：GB-REV-CADENCE-001
> **维护人**：reviewer-agent (Foxtrot)
> **生效日期**：2026-06-12 (Sprint 1 Day 2 启动)
> **关联文档**：`docs/bmad/REVIEW-REPORT-v1.2.md` / `STATUS-UPDATE-2026-06-12-v1.1.md`
> **框架基线**：fdd-bmad-custom（Sprint 内多频次复评机制）

---

## 1. 一页式总览

| 频次 | 时点 | 形式 | 产出 | 主持人 | 时长 |
| --- | --- | --- | --- | --- | --- |
| **每日** | 09:00 HKT | 站会（Daily Standup） | 昨日完成 / 今日计划 / 阻塞 | team-lead | 15 min |
| **周三** | 14:00 HKT | Code Review（PR 集中评审） | PR 通过 / 退回 | bravo + reviewer | 60 min |
| **周五** | 16:00 HKT | Sprint Review（双周 / Sprint 末） | 演示 + 复评 | team-lead | 90 min |
| **Day 6** | 16:00 HKT | Sprint 复评（v1.0 / v1.1 / v1.2 / v1.3 ...） | REVIEW-REPORT 增量 | reviewer-agent | 90 min |
| **每月 1 号** | 10:00 HKT | ADR 归档评审 | 季度 ADR 索引 | bravo | 45 min |
| **季度** | Sprint 4 末 | 全量复评 + Quality Gate | v0.0 / v1.0 / v2.0 快照 | team-lead | 240 min |

---

## 2. 详细规则

### 2.1 每日站会（Daily Standup）— 09:00 HKT

**出席**：team-lead + bravo + charlie + delta + echo + golf + hotel + foxtrot

**议程（每人 ≤ 2 分钟）**：
1. 昨日完成（Yesterday）
2. 今日计划（Today）
3. 阻塞项（Blockers）

**输出**：`inbox/<agent>.json` 末尾追加 1 条 standup 记录

**特殊**：周五站会与 Sprint Review 合并（不重复召开）

### 2.2 周三 Code Review — 14:00 HKT

**对象**：当周合并至 `main` 的所有 PR

**形式**：
1. bravo（architect）走查架构合规性
2. foxtrot（reviewer）走查测试覆盖 + 文档同步
3. delta（qa）走查边界用例 + E2E
4. echo（devops）走查部署 + 监控影响

**判定**：
- LGTM（≥ 2 人同意）→ Merge
- Comment（需修改后重新评审）
- Block（架构 / 安全 / 数据风险）

**输出**：PR Approve / Request Changes

### 2.3 周五 Sprint Review — 16:00 HKT（双周）

**出席**：全员 + 利益相关方（产品 / 业务 / 投资人）

**议程**：
1. Sprint Goal 回顾（5 min）
2. Demo：可工作的增量（30 min）
3. Burn-down / Velocity（10 min）
4. 风险与决策（15 min）
5. 下 Sprint 预告（10 min）
6. Q&A（20 min）

**输出**：`docs/bmad/STATUS-UPDATE-<SprintN>-<Date>.md`

### 2.4 Day 6 复评（每次 Sprint 第 6 天 16:00）— REVIEW-REPORT 滚动

**触发**：每次 Sprint 第 6 天
**v1.0**（Sprint 0 Day 6）→ **v1.1**（Sprint 1 Day 2 触发）→ **v1.2**（Sprint 1 Day 2 触发）→ **v1.3**...

**产出**：`docs/bmad/REVIEW-REPORT-v<N>.<N+1>.md`

**打分维度**（5 维 10 分制）：
- 完整性 20% / 一致性 25% / 可执行性 25% / 专业性 15% / 本地化 15%

**判定**：
- ≥ 9.0 = **Pass**（可进入下一 Sprint）
- 7.0-8.9 = **Conditional Pass**（带 1-5 项 P0 必清）
- < 7.0 = **Fail**（回滚至上一 Sprint，重新派发）

### 2.5 每月 ADR 归档评审 — 每月 1 号 10:00 HKT

**对象**：当月新增的 `docs/bmad/adr/ADR-NNN-*.md`

**形式**：
1. bravo 主持，逐条过 ADR 决策
2. foxtrot 评审 ADR 质量（Context / Decision / Consequences）
3. 团队投票：Accept / Reject / Defer

**输出**：
- `docs/bmad/adr/INDEX.md`（季度 ADR 索引）
- `docs/bmad/adr/REVIEWED-<YYYY-MM>.md`（当月评审记录）

### 2.6 季度全量复评 — Sprint 4 / Sprint 8 末

**对象**：累计 ≈ 3 个月的所有交付物

**形式**：
1. v0.0 快照（季度初）
2. v1.0 快照（季度中）
3. v2.0 快照（季度末）
4. Quality Gate：Pass / Conditional Pass / Fail

**输出**：`docs/postmortem/Quarterly-Review-<YYYY-QN>.md`

---

## 3. 评审时间表（2026 Q2-Q3 滚动）

| 日期 | 评审类型 | 主题 | 主持人 |
| --- | --- | --- | --- |
| 2026-06-12 (Day 2) | Day 6 复评 v1.0 → v1.2 | Sprint 0 + Sprint 1 统一修复 | reviewer (Foxtrot) |
| 2026-06-13 (Day 3) | 周三 Code Review | Sprint 1 首批 PR | bravo + foxtrot |
| 2026-06-14 (Day 4) | 每日站会 | B2B 范围决议 | team-lead |
| 2026-06-19 (Day 6) | Sprint Review | Sprint 1 演示 + Velocity | team-lead |
| 2026-06-26 (Sprint 1 末) | Day 6 复评 v1.3 | Sprint 1 闭环 | reviewer |
| 2026-07-01 | 每月 ADR 归档 | Q2 ADR 评审 | bravo |
| 2026-07-10 (Sprint 2 Day 6) | Day 6 复评 v2.0 | Sprint 2 | reviewer |
| 2026-07-24 (Sprint 3 Day 6) | Day 6 复评 v2.1 | Sprint 3 | reviewer |
| 2026-08-07 (Sprint 4 Day 6) | 季度全量复评 v0.0/v1.0/v2.0 | Q3 | team-lead + reviewer |

---

## 4. 异常处理 (Escalation)

| 异常 | 响应时长 | 升级路径 |
| --- | --- | --- |
| **评审阻塞**（> 24h 未响应） | 24h | reviewer → team-lead → sponsor |
| **P0 安全问题** | 1h | reviewer → architect → CTO |
| **跨文档 SSOT 矛盾** | 4h | reviewer + architect 联合评审 |
| **Day 6 复评未通过** | Sprint 内 | 回滚至上一 Sprint，3 天内重启 |

---

## 5. 工具与产出位置

| 产出 | 路径 | 维护人 |
| --- | --- | --- |
| REVIEW-REPORT | `docs/bmad/REVIEW-REPORT-v<N>.<N+1>.md` | reviewer-agent |
| STATUS-UPDATE | `docs/bmad/STATUS-UPDATE-<SprintN>-<Date>.md` | team-lead |
| ADR 评审记录 | `docs/bmad/adr/REVIEWED-<YYYY-MM>.md` | bravo |
| Sprint Review | `docs/postmortem/Sprint<N>-Review-<Date>.md` | team-lead |
| 季度复评 | `docs/postmortem/Quarterly-Review-<YYYY-QN>.md` | team-lead + reviewer |
| 站会记录 | `.codebuddy/teams/<team>/inboxes/<agent>.json` | team-lead |
| 评审日历 | `docs/bmad/review-cadence.md`（本文件）| reviewer |

---

## 6. 与 8 Agent 团队协作约定

| Agent | 评审责任 |
| --- | --- |
| **bravo**（architect） | 周三 Code Review 主持 + ADR 归档 + 跨文档 SSOT 维护 |
| **charlie**（pm） | Sprint Review 业务演示 + 验收标准对齐 |
| **delta**（qa） | 周三 Code Review 测试 + E2E + 覆盖率 |
| **echo**（devops） | 周三 Code Review 部署 / 监控 + 季度灾备演练 |
| **foxtrot**（reviewer） | Day 6 复评签字 + 跨文档一致性 + 评审日历维护 |
| **golf**（dev） | 周三 Code Review 配合 + PR 提交 |
| **hotel**（data） | 埋点字典评审 + 数据驱动决策支持 |
| **team-lead** | 站会主持 + 异常升级 + Sprint Review 主持 |

---

*— 评审日历结束 —*
*Foxtrot · 2026-06-12 16:38 HKT · fdd-bmad-custom Quality Gate*
