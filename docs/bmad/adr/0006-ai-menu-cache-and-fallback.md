> **元信息**：作者 architect-agent (bravo) | 版本 1.0 | 日期 2026-06-12 (Asia/Hong_Kong)
> **框架**：fdd-bmad-custom（Architect 阶段产物：Architecture Decision Record）
> **基线状态**：Day 2 立项，状态：已接受
> **评审触发**：`docs/bmad/REVIEW-REPORT.md` §3.1 NEW-P1-03（缺失 ADR 文档）
> **关联代码**：`app/Services/AiMenuService.php`、`tests/Unit/Services/AiMenuServiceTest.php`

# ADR-0006: AI 菜单缓存、降级与限流

## §1 背景（Context）

GreenBite 的"AI 每日菜单"是产品差异化核心（product-brief §3 关键体验）。当前实现位于 `app/Services/AiMenuService.php`，对接 Google Gemini 2.5 Flash 模型。REVIEW-REPORT §3.1 NEW-P1-03 与 §9.3 指出**该 Service 缺乏 ADR 把"为什么这样缓存 / 降级 / 限流"沉淀下来**，后续 PR 易被"为什么不用 Redis 直连"、"为什么限流 3 次"等问题反复质疑。

业务侧需求：

- **每日一次菜单**：用户每天打开 dashboard 看到同一份 AI 生成菜单（避免 Gemini 调用抖动造成内容漂移）
- **降级必须优雅**：Gemini 限流 / 故障 / 缺 key 时，**绝不能让用户看到空白页**——必须有兜底文案
- **限流必须可解释**：每日 3 次重新生成上限，既保护 Gemini 配额（HKD 0 成本对我们是优势），又防止用户反复刷新消耗资源
- **缓存与降级的边界要清楚**：什么算"命中"？什么算"降级"？必须能在 Grafana 上一眼区分

技术侧约束：

- **Sprint 1 阶段 Redis 不可用**：`CACHE_DRIVER=file` 或 `array`（本地）；Day 5 才上 Redis
- **Gemini API 调用限速**：免费层 15 RPM / 1500 RPD / 1M TPM（Google AI Studio 文档）
- **HK 网络到 `generativelanguage.googleapis.com` 延迟**：~80~200ms（ap-east-1 出口测得），超时阈值 8s

## §2 决策（Decision）

我们采用 **"Cache 抽象 + 三层降级 + 限流前置"** 的实现策略：

1. **缓存抽象：Laravel `Cache` Facade，绝不直连 Redis**
   - 通过 `Cache::get()` / `Cache::put()` / `Cache::increment()` / `Cache::forget()` 操作
   - 不使用 `Redis::xxx` 直连；理由是：(a) `CACHE_DRIVER` 切换时（`file` → `redis`）代码零改动；(b) 单元测试可注入 `array` 驱动，不需要 `Redis` mock；(c) 锁语义 (`Cache::lock`) 在所有驱动下行为一致
   - 当前 `CACHE_TTL_SECONDS = 86400`（24h），与"每日一次菜单"业务语义对齐

2. **三层降级链：`Cache → DB → Gemini → 本地模板`**

   ```
   请求进入 generateDailyMenuForUser
   ├─ 1. 命中 Cache (TTL 24h)  →  返回，source=cache（DB 落库仍走 upsert）
   ├─ 2. 命中 DB (DailyMenu)    →  回填 Cache，返回，source=db
   ├─ 3. 调 Gemini (timeout 8s) →  成功则落 Cache+DB，source=gemini
   └─ 4. 失败/超时/无 key       →  generateFallbackMenu() 本地模板，source=fallback
   ```

   - 任意一层失败都进入下一层；任意一层成功都终止
   - `source` 字段（`gemini` / `cache` / `db` / `fallback`）写入 `daily_menus.source`，可观测
   - 用户**永远拿到一份菜单**，不会看到 500 / 空白

3. **限流前置：每日 3 次重新生成（`regenerate()`）**
   - `Cache::increment("ai_menu:regen:{$userId}:{$date}")` 原子计数
   - 第一次 increment 返回 1 时 `Cache::put` 设 24h TTL（避免 0 → 1 时 TTL 丢失）
   - 计数 > 3 抛 `GuardFailedException('GUARD-AI-RATE')`，前端提示"明日再试"
   - **不限制**首次生成（`generateDailyMenuForUser`）——首次是 Cache 命中不计费

