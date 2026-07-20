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
