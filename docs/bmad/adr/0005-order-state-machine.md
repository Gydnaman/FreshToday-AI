> **元信息**：作者 architect-agent (bravo) | 版本 1.0 | 日期 2026-06-12 (Asia/Hong_Kong)
> **框架**：fdd-bmad-custom（Architect 阶段产物：Architecture Decision Record）
> **基线状态**：Day 2 立项，状态：已接受
> **评审触发**：`docs/bmad/REVIEW-REPORT.md` §9.3 NEW-P1-01（refund_required 散落状态）
> **关联文档**：`docs/bmad/order-state-machine.md` 附录 A（7 态 SSOT）、`tests/Unit/Services/OrderServiceTest.php`

# ADR-0005: 订单状态机实现（7 态 SSOT，OrderService 守卫）

## §1 背景（Context）

订单状态机是 GreenBite 核心领域逻辑。`docs/bmad/order-state-machine.md` 已定义了 7 个状态（`pending / paid / processing / shipped / delivered / cancelled / refunded`）及其合法转移矩阵。当前代码骨架已实现：

- 枚举类 `app/Enums/OrderStatus.php`（PHP 8.1 backed enum）
- Service 接口 `app/Services/OrderService.php::transition()` / `canTransition()` / `getAllowedTransitions()`
- 异常体系 `app/Exceptions/InvalidTransitionException.php` / `GuardFailedException.php`
- 完整测试 `tests/Unit/Services/OrderServiceTest.php`（覆盖 7 态 × happy path + 5 类非法转移）

但**"为什么状态机守卫放在 `OrderService::canTransition` 而不是数据库 CHECK 约束或 PHP 反射"**这一关键决策没有 ADR 文档化。后续 PR 容易被"用 MySQL CHECK 约束就够了"、"用 enum 反射自动生成守卫"等错误方案诱惑。

此外，REVIEW-REPORT §9.3 NEW-P1-01 提出的 `refund_required` 散落状态（webhook 晚到导致 `pending` 已 `cancelled` 后才收到支付成功事件）需要本 ADR 明确**不入 7 态 SSOT** 的决议。

业务侧需求：

- **状态转移必须可审计**：每次写 `orders.status` 必须伴随 `order_status_logs` 一行（含 `from / to / trigger / actor_type / timestamp / context`）
- **并发安全**：同一订单被两个 webhook 同时打到时，必须有一个胜出，另一个被 `InvalidTransitionException` 拒掉
- **业务可读性**：状态机图（Mermaid）必须与代码 1:1 对应；新增状态需要改 3 处（枚举 + Service + 状态机图）
- **HK 性能基线**：单次 `transition()` 调用 < 50ms（从 webhook 进入到落库完成）

## §2 决策（Decision）

我们采用 **`OrderService::canTransition()` 单一权威 + PHP backed enum + DB CHECK 仅作兜底** 的三段式实现：

1. **状态机权威：`app/Services/OrderService::transition()` 单一入口**
   - 所有 `orders.status` 写操作必须经过此方法；其他位置（含 Eloquent 直接 `->update(['status' => ...])`）一律禁止
   - 实施手段：code review checklist + 后续可用 PHPStan 静态分析检测（Day 5 评估）
   - 副作用：每次转移必写 `order_status_logs`（不变量 §1）

2. **判定核心：`canTransition(Order, OrderStatus, trigger): bool` 返回合法布尔**
   - 逻辑：(a) 当前状态是否在 §A 转移矩阵中作为源状态出现 (b) 触发器 `trigger` 字符串是否在合法触发器白名单 (c) 守卫条件（G0 归属 / G1 状态合法性 / G2 幂等性 / I 库存 / P 支付）是否全部通过
   - 守卫失败抛 `GuardFailedException`；状态不合法抛 `InvalidTransitionException`（两套异常分清"业务规则违反"和"非法调用"）

3. **状态值定义：PHP 8.1 backed enum `app/Enums/OrderStatus.php`**
   - 7 个 case 与 `order-state-machine.md` 附录 A 1:1 对应
   - `values()` 方法用于 Eloquent 的 `enum` cast 与 DB CHECK 约束同步
   - 状态机图（Mermaid）从 enum `cases()` 数组自动生成（**Sprint 2 工具**），避免手工维护漂移

