<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $tree = Cache::remember('categories:tree', 3600, function () {
            return Category::with('children:id,parent_id,name,slug,sort_order')
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->get(['id', 'parent_id', 'name', 'slug', 'sort_order']);
        });

        return response()->json(['data' => $tree]);
    }
}
