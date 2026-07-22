@extends('layouts.app')

@section('title', i18n('catalog.pageTitle'))

@section('content')
<div class="container mx-auto px-4 py-12">
    <div class="text-center mb-12 animate-fade-in-up">
        <h1 class="text-3xl md:text-5xl font-extrabold text-gray-900 mb-4 tracking-tight">{{ i18n('catalog.title') }}</h1>
        <p class="text-xl text-gray-600 max-w-2xl mx-auto">{{ i18n('catalog.subtitle') }}</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
        @foreach($products as $product)
        @php
            $soldOut = ((int) $product->stock) <= 0;
        @endphp
        <div id="product-{{ $product->id }}" class="bg-white rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100 group scroll-mt-20">
            <div class="relative h-48 overflow-hidden bg-gray-100">
                <a href="{{ route('products.show', $product) }}" aria-label="{{ $product->name }}">
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                </a>

                {{-- 碳足迹徽章（左上） --}}
                @if(!is_null($product->carbon_footprint))
                <div class="absolute top-3 left-3 bg-white/90 backdrop-blur text-green-700 text-xs font-bold px-2 py-1 rounded-md flex items-center shadow-sm">
                    <i data-lucide="leaf" class="w-3 h-3 mr-1"></i> {{ number_format((float) $product->carbon_footprint, 2) }} {{ i18n('catalog.carbonUnit') }}
                </div>
                @endif

                {{-- Organic 徽章（右上） --}}
                @if($product->is_organic)
                <div class="absolute top-3 right-3 bg-green-600 text-white text-xs font-bold px-2 py-1 rounded-md flex items-center shadow-sm">
                    <i data-lucide="sprout" class="w-3 h-3 mr-1"></i> {{ i18n('catalog.organic') }}
                </div>
                @endif
            </div>
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-1">
                    <a href="{{ route('products.show', $product) }}" class="hover:text-green-700 transition-colors">{{ $product->name }}</a>
                </h3>
                @if($product->origin)
                <p class="text-xs text-gray-400 mb-2 flex items-center gap-1">
                    <i data-lucide="map-pin" class="w-3 h-3"></i> {{ $product->origin }}
                </p>
                @endif
                <p class="text-gray-500 text-sm h-10 line-clamp-2 mb-4">{{ $product->description }}</p>
                <div class="flex items-center justify-between mt-auto">
                    <span class="text-2xl font-bold text-green-600">HK${{ number_format((float) $product->price, 2) }}</span>

                    @if($soldOut)
                        <button type="button" disabled
                            class="bg-gray-100 text-gray-400 p-3 rounded-xl cursor-not-allowed flex items-center gap-1 text-sm font-semibold"
                            aria-label="{{ i18n('catalog.soldOutAria') }}">
                            <i data-lucide="ban" class="w-4 h-4"></i> {{ i18n('catalog.soldOut') }}
                        </button>
                    @else
                        <button type="button"
                            onclick="addToCartAuth({{ (int) $product->id }}, {{ \Illuminate\Support\Js::from($product->name) }}, {{ (float) $product->price }})"
                            class="bg-green-100 text-green-700 p-3 rounded-xl hover:bg-green-600 hover:text-white transition group-hover:scale-110 duration-300"
                            aria-label="{{ i18n('catalog.addToCartAria') }}">
                            <i data-lucide="plus" class="w-5 h-5 font-bold"></i>
                        </button>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-16 text-center">
        <a href="{{ url('/subscriptions') }}" class="inline-flex items-center justify-center bg-green-600 text-white px-8 py-4 rounded-xl text-lg font-bold hover:bg-green-700 transition-colors shadow-lg hover:shadow-green-500/30">
            <i data-lucide="calendar-days" class="mr-3 h-5 w-5"></i>
            {{ i18n('catalog.explorePlans') }}
        </a>
    </div>
</div>
@endsection
