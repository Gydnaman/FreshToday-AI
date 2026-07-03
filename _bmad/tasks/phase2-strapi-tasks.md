# Phase 2 — Strapi 5 Help Center 内容建模 任务拆解（UAT 适配版 / P1 延期）

> **优先级调整（2026-07-03 11:00 经用户确认）**：
> - **P0（现在）** = Nuxt 框架重建（beta-nuxt-fe 负责；gamma 监控进度 + 准备 Strapi 设计稿）
> - **P1（Nuxt 跑通后启动）** = Strapi 内容建模（gamma 主责，对接 UAT `cms.uat.notasign.com`）
>
> **不再做的事**：本地 `npx create-strapi-app`、本地 `npm run develop`、本地 curl。**改用 UAT 现有 Strapi**。

## 角色边界（重划）
- **beta-nuxt-fe** = Nuxt 工程初始化 + 主题 + 16 组件 + 4 页面（mock 数据驱动）
- **gamma-strapi-be** =
  - P0：只做 Strapi **设计稿预生产**（schema.json 静态文件 + lifecycle 伪代码 + seed 脚本模板，**不依赖 Strapi 实例**）+ 监控 Nuxt 进度
  - P1：拿到 UAT Admin Token 后，把设计稿推到 `cms.uat.notasign.com`，跑 curl 验证

---

## P0 阶段任务（立即执行 / 当前周）

### P0.A — Strapi 现状摸底（已完成）
- [x] T-A1 探测 `cms.uat.notasign.com` 公开 API（GET，不写）
  - `/admin` → 200（Strapi 5 SPA）
  - `/api/pages` → 200（已有）
  - `/api/posts` → 200（已有）
  - `/api/categories` → 200（已有 — ⚠️ 与 help-category 命名潜在冲突）
  - `/api/redirects` → 404
  - `/api/help-articles` → 404（**未建**）
  - `/api/help-categories` → 404（**未建**）
- [x] T-A2 探测 i18n locales
  - 已确认三语：`en`(default), `zh`, **`zh-Hant`**
  - **冲突点**：SPEC §2.1 写 `zh-HK`，UAT 实际是 `zh-Hant`
  - **建议**：沿用 `zh-Hant`（零迁移、与现有 page/post 对齐）
- [ ] T-A3 探测现有 `page` / `post` / `category` 字段（GET schema），看是否复用 SEO component
  - **不阻塞 P0**，P1 启动时做

### P0.B — Strapi 设计稿预生产（gamma 现在做）
- [ ] T-B1 写 `docs/help-center-spec/01-schema-help-article.md` —— help-article 完整 schema.json（不动 Strapi）
- [ ] T-B2 写 `docs/help-center-spec/02-schema-help-category.md`
- [ ] T-B3 写 `docs/help-center-spec/03-schema-help-redirect.md`
- [ ] T-B4 写 `docs/help-center-spec/04-schema-help-settings.md`（Single Type）
- [ ] T-B5 写 `docs/help-center-spec/05-components-help.md` —— 7 个 help.* component
- [ ] T-B6 写 `docs/help-center-spec/06-lifecycles.md` —— 校验/拦截逻辑伪代码（depth/loop/routePath 唯一性）
- [ ] T-B7 写 `docs/help-center-spec/07-api-token.md` —— Custom API Token scope 文档（**不放 token 明文**）
- [ ] T-B8 写 `docs/help-center-spec/08-seed-script.template.ts` —— seed 脚本模板（运行时填真实 documentId）
- [ ] T-B9 写 `docs/help-center-spec/09-curl-verification.md` —— curl 模板（运行后填实际 documentId）
- [ ] T-B10 写 `docs/help-center-spec/00-README.md` —— 设计稿索引 + locale 决策记录 + 部署流程

### P0.C — Nuxt 进度监控（gamma 配合）
- [ ] T-C1 监听 beta-nuxt-fe 的 send_message，每完成一个里程碑（依赖装好 / 主题色 OK / 页面 1 完成 / 全部页面完成）记录到工作记忆
- [ ] T-C2 在 Nuxt 跑通后，从 Nuxt 工程读 `nuxt.config.ts` 的 `runtimeConfig.strapi.url` 占位，准备 P1 的 token 注入位置
- [ ] T-C3 Phase 1 验收（Day 5）后，向 Alpha 报告"Strapi 设计稿预生产完成 + Nuxt 集成点已识别"

---

## P1 阶段任务（Nuxt 跑通后启动 / 第 2 周初）

