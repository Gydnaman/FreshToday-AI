<?php

namespace Tests\Feature\Web;

use App\Models\DailyMenu;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /** 未登录访问 dashboard 重定向到登录页 */
    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirectContains('/login');
    }

    /** 登录后无菜单时显示默认提示 */
    public function test_dashboard_shows_default_message_when_no_menu(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('No menu generated yet');
    }

    /** 有菜单但无 menu_json 时显示纯文本 */
    public function test_dashboard_shows_plain_text_when_no_menu_json(): void
    {
        $user = User::factory()->create();
        DailyMenu::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'menu_content' => 'Simple text menu without JSON',
            'menu_json' => null,
            'source' => 'fallback',
            'tokens_used' => 0,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Simple text menu without JSON');
    }

    /** 有 menu_json 时渲染 HTML 含食材链接 */
    public function test_dashboard_renders_html_with_product_links(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Organic Tomato', 'stock' => 10]);

        $menuJson = [
            'greeting' => 'Good morning!',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'Tomato Toast', 'ingredients' => ['Organic Tomato'], 'description' => 'Fresh Organic Tomato on toast'],
                ['type' => 'lunch', 'name' => 'Salad', 'ingredients' => [], 'description' => 'Light'],
                ['type' => 'dinner', 'name' => 'Fish', 'ingredients' => [], 'description' => 'Rich'],
            ],
            'tip' => 'Stay healthy!',
        ];

        DailyMenu::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'menu_content' => 'Good morning! ... (text version)',
            'menu_json' => $menuJson,
            'source' => 'deepseek',
            'tokens_used' => 150,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        // HTML 版本渲染（含链接）
        $response->assertSee('catalog#product-'.$product->id, false);
        $response->assertSee('Organic Tomato', false);
        // 食材名被包装成 <a> 标签
        $this->assertStringContainsString(
            '<a href="/catalog#product-'.$product->id.'"',
            $response->getContent()
        );
    }

    /** menu_json 中的食材无对应商品时保持纯文本 */
    public function test_dashboard_handles_ingredients_without_products(): void
    {
        $user = User::factory()->create();

        $menuJson = [
            'greeting' => 'Hi',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'Mystery Dish', 'ingredients' => ['Unknown Ingredient'], 'description' => 'With Unknown Ingredient'],
                ['type' => 'lunch', 'name' => 'X', 'ingredients' => [], 'description' => 'Y'],
                ['type' => 'dinner', 'name' => 'X', 'ingredients' => [], 'description' => 'Z'],
            ],
            'tip' => 'Tip',
        ];

        DailyMenu::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'menu_content' => 'Text',
            'menu_json' => $menuJson,
            'source' => 'deepseek',
            'tokens_used' => 100,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        // Unknown Ingredient 保持纯文本（不出现链接）
        $response->assertSee('Unknown Ingredient');
        $this->assertStringNotContainsString('Unknown Ingredient</a>', $response->getContent());
    }

    /** 只显示当日菜单（昨天的不显示） */
    public function test_dashboard_only_shows_today_menu(): void
    {
        $user = User::factory()->create();

        // 昨天的菜单
        DailyMenu::create([
            'user_id' => $user->id,
            'date' => now()->subDay()->toDateString(),
            'menu_content' => 'Yesterday menu',
            'menu_json' => null,
            'source' => 'deepseek',
            'tokens_used' => 50,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('No menu generated yet'); // 昨天的不算，显示默认提示
        $response->assertDontSee('Yesterday menu');
    }
}
