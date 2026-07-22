<?php

namespace Tests\Unit\Services;

use App\Enums\GuardCode;
use App\Exceptions\GuardFailedException;
use App\Models\DailyMenu;
use App\Models\Product;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\AiMenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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

                return ['', 0, null];
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
}
