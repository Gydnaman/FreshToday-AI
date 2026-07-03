@extends('layouts.app')
@section('title', 'Checkout')
@section('content')
@php
    $err = session('checkout_error');
@endphp
<div class="min-h-screen bg-gray-50 py-12 px-4">
<div class="max-w-5xl mx-auto">

    <div class="mb-8">
        <h1 class="text-3xl font-extrabold text-gray-900 flex items-center gap-3">
            <i data-lucide="credit-card" class="w-8 h-8 text-green-600"></i> Checkout
        </h1>
        <p class="text-gray-500 mt-1">Complete your order</p>
    </div>

    @if($err)
    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 flex items-start gap-2">
        <i data-lucide="alert-triangle" class="w-5 h-5 flex-shrink-0 mt-0.5"></i>
        <div>
            <p class="font-semibold">結算失敗</p>
            <p>{{ $err }}</p>
        </div>
    </div>
    @endif

    {{-- Steps --}}
    <div class="flex items-center gap-2 mb-10">
        @foreach(['Delivery','Payment','Confirm'] as $i => $label)
        <div class="flex items-center gap-2">
            <div id="step-dot-{{ $i+1 }}" class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
                {{ $i===0 ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-500' }}">{{ $i+1 }}</div>
            <span class="text-sm font-medium {{ $i===0 ? 'text-green-600' : 'text-gray-400' }}" id="step-label-{{ $i+1 }}">{{ $label }}</span>
        </div>
        @if($i < 2)<div class="flex-1 h-0.5 bg-gray-200 mx-1" id="step-line-{{ $i+1 }}"></div>@endif
        @endforeach
    </div>

    <form id="checkout-form" method="POST" action="{{ route('web.checkout.place') }}">
        @csrf
        <input type="hidden" name="items" id="items-field">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- Left: Form Steps --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- 未登录拦截 --}}
                <div id="not-logged-in" class="hidden bg-yellow-50 border border-yellow-200 rounded-2xl p-6 text-center">
                    <i data-lucide="log-in" class="w-10 h-10 text-yellow-600 mx-auto mb-2"></i>
                    <p class="font-semibold text-gray-800">請先登入</p>
                    <p class="text-sm text-gray-500 mb-4">需要登入帳號才能結算。</p>
                    <a href="{{ url('/login?return=/checkout') }}" class="inline-block bg-green-600 text-white px-6 py-2 rounded-xl font-semibold hover:bg-green-700 transition">前往登入</a>
                </div>

                {{-- Step 1: Delivery --}}
                <div id="form-step-1" class="auth-required">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-bold text-gray-800 mb-5 flex items-center gap-2">
                            <i data-lucide="map-pin" class="w-5 h-5 text-green-600"></i> Delivery Address
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                <input name="shipping_address[name]" type="text" placeholder="Your full name" required
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                <input name="shipping_address[phone]" type="tel" placeholder="+852 XXXX XXXX" required
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition text-sm">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Address</label>
                                <input name="shipping_address[address]" type="text" placeholder="Flat, Floor, Building, Street, District" required
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
                                <select name="shipping_address[district]" required
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 transition text-sm">
                                    <option value="">Select district</option>
                                    @foreach(['Hong Kong Island','Kowloon','New Territories','Lantau Island'] as $d)
                                    <option>{{ $d }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Preferred Delivery Date</label>
                                <input name="shipping_address[date]" type="date"
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 transition text-sm">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Notes <span class="text-gray-400">(optional)</span></label>
                                <input name="shipping_address[notes]" type="text" placeholder="e.g. Leave at door, ring twice"
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 transition text-sm">
                            </div>
                        </div>
                        <p id="step1-err" class="text-red-500 text-sm mt-3 hidden">Please fill in Name, Phone, Address and District.</p>
                        <button type="button" onclick="goStep(2)" class="mt-6 w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white py-3.5 rounded-xl font-bold hover:from-green-600 hover:to-emerald-700 transition shadow-lg shadow-green-500/25">
                            Continue to Payment →
                        </button>
                    </div>
                </div>

                {{-- Step 2: Payment --}}
                <div id="form-step-2" class="hidden auth-required">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-bold text-gray-800 mb-5 flex items-center gap-2">
                            <i data-lucide="credit-card" class="w-5 h-5 text-green-600"></i> Payment Method
                        </h2>
                        <div class="bg-blue-50 text-blue-700 text-xs rounded-xl px-4 py-3 mb-4 flex items-center gap-2">
                            <i data-lucide="shield-check" class="w-4 h-4"></i>
                            沙箱環境：點擊「Place Order」會通過 <code>POST /checkout/place</code> 創建訂單並跳轉到 mock 支付頁（return_url 為 <code>/orders</code>）。
                        </div>
                        <div class="flex gap-3 mt-6">
                            <button type="button" onclick="goStep(1)" class="px-6 py-3 border border-gray-200 text-gray-600 rounded-xl hover:bg-gray-50 font-medium transition">← Back</button>
                            <button type="button" onclick="goStep(3)" class="flex-1 bg-gradient-to-r from-green-500 to-emerald-600 text-white py-3.5 rounded-xl font-bold hover:from-green-600 hover:to-emerald-700 transition shadow-lg shadow-green-500/25">
                                Review Order →
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Step 3: Confirm --}}
                <div id="form-step-3" class="hidden auth-required">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-bold text-gray-800 mb-5 flex items-center gap-2">
                            <i data-lucide="clipboard-check" class="w-5 h-5 text-green-600"></i> Review & Confirm
                        </h2>
                        <div id="confirm-details" class="space-y-4 text-sm mb-6"></div>
                        <div class="border-t border-gray-100 pt-5">
                            <button id="place-order-btn" type="submit"
                                class="w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white py-4 rounded-xl font-bold text-base hover:from-green-600 hover:to-emerald-700 transition shadow-xl shadow-green-500/30 flex items-center justify-center gap-2">
                                <i data-lucide="check-circle" class="w-5 h-5"></i> Place Order
                            </button>
                            <button type="button" onclick="goStep(2)" class="mt-3 w-full text-sm text-gray-400 hover:text-gray-600 transition">← Edit Payment</button>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Right: Order Summary --}}
            <div class="lg:col-span-1 auth-required" id="checkout-summary-col">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sticky top-6">
                    <h2 class="text-base font-bold text-gray-800 mb-4">Your Order</h2>
                    <div id="co-items" class="space-y-3 mb-4 max-h-56 overflow-y-auto text-sm text-gray-600"></div>
                    <div class="border-t border-gray-100 pt-4 space-y-2 text-sm">
                        <div class="flex justify-between"><span>Subtotal</span><span class="font-semibold">HK$<span id="co-sub">0</span></span></div>
                        <div class="flex justify-between"><span>Delivery</span><span class="font-semibold" id="co-del">HK$30</span></div>
                        <div class="flex justify-between font-bold text-gray-900 text-base border-t pt-2 mt-2">
                            <span>Total</span><span class="text-green-600">HK$<span id="co-total">0</span></span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>

</div>
</div>

<style>
.pay-method-card.selected { border-color: #10b981; background: #f0fdf4; }
.pay-method-card.selected i, .pay-method-card.selected span { color: #059669; }
</style>

<script>
$(document).ready(function() {
    const FREE_AT = 200, DELIVERY = 30;
    let currentStep = 1;

    // ── 登录态判断（session 模式：调 /api/me）─────────────────────
    fetch('/api/me', { credentials: 'include' })
        .then(r => {
            if (!r.ok) throw new Error('UNAUTHORIZED');
            return r.json();
        })
        .then(() => {
            // 已登录，拉购物车
            return fetchItems();
        })
        .catch(() => {
            $('#not-logged-in').removeClass('hidden');
            $('.auth-required').addClass('hidden');
            return [];
        })
        .then(items => {
            $('#items-field').val(JSON.stringify(items.map(i => ({ product_id: i.product_id, quantity: i.qty }))));
            buildSummary(items);
            lucide.createIcons();
        });

    // ── 拉购物车数据（session cookie 模式） ───────────────────────
    function fetchItems() {
        return fetch('/api/cart', { credentials: 'include' })
            .then(r => { if (!r.ok) throw new Error('UNAUTHORIZED'); return r.json(); })
            .then(d => {
                return (d.items || []).map(it => ({
                    product_id: it.product_id,
                    name: it.product.name,
                    price: parseFloat(it.product.price),
                    qty: it.quantity,
                    image: it.product.image,
                }));
            })
            .catch(() => []);
    }

    function buildSummary(items) {
        const subtotal = items.reduce((s, i) => s + i.price * i.qty, 0);
        const delivery = subtotal >= FREE_AT ? 0 : DELIVERY;
        const total    = subtotal + delivery;
        const coItems = $('#co-items');
        coItems.empty();
        if (items.length === 0) {
            coItems.append('<p class="text-gray-400 text-xs">購物車為空</p>');
        } else {
            items.forEach(i => {
                coItems.append(`<div class="flex justify-between"><span>${i.name} x${i.qty}</span><span>HK$${(i.price*i.qty).toFixed(2)}</span></div>`);
            });
        }
        $('#co-sub').text(subtotal.toFixed(2));
        $('#co-del').text(delivery === 0 ? '🎉 Free' : `HK$${delivery.toFixed(2)}`);
        $('#co-total').text(total.toFixed(2));
        return { subtotal, delivery, total };
    }

    fetchItems().then(items => {
        // 把 items 塞进 hidden field（提交时用）
        $('#items-field').val(JSON.stringify(items.map(i => ({ product_id: i.product_id, quantity: i.qty }))));
        buildSummary(items);
        lucide.createIcons();
    });

    // ── Step navigation ──────────────────────────────────────────────
    window.goStep = function(n) {
        // session 认证由后端 auth middleware 拦截，前端不需额外检查
        if (n === 2) {
            // 校验 delivery 表单
            const form = document.getElementById('checkout-form');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            $('#step1-err').addClass('hidden');
        }
        if (n === 3) {
            buildConfirm();
        }

        currentStep = n;
        for (let i = 1; i <= 3; i++) {
            $(`#form-step-${i}`).addClass('hidden');
        }
        $(`#form-step-${n}`).removeClass('hidden');
        updateStepUI(n);
        $('html,body').animate({scrollTop:0}, 200);
    };

    function updateStepUI(n) {
        [1,2,3].forEach(i => {
            const active = i < n;
            const current = i === n;
            $(`#step-dot-${i}`)
                .toggleClass('bg-green-600 text-white', active || current)
                .toggleClass('bg-gray-200 text-gray-500', !active && !current);
            $(`#step-label-${i}`)
                .toggleClass('text-green-600', active || current)
                .toggleClass('text-gray-400', !active && !current);
            if (i <= 2) {
                $(`#step-line-${i}`).toggleClass('bg-green-400', active).toggleClass('bg-gray-200', !active);
            }
        });
        lucide.createIcons();
    }

    function buildConfirm() {
        const addr = {
            name: $('input[name="shipping_address[name]"]').val(),
            phone: $('input[name="shipping_address[phone]"]').val(),
            address: $('input[name="shipping_address[address]"]').val(),
            district: $('select[name="shipping_address[district]"]').val(),
            date: $('input[name="shipping_address[date]"]').val() || 'ASAP',
        };
        $('#confirm-details').html(`
            <div class="bg-gray-50 rounded-xl p-4 space-y-1">
                <p class="font-semibold text-gray-700 mb-2">📦 Delivery</p>
                <p><span class="text-gray-400 w-24 inline-block">Name:</span>${addr.name}</p>
                <p><span class="text-gray-400 w-24 inline-block">Phone:</span>${addr.phone}</p>
                <p><span class="text-gray-400 w-24 inline-block">Address:</span>${addr.address}, ${addr.district}</p>
                <p><span class="text-gray-400 w-24 inline-block">Date:</span>${addr.date}</p>
            </div>
            <div class="bg-green-50 rounded-xl p-4 flex justify-between items-center">
                <span class="font-bold text-gray-800">Total Payable</span>
                <span class="text-xl font-extrabold text-green-600">HK$${$('#co-total').text()}</span>
            </div>
        `);
    }

    // ── Form submit → POST /checkout/place（session 认证，前端不需额外检查）──
    $('#checkout-form').on('submit', function(e) {
        const btn = $('#place-order-btn');
        btn.prop('disabled', true).html('<i data-lucide="loader" class="animate-spin w-5 h-5 mr-2"></i> Processing...');
        lucide.createIcons();
        // 不 e.preventDefault() — 让浏览器原生 submit 走 POST
    });

    lucide.createIcons();
});
</script>
@endsection
