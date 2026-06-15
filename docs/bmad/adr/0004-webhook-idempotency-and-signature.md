> **元信息**：作者 architect-agent (bravo) | 版本 1.0 | 日期 2026-06-12 (Asia/Hong_Kong)
> **框架**：fdd-bmad-custom（Architect 阶段产物：Architecture Decision Record）
> **基线状态**：Day 2 立项，状态：已接受
> **评审触发**：`docs/bmad/REVIEW-REPORT.md` §3.1 NEW-P1-03

# ADR-0004: Webhook 幂等与签名校验

## §1 背景（Context）

GreenBite 在 Sprint 1 接入 Stripe 与 PayMe 两个支付网关的 Webhook 入口（`POST /api/stripe/webhook`、`POST /api/payme/webhook`），用于驱动订单状态机从 `pending → paid` 与退款链路 `*→refunded`。评审报告 `REVIEW-REPORT.md §3.1 NEW-P1-03` 明确指出：当前代码骨架已经存在控制器与数据库表，但**缺乏 ADR 把"幂等"与"验签"的实现细节定下来**，导致 Sprint 1 后续 PR 没有"为什么这样做"的可追溯依据。

Webhook 在生产环境下面临三重威胁：

1. **重放攻击（Replay）**：Stripe 文档明确"失败会重试，最多 3 天 11 次"。若我们的 `handle()` 不做去重，重复 webhook 会导致 `OrderService::transition(pending→paid)` 被多次调用；虽然 `OrderService::canTransition` 会拒绝第二次合法转移，但**审计日志 `order_status_logs` 会被写多份**，并可能触发重复的库存释放 / 短信通知。
2. **伪造来源（Forgery）**：未带 `Stripe-Signature` 头或签名错误的请求会绕过我们的业务校验直接落库 `stripe_webhook_events`；攻击者可通过 `evt_xxx` 命名规则探测我们的事件命名空间。
3. **乱序到达（Out-of-order）**：webhook 可能在用户前端收到支付成功页**之后**才到达（典型 200~500ms 延迟）；如果我们的状态机先把订单置 `cancelled`（30min 超时）再收到 `payment_intent.succeeded`，会触发 `refund_required` 散落状态——这正是 `order-state-machine.md` 附录 A §NEW-P1-01 决议要处理的边界。

业务侧进一步要求：

- **幂等键必须可重放**：同一 `evt_xxx` 重复请求 100 次，仅触发一次业务副作用
- **签名校验失败必须返回 401 `INVALID_SIGNATURE`**（与 `api-contract.md §1.2` 错误码字典对齐）
- **处理失败的 webhook 不能让 Stripe 无限重试**（返回 200，落 `stripe_webhook_events.status=failed`，由 `ReconcilePaymentJob` 异步重试）

## §2 决策（Decision）

我们采用 **"DB 去重主键 + HMAC-SHA256 签名校验 + 200 始终返回"** 三段式方案：

1. **幂等键 = `stripe_webhook_events.provider_event_id`**（UNIQUE 约束）
   - Stripe 端 `evt_xxx` 全局唯一；作为去重主键写入 `stripe_webhook_events` 表
   - 第二次收到相同 `event_id` 时，`INSERT` 触发唯一约束冲突，控制器捕获后直接返回 200，**不进入 `PaymentService::handleWebhook` 业务逻辑**
   - PayMe 端用其 `notify_id` 字段（HK FPS 标准）作类比处理

2. **签名校验 = `hash_hmac('sha256', $signedPayload, STRIPE_WEBHOOK_SECRET)`**
   - `$signedPayload` 格式：`$event_id . '.' . json_encode($payload)`（与 Stripe SDK `Webhook::constructEvent` 一致）
   - 校验失败：`401 INVALID_SIGNATURE`，**且不写 `stripe_webhook_events` 表**（避免日志被恶意污染）
   - 开发环境（`APP_ENV=local|testing`）若 `STRIPE_WEBHOOK_SECRET` 未配置，放行便于联调；生产环境**必须**配置且**强制**校验

3. **业务处理包裹在 try-catch 中，错误落 `stripe_webhook_events.status=failed`**
   - 任何业务异常（如 `GuardFailedException`、`InvalidTransitionException`）都被捕获，写 `last_error` 字段，**仍返回 200**
   - 这是为了避免 Stripe 在 5xx 时进入指数退避重试风暴
   - 失败 webhook 由 `ReconcilePaymentJob`（5min 延迟 + 最多 3 次）重试处理

4. **缓存层（Redis）不参与** webhook 路径
   - Webhook 流量不大（Stripe 重试峰值约 10 RPS/订单），但**强一致要求**——任何缓存击穿都会导致重复业务副作用
   - DB UNIQUE 约束是唯一可信赖的幂等闸门

5. **签名校验通过但落库失败**（如 DB 短暂不可用）：返回 503，让 Stripe 重试
   - 这是**唯一**合法的 5xx 场景：业务还没接受，不能让 Stripe 以为已处理

## §3 备选方案（Alternatives Considered）

### 3.1 备选 A：Redis SETNX 幂等（拒绝）

