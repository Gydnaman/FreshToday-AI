# Phase 1 MVP 跑通 — 协调方案 v0.1

> **本文件是协调方案**（非代码方案），目标是让 beta（team member）跑通 Nuxt 4 MVP。
> **4 步循环状态**：Step 1（本 v0.1）→ Step 2（review 待）→ Step 3（改待）→ Step 4（再 review 待）
> **决策点（2026-07-03 11:35 用户拍板）**：MVP 优先于完整 Phase 1，先把 Nuxt 跑通

## §1 为什么改方向

| 维度 | 原 Phase 1 | MVP |
|---|---|---|
| 范围 | 14 组件 + 4 页面 + 主题 + 字体 + i18n + 布局 | Nuxt 4 + 主题 + 字体 + i18n + 1 首页 |
| 周期 | 5 天 | 1-2 天 |
| 目标 | 完整骨架 | **跑通即可** |
| 后续 | 迭代补组件 | MVP 验证后再补 |

**驱动**：用户原话"先跑一个 mvp 出来，所以第一阶段最终目标是先把 nuxt 跑通"。

## §2 MVP 范围（最小可启动）

### In scope（必做）
1. **Nuxt 4 工程初始化**
   - `nuxi init front-oversea-help-center-pc` 或基于 `nuxt-ui-templates/docs` 模板
   - `pnpm install` 跑通
2. **版本锁定**（SPEC §3.1 强约束）
   - Nuxt 4.4.8
   - Nuxt UI 4.9
   - Tailwind 4.3
   - @nuxt/content 3.14
   - Node >= 20, pnpm >= 9
   - `package.json` 必填 `engines` + `packageManager` 字段
3. **主题色跑通**（nota / mint / notaneutral）
   - `app.config.ts` 中 `ui.colors` 配置
   - `main.css` 中 `@theme` 50-950 色阶 token
   - 主页能看到紫绿渐变（而非模板默认绿）
4. **字体跑通**（Nunito Sans）
   - 4 个 .ttf 从 dev-package `static-reference/assets/fonts/` 复制
   - `@font-face` 声明
   - 全局应用
5. **i18n 跑通**（zh / en / **zh-Hant**）
   - `@nuxtjs/i18n` 配置
   - 3 个语言文件（`i18n/zh.json` / `en.json` / `zh-Hant.json`）
   - 三语切换测试
6. **1 个首页**（mock 数据驱动）
   - `pages/index.vue` 极简：标题 + 1 个按钮
   - 验证 SSR 跑通、HTML 含中文
7. **README 必填**
   - 启动命令（`pnpm install` / `pnpm dev` / `pnpm build`）
   - Node / pnpm 版本要求
   - 目录结构简述
8. **`.gitignore` 标准 Nuxt 4 模板**
9. **本地 `pnpm dev` 验证**：`http://localhost:3000` 可访问
10. **本地 `pnpm build` 验证**：产物 `.output/` 生成

### Out of scope（**明确不做**）
- ❌ 14 个 Help 组件（HeroSearch / TopicCard / TopicGrid / QuickActions / PopularArticles / ResourceLinks / ArticleRenderer / MarkdownBody / BlocksBody / ContactSupport / SearchPalette + 布局）
- ❌ 4 个完整页面（首页完整版 / 分类页 / 文章页 / 搜索页）
- ❌ Strapi 接入（Phase 3）
- ❌ TOC / 滚动高亮 / Mobile drawer
- ❌ Search palette / 搜索逻辑
- ❌ ContactSupport / Chatwoot / Dify
- ❌ 视觉回归（delta 范围）
- ❌ E2E / smoke / API 测试
- ❌ Lighthouse 性能
- ❌ SEO meta / sitemap / llms.txt / ai-index.json

### 不在 MVP 但仍要做的（"v0"层）
- nuxt.config.ts 留 runtimeConfig.strapi 占位（Phase 3 填）
- i18n.locales 包含 zh-Hant（与 UAT 一致，**不**用 zh-HK）
- .env.example 留 NUXT_STRAPI_TOKEN 占位
- 不加载 Chatwoot / Dify 外部脚本