4. **数据库 CHECK 约束：仅作"数据完整性兜底"，不参与业务判定**
   - 在 `orders` 表添加 `CHECK (status IN ('pending','paid','processing','shipped','delivered','cancelled','refunded'))`（MySQL 8.0.16+ 支持）
   - 失败时由 Laravel 抛 `QueryException(3819)`，由全局异常 handler 转 `422 DATA_INTEGRITY` 给 API 层
   - **不依赖** CHECK 做"是否允许从 A 转到 B"的判定——这是 Service 层职责

> **Addendum 2026-06-15**：CHECK 约束使用 `if (\DB::getDriverName() === 'mysql')` 驱动分发。
> - MySQL 8.0.16+ 创建 CHECK 约束（生产保留兜底）
> - SQLite 跳过 CHECK 语法（`ALTER TABLE ADD CONSTRAINT` 不支持）
> - PostgreSQL/SQL Server：暂未启用 CHECK，行为同 SQLite；Sprint 2 评估扩展
> - **SSOT 仍由 OrderService::canTransition 主导**（本 addendum 不改变 ADR §2.1/§2.4/§3.1 决策）
> - 详细评估：`docs/bmad/SSOT-IMPACT-ASSESSMENT-2026-06-15.md`

5. **`refund_required` 散落状态不入 7 态 SSOT**
   - 决议（见 `order-state-machine.md` 附录 A §NEW-P1-01）：`refund_required` 是 `pending→cancelled` 路径中 webhook 晚到导致的**内部 sentinel 状态**，由 `OrderStatusLog.context` JSON 字段 `{"refund_required": true}` 表达
   - 实现：`PaymentService::onChargeRefunded` 检测到该 sentinel 后，**异步**调用 `OrderService::transition(*→refunded)`
   - 不修改 7 态枚举；保持 SSOT 稳定

6. **并发安全：`SELECT ... FOR UPDATE` 行级锁**
   - `transition()` 在事务内先 `lockForUpdate()` 取订单行，**读最新状态**后再判定
   - 第二个并发请求会阻塞到第一个事务 commit，读取到的就是已变更的状态，`canTransition` 拒绝之
   - 这是 DB UNIQUE + 行锁的经典组合，**比乐观重试更可靠**（避免 ABA 问题）

## §3 备选方案（Alternatives Considered）

### 3.1 备选 A：MySQL `CHECK (status IN ...) + 触发器实现转移矩阵`（拒绝）

| 维度 | 评估 |
| --- | --- |
| **方案** | 在 `orders` 表加 7 个 `CHECK` 约束 + 写 `BEFORE UPDATE` 触发器判断合法转移 |
| **优势** | 数据层自包含；任何写入（即使是直 SQL）都被约束 |
| **劣势** | ① MySQL CHECK 约束**只能判单行值，不能跨行**——"当前状态是 pending，要转 paid"需要先读再写，CHECK 无法表达这种"时序逻辑"；② 触发器**不可重入**（一个 UPDATE 内不能查自身再决定），并发下会导致 lost update；③ 业务规则分散在 SQL 中，新人 onboarding 成本高；④ 调试困难（错误信息不直观） |
| **拒绝理由** | **MySQL CHECK 只能兜底"值合法性"，不能表达"转移合法性"**；把状态机搬进 DB 是把 1990 年代的范式套到 2026 年的领域驱动设计上，反向演进 |

### 3.2 备选 B：PHP Enum 反射 + Attribute 自动生成守卫（拒绝）

| 维度 | 评估 |
| --- | --- |
| **方案** | 用 PHP 8.1 backed enum + `#[Transition(from: Pending, to: Paid, trigger: 'payment_succeeded', guard: [...])]` 注解 + 反射自动注册转移矩阵 |
| **优势** | 代码即文档；`ReflectionClass::getAttributes()` 一次扫描完成；类型安全 |
| **劣势** | ① 反射**启动开销**——每次请求都扫描 enum 类（即使 OPcache 缓存也需 ~5ms）；② 守卫表达式序列化复杂（`guard: 'fn(order) => order.total > 0'`）；③ 调试栈变深（错误信息指向反射调用而非业务代码）；④ 与 `OrderService::canTransition` 这种"显式方法"相比，可读性反而下降（新人需要懂反射 + Attribute + Invocable 三件套） |
| **拒绝理由** | **反射是"过度聪明"的解法**；显式的 `match` 表达式或 `array<int, array{to: OrderStatus, triggers: array, guards: array}>` 配置更易读、易测、易调试。性能开销在 HK 10K DAU 量级不显著，但维护成本是真实的 |

