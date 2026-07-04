<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin 产品管理控制器（最小版：列表 + 创建 + 图片上传）
 *
 * 入口：/admin/products
 * 守卫：IsAdmin middleware
 * 状态：创建时默认 draft（不在公开 /api/products 显示，避免误上架未完成品）
 */
class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::with('category:id,name')
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('admin.products.index', [
            'products' => $products,
        ]);
    }

    public function create(): View
    {
        $categories = Category::orderBy('sort_order')->orderBy('name')->get();

        return view('admin.products.create', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|integer|exists:categories,id',
            'price' => 'required|numeric|min:0|max:99999.99',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string|max:5000',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:512',
            'is_organic' => 'nullable|boolean',
            'origin' => 'nullable|string|max:64',
            'carbon_footprint' => 'nullable|numeric|min:0|max:99.999',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $this->storeImage($request->file('image'));
        }

        $data['is_organic'] = (bool) ($data['is_organic'] ?? false);
        // 最小版：创建后默认 draft，避免误上架
        $data['status'] = 'draft';

        Product::create($data);

        return redirect()
            ->route('admin.products.index')
            ->with('success', '产品已创建（草稿状态，需手动上架）');
    }

    public function edit(Product $product): View
    {
        $categories = Category::orderBy('sort_order')->orderBy('name')->get();

        return view('admin.products.edit', [
            'product' => $product,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|integer|exists:categories,id',
            'price' => 'required|numeric|min:0|max:99999.99',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string|max:5000',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:512',
            'is_organic' => 'nullable|boolean',
            'origin' => 'nullable|string|max:64',
            'carbon_footprint' => 'nullable|numeric|min:0|max:99.999',
            'status' => 'nullable|in:draft,published,archived',
        ]);

        if ($request->hasFile('image')) {
            // 删除旧图
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $this->storeImage($request->file('image'));
        }

        $data['is_organic'] = (bool) ($data['is_organic'] ?? false);

        $product->update($data);

        return redirect()
            ->route('admin.products.index')
            ->with('success', "产品「{$product->fresh()->name}」已更新");
    }

    /**
     * 存图到 storage/app/public/products/{Y/m/d}/{ulid}.{ext}
     */
    private function storeImage(UploadedFile $file): string
    {
        $subdir = 'products/'.now()->format('Y/m/d');
        $name = (string) Str::ulid().'.'.$file->getClientOriginalExtension();
        $file->storeAs($subdir, $name, 'public');

        return $subdir.'/'.$name;
    }
}
