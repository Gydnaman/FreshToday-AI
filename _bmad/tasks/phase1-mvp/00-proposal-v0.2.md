# Phase 1 MVP 跑通 — 协调方案 v0.2

> v0.1 → v0.2 升级（按 review 修 10 项高/中严重度）
> **4 步循环状态**：Step 1 v0.1（chat+落盘）✅ → Step 2 review 15 条 ✅ → Step 3（本 v0.2）⏳ → Step 4 再 review
> **关联任务**：git-push 任务 v0.2 留底待 P1 启动；Strapi 设计稿 P0 已就位

## v0.1 → v0.2 修复映射

| Finding | 严重度 | v0.2 修复 |
|---|---|---|
| M1 | 🔴 高 | §3 改"alpha 改 beta agent 文件"（不是 gamma 改）|
| M2 | 🔴 高 | §4 动作 0 加：先 team status 确认 beta 健康 |
| M3 | 🔴 高 | §2 第 6 项加 mock 数据结构：标题 + 1 hero 段 + 3 张卡片标题 |
| M4 | 🟡 中 | §5 验收加第 8 项：主题对比度检查（按钮文字 vs 背景 ≥ 4.5:1） |
| M5 | 🟡 中 | §4 加动作 3.5：沉默 2 小时告警 alpha |
| M6 | 🟡 中 | §6 风险加应对决策树（用户拍板扩 / 坚持 MVP） |
| M7 | 🟡 中 | §4 动作 1 注明：beta 已 spawn 完，agent 文件改动需 beta 重启才生效（**当前 session 不影响**） |
| M8 | 🟡 中 | §2 加模板来源：`nuxt-ui-templates/docs` 当前 package 基线 |
| M9 | 🟡 中 | §4 加动作 4.5：MVP 跑通后 gamma 在 P1 启动前**产 Strapi 数据契约** |
| M10 | 🟡 中 | §2 i18n 加策略：`prefix_except_default`（zh 无前缀，en / zh-Hant 带前缀） |
| M11-M15 | 🟢 低 | 记录到 §6 风险，不修 |

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
   - 模板来源：`nuxt-ui-templates/docs` 当前 package 基线
   - 路径：`d:\HelpCentreRebuild\front-oversea-help-center-pc\`
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
   - 全局应用（**非 Inter**）
5. **i18n 跑通**（zh / en / **zh-Hant**）
   - 策略：`prefix_except_default`（zh 无前缀，en / zh-Hant 带 `/en/` / `/zh-Hant/`）
   - `@nuxtjs/i18n` 配置
   - 3 个语言文件（`i18n/zh.json` / `en.json` / `zh-Hant.json`）
   - 三语切换测试
6. **1 个首页**（mock 数据驱动）
   - `pages/index.vue` 极简：标题 + 1 hero 段 + 3 张卡片标题
   - mock 数据结构：`{ title, heroText, cards: [{title}, {title}, {title}] }`
   - 验证 SSR 跑通、HTML 含中文
7. **README 必填**
   - 启动命令（`pnpm install` / `pnpm dev` / `pnpm build`）
   - Node / pnpm 版本要求
   - 目录结构简述（5-8 个关键目录）
   - **不要**包含 token / 内部 URL / 内部账号
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
| **alpha** | **改 beta agent 文件**加 MVP 范围声明（v0.1 误写为 gamma 改）|
| **gamma-strapi-be**（我） | send_message 通知 beta + 监控进度 + P1 准备数据契约 |
| **alpha** | 验收 MVP 跑通 |
| delta / epsilon / zeta | MVP 完成后启动 |

**我**（gamma）**绝对不写 Vue 组件 / nuxt.config.ts**。违越界 = 重做。

## §4 协调动作

### 动作 0：前置检查（**gamma 必先做**）

**0.1 确认 beta 健康**
- 用 send_message 发 `team status` 查询（如果可用）
- 备选：直接发 send_message 探活（"收到请回"）
- beta 不响应 → 通知 alpha 介入

**0.2 确认 alpha 收到方案**
- 给 alpha 发 send_message：本 v0.2 方案 + 修改 beta agent 的请求

### 动作 1：alpha 改 beta agent 文件（**用户/alpha 执行**，不在我工作区）

- 文件：`.codebuddy/agents/beta-nuxt-frontend.md`
- 追加"MVP 阶段（v0.1）"段，明确 §2 范围
- **重要**：beta 已 spawn 完，agent 文件改动**当前 session 不影响**。需 beta 重启才生效

### 动作 2：gamma 发 send_message 给 beta

- 通知 MVP 启动
- 附本方案链接（路径 `_bmad/tasks/phase1-mvp/00-proposal-v0.2.md`）
- 明确 gamma 不抢活
- 明确 7 项验收

### 动作 3：监控

- 等 beta 每完成里程碑（依赖装好 / 主题色 OK / 首页能跑）send_message 回报
- 记录到 `_bmad/tasks/phase1-mvp/04-progress.md`
- **沉默 2 小时告警 alpha**（无新 message 触发）

### 动作 4：MVP 跑通后

- 通知 alpha 验收
- **不触发** git push（等用户指令）
- **不触发** Phase 3 联调（等用户指令）

### 动作 4.5：MVP 跑通后、Phase 3 启动前，gamma 做的事

- 产 Strapi 数据契约：Nuxt server route 调 Strapi 的 schema、字段、populate 片段
- 写 `front-oversea-help-center-pc/server/utils/strapi-populate.ts` 设计稿
- 供 beta 在 Phase 3 联调时使用

## §5 验收（8 项必勾）

- [ ] 1. `pnpm dev` 可启动，`http://localhost:3000` 可见
- [ ] 2. 主页非模板默认绿（紫绿渐变）
- [ ] 3. 字体非 Inter（Nunito Sans）
- [ ] 4. i18n 切换 zh / en / **zh-Hant** 三个语言 OK，路由按 `prefix_except_default` 策略
- [ ] 5. `pnpm build` 跑通，`.output/` 生成
- [ ] 6. README 含启动 / 构建 / Node / pnpm 版本
- [ ] 7. .gitignore 含 node_modules / .nuxt / .output / .env
- [ ] 8. **主题对比度检查**：主按钮 vs 背景 ≥ 4.5:1（WCAG AA）

