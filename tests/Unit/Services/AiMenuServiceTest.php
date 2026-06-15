<?php

namespace Tests\Unit\Services;

use App\Exceptions\GuardFailedException;
use App\Models\DailyMenu;
use App\Models\Product;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\AiMenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
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
        $this->app->forgetInstance(\App\Services\Ai\Contracts\AiProviderInterface::class);
        $this->app->forgetInstance(\App\Services\AiMenuService::class);
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
}
