@extends('layouts.app')

@section('title', 'Product Catalog')

@section('content')
<div class="container mx-auto px-4 py-12">
    <div class="text-center mb-12 animate-fade-in-up">
        <h1 class="text-3xl md:text-5xl font-extrabold text-gray-900 mb-4 tracking-tight">Local Farm Freshness</h1>
        <p class="text-xl text-gray-600 max-w-2xl mx-auto">
            Discover sustainably grown produce from Hong Kong's local farms. 
            Every purchase reduces food miles and supports our community.
        </p>
    </div>
    
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
        @foreach($products as $product)
        <div class="bg-white rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100 group">
            <div class="relative h-48 overflow-hidden bg-gray-100">
                <img src="{{ $product->image }}" alt="{{ $product->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                <div class="absolute top-3 right-3 bg-white/90 backdrop-blur text-green-700 text-xs font-bold px-2 py-1 rounded-md flex items-center shadow-sm">
                    <i data-lucide="leaf" class="w-3 h-3 mr-1"></i> {{ $product->carbonFootprint }}
                </div>
            </div>
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-2">{{ $product->name }}</h3>
                <p class="text-gray-500 text-sm h-10 line-clamp-2 mb-4">{{ $product->description }}</p>
                <div class="flex items-center justify-between mt-auto">
                    <span class="text-2xl font-bold text-green-600">HK${{ $product->price }}</span>
                    <button onclick="addToCart(this.dataset.name, this.dataset.price)" data-name="{{ $product->name }}" data-price="{{ $product->price }}" class="bg-green-100 text-green-700 p-3 rounded-xl hover:bg-green-600 hover:text-white transition group-hover:scale-110 duration-300" aria-label="Add to cart">
                        <i data-lucide="plus" class="w-5 h-5 font-bold"></i>
                    </button>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    
    <div class="mt-16 text-center">
        <a href="{{ url('/subscriptions') }}" class="inline-flex items-center justify-center bg-green-600 text-white px-8 py-4 rounded-xl text-lg font-bold hover:bg-green-700 transition-colors shadow-lg hover:shadow-green-500/30">
            <i data-lucide="calendar-days" class="mr-3 h-5 w-5"></i> 
            Explore Subscription Plans
        </a>
    </div>
</div>
@endsection
