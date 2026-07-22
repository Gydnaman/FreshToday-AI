# AI 菜单生产化加固 - 执行 Ledger

**Plan:** `docs/superpowers/plans/2026-07-20-ai-menu-production-hardening.md`
**Base commit:** 9dfb1295509eaf52c7b660434e183896e78caab5 (main)
**Baseline tests:** 94 passed / 378 assertions / 0 failed

## Progress

- Task 1: complete (commits 9dfb129..7287b9e, review clean — 3 Minor 风格项已记录待 final review)
  - Minor-1: MenuOutputValidator.php:108 `?? ''` 兜底为死代码（isset 已保证）
  - Minor-2: MenuOutputValidator.php:105 array_merge 循环分配可改 array_push 展开
  - Minor-3: 测试未覆盖 validateJson 的非法 type / 非数组 ingredients 分支

- Task 2: complete (commits 7287b9e..dded6cb, review clean — 3 Minor 项已记录待 final review)
  - Minor-4: PromptBuilder.php:110 `---` 移除后残留双空格，可加 preg_replace('/\s+/', ' ', ...) 压缩
  - Minor-5: PromptBuilder.php:113 行首角色前缀正则依赖"换行已先被替换"的前置步骤，防御链耦合紧
  - Minor-6: 测试断言与 brief 原文不一致（'No separator' vs 'No  separator'），建议改实现压缩空格后恢复 brief 断言

- Task 3: complete (commits dded6cb..2d4afc4, 1 Important fix + re-review clean)
  - Fix-1: OpenAI 测试补强 strict/name/additionalProperties 断言（Important，已修）
  - Minor-7: GeminiProvider.php:302 返回 text 未 trim，与 OpenAI/DeepSeek 不一致
  - Minor-8: DeepSeek 测试未覆盖"返回非菜单 JSON"场景（Task 4 Validator 兜底，边界正确）
  - 备注：brief 测试代码三个 fake 响应最外层缺 `]`（PHP 语法错误），implementer 逐字复制后修复

- Task 4: complete (commits 2d4afc4..a6de3dd, review clean — 3 Minor 项已记录待 final review)
  - Minor-9: AiMenuService.php:135 自由文本校验失败时 Log 未记录 availableProducts 上下文
  - Minor-10: AiMenuService.php:130-133 JSON 校验失败时无日志（静默 fallthrough）
  - Minor-11: MenuOutputValidator::validateJson 不检查 greeting/tip 的黑名单词（设计边界，仅查 ingredients 匹配商品）

- Task 5: complete (commits a6de3dd..a8a7e83, 2 Important fix + re-review clean)
  - Fix-1: getFailureRate 删掉 $windowSeconds 死参数（Important，已修）
  - Fix-2: TTL 窗口漂移改 Cache::add+increment 原子组合（Important，已修）
  - Fix-3: latencyMs 加未使用注释（Minor，已修）
  - Fix-4: HealthController 走 MetricsRecorder 抽象（Minor，已修）
  - 观察-1: Cache::add+increment 理论竞态存在但大幅改善，可接受
  - 观察-2: 类注释"1h 滑动窗口"措辞不准（实为固定窗口），但 docblock 已明确说明

- Task 6: complete (commits a8a7e83..4c730ef, 1 Critical fix + re-review clean)
  - Fix-1: upsertMenu 条件 fill menu_json，避免缓存命中清空（Critical，已修）
  - Fix-2: 新增 test_menu_json_is_preserved_on_cache_hit 回归测试（Important，已修）
  - 根因：brief 设计缺陷（缓存命中传 null + upsertMenu 先查后 fill 组合）
  - 教训：reviewer 的边界分析比 implementer 更深入，识别了"缓存与 DB 并存"主路径

- Task 7: complete (commits 4c730ef..d4faa1f, 2 Important fix + re-review clean)
  - Fix-1: CircuitBreaker::recordFailure 改读改写，修复 database store 下 increment 返回 false 导致熔断器静默失效（Important，已修）
  - Fix-2: FailoverProvider docblock 补充成功定义语义（Important，已修）
  - 根因：Cache::increment 跨 store 行为不一致（array store 初始化 vs database store 返回 false）
  - 教训：测试环境 array store 掩盖了生产 database store 的真实行为

---

## Final Whole-Branch Review (9dfb129..d4faa1f)

**Verdict: Ready to merge**

- Cross-Task Consistency: ✅ 全部对齐（8 组组件契约）
- Milestone Coverage: M1 ✅ / M2 ✅ / M3 ✅ / M4 ✅
- 必须修才能 merge: 无
- 技术债务（合并后处理）: Minor-1~11（风格/测试覆盖/可观测性增强）

### 架构风险（已确认，非阻塞）
1. FailoverProvider "content 非空即成功" vs AiMenuService 业务校验语义边界——建议文档化
2. 指标记录只统计"真实调 Provider"次数（缓存/DB 命中提前 return）——语义正确但需消费者理解口径
3. CircuitBreaker 与 MetricsRecorder Cache key 命名空间独立——暂无一致性问题
4. PHP 8.2 `new` in initializer——符合版本底线

### 建议合并后动作
- 在 plan 或 README 补一句 FailoverProvider 语义边界说明
- Minor-4/5/6 可统一通过 PromptBuilder 加 preg_replace('/\s+/',' ',...) 一次解决
