# Home Daily Menu Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 登录用户每天第一次进入首页时生成并持久化一份菜单，当天保持不变，仅在主动重新生成时覆盖，并可查看最近 7 天历史。

**Architecture:** 用新的 `Web\HomeController` 取代首页闭包，访客继续渲染营销首页，登录用户通过 `AiMenuService` 执行“当天存在则读取、缺失则生成”。`AiMenuService` 负责按用户与日期轮换最多 8 个已上架有库存商品、结构化 fallback 和强制重新生成；数据库联合唯一索引保证一用户一天最多一条记录。

**Tech Stack:** PHP 8.2、Laravel 12、Eloquent、Blade、Tailwind CSS 4、vanilla JavaScript/jQuery、Sanctum、PHPUnit 11、DeepSeek OpenAI-compatible API、Vite 7。

## Global Constraints

- 本项目是毕业设计；优先完成可从 UI 演示并由持久化数据验证的垂直切片。
- 同一用户同一天普通访问不得重复调用 AI；仅主动重新生成允许覆盖当天菜单。
- 只允许使用 `status = published` 且 `stock > 0` 的仓库商品，不得编造商品。
- 访客创建账户区与登录菜单区必须互斥。
- 最近 7 天指今天及过去 6 天，不生成未来菜单，不回填缺失历史。
- 保留 `?return=` 登录回跳；仅无显式回跳时默认 `/`。
- DeepSeek 密钥只存在本地 `.env`，不得进入 Git、日志、测试输出或文档。
- 所有新用户文案必须同时提供简体中文、繁体中文和英文。
- 保留现有鉴权、每日重新生成 3 次限制、输出校验和 XSS 转义。

---

## File Map

- Create `database/migrations/2026_07_22_120000_add_unique_user_date_to_daily_menus.php`: 清理重复数据并增加每日菜单联合唯一索引。
- Create `tests/Feature/Database/DailyMenuUniquenessTest.php`: 验证数据库唯一性。
- Create `tests/Unit/Services/DailyMenuLifecycleTest.php`: 覆盖同日复用、跨日轮换、商品过滤、fallback 和强制重新生成。
- Modify `app/Services/AiMenuService.php`: 增加候选商品轮换、结构化 fallback 和强制生成路径。
- Modify `app/Services/Ai/PromptBuilder.php`: 将菜单日期加入短 prompt。
- Modify `config/ai.php`: 支持 DeepSeek 最大输出 Token 配置。
- Modify `app/Services/Ai/Providers/DeepseekProvider.php`: 使用配置化最大输出 Token。
- Create `app/Http/Controllers/Web/HomeController.php`: 提供访客/登录首页数据和七日菜单视图模型。
- Create `tests/Feature/Web/HomePageTest.php`: 覆盖首页登录态、首次生成、隔离、历史和三语。
- Modify `routes/web.php`: 将 `/` 指向 `HomeController@index`。
- Modify `resources/views/pages/welcome.blade.php`: 互斥渲染注册区和每日菜单区，加入日期切换和重新生成。
- Modify `resources/views/auth/login.blade.php`: 默认登录回跳改为 `/`。
- Modify `resources/lang/en.json`, `zh.json`, `zhhk.json`: 新增 `homeMenu.*` 文案。
- Create `tests/Feature/Api/MenuRegenerateTest.php`: 验证主动重新生成覆盖和限流错误。

---

### Task 1: Enforce One Menu Per User Per Day

**Files:**
- Create: `tests/Feature/Database/DailyMenuUniquenessTest.php`
- Create: `database/migrations/2026_07_22_120000_add_unique_user_date_to_daily_menus.php`

**Interfaces:**
- Consumes: `daily_menus.user_id`, `daily_menus.date`。
- Produces: database constraint `daily_menus_user_date_unique`。

- [ ] **Step 1: Write the failing database constraint test**

Create `tests/Feature/Database/DailyMenuUniquenessTest.php`:

