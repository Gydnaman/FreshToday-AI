# GreenBite 测试策略总览（Sprint 0）

> 本文件是 GreenBite（FreshToday-AI）项目的 **测试策略总纲**，作为 Sprint 0~Sprint 3 整个交付周期内 QA 活动、工具选型、准入/准出标准、缺陷分级的统一基准。  
> 与 `fdd-bmad-custom` 框架中的 `bmad-qa-generate-e2e-tests` / `bmad-code-review` / `bmad-review-edge-case-hunter` 三个 skill 配合使用。

---

## 元信息（Metadata）

| 字段 | 值 |
| --- | --- |
| 文档名称 | GreenBite 测试策略总览（Test Strategy） |
| 创建人 | qa-agent |
| 版本 | v1.0 |
| 创建日期 | 2026-06-12 |
| 适用项目 | GreenBite / FreshToday-AI |
| 技术栈 | Laravel 12 + MySQL 8 + Tailwind 4 + Blade + jQuery + Gemini API |
| 关联文档 | `e2e-scenarios.md`、`edge-cases.md` |
| 关联 Skill | `bmad-qa-generate-e2e-tests`、`bmad-code-review`、`bmad-review-edge-case-hunter` |

---

## 1. 测试金字塔与配比目标

GreenBite 采用 fdd-bmad-custom 推荐的「四层测试金字塔 + 探索性测试」模型，从下到上覆盖粒度由细到粗：

```text
            ┌──────────────────────────────┐
            │   探索性测试 (Exploratory)    │   10%   ← QA Session-Based
            ├──────────────────────────────┤
            │     E2E（端到端）              │   10%   ← Playwright + 关键路径
            ├──────────────────────────────┤
            │     集成 / Feature 测试        │   30%   ← PHPUnit Feature Suite
            ├──────────────────────────────┤
            │     单元测试 (Unit)            │   50%   ← PHPUnit Unit Suite
            └──────────────────────────────┘
```

| 层级 | 目标占比 | 主要工具 | 关注点 | 触发时机 |
| --- | --- | --- | --- | --- |
| 单元 (Unit) | 50% | PHPUnit | Model 关系、Service 逻辑、Util 工具、纯函数 | 每次 push / PR |
| 集成 / Feature | 30% | PHPUnit + Laravel Test（HTTP 模拟、Queue Fake、Mail Fake） | Controller、表单校验、DB 事务、中间件、Mail/Notification | 每次 push / PR |
| E2E | 10% | Playwright（建议） / Laravel Dusk（备选） | 关键用户旅程、跨页面状态、支付、订阅 | PR 合并到 main / Nightly |
| 探索性 | 10% | QA 手工 + Session-Based Test (SBTM) | Charter 驱动、启发式遍历、缺陷挖掘 | 每个 Sprint 至少 1 次 Charter Session |

### 覆盖目标（Coverage Goals）

| 维度 | 目标 | 度量工具 | 准入门槛 |
| --- | --- | --- | --- |
| 行覆盖率 (Line) | **≥ 80%** | phpunit + `pcov` / `xdebug` | < 80% 禁止合并 |
| 分支覆盖率 (Branch) | **≥ 70%** | phpunit + `pcov` | < 70% 列入技术债 |
| 关键路径 E2E | **100%** | Playwright | 5 个核心场景必须绿 |
| Service 层（`app/Services`） | **≥ 90%** | phpunit | 与外部 API 交互的逻辑必须 Mock |
| 关键 Controller | **100%** 覆盖正向 + 异常 | phpunit Feature | 必须包含 `assertStatus` 验证 |

> 关键路径指：`注册 → 问卷 → AI 菜单 → 加购 → 下单 → 订单` / `订阅续费` / `碳足迹累计` / `支付失败回滚` / `i18n 切换`，详见 `e2e-scenarios.md`。

---

## 2. 工具选型

