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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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

            public array $result = ['', 0, null];

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

                return $this->result;
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
}
