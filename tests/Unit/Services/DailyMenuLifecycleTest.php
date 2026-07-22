<?php

namespace Tests\Unit\Services;

use App\Enums\GuardCode;
use App\Exceptions\GuardFailedException;
use App\Models\DailyMenu;
use App\Models\Product;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Ai\MenuOutputValidator;
use App\Services\AiMenuService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DailyMenuLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private object $provider;

    private AiMenuService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Carbon::setTestNow('2026-07-22 09:00:00');

        $this->user = User::factory()->create();
        UserPreference::factory()->for($this->user)->create();

        $this->provider = new class implements AiProviderInterface
        {
            public array $calls = [];

            public int $generation = 0;

            public bool $returnEmpty = true;

            public ?array $result = null;

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
                $this->calls[] = compact('preferences', 'products');

                if ($this->result !== null) {
                    return $this->result;
                }

                if ($this->returnEmpty) {
                    return ['', 0, null];
                }

                $this->generation++;
                $ingredient = $products[0];
                $json = [
                    'greeting' => 'Generated menu '.$this->generation,
                    'meals' => [
                        ['type' => 'breakfast', 'name' => 'Menu '.$this->generation.' breakfast', 'ingredients' => [$ingredient], 'description' => 'Fresh breakfast with '.$ingredient],
                        ['type' => 'lunch', 'name' => 'Menu '.$this->generation.' lunch', 'ingredients' => [$ingredient], 'description' => 'Fresh lunch with '.$ingredient],
                        ['type' => 'dinner', 'name' => 'Menu '.$this->generation.' dinner', 'ingredients' => [$ingredient], 'description' => 'Fresh dinner with '.$ingredient],
                    ],
                    'tip' => 'Use fresh ingredients.',
                ];

                return [json_encode($json), 100, $json];
            }
        };

        $this->service = new AiMenuService($this->provider);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_only_published_in_stock_products_are_sent_and_limit_is_eight(): void
    {
        Product::factory()->count(10)->sequence(fn ($sequence) => [
            'name' => 'Published '.$sequence->index,
            'status' => Product::STATUS_PUBLISHED,
            'stock' => 10,
        ])->create();
        Product::factory()->create(['name' => 'Draft Secret', 'status' => Product::STATUS_DRAFT, 'stock' => 10]);
        Product::factory()->create(['name' => 'Sold Out Secret', 'status' => Product::STATUS_PUBLISHED, 'stock' => 0]);

        $this->service->generateDailyMenuForUser($this->user);

        $products = $this->provider->calls[0]['products'];
        $this->assertCount(8, $products);
        $this->assertNotContains('Draft Secret', $products);
        $this->assertNotContains('Sold Out Secret', $products);
        $this->assertSame('2026-07-22', $this->provider->calls[0]['preferences']['menu_date']);
    }

    public function test_next_day_uses_a_rotated_candidate_order_and_creates_a_new_menu(): void
    {
        Product::factory()->count(9)->sequence(fn ($sequence) => [
            'name' => 'Product '.$sequence->index,
            'status' => Product::STATUS_PUBLISHED,
            'stock' => 10,
        ])->create();

        $first = $this->service->generateDailyMenuForUser($this->user);
        $firstProducts = $this->provider->calls[0]['products'];

        Carbon::setTestNow('2026-07-23 09:00:00');
        $second = $this->service->generateDailyMenuForUser($this->user);
        $secondProducts = $this->provider->calls[1]['products'];

        $this->assertNotSame($first->id, $second->id);
        $this->assertNotSame($firstProducts, $secondProducts);
        $this->assertCount(2, DailyMenu::where('user_id', $this->user->id)->get());
    }

    public function test_candidate_rotation_uses_the_full_date_across_years(): void
    {
        Product::factory()->count(9)->sequence(fn ($sequence) => [
            'name' => 'Yearly Product '.$sequence->index,
            'status' => Product::STATUS_PUBLISHED,
            'stock' => 10,
        ])->create();

        $this->service->generateDailyMenuForUser($this->user);
        $firstProducts = $this->provider->calls[0]['products'];

        Carbon::setTestNow('2027-07-22 09:00:00');
        $this->service->generateDailyMenuForUser($this->user);
        $secondProducts = $this->provider->calls[1]['products'];

        $this->assertNotSame($firstProducts, $secondProducts);
    }

    public function test_normal_cache_hit_returns_database_truth_without_rewriting_the_menu(): void
    {
        $winnerJson = [
            'greeting' => 'Persisted greeting',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'A', 'ingredients' => ['Carrot'], 'description' => 'A'],
                ['type' => 'lunch', 'name' => 'B', 'ingredients' => ['Carrot'], 'description' => 'B'],
                ['type' => 'dinner', 'name' => 'C', 'ingredients' => ['Carrot'], 'description' => 'C'],
            ],
            'tip' => 'Persisted tip',
        ];
        $persisted = DailyMenu::create([
            'user_id' => $this->user->id,
            'date' => now()->toDateString(),
            'menu_content' => 'Persisted menu content',
            'menu_json' => $winnerJson,
            'source' => 'persisted-provider',
            'tokens_used' => 77,
        ])->fresh();
        $cacheKey = 'ai_menu:user:'.$this->user->id.':date:'.now()->toDateString();
        Cache::put($cacheKey, 'Unpersisted cache content', 86400);
        Carbon::setTestNow(now()->addMinute());

        $returned = $this->service->generateDailyMenuForUser($this->user);
        $fresh = $persisted->fresh();

        $this->assertSame($persisted->id, $returned->id);
        $this->assertSame($persisted->id, $fresh->id);
        $this->assertSame($persisted->menu_content, $fresh->menu_content);
        $this->assertSame($persisted->menu_json, $fresh->menu_json);
        $this->assertSame($persisted->source, $fresh->source);
        $this->assertSame($persisted->tokens_used, $fresh->tokens_used);
        $this->assertTrue($persisted->created_at->equalTo($fresh->created_at));
        $this->assertTrue($persisted->updated_at->equalTo($fresh->updated_at));
        $this->assertSame($persisted->menu_content, $returned->menu_content);
        $this->assertSame($persisted->menu_json, $returned->menu_json);
        $this->assertSame($persisted->id, Cache::get($cacheKey));
        $this->assertSame([], $this->provider->calls);
    }

    public function test_regenerate_calls_provider_again_and_overwrites_same_row(): void
    {
        Product::factory()->count(3)->create(['status' => Product::STATUS_PUBLISHED, 'stock' => 10]);
        $this->provider->returnEmpty = false;

        $first = $this->service->generateDailyMenuForUser($this->user);
        $firstUpdatedAt = $first->updated_at;
        Carbon::setTestNow(now()->addMinute());

        $regenerated = $this->service->regenerate($this->user);

        $this->assertSame($first->id, $regenerated->id);
        $this->assertNotSame($first->menu_content, $regenerated->menu_content);
        $this->assertTrue($regenerated->updated_at->gt($firstUpdatedAt));
        $this->assertCount(2, $this->provider->calls);
        $this->assertSame(1, DailyMenu::where('user_id', $this->user->id)->count());
    }

    public function test_non_unique_insert_query_exception_is_rethrown_even_when_a_same_date_row_exists(): void
    {
        Product::factory()->count(3)->create(['status' => Product::STATUS_PUBLISHED, 'stock' => 10]);
        $this->provider->returnEmpty = false;
        $exception = self::queryException(
            ['HY000', 5, 'database is locked'],
            'SQLSTATE[HY000]: General error: 5 database is locked',
        );
        $eventName = 'eloquent.creating: '.DailyMenu::class;

        Event::listen($eventName, function (DailyMenu $menu) use ($exception): void {
            DB::table('daily_menus')->insert([
                'user_id' => $menu->user_id,
                'date' => $menu->date->toDateString(),
                'menu_content' => 'Concurrent winner',
                'source' => 'fake',
                'tokens_used' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw $exception;
        });

        try {
            try {
                $this->service->generateDailyMenuForUser($this->user, force: true);
                $this->fail('Expected the non-unique query exception to be rethrown.');
            } catch (QueryException $caught) {
                $this->assertSame($exception, $caught);
            }
        } finally {
            Event::forget($eventName);
        }

        $this->assertSame(
            'Concurrent winner',
            DailyMenu::where('user_id', $this->user->id)->firstOrFail()->menu_content,
        );
    }

    public function test_failed_insert_does_not_cache_unpersisted_menu_content(): void
    {
        Product::factory()->count(3)->create(['status' => Product::STATUS_PUBLISHED, 'stock' => 10]);
        $this->provider->returnEmpty = false;
        $exception = self::queryException(
            ['HY000', 5, 'database is locked'],
            'SQLSTATE[HY000]: General error: 5 database is locked',
        );
        $eventName = 'eloquent.creating: '.DailyMenu::class;
        Event::listen($eventName, fn (): never => throw $exception);

        try {
            try {
                $this->service->generateDailyMenuForUser($this->user, force: true);
                $this->fail('Expected the failed insert to throw.');
            } catch (QueryException $caught) {
                $this->assertSame($exception, $caught);
            }
        } finally {
            Event::forget($eventName);
        }

        $cacheKey = 'ai_menu:user:'.$this->user->id.':date:'.now()->toDateString();
        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_update_query_exception_is_rethrown_without_race_recovery_or_cache_replacement(): void
    {
        Product::factory()->count(3)->create(['status' => Product::STATUS_PUBLISHED, 'stock' => 10]);
        $this->provider->returnEmpty = false;
        $original = $this->service->generateDailyMenuForUser($this->user);
        $cacheKey = 'ai_menu:user:'.$this->user->id.':date:'.now()->toDateString();
        $exception = self::queryException(
            ['23000', 19, 'UNIQUE constraint failed: daily_menus.user_id, daily_menus.date'],
            'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: daily_menus.user_id, daily_menus.date',
            'update daily_menus set menu_content = ?',
        );
        $attempts = 0;
        $eventName = 'eloquent.updating: '.DailyMenu::class;
        Event::listen($eventName, function () use (&$attempts, $exception): never {
            $attempts++;

            throw $exception;
        });

        try {
            try {
                $this->service->generateDailyMenuForUser($this->user, force: true);
                $this->fail('Expected the failed update to throw.');
            } catch (QueryException $caught) {
                $this->assertSame($exception, $caught);
            }
        } finally {
            Event::forget($eventName);
        }

        $this->assertSame(1, $attempts);
        $this->assertSame($original->id, Cache::get($cacheKey));
    }

    #[DataProvider('dailyMenuUniqueConstraintProvider')]
    public function test_supported_unique_insert_race_returns_the_winner_without_overwriting_it(
        array $errorInfo,
        string $message,
    ): void
    {
        Product::factory()->count(3)->create(['status' => Product::STATUS_PUBLISHED, 'stock' => 10]);
        $this->provider->returnEmpty = false;
        $exception = self::queryException($errorInfo, $message);
        $winnerId = null;
        $winnerTimestamp = now()->subMinute();
        $winnerJson = [
            'greeting' => 'Concurrent winner greeting',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'Winner A', 'ingredients' => ['Carrot'], 'description' => 'A'],
                ['type' => 'lunch', 'name' => 'Winner B', 'ingredients' => ['Carrot'], 'description' => 'B'],
                ['type' => 'dinner', 'name' => 'Winner C', 'ingredients' => ['Carrot'], 'description' => 'C'],
            ],
            'tip' => 'Concurrent winner tip',
        ];
        $eventName = 'eloquent.creating: '.DailyMenu::class;

        Event::listen($eventName, function (DailyMenu $menu) use ($exception, &$winnerId, $winnerTimestamp, $winnerJson): void {
            $winnerId = DB::table('daily_menus')->insertGetId([
                'user_id' => $menu->user_id,
                'date' => $menu->date->toDateString(),
                'menu_content' => 'Concurrent winner',
                'menu_json' => json_encode($winnerJson, JSON_THROW_ON_ERROR),
                'source' => 'concurrent-provider',
                'tokens_used' => 41,
                'created_at' => $winnerTimestamp,
                'updated_at' => $winnerTimestamp,
            ]);

            throw $exception;
        });

        try {
            $returned = $this->service->generateDailyMenuForUser($this->user);
        } finally {
            Event::forget($eventName);
        }

        $cacheKey = 'ai_menu:user:'.$this->user->id.':date:'.now()->toDateString();
        $winner = DailyMenu::findOrFail($winnerId);
        $this->assertSame($winnerId, $returned->id);
        $this->assertSame('Concurrent winner', $returned->menu_content);
        $this->assertSame($winnerJson, $returned->menu_json);
        $this->assertSame('concurrent-provider', $returned->source);
        $this->assertSame(41, $returned->tokens_used);
        $this->assertTrue($winnerTimestamp->equalTo($winner->created_at));
        $this->assertTrue($winnerTimestamp->equalTo($winner->updated_at));
        $this->assertSame($winnerId, Cache::get($cacheKey));
        $this->assertSame(1, DailyMenu::where('user_id', $this->user->id)->count());
    }

    public function test_fallback_is_structured_and_only_uses_candidate_products(): void
    {
        Product::factory()->count(3)->sequence(
            ['name' => 'Choy Sum'],
            ['name' => 'Chinese Cabbage'],
            ['name' => 'Carrot'],
        )->create(['status' => Product::STATUS_PUBLISHED, 'stock' => 10]);

        $menu = $this->service->generateDailyMenuForUser($this->user);

        $this->assertIsArray($menu->menu_json);
        $this->assertCount(3, $menu->menu_json['meals']);
        $ingredients = collect($menu->menu_json['meals'])->pluck('ingredients')->flatten()->all();
        $this->assertEmpty(array_diff($ingredients, ['Choy Sum', 'Chinese Cabbage', 'Carrot']));
    }

    public function test_generation_is_rejected_when_no_published_in_stock_products_exist(): void
    {
        Product::factory()->create(['status' => Product::STATUS_DRAFT, 'stock' => 10]);
        Product::factory()->create(['status' => Product::STATUS_PUBLISHED, 'stock' => 0]);

        try {
            $this->service->generateDailyMenuForUser($this->user);
            $this->fail('Expected generation to be rejected without available products.');
        } catch (GuardFailedException $exception) {
            $this->assertSame(GuardCode::Ai, $exception->guardCode);
            $this->assertSame('NO_AVAILABLE_PRODUCTS', $exception->context['reason']);
            $this->assertSame([], $this->provider->calls);
        }
    }

    public function test_non_scalar_provider_ingredient_is_rejected_and_structured_fallback_is_persisted(): void
    {
        $products = ['Choy Sum', 'Chinese Cabbage', 'Carrot'];
        foreach ($products as $product) {
            Product::factory()->create([
                'name' => $product,
                'status' => Product::STATUS_PUBLISHED,
                'stock' => 10,
            ]);
        }

        $providerMenu = [
            'greeting' => 'Hello',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'Broken', 'ingredients' => [['name' => 'Choy Sum']], 'description' => 'A'],
                ['type' => 'lunch', 'name' => 'Lunch', 'ingredients' => ['Chinese Cabbage'], 'description' => 'B'],
                ['type' => 'dinner', 'name' => 'Dinner', 'ingredients' => ['Carrot'], 'description' => 'C'],
            ],
            'tip' => 'Tip',
        ];
        $this->provider->result = ['provider text', 100, $providerMenu];

        $menu = $this->service->generateDailyMenuForUser($this->user);

        $this->assertNotSame($providerMenu, $menu->menu_json);
        $this->assertSame(0, $menu->tokens_used);
        $ingredients = collect($menu->menu_json['meals'])->pluck('ingredients')->flatten()->all();
        $this->assertContainsOnly('string', $ingredients);
        $this->assertEmpty(array_diff($ingredients, $products));
    }

    public function test_provider_ingredients_are_persisted_only_when_they_exactly_match_candidates(): void
    {
        $products = ['Local Organic Choy Sum', 'Chinese Cabbage', 'Carrot'];
        foreach ($products as $product) {
            Product::factory()->create([
                'name' => $product,
                'status' => Product::STATUS_PUBLISHED,
                'stock' => 10,
            ]);
        }

        $providerMenu = [
            'greeting' => 'Hello',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'Abbreviated', 'ingredients' => ['Choy Sum'], 'description' => 'A'],
                ['type' => 'lunch', 'name' => 'Empty', 'ingredients' => [''], 'description' => 'B'],
                ['type' => 'dinner', 'name' => 'Exact', 'ingredients' => ['Carrot'], 'description' => 'C'],
            ],
            'tip' => 'Tip',
        ];
        $this->provider->result = ['provider text', 100, $providerMenu];

        $menu = $this->service->generateDailyMenuForUser($this->user);

        $this->assertNotSame($providerMenu, $menu->menu_json);
        $ingredients = collect($menu->menu_json['meals'])->pluck('ingredients')->flatten()->all();
        $this->assertEmpty(array_diff($ingredients, $products));
        $this->assertNotContains('Choy Sum', $ingredients);
        $this->assertNotContains('', $ingredients);
    }

    public function test_validator_exception_is_treated_as_invalid_provider_output_and_falls_back(): void
    {
        Product::factory()->create([
            'name' => 'Carrot',
            'status' => Product::STATUS_PUBLISHED,
            'stock' => 10,
        ]);
        $providerMenu = [
            'greeting' => 'Hello',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'A', 'ingredients' => ['Carrot'], 'description' => 'A'],
                ['type' => 'lunch', 'name' => 'B', 'ingredients' => ['Carrot'], 'description' => 'B'],
                ['type' => 'dinner', 'name' => 'C', 'ingredients' => ['Carrot'], 'description' => 'C'],
            ],
            'tip' => 'Tip',
        ];
        $this->provider->result = ['provider text', 100, $providerMenu];
        $validator = new class extends MenuOutputValidator
        {
            public function validateJson(array $data, array $availableProducts): bool
            {
                throw new \TypeError('Malformed provider payload');
            }
        };

        $menu = (new AiMenuService($this->provider, $validator))
            ->generateDailyMenuForUser($this->user);

        $this->assertNotSame($providerMenu, $menu->menu_json);
        $this->assertSame(0, $menu->tokens_used);
        $this->assertSame(['Carrot'], $menu->menu_json['meals'][0]['ingredients']);
    }

    #[DataProvider('invalidRenderableFieldProvider')]
    public function test_invalid_renderable_provider_fields_use_structured_fallback(
        string $field,
        mixed $invalidValue,
    ): void
    {
        Product::factory()->create([
            'name' => 'Carrot',
            'status' => Product::STATUS_PUBLISHED,
            'stock' => 10,
        ]);
        $providerMenu = [
            'greeting' => 'Hello',
            'meals' => [
                ['type' => 'breakfast', 'name' => 'A', 'ingredients' => ['Carrot'], 'description' => 'A'],
                ['type' => 'lunch', 'name' => 'B', 'ingredients' => ['Carrot'], 'description' => 'B'],
                ['type' => 'dinner', 'name' => 'C', 'ingredients' => ['Carrot'], 'description' => 'C'],
            ],
            'tip' => 'Tip',
        ];
        match ($field) {
            'greeting', 'tip' => $providerMenu[$field] = $invalidValue,
            'name', 'description' => $providerMenu['meals'][0][$field] = $invalidValue,
        };
        $this->provider->result = ['provider text', 100, $providerMenu];

        $menu = $this->service->generateDailyMenuForUser($this->user);

        $this->assertNotSame($providerMenu, $menu->menu_json);
        $this->assertSame(0, $menu->tokens_used);
        $this->assertIsString($menu->menu_json['greeting']);
        $this->assertNotSame('', trim($menu->menu_json['greeting']));
        $this->assertIsString($menu->menu_json['tip']);
        $this->assertNotSame('', trim($menu->menu_json['tip']));
        foreach ($menu->menu_json['meals'] as $meal) {
            $this->assertIsString($meal['name']);
            $this->assertNotSame('', trim($meal['name']));
            $this->assertIsString($meal['description']);
            $this->assertNotSame('', trim($meal['description']));
        }
    }

    public static function invalidRenderableFieldProvider(): array
    {
        return [
            'greeting array' => ['greeting', ['invalid']],
            'tip array' => ['tip', ['invalid']],
            'meal name array' => ['name', ['invalid']],
            'meal description array' => ['description', ['invalid']],
            'greeting empty' => ['greeting', ''],
            'tip empty' => ['tip', ''],
            'meal name empty' => ['name', ''],
            'meal description empty' => ['description', ''],
        ];
    }

    public static function dailyMenuUniqueConstraintProvider(): array
    {
        return [
            'SQLite' => [
                ['23000', 19, 'UNIQUE constraint failed: daily_menus.user_id, daily_menus.date'],
                'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: daily_menus.user_id, daily_menus.date',
            ],
            'MySQL' => [
                ['23000', 1062, "Duplicate entry '1-2026-07-22' for key 'daily_menus_user_date_unique'"],
                "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-2026-07-22' for key 'daily_menus_user_date_unique'",
            ],
        ];
    }

    private static function queryException(
        array $errorInfo,
        string $message,
        string $sql = 'insert into daily_menus (...) values (...)',
    ): QueryException
    {
        $previous = new PDOException($message, (int) $errorInfo[1]);
        $previous->errorInfo = $errorInfo;

        return new QueryException('testing', $sql, [], $previous);
    }
}