```php
<?php

namespace Tests\Feature\Database;

use App\Models\DailyMenu;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyMenuUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_have_two_menus_for_the_same_date(): void
    {
        $user = User::factory()->create();
        $date = now()->toDateString();

        DailyMenu::create([
            'user_id' => $user->id,
            'date' => $date,
            'menu_content' => 'First menu',
        ]);

        $this->expectException(QueryException::class);

        DailyMenu::create([
            'user_id' => $user->id,
            'date' => $date,
            'menu_content' => 'Duplicate menu',
        ]);
    }
}
```

- [ ] **Step 2: Run the test and verify RED**

Run:

```powershell
php artisan test tests/Feature/Database/DailyMenuUniquenessTest.php
```

Expected: FAIL because the second insert succeeds and no `QueryException` is thrown.

- [ ] **Step 3: Add the deduplicating unique-index migration**

Create `database/migrations/2026_07_22_120000_add_unique_user_date_to_daily_menus.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('daily_menus')
            ->select('user_id', 'date', DB::raw('MAX(id) as keep_id'))
            ->groupBy('user_id', 'date')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('daily_menus')
                ->where('user_id', $duplicate->user_id)
                ->where('date', $duplicate->date)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('daily_menus', function (Blueprint $table): void {
            $table->unique(['user_id', 'date'], 'daily_menus_user_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('daily_menus', function (Blueprint $table): void {
            $table->dropUnique('daily_menus_user_date_unique');
        });
    }
};
```

- [ ] **Step 4: Run the database test and verify GREEN**

Run:

```powershell
php artisan test tests/Feature/Database/DailyMenuUniquenessTest.php
```

Expected: 1 test passes.

- [ ] **Step 5: Commit the data invariant**

```powershell
git add database/migrations/2026_07_22_120000_add_unique_user_date_to_daily_menus.php tests/Feature/Database/DailyMenuUniquenessTest.php
git commit -m "feat: enforce one daily menu per user"
```

---

### Task 2: Rotate Valid Products and Build a Structured Daily Fallback

**Files:**
- Create: `tests/Unit/Services/DailyMenuLifecycleTest.php`
- Modify: `app/Services/AiMenuService.php`
- Modify: `app/Services/Ai/PromptBuilder.php`

**Interfaces:**
- Consumes: `Product::STATUS_PUBLISHED`, `UserPreference`, application date.
- Produces: `AiMenuService::generateDailyMenuForUser(User $user, ?array $overridePreferences = null, bool $force = false): DailyMenu` and a maximum of 8 rotated product names.

- [ ] **Step 1: Write failing lifecycle tests for filtering and date rotation**

Create `tests/Unit/Services/DailyMenuLifecycleTest.php` with a fake Provider that records calls:

```php
<?php

namespace Tests\Unit\Services;

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

        $this->provider = new class implements AiProviderInterface {
            public array $calls = [];

            public function name(): string { return 'fake'; }
            public function isConfigured(): bool { return true; }

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
            ['name' => '菜心'], ['name' => '白菜'], ['name' => '紅蘿蔔'],
        )->create(['status' => Product::STATUS_PUBLISHED, 'stock' => 10]);

        $menu = $this->service->generateDailyMenuForUser($this->user);

        $this->assertIsArray($menu->menu_json);
        $this->assertCount(3, $menu->menu_json['meals']);
        $ingredients = collect($menu->menu_json['meals'])->pluck('ingredients')->flatten()->all();
        $this->assertEmpty(array_diff($ingredients, ['菜心', '白菜', '紅蘿蔔']));
    }
}
```

- [ ] **Step 2: Run the lifecycle tests and verify RED**

Run:

```powershell
php artisan test tests/Unit/Services/DailyMenuLifecycleTest.php
```

Expected: failures because draft/out-of-stock products are included, `menu_date` is missing, product count is not capped, and fallback has no `menu_json`.

- [ ] **Step 3: Add date-aware candidate rotation**

In `AiMenuService`, add `MAX_CANDIDATE_PRODUCTS = 8`, include `Product::STATUS_PUBLISHED`, set `$preferences['menu_date'] = $date`, and replace the unrestricted product query with:

```php
private const MAX_CANDIDATE_PRODUCTS = 8;

/** @return array<int, string> */
private function candidateProductNames(User $user, string $date): array
{
    $names = Product::query()
        ->where('status', Product::STATUS_PUBLISHED)
        ->where('stock', '>', 0)
        ->orderBy('id')
        ->pluck('name')
        ->values();

    if ($names->isEmpty()) {
        return [];
    }

    $offset = ($user->id + Carbon::parse($date)->dayOfYear) % $names->count();

    return $names->slice($offset)
        ->concat($names->take($offset))
        ->take(self::MAX_CANDIDATE_PRODUCTS)
        ->values()
        ->all();
}
```

If the candidate list is empty, throw:

```php
throw new GuardFailedException(GuardCode::Ai, '暂无可推荐商品', [
    'reason' => 'NO_AVAILABLE_PRODUCTS',
]);
```

- [ ] **Step 4: Add a structured fallback**

Replace the text-only fallback with:

```php
/** @param array<int, string> $products */
private function generateFallbackMenuJson(array $preferences, array $products): array
{
    $habit = $preferences['dietary_habits'] ?? 'Healthy';
    $items = collect($products)->values();
    $productAt = fn (int $index): string => $items[$index % $items->count()];

    return [
        'greeting' => "A fresh {$habit} menu selected from today's GreenBite products.",
        'meals' => [
            ['type' => 'breakfast', 'name' => 'Morning '.$productAt(0), 'ingredients' => [$productAt(0)], 'description' => 'Serve simply to keep the ingredient fresh and light.'],
            ['type' => 'lunch', 'name' => 'Seasonal '.$productAt(1), 'ingredients' => [$productAt(1)], 'description' => 'Cook gently with a small amount of oil for a balanced lunch.'],
            ['type' => 'dinner', 'name' => 'Evening '.$productAt(2), 'ingredients' => [$productAt(2)], 'description' => 'Prepare warm with simple seasoning for a satisfying dinner.'],
        ],
        'tip' => 'Use only the portions you need and store the remaining produce carefully.',
    ];
}
```

When Provider output is empty or invalid, render and persist it:

```php
$jsonData = $this->generateFallbackMenuJson($preferences, $availableProducts);
$content = MenuRenderer::renderTextFromJson($jsonData);
$tokens = 0;
```

- [ ] **Step 5: Add the date to the short prompt**

In `PromptBuilder::buildUserPrompt()` add:

```php
$menuDate = self::sanitizeUserInput($preferences['menu_date'] ?? now()->toDateString());
```

and include this single line inside `<user_preferences>`:

```text
Menu date: {$menuDate}
```

- [ ] **Step 6: Run lifecycle and existing AI tests**

```powershell
php artisan test tests/Unit/Services/DailyMenuLifecycleTest.php tests/Unit/Services/AiMenuServiceTest.php tests/Unit/Services/AiMenuServiceFallbackTest.php
```

Expected: all tests pass. Update existing fallback assertions from `[AI Demo]` to assert structured fallback meal content and `menu_json` where necessary; do not weaken the assertions.

- [ ] **Step 7: Commit product rotation and fallback**

```powershell
git add app/Services/AiMenuService.php app/Services/Ai/PromptBuilder.php tests/Unit/Services/DailyMenuLifecycleTest.php tests/Unit/Services/AiMenuServiceTest.php tests/Unit/Services/AiMenuServiceFallbackTest.php
git commit -m "feat: generate date-aware daily menus"
```

---

### Task 3: Make Regeneration Actually Replace Today's Menu

**Files:**
- Modify: `tests/Unit/Services/DailyMenuLifecycleTest.php`
- Create: `tests/Feature/Api/MenuRegenerateTest.php`
- Modify: `app/Services/AiMenuService.php`

**Interfaces:**
- Consumes: `AiMenuService::regenerate(User $user, ?array $overridePreferences = null)`.
- Produces: force path through `generateDailyMenuForUser(..., force: true)` while retaining one database row.

- [ ] **Step 1: Add failing force-regeneration tests**

Extend the fake Provider in `DailyMenuLifecycleTest` with this exact behavior:

