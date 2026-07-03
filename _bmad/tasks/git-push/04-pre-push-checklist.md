# Push 前 14 项勾选清单

> 每项必勾，缺一项不上推。

## §1 准备（4 项）

- [ ] 1. `front-oversea-help-center-pc/` 目录存在且 `pnpm dev` 可启动
- [ ] 2. `pnpm-lock.yaml` 已生成（依赖锁版本）
- [ ] 3. 公司 GitLab 仓库 `HelpCentreRebuild-Nuxt` 已 UI 建空仓（默认分支 = main）
- [ ] 4. GitLab Push rules 已查（无强制签名 / 大文件限制 / 强制 MR）

## §2 边界（4 项）

- [ ] 5. `front-oversea-help-center-pc/.gitignore` 含 `node_modules`、`.nuxt`、`.output`、`.env`、`.env.*`、`dist`
- [ ] 6. `front-oversea-help-center-pc/.env` **不存在**或**已被 .gitignore 排除**
- [ ] 7. `front-oversea-help-center-pc/.env.example` 存在（**可以** push）
- [ ] 8. `front-oversea-help-center-pc/README.md` 含 7 段必填（启动 / 构建 / 依赖版本 / Node / pnpm / 目录结构 / 联系方式）

## §3 Git 配置（3 项）

- [ ] 9. `git config --global user.name "公司姓名"` 已设
- [ ] 10. `git config --global user.email "公司邮箱"` 已设
- [ ] 11. `git config --list --show-origin | grep -i ignore` 已查（无全局 .gitignore_global 干扰，或已确认无误）

## §4 Dry-run（3 项）

- [ ] 12. `git ls-remote --symref ... HEAD` 返回 main（不是 master）
- [ ] 13. `git push --dry-run ...` 输出**不含**敏感文件路径
- [ ] 14. 输出文件大小 < 5 MB（Nuxt 工程应该 < 2 MB）

## 全勾选后

→ 跑 `03-runbook.md` Step 4.2 真正 push
→ 跑 `05-post-push-verify.md` 5 步验证
