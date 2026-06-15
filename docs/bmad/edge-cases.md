# GreenBite 边界情况清单（Edge Cases）

> 本文件由 `bmad-review-edge-case-hunter` skill 思路驱动，覆盖 GreenBite（FreshToday-AI）项目 5 大类、**30+ 个边界情况**。  
> 每个边界给出：**触发条件 / 影响范围 / 检测方法 / 处理策略**。  
> 关联文档：`test-strategy.md`、`e2e-scenarios.md`。

---

## 元信息（Metadata）

| 字段 | 值 |
| --- | --- |
| 文档名称 | GreenBite 边界情况清单（Edge Case Catalog） |
| 创建人 | qa-agent |
| 版本 | v1.0 |
| 创建日期 | 2026-06-12 |
| 分类维度 | 数据 / 状态 / 用户行为 / 第三方 / i18n & a11y |
| 关联 Skill | `bmad-review-edge-case-hunter`、`bmad-qa-generate-e2e-tests` |
| 评审节奏 | 每次 PR 触发 Edge Case Hunter 自动 review + 每次 Sprint 末人工复核 |

---

## 边界情况总览表

| 分类 | 数量 | 编号区间 |
| --- | --- | --- |
| 数据类 | 7 | D-01 ~ D-07 |
| 状态类 | 7 | S-01 ~ S-07 |
| 用户行为类 | 6 | U-01 ~ U-06 |
| 第三方类 | 7 | T-01 ~ T-07 |
| i18n & a11y 类 | 7 | I-01 ~ I-07 |
| **合计** | **34** | — |

---

## 一、数据类（Data Edge Cases）

### D-01：空值 / Null

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 用户注册时 `name=''`、`email=null`、`password=null`；商品 `carbon_footprint=null`；订单 `total_price=null` |
| 影响范围 | 必填校验失败、累计 carbon 跳过该商品、`total_price` 默认 0、报表 NPE |
| 检测方法 | PHPUnit Feature：post `/register` 漏字段、Factory `Product::create(['carbon_footprint'=>null])` + 下单后断言 dashboard 行为 |
| 处理策略 | 表单层 `required` 校验；Model `default` 值；Service 层 `?? 0` 兜底；日志 warning 但不阻断 |

### D-02：超长字符串

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 用户 `name` 输入 5000 字符；商品 `description` 100KB；AI 菜单 10MB；评论 1MB |
| 影响范围 | DB 截断、UI 撑爆、CSS 溢出、Stripe description 100 字符上限 |
| 检测方法 | Feature 测：`Str::random(5000)`、`mb_strlen > 65535`；Dusk 截图检查无横向滚动 |
| 处理策略 | Laravel `max:255` / `max:1000` 校验；`Str::limit()` 截断；Stripe token 化 |

### D-03：特殊字符 / XSS

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 用户输入 `<script>alert(1)</script>`、`"><img src=x onerror=alert(1)>`、SQL 片段 `' OR 1=1 --` |
| 影响范围 | XSS 攻击、CSRF 误判、SQL 注入 |
| 检测方法 | Feature 注入 OWASP CheatSheet payload；PHPUnit 用 `try{DB::select("...")}catch`；Playwright 监听 `dialog` |
| 处理策略 | Blade `{{ }}` 自动转义；`v-text` 不用 `v-html`；Eloquent 参数化；CSP header；DOMPurify 仅对富文本 |

### D-04：Emoji 与四字节字符

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 用户名 `🍎🍏小明🌱`；商品名称 `有機🥬甘藍`；菜单返回 emoji 多行 |
| 影响范围 | MySQL `utf8mb3` 截断 → 需 `utf8mb4`；`strlen` vs `mb_strlen` 计算错；UI 渲染宽度 |
| 检测方法 | Migration 检查 `charset=utf8mb4`、`strlen($name) !== mb_strlen($name)`；Playwright 多浏览器截图对比 |
| 处理策略 | DB 默认 `utf8mb4_unicode_ci`；所有字符串函数用 `mb_*`；Laravel 12 默认 `utf8mb4`；UI 字体回退到 `Noto Color Emoji` |

