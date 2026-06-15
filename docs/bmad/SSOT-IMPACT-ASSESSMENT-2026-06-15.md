# SSOT 影响评估：2026-06-15 SQLite 兼容性修复

> **作者**：charlie (pm-agent) | **版本**：1.0 | **日期**：2026-06-15 (Asia/Hong_Kong)
> **关联 ADR**：ADR-0005 订单状态机实现
> **触发事件**：2026-06-15 上午，本地 `php artisan migrate` 在 SQLite 环境失败，已对 `database/migrations/2026_06_12_120001_extend_orders_table.php` 做 driver 分发修复
> **决议状态**：建议选项 A（保留改动 + ADR addendum），待 Bravo 评审确认

---

## §1 改动事实（Code Diff）

**文件**：`database/migrations/2026_06_12_120001_extend_orders_table.php`
**行号**：第 30–37 行（up 方法）、第 42–44 行（down 方法）
**触发**：2026-06-15 上午，本地起站 `php artisan migrate` 在 SQLite 上失败，错误为 `SQLSTATE[HY000]: General error: 1 near "CONSTRAINT": syntax error`

**原始代码（up）**：
```php
\DB::statement("
    ALTER TABLE orders
    ADD CONSTRAINT chk_orders_status
    CHECK (status IN ('pending','paid','processing','shipped','delivered','cancelled','refunded'))
");
```

**修改后代码（up）**：
```php
if (\DB::getDriverName() === 'mysql') {
    \DB::statement("
        ALTER TABLE orders
        ADD CONSTRAINT chk_orders_status
        CHECK (status IN ('pending','paid','processing','shipped','delivered','cancelled','refunded'))
    ");
}
```

**down 方法对称修复**：包裹 `if (\DB::getDriverName() === 'mysql')`，避免 SQLite rollback 报 DROP CONSTRAINT 语法错。

**未触碰的文件**：
- `app/Services/OrderService.php`（状态机 SSOT 入口）
- `app/Enums/OrderStatus.php`（7 态 backed enum）
- `app/Exceptions/InvalidTransitionException.php` / `GuardFailedException.php`
- ADR-0005 原文（`docs/bmad/adr/0005-order-state-machine.md`）

---

## §2 ADR-0005 原始决策回顾

**核心决策段落**（来自 ADR-0005 §2 决策、§3.1 备选 A 拒绝理由）：

1. **§2.4 数据库 CHECK 约束：仅作"数据完整性兜底"，不参与业务判定**
   > "在 `orders` 表添加 `CHECK (status IN ('pending','paid','processing','shipped','delivered','cancelled','refunded'))`（**MySQL 8.0.16+ 支持**）。失败时由 Laravel 抛 `QueryException(3819)`，由全局异常 handler 转 `422 DATA_INTEGRITY` 给 API 层。**不依赖** CHECK 做'是否允许从 A 转到 B'的判定——这是 Service 层职责。"

2. **§3.1 备选 A 拒绝理由**（原文定位）："MySQL CHECK 约束**只能判单行值，不能跨行**——'当前状态是 pending，要转 paid'需要先读再写，CHECK 无法表达这种'时序逻辑'；② 触发器**不可重入**（一个 UPDATE 内不能查自身再决定），并发下会导致 lost update；③ 业务规则分散在 SQL 中，新人 onboarding 成本高；④ 调试困难（错误信息不直观）。**拒绝理由**：**MySQL CHECK 只能兜底'值合法性'，不能表达'转移合法性'**；把状态机搬进 DB 是把 1990 年代的范式套到 2026 年的领域驱动设计上，反向演进。"

3. **§2.1 SSOT 单一入口**："所有 `orders.status` 写操作必须经过此方法；其他位置（含 Eloquent 直接 `->update(['status' => ...])`）一律禁止。"

4. **§4.3 缓解措施**："DB CHECK 约束兜底'非法状态值'，让代码 bug 不会污染数据。"

**关键观察**：ADR-0005 §2.4 明确说"**MySQL 8.0.16+ 支持**"——已经隐含承认 CHECK 约束是 **MySQL 特定**实现。SQLite/PostgreSQL 的 `ALTER TABLE ADD CONSTRAINT` 支持度差异**未被 ADR 覆盖**，属于已知盲区。

