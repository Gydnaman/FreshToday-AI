@extends('layouts.app')

@section('title', 'My Cart')

@section('content')
<div class="min-h-screen bg-gray-50 py-12 px-4">
<div class="max-w-5xl mx-auto">

    {{-- Page Header --}}
    <div class="mb-8">
        <h1 class="text-3xl font-extrabold text-gray-900 flex items-center gap-3">
            <i data-lucide="shopping-cart" class="w-8 h-8 text-green-600"></i>
            My Cart
        </h1>
        <p class="text-gray-500 mt-1">Review your items before checkout</p>
    </div>

    {{-- Empty State (shown when cart is empty) --}}
    <div id="empty-state" class="hidden text-center py-24">
        <div class="bg-green-50 rounded-full w-24 h-24 flex items-center justify-center mx-auto mb-6">
            <i data-lucide="shopping-basket" class="w-10 h-10 text-green-400"></i>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-2">Your cart is empty</h2>
        <p class="text-gray-500 mb-8">Looks like you haven't added anything yet. Browse our fresh produce!</p>
        <a href="{{ url('/catalog') }}" class="inline-flex items-center gap-2 bg-green-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-green-700 transition shadow-lg shadow-green-600/25">
            <i data-lucide="shopping-basket" class="w-5 h-5"></i> Shop Now
        </a>
    </div>

    {{-- Cart Content (shown when cart has items) --}}
    <div id="cart-content" class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {{-- Left: Item List --}}
        <div class="lg:col-span-2 space-y-4" id="cart-items-list">
            {{-- Items injected by JS --}}
        </div>

        {{-- Right: Order Summary --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sticky top-6">
                <h2 class="text-lg font-bold text-gray-800 mb-5 flex items-center gap-2">
                    <i data-lucide="receipt" class="w-5 h-5 text-green-600"></i>
                    Order Summary
                </h2>

                <div class="space-y-3 text-sm text-gray-600 mb-5">
                    <div class="flex justify-between">
                        <span>Subtotal (<span id="summary-count">0</span> items)</span>
                        <span class="font-semibold text-gray-900">HK$<span id="summary-subtotal">0.00</span></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="flex items-center gap-1">
                            <i data-lucide="truck" class="w-3.5 h-3.5 text-green-500"></i> Delivery Fee
                        </span>
                        <span id="summary-delivery" class="font-semibold text-gray-900">HK$30.00</span>
                    </div>
                    <div class="flex justify-between text-green-600">
                        <span class="flex items-center gap-1">
                            <i data-lucide="leaf" class="w-3.5 h-3.5"></i> Carbon Offset
                        </span>
                        <span class="font-semibold">Included</span>
                    </div>
                    <div class="border-t border-gray-100 pt-3 flex justify-between text-base font-bold text-gray-900">
                        <span>Total</span>
                        <span class="text-green-600">HK$<span id="summary-total">0.00</span></span>
                    </div>
                </div>

                {{-- Free delivery progress --}}
                <div class="mb-5" id="free-delivery-bar">
                    <p class="text-xs text-gray-500 mb-1.5">
                        <span id="free-delivery-msg">Add HK$<span id="free-delivery-remaining">200</span> more for free delivery!</span>
                    </p>
                    <div class="w-full bg-gray-100 rounded-full h-2">
                        <div id="free-delivery-progress" class="bg-gradient-to-r from-green-400 to-emerald-500 h-2 rounded-full transition-all duration-500" style="width:0%"></div>
                    </div>
                </div>

                {{-- Eco badge --}}
                <div class="bg-green-50 rounded-xl px-4 py-3 mb-5 flex items-center gap-2 text-xs text-green-700 font-medium">
                    <i data-lucide="leaf" class="w-4 h-4"></i>
                    All products sourced from HK & GBA organic farms
                </div>

                <a id="checkout-btn" href="{{ url('/checkout') }}"
                    class="block w-full text-center bg-gradient-to-r from-green-500 to-emerald-600 text-white py-4 rounded-xl font-bold text-base hover:from-green-600 hover:to-emerald-700 transition shadow-lg shadow-green-500/30">
                    Proceed to Checkout →
                </a>

                <a href="{{ url('/catalog') }}" class="block text-center text-sm text-gray-400 hover:text-green-600 mt-3 transition">
                    ← Continue Shopping
                </a>
            </div>
        </div>
    </div>

</div>
</div>

<style>
.cart-item { animation: fadeInUp 0.3s ease; }
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}
.qty-btn { transition: background 0.15s; }
.qty-btn:hover { background: #f0fdf4; }
</style>

<script>
$(document).ready(function() {
    const DELIVERY_FEE     = 30;
    const FREE_DELIVERY_AT = 200;

    // ── Helpers ──────────────────────────────────────────────────────────────
    function getCart() {
        return JSON.parse(localStorage.getItem('greenbite_cart') || '[]');
    }
    function saveCart(cart) {
        localStorage.setItem('greenbite_cart', JSON.stringify(cart));
        $('#cart-count').text(cart.length); // update navbar badge
    }

    // Aggregate: merge duplicate items into { name, price, qty }
    function aggregate(cart) {
        const map = {};
        cart.forEach(item => {
            const key = item.name;
            if (!map[key]) map[key] = { name: item.name, price: parseFloat(item.price), qty: 0 };
            map[key].qty++;
        });
        return Object.values(map);
    }

    // Flatten aggregated back to raw cart array
    function flatten(agg) {
        const raw = [];
        agg.forEach(item => {
            for (let i = 0; i < item.qty; i++) {
                raw.push({ name: item.name, price: item.price });
            }
        });
        return raw;
    }

    // Icon mapping for products
        function productIcon(name) {
            const n = name.toLowerCase();
        if (n.includes('kale') || n.includes('spinach')) return 'salad';
        if (n.includes('egg')) return 'egg';
        if (n.includes('tomato')) return 'cherry';
        if (n.includes('fruit')) return 'apple';
        if (n.includes('tofu')) return 'box';
        if (n.includes('rice')) return 'wheat';
        return 'package';
    }

    // ── Render ────────────────────────────────────────────────────────────────
    function render() {
        const raw  = getCart();
        const agg  = aggregate(raw);
        const list = $('#cart-items-list');
        list.empty();

        if (agg.length === 0) {
            $('#empty-state').removeClass('hidden');
            $('#cart-content').addClass('hidden');
            return;
        }

        $('#empty-state').addClass('hidden');
        $('#cart-content').removeClass('hidden');

        agg.forEach((item, idx) => {
            const lineTotal = (item.price * item.qty).toFixed(2);
            list.append(`
            <div class="cart-item bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-4" data-name="${item.name}">
                <div class="w-14 h-14 bg-green-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i data-lucide="${productIcon(item.name)}" class="w-7 h-7 text-green-500"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-gray-900 truncate">${item.name}</h3>
                    <p class="text-sm text-gray-400 mt-0.5">HK$${item.price.toFixed(2)} each</p>
                    <div class="flex items-center gap-3 mt-2">
                        <div class="inline-flex items-center border border-gray-200 rounded-lg overflow-hidden">
                            <button class="qty-btn w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-50" onclick="changeQty('${item.name}', -1)">
                                <i data-lucide="minus" class="w-3.5 h-3.5"></i>
                            </button>
                            <span class="w-8 text-center font-semibold text-sm text-gray-800">${item.qty}</span>
                            <button class="qty-btn w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-50" onclick="changeQty('${item.name}', 1)">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                        <button class="text-red-400 hover:text-red-600 transition text-xs font-medium flex items-center gap-1" onclick="removeItem('${item.name}')">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Remove
                        </button>
                    </div>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="font-bold text-green-600 text-lg">HK$${lineTotal}</p>
                </div>
            </div>`);
        });

        lucide.createIcons();
        updateSummary(agg);
    }

    function updateSummary(agg) {
        const subtotal   = agg.reduce((s, i) => s + i.price * i.qty, 0);
        const itemCount  = agg.reduce((s, i) => s + i.qty, 0);
        const delivery   = subtotal >= FREE_DELIVERY_AT ? 0 : DELIVERY_FEE;
        const total      = subtotal + delivery;
        const remaining  = Math.max(0, FREE_DELIVERY_AT - subtotal);
        const pct        = Math.min(100, (subtotal / FREE_DELIVERY_AT) * 100);

        $('#summary-count').text(itemCount);
        $('#summary-subtotal').text(subtotal.toFixed(2));
        $('#summary-delivery').text(delivery === 0 ? '🎉 Free' : `HK$${delivery.toFixed(2)}`);
        $('#summary-total').text(total.toFixed(2));
        $('#free-delivery-progress').css('width', pct + '%');

        if (remaining === 0) {
            $('#free-delivery-msg').html('<span class="text-green-600 font-semibold">🎉 You\'ve unlocked free delivery!</span>');
        } else {
            $('#free-delivery-msg').html(`Add <strong>HK$${remaining.toFixed(2)}</strong> more for free delivery!`);
        }

        // Pass total to checkout link via query param
        $('#checkout-btn').attr('href', `/checkout?total=${total.toFixed(2)}&items=${itemCount}`);
    }

    // ── Actions ───────────────────────────────────────────────────────────────
    window.changeQty = function(name, delta) {
        const agg = aggregate(getCart());
        const item = agg.find(i => i.name === name);
        if (!item) return;
        item.qty = Math.max(0, item.qty + delta);
        if (item.qty === 0) {
            agg.splice(agg.indexOf(item), 1);
        }
        saveCart(flatten(agg));
        render();
    };

    window.removeItem = function(name) {
        const agg = aggregate(getCart()).filter(i => i.name !== name);
        saveCart(flatten(agg));
        render();
    };

    // Initial render
    render();
});
</script>
@endsection
