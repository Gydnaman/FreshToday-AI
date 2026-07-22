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
            ->assertViewHas('menuError', fn (?string $error): bool => filled($error))
            ->assertSee('data-testid="menu-no-products"', false);

        $this->assertDatabaseCount('daily_menus', 0);
    }

    public function test_generation_failure_still_returns_safe_home_state(): void
    {
        $user = User::factory()->create();
        UserPreference::factory()->for($user)->create();
        $unsafeMessage = '<script>alert("menu")</script>';

        $this->mock(AiMenuService::class, function (MockInterface $mock) use ($user, $unsafeMessage): void {
            $mock->shouldReceive('generateDailyMenuForUser')
                ->once()
                ->withArgs(fn (User $requestedUser): bool => $requestedUser->is($user))
                ->andThrow(new GuardFailedException(GuardCode::Ai, $unsafeMessage));
        });

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertViewHas('menuState', 'generation_failed')
            ->assertViewHas('menuError', $unsafeMessage)
            ->assertSee('data-testid="menu-generation-failed"', false)
            ->assertSee(e($unsafeMessage), false)
            ->assertDontSee($unsafeMessage, false);
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

        $response->assertSee('/catalog#product-'.$available->id, false);
        $this->assertStringNotContainsString('/catalog#product-'.$draft->id, $response->getContent());
        $this->assertStringNotContainsString('/catalog#product-'.$outOfStock->id, $response->getContent());
    }
}
