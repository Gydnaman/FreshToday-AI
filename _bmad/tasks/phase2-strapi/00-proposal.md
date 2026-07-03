# Phase 2 — Strapi 内容建模 设计方案（Proposal）

> **本文件是方案**，**改前必须有**（2026-07-03 用户硬要求）。对应的实施产物：`docs/help-center-spec/`（11 个文件）。
> **本方案已经过一次 adversarial review**（见 `phase2-strapi-tasks.md` "P0 自查记录" 段），11 项高/中严重度已修，4 项低严重度留 Phase 5。
>
> **当前任务状态**：方案已 Review、已实施、待最终复核（再 Review）。

## 1. 目标

在 `https://cms.uat.notasign.com/admin/`（UAT 现有 Strapi 5）上落地 Help Center 4 个核心 Content Type + 12 个 help.* Component，供 `front-oversea-help-center-pc`（Nuxt 4 前台）作为内容源。

**业务目标**：
- 替换现有 VitePress + 本地 Markdown 的内容运营模式 → Strapi 5 后台编辑 + D&P + i18n
- 解决"内容运营无法自助、无 CMS 后台、SEO 管理困难"三大痛点（见 PRD §1.4）

**技术目标**：
- 4 个 CT 在 Admin 可见，字段完整
- 7 个正文 component + 5 个辅助 component = 12 个
- i18n 3 语（en/zh/zh-Hant）可独立编辑和发布
- routePath 唯一、分类深度 ≤ 2、redirect loop 阻断（lifecycle 强制）
- Custom API Token 严格只读，scope 最小化

## 2. 范围

### In scope（P0 + P1 阶段）
- 4 个 CT schema 设计 + 部署到 UAT
- 12 个 Component schema 设计 + 部署
- 3 个 lifecycle（help-article / help-category / help-redirect）伪代码 + 部署
- 1 个工具函数（`normalize-path.ts`）
- seed 脚本 + 三语测试数据 + 2 条 redirect
- Custom API Token scope 文档
- curl 验证脚本 + 13 项 Phase 2 自检表

### Out of scope（不在本期）
- 本地 Strapi 工程（用 UAT）
- Nuxt 业务页面（beta 范围）
- 新增 Strapi Admin 角色 / 审核流
- 改/加 Strapi locale
- 下载远端媒体
- 接 webhook / purge cache（Phase 3）
- Chatwoot / Dify（Phase 6）
- VitePress 历史内容迁移（Phase 5）

## 3. 依赖

| 依赖 | 状态 | 备注 |
|---|---|---|
| UAT Strapi Admin 可登录 | OK 已确认（200） | 用户提供账号 |
| UAT i18n locale = en/zh/zh-Hant | OK 已确认 | 不要加 zh-HK |
| 现有 `page` / `post` / `category` CT | OK 已确认存在 | `category` 与 `help-category` 命名潜在冲突 |
| `shared.seo` component | 待确认 P1 启动时 | 若不存在则新建 `help.seo` |
| GitLab `front-oversea-strapi` 仓库写权限 | 待确认 P1 启动时 | 由用户/运维提供 |
| beta-nuxt-fe 跑通 Nuxt 工程 | 进行中（P0 阶段 beta） | P1 启动前置条件 |
| Admin Token（临时） | 待确认 P1 启动时 | **不进任何文件** |

## 4. 影响面

| 影响对象 | 变化 |
|---|---|
| `cms.uat.notasign.com` | +4 CT、+12 Component、+3 lifecycle、+测试数据 |
| 现有 `page` / `post` | 不变 |
| 现有 `category` | 不变；`help-category` 不冲突（`help-` 前缀） |
| 现有 Public role 权限 | 待确认 find 端点是否需 token |
| Nuxt 工程 | 不变（`runtimeConfig.strapi.token` 留空，等 P1 填） |
| 前端代码 | 不变（beta 范围） |
| 数据库（UAT Postgres/sqlite） | +N 张表（Strapi 5 自动建） |

## 5. 关键决策（已拍板）