### 3.3 备选 C：GraphQL 状态机引擎（如 `reinink/duplicate`，或自研）（拒绝）

| 维度 | 评估 |
| --- | --- |
| **方案** | 用专用状态机库（XState / 阿里有限状态机）生成 PHP 类 |
| **优势** | 形式化、可视化、状态图自动生成 |
| **劣势** | ① XState 是 JS 库，不能直接用；② PHP 领域没有成熟 FSM 库，自己造一个工作量 = ADR-0005 的 3 倍；③ 状态机的"价值"在于**业务可读性**，不是"形式化正确性"——过度形式化反而把领域专家挡在门外 |
| **拒绝理由** | **Sprint 1 阶段不值得为形式化付额外成本**；Mermaid 状态图 + 显式 PHP 数组已足够沟通。Sprint 3+ 若状态机复杂度爆炸（> 20 态）再考虑 |

### 3.4 备选 D：把状态机搬进 Stripe / 支付网关（拒绝）

| 维度 | 评估 |
| --- | --- |
| **方案** | 用 Stripe `PaymentIntent.status`（`requires_payment_method / requires_confirmation / processing / succeeded / canceled`）作为订单状态 |
| **优势** | 减少一处状态同步 |
| **劣势** | ① Stripe 状态机**只覆盖支付段**，不覆盖仓库 / 物流 / 售后；② 我们的 7 态 = Stripe 5 态 + 仓库 2 态（`processing` `shipped`），强行 1:1 映射会丢失业务语义；③ 多支付网关（Stripe + PayMe + FPS）状态机不一致，无法对齐 |
| **拒绝理由** | **业务状态必须独立于支付网关**；网关状态只是订单状态转移的**触发器**之一 |

## §4 后果（Consequences）

### 4.1 正面后果

- **可审计**：每次状态变更必写 `order_status_logs`；运维可 `SELECT * WHERE order_id=? ORDER BY created_at` 还原完整轨迹
- **类型安全**：PHP 8.1 backed enum 编译期就阻止 `OrderStatus::Foo` 之类的拼写错误
- **可视化**：Mermaid 状态图与 enum `cases()` 同步（Sprint 2 工具）；Sprint 1 阶段手工同步
- **并发安全**：`lockForUpdate` 行锁 + `canTransition` 守卫让两个并发 webhook 不会都成功
- **错误码分层**：`InvalidTransitionException`（422 BUSINESS_RULE，状态不合法）与 `GuardFailedException`（422 GUARD_FAILED，业务规则违反）分开，前端可针对不同异常做不同提示

### 4.2 负面后果 / 风险

- **行锁开销**：每个 `transition` 至少 2 次 SQL（SELECT FOR UPDATE + UPDATE）；HK 10K DAU 下峰值 webhook 流量 ~50 RPS，QPS < 100，无压力
- **人工修正状态困难**：运营后台"强制改状态"必须经过 `OrderService::transition(force: true)`，且必须写 `actor_type='admin'` 到日志（防止滥用）
- **`refund_required` sentinel 的可发现性差**：新人 grep 不到这个状态名，必须读 ADR 才能理解（缓解：在 `OrderStatusLog` 的 `context` JSON 中保留这个 key，加 README 说明）

### 4.3 缓解措施

- DB CHECK 约束兜底"非法状态值"，让代码 bug 不会污染数据
- `phpstan` 规则（Day 5 评估）禁止 `Order::query()->update(['status' => ...])` 直接写
- `OrderService::transition()` 写 `$this->log->create(...)` 强制审计日志
- `OrderStatusLog` 的 `context` JSON schema 在 `docs/bmad/er-diagram.md §2.13` 中维护

## §5 实施（Implementation）

### 5.1 核心文件（已落地）

