<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DailyMenu;
use App\Models\Product;
use App\Services\Ai\MenuRenderer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard 控制器
 *
 * 职责：从 DB 读取当日菜单，渲染成 HTML（含食材链接）供 dashboard 展示。
 *
 * 修复历史：原 dashboard 从 session('daily_ai_menu') 读取，但该 key 从未被写入，
 *           导致永远显示默认提示。改为从 daily_menus 表读取真实数据。
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // 读当日菜单
        $todayMenu = DailyMenu::where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        // 渲染 HTML（如果有 menu_json）
        $aiMenuHtml = null;
        if ($todayMenu && $todayMenu->menu_json) {
            $productMap = Product::where('stock', '>', 0)
                ->pluck('id', 'name')
                ->toArray();
            $aiMenuHtml = MenuRenderer::renderHtmlFromJson($todayMenu->menu_json, $productMap);
        }

        return view('shop.dashboard', [
            'aiMenu' => $todayMenu?->menu_content,
            'aiMenuHtml' => $aiMenuHtml,
        ]);
    }
}