```php
public array $calls = [];
public int $generation = 0;
public bool $returnEmpty = true;

public function generate(array $preferences, array $products): array
{
    $this->calls[] = compact('preferences', 'products');

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
```

Add:

```php
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
```

Create `tests/Feature/Api/MenuRegenerateTest.php`:

```php
<?php

namespace Tests\Feature\Api;

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

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->user = User::factory()->create();
        UserPreference::factory()->for($this->user)->create();
        Product::factory()->count(3)->create(['status' => Product::STATUS_PUBLISHED, 'stock' => 10]);

        $provider = new class implements AiProviderInterface {
            public int $generation = 0;
            public function name(): string { return 'fake'; }
            public function isConfigured(): bool { return true; }
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

        $this->app->instance(AiProviderInterface::class, $provider);
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

        $this->postJson('/api/menu/regenerate')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'GUARD-AI-RATE');

        $this->assertSame($latest, DailyMenu::where('user_id', $this->user->id)->firstOrFail()->menu_content);
    }
}
```

- [ ] **Step 2: Run the tests and verify RED**

```powershell
php artisan test tests/Unit/Services/DailyMenuLifecycleTest.php --filter=regenerate
php artisan test tests/Feature/Api/MenuRegenerateTest.php
```

Expected: the content remains unchanged because the current service re-reads the existing row.

- [ ] **Step 3: Add the explicit force flag**

Change the signature:

```php
public function generateDailyMenuForUser(
    User $user,
    ?array $overridePreferences = null,
    bool $force = false,
): DailyMenu
```

Wrap both cache and DB early-return paths in `if (! $force)`. Change `regenerate()` to:

```php
Cache::forget(sprintf(self::CACHE_KEY_MENU, $user->id, $date));

return $this->generateDailyMenuForUser($user, $overridePreferences, force: true);
```

Keep `upsertMenu()` updating the same date row. Import `Illuminate\Database\QueryException` and wrap the first save as follows so a concurrent insert reloads and updates the winning row:

```php
try {
    $menu->fill($fillData)->save();
} catch (QueryException $exception) {
    $menu = DailyMenu::where('user_id', $user->id)
        ->whereDate('date', $dateStr)
        ->first();

    if (! $menu) {
        throw $exception;
    }

    $menu->fill($fillData)->save();
}

return $menu;
```

- [ ] **Step 4: Run regeneration and existing rate-limit tests**

```powershell
php artisan test tests/Unit/Services/DailyMenuLifecycleTest.php tests/Feature/Api/MenuRegenerateTest.php tests/Unit/Services/AiMenuServiceTest.php --filter=regenerate
```

Expected: force regeneration changes the saved menu, only one row exists, and the fourth attempt is rejected.

- [ ] **Step 5: Commit regeneration semantics**

```powershell
git add app/Services/AiMenuService.php tests/Unit/Services/DailyMenuLifecycleTest.php tests/Feature/Api/MenuRegenerateTest.php
git commit -m "fix: force daily menu regeneration"
```

---

### Task 4: Add the Auth-Aware Home Controller and Seven-Day View Model

**Files:**
- Create: `app/Http/Controllers/Web/HomeController.php`
- Create: `tests/Feature/Web/HomePageTest.php`
- Modify: `routes/web.php`
- Modify: `resources/views/pages/welcome.blade.php`

**Interfaces:**
- Consumes: Web session user, `AiMenuService`, `DailyMenu`, `MenuRenderer`.
- Produces: view variables `menuDays`, `menuState`, and `menuError`; data test IDs `guest-signup-section` and `daily-menu-section`.

- [ ] **Step 1: Write failing homepage behavior tests**

Create `tests/Feature/Web/HomePageTest.php` covering:

```php
public function test_guest_sees_signup_but_not_daily_menu(): void
{
    $this->get('/')
        ->assertOk()
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
        ->assertDontSee('data-testid="guest-signup-section"', false)
        ->assertSee('data-testid="daily-menu-section"', false)
        ->assertSee('Saved today menu');
}

public function test_authenticated_first_home_visit_generates_and_persists_today_menu(): void
{
    $user = User::factory()->create();
    UserPreference::factory()->for($user)->create();
    Product::factory()->count(3)->create(['status' => Product::STATUS_PUBLISHED, 'stock' => 10]);

    $this->actingAs($user)->get('/')->assertOk();

    $this->assertDatabaseHas('daily_menus', [
        'user_id' => $user->id,
        'date' => now()->toDateString(),
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
    DailyMenu::create(['user_id' => $other->id, 'date' => now()->subDay()->toDateString(), 'menu_content' => 'Other user menu']);

    $this->actingAs($user)->get('/')
        ->assertOk()
        ->assertSee('Today owner menu')
        ->assertSee('Six days owner menu')
        ->assertDontSee('Seven days old menu')
        ->assertDontSee('Other user menu');
}

public function test_user_without_preferences_sees_survey_state_without_menu_generation(): void
{
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')
        ->assertOk()
        ->assertSee('data-testid="menu-needs-preferences"', false);

    $this->assertDatabaseCount('daily_menus', 0);
}
```

- [ ] **Step 2: Run homepage tests and verify RED**

```powershell
php artisan test tests/Feature/Web/HomePageTest.php
```

Expected: missing test IDs and no authenticated home generation.

- [ ] **Step 3: Add `HomeController`**

Implement `HomeController::index(Request $request): View` with this flow:

```php
if (! $request->user()) {
    return view('pages.welcome', [
        'menuDays' => collect(),
        'menuState' => 'guest',
        'menuError' => null,
    ]);
}

$user = $request->user();
$menuState = 'ready';
$menuError = null;

if (! $user->userPreferences()->exists()) {
    $menuState = 'needs_preferences';
} else {
    try {
        $this->aiService->generateDailyMenuForUser($user);
    } catch (GuardFailedException $exception) {
        $menuState = ($exception->context['reason'] ?? null) === 'NO_AVAILABLE_PRODUCTS'
            ? 'no_products'
            : 'generation_failed';
        $menuError = $exception->userMessage;
    }
}
```

Then query only the current user's records with date strings from `now()->subDays(6)->toDateString()` through `now()->toDateString()` inclusive, build exactly seven date entries, and render `menu_json` through a published/in-stock product map. Use:

```php
$menusByDate = DailyMenu::query()
    ->where('user_id', $user->id)
    ->whereDate('date', '>=', now()->subDays(6)->toDateString())
    ->whereDate('date', '<=', now()->toDateString())
    ->orderByDesc('date')
    ->get()
    ->keyBy(fn (DailyMenu $menu) => $menu->date->toDateString());
```

Each entry has:

```php
[
    'date' => $date->toDateString(),
    'label' => $date->isToday() ? i18n('homeMenu.today') : $date->translatedFormat('m/d'),
    'menu' => $menu,
    'html' => $menu?->menu_json ? MenuRenderer::renderHtmlFromJson($menu->menu_json, $productMap) : null,
]
```

- [ ] **Step 4: Route `/` through `HomeController`**

Replace the root closure with:

```php
use App\Http\Controllers\Web\HomeController;

Route::get('/', [HomeController::class, 'index'])->name('home');
```

- [ ] **Step 5: Add minimal mutually exclusive Blade states**

Wrap the existing Join CTA in:

```blade
@guest
<div data-testid="guest-signup-section" class="bg-white rounded-3xl ...">
    {{-- existing Join CTA unchanged --}}
</div>
@endguest
```

Add an authenticated section before it with `data-testid="daily-menu-section"`, seven date buttons, today's active panel, plain-text fallback, and `menu-needs-preferences` / `menu-no-products` markers. Use `{!! $day['html'] !!}` only for output created by `MenuRenderer`.

- [ ] **Step 6: Run homepage and dashboard regression tests**

```powershell
php artisan test tests/Feature/Web/HomePageTest.php tests/Feature/Web/DashboardTest.php
```

Expected: homepage tests pass and the existing dashboard remains compatible.

- [ ] **Step 7: Commit the authenticated homepage**

