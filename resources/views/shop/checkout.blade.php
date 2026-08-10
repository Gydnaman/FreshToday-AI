@extends('layouts.app')

@section('title', i18n('checkout.title'))
@section('content')
@php
    $err = session('checkout_error');
@endphp
<div class="min-h-screen bg-gray-50 py-12 px-4">
<div class="max-w-5xl mx-auto">

    <div class="mb-8">
        <h1 class="text-3xl font-extrabold text-gray-900 flex items-center gap-3">
            <i data-lucide="credit-card" class="w-8 h-8 text-green-600"></i> {{ i18n('checkout.title') }}
        </h1>
        <p class="text-gray-500 mt-1">{{ i18n('checkout.subtitle') }}</p>
    </div>

    @if($errors->any())
    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3" role="alert">
        {{ $errors->first() }}
    </div>
    @endif
    @if($err)
    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 flex items-start gap-2">
        <i data-lucide="alert-triangle" class="w-5 h-5 flex-shrink-0 mt-0.5"></i>
        <div>
            <p class="font-semibold">{{ i18n('checkout.checkoutFailed') }}</p>
            <p>{{ $err }}</p>
        </div>
    </div>
    @endif

    {{-- Steps --}}
    <div class="flex items-center gap-2 mb-10">
        @php
            $stepLabels = [i18n('checkout.stepDelivery'), i18n('checkout.stepPayment'), i18n('checkout.stepConfirm')];
        @endphp
        @foreach($stepLabels as $i => $label)
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
        <input type="hidden" name="payment_method" value="sandbox_card">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- Left: Form Steps --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- 未登录拦截 --}}
                <div id="not-logged-in" class="hidden bg-yellow-50 border border-yellow-200 rounded-2xl p-6 text-center">
                    <i data-lucide="log-in" class="w-10 h-10 text-yellow-600 mx-auto mb-2"></i>
                    <p class="font-semibold text-gray-800">{{ i18n('checkout.pleaseLogin') }}</p>
                    <p class="text-sm text-gray-500 mb-4">{{ i18n('checkout.loginRequired') }}</p>
                    <a href="{{ url('/login?return=/checkout') }}" class="inline-block bg-green-600 text-white px-6 py-2 rounded-xl font-semibold hover:bg-green-700 transition">{{ i18n('checkout.goToLogin') }}</a>
                </div>

                {{-- Step 1: Delivery --}}
                <div id="form-step-1" class="auth-required">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-bold text-gray-800 mb-5 flex items-center gap-2">
                            <i data-lucide="map-pin" class="w-5 h-5 text-green-600"></i> {{ i18n('checkout.deliveryAddress') }}
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('checkout.fullName') }} <span class="text-red-500">*</span></label>
                                <input name="shipping_address[name]" type="text" placeholder="{{ i18n('checkout.fullNamePlaceholder') }}" required
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('checkout.phone') }} <span class="text-red-500">*</span></label>
                                <input name="shipping_address[phone]" type="tel" placeholder="{{ i18n('checkout.phonePlaceholder') }}" required
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition text-sm">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('checkout.address') }} <span class="text-red-500">*</span></label>
                                <input name="shipping_address[address]" type="text" placeholder="{{ i18n('checkout.addressPlaceholder') }}" required
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('checkout.district') }} <span class="text-red-500">*</span></label>
                                <select name="shipping_address[district]" required
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 transition text-sm">
                                    <option value="">{{ i18n('checkout.selectDistrict') }}</option>
                                    @php
                                        $districts = [
                                            i18n('checkout.districtHK'),
                                            i18n('checkout.districtKL'),
                                            i18n('checkout.districtNT'),
                                            i18n('checkout.districtLantau'),
                                        ];
                                    @endphp
                                    @foreach($districts as $d)
                                    <option>{{ $d }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('checkout.deliveryDate') }}</label>
                                <input id="delivery-date" name="shipping_address[date]" type="hidden" value="{{ old('shipping_address.date') }}">
                                <div class="flex items-center gap-2" aria-label="{{ i18n('checkout.deliveryDate') }}">
                                    <input id="delivery-year" type="text" inputmode="numeric" maxlength="4" pattern="[0-9]{4}"
                                        placeholder="{{ i18n('checkout.dateYearPlaceholder') }}" aria-label="{{ i18n('checkout.dateYearLabel') }}" autocomplete="off"
                                        class="w-2/5 min-w-0 px-3 py-3 text-center bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 transition text-sm">
                                    <span class="text-gray-400" aria-hidden="true">/</span>
                                    <input id="delivery-month" type="text" inputmode="numeric" maxlength="2" pattern="[0-9]{2}"
                                        placeholder="{{ i18n('checkout.dateMonthPlaceholder') }}" aria-label="{{ i18n('checkout.dateMonthLabel') }}" autocomplete="off"
                                        class="w-1/4 min-w-0 px-2 py-3 text-center bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 transition text-sm">
                                    <span class="text-gray-400" aria-hidden="true">/</span>
                                    <input id="delivery-day" type="text" inputmode="numeric" maxlength="2" pattern="[0-9]{2}"
                                        placeholder="{{ i18n('checkout.dateDayPlaceholder') }}" aria-label="{{ i18n('checkout.dateDayLabel') }}" autocomplete="off"
                                        class="w-1/4 min-w-0 px-2 py-3 text-center bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 transition text-sm">
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('checkout.deliveryNotes') }} <span class="text-gray-400">{{ i18n('common.optional') }}</span></label>
                                <input name="shipping_address[notes]" type="text" placeholder="{{ i18n('checkout.notesPlaceholder') }}"
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 transition text-sm">
                            </div>
                        </div>
                        <p id="step1-err" class="text-red-500 text-sm mt-3 hidden">{{ i18n('checkout.pleaseFillDelivery') }}</p>
                        <button type="button" onclick="goStep(2)" class="mt-6 w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white py-3.5 rounded-xl font-bold hover:from-green-600 hover:to-emerald-700 transition shadow-lg shadow-green-500/25">
                            {{ i18n('checkout.continueToPayment') }}
                        </button>
                    </div>
                </div>

                {{-- Step 2: Payment --}}
                <div id="form-step-2" class="hidden auth-required">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-bold text-gray-800 mb-5 flex items-center gap-2">
                            <i data-lucide="credit-card" class="w-5 h-5 text-green-600"></i> {{ i18n('checkout.paymentMethod') }}
                        </h2>
                        <div class="bg-blue-50 text-blue-700 text-xs rounded-xl px-4 py-3 mb-4 flex items-center gap-2">
                            <i data-lucide="shield-check" class="w-4 h-4"></i>
                            {{ i18n('checkout.sandboxCardNotice') }}
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label for="cardholder-name" class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('checkout.nameOnCard') }}</label>
                                <input id="cardholder-name" data-sandbox-card type="text" required maxlength="120" autocomplete="off"
                                    placeholder="{{ i18n('checkout.cardholderPlaceholder') }}"
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 transition text-sm">
                            </div>
                            <div class="md:col-span-2">
                                <label for="card-number" class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('checkout.cardNumber') }}</label>
                                <input id="card-number" data-sandbox-card type="text" required inputmode="numeric" maxlength="19"
                                    pattern="[0-9]{4} [0-9]{4} [0-9]{4} [0-9]{4}" autocomplete="off"
                                    placeholder="{{ i18n('checkout.cardNumberPlaceholder') }}"
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 transition text-sm tracking-wider">
                            </div>
                            <div>
                                <label for="card-expiry" class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('checkout.expiry') }}</label>
                                <input id="card-expiry" data-sandbox-card type="text" required inputmode="numeric" maxlength="5"
                                    pattern="(0[1-9]|1[0-2])/[0-9]{2}" autocomplete="off"
                                    placeholder="{{ i18n('checkout.expiryPlaceholder') }}"
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 transition text-sm">
                            </div>
                            <div>
                                <label for="card-cvv" class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('checkout.cvv') }}</label>
                                <input id="card-cvv" data-sandbox-card type="password" required inputmode="numeric" maxlength="3"
                                    pattern="[0-9]{3}" autocomplete="off" placeholder="{{ i18n('checkout.cvvPlaceholder') }}"
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 transition text-sm">
                            </div>
                        </div>
                        <div class="mt-4 flex items-center justify-between gap-3 rounded-xl bg-gray-50 px-4 py-3 text-xs text-gray-600">
                            <span>{{ i18n('checkout.testCardLabel') }}</span>
                            <code class="font-semibold text-gray-800">4242 4242 4242 4242</code>
                        </div>
                        <p class="mt-3 text-xs text-gray-500 flex items-center gap-1.5">
                            <i data-lucide="lock" class="w-3.5 h-3.5"></i> {{ i18n('checkout.security') }}
                        </p>

                        <div class="flex gap-3 mt-6">
                            <button type="button" onclick="goStep(1)" class="px-6 py-3 border border-gray-200 text-gray-600 rounded-xl hover:bg-gray-50 font-medium transition">← {{ i18n('common.back') }}</button>
                            <button type="button" onclick="goStep(3)" class="flex-1 bg-gradient-to-r from-green-500 to-emerald-600 text-white py-3.5 rounded-xl font-bold hover:from-green-600 hover:to-emerald-700 transition shadow-lg shadow-green-500/25">
                                {{ i18n('checkout.reviewOrder') }}
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Step 3: Confirm --}}
                <div id="form-step-3" class="hidden auth-required">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-bold text-gray-800 mb-5 flex items-center gap-2">
                            <i data-lucide="clipboard-check" class="w-5 h-5 text-green-600"></i> {{ i18n('checkout.stepConfirm') }}
                        </h2>
                        <div id="confirm-details" class="space-y-4 text-sm mb-6"></div>
                        <div class="border-t border-gray-100 pt-5">
                            <button id="place-order-btn" type="submit"
                                class="w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white py-4 rounded-xl font-bold text-base hover:from-green-600 hover:to-emerald-700 transition shadow-xl shadow-green-500/30 flex items-center justify-center gap-2">
                                <i data-lucide="check-circle" class="w-5 h-5"></i> {{ i18n('checkout.placeOrder') }}
                            </button>
                            <button type="button" onclick="goStep(2)" class="mt-3 w-full text-sm text-gray-400 hover:text-gray-600 transition">← {{ i18n('checkout.editPayment') }}</button>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Right: Order Summary --}}
            <div class="lg:col-span-1 auth-required" id="checkout-summary-col">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sticky top-6">
                    <h2 class="text-base font-bold text-gray-800 mb-4">{{ i18n('checkout.yourOrder') }}</h2>
                    <div id="co-items" class="space-y-3 mb-4 max-h-56 overflow-y-auto text-sm text-gray-600"></div>
                    <div class="border-t border-gray-100 pt-4 space-y-2 text-sm">
                        <div class="flex justify-between"><span>{{ i18n('checkout.subtotalLabel') }}</span><span class="font-semibold">HK$<span id="co-sub">0</span></span></div>
                        <div class="flex justify-between"><span>{{ i18n('checkout.deliveryLabel') }}</span><span class="font-semibold" id="co-del">HK$30</span></div>
                        <div class="flex justify-between font-bold text-gray-900 text-base border-t pt-2 mt-2">
                            <span>{{ i18n('checkout.totalLabel') }}</span><span class="text-green-600">HK$<span id="co-total">0</span></span>
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
const checkoutI18n = {
    cartEmpty: @json(i18n('checkout.cartEmpty')),
    free: @json(i18n('cart.free')),
    processing: @json(i18n('checkout.processing')),
    deliveryLabel: @json(i18n('checkout.deliveryLabel')),
    nameLabel: @json(i18n('checkout.nameLabel')),
    phoneLabel: @json(i18n('checkout.phoneLabel')),
    addressLabel: @json(i18n('checkout.addressLabel')),
    dateLabel: @json(i18n('checkout.dateLabel')),
    dateIncomplete: @json(i18n('checkout.dateIncomplete')),
    dateInvalid: @json(i18n('checkout.dateInvalid')),
    datePast: @json(i18n('checkout.datePast')),
    cardExpiryInvalid: @json(i18n('checkout.cardExpiryInvalid')),
    totalPayable: @json(i18n('checkout.totalPayable')),
};

