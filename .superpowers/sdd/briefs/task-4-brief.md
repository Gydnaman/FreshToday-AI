### Task 4: AiMenuService 集成 Validator + JSON 渲染

**Files:**
- Modify: `app/Services/AiMenuService.php:73-86,176-202,204-213`
- Create: `app/Services/Ai/MenuRenderer.php`
- Test: `tests/Unit/Services/AiMenuServiceTest.php`（新增 case）
- Test: `tests/Unit/Services/Ai/MenuRendererTest.php`

**Interfaces:**
- Consumes: `MenuOutputValidator`（Task 1）、Provider 返回的 `json_data`（Task 3）
- Produces:
  - `MenuRenderer::renderTextFromJson(array $json): string`（把 JSON 渲染成纯文本供 `menu_content` 用）
  - `AiMenuService::generateDailyMenuForUser()` 行为变更：优先用 `json_data` 渲染，校验失败走 fallback

- [ ] **Step 1: 写 MenuRenderer 失败测试**

创建 `tests/Unit/Services/Ai/MenuRendererTest.php`：

```php
<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\MenuRenderer;
use PHPUnit\Framework\TestCase;

class MenuRendererTest extends TestCase
{
    public function test_render_text_from_json_produces_readable_menu(): void
    {
        $json = [
            'greeting' => 'Good morning!',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'Tomato Toast', 'ingredients' => ['Tomato', 'Bread'], 'description' => 'Fresh start'],
                ['type' => 'lunch', 'name' => 'Spinach Salad', 'ingredients' => ['Spinach'], 'description' => 'Light and crisp'],
                ['type' => 'dinner', 'name' => 'Grilled Salmon', 'ingredients' => ['Salmon', 'Lemon'], 'description' => 'Omega-3 rich'],
            ],
            'tip' => 'Stay hydrated throughout the day!',
        ];

        $text = MenuRenderer::renderTextFromJson($json);

        $this->assertStringContainsString('Good morning!', $text);
        $this->assertStringContainsString('Breakfast: Tomato Toast', $text);
        $this->assertStringContainsString('Fresh start', $text);
        $this->assertStringContainsString('Lunch: Spinach Salad', $text);
        $this->assertStringContainsString('Dinner: Grilled Salmon', $text);
        $this->assertStringContainsString('Stay hydrated', $text);
    }

    public function test_render_capitalizes_meal_type(): void
    {
        $json = [
            'greeting' => 'Hi',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'X', 'ingredients' => [], 'description' => 'Y'],
                ['type' => 'lunch', 'name' => 'X', 'ingredients' => [], 'description' => 'Y'],
                ['type' => 'dinner', 'name' => 'X', 'ingredients' => [], 'description' => 'Y'],
            ],
            'tip' => 'Z',
        ];

        $text = MenuRenderer::renderTextFromJson($json);

        $this->assertStringContainsString('Breakfast:', $text);
        $this->assertStringContainsString('Lunch:', $text);
        $this->assertStringContainsString('Dinner:', $text);
    }
}
```

- [ ] **Step 2: 跑测试确认失败**

```bash
php vendor/bin/phpunit tests/Unit/Services/Ai/MenuRendererTest.php --no-coverage
```

预期：`Class 'App\Services\Ai\MenuRenderer' not found`

- [ ] **Step 3: 实现 MenuRenderer**

创建 `app/Services/Ai/MenuRenderer.php`：

```php
<?php

namespace App\Services\Ai;

/**
 * 菜单渲染器
 *
 * 职责：把结构化 JSON 菜单渲染成纯文本，供 menu_content 字段使用（兼容现有前端）。
 *
 * 输出格式：
 *   {greeting}
 *
 *   Breakfast: {name}
 *   {description}
 *
 *   Lunch: {name}
 *   {description}
 *
 *   Dinner: {name}
 *   {description}
 *
 *   💡 Tip: {tip}
 */
class MenuRenderer
{
    /**
     * @param  array{greeting:string,meals:array<int,array{type:string,name:string,ingredients:array<int,string>,description:string}>,tip:string}  $json
     */
    public static function renderTextFromJson(array $json): string
    {
        $parts = [$json['greeting']];

        foreach ($json['meals'] as $meal) {
            $type = ucfirst($meal['type']);
            $parts[] = ''; // 空行
            $parts[] = "{$type}: {$meal['name']}";
            $parts[] = $meal['description'];
        }

        $parts[] = '';
        $parts[] = "💡 Tip: {$json['tip']}";

        return implode("\n", $parts);
    }
}
```

- [ ] **Step 4: 改造 AiMenuService 集成 Validator + JSON**

`app/Services/AiMenuService.php` 关键修改：

**在类顶部加 use**：

```php
use App\Services\Ai\MenuOutputValidator;
use App\Services\Ai\MenuRenderer;
```

**在构造函数注入 Validator**：

```php
public function __construct(
    private readonly AiProviderInterface $provider,
    private readonly MenuOutputValidator $validator = new MenuOutputValidator,
) {}
```

**修改 `generateDailyMenuForUser()` 第 3-5 步（line 73-86）**：

