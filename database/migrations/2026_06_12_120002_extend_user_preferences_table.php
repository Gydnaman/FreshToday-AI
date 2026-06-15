<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 1: 补全 user_preferences（与 e2e-scenarios S1 对齐）
 * 决策：方案 A — 新增 cooking_skill + budget_hkd（FOLLOW-UP-2026-06-12）
 * 详见 docs/bmad/er-diagram.md §2.4
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->json('allergies')->nullable()->after('goals')->comment('过敏原数组');
            $table->tinyInteger('household_size')->default(1)->after('allergies');
            $table->enum('cooking_skill', ['Beginner', 'Intermediate', 'Advanced'])
                  ->nullable()
                  ->after('household_size')
                  ->comment('e2e-scenarios S1 Q4');
            $table->decimal('budget_hkd', 8, 2)->nullable()->after('cooking_skill')
                  ->comment('每周预算 HKD, e2e-scenarios S1 Q6');
        });
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropColumn(['allergies', 'household_size', 'cooking_skill', 'budget_hkd']);
        });
    }
};