| 工具 | 用途 | 状态 | 备注 |
| --- | --- | --- | --- |
| **PHPUnit 11** | 单元 + Feature 测试 | 已装（Laravel 12 默认） | `phpunit.xml` 已使用 sqlite in-memory |
| **Mockery** | 依赖 Mock | 随 PHPUnit 引入 | 主要用于 Mock Gemini API / 支付网关 |
| **Laravel HTTP Test** | Controller / 路由集成 | 已装 | `->actingAs()` + `->assertSessionHas()` |
| **Faker** | 测试数据生成 | 随 Laravel 引入 | 制造「真实」订单 / 用户 |
| **Playwright** | E2E（建议） | **建议安装** | 跨浏览器（Chromium / Firefox / WebKit），CI 友好，支持 i18n / RTL |
| **Laravel Dusk** | E2E（备选） | 备选 | 若团队无 Node 环境，可回退到 Dusk |
| **PHPStan / Larastan** | 静态分析 | 建议 Sprint 1 引入 | 防 SQL 注入、类型错误 |
| **Pest** | 替代 PHPUnit 的 DSL | 备选 | 若团队偏好 BDD 风格 |
| **Mailpit / Log fake** | 邮件验证 | dev 内置 | 用于邮件验证 / 订单通知测试 |
| **Stripe CLI** | Webhook 测试 | Sprint 2 起 | 模拟 `payment_intent.succeeded` / `failed` |

### 工具决策依据

- **PHPUnit**：Laravel 默认零成本、覆盖 Unit/Feature 双场景。
- **Playwright vs Dusk**：Playwright 跨浏览器能力强、社区活跃、对 a11y 与 i18n 有更好的内建支持；Dusk 上手快但仅支持 Chrome、且对前端 SPA 适配较弱。**建议**优先 Playwright。
- **Mock 优先于真依赖**：Gemini API、Stripe、SMTP、CDN 全部用 Mock，**不**在测试环境真实调用。

---

## 3. 测试环境矩阵

| 环境 | 用途 | 数据库 | 外部依赖 | 数据来源 | 准入角色 |
| --- | --- | --- | --- | --- | --- |
| **local** | 开发者本机 | sqlite / docker mysql | Mock 全部 | Seeders + Factory | 全员 |
| **CI**（GitHub Actions / GitLab CI） | 每次 PR 自动跑 | sqlite `:memory:` | Mock 全部 | Factory | 自动化 |
| **staging** | 集成验收 / 预发 | MySQL 8 真实实例 | Sandbox 第三方（Stripe test、Mailpit） | Anonymized prod snapshot | QA + PM + Dev |
| **production** | 上线后 smoke | MySQL 8 真实 | 真实第三方 | 真实用户 | DevOps + QA 监控 |

### 环境间隔离原则

1. **任何测试不得直接连 production DB**（CI 通过 PR 静态检查 + DB 用户权限双重防护）。
2. staging 必须使用 Stripe **test mode**、Mailpit / sandbox SMTP、Gemini `test` key。
3. 跨环境 schema 同步：CI 跑 `migrate:fresh --seed`，staging 跑 `migrate`（不 seed）。
4. 测试用户 / 测试卡号（Stripe `4242 4242 4242 4242`、拒付 `4000 0000 0000 0002`）固定在 `tests/Fixtures` 维护。

---

## 4. 缺陷分级（Severity）

> 与 fdd-bmad-custom 的 `bmad-code-review` 缺陷标签对齐。

| 级别 | 名称 | 定义 | SLA 响应 | SLA 修复 | 准入 / 准出影响 |
| --- | --- | --- | --- | --- | --- |
| **P0** | 阻断 Blocker | 核心流程不可用：注册/支付/订阅全断；数据丢失；安全漏洞 | 30 min | 4 h | **必须 0 个 P0 才能发版** |
| **P1** | 严重 Critical | 主要功能不可用但有 workaround：AI 菜单生成失败但 fallback 缺失 | 2 h | 24 h | 上线前必须清零 |
| **P2** | 一般 Major | 非核心功能异常：UI 错位、错别字、弱网降级缺失 | 1 工作日 | Sprint 内 | 上线前可遗留 ≤ 3 个 |
| **P3** | 建议 Minor | 体验优化、文案、a11y 细节 | 1 Sprint | 视 backlog | 视价值评估 |

