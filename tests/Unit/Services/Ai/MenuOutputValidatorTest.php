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
