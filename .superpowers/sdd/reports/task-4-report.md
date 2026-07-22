# Task 4 Report: AiMenuService 集成 Validator + MenuRenderer

**Status:** DONE

## 实现内容

1. 创建 `MenuRenderer::renderTextFromJson(array $json): string` — 把结构化 JSON 渲染成纯文本（greeting + 3 meals + 💡 Tip）
2. `AiMenuService` 集成：
   - 构造函数注入 `MenuOutputValidator`（PHP 8.2 `new` in initializer 默认值）
   - `generateDailyMenuForUser()`：优先用 `jsonData` 渲染（通过 `validateJson`），自由文本通过 `validate` 校验，校验失败清空 content + tokens=0 触发 fallback
   - `generateDailyMenu()` 旧接口同步集成
   - `callProvider()` 返回 3 元素（`['', 0, null]` 而非 `['', 0]`）

## TDD 证据

### RED
```bash
$ php vendor/bin/phpunit tests/Unit/Services/Ai/MenuRendererTest.php --no-coverage
ERRORS! Tests: 2, Assertions: 0, Errors: 2.
Error: Class "App\Services\Ai\MenuRenderer" not found
```

### GREEN
```bash
$ php vendor/bin/phpunit tests/Unit/Services/Ai/MenuRendererTest.php --no-coverage
OK (2 tests, 9 assertions)

$ php vendor/bin/phpunit tests/Unit/Services/AiMenuServiceTest.php --no-coverage
OK (7 tests, 14 assertions)  # 原 4 + 新 3
```

### 全量回归
```bash
$ php vendor/bin/phpunit --no-coverage
OK (116 tests, 440 assertions)  # 基线 94 → 116 (+22: T1=8, T2=5, T3=4, T4=5)
```

## 文件清单

- 创建 `app/Services/Ai/MenuRenderer.php`
- 创建 `tests/Unit/Services/Ai/MenuRendererTest.php`
- 修改 `app/Services/AiMenuService.php`（use / 构造函数 / generateDailyMenuForUser / generateDailyMenu / callProvider）
- 修改 `tests/Unit/Services/AiMenuServiceTest.php`（+3 测试）

## Commit

`feat(ai): integrate output validation and JSON rendering into AiMenuService` (4 files, +204/-9)

## Self-Review

- **完整性**：两个入口方法（ForUser + 旧接口）都集成 Validator；JSON 优先，自由文本兜底
- **降级语义**：校验失败 `tokens=0` + content 清空 → 走 fallback，source 仍记 provider 名（保留原行为）
- **类型一致**：`callProvider` docblock 与 Interface 一致 `array{0:string,1:int,2:?array}`
- **YAGNI**：未加 brief 之外功能（如 validation 失败原因写入 DB、重试机制）

## 顾虑

1. **`new` in initializer**：构造函数 `private readonly MenuOutputValidator $validator = new MenuOutputValidator` 是 PHP 8.1+ 特性。虽然 composer.json 要求 `^8.2`，但这是相对较新的语法，老代码审查者可能不熟。功能正确，已测试通过。
2. **TODO 注释遗留**：`generateDailyMenuForUser` 中 `// TODO: Task 6 把 $jsonData 存入 menu_json 列` 是有意留下的 Task 6 锚点，不是遗漏。

## 报告路径

`d:/FreshToday-AI/.superpowers/sdd/reports/task-4-report.md`
