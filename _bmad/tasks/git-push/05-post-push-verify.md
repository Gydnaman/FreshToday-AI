# Push 后 5 步验证

> push 完必跑，发现问题立即走 `06-rollback.md`

## Step 5.1 — GitLab UI 验证

- 浏览器打开 `https://gitlab.fabigbig.com/oversea/HelpCentreRebuild-Nuxt`
- 期望：
  - main 分支存在
  - 文件列表显示 Nuxt 工程目录结构
  - **看不到** `node_modules/`、`.nuxt/`、`.output/`、`.env` 等
  - commit message 显示 `feat: initial Nuxt 4 scaffold`（或约定的 commit message）

## Step 5.2 — 仓库大小

- GitLab UI → Project → 仓库信息
- 期望：< 5 MB（Nuxt 工程应该 < 2 MB）
- 如果 > 5 MB → 大概率误推了 `node_modules/`，**立即走回滚**

## Step 5.3 — 敏感文件扫描

```powershell
cd "d:\HelpCentreRebuild\front-oversea-help-center-pc"
# 列出远端实际文件
git ls-remote --refs origin main  # 仅看 refs
git archive origin/main | tar -t  # 列出远端 main 实际文件
```

期望输出**不含**：
- `**/.env`（非 .env.example）
- `**/node_modules/**`
- `**/.nuxt/**`
- `**/.output/**`
- `**/*.log`
- `**/secrets.json` / `**/credentials.json`

## Step 5.4 — CI/CD（如有）

- GitLab UI → CI/CD → Pipelines
- 如果仓库配了 CI，跑通即可
- 如果没配 CI，跳过

## Step 5.5 — 通知 alpha / beta

- send_message 给 alpha（team lead）："Nuxt 工程已 push 到 `HelpCentreRebuild-Nuxt`，commit `XXXXX`"
- 等 beta 确认能 clone + `pnpm install` + `pnpm dev` 跑通
- 通知 zeta：migration 计划可基于此工程展开

## 发现问题

| 现象 | 行动 |
|---|---|
| 敏感文件泄漏 | 立即走 `06-rollback.md` 中档策略 |
| 仓库过大 | 走 `06-rollback.md` 中档策略 + 检查 .gitignore |
| CI 失败 | 跑第二次 push fix（**不** force push） |
| 团队成员 clone 失败 | 检查 .gitattributes / LFS 配置 |