### 缺陷优先级矩阵（P × S）

| | 高影响 | 中影响 | 低影响 |
| --- | --- | --- | --- |
| **高概率** | P0 | P1 | P2 |
| **中概率** | P1 | P2 | P3 |
| **低概率** | P2 | P3 | P3 |

---

## 5. 准入 / 准出标准（Entry / Exit Criteria）

### 5.1 准入（Entry Criteria）— 进入测试阶段必须满足

- [ ] PM 已确认 PRD 定稿（含 i18n 词条清单）
- [ ] Architect 已输出架构图 + 接口契约（OpenAPI / 接口签名）
- [ ] Dev 已完成功能开发并通过 `composer test` 本地全绿
- [ ] 所有新代码 `php-cs-fixer` / `phpstan` 通过
- [ ] 数据库 migration 可在 sqlite / MySQL 双向跑通
- [ ] 所有第三方 Key 已在 staging 配齐（Stripe / Gemini / SMTP）
- [ ] QA 已收到验收 Checklist（AC）+ 测试数据 Seed 脚本

### 5.2 准出（Exit Criteria）— 可发布到生产

- [ ] **0 个 P0 缺陷**、**0 个 P1 缺陷**开放
- [ ] P2 缺陷 ≤ 3 个且有 owner / 截止日期
- [ ] 单元测试覆盖率 ≥ 80%，关键 Service ≥ 90%
- [ ] 5 个 E2E 场景全部绿（Playwright 报告归档）
- [ ] 性能：关键路径 P95 < 500ms（生产同等规模 staging 验证）
- [ ] 安全：`phpcs` Security 规则 + 渗透测试（依赖 devops-agent）
- [ ] 可访问性（a11y）：核心页面通过 axe-core 自动扫描无 critical
- [ ] i18n 抽查：英文 / 繁中 / 简中 三语种 key 100% 覆盖，无硬编码
- [ ] 回滚方案演练过（devops-agent 提供 runbook）

---

## 6. Sprint 0~Sprint 3 测试重点

| Sprint | 主题 | 测试重点 | 自动化产出 |
| --- | --- | --- | --- |
| **Sprint 0** | 基础脚手架 | CI/CD 测试流水线、PHPUnit + Playwright 框架搭建、Mock 服务（SMTP / Stripe）、Seed 数据 | `phpunit.xml` 调整、首个 `ExampleTest`、Playwright 配置、CI workflow |
| **Sprint 1** | 用户 / 问卷 / AI 菜单 | 注册 / 登录 / 邮件验证、6 题问卷提交、`AiMenuService`（含 Gemini 5xx / 限流 / 超时 / 降级）| 单元：User / UserPreference / AiMenuService；Feature：SurveyController；E2E：新用户首次体验 |
| **Sprint 2** | 商品 / 购物车 / 订单 / 订阅 | 购物车 CRUD、订单状态机、Stripe 支付、订阅续费、Webhook 重放、库存扣减与回滚 | 单元：Order / Subscription 状态机；Feature：CheckoutController、StripeWebhookController；E2E：订阅续费、支付失败回滚 |
| **Sprint 3** | 碳足迹 / 仪表盘 / i18n / 探索性 | `carbon_saved` 累计计算、dashboard 渲染、i18n 切换（en / zh-HK / zh-CN）、a11y、跨设备、RTL | 单元：CarbonCalculator；Feature：DashboardController；E2E：碳足迹累计、i18n 切换；Session-Based 探索性测试 Charter |

---

## 7. ATDD 工作流（与 fdd-bmad-custom 对齐）

GreenBite 的 E2E 用例采用 **ATDD（Acceptance Test-Driven Development）** 流程：

```text
PRD(PM) ──► 用户故事 AC ──► QA 编写 E2E（Given/When/Then）
                                  │
                                  ▼
                        Architect 提供接口契约
                                  │
                                  ▼
                        Dev 实现 + Unit / Feature
                                  │
                                  ▼
                        E2E 验证（红 → 绿）
                                  │
                                  ▼
                        Code Review（含 bmad-code-review）
                                  │
                                  ▼
                        Edge Case Hunter（bmad-review-edge-case-hunter）
```

### Given/When/Then 模板（统一）

```gherkin
Given <前置条件：账号、数据库、Mock>
  And <附加前置>
When  <用户操作 / 系统触发>
Then  <可断言的预期结果：HTTP 200 / DB 状态 / 页面 DOM>
  And <性能 / a11y / i18n 断言>
```

---

## 8. 报告与度量（QA KPIs）

| 指标 | 公式 | 目标 | 报告频率 |
| --- | --- | --- | --- |
| 缺陷逃逸率 | 生产缺陷 / 总缺陷 × 100% | < 10% | 每 Sprint |
| 测试覆盖率 | `phpunit --coverage-text` | ≥ 80% | 每次 CI |
| E2E 稳定性 | 1 - 误报数 / 总执行数 | ≥ 95% | 每日 |
| 平均修复时间 (MTTR) | Σ(修复时间-发现时间) / 缺陷数 | P0 < 4h / P1 < 24h | 每 Sprint |
| ATDD 用例落地率 | 已转 E2E 的 AC / 总 AC | 100% | 每 Sprint |
| 探索性缺陷发现数 | SBTM Session 缺陷 | 持续监控 | 每次 Session |

---

## 9. 风险与缓解

| 风险 | 影响 | 缓解措施 |
| --- | --- | --- |
| Gemini API 限流 / 5xx | AI 菜单失败 | Fallback 菜单 + 重试 + 限流监控；详见 `edge-cases.md` |
| Stripe Webhook 抖动 | 订阅状态错乱 | 幂等 token + 重放保护 + 状态机校验 |
| jQuery + Tailwind 4 兼容性 | E2E 元素选择不稳 | Playwright 优先用 `data-testid` 选元素 |
| sqlite vs MySQL 行为差异 | 测试假阳 / 假阴 | 关键 transaction / 字符集在 staging 用真 MySQL 复测 |
| i18n key 漂移 | 文案硬编码 | CI 加 `grep -R` 检查模板内中文硬编码 |
| 时区错乱（HK vs UTC） | 订单时间偏差 | Carbon 统一用 `Asia/Hong_Kong`，CI 强制 `APP_TIMEZONE` |

---

## 10. 附录

### 10.1 测试目录约定

```text
tests/
├── Unit/                      # 单元测试
│   ├── Models/
│   ├── Services/
│   └── Support/
├── Feature/                   # 集成 / HTTP 测试
│   ├── Auth/
│   ├── Survey/
│   ├── Checkout/
│   └── Webhook/
├── E2E/                       # Playwright
│   ├── specs/
│   │   ├── new-user-onboarding.spec.ts
│   │   ├── subscription-renewal.spec.ts
│   │   ├── carbon-accumulation.spec.ts
│   │   ├── payment-failure-rollback.spec.ts
│   │   └── i18n-switch.spec.ts
│   ├── fixtures/
│   └── playwright.config.ts
└── Fixtures/                  # 共享测试数据
```

### 10.2 相关 Skill 速查

- `bmad-qa-generate-e2e-tests`：从 AC 自动生成 Playwright 用例
- `bmad-code-review`：PR 级别的代码 + 测试审查
- `bmad-review-edge-case-hunter`：基于路径覆盖推导边界情况

---

*文档结束。如需细化 E2E 用例，见 `e2e-scenarios.md`；如需边界清单，见 `edge-cases.md`。*
