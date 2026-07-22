# Task 6 Report: daily_menus 加 menu_json 列

**Status:** DONE

## 实现内容

1. Migration `2026_07_20_120000_add_menu_json_to_daily_menus.php`：`menu_json` JSON 列 nullable，`after('menu_content')`，含 comment
2. `DailyMenu` Model：`menu_json` 加 `$fillable` + `$casts = ['menu_json' => 'array']`
3. `AiMenuService::upsertMenu()` 签名加 `?array $jsonData = null`，`fill()` 时写入 `menu_json`
4. 两个调用点：缓存命中传 `null`，正常落库传 `$jsonData ?? null`
5. 移除 Task 4 留下的 `// TODO: Task 6` 注释
6. `AiMenuServiceTest::test_provider_json_output_is_rendered_to_text` 追加 3 个断言：`assertIsArray($menu->menu_json)` / `assertSame('Good day!', $menu->menu_json['greeting'])` / `assertCount(3, $menu->menu_json['meals'])`

## TDD 证据

本 Task 无独立 RED 阶段（是对 Task 4 已有测试的增量断言 + migration + Model 改动）。

**验证命令：**
```bash
$ php artisan migrate:fresh --seed
✓ Users: 2 / Categories: 14 / Products: 24 / Subscription Plans: 3

$ php vendor/bin/phpunit tests/Unit/Services/AiMenuServiceTest.php --no-coverage --filter test_provider_json_output_is_rendered_to_text
OK (1 test, 7 assertions)

$ php vendor/bin/phpunit --no-coverage
OK (121 tests, 453 assertions)  # 与 Task 5 持平（121 tests），断言 +3（menu_json 检查）
```

## 文件清单

- 创建 `database/migrations/2026_07_20_120000_add_menu_json_to_daily_menus.php`
- 修改 `app/Models/DailyMenu.php`（fillable + casts）
- 修改 `app/Services/AiMenuService.php`（upsertMenu 签名 + 2 调用点 + 移除 TODO）
- 修改 `tests/Unit/Services/AiMenuServiceTest.php`（+3 断言）

## Commit

`feat(ai): add menu_json column to daily_menus for structured data` (4 files, +36/-7)

## Self-Review

- **完整性**：migration / Model / Service / 测试四者同步；两个调用点都传了 jsonData（缓存路径 null，正常路径 $jsonData ?? null）
- **兼容性**：`menu_json` nullable，旧数据无此列值不受影响；`menu_content` 保留渲染文本，前端无感知
- **一致性**：`?? null` 与参数默认值 `= null` 对齐（防御性，理论上 $jsonData 在 JSON 模式外就是 null）
- **YAGNI**：未加索引（JSON 列查询场景未出现）、未加 backfill（旧数据 menu_json 保持 null 合理）

## 顾虑

1. **缓存路径 menu_json 永远为 null**：`generateDailyMenuForUser` 命中 Cache 时直接 `upsertMenu(..., null)`，意味着如果同一天的菜单先从 DB 读到再命中缓存，menu_json 会被覆盖为 null。**但实际不会**：命中 DB（步骤 2）时直接 `return $existing`，不走 `upsertMenu`；命中缓存（步骤 1）时说明 DB 没有该记录（或已被缓存），`upsertMenu` 是新建。边界：如果先正常生成（menu_json 有值）→ 缓存失效但 DB 有记录 → 走步骤 2 直接返回 DB 记录（menu_json 保留）。安全。
2. **migration 时间戳硬编码 `2026_07_20_120000`**：与其他 migration 的真实时间戳风格一致，但写死的时间可能与实际执行时间不符。这是 brief 指定的文件名，遵循 brief。

## 报告路径

`d:/FreshToday-AI/.superpowers/sdd/reports/task-6-report.md`

---

## Fix Round 1（reviewer 1 Critical + 1 Important）

### Critical #1: 缓存命中路径清空已有记录的 menu_json

**问题确认：** Reviewer 分析完全正确，我此前的"边界安全"分析有误。

错误链路：
1. 正常生成后同时写缓存（`menu_content`）+ DB（含 `menu_json`）
2. 第二次请求命中缓存 → `upsertMenu(..., null)`
3. `upsertMenu` 内部 `first()` 找到已有记录 → `fill(['menu_json' => null])->save()`
4. **menu_json 被覆盖为 null** → Task 6 核心交付物形同虚设

**Fix（方案 A，最小改动）：** `upsertMenu` 仅在 `$jsonData !== null` 时才 fill `menu_json`：

```php
$fillData = ['menu_content' => $content, 'source' => $source, 'tokens_used' => $tokens];
if ($jsonData !== null) {
    $fillData['menu_json'] = $jsonData;
}
$menu->fill($fillData)->save();
```

**File:** `app/Services/AiMenuService.php:127-148`

**根因：** brief 设计的"缓存命中传 null"与 `upsertMenu` 的"先查后 fill"组合本身有缺陷。按 brief 实现无过错，但 bug 必须修。

### Important #2: 测试未覆盖缓存命中路径的 menu_json 保留

**Fix:** 新增 `test_menu_json_is_preserved_on_cache_hit`：
- 第一次调用正常生成（写缓存 + DB 含 menu_json）
- 第二次调用缓存命中
- 断言 `menu_json` 仍是数组 + `greeting` 保留 + `meals` count=3

**File:** `tests/Unit/Services/AiMenuServiceTest.php:336-375`

### 测试证据

**覆盖测试：** `tests/Unit/Services/AiMenuServiceTest.php`（8 tests）

**命令：** `php vendor/bin/phpunit tests/Unit/Services/AiMenuServiceTest.php --no-coverage`

**输出：** `OK (8 tests, 21 assertions)`

**全量回归：** `php vendor/bin/phpunit --no-coverage` → `OK (122 tests, 457 assertions)`（+1 新回归测试）
