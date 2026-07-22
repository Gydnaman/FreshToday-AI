<?php

namespace App\Services;

use App\Enums\GuardCode;
use App\Exceptions\GuardFailedException;
use App\Models\DailyMenu;
use App\Models\Product;
use App\Models\User;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Ai\MenuOutputValidator;
use App\Services\Ai\MenuRenderer;
use App\Services\Ai\MetricsRecorder;
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

    private const MAX_CANDIDATE_PRODUCTS = 8;

    private const CACHE_KEY_MENU = 'ai_menu:user:%d:date:%s';

    private const CACHE_KEY_REGEN = 'ai_menu:regen:%d:%s';

    public function __construct(
        private readonly AiProviderInterface $provider,
        private readonly MenuOutputValidator $validator = new MenuOutputValidator,
    ) {}

    public function generateDailyMenuForUser(User $user, ?array $overridePreferences = null, bool $force = false): DailyMenu
    {
        $preferences = $overridePreferences ?? $this->resolvePreferences($user);
        if (empty($preferences)) {
            throw new GuardFailedException(GuardCode::Ai, '用户未填写问卷偏好，无法生成菜单', [
                'user_id' => $user->id,
            ]);
        }

        $date = now()->toDateString();
        $preferences['menu_date'] = $date;
        // 写库时用 Carbon 完整时间戳（00:00:00），与 date cast 一致
        // → updateOrCreate 的 where('date', ...) 才能匹配
        $dateForDb = Carbon::parse($date)->startOfDay();
        $cacheKey = sprintf(self::CACHE_KEY_MENU, $user->id, $date);

        // 1. 命中缓存（无 json 数据，传 null）
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $this->upsertMenu($user, $date, $cached, $this->provider->name(), 0, null);
        }

        // 2. 命中 DB
        $existing = DailyMenu::where('user_id', $user->id)->whereDate('date', $date)->first();
        if ($existing) {
            Cache::put($cacheKey, $existing->menu_content, self::CACHE_TTL_SECONDS);

            return $existing;
        }

        // 3. 调 Provider
        $availableProducts = $this->candidateProductNames($user, $date);
        if ($availableProducts === []) {
            throw new GuardFailedException(GuardCode::Ai, '暂无可推荐商品', [
                'reason' => 'NO_AVAILABLE_PRODUCTS',
            ]);
        }

        [$content, $tokens, $jsonData] = $this->callProvider($preferences, $availableProducts);

        // 4. 校验 + 渲染
        // 4a. JSON 模式：优先用结构化数据
        if ($jsonData !== null) {
            if ($this->validator->validateJson($jsonData, $availableProducts)) {
                $content = MenuRenderer::renderTextFromJson($jsonData);
            } else {
                Log::warning('AiMenuService: provider JSON output failed validation', [
                    'provider' => $this->provider->name(),
                ]);
                $content = '';
                $tokens = 0;
            }
        }
        // 4b. 自由文本模式：校验文本合法性
        elseif ($content !== '' && ! $this->validator->validate($content, $availableProducts)) {
            Log::warning('AiMenuService: provider output failed validation', [
                'provider' => $this->provider->name(),
                'content_preview' => substr($content, 0, 200),
            ]);
            $content = ''; // 触发 fallback
            $tokens = 0;
        }

        // 5. Provider 返回空 / 校验失败 → 本地模板
        //    source 仍记 provider 名（保留 Sprint 1 行为："意图调用的 provider"）
        if ($content === '') {
            $jsonData = $this->generateFallbackMenuJson($preferences, $availableProducts);
            $content = MenuRenderer::renderTextFromJson($jsonData);
            $tokens = 0;
        }

        Cache::put($cacheKey, $content, self::CACHE_TTL_SECONDS);

        // 指标埋点（latency 暂时传 0，后续加 Stopwatch）
        $status = $tokens > 0 ? 'success' : 'failure';
        MetricsRecorder::recordGeneration($this->provider->name(), $status, 0, $tokens);

        // 6. 落库
        return $this->upsertMenu($user, $dateForDb, $content, $this->provider->name(), $tokens, $jsonData ?? null);
    }

    /**
     * 强制重新生成（限流 3 次/天）
     */
    public function regenerate(User $user, ?array $overridePreferences = null): DailyMenu
    {
        $date = now()->toDateString();
        $regenKey = sprintf(self::CACHE_KEY_REGEN, $user->id, $date);
        $count = (int) Cache::increment($regenKey);
        // I-6 修复：每次调用都刷新 TTL + 用 increment 返回值（非固定 1）
        // 旧代码只在 count===1 时 put(1, TTL)，并发下可能被固定值重置
        Cache::put($regenKey, $count, self::CACHE_TTL_SECONDS);
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
        [$content, , $jsonData] = $this->callProvider($preferences, $availableProducts);

        if ($jsonData !== null && $this->validator->validateJson($jsonData, $availableProducts)) {
            return MenuRenderer::renderTextFromJson($jsonData);
        }

        if ($content !== '' && $this->validator->validate($content, $availableProducts)) {
            return $content;
        }

        return MenuRenderer::renderTextFromJson(
            $this->generateFallbackMenuJson($preferences, $availableProducts)
        );
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

    private function upsertMenu(User $user, Carbon|string $date, string $content, string $source, int $tokens, ?array $jsonData = null): DailyMenu
    {
        // 用 whereDate 自己查询再 save，规避 updateOrCreate 内部用精确 where 无法匹配 date cast 的问题
        $dateStr = $date instanceof Carbon ? $date->toDateString() : $date;
        $menu = DailyMenu::where('user_id', $user->id)
            ->whereDate('date', $dateStr)
            ->first();
        if (! $menu) {
            $menu = new DailyMenu(['user_id' => $user->id, 'date' => $dateStr]);
        }
        $fillData = [
            'menu_content' => $content,
            'source' => $source,
            'tokens_used' => $tokens,
        ];
        // 仅在 jsonData 非 null 时更新 menu_json，避免缓存命中路径清空已有数据
        if ($jsonData !== null) {
            $fillData['menu_json'] = $jsonData;
        }
        $menu->fill($fillData)->save();

        return $menu;
    }

    /**
     * 调用 Provider；统一封装"失败返回空"的语义
     * - Provider 内部已捕获所有异常，这里只兜底 NullProvider / 工厂异常
     *
     * @return array{0:string,1:int,2:?array} [content, tokens_used, json_data]
     */
    private function callProvider(array $preferences, array $availableProducts): array
    {
        // NullProvider 短路：避免无意义日志噪音
        if ($this->provider instanceof NullProvider) {
            return ['', 0, null];
        }

        if (! $this->provider->isConfigured()) {
            Log::info('AiMenuService: provider not configured, skip call', [
                'provider' => $this->provider->name(),
            ]);

            return ['', 0, null];
        }

        try {
            return $this->provider->generate($preferences, $availableProducts);
        } catch (\Throwable $e) {
            // Provider 自身已捕获异常；这里再兜一次（防御性编程）
            Log::error('AiMenuService: unexpected provider exception', [
                'provider' => $this->provider->name(),
                'error' => $e->getMessage(),
            ]);

            return ['', 0, null];
        }
    }

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
}
