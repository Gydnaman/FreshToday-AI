# Step 2 — BMad Review 走读 git push 方案 v0.1（15 条 finding）

> **review 模式**：adversarial，cynical，假设方案有问题
> **review 时间**：2026-07-03 11:27
> **review 对象**：方案 v0.1（在 chat 里发给用户，未落盘）
> **review 结论**：15 条 finding，8 项高/中严重度必须修，7 项低严重度记录

## Review 输入

方案 v0.1 摘要（v0.1 在 chat 中已发）：
- 目标：把 `d:\HelpCentreRebuild\front-oversea-help-center-pc\` git push 到 GitLab
- 远端：`https://gitlab.fabigbig.com/oversea/HelpCentreRebuild-Nuxt`
- Token：`<GITLAB_TOKEN_REDACTED>`（chat 泄露，用户决定不撤销）
- 范围：只 push Nuxt 子目录，不 push 根目录
- 时机：等 beta 跑通 Nuxt 后

## 15 条 Finding

### F1 — 🔴 高
**未说 git config user.name/user.email 怎么设**
首次 commit 必填字段，缺失则 git 拒绝；用户在公司机器上可能是不同身份。
**修复位置**：v0.2 §3 Step 2.2

### F2 — 🔴 高
**没说 GitLab 默认分支是 main 还是 master**
`git push -u origin main` 失败回退 `master` 是常见坑。
**修复位置**：v0.2 §3 Step 1.3 用 `git ls-remote --symref` 探测

### F3 — 🔴 高
**没说 `front-oversea-help-center-pc/.gitignore` 模板**
`node_modules/`、`.nuxt/`、`.output/`、`.env`、`.env.local`、`dist/` 不在 .gitignore 会污染仓库（几百 MB）。
**修复位置**：v0.2 §4 完整 .gitignore 模板

### F4 — 🟡 中
**没说首次 commit 的 message 规范**
**修复位置**：v0.2 §3 Step 2.5 约定 Angular 风格

### F5 — 🟡 中
**没说 README 必填项**
SPEC Phase 1 验收第 1 项要求 README 写清启动命令。
**修复位置**：v0.2 §5 README 必填 7 段

### F6 — 🟡 中
**未考虑 GitLab "Push rules" / "Mirroring"**
公司 GitLab 可能配了 pre-receive hook 拒绝大文件 / 强制签名。
**修复位置**：v0.2 §3 Step 1.2 先查 GitLab UI 设置

### F7 — 🟡 中
**没说"先在 GitLab UI 创建空仓库"还是"git push 自动建"**
GitLab 不会自动建仓，必须先在 UI 建。
**修复位置**：v0.2 §3 Step 1.1

### F8 — 🟡 中
**GitLab token 明文用 chat 不撤销**
按 07-api-token.md 强规则"不存任何 token 字符串到仓库"，但 chat 历史 = 文件，token 已泄露；用户说"先不管"=已知风险接受。
**修复位置**：v0.2 §6 风险承认 + 后续补救建议

### F9 — 🟡 中
**方案 v0.1 没考虑"dry-run"步骤**
`git push --dry-run` 可提前看到要 push 的内容（防止误推整个根目录）。
**修复位置**：03 runbook Step 4.1

### F10 — 🟡 中
**没说 push 失败回滚策略**
第一次 push 半成功（main 推上去了，但有敏感文件），怎么 reset。
**修复位置**：06 三档回滚策略

### F11 — 🟢 低
**没说 .gitignore_global**
公司机器有全局 .gitignore_global 的话行为可能不同。
**修复位置**：04 checklist §3

### F12 — 🟢 低
**没说 lock 依赖版本**
`package.json` 是否带 `engines` 字段。
**修复位置**：v0.2 §4 package.json engines

### F13 — 🟢 低
**未考虑 LFS**
Nuxt 工程未来可能有大字体/大图片。
**修复位置**：v0.2 §7 暂不启用，watch 列表

### F14 — 🟢 低
**没说 push 完做什么**
**修复位置**：05 push 后 5 步验证

### F15 — 🟢 低
**没说 `git push --set-upstream` 失败时的替代方案**
SSH key 配置？
**修复位置**：v0.2 §8

## 累计 review finding 统计（跨 3 个方案）

- P0 Strapi 第一轮：15 条
- P0 Strapi 第二轮：12 条
- **本轮（git push 方案）：15 条**
- **总计：42 条 finding**

## 高/中严重度必须修

F1, F2, F3, F6, F7, F8, F9, F10 共 8 项必须在 v0.2 中修。

## Step 2 完成判定

- ✅ 至少 10 条 finding（实际 15 条）
- ✅ 高/中严重度列出
- ✅ 修复位置对应到 v0.2 章节
- ✅ 累计 42 条 finding 跨 3 个方案有统计

**进入 Step 3**：升级方案 v0.1 → v0.2（见 `02-proposal-v0.2.md`）。
