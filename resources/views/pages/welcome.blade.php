@extends('layouts.app')

@section('title', __('home.title'))

@section('content')
<div class="bg-gradient-to-br from-green-50 to-emerald-100 pb-16 pt-8">
    <div class="container mx-auto px-4">
        <div class="text-center max-w-3xl mx-auto mb-20 animate-fade-in-up">
            <div class="inline-block bg-green-100 text-green-800 px-4 py-1 rounded-full mb-6 font-medium border border-green-200">
                {{ i18n('home.badge') }}
            </div>
            <h1 class="text-5xl md:text-6xl font-extrabold text-gray-900 mb-6 tracking-tight">
                {{ i18n('home.title') }} <span class="text-green-600 block mt-2">{{ i18n('home.titleHighlight') }}</span>
            </h1>
            <p class="text-xl text-gray-600 mb-8 leading-relaxed">
                {{ i18n('home.subtitle') }}
            </p>
            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <a href="{{ url('/catalog') }}" class="bg-green-600 text-white px-8 py-4 rounded-xl text-lg font-medium hover:bg-green-700 transition-colors shadow-lg hover:shadow-green-500/30">
                    {{ i18n('home.ctaExplore') }}
                </a>
                <a href="{{ url('/subscriptions') }}" class="bg-white text-green-700 px-8 py-4 rounded-xl text-lg font-medium border-2 border-green-600 hover:bg-green-50 transition-colors">
                    {{ i18n('home.ctaPlans') }}
                </a>
            </div>
        </div>

        <!-- Features -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-20">
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-8 shadow-sm text-center transform hover:-translate-y-1 transition duration-300 border border-white">
                <div class="bg-green-100 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-6 rotate-3">
                    <i data-lucide="truck" class="h-8 w-8 text-green-600"></i>
                </div>
                <h3 class="text-xl font-bold mb-3 text-gray-900">{{ i18n('home.feature1Title') }}</h3>
                <p class="text-gray-600 leading-relaxed">{{ i18n('home.feature1Desc') }}</p>
            </div>
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-8 shadow-sm text-center transform hover:-translate-y-1 transition duration-300 border border-white">
                <div class="bg-green-100 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-6 -rotate-3">
                    <i data-lucide="leaf" class="h-8 h-8 text-green-600"></i>
                </div>
                <h3 class="text-xl font-bold mb-3 text-gray-900">{{ i18n('home.feature2Title') }}</h3>
                <p class="text-gray-600 leading-relaxed">{{ i18n('home.feature2Desc') }}</p>
            </div>
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-8 shadow-sm text-center transform hover:-translate-y-1 transition duration-300 border border-white">
                <div class="bg-green-100 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-6 rotate-3">
                    <i data-lucide="package-check" class="h-8 w-8 text-green-600"></i>
                </div>
                <h3 class="text-xl font-bold mb-3 text-gray-900">{{ i18n('home.feature3Title') }}</h3>
                <p class="text-gray-600 leading-relaxed">{{ i18n('home.feature3Desc') }}</p>
            </div>
        </div>

        @auth
        <section data-testid="daily-menu-section" class="bg-white rounded-3xl shadow-xl overflow-hidden mb-10 border border-gray-100 p-8">
            <div class="flex flex-wrap gap-2 mb-6" role="tablist">
                @foreach ($menuDays as $day)
                    <button type="button"
                            role="tab"
                            aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                            data-menu-date="{{ $day['date'] }}"
                            class="px-4 py-2 rounded-lg border border-gray-200">
                        {{ $day['label'] }}
                    </button>
                @endforeach
            </div>

            @if ($menuState === 'needs_preferences')
                <div data-testid="menu-needs-preferences">
                    <a href="{{ url('/survey') }}">{{ i18n('homeMenu.needsPreferences') }}</a>
                </div>
            @elseif ($menuState === 'no_products')
                <div data-testid="menu-no-products">
                    <p>{{ i18n('homeMenu.noProducts') }}</p>
                    @if ($menuError)
                        <p>{{ $menuError }}</p>
                    @endif
                </div>
            @elseif ($menuState === 'generation_failed')
                <div data-testid="menu-generation-failed">
                    <p>{{ $menuError ?: i18n('homeMenu.generationFailed') }}</p>
                </div>
            @else
                @foreach ($menuDays as $day)
                    <div data-menu-panel="{{ $day['date'] }}" @if (! $loop->first) hidden @endif>
                        @if ($day['html'])
                            {!! $day['html'] !!}
                        @elseif ($day['menu'])
                            <p class="whitespace-pre-line">{{ $day['menu']->menu_content }}</p>
                        @else
                            <p>{{ i18n('homeMenu.noMenu') }}</p>
                        @endif
                    </div>
                @endforeach
            @endif
        </section>
        @endauth

        <!-- Join CTA -->
        @guest
        <div data-testid="guest-signup-section" class="bg-white rounded-3xl shadow-xl overflow-hidden mb-10 border border-gray-100">
            <div class="grid grid-cols-1 lg:grid-cols-2">
                <div class="p-12 bg-gradient-to-br from-green-600 to-emerald-700 text-white flex flex-col justify-center">
                    <h2 class="text-4xl font-bold mb-4 tracking-tight">{{ i18n('home.joinTitle') }}</h2>
                    <p class="text-green-50 mb-8 text-lg leading-relaxed">{{ i18n('home.joinDesc') }}</p>
                    <div class="space-y-6">
                        <div class="flex items-center bg-white/10 p-3 rounded-lg backdrop-blur-sm">
                            <i data-lucide="users" class="h-6 w-6 mr-4 text-green-200"></i>
                            <span class="text-lg font-medium">{{ i18n('home.joinStat1') }}</span>
                        </div>
                        <div class="flex items-center bg-white/10 p-3 rounded-lg backdrop-blur-sm">
                            <i data-lucide="cloud-off" class="h-6 w-6 mr-4 text-green-200"></i>
                            <span class="text-lg font-medium">{{ i18n('home.joinStat2') }}</span>
                        </div>
                    </div>
                </div>
                <div class="p-12 lg:p-16">
                    <h3 class="text-2xl font-bold text-gray-900 mb-8">{{ i18n('home.joinCta') }}</h3>
                    <form id="signup-form" class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('home.formName') }}</label>
                            <input type="text" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('home.formEmail') }}</label>
                            <input type="email" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition shadow-sm">
                        </div>
                        <button type="submit" class="w-full bg-green-600 text-white py-4 px-6 rounded-xl hover:bg-green-700 transition-colors font-bold text-lg mt-4 shadow-lg shadow-green-600/30">
                            {{ i18n('home.formCta') }}
                        </button>
                    </form>
                    <p class="text-center text-gray-500 mt-6 text-sm">
                        {{ i18n('home.formTerms') }}
                    </p>
                </div>
            </div>
        </div>
        @endguest
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#signup-form').on('submit', function(e) {
            e.preventDefault();
            const btn = $(this).find('button');
            btn.html('<i data-lucide="loader" class="animate-spin inline h-5 w-5 mr-2"></i> ' + {{ Js::from(i18n('common.loading')) }});
            lucide.createIcons();
            setTimeout(function() {
                window.location.href = "{{ url('/catalog') }}";
            }, 1000);
        });
    });
</script>
@endsection
