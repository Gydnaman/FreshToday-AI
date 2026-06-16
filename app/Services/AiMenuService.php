<?php

namespace App\Services;

use App\Enums\GuardCode;
use App\Exceptions\GuardFailedException;
use App\Models\DailyMenu;
use App\Models\Product;
use App\Models\User;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Ai\Providers\NullProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AI 菜单服务（Sprint 2：多 Provider 适配）
 *
 * 变更点（相对 Sprint 1）：
 *  - 注入 AiProviderInterface（由 AiProviderFactory 解析），不再硬编码 Gemini
 *  - 保留全部原有降级链（Cache → DB → Provider → 本地模板）
 *  - 保留全部原有 GUARD（GUARD-AI 未填问卷、GUARD-AI-RATE 3 次/天）
 *  - source 字段：跟随 Provider 名（gemini/openai/deepseek/fallback）
 *  - 5xx/4xx/timeout/网络异常 全部由 Provider 内部捕获，本 Service 不感知
 *
 * 配置（.env 任选其一）：
 *   GEMINI_API_KEY=...    # 默认 google
 *   OPENAI_API_KEY=...    # OpenAI 兼容
 *   DEEPSEEK_API_KEY=...  # OpenAI 兼容，HK 出口稳定
 *   AI_PROVIDER=gemini    # 显式指定，留空则按 config('ai.auto_detect_order') 探测
 */
class AiMenuService
{
    private const CACHE_TTL_SECONDS = 86400;       // 24h

    private const DAILY_REGEN_LIMIT = 3;

    private const CACHE_KEY_MENU = 'ai_menu:user:%d:date:%s';

    private const CACHE_KEY_REGEN = 'ai_menu:regen:%d:%s';

    public function __construct(private readonly AiProviderInterface $provider) {}

    public function generateDailyMenuForUser(User $user, ?array $overridePreferences = null): DailyMenu
    {
        $preferences = $overridePreferences ?? $this->resolvePreferences($user);
        if (empty($preferences)) {
            throw new GuardFailedException(GuardCode::Ai, '用户未填写问卷偏好，无法生成菜单', [
                'user_id' => $user->id,
            ]);
        }

        $date = now()->toDateString();
        // 写库时用 Carbon 完整时间戳（00:00:00），与 date cast 一致
        // → updateOrCreate 的 where('date', ...) 才能匹配
        $dateForDb = Carbon::parse($date)->startOfDay();
        $cacheKey = sprintf(self::CACHE_KEY_MENU, $user->id, $date);

        // 1. 命中缓存
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $this->upsertMenu($user, $date, $cached, $this->provider->name(), 0);
        }

        // 2. 命中 DB
        $existing = DailyMenu::where('user_id', $user->id)->whereDate('date', $date)->first();
        if ($existing) {
            Cache::put($cacheKey, $existing->menu_content, self::CACHE_TTL_SECONDS);

            return $existing;
        }

        // 3. 调 Provider
        $availableProducts = Product::where('stock', '>', 0)->pluck('name')->toArray();
        [$content, $tokens] = $this->callProvider($preferences, $availableProducts);

        // 4. Provider 返回空（缺 key / 全失败）→ 本地模板
        //    source 仍记 provider 名（保留 Sprint 1 行为："意图调用的 provider"）
        if ($content === '') {
            $content = $this->generateFallbackMenu($preferences, $availableProducts);
            $tokens = 0;
        }

        Cache::put($cacheKey, $content, self::CACHE_TTL_SECONDS);

        return $this->upsertMenu($user, $dateForDb, $content, $this->provider->name(), $tokens);
    }

    /**
     * 强制重新生成（限流 3 次/天）
     */
    public function regenerate(User $user, ?array $overridePreferences = null): DailyMenu
    {
        $date = now()->toDateString();
        $regenKey = sprintf(self::CACHE_KEY_REGEN, $user->id, $date);
        $count = (int) Cache::increment($regenKey);
        if ($count === 1) {
            Cache::put($regenKey, 1, self::CACHE_TTL_SECONDS);
        }
        if ($count > self::DAILY_REGEN_LIMIT) {
            throw new GuardFailedException(GuardCode::AiRate, '每日最多重新生成 3 次', [
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
        [$content] = $this->callProvider($preferences, $availableProducts);

        return $content !== '' ? $content : $this->generateFallbackMenu($preferences, $availableProducts);
    }

    /**
     * 当前 Provider 名（暴露给上层做可观测 / 日志）
     */
    public function providerName(): string
    {
        return $this->provider->name();
    }

    private function resolvePreferences(User $user): ?array
    {
        $pref = $user->userPreferences;
        if (! $pref) {
            return null;
        }

        return [
            'purpose' => $pref->usage_purpose,
            'dietary_habits' => $pref->dietary_habits,
            'goals' => $pref->goals,
            'cooking_skill' => $pref->cooking_skill,
            'budget_hkd' => $pref->budget_hkd,
        ];
    }

    private function upsertMenu(User $user, Carbon|string $date, string $content, string $source, int $tokens): DailyMenu
    {
        // 用 whereDate 自己查询再 save，规避 updateOrCreate 内部用精确 where 无法匹配 date cast 的问题
        $dateStr = $date instanceof Carbon ? $date->toDateString() : $date;
        $menu = DailyMenu::where('user_id', $user->id)
            ->whereDate('date', $dateStr)
            ->first();
        if (! $menu) {
            $menu = new DailyMenu(['user_id' => $user->id, 'date' => $dateStr]);
        }
        $menu->fill([
            'menu_content' => $content,
            'source' => $source,
            'tokens_used' => $tokens,
        ])->save();

        return $menu;
    }

    /**
     * 调用 Provider；统一封装"失败返回空"的语义
     * - Provider 内部已捕获所有异常，这里只兜底 NullProvider / 工厂异常
     *
     * @return array{0:string,1:int} [content, tokens_used]
     */
    private function callProvider(array $preferences, array $availableProducts): array
    {
        // NullProvider 短路：避免无意义日志噪音
        if ($this->provider instanceof NullProvider) {
            return ['', 0];
        }

        if (! $this->provider->isConfigured()) {
            Log::info('AiMenuService: provider not configured, skip call', [
                'provider' => $this->provider->name(),
            ]);

            return ['', 0];
        }

        try {
            return $this->provider->generate($preferences, $availableProducts);
        } catch (\Throwable $e) {
            // Provider 自身已捕获异常；这里再兜一次（防御性编程）
            Log::error('AiMenuService: unexpected provider exception', [
                'provider' => $this->provider->name(),
                'error' => $e->getMessage(),
            ]);

            return ['', 0];
        }
    }

    private function generateFallbackMenu(array $preferences, array $availableProducts): string
    {
        $ingredients = array_slice($availableProducts, 0, 2);
        $itemsStr = implode(' and ', $ingredients) ?: 'seasonal produce';
        $habit = $preferences['dietary_habits'] ?? 'Healthy';

        return "🌱 [AI Demo] A {$habit} lunch just for you: we picked freshly harvested {$itemsStr}. "
            .'Lightly sauté with a little olive oil to preserve nutrients. '
            .'Lower-carbon and delicious—enjoy!';
    }
}
