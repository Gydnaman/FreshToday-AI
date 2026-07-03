# 三档回滚策略

> push 后发现问题的回退方案。按严重度分三档。

## 轻档：仓库小且无敏感数据，仅有冗余文件

**场景**：push 上去了，但发现多了几个无用文件，无敏感数据

```powershell
# 1. 本地删除
cd "d:\HelpCentreRebuild\front-oversea-help-center-pc"
git rm --cached <unwanted-files>
# 2. 加 .gitignore
# 编辑 .gitignore 加上 <unwanted-files>
# 3. 提交 + 推送
git add .gitignore
git commit -m "chore: add .gitignore entries"
$env:GITLAB_TOKEN = "<GITLAB_TOKEN_REDACTED>"
git push
Remove-Item Env:GITLAB_TOKEN
```

**安全**：✓ 不动历史 commit，其它人 pull 不会破坏

## 中档：泄漏了非关键敏感文件（如 .env.local、build artifacts）

**场景**：发现 push 出去含 `.env.local`、`node_modules/` 等

```powershell
# 1. 本地彻底删除
cd "d:\HelpCentreRebuild\front-oversea-help-center-pc"
git rm -r --cached .
echo "node_modules/" >> .gitignore
echo ".env" >> .gitignore
echo ".env.local" >> .gitignore
git add .gitignore
git commit -m "fix: remove sensitive files from history"
# 2. 用 git-filter-repo 清理历史（如果想彻底）
pip install git-filter-repo
git filter-repo --path node_modules --invert-paths
# 3. force push（注意：这会重写历史！）
$env:GITLAB_TOKEN = "<GITLAB_TOKEN_REDACTED>"
git push origin --force --all
Remove-Item Env:GITLAB_TOKEN
```

**中危**：force push 会**重写 commit hash**，已 clone 的成员需要重新 clone

**警告**：本档执行前应先告知 alpha（team lead）+ beta（可能已 clone）

## 严禁档：泄漏了关键 token（如 CMS API token / 数据库密码）

**场景**：发现 push 出去含 `glpat-...`、`DATABASE_URL=...` 等

**绝对不能只靠 git 回滚**——commit 历史在任何 clone 上都看得到

**正确做法**：
1. **立即去源头 Revoke**：
   - GitLab token：`https://gitlab.fabigbig.com/-/user_settings/personal_access_tokens` → Revoke
   - CMS token：在 Strapi Admin → API Tokens → Revoke
   - 数据库密码：找运维
2. **生成新 token**（不通过 chat，单独发给用户）
3. **本地历史清理**（同中档 force push）
4. **审计日志**：在 GitLab UI → Activity 查看 token 泄漏后是否有异常 push
5. **通报 alpha + 团队**：安全事件，按公司流程上报

## 严禁做的事

- ❌ `git push --force` 之前**不**告知团队（会覆盖他人 commit）
- ❌ 把 token 写进 commit message（即使后面 reset 也留痕）
- ❌ 把 token 写进 README / 文档（即使后面删除 commit 也留痕）
- ❌ 假设"反正没人看就不撤销 token"（token 泄漏 = 任何看 chat 的人都能用）

## 验证回滚成功

```powershell
# 远端应不再含敏感文件
git archive origin/main | tar -t | grep -E "(\.env$|\.env\.local$|node_modules|\.nuxt|\.output)" || echo "OK: no sensitive files"
# 期望：输出 "OK: no sensitive files"
```

如果还有 → 中档未彻底，转严禁档处理。
