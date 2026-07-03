# Git Push Nuxt 工程方案 v0.2

> v0.1 在 chat 中已发（未落盘）；本 v0.2 按 `01-review.md` 15 条 finding 升级
> 配套：`03-runbook.md`（执行命令）+ `04-pre-push-checklist.md`（14 项勾选）+ `05-post-push-verify.md`（5 步验证）+ `06-rollback.md`（三档回滚）

## §1 目标

把 `d:\HelpCentreRebuild\front-oversea-help-center-pc\`（Nuxt 4 工程，等 beta 跑通）git push 到 `https://gitlab.fabigbig.com/oversea/HelpCentreRebuild-Nuxt` 仓库。

## §2 范围

**In scope**：仅 `front-oversea-help-center-pc/` 一个子目录，含 Nuxt 工程文件 + .gitignore + README.md + .env.example（**不含 .env**）

**Out of scope**（不 push）：
- `d:\HelpCentreRebuild\` 根目录其它子目录
- `notasign-help-center-dev-package/`（设计资源版权）
- `superpowers/`（脚手架）
- `_bmad/`（团队规则）
- `nuxt-ui-docs-template/`（模板源）
- `docs/help-center-spec/`（Strapi 设计稿，留本地）
- `.codebuddy/`（IDE 状态）
- `帮助中心前台与内容管理体系建设-1.17.0.md`、`HelpCentreRebuildWorkflow.md`（PRD / 流程内部）

**任何文件级 .env / .env.local / config.local 都不 push**

## §3 实施步骤（升级版）

### Step 1 — GitLab UI 准备工作（**用户执行**，不在我工作区）

**1.1 手动建空仓**（F7 修复）
- 浏览器打开 `https://gitlab.fabigbig.com/overseas/HelpCentreRebuild-Nuxt`
- 点击 "New project" → "Create blank project"
- Project name: `HelpCentreRebuild-Nuxt`
- Project URL: `oversea/HelpCentreRebuild-Nuxt`
- Visibility Level: **Private**（公司内部）
- ✅ Initialize repository with a README：**不勾**（避免和本地 commit 冲突）
- 点击 "Create project"

**1.2 查 GitLab Push rules**（F6 修复）
- 进入项目 → Settings → Repository → Push rules
- 检查：是否有最大文件大小限制 / 是否要求 commit 签名 / 是否要求 linear history
- 记录到 `04-pre-push-checklist.md` Step 1.4

**1.3 探测默认分支**（F2 修复）
- 用户在 PowerShell 跑：
  ```powershell
  git ls-remote --symref https://gitlab.fabigbig.com/overseas/HelpCentreRebuild-Nuxt.git HEAD
  ```
- 期望输出：`ref: refs/heads/main HEAD` 或 `ref: refs/heads/master HEAD`
- 记录到 `04-pre-push-checklist.md` Step 1.3

### Step 2 — 本地准备（在 `front-oversea-help-center-pc/` 内）

**2.1 cd 到 Nuxt 子目录**（**关键**：不是根目录）

**2.2 git config user.name/email**（F1 修复）
```powershell
git config user.name "李汉通"  # 或公司花名
git config user.email "lihantong@company.com"  # 公司邮箱
```
- 验证：`git config --get user.name` 和 `git config --get user.email`

**2.3 检查 .gitignore_global**（F11 修复）
```powershell
git config --get core.excludesfile
# 如果有输出，记下路径；后续 push 后如发现莫名文件被忽略，先看这个
```

**2.4 验证 .gitignore 完整性**（F3 修复）

**2.5 准备 README 必填 7 段**（F5 修复）—— 见 §5

**2.6 准备 package.json `engines` 字段**（F12 修复）—— 见 §4.2

**2.7 git init**
```powershell
git init
git branch -M main  # 强制本地主分支名 = main
```

**2.8 首次 commit**（F4 修复：commit message 规范）
- 约定用 Angular 风格（与 GitLab 默认一致）
- 首次 commit 建议：
  - `feat: initial Nuxt 4 scaffold with help-center components`
  - 或 `chore: initial commit from local dev`
- **不**在 message 里写任何 token / 内部 URL / 内部账号

### Step 3 — 关联远端

```powershell
git remote add origin https://gitlab.fabigbig.com/overseas/HelpCentreRebuild-Nuxt.git
git remote -v  # 验证
```

### Step 4 — Dry-run + 真正 push（见 `03-runbook.md`）

- **Step 4.1** Dry-run（**必先做**）
- **Step 4.2** 真正 push
- **Step 4.3** unset env

### Step 5 — push 后验证（见 `05-post-push-verify.md`）

5 步：UI 验证 → 仓库大小 → 敏感扫描 → CI → 通知

## §4 .gitignore 完整模板（Nuxt 4 + Nuxt UI 4 + 标准）

