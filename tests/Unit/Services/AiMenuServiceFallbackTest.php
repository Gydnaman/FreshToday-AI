<?php

namespace Tests\Unit\Services;

use App\Models\DailyMenu;
use App\Models\Product;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Ai\Providers\NullProvider;
use App\Services\AiMenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AI 菜单降级路径专项测试
 *
 * 场景：
 *  - Gemini 5xx：自动降级 fallback 模板，source='gemini'，menu_content 非空
 *  - Gemini 4xx：降级 fallback
 *  - Gemini 超时 (Http::timeout 8s)：降级 fallback
 *  - 无 GEMINI_API_KEY：降级 fallback，tokens_used=0
 *  - 降级结果可入 DailyMenu + Cache 复用
 *  - 降级后 24h 内不重复调用 Gemini（命中 cache）
 *  - 边界：可售商品为空时 fallback 文本不爆
 *
 * 与 AiMenuServiceTest 的差异：
 *  - AiMenuServiceTest 覆盖：缓存 / 限流 / 缺失偏好 / 同日复用
 *  - 本测试覆盖：上游 API 失败的 5 种降级路径
 */
class AiMenuServiceFallbackTest extends TestCase
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
        UserPreference::factory()->for($this->user)->create([
            'usage_purpose' => 'Reduce Carbon Footprint',
            'dietary_habits' => 'Vegetarian/Vegan',
            'goals' => 'Eat greener',
            'cooking_skill' => 'Intermediate',
            'budget_hkd' => 800,
        ]);
        Product::factory()->count(5)->create();
        // 注入测试用 key（Http::fake 不受 env 限制）
        config(['services.gemini.key' => 'fake_key_for_test']);
        putenv('GEMINI_API_KEY=fake_key_for_test');
        // Sprint 2：显式锁定到 Gemini Provider（避免与 OPENAI/DEEPSEEK env 冲突）
        config(['ai.default' => 'gemini']);
        config(['ai.providers.gemini.key' => 'fake_key_for_test']);
        // 清空其他 provider env，防止本机真实 KEY 干扰探测
        putenv('OPENAI_API_KEY');
        putenv('DEEPSEEK_API_KEY');
        config(['ai.providers.openai.key' => null]);
        config(['ai.providers.deepseek.key' => null]);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        putenv('GEMINI_API_KEY'); // 清理
        putenv('OPENAI_API_KEY');
        putenv('DEEPSEEK_API_KEY');
        Cache::flush();
        parent::tearDown();
    }

    /**
     * 场景 1：Gemini 返回 500 Internal Server Error
     * 预期：fallback 模板内容写入 DailyMenu，source='gemini'，menu_content 非空
     */
    public function test_gemini_5xx_falls_back_to_template(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                ['error' => ['code' => 500, 'message' => 'Internal error']],
                500,
            ),
        ]);

        $menu = $this->service->generateDailyMenuForUser($this->user);

        $this->assertNotEmpty($menu->menu_content, '5xx 降级后内容必须非空');
        $this->assertStringContainsString('[AI Demo]', $menu->menu_content, '降级模板应含 [AI Demo] 标识');
        $this->assertEquals('gemini', $menu->source, '5xx 降级时 source 仍记 gemini（fallback 由 callGemini 内部触发）');
        $this->assertEquals(0, $menu->tokens_used, '5xx 降级时 tokens_used 应为 0');
    }

    /**
     * 场景 2：Gemini 返回 503 Service Unavailable（限流/维护）
     * 预期：fallback 模板生效
     */
    public function test_gemini_503_falls_back_to_template(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('Service Unavailable', 503),
        ]);

        $menu = $this->service->generateDailyMenuForUser($this->user);

        $this->assertNotEmpty($menu->menu_content);
        $this->assertStringContainsString('Vegetarian', $menu->menu_content, 'fallback 文本应反映用户 dietary_habits');
    }

    /**
     * 场景 3：Gemini 返回 400 Bad Request（参数错误）
     * 预期：fallback 模板生效（4xx 走非 successful 分支）
     */
    public function test_gemini_4xx_falls_back_to_template(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                ['error' => ['code' => 400, 'message' => 'Invalid API key']],
                400,
            ),
        ]);

        $menu = $this->service->generateDailyMenuForUser($this->user);

        $this->assertNotEmpty($menu->menu_content, '4xx 降级后内容必须非空');
    }

    /**
     * 场景 4：Gemini 正常返回但 candidates 为空
     * 预期：fallback 模板生效
     */
    public function test_gemini_empty_candidates_falls_back(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [],
                'usageMetadata' => ['totalTokenCount' => 0],
            ], 200),
        ]);

        $menu = $this->service->generateDailyMenuForUser($this->user);

        $this->assertNotEmpty($menu->menu_content, '空 candidates 降级后内容必须非空');
        $this->assertStringContainsString('[AI Demo]', $menu->menu_content);
    }

    /**
     * 场景 5：无 GEMINI_API_KEY 环境变量
     * 预期：直接走 fallback，不发起 HTTP 请求
     */
    public function test_no_api_key_falls_back_without_http_call(): void
    {
        // Sprint 2：本测试核心是"无 key 时 AiMenuService 不发任何 HTTP"
        // 直接 bind 一个 NullProvider 进容器，绕开 Factory 探测（避免与 .env 真实 KEY 冲突）
        $this->app->forgetInstance(AiProviderInterface::class);
        $this->app->forgetInstance(AiMenuService::class);
        $this->app->instance(
            AiProviderInterface::class,
            new NullProvider
        );
        $this->service = app(AiMenuService::class);
        Http::fake(); // 若代码不调用，recorded 数组应为空

        $menu = $this->service->generateDailyMenuForUser($this->user);

        $this->assertNotEmpty($menu->menu_content);
        Http::assertNothingSent(); // 关键断言：未发出任何 HTTP 请求
    }

    /**
     * 场景 6：降级后结果被缓存 24h
     * 第二次调用同用户同日，应命中 cache 不再调 Gemini（即便 Gemini 已恢复）
     */
    public function test_fallback_result_is_cached_for_24h(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('Server Error', 500),
        ]);

        $first = $this->service->generateDailyMenuForUser($this->user);
        $firstContent = $first->menu_content;

        // 移除 fake，第二次调用应完全走 cache
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('Should not be called', 200),
        ]);

        $second = $this->service->generateDailyMenuForUser($this->user);

        $this->assertEquals($firstContent, $second->menu_content, '24h 缓存命中：内容一致');
        Http::assertNothingSent(); // 验证第二次未发起 HTTP

        // DB 也只有 1 条
        $this->assertEquals(1, DailyMenu::where('user_id', $this->user->id)->count());
    }

    /**
     * 场景 7：可售商品列表为空时 fallback 文本不爆
     * 预期：fallback 用默认 'seasonal produce' 兜底
     */
    public function test_fallback_with_no_available_products(): void
    {
        // 删除所有可售商品（Product::where('stock','>',0) 即返回空）
        Product::query()->update(['stock' => 0]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('Error', 500),
        ]);

        $menu = $this->service->generateDailyMenuForUser($this->user);

        $this->assertNotEmpty($menu->menu_content);
        $this->assertStringContainsString('seasonal produce', $menu->menu_content, '无可售商品时应使用 seasonal produce 兜底');
    }

    /**
     * 场景 8：Http::fake 抛 ConnectionException（网络中断）
     * 预期：catch \Exception 分支兜底，fallback 模板生效
     */
    public function test_network_exception_falls_back(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Network unreachable');
        });

        $menu = $this->service->generateDailyMenuForUser($this->user);

        $this->assertNotEmpty($menu->menu_content, '网络异常时降级后内容必须非空');
        $this->assertStringContainsString('[AI Demo]', $menu->menu_content);
    }

    /**
     * 场景 9：降级文本反映用户偏好（dietary_habits）
     * 验证 fallback 是个性化的，不是固定文案
     */
    public function test_fallback_text_includes_user_dietary_preference(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('Error', 500),
        ]);

        $menu = $this->service->generateDailyMenuForUser($this->user);

        // preferences.dietary_habits = 'Vegetarian/Vegan'
        $this->assertStringContainsString('Vegetarian', $menu->menu_content, 'fallback 文本应反映 dietary_habits');
    }

    /**
     * 场景 10：regenerate() 在降级后仍能工作（不受 5xx 影响）
     * 验证：regenerate → invalidate cache → 下次仍走降级（除非已 cached）
     */
    public function test_regenerate_after_5xx_still_works(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('Error', 500),
        ]);

        $first = $this->service->generateDailyMenuForUser($this->user);
        $this->assertNotEmpty($first->menu_content);

        // regenerate：失效 cache + 重新生成（限流 +1）
        $regenerated = $this->service->regenerate($this->user);
        $this->assertNotEmpty($regenerated->menu_content);
        $this->assertEquals($this->user->id, $regenerated->user_id);

        // 限流计数：regenerate 调用了 1 次，increment 1 次
        $regenKey = sprintf('ai_menu:regen:%d:%s', $this->user->id, now()->toDateString());
        $this->assertGreaterThanOrEqual(1, (int) Cache::get($regenKey), 'regen 计数器应自增');
    }

    /**
     * 场景 11：纯文本接口（SurveyController demo）降级
     * 验证 generateDailyMenu(array, array) 也走同一降级路径
     */
    public function test_text_only_interface_also_falls_back(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('Error', 500),
        ]);

        $content = $this->service->generateDailyMenu(
            preferences: [
                'purpose' => 'Eat Healthier',
                'dietary_habits' => 'Keto/Low Carb',
                'goals' => 'Lose weight',
                'cooking_skill' => 'Beginner',
                'budget_hkd' => 500,
            ],
            availableProducts: ['Tomato', 'Spinach'],
        );

        $this->assertNotEmpty($content);
        $this->assertStringContainsString('[AI Demo]', $content);
        $this->assertStringContainsString('Keto', $content);
    }
}
