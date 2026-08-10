<?php

namespace App\Http\Controllers\Web;

use App\Enums\Currency;
use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Web 端结算页控制器（Sprint 1 + I-3 修复）
 *
 *  - GET  /checkout        显示结算页（session 认证）
 *  - POST /checkout/place  接收表单 → 直接调 OrderService + PaymentService
 *
 * I-3 修复（2026-07-03）：
 * - 从 BFF HTTP 自调用改为直接调 Service（消除 Http::withToken）
 * - 从 PAT hidden field 改为 session 认证（auth middleware）
 * - 不再需要 gb_token input
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly PaymentService $paymentService,
    ) {}

    public function show(Request $request): View
    {
        return view('shop.checkout');
    }

    public function place(Request $request): RedirectResponse
    {
        // Web 表单把 items 以 JSON 字符串塞在单个 hidden field 提交，先 decode 成数组再验证
        if (is_string($request->input('items'))) {
            $request->merge(['items' => json_decode($request->input('items'), true) ?? []]);
        }

        $data = $request->validate([
            'shipping_address.name' => 'required|string|max:120',
            'shipping_address.phone' => 'required|string|max:32',
            'shipping_address.address' => 'required|string|max:255',
            'shipping_address.district' => 'required|string|max:64',
            'shipping_address.date' => 'nullable|date_format:Y-m-d|after_or_equal:today',
            'shipping_address.notes' => 'nullable|string|max:255',
            'payment_method' => 'required|in:sandbox_card',
            'items' => 'required|array',
            'coupon_code' => 'nullable|string|max:32',
        ]);

        $items = $data['items']; // 已验证为 array
        if (count($items) === 0) {
            return $this->errorRedirect('购物车为空', '/cart');
        }

        $normalized = [];
        foreach ($items as $row) {
            if (! isset($row['product_id'], $row['quantity'])) {
                continue;
            }
            $qty = (int) $row['quantity'];
            if ($qty <= 0 || $qty > 999) {
                continue;
            }
            $normalized[] = [
                'product_id' => (int) $row['product_id'],
                'quantity' => $qty,
            ];
        }
        if (count($normalized) === 0) {
            return $this->errorRedirect('购物车为空', '/cart');
        }

        $shippingAddress = [
            'name' => $data['shipping_address']['name'],
            'phone' => $data['shipping_address']['phone'],
            'address' => $data['shipping_address']['address'],
            'district' => $data['shipping_address']['district'],
            'date' => $data['shipping_address']['date'] ?? null,
            'notes' => $data['shipping_address']['notes'] ?? null,
            'currency' => Currency::HKD->value,
        ];

        try {
            $order = DB::transaction(function () use ($request, $normalized, $shippingAddress, $data) {
                $order = $this->orderService->createOrder(
                    user: $request->user(),
                    items: $normalized,
                    shippingAddress: $shippingAddress,
                    couponCode: $data['coupon_code'] ?? null,
                );

                if ((float) $order->total_price < 200) {
                    $order->increment('total_price', 30);
                    $order->refresh();
                }

                $returnUrl = rtrim(config('app.url') ?: $request->getSchemeAndHttpHost(), '/').'/orders';
                $payment = $this->paymentService->createIntent($order, 'sandbox_card', $returnUrl);
                $this->paymentService->completeSandboxPayment($payment);
                $request->user()->cartItems()->delete();

                return $order->fresh();
            });

            return redirect()->to('/orders?payment=success&order='.urlencode($order->order_no));
        } catch (\Throwable $e) {
            $msg = method_exists($e, 'toApiPayload')
                ? ($e->toApiPayload()['message'] ?? '订单创建失败')
                : '订单创建失败：'.$e->getMessage();

            return $this->errorRedirect($msg, '/cart');
        }
    }

    private function errorRedirect(string $message, string $to): RedirectResponse
    {
        session()->flash('checkout_error', $message);

        return redirect()->to($to);
    }
}
