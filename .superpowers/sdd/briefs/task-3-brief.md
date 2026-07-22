### Task 3: JSON 结构化输出（Gemini + OpenAI）

**Files:**
- Modify: `app/Services/Ai/Providers/GeminiProvider.php`
- Modify: `app/Services/Ai/Providers/OpenAiProvider.php`
- Modify: `app/Services/Ai/Providers/DeepseekProvider.php`
- Create: `app/Services/Ai/MenuSchema.php`
- Test: `tests/Unit/Services/Ai/Providers/JsonOutputTest.php`

**Interfaces:**
- Consumes: `PromptBuilder`（Task 2）
- Produces:
  - `MenuSchema::geminiSchema(): array`（Gemini responseSchema 格式）
  - `MenuSchema::openAiSchema(): array`（OpenAI json_schema 格式）
  - `MenuSchema::deepSeekSchema(): array`（DeepSeek 仅 json_object，schema 靠后端校验）
  - Provider 返回 `array{0:string,1:int,2:?array}`（第 3 元素为解析后的 JSON 或 null）

**Note:** DeepSeek 不支持强制 schema，只能 `response_format: {type: 'json_object'}`，schema 校验靠 `MenuOutputValidator::validateJson`。

- [ ] **Step 1: 写失败测试**

创建 `tests/Unit/Services/Ai/Providers/JsonOutputTest.php`：

```php
<?php

namespace Tests\Unit\Services\Ai\Providers;

use App\Services\Ai\MenuSchema;
use App\Services\Ai\Providers\DeepseekProvider;
use App\Services\Ai\Providers\GeminiProvider;
use App\Services\Ai\Providers\OpenAiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JsonOutputTest extends TestCase
{
    public function test_menu_schema_has_required_fields(): void
    {
        $gemini = MenuSchema::geminiSchema();
        $this->assertArrayHasKey('type', $gemini);
        $this->assertSame('object', $gemini['type']);
        $this->assertArrayHasKey('properties', $gemini);
        $this->assertArrayHasKey('greeting', $gemini['properties']);
        $this->assertArrayHasKey('meals', $gemini['properties']);
        $this->assertArrayHasKey('tip', $gemini['properties']);

        $openai = MenuSchema::openAiSchema();
        $this->assertArrayHasKey('type', $openai);
        $this->assertSame('json_schema', $openai['type']);
        $this->assertArrayHasKey('json_schema', $openai);
    }

    public function test_gemini_provider_requests_json_output(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'greeting' => 'Hi',
                        'meals' => [
                            ['type' => 'breakfast', 'name' => 'X', 'ingredients' => ['Tomato'], 'description' => 'Y'],
                            ['type' => 'lunch', 'name' => 'X', 'ingredients' => ['Spinach'], 'description' => 'Y'],
                            ['type' => 'dinner', 'name' => 'X', 'ingredients' => ['Salmon'], 'description' => 'Y'],
                        ],
                        'tip' => 'Tip',
                    ])]]],
                ],
                'usageMetadata' => ['totalTokenCount' => 100],
            ], 200),
        ]);

        $provider = new GeminiProvider([
            'key' => 'fake',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'model' => 'gemini-2.5-flash',
            'timeout' => 8,
        ]);

        [$content, $tokens, $json] = $provider->generate(['purpose' => 'X'], ['Tomato', 'Spinach', 'Salmon']);

        $this->assertNotEmpty($content);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('meals', $json);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return isset($body['generationConfig']['responseMimeType'])
                && $body['generationConfig']['responseMimeType'] === 'application/json'
                && isset($body['generationConfig']['responseSchema']);
        });
    }

    public function test_openai_provider_requests_json_output(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'greeting' => 'Hi',
                        'meals' => [
                            ['type' => 'breakfast', 'name' => 'X', 'ingredients' => ['Tomato'], 'description' => 'Y'],
                            ['type' => 'lunch', 'name' => 'X', 'ingredients' => ['Spinach'], 'description' => 'Y'],
                            ['type' => 'dinner', 'name' => 'X', 'ingredients' => ['Salmon'], 'description' => 'Y'],
                        ],
                        'tip' => 'Tip',
                    ])],
                ],
                'usage' => ['total_tokens' => 100],
            ], 200),
        ]);

        $provider = new OpenAiProvider([
            'key' => 'fake',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
            'timeout' => 15,
        ]);

        [$content, $tokens, $json] = $provider->generate(['purpose' => 'X'], ['Tomato', 'Spinach', 'Salmon']);

        $this->assertIsArray($json);
        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return isset($body['response_format']['type'])
                && $body['response_format']['type'] === 'json_schema';
        });
    }

    public function test_deepseek_provider_requests_json_object(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'greeting' => 'Hi',
                        'meals' => [
                            ['type' => 'breakfast', 'name' => 'X', 'ingredients' => ['Tomato'], 'description' => 'Y'],
                            ['type' => 'lunch', 'name' => 'X', 'ingredients' => ['Spinach'], 'description' => 'Y'],
                            ['type' => 'dinner', 'name' => 'X', 'ingredients' => ['Salmon'], 'description' => 'Y'],
                        ],
                        'tip' => 'Tip',
                    ])],
                ],
                'usage' => ['total_tokens' => 100],
            ], 200),
        ]);

        $provider = new DeepseekProvider([
            'key' => 'fake',
            'base_url' => 'https://api.deepseek.com/v1',
            'model' => 'deepseek-chat',
            'timeout' => 15,
        ]);

        [$content, $tokens, $json] = $provider->generate(['purpose' => 'X'], ['Tomato', 'Spinach', 'Salmon']);

        $this->assertIsArray($json);
        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return isset($body['response_format']['type'])
                && $body['response_format']['type'] === 'json_object';
        });
    }
}
```

