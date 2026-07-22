<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\GuardFailedException;
use App\Http\Controllers\Controller;
use App\Models\DailyMenu;
use App\Models\Product;
use App\Services\Ai\MenuRenderer;
use App\Services\AiMenuService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(private readonly AiMenuService $aiService) {}

    public function index(Request $request): View
    {
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

        $today = now()->startOfDay();
        $menusByDate = DailyMenu::query()
            ->where('user_id', $user->id)
            ->whereDate('date', '>=', $today->copy()->subDays(6)->toDateString())
            ->whereDate('date', '<=', $today->toDateString())
            ->orderByDesc('date')
            ->get()
            ->keyBy(fn (DailyMenu $menu): string => $menu->date->toDateString());

        $productMap = Product::query()
            ->where('status', Product::STATUS_PUBLISHED)
            ->where('stock', '>', 0)
            ->pluck('id', 'name')
            ->toArray();

        $menuDays = collect(range(0, 6))->map(function (int $daysAgo) use ($today, $menusByDate, $productMap): array {
            $date = $today->copy()->subDays($daysAgo);
            $menu = $menusByDate->get($date->toDateString());

            return [
                'date' => $date->toDateString(),
                'label' => $date->isToday() ? i18n('homeMenu.today') : $date->translatedFormat('m/d'),
                'menu' => $menu,
                'html' => $menu?->menu_json
                    ? MenuRenderer::renderHtmlFromJson($menu->menu_json, $productMap)
                    : null,
            ];
        });

        return view('pages.welcome', [
            'menuDays' => $menuDays,
            'menuState' => $menuState,
            'menuError' => $menuError,
        ]);
    }
}
