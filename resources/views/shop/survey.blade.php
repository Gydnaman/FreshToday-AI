@extends('layouts.app')

@section('title', i18n('survey.title'))

@section('content')
<div class="container mx-auto px-4 py-16">
    <div class="max-w-2xl mx-auto bg-white rounded-3xl shadow-xl overflow-hidden border border-gray-100 animate-fade-in-up">

        <div class="bg-gradient-to-r from-green-500 to-emerald-600 p-8 text-center text-white">
            <h2 class="text-3xl font-extrabold mb-2">{{ i18n('survey.title') }}</h2>
            <p class="text-green-50">{{ i18n('survey.subtitle') }}</p>
        </div>

        <div class="p-8 md:p-12">
            @if ($errors->any())
                <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4 text-sm text-red-700" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif
            <form id="survey-form" action="{{ url('/api/survey') }}" method="POST" class="space-y-8">
                @csrf

                {{-- Q1: Lifestyle --}}
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-1">1. {{ i18n('survey.q1.title') }}</h3>
                    <p class="text-xs text-gray-400 mb-4">{{ i18n('survey.single') }}</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach(['A','B','C','D','E','F'] as $i => $val)
                        <label class="flex items-center p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-green-50 hover:border-green-300 transition group">
                            <input type="radio" name="usage_purpose" value="{{ i18n('survey.q1.options.' . $i . '.label') }}" required class="w-5 h-5 text-green-600 border-gray-300 focus:ring-green-500">
                            <span class="ml-3 text-gray-700 font-medium group-hover:text-green-700">{{ i18n('survey.q1.options.' . $i . '.label') }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- Q2: Household size --}}
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-1">2. {{ i18n('survey.q2.title') }}</h3>
                    <p class="text-xs text-gray-400 mb-4">{{ i18n('survey.single') }}</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        @foreach(['A','B','C'] as $i => $val)
                        <label class="flex items-center p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-green-50 hover:border-green-300 transition group">
                            <input type="radio" name="household_size" value="{{ $i + 1 }}" required class="w-5 h-5 text-green-600 border-gray-300 focus:ring-green-500">
                            <span class="ml-3 text-gray-700 font-medium group-hover:text-green-700">{{ i18n('survey.q2.options.' . $i . '.label') }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- Q3: Goals (multi) --}}
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-1">3. {{ i18n('survey.q3.title') }}</h3>
                    <p class="text-xs text-gray-400 mb-4">{{ i18n('survey.multi') }}</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach(['A','B','C','D'] as $i => $val)
                        <label class="flex items-center p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-green-50 hover:border-green-300 transition group">
                            <input type="checkbox" name="goals[]" value="{{ i18n('survey.q3.options.' . $i . '.label') }}" class="w-5 h-5 text-green-600 border-gray-300 focus:ring-green-500">
                            <span class="ml-3 text-gray-700 font-medium group-hover:text-green-700">{{ i18n('survey.q3.options.' . $i . '.label') }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- Q4: Dietary --}}
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-1">4. {{ i18n('survey.q4.title') }}</h3>
                    <p class="text-xs text-gray-400 mb-4">{{ i18n('survey.single') }}</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        @foreach(['A','B','C'] as $i => $val)
                        <label class="flex items-center p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-green-50 hover:border-green-300 transition group">
                            <input type="radio" name="dietary_habits" value="{{ i18n('survey.q4.options.' . $i . '.label') }}" required class="w-5 h-5 text-green-600 border-gray-300 focus:ring-green-500">
                            <span class="ml-3 text-gray-700 font-medium group-hover:text-green-700">{{ i18n('survey.q4.options.' . $i . '.label') }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- Q5: Cooking style --}}
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-1">5. {{ i18n('survey.q5.title') }}</h3>
                    <p class="text-xs text-gray-400 mb-4">{{ i18n('survey.single') }}</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach(['A','B'] as $i => $val)
                        <label class="flex items-center p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-green-50 hover:border-green-300 transition group">
                            <input type="radio" name="cooking_skill" value="{{ $i === 0 ? 'Beginner' : 'Advanced' }}" required class="w-5 h-5 text-green-600 border-gray-300 focus:ring-green-500">
                            <span class="ml-3 text-gray-700 font-medium group-hover:text-green-700">{{ i18n('survey.q5.options.' . $i . '.label') }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- Q6: Mission alignment --}}
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-1">6. {{ i18n('survey.q6.title') }}</h3>
                    <p class="text-xs text-gray-400 mb-4">{{ i18n('survey.single') }}</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        @foreach(['A','B','C'] as $i => $val)
                        <label class="flex items-center p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-green-50 hover:border-green-300 transition group">
                            <input type="radio" name="mission" value="{{ i18n('survey.q6.options.' . $i . '.label') }}" required class="w-5 h-5 text-green-600 border-gray-300 focus:ring-green-500">
                            <span class="ml-3 text-gray-700 font-medium group-hover:text-green-700">{{ i18n('survey.q6.options.' . $i . '.label') }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-100">
                    <button type="submit" class="w-full bg-green-600 text-white py-4 px-6 rounded-xl hover:bg-green-700 transition-colors font-bold text-lg shadow-lg shadow-green-600/30 flex items-center justify-center">
                        {{ i18n('survey.submit') }} <i data-lucide="sparkles" class="ml-2 w-5 h-5"></i>
                    </button>
                    <p class="text-center text-sm text-gray-500 mt-4">{{ i18n('survey.privacy') }}</p>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $('#survey-form').on('submit', function(e) {
        const btn = $(this).find('button[type="submit"]');
        btn.html('<i data-lucide="loader" class="animate-spin mr-2 w-5 h-5"></i> ' + @json(i18n('survey.generating')));
        lucide.createIcons();
    });
</script>
@endsection
