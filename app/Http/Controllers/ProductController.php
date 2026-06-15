<?php

namespace App\Http\Controllers;

use App\Models\Product;

/**
 * Web 端产品目录页控制器（/catalog）
 *
 * 直接读 Product Eloquent，与 Api\ProductController 走同一条数据源，
 * 保证 web / 移动端数据一致。早期版本硬编码 4 条 mock 商品，已废弃。
 *
 * 注意：本控制器只用于 web Blade 渲染；JSON API 调用请走 /api/products（App\Http\Controllers\Api）。
 */
class ProductController extends Controller
{
    public function index()
    {
        $products = Product::query()
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        return view('catalog', ['products' => $products]);
    }
}