### D-05：Unicode 归一化（Normalization）

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 同一邮箱 `café@test.com` 出现 NFD (`é` = `e\u0301`) 与 NFC 两种形态；用户名用全角／半角混用 |
| 影响范围 | 重复账号绕过、唯一约束失效、搜索不命中 |
| 检测方法 | Feature：分别用 NFC / NFD 注册，断言 422；Factory `Str::of('café')->normalize()` |
| 处理策略 | 写入前 `Normalizer::normalize(Form::NFC)`；MySQL 字段 collation `utf8mb4_unicode_ci`（已归一化） |

### D-06：SQL 注入

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 搜索框输入 `'; DROP TABLE users; --`、URL 注入 `?id=1 OR 1=1` |
| 影响范围 | 数据泄露 / 丢失 |
| 检测方法 | PHPUnit `DB::table('users')->count()` 在攻击前后相等；PHPStan 安全规则；`grep "DB::raw\|DB::statement"` |
| 处理策略 | 全部 Eloquent + 参数绑定；`DB::raw` 仅允许白名单；WAF（Cloudflare）；输入正则白名单 |

### D-07：大数据量 / 性能

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 用户有 1 万个订单；购物车 200 件商品；AI 菜单生成 100 次 |
| 影响范围 | 列表加载慢、N+1 查询、内存爆 |
| 检测方法 | Factory `Order::factory()->count(10000)`；Laravel Debugbar 看查询数；P95 监控 |
| 处理策略 | 分页 + 游标；`with('products')` 防 N+1；Service 缓存；前端虚拟滚动 |

---

## 二、状态类（State Edge Cases）

### S-01：订单状态非法转移

| 字段 | 内容 |
| --- | --- |
| 触发条件 | `pending → refunded` 跳过 `paid`；`canceled → paid` 复活订单；`paid` 直接 → `delivered` 跳过 `shipped` |
| 影响范围 | 财务对账错乱、库存错乱、用户投诉 |
| 检测方法 | Unit 测 `OrderStateMachine::transition()` 全部非法对；Feature 模拟直接调 `PATCH /orders/{id}` |
| 处理策略 | 状态机白名单（`pending→paid→shipped→delivered`，`pending→canceled`）；非法转移抛 `InvalidTransitionException`；事件溯源审计 |

### S-02：并发下单（超卖）

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 商品 `stock=1`，100 用户同时下单；2 设备同时点 pay |
| 影响范围 | 超卖、负库存、退款纠纷 |
| 检测方法 | PHPUnit 并发：`pcntl_fork` 启 10 进程同时下单；最终 `stock >= 0` |
| 处理策略 | `SELECT ... FOR UPDATE` 行锁；唯一约束；`where('stock','>=',qty)->decrement('stock', qty)` 原子操作；乐观锁 `version` 字段；返回 409 让用户重试 |

### S-03：订阅过期未续

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 订阅 `end_date` 过 1 天未续；用户未及时看邮件；卡被换 |
| 影响范围 | 服务中断、用户不知道 |
| 检测方法 | Feature：`Carbon::setTestNow('+1 day')` 后访问 dashboard，断言状态 `expired`、banner 提示 |
| 处理策略 | T-3 / T-1 / T+0 / T+3 邮件 + 站内信；Grace period 3 天仍可访问；过期后降级为非会员视图 |

### S-04：未支付订单超时

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 用户下单后 15 min 未支付 |
| 影响范围 | 库存被占着、商品下架后无法满足新订单 |
| 检测方法 | Feature：下 pending 订单 + time travel 16 min + 触发 scheduled job |
| 处理策略 | `php artisan schedule:run` 每分钟跑；状态 `pending → expired`；库存回滚；用户收到失效通知 |

### S-05：双重扣款

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 用户点击 pay-now 时网络抖，重复提交；浏览器重发 |
| 影响范围 | 重复扣款、退款纠纷 |
| 检测方法 | Feature：模拟 2 次相同 `idempotency_key` 请求；Unit 测 PaymentService 幂等 |
| 处理策略 | `idempotency_key` 唯一索引；前端按钮 loading + disabled；Stripe `Idempotency-Key` header；DB 唯一约束防双写 |

### S-06：取消与退款竞态

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 用户点 cancel 的同时 webhook 来 `charge.refunded` |
| 影响范围 | 双重状态变更、库存回滚 2 次 |
| 检测方法 | PHPUnit 并发模拟；Feature：先 cancel 再 webhook |
| 处理策略 | 状态机 + DB 事务；行锁；webhook 处理时检查当前状态；幂等 token |