## §6 风险与决策树

### 风险

| 风险 | 严重度 | 应对 |
|---|---|---|
| beta 还在跑原 Phase 1（14 组件 + 4 页面） | 🟡 中 | alpha 改 beta agent + gamma send_message 转 MVP |
| 模板降级到 Nuxt 3 | 🟡 中 | package.json 锁版本；beta 已知约束 |
| gamma 越界写 Vue | 🟢 低 | BMad override 强约束"gamma 不写 Vue" |
| Strapi i18n 映射 Nuxt i18n 时错乱 | 🟢 低 | 用 zh-Hant（与 UAT 一致） |
| beta agent 文件改了不生效（已 spawn） | 🟡 中 | gamma 改 send_message 内容；agent 文件改动作下次启动生效 |
| **MVP 跑通后用户不满** | 🟢 低 | 见决策树 |
| **git push 任务 v0.2 还有 15 条 edge case** | 🟡 中 | v0.2 留底，下次启动 v0.3 修 |
| **MVP 不含 Mobile 适配** | 🟢 低 | 跑通就好；dev-package 截图供后续 |
| **MVP 不含 search/seo/llms** | 🟢 低 | Phase 4 补 |
| **README 详细度未规定** | 🟢 低 | beta 自由发挥；MVP 验收看 §5 第 6 项 |

### 决策树：MVP 跑通后用户说"不够"

```
用户："MVP 太简单，要 14 组件 + 4 页面"
  ├─ 选项 A：扩到完整 Phase 1（再加 3-4 天）
  ├─ 选项 B：MVP + 加 1-2 个最关键组件（如 TopicGrid + HeroSearch）
  └─ 选项 C：MVP 验收通过，进 Phase 3 联调
        （用户之后想补再说）
```

按用户拍板走。

## §7 不在 gamma 范围

明确不做的：
- ❌ 写任何 `.vue` 文件
- ❌ 改 `nuxt.config.ts` / `app.config.ts`
- ❌ 跑 `pnpm install` / `pnpm dev` / `pnpm build`
- ❌ 改 `package.json` 依赖
- ❌ 复制字体文件
- ❌ 改 beta agent 文件（**M1 修复**：alpha 改）

明确要做的：
- ✅ 发 send_message（协调）
- ✅ 监控进度（记录到 04-progress.md）
- ✅ 准备 Phase 3 Strapi 数据契约（M9 修复）
- ✅ 沉默 2 小时告警 alpha（M5 修复）

## §8 关联任务

| 任务 | 状态 | 下一步 |
|---|---|---|
| **MVP 跑通**（本任务） | v0.2 方案完成 | 等 alpha 改 agent + gamma 发 send_message |
| **git push** | v0.2 留底 + 15 条 edge case 待 v0.3 | 等 MVP 跑通 + 用户指令 |
| **Strapi 设计稿** | 11 个文件已就位 | P1 启动（等用户指令） |
| **Phase 3 联调** | 未启动 | 等 MVP + Strapi 部署完 |

## §9 4 步循环状态

- Step 1 v0.1：✅
- Step 2 review：✅
- Step 3 v0.2（本文件）：✅ 完成
- Step 4 再 review：⏳
