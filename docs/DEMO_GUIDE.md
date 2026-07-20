# GreenBite AI 菜单演示手册

> 演示前 30 分钟过一遍这份 checklist，确保万无一失。

---

## 一、演示前准备（前一天晚上）

### 1. 环境检查

```bash
cd d:/FreshToday-AI

# 确认 .env 有 DeepSeek key
grep DEEPSEEK_API_KEY .env

# 清缓存
php artisan config:clear

# 确认依赖已装
ls vendor/bin/phpunit
```

### 2. 数据库重置 + Seed

```bash
# 完全重置（干净环境）
php artisan migrate:fresh --seed

# 加 demo 用户（3 个画像）
php artisan db:seed --class=DemoSeeder
```

### 3. 验证 AI 配置

```bash
php artisan serve
# 另开终端：
curl http://localhost:8000/api/health/ai
```

**预期输出**：

```json
{
  "provider": "deepseek",
  "configured": true,
  "last_success_at": null,
  "last_failure_at": null,
  "failure_rate_1h": 0
}
```

看到 `"configured": true` = key 配置正确。

### 4. 预生成菜单（断网兜底）

**关键步骤**：演示前一晚批量生成，菜单落库 + 缓存 24h，现场即使断网也能从 DB 读。

```bash
php artisan tinker
```

```php
$users = \App\Models\User::whereIn('email', [
    'demo-vegan@greenbite.hk',
    'demo-family@greenbite.hk',
    'demo-keto@greenbite.hk',
])->get();

$service = app(\App\Services\AiMenuService::class);
foreach ($users as $u) {
    $menu = $service->generateDailyMenuForUser($u);
    echo "✓ {$u->email}: {$menu->source} ({$menu->tokens_used} tokens)\n";
}
```

**预期输出**：

```
✓ demo-vegan@greenbite.hk: deepseek (XXX tokens)
✓ demo-family@greenbite.hk: deepseek (XXX tokens)
✓ demo-keto@greenbite.hk: deepseek (XXX tokens)
```

看到 `deepseek` + `tokens > 0` = AI 调用成功。如果是 `[AI Demo]` fallback，检查 key 或网络。

### 5. 验证菜单已落库

```bash
php artisan tinker
```

```php
\App\Models\DailyMenu::with('user')->latest()->take(3)->get()->each(function($m) {
    echo "{$m->user->email}\n";
    echo "  source: {$m->source}, tokens: {$m->tokens_used}\n";
    echo "  has menu_json: " . ($m->menu_json ? 'YES' : 'NO') . "\n";
    echo "  preview: " . substr($m->menu_content, 0, 100) . "...\n\n";
});
```

确认三条记录的 `menu_json` 都是 `YES`。

### 6. 录屏兜底（可选但强烈推荐）

提前录一段完整操作视频（问卷 → 生成 → 菜单展示），现场网络挂了就放视频。

---

## 二、演示当天（开场前 15 分钟）

### 1. 启动服务

```bash
cd d:/FreshToday-AI
php artisan config:clear  # 再清一次保险
php artisan serve
```

### 2. 再次健康检查

```bash
curl http://localhost:8000/api/health/ai
```

`last_success_at` 应该有值（昨晚预生成的时间戳）。

### 3. 浏览器准备

- 开一个无痕窗口（避免旧 session 干扰）
- 访问 `http://localhost:8000`
- 预先登录好一个 demo 账号（推荐 `demo-family@greenbite.hk`，画像最普适）

### 4. 备用终端窗口

- 窗口 1：`php artisan serve`（运行中）
- 窗口 2：`tail -f storage/logs/laravel.log`（实时日志，演示降级时看）
- 窗口 3：准备执行 tinker 命令（演示降级用）

---

## 三、演示动线（推荐 10 分钟版）

### Part 1：个性化生成（3 分钟）

**话术**："GreenBite 的 AI 菜单不是通用的，而是根据你的饮食习惯、目标、预算个性化生成。"

1. 打开问卷页（或展示已有的问卷数据）
2. 讲解三个字段如何影响生成：
   - `dietary_habits`: 素食/生酮/无限制
   - `goals`: 减脂/家庭营养/生酮维持
   - `budget_hkd`: 500/1500/3000
3. 点击"生成菜单"
4. **等待时讲解**："AI 正在分析你的偏好，结合当前库存的 24 种本地有机食材，生成今天的三餐建议。"
5. 展示菜单

**讲点**：
- 菜单内容包含库存商品名（如"本地有机菜心"）
- 结构清晰：早餐/午餐/晚餐 + 营养提示
- 字数控制在 80-120 词

### Part 2：对比三种画像（2 分钟）

**话术**："同样的系统，不同用户得到完全不同的菜单。"

1. 退出登录，换 `demo-vegan@greenbite.hk`
2. 展示菜单（应该是素食 + 低成本食材 + 简单烹饪）
3. 换 `demo-keto@greenbite.hk`
4. 展示菜单（应该是低碳高脂 + 高级食材 + 复杂烹饪）

**讲点**：三个菜单的食材选择、烹饪难度、预算匹配完全不同。

### Part 3：技术架构亮点（3 分钟）

**话术**："这不是简单的 ChatGPT 套壳，而是经过生产化加固的系统。"

打开 tinker 或 admin，展示 `daily_menus` 表：

```php
$menu = \App\Models\DailyMenu::latest()->first();
$menu->menu_json;  // 结构化 JSON
```

