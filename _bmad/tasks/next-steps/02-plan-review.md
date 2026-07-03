# BMad Review — 下一步实施计划 01-plan.md

> **Reviewer**：edge-case-hunter（边界条件视角）+ adversarial-review
> **日期**：2026-07-03
> **被评对象**：`_bmad/tasks/next-steps/01-plan.md`

---

## §1 Findings

### F-1 [Important] Task 1 的 .gitignore 规则会把 `_bmad/custom/` 也忽略掉

**问题**：计划 .gitignore 写了 `_bmad/core/`、`_bmad/fdd-custom/` 等，但没写 `_bmad/custom/`——说"保留跟踪"。但 `.gitignore` 的匹配规则是前缀匹配，`_bmad/core/` 只忽略 core 子目录，不影响 custom。这部分逻辑是对的。

但问题在于：计划说"保留 `_bmad/custom/`"，却没验证 custom 目录里是否有不该提交的文件（如 `config.user.toml` 是 personal/gitignored 的）。`_bmad/custom/config.user.toml` 第 4 行写着 "NOT committed (gitignored)"。

**影响**：如果直接 `git add _bmad/custom/`，会把 `config.user.toml`（个人配置）也提交进去。

**修复**：Task 1 Step 3 的 git add 应排除 `config.user.toml`，或在 .gitignore 加 `_bmad/custom/config.user.toml`。

---

### F-2 [Important] Task 4 的 RED 测试逻辑有误——cancelled 状态下 canBeRefunded() 会先抛异常

**问题**：测试代码把订单状态改成 `Cancelled`，然后调 `refund()`。但 `PaymentService::refund()` 第 131 行先检查 `$order->status->canBeRefunded()`——Cancelled 是终态，`canBeRefunded()` 返回 false，直接抛 `GuardFailedException(P2)`。**测试还没到 transition 那步就异常了**，payment 没被 update，断言 `assertNotEquals('refunded')` 会通过——但这是"假绿"。

**根因**：I-5 的真实场景是 transition **内部**失败（如 releaseStock 抛异常），不是 transition 前的 guard 失败。测试需要构造一个"guard 通过但 transition 内部失败"的场景。

**修复**：测试应模拟 transition 内部失败。方法：
- 用 Mockery 让 `OrderService::transition` 抛 `InvalidTransitionException`
- 或把订单状态改成 `Refunded`（已终态），这样 `canBeRefunded()` 可能返回 false... 需查 enum

实际上最简单的构造：让 `$payment->order` 返回的 order 与实际 order 不一致（模拟竞态），或用 Mockery。

建议测试改为：

```php
public function test_refund_rolls_back_payment_if_transition_fails(): void
{
    [$user, $order, $payment] = $this->createPaidOrder();

    // 模拟 transition 失败：并发场景下 order 已被另一个请求改成 Cancelled
    // 但 payment->order 的关系是懒加载，refresh 前拿到的是旧状态
    $order->update(['status' => OrderStatus::Cancelled]);
    $payment->refresh(); // 确保 payment->order 关系重新加载

    // canBeRefunded 对 Cancelled 应返回 false → GuardFailedException
    // 这个测试验证的是：guard 失败时 payment 不应被 update
    try {
        $this->paymentService->refund($payment, 100, 'test');
        $this->fail('Expected exception');
    } catch (GuardFailedException $e) {
        // 期望
    }

    $payment->refresh();
    $this->assertNotEquals('refunded', $payment->status);
}
```

但这测的是 guard 失败（transition 前），不是 transition 内部失败。要测真正的 I-5 场景（transition 内部失败导致 payment 回滚），需要 Mock OrderService。

**判定**：计划的测试逻辑需要修正。但修复方向（DB::transaction 包裹）是对的——事务会保护所有情况下的回滚，无论 guard 失败还是 transition 内部失败。

**建议**：简化测试——测"guard 失败时 payment 不变"（容易构造），再注一个注释说明"transition 内部失败的回滚由 DB::transaction 保证，不易构造集成测试，靠单元测试覆盖"。

---

### F-3 [Medium] Task 5 的 RED 测试可能不会 RED

**问题**：测试 `test_regenerate_counter_persists_ttl_across_calls` 调两次 regenerate，期望计数器=2。但旧代码：
1. 第一次 increment → 1，put(1, TTL)
2. 第二次 increment → 2，不 put

计数器值是 2，测试会 **直接 PASS**（假绿）。

旧代码的 bug 是"如果 key 在两次调用间过期，第二次 increment 创建新 key 返回 1"——但这需要时间旅行或 TTL 操控。在测试中不模拟 TTL 过期，bug 不会触发。

**修复**：测试需要模拟 TTL 过期。方法：
- 用 `Cache::forget($regenKey)` 模拟过期
- 或用 `Carbon::setTestNow(now()->addHours(25))` 模拟时间流逝

建议改为：

```php
public function test_regenerate_counter_resets_after_ttl_expiry(): void
{
    // 第一次调用
    try { $this->aiMenuService->regenerate($this->user); } catch (\Throwable $e) {}

    // 模拟 TTL 过期
    $regenKey = sprintf('ai_menu:regen:%d:%s', $this->user->id, now()->toDateString());
    Cache::forget($regenKey);

    // 第二次调用（TTL 过期后，应重新计数）
    try { $this->aiMenuService->regenerate($this->user); } catch (\Throwable $e) {}

    // 计数器应为 1（新的一天或 TTL 过期后重新计数）
    $count = (int) Cache::get($regenKey);
    $this->assertEquals(1, $count);
}
```

但这测的是"TTL 过期后重置"，不是"TTL 续期"。I-6 的真正问题是"TTL 未续期导致提前过期"——这更难测。

**判定**：I-6 的测试设计需要重新想。修复方向（总是 put）是对的，但测试验证方式需调整。

---

### F-4 [Low] Task 3 的 deprecation 头插在"第 1 行后"可能破坏 markdown 标题

**问题**：`DAY5-GAP-REPORT` 第 1 行是 `# Day 5 Gap Report — 2026-06-15`。在"第 1 行后插入"blockquote 会把标题和 deprecation 消息分开，视觉上 OK 但可能影响 markdown 渲染。

**修复**：在标题后加空行再插 blockquote。或直接改标题为 `# Day 5 Gap Report — 2026-06-15 [DEPRECATED]`。

---

### F-5 [Low] 计划没提 Task 4 的 createPaidOrder helper 是否存在

**问题**：Task 4 测试代码用 `$this->createPaidOrder()`，但没确认 PaymentServiceTest 是否有这个 helper。

**修复**：执行时如果不存在，内联创建 user + product + order + payment。

---

## §2 修正建议

| Finding | 严重度 | 修正 |
|---|---|---|
| F-1 | Important | .gitignore 加 `_bmad/custom/config.user.toml` |
| F-2 | Important | Task 4 测试改为测"guard 失败时 payment 不变"，注释说明 transition 内部失败靠事务保证 |
| F-3 | Medium | Task 5 测试改为模拟 TTL 过期后重新计数 |
| F-4 | Low | deprecation 标记改标题 |
| F-5 | Low | 执行时确认 helper |

---

## §3 判定

**Conditional Pass** — 计划方向正确，修复方向正确。但 F-1（提交个人配置）和 F-2（假绿测试）需执行前修正。
