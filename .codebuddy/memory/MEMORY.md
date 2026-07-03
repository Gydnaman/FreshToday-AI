# FreshToday-AI / GreenBite 项目长期记忆

## 项目基本信息
- **项目名**：GreenBite（仓库目录名 FreshToday-AI）
- **技术栈**：Laravel 12 / PHP 8.2 / SQLite(dev) / MySQL 8(prod) / Tailwind CSS 4 / Sanctum
- **业务**：香港本地有机农产品电商 + AI 每日菜单（Gemini/OpenAI/DeepSeek 三选一）
- **支付**：Stripe（已实现验签）+ PayMe（验签待 Sprint 2）+ Alipay HK（待 Sprint 2）

## 用户偏好
- 在 main 分支直接做，不用 worktree
- 用 Subagent-driven 方式执行（每 Task 派 subagent review）
- BMAD + Superpowers 方法论作为项目约束（已存入持久记忆）

## 测试基线（2026-07-03）
- 74 passed / 316 assertions / 0 failed
- 测试文件：`_bmad/tasks/project-review/02-test-baseline.md`

## 已知技术债务
- I-3：Web CheckoutController 把 PAT 放 HTML hidden（XSS 风险）
- I-5：OrderService refund 异常时状态已写（NEW-P2-10）
- I-6：AiMenu 限流 TTL 续期（NEW-P2-08）
- Minor-3：WebhookFlowTest sign() 与 postJson JSON 编码隐式耦合
- Minor-4：docker-compose version: '3.9' 已废弃
- Minor-5：PayMe webhook_secret 配置不一致（Controller 检查 api_key 非 webhook_secret）
