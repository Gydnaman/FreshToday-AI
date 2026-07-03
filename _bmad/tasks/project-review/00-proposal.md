# 项目评审方案 — FreshToday-AI / GreenBite

> **日期**：2026-07-03
> **方法**：BMAD（adversarial-review + edge-case-hunter + code-review）+ Superpowers（verification-before-completion + systematic-debugging）
> **范围**：全项目静态评审（代码 + 配置 + 文档一致性 + 安全）
> **约束**：vendor 目录不存在，无法跑测试——所有"测试通过率"声明标记为"未验证"

## 评审维度

1. **文档与代码一致性**（README vs 实际代码）
2. **安全审查**（webhook 验签、认证、支付）
3. **配置完整性**（.env / docker-compose / composer / package）
4. **架构与代码质量**（状态机、Service 分层、Controller）
5. **测试可信度**（声明 vs 证据）

## 评审输出

- `01-findings.md` — 按严重度分级的 findings 报告