### S-07：邮箱验证链接已用 / 重发

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 同一链接点 2 次；过期后点击；重发 5 次 |
| 影响范围 | 验证态错乱、被钓鱼风险 |
| 检测方法 | Feature：点一次 → 200；点第二次 → 410 gone；过期 → 410 |
| 处理策略 | Token 一次性（用后标记）；TTL 60 min；限流：1 min 1 次，1 h 5 次；CSRF token 校验 |

---

## 三、用户行为类（User Behavior Edge Cases）

### U-01：重复点击 / 双击

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 用户连点 5 次 [pay-now]；连点 10 次 [add-to-cart] |
| 影响范围 | 重复订单、库存异常 |
| 检测方法 | Playwright `dblclick`、连击测 |
| 处理策略 | 按钮点击后立即 `disabled` + loading；后端幂等键；CSRF token 单次有效；乐观 UI |

### U-02：刷新页面（F5）

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 用户在下单成功页 F5；表单填写一半 F5 |
| 影响范围 | 表单数据丢失、重复提交（GET 重放） |
| 检测方法 | Playwright `page.reload()`；Feature：POST 路由只接受 GET 跳 |
| 处理策略 | PRG 模式（POST 302 → GET）；表单草稿存 `localStorage`；订单详情用 `GET /orders/{id}`；付款后禁止后退回卡号页 |

### U-03：网络中断

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 用户在支付时断网；移动端进电梯 |
| 影响范围 | 订单状态未知、用户焦虑 |
| 检测方法 | Playwright `context.setOffline(true)`；Network throttling `Slow 3G` |
| 处理策略 | 离线提示 + 指数退避重试；订单结果通过 webhook 而非同步；前端缓存 last-known 订单状态；"Reconnect" 按钮 |

### U-04：浏览器后退 / 前进

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 用户在支付页按 back 回购物车；前进回支付页 |
| 影响范围 | 重复扣款、表单陈旧数据提交 |
| 检测方法 | Playwright `page.goBack()`、`page.goForward()` |
| 处理策略 | 支付页 `Cache-Control: no-store`；`window.history.pushState` 重写；`beforeunload` 警告未保存表单 |

### U-05：跨设备 / 跨标签页登录

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 用户手机和电脑同时登录；A 设备下单，B 设备刷新 |
| 影响范围 | 购物车不一致、订阅状态不一致、Carbon 累计缺 |
| 检测方法 | Feature：2 context 模拟 2 设备；轮询 `GET /api/sync` |
| 处理策略 | Cart 存服务端而非 localStorage；Broadcast 事件同步；订阅状态 30s 内推送；冲突时以服务端为准并提示 |

### U-06：超时 / 离开

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 用户在问卷停留 24h；在购物车停留 7 天 |
| 影响范围 | 库存预留过期、session 失效 |
| 检测方法 | Feature：mock `Carbon::setTestNow('+25h')`；`session.driver` 测 |
| 处理策略 | 问卷草稿 auto-save（每 30s）；Session GC（默认 120 min，可调）；订单 pending 15 min 自动 expired；离线 `localStorage` 兜底 |

---

## 四、第三方类（Third-Party Edge Cases）

### T-01：Gemini API 5xx

| 字段 | 内容 |
| --- | --- |
| 触发条件 | Gemini 返回 500 / 502 / 503；`generativelanguage.googleapis.com` 不可达 |
| 影响范围 | AI 菜单无法生成；用户卡在 dashboard |
| 检测方法 | Playwright `route.fulfill({ status: 503 })`；Unit Mock `Http::fake(['*' => 503])` |
| 处理策略 | **已有** `generateFallbackMenu`；重试 3 次（指数退避 1s/3s/9s）；UI 提示 "AI busy, showing default"；写入 `daily_menus` 标记 `is_fallback=1`；Sentry 报警 |

### T-02：Gemini API 超时

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 请求 30s 无响应；DNS 解析慢 |
| 影响范围 | 用户等待、P95 飙升 |
| 检测方法 | Playwright `route.abort('timedout')`；PHPUnit `Http::fake` 加 delay |
| 处理策略 | `Http::timeout(8)->retry(2, 500)`；前端按钮 loading + 30s 兜底转 fallback；background job 异步重试 |

### T-03：Gemini 限流（429）

