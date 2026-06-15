<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Web 端结算页控制器（Sprint 1）
 *
 *  - GET  /checkout        显示结算页（要求已登录）
 *  - POST /checkout/place  接收表单 → 调 /api/orders → 调 /api/orders/{id}/pay
 *
 * 注意：浏览器侧走 Sanctum PAT（无 session），所以：
 *  1. /checkout 必须要求已登录，否则重定向到 /login?return=/checkout
 *  2. /checkout/place 不依赖 web session，但 form 必须带 _token 防 CSRF；
 *     业务上：表单提交时浏览器仍在同一台机器上，session 中有 token
 *     （Sanctum SPA 模式会写 cookie，但纯 PAT 模式下没有）。
 *     为简单起见，本控制器只做 CSRF + 表单校验，订单和支付通过 Http::withToken
 *     调内部 /api/* 端点 —— web 用户必须把 token 放进 form hidden field（gb_token），
 *     由前端 checkout.blade.php 在 form 提交时填入。
 *
 * 设计权衡：
 *  - 既然"不要修改 API 控制器"，最简单的方式是 web CheckoutController 作为 BFF
 *    调 /api/orders 与 /api/orders/{id}/pay 内部端点，复制 token 透传过去。
 *  - 若想完全本地事务，OrderService 是 public 的，但 cart-clear 还得调
 *    $user->cartItems()->delete()，需要 web auth。综合考虑走 BFF 最干净。
 */
class CheckoutController extends Controller
{
    /**
     * 显示结算页
     *
     * 由于浏览器用 PAT 而非 session，PHP 服务端拿不到 token。
     * 我们用 client-side 守卫：页面加载时 jQuery 读 localStorage 的 gb_token，
     * 没有则跳 /login?return=/checkout。
     * 服务端这里只准备一个干净的视图 + 订单摘要回填（前端做）。
     */
    public function show(Request $request): View
    {
        // 缺货检查：从 cart 渲染时再做（前端 + 库存由 OrderService guard）
        return view('checkout');
    }

    /**
     * 提交订单
     *
     * 接收：
     *  - gb_token: Sanctum PAT（前端从 localStorage 传回）
     *  - shipping_address: 表单提交（数组）
     *  - items: 由前端 cart 渲染 JSON 后 hidden 提交
     *  - coupon_code: 可选
     *
     * 业务：
     *  1. 校验输入
     *  2. POST /api/orders（透传 token）
     *  3. POST /api/orders/{id}/pay（透传 token，return_url 用本站 /orders）
     *  4. 302 → redirect_url（支付网关）— 沙箱下直接到 /orders 看到 mock 状态
     */
    public function place(Request $request): RedirectResponse
    {
        // ── 1. 取 token ────────────────────────────────────────────────
        $token = (string) $request->input('gb_token', '');
        if ($token === '') {
            return $this->errorRedirect('请先登录后再结算', '/login?return=/checkout');
        }

        // ── 2. 校验表单 ───────────────────────────────────────────────
        $data = $request->validate([
            'shipping_address.name'    => 'required|string|max:120',
            'shipping_address.phone'   => 'required|string|max:32',
            'shipping_address.address' => 'required|string|max:255',
            'shipping_address.district'=> 'required|string|max:64',
            'shipping_address.date'    => 'nullable|date',
            'shipping_address.notes'   => 'nullable|string|max:255',
            'items'                    => 'required|json',
            'coupon_code'              => 'nullable|string|max:32',
        ]);

        // ── 3. 解析 cart items ────────────────────────────────────────
        $items = json_decode($data['items'], true);
        if (! is_array($items) || count($items) === 0) {
            return $this->errorRedirect('购物车为空', '/cart');
        }

        // 标准化 items：[{product_id, quantity}]
        $normalized = [];
        foreach ($items as $row) {
            if (! isset($row['product_id'], $row['quantity'])) continue;
            $qty = (int) $row['quantity'];
            if ($qty <= 0) continue;
            $normalized[] = [
                'product_id' => (int) $row['product_id'],
                'quantity'   => $qty,
            ];
        }
        if (count($normalized) === 0) {
            return $this->errorRedirect('购物车为空', '/cart');
        }

        $shippingAddress = [
            'name'     => $data['shipping_address']['name'],
            'phone'    => $data['shipping_address']['phone'],
            'address'  => $data['shipping_address']['address'],
            'district' => $data['shipping_address']['district'],
            'date'     => $data['shipping_address']['date'] ?? null,
            'notes'    => $data['shipping_address']['notes'] ?? null,
            'currency' => 'HKD',
        ];

        // ── 4. 调 /api/orders（创建订单，OrderService 内部事务 + 库存预占） ──
        $apiBase = rtrim(config('app.url') ?: $request->getSchemeAndHttpHost(), '/');
        $orderResp = Http::withToken($token)
            ->acceptJson()
            ->post($apiBase . '/api/orders', [
                'items'            => $normalized,
                'shipping_address' => $shippingAddress,
                'coupon_code'      => $data['coupon_code'] ?? null,
            ]);

        if (! $orderResp->successful()) {
            $msg = $this->extractError($orderResp, '订单创建失败');
            return $this->errorRedirect($msg, '/cart');
        }
        $orderId = (int) ($orderResp->json('order.id') ?? 0);
        if ($orderId <= 0) {
            return $this->errorRedirect('订单创建失败：响应缺少 id', '/cart');
        }

        // ── 5. 调 /api/orders/{id}/pay（创建支付意图） ──────────────────
        $returnUrl = $apiBase . '/orders';
        $payResp = Http::withToken($token)
            ->acceptJson()
            ->post($apiBase . "/api/orders/{$orderId}/pay", [
                'provider'   => 'stripe',
                'return_url' => $returnUrl,
            ]);

        if (! $payResp->successful()) {
            $msg = $this->extractError($payResp, '支付意图创建失败');
            return $this->errorRedirect("订单 #{$orderId} 已创建，但 " . $msg, '/orders');
        }

        // ── 6. 302 到支付网关（或沙箱 mock URL） ───────────────────────
        $redirect = (string) ($payResp->json('redirect_url') ?? $returnUrl);
        return redirect()->away($redirect);
    }

    /**
     * 把错误写到 session flash + 重定向回 /checkout
     */
    private function errorRedirect(string $message, string $to): RedirectResponse
    {
        session()->flash('checkout_error', $message);
        return redirect()->to($to);
    }

    /**
     * 从 API 响应中提取 error.message
     */
    private function extractError(\Illuminate\Http\Client\Response $resp, string $fallback): string
    {
        $err = $resp->json('error');
        if (is_array($err)) {
            return (string) ($err['message'] ?? $fallback);
        }
        return $fallback . '（HTTP ' . $resp->status() . '）';
    }
}
