# Runbook — PowerShell 命令清单

> **本文件是用户执行**。每条命令单独可粘贴。
> 假设：GitLab UI 已建空仓 `HelpCentreRebuild-Nuxt`（Step 1.1 完成）
> 假设：`front-oversea-help-center-pc/` 已存在（beta P0 跑通）

## Step 4.1 — Dry-run（必先做，F9 修复）

```powershell
cd "d:\HelpCentreRebuild\front-oversea-help-center-pc"

# 检测默认分支（F2 修复）
git ls-remote --symref https://gitlab.fabigbig.com/oversea/HelpCentreRebuild-Nuxt.git HEAD

# Dry-run：只看不推
$env:GITLAB_TOKEN = "<GITLAB_TOKEN_REDACTED>"
git push --dry-run --set-upstream https://oauth2:$env:GITLAB_TOKEN@gitlab.fabigbig.com/oversea/HelpCentreRebuild-Nuxt.git main
Remove-Item Env:GITLAB_TOKEN
```

**Dry-run 期望**：
- 看到一长串 `refs/heads/main:refs/heads/main [up to date]` 或 `new branch`
- **不要看到** `node_modules/`、`.nuxt/`、`.output/`、`.env` 等

## Step 4.2 — 真正 push

```powershell
cd "d:\HelpCentreRebuild\front-oversea-help-center-pc"
$env:GITLAB_TOKEN = "<GITLAB_TOKEN_REDACTED>"
git push --set-upstream https://oauth2:$env:GITLAB_TOKEN@gitlab.fabigbig.com/oversea/HelpCentreRebuild-Nuxt.git main
Remove-Item Env:GITLAB_TOKEN
```

**期望**：`Writing objects: 100% (XX/XX)`, `remote: ... To https://gitlab.fabigbig.com/oversea/HelpCentreRebuild-Nuxt.git`

## Step 4.3 — push 后立即 unset + 不撤销 token

```powershell
# 确认 env 已清
Get-ChildItem Env:GITLAB_TOKEN  # 期望：找不到
```

## 注意

- **不**在 PowerShell 之外的任何地方贴 token（包括 commit message、PR description、聊天）
- **不**用 `git config --global credential.helper store` 持久化
- **不**写进 .git/config

## 失败处理

| 错误 | 原因 | 解决 |
|---|---|---|
| `Permission denied (publickey)` | SSH key 未配 | 改用 HTTPS + token（本 runbook 路径） |
| `HTTP 403 Forbidden` | token 无 push 权限 | 检查 token scope（需 `api` 或 `write_repository`） |
| `repository not found` | 仓库未建 / 路径错 | 回 Step 1.1 在 GitLab UI 建仓 |
| `refusing to merge unrelated histories` | 远端已有 commit | `git pull origin main --allow-unrelated-histories` |
| push 出去后看到敏感文件 | .gitignore 不全 | 见 `06-rollback.md` 中档策略 |