| 字段 | 内容 |
| --- | --- |
| 触发条件 | QPS 突增触发 `429 Too Many Requests` |
| 影响范围 | 大量失败 |
| 检测方法 | `Http::fake(['*' => Http::response('', 429)])` |
| 处理策略 | 解析 `Retry-After` 头；队列化请求（`GenerateAiMenuJob`）；客户端展示降级文案；Redis 令牌桶全局限流 10 RPS |

### T-04：Gemini 内容审核拒绝

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 用户偏好含敏感词；返回 `SAFETY` block |
| 影响范围 | 菜单为空 / 报错 |
| 检测方法 | Mock 返回 `{ "candidates": [{ "finishReason": "SAFETY" }] }` |
| 处理策略 | `finishReason !== 'STOP'` 时降级 fallback；Sentry 收集；提示用户重新选择偏好 |

### T-05：Stripe Webhook 重放

| 字段 | 内容 |
| --- | --- |
| 触发条件 | Stripe 同一 event_id 投递 2+ 次（网络重试） |
| 影响范围 | 重复发货、重复扣款、状态错乱 |
| 检测方法 | Feature：相同 payload 调 `/webhook/stripe` 2 次，断言 DB 只更新 1 次 |
| 处理策略 | `stripe_webhook_events` 表 + 唯一约束 `event_id`；Laravel `VerifyCsrfToken` 排除 webhook；签名校验 `Stripe\Webhook::constructEvent` |

### T-06：SMTP 失败

| 字段 | 内容 |
| --- | --- |
| 触发条件 | Mailgun 5xx；配额用尽；SPF/DKIM 失败 |
| 影响范围 | 验证邮件、订单通知发不出 |
| 检测方法 | Feature：`Mail::fake()` 后断言；Mailpit 不接 + 触发发送 |
| 处理策略 | `failed_jobs` 队列重试 3 次；失败入 `dead_letters` 表；后台监控告警；本地落 `storage/logs/mail-fallback.log` 兜底；提供 resend 接口 |

### T-07：CDN 故障

| 字段 | 内容 |
| --- | --- |
| 触发条件 | Cloudflare / 自建 CDN 503；DNS 污染 |
| 影响范围 | 商品图、JS、CSS 加载失败 |
| 检测方法 | Playwright `route.abort`；Lighthouse offline 模式 |
| 处理策略 | `<img onerror>` 占位；Service Worker 缓存壳；图片多 CDN 备份；CSS 关键路径 inline；`Cache-Control: immutable`；Sentry 监控资源 4xx/5xx |

---

## 五、i18n & a11y 类（i18n & Accessibility Edge Cases）

### I-01：RTL（Right-to-Left）语言

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 用户切换 ar / he（v2 范围，v1 仅准备） |
| 影响范围 | 布局镜像错乱、图标方向、双向文本（Bidi） |
| 检测方法 | Playwright `page.emulateMedia` + 注入 `dir=rtl`；截图回归 |
| 处理策略 | Tailwind `rtl:` 变体；`<html dir>`；数字仍 LTR；用 logical properties `ms-/me-` 替 `ml-/mr-`；v1 仅记录，不实施 |

### I-02：繁体 vs 简体

| 字段 | 内容 |
| --- | --- |
| 触发条件 | zh-HK 用户看 zh-CN 文案或反之；香港字形（的→的 vs 𠂉） |
| 影响范围 | 用户觉得「被简体化」或不专业 |
| 检测方法 | Feature：切换语言断言 key 集合 ≥ 95% 完整；Diff 工具对比 `zh-HK.json` vs `zh-CN.json` |
| 处理策略 | **两个独立 JSON 文件**；CI 跑 OpenCC diff 检查覆盖率；术语表维护；避免使用 zh-Hant 兼容兜底 |

### I-03：屏幕阅读器（NVDA / VoiceOver）

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 视障用户用 NVDA / VoiceOver 访问 |
| 影响范围 | 表单无 label、按钮无 aria-label、弹窗未 focus trap |
| 检测方法 | axe-core 自动扫描；`@axe-core/playwright`；手动 NVDA 录屏 |
| 处理策略 | 所有交互元素 `aria-label`；`<form>` 关联 `<label for>`；`role="alert"` 异步消息；live region 公告错误；`tabindex` 顺序符合视觉 |

