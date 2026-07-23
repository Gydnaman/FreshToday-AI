<?php

namespace Tests\Feature\Api;

use App\Enums\GuardCode;
use App\Exceptions\GuardFailedException;
use App\Models\DailyMenu;
use App\Models\Product;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\AiMenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MenuRegenerateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private object $provider;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->user = User::factory()->create();
        UserPreference::factory()->for($this->user)->create();
        Product::factory()->count(3)->create(['status' => Product::STATUS_PUBLISHED, 'stock' => 10]);

        $this->provider = new class implements AiProviderInterface
        {
            public int $generation = 0;

            public function name(): string
            {
                return 'fake';
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function generate(array $preferences, array $products): array
            {
                $this->generation++;
                $ingredient = $products[0];
                $json = [
                    'greeting' => 'Version '.$this->generation,
                    'meals' => [
                        ['type' => 'breakfast', 'name' => 'Breakfast '.$this->generation, 'ingredients' => [$ingredient], 'description' => 'Breakfast with '.$ingredient],
                        ['type' => 'lunch', 'name' => 'Lunch '.$this->generation, 'ingredients' => [$ingredient], 'description' => 'Lunch with '.$ingredient],
                        ['type' => 'dinner', 'name' => 'Dinner '.$this->generation, 'ingredients' => [$ingredient], 'description' => 'Dinner with '.$ingredient],
                    ],
                    'tip' => 'Fresh tip '.$this->generation,
                ];

                return [json_encode($json), 100, $json];
            }
        };

        $this->app->instance(AiProviderInterface::class, $this->provider);
        $this->app->forgetInstance(AiMenuService::class);
    }

    public function test_authenticated_user_can_replace_today_menu_without_creating_a_second_row(): void
    {
        $service = app(AiMenuService::class);
        $original = $service->generateDailyMenuForUser($this->user);

        $response = $this->actingAs($this->user)->postJson('/api/menu/regenerate');

        $response->assertOk()->assertJsonPath('data.date', now()->toDateString());
        $replacement = DailyMenu::where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame($original->id, $replacement->id);
        $this->assertNotSame($original->menu_content, $replacement->menu_content);
        $this->assertSame(1, DailyMenu::where('user_id', $this->user->id)->count());
    }

    public function test_fourth_regeneration_is_rejected_and_keeps_latest_menu(): void
    {
        $this->actingAs($this->user);
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postJson('/api/menu/regenerate')->assertOk();
        }
        $latest = DailyMenu::where('user_id', $this->user->id)->firstOrFail()->menu_content;
        $menuCacheKey = 'ai_menu:user:'.$this->user->id.':date:'.now()->toDateString();
        $latestMenu = DailyMenu::where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame($latestMenu->id, Cache::get($menuCacheKey));

        $this->postJson('/api/menu/regenerate')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'GUARD-AI-RATE');

        $this->assertSame(3, $this->provider->generation);
        $this->assertSame($latestMenu->id, Cache::get($menuCacheKey));
        $this->assertSame($latest, DailyMenu::where('user_id', $this->user->id)->firstOrFail()->menu_content);
    }

    public function test_regeneration_counter_initializes_ttl_then_increments_without_stale_put(): void
    {
        $regenKey = 'ai_menu:regen:'.$this->user->id.':'.now()->toDateString();
        $operations = [];
        Cache::shouldReceive('add')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $key, int $value, int $ttl) use (&$operations, $regenKey): bool {
                $this->assertSame([$regenKey, 0, 86400], [$key, $value, $ttl]);
                $operations[] = 'add';

                return false;
            });
        Cache::shouldReceive('increment')
            ->once()
            ->with($regenKey)
            ->andReturnUsing(function () use (&$operations): int {
                $operations[] = 'increment';

                return 4;
            });
        Cache::shouldReceive('put')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $key, mixed $value, int $ttl) use (&$operations, $regenKey): bool {
                $this->assertSame([$regenKey, 4, 86400], [$key, $value, $ttl]);
                $operations[] = 'put';

                return true;
            });

        try {
            app(AiMenuService::class)->regenerate($this->user);
            $this->fail('Expected the fourth regeneration to be rate limited.');
        } catch (GuardFailedException $exception) {
            $this->assertSame(GuardCode::AiRate, $exception->guardCode);
        }

        $this->assertSame(['add', 'increment'], $operations);
    }

    public function test_today_menu_links_only_published_in_stock_products(): void
    {
        $available = Product::factory()->create([
            'name' => 'API Available Tomato',
            'status' => Product::STATUS_PUBLISHED,
            'stock' => 10,
        ]);
        $draft = Product::factory()->create([
            'name' => 'API Draft Carrot',
            'status' => Product::STATUS_DRAFT,
            'stock' => 10,
        ]);
        $outOfStock = Product::factory()->create([
            'name' => 'API Empty Kale',
            'status' => Product::STATUS_PUBLISHED,
            'stock' => 0,
        ]);
        DailyMenu::create([
            'user_id' => $this->user->id,
            'date' => now()->toDateString(),
            'menu_content' => 'Structured API menu',
            'menu_json' => [
                'greeting' => 'API choices',
                'meals' => [
                    ['type' => 'breakfast', 'name' => 'Tomato', 'ingredients' => [$available->name], 'description' => 'Tomato'],
                    ['type' => 'lunch', 'name' => 'Carrot', 'ingredients' => [$draft->name], 'description' => 'Carrot'],
                    ['type' => 'dinner', 'name' => 'Kale', 'ingredients' => [$outOfStock->name], 'description' => 'Kale'],
                ],
                'tip' => 'API tip',
            ],
        ]);

        $html = $this->actingAs($this->user)
            ->getJson('/api/menu/today')
            ->assertOk()
            ->json('data.content_html');

        $this->assertIsString($html);
        $this->assertStringContainsString('/catalog#product-'.$available->id, $html);
        $this->assertStringNotContainsString('/catalog#product-'.$draft->id, $html);
        $this->assertStringNotContainsString('/catalog#product-'.$outOfStock->id, $html);
    }
}
