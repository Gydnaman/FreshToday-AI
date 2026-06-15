<?php

namespace App\Services;

use App\Exceptions\GuardFailedException;
use App\Models\DailyMenu;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI 菜单服务（Sprint 1 增强版）
 *
 * 增强项：
 * - Redis 缓存 24h（Sprint 1: file/array driver 兼容）
 * - 每日 3 次重新生成限流（Redis 计数器）
 * - DailyMenu 落库（user_id, date UQ）
 * - 失败/无 key 时降级为本地模板
 */
class AiMenuService
{
    private const CACHE_TTL_SECONDS = 86400;       // 24h
    private const DAILY_REGEN_LIMIT  = 3;
    private const CACHE_KEY_MENU     = 'ai_menu:user:%d:date:%s';

    public function generateDailyMenuForUser(User $user, ?array $overridePreferences = null): DailyMenu
    {
        $preferences = $overridePreferences ?? $this->resolvePreferences($user);
        if (empty($preferences)) {
            throw new GuardFailedException('GUARD-AI', '用户未填写问卷偏好，无法生成菜单', [
                'user_id' => $user->id,
            ]);
        }

        $date = now()->toDateString();
        $cacheKey = sprintf(self::CACHE_KEY_MENU, $user->id, $date);

        // 1. 命中缓存
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $this->upsertMenu($user, $date, $cached, 'gemini', 0);
        }

        // 2. 命中 DB
        $existing = DailyMenu::where('user_id', $user->id)->where('date', $date)->first();
        if ($existing) {
            Cache::put($cacheKey, $existing->menu_content, self::CACHE_TTL_SECONDS);
            return $existing;
        }

        // 3. 调 Gemini
        $availableProducts = Product::where('stock', '>', 0)->pluck('name')->toArray();
        [$content, $tokens] = $this->callGemini($preferences, $availableProducts);

        Cache::put($cacheKey, $content, self::CACHE_TTL_SECONDS);

        return $this->upsertMenu($user, $date, $content, 'gemini', $tokens);
    }

    /**
     * 强制重新生成（限流 3 次/天）
     */
    public function regenerate(User $user, ?array $overridePreferences = null): DailyMenu
    {
        $date = now()->toDateString();
        $regenKey = sprintf('ai_menu:regen:%d:%s', $user->id, $date);
        $count = (int) Cache::increment($regenKey);
        if ($count === 1) {
            Cache::put($regenKey, 1, self::CACHE_TTL_SECONDS);
        }
        if ($count > self::DAILY_REGEN_LIMIT) {
            throw new GuardFailedException('GUARD-AI-RATE', '每日最多重新生成 3 次', [
                'limit' => self::DAILY_REGEN_LIMIT,
            ]);
        }

        // 失效缓存
        Cache::forget(sprintf(self::CACHE_KEY_MENU, $user->id, $date));

        return $this->generateDailyMenuForUser($user, $overridePreferences);
    }

    public function getTodayMenu(User $user): ?DailyMenu
    {
        return DailyMenu::where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->first();
    }

    /** 兼容旧接口：纯文本输出（供 SurveyController demo） */
    public function generateDailyMenu(array $preferences, array $availableProducts): string
    {
        [$content, ] = $this->callGemini($preferences, $availableProducts);
        return $content;
    }

    private function resolvePreferences(User $user): ?array
    {
        $pref = $user->userPreferences;
        if (! $pref) return null;
        return [
            'purpose'        => $pref->usage_purpose,
            'dietary_habits' => $pref->dietary_habits,
            'goals'          => $pref->goals,
            'cooking_skill'  => $pref->cooking_skill,
            'budget_hkd'     => $pref->budget_hkd,
        ];
    }

    private function upsertMenu(User $user, string $date, string $content, string $source, int $tokens): DailyMenu
    {
        return DailyMenu::updateOrCreate(
            ['user_id' => $user->id, 'date' => $date],
            ['menu_content' => $content, 'source' => $source, 'tokens_used' => $tokens],
        );
    }

    /** @return array{0:string, 1:int} [content, tokens_used] */
    private function callGemini(array $preferences, array $availableProducts): array
    {
        $apiKey = env('GEMINI_API_KEY');
        if (! $apiKey) {
            return [$this->generateFallbackMenu($preferences, $availableProducts), 0];
        }

        $prompt = "You are a professional nutritionist. Create a ~100-word personalized daily menu.\n"
            . "Purpose: " . ($preferences['purpose'] ?? 'Healthy eating') . "\n"
            . "Dietary: " . ($preferences['dietary_habits'] ?? 'No restriction') . "\n"
            . "Goals: " . ($preferences['goals'] ?? 'Wellness') . "\n"
            . "Skill: " . ($preferences['cooking_skill'] ?? 'Beginner') . "\n"
            . "Budget HKD/wk: " . ($preferences['budget_hkd'] ?? 'flexible') . "\n"
            . "Available: " . implode(', ', $availableProducts) . "\n"
            . "Encourage low-carbon, healthy meals.";

        try {
            $response = Http::timeout(8)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}",
                ['contents' => [['parts' => [['text' => $prompt]]]]],
            );

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text']
                    ?? $this->generateFallbackMenu($preferences, $availableProducts);
                $tokens = $data['usageMetadata']['totalTokenCount'] ?? 0;
                return [$text, (int) $tokens];
            }

            Log::error('Gemini API Error: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('Gemini Request Exception: ' . $e->getMessage());
        }

        return [$this->generateFallbackMenu($preferences, $availableProducts), 0];
    }

    private function generateFallbackMenu(array $preferences, array $availableProducts): string
    {
        $ingredients = array_slice($availableProducts, 0, 2);
        $itemsStr = implode(' and ', $ingredients) ?: 'seasonal produce';
        $habit = $preferences['dietary_habits'] ?? 'Healthy';

        return "🌱 [AI Demo] A {$habit} lunch just for you: we picked freshly harvested {$itemsStr}. "
            . "Lightly sauté with a little olive oil to preserve nutrients. "
            . "Lower-carbon and delicious—enjoy!";
    }
}
