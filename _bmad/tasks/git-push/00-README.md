# Git Push Nuxt 工程到 GitLab —— 方案包

> **本目录是方案 + 实施配套**，**改前必须有**（2026-07-03 用户硬要求 4 步循环）。
> **状态机**：Step 1 方案 ✅ → Step 2 review ✅ → Step 3 v0.2（本包）⏳ → Step 4 实施后再 Review

## 文件清单

| # | 文件 | 用途 |
|---|---|---|
| 00 | `00-README.md`（本文件） | 索引 + 决策记录 + 4 步循环状态 |
| 01 | `01-review.md` | adversarial review 15 条 finding |
| 02 | `02-proposal-v0.2.md` | 升级方案（修 8 项高/中严重度） |
| 03 | `03-runbook.md` | PowerShell 命令清单（用户运行时执行） |
| 04 | `04-pre-push-checklist.md` | push 前 14 项勾选清单 |
| 05 | `05-post-push-verify.md` | push 后 5 步验证 |
| 06 | `06-rollback.md` | 三档回滚策略 |

## 4 步循环状态

- ✅ **Step 1（方案 v0.1）**：chat 发出，未落盘（用户说"先不管"后，我决定落盘到本目录）
- ✅ **Step 2（Review）**：15 条 finding，8 项高/中严重度 + 7 项低严重度（见 `01-review.md`）
- ⏳ **Step 3（v0.2 实施）**：当前正在做，按 review 升 v0.2（见 `02-proposal-v0.2.md`）+ 产出 runbook / checklist
- ⏳ **Step 4（再 Review）**：实施后走读，确认 v0.2 是否真的修对了，且没引入新问题

## 升级后 v0.1 → v0.2 修复摘要

| 严重度 | Finding | v0.2 修复位置 |
|---|---|---|
| 🔴 高 F1 | git config user.name/email | 02 §3 Step 2.2 |
| 🔴 高 F2 | GitLab 默认分支 | 02 §3 Step 1.3 |
| 🔴 高 F3 | .gitignore 模板 | 02 §4 + 完整模板 |
| 🟡 中 F4 | commit message 规范 | 02 §3 Step 2.5 |
| 🟡 中 F5 | README 必填项 | 02 §5 + 7 段必填 |
| 🟡 中 F6 | GitLab Push rules | 02 §3 Step 1.2 |
| 🟡 中 F7 | UI 手动建仓 | 02 §3 Step 1.1 |
| 🟡 中 F8 | token 泄露 | 02 §6 风险承认 |
| 🟡 中 F9 | dry-run | 03 runbook Step 4.1 |
| 🟡 中 F10 | 失败回滚 | 06 三档策略 |
| 🟢 低 F11 | .gitignore_global | 04 checklist §3 |
| 🟢 低 F12 | lock 依赖版本 | 02 §4 package.json engines |
| 🟢 低 F13 | LFS | 02 §7 暂不启用 |
| 🟢 低 F14 | push 后做什么 | 05 五步验证 |
| 🟢 低 F15 | SSH 备选 | 02 §8 |

## 关键决策（与 v0.1 一致 + 升级）

### 仓库命名
- **远端**：`HelpCentreRebuild-Nuxt`（用户原话）
- **本地**：`front-oversea-help-center-pc/`（SPEC 命名）
- 接受不一致，CI/CD 映射

### Token
- **不撤销** `<GITLAB_TOKEN_REDACTED>`（用户决定接受风险）
- **不写进任何文件**
- **PowerShell 进程 env** 一次性使用，完即 unset
- BMad override 强规则"不放 token 明文"在本方案中**保留**（仅 chat 历史泄露，仓库 / 文档 / .env 都不写）

### Push 边界
- **只 push `front-oversea-help-center-pc/`** 一个子目录
- 该子目录 `git init`（不是根目录 `git init`）

## 范围严格控制

### In scope
- GitLab UI 手动创建空仓
- `front-oversea-help-center-pc/.gitignore` 完整模板（Nuxt 4 标准）
- `front-oversea-help-center-pc/README.md` 必填 7 段
- `git init` → `git add` → `git commit` → `git remote add` → `git push --dry-run` → `git push`
- push 后 5 步验证

### Out of scope
- ❌ 不读 `C:\Users\lihantong\.config\nota-sign-cms` 文件内容
- ❌ 不 push `d:\HelpCentreRebuild\` 根目录的其它子目录
- ❌ 不把任何 token / .env / 配置加进 git
- ❌ 不撤销/不重新生成 GitLab token
- ❌ 不下载/不处理远端媒体

## 4 步循环当前进度

- Step 1：✅
- Step 2：✅
- Step 3：⏳ 进行中（写 01-review.md / 02-proposal-v0.2.md / 03-runbook.md / 04-checklist.md / 05-verify.md / 06-rollback.md）
- Step 4：⏳ 等 Step 3 完成后走读
