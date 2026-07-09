@extends('layouts.app')

@section('title', i18n('subscriptions.title'))

@section('content')
<div class="container mx-auto px-4 py-16 text-center">
    <div class="animate-fade-in-up">
        <h2 class="text-4xl font-extrabold text-gray-900 mb-4">{{ i18n('subscriptions.title') }}</h2>
        <p class="text-xl text-gray-600 mb-12 max-w-2xl mx-auto">{{ i18n('subscriptions.subtitle') }}</p>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
            <!-- Plan 1 -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 hover:shadow-xl transition transform hover:-translate-y-2">
                <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ i18n('subscriptions.individualName') }}</h3>
                <p class="text-gray-500 mb-6">{{ i18n('subscriptions.individualDesc') }}</p>
                <div class="text-4xl font-extrabold text-green-600 mb-6">HK$280<span class="text-lg text-gray-400 font-normal">{{ i18n('subscriptions.perWeek') }}</span></div>
                <ul class="text-left space-y-4 mb-8">
                    <li class="flex items-center"><i data-lucide="check" class="w-5 h-5 text-green-500 mr-2"></i> {{ i18n('subscriptions.individualFeature1') }}</li>
                    <li class="flex items-center"><i data-lucide="check" class="w-5 h-5 text-green-500 mr-2"></i> {{ i18n('subscriptions.individualFeature2') }}</li>
                </ul>
                <button class="w-full bg-green-100 text-green-700 py-3 rounded-xl font-bold hover:bg-green-600 hover:text-white transition">{{ i18n('subscriptions.individualCta') }}</button>
            </div>
            
            <!-- Plan 2 -->
            <div class="bg-green-600 rounded-2xl shadow-lg border border-green-500 p-8 transform hover:-translate-y-2 transition relative md:-mt-4 md:mb-4">
                <div class="absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-yellow-400 text-yellow-900 px-4 py-1 rounded-full text-sm font-bold shadow-md">{{ i18n('subscriptions.familyBadge') }}</div>
                <h3 class="text-2xl font-bold text-white mb-2">{{ i18n('subscriptions.familyName') }}</h3>
                <p class="text-green-100 mb-6">{{ i18n('subscriptions.familyDesc') }}</p>
                <div class="text-4xl font-extrabold text-white mb-6">HK$450<span class="text-lg text-green-200 font-normal">{{ i18n('subscriptions.perWeek') }}</span></div>
                <ul class="text-left space-y-4 mb-8 text-green-50">
                    <li class="flex items-center"><i data-lucide="check" class="w-5 h-5 text-white mr-2"></i> {{ i18n('subscriptions.familyFeature1') }}</li>
                    <li class="flex items-center"><i data-lucide="check" class="w-5 h-5 text-white mr-2"></i> {{ i18n('subscriptions.familyFeature2') }}</li>
                    <li class="flex items-center"><i data-lucide="check" class="w-5 h-5 text-white mr-2"></i> {{ i18n('subscriptions.familyFeature3') }}</li>
                </ul>
                <button class="w-full bg-white text-green-700 py-3 rounded-xl font-bold hover:bg-gray-100 transition shadow">{{ i18n('subscriptions.familyCta') }}</button>
            </div>
            
            <!-- Plan 3 -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 hover:shadow-xl transition transform hover:-translate-y-2">
                <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ i18n('subscriptions.customName') }}</h3>
                <p class="text-gray-500 mb-6">{{ i18n('subscriptions.customDesc') }}</p>
                <div class="text-4xl font-extrabold text-green-600 mb-6">HK$150+<span class="text-lg text-gray-400 font-normal">{{ i18n('subscriptions.perWeek') }}</span></div>
                <ul class="text-left space-y-4 mb-8">
                    <li class="flex items-center"><i data-lucide="check" class="w-5 h-5 text-green-500 mr-2"></i> {{ i18n('subscriptions.customFeature1') }}</li>
                    <li class="flex items-center"><i data-lucide="check" class="w-5 h-5 text-green-500 mr-2"></i> {{ i18n('subscriptions.customFeature2') }}</li>
                </ul>
                <button class="w-full bg-green-100 text-green-700 py-3 rounded-xl font-bold hover:bg-green-600 hover:text-white transition">{{ i18n('subscriptions.customCta') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection
