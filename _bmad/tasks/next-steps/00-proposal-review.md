# BMad Review — "下一步建议"方案

> **Reviewer**：adversarial-review（愤世嫉俗视角）
> **日期**：2026-07-03
> **被评对象**：主 agent 提出的下一步建议（3 选项 + 推荐先做 1 再做 I-3）
> **方法**：逐条找漏洞、假假设、遗漏

---

## §0 被评方案摘要

主 agent 提出三个方向：
1. **提交未跟踪的方法论文件**（`_bmad/` + `superpowers/` + `.codebuddy/`），5 分钟
2. **修剩余 3 个 Important**：I-3（Web checkout PAT 暴露）、I-5（OrderService refund 状态已写）、I-6（AiMenu TTL 续期）
3. **推进 Sprint 2 功能**（PayMe/Alipay 接入、OpenAPI 自动生成、Mutation Testing）

推荐：先做 1，然后做 2 中的 I-3。

---

## §1 Findings

### F-1 [Critical] 方案 1 低估了复杂度——`_bmad/` 和 `superpowers/` 不是本项目原生产物

**问题**：方案说"提交未跟踪的方法论文件，5 分钟"。但：
- `_bmad/` 是 BMAD 框架安装产物（manifest.yaml 显示 `installDate: 2026-07-02`），含 40 个 TOML + core 模块 + fdd-custom 模块——这是**外部框架的安装副本**，不是项目代码
- `superpowers/` 是 Superpowers 仓库的克隆（README 显示是 obra/superpowers 项目），含 48 个文件 + tests + hooks——也是**外部仓库副本**
- 把这两个目录提交到项目 git，等于把第三方框架的完整副本塞进项目仓库，未来更新时会制造大量 noise diff

**影响**：
- 项目仓库膨胀（_bmad 65 文件 + superpowers 100+ 文件）
- 这两个目录的 .gitignore 策略从未讨论过
- `.codebuddy/` 目录按系统提示是"项目相关数据，NOT 临时缓存"，但其中 `memory/` 和 `teams/` 是否该提交需要用户决定

**修复**：方案 1 不能简单 `git add`。需要先决定：
- `_bmad/` 和 `superpowers/` 是加入 .gitignore（推荐——它们是工具，不是项目代码），还是提交（如果团队需要共享配置）？
- 如果提交，只提交 `custom/` 覆盖文件（项目特定约束），不提交 core/framework 文件
- `.codebuddy/memory/` 建议提交（跨会话上下文），`.codebuddy/teams/` 视情况

---

### F-2 [Important] 推荐优先做 I-3 缺乏依据——I-3 影响范围大但实际攻击面窄

**问题**：方案说"I-3 安全风险最高，优先做"。但 I-3 的实际风险取决于：
- Web checkout 页面是否已部署到生产？——项目还没部署（vendor 刚装上，连 .env 都是刚配的）
- 当前阶段是否有真实用户？——没有，demo 阶段
- XSS 攻击需要攻击者已能在页面注入脚本——这要求先有 XSS 漏洞

**对比 I-5**：
- I-5 是**数据完整性问题**——refund 失败时状态已写，财务对账偏差
- I-5 在任何环境都会触发（不需要攻击者），是**代码逻辑 bug**
- I-5 影响真实业务流程（退款），一旦有真实订单就会暴露

**影响**：优先级判断可能有误。I-3 是"潜在安全风险"（需 XSS 前提），I-5 是"确定逻辑 bug"（无前提条件）。

**修复**：重新评估优先级。建议：
- I-5 优先（确定 bug，影响数据完整性，改动范围可控——OrderService 单文件）
- I-3 其次（需认证模式重构，影响 CheckoutController + 前端，范围大）
- I-6 最后（小改，低风险）

---

### F-3 [Important] 方案 2 的三个 Important 都标"需先 brainstorming"，但没说 brainstorm 什么

**问题**：方案说 I-3/I-5/I-6"需先 brainstorming（设计方案）→ review → 改 → 再 review"。但：
- I-5 的修复方向已经在 REVIEW-REPORT-v1.2 §3 NEW-P2-10 明确："把 `$order->save()` 放在 `handleRefund` 成功之后"——这不是设计问题，是实现问题
- I-6 的修复方向也明确：用 `Cache::add` 替代 `put` 保证原子性——同样不是设计问题
- 只有 I-3 真正需要 brainstorming（认证模式选择：session vs SPA cookie vs 其他）

**影响**：给 I-5/I-6 套 brainstorming 流程是过度流程化，浪费时间。

**修复**：
- I-5/I-6 直接进 writing-plans → 执行（修复方向已明确）
- 只有 I-3 走完整 brainstorming

---

### F-4 [Medium] 方案完全忽略了 git push

**问题**：6 个修复 commit 都在本地 main，但从未 push 到 origin。方案 1 提"提交方法论文件"，但没提"把已有 6 个 commit push 上去"。

**影响**：如果本地仓库出问题（磁盘故障、误删），6 个修复全部丢失。

**修复**：方案应包含 `git push origin main`（或至少提醒用户）。

---

### F-5 [Medium] 方案 3（Sprint 2 功能）为时过早

**问题**：方案提"推进 Sprint 2 功能"，但：
- 项目刚修复完 Critical，还有 3 个 Important 未修
- 项目自评 9.21/10 的虚高问题虽已暴露，但 REVIEW-REPORT-v1.2 本身没更新/作废
- Day5 Gap Report 的 2/54 错误数字也没修正（只改了 README）

**影响**：在基础未稳的情况下推新功能，会重复"代码写了但没验证"的模式。

**修复**：方案 3 应明确排除，或标注"仅在 I-3/I-5/I-6 全部修复后启动"。

---

### F-6 [Low] 方案没提清理过时文档

**问题**：`docs/bmad/DAY5-GAP-REPORT-2026-06-15.md` 说 2/54 通过，`docs/bmad/REVIEW-REPORT-v1.2.md` 说 37 用例 8.95/10——这些文档现在已知是错的，但仍存在于 docs/ 中，会误导未来读者。

**影响**：文档 SSOT 被破坏——新人读 docs/ 会看到矛盾信息。

**修复**：在方案中加一步：给过时文档加 deprecation 头部，或更新关键数字。

---

## §2 修正后的方案

基于以上 6 个 finding，原方案修正为：

### 阶段 1：收尾当前修复（30 分钟）
1. **决定 `_bmad/` + `superpowers/` 的 git 策略**（F-1）——建议加入 .gitignore，只提交 custom 覆盖
2. **push 已有 6 个 commit 到 origin**（F-4）
3. **给过时文档加 deprecation 头**（F-6）——DAY5-GAP-REPORT + REVIEW-REPORT-v1.2

### 阶段 2：修 I-5 + I-6（1 小时，修复方向已明确，直接写计划）
- I-5：OrderService refund 事务调整（F-2/F-3）
- I-6：AiMenu Cache::add 原子性（F-3）

### 阶段 3：brainstorming I-3（需设计，单独走完整流程）
- Web checkout 认证模式重构（F-3）

### 排除
- Sprint 2 功能开发——在 I-3/I-5/I-6 全部修复前不启动（F-5）

---

## §3 判定

**Conditional Pass** — 方案方向正确但优先级判断有误（F-2），且低估了方案 1 的复杂度（F-1）。按修正后的 3 阶段方案执行。