**讲点 1：JSON 结构化输出**

```
{
  "greeting": "...",
  "meals": [
    {"type": "breakfast", "name": "...", "ingredients": [...], "description": "..."},
    {"type": "lunch", ...},
    {"type": "dinner", ...}
  ],
  "tip": "..."
}
```

"AI 不是返回自由文本，而是结构化 JSON，前端可以富渲染。"

**讲点 2：五道防线**

```
PromptBuilder 契约
    ↓
JSON Schema 强制
    ↓
MenuOutputValidator 校验
    ↓
本地 fallback 模板
    ↓
FailoverProvider 灾备（可开启）
```

"即使 AI 返回乱码、跑题、广告，系统也能拦截并降级。"

**讲点 3：可观测性**

打开 `http://localhost:8000/api/health/ai`，实时展示：

```json
{
  "provider": "deepseek",
  "configured": true,
  "last_success_at": "2026-07-20T03:15:00+00:00",
  "failure_rate_1h": 0
}
```

### Part 4：现场演示降级（2 分钟，效果拉满）

**话术**："最极端的情况：AI 服务完全挂掉，用户体验也不中断。"

**操作**：

```bash
# 终端 3：改错 key
cd d:/FreshToday-AI
echo "DEEPSEEK_API_KEY=sk-invalid" >> .env
php artisan config:clear
```

**浏览器**： regenerate 菜单

**预期结果**：菜单变成 `[AI Demo] ...`（本地模板）

**终端 2**（日志）：应该看到 `AiMenuService: provider output failed validation` 或 `non-2xx`

**话术**："看到了吗？AI 挂了，但用户依然拿到一份合理的菜单，只是标注了 [AI Demo]。这就是降级链的价值。"

**恢复**：

```bash
# 改回正确 key
vim .env  # 改回 sk-真实key
php artisan config:clear
```

再 regenerate，恢复正常 AI 菜单。

---

## 四、常见问题预案

### Q1: 现场网络挂了

**A**: 昨晚已批量预生成，菜单在 DB 里。直接展示 DB 中的菜单（tinker 或 admin），说明"这是昨晚 AI 生成的结果，已缓存 24h"。

### Q2: DeepSeek API 临时故障

**A**: 同 Q1，用预生成的菜单。或者现场切换到 fallback 模式讲解降级链。

### Q3: 有人问"为什么不用 ChatGPT？"

**A**:
1. DeepSeek 在香港出口稳定（OpenAI 需要代理）
2. 成本：DeepSeek ~$0.2/1M tokens vs GPT-4o-mini ~$0.3/1M
3. 架构上我们预留了多 Provider 支持（`AiProviderInterface`），切换只需改配置

### Q4: 有人问"菜单质量如何保证？"

**A**: 五道防线（前面 Part 3 讲点 2），重点强调：
1. Prompt 注入防御（`<user_preferences>` 标签隔离）
2. JSON Schema 强制（结构化输出）
3. 后端校验（黑名单 + 商品匹配）
4. 本地 fallback（AI 挂了也有兜底）

### Q5: 有人问"数据安全吗？"

**A**:
1. API Key 不存数据库，只在环境变量
2. 用户偏好数据在 prompt 中用标签隔离，防止注入
3. 所有 AI 输出经过后端校验才落库

---

## 五、演示后清理

### 1. 恢复 .env

确保 `DEEPSEEK_API_KEY` 是正确值（如果演示了降级）。

### 2. 清缓存

```bash
php artisan config:clear
```

### 3. 备份演示数据（可选）

```bash
cp database/database.sqlite database/database-demo-backup.sqlite
```

下次演示可直接恢复：

```bash
cp database/database-demo-backup.sqlite database/database.sqlite
```

---

## 六、快速参考卡

### Demo 账号

| 邮箱 | 密码 | 画像 |
|---|---|---|
| demo-vegan@greenbite.hk | demo1234 | 素食健身者（低预算/新手） |
| demo-family@greenbite.hk | demo1234 | 家庭主厨（中预算/中级） |
| demo-keto@greenbite.hk | demo1234 | 生酮白领（高预算/高级） |

### 关键 URL

- 首页: `http://localhost:8000`
- 健康检查: `http://localhost:8000/api/health/ai`
- 问卷: 登录后 `/survey`（或 dashboard 入口）

### 关键命令

```bash
# 启动
php artisan serve

# 清缓存
php artisan config:clear

# 重置 DB + seed
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoSeeder

# 查看日志
tail -f storage/logs/laravel.log

# Tinker
php artisan tinker
```

### 降级演示

```bash
# 改错 key
echo "DEEPSEEK_API_KEY=sk-invalid" >> .env
php artisan config:clear
# regenerate 菜单 → 看到 [AI Demo] fallback

# 恢复
vim .env  # 改回正确 key
php artisan config:clear
```

---

## 七、演示成功标准

- ✅ 三个 demo 用户都能登录
- ✅ 菜单内容个性化（素食/家庭/生酮明显不同）
- ✅ 菜单包含库存商品名（如"本地有机菜心"）
- ✅ `menu_json` 字段有结构化数据
- ✅ `/api/health/ai` 返回 `configured: true`
- ✅ 降级演示能看到 `[AI Demo]` fallback
- ✅ 日志能看到校验/降级记录

---

**祝演示顺利！** 有任何问题随时翻这份手册的"常见问题预案"章节。
