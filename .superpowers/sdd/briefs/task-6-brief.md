### Task 6: daily_menus 加 menu_json 列

**Files:**
- Create: `database/migrations/2026_07_20_120000_add_menu_json_to_daily_menus.php`
- Modify: `app/Models/DailyMenu.php:10-15`
- Modify: `app/Services/AiMenuService.php::upsertMenu()`
- Test: `tests/Unit/Services/AiMenuServiceTest.php`（验证 json 入库）

**Interfaces:**
- Consumes: Task 4 的 `$jsonData`
- Produces: `DailyMenu::menu_json` cast 为 `array`

- [ ] **Step 1: 写 migration**

创建 `database/migrations/2026_07_20_120000_add_menu_json_to_daily_menus.php`：

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_menus', function (Blueprint $table) {
            $table->json('menu_json')->nullable()->after('menu_content')->comment('结构化菜单 JSON（greeting/meals/tip）');
        });
    }

    public function down(): void
    {
        Schema::table('daily_menus', function (Blueprint $table) {
            $table->dropColumn('menu_json');
        });
    }
};
```

- [ ] **Step 2: 更新 Model**

`app/Models/DailyMenu.php:10-15` 修改：

```php
    protected $fillable = ['user_id', 'menu_content', 'menu_json', 'date', 'source', 'tokens_used'];

    protected $casts = [
        'date' => 'date',
        'tokens_used' => 'integer',
        'menu_json' => 'array',
    ];
```

- [ ] **Step 3: 修改 upsertMenu 接收 jsonData**

`app/Services/AiMenuService.php::upsertMenu()` 签名改为：

```php
    private function upsertMenu(User $user, Carbon|string $date, string $content, string $source, int $tokens, ?array $jsonData = null): DailyMenu
    {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : $date;
        $menu = DailyMenu::where('user_id', $user->id)
            ->whereDate('date', $dateStr)
            ->first();
        if (! $menu) {
            $menu = new DailyMenu(['user_id' => $user->id, 'date' => $dateStr]);
        }
        $menu->fill([
            'menu_content' => $content,
            'menu_json' => $jsonData,
            'source' => $source,
            'tokens_used' => $tokens,
        ])->save();

        return $menu;
    }
```

调用点（`generateDailyMenuForUser` line 62, 86）传入 `$jsonData`：

```php
        // 1. 命中缓存（无 json 数据，传 null）
        if ($cached) {
            return $this->upsertMenu($user, $date, $cached, $this->provider->name(), 0, null);
        }

        // ...

        // 6. 落库
        return $this->upsertMenu($user, $dateForDb, $content, $this->provider->name(), $tokens, $jsonData ?? null);
```

- [ ] **Step 4: 跑 migration + 测试**

```bash
php artisan migrate:fresh --seed
php vendor/bin/phpunit tests/Unit/Services/AiMenuServiceTest.php --no-coverage --filter test_provider_json_output_is_rendered_to_text
```

预期：测试通过，且能断言 `$menu->menu_json` 为数组

- [ ] **Step 5: Commit**

```bash
git add database/migrations/ app/Models/DailyMenu.php app/Services/AiMenuService.php
git commit -m "feat(ai): add menu_json column to daily_menus for structured data"
```

---

