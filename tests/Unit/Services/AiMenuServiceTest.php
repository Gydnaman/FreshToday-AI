<?php

namespace Tests\Unit\Services;

use App\Exceptions\GuardFailedException;
use App\Models\DailyMenu;
use App\Models\Product;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\AiMenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AiMenuService 测试
 * 覆盖：缓存 / 限流 / 降级
 */
class AiMenuServiceTest extends TestCase
{
    use RefreshDatabase;

    private AiMenuService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Sprint 2：注入 config 后 forget 单例，迫使容器重新解析
        config(['ai.default' => 'gemini']);
        config(['ai.providers.gemini.key' => 'fake_key_for_test']);
        putenv('GEMINI_API_KEY=fake_key_for_test');
        putenv('OPENAI_API_KEY');
        putenv('DEEPSEEK_API_KEY');
        config(['ai.providers.openai.key' => null]);
        config(['ai.providers.deepseek.key' => null]);
        $this->app->forgetInstance(AiProviderInterface::class);
        $this->app->forgetInstance(AiMenuService::class);
        $this->service = app(AiMenuService::class);
        $this->user = User::factory()->create();
        UserPreference::factory()->for($this->user)->create();
        Product::factory()->count(3)->create();
    }

    /** GUARD-AI：用户未填问卷时拒绝 */
    public function test_generate_rejects_when_no_preferences(): void
    {
        $user = User::factory()->create();
        $this->expectException(GuardFailedException::class);
        $this->service->generateDailyMenuForUser($user);
    }

    /** 首次生成：写入 DailyMenu，source = gemini（无 key 走 fallback） */
    public function test_generate_creates_daily_menu_record(): void
    {
        $menu = $this->service->generateDailyMenuForUser($this->user);

        $this->assertNotEmpty($menu->menu_content);
        $this->assertEquals('gemini', $menu->source);
        $this->assertEquals($this->user->id, $menu->user_id);
    }

    /** 同日重复生成：返回已有 DailyMenu（无重复行） */
    public function test_second_call_returns_existing_menu(): void
    {
        $first = $this->service->generateDailyMenuForUser($this->user);
        $second = $this->service->generateDailyMenuForUser($this->user);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, DailyMenu::where('user_id', $this->user->id)->count());
    }

    /** GUARD-AI-RATE：每日 regenerate 超过 3 次拒绝 */
    public function test_regenerate_rate_limit_3_per_day(): void
    {
        // 3 次 OK
        for ($i = 0; $i < 3; $i++) {
            $this->service->regenerate($this->user);
        }

        // 第 4 次拒绝
        $this->expectException(GuardFailedException::class);
        $this->service->regenerate($this->user);
    }

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

        $this->assertIsArray($menu->menu_json, '黑名单关键词应触发结构化 fallback');
        $this->assertCount(3, $menu->menu_json['meals']);
        $this->assertStringContainsString($menu->menu_json['meals'][0]['name'], $menu->menu_content);
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

        $this->assertIsArray($menu->menu_json);
        $this->assertCount(3, $menu->menu_json['meals']);
        $this->assertStringContainsString($menu->menu_json['meals'][0]['name'], $menu->menu_content);
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

        // menu_json 入库（Task 6）
        $this->assertIsArray($menu->menu_json);
        $this->assertSame('Good day!', $menu->menu_json['greeting']);
        $this->assertCount(3, $menu->menu_json['meals']);
    }

    /** 缓存命中时 menu_json 保留（Task 6 Critical fix 回归测试） */
    public function test_menu_json_is_preserved_on_cache_hit(): void
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

        Product::factory()->create(['name' => 'Tomato', 'stock' => 10]);
        Product::factory()->create(['name' => 'Spinach', 'stock' => 10]);
        Product::factory()->create(['name' => 'Salmon', 'stock' => 10]);

        // 第一次调用：正常生成（写缓存 + DB 含 menu_json）
        $menu1 = $this->service->generateDailyMenuForUser($this->user);
        $this->assertIsArray($menu1->menu_json);

        // 第二次调用：缓存命中（步骤 1 upsertMenu(..., null)）
        // 修复前：menu_json 被覆盖为 null
        // 修复后：menu_json 保留
        $menu2 = $this->service->generateDailyMenuForUser($this->user);
        $this->assertIsArray($menu2->menu_json, '缓存命中后 menu_json 必须保留');
        $this->assertSame('Good day!', $menu2->menu_json['greeting']);
        $this->assertCount(3, $menu2->menu_json['meals']);
    }
}