### I-04：键盘导航

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 用户禁用鼠标；只用 Tab / Enter / Esc |
| 影响范围 | 模态框无法关闭、菜单无法进入、skip links 缺失 |
| 检测方法 | Playwright `page.keyboard.press('Tab')` 全程；axe 测 focus order |
| 处理策略 | 模态框 focus trap + Esc 关闭；自定义控件支持 `Enter`/`Space`；`<a href>` 跳而非 `<div onclick>`；"Skip to main content" link；`:focus-visible` 可见 |

### I-05：色盲与对比度

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 红绿色盲用户看 "订单已取消" 红字；低对比度灰字 |
| 影响范围 | 信息无法识别 |
| 检测方法 | axe 测 contrast ratio ≥ 4.5:1；Chrome DevTools 模拟色盲滤镜 |
| 处理策略 | 不仅靠颜色，配合 icon / 文字；Tailwind 默认调色板已通过；自定义品牌色需跑 axe；提供"高对比度"模式开关 |

### I-06：深色模式

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 用户切 `prefers-color-scheme: dark` |
| 影响范围 | 文字与背景同色、图片过曝、阴影消失 |
| 检测方法 | Playwright `colorScheme: 'dark'`；截图对比；`html.dark` 覆盖 |
| 处理策略 | Tailwind 4 `dark:` 变体；CSS 变量统一；图片准备 `@media (prefers-color-scheme)` 双版本；硬编码颜色走 token |

### I-07：动态字号 / 200% 缩放

| 字段 | 内容 |
| --- | --- |
| 触发条件 | 老年用户浏览器缩放 200%；移动端辅助功能大字体 |
| 影响范围 | 文字截断、按钮溢出、横向滚动 |
| 检测方法 | Playwright `viewport` 配合 CSS `font-size: 200%`；axe zoom test |
| 处理策略 | 容器用 `min-w-0` 允许收缩；按钮 `min-h-44px`；line-clamp 防破版；`overflow-wrap: anywhere`；rem 而非 px |

---

## 六、跨类风险监控（Cross-Cutting Risks）

| 风险 | 涉及边界 | 监控指标 |
| --- | --- | --- |
| 时区错乱 | D-04 / U-06 | 服务端 `APP_TIMEZONE=Asia/Hong_Kong`；前端 `Intl.DateTimeFormat` |
| 浮点精度 | D-07 / S-01 | 全部用 DECIMAL + bcmath；禁止 JS 浮点算钱 |
| 字符集 | D-04 / D-05 | MySQL `utf8mb4`；CSV 导出 BOM |
| 速率限制 | T-01 / T-03 / S-02 | Laravel `RateLimiter` + Redis；监控 429 |
| 可观测性 | 全部 | Sentry 错误、Laravel Telescope、Prometheus 业务指标 |

---

## 七、Edge Case Hunter 自动审查清单（PR Template）

Dev 在 PR 中勾选：

- [ ] 涉及数据：检查空值 / 超长 / XSS / Emoji / 归一化 / SQL 注入
- [ ] 涉及状态机：检查非法转移、并发、过期、双重扣款
- [ ] 涉及用户交互：检查双击 / 刷新 / 断网 / 后退 / 多设备
- [ ] 涉及第三方：检查 5xx / 超时 / 限流 / Webhook 重放 / SMTP / CDN
- [ ] 涉及 i18n：检查 zh-HK/zh-CN 完整、a11y axe 通过、深色模式

QA 用 `bmad-review-edge-case-hunter` 自动跑：

```bash
# 示例：跑数据类边界回归
php artisan test --filter='EdgeCaseDataTest'
npx playwright test --grep "@data|@state|@thirdparty|@i18n"
```

---

## 八、行动闭环

| 阶段 | 责任 | 动作 |
| --- | --- | --- |
| 发现 | Edge Case Hunter / QA | 提单 + 分类 + 严重度 |
| 评估 | QA + Dev | 24h 内归入 P0~P3 |
| 修复 | Dev | 加测试 + 修复 |
| 回归 | QA | 加入对应边界回归套件 |
| 沉淀 | QA | 更新本文件 + 评审 PR template |

---

*文档结束。共 **34 个边界情况**，分布于 5 大类，作为 Sprint 0 末的 Edge Case Hunter 自动审查与 Sprint 3 末的人工复核基线。*