### P1.A — Strapi Admin 部署（需要用户/运维配合）
- [ ] T-1 在 `cms.uat.notasign.com` 登录 Admin，**手工**或脚本创建 7 个 help.* component
- [ ] T-2 **手工**或脚本创建 4 个 CT（help-article / help-category / help-redirect / help-settings）
- [ ] T-3 启用 i18n（zh / en / zh-Hant），启用 Draft & Publish
- [ ] T-4 在 Settings → API Tokens 创建 Custom Token：
  - Type: `custom`
  - Scope: `help-article:find,findOne` + `help-category:find,findOne` + `help-redirect:find,findOne` + `help-settings:find,findOne` + `plugin::upload.read`
  - **不**勾 `create/update/delete`
  - **不**勾任何 `admin::*`
  - Token 字符串写到用户本地 `.env`（**不提交 git**），env var 名 `NUXT_STRAPI_TOKEN`
- [ ] T-5 把 admin token 给 gamma（**仅 P1 启动时**一次性，存到 PowerShell env 不进项目文件），用于跑 seed 脚本

### P1.B — Lifecycle 部署
- [ ] T-6 在 GitLab `front-oversea-strapi` 仓库 `src/api/help-article/content-types/help-article/lifecycles.ts` 提交（按 P0-B6 设计稿）
- [ ] T-7 同上 help-category / help-redirect
- [ ] T-8 PR 合并 → UAT 自动部署（沿用现有 CI/CD）

### P1.C — Seed 数据
- [ ] T-9 跑 `node scripts/seed-help-data.js`（按 P0-B8 模板），创建：
  - 2 个一级分类（getting-started / send-and-sign）— zh/en/zh-Hant
  - 1 个二级分类（quickstart） — zh/en/zh-Hant
  - 2 篇文章 — zh/en/zh-Hant（renderMode=markdown，正文含图片+代码块）
  - 1 个 help-settings（zh/en/zh-Hant 3 个 locale，各 4-6 quickActions + 2-3 resourceLinks + 2-3 popularArticles）
  - 1-2 条 help-redirect（/User_Guide/quickstart → /getting-started/quickstart）
- [ ] T-10 三语全部发布

### P1.D — 验证
- [ ] T-11 curl `https://cms.uat.notasign.com/api/help-articles?locale=zh&status=published&populate=*` → ≥1
- [ ] T-12 curl `https://cms.uat.notasign.com/api/help-categories?locale=zh&filters[showOnHome][$eq]=true&populate=*` → ≥1
- [ ] T-13 curl `https://cms.uat.notasign.com/api/help-redirects?filters[enabled][$eq]=true` → ≥1
- [ ] T-14 验证草稿不带 publicationState 不返回
- [ ] T-15 写 `front-oversea-help-center-pc/PHASE2_VERIFICATION.md` 含 curl 实际输出

---

## Superpowers 约束（仍生效）
1. **TDD 优先**：lifecycle 校验逻辑在 P0 设计稿中要先列"违反用例 → 期望行为"再写代码
2. **频繁提交**：P0 设计稿每完成一个文件就 git commit（仅本仓库）
3. **YAGNI**：4 CT 字段严格按 SPEC；不预加 SPEC 没有的字段
4. **DRY**：routePath 归一化函数统一在 P0-B6 单文件，被 3 个 lifecycle 引用
5. **范围控制**：
   - gamma 不做 Nuxt 业务页面（beta 范围）
   - gamma 不下载远端媒体；不接 webhook URL；不接 purge cache 配置（Phase 5 处理）
6. **审查每一步**：完成 P0 后用 bmad-review-adversarial-general 走读；完成 P1 整体后用 bmad-review-edge-case-hunter 跑边界

## 风险 / 已知约束
- R1：UAT 现有 `category` CT 与 `help-category` 命名潜在冲突 → P1 启动时确认
- R2：UAT 现有 locale 是 `zh-Hant` 不是 `zh-HK` → Nuxt 端 i18n 也要相应调整
- R3：UAT Strapi 公开 API 端点（find）当前不需要 token 即返回 200（**安全风险：可能未配 Public role 权限**），P1 启动时由用户/运维确认是否限制
- R4：Strapi 5 中 `status=published` 相对 `publishedAt IS NOT NULL`，seed 脚本必须显式 publish
- R5：Admin Token 在 P1 跑 seed 时只能存 PowerShell 进程 env，不能写进仓库
