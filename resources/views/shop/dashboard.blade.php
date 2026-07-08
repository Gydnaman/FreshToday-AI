@extends('layouts.app')

@section('title', 'My Dashboard')

@section('content')
<div class="container mx-auto px-4 py-12">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-extrabold text-gray-900 mb-8">Welcome Back, Eco Saver! 👋</h1>

        <!-- AI Menu Section (Core Feature) -->
        <div class="bg-gradient-to-r from-emerald-500 to-green-600 rounded-2xl shadow-xl overflow-hidden mb-8 animate-fade-in-up">
            <div class="p-8">
                <div class="flex items-center justify-between mb-6 border-b border-green-400/50 pb-4">
                    <h2 class="text-2xl font-bold text-white flex items-center">
                        <i data-lucide="sparkles" class="mr-3 w-6 h-6 text-yellow-300"></i>
                        Today's Personalized Menu
                    </h2>
                    <span class="bg-white/20 px-3 py-1 rounded-full text-green-50 text-sm font-medium">Updated just now</span>
                </div>
                
                <div class="bg-white/10 backdrop-blur-md rounded-xl p-6 border border-white/20">
                    <p class="text-lg text-white leading-relaxed font-medium">
                        {{ $aiMenu }}
                    </p>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button class="bg-white text-green-700 px-6 py-2 rounded-lg font-bold hover:bg-gray-50 transition shadow-sm">
                        Add Ingredients to Cart
                    </button>
                </div>
            </div>
        </div>

        <!-- Dashboard Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-gray-500 text-sm font-medium">Carbon Saved</h3>
                    <div class="text-2xl font-extrabold text-gray-900 mt-1">12.5 <span class="text-base text-gray-500">kg CO2e</span></div>
                </div>
                <div class="bg-green-100 p-3 rounded-full text-green-600">
                    <i data-lucide="tree-deciduous" class="w-6 h-6"></i>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-gray-500 text-sm font-medium">Orders Placed</h3>
                    <div class="text-2xl font-extrabold text-gray-900 mt-1">4</div>
                </div>
                <div class="bg-blue-100 p-3 rounded-full text-blue-600">
                    <i data-lucide="package" class="w-6 h-6"></i>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-gray-500 text-sm font-medium">Active Subscription</h3>
                    <div class="text-2xl font-extrabold text-gray-900 mt-1">Individual</div>
                </div>
                <div class="bg-purple-100 p-3 rounded-full text-purple-600">
                    <i data-lucide="repeat" class="w-6 h-6"></i>
                </div>
            </div>
        </div>
        
    </div>
</div>
@endsection