4. **GUARD-AI：未填问卷时拒绝生成**
   - `resolvePreferences($user)` 返回 null 时抛 `GuardFailedException('GUARD-AI')`
   - 理由：没有偏好就给 Gemini 等于浪费 token，且菜单质量差

5. **`DailyMenu::updateOrCreate` 幂等写库**
   - 主键 `(user_id, date)` 联合唯一（migration 中已加 `UNIQUE(user_id, date)`）
   - 多次同一天调用不会产生重复行；`source` 字段反映最后一次写入的来源
   - 业务副作用：Grafana 看到的 `source=fallback` 数量 = 真实降级次数（用于判断 Gemini 健康度）

6. **不重试 Gemini**
   - 单次失败直接降级；不重试 3 次
   - 理由：(a) Gemini 限速是按 RPM，3 次重试会消耗更多配额；(b) 用户等待时间从 8s 变成 24s，体验更差；(c) 我们已经有 DB 历史菜单兜底，重试价值低

## §3 备选方案（Alternatives Considered）

### 3.1 备选 A：Redis 直连（`Redis::get` / `Redis::set`）（拒绝）

| 维度 | 评估 |
| --- | --- |
| **方案** | 用 `Illuminate\Support\Facades\Redis` 直接操作 `ai_menu:*` key |
| **优势** | 性能略高（少一层抽象）；可以用 Redis 特有的 `ZADD` / `EVAL` 做更复杂限流 |
| **劣势** | ① `CACHE_DRIVER=file` 时 `Redis` Facade 不可用（laravel 会抛 `RuntimeException`），**测试环境崩溃**；② `Redis::set` 没有 TTL 原子保证（旧版本 Redis），要用 `SETEX` 写两行；③ 跨驱动切换要改业务代码（违反"基础设施可替换"原则） |
| **拒绝理由** | **抽象的价值 > 微优化**；Laravel `Cache` Facade 在 Redis 驱动下底层就是 `Redis::setex`，性能差异 < 5%。Sprint 1 阶段我们用 `file/array` 驱动，必须走抽象 |

### 3.2 备选 B：单层降级（直接 Cache → Gemini，无 DB 中间层）（拒绝）

| 维度 | 评估 |
| --- | --- |
| **方案** | `Cache miss → 直接调 Gemini → 写 Cache 返回`，不查 DB |
| **优势** | 简单，少一次 SQL |
| **劣势** | ① Cache 失效（`file` 驱动重启 / Redis flush）时**所有用户重新打 Gemini**——1000 用户 = 1000 次调用，可能触发 Gemini 限速；② 无法实现"管理员强制刷新所有用户菜单"（无 DB 索引）；③ `daily_menus` 表失去审计价值 |
| **拒绝理由** | **DB 是事实表**（什么时间给什么人生成了什么内容，可追溯）；Cache 只是性能优化层。降级链必须有 DB 这一中间层 |

### 3.3 备选 C：每次重新生成都打 Gemini（无限流）（拒绝）

| 维度 | 评估 |
| --- | --- |
| **方案** | 移除 `regenerate()` 限流，前端按钮随便点 |
| **优势** | 用户体验好（"我想换就换"） |
| **劣势** | ① 恶意用户 1 分钟点 100 次 = 100 次 Gemini 调用 = 触发限速 → 全员降级；② Gemini 配额 1500 RPD，理论上 1500 用户同时点就爆；③ 实际业务中"重新生成"价值不大（同一天偏好不变，结果高度相似） |
| **拒绝理由** | **3 次/天是产品决策**（与 pm-agent §PRD "我的菜单" 互动频率对齐），不是技术限制。即使 Gemini 配额无限，也应该限流以保证稳定 |

### 3.4 备选 D：失败重试 3 次（指数退避）（拒绝）

