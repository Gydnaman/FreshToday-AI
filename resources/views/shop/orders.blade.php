@extends('layouts.app')

@section('title', i18n('orders.title'))

@section('content')
<div class="container mx-auto px-4 py-12">
    <div class="max-w-4xl mx-auto animate-fade-in-up">

        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900">{{ i18n('orders.title') }}</h1>
            <a href="/catalog" class="bg-green-600 text-white px-5 py-2 rounded-xl font-bold hover:bg-green-700 transition shadow-sm flex items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i> {{ i18n('common.shopMore') }}
            </a>
        </div>

        <!-- Order Status Tabs -->
        <div class="flex gap-2 mb-8 border-b border-gray-200" id="order-tabs">
            <button class="tab-btn px-4 py-2 text-sm font-semibold border-b-2 transition -mb-px text-green-700 border-green-600" data-target="all">{{ i18n('orders.tabAll') }}</button>
            <button class="tab-btn px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition -mb-px" data-target="processing">{{ i18n('orders.tabProcessing') }}</button>
            <button class="tab-btn px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition -mb-px" data-target="delivered">{{ i18n('orders.tabDelivered') }}</button>
        </div>

        <!-- Order List -->
        <div class="space-y-6" id="orders-container">
            @if(isset($orders) && count($orders) > 0)
                @foreach($orders as $order)
                    <!-- Order Card -->
                    <div class="order-card bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg transition" data-status="{{ strtolower($order->status) }}">
                        <div class="flex items-center justify-between p-5 border-b border-gray-50">
                            <div>
                                <span class="text-xs text-gray-400 font-medium uppercase tracking-wider">{{ i18n('orders.orderNo') }}{{ $order->order_number }}</span>
                                <p class="text-sm text-gray-500 mt-0.5">{{ i18n('orders.placedOn') }} {{ $order->date }}</p>
                            </div>
                            @if(strtolower($order->status) === 'delivered')
                                <span class="inline-flex items-center gap-1.5 bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold">
                                    <i data-lucide="check-circle" class="w-3 h-3"></i> {{ i18n('orders.statusDelivered') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-bold">
                                    <i data-lucide="truck" class="w-3 h-3"></i> {{ i18n('orders.statusOutForDelivery') }}
                                </span>
                            @endif
                        </div>
                        <div class="p-5">
                            <div class="flex items-start gap-4">
                                <div class="flex-1 space-y-1">
                                    <p class="font-semibold text-gray-900">{{ $order->product_name }}</p>
                                    <p class="text-sm text-gray-500">{{ $order->product_type }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-extrabold text-gray-900 text-lg">{{ $order->price }}</p>
                                    <p class="text-xs text-gray-400">{{ i18n('orders.inclDelivery') }}</p>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center justify-between">
                                <div class="flex items-center gap-1.5 text-sm text-green-700 font-medium">
                                    <i data-lucide="leaf" class="w-4 h-4"></i>
                                    <span>{{ i18n('orders.saved') }} {{ $order->co2_saved }} CO₂e</span>
                                </div>
                                <button class="text-sm text-green-600 font-semibold hover:underline">{{ i18n('common.viewDetails') }}</button>
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <!-- Empty State -->
                <div class="text-center py-24" id="empty-state">
                    <div class="bg-green-50 rounded-full w-24 h-24 flex items-center justify-center mx-auto mb-6">
                        <i data-lucide="package-open" class="w-10 h-10 text-green-400"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">{{ i18n('orders.emptyTitle') }}</h2>
                    <p class="text-gray-500 mb-6">{{ i18n('orders.emptySubtitle') }}</p>
                    <a href="/catalog" class="bg-green-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-green-700 transition shadow">{{ i18n('orders.shopNow') }}</a>
                </div>
            @endif
            
            <!-- Hidden empty state for when filters result in 0 visible items -->
            <div class="text-center py-24 hidden" id="filter-empty-state">
                <div class="bg-gray-50 rounded-full w-24 h-24 flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="search" class="w-10 h-10 text-gray-400"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">{{ i18n('orders.filterEmptyTitle') }}</h2>
                <p class="text-gray-500 mb-6">{{ i18n('orders.empty') }}</p>
            </div>
        </div>

    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tabBtns = document.querySelectorAll('.tab-btn');
        const orderCards = document.querySelectorAll('.order-card');
        const filterEmptyState = document.getElementById('filter-empty-state');
        const mainEmptyState = document.getElementById('empty-state');
        
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove active classes from all buttons
                tabBtns.forEach(b => {
                    b.classList.remove('text-green-700', 'border-green-600', 'font-semibold');
                    b.classList.add('text-gray-500', 'border-transparent', 'font-medium');
                });
                
                // Add active classes to clicked button
                btn.classList.remove('text-gray-500', 'border-transparent', 'font-medium');
                btn.classList.add('text-green-700', 'border-green-600', 'font-semibold');
                
                const target = btn.getAttribute('data-target');
                let visibleCount = 0;
                
                if (orderCards.length > 0) {
                    orderCards.forEach(card => {
                        if (target === 'all' || card.getAttribute('data-status') === target || (target === 'processing' && card.getAttribute('data-status') !== 'delivered')) {
                            card.style.display = 'block';
                            visibleCount++;
                        } else {
                            card.style.display = 'none';
                        }
                    });
                    
                    if (visibleCount === 0) {
                        filterEmptyState.classList.remove('hidden');
                    } else {
                        filterEmptyState.classList.add('hidden');
                    }
                }
            });
        });
    });
</script>
@endsection
