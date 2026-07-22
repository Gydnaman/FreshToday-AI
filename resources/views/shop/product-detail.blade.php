@extends('layouts.app')

@section('title', $product->name)

@section('content')
@php($soldOut = (int) $product->stock <= 0)
<div class="container mx-auto px-4 py-8 md:py-12">
    <a href="{{ route('catalog') }}" class="inline-flex items-center gap-2 text-green-700 hover:text-green-800 font-semibold">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        {{ i18n('productDetail.backToCatalog') }}
    </a>

    <article class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 bg-white rounded-2xl border border-gray-100 shadow-sm p-5 md:p-8">
        <div class="rounded-2xl overflow-hidden bg-gray-100 aspect-square">
            @if($product->image_url)
                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
            @else
                <div data-testid="product-image-placeholder" class="w-full h-full flex flex-col items-center justify-center text-gray-400">
                    <i data-lucide="image-off" class="w-14 h-14 mb-3"></i>
                    <span>{{ i18n('productDetail.imageUnavailable') }}</span>
                </div>
            @endif
        </div>

        <div class="flex flex-col">
            @if($product->category)
                <p class="text-sm font-semibold uppercase tracking-wide text-green-700">{{ $product->category->name }}</p>
            @endif
            <h1 class="mt-2 text-3xl md:text-4xl font-extrabold text-gray-900">{{ $product->name }}</h1>

            <div class="mt-4 flex flex-wrap gap-2">
                @if($product->is_organic)
                    <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-3 py-1 text-sm font-semibold text-green-700">
                        <i data-lucide="sprout" class="w-4 h-4"></i>{{ i18n('productDetail.organic') }}
                    </span>
                @endif
                @if(!is_null($product->carbon_footprint))
                    <span data-testid="product-carbon" class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700">
                        <i data-lucide="leaf" class="w-4 h-4"></i>
                        {{ i18n('productDetail.carbonFootprint') }}: {{ number_format((float) $product->carbon_footprint, 2) }} {{ i18n('productDetail.carbonUnit') }}
                    </span>
                @endif
            </div>

            @if($product->origin)
                <p data-testid="product-origin" class="mt-5 flex items-center gap-2 text-gray-600">
                    <i data-lucide="map-pin" class="w-5 h-5 text-green-600"></i>
                    <span><strong>{{ i18n('productDetail.origin') }}:</strong> {{ $product->origin }}</span>
                </p>
            @endif

            <p class="mt-6 text-3xl font-extrabold text-green-600">HK${{ number_format((float) $product->price, 2) }}</p>

            <p class="mt-2 {{ $soldOut ? 'text-red-600' : 'text-green-700' }} font-semibold">
                {{ $soldOut ? i18n('productDetail.soldOut') : i18n('productDetail.stockRemaining', ['count' => $product->stock]) }}
            </p>

            <section class="mt-8">
                <h2 class="text-lg font-bold text-gray-900">{{ i18n('productDetail.description') }}</h2>
                <p class="mt-2 text-gray-600 leading-7">{{ $product->description }}</p>
            </section>

            <div class="mt-8 pt-6 border-t border-gray-100">
                @if($soldOut)
                    <button data-testid="sold-out-button" type="button" disabled class="w-full rounded-xl bg-gray-200 px-6 py-4 font-bold text-gray-500 cursor-not-allowed">
                        {{ i18n('productDetail.soldOut') }}
                    </button>
                @else
                    <label for="product-quantity" class="block text-sm font-semibold text-gray-700 mb-2">{{ i18n('productDetail.quantity') }}</label>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <input id="product-quantity" type="number" min="1" max="{{ (int) $product->stock }}" value="1"
                            class="w-full sm:w-28 rounded-xl border border-gray-300 px-4 py-3 focus:border-green-500 focus:ring-green-500">
                        <button type="button"
                            onclick="addToCartAuth({{ (int) $product->id }}, {{ \Illuminate\Support\Js::from($product->name) }}, {{ (float) $product->price }}, Number(document.getElementById('product-quantity').value))"
                            class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-green-600 px-6 py-3 font-bold text-white hover:bg-green-700 transition">
                            <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                            {{ i18n('productDetail.addToCart') }}
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </article>
</div>
@endsection
