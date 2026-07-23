<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;

/**
 * Web 端产品目录页控制器（/catalog）
 *
 * 直接读 Product Eloquent，与 Api\ProductController 走同一条数据源，
 * 保证 web / 移动端数据一致。
 * 只展示 published 状态产品，与 /api/products 行为一致。
 */
class ProductController extends Controller
{
    public function index(): View
    {
        $products = Product::query()
            ->where('status', Product::STATUS_PUBLISHED)
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        return view('shop.catalog', ['products' => $products]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->status === Product::STATUS_PUBLISHED, 404);

        $product->load('category:id,name,slug');

        return view('shop.product-detail', ['product' => $product]);
    }
}
