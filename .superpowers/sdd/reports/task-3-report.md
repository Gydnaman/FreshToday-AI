# Task 3 Report: JSON 结构化输出

**Status:** DONE_WITH_CONCERNS

## 实现内容

1. 创建 `MenuSchema`：
   - `geminiSchema(): array` — Gemini responseSchema 格式（含 minItems/maxItems=3、enum meal type、required 字段）
   - `openAiSchema(): array` — OpenAI Structured Outputs（`type: json_schema` + `strict: true` + `additionalProperties: false`）
   - `deepSeekSchema(): array` — 仅 `type: json_object`（DeepSeek 不支持强制 schema）

2. 三个 Provider 改造返回 `array{0:string,1:int,2:?array}`：
   - Gemini：`generationConfig.responseMimeType=application/json` + `responseSchema=MenuSchema::geminiSchema()`
   - OpenAI：`response_format=MenuSchema::openAiSchema()`，`max_tokens` 300→500（JSON 更耗 token）
   - DeepSeek：`response_format=MenuSchema::deepSeekSchema()`，`max_tokens` 300→500
   - 三家都增加 `json_decode($text, true)` + `is_array($json)` 校验，解析失败返回 `['', 0, null]`

3. `AiProviderInterface` docblock 更新为 `array{0:string,1:int,2:?array}`
4. `NullProvider::generate()` 返回 `['', 0, null]`

## TDD 证据

### RED
```bash
$ php vendor/bin/phpunit tests/Unit/Services/Ai/Providers/JsonOutputTest.php --no-coverage
（初次）Parse error: Unclosed '[' on line 33
（修复语法后）ERRORS! Tests: 4, Assertions: 0, Errors: 4.
ErrorException: Undefined array key 2
```
两次失败都符合预期：先语法错（brief 测试代码 bug），后逻辑错（Provider 返回 2 元素，测试解构 3 个）。

### GREEN
```bash
$ php vendor/bin/phpunit tests/Unit/Services/Ai/Providers/JsonOutputTest.php --no-coverage
OK (4 tests, 17 assertions)
```

### 回归
- `tests/Unit/Services/`: OK (61 tests, 128 assertions)
- 全量: OK (111 tests, 424 assertions)（基线 94 → 现 111，+17：Task 1=8 + Task 2=5 + Task 3=4）

## 文件清单

- 创建 `app/Services/Ai/MenuSchema.php`
- 创建 `tests/Unit/Services/Ai/Providers/JsonOutputTest.php`
- 修改 `app/Services/Ai/Providers/GeminiProvider.php`（generate 返回 3 元素 + JSON 模式）
- 修改 `app/Services/Ai/Providers/OpenAiProvider.php`（同上）
- 修改 `app/Services/Ai/Providers/DeepseekProvider.php`（同上）
- 修改 `app/Services/Ai/Providers/NullProvider.php`（返回 3 元素）
- 修改 `app/Services/Ai/Contracts/AiProviderInterface.php`（docblock）

## Commit

`feat(ai): enforce JSON structured output across all providers` (7 files, +304/-23)

## Self-Review

- **完整性**：3 个 Provider 全部返回 3 元素；JSON 解析失败有 Log::warning；Interface 契约同步更新
- **兼容性**：`AiMenuService::callProvider` 解构 `[$content, $tokens]` 不会报错（PHP 解构多余元素忽略），为 Task 4 完整改造留下空间
- **YAGNI**：未加 brief 之外的功能（如 schema 版本管理、动态 schema）

## 顾虑（DONE_WITH_CONCERNS 原因）

1. **brief 测试代码有 PHP 语法错误**：三个 Http::fake 响应的嵌套数组最外层都缺一个 `]`。我已逐字复制后修复，但这意味着 brief 本身有 bug。建议后续修订 plan。
2. **AiMenuService 尚未消费 `json_data`**：当前 `callProvider` 返回 3 元素但 Service 层只解构 2 个，`json_data` 被丢弃。这是**预期行为**（Task 4 才会集成 Validator + MenuRenderer），但中间状态下 JSON 模式看似"白做了"——实际 HTTP 请求已带 schema，只是后端没用。需等 Task 4 完成才闭环。
3. **DeepSeek 不强制 schema**：仅 `json_object`，模型可能返回任意 JSON（如 `{"error": "..."}`），靠 Task 4 的 `MenuOutputValidator::validateJson` 兜底。当前 Task 3 测试的 fake 返回的是合法 schema JSON，未覆盖"DeepSeek 返回非菜单 JSON"场景。

## 报告路径

`d:/FreshToday-AI/.superpowers/sdd/reports/task-3-report.md`

---

## Fix Round 1（reviewer Important finding）

**Finding:** OpenAI 测试未验证 `strict: true` 和 `additionalProperties: false`（reviewer Important #1 + Minor #4 name 字段）

**Fix:** `tests/Unit/Services/Ai/Providers/JsonOutputTest.php:124-129` `Http::assertSent` 回调补强为：

```php
return isset($body['response_format']['type'])
    && $body['response_format']['type'] === 'json_schema'
    && ($body['response_format']['json_schema']['name'] ?? '') === 'daily_menu'
    && ($body['response_format']['json_schema']['strict'] ?? false) === true
    && ($body['response_format']['json_schema']['schema']['additionalProperties'] ?? true) === false;
```

**覆盖测试：** `tests/Unit/Services/Ai/Providers/JsonOutputTest.php::test_openai_provider_requests_json_output`

**命令：** `php vendor/bin/phpunit tests/Unit/Services/Ai/Providers/JsonOutputTest.php --no-coverage`

**输出：** `OK (4 tests, 17 assertions)`

**结果：** 实现本身已正确（`MenuSchema::openAiSchema()` 含 `strict: true` + 双层 `additionalProperties: false` + `name: 'daily_menu'`），补强的断言通过，证明 reviewer 的担心已通过测试守住。
