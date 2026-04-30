@extends('layouts.app')
@section('title', 'Checkout')
@section('content')
<div class="min-h-screen bg-gray-50 py-12 px-4">
<div class="max-w-5xl mx-auto">

    <div class="mb-8">
        <h1 class="text-3xl font-extrabold text-gray-900 flex items-center gap-3">
            <i data-lucide="credit-card" class="w-8 h-8 text-green-600"></i> Checkout
        </h1>
        <p class="text-gray-500 mt-1">Complete your order</p>
    </div>

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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {{-- Left: Form Steps --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Step 1: Delivery --}}
            <div id="form-step-1">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-lg font-bold text-gray-800 mb-5 flex items-center gap-2">
                        <i data-lucide="map-pin" class="w-5 h-5 text-green-600"></i> Delivery Address
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input id="d-name" type="text" placeholder="Your full name"
                                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                            <input id="d-phone" type="tel" placeholder="+852 XXXX XXXX"
                                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Address</label>
                            <input id="d-address" type="text" placeholder="Flat, Floor, Building, Street, District"
                                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
                            <select id="d-district" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 transition text-sm">
                                <option value="">Select district</option>
                                @foreach(['Hong Kong Island','Kowloon','New Territories','Lantau Island'] as $d)
                                <option>{{ $d }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Preferred Delivery Date</label>
                            <input id="d-date" type="date"
                                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 transition text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Notes <span class="text-gray-400">(optional)</span></label>
                            <input id="d-notes" type="text" placeholder="e.g. Leave at door, ring twice"
                                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 transition text-sm">
                        </div>
                    </div>
                    <p id="step1-err" class="text-red-500 text-sm mt-3 hidden">Please fill in Name, Phone, Address and District.</p>
                    <button onclick="goStep(2)" class="mt-6 w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white py-3.5 rounded-xl font-bold hover:from-green-600 hover:to-emerald-700 transition shadow-lg shadow-green-500/25">
                        Continue to Payment →
                    </button>
                </div>
            </div>

            {{-- Step 2: Payment --}}
            <div id="form-step-2" class="hidden">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-lg font-bold text-gray-800 mb-5 flex items-center gap-2">
                        <i data-lucide="credit-card" class="w-5 h-5 text-green-600"></i> Payment Method
                    </h2>

                    {{-- Method selector --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
                        @foreach([['card','credit-card','Card'],['fps','smartphone','FPS / PayMe'],['payme','wallet','PayPal / Alipay']] as $m)
                        <label class="pay-method-card flex flex-col items-center gap-2 p-4 border-2 border-gray-100 rounded-xl cursor-pointer hover:border-green-400 hover:bg-green-50 transition text-center" data-method="{{ $m[0] }}">
                            <i data-lucide="{{ $m[1] }}" class="w-6 h-6 text-gray-400"></i>
                            <span class="text-sm font-semibold text-gray-700">{{ $m[2] }}</span>
                        </label>
                        @endforeach
                    </div>

                    {{-- Card fields --}}
                    <div id="pay-card-fields" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Card Number</label>
                            <input id="p-card" type="text" placeholder="1234 5678 9012 3456" maxlength="19"
                                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 transition text-sm font-mono"
                                oninput="this.value=this.value.replace(/\D/g,'').replace(/(.{4})/g,'$1 ').trim()">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Expiry</label>
                                <input id="p-expiry" type="text" placeholder="MM/YY" maxlength="5"
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 transition text-sm font-mono"
                                    oninput="this.value=this.value.replace(/\D/g,'').replace(/(\d{2})(\d)/,'$1/$2')">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">CVV</label>
                                <input id="p-cvv" type="password" placeholder="•••" maxlength="3"
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 transition text-sm font-mono">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name on Card</label>
                            <input id="p-name" type="text" placeholder="As printed on card"
                                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 transition text-sm">
                        </div>
                    </div>

                    {{-- FPS / Alternative --}}
                    <div id="pay-alt-fields" class="hidden text-center py-6">
                        <div class="bg-gray-50 rounded-2xl p-6 inline-block">
                            <div class="w-28 h-28 bg-white border border-gray-200 rounded-xl flex items-center justify-center mx-auto mb-3">
                                <i data-lucide="qr-code" class="w-16 h-16 text-green-600"></i>
                            </div>
                            <p class="text-sm text-gray-500">Scan QR code with your banking app</p>
                            <p class="text-xs text-gray-400 mt-1">FPS ID: 123456789</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 bg-blue-50 text-blue-700 text-xs rounded-xl px-4 py-3 mt-4">
                        <i data-lucide="shield-check" class="w-4 h-4"></i>
                        Your payment is secured with 256-bit SSL encryption
                    </div>

                    <p id="step2-err" class="text-red-500 text-sm mt-3 hidden">Please complete card details.</p>
                    <div class="flex gap-3 mt-6">
                        <button onclick="goStep(1)" class="px-6 py-3 border border-gray-200 text-gray-600 rounded-xl hover:bg-gray-50 font-medium transition">← Back</button>
                        <button onclick="goStep(3)" class="flex-1 bg-gradient-to-r from-green-500 to-emerald-600 text-white py-3.5 rounded-xl font-bold hover:from-green-600 hover:to-emerald-700 transition shadow-lg shadow-green-500/25">
                            Review Order →
                        </button>
                    </div>
                </div>
            </div>

            {{-- Step 3: Confirm --}}
            <div id="form-step-3" class="hidden">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-lg font-bold text-gray-800 mb-5 flex items-center gap-2">
                        <i data-lucide="clipboard-check" class="w-5 h-5 text-green-600"></i> Review & Confirm
                    </h2>
                    <div id="confirm-details" class="space-y-4 text-sm mb-6"></div>
                    <div class="border-t border-gray-100 pt-5">
                        <button id="place-order-btn" onclick="placeOrder()"
                            class="w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white py-4 rounded-xl font-bold text-base hover:from-green-600 hover:to-emerald-700 transition shadow-xl shadow-green-500/30 flex items-center justify-center gap-2">
                            <i data-lucide="check-circle" class="w-5 h-5"></i> Place Order
                        </button>
                        <button onclick="goStep(2)" class="mt-3 w-full text-sm text-gray-400 hover:text-gray-600 transition">← Edit Payment</button>
                    </div>
                </div>
            </div>

            {{-- Step 4: Success --}}
            <div id="form-step-4" class="hidden">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
                    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i data-lucide="check-circle" class="w-10 h-10 text-green-600"></i>
                    </div>
                    <h2 class="text-2xl font-extrabold text-gray-900 mb-2">Order Placed! 🎉</h2>
                    <p class="text-gray-500 mb-1">Thank you for supporting local Hong Kong farms.</p>
                    <p class="text-xs text-gray-400 mb-8">Order ID: <span id="order-id" class="font-mono font-semibold"></span></p>
                    <div class="flex flex-col sm:flex-row gap-3 justify-center">
                        <a href="{{ url('/orders') }}" class="bg-green-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-green-700 transition">View My Orders</a>
                        <a href="{{ url('/catalog') }}" class="border border-gray-200 text-gray-600 px-8 py-3 rounded-xl font-bold hover:bg-gray-50 transition">Continue Shopping</a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right: Order Summary --}}
        <div class="lg:col-span-1" id="checkout-summary-col">
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
</div>
</div>

<style>
.pay-method-card.selected { border-color: #10b981; background: #f0fdf4; }
.pay-method-card.selected i, .pay-method-card.selected span { color: #059669; }
</style>

<script>
$(document).ready(function() {
    const FREE_AT = 200, DELIVERY = 30;
    let currentStep = 1, selectedMethod = 'card';

    // ── Init: read cart from localStorage ────────────────────────────────────
    function getCart() { return JSON.parse(localStorage.getItem('greenbite_cart') || '[]'); }
    function aggregate(cart) {
        const map = {};
        cart.forEach(i => {
            if (!map[i.name]) map[i.name] = { name: i.name, price: parseFloat(i.price), qty: 0 };
            map[i.name].qty++;
        });
        return Object.values(map);
    }

    function initSummary() {
        const agg  = aggregate(getCart());
        // Also read from URL params as fallback
        const params   = new URLSearchParams(window.location.search);
        const urlTotal = parseFloat(params.get('total') || 0);

        const subtotal = agg.length > 0
            ? agg.reduce((s, i) => s + i.price * i.qty, 0)
            : urlTotal;
        const delivery = subtotal >= FREE_AT ? 0 : DELIVERY;
        const total    = subtotal + delivery;

        const coItems = $('#co-items');
        coItems.empty();
        if (agg.length > 0) {
            agg.forEach(i => {
                coItems.append(`<div class="flex justify-between"><span>${i.name} x${i.qty}</span><span>HK$${(i.price*i.qty).toFixed(2)}</span></div>`);
            });
        } else {
            coItems.append(`<p class="text-gray-400 text-xs">Items from your cart</p>`);
        }
        $('#co-sub').text(subtotal.toFixed(2));
        $('#co-del').text(delivery === 0 ? '🎉 Free' : `HK$${delivery.toFixed(2)}`);
        $('#co-total').text(total.toFixed(2));

        return { subtotal, delivery, total, agg };
    }

    const orderData = initSummary();

    // ── Payment method tabs ────────────────────────────────────────────────
    $('.pay-method-card').first().addClass('selected');
    $(document).on('click', '.pay-method-card', function() {
        $('.pay-method-card').removeClass('selected');
        $(this).addClass('selected');
        selectedMethod = $(this).data('method');
        if (selectedMethod === 'card') {
            $('#pay-card-fields').removeClass('hidden');
            $('#pay-alt-fields').addClass('hidden');
        } else {
            $('#pay-card-fields').addClass('hidden');
            $('#pay-alt-fields').removeClass('hidden');
        }
    });

    // ── Step navigation ────────────────────────────────────────────────────
    window.goStep = function(n) {
        // Validate
        if (n === 2) {
            const name = $('#d-name').val().trim();
            const phone = $('#d-phone').val().trim();
            const addr  = $('#d-address').val().trim();
            const dist  = $('#d-district').val();
            if (!name || !phone || !addr || !dist) {
                $('#step1-err').removeClass('hidden');
                return;
            }
            $('#step1-err').addClass('hidden');
        }
        if (n === 3) {
            if (selectedMethod === 'card') {
                const card = $('#p-card').val().replace(/\s/g,'');
                const exp  = $('#p-expiry').val();
                const cvv  = $('#p-cvv').val();
                if (card.length < 16 || exp.length < 5 || cvv.length < 3) {
                    $('#step2-err').removeClass('hidden');
                    return;
                }
                $('#step2-err').addClass('hidden');
            }
            buildConfirm();
        }

        currentStep = n;
        for (let i = 1; i <= 4; i++) {
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
        const card = selectedMethod === 'card'
            ? `•••• •••• •••• ${$('#p-card').val().replace(/\s/g,'').slice(-4)}`
            : selectedMethod.toUpperCase() + ' / QR Code';

        $('#confirm-details').html(`
            <div class="bg-gray-50 rounded-xl p-4 space-y-1">
                <p class="font-semibold text-gray-700 mb-2">📦 Delivery</p>
                <p><span class="text-gray-400 w-24 inline-block">Name:</span>${$('#d-name').val()}</p>
                <p><span class="text-gray-400 w-24 inline-block">Phone:</span>${$('#d-phone').val()}</p>
                <p><span class="text-gray-400 w-24 inline-block">Address:</span>${$('#d-address').val()}, ${$('#d-district').val()}</p>
                <p><span class="text-gray-400 w-24 inline-block">Date:</span>${$('#d-date').val() || 'ASAP'}</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-4 space-y-1">
                <p class="font-semibold text-gray-700 mb-2">💳 Payment</p>
                <p><span class="text-gray-400 w-24 inline-block">Method:</span>${card}</p>
            </div>
            <div class="bg-green-50 rounded-xl p-4 flex justify-between items-center">
                <span class="font-bold text-gray-800">Total Payable</span>
                <span class="text-xl font-extrabold text-green-600">HK$${$('#co-total').text()}</span>
            </div>
        `);
    }

    // ── Place Order ─────────────────────────────────────────────────────────
    window.placeOrder = function() {
        const btn = $('#place-order-btn');
        btn.html('<i data-lucide="loader" class="animate-spin w-5 h-5 mr-2"></i> Processing...').prop('disabled', true);
        lucide.createIcons();

        setTimeout(() => {
            // Clear cart
            localStorage.removeItem('greenbite_cart');
            $('#cart-count').text('0');

            // Generate order ID
            const orderId = 'GB-' + Date.now().toString(36).toUpperCase();
            $('#order-id').text(orderId);

            goStep(4);
            $('#checkout-summary-col').addClass('hidden');
        }, 2000);
    };

    lucide.createIcons();
});
</script>
@endsection
