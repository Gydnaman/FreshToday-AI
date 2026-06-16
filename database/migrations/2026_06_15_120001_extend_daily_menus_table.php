<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-15 补：daily_menus 表加 source + tokens_used
 * - 详见 app/Models/DailyMenu.php $fillable 与 app/Services/AiMenuService.php::upsertMenu
 * - 见 ADR-0006 AI 菜单缓存与降级（三层降级标记来源）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_menus', function (Blueprint $table) {
            $table->string('source', 32)->default('template')->after('menu_content')->comment('gemini | template | cache | fallback');
            $table->unsignedInteger('tokens_used')->nullable()->after('source')->comment('Gemini tokens，cache/template 为 null');
        });
    }

    public function down(): void
    {
        Schema::table('daily_menus', function (Blueprint $table) {
            $table->dropColumn(['source', 'tokens_used']);
        });
    }
};