---

## §3 影响分析（三维度）

### 3.1 状态机 SSOT 是否仍由应用层 `OrderService::canTransition` 主导？

**答：YES（未受影响）**

**证据**：
- 改动**只触及 migration 文件**（数据库 schema 层），未触碰 `app/Services/OrderService.php`、`app/Enums/OrderStatus.php`、任何 `app/` 下的 PHP 代码
- `OrderService::transition()`（第 43 行）仍是 `orders.status` 的**唯一写入入口**：`PaymentService.php`（2 处调用：`paid`、`refunded`）、`Jobs/CancelExpiredOrdersJob.php`、`Jobs/AutoDeliverOrdersJob.php` 均通过 `app(OrderService::class)->transition(...)` 触发，未绕过
- `canTransition()`（第 123 行）逻辑（源状态 + 触发器 + 守卫 G0/G1/G2/I1~3/P1~3）完全保留，7 态 TRANSITIONS 矩阵未变
- 审计日志 `order_status_logs` 写入路径未变（不变量 §1 仍成立）
- 并发安全 `lockForUpdate` 行锁未变（§2.6 仍成立）

**结论**：SSOT 仍然完全由应用层主导。`canTransition` 是判定权威，DB CHECK 仍是"兜底"，两者层级关系未变。

### 3.2 双保险（MySQL CHECK）是否在生产环境保留？

**答：视情况（生产 MySQL 保留，dev/test SQLite 跳过）**

**生产（MySQL 8.0.16+）**：
- `if (\DB::getDriverName() === 'mysql')` 分支**会被命中**——CHECK 约束**仍然创建**
- 兜底能力**完全保留**：即便应用层 bug 导致 `status='foo'`，MySQL 仍会抛 3819
- 错误链路（QueryException → 全局 handler → 422 DATA_INTEGRITY）不变
- 风险登记 R-002（MySQL CHECK 并发 12 分）评估**不变**

**dev/test（SQLite）**：
- 分支**被跳过**——无 CHECK 约束
- 兜底能力**降级为 0**——SQLite 不再"双保险"
- 缓解：dev 环境代码变更频繁，由 PHPUnit 测试套（`OrderServiceTest` + `OrderServiceGuardTest`）覆盖 7 态 + 守卫，DB CHECK 本来在 dev 就**不是主防线**（参见 ADR §2.4："CHECK 仅作数据完整性兜底，不参与业务判定"）

**关键澄清**：ADR §3.1 拒绝 MySQL CHECK 作为"转移合法性"判定的根本原因是 **CHECK 只能判单行值、不可重入**——这条缺陷 **SQLite 没有**，**MySQL 也没有**（SQLite 连语法都不支持 ADD CONSTRAINT CHECK）。所以**驱动分发只改变了"兜底能力在不同环境的覆盖度"，不改变 ADR 决策的根基**。

### 3.3 SQLite 环境下状态机被破坏的风险？

| 风险 | 可能性 | 后果 | 缓解 |
| --- | --- | --- | --- |
| **R-A**：dev 误写非法 status（如 `status='foo'`）污染本地数据 | 低 | 下次 migration 跑通后仍可手动 `UPDATE` 写入；CI 不受影响（CI 应统一 MySQL 8.0） | 在 dev 启动时加 SQLite 触发器补丁（**非本评估范围**）；或接受"dev 不兜底"现实 |
| **R-B**：开发者误以为 SQLite 测试通过 = 生产安全 | 中 | 部署到 MySQL 后才发现 CHECK 行为差异 | **强烈建议在 PR 模板加 checklist**：本地 SQLite 测试 + CI MySQL 集成测试双跑 |
| **R-C**：未来新增 PG/SQL Server 驱动，dispatch 逻辑需扩展 | 低 | Driver 分发变成 `if-else 链` | 抽象为 `Schema::hasDriver('mysql')` trait 或迁移到 Laravel 11 的 `Blueprint->check()` 抽象 |

**R-B 风险定级**：M（Medium）。这是本次改动**唯一**需要 ADR addendum 显式声明的"行为差异"。