### 5.1 不在本地起 Strapi
**决策**：用 UAT `cms.uat.notasign.com/admin/`，**不**做 `npx create-strapi-app`。
**理由**：
- 公司已有 UAT Strapi，复用价值高
- 单租户本地 Strapi 与生产 UAT Strapi schema 会漂移
- 减少运维负担（不用维护多份 Strapi 实例）

### 5.2 Locale = zh-Hant（非 SPEC 写的 zh-HK）
**决策**：UAT 现有 locale 是 `en / zh / zh-Hant`，**不**要加 `zh-HK`。
**理由**：
- UAT Strapi 已存在 `zh-Hant`，加 `zh-HK` 会与已有 `post` / `page` 三语数据冲突
- ISO 639 旧码（`zh-Hant`）和 SPEC 用的 BCP-47 码（`zh-HK`）技术等价
- Nuxt i18n 也接受 `zh-Hant`，零成本切换
- beta 端也需相应调整（已在 BMad override 中声明）

### 5.3 12 个 help.* component
**决策**：SPEC 显式列 7 个正文 component + 实际引用需要 5 个辅助 = 12 个。
**理由**：
- SPEC 漏列 5 个（quick-action / resource-link / contact-cta / featured-link / search-alias）
- 4 个 CT 实际引用这些 component 才能跑通
- 已补建在 `05b-components-help-supplementary.md`

### 5.4 routePath 支持中文
**决策**：routePath regex = `^/[\p{L}\p{N}_/-]+$`，归一化时 ASCII 段小写、Unicode 段保留。
**理由**：
- Phase 5 迁移 VitePress 旧路径可能含中文段
- 国际化场景下，路径段有自然语言是有意义的
- 与 `shared.seo` 字段命名（slug 派生）解耦：slug 是编辑友好，routePath 是前台定位

### 5.5 help-redirect 全局唯一由 lifecycle 强制
**决策**：fromPath 在 help-redirect 表内跨 locale 全局唯一，**由 lifecycle 强制不在 schema 强制**。
**理由**：
- Strapi 5 schema 加 `unique: true` 会触发 DB error，lifecycle 拿不到明确报错
- lifecycle 可以给出"另一条规则已占用"的具体 documentId
- 性能可接受（单次 findFirst）

### 5.6 parent depth 按 locale 算
**决策**：在 `getParentDepthAndDocId(parent, locale)` 中显式传 locale，避免拿到跨语言条目。
**理由**：
- Strapi 5 i18n 模式下每个 locale 有独立 entry
- 同一 documentId 在不同 locale 下可能有不同 parent（理论上）
- 安全起见按 locale 严格取

## 6. 替代方案对比

| 替代方案 | 取舍 | 决策 |
|---|---|---|
| 本地 Strapi + 远程同步 UAT | 复杂、易漂移、需双向同步脚本 | 不用 |
| 仅设计稿、不动 UAT | 解决不了"内容运营无法自助"痛点 | 仍要 P1 部署 |
| 改/加 `zh-HK` locale | 已有 zh-Hant 数据，加 zh-HK 冗余 | 沿用 zh-Hant |
| Strapi 5 schema unique + lifecycle 兜底 | DB error 报错不友好 | 纯 lifecycle |
| routePath 仅英文 | Phase 5 中文路径无法迁移 | 支持中文 |

## 7. 风险

| 风险 | 严重度 | 缓解 |
|---|---|---|
| Public role find 端点无需 token | 高 | P1 启动时确认是否限制为 token 必填 |
| `shared.seo` 不存在 | 中 | P1 启动时确认；不存在则新建 `help.seo` |
| `category` 与 `help-category` 命名冲突 | 低 | 已有 `help-` 前缀隔离；P1 启动时确认 |
| GitLab `front-oversea-strapi` 仓库不可写 | 中 | P1 启动时确认权限；不可写则只产设计稿 |
| Admin Token 泄露 | 高 | 仅 PowerShell 进程 env，任务完立即 unset 并 Revoke |
| routePath 跨 CT 唯一性在并发下漏检 | 中 | 接受小概率；并发写场景靠业务侧互斥 |
| parent 链 N+1 查慢 | 低 | `MAX_HOPS=50` 防御；N+1 仅在写时触发 |
| seed 脚本用 `fetch` 但 UAT Strapi 跨域 | 中 | Strapi Admin 默认 allow all；若失败需在 CORS 加 nuxt 工程 origin |

