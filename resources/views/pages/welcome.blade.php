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
        <section data-testid="daily-menu-section" class="bg-white rounded-3xl shadow-xl overflow-hidden mb-10 border border-gray-100 p-5 sm:p-8">
            <div class="mb-6">
                <h2 class="text-3xl font-extrabold text-gray-900">{{ i18n('homeMenu.title') }}</h2>
                <p class="mt-2 text-gray-600">{{ i18n('homeMenu.subtitle') }}</p>
            </div>

            @if ($menuState === 'needs_preferences')
                <div data-testid="menu-needs-preferences" class="rounded-2xl bg-amber-50 p-5 text-amber-900">
                    <p>{{ i18n('homeMenu.needsPreferences') }}</p>
                    <a href="{{ url('/survey') }}" class="mt-4 inline-flex rounded-lg bg-amber-700 px-4 py-2 font-semibold text-white hover:bg-amber-800">
                        {{ i18n('homeMenu.completePreferences') }}
                    </a>
                </div>
            @elseif ($menuState === 'no_products')
                <div data-testid="menu-no-products" class="rounded-2xl bg-gray-50 p-5 text-gray-700">
                    <p>{{ i18n('homeMenu.noProducts') }}</p>
                    @if ($menuError)
                        <p class="mt-2 text-sm">{{ $menuError }}</p>
                    @endif
                </div>
            @elseif ($menuState === 'generation_failed')
                <div data-testid="menu-generation-failed" class="rounded-2xl bg-red-50 p-5 text-red-700" role="alert">
                    <p>{{ $menuError ?: i18n('homeMenu.generationFailed') }}</p>
                </div>
            @else
                <p class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">{{ i18n('homeMenu.previousDays') }}</p>
                <div class="mb-6 overflow-x-auto pb-2">
                    <div class="flex min-w-max gap-2" role="tablist" aria-label="{{ i18n('homeMenu.previousDays') }}">
                        @foreach ($menuDays as $day)
                            <button type="button"
                                    id="menu-tab-{{ $day['date'] }}"
                                    role="tab"
                                    aria-controls="menu-panel-{{ $day['date'] }}"
                                    aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                                    tabindex="{{ $loop->first ? '0' : '-1' }}"
                                    data-menu-date="{{ $day['date'] }}"
                                    class="rounded-lg border px-4 py-2 font-medium transition {{ $loop->first ? 'border-green-600 bg-green-600 text-white' : 'border-gray-200 bg-white text-gray-700 hover:border-green-300 hover:text-green-700' }}">
                                {{ $day['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                @foreach ($menuDays as $day)
                    <div id="menu-panel-{{ $day['date'] }}"
                         data-menu-panel="{{ $day['date'] }}"
                         role="tabpanel"
                         aria-labelledby="menu-tab-{{ $day['date'] }}"
                         class="rounded-2xl border border-gray-100 bg-gray-50 p-5 sm:p-6"
                         @if (! $loop->first) hidden @endif>
                        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">{{ $day['label'] }}</h3>
                                <p class="text-sm text-gray-500">{{ $day['date'] }}</p>
                                @if ($day['menu'])
                                    <p class="mt-2 text-sm text-gray-500">
                                        {{ i18n('homeMenu.source') }}: {{ $day['menu']->source }}
                                    </p>
                                @endif
                            </div>

                            @if ($loop->first && $day['menu'])
                                <div class="sm:text-right">
                                    <p class="mb-2 text-sm font-medium text-green-700">{{ i18n('homeMenu.updated') }}</p>
                                    <button data-testid="regenerate-menu-button"
                                            type="button"
                                            id="regenerate-menu-button"
                                            class="w-full rounded-lg bg-green-600 px-4 py-2 font-semibold text-white transition hover:bg-green-700 disabled:cursor-wait disabled:opacity-60 sm:w-auto">
                                        {{ i18n('homeMenu.regenerate') }}
                                    </button>
                                    <p id="regenerate-menu-error" class="hidden mt-2 max-w-sm text-sm text-red-600" role="alert"></p>
                                </div>
                            @endif
                        </div>

                        <div class="text-gray-700">
                            @if ($day['html'])
                                {!! $day['html'] !!}
                            @elseif ($day['menu'])
                                <p class="whitespace-pre-line">{{ $day['menu']->menu_content }}</p>
                            @else
                                <p>{{ i18n('homeMenu.noMenu') }}</p>
                            @endif
                        </div>
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
        const $tabs = $('[data-menu-date]');

        function activateMenuTab($tab, shouldFocus) {
            const selectedDate = $tab.data('menu-date');

            $tabs
                .attr('aria-selected', 'false')
                .attr('tabindex', '-1')
                .removeClass('border-green-600 bg-green-600 text-white')
                .addClass('border-gray-200 bg-white text-gray-700');

            $tab
                .attr('aria-selected', 'true')
                .attr('tabindex', '0')
                .removeClass('border-gray-200 bg-white text-gray-700')
                .addClass('border-green-600 bg-green-600 text-white');

            $('[data-menu-panel]').prop('hidden', true);
            $('[data-menu-panel="' + selectedDate + '"]').prop('hidden', false);

            if (shouldFocus) {
                $tab.trigger('focus');
            }
        }

        $tabs.on('click', function() {
            activateMenuTab($(this), false);
        });

        $tabs.on('keydown', function(event) {
            const currentIndex = $tabs.index(this);
            let targetIndex;

            switch (event.key) {
                case 'ArrowLeft':
                    targetIndex = (currentIndex - 1 + $tabs.length) % $tabs.length;
                    break;
                case 'ArrowRight':
                    targetIndex = (currentIndex + 1) % $tabs.length;
                    break;
                case 'Home':
                    targetIndex = 0;
                    break;
                case 'End':
                    targetIndex = $tabs.length - 1;
                    break;
                default:
                    return;
            }

            event.preventDefault();
            activateMenuTab($tabs.eq(targetIndex), true);
        });

        $('#regenerate-menu-button').on('click', function() {
            const $button = $(this);
            const $error = $('#regenerate-menu-error');
            $button.prop('disabled', true).text(@json(i18n('homeMenu.regenerating')));
            $error.addClass('hidden').text('');

            gbFetch('/api/menu/regenerate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({}),
            })
                .then(async response => ({ ok: response.ok, body: await response.json() }))
                .then(({ ok, body }) => {
                    if (! ok) throw new Error(body?.error?.message || @json(i18n('homeMenu.regenerateFailed')));
                    location.reload();
                })
                .catch(error => {
                    $error.removeClass('hidden').text(error.message || @json(i18n('homeMenu.regenerateFailed')));
                    $button.prop('disabled', false).text(@json(i18n('homeMenu.regenerate')));
                });
        });

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