$(document).ready(function() {
    const FREE_AT = 200, DELIVERY = 30;
    let currentStep = 1;
    const dateYear = document.getElementById('delivery-year');
    const dateMonth = document.getElementById('delivery-month');
    const dateDay = document.getElementById('delivery-day');
    const deliveryDate = document.getElementById('delivery-date');

    function setDatePartBehavior(input, digits, nextInput = null, previousInput = null) {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/\D/g, '').slice(0, digits);
            input.setCustomValidity('');
            if (input.value.length === digits && nextInput) nextInput.focus();
            syncDeliveryDate(false);
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'Backspace' && input.value === '' && previousInput) previousInput.focus();
        });
    }

    function syncDeliveryDate(showErrors = true) {
        [dateYear, dateMonth, dateDay].forEach(input => input.setCustomValidity(''));
        const year = dateYear.value;
        const month = dateMonth.value;
        const day = dateDay.value;

        if (!year && !month && !day) {
            deliveryDate.value = '';
            return true;
        }
        if (year.length !== 4 || month.length !== 2 || day.length !== 2) {
            deliveryDate.value = '';
            if (showErrors) {
                const incompleteInput = year.length !== 4 ? dateYear : (month.length !== 2 ? dateMonth : dateDay);
                incompleteInput.setCustomValidity(checkoutI18n.dateIncomplete);
            }
            return false;
        }

        const y = Number(year), m = Number(month), d = Number(day);
        const selected = new Date(y, m - 1, d);
        const isRealDate = y >= 1000 && m >= 1 && m <= 12 && d >= 1 &&
            selected.getFullYear() === y && selected.getMonth() === m - 1 && selected.getDate() === d;
        if (!isRealDate) {
            deliveryDate.value = '';
            if (showErrors) dateDay.setCustomValidity(checkoutI18n.dateInvalid);
            return false;
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (selected < today) {
            deliveryDate.value = '';
            if (showErrors) dateDay.setCustomValidity(checkoutI18n.datePast);
            return false;
        }

        deliveryDate.value = `${year}-${month}-${day}`;
        return true;
    }

    setDatePartBehavior(dateYear, 4, dateMonth);
    setDatePartBehavior(dateMonth, 2, dateDay, dateYear);
    setDatePartBehavior(dateDay, 2, null, dateMonth);

    dateYear.addEventListener('paste', event => {
        const digits = event.clipboardData.getData('text').replace(/\D/g, '');
        if (digits.length < 8) return;
        event.preventDefault();
        dateYear.value = digits.slice(0, 4);
        dateMonth.value = digits.slice(4, 6);
        dateDay.value = digits.slice(6, 8);
        dateDay.focus();
        syncDeliveryDate(false);
    });

    const savedDate = deliveryDate.value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (savedDate) {
        dateYear.value = savedDate[1];
        dateMonth.value = savedDate[2];
        dateDay.value = savedDate[3];
    }
    const cardNumber = document.getElementById('card-number');
    const cardExpiry = document.getElementById('card-expiry');
    const cardCvv = document.getElementById('card-cvv');

    cardNumber.addEventListener('input', () => {
        const digits = cardNumber.value.replace(/\D/g, '').slice(0, 16);
        cardNumber.value = digits.replace(/(\d{4})(?=\d)/g, '$1 ');
        cardNumber.setCustomValidity('');
    });

    cardExpiry.addEventListener('input', () => {
        const digits = cardExpiry.value.replace(/\D/g, '').slice(0, 4);
        cardExpiry.value = digits.length > 2 ? digits.slice(0, 2) + '/' + digits.slice(2) : digits;
        cardExpiry.setCustomValidity('');
    });

    cardCvv.addEventListener('input', () => {
        cardCvv.value = cardCvv.value.replace(/\D/g, '').slice(0, 3);
        cardCvv.setCustomValidity('');
    });

    function validateCardExpiry(showErrors = true) {
        cardExpiry.setCustomValidity('');
        const match = cardExpiry.value.match(/^(0[1-9]|1[0-2])\/(\d{2})$/);
        if (!match) {
            if (showErrors) cardExpiry.setCustomValidity(checkoutI18n.cardExpiryInvalid);
            return false;
        }

        const now = new Date();
        const month = Number(match[1]);
        const year = 2000 + Number(match[2]);
        const valid = year > now.getFullYear() || (year === now.getFullYear() && month >= now.getMonth() + 1);
        if (!valid && showErrors) cardExpiry.setCustomValidity(checkoutI18n.cardExpiryInvalid);
        return valid;
    }



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
                    image: it.product.image_url,
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
            coItems.append('<p class="text-gray-400 text-xs">' + checkoutI18n.cartEmpty + '</p>');
        } else {
            items.forEach(i => {
                coItems.append(`<div class="flex justify-between"><span>${i.name} x${i.qty}</span><span>HK$${(i.price*i.qty).toFixed(2)}</span></div>`);
            });
        }
        $('#co-sub').text(subtotal.toFixed(2));
        $('#co-del').text(delivery === 0 ? '🎉 ' + checkoutI18n.free : `HK$${delivery.toFixed(2)}`);
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
        if (n === 2) {
            syncDeliveryDate(true);
            const deliveryFields = Array.from(document.querySelectorAll('#form-step-1 input:not([type="hidden"]), #form-step-1 select'));
            const invalidDelivery = deliveryFields.find(field => !field.checkValidity());
            if (invalidDelivery) {
                invalidDelivery.reportValidity();
                return;
            }
            $('#step1-err').addClass('hidden');
        }

        if (n === 3) {
            validateCardExpiry(true);
            const paymentFields = Array.from(document.querySelectorAll('#form-step-2 [data-sandbox-card]'));
            const invalidPayment = paymentFields.find(field => !field.checkValidity());
            if (invalidPayment) {
                invalidPayment.reportValidity();
                return;
            }
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

    function escapeHtml(s) {
        if (!s) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
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
                <p class="font-semibold text-gray-700 mb-2">📦 ${checkoutI18n.deliveryLabel}</p>
                <p><span class="text-gray-400 w-24 inline-block">${checkoutI18n.nameLabel}</span>${escapeHtml(addr.name)}</p>
                <p><span class="text-gray-400 w-24 inline-block">${checkoutI18n.phoneLabel}</span>${escapeHtml(addr.phone)}</p>
                <p><span class="text-gray-400 w-24 inline-block">${checkoutI18n.addressLabel}</span>${escapeHtml(addr.address)}, ${escapeHtml(addr.district)}</p>
                <p><span class="text-gray-400 w-24 inline-block">${checkoutI18n.dateLabel}</span>${escapeHtml(addr.date)}</p>
            </div>
            <div class="bg-green-50 rounded-xl p-4 flex justify-between items-center">
                <span class="font-bold text-gray-800">${checkoutI18n.totalPayable}</span>
                <span class="text-xl font-extrabold text-green-600">HK$${$('#co-total').text()}</span>
            </div>
        `);
    }

    $('#checkout-form').on('submit', function(e) {
        if (!syncDeliveryDate(true)) {
            e.preventDefault();
            this.reportValidity();
            return;
        }

        const btn = $('#place-order-btn');
        btn.prop('disabled', true).html('<i data-lucide="loader" class="animate-spin w-5 h-5 mr-2"></i> ' + checkoutI18n.processing);
        lucide.createIcons();
        // 不 e.preventDefault() — 让浏览器原生 submit 走 POST
    });

    lucide.createIcons();
});
</script>
@endsection
