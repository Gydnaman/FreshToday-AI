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

    public function test_render_html_from_json_wraps_ingredients_with_links(): void
    {
        $json = [
            'greeting' => 'Good morning!',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'Tomato Toast', 'ingredients' => ['Tomato'], 'description' => 'Fresh Tomato on toast'],
                ['type' => 'lunch', 'name' => 'Spinach Salad', 'ingredients' => ['Spinach'], 'description' => 'Light Spinach bowl'],
                ['type' => 'dinner', 'name' => 'Grilled Salmon', 'ingredients' => ['Salmon'], 'description' => 'Omega-3 rich Salmon'],
            ],
            'tip' => 'Stay hydrated!',
        ];

        // 模拟商品库（name => id）
        $productMap = ['Tomato' => 3, 'Spinach' => 5, 'Salmon' => 8];

        $html = MenuRenderer::renderHtmlFromJson($json, $productMap);

        // 食材被包装成链接
        $this->assertStringContainsString('<a href="/catalog#product-3"', $html);
        $this->assertStringContainsString('>Tomato</a>', $html);
        $this->assertStringContainsString('<a href="/catalog#product-5"', $html);
        $this->assertStringContainsString('>Spinach</a>', $html);
        $this->assertStringContainsString('<a href="/catalog#product-8"', $html);
        $this->assertStringContainsString('>Salmon</a>', $html);
    }

    public function test_render_html_escapes_non_ingredient_text(): void
    {
        $json = [
            'greeting' => '<script>alert("xss")</script>Good day!',
            'meals' => [
                ['type' => 'breakfast', 'name' => '<b>Tomato</b> Toast', 'ingredients' => ['Tomato'], 'description' => 'Fresh & healthy'],
                ['type' => 'lunch', 'name' => 'Salad', 'ingredients' => [], 'description' => 'Light'],
                ['type' => 'dinner', 'name' => 'Fish', 'ingredients' => [], 'description' => 'Rich'],
            ],
            'tip' => 'Tip!',
        ];

        $html = MenuRenderer::renderHtmlFromJson($json, ['Tomato' => 3]);

        // 非食材文本被转义（防 XSS）
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        // 食材链接内的商品名也被转义，但链接本身保留
        $this->assertStringContainsString('<a href="/catalog#product-3"', $html);
    }

    public function test_render_html_skips_ingredients_not_in_product_map(): void
    {
        $json = [
            'greeting' => 'Hi',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'Tomato Special', 'ingredients' => ['Tomato', 'Unknown Veg'], 'description' => 'With Tomato and Unknown Veg'],
                ['type' => 'lunch', 'name' => 'X', 'ingredients' => [], 'description' => 'Y'],
                ['type' => 'dinner', 'name' => 'X', 'ingredients' => [], 'description' => 'Y'],
            ],
            'tip' => 'Z',
        ];

        $html = MenuRenderer::renderHtmlFromJson($json, ['Tomato' => 3]);

        // Tomato 有映射 → 渲染链接（第一次出现）
        $this->assertStringContainsString('<a href="/catalog#product-3"', $html);
        // Unknown Veg 无映射 → 保持纯文本（不出现链接）
        $this->assertStringNotContainsString('Unknown Veg</a>', $html);
    }

    public function test_render_html_includes_meal_structure(): void
    {
        $json = [
            'greeting' => 'Good morning!',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'Toast', 'ingredients' => ['Bread'], 'description' => 'Fresh'],
                ['type' => 'lunch', 'name' => 'Salad', 'ingredients' => ['Lettuce'], 'description' => 'Light'],
                ['type' => 'dinner', 'name' => 'Fish', 'ingredients' => ['Salmon'], 'description' => 'Rich'],
            ],
            'tip' => 'Stay healthy!',
        ];

        $html = MenuRenderer::renderHtmlFromJson($json, []);

        // 结构完整性
        $this->assertStringContainsString('Good morning!', $html);
        $this->assertStringContainsString('Breakfast:', $html);
        $this->assertStringContainsString('Toast', $html);
        $this->assertStringContainsString('Fresh', $html);
        $this->assertStringContainsString('Stay healthy!', $html);
    }
}
