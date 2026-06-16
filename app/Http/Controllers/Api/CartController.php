<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = CartItem::with('product:id,name,price,image,stock')
            ->where('user_id', $request->user()->id)
            ->get();
        $total = $items->sum(fn ($i) => $i->subtotal());

        return response()->json([
            'items' => $items,
            'total' => $total,
            'item_count' => $items->sum('quantity'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);
        $product = Product::findOrFail($data['product_id']);
        if (! $product->hasStock($data['quantity'])) {
            return response()->json([
                'error' => ['code' => 'OUT_OF_STOCK', 'message' => '库存不足'],
            ], 409);
        }

        $item = CartItem::firstOrNew([
            'user_id' => $request->user()->id,
            'product_id' => $data['product_id'],
        ]);
        $item->quantity = ($item->exists ? $item->quantity : 0) + $data['quantity'];
        $item->save();

        return response()->json(['item' => $item->load('product:id,name,price,image,stock')], 201);
    }

    public function update(Request $request, CartItem $item): JsonResponse
    {
        $this->authorizeOwner($request, $item);
        $data = $request->validate(['quantity' => 'required|integer|min:0']);
        if ($data['quantity'] === 0) {
            return $this->destroy($request, $item);
        }
        $item->update(['quantity' => $data['quantity']]);

        return response()->json(['item' => $item->fresh('product')]);
    }

    public function destroy(Request $request, CartItem $item): JsonResponse
    {
        $this->authorizeOwner($request, $item);
        $item->delete();

        return response()->json(null, 204);
    }

    private function authorizeOwner(Request $request, CartItem $item): void
    {
        if ($item->user_id !== $request->user()->id) {
            abort(403, 'NOT_OWNER');
        }
    }
}
