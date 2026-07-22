### Task 1: MenuOutputValidator — 输出校验器

**Files:**
- Create: `app/Services/Ai/MenuOutputValidator.php`
- Test: `tests/Unit/Services/Ai/MenuOutputValidatorTest.php`

**Interfaces:**
- Consumes: 无（独立组件）
- Produces:
  - `MenuOutputValidator::validate(string $content, array $availableProducts): bool`
  - `MenuOutputValidator::validateJson(array $data, array $availableProducts): bool`
  - 常量 `MenuOutputValidator::MIN_LENGTH = 50`、`MAX_LENGTH = 2000`
  - 常量 `MenuOutputValidator::BLACKLIST = ['as an ai', 'i cannot', "i'm sorry", '```', 'http://', 'https://', 'fallback']`

- [ ] **Step 1: 写失败测试**

创建 `tests/Unit/Services/Ai/MenuOutputValidatorTest.php`：

```php
<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\MenuOutputValidator;
use PHPUnit\Framework\TestCase;

class MenuOutputValidatorTest extends TestCase
{
    private MenuOutputValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new MenuOutputValidator;
    }

    public function test_validate_rejects_too_short_content(): void
    {
        $this->assertFalse($this->validator->validate('short', ['Tomato']));
    }

    public function test_validate_rejects_too_long_content(): void
    {
        $long = str_repeat('a', 2001);
        $this->assertFalse($this->validator->validate($long, ['Tomato']));
    }

    public function test_validate_rejects_blacklist_keywords(): void
    {
        $base = str_repeat('x', 100);
        $this->assertFalse($this->validator->validate("As an AI, {$base}", ['Tomato']));
        $this->assertFalse($this->validator->validate("I cannot help. {$base}", ['Tomato']));
        $this->assertFalse($this->validator->validate("Here is code: ```php {$base}", ['Tomato']));
        $this->assertFalse($this->validator->validate("Visit https://spam.com {$base}", ['Tomato']));
    }

    public function test_validate_rejects_content_without_any_product_mention(): void
    {
        $content = str_repeat('Generic healthy meal advice. ', 10); // 不含商品名
        $this->assertFalse($this->validator->validate($content, ['Tomato', 'Spinach']));
    }

    public function test_validate_accepts_valid_menu_with_product_mention(): void
    {
        $content = str_repeat('Start your day with fresh Tomato salad. ', 5);
        $this->assertTrue($this->validator->validate($content, ['Tomato', 'Spinach']));
    }

    public function test_validate_json_rejects_invalid_structure(): void
    {
        $this->assertFalse($this->validator->validateJson([], ['Tomato']));
        $this->assertFalse($this->validator->validateJson(['meals' => []], ['Tomato']));
        $this->assertFalse($this->validator->validateJson(['meals' => [['type' => 'lunch']]], ['Tomato'])); // 缺字段
    }

    public function test_validate_json_accepts_valid_structure(): void
    {
        $data = [
            'greeting' => 'Good morning!',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'Tomato Toast', 'ingredients' => ['Tomato'], 'description' => 'Fresh start'],
                ['type' => 'lunch', 'name' => 'Spinach Salad', 'ingredients' => ['Spinach'], 'description' => 'Light lunch'],
                ['type' => 'dinner', 'name' => 'Grilled Salmon', 'ingredients' => ['Salmon'], 'description' => 'Omega-3 rich'],
            ],
            'tip' => 'Stay hydrated!',
        ];
        $this->assertTrue($this->validator->validateJson($data, ['Tomato', 'Spinach', 'Salmon']));
    }

    public function test_validate_json_rejects_when_no_product_mentioned(): void
    {
        $data = [
            'greeting' => 'Hello',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'Toast', 'ingredients' => ['Bread'], 'description' => 'X'],
                ['type' => 'lunch', 'name' => 'Rice', 'ingredients' => ['Rice'], 'description' => 'Y'],
                ['type' => 'dinner', 'name' => 'Pasta', 'ingredients' => ['Pasta'], 'description' => 'Z'],
            ],
            'tip' => 'Tip',
        ];
        $this->assertFalse($this->validator->validateJson($data, ['Tomato', 'Spinach']));
    }
}
```

- [ ] **Step 2: 跑测试确认失败**

```bash
cd d:/FreshToday-AI
php vendor/bin/phpunit tests/Unit/Services/Ai/MenuOutputValidatorTest.php --no-coverage
```

预期：`Error: Class 'App\Services\Ai\MenuOutputValidator' not found`

- [ ] **Step 3: 实现 MenuOutputValidator**

创建 `app/Services/Ai/MenuOutputValidator.php`：

```php
<?php

