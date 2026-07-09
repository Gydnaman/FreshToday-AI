<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin 产品管理控制器
 *
 * 入口：/admin/products（auth 中间件，登录即可访问）
 * 普通用户只能管理自己的商品，管理员可管理全部（ProductPolicy 控制）
 * 创建时默认 draft 状态
 */
class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $q = Product::with('category:id,name')
            ->orderByDesc('updated_at');

        // 普通用户只看自己的，管理员看全部
        if (! $request->user()->is_admin) {
            $q->where('user_id', $request->user()->id);
        }

        return view('admin.products.index', [
            'products' => $q->paginate(20),
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
        $data['status'] = 'draft';
        $data['user_id'] = $request->user()->id;

        Product::create($data);

        $this->invalidateProductCache();

        return redirect()
            ->route('admin.products.index')
            ->with('success', '产品已创建（草稿状态，需手动上架）');
    }

    public function edit(Product $product): View
    {
        abort_if(! $this->canManage($product), 403);

        $categories = Category::orderBy('sort_order')->orderBy('name')->get();

        return view('admin.products.edit', [
            'product' => $product,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        abort_if(! $this->canManage($product), 403);

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

        $this->invalidateProductCache();

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

    /**
     * 产品变更时递增缓存版本号，使所有 products:list:* 缓存失效（靠 5min TTL 兜底）
     */
    private function invalidateProductCache(): void
    {
        Cache::increment('products:cache_version');
    }

    /**
     * admin 或产品所有者可以管理该产品
     */
    private function canManage(Product $product): bool
    {
        $user = request()->user();

        return $user->is_admin || (int) $product->user_id === $user->id;
    }
}