## 8. 验收（13 项 + 自检表）

见 `_bmad/tasks/phase2-strapi-tasks.md` 的 P1.D 阶段 T-11~T-15 + `docs/help-center-spec/09-curl-verification.md` 的"验证 7"。

## 9. P0 已完成（11 个文件 / 2963 行）

| 文件 | 内容 |
|---|---|
| 00-README.md | 索引 + 决策 + 部署流程 |
| 01-schema-help-article.md | 25 字段 schema |
| 02-schema-help-category.md | 含 depth/parent/home 字段 |
| 03-schema-help-redirect.md | 含 7 字段 |
| 04-schema-help-settings.md | Single Type，引用 5 个辅助 component |
| 05-components-help.md | 7 个正文 component |
| 05b-components-help-supplementary.md | 5 个辅助 component |
| 06-lifecycles.md | 3 个 lifecycle + 工具函数 |
| 07-api-token.md | scope 文档（无明文） |
| 08-seed-script.template.ts | seed 脚本（fetch + query） |
| 09-curl-verification.md | 11 个 curl + 13 项自检 |

## 10. P1 待启动（需用户/运维配合）

- 登录 UAT Admin，创建 7 + 5 = 12 个 component
- 创建 4 个 CT
- 启用 i18n / D&P
- 创建 Custom API Token（用户手 Generate，存 .env）
- PR 推 lifecycle 到 GitLab `front-oversea-strapi`
- 跑 seed 脚本（Admin Token 临时 env）
- 跑 11 个 curl 验证
- 写 `PHASE2_VERIFICATION.md`

## 11. BMad Review 记录

**已 Review 一次**（adversarial review，2026-07-03 11:30）：

| Finding # | 严重度 | 状态 | 修复 |
|---|---|---|---|
| 1 | 高 | 已修 | 新建 05b 补 5 个 component |
| 2 | 高 | 待 P1 启动确认 | shared.seo 是否存在 |
| 3 | 高 | 已修 | fromPath 唯一性由 lifecycle 强制 |
| 4 | 中 | 已修 | parent depth 加 locale 参数 |
| 5 | 中 | 已修 | detectCycle 加 MAX_HOPS + 字段裁剪 |
| 6 | 中 | 已修 | seed 改用 fetch 非 $fetch |
| 7 | 中 | 已修 | status=published 改用 query 不用 body |
| 8 | 中 | 已复核 | seed popularArticles 取 articleDocId 正确 |
| 9 | 中 | 已修 | help-redirect 加"前端跳转优先级"小节 |
| 10 | 中 | 已修 | beforeUpdate depth 重算 |
| 11 | 中 | 已修 | routePath regex 支持中文 |
| 12 | 低 | 留 Phase 5 | lifecycle 错误消息硬编码中文 |
| 13 | 低 | 已修 | seed 描述"locale 共享树结构"误导 |
| 14 | 低 | 已修 | help.table.rows 结构描述补全 |
| 15 | 低 | 已处理 | PHASE2_VERIFICATION.template.md 改为运行时补建 |

## 12. 下一步

**当前状态**：方案已 Review、已实施（设计稿 11 个文件）、**待最终再 Review**（按新规则）。

**再 Review 待做**：
- 重新走读整个 `docs/help-center-spec/` 目录
- 重点检查 review 后的改动是否引入新问题
- 重点检查 4 项低严重度 finding 是否真的可以留 Phase 5

**P1 启动前置**（等 beta 跑通 Nuxt + 你下令 P1）：
- 确认 `shared.seo` 是否存在
- 确认 `category` 现有 CT 用途
- 确认 Public role 权限策略
- 提供 Admin Token（PowerShell 进程 env）
- 提供 GitLab 仓库写权限

