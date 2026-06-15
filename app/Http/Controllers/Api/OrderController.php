<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\GuardFailedException;
use App\Exceptions\InvalidTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly PaymentService $paymentService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'                       => 'required|array|min:1',
            'items.*.product_id'          => 'required|integer|exists:products,id',
            'items.*.quantity'            => 'required|integer|min:1',
            'shipping_address'            => 'required|array',
            'coupon_code'                 => 'nullable|string|max:32',
            'user_subscription_id'        => 'nullable|integer|exists:user_subscriptions,id',
        ]);

        try {
            $order = $this->orderService->createOrder(
                user: $request->user(),
                items: $data['items'],
                shippingAddress: $data['shipping_address'],
                couponCode: $data['coupon_code'] ?? null,
                userSubscriptionId: $data['user_subscription_id'] ?? null,
            );

            // 清空购物车
            $request->user()->cartItems()->delete();

            return response()->json(['order' => $order->load('products')], 201);
        } catch (GuardFailedException $e) {
            $payload = $e->toApiPayload();
            return response()->json(['error' => $payload], $payload['http']);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status'   => 'nullable|string',
            'page'     => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $q = Order::with('products')->where('user_id', $request->user()->id);
        if (! empty($data['status'])) $q->where('status', $data['status']);
        $orders = $q->orderBy('created_at', 'desc')->paginate($data['per_page'] ?? 20);

        return response()->json([
            'data' => $orders->items(),
            'meta' => ['pagination' => [
                'total'        => $orders->total(),
                'per_page'     => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
            ]],
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id && ! ($request->user()->is_admin ?? false)) {
            return response()->json(['error' => ['code' => 'NOT_OWNER', 'message' => '无权查看']], 403);
        }
        return response()->json(['data' => $order->load(['products', 'payments', 'statusLogs'])]);
    }

    public function pay(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['error' => ['code' => 'NOT_OWNER', 'message' => '无权操作']], 403);
        }
        $data = $request->validate([
            'provider'   => 'required|in:stripe,payme,alipay_hk',
            'return_url' => 'required|url',
        ]);

        try {
            $payment = $this->paymentService->createIntent($order, $data['provider'], $data['return_url']);
            return response()->json([
                'payment'      => $payment,
                'redirect_url' => $data['return_url'] . '?payment_id=' . $payment->id,
            ]);
        } catch (InvalidTransitionException $e) {
            return response()->json(['error' => $e->toApiPayload()], 422);
        } catch (GuardFailedException $e) {
            $payload = $e->toApiPayload();
            return response()->json(['error' => $payload], $payload['http']);
        }
    }
}