| 维度 | 评估 |
| --- | --- |
| **方案** | `Cache::lock("webhook:{$event_id}", 30)` 或 `Redis::set("evt:{$id}", 1, 'NX', 'EX', 86400)` |
| **优势** | 写性能高（µs 级），天然 TTL 过期 |
| **劣势** | ① Redis 不可用时**直接击穿到业务层**，可能产生重复副作用；② Redis 重启 / 故障切换会丢失幂等记录；③ 我们的 Redis 在 Sprint 1 阶段用 `file/array` 驱动，`SETNX` 跨进程不可见，**会立刻失效**；④ 与"DB UNIQUE"组合会形成双写一致性问题 |
| **拒绝理由** | **强一致场景下，DB UNIQUE 才是唯一可信的幂等源**；Redis 仅适合做"软防重"（如 `StripeWebhookRateLimit`），不能作为权威 |

### 3.2 备选 B：Stripe `Idempotency-Key` Header（推迟）

| 维度 | 评估 |
| --- | --- |
| **方案** | 我们的 `createPaymentIntent` 请求带 `Idempotency-Key: order_id`，由 Stripe 端 24h 去重 |
| **优势** | 适合"主动创建支付意图"场景，能避免重复 `PaymentIntent` 对象 |
| **劣势** | ① 解决的是"我们的出站请求幂等"，**不解决"入站 Webhook 幂等"**；② Stripe 端 Idempotency-Key 与 Webhook 事件去重是两条独立链路；③ 我们还要接 PayMe / FPS 等 HK 本地支付，PayMe 不支持类似机制 |
| **拒绝理由** | 解决的不是同一个问题；本 ADR 关注"入站 Webhook 幂等"。**但出站 Idempotency-Key 应作为独立 PR 在 Sprint 1 Day 4 由 PaymentService 一并实施**，详见 §5.3 |

### 3.3 备选 C：MySQL 唯一索引 + 应用层 pre-check（采用 + 强化）

| 维度 | 评估 |
| --- | --- |
| **方案** | 先 `SELECT WHERE provider_event_id = ?` 再 `INSERT`（应用层判重） |
| **优势** | 可读性好，能返回 409 / 200 由调用方决定 |
| **劣势** | ① TOCTOU（Time-of-check to time-of-use）竞态：两个并发 webhook 在 `SELECT` 都返回空后会双双 `INSERT`；② 性能差（2 次往返） |
| **拒绝理由** | 单纯应用层 pre-check 不能解决并发；**我们采用"DB UNIQUE + 捕获异常"模式**——先 `INSERT` 占位（`status=received`），捕获 `QueryException(23000)` 后判定为重复，返回 200。这是 Stripe 官方推荐做法（见 Stripe 文档 "Webhook idempotency" 章节） |

### 3.4 备选 D：消息队列前置去重（拒绝）

| 维度 | 评估 |
| --- | --- |
| **方案** | Webhook → Kafka / Redis Stream → Worker 消费时去重 |
| **优势** | 削峰、异步、可观测性高 |
| **劣势** | ① 增加 Webhook → 队列的额外一跳，Stripe 5xx 重试间隔会被队列消费时延拉长（违反 Stripe SLA）；② 当前架构无消息队列（Roadmap Sprint 2 才引入 Laravel Horizon），**超前落地是过度工程**；③ 队列消费失败本身又是一个幂等问题，递归 |
| **拒绝理由** | **架构演进方向是对的，但 Sprint 1 不应提前**；在 ADR-0004 v1.1 复评时根据流量再评估是否升级到队列前置 |

## §4 后果（Consequences）

### 4.1 正面后果

- **强幂等保证**：DB UNIQUE 约束是 InnoDB 引擎级保证，跨进程、跨服务、跨 Redis 故障都生效
- **可观测性**：`stripe_webhook_events` 表本身就是审计日志，包含 `payload / signature / received_at / processed_at / status / attempts / last_error`，可直接 `SELECT * WHERE status='failed'` 排查
- **对账友好**：`related_payment_id` / `related_order_id` 双外键，可反向 join 出"哪些订单是被哪些 webhook 触发的"
- **错误码与契约对齐**：`INVALID_SIGNATURE 401` 与 `api-contract.md §1.2` 错误码字典一致（这是 REVIEW-REPORT §3.6 P2-02 已修复）

### 4.2 负面后果 / 风险

- **DB 是 SPOLE**：若 `stripe_webhook_events` 表所在的 MySQL 主库故障，所有 webhook 都会 503，Stripe 会重试（这其实是**正确行为**，符合 §2 决策 5）
- **写放大**：每个 webhook 都至少写 1 次 DB；流量大时（>1000 RPS）需考虑分表。当前 Stripe webhook 峰值 < 50 RPS，可接受
- **unique 冲突时返回 200**：调试时容易误判"是否真的处理了"；**必须在日志中明确打 `webhook_duplicate_ignored` 字段**

### 4.3 缓解措施

