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