- [ ] **Step 2: 跑测试确认失败**

```bash
php vendor/bin/phpunit tests/Unit/Services/Ai/Providers/JsonOutputTest.php --no-coverage
```

预期：失败（`MenuSchema` 不存在 + Provider 返回 2 元素而非 3）

- [ ] **Step 3: 实现 MenuSchema**

创建 `app/Services/Ai/MenuSchema.php`：

```php
<?php

namespace App\Services\Ai;

/**
 * 菜单 JSON Schema 定义
 *
 * 三家 Provider 的 schema 格式略有差异：
 *  - Gemini: responseSchema (OpenAPI 3.0 subset)
 *  - OpenAI: json_schema (JSON Schema draft 2020-12)
 *  - DeepSeek: 仅支持 json_object，schema 靠后端校验
 */
class MenuSchema
{
    /**
     * Gemini responseSchema 格式
     *
     * @see https://ai.google.dev/api/rest/v1beta/GenerationConfig
     */
    public static function geminiSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'greeting' => ['type' => 'string', 'description' => 'Friendly opening line'],
                'meals' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['breakfast', 'lunch', 'dinner']],
                            'name' => ['type' => 'string'],
                            'ingredients' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'description' => ['type' => 'string'],
                        ],
                        'required' => ['type', 'name', 'ingredients', 'description'],
                    ],
                    'minItems' => 3,
                    'maxItems' => 3,
                ],
                'tip' => ['type' => 'string', 'description' => 'Closing nutrition tip'],
            ],
            'required' => ['greeting', 'meals', 'tip'],
        ];
    }

    /**
     * OpenAI Structured Outputs 格式
     *
     * @see https://platform.openai.com/docs/guides/structured-outputs
     */
    public static function openAiSchema(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'daily_menu',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'greeting' => ['type' => 'string'],
                        'meals' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['type' => 'string', 'enum' => ['breakfast', 'lunch', 'dinner']],
                                    'name' => ['type' => 'string'],
                                    'ingredients' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'description' => ['type' => 'string'],
                                ],
                                'required' => ['type', 'name', 'ingredients', 'description'],
                                'additionalProperties' => false,
                            ],
                            'minItems' => 3,
                            'maxItems' => 3,
                        ],
                        'tip' => ['type' => 'string'],
                    ],
                    'required' => ['greeting', 'meals', 'tip'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * DeepSeek 仅支持 json_object（不强制 schema）
     */
    public static function deepSeekSchema(): array
    {
        return ['type' => 'json_object'];
    }
}
```

- [ ] **Step 4: 改造 GeminiProvider 支持 JSON 输出**

`app/Services/Ai/Providers/GeminiProvider.php` 完整替换 `generate()` 方法：

```php
use App\Services\Ai\MenuSchema;
use App\Services\Ai\PromptBuilder;

public function generate(array $preferences, array $products): array
{
    if (! $this->isConfigured()) {
        return ['', 0, null];
    }

    $systemPrompt = PromptBuilder::buildSystemPrompt();
    $userPrompt = PromptBuilder::buildUserPrompt($preferences, $products);
    $combinedPrompt = $systemPrompt."\n\n".$userPrompt;

    $url = rtrim($this->config['base_url'], '/')
        .'/models/'.$this->config['model']
        .':generateContent?key='.$this->config['key'];

    try {
        $response = Http::timeout($this->config['timeout'] ?? 8)->post($url, [
            'contents' => [['parts' => [['text' => $combinedPrompt]]]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => MenuSchema::geminiSchema(),
            ],
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $tokens = (int) ($data['usageMetadata']['totalTokenCount'] ?? 0);

            if ($text !== '') {
                $json = json_decode($text, true);
                if (is_array($json)) {
                    return [$text, $tokens, $json];
                }
                Log::warning('GeminiProvider: invalid JSON in response', ['text' => substr($text, 0, 200)]);

                return ['', 0, null];
            }

            Log::warning('GeminiProvider: empty candidates', ['model' => $this->config['model']]);

            return ['', 0, null];
        }

        Log::warning('GeminiProvider: non-2xx response', [
            'status' => $response->status(),
            'body' => substr($response->body(), 0, 200),
        ]);
    } catch (\Throwable $e) {
        Log::warning('GeminiProvider: request exception', ['message' => $e->getMessage()]);
    }

    return ['', 0, null];
}
```

