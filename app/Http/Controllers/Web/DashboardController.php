<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DailyMenu;
use App\Models\Product;
use App\Services\Ai\MenuRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // 真实统计数据（替换原硬编码占位：12.5 kg / 4 单 / Individual）
        $orderCount = $user->orders()->count();

        $activeSubscription = $user->userSubscriptions()
            ->where('status', 'active')
            ->with('subscriptionPlan:id,name')
            ->latest()
            ->first();

        // 碳减排：有效订单（非待付/取消/退款）中商品碳足迹 × 数量之和
        $carbonSaved = DB::table('order_product')
            ->join('orders', 'orders.id', '=', 'order_product.order_id')
            ->join('products', 'products.id', '=', 'order_product.product_id')
            ->where('orders.user_id', $user->id)
            ->whereNotIn('orders.status', ['pending', 'cancelled', 'refunded'])
            ->sum(DB::raw('products.carbon_footprint * order_product.quantity'));

        return view('shop.dashboard', [
            'aiMenu' => $todayMenu?->menu_content,
            'aiMenuHtml' => $aiMenuHtml,
            'orderCount' => $orderCount,
            'carbonSaved' => (float) $carbonSaved,
            'subscriptionName' => $activeSubscription?->subscriptionPlan?->name,
        ]);
    }
}
