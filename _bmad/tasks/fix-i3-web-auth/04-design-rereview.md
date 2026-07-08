# 再 Review — 修正后的设计文档 02-design.md

> **Reviewer**：adversarial-review
> **日期**：2026-07-03

## §1 修正核对

| Finding | 修正状态 |
|---|---|
| F-1（API 认证失败返 302） | ✅ §2.1 加 AuthenticationException render 判断 |
| F-2（logout 多 tab 行为） | ✅ §2.2 明确 invalidate + regenerateToken |
| F-3（CORS） | ✅ §2.1 加同源说明 + 未来分离部署注释 |
| F-4（guest cart） | ✅ §2.3 标注 guest localStorage 不变 |
| F-5（/api/login 响应格式） | ✅ §2.2 明确 `{"user": {...}}` 无 token |
| F-6（cart clear 责任） | ✅ §2.2 明确 CheckoutController 自调 cartItems()->delete() |

## §2 新发现

无重大问题。

**小注**：设计文档现在覆盖了后端配置、控制器、前端、测试四个层面，6 个 review finding 全部有对应修正。F-1 的 Critical 风险（statefulApi 导致 302）已有明确缓解方案。

## §3 判定

**Pass** — 设计文档可转 writing-plans。

## §4 下一步

按 Superpowers brainstorming skill，设计批准后转 writing-plans skill 写实施计划。计划将包含：
1. 启用 statefulApi + 修 AuthenticationException render
2. 改 AuthController（login/logout/me）
3. 改 CheckoutController（直接调 OrderService）
4. 改前端 gbFetch + auth.blade.php + checkout.blade.php
5. 改测试（actingAs 代替 PAT header）
6. 跑全量测试验证