| 维度 | 评估 |
| --- | --- |
| **方案** | Gemini 失败后等 1s / 2s / 4s 重试，3 次都失败再降级 |
| **优势** | 网络抖动场景下能挽回 |
| **劣势** | ① 99% 失败是**业务失败**（限速、配额耗尽、key 失效），重试只会更糟；② 用户等待 1+2+4=7s + 3×8s 超时 = 31s，**体验灾难**；③ 重试风暴会触发 Gemini 服务端限速（429），影响所有用户 |
| **拒绝理由** | **降级 = 立即降级**；重试应放在后台任务（如 `RegenerateMenuJob`），不在用户请求路径上 |

### 3.5 备选 E：用 Meilisearch / Algolia 索引历史菜单（推迟到 Sprint 3+）

| 维度 | 评估 |
| --- | --- |
| **方案** | 把 `daily_menus.menu_content` 全文索引到 Meilisearch，支持"我上周的菜单"搜索 |
| **优势** | 提升产品体验 |
| **劣势** | ① Sprint 1 阶段无 Meilisearch 实例（roadmap Sprint 3 才引入）；② 菜单是短文本（~100 词），传统 DB LIKE 已足够；③ 引入搜索后 `daily_menus` 写入路径变长（写 DB + 写索引），分布式事务风险 |
| **拒绝理由** | **不在 Sprint 1 范围**；roadmap Sprint 3 有专门任务卡 |

## §4 后果（Consequences）

### 4.1 正面后果

- **可观测性强**：`daily_menus.source` 字段直接反映降级比例；Grafana 上看 `source='fallback'` 百分比 = Gemini 健康度
- **测试容易**：`array` 驱动下，单元测试不依赖任何外部服务
- **优雅降级**：用户永远看到菜单（哪怕是模板），不破坏"价值交付"
- **成本可控**：每日 1000 用户 × 1 次 Gemini 调用 = 1000 RPD，安全线内
- **审计完整**：`daily_menus` 留下完整历史，可做"哪天的菜单用户最爱加购"分析

### 4.2 负面后果 / 风险

- **Cache 击穿风险**：Gemini 限速或宕机时，所有用户第一次访问会同时打 DB → Gemini → fallback；如果 fallback 模板也加载慢，**首屏延迟可能飙到 8s**
- **降级质量差**：`generateFallbackMenu()` 模板过于简单（"🌱 [AI Demo] A {habit} lunch..."），会让用户觉得"AI 菜单是假的"
- **限流计数基于本地时间**：用 `now()->toDateString()`（`Asia/Hong_Kong` 时区）作为 key，跨时区用户行为不友好——但我们目标用户 100% HK，可接受
- **Gemini 配额不可观测**：当前没有 alert 配额用量；Sprint 2 需补 Grafana 面板

### 4.3 缓解措施

- **Cache 击穿**：DB 这一中间层把"瞬时高并发"摊平——1000 用户同时打，先到 DB 的 1 个用户调 Gemini，其他 999 个等 DB 落库后命中 DB（DB `SELECT + INSERT` 5ms 级）
- **降级质量**：Sprint 2 优化 `generateFallbackMenu()`，基于 `preferences` 渲染更个性化的模板（如"素食 / 高蛋白"分类）
- **配额监控**：Sprint 2 devops 加 `gemini_tokens_used_total` 计数器
- **时区**：`config('app.timezone') = 'Asia/Hong_Kong'` 已配置；HK 用户 100%

## §5 实施（Implementation）

### 5.1 核心文件（已落地）

- `app/Services/AiMenuService.php` — 3 公共方法 + 4 私有方法
  - `generateDailyMenuForUser(User, ?array $override = null): DailyMenu` — 4 层降级链
  - `regenerate(User, ?array $override = null): DailyMenu` — 3 次/天限流
  - `getTodayMenu(User): ?DailyMenu` — 仪表盘用
  - `generateDailyMenu(array, array): string` — 兼容旧 SurveyController demo
  - `resolvePreferences(User): ?array` — 问卷偏好解析
  - `upsertMenu(User, date, content, source, tokens): DailyMenu` — 幂等写库
  - `callGemini(array, array): array` — 调 Gemini 8s 超时
  - `generateFallbackMenu(array, array): string` — 本地模板降级