- `app/Enums/OrderStatus.php` — 7 态 backed enum，含 `label()` / `isTerminal()` / `canBePaid()` / `canBeCancelled()` / `canBeRefunded()` / `values()` 6 个方法
- `app/Services/OrderService.php` — `transition()` / `canTransition()` / `getAllowedTransitions()` / `createOrder()` 4 个公共方法
- `app/Exceptions/InvalidTransitionException.php` — 非法状态转移
- `app/Exceptions/GuardFailedException.php` — 守卫失败（含 GUARD-G0 归属、GUARD-P1 支付、GUARD-I1~3 库存、GUARD-AI 问卷）

### 5.2 转移矩阵实现（`OrderService::canTransition` 内部）

```php
private const TRANSITIONS = [
    'pending' => [
        'paid'      => ['triggers' => ['payment_succeeded'], 'guards' => ['GUARD-P1', 'GUARD-P3']],
        'cancelled' => ['triggers' => ['user_cancel', 'timeout_cancel'], 'guards' => ['GUARD-G0']],
    ],
    'paid' => [
        'processing' => ['triggers' => ['admin_pick', 'auto_dispatch'], 'guards' => ['GUARD-I1']],
        'refunded'   => ['triggers' => ['admin_refund'], 'guards' => ['GUARD-P2']],
    ],
    // ... 共 7 态
];
```

### 5.3 状态机图（已在 `order-state-machine.md §1` 维护）

Mermaid `stateDiagram-v2` 与本 ADR 附录 A 1:1 对应。每次新增状态必须**先改 `order-state-machine.md` 附录 A**，再改 `OrderStatus` enum，再改 `TRANSITIONS` 常量——单向同步流。

### 5.4 测试覆盖

- `tests/Unit/Services/OrderServiceTest.php` — 7 态 happy path（7 test）+ 非法转移（5 test）
- `tests/Unit/Services/OrderServiceGuardTest.php` — 守卫 G0/G1/G2/I1~3/P1~3 全覆盖
- `tests/Unit/Services/OrderServiceIdempotencyTest.php` — 同一 webhook 重复 100 次仅一次状态变更
- `tests/Unit/Services/OrderServiceRefundTest.php` — `delivered→refunded` 终态回退

### 5.5 后续 PR（不在本 ADR 范围）

- **Sprint 1 Day 4**：DB CHECK 约束迁移 `database/migrations/2026_xx_xx_add_check_to_orders_status.php`
- **Sprint 1 Day 5**：PHPStan 自定义规则禁直接写 status
- **Sprint 2**：从 enum 自动生成 Mermaid 状态图工具
- **Sprint 3+**：评估 XState 形式化（仅在状态机复杂度爆炸时）

## §6 引用（References）

- **领域定义**：`docs/bmad/order-state-machine.md` 附录 A（7 态 SSOT 跨文档对照表 + 字段约束 + 合法转移）
- **状态机图**：`docs/bmad/order-state-machine.md` §1 Mermaid stateDiagram-v2
- **实现代码**：
  - `app/Enums/OrderStatus.php`（backed enum）
  - `app/Services/OrderService.php`（`transition` / `canTransition` / `getAllowedTransitions`）
  - `app/Exceptions/InvalidTransitionException.php` / `GuardFailedException.php`
  - `app/Models/OrderStatusLog.php`（审计日志）
- **数据库**：`database/migrations/2026_06_12_xxxx_create_orders_and_status_logs.php`（待 Day 4 加 CHECK 约束）
- **API 契约**：`docs/bmad/api-contract.md` §A.2（合法状态码引用本 ADR）
- **评审触发**：`docs/bmad/REVIEW-REPORT.md` §9.3 NEW-P1-01（`refund_required` 决议）
- **测试**：
  - `tests/Unit/Services/OrderServiceTest.php`（主测试，7 态 happy path）
  - `tests/Unit/Services/OrderServiceGuardTest.php`（守卫覆盖）
  - `tests/Unit/Services/OrderServiceIdempotencyTest.php`（幂等性）
  - `tests/Unit/Services/OrderServiceRefundTest.php`（退款路径）
- **关联 ADR**：
  - ADR-0004（Webhook 是状态机的主要触发器）
  - ADR-0006（AI 菜单的"过期 / 重新生成"也用类似模式，状态机思想可借鉴）
- **外部参考**：
  - Domain-Driven Design (Eric Evans) 第 5 章"Software States"
  - Martin Fowler "Patterns of Enterprise Application Architecture" — State Pattern
  - Laravel 官方文档 "Eloquent Mutators & Casting" enum 章节
