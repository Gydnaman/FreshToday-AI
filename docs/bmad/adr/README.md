# GreenBite ADR 索引（Architecture Decision Records）

> **元信息**：维护人 architect-agent (bravo) | 框架 fdd-bmad-custom | 起始 2026-06-12
> **目的**：沉淀 Sprint 0 评审指出的"为什么这样设计"决策，避免后续 PR 反复质疑基础架构

---

## 编号规则

- 4 位数字（`NNNN`），从 `0001` 起
- 必须连续；废弃的 ADR 加 `status: 废弃` 但**不删除**（保留历史）
- 状态：`已提议` → `已接受` → `已废弃` / `已替代`

## 索引

| 编号 | 标题 | 状态 | 日期 | 关联文档 |
|---|---|---|---|---|
| [ADR-0004](./0004-webhook-idempotency-and-signature.md) | Webhook 幂等与签名校验 | 已接受 | 2026-06-12 | `REVIEW-REPORT §3.1 NEW-P1-03`、`api-contract §2.8` |
| [ADR-0005](./0005-order-state-machine.md) | 订单状态机实现（7 态 SSOT） | 已接受 | 2026-06-12 | `REVIEW-REPORT §9.3 NEW-P1-01`、`order-state-machine.md` 附录 A |
| [ADR-0006](./0006-ai-menu-cache-and-fallback.md) | AI 菜单缓存、降级与限流 | 已接受 | 2026-06-12 | `REVIEW-REPORT §3.1 NEW-P1-03`、`AiMenuService.php` |

## Sprint 0 之前（历史背景）

| 编号 | 标题 | 备注 |
|---|---|---|
| ADR-0001 ~ 0003 | （尚未沉淀）| Sprint 0 阶段的架构决策（Stripe vs PayMe、Laravel 11 vs 12、MySQL vs Postgres 等）需要 Sprint 1 Week 2 补全 |

## 使用规范

- **任何 PR 修改架构核心组件**（Service / Controller / Migration / Enums）必须在 PR 描述中引用对应 ADR 编号
- **新增状态 / 新增支付网关 / 新增 AI 模型** 需先新建 ADR，再改代码
- **废弃 ADR 需新增 `已替代 #NNNN` 标记**，并在原文件保留废弃说明
- **状态变更流**：`已提议`（草案）→ `已接受`（合并后）→ `已废弃`（被新 ADR 替代）

## 评审触发

- 任何 P0 级别（`REVIEW-REPORT` 标记）必须落地为 ADR
- Sprint 1 期间每天 17:00 HKT 站会同步新增 ADR

## Day 3 起 PR Template 强制引用

```markdown
## ADR 引用
- [ ] 涉及架构变更（如 Service / Controller / Migration / Enum 改动）已引用对应 ADR-NNNN
- [ ] 新增状态 / 支付网关 / AI 模型已先建 ADR
```

---

*— 索引维护：architect-agent · 2026-06-12 16:30 HKT · fdd-bmad-custom Architect 阶段产物*