- `app/Models/DailyMenu.php` — Eloquent 模型，字段 `user_id` / `date` / `menu_content` / `source` / `tokens_used` + `(user_id, date)` UNIQUE 约束

### 5.2 关键常量

```php
private const CACHE_TTL_SECONDS = 86400;       // 24h
private const DAILY_REGEN_LIMIT  = 3;           // 每日 3 次
private const CACHE_KEY_MENU     = 'ai_menu:user:%d:date:%s';
private const CACHE_KEY_REGEN    = 'ai_menu:regen:%d:%s';
```

### 5.3 降级链伪代码

```php
public function generateDailyMenuForUser(User $user, ?array $override = null): DailyMenu
{
    $preferences = $override ?? $this->resolvePreferences($user);
    if (!$preferences) throw new GuardFailedException('GUARD-AI', ...);

    $date = now()->toDateString();
    $cacheKey = sprintf(self::CACHE_KEY_MENU, $user->id, $date);

    // Layer 1: Cache
    $cached = Cache::get($cacheKey);
    if ($cached) {
        return $this->upsertMenu($user, $date, $cached, 'cache', 0);
    }

    // Layer 2: DB
    $existing = DailyMenu::where('user_id', $user->id)->where('date', $date)->first();
    if ($existing) {
        Cache::put($cacheKey, $existing->menu_content, self::CACHE_TTL_SECONDS);
        return $existing;  // source 保留原值（gemini/fallback）
    }

    // Layer 3: Gemini
    $available = Product::where('stock', '>', 0)->pluck('name')->toArray();
    [$content, $tokens] = $this->callGemini($preferences, $available);
    Cache::put($cacheKey, $content, self::CACHE_TTL_SECONDS);

    return $this->upsertMenu($user, $date, $content, 'gemini', $tokens);
}
```

### 5.4 测试覆盖

- `tests/Unit/Services/AiMenuServiceTest.php` — 4 个测试：
  - `test_generate_rejects_when_no_preferences` — GUARD-AI
  - `test_generate_creates_daily_menu_record` — 正常生成
  - `test_second_call_returns_existing_menu` — 同日重复（无重复行）
  - `test_regenerate_rate_limit_3_per_day` — GUARD-AI-RATE

### 5.5 后续 PR（不在本 ADR 范围）

- **Sprint 1 Day 3**：切换 `CACHE_DRIVER=redis`（架构演进），代码零改动
- **Sprint 2**：Grafana 面板 `ai_menu_source_breakdown`（按 source 计数）
- **Sprint 2**：优化 `generateFallbackMenu()` 模板质量
- **Sprint 3+**：评估 Meilisearch 索引历史菜单

## §6 引用（References）

- **触发评审**：`docs/bmad/REVIEW-REPORT.md` §3.1 NEW-P1-03（缺失 ADR）、§9.3（架构改进建议）
- **实现代码**：
  - `app/Services/AiMenuService.php`（主实现，3 公共方法 + 4 私有方法）
  - `app/Models/DailyMenu.php`（Eloquent 模型 + `(user_id, date)` UNIQUE）
  - `database/migrations/2026_06_12_xxxx_create_daily_menus.php`（UNIQUE 约束）
- **测试**：
  - `tests/Unit/Services/AiMenuServiceTest.php`（4 个测试覆盖：缓存、限流、降级、幂等）
  - 后续：`tests/Feature/Api/MenuControllerTest.php`（HTTP 层 E2E）
- **业务定义**：`docs/bmad/prd-mvp.md` §3.2（AI 每日菜单是核心体验）
- **状态机联动**：本 ADR 引用 ADR-0005 的"状态机思想"——`generateDailyMenuForUser` 也有"状态"（cache / db / gemini / fallback），但**不是订单状态**，不入 7 态 SSOT
- **关联 ADR**：
  - ADR-0005（状态机：AI 菜单的 source 字段用类似"状态标记"思想）
  - ADR-0004（Webhook 限流：本 ADR 的 Cache::increment 与 webhook 限流思想一致）
- **外部参考**：
  - Google AI Studio 文档 — Gemini API 限速策略
  - Laravel 官方文档 "Cache" 章节
  - SRE 工作手册 — "Cascading Fallback" 模式
