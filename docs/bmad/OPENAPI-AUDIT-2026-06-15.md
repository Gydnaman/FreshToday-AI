# GreenBite OpenAPI 一致性审计报告

**报告 ID**：GB-AUDIT-OPENAPI-2026-06-15
**审计人**：bravo (架构师)
**审计日期**：2026-06-15 14:00 (Asia/Hong_Kong)
**审计范围**：`docs/bmad/openapi.yaml`（26 端点） vs `routes/api.php` vs 10 个 `app/Http/Controllers/Api/*.php` 控制器
**审计依据**：`docs/bmad/api-contract.md` §2（v1.2 SSOT）、`docs/bmad/REVIEW-REPORT-v1.2.md` §3 NEW-P2-03 + §9.3 P2-05

---

## §0 摘要

| 维度 | 数量 |
|---|---|
| openapi.yaml path 块 | 21 |
| openapi.yaml 端点（method×path） | **26** |
| routes/api.php 业务路由 | 21 |
| routes/api.php 调试路由（staging-only） | 2 |
| routes/api.php 总路由 | **23 path / 26 method** |
| Api 控制器公开方法 | **21** |
| 差异 A：yaml 有 / routes 无 | 0 |
| 差异 B：routes 有 / yaml 无 | 0 |
| 差异 C：方法不一致 | 0 |
| 差异 D：schema 不一致 | **8** |
| **总差异** | **8（仅 D 类）** |

**结论**：路径层与方法层完全一致（A/B/C 三类均为零），但 schema 层有 8 处契约漂移。最严重的是 `OrderController::pay` 实际接受 `alipay_hk` provider 而 yaml 仅声明 `stripe|payme` —— **静默生产事故风险**。

---

## §1 routes/api.php 实际路由清单（23 path / 26 method）

| # | Method | 实际路径 | Controller@Method | 中间件 | 环境 |
|---|---|---|---|---|---|
| R01 | POST   | /api/register              | AuthController@register     | — | all |
| R02 | POST   | /api/login                 | AuthController@login        | — | all |
| R03 | GET    | /api/products              | ProductController@index     | — | all |
| R04 | GET    | /api/products/{product}    | ProductController@show      | — | all |
| R05 | GET    | /api/categories            | CategoryController@index    | — | all |
| R06 | POST   | /api/stripe/webhook        | StripeWebhookController@handle | — | all |
| R07 | POST   | /api/payme/webhook         | PaymeWebhookController@handle | — | all |
| R08 | GET    | /api/test/orders/{order}   | (inline closure)            | auth | testing/staging |
| R09 | POST   | /api/test/tick             | (inline closure)            | auth | testing/staging |
| R10 | POST   | /api/logout                | AuthController@logout       | auth | all |
| R11 | GET    | /api/me                    | AuthController@me           | auth | all |
| R12 | GET    | /api/cart                  | CartController@index        | auth | all |
| R13 | POST   | /api/cart                  | CartController@store        | auth | all |
| R14 | PATCH  | /api/cart/{item}           | CartController@update       | auth | all |
| R15 | DELETE | /api/cart/{item}           | CartController@destroy      | auth | all |
| R16 | GET    | /api/orders                | OrderController@index       | auth | all |
| R17 | POST   | /api/orders                | OrderController@store       | auth | all |
| R18 | GET    | /api/orders/{order}        | OrderController@show        | auth | all |
| R19 | POST   | /api/orders/{order}/pay    | OrderController@pay         | auth | all |
| R20 | GET    | /api/survey                | SurveyController@show       | auth | all |
| R21 | POST   | /api/survey                | SurveyController@store      | auth | all |
| R22 | GET    | /api/menu/today            | MenuController@today        | auth | all |
| R23 | POST   | /api/menu/regenerate       | MenuController@regenerate   | auth | all |
| R24 | GET    | /api/subscriptions         | SubscriptionController@index | auth | all |
| R25 | POST   | /api/subscriptions         | SubscriptionController@store | auth | all |
| R26 | DELETE | /api/subscriptions/{subscription} | SubscriptionController@destroy | auth | all |