namespace App\Services\Ai;

/**
 * AI 菜单输出校验器
 *
 * 职责：对 Provider 返回的自由文本或结构化 JSON 做合法性校验，
 *       拦截跑题、注入、拒绝回答、广告链接等异常输出。
 *
 * 校验规则（自由文本）：
 *  1. 长度 ∈ [MIN_LENGTH, MAX_LENGTH]
 *  2. 不含 BLACKLIST 关键词（注入/拒绝/代码块/URL/FALLBACK 信号）
 *  3. 至少提到 1 个 availableProducts 中的商品名（防止完全跑题）
 *
 * 校验规则（JSON）：
 *  1. 必须含 greeting / meals / tip 三个 key
 *  2. meals 必须是数组且 count=3
 *  3. 每个 meal 必须含 type/name/ingredients/description
 *  4. type ∈ {breakfast, lunch, dinner}
 *  5. 所有 ingredients 拼接后至少匹配 1 个 availableProducts
 */
class MenuOutputValidator
{
    public const MIN_LENGTH = 50;

    public const MAX_LENGTH = 2000;

    public const BLACKLIST = [
        'as an ai',
        'i cannot',
        "i'm sorry",
        '```',
        'http://',
        'https://',
        'fallback',
    ];

    private const VALID_MEAL_TYPES = ['breakfast', 'lunch', 'dinner'];

    /**
     * 校验自由文本输出
     *
     * @param  array<int,string>  $availableProducts
     */
    public function validate(string $content, array $availableProducts): bool
    {
        $len = mb_strlen($content);
        if ($len < self::MIN_LENGTH || $len > self::MAX_LENGTH) {
            return false;
        }

        $lower = mb_strtolower($content);
        foreach (self::BLACKLIST as $word) {
            if (mb_strpos($lower, $word) !== false) {
                return false;
            }
        }

        return $this->mentionsAnyProduct($content, $availableProducts);
    }

    /**
     * 校验结构化 JSON 输出
     *
     * @param  array<string,mixed>  $data
     * @param  array<int,string>  $availableProducts
     */
    public function validateJson(array $data, array $availableProducts): bool
    {
        if (! isset($data['greeting'], $data['meals'], $data['tip'])) {
            return false;
        }

        if (! is_array($data['meals']) || count($data['meals']) !== 3) {
            return false;
        }

        $allIngredients = [];
        foreach ($data['meals'] as $meal) {
            if (! is_array($meal)) {
                return false;
            }
            if (! isset($meal['type'], $meal['name'], $meal['ingredients'], $meal['description'])) {
                return false;
            }
            if (! in_array($meal['type'], self::VALID_MEAL_TYPES, true)) {
                return false;
            }
            if (! is_array($meal['ingredients'])) {
                return false;
            }
            $allIngredients = array_merge($allIngredients, $meal['ingredients']);
        }

        $ingredientsText = implode(' ', $allIngredients).' '.($data['greeting'] ?? '').' '.($data['tip'] ?? '');

        return $this->mentionsAnyProduct($ingredientsText, $availableProducts);
    }

    /**
     * 检查文本是否提到任一商品名（大小写不敏感）
     *
     * @param  array<int,string>  $availableProducts
     */
    private function mentionsAnyProduct(string $text, array $availableProducts): bool
    {
        $lower = mb_strtolower($text);
        foreach ($availableProducts as $product) {
            if (mb_strpos($lower, mb_strtolower($product)) !== false) {
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 4: 跑测试确认通过**

```bash
php vendor/bin/phpunit tests/Unit/Services/Ai/MenuOutputValidatorTest.php --no-coverage
```

预期：`OK (8 tests, 8 assertions)`

- [ ] **Step 5: Commit**

```bash
git add app/Services/Ai/MenuOutputValidator.php tests/Unit/Services/Ai/MenuOutputValidatorTest.php
git commit -m "feat(ai): add MenuOutputValidator for output validation"
```

---

