@extends('layouts.app')

@section('title', 'Subscriptions')

@section('content')
<div class="container mx-auto px-4 py-16 text-center">
    <div class="animate-fade-in-up">
        <h2 class="text-4xl font-extrabold text-gray-900 mb-4">Choose Your Green Plan</h2>
        <p class="text-xl text-gray-600 mb-12 max-w-2xl mx-auto">Get fresh, organic deliveries tailored to your household size and cooking habits.</p>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
            <!-- Plan 1 -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 hover:shadow-xl transition transform hover:-translate-y-2">
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Individual Box</h3>
                <p class="text-gray-500 mb-6">Perfect for 1-2 people</p>
                <div class="text-4xl font-extrabold text-green-600 mb-6">HK$280<span class="text-lg text-gray-400 font-normal">/week</span></div>
                <ul class="text-left space-y-4 mb-8">
                    <li class="flex items-center"><i data-lucide="check" class="w-5 h-5 text-green-500 mr-2"></i> 4-5 Seasonal Veggies</li>
                    <li class="flex items-center"><i data-lucide="check" class="w-5 h-5 text-green-500 mr-2"></i> 2 Types of Local Fruits</li>
                </ul>
                <button class="w-full bg-green-100 text-green-700 py-3 rounded-xl font-bold hover:bg-green-600 hover:text-white transition">Subscribe Now</button>
            </div>
            
            <!-- Plan 2 -->
            <div class="bg-green-600 rounded-2xl shadow-lg border border-green-500 p-8 transform hover:-translate-y-2 transition relative md:-mt-4 md:mb-4">
                <div class="absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-yellow-400 text-yellow-900 px-4 py-1 rounded-full text-sm font-bold shadow-md">MOST POPULAR</div>
                <h3 class="text-2xl font-bold text-white mb-2">Family Box</h3>
                <p class="text-green-100 mb-6">Perfect for 3-4 people</p>
                <div class="text-4xl font-extrabold text-white mb-6">HK$450<span class="text-lg text-green-200 font-normal">/week</span></div>
                <ul class="text-left space-y-4 mb-8 text-green-50">
                    <li class="flex items-center"><i data-lucide="check" class="w-5 h-5 text-white mr-2"></i> 6-8 Seasonal Veggies</li>
                    <li class="flex items-center"><i data-lucide="check" class="w-5 h-5 text-white mr-2"></i> 4 Types of Local Fruits</li>
                    <li class="flex items-center"><i data-lucide="check" class="w-5 h-5 text-white mr-2"></i> Free Range Eggs</li>
                </ul>
                <button class="w-full bg-white text-green-700 py-3 rounded-xl font-bold hover:bg-gray-100 transition shadow">Subscribe Now</button>
            </div>
            
            <!-- Plan 3 -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 hover:shadow-xl transition transform hover:-translate-y-2">
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Custom Box</h3>
                <p class="text-gray-500 mb-6">Tailored to your needs</p>
                <div class="text-4xl font-extrabold text-green-600 mb-6">HK$150+<span class="text-lg text-gray-400 font-normal">/week</span></div>
                <ul class="text-left space-y-4 mb-8">
                    <li class="flex items-center"><i data-lucide="check" class="w-5 h-5 text-green-500 mr-2"></i> Full Catalog Access</li>
                    <li class="flex items-center"><i data-lucide="check" class="w-5 h-5 text-green-500 mr-2"></i> Flexible Delivery Days</li>
                </ul>
                <button class="w-full bg-green-100 text-green-700 py-3 rounded-xl font-bold hover:bg-green-600 hover:text-white transition">Build Your Box</button>
            </div>
        </div>
    </div>
</div>
@endsection
