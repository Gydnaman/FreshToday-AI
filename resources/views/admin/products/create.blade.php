@extends('layouts.app')

@section('title', '新建产品 — GreenBite Admin')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <div class="mb-6">
        <a href="{{ route('admin.products.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
            ← 返回产品列表
        </a>
        <h1 class="text-3xl font-bold text-gray-800 mt-2">➕ 新建产品</h1>
        <p class="text-sm text-gray-500 mt-1">保存后默认 <span class="px-2 py-0.5 bg-yellow-100 text-yellow-800 rounded text-xs">草稿</span> 状态，不会在公开产品页显示。</p>
    </div>

    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded">
            <p class="font-semibold mb-1">提交失败：</p>
            <ul class="list-disc list-inside text-sm">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.products.store') }}"
          method="POST"
          enctype="multipart/form-data"
          class="bg-white shadow rounded-lg p-6 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">产品名称 <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500 focus:border-transparent"
                   required maxlength="255">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">分类 <span class="text-red-500">*</span></label>
            <select name="category_id" required
                    class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500">
                <option value="">— 请选择 —</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>
                        {{ $cat->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">价格 (HKD) <span class="text-red-500">*</span></label>
                <input type="number" name="price" value="{{ old('price') }}"
                       step="0.01" min="0" max="99999.99"
                       class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500"
                       required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">库存 <span class="text-red-500">*</span></label>
                <input type="number" name="stock" value="{{ old('stock', 0) }}"
                       min="0"
                       class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500"
                       required>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">产品描述</label>
            <textarea name="description" rows="4" maxlength="5000"
                      class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500">{{ old('description') }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">主图</label>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp"
                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:bg-green-50 file:text-green-700">
            <p class="text-xs text-gray-500 mt-1">支持 jpg / png / webp，最大 512KB</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">产地</label>
                <input type="text" name="origin" value="{{ old('origin') }}"
                       maxlength="64"
                       class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">碳足迹 (kg CO2e)</label>
                <input type="number" name="carbon_footprint" value="{{ old('carbon_footprint') }}"
                       step="0.001" min="0" max="99.999"
                       class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500">
            </div>
        </div>

        <div class="flex items-center">
            <input type="checkbox" name="is_organic" value="1" id="is_organic"
                   @checked(old('is_organic'))
                   class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
            <label for="is_organic" class="ml-2 text-sm text-gray-700">🌱 有机认证</label>
        </div>

        <div class="flex gap-3 pt-4 border-t">
            <button type="submit"
                    class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg shadow transition">
                💾 保存草稿
            </button>
            <a href="{{ route('admin.products.index') }}"
               class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg">
                取消
            </a>
        </div>
    </form>
</div>
@endsection
