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
