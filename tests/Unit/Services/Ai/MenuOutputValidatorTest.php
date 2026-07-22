<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\MenuOutputValidator;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_validate_json_requires_each_meal_type_exactly_once(): void
    {
        $data = [
            'greeting' => 'Good morning!',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'Tomato Toast', 'ingredients' => ['Tomato'], 'description' => 'Fresh start'],
                ['type' => 'lunch', 'name' => 'Spinach Salad', 'ingredients' => ['Spinach'], 'description' => 'Light lunch'],
                ['type' => 'lunch', 'name' => 'Salmon Salad', 'ingredients' => ['Salmon'], 'description' => 'Repeated lunch'],
            ],
            'tip' => 'Stay hydrated!',
        ];

        $this->assertFalse($this->validator->validateJson($data, ['Tomato', 'Spinach', 'Salmon']));
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

    /** 严格校验：任何 1 个食材不在商品库 → 校验失败 */
    public function test_validate_json_rejects_when_any_ingredient_not_in_products(): void
    {
        $data = [
            'greeting' => 'Hello',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'Toast', 'ingredients' => ['Tomato'], 'description' => 'X'],
                ['type' => 'lunch', 'name' => 'Salad', 'ingredients' => ['Spinach'], 'description' => 'Y'],
                ['type' => 'dinner', 'name' => 'Luxury Dish', 'ingredients' => ['Truffle'], 'description' => 'Z'], // Truffle 不在商品库
            ],
            'tip' => 'Tip',
        ];
        $this->assertFalse($this->validator->validateJson($data, ['Tomato', 'Spinach']), 'Truffle 不在商品库，应校验失败');
    }

    /** Provider ingredients must use the exact candidate names. */
    public function test_validate_json_rejects_fuzzy_match_ingredient_in_product(): void
    {
        $data = [
            'greeting' => 'Hello',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'X', 'ingredients' => ['菜心'], 'description' => 'A'],
                ['type' => 'lunch', 'name' => 'Y', 'ingredients' => ['白菜'], 'description' => 'B'],
                ['type' => 'dinner', 'name' => 'Z', 'ingredients' => ['紅蘿蔔'], 'description' => 'C'],
            ],
            'tip' => 'Tip',
        ];
        $products = ['本地有機菜心', '本地有機白菜', '本地有機紅蘿蔔'];
        $this->assertFalse($this->validator->validateJson($data, $products));
    }

    /** Provider ingredients may not add text around a candidate name. */
    public function test_validate_json_rejects_fuzzy_match_product_in_ingredient(): void
    {
        $data = [
            'greeting' => 'Hello',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'X', 'ingredients' => ['新鮮有機菜心'], 'description' => 'A'],
                ['type' => 'lunch', 'name' => 'Y', 'ingredients' => ['嫩白菜'], 'description' => 'B'],
                ['type' => 'dinner', 'name' => 'Z', 'ingredients' => ['甜紅蘿蔔'], 'description' => 'C'],
            ],
            'tip' => 'Tip',
        ];
        $products = ['有機菜心', '白菜', '紅蘿蔔'];
        $this->assertFalse($this->validator->validateJson($data, $products));
    }

    public function test_validate_json_rejects_non_string_and_empty_ingredients_without_throwing(): void
    {
        $data = [
            'greeting' => 'Hello',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'X', 'ingredients' => [['name' => 'Tomato']], 'description' => 'A'],
                ['type' => 'lunch', 'name' => 'Y', 'ingredients' => [''], 'description' => 'B'],
                ['type' => 'dinner', 'name' => 'Z', 'ingredients' => ['Tomato'], 'description' => 'C'],
            ],
            'tip' => 'Tip',
        ];

        $this->assertFalse($this->validator->validateJson($data, ['Tomato']));
    }

    #[DataProvider('invalidRenderableFieldProvider')]
    public function test_validate_json_rejects_non_string_or_empty_renderable_fields(
        string $field,
        mixed $invalidValue,
    ): void
    {
        $data = [
            'greeting' => 'Hello',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'X', 'ingredients' => ['Tomato'], 'description' => 'A'],
                ['type' => 'lunch', 'name' => 'Y', 'ingredients' => ['Tomato'], 'description' => 'B'],
                ['type' => 'dinner', 'name' => 'Z', 'ingredients' => ['Tomato'], 'description' => 'C'],
            ],
            'tip' => 'Tip',
        ];

        match ($field) {
            'greeting', 'tip' => $data[$field] = $invalidValue,
            'name', 'description' => $data['meals'][0][$field] = $invalidValue,
        };

        $this->assertFalse($this->validator->validateJson($data, ['Tomato']));
    }

    public static function invalidRenderableFieldProvider(): array
    {
        return [
            'greeting array' => ['greeting', ['invalid']],
            'tip array' => ['tip', ['invalid']],
            'meal name array' => ['name', ['invalid']],
            'meal description array' => ['description', ['invalid']],
            'greeting empty' => ['greeting', ''],
            'tip empty' => ['tip', ''],
            'meal name empty' => ['name', ''],
            'meal description empty' => ['description', ''],
        ];
    }

    /** A mixed payload is rejected as soon as one ingredient is not an exact candidate. */
    public function test_validate_json_rejects_mixed_scenario_with_non_exact_ingredient(): void
    {
        $data = [
            'greeting' => 'Hello',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'X', 'ingredients' => ['Tomato'], 'description' => 'A'], // 精确匹配
                ['type' => 'lunch', 'name' => 'Y', 'ingredients' => ['菜心'], 'description' => 'B'], // 模糊匹配到"本地有機菜心"
                ['type' => 'dinner', 'name' => 'Z', 'ingredients' => ['Caviar'], 'description' => 'C'], // 不匹配
            ],
            'tip' => 'Tip',
        ];
        $products = ['Tomato', '本地有機菜心'];
        $this->assertFalse($this->validator->validateJson($data, $products), 'Caviar 不在商品库，应校验失败');
    }
}
