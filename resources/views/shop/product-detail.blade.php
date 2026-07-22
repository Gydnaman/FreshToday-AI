@extends('layouts.app')

@section('title', $product->name)

@section('content')
<div class="container mx-auto px-4 py-12">
    <a href="{{ route('catalog') }}" class="text-green-700 hover:text-green-800">{{ i18n('common.back') }}</a>
    <article class="mt-6 bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        @if($product->category)
            <p>{{ $product->category->name }}</p>
        @endif
        <h1 class="text-3xl font-extrabold text-gray-900">{{ $product->name }}</h1>
        <p class="mt-4 text-2xl font-bold text-green-600">HK${{ number_format((float) $product->price, 2) }}</p>
        <p class="mt-6 text-gray-600">{{ $product->description }}</p>
    </article>
</div>
@endsection
