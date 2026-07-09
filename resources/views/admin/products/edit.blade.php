@extends('layouts.app')

@section('title', i18n('admin.products.edit.title') . ' — GreenBite Admin')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <div class="mb-6">
        <a href="{{ route('admin.products.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
            ← {{ i18n('admin.products.edit.backToList') }}
        </a>
        <h1 class="text-3xl font-bold text-gray-800 mt-2">{{ i18n('admin.products.edit.title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ i18n('admin.products.edit.note') }}</p>
    </div>

    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded">
            <p class="font-semibold mb-1">{{ i18n('admin.products.edit.submitFailed') }}</p>
            <ul class="list-disc list-inside text-sm">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.products.update', $product) }}"
          method="POST"
          enctype="multipart/form-data"
          class="bg-white shadow rounded-lg p-6 space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('admin.products.edit.name') }} <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $product->name) }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500 focus:border-transparent"
                   required maxlength="255">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('admin.products.edit.category') }} <span class="text-red-500">*</span></label>
            <select name="category_id" required
                    class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500">
                <option value="">{{ i18n('admin.products.edit.pleaseSelect') }}</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(old('category_id', $product->category_id) == $cat->id)>
                        {{ $cat->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('admin.products.edit.price') }} <span class="text-red-500">*</span></label>
                <input type="number" name="price" value="{{ old('price', $product->price) }}"
                       step="0.01" min="0" max="99999.99"
                       class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500"
                       required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('admin.products.edit.stock') }} <span class="text-red-500">*</span></label>
                <input type="number" name="stock" value="{{ old('stock', $product->stock) }}"
                       min="0"
                       class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500"
                       required>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('admin.products.edit.description') }}</label>
            <textarea name="description" rows="4" maxlength="5000"
                      class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500">{{ old('description', $product->description) }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('admin.products.edit.image') }}</label>
            @if ($product->image_url)
                <div class="mb-2 flex items-center gap-3">
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}"
                         class="w-20 h-20 object-cover rounded border">
                    <span class="text-xs text-gray-500">{{ i18n('admin.products.edit.currentImage') }}</span>
                </div>
            @endif
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp"
                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:bg-green-50 file:text-green-700">
            <p class="text-xs text-gray-500 mt-1">{{ i18n('admin.products.edit.imageHint') }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('admin.products.edit.origin') }}</label>
                <input type="text" name="origin" value="{{ old('origin', $product->origin) }}"
                       maxlength="64"
                       class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('admin.products.edit.carbonFootprint') }}</label>
                <input type="number" name="carbon_footprint" value="{{ old('carbon_footprint', $product->carbon_footprint) }}"
                       step="0.001" min="0" max="99.999"
                       class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500">
            </div>
        </div>

        <div class="flex items-center">
            <input type="checkbox" name="is_organic" value="1" id="is_organic"
                   @checked(old('is_organic', $product->is_organic))
                   class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
            <label for="is_organic" class="ml-2 text-sm text-gray-700">🌱 {{ i18n('admin.products.edit.isOrganic') }}</label>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('admin.products.edit.status') }}</label>
            <select name="status"
                    class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500">
                @php
                    $currentStatus = old('status', $product->status);
                @endphp
                <option value="draft" @selected($currentStatus === 'draft')>{{ i18n('admin.products.edit.statusDraft') }}</option>
                <option value="published" @selected($currentStatus === 'published')>{{ i18n('admin.products.edit.statusPublished') }}</option>
                <option value="archived" @selected($currentStatus === 'archived')>{{ i18n('admin.products.edit.statusArchived') }}</option>
            </select>
        </div>

        <div class="flex gap-3 pt-4 border-t">
            <button type="submit"
                    class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg shadow transition">
                {{ i18n('admin.products.edit.save') }}
            </button>
            <a href="{{ route('admin.products.index') }}"
               class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg">
                {{ i18n('common.cancel') }}
            </a>
        </div>
    </form>
</div>
@endsection