- [ ] **Step 5: 改造 OpenAiProvider + DeepseekProvider**

`app/Services/Ai/Providers/OpenAiProvider.php` `generate()` 替换（关键片段）：

```php
use App\Services\Ai\MenuSchema;
use App\Services\Ai\PromptBuilder;

public function generate(array $preferences, array $products): array
{
    if (! $this->isConfigured()) {
        return ['', 0, null];
    }

    $systemPrompt = PromptBuilder::buildSystemPrompt();
    $userPrompt = PromptBuilder::buildUserPrompt($preferences, $products);

    $url = rtrim($this->config['base_url'], '/').'/chat/completions';

    try {
        $response = Http::timeout($this->config['timeout'] ?? 15)
            ->withToken($this->config['key'])
            ->acceptJson()
            ->asJson()
            ->post($url, [
                'model' => $this->config['model'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 500, // JSON 比纯文本耗 token，放宽到 500
                'response_format' => MenuSchema::openAiSchema(),
            ]);

        if ($response->successful()) {
            $data = $response->json();
            $text = $data['choices'][0]['message']['content'] ?? '';
            $tokens = (int) ($data['usage']['total_tokens'] ?? 0);

            if ($text !== '') {
                $json = json_decode($text, true);
                if (is_array($json)) {
                    return [trim($text), $tokens, $json];
                }
                Log::warning('OpenAiProvider: invalid JSON', ['text' => substr($text, 0, 200)]);

                return ['', 0, null];
            }

            Log::warning('OpenAiProvider: empty choices', ['model' => $this->config['model']]);

            return ['', 0, null];
        }

        Log::warning('OpenAiProvider: non-2xx', ['status' => $response->status()]);
    } catch (\Throwable $e) {
        Log::warning('OpenAiProvider: exception', ['message' => $e->getMessage()]);
    }

    return ['', 0, null];
}
```

`DeepseekProvider.php` 同样改造，`response_format` 用 `MenuSchema::deepSeekSchema()`。

- [ ] **Step 6: 更新 AiProviderInterface 契约注释**

`app/Services/Ai/Contracts/AiProviderInterface.php:32-38` 修改 docblock：

```php
    /**
     * 生成个性化菜单文本
     *
     * @param  array<string,mixed>  $preferences  来自 UserPreference 的偏好
     * @param  array<int,string>  $products  可售商品名称列表
     * @return array{0:string,1:int,2:?array} [content, tokens_used, json_data]
     *         content: 原始文本（JSON 字符串或纯文本）
     *         tokens_used: 成功时 Provider 报告的 token 数，失败为 0
     *         json_data: 解析后的 JSON 数组（JSON 模式），非 JSON 模式或解析失败为 null
     */
    public function generate(array $preferences, array $products): array;
```

- [ ] **Step 7: 同步更新 NullProvider**

`app/Services/Ai/Providers/NullProvider.php:28-32` 修改：

```php
    public function generate(array $preferences, array $products): array
    {
        return ['', 0, null];
    }
```

- [ ] **Step 8: 跑测试确认通过 + 回归**

```bash
php vendor/bin/phpunit tests/Unit/Services/Ai/Providers/JsonOutputTest.php --no-coverage
php vendor/bin/phpunit tests/Unit/Services/ --no-coverage
```

预期：新测试 `OK (4 tests)`；旧 AiMenuService 测试可能失败（因为 `generate()` 返回 3 元素，`AiMenuService::callProvider` 解构只取 2 个）→ 进入 Task 4 修复。

- [ ] **Step 9: Commit**

```bash
git add app/Services/Ai/MenuSchema.php app/Services/Ai/Providers/ app/Services/Ai/Contracts/AiProviderInterface.php tests/Unit/Services/Ai/Providers/JsonOutputTest.php
git commit -m "feat(ai): enforce JSON structured output across all providers"
```

---