```powershell
git add app/Http/Controllers/Web/HomeController.php routes/web.php resources/views/pages/welcome.blade.php tests/Feature/Web/HomePageTest.php
git commit -m "feat: show daily menus on the home page"
```

---

### Task 5: Complete Three-Language UI, Regenerate Interaction, and Login Redirect

**Files:**
- Modify: `tests/Feature/Web/HomePageTest.php`
- Modify: `resources/views/pages/welcome.blade.php`
- Modify: `resources/views/auth/login.blade.php`
- Modify: `resources/lang/en.json`
- Modify: `resources/lang/zh.json`
- Modify: `resources/lang/zhhk.json`

**Interfaces:**
- Consumes: `POST /api/menu/regenerate`, `gbFetch()`, `menuDays`.
- Produces: `homeMenu.*` translation namespace and default login destination `/`.

- [ ] **Step 1: Add failing UI-contract and locale tests**

Add these test methods to `HomePageTest`:

```php
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
    $this->get('/login')
        ->assertOk()
        ->assertSee("params.get('return') || '/'", false)
        ->assertSee("params.get('return')", false);
}
```

- [ ] **Step 2: Run the focused tests and verify RED**

```powershell
php artisan test tests/Feature/Web/HomePageTest.php --filter="locale|regenerate|login"
```

Expected: missing translations/button contract and login still contains `/catalog`.

- [ ] **Step 3: Add `homeMenu` translations**

Add these exact objects:

```json
// en.json
"homeMenu": {
  "title": "Today's menu",
  "subtitle": "Your personalized recommendations for today and the previous six days.",
  "today": "Today",
  "previousDays": "Previous days",
  "noMenu": "No menu was saved for this day.",
  "needsPreferences": "Complete your preferences before generating a menu.",
  "completePreferences": "Complete preferences",
  "noProducts": "No products are currently available for menu recommendations.",
  "generationFailed": "We could not generate today's menu.",
  "regenerate": "Regenerate",
  "regenerating": "Regenerating...",
  "regenerateFailed": "Regeneration failed. Please try again.",
  "updated": "Saved for today",
  "source": "Source"
}

// zh.json
"homeMenu": {
  "title": "今日菜单",
  "subtitle": "查看今天及过去六天的个性化推荐菜单。",
  "today": "今天",
  "previousDays": "往日菜单",
  "noMenu": "当日暂无菜单。",
  "needsPreferences": "请先完成饮食偏好，再生成菜单。",
  "completePreferences": "填写饮食偏好",
  "noProducts": "当前没有可用于推荐菜单的商品。",
  "generationFailed": "今日菜单生成失败。",
  "regenerate": "重新生成",
  "regenerating": "正在重新生成...",
  "regenerateFailed": "重新生成失败，请稍后重试。",
  "updated": "今日已保存",
  "source": "来源"
}

// zhhk.json
"homeMenu": {
  "title": "今日餐單",
  "subtitle": "查看今日及過去六日的個人化推薦餐單。",
  "today": "今日",
  "previousDays": "過往餐單",
  "noMenu": "當日暫無餐單。",
  "needsPreferences": "請先完成飲食偏好，再生成餐單。",
  "completePreferences": "填寫飲食偏好",
  "noProducts": "目前沒有可用於推薦餐單的商品。",
  "generationFailed": "今日餐單生成失敗。",
  "regenerate": "重新生成",
  "regenerating": "正在重新生成...",
  "regenerateFailed": "重新生成失敗，請稍後再試。",
  "updated": "今日已儲存",
  "source": "來源"
}
```

Insert each object as valid JSON without the explanatory comment lines, then validate all files with `ConvertFrom-Json`.

- [ ] **Step 4: Complete the menu UI and interaction**

The active menu card must show the selected date, rendered menu, source label, and:

```blade
<button data-testid="regenerate-menu-button" type="button" id="regenerate-menu-button">
    {{ i18n('homeMenu.regenerate') }}
</button>
<p id="regenerate-menu-error" class="hidden text-red-600" role="alert"></p>
```

Only show the button when today's menu exists. Implement the click handler:

