@php
    /**
     * 通用语言切换下拉
     * 由 layouts/app.blade.php @include 引入。
     * 数据 SSOT：docs/i18n/PLAN-i18n.md §2.2
     */
    $supported = [
        'zhhk' => ['name' => '繁體中文', 'flag' => '🇭🇰'],
        'en'   => ['name' => 'English',  'flag' => '🇬🇧'],
        'zh'   => ['name' => '简体中文', 'flag' => '🇨🇳'],
    ];
    $current = str_replace('_', '-', app()->getLocale());
    $currentInfo = $supported[$current] ?? $supported['zh'];
@endphp
<div class="relative" x-data="{ open: false }">
    <button type="button"
            onclick="this.nextElementSibling.classList.toggle('hidden')"
            class="flex items-center gap-1 text-sm text-gray-600 hover:text-green-600 px-2 py-1 rounded transition"
            aria-haspopup="true"
            aria-expanded="false"
            data-i18n-aria="nav.language"
            aria-label="{{ i18n('nav.language') }}">
        <span aria-hidden="true">🌐</span>
        <span class="font-medium">{{ $currentInfo['flag'] }} {{ $currentInfo['name'] }}</span>
        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M5 8l5 5 5-5H5z"/></svg>
    </button>
    <div class="hidden absolute right-0 mt-1 bg-white border border-gray-100 rounded-lg shadow-lg z-50 min-w-[160px] py-1">
        @foreach($supported as $code => $info)
            <a href="{{ url()->current() }}?lang={{ $code }}"
               class="block px-4 py-2 text-sm hover:bg-green-50 {{ $current === $code ? 'text-green-700 font-bold' : 'text-gray-700' }}">
                <span class="mr-2">{{ $info['flag'] }}</span>{{ $info['name'] }}
            </a>
        @endforeach
    </div>
</div>