```gitignore
# ===== Nuxt =====
.nuxt
.nitro
.cache
.output
.data
dist

# ===== Node =====
node_modules
.pnp
.pnp.js
.npm
.yarn

# ===== Env =====
.env
.env.*
!.env.example
.env.local
.env.*.local

# ===== Logs =====
logs
*.log
npm-debug.log*
yarn-debug.log*
yarn-error.log*
pnpm-debug.log*
lerna-debug.log*

# ===== IDE =====
.idea
.vscode
!.vscode/settings.json
!.vscode/extensions.json
*.swp
*.swo

# ===== OS =====
.DS_Store
Thumbs.db
ehthumbs.db
Desktop.ini

# ===== Tests =====
coverage
.nyc_output

# ===== Misc =====
*.local
.history
```

## §4.2 package.json 必填 engines 字段（F12 修复）

```json
{
  "engines": {
    "node": ">=20.0.0",
    "pnpm": ">=9.0.0"
  },
  "packageManager": "pnpm@9.0.0"
}
```

（与 SPEC §3.1 Nuxt 4.4.8 + Nuxt UI 4.9 + Tailwind 4.3 锁版本要求一致）

## §5 README 必填 7 段（F5 修复）

```markdown
# Nota Sign Help Center Frontend (Nuxt 4)

> 替换现有 VitePress Help Center (support.notasign.com)
> SPEC: `d:\HelpCentreRebuild\帮助中心前台与内容管理体系建设-1.17.0.md` §2.2

## 1. 技术栈

- Nuxt 4.4.8 + Nuxt UI 4.9 + Tailwind 4.3 + @nuxt/content 3.14
- Node >= 20, pnpm >= 9
- 内容源：Strapi 5（UAT `https://cms.uat.notasign.com`）

## 2. 启动

```bash
pnpm install
cp .env.example .env  # 填 NUXT_STRAPI_TOKEN 等
pnpm dev  # http://localhost:3000
```

## 3. 构建

```bash
pnpm build
pnpm preview
```

## 4. 目录结构

（按 SPEC §2.2 app/ server/ i18n/ ... 列出关键目录）

## 5. 依赖版本

（抄 package.json 关键版本号）

## 6. 部署

（与 `support.notasign.com` 同部署方式）

## 7. 联系方式

Nota Sign 前端团队 / alpha@notasign.com
```

## §6 Token 风险承认（F8 修复）

**已知风险**：`<GITLAB_TOKEN_REDACTED>` 已在 chat 历史泄露，用户决定**不撤销**继续使用。

**缓解措施**：
- 仅在 PowerShell 进程 env 一次性使用，完即 `Remove-Item Env:GITLAB_TOKEN`
- **不**写进 .git/config（不持久化）
- **不**写进任何 commit / 文档 / .env
- push 后**建议**用户在空闲时去 GitLab UI Revoke 旧 token + 重新 Generate（不阻塞本任务）

**事故预案**：如果 push 后发现 token 已被滥用，立即走 `06-rollback.md` 严禁档。

## §7 LFS（F13 修复）

**本期不启用**。Nuxt 工程主要是代码 + lockfile + .gitignore，预期 < 2 MB。

**Watch 列表**：
- 如果未来引入大字体（> 1 MB 的 .woff2）→ 启用 LFS
- 如果未来有大背景图（> 5 MB）→ 启用 LFS
- 启用方法：在 `.gitattributes` 加 `*.woff2 filter=lfs diff=lfs merge=lfs -text`

## §8 SSH 备选方案（F15 修复）

如果 HTTPS + token 反复失败，备选 SSH：

```powershell
# 1. 生成 SSH key
ssh-keygen -t ed25519 -C "lihantong@company.com"
# 2. 复制公钥到 GitLab UI → Settings → SSH Keys
cat ~/.ssh/id_ed25519.pub
# 3. 测试
ssh -T git@gitlab.fabigbig.com
# 4. push 用 SSH URL
git remote set-url origin git@gitlab.fabigbig.com:overseas/HelpCentreRebuild-Nuxt.git
git push -u origin main
```

## §9 实施依赖

| 依赖 | 状态 |
|---|---|
| `front-oversea-help-center-pc/` 存在 | ⏳ beta 跑通 |
| `pnpm dev` 本地可启动 | ⏳ beta 验证 |
| `pnpm-lock.yaml` 生成 | ⏳ beta |
| GitLab UI 手动建仓 | ⏳ 用户 |
| GitLab token 仍可用（不撤销） | ✅ 用户决定 |

## §10 验收

- [ ] 14 项 pre-push checklist 全勾（见 `04-pre-push-checklist.md`）
- [ ] Dry-run 输出符合预期
- [ ] 真正 push 成功
- [ ] 5 步 post-push verify 通过
- [ ] 通知 alpha + beta
- [ ] GitLab UI 无敏感文件、无超大文件