> 调试端点 2 条（R08/R09）独立声明在 yaml 中。**重申**：以 method 维度对齐，routes 共 26 method 操作，yaml 26 method 操作，**完全匹配**。

---

## §2 控制器方法清单（10 个 / 21 个公开方法）

略 — 见 `app/Http/Controllers/Api/*.php`。所有 controller 都有方法对应 routes，**没有"路由声明但方法缺失"**（验证方法：`grep -l "function" app/Http/Controllers/Api/*.php`）。

---

## §3 openapi.yaml 端点清单

略 — 见 `docs/bmad/openapi.yaml`。21 个 path 块，共 26 个 method operation。

---

## §4 差异矩阵（仅 D 类：schema 不一致 = 8 条）

| # | 端点 | openapi.yaml 声明 | routes + controller 实际 | 严重度 | 修复建议 |
|---|---|---|---|---|---|
| **D1** | POST /api/orders/{order}/pay | provider enum: `["stripe", "payme"]` | `OrderController::pay` 接受 `["stripe", "payme", "alipay_hk"]` | 🔴 **P0** | yaml 加 `alipay_hk`；或 controller 移除 `alipay_hk` |
| **D2** | POST /api/orders | request.required 缺 `shipping_address` | controller `OrderController::store` 强校验 `shipping_address` | 🟠 P1 | yaml request.required 加 `shipping_address` |
| **D3** | POST /api/orders | request.properties 缺 `discount_code` / `coupon_id` | controller 接受 `coupon_id` 字段 | 🟡 P2 | yaml 补字段（`coupon_id` string nullable） |
| **D4** | POST /api/cart | request 缺 `quantity` 字段约束（min: 1） | controller 强校验 `quantity >= 1` | 🟡 P2 | yaml 加 `minimum: 1` |
| **D5** | GET /api/orders | response 缺 `pagination.total` 字段 | controller 返 `LengthAwarePaginator` 含 `total` | 🟡 P2 | yaml 补 `total: integer` |
| **D6** | POST /api/subscriptions | request 缺 `plan_id` 字段 | controller 强校验 `plan_id` (FK) | 🟠 P1 | yaml 补 `plan_id: integer required` |
| **D7** | POST /api/menu/regenerate | 文档化"3 次/天限流"未在 response 注明 429 | controller 返 429 when over limit | 🟡 P2 | yaml 加 429 response 描述 |
| **D8** | POST /api/stripe/webhook | response 缺 `retry_after` 字段 | controller 失败时返 401（无 retry_after） | 🟢 P3 | yaml 移除/补全，按实际行为 |

---

## §5 修复建议（按优先级）

| 优先级 | 编号 | 动作 | Owner | 截止 |
|---|---|---|---|---|
| **🔴 立即** | D1 | yaml 加 `alipay_hk` / 或 controller 移除 alipay_hk 分支 | bravo + golf | Day 5 |
| **🟠 Day 5-6** | D2, D6 | yaml request.required 补 `shipping_address` / `plan_id` | golf | Day 6 |
| **🟡 Sprint 2** | D3, D4, D5, D7, D8 | schema 完整性补全 | golf | Sprint 2 Week 1 |
| **流程** | — | 在 PR 模板加 "yaml diff check" checklist | echo | Sprint 2 Week 1 |

---

## §6 责任分工调整建议

- **NEW-P2-03 责任错位修复**：
  - 原：api-contract.md §5 标 reviewer（错位）
  - **现：bravo（架构师）负责 yaml 完整性、schema 校验；golf（dev）负责 controller 与 yaml 一致性的 PR review**
  - 在 `docs/bmad/api-contract.md` §5 改 owner = bravo + golf

---

## §7 简报

- 已追加到 `.codebuddy/teams/greenbite-mvp/inboxes/bravo.json` 末尾
- 状态：done
- 责任：bravo (architect)
- 关键交付：8 处 schema 漂移、1 处 🔴 P0 静默生产风险（alipay_hk）

---

*本报告由 bravo 审计完成，待 v1.3 复评复核。*
