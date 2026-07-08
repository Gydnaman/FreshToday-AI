@extends('layouts.app')

@section('title', '产品管理 — GreenBite Admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-800">📦 产品管理</h1>
        <a href="{{ route('admin.products.create') }}"
           class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg shadow transition">
            + 新建产品
        </a>
    </div>

    @if (session('success'))
        <div class="mb-4 p-3 bg-green-100 border border-green-300 text-green-800 rounded">
            ✅ {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 p-3 bg-red-100 border border-red-300 text-red-800 rounded">
            ❌ {{ session('error') }}
        </div>
    @endif

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">图片</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">名称</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">分类</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">价格</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">库存</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">状态</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">更新时间</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">操作</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($products as $p)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            @if ($p->image_url)
                                <img src="{{ $p->image_url }}"
                                     alt="{{ $p->name }}"
                                     class="w-12 h-12 object-cover rounded border">
                            @else
                                <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center text-gray-400 text-xs">
                                    无图
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $p->name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $p->category->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-gray-900">HK$ {{ number_format((float) $p->price, 2) }}</td>
                        <td class="px-4 py-3 text-right text-gray-900">{{ $p->stock }}</td>
                        <td class="px-4 py-3">
                            @php
                                $badge = match($p->status) {
                                    'published' => 'bg-green-100 text-green-800',
                                    'draft' => 'bg-yellow-100 text-yellow-800',
                                    'archived' => 'bg-gray-100 text-gray-600',
                                    default => 'bg-gray-100 text-gray-600',
                                };
                                $label = match($p->status) {
                                    'published' => '已上架',
                                    'draft' => '草稿',
                                    'archived' => '已归档',
                                    default => $p->status,
                                };
                            @endphp
                            <span class="px-2 py-1 text-xs font-semibold rounded {{ $badge }}">{{ $label }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-sm">{{ $p->updated_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-center">
                            <a href="{{ route('admin.products.edit', $p) }}"
                               class="inline-flex items-center gap-1 px-3 py-1 text-sm text-green-600 hover:text-green-800 hover:bg-green-50 rounded transition">
                                <i data-lucide="pencil" class="w-4 h-4"></i> 编辑
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-gray-500">
                            还没有任何产品。<a href="{{ route('admin.products.create') }}" class="text-green-600 underline">立即创建第一个 →</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="px-4 py-3 bg-gray-50 border-t">
            {{ $products->links() }}
        </div>
    </div>

    <p class="mt-4 text-sm text-gray-500">
        💡 提示：<a href="{{ url('/') }}" class="text-blue-600 underline">返回首页</a> ·
        公开产品页只显示 <code>published</code> 状态的产品（编辑/上下架功能后续 Sprint）
    </p>
</div>
@endsection