```php
        // 3. 调 Provider
        $availableProducts = Product::where('stock', '>', 0)->pluck('name')->toArray();
        [$content, $tokens, $jsonData] = $this->callProvider($preferences, $availableProducts);

        // 4. 校验 + 渲染
        // 4a. JSON 模式：优先用结构化数据
        if ($jsonData !== null && $this->validator->validateJson($jsonData, $availableProducts)) {
            $content = MenuRenderer::renderTextFromJson($jsonData);
            // TODO: Task 6 把 $jsonData 存入 menu_json 列
        }
        // 4b. 自由文本模式：校验文本合法性
        elseif ($content !== '' && ! $this->validator->validate($content, $availableProducts)) {
            Log::warning('AiMenuService: provider output failed validation', [
                'provider' => $this->provider->name(),
                'content_preview' => substr($content, 0, 200),
            ]);
            $content = ''; // 触发 fallback
            $tokens = 0;
        }

        // 5. Provider 返回空 / 校验失败 → 本地模板
        if ($content === '') {
            $content = $this->generateFallbackMenu($preferences, $availableProducts);
            $tokens = 0;
        }
```

**修改 `callProvider()` 返回 3 元素（line 176-202）**：

```php
    /**
     * @return array{0:string,1:int,2:?array} [content, tokens_used, json_data]
     */
    private function callProvider(array $preferences, array $availableProducts): array
    {
        if ($this->provider instanceof NullProvider) {
            return ['', 0, null];
        }

        if (! $this->provider->isConfigured()) {
            Log::info('AiMenuService: provider not configured', [
                'provider' => $this->provider->name(),
            ]);

            return ['', 0, null];
        }

        try {
            return $this->provider->generate($preferences, $availableProducts);
        } catch (\Throwable $e) {
            Log::error('AiMenuService: unexpected provider exception', [
                'provider' => $this->provider->name(),
                'error' => $e->getMessage(),
            ]);

            return ['', 0, null];
        }
    }
```

**修改 `generateDailyMenu()` 兼容旧接口（line 120-125）**：

```php
    public function generateDailyMenu(array $preferences, array $availableProducts): string
    {
        [$content, , $jsonData] = $this->callProvider($preferences, $availableProducts);

        if ($jsonData !== null && $this->validator->validateJson($jsonData, $availableProducts)) {
            return MenuRenderer::renderTextFromJson($jsonData);
        }

        if ($content !== '' && $this->validator->validate($content, $availableProducts)) {
            return $content;
        }

        return $this->generateFallbackMenu($preferences, $availableProducts);
    }
```

- [ ] **Step 5: 在 AiMenuServiceTest 新增校验失败 case**

`tests/Unit/Services/AiMenuServiceTest.php` 末尾追加：

```php
    /** 校验失败：Provider 返回含黑名单关键词的文本 → 走 fallback */
    public function test_provider_output_with_blacklist_keyword_falls_back(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'As an AI, I cannot help you. '.str_repeat('x', 100)]]],
                ]],
                'usageMetadata' => ['totalTokenCount' => 50],
            ], 200),
        ]);

        $menu = $this->service->generateDailyMenuForUser($this->user);

        $this->assertStringContainsString('[AI Demo]', $menu->menu_content, '黑名单关键词应触发 fallback');
        $this->assertEquals(0, $menu->tokens_used, '校验失败时 tokens 应清零');
    }

    /** 校验失败：Provider 返回过短内容 → 走 fallback */
    public function test_provider_output_too_short_falls_back(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Too short']]],
                ]],
                'usageMetadata' => ['totalTokenCount' => 10],
            ], 200),
        ]);

        $menu = $this->service->generateDailyMenuForUser($this->user);

        $this->assertStringContainsString('[AI Demo]', $menu->menu_content);
    }

    /** JSON 模式：Provider 返回合法 JSON → 渲染成文本 */
    public function test_provider_json_output_is_rendered_to_text(): void
    {
        $json = [
            'greeting' => 'Good day!',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'Tomato Toast', 'ingredients' => ['Tomato'], 'description' => 'Fresh'],
                ['type' => 'lunch', 'name' => 'Spinach Salad', 'ingredients' => ['Spinach'], 'description' => 'Light'],
                ['type' => 'dinner', 'name' => 'Salmon', 'ingredients' => ['Salmon'], 'description' => 'Rich'],
            ],
            'tip' => 'Stay healthy!',
        ];

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode($json)]]],
                ]],
                'usageMetadata' => ['totalTokenCount' => 150],
            ], 200),
        ]);

        // 确保有对应商品
        Product::factory()->create(['name' => 'Tomato', 'stock' => 10]);
        Product::factory()->create(['name' => 'Spinach', 'stock' => 10]);
        Product::factory()->create(['name' => 'Salmon', 'stock' => 10]);

        $menu = $this->service->generateDailyMenuForUser($this->user);

        $this->assertStringContainsString('Good day!', $menu->menu_content);
        $this->assertStringContainsString('Breakfast: Tomato Toast', $menu->menu_content);
        $this->assertStringContainsString('💡 Tip: Stay healthy!', $menu->menu_content);
        $this->assertEquals(150, $menu->tokens_used);
    }
```

文件顶部加 `use Illuminate\Support\Facades\Http;`。

- [ ] **Step 6: 跑测试确认通过 + 全量回归**

```bash
php vendor/bin/phpunit tests/Unit/Services/Ai/MenuRendererTest.php --no-coverage
php vendor/bin/phpunit tests/Unit/Services/AiMenuServiceTest.php --no-coverage
php vendor/bin/phpunit tests/ --no-coverage
```

预期：新测试通过；全量 86 + 新增（8+5+4+2+3=22）= 108 tests passed / 0 failed

- [ ] **Step 7: Commit**

```bash
git add app/Services/Ai/MenuRenderer.php app/Services/AiMenuService.php tests/Unit/Services/Ai/MenuRendererTest.php tests/Unit/Services/AiMenuServiceTest.php
git commit -m "feat(ai): integrate output validation and JSON rendering into AiMenuService"
```

---