```javascript
$('#regenerate-menu-button').on('click', function() {
    const $button = $(this);
    const $error = $('#regenerate-menu-error');
    $button.prop('disabled', true).text(@json(i18n('homeMenu.regenerating')));
    $error.addClass('hidden').text('');

    gbFetch('/api/menu/regenerate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({}),
    })
        .then(async response => ({ ok: response.ok, body: await response.json() }))
        .then(({ ok, body }) => {
            if (! ok) throw new Error(body?.error?.message || @json(i18n('homeMenu.regenerateFailed')));
            location.reload();
        })
        .catch(error => {
            $error.removeClass('hidden').text(error.message || @json(i18n('homeMenu.regenerateFailed')));
            $button.prop('disabled', false).text(@json(i18n('homeMenu.regenerate')));
        });
});
```

Date buttons switch already-rendered panels locally and never call the API.

- [ ] **Step 5: Change login's default return target**

In `resources/views/auth/login.blade.php`:

```javascript
const returnTo = params.get('return') || '/';
```

Do not change explicit `return` handling.

- [ ] **Step 6: Validate translations, tests, and production build**

```powershell
Get-Content -Raw resources\lang\zh.json | ConvertFrom-Json | Out-Null
Get-Content -Raw resources\lang\zhhk.json | ConvertFrom-Json | Out-Null
Get-Content -Raw resources\lang\en.json | ConvertFrom-Json | Out-Null
php artisan test tests/Feature/Web/HomePageTest.php tests/Feature/Api/MenuRegenerateTest.php
npm run build
```

Expected: JSON parsing succeeds, focused tests pass, and Vite exits 0.

- [ ] **Step 7: Commit the complete home menu UI**

```powershell
git add resources/views/pages/welcome.blade.php resources/views/auth/login.blade.php resources/lang/en.json resources/lang/zh.json resources/lang/zhhk.json tests/Feature/Web/HomePageTest.php
git commit -m "feat: complete the daily menu home experience"
```

---

### Task 6: Verify and Configure DeepSeek V4 Flash with a Small Token Budget

**Files:**
- Modify: `config/ai.php`
- Modify: `app/Services/Ai/Providers/DeepseekProvider.php`
- Modify: `.env.example`
- Test: `tests/Unit/Services/Ai/Providers/JsonOutputTest.php`
- Local only: `.env` (ignored; never stage)

**Interfaces:**
- Consumes: `DEEPSEEK_API_KEY`, DeepSeek `GET /models`, chat completions.
- Produces: `AI_PROVIDER=deepseek`, verified V4 Flash model selection, `AI_DEEPSEEK_MAX_TOKENS=400` default.

- [ ] **Step 1: Add a failing Provider request-budget test**

In `JsonOutputTest`, configure DeepSeek with `max_tokens => 400`, fake the completion endpoint, call `generate()`, and assert:

```php
Http::assertSent(fn ($request) =>
    $request['model'] === 'deepseek-chat'
    && $request['max_tokens'] === 400
    && count($request['messages']) === 2
);
```

- [ ] **Step 2: Run the Provider test and verify RED**

```powershell
php artisan test tests/Unit/Services/Ai/Providers/JsonOutputTest.php
```

Expected: request still hard-codes `max_tokens = 500`.

- [ ] **Step 3: Make the output budget configurable**

In `config/ai.php` DeepSeek config add:

```php
'max_tokens' => (int) env('AI_DEEPSEEK_MAX_TOKENS', 400),
```

In `DeepseekProvider` use:

```php
'max_tokens' => $this->config['max_tokens'] ?? 400,
```

Add commented, secret-free examples to `.env.example`:

```dotenv
# AI_PROVIDER=deepseek
# AI_DEEPSEEK_MODEL=deepseek-chat
# AI_DEEPSEEK_MAX_TOKENS=400
```

- [ ] **Step 4: Query the authorized model list without printing the key**

Using the ignored local `.env` key, request `GET https://api.deepseek.com/v1/models` with a Bearer header. Print only HTTP status and model IDs.

Decision rule:

