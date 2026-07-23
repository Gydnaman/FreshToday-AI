<?php

namespace Tests\Feature\Web;

use App\Enums\GuardCode;
use App\Exceptions\GuardFailedException;
use App\Models\DailyMenu;
use App\Models\Product;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\AiMenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_signup_but_not_daily_menu(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertViewHas('menuDays', fn ($days): bool => $days instanceof Collection && $days->isEmpty())
            ->assertViewHas('menuState', 'guest')
            ->assertViewHas('menuError', null)
            ->assertSee('data-testid="guest-signup-section"', false)
            ->assertDontSee('data-testid="daily-menu-section"', false);
    }

    public function test_authenticated_user_hides_signup_and_sees_saved_today_menu(): void
    {
        $user = User::factory()->create();
        UserPreference::factory()->for($user)->create();
        DailyMenu::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'menu_content' => 'Saved today menu',
        ]);

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertViewHas('menuState', 'ready')
            ->assertViewHas('menuError', null)
            ->assertDontSee('data-testid="guest-signup-section"', false)
            ->assertSee('data-testid="daily-menu-section"', false)
            ->assertSee('Saved today menu');
    }

    public function test_authenticated_first_home_visit_generates_and_persists_today_menu(): void
    {
        $user = User::factory()->create();
        UserPreference::factory()->for($user)->create();
        Product::factory()->count(3)->create([
            'status' => Product::STATUS_PUBLISHED,
            'stock' => 10,
        ]);

        $this->actingAs($user)->get('/')->assertOk();

        $this->assertDatabaseHas('daily_menus', [
            'user_id' => $user->id,
            'date' => now()->toDateString().' 00:00:00',
        ]);
    }

    public function test_home_lists_only_current_users_last_seven_days(): void
    {
        $user = User::factory()->create();
        UserPreference::factory()->for($user)->create();
        $other = User::factory()->create();

        DailyMenu::create(['user_id' => $user->id, 'date' => now()->toDateString(), 'menu_content' => 'Today owner menu']);
        DailyMenu::create(['user_id' => $user->id, 'date' => now()->subDays(6)->toDateString(), 'menu_content' => 'Six days owner menu']);
        DailyMenu::create(['user_id' => $user->id, 'date' => now()->subDays(7)->toDateString(), 'menu_content' => 'Seven days old menu']);
        DailyMenu::create(['user_id' => $user->id, 'date' => now()->addDay()->toDateString(), 'menu_content' => 'Future owner menu']);
        DailyMenu::create(['user_id' => $other->id, 'date' => now()->subDay()->toDateString(), 'menu_content' => 'Other user menu']);

        $expectedDates = collect(range(0, 6))
            ->map(fn (int $daysAgo): string => now()->subDays($daysAgo)->toDateString())
            ->all();

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertViewHas('menuDays', function ($days) use ($expectedDates): bool {
                return $days instanceof Collection
                    && $days->count() === 7
                    && $days->pluck('date')->all() === $expectedDates;
            })
            ->assertSee('Today owner menu')
            ->assertSee('Six days owner menu')
            ->assertDontSee('Seven days old menu')
            ->assertDontSee('Future owner menu')
            ->assertDontSee('Other user menu');
    }

    public function test_user_without_preferences_sees_survey_state_without_menu_generation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertViewHas('menuState', 'needs_preferences')
            ->assertViewHas('menuError', null)
            ->assertSee('data-testid="menu-needs-preferences"', false);

        $this->assertDatabaseCount('daily_menus', 0);
    }

    public function test_user_with_preferences_but_no_products_sees_no_products_state(): void
    {
        $user = User::factory()->create();
        UserPreference::factory()->for($user)->create();

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertViewHas('menuState', 'no_products')
            ->assertViewHas('menuError', null)
            ->assertSee('data-testid="menu-no-products"', false)
            ->assertSee(i18n('homeMenu.noProducts'));

        $this->assertDatabaseCount('daily_menus', 0);
    }

    #[DataProvider('localizedGuardFailureProvider')]
    public function test_guard_failure_is_mapped_to_localized_home_copy(
        string $requestLocale,
        string $translationLocale,
        GuardCode $guardCode,
        array $context,
        string $expectedState,
        string $translationKey,
        bool $expectsMenuError,
    ): void
    {
        $user = User::factory()->create();
        UserPreference::factory()->for($user)->create();
        $serviceMessage = '服务层简体中文错误，不可直接展示';

        $this->mock(AiMenuService::class, function (MockInterface $mock) use ($user, $guardCode, $serviceMessage, $context): void {
            $mock->shouldReceive('generateDailyMenuForUser')
                ->once()
                ->withArgs(fn (User $requestedUser): bool => $requestedUser->is($user))
                ->andThrow(new GuardFailedException($guardCode, $serviceMessage, $context));
        });

        $expectedMessage = i18n($translationKey, locale: $translationLocale);
        $response = $this->actingAs($user)->get('/?lang='.$requestLocale)
            ->assertOk()
            ->assertViewHas('menuState', $expectedState)
            ->assertViewHas('menuError', $expectsMenuError ? $expectedMessage : null)
            ->assertSee($expectedMessage);

        $this->assertStringNotContainsString($serviceMessage, $response->getContent());
    }

    public static function localizedGuardFailureProvider(): array
    {
        return [
            'English generation failure' => ['en', 'en', GuardCode::Ai, [], 'generation_failed', 'homeMenu.generationFailed', true],
            'Simplified Chinese generation failure' => ['zh', 'zh', GuardCode::Ai, [], 'generation_failed', 'homeMenu.generationFailed', true],
            'Traditional Chinese generation failure' => ['zh-TW', 'zhhk', GuardCode::Ai, [], 'generation_failed', 'homeMenu.generationFailed', true],
            'English no products' => ['en', 'en', GuardCode::Ai, ['reason' => 'NO_AVAILABLE_PRODUCTS'], 'no_products', 'homeMenu.noProducts', false],
            'Traditional Chinese no products' => ['zh-TW', 'zhhk', GuardCode::Ai, ['reason' => 'NO_AVAILABLE_PRODUCTS'], 'no_products', 'homeMenu.noProducts', false],
            'English rate limit' => ['en', 'en', GuardCode::AiRate, [], 'generation_failed', 'homeMenu.rateLimited', true],
            'Traditional Chinese rate limit' => ['zh-TW', 'zhhk', GuardCode::AiRate, [], 'generation_failed', 'homeMenu.rateLimited', true],
        ];
    }

    public function test_unexpected_generation_failure_returns_generic_error_without_leaking_details(): void
    {
        $user = User::factory()->create();
        UserPreference::factory()->for($user)->create();
        $sensitiveDetail = 'https://provider.example/v1/menu?key=PRIVATE_PROVIDER_KEY';

        $this->mock(AiMenuService::class, function (MockInterface $mock) use ($sensitiveDetail): void {
            $mock->shouldReceive('generateDailyMenuForUser')
                ->once()
                ->andThrow(new RuntimeException($sensitiveDetail));
        });

        $response = $this->actingAs($user)->get('/');

        $response
            ->assertOk()
            ->assertViewHas('menuState', 'generation_failed')
            ->assertViewHas('menuError', i18n('homeMenu.generationFailed'))
            ->assertSee('data-testid="menu-generation-failed"', false)
            ->assertSee(i18n('homeMenu.generationFailed'));
        $this->assertStringNotContainsString($sensitiveDetail, $response->getContent());
        $this->assertStringNotContainsString('PRIVATE_PROVIDER_KEY', $response->getContent());
        $this->assertSame(1, substr_count($response->getContent(), e(i18n('homeMenu.generationFailed'))));
    }

    public function test_home_menu_copy_exists_in_all_supported_locales(): void
    {
        $user = User::factory()->create();
        $keys = [
            'homeMenu.title',
            'homeMenu.subtitle',
            'homeMenu.today',
            'homeMenu.previousDays',
            'homeMenu.noMenu',
            'homeMenu.needsPreferences',
            'homeMenu.completePreferences',
            'homeMenu.noProducts',
            'homeMenu.generationFailed',
            'homeMenu.rateLimited',
            'homeMenu.regenerate',
            'homeMenu.regenerating',
            'homeMenu.regenerateFailed',
            'homeMenu.updated',
            'homeMenu.source',
        ];

        foreach (['en', 'zh', 'zhhk'] as $locale) {
            $this->actingAs($user)->get('/?lang='.$locale)
                ->assertOk()
                ->assertDontSee('homeMenu.', false);

            foreach ($keys as $key) {
                $translation = i18n($key, locale: $locale);

                $this->assertNotSame($key, $translation, "Missing {$locale} translation for {$key}");
                $this->assertStringNotContainsString('homeMenu.', $translation);
            }
        }
    }

    public function test_daily_menu_copy_is_available_in_all_supported_locales(): void
    {
        $user = User::factory()->create();
        UserPreference::factory()->for($user)->create();
        DailyMenu::create(['user_id' => $user->id, 'date' => now()->toDateString(), 'menu_content' => 'Menu']);

        $this->actingAs($user)->get('/?lang=zh')->assertSee('今日菜单');
        $this->actingAs($user)->get('/?lang=zhhk')->assertSee('今日餐單');
        $this->actingAs($user)->get('/?lang=en')->assertSee("Today's menu");
    }

    public function test_saved_today_menu_has_regenerate_contract(): void
    {
        $user = User::factory()->create();
        UserPreference::factory()->for($user)->create();
        DailyMenu::create(['user_id' => $user->id, 'date' => now()->toDateString(), 'menu_content' => 'Menu']);

        $this->actingAs($user)->get('/')
            ->assertSee('data-testid="regenerate-menu-button"', false)
            ->assertSee("gbFetch('/api/menu/regenerate'", false);
    }

    public function test_login_defaults_to_home_and_preserves_explicit_return_code(): void
    {
        $response = $this->get('/login')
            ->assertOk()
            ->assertSee('function normalizeReturnDestination(rawReturn, currentOrigin)', false)
            ->assertSee("normalizeReturnDestination(params.get('return'))", false);

        $this->assertSame(2, substr_count($response->getContent(), 'location.href = returnTo;'));
    }

    public function test_daily_menu_tabs_support_roving_keyboard_focus(): void
    {
        $user = User::factory()->create();
        UserPreference::factory()->for($user)->create();
        DailyMenu::create(['user_id' => $user->id, 'date' => now()->toDateString(), 'menu_content' => 'Menu']);

        $this->actingAs($user)->get('/')
            ->assertSee("case 'ArrowLeft':", false)
            ->assertSee("case 'ArrowRight':", false)
            ->assertSee("case 'Home':", false)
            ->assertSee("case 'End':", false)
            ->assertSee('activateMenuTab($tabs.eq(targetIndex), true);', false);
    }

    public function test_malformed_historical_menu_json_falls_back_to_escaped_plain_text(): void
    {
        $user = User::factory()->create();
        UserPreference::factory()->for($user)->create();
        DailyMenu::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'menu_content' => 'Valid today menu',
        ]);
        $unsafeFallback = '<script>alert("history-secret")</script> Safe historic fallback';
        DailyMenu::create([
            'user_id' => $user->id,
            'date' => now()->subDay()->toDateString(),
            'menu_content' => $unsafeFallback,
            'menu_json' => [
                'greeting' => ['invalid'],
                'meals' => [],
                'tip' => 'Invalid persisted structure',
            ],
        ]);

        $response = $this->actingAs($user)->get('/');

        $response
            ->assertOk()
            ->assertSee('Valid today menu')
            ->assertSee(e($unsafeFallback), false)
            ->assertDontSee($unsafeFallback, false);
    }

    public function test_structured_menu_links_only_published_in_stock_products(): void
    {
        $user = User::factory()->create();
        UserPreference::factory()->for($user)->create();
        $available = Product::factory()->create([
            'name' => 'Available Tomato',
            'status' => Product::STATUS_PUBLISHED,
            'stock' => 10,
        ]);
        $draft = Product::factory()->create([
            'name' => 'Draft Carrot',
            'status' => Product::STATUS_DRAFT,
            'stock' => 10,
        ]);
        $outOfStock = Product::factory()->create([
            'name' => 'Empty Kale',
            'status' => Product::STATUS_PUBLISHED,
            'stock' => 0,
        ]);

        DailyMenu::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'menu_content' => 'Structured menu',
            'menu_json' => [
                'greeting' => 'Fresh choices',
                'meals' => [
                    ['type' => 'breakfast', 'name' => 'Tomato plate', 'ingredients' => ['Available Tomato'], 'description' => 'Fresh tomato'],
                    ['type' => 'lunch', 'name' => 'Carrot plate', 'ingredients' => ['Draft Carrot'], 'description' => 'Fresh carrot'],
                    ['type' => 'dinner', 'name' => 'Kale plate', 'ingredients' => ['Empty Kale'], 'description' => 'Fresh kale'],
                ],
                'tip' => 'Keep it fresh',
            ],
        ]);

        $response = $this->actingAs($user)->get('/')->assertOk();

        $response->assertSee('/products/'.$available->id, false);
        $this->assertStringNotContainsString('/products/'.$draft->id, $response->getContent());
        $this->assertStringNotContainsString('/products/'.$outOfStock->id, $response->getContent());
    }
}