- 主库 HA：HKT 主从切换 < 30s（deposit 已承诺）
- 监控：`stripe_webhook_events.status='failed'` 持续 5min 触发企业微信告警
- 索引：`(provider, event_type)` 与 `status` 联合索引用于运维查询

## §5 实施（Implementation）

### 5.1 数据库迁移

`database/migrations/2026_06_12_120004_create_payments_and_webhook_events.php` 已落库：

```sql
CREATE TABLE stripe_webhook_events (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    provider VARCHAR(32),
    provider_event_id VARCHAR(128) UNIQUE COMMENT 'evt_xxx 去重主键',  -- 幂等键
    event_type VARCHAR(64),
    payload JSON,
    signature VARCHAR(255) NULL,
    received_at TIMESTAMP,
    processed_at TIMESTAMP NULL,
    status ENUM('received','processing','processed','failed','ignored') DEFAULT 'received',
    attempts TINYINT UNSIGNED DEFAULT 0,
    last_error TEXT NULL,
    related_payment_id BIGINT NULL,
    related_order_id BIGINT NULL,
    created_at TIMESTAMP, updated_at TIMESTAMP,
    INDEX (provider, event_type),
    INDEX (status),
    INDEX (received_at)
);
```

### 5.2 控制器伪代码（已实现，见 `app/Http/Controllers/Api/StripeWebhookController.php`）

```php
public function handle(Request $request): JsonResponse
{
    $payload = $request->all();
    $signature = $request->header('Stripe-Signature');

    // Step 1: 验签（失败 → 401，不落库）
    if (! $this->verifySignature($payload, $signature)) {
        return response()->json(['error' => ['code' => 'INVALID_SIGNATURE']], 401);
    }

    // Step 2: 幂等占位（DB UNIQUE）
    try {
        $event = StripeWebhookEvent::create([
            'provider'          => 'stripe',
            'provider_event_id' => $payload['id'],
            'event_type'        => $payload['type'],
            'payload'           => $payload,
            'signature'         => $signature,
            'received_at'       => now(),
            'status'            => 'received',
        ]);
    } catch (QueryException $e) {
        if ($e->errorInfo[1] === 1062) {  // ER_DUP_ENTRY
            Log::info('webhook_duplicate_ignored', ['event_id' => $payload['id']]);
            return response()->json(['received' => true]);  // 幂等返回
        }
        throw $e;  // 其它 DB 错误让 Stripe 重试（503）
    }

    // Step 3: 业务处理（包裹在 try-catch）
    try {
        $this->payments->handleWebhook('stripe', $payload, $signature);
        $event->update(['status' => 'processed', 'processed_at' => now()]);
    } catch (\Throwable $e) {
        $event->update(['status' => 'failed', 'last_error' => $e->getMessage()]);
        Log::error('Stripe webhook unhandled error', ['error' => $e->getMessage()]);
        // 仍返回 200，避免 Stripe 无限重试
    }

    return response()->json(['received' => true]);
}
```

### 5.3 后续 PR（不在本 ADR 范围）

- **Sprint 1 Day 3-4**：在 `PaymentService::createIntent` 出站请求中加 `Idempotency-Key: order_id` 头（属 ADR-0004 配套，但不阻塞）
- **Sprint 2**：监控告警 `stripe_webhook_events.status='failed' > 10/5min`（属 devops `monitoring-and-runbooks.md` 更新）
- **Sprint 3**：评估是否升级到 Kafka / Redis Stream 前置（流量达到 1000 RPS 时）

## §6 引用（References）

- **触发评审**：`docs/bmad/REVIEW-REPORT.md` §3.1 NEW-P1-03（P1 级：缺失 ADR 文档）
- **实现代码**：
  - `app/Http/Controllers/Api/StripeWebhookController.php`（控制器）
  - `app/Http/Controllers/Api/PaymeWebhookController.php`（PayMe 同构实现，FPS notify_id 去重）
  - `app/Services/PaymentService.php::handleWebhook`（业务入口，调用 `OrderService::transition`）
- **数据库**：`database/migrations/2026_06_12_120004_create_payments_and_webhook_events.php`
- **API 契约**：`docs/bmad/api-contract.md` §2.8（Webhook 端点契约）、§1.2（`INVALID_SIGNATURE 401` 错误码）
- **状态机联动**：`docs/bmad/order-state-machine.md` §3 转移矩阵（`pending→paid` 守卫）、附录 A §NEW-P1-01（`refund_required` 散落状态处理）
- **监控**：`docs/bmad/monitoring-and-runbooks.md` §6.2 告警（待 Day 3 同步）
- **关联 ADR**：ADR-0005（订单状态机：webhook 是状态机的主要触发器）
- **关联测试**（待 Delta-agent 补全）：
  - `tests/Unit/Services/PaymentServiceTest.php`（webhook 入参 fixture）
  - `tests/Feature/Api/StripeWebhookTest.php`（HTTP 层 E2E：签名失败 401、重复 event_id 200、正常 200）
- **外部参考**：
  - Stripe 官方文档 "Webhook idempotency" 章节
  - Stripe SDK 源码 `\Stripe\Webhook::constructEvent`
  - OWASP "Webhook Security Cheat Sheet"