## §3 角色分工

| 角色 | 范围 |
|---|---|
| **beta-nuxt-fe** | MVP 实施：建工程 / 装依赖 / 写主题 / 字体 / i18n / 1 首页 / README |
| **gamma-strapi-be**（我） | 协调：方案 / 改 beta agent 文件 / 监控进度 / 跨 agent 数据契约 |
| **alpha** | 验收 MVP 跑通 |
| delta / epsilon / zeta | MVP 完成后启动 |

**我**（gamma）**绝对不写 Vue 组件 / nuxt.config.ts**。违越界 = 重做。

## §4 协调动作（gamma 实施）

### 动作 1：改 beta agent 文件，加 MVP 范围声明
- 文件：`.codebuddy/agents/beta-nuxt-frontend.md`
- 追加"MVP 阶段（v0.1）"段，明确 §2 范围

### 动作 2：发 send_message 给 beta
- 通知 MVP 启动
- 附本方案链接
- 明确 gamma 不抢活

### 动作 3：监控
- 等 beta 每完成里程碑（依赖装好 / 主题色 OK / 首页能跑）send_message 回报
- 记录到 `_bmad/tasks/phase1-mvp/04-progress.md`

### 动作 4：MVP 跑通后
- 通知 alpha 验收
- 触发 git push 任务（v0.2 留底，开 v0.3 跑 push）
- 触发 Strapi P1 任务（设计稿已在）

## §5 验收（7 项必勾）

- [ ] 1. `pnpm dev` 可启动，`http://localhost:3000` 可见
- [ ] 2. 主页非模板默认绿（紫绿渐变）
- [ ] 3. 字体非 Inter（Nunito Sans）
- [ ] 4. i18n 切换 zh / en / zh-Hant 三个语言 OK
- [ ] 5. `pnpm build` 跑通，`.output/` 生成
- [ ] 6. README 含启动 / 构建 / Node / pnpm 版本
- [ ] 7. .gitignore 含 node_modules / .nuxt / .output / .env

## §6 风险

| 风险 | 严重度 | 缓解 |
|---|---|---|
| beta 还在跑原 Phase 1（14 组件 + 4 页面） | 🟡 中 | 改 beta agent 文件 + send_message 让他转 MVP |
| 模板降级到 Nuxt 3 | 🟡 中 | package.json 锁版本；beta 已知约束 |
| gamma 越界写 Vue | 🟢 低 | BMad override 强约束"gamma 不写 Vue" |
| Strapi i18n 映射 Nuxt i18n 时错乱 | 🟢 低 | 用 zh-Hant（与 UAT 一致） |
| MVP 跑通后用户不满（想要完整 Phase 1） | 🟢 低 | 透明：MVP 是用户拍板的方向 |

## §7 不在 gamma 范围

明确不做的：
- ❌ 写任何 `.vue` 文件
- ❌ 改 `nuxt.config.ts` / `app.config.ts`
- ❌ 跑 `pnpm install` / `pnpm dev` / `pnpm build`
- ❌ 改 `package.json` 依赖
- ❌ 复制字体文件

明确要做的：
- ✅ 改 `beta` agent 文件（协调）
- ✅ 发 send_message（协调）
- ✅ 监控进度（记录）
- ✅ 准备 Phase 3 联调数据契约（设计稿已在）

## §8 关联任务

- **P0 搁置**：git push v0.2 留底，等 MVP 跑通后开 v0.3
- **P0 已完成**：Strapi 设计稿 11 个文件
- **P1 待启动**：MVP 跑通 → Strapi UAT 部署
- **Phase 3 待启动**：MVP 跑通 + Strapi 部署 → 联调
- **Phase 4+**：MVP 验证后按 WORKFLOW 推进

## §9 下一步

按 4 步循环：
1. 本 v0.1 已出（chat 草稿 + 本落盘）
2. **下一步**：Step 2 = adversarial review 走读 v0.1
3. Step 3 = 按 review 改
4. Step 4 = 再 review
5. **实施**：MVP 协调动作（改 beta agent + send_message + 监控）