- If the returned IDs include `deepseek-v4-flash`, set local `AI_DEEPSEEK_MODEL=deepseek-v4-flash`.
- Otherwise set local `AI_DEEPSEEK_MODEL=deepseek-chat`, the stable chat alias already supported by this repository.
- Set local `AI_PROVIDER=deepseek` and `AI_DEEPSEEK_MAX_TOKENS=400`.
- Run `php artisan config:clear` after changing `.env`.

- [ ] **Step 5: Run one real minimal menu generation**

Use the isolated worktree database and a demo user with preferences. Record only response status, chosen model, `tokens_used`, saved menu ID, and whether all returned ingredients match published in-stock products. Do not print the key, Authorization header, full prompt, or full response body.

If 400 output tokens truncates valid JSON, increase only `AI_DEEPSEEK_MAX_TOKENS` to 450 and repeat once. Do not exceed 450 in this iteration.

- [ ] **Step 6: Run Provider tests and commit tracked configuration**

```powershell
php artisan test tests/Unit/Services/Ai/Providers/JsonOutputTest.php
git add config/ai.php app/Services/Ai/Providers/DeepseekProvider.php .env.example tests/Unit/Services/Ai/Providers/JsonOutputTest.php
git commit -m "chore: tune DeepSeek daily menu generation"
```

Confirm `git status --short` does not include `.env`.

---

### Task 7: Full Regression and Browser Demonstration

**Files:**
- Verify only; no unrelated refactoring.

**Interfaces:**
- Consumes: all outputs from Tasks 1-6.
- Produces: evidence that the complete graduation-project flow works locally.

- [ ] **Step 1: Run focused tests**

```powershell
php artisan test tests/Feature/Database/DailyMenuUniquenessTest.php
php artisan test tests/Unit/Services/DailyMenuLifecycleTest.php
php artisan test tests/Feature/Api/MenuRegenerateTest.php
php artisan test tests/Feature/Web/HomePageTest.php
php artisan test tests/Feature/Web/DashboardTest.php
php artisan test tests/Feature/Api/AuthApiTest.php
```

Expected: zero failures.

- [ ] **Step 2: Run the full PHPUnit suite**

```powershell
php artisan test
```

Expected: all tests pass; the current baseline is 160 tests before this iteration.

- [ ] **Step 3: Run production build and route checks**

```powershell
npm run build
php artisan route:list --path=menu
php artisan route:list --path=login
```

Expected: Vite exits 0; menu API routes remain protected and login/home routes are reachable.

- [ ] **Step 4: Perform browser verification on the worktree server**

1. Open `/` logged out: signup section visible, daily menu absent.
2. Log in with the demo account: default navigation returns `/`.
3. Confirm signup section is absent and today's menu appears.
4. Reload twice: menu text and saved database ID remain unchanged.
5. Click “重新生成”: one request is sent, the same database ID has a newer `updated_at`, and the page shows the replacement.
6. Switch across the seven date buttons: existing menus display; missing dates show the localized empty state without network requests.
7. Open `/login?return=/checkout`, log in, and confirm `/checkout` still wins.
8. Check Simplified Chinese, Traditional Chinese, and English for raw keys.
9. Check a 375px viewport for horizontal overflow and operable controls.
10. Confirm the browser console contains no error caused by this feature.

- [ ] **Step 5: Review diff and worktree state**

```powershell
git diff main...HEAD --check
git status --short
```

Expected: no whitespace errors, no uncommitted tracked files, and `.env` remains ignored.

---

## Completion Checklist

- [ ] Guest signup and authenticated daily menu states are mutually exclusive.
- [ ] Default login returns home while explicit return targets remain intact.
- [ ] First authenticated home visit creates one persisted menu for today.
- [ ] Repeated same-day visits do not call AI again.
- [ ] Active regeneration overwrites today's row and remains limited to 3 per day.
- [ ] Tomorrow rotates the product candidate list and creates a new row.
- [ ] Seven-day history is current-user-only and never backfills missing dates.
- [ ] DeepSeek uses the verified V4 Flash ID or official stable chat alias with no more than 450 output tokens.
- [ ] No secret is tracked or printed.
- [ ] Three-language UI, focused tests, full suite, build, routes, and browser demonstration pass.
