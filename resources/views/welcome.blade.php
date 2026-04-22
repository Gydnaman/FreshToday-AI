@extends('layouts.app')

@section('title', 'Welcome')

@section('content')
<div class="bg-gradient-to-br from-green-50 to-emerald-100 pb-16 pt-8">
    <div class="container mx-auto px-4">
        <div class="text-center max-w-3xl mx-auto mb-20 animate-fade-in-up">
            <div class="inline-block bg-green-100 text-green-800 px-4 py-1 rounded-full mb-6 font-medium border border-green-200">
                🌱 Sustainable Food Subscriptions for Hong Kong
            </div>
            <h1 class="text-5xl md:text-6xl font-extrabold text-gray-900 mb-6 tracking-tight">
                Farm-Fresh Produce. <span class="text-green-600 block mt-2">Zero Food Miles.</span>
            </h1>
            <p class="text-xl text-gray-600 mb-8 leading-relaxed">
                Get seasonal, organic produce delivered from local Hong Kong farms. 
                Reduce carbon footprint while enjoying the freshest ingredients.
            </p>
            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <a href="{{ url('/catalog') }}" class="bg-green-600 text-white px-8 py-4 rounded-xl text-lg font-medium hover:bg-green-700 transition-colors shadow-lg hover:shadow-green-500/30">
                    Explore Products
                </a>
                <a href="{{ url('/subscriptions') }}" class="bg-white text-green-700 px-8 py-4 rounded-xl text-lg font-medium border-2 border-green-600 hover:bg-green-50 transition-colors">
                    Subscription Plans
                </a>
            </div>
        </div>
        
        <!-- Features -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-20">
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-8 shadow-sm text-center transform hover:-translate-y-1 transition duration-300 border border-white">
                <div class="bg-green-100 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-6 rotate-3">
                    <i data-lucide="truck" class="h-8 w-8 text-green-600"></i>
                </div>
                <h3 class="text-xl font-bold mb-3 text-gray-900">Carbon-Neutral Delivery</h3>
                <p class="text-gray-600 leading-relaxed">All deliveries offset through verified carbon credits</p>
            </div>
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-8 shadow-sm text-center transform hover:-translate-y-1 transition duration-300 border border-white">
                <div class="bg-green-100 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-6 -rotate-3">
                    <i data-lucide="leaf" class="h-8 w-8 text-green-600"></i>
                </div>
                <h3 class="text-xl font-bold mb-3 text-gray-900">100% Local Farms</h3>
                <p class="text-gray-600 leading-relaxed">Supporting 15+ small farms across Hong Kong</p>
            </div>
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-8 shadow-sm text-center transform hover:-translate-y-1 transition duration-300 border border-white">
                <div class="bg-green-100 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-6 rotate-3">
                    <i data-lucide="package-check" class="h-8 w-8 text-green-600"></i>
                </div>
                <h3 class="text-xl font-bold mb-3 text-gray-900">Plastic-Free Packaging</h3>
                <p class="text-gray-600 leading-relaxed">Compostable materials for zero waste</p>
            </div>
        </div>
        
        <!-- Join CTA -->
        <div class="bg-white rounded-3xl shadow-xl overflow-hidden mb-10 border border-gray-100">
            <div class="grid grid-cols-1 lg:grid-cols-2">
                <div class="p-12 bg-gradient-to-br from-green-600 to-emerald-700 text-white flex flex-col justify-center">
                    <h2 class="text-4xl font-bold mb-4 tracking-tight">Join Our Green Community</h2>
                    <p class="text-green-50 mb-8 text-lg leading-relaxed">
                        Be part of Hong Kong's sustainable food movement. Every subscription helps reduce food waste and supports local farmers.
                    </p>
                    <div class="space-y-6">
                        <div class="flex items-center bg-white/10 p-3 rounded-lg backdrop-blur-sm">
                            <i data-lucide="users" class="h-6 w-6 mr-4 text-green-200"></i>
                            <span class="text-lg font-medium">500+ households reducing carbon footprint</span>
                        </div>
                        <div class="flex items-center bg-white/10 p-3 rounded-lg backdrop-blur-sm">
                            <i data-lucide="cloud-off" class="h-6 w-6 mr-4 text-green-200"></i>
                            <span class="text-lg font-medium">15,000+ kg of CO2 saved monthly</span>
                        </div>
                    </div>
                </div>
                <div class="p-12 lg:p-16">
                    <h3 class="text-2xl font-bold text-gray-900 mb-8">Start Your Journey Today</h3>
                    <form id="signup-form" class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input type="text" placeholder="John Doe" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" placeholder="john@example.hk" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition shadow-sm">
                        </div>
                        <button type="submit" class="w-full bg-green-600 text-white py-4 px-6 rounded-xl hover:bg-green-700 transition-colors font-bold text-lg mt-4 shadow-lg shadow-green-600/30">
                            Create Free Account
                        </button>
                    </form>
                    <p class="text-center text-gray-500 mt-6 text-sm">
                        By signing up, you agree to our Terms of Service.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in-up {
        animation: fadeInUp 0.8s ease-out forwards;
    }
</style>

<script>
    $(document).ready(function() {
        $('#signup-form').on('submit', function(e) {
            e.preventDefault();
            const btn = $(this).find('button');
            const originalText = btn.text();
            btn.html('<i data-lucide="loader" class="animate-spin inline h-5 w-5 mr-2"></i> Processing...');
            lucide.createIcons();
            
            // Simulate AJAX signup
            setTimeout(function() {
                alert('Welcome to GreenBite! Start adding healthy produce to your cart.');
                window.location.href = "{{ url('/catalog') }}";
            }, 1000);
        });
    });
</script>
@endsection