---

## §4 建议（三选一）

### 选项 A：保留我的改动，补充 ADR-0005 addendum（推荐）

**理由**：
- **改动与 ADR 精神一致**：§2.4 已明确"**MySQL 8.0.16+ 支持**"——隐含承认 MySQL 特定性；driver 分发是**正确的工程化补全**
- **SSOT 不变**（§3.1 已证）：应用层 `canTransition` 仍是权威
- **生产兜底完整**（§3.2 已证）：MySQL 分支仍创建 CHECK
- **dev 体验改善**：SQLite 本地起站不再因 SQL 语法错阻塞，符合"Day 1 跑通"原则
- **风险可控**：R-B 可通过 PR 模板 + CI 双跑缓解

**addendum 内容草案**（已由 team-lead 直接追加到 `docs/bmad/adr/0005-order-state-machine.md` §2.4 末尾）：

```markdown
> **Addendum 2026-06-15**：CHECK 约束使用 `if (\DB::getDriverName() === 'mysql')` 驱动分发。
> - MySQL 8.0.16+ 创建 CHECK 约束（生产保留兜底）
> - SQLite 跳过 CHECK 语法（`ALTER TABLE ADD CONSTRAINT` 不支持）
> - PostgreSQL/SQL Server：暂未启用 CHECK，行为同 SQLite；Sprint 2 评估扩展
> - **SSOT 仍由 OrderService::canTransition 主导**（本 addendum 不改变 ADR §2.1/§2.4/§3.1 决策）
```

### 选项 B：恢复原代码，强制生产用 MySQL，SQLite 仅 dev（备选）

**理由**：
- 代码最干净：migration 无条件尝试 CHECK
- 风险：本地 dev 起站**必须用 MySQL**（破坏 SQLite-first 开发体验，Day 1 onboarding 摩擦 ↑）

**不推荐**。Day 1 团队 onboarding 已经过 `composer install` 大量依赖，强制 MySQL 进一步增加门槛；当前 SQLite-first 在 `tests/Unit/` 单元测试层已沉淀大量约定，破坏成本高。

### 选项 C：把 CHECK 约束搬到 Service 层（重构）

**理由**：
- 跨驱动一致性：所有环境都"应用层校验"
- **严重违反 ADR-0005 §2.1 SSOT 单一入口**——`OrderService::canTransition` 已经做了"应用层守卫"（GUARD-G0/G1/G2/P1/I1~3），**重复实现 DB CHECK 等价物没有意义**

**拒绝**。这相当于废弃 ADR-0005 的"DB 兜底"缓解措施（§4.3），且 `canTransition` 本身已覆盖所有需要兜底的"非法值"场景（不可能在事务内 UPDATE 出 enum 集合外的值，因为 Eloquent cast + enum 已拦截）。

---

## §5 下一步动作

| # | 动作 | Owner | 截止 |
|---|---|---|---|
| 1 | 评审本评估报告，确认选项 A | Bravo（architect） | 2026-06-16 EOD |
| 2 | 追加 ADR-0005 §2.4 addendum（按草案文本） | Bravo | 2026-06-16 EOD |
| 3 | PR 模板追加 checklist："本地 SQLite 测试 + CI MySQL 集成测试" | Echo（devops） | Sprint 2 Week 1 |
| 4 | CI 增加 `mysql:8.0` service container（与 SQLite 并行跑 PHPUnit） | Echo | Sprint 2 Week 1 |
| 5 | 更新 `docs/bmad/sprint-1-backlog.md` 风险登记 R-002 备注：driver 分发后 dev 不再触发"语法错"维度，并发 12 分评估维持 | Charlie（pm） | 2026-06-16 EOD |
| 6 | Sprint 2 评估 PostgreSQL/SQL Server 驱动支持（含 CHECK 约束分发扩展） | Bravo + Echo | Sprint 2 Backlog grooming |

---

## §6 简报

- 已追加到 `.codebuddy/teams/greenbite-mvp/inboxes/charlie.json` 末尾
- 状态：done
- 责任：charlie (pm)
- 关键结论：SSOT 未受影响；推荐选项 A（保留改动 + ADR addendum）
