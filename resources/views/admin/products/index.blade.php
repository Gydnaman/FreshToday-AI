@extends('layouts.app')

@section('title', i18n('admin.products.index.title') . ' — GreenBite Admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-800">{{ i18n('admin.products.index.title') }}</h1>
        <a href="{{ route('admin.products.create') }}"
           class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg shadow transition">
            + {{ i18n('admin.products.index.create') }}
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
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ i18n('admin.products.index.image') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ i18n('admin.products.index.name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ i18n('admin.products.index.category') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ i18n('admin.products.index.price') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ i18n('admin.products.index.stock') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ i18n('admin.products.index.status') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ i18n('admin.products.index.updatedAt') }}</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">{{ i18n('admin.products.index.actions') }}</th>
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
                                    {{ i18n('admin.products.index.noImage') }}
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
                                    'published' => i18n('admin.products.status.published'),
                                    'draft' => i18n('admin.products.status.draft'),
                                    'archived' => i18n('admin.products.status.archived'),
                                    default => $p->status,
                                };
                            @endphp
                            <span class="px-2 py-1 text-xs font-semibold rounded {{ $badge }}">{{ $label }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-sm">{{ $p->updated_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-center">
                            <a href="{{ route('admin.products.edit', $p) }}"
                               class="inline-flex items-center gap-1 px-3 py-1 text-sm text-green-600 hover:text-green-800 hover:bg-green-50 rounded transition">
                                <i data-lucide="pencil" class="w-4 h-4"></i> {{ i18n('common.edit') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-gray-500">
                            {!! i18n('admin.products.index.empty') !!}
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
        💡 {{ i18n('admin.products.index.footer') }}
    </p>
</div>
@endsection
