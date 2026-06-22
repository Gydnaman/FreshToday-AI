<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|integer|exists:categories,id',
            'is_organic' => 'nullable|boolean',
            'q' => 'nullable|string|max:100',
            'sort' => 'nullable|in:price_asc,price_desc,newest',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $cacheKey = 'products:list:'.md5(json_encode($validated));
        $products = Cache::remember($cacheKey, 300, function () use ($validated) {
            $q = Product::with('category:id,name,slug')
                ->where('status', Product::STATUS_PUBLISHED); // admin draft / archived 不可见
            if (! empty($validated['category_id'])) {
                $q->where('category_id', $validated['category_id']);
            }
            if (isset($validated['is_organic'])) {
                $q->where('is_organic', $validated['is_organic']);
            }
            if (! empty($validated['q'])) {
                $q->where('name', 'like', '%'.$validated['q'].'%');
            }

            match ($validated['sort'] ?? null) {
                'price_asc' => $q->orderBy('price', 'asc'),
                'price_desc' => $q->orderBy('price', 'desc'),
                'newest' => $q->orderBy('created_at', 'desc'),
                default => $q->orderBy('id', 'asc'),
            };

            return $q->paginate($validated['per_page'] ?? 20);
        });

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'pagination' => [
                    'total' => $products->total(),
                    'per_page' => $products->perPage(),
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                ],
            ],
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load('category:id,name,slug');

        return response()->json(['data' => $product]);
    }
}
